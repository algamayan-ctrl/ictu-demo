<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Enums\UserRole;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\NotificationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * ICTU technical turnover: ICTU is the Technical System Custodian with no
 * application account. System Configuration and Delivery Records leave the
 * SPMU Admin menu but stay in the code, guarded on the server.
 */
class IctuTechnicalTurnoverTest extends TestCase
{
    use RefreshDatabase;

    private User $borrower;

    private User $officer;

    private User $head;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertDisposableTestDatabase();
        $this->assertSame('array', config('mail.default'));

        $this->seed(DatabaseSeeder::class);

        $this->borrower = $this->classificationUser(AccessClassification::BorrowerOnly);
        $this->officer = $this->classificationUser(AccessClassification::SpmuOfficer);
        $this->head = $this->classificationUser(AccessClassification::SpmuHead);
    }

    public function test_admin_sidebar_keeps_configuration_and_user_accounts_without_the_technical_entries(): void
    {
        $page = $this->as($this->head)->get(route('dashboard'))->assertOk();
        $nav = $this->primaryNavigation($page->getContent());

        $this->assertArrayHasKey('Configuration', $nav);
        $this->assertArrayHasKey('User Accounts', $nav);
        $this->assertSame(route('policies.index'), $nav['Configuration']);
        $this->assertSame(route('administration.users.index'), $nav['User Accounts']);

        $this->assertArrayNotHasKey('System Configuration', $nav);
        $this->assertArrayNotHasKey('Delivery Records', $nav);
        $this->assertNotContains(route('reports.notifications'), $nav);
        $this->assertNotContains(route('administration.settings.index', ['scope' => 'system']), $nav);

        $this->as($this->head)->get(route('policies.index'))->assertOk();
        $this->as($this->head)->get(route('administration.users.index'))->assertOk();
        /* Configuration's Late Return Fee card still opens the operational settings screen. */
        $this->as($this->head)->get(route('administration.settings.index', ['section' => 'late-return-fee']))->assertOk();
    }

    public function test_technical_routes_refuse_the_action_officer_and_borrowers_by_direct_url(): void
    {
        $setting = SystemSetting::query()->where('setting_key', 'max_upload_mb')->firstOrFail();
        $before = $setting->value_json;
        $delivery = $this->failedEmailDelivery();

        foreach ([$this->officer, $this->borrower] as $user) {
            $this->as($user)->get(route('administration.index'))->assertForbidden();
            $this->as($user)->get(route('administration.settings.index', ['scope' => 'system']))->assertForbidden();
            $this->as($user)->get(route('administration.settings.index'))->assertForbidden();
            $this->as($user)->put(route('administration.settings.update', $setting), ['value' => 64])->assertForbidden();
            $this->as($user)->get(route('reports.notifications'))->assertForbidden();
            $this->as($user)->post(route('reports.notifications.resolve', $delivery), ['remarks' => 'x'])->assertForbidden();
            $this->as($user)->post(route('reports.notifications.resolveBulk'), ['delivery_ids' => [$delivery->id]])->assertForbidden();
        }

        $this->assertSame($before, $setting->fresh()->value_json);
        $this->assertSame('FAILED', $delivery->fresh()->delivery_status);
    }

    public function test_retired_ictu_maintainer_cannot_sign_in(): void
    {
        $ictu = User::factory()->create([
            'access_classification' => AccessClassification::IctuMaintainer,
            'password' => 'correct-horse-battery-staple',
        ]);
        $ictu->roles()->attach(
            Role::query()->where('role_code', UserRole::Ictu->value)->firstOrFail()->id,
            ['assigned_at' => now()]
        );

        $this->post('/login', [
            'email' => $ictu->email,
            'password' => 'correct-horse-battery-staple',
        ])->assertSessionHasErrors();

        $this->assertGuest();
    }

    public function test_notification_delivery_logging_continues_without_the_menu_entry(): void
    {
        Queue::fake();

        $event = app(NotificationService::class)->send(
            'ACCOUNT_ACCESS_DISABLED',
            collect([$this->borrower]),
            'Turnover fixture message.',
            $this->borrower
        );

        $channels = $event->deliveries()->pluck('channel')->sort()->values()->all();
        $this->assertSame(['EMAIL', 'SYSTEM'], $channels);
        $this->assertSame('SENT', $event->deliveries()->where('channel', 'EMAIL')->value('delivery_status'));
    }

    private function failedEmailDelivery(): \App\Models\NotificationDelivery
    {
        Queue::fake();

        $event = app(NotificationService::class)->send(
            'ACCOUNT_ACCESS_DISABLED',
            collect([$this->borrower]),
            'Turnover fixture message.',
            $this->borrower
        );

        $delivery = $event->deliveries()->where('channel', 'EMAIL')->firstOrFail();
        $delivery->forceFill(['delivery_status' => 'FAILED'])->save();

        return $delivery;
    }

    /** @return array<string, string> label => href of the primary navigation links */
    private function primaryNavigation(string $html): array
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $links = [];

        foreach ((new \DOMXPath($dom))->query('//nav[@aria-label="Primary navigation"]/a') as $link) {
            $links[trim((string) $link->getAttribute('aria-label'))] = (string) $link->getAttribute('href');
        }

        return $links;
    }

    private function as(User $user): static
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        return $this->withSession(['active_workspace' => $user->primaryWorkspace()])->actingAs($user);
    }

    private function classificationUser(AccessClassification $classification): User
    {
        return User::query()
            ->where('access_classification', $classification->value)
            ->firstOrFail();
    }
}
