<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Enums\AccountStatus;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Services\UserRoleAssignmentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class IctuInstitutionalAccountVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.seed_demo_users' => true,
            'services.institutional_email.allowed_domains' => 'cspc.edu.ph,spmu.test',
        ]);

        $this->seed(DatabaseSeeder::class);
    }

    private function ictu(): User
    {
        $actor = User::factory()->create([
            'email' => 'ictu.integration@cspc.edu.ph',
            'access_classification' => AccessClassification::IctuMaintainer,
            'account_status' => AccountStatus::Active,
            'organizational_unit_id' => OrganizationalUnit::where('unit_code', 'ICTU')
                ->firstOrFail()
                ->id,
        ]);
        app(UserRoleAssignmentService::class)->synchronize($actor, AccessClassification::IctuMaintainer);

        return $actor;
    }

    private function formPayload(array $overrides = []): array
    {
        $ccs = OrganizationalUnit::where('unit_code', 'CCS')->firstOrFail();

        return array_merge([
            'division_code' => $ccs->divisionCode(),
            'organizational_unit_id' => $ccs->id,
            'employee_no' => 'ICTU-REG-999',
            'full_name' => 'New Institutional Borrower',
            'designation' => 'Administrative Aide',
            'employment_type' => 'STAFF',
            'employment_status' => 'FULL_TIME',
            'email' => 'new.borrower@cspc.edu.ph',
            'mobile_no' => '09171234567',
            'access_classification' => 'BORROWER_ONLY',
            'account_status' => 'ACTIVE',
            'password' => 'Complex!Pass2026',
            'password_confirmation' => 'Complex!Pass2026',
        ], $overrides);
    }

    private function storeOtp(
        User $user,
        string $code = '123456',
        $expiresAt = null,
        int $attempts = 0,
    ): void {
        DB::table('email_verification_otps')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'email' => strtolower($user->email),
                'code_hash' => Hash::make($code),
                'expires_at' => $expiresAt ?? now()->addMinutes(10),
                'attempts' => $attempts,
                'last_sent_at' => now()->subMinutes(2),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function test_ictu_registration_creates_pending_account_and_otp(): void
    {
        Mail::fake();

        $this->actingAs($this->ictu())
            ->post(
                route('administration.users.store'),
                $this->formPayload()
            )
            ->assertRedirect(route('administration.users.index'));

        $user = User::where(
            'email',
            'new.borrower@cspc.edu.ph'
        )->firstOrFail();

        $this->assertNull($user->email_verified_at);

        $this->assertDatabaseHas('email_verification_otps', [
            'user_id' => $user->id,
            'email' => 'new.borrower@cspc.edu.ph',
            'attempts' => 0,
        ]);
    }

    public function test_pending_user_can_verify_account_with_valid_otp(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'otp.verify@cspc.edu.ph',
        ]);

        $this->storeOtp($user, '123456');

        $this->post(
            route('account.verification.verify'),
            [
                'email' => $user->email,
                'otp' => '123456',
            ]
        )
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $this->assertNotNull(
            $user->fresh()->email_verified_at
        );

        $this->assertDatabaseMissing(
            'email_verification_otps',
            ['user_id' => $user->id]
        );

        $this->assertDatabaseHas('audit_events', [
            'action_code' => 'USER_EMAIL_VERIFIED',
            'record_type' => User::class,
            'record_id' => $user->id,
        ]);
    }

    public function test_invalid_otp_does_not_verify_and_counts_attempt(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'otp.invalid@cspc.edu.ph',
        ]);

        $this->storeOtp($user, '123456');

        $this->post(
            route('account.verification.verify'),
            [
                'email' => $user->email,
                'otp' => '654321',
            ]
        )
            ->assertSessionHasErrors('otp');

        $this->assertNull(
            $user->fresh()->email_verified_at
        );

        $this->assertDatabaseHas('email_verification_otps', [
            'user_id' => $user->id,
            'attempts' => 1,
        ]);
    }

    public function test_expired_otp_is_rejected(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'otp.expired@cspc.edu.ph',
        ]);

        $this->storeOtp(
            $user,
            '123456',
            now()->subMinute()
        );

        $this->post(
            route('account.verification.verify'),
            [
                'email' => $user->email,
                'otp' => '123456',
            ]
        )
            ->assertSessionHasErrors('otp');

        $this->assertNull(
            $user->fresh()->email_verified_at
        );

        $this->assertDatabaseMissing(
            'email_verification_otps',
            ['user_id' => $user->id]
        );
    }

    public function test_public_resend_creates_new_otp_for_pending_account(): void
    {
        Mail::fake();

        $user = User::factory()->unverified()->create([
            'email' => 'otp.resend@cspc.edu.ph',
        ]);

        $this->post(
            route('account.verification.resend'),
            ['email' => $user->email]
        )
            ->assertRedirect(
                route(
                    'account.verification.show',
                    ['email' => $user->email]
                )
            )
            ->assertSessionHas('status');

        $this->assertDatabaseHas('email_verification_otps', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        $this->post(
            route('account.verification.resend'),
            ['email' => $user->email]
        )
            ->assertSessionHasErrors('otp');
    }

    public function test_pending_account_is_rejected_for_login(): void
    {
        $pending = User::factory()->unverified()->create([
            'email' => 'not.verified@cspc.edu.ph',
            'password' => 'Complex!Pass2026',
        ]);

        $this->post(
            route('login.store'),
            [
                'email' => $pending->email,
                'password' => 'Complex!Pass2026',
            ]
        )
            ->assertRedirect(route('account.verification.show', ['email' => $pending->email]));

        $this->assertGuest();
    }

    public function test_registration_refuses_unapproved_email_domain(): void
    {
        Mail::fake();

        $this->actingAs($this->ictu())
            ->post(
                route('administration.users.store'),
                $this->formPayload([
                    'email' => 'wrong@gmail.com',
                ])
            )
            ->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('users', [
            'email' => 'wrong@gmail.com',
        ]);
    }

    public function test_ictu_can_resend_otp_but_borrower_cannot_use_ictu_route(): void
    {
        Mail::fake();

        $pending = User::factory()->unverified()->create([
            'email' => 'ictu.resend@cspc.edu.ph',
        ]);

        $this->actingAs($this->ictu())
            ->post(
                route(
                    'administration.users.email-verification.resend',
                    $pending
                )
            )
            ->assertRedirect(
                route('administration.users.edit', $pending)
            );

        $this->assertDatabaseHas('email_verification_otps', [
            'user_id' => $pending->id,
        ]);

        $borrower = User::factory()->create([
            'email' => 'ordinary.borrower@cspc.edu.ph',
        ]);

        $this->actingAs($borrower)
            ->post(
                route(
                    'administration.users.email-verification.resend',
                    $pending
                )
            )
            ->assertForbidden();
    }
}
