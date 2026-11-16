<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Models\AuditEvent;
use App\Models\BorrowingRequest;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditFilterControlsTest extends TestCase
{
    use RefreshDatabase;

    private User $head;
    private User $officer;
    private User $technical;
    private array $categoryIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::create(2026, 11, 15, 12, 0, 0, 'Asia/Manila'));
        $this->head = User::factory()->create(['full_name' => 'SPMU Head Demo', 'access_classification' => AccessClassification::SpmuHead]);
        $this->officer = User::factory()->create(['full_name' => 'SPMU Action Officer Demo', 'access_classification' => AccessClassification::SpmuOfficer]);
        $this->technical = User::factory()->create(['full_name' => 'Excluded technical actor', 'access_classification' => AccessClassification::IctuMaintainer]);
        $operational = SystemSetting::create(['setting_key' => 'daily_operating_state', 'value_json' => 'OPEN']);
        $technical = SystemSetting::create(['setting_key' => 'max_upload_mb', 'value_json' => 10]);
        foreach ([
            ['BORROWING', 'REQUEST_SUBMITTED', BorrowingRequest::class, null],
            ['APPROVAL', 'REQUEST_APPROVED', BorrowingRequest::class, null],
            ['RELEASE_RETURN', 'ITEMS_RELEASED', 'CustodyTransaction', null],
            ['LAUNDRY_GATEPASS', 'LAUNDRY_RETURN_RECORDED_FROM_FORM', 'LaundryJob', null],
            ['ACCOUNTABILITY', 'PROPERTY_ACCOUNTABILITY_CASE_RESOLVED', 'Incident', null],
            ['INVENTORY', 'INVENTORY_ITEM_UPDATED', 'InventoryItem', null],
            ['CORRECTIONS_REOPENINGS', 'REQUEST_RETURNED_FOR_REVISION', BorrowingRequest::class, null],
            ['ACCOUNT', 'USER_ACCOUNT_UPDATED', User::class, $this->officer->id],
            ['OPERATIONAL_SETTINGS', 'SYSTEM_SETTING_CHANGED', SystemSetting::class, $operational->id],
            ['SETTINGS', 'SYSTEM_SETTING_CHANGED', SystemSetting::class, $technical->id],
            ['OTHER_OPERATIONAL', 'REPORT_EXPORTED', 'Report', null],
        ] as [$category, $action, $type, $id]) {
            $this->categoryIds[$category] = $this->event($action, $this->head, $type, $id)->id;
        }
        $this->event('LOGIN_SUCCESS', $this->technical, User::class, $this->technical->id);
        $this->event('SECURITY_BACKUP_CREATED', $this->technical);
        $this->event('UNCLASSIFIED_TECHNICAL_EVENT', $this->technical);
        $this->actingAs($this->head)->withSession(['active_workspace' => 'SPMU']);
    }

    private function event(string $action, ?User $actor, string $type = 'AuditFixture', ?int $id = null, string $reason = 'Recorded audit fixture', ?string $time = null): AuditEvent
    {
        return AuditEvent::create([
            'actor_user_id' => $actor?->id, 'actor_name' => $actor?->full_name,
            'actor_role' => $actor?->access_classification?->value, 'action_code' => $action,
            'record_type' => $type, 'record_id' => $id, 'reason' => $reason, 'occurred_at' => $time ?? now(),
            'correlation_id' => (string) \Illuminate\Support\Str::uuid(),
        ]);
    }

    private function page(array $query = [])
    {
        return $this->get(route('reports.audit', $query + ['per_page' => 28]))->assertOk();
    }

    private function ids(array $query = []): array
    {
        return $this->page($query)->viewData('events')->getCollection()->pluck('id')->sort()->values()->all();
    }

    public function test_selector_has_nine_choices_and_grouped_filters_are_exact_unions_of_original_categories(): void
    {
        $page = $this->page();
        $this->assertCount(8, $page->viewData('categoryOptions'));
        $document = new \DOMDocument();
        @$document->loadHTML($page->getContent());
        $this->assertSame(9, (new \DOMXPath($document))->query('//select[@id="audit-category-filter"]/option')->length);
        $this->assertCount(11, $this->ids());
        foreach ([
            'BORROWING_APPROVALS' => ['BORROWING', 'APPROVAL'],
            'ADMINISTRATION_SETTINGS' => ['ACCOUNT', 'OPERATIONAL_SETTINGS', 'SETTINGS'],
        ] as $group => $members) {
            $expected = collect($members)->map(fn ($key) => $this->categoryIds[$key])->sort()->values()->all();
            $this->assertSame($expected, $this->ids(['category' => $group]));
            $this->assertSame(collect($members)->sort()->values()->all(), $this->page(['category' => $group])->viewData('events')
                ->getCollection()->pluck('audit_category')->sort()->values()->all());
        }
        $this->assertDatabaseCount('audit_events', 14);
        $this->assertStringContainsString('Other Activities', $page->getContent());
        $this->assertStringNotContainsString('Other Operational', $page->getContent());
    }

    public function test_every_category_uses_the_selector_wording_in_record_types_and_details(): void
    {
        $originalEvents = AuditEvent::query()->orderBy('id')->get()->toArray();
        $all = $this->page();
        $document = new \DOMDocument();
        @$document->loadHTML($all->getContent());
        $allRows = new \DOMXPath($document);

        foreach ([
            'BORROWING_APPROVALS' => ['Borrowing & Approvals', ['BORROWING', 'APPROVAL']],
            'RELEASE_RETURN' => ['Release / Return', ['RELEASE_RETURN']],
            'LAUNDRY_GATEPASS' => ['Laundry / Gate Pass', ['LAUNDRY_GATEPASS']],
            'ACCOUNTABILITY' => ['Accountability', ['ACCOUNTABILITY']],
            'INVENTORY' => ['Inventory', ['INVENTORY']],
            'CORRECTIONS_REOPENINGS' => ['Corrections / Reopenings', ['CORRECTIONS_REOPENINGS']],
            'ADMINISTRATION_SETTINGS' => ['Administration & Settings', ['ACCOUNT', 'OPERATIONAL_SETTINGS', 'SETTINGS']],
            'OTHER_OPERATIONAL' => ['Other Activities', ['OTHER_OPERATIONAL']],
        ] as $filter => [$label, $members]) {
            $this->assertSame($label, $all->viewData('categoryOptions')[$filter]);
            $filtered = $this->page(['category' => $filter]);
            $this->assertSame(
                collect($members)->map(fn ($category) => $this->categoryIds[$category])->sort()->values()->all(),
                $filtered->viewData('events')->getCollection()->pluck('id')->sort()->values()->all()
            );

            $filteredDocument = new \DOMDocument();
            @$filteredDocument->loadHTML($filtered->getContent());
            $filteredRows = new \DOMXPath($filteredDocument);
            $detailLabels = $filteredRows->query('//tr[@data-audit-detail-row]//div[dt[normalize-space(.)="Category"]]/dd');
            $this->assertCount(count($members), $detailLabels);
            foreach ($detailLabels as $detailLabel) {
                $this->assertSame($label, trim($detailLabel->textContent));
            }

            foreach ($members as $category) {
                $badgeQuery = '//tr[@data-audit-row]//span[@data-type="'.$category.'"]';
                foreach ([$allRows, $filteredRows] as $rows) {
                    $badges = $rows->query($badgeQuery);
                    $this->assertCount(1, $badges);
                    $this->assertSame($label, trim($badges->item(0)->textContent));
                }
            }
        }

        $this->assertSame($originalEvents, AuditEvent::query()->orderBy('id')->get()->toArray());
    }

    public function test_historical_category_links_keep_their_exact_scope_and_selected_option(): void
    {
        foreach (['BORROWING', 'APPROVAL', 'ACCOUNT', 'OPERATIONAL_SETTINGS', 'SETTINGS'] as $category) {
            $page = $this->page(['category' => $category]);
            $this->assertSame([$this->categoryIds[$category]], $this->ids(['category' => $category]));
            $this->assertSame($category, $page->viewData('filters')['category']);
            $this->assertStringContainsString('(historical filter)', $page->viewData('categoryOptions')[$category]);
            $this->assertSame($category, $page->viewData('events')->first()->audit_category);
            $this->assertStringStartsWith(
                $page->viewData('eventCategoryLabels')[$category].' — ',
                $page->viewData('categoryOptions')[$category]
            );
        }
    }

    public function test_actor_search_date_record_scope_and_page_size_compose_with_the_grouped_category(): void
    {
        foreach (range(1, 15) as $index) $this->event('REQUEST_APPROVED', $this->officer, BorrowingRequest::class, 123, 'needle grouped record '.$index);
        $this->event('REQUEST_SUBMITTED', $this->head, BorrowingRequest::class, 123, 'needle excluded actor');
        $this->event('REQUEST_APPROVED', $this->officer, BorrowingRequest::class, 123, 'needle excluded date', '2026-11-01 08:00:00');
        $this->event('LOGIN_SUCCESS', $this->officer, User::class, $this->officer->id, 'needle excluded audience');
        $page = $this->page(['category' => 'BORROWING_APPROVALS', 'actor' => $this->officer->id,
            'q' => 'needle', 'date' => 'TODAY', 'record_type' => 'BorrowingRequest', 'record_id' => 123, 'per_page' => 7, 'page' => 2]);
        $events = $page->viewData('events');
        $this->assertSame(15, $events->total());
        $this->assertCount(7, $events);
        $this->assertSame(2, $events->currentPage());
        $this->assertSame([$this->officer->id], $events->getCollection()->pluck('actor_user_id')->unique()->values()->all());
        $this->assertSame(7, $page->viewData('filters')['per_page']);
    }

    public function test_actor_choices_use_only_the_authorized_audience_and_keep_duplicate_names_distinct(): void
    {
        $one = User::factory()->create(['full_name' => 'Same Recorded Name', 'email' => 'actor.one@cspc.edu.ph']);
        $two = User::factory()->create(['full_name' => 'Same Recorded Name', 'email' => 'actor.two@cspc.edu.ph']);
        $unused = User::factory()->create(['full_name' => 'Unrelated account profile']);
        $this->event('REQUEST_SUBMITTED', $one);
        $this->event('REQUEST_SUBMITTED', $two);
        $system = $this->event('REPORT_EXPORTED', null);
        $page = $this->page();
        $choices = $page->viewData('actorChoices');
        $this->assertCount(3, $choices);
        $duplicates = $choices->where('label', 'Same Recorded Name')->values();
        $this->assertSame([(string) $one->id, (string) $two->id], $duplicates->pluck('value')->all());
        $this->assertSame([$one->email, $two->email], $duplicates->pluck('detail')->all());
        $this->assertNotContains((string) $this->technical->id, $choices->pluck('value')->all());
        $this->assertNotContains((string) $unused->id, $choices->pluck('value')->all());
        $this->assertTrue($page->viewData('hasSystemActor'));
        $this->assertSame([$system->id], $this->ids(['actor' => 'SYSTEM']));
        $this->assertSame([(string) $one->id], $this->page(['actor' => $one->id])->viewData('events')->getCollection()
            ->pluck('actor_user_id')->map(fn ($id) => (string) $id)->unique()->values()->all());
    }

    public function test_grouped_and_legacy_filters_do_not_grant_other_roles_audit_access(): void
    {
        foreach ([AccessClassification::BorrowerOnly, AccessClassification::SpmuOfficer, AccessClassification::IctuMaintainer] as $classification) {
            $this->actingAs(User::factory()->create(['access_classification' => $classification]))
                ->get(route('reports.audit', ['category' => 'ADMINISTRATION_SETTINGS']))->assertForbidden();
        }
    }

    public function test_current_actor_controls_render_safely_and_keep_an_explicit_historical_selection(): void
    {
        $unsafe = User::factory()->create(['full_name' => '<script>alert("actor")</script>']);
        $this->event('REQUEST_SUBMITTED', $unsafe);
        $page = $this->page(['actor' => $unsafe->id]);
        $page->assertSee('type="hidden" id="audit-actor-filter"', false)
            ->assertSee('role="combobox"', false)->assertSee('data-audit-actor-options', false)
            ->assertDontSee('<select id="audit-actor-filter"', false)
            ->assertDontSee('<script>alert("actor")</script>', false);
        $this->page(['actor' => 999999])->assertSee('Actor #999999 (historical filter)');
        $this->page(['actor' => 'SYSTEM'])->assertSee('value="SYSTEM"', false);
    }

    public function test_rendered_fixtures_cover_long_actor_lists_system_and_legacy_filters(): void
    {
        foreach (range(1, 12) as $index) {
            $actor = User::factory()->create(['full_name' => 'Search Actor '.str_pad((string) $index, 2, '0', STR_PAD_LEFT)]);
            $this->event('REQUEST_SUBMITTED', $actor);
        }
        $one = User::factory()->create(['full_name' => 'Same Recorded Name', 'email' => 'one@cspc.edu.ph']);
        $two = User::factory()->create(['full_name' => 'Same Recorded Name', 'email' => 'two@cspc.edu.ph']);
        $this->event('REQUEST_SUBMITTED', $one);
        $this->event('REQUEST_SUBMITTED', $two);
        $this->event('REPORT_EXPORTED', null);
        foreach ([
            'audit.html' => [],
            'audit-selected.html' => ['actor' => $this->head->id, 'category' => 'ADMINISTRATION_SETTINGS', 'q' => 'Recorded', 'date' => 'TODAY'],
            'audit-system.html' => ['actor' => 'SYSTEM'],
            'audit-legacy.html' => ['category' => 'OPERATIONAL_SETTINGS'],
        ] as $file => $query) {
            $page = $this->page($query);
            if ($directory = getenv('AUDIT_FILTER_HTML_DIR')) file_put_contents($directory.'/'.$file, $page->getContent());
        }
    }
}
