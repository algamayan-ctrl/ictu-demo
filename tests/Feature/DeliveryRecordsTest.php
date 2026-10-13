<?php

namespace Tests\Feature;

use App\Enums\AccessClassification;
use App\Models\NotificationDelivery;
use App\Models\NotificationEvent;
use App\Models\User;
use App\Services\NotificationService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

/**
 * ICTU Delivery Records: what is recorded for each notification attempt, how
 * it is worded, who may read it, and that every retained record stays
 * reachable through server-side filters and pagination.
 */
class DeliveryRecordsTest extends TestCase
{
    use RefreshDatabase;

    private User $borrower;

    private User $officer;

    private User $head;

    private User $ictu;

    protected function setUp(): void
    {
        parent::setUp();

        // These tests change data and send mail; only the isolated database and the in-memory mailer are allowed.
        /*
         * Tests\TestCase has already refused any database that is not provably
         * disposable, before RefreshDatabase rebuilt it. Asserted again here so
         * the requirement stays visible beside tests that rewrite audit history.
         */
        $this->assertDisposableTestDatabase();
        $this->assertSame('array', config('mail.default'));

        $this->seed(DatabaseSeeder::class);

        $this->borrower = $this->classificationUser(AccessClassification::BorrowerOnly);
        $this->officer = $this->classificationUser(AccessClassification::SpmuOfficer);
        $this->head = $this->classificationUser(AccessClassification::SpmuHead);
        $this->ictu = $this->classificationUser(AccessClassification::IctuMaintainer);
    }

    public function test_an_accepted_email_and_its_in_system_copy_are_recorded_without_claiming_delivery(): void
    {
        Queue::fake();

        $event = app(NotificationService::class)->send(
            'ACCOUNT_ACCESS_DISABLED',
            collect([$this->borrower]),
            'CONFIDENTIAL-MESSAGE-BODY for the borrower.',
            $this->borrower
        );

        // Mail is handed to the transport inside the request; nothing is queued for later.
        Queue::assertNothingPushed();
        $this->assertCount(1, $this->sentMessages());
        $this->assertSame($this->borrower->email, $this->sentMessages()[0]->getOriginalMessage()->getTo()[0]->getAddress());

        $deliveries = $event->deliveries()->orderBy('id')->get()->keyBy('channel');
        $this->assertSame(['EMAIL', 'SYSTEM'], $deliveries->keys()->sort()->values()->all());

        $email = $deliveries['EMAIL'];
        $this->assertSame('SENT', $email->delivery_status);
        $this->assertSame('array', $email->provider);
        $this->assertSame($this->borrower->email, $email->address_snapshot);
        $this->assertSame($this->borrower->id, $email->recipient_user_id);
        $this->assertSame(1, $email->attempt_no);
        $this->assertNotNull($email->attempted_at);

        $system = $deliveries['SYSTEM'];
        $this->assertSame('SENT', $system->delivery_status);
        $this->assertSame('system', $system->provider);
        $this->assertSame((string) $this->borrower->id, $system->address_snapshot);

        $page = $this->as($this->ictu)->get(route('reports.notifications'))->assertOk();
        $page->assertSee('Account Access Disabled')
            ->assertSee('Posted In-System')
            ->assertSee('Stored in the in-system notification record.')
            ->assertSee('Not yet opened')
            ->assertSee('Attempted At')
            ->assertSee('Status Meaning')
            // The in-memory test mailer never sends, and the page says so instead of implying delivery.
            ->assertSee('No email left the server.')
            ->assertDontSee('Delivered')
            // The message body is never part of delivery diagnostics.
            ->assertDontSee('CONFIDENTIAL-MESSAGE-BODY');

        // With a real transport the wording is "accepted", with the limit of that evidence stated.
        $email->forceFill(['provider' => 'smtp'])->save();
        $this->as($this->ictu)->get(route('reports.notifications', ['channel' => 'EMAIL']))
            ->assertOk()
            ->assertSee('Accepted by the configured mail transport.')
            ->assertSee('This does not confirm that it reached the recipient', false)
            ->assertSee('Mail Transport')
            ->assertDontSee('Delivered');
    }

