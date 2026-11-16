<?php

namespace App\Console\Commands;

use App\Services\RequestNumberTransition;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

// This is the command for previewing and applying a verified Control No. mapping.
class ReviewRequestNumbers extends Command
{
    protected $signature = 'requests:review-numbers {--output= : Save the read-only preview as a new JSON file} {--apply : Apply the exact reviewed mapping and activate numbering} {--expect= : Fingerprint from the reviewed preview} {--rollback= : Reverse one unchanged applied batch}';
    protected $description = 'Preview academic-year request numbers without changing records; apply only an exact verified mapping.';

    public function handle(RequestNumberTransition $transition): int
    {
        try {
            if ($batch = $this->option('rollback')) {
                $transition->rollback((string) $batch); $this->info('The numbering batch was reversed.'); return self::SUCCESS;
            }
            if ($this->option('apply')) {
                $batch = $transition->apply((string) $this->option('expect'));
                $this->info('Academic-year numbering activated. Reversible batch: '.$batch); return self::SUCCESS;
            }
            $preview = $transition->preview();
            if ($output = $this->option('output')) {
                if (file_exists($output) || !is_dir(dirname($output))) {
                    $this->error('Choose a new output filename in an existing directory.'); return self::FAILURE;
                }
                file_put_contents($output, json_encode($preview, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            }
            $this->line('Read-only preview: '.count($preview['rows']).' records; '.count($preview['errors']).' unresolved records.');
            $this->line('Fingerprint: '.$preview['fingerprint']);
            if (!$preview['activation_ready']) $this->warn('Activation is not ready: configure an academic year covering the current date.');
            if ($preview['errors']) { $this->table(['Request ID','Issue'], array_map(fn ($row) => [$row['request_id'], $row['reason']], $preview['errors'])); return self::FAILURE; }
            $this->line('No records changed. Review the complete mapping before using --apply --expect=FINGERPRINT.');
            return self::SUCCESS;
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) foreach ($messages as $message) $this->error($message);
            return self::FAILURE;
        }
    }
}
