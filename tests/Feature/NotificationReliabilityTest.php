<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Enums\RequestStatus;
use App\Models\Allocation;
use App\Models\BorrowingRequest;
use App\Models\CustodyLine;
use App\Models\CustodyTransaction;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\NotificationDelivery;
use App\Models\NotificationEvent;
use App\Models\OrganizationalUnit;
use App\Models\RequestItem;
use App\Models\RequestVersion;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\NotificationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * Notification reliability: an email leaves only after its business
 * transaction commits, a rolled-back action sends and records nothing, and a
 * scheduled reminder is issued once without duplicates.
 */
class NotificationReliabilityTest extends TestCase
{
    use RefreshDatabase;

    private User $borrower;

    private User $ictu;

    protected function setUp(): void
    {
        parent::setUp();

        // Isolated database and in-memory mailer only; no real email can leave.
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        $this->assertSame('array', config('mail.default'));

        $this->seed(DatabaseSeeder::class);

        $this->borrower = User::query()->where('access_classification', AccessClassification::BorrowerOnly->value)->firstOrFail();
        $this->ictu = User::query()->where('access_classification', AccessClassification::IctuMaintainer->value)->firstOrFail();
    }

    public function test_an_email_is_handed_to_the_transport_only_after_the_transaction_commits(): void
    {
        $event = DB::transaction(function (): NotificationEvent {
            $event = $this->notify('Committed action.');

            // Still inside the transaction: nothing has left, and no email attempt is recorded yet.
            $this->assertCount(0, $this->sentMessages());
            $this->assertSame(['SYSTEM'], $event->deliveries()->pluck('channel')->all());

            return $event;
        });

        $this->assertCount(1, $this->sentMessages());
        $email = $event->deliveries()->where('channel', 'EMAIL')->sole();
        $this->assertSame('SENT', $email->delivery_status);
        $this->assertSame($this->borrower->email, $email->address_snapshot);
        $this->assertSame($this->borrower->id, $email->recipient_user_id);
        $this->assertSame(1, $email->attempt_no);
        $this->assertSame('SENT', $event->deliveries()->where('channel', 'SYSTEM')->sole()->delivery_status);
    }

    public function test_a_rolled_back_transaction_sends_nothing_and_leaves_no_records(): void
    {
        $events = NotificationEvent::query()->count();
        $deliveries = NotificationDelivery::query()->count();

        try {
            DB::transaction(function (): void {
                $this->borrower->forceFill(['designation' => 'Changed inside the failed action'])->save();
                $this->notify('This action is about to fail.');

                throw new RuntimeException('Business action failed after the notification was requested.');
            });
            $this->fail('The transaction should have failed.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Business action failed after the notification was requested.', $exception->getMessage());
        }

        // No email, no event, no in-system notification, no delivery record, no business change.
        $this->assertCount(0, $this->sentMessages());
        $this->assertSame($events, NotificationEvent::query()->count());
        $this->assertSame($deliveries, NotificationDelivery::query()->count());
        $this->assertNotSame('Changed inside the failed action', $this->borrower->fresh()->designation);
    }

    public function test_nested_transactions_wait_for_the_outermost_commit_and_respect_its_rollback(): void
    {
        DB::transaction(function (): void {
            DB::transaction(fn () => $this->notify('Inner step committed.'));

            // The inner commit alone must not release the email.
            $this->assertCount(0, $this->sentMessages());
        });
        $this->assertCount(1, $this->sentMessages());

        try {
            DB::transaction(function (): void {
                DB::transaction(fn () => $this->notify('Inner step of a failing action.'));

                throw new RuntimeException('Outer action failed.');
            });
        } catch (RuntimeException) {
            // expected
        }

        $this->assertCount(1, $this->sentMessages());
        $this->assertSame(1, NotificationDelivery::query()->where('channel', 'EMAIL')->count());
    }

    public function test_without_a_transaction_the_email_is_attempted_immediately_as_before(): void
    {
        $event = $this->notify('No transaction is open.');

        $this->assertCount(1, $this->sentMessages());
        $this->assertSame(['EMAIL', 'SYSTEM'], $event->deliveries()->pluck('channel')->sort()->values()->all());
    }