    public function test_a_failed_email_is_recorded_as_failed_with_a_sanitized_reason(): void
    {
        Mail::shouldReceive('html')->once()->andThrow(new RuntimeException(
            'Failed to authenticate on SMTP server with username "mailer.account@cspc.test" using the following authenticators: "LOGIN". '
            .'Authenticator "LOGIN" returned "Expected response code 235 but got code 535, with message 535 5.7.8 Bad credentials". '
            .'password=Sup3rSecret! dsn smtp://mailer.account:Sup3rSecret@smtp.example.test:587'
        ));

        $event = app(NotificationService::class)->send(
            'ACCOUNT_ACCESS_DISABLED',
            collect([$this->borrower]),
            'Your account access has been disabled.',
            $this->borrower
        );

        $email = $event->deliveries()->where('channel', 'EMAIL')->sole();
        $this->assertSame('FAILED', $email->delivery_status);
        $this->assertNull($email->resolved_at);
        $this->assertStringContainsString('535 5.7.8 Bad credentials', $email->provider_response);
        $this->assertStringContainsString('smtp.example.test:587', $email->provider_response);
        $this->assertStringNotContainsString('mailer.account', $email->provider_response);
        $this->assertStringNotContainsString('Sup3rSecret', $email->provider_response);

        // The in-system copy is its own attempt and is not affected by the email failure.
        $this->assertSame('SENT', $event->deliveries()->where('channel', 'SYSTEM')->sole()->delivery_status);

        $this->as($this->ictu)->get(route('reports.notifications', ['status' => 'CURRENT_FAILURE']))
            ->assertOk()
            ->assertSee('Delivery Failed')
            ->assertSee('Failure Reason')
            ->assertSee('535 5.7.8 Bad credentials')
            ->assertSee('The system does not retry automatically.')
            ->assertDontSee('mailer.account')
            ->assertDontSee('Sup3rSecret');
    }

    public function test_a_later_attempt_never_erases_or_rewrites_the_earlier_failure(): void
    {
        Mail::shouldReceive('html')->once()->andThrow(new RuntimeException('Connection could not be established with host "smtp.example.test:587"'));
        $first = app(NotificationService::class)->send('ACCOUNT_ACCESS_DISABLED', collect([$this->borrower]), 'First attempt.', $this->borrower);
        $failed = $first->deliveries()->where('channel', 'EMAIL')->sole();
        $failedRow = (array) DB::table('notification_deliveries')->where('id', $failed->id)->first();

        // Back to the real in-memory test mailer for the next attempt.
        $this->app->forgetInstance('mail.manager');
        Mail::clearResolvedInstance('mail.manager');
        $second = app(NotificationService::class)->send('ACCOUNT_ACCESS_DISABLED', collect([$this->borrower]), 'Second attempt.', $this->borrower);
        $sent = $second->deliveries()->where('channel', 'EMAIL')->sole();

        // There is no retry feature: a new send is a new event with its own records.
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame('SENT', $sent->delivery_status);
        $this->assertSame(1, $sent->attempt_no);
        $this->assertSame($failedRow, (array) DB::table('notification_deliveries')->where('id', $failed->id)->first());
        $this->assertSame(1, NotificationDelivery::query()->actionableFailures()->count());

        // Both attempts are listed separately, each under its own record number.
        $page = $this->as($this->ictu)->get(route('reports.notifications', ['channel' => 'EMAIL', 'per_page' => 28]))->assertOk();
        $ids = $page->viewData('deliveries')->pluck('id')->all();
        $this->assertContains($failed->id, $ids);
        $this->assertContains($sent->id, $ids);
        $page->assertSee('#'.$failed->id)->assertSee('#'.$sent->id);
    }

