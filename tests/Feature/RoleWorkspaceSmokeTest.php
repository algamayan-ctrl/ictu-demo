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
            '/custody',
            '/accountability',
            '/reports',
            '/administration',
            '/administration/settings',
        ]);
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
                'see' => ['For Approval', 'Inventory', 'Release & Return', 'Analytics', 'Reports', 'Configuration', 'Operational Audit Trail'],
                'hide' => ['User Accounts', 'Technical Audit Trail', 'System Configuration', 'Delivery Records', 'Laundry'],
            ],
            UserRole::Ictu->value => [
                'see' => ['User Accounts', 'System Configuration', 'Technical Audit Trail', 'Delivery Records'],
                'hide' => ['Borrowing Calendar', 'For Approval', 'Laundry', 'Operational Audit Trail', 'Inventory', 'Analytics'],
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

            /*
             * Read the menu itself. Matching the whole page would also match
             * unrelated text such as stylesheet comments.
             */
            $menu = $this->navigation($response->getContent());

            foreach ($labels['see'] as $label) {
                $this->assertContains($label, $menu['labels'], "{$roleCode} menu offers {$label}.");
            }

            foreach ($labels['hide'] as $label) {
                $this->assertNotContains($label, $menu['labels'], "{$roleCode} menu does not offer {$label}.");
            }

            foreach ($ictuOnlyLinks as $path) {
                $role === UserRole::Ictu
                    ? $this->assertContains($path, $menu['paths'], "ICTU menu links to {$path}.")
                    : $this->assertNotContains($path, $menu['paths'], "{$roleCode} menu does not link to {$path}.");
            }
        }

        $actionOfficer = User::query()
            ->where('access_classification', AccessClassification::SpmuOfficer->value)
            ->firstOrFail();

        $officerMenu = $this->navigation(
            $this->withSession(['active_workspace' => UserRole::Spmu->value])
                ->actingAs($actionOfficer)
                ->get('/dashboard')
                ->assertOk()
                ->getContent()
        );

        $this->assertContains('Laundry', $officerMenu['labels']);
        $this->assertNotContains('User Accounts', $officerMenu['labels']);
        $this->assertNotContains('Operational Audit Trail', $officerMenu['labels']);
        $this->assertNotContains('Technical Audit Trail', $officerMenu['labels']);
        $this->assertNotContains('Analytics', $officerMenu['labels']);
        $this->assertNotContains('/administration/users', $officerMenu['paths']);
        $this->assertNotContains('/reports/audit', $officerMenu['paths']);
        $this->assertNotContains('/reports/notifications', $officerMenu['paths']);
    }

    /**
     * The labels and link paths of the page's navigation (main menu and
     * account menu) - what the signed-in user can actually click.
     *
     * @return array{labels: list<string>, paths: list<string>}
     */
    private function navigation(string $html): array
    {
        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();

        $links = (new \DOMXPath($document))->query(
            '//aside[contains(@class,"app-sidebar")]//a | //aside[contains(@class,"app-sidebar")]//button | //nav[contains(@class,"account-nav")]//a'
        );

        $this->assertGreaterThan(0, $links->length, 'The page has a navigation menu.');

        $labels = [];
        $paths = [];

        foreach ($links as $link) {
            $label = trim((string) preg_replace('/\s+/u', ' ', $link->textContent));
            if ($label !== '') {
                $labels[] = $label;
            }

            $href = $link instanceof \DOMElement ? $link->getAttribute('href') : '';
            if ($href !== '') {
                $paths[] = (string) (parse_url($href, PHP_URL_PATH) ?: '/');
            }
        }

        return ['labels' => array_values(array_unique($labels)), 'paths' => array_values(array_unique($paths))];
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
