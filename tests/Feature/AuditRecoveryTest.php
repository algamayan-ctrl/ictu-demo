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
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));

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
        // Operational events do not exist for ICTU, and technical ones do not exist for the Head.
        $this->revertAs($this->ictu, $inventoryEvent)->assertNotFound();
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

        // Neither trail offers the action to the other audience.
        $this->as($this->ictu)->get(route('reports.audit'))->assertOk()->assertDontSee('Recovery Flag Wrong');
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
                'deletion_reason' => 'Created by mistake.',
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

    public function test_ictu_reverts_a_technical_setting_but_not_one_whose_previous_value_is_no_longer_valid(): void
    {
        $upload = SystemSetting::query()->where('setting_key', 'max_upload_mb')->firstOrFail();
        $previous = $upload->value_json;

        $this->as($this->ictu)
            ->put(route('administration.settings.update', $upload), ['value' => 12, 'reason' => 'Raised for a large scan.'])
            ->assertSessionHasNoErrors();
        $event = $this->latestEvent('SYSTEM_SETTING_CHANGED', $upload->id);
        $changesBefore = DB::table('configuration_changes')->where('system_setting_id', $upload->id)->count();

        // The Head neither sees nor reaches a technical setting event.
        $this->revertAs($this->head, $event)->assertNotFound();

        $this->as($this->ictu)->get(route('reports.audit'))->assertOk()->assertSee('Revert This Change');
        $this->revertAs($this->ictu, $event)->assertSessionHasNoErrors();

        $this->assertSame($previous, $upload->fresh()->value_json);
        $this->assertSame($changesBefore + 1, DB::table('configuration_changes')->where('system_setting_id', $upload->id)->count());
        $revert = $this->latestEvent('SYSTEM_SETTING_CHANGE_REVERTED', $upload->id);
        $this->assertSame(12, $revert->before_json['value']);
        $this->assertSame($previous, $revert->after_json['value']);
        $this->assertSame($event->id, $revert->after_json['reverted_audit_event_no']);

        // The seeded backup schedule placeholder does not pass today's rules, so it is not restored.
        $backup = SystemSetting::query()->where('setting_key', 'backup_schedule')->firstOrFail();
        $this->as($this->ictu)
            ->put(route('administration.settings.update', $backup), ['backup_time' => '02:00', 'backup_recurrence' => 'daily'])
            ->assertSessionHasNoErrors();
        $backupEvent = $this->latestEvent('SYSTEM_SETTING_CHANGED', $backup->id);

        $this->revertAs($this->ictu, $backupEvent)->assertSessionHasErrors('revert');
        $this->assertSame(['time' => '02:00', 'recurrence' => 'daily'], $backup->fresh()->value_json);
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

    public function test_operational_setting_changes_are_shown_to_the_head_only_and_are_never_revertible(): void
    {
        $setting = SystemSetting::query()->where('setting_key', 'approved_letter_download_time')->firstOrFail();
        $this->assertNotContains($setting->setting_key, array_merge(
            SettingController::ICTU_SETTING_KEYS,
            SettingController::PROTECTED_DIRECT_SETTING_KEYS,
        ));

        $this->as($this->head)
            ->put(route('administration.settings.update', $setting), ['value' => '22:00', 'reason' => 'Policy update.'])
            ->assertSessionHasNoErrors();
        $event = $this->latestEvent('SYSTEM_SETTING_CHANGED', $setting->id);

        $technical = SystemSetting::query()->where('setting_key', 'max_upload_mb')->firstOrFail();
        $this->as($this->ictu)
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

        // The Head investigates the operational setting; the technical one stays with ICTU.
        $headIds = $pageIds($this->head);
        $this->assertContains($event->id, $headIds);
        $this->assertNotContains($technicalEvent->id, $headIds);
        $this->assertSame([$event->id], $pageIds($this->head, ['category' => 'OPERATIONAL_SETTINGS']));
        $this->as($this->head)
            ->get(route('reports.audit', ['category' => 'OPERATIONAL_SETTINGS']))
            ->assertOk()
            ->assertSee('Operational Settings')
            ->assertSee('Policy update.')
            ->assertSee('22:00')
            ->assertDontSee('Will Be Restored To');

        $ictuIds = $pageIds($this->ictu);
        $this->assertContains($technicalEvent->id, $ictuIds);
        $this->assertNotContains($event->id, $ictuIds);
        // A forged category or record filter does not bring the operational event into ICTU's trail.
        $this->assertNotContains($event->id, $pageIds($this->ictu, ['category' => 'OPERATIONAL_SETTINGS']));
        $this->assertSame([], $pageIds($this->ictu, ['record_type' => 'SystemSetting', 'record_id' => $setting->id]));
        $this->assertSame([], $pageIds($this->head, ['record_type' => 'SystemSetting', 'record_id' => $technical->id]));

        // Fees and deadlines are never reverted from an audit event, by either office.
        $this->revertAs($this->head, $event)->assertForbidden();
        $this->revertAs($this->ictu, $event)->assertNotFound();
        $this->revertAs($this->head, $technicalEvent)->assertNotFound();

        $this->assertSame('22:00', $setting->fresh()->value_json);
        $this->assertSame(9, $technical->fresh()->value_json);
        $this->assertSame(0, AuditEvent::query()->where('action_code', 'SYSTEM_SETTING_CHANGE_REVERTED')->count());
    }

    public function test_an_account_full_name_is_never_reverted_from_the_audit_trail(): void
    {
        $account = $this->borrower;
        $originalName = $account->full_name;
        $this->updateAccount($account, ['designation' => 'Instructor I']);

        // A rename on its own offers no revert.
        $this->updateAccount($account->fresh(), ['full_name' => 'Recovery Renamed Borrower']);
        $rename = $this->latestEvent('USER_ACCOUNT_UPDATED', $account->id);
        $this->assertSame($originalName, $rename->before_json['full_name']);
        $this->assertSame('Recovery Renamed Borrower', $rename->after_json['full_name']);
        $eventsBefore = AuditEvent::query()->count();

        $this->as($this->ictu)
            ->get(route('reports.audit', ['record_type' => 'User', 'record_id' => $account->id]))
            ->assertOk()
            ->assertDontSee('Will Be Restored To');
        $this->revertAs($this->ictu, $rename)->assertSessionHasErrors('revert');

        $this->assertSame('Recovery Renamed Borrower', $account->fresh()->full_name);
        $this->assertSame($eventsBefore, AuditEvent::query()->count());

        // A rename made together with a designation change restores the designation only.
        $this->updateAccount($account->fresh(), ['full_name' => 'Recovery Renamed Again', 'designation' => 'Recovery Designation Wrong']);
        $mixed = $this->latestEvent('USER_ACCOUNT_UPDATED', $account->id);

        $this->as($this->ictu)
            ->get(route('reports.audit', ['record_type' => 'User', 'record_id' => $account->id]))
            ->assertOk()
            ->assertSee('Not restored by this action: Full Name.');
        $this->revertAs($this->ictu, $mixed)->assertSessionHasNoErrors();

        $account->refresh();
        $this->assertSame('Instructor I', $account->designation);
        $this->assertSame('Recovery Renamed Again', $account->full_name);
        $revert = $this->latestEvent('USER_ACCOUNT_CHANGE_REVERTED', $account->id);
        $this->assertArrayNotHasKey('full_name', $revert->after_json);
        $this->assertArrayNotHasKey('full_name', $revert->before_json);
        // The audit history of the name itself is intact.
        $this->assertSame('Recovery Renamed Borrower', $mixed->fresh()->before_json['full_name']);
    }

    public function test_a_profile_revert_leaves_identity_verification_roles_status_and_credentials_alone(): void
    {
        $account = $this->borrower;
        $this->updateAccount($account, ['designation' => 'Instructor I']);
        $account->refresh();

        // One ICTU edit changes the designation together with the institutional email.
        $newEmail = 'recovery.'.$account->email;
        $this->updateAccount($account, ['designation' => 'Recovery Designation Wrong', 'email' => $newEmail]);
        $event = $this->latestEvent('USER_ACCOUNT_UPDATED', $account->id);

        $protected = fn (): array => [
            'row' => collect((array) DB::table('users')->where('id', $account->id)->first())
                ->except(['designation', 'updated_at'])
                ->all(),
            'roles' => DB::table('user_roles')->where('user_id', $account->id)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'units' => $account->fresh()->authorizedOrganizationalUnits()->pluck('organizational_units.id')->sort()->values()->all(),
            'sessions' => DB::table('sessions')->where('user_id', $account->id)->count(),
            'reset_tokens' => DB::table('password_reset_tokens')->count(),
        ];
        $before = $protected();
        $this->assertSame($newEmail, $before['row']['email']);
        $this->assertNull($before['row']['email_verified_at']);

        $this->as($this->ictu)
            ->get(route('reports.audit', ['record_type' => 'User', 'record_id' => $account->id]))
            ->assertOk()
            ->assertSee('Not restored by this action: Email.');

        $this->revertAs($this->ictu, $event)->assertSessionHasNoErrors();

        $this->assertSame('Instructor I', $account->fresh()->designation);
        // Email, verification, employee number, status, classification, password hash,
        // roles and unit assignments are exactly as they were before the revert.
        $this->assertSame($before, $protected());
    }

    public function test_ictu_reverts_profile_details_but_never_account_access_or_credentials(): void
    {
        $account = $this->borrower;
        $this->updateAccount($account, ['designation' => 'Instructor I']);
        $account->refresh();
        $originalDesignation = $account->designation;
        $originalMobile = $account->mobile_no;
        $passwordHash = $account->getRawOriginal('password');

        $this->updateAccount($account, ['designation' => 'Recovery Designation Wrong', 'mobile_no' => '09991112222']);
        $event = $this->latestEvent('USER_ACCOUNT_UPDATED', $account->id);
        $this->assertSame('09991112222', $account->fresh()->mobile_no);

        $this->revertAs($this->ictu, $event)->assertSessionHasNoErrors();

        $account->refresh();
        $this->assertSame($originalDesignation, $account->designation);
        $this->assertSame($originalMobile, $account->mobile_no);
        $this->assertSame($passwordHash, $account->getRawOriginal('password'));
        $revert = $this->latestEvent('USER_ACCOUNT_CHANGE_REVERTED', $account->id);
        $this->assertSame($event->id, $revert->after_json['reverted_audit_event_no']);
        $this->assertSame(
            ['designation', 'mobile_no', 'reverted_audit_event_no', 'reverted_audit_event_recorded_at'],
            collect(array_keys($revert->after_json))->sort()->values()->all()
        );

        // Account status changes keep the Deactivate / Reactivate actions.
        $this->as($this->ictu)
            ->patch(route('administration.users.deactivate', $account))
            ->assertSessionHasNoErrors();
        $deactivation = $this->latestEvent('USER_ACCOUNT_UPDATED', $account->id);

        $this->revertAs($this->ictu, $deactivation)->assertSessionHasErrors('revert');
        $this->assertSame('INACTIVE', $account->fresh()->account_status->value);
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

    /** Edit an account through the ICTU form, changing only the given fields. */
    private function updateAccount(User $account, array $changes): void
    {
        $account->loadMissing('organizationalUnit');

        $this->as($this->ictu)
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