    public function test_notification_preferences_and_recipients_are_respected(): void
    {
        $this->borrower->forceFill(['notification_preferences' => ['system' => true, 'email' => false]])->save();

        $optional = app(NotificationService::class)->send('RETURN_DUE_TOMORROW', collect([$this->borrower, $this->borrower]), 'Reminder.', $this->borrower);
        // One record per recipient and enabled channel, even if the recipient is passed twice.
        $this->assertSame(['SYSTEM'], $optional->deliveries()->pluck('channel')->all());
        $this->assertCount(0, $this->sentMessages());

        // A required channel is still attempted when the recipient switched it off.
        $required = app(NotificationService::class)->send(
            'ACCOUNT_ACCESS_DISABLED',
            collect([$this->borrower]),
            'Required notice.',
            $this->borrower,
            ['SYSTEM', 'EMAIL'],
            ['EMAIL']
        );
        $this->assertSame(['EMAIL', 'SYSTEM'], $required->deliveries()->pluck('channel')->sort()->values()->all());
        $this->assertCount(1, $this->sentMessages());

        // The real ICTU deactivation action notifies the affected account only.
        $this->as($this->ictu)->patch(route('administration.users.deactivate', $this->officer))->assertSessionHasNoErrors();
        $event = NotificationEvent::query()->where('event_code', 'ACCOUNT_ACCESS_DISABLED')->orderByDesc('id')->firstOrFail();
        $this->assertSame([$this->officer->id], $event->deliveries()->pluck('recipient_user_id')->unique()->values()->all());
        $this->assertSame((new User)->getMorphClass(), $event->source_type);
        $this->assertSame($this->officer->id, (int) $event->source_id);
    }

    public function test_every_record_beyond_the_old_500_limit_is_reachable_and_filterable(): void
    {
        $this->seedDeliveries(620);

        $page = $this->as($this->ictu)->get(route('reports.notifications'))->assertOk();
        $deliveries = $page->viewData('deliveries');
        $this->assertSame(620, $deliveries->total());
        $this->assertSame(7, $deliveries->perPage());
        $this->assertCount(7, $deliveries->items());
        $page->assertSee('620 total records')->assertSee('1–7 of 620');
        // Only the current page is rendered.
        $this->assertSame(7, substr_count($page->getContent(), 'data-delivery-date='));

        // The oldest record sits on the last page, far past the previous cap.
        $last = $this->as($this->ictu)->get(route('reports.notifications', ['page' => $deliveries->lastPage()]))->assertOk();
        $this->assertSame(89, $deliveries->lastPage());
        $last->assertSee('oldest.record@cspc.test')->assertSee('617–620 of 620');

        // Search runs in the database, so it finds an old record without paging to it.
        $search = $this->as($this->ictu)->get(route('reports.notifications', ['q' => 'oldest.record']))->assertOk();
        $this->assertSame(1, $search->viewData('deliveries')->total());
        $search->assertSee('1 matching of 620 total records')->assertSee('oldest.record@cspc.test');
        $this->assertSame(0, $this->as($this->ictu)->get(route('reports.notifications', ['q' => 'no-such-recipient-100%']))->viewData('deliveries')->total());

        $total = fn (array $query): int => $this->as($this->ictu)
            ->get(route('reports.notifications', $query))
            ->assertOk()
            ->viewData('deliveries')
            ->total();

        $this->assertSame(310, $total(['channel' => 'EMAIL']));
        $this->assertSame(310, $total(['channel' => 'SYSTEM']));
        $this->assertSame(20, $total(['status' => 'CURRENT_FAILURE']));
        $this->assertSame(11, $total(['status' => 'HISTORICAL_FAILURE']));
        $this->assertSame(589, $total(['status' => 'SENT']));
        $this->assertSame(20, $total(['status' => 'failed']));
        $this->assertSame(200, $total(['event' => 'PICKUP_SCHEDULED']));
        $this->assertSame(420, $total(['event' => 'REQUEST_SUBMITTED']));
        $this->assertSame(200, $total(['q' => 'pickup scheduled']));
        $this->assertSame(310, $total(['q' => 'in-system']));
        $this->assertSame(1, $total(['date' => 'TODAY']));
        $this->assertSame(1, $total(['date' => 'YESTERDAY']));
        $this->assertSame(7, $total(['date' => '7_DAYS']));
        $this->assertSame(30, $total(['date' => '30_DAYS']));
        $this->assertSame(100, $total(['channel' => 'EMAIL', 'event' => 'PICKUP_SCHEDULED']));
        // Unknown filter values fall back to "all" instead of failing or widening access.
        $this->assertSame(620, $total(['channel' => 'SMS', 'status' => 'NOPE', 'event' => 'x', 'date' => 'x', 'per_page' => 5000]));

        // Page size and paging keep the active filters.
        $paged = $this->as($this->ictu)
            ->get(route('reports.notifications', ['channel' => 'EMAIL', 'per_page' => 28, 'page' => 2]))
            ->assertOk();
        $this->assertSame(28, $paged->viewData('deliveries')->perPage());
        $this->assertSame(2, $paged->viewData('deliveries')->currentPage());
        $this->assertTrue($paged->viewData('deliveries')->getCollection()->every(fn ($delivery) => $delivery->channel === 'EMAIL'));
        $paged->assertSee('29–56 of 310')
            ->assertSee('<option value="EMAIL" selected', false)
            ->assertSee('<option value="28" selected', false)
            ->assertSee('name="page" value="3"', false)
            ->assertSee('name="page" value="1"', false);

        // The legacy SMS view stays separate and is never mixed into current records.
        $this->assertSame(0, $total(['legacy' => 1]));
    }

