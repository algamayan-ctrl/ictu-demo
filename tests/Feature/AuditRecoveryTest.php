<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Http\Controllers\SettingController;
use App\Models\AuditEvent;
use App\Models\InventoryItem;
use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Controlled "Revert This Change" from the Audit Trail: only confirmed
 * low-risk fields, only for the role that owns the record, never over a newer
 * change, never twice, and never by editing an existing audit event.
 */
class AuditRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private User $borrower;

    private User $officer;

    private User $head;

    private User $ictu;

    protected function setUp(): void
    {
        parent::setUp();

        // These tests change data; they must only ever run on the isolated test database.
        /*
         * Tests\TestCase has already refused any database that is not provably
         * disposable, before RefreshDatabase rebuilt it. Asserted again here so
         * the requirement stays visible beside tests that rewrite audit history.
         */
        $this->assertDisposableTestDatabase();

        $this->seed(DatabaseSeeder::class);

        $this->borrower = $this->classificationUser(AccessClassification::BorrowerOnly);
        $this->officer = $this->classificationUser(AccessClassification::SpmuOfficer);
        $this->head = $this->classificationUser(AccessClassification::SpmuHead);
        $this->ictu = $this->classificationUser(AccessClassification::IctuMaintainer);
    }

    public function test_the_head_reverts_an_inventory_detail_change_and_the_original_event_is_untouched(): void
    {
        $item = $this->createInventoryItem('Recovery Chair Blue', 40);

        $this->updateInventory($item, ['unique_description' => 'Recovery Chair Wrong', 'specification' => 'Mistyped']);
        $event = $this->latestEvent('INVENTORY_ITEM_UPDATED', $item->id);
        $originalRow = (array) DB::table('audit_events')->where('id', $event->id)->first();

        $this->as($this->head)
            ->get(route('reports.audit', ['record_type' => 'InventoryItem', 'record_id' => $item->id]))
            ->assertOk()
            ->assertSee('Revert This Change')
            ->assertSee('Will Be Restored To');

        $this->as($this->head)
            ->post(route('reports.audit.revert', ['event' => $event->id]), [
                'revert_reason' => 'The rename was entered on the wrong item.',
                'confirm_revert' => 1,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $item->refresh();
        $this->assertSame('Recovery Chair Blue', $item->unique_description);
        $this->assertNull($item->specification);
        // Fields outside the reverted change are not written.
        $this->assertSame(40, $item->total_quantity);
        $this->assertTrue($item->active);

        $revert = $this->latestEvent('INVENTORY_ITEM_CHANGE_REVERTED', $item->id);
        $this->assertSame('The rename was entered on the wrong item.', $revert->reason);
        $this->assertSame($this->head->id, $revert->actor_user_id);
        $this->assertSame($this->head->full_name, $revert->actor_name);
        $this->assertSame(AccessClassification::SpmuHead->value, $revert->actor_role);
        $this->assertSame('Recovery Chair Wrong', $revert->before_json['unique_description']);
        $this->assertSame('Recovery Chair Blue', $revert->after_json['unique_description']);
        $this->assertSame($event->id, $revert->after_json['reverted_audit_event_no']);
        $this->assertArrayNotHasKey('total_quantity', $revert->after_json);

        // The original audit event is byte-for-byte what it was.
        $this->assertSame($originalRow, (array) DB::table('audit_events')->where('id', $event->id)->first());

        // The trail now shows the outcome instead of offering the action again.
        $this->as($this->head)
            ->get(route('reports.audit', ['record_type' => 'InventoryItem', 'record_id' => $item->id]))
            ->assertOk()
            ->assertSee('This change was reverted on')
            ->assertDontSee('Will Be Restored To');
    }

    public function test_a_change_cannot_be_reverted_twice(): void
    {
        $item = $this->createInventoryItem('Recovery Table', 5);
        $this->updateInventory($item, ['unique_description' => 'Recovery Table Wrong']);
        $event = $this->latestEvent('INVENTORY_ITEM_UPDATED', $item->id);

        $this->revertAs($this->head, $event)->assertSessionHasNoErrors();
        $eventsAfterFirst = AuditEvent::query()->count();

        // An immediate identical resubmission is absorbed by the duplicate-submission guard.
        $this->revertAs($this->head, $event)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($eventsAfterFirst, AuditEvent::query()->count());

        // Once that short window has passed, the recovery rules themselves refuse it.
        $this->travel(30)->seconds();
        $this->revertAs($this->head, $event)->assertRedirect()->assertSessionHasErrors('revert');

        // Even after the same wrong value is entered again, the old event stays spent.
        $this->updateInventory($item->fresh(), ['unique_description' => 'Recovery Table Wrong']);
        $this->travel(30)->seconds();
        $this->revertAs($this->head, $event)->assertSessionHasErrors('revert');

        $this->assertSame('Recovery Table Wrong', $item->fresh()->unique_description);
        $this->assertSame(1, AuditEvent::query()->where('action_code', 'INVENTORY_ITEM_CHANGE_REVERTED')->count());
        $this->assertSame($eventsAfterFirst + 1, AuditEvent::query()->count());
    }

    public function test_a_stale_revert_never_overwrites_a_newer_change(): void
    {
        $item = $this->createInventoryItem('Recovery Tent A', 3);
        $this->updateInventory($item, ['unique_description' => 'Recovery Tent B']);
        $older = $this->latestEvent('INVENTORY_ITEM_UPDATED', $item->id);
        $this->updateInventory($item->fresh(), ['unique_description' => 'Recovery Tent C']);
        $newer = $this->latestEvent('INVENTORY_ITEM_UPDATED', $item->id);
        $eventsBefore = AuditEvent::query()->count();

        $this->revertAs($this->head, $older)->assertSessionHasErrors('revert');

        $this->assertSame('Recovery Tent C', $item->fresh()->unique_description);
        $this->assertSame($eventsBefore, AuditEvent::query()->count());

        $this->as($this->head)
            ->get(route('reports.audit', ['record_type' => 'InventoryItem', 'record_id' => $item->id]))
            ->assertOk()
            ->assertSee('was changed again after this event');

        // The most recent change is still revertible.
        $this->revertAs($this->head, $newer)->assertSessionHasNoErrors();
        $this->assertSame('Recovery Tent B', $item->fresh()->unique_description);
    }

    public function test_a_revert_needs_a_reason_and_an_explicit_confirmation(): void
    {
        $item = $this->createInventoryItem('Recovery Podium', 2);
        $this->updateInventory($item, ['unique_description' => 'Recovery Podium Wrong']);
        $event = $this->latestEvent('INVENTORY_ITEM_UPDATED', $item->id);
        $eventsBefore = AuditEvent::query()->count();

        $this->as($this->head)
            ->post(route('reports.audit.revert', ['event' => $event->id]), ['confirm_revert' => 1])
            ->assertSessionHasErrors('revert_reason');
        $this->as($this->head)
            ->post(route('reports.audit.revert', ['event' => $event->id]), ['revert_reason' => 'short', 'confirm_revert' => 1])
            ->assertSessionHasErrors('revert_reason');
        $this->as($this->head)
            ->post(route('reports.audit.revert', ['event' => $event->id]), ['revert_reason' => 'A complete and valid reason.'])
            ->assertSessionHasErrors('confirm_revert');

        $this->assertSame('Recovery Podium Wrong', $item->fresh()->unique_description);
        $this->assertSame($eventsBefore, AuditEvent::query()->count());
    }

    public function test_other_roles_and_the_other_audit_audience_cannot_revert(): void
    {
        $item = $this->createInventoryItem('Recovery Flag', 2);
        $this->updateInventory($item, ['unique_description' => 'Recovery Flag Wrong']);
        $inventoryEvent = $this->latestEvent('INVENTORY_ITEM_UPDATED', $item->id);

        $this->updateAccount($this->borrower, ['designation' => 'Recovery Designation Wrong']);
        $accountEvent = $this->latestEvent('USER_ACCOUNT_UPDATED', $this->borrower->id);
        $eventsBefore = AuditEvent::query()->count();

        $this->revertAs($this->officer, $inventoryEvent)->assertForbidden();
        $this->revertAs($this->borrower, $inventoryEvent)->assertForbidden();
        // The retired ICTU classification opens no portal; account events are view-only for the Head.
        $this->revertAs($this->ictu, $inventoryEvent)->assertForbidden();
        $this->revertAs($this->head, $accountEvent)->assertNotFound();

        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->post(route('reports.audit.revert', ['event' => $inventoryEvent->id]), [
            'revert_reason' => 'Signed-out attempt to revert.',
            'confirm_revert' => 1,
        ])->assertRedirect(route('login'));

        $this->assertSame('Recovery Flag Wrong', $item->fresh()->unique_description);
        $this->assertSame('Recovery Designation Wrong', $this->borrower->fresh()->designation);
        $this->assertSame($eventsBefore, AuditEvent::query()->count());

        $this->as($this->ictu)->get(route('reports.audit'))->assertForbidden();
    }

    public function test_stock_archiving_and_deletion_are_never_reverted_from_the_audit_trail(): void
    {
        $item = $this->createInventoryItem('Recovery Linen', 12);

        $this->as($this->head)
            ->post(route('inventory.adjust', $item), ['action' => 'STOCK_ADDITION', 'quantity' => 8, 'reason' => 'Delivery receipt 1001.'])
            ->assertSessionHasNoErrors();
        $adjustment = $this->latestEvent('INVENTORY_ADJUSTMENT_RECORDED', $item->id);
        $quantityAfterAdjustment = $item->fresh()->total_quantity;

        // Archiving is the only change in this edit, and it has its own commitment check.
        $this->updateInventory($item->fresh(), ['active' => 0]);
        $archive = $this->latestEvent('INVENTORY_ITEM_UPDATED', $item->id);

        $unused = $this->createInventoryItem('Recovery Unused Item', 0);
        DB::table('inventory_transaction_lines')->where('inventory_item_id', $unused->id)->delete();
        DB::table('inventory_transactions')->where('source_type', InventoryItem::class)->where('source_id', $unused->id)->delete();
        $this->as($this->head)
            ->delete(route('inventory.destroy', $unused), [
                'confirm_item_name' => 'Recovery Unused Item',
                'delete_reason' => 'Created by mistake.',
            ])
            ->assertSessionHasNoErrors();
        $deletion = $this->latestEvent('INVENTORY_ITEM_DELETED', $unused->id);
        $eventsBefore = AuditEvent::query()->count();

        foreach ([$adjustment, $archive, $deletion] as $event) {
            $this->revertAs($this->head, $event)->assertSessionHasErrors('revert');
        }

        $this->assertSame($quantityAfterAdjustment, $item->fresh()->total_quantity);
        $this->assertFalse($item->fresh()->active);
        $this->assertNull(InventoryItem::query()->find($unused->id));
        $this->assertSame($eventsBefore, AuditEvent::query()->count());

        // No Restore or Revert control is offered for these events.
        $this->as($this->head)
            ->get(route('reports.audit', ['record_type' => 'InventoryItem', 'record_id' => $item->id]))
            ->assertOk()
            ->assertDontSee('Will Be Restored To');
        $this->as($this->head)
            ->get(route('reports.audit', ['record_type' => 'InventoryItem', 'record_id' => $unused->id]))
            ->assertOk()
            ->assertSee('Preserved snapshot of the deleted record')
            ->assertDontSee('Will Be Restored To');
    }

    public function test_a_mixed_edit_restores_only_the_eligible_fields(): void
    {
        $item = $this->createInventoryItem('Recovery Banner', 6);
        $this->updateInventory($item, ['unique_description' => 'Recovery Banner Wrong', 'active' => 0]);
        $event = $this->latestEvent('INVENTORY_ITEM_UPDATED', $item->id);

        $this->as($this->head)
            ->get(route('reports.audit', ['record_type' => 'InventoryItem', 'record_id' => $item->id]))
            ->assertOk()
            ->assertSee('Not restored by this action: Active.');

        $this->revertAs($this->head, $event)->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertSame('Recovery Banner', $item->unique_description);
        // Archiving was part of the same edit but is not undone by a revert.
        $this->assertFalse($item->active);
    }

    public function test_a_previous_name_that_is_now_taken_blocks_the_revert(): void
    {
        $item = $this->createInventoryItem('Recovery Stage', 1);
        $this->updateInventory($item, ['unique_description' => 'Recovery Stage Renamed']);
        $event = $this->latestEvent('INVENTORY_ITEM_UPDATED', $item->id);
        // Past the duplicate-submission window, the freed name is given to a new item.
        $this->travel(30)->seconds();
        $this->createInventoryItem('Recovery Stage', 1);

        $this->revertAs($this->head, $event)->assertSessionHasErrors('revert');

        $this->assertSame('Recovery Stage Renamed', $item->fresh()->unique_description);
    }
    public function test_only_the_item_name_and_specification_are_restored_for_inventory(): void
    {
        $item = $this->createInventoryItem('Recovery Canopy', 9);
        $otherCategoryId = DB::table('inventory_categories')->where('id', '!=', $item->category_id)->value('id');
        $otherUnitId = DB::table('units_of_measure')->where('id', '!=', $item->unit_id)->value('id');
        $this->assertNotNull($otherCategoryId);
        $this->assertNotNull($otherUnitId);

        // One edit changes the name together with every other editable detail.
        $this->updateInventory($item, [
            'unique_description' => 'Recovery Canopy Wrong',
            'specification' => 'Wrong specification',
            'category_id' => $otherCategoryId,
            'unit_id' => $otherUnitId,
            'borrowable' => 0,
            'laundry_required' => 1,
            'provisional' => 1,
        ]);
        $mixed = $this->latestEvent('INVENTORY_ITEM_UPDATED', $item->id);

        $this->as($this->head)
            ->get(route('reports.audit', ['record_type' => 'InventoryItem', 'record_id' => $item->id]))
            ->assertOk()
            ->assertSee('Not restored by this action: Category Id, Unit Id, Borrowable, Laundry Required, Provisional.');

        $this->revertAs($this->head, $mixed)->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertSame('Recovery Canopy', $item->unique_description);
        $this->assertNull($item->specification);
        // Everything else keeps the value from the edit; it is corrected on the inventory form.
        $this->assertSame((int) $otherCategoryId, (int) $item->category_id);
        $this->assertSame((int) $otherUnitId, (int) $item->unit_id);
        $this->assertFalse($item->borrowable);
        $this->assertTrue($item->laundry_required);
        $this->assertTrue($item->provisional);
        $this->assertSame(9, $item->total_quantity);
        $this->assertSame('SERVICEABLE', $item->condition_code);

        $revert = $this->latestEvent('INVENTORY_ITEM_CHANGE_REVERTED', $item->id);
        $this->assertSame(
            ['reverted_audit_event_no', 'reverted_audit_event_recorded_at', 'specification', 'unique_description'],
            collect(array_keys($revert->after_json))->sort()->values()->all()
        );
        // The original event still holds the before and after value of every field.
        $this->assertSame((int) $otherCategoryId, (int) $mixed->fresh()->after_json['category_id']);
        $this->assertTrue($mixed->fresh()->before_json['borrowable']);

        // An edit that changed only the other details offers no revert at all.
        $this->updateInventory($item, ['borrowable' => 1, 'laundry_required' => 0]);
        $flagsOnly = $this->latestEvent('INVENTORY_ITEM_UPDATED', $item->id);
        $eventsBefore = AuditEvent::query()->count();

        $this->revertAs($this->head, $flagsOnly)->assertSessionHasErrors('revert');

        $this->assertTrue($item->fresh()->borrowable);
        $this->assertSame($eventsBefore, AuditEvent::query()->count());
    }

    public function test_setting_changes_are_shown_to_the_head_and_are_never_revertible(): void
    {
        $setting = SystemSetting::query()->where('setting_key', 'approved_letter_download_time')->firstOrFail();
        $this->assertNotContains($setting->setting_key, array_merge(
            SettingController::TECHNICAL_SETTING_KEYS,
            SettingController::PROTECTED_DIRECT_SETTING_KEYS,
        ));

        $this->as($this->head)
            ->put(route('administration.settings.update', $setting), ['value' => '22:00', 'reason' => 'Policy update.'])
            ->assertSessionHasNoErrors();
        $event = $this->latestEvent('SYSTEM_SETTING_CHANGED', $setting->id);

        $technical = SystemSetting::query()->where('setting_key', 'max_upload_mb')->firstOrFail();
        $this->as($this->head)
            ->put(route('administration.settings.update', $technical), ['value' => 9, 'reason' => 'Technical change.'])
            ->assertSessionHasNoErrors();
        $technicalEvent = $this->latestEvent('SYSTEM_SETTING_CHANGED', $technical->id);

        $pageIds = fn (User $user, array $query = []): array => $this->as($user)
            ->get(route('reports.audit', $query + ['per_page' => 28]))
            ->assertOk()
            ->viewData('events')
            ->getCollection()
            ->pluck('id')
            ->all();

        // The Head investigates both; each stays under its own category.
        $headIds = $pageIds($this->head);
        $this->assertContains($event->id, $headIds);
        $this->assertContains($technicalEvent->id, $headIds);
        $this->assertSame([$event->id], $pageIds($this->head, ['category' => 'OPERATIONAL_SETTINGS']));
        $this->assertSame([$technicalEvent->id], $pageIds($this->head, ['category' => 'SETTINGS']));
        $this->as($this->head)
            ->get(route('reports.audit', ['category' => 'OPERATIONAL_SETTINGS']))
            ->assertOk()
            ->assertSee('Operational Settings')
            ->assertSee('Policy update.')
            ->assertSee('22:00')
            ->assertDontSee('Will Be Restored To');

        // Fees and deadlines are never reverted from an audit event; system settings are view-only.
        $this->revertAs($this->head, $event)->assertForbidden();
        $this->revertAs($this->ictu, $event)->assertForbidden();
        $this->revertAs($this->head, $technicalEvent)->assertNotFound();

        $this->assertSame('22:00', $setting->fresh()->value_json);
        $this->assertSame(9, $technical->fresh()->value_json);
        $this->assertSame(0, AuditEvent::query()->where('action_code', 'SYSTEM_SETTING_CHANGE_REVERTED')->count());
    }
    /**
     * The order a snapshot's keys come back in belongs to the database: a
     * text column keeps them as written, MySQL's native JSON type re-sorts
     * them by length. The page must read the same either way.
     */
    public function test_the_field_lists_keep_one_order_however_the_database_returns_the_snapshot_keys(): void
    {
        $item = $this->createInventoryItem('Order Canopy', 9);

        $this->updateInventory($item, [
            'unique_description' => 'Order Canopy Wrong',
            'specification' => 'Wrong specification',
            'category_id' => DB::table('inventory_categories')->where('id', '!=', $item->category_id)->value('id'),
            'unit_id' => DB::table('units_of_measure')->where('id', '!=', $item->unit_id)->value('id'),
            'borrowable' => 0,
            'laundry_required' => 1,
            'provisional' => 1,
        ]);
        $event = $this->latestEvent('INVENTORY_ITEM_UPDATED', $item->id);

        $expectedSkipped = 'Not restored by this action: Category Id, Unit Id, Borrowable, Laundry Required, Provisional.';
        $orderings = [
            'as written' => fn (array $snapshot): array => $snapshot,
            'reversed' => fn (array $snapshot): array => array_reverse($snapshot, true),
            'alphabetical' => function (array $snapshot): array {
                ksort($snapshot);

                return $snapshot;
            },
            /* What MySQL's native JSON type does: shorter keys first, then by name. */
            'by key length' => function (array $snapshot): array {
                uksort($snapshot, fn (string $a, string $b): int => [strlen($a), $a] <=> [strlen($b), $b]);

                return $snapshot;
            },
        ];

        $before = $event->before_json;
        $after = $event->after_json;

        foreach ($orderings as $label => $reorder) {
            DB::table('audit_events')->where('id', $event->id)->update([
                'before_json' => json_encode($reorder($before)),
                'after_json' => json_encode($reorder($after)),
            ]);

            $this->as($this->head)
                ->get(route('reports.audit', ['record_type' => 'InventoryItem', 'record_id' => $item->id]))
                ->assertOk()
                ->assertSee($expectedSkipped)
                /* The restorable fields keep their order too: name, then specification. */
                ->assertSeeInOrder(['<strong>Item Name</strong>', '<strong>Specification</strong>'], false);

            /* The same content, whatever the order: nothing was dropped or added. */
            $stored = AuditEvent::query()->findOrFail($event->id);
            $this->assertEquals($before, $stored->before_json, "Snapshot content changed for the [{$label}] ordering.");
            $this->assertEquals($after, $stored->after_json, "Snapshot content changed for the [{$label}] ordering.");
        }

        /* A reordered snapshot still reverts exactly the two eligible fields. */
        $this->revertAs($this->head, $event)->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertSame('Order Canopy', $item->unique_description);
        $this->assertNull($item->specification);
        $this->assertFalse($item->borrowable);
        $this->assertTrue($item->laundry_required);
    }

    public function test_fields_the_record_does_not_declare_are_listed_last_in_alphabetical_order(): void
    {
        $item = $this->createInventoryItem('Order Lectern', 4);

        $this->updateInventory($item, ['unique_description' => 'Order Lectern Wrong', 'borrowable' => 0]);
        $event = $this->latestEvent('INVENTORY_ITEM_UPDATED', $item->id);

        /* Two fields no model declares, written ahead of the declared ones. */
        $before = ['zeta_note' => 'a', 'alpha_note' => 'a'] + $event->before_json;
        $after = ['zeta_note' => 'b', 'alpha_note' => 'b'] + $event->after_json;

        DB::table('audit_events')->where('id', $event->id)->update([
            'before_json' => json_encode($before),
            'after_json' => json_encode($after),
        ]);

        $this->as($this->head)
            ->get(route('reports.audit', ['record_type' => 'InventoryItem', 'record_id' => $item->id]))
            ->assertOk()
            ->assertSee('Not restored by this action: Borrowable, Alpha Note, Zeta Note.');
    }

    private function revertAs(User $user, AuditEvent $event): \Illuminate\Testing\TestResponse
    {
        return $this->as($user)->post(route('reports.audit.revert', ['event' => $event->id]), [
            'revert_reason' => 'Recovery test: the change was made in error.',
            'confirm_revert' => 1,
        ]);
    }

    private function latestEvent(string $action, int $recordId): AuditEvent
    {
        return AuditEvent::query()
            ->where('action_code', $action)
            ->where('record_id', $recordId)
            ->orderByDesc('id')
            ->firstOrFail();
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

    private function createInventoryItem(string $name, int $quantity): InventoryItem
    {
        $this->as($this->head)
            ->post(route('inventory.store'), [
                'category_id' => DB::table('inventory_categories')->value('id'),
                'unit_id' => DB::table('units_of_measure')->value('id'),
                'unique_description' => $name,
                'borrowable' => 1,
                'active' => 1,
                'total_quantity' => $quantity,
                'condition_code' => 'SERVICEABLE',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        return InventoryItem::query()->where('unique_description', $name)->orderByDesc('id')->firstOrFail();
    }

    /** Edit an item through its own form, changing only the given fields. */
    private function updateInventory(InventoryItem $item, array $changes): void
    {
        $this->as($this->head)
            ->put(route('inventory.update', $item), $changes + [
                'category_id' => $item->category_id,
                'unit_id' => $item->unit_id,
                'unique_description' => $item->unique_description,
                'specification' => $item->specification,
                'borrowable' => $item->borrowable ? 1 : 0,
                'laundry_required' => $item->laundry_required ? 1 : 0,
                'provisional' => $item->provisional ? 1 : 0,
                'off_campus_allowed' => $item->off_campus_allowed ? 1 : 0,
                'active' => $item->active ? 1 : 0,
                'change_reason' => 'Recovery test edit.',
            ])
            ->assertSessionHasNoErrors();
    }

    /** Edit an account through the SPMU Admin form, changing only the given fields. */
    private function updateAccount(User $account, array $changes): void
    {
        $account->loadMissing('organizationalUnit');

        $this->as($this->head)
            ->put(route('administration.users.update', $account), $changes + [
                'division_code' => $account->organizationalUnit->divisionCode(),
                'organizational_unit_id' => $account->organizational_unit_id,
                'employee_no' => $account->employee_no,
                'full_name' => $account->full_name,
                'designation' => $account->designation,
                'employment_type' => $account->employment_type->value,
                'employment_status' => $account->employment_status ?: 'FULL_TIME',
                'email' => $account->email,
                'mobile_no' => $account->mobile_no,
                'account_status' => $account->account_status->value,
                'access_classification' => $account->access_classification->value,
            ])
            ->assertSessionHasNoErrors();
    }
}
