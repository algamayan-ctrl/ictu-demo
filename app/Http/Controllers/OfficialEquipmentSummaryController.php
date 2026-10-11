<?php

namespace App\Http\Controllers;

use App\Enums\AccessClassification;
use App\Reports\ReportRecordTable;
use App\Services\AuditService;
use App\Services\OfficialEquipmentSummaryExporter;
use App\Services\OfficialEquipmentSummaryService;
use App\Support\RequestRecordAccess;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OfficialEquipmentSummaryController extends Controller
{
    public const FORMATS = ['print', 'pdf', 'docx', 'xlsx', 'csv'];

    /**
     * Authorize and resolve the selected period.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function period(Request $request): array
    {
        abort_unless($request->user()?->access_classification === AccessClassification::SpmuHead, 403);
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $from = Carbon::createFromFormat('!Y-m-d', $data['from'] ?? now()->startOfMonth()->format('Y-m-d'));
        $to = Carbon::createFromFormat('!Y-m-d', $data['to'] ?? now()->format('Y-m-d'));
        if ($from->gt($to) || $from->diffInDays($to) > 366) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'to' => 'Choose an inclusive date range of up to 366 days.',
            ]);
        }

        return [$from->startOfDay(), $to->endOfDay()];
    }

    /**
     * The system preview of the official form's records.
     *
     * The records come from the same service call every official format uses,
     * so what is reviewed here is exactly what is printed or exported. The
     * institutional layout (headings, form code, signatures, page footer) is
     * applied only by export(), never on this screen.
     */
    public function index(Request $request, OfficialEquipmentSummaryService $service): View
    {
        [$from, $to] = $this->period($request);
        $back = $this->backUrl($request);
        $scope = array_filter([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'back' => $back,
        ]);

        $report = null;
        $failure = null;

        try {
            $report = $service->build($from, $to);
        } catch (ModelNotFoundException) {
            $failure = 'no_active_revision';
        } catch (\Throwable $exception) {
            report($exception);
            $failure = 'error';
        }

        $table = $report === null ? null : ReportRecordTable::fromRequest(
            $request,
            array_map(static fn (array $col): array => [
                'key' => $col['key'],
                'label' => $col['group'] ? ucfirst(strtolower($col['group'])).' '.$col['label'] : $col['label'],
            ], $report['columns']),
            /* "View Record" is offered only for requests this user may actually open. */
            RequestRecordAccess::withoutUnviewableLinks(
                collect($report['rows']),
                $request->user(),
                $request->session()->get('active_workspace')
            ),
            route('reports.official.equipment'),
            $scope
        );

        return view('reports.official.preview', [
            'report' => $report,
            'table' => $table,
            'failure' => $failure,
            'from' => $from,
            'to' => $to,
            'back' => $back,
            'exportQuery' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        ]);
    }

    public function export(Request $request, string $format, OfficialEquipmentSummaryService $service, OfficialEquipmentSummaryExporter $exporter, AuditService $audit): Response|View|StreamedResponse
    {
        abort_unless(in_array($format, self::FORMATS, true), 404);
        [$from, $to] = $this->period($request);
        $report = $service->build($from, $to);
        $report['generated_by'] = $request->user()->full_name;
        $audit->record(
            'OFFICIAL_REPORT_GENERATED',
            $report['template'],
            reason: 'SPMU Head generated official form '.$report['template']->form_code.' as '.strtoupper($format),
            after: ['template_id' => $report['template']->id, 'version_no' => $report['template']->version_no,
                'from' => $report['from']->toDateString(), 'to' => $report['to']->toDateString(), 'records' => $report['total'], 'format' => $format]
        );

        if ($format === 'print') {
            return view('reports.official.equipment', ['report' => $report, 'print' => true, 'sealSrc' => $this->seal()]);
        }

        $bytes = $exporter->render($format, $report, $this->seal());
        $mime = [
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'csv' => 'text/csv; charset=UTF-8',
        ][$format];
        $code = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $report['template']->form_code);
        $filename = $code.'_'.$report['from']->format('Ymd').'-'.$report['to']->format('Ymd').'_v'.$report['template']->version_no.'.'.$format;
        return response($bytes, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * The Reports page state the preview was opened from, so "Back to Reports"
     * restores its period, filters and preview. Only a same-site Reports path
     * is accepted; anything else falls back to Reports itself.
     */
    private function backUrl(Request $request): ?string
    {
        $back = (string) $request->query('back', '');
        $reportsPath = route('reports.index', [], false);

        if (
            $back === ''
            || strlen($back) > 2000
            || ! str_starts_with($back, $reportsPath)
            || preg_match('#^'.preg_quote($reportsPath, '#').'(?:[?\#]|$)#', $back) !== 1
            || str_contains($back, '\\')
        ) {
            return null;
        }

        return $back;
    }

    private function seal(): ?string
    {
        foreach (['images/cspc-seal-document.png', 'images/cspc-logo.png'] as $candidate) {
            if (is_file(public_path($candidate))) {
                return 'data:image/png;base64,'.base64_encode((string) file_get_contents(public_path($candidate)));
            }
        }
        return null;
    }
}