    public function test_only_ictu_can_open_delivery_records(): void
    {
        $this->seedDeliveries(3);
        $failure = NotificationDelivery::query()->actionableFailures()->first()
            ?? NotificationDelivery::query()->where('channel', 'EMAIL')->firstOrFail();

        foreach ([$this->borrower, $this->officer, $this->head] as $user) {
            foreach ([[], ['legacy' => 1], ['status' => 'failed'], ['q' => 'cspc'], ['channel' => 'EMAIL', 'page' => 2]] as $query) {
                $this->as($user)->get(route('reports.notifications', $query))->assertForbidden();
            }
            $this->as($user)
                ->post(route('reports.notifications.resolve', $failure), ['resolution_note' => 'Not permitted for this role.'])
                ->assertForbidden();
        }

        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->get(route('reports.notifications'))->assertRedirect(route('login'));

        $this->as($this->ictu)->get(route('reports.notifications'))->assertOk();

        // The Head keeps the Operational Audit Trail; ICTU keeps the Technical one.
        $this->as($this->head)->get(route('reports.audit'))->assertOk()->assertSee('Operational Audit Trail');
        $this->as($this->ictu)->get(route('reports.audit'))->assertOk()->assertSee('Technical Audit Trail');
    }

    public function test_older_records_are_masked_on_display_without_being_rewritten_and_expose_no_documents(): void
    {
        $event = NotificationEvent::query()->create([
            'event_code' => 'REQUEST_SUBMITTED',
            'source_type' => 'App\\Models\\BorrowingRequest',
            'source_id' => 41,
            'payload_snapshot_json' => ['message' => 'CONFIDENTIAL request details for the borrower.'],
            'occurred_at' => now(),
        ]);
        $raw = 'Failed to authenticate on SMTP server with username "legacy.mailer@cspc.test" using 2 possible authenticators. '
            .'Authorization: Bearer abcDEF123456789tokenVALUE AUTH PLAIN AGxlZ2FjeS5tYWlsZXIAcGFzc3dvcmQ= api_key=LIVE-KEY-0001 '
            .'Expected response code 250 but got code 550, with message 550 5.1.1 User unknown';
        $delivery = NotificationDelivery::query()->create([
            'notification_event_id' => $event->id,
            'recipient_user_id' => $this->borrower->id,
            'channel' => 'EMAIL',
            'address_snapshot' => $this->borrower->email,
            'attempt_no' => 1,
            'provider' => 'smtp',
            'attempted_at' => now(),
            'delivery_status' => 'FAILED',
            'provider_response' => $raw,
        ]);

        $page = $this->as($this->ictu)->get(route('reports.notifications'))->assertOk();
        $page->assertSee('550 5.1.1 User unknown')
            ->assertSee('BorrowingRequest #41')
            ->assertDontSee('legacy.mailer')
            ->assertDontSee('abcDEF123456789tokenVALUE')
            ->assertDontSee('AGxlZ2FjeS5tYWlsZXIAcGFzc3dvcmQ=')
            ->assertDontSee('LIVE-KEY-0001')
            ->assertDontSee('CONFIDENTIAL request details');

        // No link from a delivery record leads to a request, a file or a document.
        $html = $page->getContent();
        $tableStart = strpos($html, 'notification-delivery-table-card');
        $this->assertNotFalse($tableStart);
        $table = substr($html, $tableStart);
        $this->assertStringNotContainsString('/requests/', $table);
        $this->assertStringNotContainsString('protected-files', $table);
        $this->assertStringNotContainsString('/documents/', $table);

        // The stored historical record is exactly as it was.
        $this->assertSame($raw, $delivery->fresh()->provider_response);
        $this->assertSame($raw, DB::table('notification_deliveries')->where('id', $delivery->id)->value('provider_response'));
    }

