<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpWord\IOFactory as WordIO;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings as WordSettings;

/**
 * All official formats use the same Active revision and record array.
 * PDF, Word and Print carry the institutional paper layout; Excel and CSV are
 * data exports with identifying rows and no letterhead or signature block.
 */
class OfficialEquipmentSummaryExporter
{
    public function render(string $format, array $report, ?string $sealSrc): string
    {
        return match ($format) {
            'pdf' => $this->pdf($report, $sealSrc),
            'docx' => $this->docx($report, $sealSrc),
            'xlsx' => $this->xlsx($report),
            'csv' => $this->csv($report),
        };
    }

    private function pdf(array $report, ?string $sealSrc): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'Helvetica');
        // Apply the sheet's @media print rules (white page, no screen chrome or margins).
        $options->set('defaultMediaType', 'print');
        $pdf = new Dompdf($options);
        [$size, $landscape] = $this->page($report);
        $pdf->setPaper(strtolower($size), $landscape ? 'landscape' : 'portrait');
        $html = view('reports.official.equipment', compact('report', 'sealSrc') + ['print' => true])->render();
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->render();
        $pdf->getCanvas()->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) use ($report): void {
            $font = $fontMetrics->getFont('Helvetica');
            $canvas->text(36, $canvas->get_height() - 21,
                (string) $report['template']->form_code.' · '.$report['template']->revision_label.' · Page '.$pageNumber.' of '.$pageCount,
                $font, 8, [0.25, 0.30, 0.38]);
        });
        return (string) $pdf->output();
    }

    private function docx(array $report, ?string $sealSrc): string
    {
        $template = $report['template'];
        $columns = $report['columns'];
        $word = new PhpWord;
        $word->setDefaultFontName('Arial');
        $word->setDefaultFontSize(9);
        $word->getDocInfo()
            ->setTitle((string) $report['title'])
            ->setSubject('Reporting period: '.$report['coverage'])
            ->setDescription($template->form_code.' · '.$template->revision_label);
        [$size, $landscape] = $this->page($report);
        [$pageW, $pageH] = match ($size) {
            'LETTER' => [12240, 15840],
            'LEGAL' => [12240, 20160],
            default => [11906, 16838],
        };
        $margin = 737; // 13 mm, same as the printed sheet
        $section = $word->addSection([
            'orientation' => $landscape ? 'landscape' : 'portrait',
            'pageSizeW' => $landscape ? $pageH : $pageW,
            'pageSizeH' => $landscape ? $pageW : $pageH,
            'marginTop' => $margin, 'marginBottom' => $margin + 280,
            'marginLeft' => $margin, 'marginRight' => $margin,
        ]);
        $usable = ($landscape ? $pageH : $pageW) - 2 * $margin;
        $center = ['alignment' => 'center', 'spaceAfter' => 0];
        $tight = ['spaceAfter' => 0];

        $footer = $section->addFooter();
        $footer->addPreserveText(
            $template->form_code.' · Effectivity Date: '.($template->effectivity_date?->format('F Y') ?? '—')
                .' · '.$template->revision_label.' · Page {PAGE} of {NUMPAGES}',
            ['size' => 8, 'color' => '404D61'], $tight
        );

        // Institutional header: seal | institution | form code.
        $head = $report['configuration']['header'] ?? [];
        $headTable = $section->addTable(['borderBottomSize' => 12, 'borderBottomColor' => '174B8D', 'cellMargin' => 40]);
        $headTable->addRow();
        $sealCell = $headTable->addCell(1300, ['valign' => 'center']);
        $sealFile = (($head['show_logo'] ?? true) && $sealSrc) ? $this->sealFile($sealSrc) : null;
        if ($sealFile) {
            $sealCell->addImage($sealFile, ['width' => 44, 'height' => 44]);
        }
        $institution = $headTable->addCell($usable - 1300 - 2600, ['valign' => 'center']);
        $institution->addText((string) ($head['republic'] ?? 'Republic of the Philippines'), ['size' => 9], $center);
        $institution->addText((string) ($head['institution'] ?? 'CAMARINES SUR POLYTECHNIC COLLEGES'), ['bold' => true, 'size' => 12], $center);
        $institution->addText((string) ($head['location'] ?? 'Nabua, Camarines Sur'), ['size' => 9], $center);
        $headTable->addCell(2600, ['valign' => 'center'])
            ->addText((string) $template->form_code, ['bold' => true, 'size' => 8], ['alignment' => 'right', 'spaceAfter' => 0]);

        $section->addText((string) $report['title'], ['bold' => true, 'size' => 11],
            ['alignment' => 'center', 'spaceBefore' => 240, 'spaceAfter' => 200]);

        // Records table with the grouped RECEIVED / RELEASED headings.
        $widths = array_map(fn (array $col): int => (int) floor($usable * $col['width'] / 100), $columns);
        $th = ['bold' => true, 'size' => 8];
        $thCell = ['bgColor' => 'EDF2F9', 'valign' => 'center'];
        $table = $section->addTable(['borderSize' => 6, 'borderColor' => '444444', 'cellMargin' => 70, 'width' => $usable, 'unit' => 'dxa']);
        $grouped = collect($columns)->contains(fn (array $col): bool => filled($col['group']));
        $table->addRow(null, ['tblHeader' => true]);
        foreach ($this->headingGroups($columns) as $group) {
            if ($group['group']) {
                $table->addCell(array_sum(array_slice($widths, $group['index'], $group['span'])), $thCell + ['gridSpan' => $group['span']])
                    ->addText($group['label'], $th, $center);
            } else {
                $table->addCell($widths[$group['index']], $thCell + ($grouped ? ['vMerge' => 'restart'] : []))
                    ->addText($group['label'], $th, $center);
            }
        }
        if ($grouped) {
            $table->addRow(null, ['tblHeader' => true]);
            foreach ($columns as $index => $col) {
                $cell = $table->addCell($widths[$index], $thCell + ($col['group'] ? [] : ['vMerge' => 'continue']));
                if ($col['group']) {
                    $cell->addText($col['label'], $th, $center);
                }
            }
        }
        foreach ($report['rows'] as $row) {
            $table->addRow(null, ['cantSplit' => true]);
            foreach ($columns as $index => $col) {
                $table->addCell($widths[$index])->addText((string) ($row[$col['key']] ?? '—'), ['size' => 8], $tight);
            }
        }
        if ($report['total'] === 0) {
            $table->addRow();
            $table->addCell($usable, ['gridSpan' => max(1, count($columns))])
                ->addText('No submitted equipment requests recorded in the selected period.', ['size' => 8], $center);
        }

        if ($report['configuration']['formula']['enabled'] ?? true) {
            [$formula, $result] = $this->summaryLines($report);
            $section->addText($formula, ['size' => 9], ['spaceBefore' => 240, 'spaceAfter' => 0]);
            $section->addText($result, ['size' => 9], $tight);
        }

        // Physical signatures only: blank ink line, printed name, designation, blank date.
        $sign = $report['configuration']['signatories'] ?? [];
        $section->addTextBreak(1);
        $signTable = $section->addTable(['cellMargin' => 40]);
        $signTable->addRow(null, ['cantSplit' => true]);
        foreach (['prepared_by', 'noted_by'] as $key) {
            $cell = $signTable->addCell((int) floor($usable / 2));
            $cell->addText(($sign[$key.'_label'] ?? ($key === 'prepared_by' ? 'Prepared by' : 'Noted by')).':', ['bold' => true, 'size' => 9], $tight);
            $cell->addTextBreak(2);
            $cell->addText('______________________________________', ['size' => 9], $tight);
            $cell->addText((string) ($sign[$key.'_name'] ?? ''), ['bold' => true, 'size' => 9], $tight);
            $cell->addText((string) ($sign[$key.'_designation'] ?? ''), ['size' => 8], $tight);
            if ($sign['show_date'] ?? true) {
                $cell->addText('Date: __________________', ['size' => 8], $tight);
            }
        }

        $temp = tempnam(sys_get_temp_dir(), 'spmu_word_');
        // PhpWord writes text as-is unless told to escape it; a heading or
        // value containing "&" or "<" would otherwise corrupt the document.
        $escaping = WordSettings::isOutputEscapingEnabled();
        WordSettings::setOutputEscapingEnabled(true);
        try {
            WordIO::createWriter($word, 'Word2007')->save($temp);
            return (string) file_get_contents($temp);
        } finally {
            WordSettings::setOutputEscapingEnabled($escaping);
            @unlink($temp);
            if ($sealFile) {
                @unlink($sealFile);
            }
        }
    }

    /**
     * Excel is a data export, not a picture of the paper form: identifying
     * rows (title, period, form revision, generation), one filterable header
     * row and the records. No seal, letterhead, signature block or page footer.
     */
    private function xlsx(array $report): string
    {
        $template = $report['template'];
        $columns = $report['columns'];
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Requests for Equipment');
        $spreadsheet->getProperties()
            ->setTitle((string) $report['title'])
            ->setSubject('Reporting period: '.$report['coverage'])
            ->setDescription($template->form_code.' · '.$template->revision_label);
        $count = max(1, count($columns));
        $letter = static fn (int $index): string => Coordinate::stringFromColumnIndex($index + 1);

        $row = 1;
        foreach ($this->identification($report) as [$label, $value]) {
            $sheet->setCellValueExplicit('A'.$row, $label, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('B'.$row, (string) $value, DataType::TYPE_STRING);
            $sheet->getStyle('A'.$row)->getFont()->setBold(true);
            $row++;
        }

        $headerRow = $row + 1;
        foreach ($columns as $index => $col) {
            $sheet->setCellValueExplicit($letter($index).$headerRow, $this->columnLabel($col), DataType::TYPE_STRING);
            $sheet->getColumnDimension($letter($index))->setWidth(max(12, round($col['width'] * 1.7, 1)));
        }
        $headerRange = 'A'.$headerRow.':'.$letter($count - 1).$headerRow;
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()->setFillType('solid')->getStartColor()->setARGB('FFEFEFEF');
        $sheet->getStyle($headerRange)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);

        $row = $headerRow + 1;
        foreach ($report['rows'] as $record) {
            foreach ($columns as $index => $col) {
                $sheet->setCellValueExplicit($letter($index).$row, (string) ($record[$col['key']] ?? ''), DataType::TYPE_STRING);
            }
            $row++;
        }
        if ($report['total'] > 0) {
            $sheet->setAutoFilter('A'.$headerRow.':'.$letter($count - 1).($row - 1));
            $sheet->getStyle('A'.($headerRow + 1).':'.$letter($count - 1).($row - 1))->getAlignment()
                ->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
        }
        $sheet->getColumnDimension('A')->setWidth(max(24, (float) $sheet->getColumnDimension('A')->getWidth()));
        $sheet->freezePane('A'.($headerRow + 1));

        $temp = tempnam(sys_get_temp_dir(), 'spmu_xlsx_');
        try {
            (new Xlsx($spreadsheet))->save($temp);
            return (string) file_get_contents($temp);
        } finally {
            @unlink($temp);
            $spreadsheet->disconnectWorksheets();
        }
    }

    /**
     * CSV stays parseable data: a short block of identifying lines, a blank
     * line, then one header row and the records. No layout or signatures.
     */
    private function csv(array $report): string
    {
        // Spreadsheet programs evaluate cells beginning with these characters as formulas.
        $cell = static fn (string $value): string => preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel keeps "—" and "–" intact
        foreach ($this->identification($report) as $line) {
            fputcsv($stream, array_map(static fn ($value): string => $cell((string) $value), $line));
        }
        fputcsv($stream, []);
        fputcsv($stream, array_map(fn (array $col): string => $cell($this->columnLabel($col)), $report['columns']));
        foreach ($report['rows'] as $row) {
            fputcsv($stream, array_map(static fn (array $col): string => $cell((string) ($row[$col['key']] ?? '')), $report['columns']));
        }
        rewind($stream);
        $output = (string) stream_get_contents($stream);
        fclose($stream);
        return $output;
    }

    /**
     * Identifying information for the data exports: what the file is, which
     * period and records it covers, and which Active form revision produced it.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function identification(array $report): array
    {
        $template = $report['template'];
        $lines = [
            ['Report', (string) $report['title']],
            ['Reporting Period', (string) $report['period_label']],
            ['Coverage', (string) $report['coverage']],
            ['Form', trim($template->form_code.' · '.$template->revision_label, ' ·')],
            ['Total Requests', (string) $report['total']],
            ['On-time Response', 'Not evaluated'],
        ];
        if (filled($report['generated_by'] ?? null)) {
            $lines[] = ['Generated By', (string) $report['generated_by']];
        }
        $lines[] = ['Date Generated', $report['generated_at']->format('F j, Y g:i A')];

        return $lines;
    }

    /** Grouped paper headings flatten to one data header ("Received Date"). */
    private function columnLabel(array $col): string
    {
        return $col['group'] ? ucfirst(strtolower($col['group'])).' '.$col['label'] : $col['label'];
    }

    /** @return array{0: string, 1: bool} paper size and whether the page is landscape */
    private function page(array $report): array
    {
        $page = $report['configuration']['page'] ?? [];
        $size = strtoupper((string) ($page['size'] ?? 'A4'));
        $size = in_array($size, ['A4', 'LETTER', 'LEGAL'], true) ? $size : 'A4';

        return [$size, strtoupper((string) ($page['orientation'] ?? 'LANDSCAPE')) !== 'PORTRAIT'];
    }

    /**
     * First heading row: one entry per ungrouped column, or one spanning entry
     * per run of consecutive columns sharing a group (RECEIVED, RELEASED).
     *
     * @return list<array{label: string, group: ?string, index: int, span: int}>
     */
    private function headingGroups(array $columns): array
    {
        $groups = [];
        foreach ($columns as $index => $col) {
            $previous = $groups === [] ? null : $groups[array_key_last($groups)];
            if ($col['group'] && $previous && $previous['group'] === $col['group']) {
                $groups[array_key_last($groups)]['span']++;
                continue;
            }
            $groups[] = ['label' => $col['group'] ?: $col['label'], 'group' => $col['group'], 'index' => $index, 'span' => 1];
        }

        return $groups;
    }

    /** The same performance-summary wording as the printed sheet. */
    private function summaryLines(array $report): array
    {
        $formula = $report['configuration']['formula'] ?? [];

        return [
            ($formula['label'] ?? 'Formula').': '
                .($formula['numerator_label'] ?? 'Number of requests responded on time')
                .' / '.($formula['denominator_label'] ?? 'Total number of requests').' × 100%',
            'Total requests: '.$report['total'].' · On-time result: Not evaluated'
                .' (The approved institutional on-time response rule must be defined before a percentage or MET status can be shown.)',
        ];
    }

    private function sealFile(string $sealSrc): ?string
    {
        if (! preg_match('#^data:image/png;base64,(.+)$#', $sealSrc, $match)) {
            return null;
        }
        $file = tempnam(sys_get_temp_dir(), 'spmu_seal_').'.png';
        file_put_contents($file, base64_decode($match[1]));

        return $file;
    }
}