    public function test_a_transport_failure_after_commit_is_recorded_as_failed_and_does_not_undo_the_action(): void
    {
        Mail::shouldReceive('html')->once()->andThrow(new RuntimeException('Connection could not be established with host "smtp.example.test:587"'));

        $event = DB::transaction(function (): NotificationEvent {
            $this->borrower->forceFill(['designation' => 'Committed change'])->save();

            return $this->notify('Committed action whose email fails.');
        });

        $this->assertSame('Committed change', $this->borrower->fresh()->designation);
        $email = $event->deliveries()->where('channel', 'EMAIL')->sole();
        $this->assertSame('FAILED', $email->delivery_status);
        $this->assertStringContainsString('smtp.example.test:587', $email->provider_response);
        $this->assertSame('SENT', $event->deliveries()->where('channel', 'SYSTEM')->sole()->delivery_status);
    }

    public function test_a_due_reminder_is_issued_once_and_is_not_repeated_on_later_runs(): void
    {
        $this->travelTo(Carbon::create(2026, 10, 12, 9, 0, 0, config('app.timezone')));
        $custody = $this->activeCustodyDue(Carbon::create(2026, 10, 13, 17, 0, 0, config('app.timezone')));

        $this->artisan('spmu:process-deadlines')->assertSuccessful();
        $this->artisan('spmu:process-deadlines')->assertSuccessful();
        $this->artisan('spmu:process-deadlines')->assertSuccessful();

        $events = NotificationEvent::query()
            ->where('event_code', 'RETURN_DUE_TOMORROW')
            ->where('source_id', $custody->id)
            ->get();
        $this->assertCount(1, $events);
        $this->assertSame([$this->borrower->id], $events[0]->deliveries()->pluck('recipient_user_id')->unique()->values()->all());
        $this->assertSame(['EMAIL', 'SYSTEM'], $events[0]->deliveries()->pluck('channel')->sort()->values()->all());
        $this->assertCount(1, $this->sentMessages());
    }

    public function test_a_failed_reminder_is_kept_as_failed_and_is_not_silently_resent(): void
    {
        $this->travelTo(Carbon::create(2026, 10, 12, 9, 0, 0, config('app.timezone')));
        $custody = $this->activeCustodyDue(Carbon::create(2026, 10, 13, 17, 0, 0, config('app.timezone')));

        // The transport is down for the first run only.
        Mail::shouldReceive('html')->once()->andThrow(new RuntimeException('Connection could not be established with host "smtp.example.test:587"'));
        $this->artisan('spmu:process-deadlines')->assertSuccessful();

        $event = NotificationEvent::query()->where('event_code', 'RETURN_DUE_TOMORROW')->where('source_id', $custody->id)->sole();
        $failed = $event->deliveries()->where('channel', 'EMAIL')->sole();
        $this->assertSame('FAILED', $failed->delivery_status);
        $this->assertSame('SENT', $event->deliveries()->where('channel', 'SYSTEM')->sole()->delivery_status);
        $failedRow = (array) DB::table('notification_deliveries')->where('id', $failed->id)->first();

        // The transport is healthy again; later runs still do not send the reminder a second time.
        $this->app->forgetInstance('mail.manager');
        Mail::clearResolvedInstance('mail.manager');
        $this->artisan('spmu:process-deadlines')->assertSuccessful();
        $this->artisan('spmu:process-deadlines')->assertSuccessful();

        $this->assertCount(0, $this->sentMessages());
        $this->assertSame(1, NotificationEvent::query()->where('event_code', 'RETURN_DUE_TOMORROW')->count());
        $this->assertSame(1, $event->deliveries()->where('channel', 'EMAIL')->count());
        // The failed attempt is preserved exactly and stays visible as a current failure.
        $this->assertSame($failedRow, (array) DB::table('notification_deliveries')->where('id', $failed->id)->first());
        $this->assertTrue(NotificationDelivery::query()->actionableFailures()->whereKey($failed->id)->exists());

        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->withSession(['active_workspace' => $this->ictu->primaryWorkspace()])
            ->actingAs($this->ictu)
            ->get(route('reports.notifications', ['status' => 'CURRENT_FAILURE']))
            ->assertOk()
            ->assertSee('Return Due Tomorrow')
            ->assertSee('Delivery Failed')
            ->assertSee('This scheduled reminder is issued once per transaction, so the email was not attempted again.');
    }