    public function test_resolving_a_failure_keeps_the_failed_record_and_its_reason(): void
    {
        $this->seedDeliveries(3);
        $failure = NotificationDelivery::query()->create([
            'notification_event_id' => NotificationEvent::query()->value('id'),
            'recipient_user_id' => $this->borrower->id,
            'channel' => 'EMAIL',
            'address_snapshot' => $this->borrower->email,
            'attempt_no' => 1,
            'provider' => 'smtp',
            'attempted_at' => now(),
            'delivery_status' => 'FAILED',
            'provider_response' => 'SMTP 451: temporary local problem.',
        ]);

        $this->as($this->ictu)
            ->post(route('reports.notifications.resolve', $failure), ['resolution_note' => 'Mail relay restarted and verified.'])
            ->assertSessionHasNoErrors();

        $failure->refresh();
        $this->assertSame('FAILED', $failure->delivery_status);
        $this->assertSame('SMTP 451: temporary local problem.', $failure->provider_response);
        $this->assertNotNull($failure->resolved_at);

        $this->as($this->ictu)->get(route('reports.notifications', ['status' => 'HISTORICAL_FAILURE']))
            ->assertOk()
            ->assertSee('Historical Delivery Failed')
            ->assertSee('SMTP 451: temporary local problem.')
            ->assertSee('Mail relay restarted and verified.');
    }

