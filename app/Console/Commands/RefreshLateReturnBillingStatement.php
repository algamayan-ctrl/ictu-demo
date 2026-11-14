<?php

namespace App\Console\Commands;

use App\Models\BillingStatement;
use App\Models\GeneratedDocument;
use App\Services\AuditService;
use App\Services\DocumentService;
use Illuminate\Console\Command;

class RefreshLateReturnBillingStatement extends Command
{
    protected $signature = 'spmu:refresh-late-return-billing {billingNo : Billing number to reissue using the current controlled layout}';

    protected $description = 'Reissue one Late Return Billing Statement after a controlled print-layout revision';

    public function handle(DocumentService $documents, AuditService $audit): int
    {
        $billing = BillingStatement::query()
            ->with('lines')
            ->where('billing_no', (string) $this->argument('billingNo'))
            ->first();

        if (! $billing) {
            $this->error('No Billing Statement matched that billing number.');

            return self::FAILURE;
        }

        if ($billing->status === 'VOID') {
            $this->error('A voided Billing Statement cannot be reissued.');

            return self::FAILURE;
        }

        if (! $billing->isLateReturnBilling()) {
            $this->error('This command only refreshes Late Return Billing Statements.');

            return self::FAILURE;
        }

        $previous = GeneratedDocument::query()
            ->where('subject_type', BillingStatement::class)
            ->where('subject_id', $billing->id)
            ->where('document_type', 'BILLING_STATEMENT')
            ->where('status', 'FINAL')
            ->first();

        $replacement = $documents->reissueLateReturnBillingStatement($billing);

        $audit->record(
            'LATE_RETURN_BILLING_STATEMENT_REISSUED',
            $billing,
            reason: 'Controlled layout revised for the cashier copy.',
            before: [
                'generated_document_id' => $previous?->id,
                'revision' => $previous?->version_no,
            ],
            after: [
                'generated_document_id' => $replacement->id,
                'revision' => $replacement->version_no,
            ],
        );

        $this->info("Reissued {$billing->billing_no} as Rev. {$replacement->version_no}.");

        return self::SUCCESS;
    }
}
