<?php

namespace App\Http\Controllers;

use App\Enums\AccessClassification;
use App\Enums\UserRole;
use App\Models\AuditEvent;
use App\Models\AcademicPeriod;
use App\Models\BillingStatement;
use App\Models\BorrowerViolation;
use App\Models\BorrowingRequest;
use App\Models\CustodyTransaction;
use App\Models\Incident;
use App\Models\InventoryItem;
use App\Models\NotificationDelivery;
use App\Models\NotificationEvent;
use App\Models\OverdueCase;
use App\Models\Penalty;
use App\Models\Sanction;
use App\Models\SystemSetting;
use App\Models\User;
use App\Reports\ReportCatalogue;
use App\Reports\ReportExportOptions;
use App\Reports\ReportFilters;
use App\Services\AuditRecoveryService;
use App\Reports\ReportRecordTable;
use App\Services\AuditService;
use App\Services\AllTransactionsService;
use App\Services\InventoryService;
use App\Services\ReportService;
use App\Services\ReportingPeriodService;
use App\Support\RequestRecordAccess;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View|RedirectResponse|JsonResponse
    {
        abort_unless(
            $request->user()?->access_classification === AccessClassification::SpmuHead,
            403
        );

        /*
         * Analytics is its own module. Bookmarks that still carry the old
         * ?tab=analytics are forwarded there with the period they pointed at,
         * rather than silently showing Reports.
         */
        if (strtolower((string) $request->input('tab')) === 'analytics') {
            return redirect()->route('analytics.index', [
                'academic_period' => $request->input('academic_period', 'month'),
            ]);
        }

        /*
         * The Borrower picker is cascading, not global. It asks this same
         * endpoint for borrowers that actually have matching report records
         * inside the selected Reporting Period + Division + Office / Unit.
         */
        if ($request->boolean('borrower_options')) {
            return $this->borrowerOptions($request);
        }

        /* All Transactions is the unfiltered landing page, not an official report type. */
        if (! $request->filled('report') || $request->input('report') === 'all-transactions') {
            $timeline = app(AllTransactionsService::class)->page($request, $request->user());

            return view('reports.all-transactions', $timeline);
        }

        $scope = $this->resolveScope($request, $request->input('report'));
        $showPreview = true;
        $dataset = null;
        $table = null;
        $previewFailed = false;

        /*
         * Show actual records for the resolved scope on first load. Rendering
         * does not record approval or imply that an operator reviewed them.
         * CSV and print resolve the same filters independently.
         */
        if ($showPreview) {
            try {
                $dataset = app(ReportService::class)->generate($scope['filters'], $request->user());
            } catch (\Throwable $exception) {
                report($exception);
                $previewFailed = true;
            }

            if ($dataset !== null) {
                $table = ReportRecordTable::fromRequest(
                    $request,
                    $dataset->columns,
                    /* "View Record" is offered only for requests this user may actually open. */
                    RequestRecordAccess::withoutUnviewableLinks(
                        $dataset->rows,
                        $request->user(),
                        $request->session()->get('active_workspace')
                    ),
                    route('reports.index'),
                    array_merge($scope['filters']->toQuery(), ['generated' => '1'])
                );
            }
        }

        $reportingPeriods = [];
        foreach (ReportingPeriodService::REPORT_PERIODS as $value => $label) {
            /* An unconfigured Previous Semester has no dates to offer, so the builder reports it as not set. */
            $unavailable = $value === 'previous_semester' && ! app(ReportingPeriodService::class)
                ->previousSemester($scope['academicPeriods'], $scope['activeAcademicPeriod']);

            $range = ($value === 'custom' || $unavailable) ? null : $this->resolveReportingPeriod(
                Request::create('/reports', 'GET', ['academic_period' => $value]),
                $scope['academicPeriods'],
                $scope['activeAcademicPeriod']
            );
            $reportingPeriods[$value] = [
                'label' => $label,
                'start_date' => $range ? $range[0]->toDateString() : null,
                'end_date' => $range ? $range[1]->toDateString() : null,
            ];
        }

        return view('reports.detailed', [
            'reportingPeriods' => $reportingPeriods,
            'activeTab' => 'reports',
            'reportGroups' => ReportCatalogue::grouped(),
            'selectedReport' => $scope['report'],
            'selectedReportMeta' => ReportCatalogue::definition($scope['report']),
            'showPreview' => $showPreview,
            'previewFailed' => $previewFailed,
            'dataset' => $dataset,
            'table' => $table,
            'records' => $table?->records,
            'reportFilters' => $scope['filters'],
            'exportType' => ReportCatalogue::definition($scope['report'])['export'],
            'from' => $scope['from'],
            'to' => $scope['to'],
            'academicPeriods' => $scope['academicPeriods'],
            'activeAcademicPeriod' => $scope['activeAcademicPeriod'],
            'selectedAcademicPeriod' => $scope['selectedAcademicPeriod'],
            'periodSelection' => $scope['periodSelection'],
        ]);
    }

    /**
     * The printable copy of a generated report.
     *
     * Print resolves its scope through resolveScope() exactly as the screen
     * and the CSV do, so the printed page is the same report with the same
     * filters — the whole record set rather than one page of it.
     */
    public function print(Request $request, string $type): View
    {
        abort_unless(
            $request->user()?->access_classification === AccessClassification::SpmuHead,
            403
        );

        abort_unless(ReportCatalogue::has($type) || ReportCatalogue::has(
            ReportCatalogue::resolveKey($type)
        ), 404);

        $scope = $this->resolveScope($request, $type);

        $options = ReportExportOptions::fromRequest($request, $scope['report']);

        return view('reports.print', [
            'dataset' => app(ReportService::class)->generate($scope['filters'], $request->user()),
            'options' => $options->contentToggles(),
            'exportOptions' => $options,
        ]);
    }

    /**
     * Return borrowers that exist inside the current upstream report scope.
     *
     * Cascade order: Reporting Period -> Division -> Office / Unit -> Borrower.
     * Borrower itself and downstream report filters are ignored while building
     * this option list so "All borrowers" always means all borrowers within
     * that current upstream scope.
     *
     * The endpoint is intentionally searchable and capped so a large borrower
     * population does not render hundreds of options in the browser at once.
     */
    private function borrowerOptions(Request $request): JsonResponse
    {
        $report = ReportCatalogue::resolveKey($request->input('report'));

        if (! array_key_exists('borrower', ReportCatalogue::filtersFor($report))) {
            return response()->json([
                'options' => [],
                'count' => 0,
                'shown' => 0,
                'has_more' => false,
            ]);
        }

        $scopeRequest = $request->duplicate();

        foreach (array_keys(ReportCatalogue::filtersFor($report)) as $filterKey) {
            if (! in_array($filterKey, ['division', 'unit'], true)) {
                $scopeRequest->query->remove($filterKey);
            }
        }

        $scopeRequest->query->remove('borrower_options');
        $scopeRequest->query->remove('generated');
        $scopeRequest->query->remove('q');
        $scopeRequest->query->remove('selected_borrower');
        $scopeRequest->query->set('report', $report);

        $scope = $this->resolveScope($scopeRequest, $report);
        $dataset = app(ReportService::class)->generate($scope['filters'], $request->user());

        $ids = $dataset->rows
            ->pluck('_borrower_user_id')
            ->filter(fn ($id): bool => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return response()->json([
                'options' => [],
                'count' => 0,
                'shown' => 0,
                'has_more' => false,
            ]);
        }

        $search = trim((string) $request->input('q', ''));
        $search = mb_substr($search, 0, 100);
        $selectedBorrower = (int) $request->input('selected_borrower', 0);
        $limit = 50;

        $query = User::query()->whereIn('id', $ids->all());

        if ($search !== '') {
            $query->where(function ($borrowers) use ($search): void {
                $borrowers
                    ->where('full_name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        $count = (clone $query)->count();

        $users = $query
            ->orderBy('full_name')
            ->orderBy('email')
            ->limit($limit)
            ->get(['id', 'full_name', 'email']);

        /*
         * When the picker is opened without a search, preserve a currently
         * selected borrower even when they fall outside the first 50 names.
         * This lets the browser validate the selection after an upstream
         * Division / Office / period change without falsely resetting it.
         */
        if (
            $search === ''
            && $selectedBorrower > 0
            && $ids->contains($selectedBorrower)
            && ! $users->contains('id', $selectedBorrower)
        ) {
            $selected = User::query()
                ->whereKey($selectedBorrower)
                ->first(['id', 'full_name', 'email']);

            if ($selected) {
                $users->prepend($selected);
            }
        }

        $options = $users
            ->map(function (User $user): array {
                $name = trim((string) $user->full_name);
                $email = trim((string) $user->email);

                return [
                    'value' => (string) $user->id,
                    'name' => $name !== '' ? $name : 'Borrower #'.$user->id,
                    'email' => $email,
                ];
            })
            ->values();

        return response()->json([
            'options' => $options,
            'count' => $count,
            'shown' => $options->count(),
            'has_more' => $count > $limit,
        ]);
    }

    /**
     * Resolve one reporting scope: period, academic period, and validated
     * filters for a report key.
     *
     * Screen, CSV and print all call this, which is what guarantees the three
     * outputs describe the same records. The reporting period itself comes
     * from ReportingPeriodService, the resolver Analytics also uses.
     *
     * @return array<string, mixed>
     */
    private function resolveScope(Request $request, ?string $reportKey): array
    {
        $academicPeriods = AcademicPeriod::query()
            ->orderByRaw("CASE WHEN status = 'ACTIVE' THEN 0 ELSE 1 END")
            ->orderByDesc('start_date')
            ->get();

        $activeAcademicPeriod = $academicPeriods->first(
            fn (AcademicPeriod $period): bool => $period->status === 'ACTIVE'
        );

        [
            $from,
            $to,
            $selectedAcademicPeriod,
            $periodSelection,
        ] = $this->resolveReportingPeriod($request, $academicPeriods, $activeAcademicPeriod);

        /*
         * Links made before the builder existed carry explicit from/to dates
         * and no period selection. Those still resolve to the range they
         * asked for instead of silently switching to the current month.
         */
        if (! $request->filled('academic_period') && $request->filled('from')) {
            $from = Carbon::parse($request->input('from'))->startOfDay();
            $to = Carbon::parse($request->input('to', now()->toDateString()))->endOfDay();
            $selectedAcademicPeriod = null;
        }

        $report = ReportCatalogue::resolveKey($reportKey);

        return [
            'report' => $report,
            'from' => $from,
            'to' => $to,
            'periodSelection' => $periodSelection,
            'academicPeriods' => $academicPeriods,
            'activeAcademicPeriod' => $activeAcademicPeriod,
            'selectedAcademicPeriod' => $selectedAcademicPeriod,
            'filters' => ReportFilters::fromRequest(
                $request,
                $report,
                $from,
                $to,
                $periodSelection,
                $selectedAcademicPeriod
            ),
        ];
    }

    /**
     * Delegates to the shared service so Reports and Analytics always read
     * the same dates from the same selection.
     *
     * @return array{0:Carbon,1:Carbon,2:?AcademicPeriod,3:string}
     */
    private function resolveReportingPeriod(
        Request $request,
        $academicPeriods,
        ?AcademicPeriod $activeAcademicPeriod
    ): array {
        return app(ReportingPeriodService::class)->resolveForReport(
            $request,
            $academicPeriods,
            $activeAcademicPeriod
        );
    }

    public function export(
        Request $request,
        string $type,
        InventoryService $inventory
    ): Response|StreamedResponse {
        abort_unless(
            $request->user()?->access_classification === AccessClassification::SpmuHead,
            403
        );

        /*
         * Every catalogue-backed formal report is exportable automatically.
         * Keep only the remaining legacy/administrative export endpoints in
         * the explicit list so adding a new formal report cannot leave its
         * Export / Print button pointing at a controller 404.
         */
        $legacyExportTypes = [
            'overdue',
            'penalty',
            'billing',
            'sanction',
            'compliance',
            'notification',
            'audit',
        ];

        abort_unless(
            ReportCatalogue::has($type) || in_array($type, $legacyExportTypes, true),
            404
        );

        /*
         * A migrated report exports the very dataset the screen rendered:
         * same builder, same filters, same rows. Only the rendering differs.
         */
        if (ReportCatalogue::has($type) && ReportCatalogue::isMigrated($type)) {
            return $this->exportDataset($request, $type);
        }

        if (in_array($type, ['notification', 'audit'], true)) {
            abort_unless($request->user()->hasRole(UserRole::Spmu), 403);
        }

        $from = Carbon::parse($request->input('from', now()->subDays(30)->toDateString()))->startOfDay();
        $to = Carbon::parse($request->input('to', now()->toDateString()))->endOfDay();

        return response()->streamDownload(function () use ($type, $from, $to, $inventory): void {
            $output = fopen('php://output', 'w');

            if ($type === 'inventory') {
                fputcsv($output, ['Description', 'Category', 'Unit', 'Total', 'Available', 'Reserved', 'Issued/On Custody', 'Laundry', 'Damaged/Maintenance', 'Lost', 'Stolen', 'Destroyed', 'Condemned']);
                InventoryItem::query()->with(['category', 'unit'])->where('active', true)->orderBy('unique_description')->each(function ($item) use ($output, $inventory): void {
                    $balance = $inventory->availability($item, now()->subYears(10)->startOfDay(), now()->addYears(10)->endOfDay());
                    fputcsv($output, [
                        $item->unique_description,
                        $item->category->category_name,
                        $item->unit->unit_name,
                        $balance['total'],
                        $balance['available'],
                        $balance['allocated'],
                        $balance['borrowed'],
                        $balance['laundry'],
                        $balance['damaged_maintenance'],
                        $balance['lost'],
                        $balance['stolen'],
                        $balance['destroyed'],
                        $balance['condemned'],
                    ]);
                });
            } elseif ($type === 'borrowing') {
                fputcsv($output, ['Request No.', 'Borrower', 'Status', 'Schedule Date', 'Return Date', 'Created', 'SPMU Approved']);

                BorrowingRequest::query()
                    ->with(['borrower', 'currentVersion', 'custody'])
                    ->whereBetween('created_at', [$from, $to])
                    ->each(function (BorrowingRequest $row) use ($output): void {
                        [
                            $displayStatus,
                            $displayLabel,
                        ] = $this->requestReportStatus($row);

                        fputcsv($output, [
                            $row->request_no,
                            $row->borrower->full_name,
                            $displayLabel
                                ?: str($displayStatus)
                                    ->replace('_', ' ')
                                    ->title(),
                            $row->currentVersion?->schedule_date?->toDateString()
                                ?? $row->currentVersion?->needed_from?->toDateString(),
                            $row->currentVersion?->return_date?->toDateString()
                                ?? $row->currentVersion?->return_due_at?->toDateString(),
                            $row->created_at,
                            $row->final_approved_at,
                        ]);
                    });
            } elseif ($type === 'utilization') {
                fputcsv($output, ['Description', 'Released Quantity']);
                InventoryItem::query()
                    ->leftJoin('request_items', 'request_items.inventory_item_id', '=', 'inventory_items.id')
                    ->leftJoin('custody_lines', 'custody_lines.request_item_id', '=', 'request_items.id')
                    ->leftJoin('custody_transactions', 'custody_transactions.id', '=', 'custody_lines.custody_transaction_id')
                    ->where(function ($query) use ($from, $to): void {
                        $query->whereNull('custody_transactions.released_at')->orWhereBetween('custody_transactions.released_at', [$from, $to]);
                    })
                    ->selectRaw('inventory_items.unique_description, COALESCE(SUM(custody_lines.actual_released_quantity), 0) AS used_quantity')
                    ->groupBy('inventory_items.id', 'inventory_items.unique_description')
                    ->orderByDesc('used_quantity')
                    ->each(fn ($row) => fputcsv($output, [$row->unique_description, $row->used_quantity]));
            } elseif ($type === 'overdue') {
                fputcsv($output, ['Custody No.', 'Borrower', 'Expected Return Date', 'Late Fee Rate', 'Accrued Amount', 'Status']);
                OverdueCase::query()->with(['custody', 'borrower'])->whereBetween('created_at', [$from, $to])->each(fn ($row) => fputcsv($output, [
                    $row->custody->custody_no,
                    $row->borrower->full_name,
                    $row->custody->due_at?->toDateString(),
                    $row->rate_snapshot,
                    $row->accrued_amount,
                    $row->status,
                ]));
            } elseif ($type === 'penalty') {
                fputcsv($output, ['Borrower ID', 'Financial Charge Type', 'Rate', 'Amount', 'Status', 'Assessed']);
                Penalty::query()->whereBetween('assessed_at', [$from, $to])->each(fn ($row) => fputcsv($output, [
                    $row->borrower_user_id,
                    $row->penalty_type,
                    $row->rate_snapshot,
                    $row->amount,
                    $row->status,
                    $row->assessed_at,
                ]));
            } elseif ($type === 'billing') {
                fputcsv($output, ['Billing No.', 'Borrower ID', 'Amount', 'Status', 'Issued']);
                BillingStatement::query()->whereBetween('issued_at', [$from, $to])->each(fn ($row) => fputcsv($output, [
                    $row->billing_no,
                    $row->borrower_user_id,
                    $row->total_amount,
                    $row->status,
                    $row->issued_at,
                ]));
            } elseif ($type === 'sanction') {
                fputcsv($output, ['Borrower ID', 'Offense No.', 'Sanction', 'Academic Period ID', 'Status', 'Confirmed']);
                Sanction::query()->whereBetween('confirmed_at', [$from, $to])->each(fn ($row) => fputcsv($output, [
                    $row->borrower_user_id,
                    $row->offense_no,
                    $row->sanction_label,
                    $row->academic_period_id,
                    $row->status,
                    $row->confirmed_at,
                ]));
            } elseif ($type === 'notification') {
                fputcsv($output, ['Time', 'Event', 'Channel', 'Recipient User ID', 'Address', 'Provider', 'Status', 'Response']);
                NotificationDelivery::query()->with('event')->whereBetween('attempted_at', [$from, $to])->each(fn ($row) => fputcsv($output, [
                    $row->attempted_at,
                    $row->event->event_code,
                    $row->channel,
                    $row->recipient_user_id,
                    $row->address_snapshot,
                    $row->provider,
                    $row->delivery_status,
                    $row->provider_response,
                ]));
            } elseif ($type === 'audit') {
                fputcsv($output, ['Time', 'Actor User ID', 'Action', 'Record Type', 'Record ID', 'Reason', 'Origin IP']);
                AuditEvent::query()->whereBetween('occurred_at', [$from, $to])->each(fn ($row) => fputcsv($output, [
                    $row->occurred_at,
                    $row->actor_user_id,
                    $row->action_code,
                    $row->record_type,
                    $row->record_id,
                    $row->reason,
                    $row->origin_ip,
                ]));
            } else {
                fputcsv($output, ['Period From', 'Period To', 'Released Custodies', 'Closed Custodies', 'Return Compliance Percent']);
                $compliance = $this->returnCompliance($from, $to);
                fputcsv($output, [
                    $from->toDateString(),
                    $to->toDateString(),
                    $compliance['released'],
                    $compliance['closed'],
                    $compliance['percentage'],
                ]);
            }

            fclose($output);
        }, "spmu-{$type}-report-".now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Stream a migrated report as CSV from the shared dataset pipeline.
     *
     * The reporting period and filters are resolved exactly as the screen
     * resolved them, so "Export CSV" always returns the report on screen.
     */
    /**
     * Render a report in one of the supported export formats.
     *
     * The scope is resolved by resolveScope(), the same method the screen
     * used, so an export always carries the report on screen — same report
     * type, period, filters and status rules. Only the rendering differs.
     */
    private function exportDataset(Request $request, string $type): Response|StreamedResponse
    {
        $scope = $this->resolveScope($request, $type);
        $options = ReportExportOptions::fromRequest($request, $scope['report']);

        $service = app(ReportService::class);
        $dataset = $service->generate($scope['filters'], $request->user());

        $extension = $options->format;
        $filename = $service->filename($dataset, $extension);

        /*
         * Exporting produces a file that leaves the system, so it is recorded
         * through the central AuditService rather than a log of this module's
         * own making.
         */
        app(AuditService::class)->record(
            'report.exported',
            AuditEvent::class,
            null,
            trim(
                $dataset->label
                .' · '.($dataset->meta['period_label'] ?? '')
                .' · '.$dataset->count().' records'
                .' · '.strtoupper($extension)
            )
        );

        /* CSV streams; the binary formats are built in full before sending. */
        if ($extension === 'csv') {
            return response()->streamDownload(
                function () use ($service, $dataset, $options): void {
                    $handle = fopen('php://output', 'w');
                    $service->writeCsv($dataset, $handle, $options);
                    fclose($handle);
                },
                $filename,
                ['Content-Type' => 'text/csv']
            );
        }

        return response(
            $service->renderExport($dataset, $options),
            200,
            [
                'Content-Type' => $service->mimeType($extension),
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]
        );
    }

    public function audit(Request $request, AuditRecoveryService $recovery): View
    {
        $classification = $request->user()?->access_classification;

        /*
         * Audit Trail RBAC:
         *
         * The SPMU Admin/Head sees the operational trail plus the account
         * and settings events of the account administration they now own.
         * Authentication, security/system and other technical records stay
         * with backend maintenance. Action Officers continue using
         * transaction/request histories instead of system-wide access.
         */
        abort_unless($classification === AccessClassification::SpmuHead, 403);

        $auditMode = 'operational';

        $allowedCategories = $this->auditAudienceCategories('head');

        /*
         * Filter on the server, in the database. The audience boundary is
         * always applied first, so a manually supplied URL/category/record
         * value cannot expose events outside the audience, such as
         * authentication or security records. Every retained event stays
         * reachable through pagination;
         * the audit table is never loaded into memory.
         */
        $categoryPairs = $this->auditCategoryPairs();

        $filters = $this->auditFilters($request, $allowedCategories);

        $audienceQuery = fn (): Builder => $this->scopeAuditCategories(
            AuditEvent::query(),
            $allowedCategories,
            $categoryPairs
        );

        $query = $this->scopeAuditCategories(
            AuditEvent::query(),
            $filters['category'] === 'ALL' ? $allowedCategories : [$filters['category']],
            $categoryPairs
        );

        $this->applyAuditFilters($query, $filters);

        /*
         * A single record's history reads oldest-first so repeated edits can
         * be followed in order; the general trail stays newest-first.
         */
        $direction = $filters['record_type'] !== null ? 'asc' : 'desc';

        $events = $query
            ->with('actor')
            ->orderBy('occurred_at', $direction)
            ->orderBy('id', $direction)
            ->paginate($filters['per_page'])
            ->withQueryString();

        $events->getCollection()->each(function (AuditEvent $event): void {
            $event->setAttribute(
                'audit_category',
                $this->auditEventCategory($event)
            );
        });

        // Which events on this page the viewer may revert, and why not when blocked.
        $recovery->annotate($events->getCollection(), $request->user());

        $actorIds = $audienceQuery()
            ->whereNotNull('actor_user_id')
            ->distinct()
            ->pluck('actor_user_id');

        $actorOptions = User::query()
            ->whereIn('id', $actorIds)
            ->orderBy('full_name')
            ->pluck('full_name', 'id');

        $hasSystemActor = $audienceQuery()
            ->whereNull('actor_user_id')
            ->exists();

        // Preserve a total count for the audit view while using the paginated, audience-filtered query.
        $totalEvents = $events->total();

        $categoryOptions = [
            'BORROWING' => 'Borrowing',
            'APPROVAL' => 'Approval',
            'RELEASE_RETURN' => 'Release / Return',
            'LAUNDRY_GATEPASS' => 'Laundry / Gate Pass',
            'ACCOUNTABILITY' => 'Accountability',
            'INVENTORY' => 'Inventory',
            'CORRECTIONS_REOPENINGS' => 'Corrections / Reopenings',
            'OPERATIONAL_SETTINGS' => 'Operational Settings',
            'OTHER_OPERATIONAL' => 'Other Operational',
            'ACCOUNT' => 'Account',
            'SETTINGS' => 'System Settings',
        ];

        return view('reports.audit', [
            'events' => $events,
            'totalEvents' => $totalEvents,
            'totalVisibleEvents' => $totalEvents,
            'auditMode' => $auditMode,
            'categoryOptions' => $categoryOptions,
            'filters' => $filters,
            'actorOptions' => $actorOptions,
            'hasSystemActor' => $hasSystemActor,
            'recordNames' => $this->auditRecordNames($events->getCollection()),
        ]);
    }

    /**
     * Revert one eligible change from the Audit Trail.
     *
     * The event must lie inside the acting user's own audit audience, so a
     * guessed event number never reaches the other trail. The recovery
     * service then re-checks the module role, the record's current state and
     * conflicts while the record is locked.
     */
    public function revertAuditEvent(
        Request $request,
        int $event,
        AuditRecoveryService $recovery
    ): RedirectResponse {
        $classification = $request->user()?->access_classification;

        abort_unless($classification === AccessClassification::SpmuHead, 403);

        // Only operational changes can be reverted; account and system-setting events are view-only.
        $auditEvent = $this->scopeAuditCategories(
            AuditEvent::query(),
            $this->auditAudienceCategories('operational'),
            $this->auditCategoryPairs()
        )
            ->whereKey($event)
            ->firstOrFail();

        $data = $request->validate([
            'revert_reason' => ['required', 'string', 'min:10', 'max:1000'],
            'confirm_revert' => ['accepted'],
        ], [
            'revert_reason.required' => 'Enter the reason for reverting this change.',
            'revert_reason.min' => 'Enter a reason of at least 10 characters for reverting this change.',
            'confirm_revert.accepted' => 'Confirm the revert before submitting.',
        ]);

        $recovery->revert(
            $auditEvent,
            $request->user(),
            trim($data['revert_reason'])
        );

        return back()->with(
            'status',
            'The change was reverted. A new audit event records the recovery and refers to the original event, which is unchanged.'
        );
    }

    /**
     * Audit categories one audience may see.
     *
     * @return list<string>
     */
    private function auditAudienceCategories(string $auditMode): array
    {
        $operational = [
            'BORROWING',
            'APPROVAL',
            'RELEASE_RETURN',
            'LAUNDRY_GATEPASS',
            'ACCOUNTABILITY',
            'INVENTORY',
            'CORRECTIONS_REOPENINGS',
            'OPERATIONAL_SETTINGS',
            'OTHER_OPERATIONAL',
        ];

        // 'head': what the SPMU Admin/Head may view; 'operational': what may be reverted.
        return $auditMode === 'head'
            ? [...$operational, 'ACCOUNT', 'SETTINGS']
            : $operational;
    }

    /**
     * Settings owned by the SPMU Head: every system setting that is not one
     * of ICTU's technical settings. Ownership is the same rule the
     * configuration screen enforces, read at display time; no audit event is
     * rewritten to move it between trails.
     *
     * @return list<int>
     */
    private function operationalSettingIds(): array
    {
        return $this->operationalSettingIds ??= SystemSetting::query()
            ->whereNotIn('setting_key', SettingController::TECHNICAL_SETTING_KEYS)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Split setting events by the office responsible for the setting.
     * A setting event whose setting can no longer be identified stays
     * technical, so nothing new is shown to the SPMU Head by default.
     */
    private function whereSettingOwnership(Builder $query, bool $operational): void
    {
        $ids = $this->operationalSettingIds();

        if ($operational) {
            $query
                ->where('record_type', SystemSetting::class)
                ->whereIn('record_id', $ids);

            return;
        }

        $query->where(function (Builder $technical) use ($ids): void {
            $technical
                ->where('record_type', '!=', SystemSetting::class)
                ->orWhereNull('record_id')
                ->orWhereNotIn('record_id', $ids);
        });
    }

    /** Record types whose current, human-readable name can be shown beside the record ID. */
    /** @var list<int>|null */
    private ?array $operationalSettingIds = null;

    private const AUDIT_RECORD_NAME_COLUMNS = [
        InventoryItem::class => 'unique_description',
        BorrowingRequest::class => 'request_no',
        CustodyTransaction::class => 'custody_no',
        User::class => 'full_name',
        SystemSetting::class => 'setting_key',
        Incident::class => 'incident_no',
        BillingStatement::class => 'billing_no',
        AcademicPeriod::class => 'term_name',
    ];

    /**
     * Sanitize the Audit Trail query string. Unknown values fall back to the
     * unfiltered default instead of failing the page.
     *
     * @param  list<string>  $allowedCategories
     * @return array{q: string, date: string, category: string, actor: string, record_type: ?string, record_id: ?int, per_page: int}
     */
    private function auditFilters(Request $request, array $allowedCategories): array
    {
        $query = mb_substr(trim((string) $request->query('q', '')), 0, 120);

        $date = strtoupper((string) $request->query('date', 'ALL'));
        if (! in_array($date, ['ALL', 'TODAY', 'YESTERDAY', '7_DAYS', '30_DAYS'], true)) {
            $date = 'ALL';
        }

        $category = strtoupper((string) $request->query('category', 'ALL'));
        if (! in_array($category, $allowedCategories, true)) {
            $category = 'ALL';
        }

        $actor = strtoupper((string) $request->query('actor', 'ALL'));
        if ($actor !== 'SYSTEM' && ! ctype_digit($actor)) {
            $actor = 'ALL';
        }

        $recordType = (string) $request->query('record_type', '');
        $recordId = (string) $request->query('record_id', '');
        $hasRecord = preg_match('/^[A-Za-z][A-Za-z0-9_]{0,79}$/', $recordType) === 1
            && ctype_digit($recordId);

        $perPage = (int) $request->query('per_page', 7);
        if (! in_array($perPage, [7, 14, 28], true)) {
            $perPage = 7;
        }

        return [
            'q' => $query,
            'date' => $date,
            'category' => $category,
            'actor' => $actor,
            'record_type' => $hasRecord ? $recordType : null,
            'record_id' => $hasRecord ? (int) $recordId : null,
            'per_page' => $perPage,
        ];
    }

    /**
     * @param  array{q: string, date: string, category: string, actor: string, record_type: ?string, record_id: ?int, per_page: int}  $filters
     */
    private function applyAuditFilters(Builder $query, array $filters): void
    {
        if ($filters['record_type'] !== null) {
            $recordType = $filters['record_type'];

            $query
                ->where('record_id', $filters['record_id'])
                ->where(function (Builder $type) use ($recordType): void {
                    $type
                        ->where('record_type', 'App\\Models\\'.$recordType)
                        ->orWhere('record_type', $recordType);
                });
        }

        if ($filters['actor'] === 'SYSTEM') {
            $query->whereNull('actor_user_id');
        } elseif ($filters['actor'] !== 'ALL') {
            $query->where('actor_user_id', (int) $filters['actor']);
        }

        $today = now(config('app.timezone', 'Asia/Manila'))->startOfDay();

        match ($filters['date']) {
            'TODAY' => $query->whereBetween('occurred_at', [$today, $today->copy()->endOfDay()]),
            'YESTERDAY' => $query->whereBetween('occurred_at', [
                $today->copy()->subDay(),
                $today->copy()->subDay()->endOfDay(),
            ]),
            '7_DAYS' => $query->where('occurred_at', '>=', $today->copy()->subDays(6)),
            '30_DAYS' => $query->where('occurred_at', '>=', $today->copy()->subDays(29)),
            default => null,
        };

        if ($filters['q'] === '') {
            return;
        }

        $term = mb_strtolower($filters['q']);
        $escape = static fn (string $value): string => str_replace(
            ['!', '%', '_'],
            ['!!', '!%', '!_'],
            $value
        );

        $like = '%'.$escape($term).'%';
        // "Inventory Item Updated" matches INVENTORY_ITEM_UPDATED; "inventory item" matches InventoryItem.
        $codeLike = '%'.$escape(str_replace(' ', '_', $term)).'%';
        $typeLike = '%'.$escape(str_replace([' ', '#'], '', $term)).'%';
        $numeric = ltrim($term, '#');
        $storesActorSnapshot = Schema::hasColumn('audit_events', 'actor_name');

        $query->where(function (Builder $search) use ($like, $codeLike, $typeLike, $numeric, $storesActorSnapshot): void {
            $search
                ->whereRaw("LOWER(reason) LIKE ? ESCAPE '!'", [$like])
                ->orWhereRaw("LOWER(action_code) LIKE ? ESCAPE '!'", [$codeLike])
                ->orWhereRaw("LOWER(record_type) LIKE ? ESCAPE '!'", [$typeLike])
                ->orWhereRaw("LOWER(before_json) LIKE ? ESCAPE '!'", [$like])
                ->orWhereRaw("LOWER(after_json) LIKE ? ESCAPE '!'", [$like])
                ->orWhereHas('actor', function (Builder $actor) use ($like): void {
                    $actor->whereRaw("LOWER(full_name) LIKE ? ESCAPE '!'", [$like]);
                });

            if ($storesActorSnapshot) {
                $search->orWhereRaw("LOWER(actor_name) LIKE ? ESCAPE '!'", [$like]);
            }

            if ($numeric !== '' && ctype_digit($numeric)) {
                $search->orWhere('record_id', (int) $numeric);
            }
        });
    }

    /**
     * Classify every distinct (action, record type) pair that exists in the
     * audit table, using the same rules as auditEventCategory(). Pairs the
     * rules do not recognise are returned under UNKNOWN; their audience then
     * depends on the actor, exactly as before.
     *
     * @return array<string, list<array{0: string, 1: string}>>
     */
    private function auditCategoryPairs(): array
    {
        $pairs = [];

        DB::table('audit_events')
            ->select('action_code', 'record_type')
            ->distinct()
            ->get()
            ->each(function (object $row) use (&$pairs): void {
                $category = $this->auditCategoryForPair(
                    (string) $row->action_code,
                    (string) $row->record_type
                ) ?? 'UNKNOWN';

                $pairs[$category][] = [(string) $row->action_code, (string) $row->record_type];
            });

        return $pairs;
    }

    /**
     * Restrict a query to the given audit categories.
     *
     * @param  list<string>  $categories
     * @param  array<string, list<array{0: string, 1: string}>>  $categoryPairs
     */
    private function scopeAuditCategories(Builder $query, array $categories, array $categoryPairs): Builder
    {
        return $query->where(function (Builder $scope) use ($categories, $categoryPairs): void {
            // Deny by default: with no matching pair, nothing is returned.
            $scope->whereRaw('1 = 0');

            foreach ($categories as $category) {
                if (in_array($category, ['OTHER_TECHNICAL', 'OTHER_OPERATIONAL'], true)) {
                    $unknownPairs = $categoryPairs['UNKNOWN'] ?? [];

                    if ($unknownPairs === []) {
                        continue;
                    }

                    $scope->orWhere(function (Builder $other) use ($category, $unknownPairs): void {
                        $this->whereAuditPairs($other, $unknownPairs);

                        $performedByIctu = function (Builder $actor): void {
                            $actor->where(
                                'access_classification',
                                AccessClassification::IctuMaintainer->value
                            );
                        };

                        if ($category === 'OTHER_TECHNICAL') {
                            $other->whereHas('actor', $performedByIctu);
                        } else {
                            $other->whereDoesntHave('actor', $performedByIctu);
                        }
                    });

                    continue;
                }

                // Setting events are one family, divided by who owns the setting.
                $isSettingsFamily = in_array($category, ['SETTINGS', 'OPERATIONAL_SETTINGS'], true);

                $pairs = $categoryPairs[$isSettingsFamily ? 'SETTINGS' : $category] ?? [];

                if ($pairs !== []) {
                    $scope->orWhere(function (Builder $known) use ($pairs, $category, $isSettingsFamily): void {
                        $this->whereAuditPairs($known, $pairs);

                        if ($isSettingsFamily) {
                            $this->whereSettingOwnership($known, $category === 'OPERATIONAL_SETTINGS');
                        }
                    });
                }
            }
        });
    }

    /** @param  list<array{0: string, 1: string}>  $pairs */
    private function whereAuditPairs(Builder $query, array $pairs): void
    {
        $recordTypesByAction = [];

        foreach ($pairs as [$actionCode, $recordType]) {
            $recordTypesByAction[$actionCode][] = $recordType;
        }

        $query->where(function (Builder $group) use ($recordTypesByAction): void {
            foreach ($recordTypesByAction as $actionCode => $recordTypes) {
                $group->orWhere(function (Builder $pair) use ($actionCode, $recordTypes): void {
                    $pair
                        ->where('action_code', (string) $actionCode)
                        ->whereIn('record_type', $recordTypes);
                });
            }
        });
    }

    /**
     * Current names of the records on one page of events, in one query per
     * record type. A missing entry for a listed type means the record no
     * longer exists.
     *
     * @param  \Illuminate\Support\Collection<int, AuditEvent>  $events
     * @return array<string, array<int, string>>
     */
    private function auditRecordNames(\Illuminate\Support\Collection $events): array
    {
        $names = [];

        foreach (self::AUDIT_RECORD_NAME_COLUMNS as $recordType => $column) {
            $ids = $events
                ->where('record_type', $recordType)
                ->pluck('record_id')
                ->filter()
                ->unique()
                ->values();

            $names[$recordType] = $ids->isEmpty()
                ? []
                : $recordType::query()
                    ->whereIn('id', $ids)
                    ->pluck($column, 'id')
                    ->map(fn ($name) => (string) $name)
                    ->all();
        }

        return $names;
    }

    /**
     * Assign one audience-safe category to every persisted audit event.
     *
     * Unknown records are intentionally NOT exposed to ICTU unless they are
     * clearly technical or were performed by ICTU. This gives the technical
     * workspace a deny-by-default boundary for operational activity.
     */
    private function auditEventCategory(AuditEvent $event): string
    {
        $category = $this->auditCategoryForPair(
            (string) $event->action_code,
            (string) $event->record_type
        );

        if (
            $category === 'SETTINGS'
            && $event->record_type === SystemSetting::class
            && in_array((int) $event->record_id, $this->operationalSettingIds(), true)
        ) {
            return 'OPERATIONAL_SETTINGS';
        }

        if ($category !== null) {
            return $category;
        }

        /*
         * An otherwise-unknown event explicitly performed by ICTU is kept
         * available to ICTU as Other Technical. Unknown system/SPMU events
         * remain operational instead of leaking into the ICTU Audit Trail.
         */
        if (
            $event->actor?->access_classification
                === AccessClassification::IctuMaintainer
        ) {
            return 'OTHER_TECHNICAL';
        }

        return 'OTHER_OPERATIONAL';
    }

    /**
     * Category decided by the action code and record type alone, or null
     * when the rules do not recognise the pair.
     */
    private function auditCategoryForPair(string $actionCode, string $recordType): ?string
    {
        $action = strtoupper(str_replace(
            ['.', '-', ' '],
            '_',
            $actionCode
        ));

        $record = strtoupper(
            class_basename($recordType)
        );

        $context = $action.' '.$record;

        $containsAny = static function (
            string $value,
            array $needles
        ): bool {
            foreach ($needles as $needle) {
                if (str_contains($value, $needle)) {
                    return true;
                }
            }

            return false;
        };

        /*
         * Technical / ICTU categories.
         */
        if ($containsAny($context, [
            'OTP',
            'EMAIL_VERIFICATION',
            'EMAIL_VERIFIED',
            'EMAIL_VERIFY',
            'GOOGLE_AUTH',
            'AUTHENTICATION',
            'LOGIN',
            'LOGOUT',
            'SIGN_IN',
            'SIGNIN',
            'PASSWORD',
            'SESSION',
        ])) {
            return 'AUTH_VERIFICATION';
        }

        if ($containsAny($context, [
            'USER_ACCOUNT',
            'USER_CREATED',
            'USER_UPDATED',
            'USER_DEACTIVATED',
            'USER_REACTIVATED',
            'USER_PENDING_REGISTRATION',
            'PROFILE_PICTURE',
            'USER_ORGANIZATIONAL',
            'ORGANIZATIONAL_UNIT',
            'ROLE_ASSIGNMENT',
            'DELEGATION',
        ])) {
            return 'ACCOUNT';
        }

        if ($containsAny($context, [
            'SYSTEM_SETTING',
            'SYSTEMSETTING',
            'CONFIGURATION_CHANGE',
            'CONFIGURATIONCHANGE',
            'TECHNICAL_SETTING',
            'SETTING_UPDATED',
            'MAIL_TRANSPORT',
            'SMTP',
        ])) {
            return 'SETTINGS';
        }

        if ($containsAny($context, [
            'SECURITY',
            'BACKUP',
            'TECHNICAL_OPERATION',
            'NOTIFICATION_DELIVERY',
            'NOTIFICATIONDELIVERY',
            'DELIVERY_FAILURE',
            'AUDIT_EXPORT',
        ])) {
            return 'SECURITY_SYSTEM';
        }

        /*
         * Operational / SPMU categories.
         *
         * Corrections are checked before Return because a status such as
         * RETURNED_FOR_REVISION is a correction workflow, not a physical
         * asset return.
         */
        if ($containsAny($context, [
            'CORRECTION',
            'REOPEN',
            'RETURNED_FOR_REVISION',
            'REVISION',
            'SUPERSEDE',
        ])) {
            return 'CORRECTIONS_REOPENINGS';
        }

        if ($containsAny($context, [
            'ACCOUNTABILITY',
            'INCIDENT',
            'OVERDUE',
            'BILLING',
            'PAYMENT',
            'RESTRICTION',
            'SANCTION',
            'VIOLATION',
            'RSLDDP',
        ])) {
            return 'ACCOUNTABILITY';
        }

        if ($containsAny($context, [
            'LAUNDRY',
            'GATE_PASS',
            'GATEPASS',
        ])) {
            return 'LAUNDRY_GATEPASS';
        }

        if ($containsAny($context, [
            'INVENTORY',
            'STOCK',
            'ALLOCATION',
            'INVENTORYITEM',
            'INVENTORYADJUSTMENT',
            'ITEM_ADJUST',
        ])) {
            return 'INVENTORY';
        }

        if ($containsAny($context, [
            'RELEASE',
            'RETURN',
            'CUSTODY',
            'ISSUANCE',
            'PICKUP',
            'PREPARATION',
        ])) {
            return 'RELEASE_RETURN';
        }

        if ($containsAny($context, [
            'APPROVAL',
            'APPROVED',
            'FINAL_APPROV',
            'HEAD_DECISION',
            'REJECTED',
        ])) {
            return 'APPROVAL';
        }

        if ($containsAny($context, [
            'BORROW',
            'REQUEST',
            'SUBMIT',
            'CANCEL',
            'RESCHEDULE',
        ])) {
            return 'BORROWING';
        }

        return null;
    }

    public function notifications(Request $request): View
    {
        /*
         * Delivery Records lists recipient addresses, mail providers and
         * provider responses, so a direct URL follows the same rule as the
         * navigation: SPMU Admin/Head only.
         */
        $this->authorizeDeliveryRecords($request);

        // SMS has no dispatch path today - the normal/current operational
        // view never mixes its historical rows in with SYSTEM/EMAIL. A
        // separate, explicitly-requested legacy view exists purely to
        // preserve audit access to them (see the `legacy` param below).
        $legacyView = $request->boolean('legacy');

        /*
         * The view's scope (current SYSTEM/EMAIL records, or legacy SMS) is
         * always applied first; every filter then runs in the database, so
         * all retained records stay reachable through pagination and the
         * table is never loaded into memory.
         */
        $scope = fn (): Builder => NotificationDelivery::query()
            ->when(
                $legacyView,
                fn (Builder $query) => $query->where('channel', 'SMS'),
                fn (Builder $query) => $query->whereIn('channel', NotificationDelivery::OPERATIONAL_CHANNELS)
            );

        $channelOptions = $scope()
            ->distinct()
            ->orderBy('channel')
            ->pluck('channel')
            ->map(fn ($channel): string => strtoupper((string) $channel))
            ->unique()
            ->values()
            ->all();

        $eventOptions = NotificationEvent::query()
            ->whereIn('id', $scope()->select('notification_event_id'))
            ->distinct()
            ->orderBy('event_code')
            ->pluck('event_code')
            ->filter()
            ->values()
            ->all();

        $statusOptions = $this->deliveryStatusOptions($scope(), $legacyView);

        $filters = $this->deliveryFilters($request, $legacyView, $channelOptions, $statusOptions, $eventOptions);

        $query = $scope()->with(['event', 'resolvedBy']);

        $this->applyDeliveryFilters($query, $filters);

        // "status=failed" is the dashboard's Failed Notifications drilldown:
        // the same actionableFailures() scope as that KPI.
        $statusFilter = ! $legacyView && $filters['status'] === 'CURRENT_FAILURE' && $filters['from_dashboard']
            ? 'failed'
            : null;

        return view('reports.notifications', [
            'deliveries' => $query
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate($filters['per_page'])
                ->withQueryString(),
            'statusFilter' => $statusFilter,
            'canResolveFailures' => ! $legacyView,
            'legacyView' => $legacyView,
            'filters' => $filters,
            'channelOptions' => $channelOptions,
            'statusOptions' => $statusOptions,
            'eventOptions' => $eventOptions,
            'totalRecords' => $scope()->count(),
        ]);
    }

    /**
     * Status categories that exist in the current scope. A failure keeps
     * delivery_status = FAILED for audit, so "current" and "historical"
     * failures are told apart by resolved_at, exactly as the table shows them.
     *
     * @return list<string>
     */
    private function deliveryStatusOptions(Builder $scope, bool $legacyView): array
    {
        $legacySmsStatuses = ['FAILED', 'NOT_CONFIGURED', 'SKIPPED'];
        $options = [];

        (clone $scope)
            ->selectRaw('delivery_status, CASE WHEN resolved_at IS NULL THEN 0 ELSE 1 END AS is_resolved')
            ->distinct()
            ->toBase()
            ->get()
            ->each(function (object $row) use (&$options, $legacyView, $legacySmsStatuses): void {
                $status = strtoupper((string) $row->delivery_status);

                $options[] = match (true) {
                    $legacyView && in_array($status, $legacySmsStatuses, true) => 'SMS_NOT_CONFIGURED',
                    $status === 'FAILED' && (int) $row->is_resolved === 1 => 'HISTORICAL_FAILURE',
                    $status === 'FAILED' => 'CURRENT_FAILURE',
                    default => $status,
                };
            });

        $options = array_values(array_unique($options));
        sort($options);

        return $options;
    }

    /**
     * @param  list<string>  $channelOptions
     * @param  list<string>  $statusOptions
     * @param  list<string>  $eventOptions
     * @return array{q: string, date: string, channel: string, status: string, event: string, per_page: int, from_dashboard: bool}
     */
    private function deliveryFilters(
        Request $request,
        bool $legacyView,
        array $channelOptions,
        array $statusOptions,
        array $eventOptions
    ): array {
        $date = strtoupper((string) $request->query('date', 'ALL'));
        if (! in_array($date, ['ALL', 'TODAY', 'YESTERDAY', '7_DAYS', '30_DAYS'], true)) {
            $date = 'ALL';
        }

        $channel = strtoupper((string) $request->query('channel', 'ALL'));
        if (! in_array($channel, $channelOptions, true)) {
            $channel = 'ALL';
        }

        $requestedStatus = (string) $request->query('status', 'ALL');
        $fromDashboard = ! $legacyView && $requestedStatus === 'failed';
        $status = $fromDashboard ? 'CURRENT_FAILURE' : strtoupper($requestedStatus);
        if (! $fromDashboard && ! in_array($status, $statusOptions, true)) {
            $status = 'ALL';
        }

        $event = strtoupper((string) $request->query('event', 'ALL'));
        if (! in_array($event, array_map('strtoupper', $eventOptions), true)) {
            $event = 'ALL';
        }

        $perPage = (int) $request->query('per_page', 7);
        if (! in_array($perPage, [7, 14, 28], true)) {
            $perPage = 7;
        }

        return [
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 120),
            'date' => $date,
            'channel' => $channel,
            'status' => $status,
            'event' => $event,
            'per_page' => $perPage,
            'from_dashboard' => $fromDashboard,
        ];
    }

    private function applyDeliveryFilters(Builder $query, array $filters): void
    {
        if ($filters['channel'] !== 'ALL') {
            $query->where('channel', $filters['channel']);
        }

        match ($filters['status']) {
            'ALL' => null,
            'CURRENT_FAILURE' => $query->actionableFailures(),
            'HISTORICAL_FAILURE' => $query->where('delivery_status', 'FAILED')->whereNotNull('resolved_at'),
            'SMS_NOT_CONFIGURED' => $query->whereIn('delivery_status', ['FAILED', 'NOT_CONFIGURED', 'SKIPPED']),
            default => $query->where('delivery_status', $filters['status']),
        };

        if ($filters['event'] !== 'ALL') {
            $query->whereHas('event', function (Builder $event) use ($filters): void {
                $event->where('event_code', $filters['event']);
            });
        }

        // A delivery is dated by its attempt; rows without one fall back to when they were recorded.
        $today = now(config('app.timezone', 'Asia/Manila'))->startOfDay();
        $range = match ($filters['date']) {
            'TODAY' => [$today, $today->copy()->endOfDay()],
            'YESTERDAY' => [$today->copy()->subDay(), $today->copy()->subDay()->endOfDay()],
            '7_DAYS' => [$today->copy()->subDays(6), $today->copy()->endOfDay()],
            '30_DAYS' => [$today->copy()->subDays(29), $today->copy()->endOfDay()],
            default => null,
        };

        if ($range !== null) {
            $query->where(function (Builder $dated) use ($range): void {
                $dated
                    ->whereBetween('attempted_at', $range)
                    ->orWhere(function (Builder $unattempted) use ($range): void {
                        $unattempted->whereNull('attempted_at')->whereBetween('created_at', $range);
                    });
            });
        }

        if ($filters['q'] === '') {
            return;
        }

        $term = mb_strtolower($filters['q']);
        $escape = static fn (string $value): string => str_replace(
            ['!', '%', '_'],
            ['!!', '!%', '!_'],
            $value
        );

        $like = '%'.$escape($term).'%';
        // "Pickup Scheduled" matches PICKUP_SCHEDULED.
        $codeLike = '%'.$escape(str_replace(' ', '_', $term)).'%';
        // The table labels the SYSTEM channel "In-system".
        $matchesInSystem = str_contains('in-system', $term) || str_contains('in system', $term);

        $query->where(function (Builder $search) use ($like, $codeLike, $matchesInSystem): void {
            $search
                ->whereRaw("LOWER(address_snapshot) LIKE ? ESCAPE '!'", [$like])
                ->orWhereRaw("LOWER(channel) LIKE ? ESCAPE '!'", [$like])
                ->orWhereRaw("LOWER(delivery_status) LIKE ? ESCAPE '!'", [$codeLike])
                ->orWhereRaw("LOWER(provider) LIKE ? ESCAPE '!'", [$like])
                ->orWhereRaw("LOWER(provider_response) LIKE ? ESCAPE '!'", [$like])
                ->orWhereHas('event', function (Builder $event) use ($codeLike): void {
                    $event->whereRaw("LOWER(event_code) LIKE ? ESCAPE '!'", [$codeLike]);
                })
                ->orWhereHas('recipient', function (Builder $recipient) use ($like): void {
                    $recipient->whereRaw("LOWER(full_name) LIKE ? ESCAPE '!'", [$like]);
                });

            if ($matchesInSystem) {
                $search->orWhere('channel', 'SYSTEM');
            }
        });
    }

    /**
     * Explicit SPMU Admin resolution of a genuine, currently-unresolved delivery
     * failure. delivery_status stays FAILED for audit - only resolved_at /
     * resolved_by_user_id / resolution_note are written, which is what
     * moves the row out of actionableFailures() (the KPI/drilldown scope)
     * and into the "Historical Failure" presentation. Never available for
     * SMS-channel rows (never a genuine, currently-actionable delivery
     * attempt) or for rows already resolved.
     */
    public function resolveNotificationFailure(Request $request, NotificationDelivery $delivery): RedirectResponse
    {
        $this->authorizeDeliveryRecords($request);

        abort_unless(
            $delivery->delivery_status === 'FAILED'
                && in_array($delivery->channel, NotificationDelivery::OPERATIONAL_CHANNELS, true)
                && $delivery->resolved_at === null,
            404
        );

        $data = $request->validate(['resolution_note' => ['required', 'string', 'min:5', 'max:1000']]);

        $delivery->update([
            'resolved_at' => now(),
            'resolved_by_user_id' => $request->user()->id,
            'resolution_note' => $data['resolution_note'],
        ]);

        app(AuditService::class)->record('NOTIFICATION_DELIVERY_FAILURE_RESOLVED', $delivery, reason: $data['resolution_note']);

        return back()->with('status', 'Delivery failure marked as resolved.');
    }

    /**
     * Bulk variant of resolveNotificationFailure() for a set of currently-
     * unresolved operational failures sharing one root cause (e.g. the same
     * transient DNS/SMTP error) - never an automatic/blanket resolution.
     * The SPMU Admin explicitly selects the rows and supplies one required note,
     * which is written identically (same resolved_at, resolved_by_user_id,
     * resolution_note) to every selected row. Submitted ids that are not
     * currently eligible (already resolved, SMS-channel, or not FAILED) are
     * silently excluded via the same actionableFailures() scope rather than
     * failing the whole batch - the UI never offers a checkbox for them in
     * the first place, so this only guards against a manipulated request.
     */
    public function resolveBulkNotificationFailures(Request $request): RedirectResponse
    {
        $this->authorizeDeliveryRecords($request);

        $data = $request->validate([
            'delivery_ids' => ['required', 'array', 'min:1'],
            'delivery_ids.*' => ['integer'],
            'resolution_note' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $deliveries = NotificationDelivery::query()
            ->whereIn('id', $data['delivery_ids'])
            ->actionableFailures()
            ->get();

        if ($deliveries->isEmpty()) {
            return back()->withErrors([
                'delivery_ids' => 'None of the selected rows are eligible for resolution. Only unresolved Email/System delivery failures can be resolved.',
            ]);
        }

        $resolvedAt = now();
        $resolverId = $request->user()->id;

        foreach ($deliveries as $delivery) {
            $delivery->update([
                'resolved_at' => $resolvedAt,
                'resolved_by_user_id' => $resolverId,
                'resolution_note' => $data['resolution_note'],
            ]);

            app(AuditService::class)->record('NOTIFICATION_DELIVERY_FAILURE_RESOLVED', $delivery, reason: $data['resolution_note']);
        }

        return back()->with('status', $deliveries->count().' delivery failure(s) marked as resolved.');
    }

    /** @return array{released:int,closed:int,percentage:float} */
    private function returnCompliance(Carbon $from, Carbon $to): array
    {
        $released = CustodyTransaction::query()->whereBetween('released_at', [$from, $to])->count();
        $closed = CustodyTransaction::query()
            ->whereBetween('released_at', [$from, $to])
            ->where('status', 'CLOSED')
            ->count();

        return [
            'released' => $released,
            'closed' => $closed,
            'percentage' => $released ? round($closed / $released * 100, 2) : 0,
        ];
    }

    /** Delivery Records and its failure resolutions belong to the SPMU Admin/Head. */
    private function authorizeDeliveryRecords(Request $request): void
    {
        abort_unless(
            $request->user()?->access_classification === AccessClassification::SpmuHead,
            403
        );
    }
}