    public function test_failure_reasons_are_summarised_only_from_what_the_transport_reported(): void
    {
        $summary = fn (?string $text): string => NotificationDelivery::failureSummary($text)['title'];

        $this->assertSame('SMTP Authentication Failed (535)', $summary('Failed to authenticate on SMTP server with username "mailer@cspc.test" using the following authenticators: "LOGIN". Authenticator "LOGIN" returned "Expected response code "235" but got code "535", with message "535-5.7.8 Username and Password not accepted."'));
        $this->assertSame('SMTP Authentication Failed', $summary('Failed to authenticate on SMTP server with username "mailer@cspc.test".'));
        $this->assertSame('DNS Resolution Failed', $summary('Connection could not be established with host "smtp.example.test:587": stream_socket_client(): php_network_getaddresses: getaddrinfo for smtp.example.test failed: Name or service not known'));
        $this->assertSame('TLS Connection Failed', $summary('Unable to connect with STARTTLS.'));
        $this->assertSame('Mail Server Connection Failed', $summary('Connection to "smtp.example.test:587" has been closed unexpectedly.'));
        $this->assertSame('Mail Server Connection Failed', $summary('Connection could not be established with host "smtp.example.test:587": Connection refused'));
        $this->assertSame('Recipient Address Rejected (550)', $summary('SMTP 550: mailbox unavailable.'));
        $this->assertSame('Recipient Address Missing', $summary('Recipient address is not configured.'));
        // Anything unrecognised gets the generic summary; a code is shown only when the text carries one.
        $this->assertSame('Email Delivery Failed', $summary('Something unusual happened in the relay.'));
        $this->assertSame('Email Delivery Failed (451)', $summary('SMTP 451: temporary local problem.'));
        $this->assertSame('Email Delivery Failed', $summary(null));
        // The summary never repeats an account name taken from the error.
        foreach (['title', 'explanation'] as $part) {
            $this->assertStringNotContainsString('mailer', NotificationDelivery::failureSummary('Failed to authenticate on SMTP server with username "mailer@cspc.test".')[$part]);
        }
    }

    public function test_failure_details_show_a_summary_first_and_keep_the_sanitized_text_collapsed(): void
    {
        $event = NotificationEvent::query()->create(['event_code' => 'REQUEST_APPROVED', 'occurred_at' => now()]);
        $raw = 'Failed to authenticate on SMTP server with username "private.mailer@cspc.test" using the following authenticators: "LOGIN", "PLAIN". '
            .'Authenticator "LOGIN" returned "Expected response code "235" but got code "535", with message "535-5.7.8 Username and Password not accepted." password=Sup3rSecret!';
        $make = fn (string $response, bool $resolved = false) => NotificationDelivery::query()->create([
            'notification_event_id' => $event->id,
            'recipient_user_id' => $this->borrower->id,
            'channel' => 'EMAIL',
            'address_snapshot' => $this->borrower->email,
            'attempt_no' => 1,
            'provider' => 'smtp',
            'attempted_at' => now(),
            'delivery_status' => 'FAILED',
            'provider_response' => $response,
            'resolved_at' => $resolved ? now() : null,
            'resolved_by_user_id' => $resolved ? $this->ictu->id : null,
            'resolution_note' => $resolved ? 'Mail host corrected by ICTU.' : null,
        ]);
        $current = $make($raw);
        $historical = $make('Connection could not be established with host "smtp.example.test:587": getaddrinfo for smtp.example.test failed: Name or service not known', true);
        $sent = NotificationDelivery::query()->create([
            'notification_event_id' => $event->id,
            'recipient_user_id' => $this->borrower->id,
            'channel' => 'EMAIL',
            'address_snapshot' => $this->borrower->email,
            'attempt_no' => 1,
            'provider' => 'smtp',
            'attempted_at' => now(),
            'delivery_status' => 'SENT',
            'provider_response' => 'Accepted by the configured Laravel mail transport.',
        ]);

        $page = $this->as($this->ictu)->get(route('reports.notifications', ['status' => 'CURRENT_FAILURE']))->assertOk();
        $html = $page->getContent();

        $page->assertSee('SMTP Authentication Failed (535)')
            ->assertSee('The mail server rejected the sign-in of the system', false)
            ->assertSee('Show Technical Details')
            ->assertSee('Hide Technical Details')
            // Collapsed by default: the control is a closed <details>, never pre-opened.
            ->assertSee('<details class="notification-delivery-technical" data-delivery-technical>', false)
            ->assertDontSee('data-delivery-technical open', false)
            // The full text is available on demand, sanitized, with its SMTP codes intact.
            ->assertSee('535-5.7.8 Username and Password not accepted.')
            ->assertSee('username &quot;[hidden]&quot;', false)
            // The existing resolution controls are unchanged.
            ->assertSee('Resolution note (required)')
            ->assertSee('Mark as Resolved');

        // The unsanitized values exist nowhere in the response: text, hidden elements, attributes or scripts.
        $this->assertStringNotContainsStringIgnoringCase('private.mailer', $html);
        $this->assertStringNotContainsString('Sup3rSecret', $html);
        // Exactly one collapsible block for the one failed row on this page.
        $this->assertSame(1, substr_count($html, 'data-delivery-technical>'));

        $resolvedPage = $this->as($this->ictu)->get(route('reports.notifications', ['status' => 'HISTORICAL_FAILURE']))->assertOk();
        $resolvedPage->assertSee('Historical Delivery Failed')
            ->assertSee('DNS Resolution Failed')
            ->assertSee('Show Technical Details')
            ->assertSee('getaddrinfo for smtp.example.test failed')
            ->assertSee('Mail host corrected by ICTU.')
            ->assertDontSee('Mark as Resolved');

        // Sent records keep their plain result line and get no failure block.
        $sentPage = $this->as($this->ictu)->get(route('reports.notifications', ['status' => 'SENT']))->assertOk();
        $sentPage->assertSee('Transport Result')->assertDontSee('Show Technical Details')->assertDontSee('data-delivery-failure', false);

        // Marking the current failure as resolved still works and changes nothing but the resolution fields.
        $this->as($this->ictu)
            ->post(route('reports.notifications.resolve', $current), ['resolution_note' => 'Sending account re-authorised.'])
            ->assertSessionHasNoErrors();
        $this->assertNotNull($current->fresh()->resolved_at);
        $this->assertSame('FAILED', $current->fresh()->delivery_status);
        $this->assertSame($raw, $current->fresh()->provider_response);
        $this->assertSame($historical->provider_response, $historical->fresh()->provider_response);
        $this->assertSame('SENT', $sent->fresh()->delivery_status);
    }

