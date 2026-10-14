<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleWorkspaceSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_borrower_workspace_pages_render(): void
    {
        $this->assertPagesRender(UserRole::Borrower, [
            '/dashboard',
            '/profile',
            '/notifications',
            '/inventory',
            '/calendar',
            '/requests',
            '/requests/create',
            '/custody',
            '/accountability',
        ]);
    }

    public function test_spmu_workspace_pages_render(): void
    {
        $this->assertPagesRender(UserRole::Spmu, [
            '/dashboard',
            '/profile',
            '/notifications',
            '/inventory',
            '/inventory/create',
            '/calendar',
            '/requests',
            '/approvals',
            '/accountability',
            '/reports',
            '/administration',
            '/administration/settings',
        ]);

        $this->actingAs($this->workspaceUser(UserRole::Spmu))
            ->get('/custody')
            ->assertRedirect(route('requests.index'));
    }

    public function test_ictu_workspace_pages_render(): void
    {
        $this->assertPagesRender(UserRole::Ictu, [
            '/dashboard',
            '/profile',
            '/notifications',
            '/reports/audit',
            '/reports/notifications',
            '/administration',
            '/administration/settings',
            '/administration/users',
            '/administration/users/create',
            '/administration/delegations',
        ]);
    }

    public function test_each_active_workspace_has_a_focused_role_specific_menu(): void
    {
        $expectations = [
            UserRole::Borrower->value => [
                'see' => ['Available Items', 'My Requests', 'My Borrowings', 'My Obligations'],
                'hide' => ['For Approval', 'User Accounts', 'Laundry'],
            ],
            /*
             * The SPMU Head reviews the Operational Audit Trail. ICTU's own
             * menu entries (User Accounts, the technical Audit Trail,
             * Delivery Records) are not offered.
             */
            UserRole::Spmu->value => [
                'see' => ['For Approval', 'Request Records', 'Inventory', 'Analytics', 'Reports', 'Configuration', 'User Accounts'],
                'hide' => ['Release & Return', 'Release', 'Return', 'Technical Audit Trail', 'Delivery Records', 'Laundry'],
            ],
            UserRole::Ictu->value => [
                'see' => ['User Accounts', 'System Configuration', 'Technical Audit Trail', 'Delivery Records'],
                'hide' => ['Borrowing Calendar', 'For Approval', 'Laundry'],
            ],
        ];

        /* ICTU-only destinations, by the address the menu would link to. */
        $ictuOnlyLinks = ['/administration/users', '/reports/notifications'];

        foreach ($expectations as $roleCode => $labels) {
            $role = UserRole::from($roleCode);
            $user = $this->workspaceUser($role);

            $response = $this->withSession(['active_workspace' => $role->value])
                ->actingAs($user)
                ->get('/dashboard')
                ->assertOk();

            $this->assertNavigationLabels($response, $labels['see'], $labels['hide']);
        }

        $actionOfficer = User::query()
            ->where('access_classification', AccessClassification::SpmuOfficer->value)
            ->firstOrFail();

        $response = $this->withSession(['active_workspace' => UserRole::Spmu->value])
            ->actingAs($actionOfficer)
            ->get('/dashboard')
            ->assertOk();
        $this->assertNavigationLabels($response,
            ['For Verification', 'Borrower Registrations', 'Release', 'Return', 'Laundry'],
            ['User Accounts', 'For Approval', 'Release & Return']
        );
    }

    private function assertNavigationLabels(\Illuminate\Testing\TestResponse $response, array $see, array $hide): void
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $labels = [];
        foreach ($xpath->query('//nav[@aria-label="Primary navigation"]/a/@aria-label') as $label) {
            $labels[] = $label->nodeValue;
        }
        $this->assertNotEmpty($labels, 'The actual primary navigation must be rendered.');
        foreach ($see as $label) {
            $this->assertContains($label, $labels);
        }
        foreach ($hide as $label) {
            $this->assertNotContains($label, $labels);
        }
    }

    /** @param array<int, string> $paths */
    private function assertPagesRender(UserRole $role, array $paths): void
    {
        $user = $this->workspaceUser($role);

        foreach ($paths as $path) {
            $this->withSession(['active_workspace' => $role->value])
                ->actingAs($user)
                ->get($path)
                ->assertOk();
        }
    }

    private function workspaceUser(UserRole $role): User
    {
        return User::query()
            ->whereHas(
                'roles',
                fn ($query) => $query
                    ->where('role_code', $role->value)
                    ->whereNull('user_roles.revoked_at'),
            )
            ->when(
                $role === UserRole::Spmu,
                fn ($query) => $query->where(
                    'access_classification',
                    AccessClassification::SpmuHead->value,
                ),
            )
            ->firstOrFail();
    }
}