    private function notify(string $message): NotificationEvent
    {
        return app(NotificationService::class)->send(
            'ACCOUNT_ACCESS_DISABLED',
            collect([$this->borrower]),
            $message,
            $this->borrower
        );
    }

    /** A released custody with five units still out, due on the given date. */
    private function activeCustodyDue(Carbon $dueAt): CustodyTransaction
    {
        $unit = OrganizationalUnit::query()->firstOrCreate(
            ['unit_code' => 'RELFIX'],
            ['unit_name' => 'Reliability Fixture Unit', 'unit_type' => 'OFFICE', 'active' => true]
        );
        $category = InventoryCategory::query()->firstOrCreate(
            ['category_code' => 'RELFIX'],
            ['category_name' => 'Reliability Fixture', 'active' => true]
        );
        $measure = UnitOfMeasure::query()->firstOrCreate(
            ['unit_code' => 'PC'],
            ['unit_name' => 'Piece', 'active' => true]
        );
        $item = InventoryItem::query()->create([
            'category_id' => $category->id,
            'unit_id' => $measure->id,
            'unique_description' => 'Reliability Fixture Chair',
            'total_quantity' => 20,
            'condition_code' => 'SERVICEABLE',
            'borrowable' => true,
            'active' => true,
        ]);
        $releasedAt = $dueAt->copy()->subDays(3)->setTime(9, 0);

        $request = BorrowingRequest::query()->create([
            'request_no' => 'BR-RELFIX-0001',
            'borrower_user_id' => $this->borrower->id,
            'accountable_unit_id' => $unit->id,
            'current_version_no' => 1,
            'status' => RequestStatus::ApprovedReadyForRelease,
        ]);
        $version = RequestVersion::query()->create([
            'request_id' => $request->id,
            'version_no' => 1,
            'purpose_event' => 'Reliability fixture',
            'location' => 'Campus',
            'division_code' => 'ACADEMIC',
            'office_unit' => 'College of Computer Studies',
            'schedule_date' => $releasedAt->toDateString(),
            'return_date' => $dueAt->toDateString(),
            'needed_from' => $releasedAt->copy()->startOfDay(),
            'return_due_at' => $dueAt,
        ]);
        $requestItem = RequestItem::query()->create([
            'request_version_id' => $version->id,
            'inventory_item_id' => $item->id,
            'description_snapshot' => $item->unique_description,
            'unit_snapshot' => 'Piece',
            'requested_quantity' => 5,
            'approved_quantity' => 5,
        ]);
        $allocation = Allocation::query()->create([
            'request_item_id' => $requestItem->id,
            'period_start' => $releasedAt->copy()->startOfDay(),
            'period_end' => $dueAt,
            'allocated_quantity' => 5,
            'released_quantity' => 5,
            'restored_quantity' => 0,
            'status' => 'RELEASED',
            'allocated_at' => $releasedAt,
        ]);
        $custody = CustodyTransaction::query()->create([
            'custody_no' => 'CUS-RELFIX-0001',
            'request_id' => $request->id,
            'request_version_id' => $version->id,
            'borrower_user_id' => $this->borrower->id,
            'status' => 'ACTIVE',
            'due_at' => $dueAt,
            'released_at' => $releasedAt,
        ]);
        CustodyLine::query()->create([
            'custody_transaction_id' => $custody->id,
            'request_item_id' => $requestItem->id,
            'allocation_id' => $allocation->id,
            'approved_quantity' => 5,
            'quantity_to_receive' => 5,
            'actual_released_quantity' => 5,
            'returned_quantity' => 0,
        ]);

        return $custody;
    }

    /** @return list<\Illuminate\Mail\SentMessage> */
    private function sentMessages(): array
    {
        return app('mailer')->getSymfonyTransport()->messages()->all();
    }
}
