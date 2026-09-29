<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationEmailConsistencyTest extends TestCase
{
    #[Test]
    public function approval_notification_uses_plain_language_and_includes_the_pickup_schedule(): void
    {
        $workflow = (string) file_get_contents(app_path('Services/RequestWorkflowService.php'));
        $notifications = (string) file_get_contents(app_path('Services/NotificationService.php'));

        $this->assertStringContainsString('Your borrowing request {$request->request_no} has been approved. Pickup is scheduled for', $workflow);
        $this->assertStringContainsString("['Pickup Schedule']", $notifications);
        $this->assertStringContainsString("['Pickup Deadline']", $notifications);
        $this->assertStringContainsString("=> 'Approved Items'", $notifications);
        $this->assertStringNotContainsString("['Inventory Status']", $notifications);
        $this->assertStringNotContainsString("['Claim Until']", $notifications);
        $this->assertStringNotContainsString('valid SPMU operating window', $notifications);
        $this->assertStringNotContainsString('SPMU Operational Calendar', $notifications);
        $this->assertStringNotContainsString('approved quantities are reserved', strtolower($notifications));
    }

    #[Test]
    public function automatic_pickup_activation_does_not_send_a_second_pickup_email(): void
    {
        $source = (string) file_get_contents(app_path('Services/CustodyService.php'));
        $start = strpos($source, 'public function ensurePickupRecord(');
        $end = strpos($source, 'public function expirePickupWindows(', $start ?: 0);

        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        $method = substr($source, $start, $end - $start);

        $this->assertStringContainsString('REQUEST_APPROVED notification', $method);
        $this->assertStringNotContainsString("notifications->send(\n                    'PICKUP_SCHEDULED'", $method);
    }

    #[Test]
    public function pickup_schedule_event_is_reserved_for_schedule_updates(): void
    {
        $notifications = (string) file_get_contents(app_path('Services/NotificationService.php'));

        $this->assertStringContainsString("'PICKUP_SCHEDULED' => 'Pickup Schedule Updated'", $notifications);
        $this->assertStringContainsString('Your pickup schedule has been updated.', $notifications);
    }

    #[Test]
    public function items_released_is_an_in_system_confirmation_only(): void
    {
        $source = (string) file_get_contents(app_path('Services/CustodyService.php'));
        $needle = "'ITEMS_RELEASED'";
        $start = strpos($source, $needle);

        $this->assertNotFalse($start);

        $block = substr($source, $start, 1100);
        $this->assertStringContainsString("['SYSTEM']", $block);
        $this->assertStringNotContainsString("['SYSTEM', 'EMAIL']", $block);
        $this->assertStringContainsString('Please return them on or before', $block);
    }

    #[Test]
    public function return_reminders_use_borrower_facing_wording(): void
    {
        $command = (string) file_get_contents(app_path('Console/Commands/ProcessOperationalDeadlines.php'));
        $notifications = (string) file_get_contents(app_path('Services/NotificationService.php'));

        $this->assertStringContainsString('Reminder: the borrowed items under', $command);
        $this->assertStringContainsString("'RETURN_DUE_TODAY' => 'Return Due Today'", $notifications);
        $this->assertStringContainsString("'RETURN_DUE_TOMORROW' => 'Return Due Tomorrow'", $notifications);
        $this->assertStringNotContainsString('effective SPMU operational return date', $command);
    }

    #[Test]
    public function email_template_avoids_internal_system_phrasing(): void
    {
        $notifications = (string) file_get_contents(app_path('Services/NotificationService.php'));

        $this->assertStringContainsString('Sent {$this->escape($footerTimestamp)}.', $notifications);
        $this->assertStringNotContainsString('Notification recorded', $notifications);
        $this->assertStringNotContainsString('Relevant Items', $notifications);
    }


    #[Test]
    public function in_system_notification_titles_use_friendly_labels(): void
    {
        $view = (string) file_get_contents(resource_path('views/notifications/index.blade.php'));

        $this->assertStringContainsString("'PICKUP_SCHEDULED' => 'Pickup Schedule Updated'", $view);
        $this->assertStringContainsString("'PROPERTY_ACCOUNTABILITY_HEAD_DECISION_RECORDED' => 'Property Accountability Decision'", $view);
        $this->assertStringContainsString("'PAYMENT_VERIFIED' => 'Payment Confirmed'", $view);
        $this->assertStringNotContainsString("'A system update was recorded.'", $view);
    }

    #[Test]
    public function notification_email_has_no_pdf_attachment_layer(): void
    {
        $source = (string) file_get_contents(app_path('Services/NotificationService.php'));

        $this->assertStringContainsString('Mail::html', $source);
        $this->assertStringNotContainsString('->attach(', $source);
        $this->assertStringNotContainsString('attachData(', $source);
    }

    #[Test]
    public function late_return_notice_keeps_total_amount_out_of_the_notice(): void
    {
        $notice = (string) file_get_contents(resource_path('views/documents/accountability/late-return-notice.blade.php'));

        $this->assertStringContainsString('Official Late-Return Fee Rate', $notice);
        $this->assertStringNotContainsString('Assessed Amount', $notice);
        $this->assertStringContainsString('Separate Late Return Billing Statement', $notice);
    }

    #[Test]
    public function transaction_emails_use_plain_language_for_current_workflows(): void
    {
        $notifications = (string) file_get_contents(app_path('Services/NotificationService.php'));

        $this->assertStringContainsString("'REQUEST_EXPIRED' => 'Borrowing Request Expired'", $notifications);
        $this->assertStringContainsString("'LATE_RETURN_FOR_HEAD_APPROVAL' => 'Late Return Ready for Review'", $notifications);
        $this->assertStringContainsString("'PROPERTY_ACCOUNTABILITY_PAYMENT_REQUIRED' => 'Property Accountability Payment Required'", $notifications);
        $this->assertStringContainsString("'PROPERTY_ACCOUNTABILITY_COMPLIANCE_VERIFIED' => 'Required Action Verified'", $notifications);
        $this->assertStringContainsString('View details:', $notifications);

        $this->assertStringNotContainsString('Step 2 discrepancy', $notifications);
        $this->assertStringNotContainsString('wet-sign', $notifications);
        $this->assertStringNotContainsString('RSLDDP Status: For External Processing', $notifications);
        $this->assertStringNotContainsString('generated pickup documents were invalidated', $notifications);
        $this->assertStringNotContainsString('reservation was restored', $notifications);
        $this->assertStringNotContainsString('Choose Request Reschedule if you still need the items', $notifications);
    }

    #[Test]
    public function university_style_email_does_not_repeat_reference_or_next_step(): void
    {
        $notifications = (string) file_get_contents(app_path('Services/NotificationService.php'));

        $this->assertStringContainsString('if ($reference && trim((string) $value) === trim((string) $reference))', $notifications);
        $this->assertStringNotContainsString('{$nextBlock}', $notifications);
        $this->assertStringNotContainsString('What happens next?', $notifications);
        $this->assertStringContainsString("array_slice(\$data['items'], 0, 5)", $notifications);
        $this->assertStringContainsString('AccessClassification::SpmuHead =>', $notifications);
        $this->assertStringContainsString('AccessClassification::SpmuOfficer =>', $notifications);
    }

    #[Test]
    public function incident_notification_does_not_duplicate_return_inspection_email(): void
    {
        $custody = (string) file_get_contents(app_path('Services/CustodyService.php'));
        $notifications = (string) file_get_contents(app_path('Services/NotificationService.php'));
        $accountability = (string) file_get_contents(app_path('Http/Controllers/AccountabilityController.php'));

        $this->assertStringContainsString("\$openedIncidents->isEmpty() ? ['SYSTEM', 'EMAIL'] : ['SYSTEM']", $custody);
        $this->assertStringContainsString("'ACCOUNTABILITY_OPENED'", $custody);
        $this->assertStringContainsString("'LATE_RETURN_RESOLVED_WITHOUT_CHARGE'", $notifications);
        $this->assertStringContainsString("'LATE_RETURN_RESOLVED_WITHOUT_CHARGE'", $accountability);
    }

    #[Test]
    public function email_copy_keeps_each_transaction_update_to_summary_details_and_one_next_step(): void
    {
        $notifications = (string) file_get_contents(app_path('Services/NotificationService.php'));

        $this->assertStringContainsString('Your scheduled pickup was missed. SPMU will review whether the approved borrowing can proceed or must be cancelled.', $notifications);
        $this->assertStringContainsString('Your return date was adjusted because SPMU is unavailable on the original return date.', $notifications);
        $this->assertStringContainsString('A Billing Statement has been issued for this case.', $notifications);
        $this->assertStringContainsString('Your linen items have been released. On return, bring the linen and the same Laundry Form to the Laundry Area first.', $notifications);
        $this->assertStringContainsString('An administrative sanction has been recorded. The reason and sanction are indicated below.', $notifications);
    }

}