    /**
     * Insert delivery rows directly: one per day going back from today, the
     * oldest email carrying a recognisable address. Even rows are EMAIL, odd
     * rows SYSTEM; the first 200 belong to PICKUP_SCHEDULED.
     */
    private function seedDeliveries(int $count): void
    {
        $pickup = NotificationEvent::query()->create(['event_code' => 'PICKUP_SCHEDULED', 'occurred_at' => now()]);
        $submitted = NotificationEvent::query()->create(['event_code' => 'REQUEST_SUBMITTED', 'occurred_at' => now()]);
        $now = now(config('app.timezone', 'Asia/Manila'))->setTime(12, 0);
        $rows = [];

        for ($i = 0; $i < $count; $i++) {
            $at = $now->copy()->subDays($i);
            $email = $i % 2 === 0;
            // 31 failed emails near the old end of the table; 11 of them already resolved.
            $failed = $email && $i >= $count - 62;
            $resolved = $failed && $i >= $count - 22;

            $rows[] = [
                'notification_event_id' => $i < 200 ? $pickup->id : $submitted->id,
                'recipient_user_id' => $this->borrower->id,
                'channel' => $email ? 'EMAIL' : 'SYSTEM',
                'address_snapshot' => $i === $count - 2 ? 'oldest.record@cspc.test' : ($email ? $this->borrower->email : (string) $this->borrower->id),
                'attempt_no' => 1,
                'provider' => $email ? 'smtp' : 'system',
                'attempted_at' => $at,
                'delivery_status' => $failed ? 'FAILED' : 'SENT',
                'provider_response' => $failed ? 'SMTP 550: mailbox unavailable.' : 'Accepted.',
                'resolved_at' => $resolved ? $at : null,
                'resolved_by_user_id' => $resolved ? $this->ictu->id : null,
                'resolution_note' => $resolved ? 'Resolved in test data.' : null,
                'created_at' => $at,
                'updated_at' => $at,
            ];
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('notification_deliveries')->insert($chunk);
        }
    }

    /** @return list<\Illuminate\Mail\SentMessage> */
    private function sentMessages(): array
    {
        return app('mailer')->getSymfonyTransport()->messages()->all();
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
