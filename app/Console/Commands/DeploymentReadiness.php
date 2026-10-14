<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class DeploymentReadiness extends Command
{
    protected $signature = 'spmu:deployment-readiness {--json : Print non-secret configuration checks}';

    protected $description = 'Read-only configuration checks; does not certify Cloud or institutional deployment';

    public function handle(): int
    {
        $checks = [
            'PHP 8.4 runtime' => PHP_MAJOR_VERSION === 8 && PHP_MINOR_VERSION === 4,
            'Debug disabled' => config('app.debug') === false,
            'HTTPS application URL' => str_starts_with((string) config('app.url'), 'https://'),
            'Valid application key' => str_starts_with((string) config('app.key'), 'base64:')
                && strlen((string) base64_decode(substr((string) config('app.key'), 7), true)) === 32,
            'Shared sessions' => in_array(config('session.driver'), ['database', 'redis'], true),
            'Secure session cookie' => config('session.secure') === true,
            'Shared cache and scheduler locks' => in_array(config('cache.default'), ['database', 'redis'], true),
            'Durable queue connection' => in_array(config('queue.default'), ['database', 'redis'], true),
            'Email transport' => ! in_array(config('mail.default'), ['array', 'log', null, ''], true),
            'Private persistent evidence disk' => app(\App\Services\ProtectedFileService::class)->writeDisk() === 's3'
                && config('filesystems.disks.s3.visibility') === 'private'
                && class_exists(\League\Flysystem\AwsS3V3\AwsS3V3Adapter::class),
            'MariaDB 11.4 connection' => false,
        ];
        if (config('database.default') === 'mysql') {
            try {
                $version = (string) DB::selectOne('SELECT VERSION() AS server_version')->server_version;
                $checks['MariaDB 11.4 connection'] = str_starts_with($version, '11.4.') && str_contains($version, 'MariaDB');
            } catch (Throwable) {
                // Connection errors may include credential/host details. Report only status.
            }
        }
        $passed = ! in_array(false, $checks, true);
        $result = ['scope' => 'configuration only', 'gate' => $passed ? 'PASS' : 'NOT READY', 'checks' => $checks,
            'not_verified' => ['Actual Cloud build, replicas, scheduler and workers', 'Live OAuth and email delivery',
                'Private bucket operations, profile-image persistence and recovery', 'ICTU nine deliverables and institutional hosting approval',
                'ISO/IEC 25010 user evaluation and real operational KPI measurements']];
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line('Configuration gate: '.$result['gate']);
            $this->table(['Check', 'Result'], collect($checks)->map(fn ($passed, $name) => [$name, $passed ? 'PASS' : 'NOT READY'])->values()->all());
            $this->line('This read-only check does not certify live services, recovery or institutional approval.');
        }

        return $passed ? self::SUCCESS : self::FAILURE;
    }
}
