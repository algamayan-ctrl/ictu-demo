<?php

namespace App\Reports\Exporters;

use App\Reports\ReportDataset;
use App\Reports\ReportExportOptions;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * The official PDF copy of a report.
 *
 * dompdf renders the same document Blade the browser preview uses, so the
 * printed record and the reviewed record are one design. Page numbering is
 * stamped from the canvas because that is the only place the real page count
 * is known — the footer never claims a page total it cannot see.
 */
class PdfExporter
{
    public function render(ReportDataset $dataset, ReportExportOptions $options): string
    {
        $contentOptions = $options->contentToggles();

        /*
         * The PDF footer is drawn directly on the DomPDF canvas below.
         * Do not render the normal HTML footer as part of the document
         * body, otherwise it appears immediately after the records.
         */
        $contentOptions['footer'] = false;

        $html = view('reports.pdf', [
            'dataset' => $dataset,
            'options' => $contentOptions,
            'exportOptions' => $options,
            /*
             * dompdf resolves no remote assets, so the seal travels with the
             * document as a data URI rather than a URL that will not load.
             */
            'sealSrc' => $this->seal(),
        ])->render();

        $pdfOptions = new Options;
        $pdfOptions->set('isRemoteEnabled', false);
        $pdfOptions->set('isHtml5ParserEnabled', true);
        $pdfOptions->set('defaultFont', 'Helvetica');

        $pdf = new Dompdf($pdfOptions);
        $pdf->setPaper($this->paper($options->pageSize), $options->orientation);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->render();

        $this->stampPageNumbers($pdf);

        return (string) $pdf->output();
    }

    /**
     * "Page X of Y" written once the layout is known, so the total is the
     * real one.
     */
    /**
     * Draw the formal institutional footer directly on every PDF page.
     *
     * DomPDF owns the real page count, so the institutional note and
     * "Page X of Y" are stamped together after layout is complete.
     */
    private function stampPageNumbers(Dompdf $pdf): void
    {
        $canvas = $pdf->getCanvas();
        $fontMetrics = $pdf->getFontMetrics();

        $regular = $fontMetrics->getFont('Helvetica');
        $fontSize = 7.5;

        $canvas->page_script(
            function (
                int $pageNumber,
                int $pageCount,
                $canvas,
                $fontMetrics
            ) use ($regular, $fontSize): void {

                $width = $canvas->get_width();
                $height = $canvas->get_height();

                /*
                 * Keep the footer inside the physical page edge while
                 * leaving a clean institutional margin.
                 */
                $left = 36;
                $right = $width - 36;

                $ruleY = $height - 42;
                $firstLineY = $height - 32;
                $secondLineY = $height - 22;

                $textColor = [0.28, 0.32, 0.37];
                $ruleColor = [0.62, 0.66, 0.70];

                /*
                 * Institutional separator.
                 */
                $canvas->line(
                    $left,
                    $ruleY,
                    $right,
                    $ruleY,
                    $ruleColor,
                    0.55
                );

                /*
                 * Left-side institutional note.
                 */
                $canvas->text(
                    $left,
                    $firstLineY,
                    'Prepared from official SPMU-ACPMP operational records.',
                    $regular,
                    $fontSize,
                    $textColor
                );

                $canvas->text(
                    $left,
                    $secondLineY,
                    'This is a system-generated report. No signature is required.',
                    $regular,
                    $fontSize,
                    $textColor
                );

                /*
                 * Real page numbering.
                 * Calculate the text width so Page X of Y is truly
                 * right-aligned with the page margin.
                 */
                $pageLabel =
                    'Page '.$pageNumber.' of '.$pageCount;

                $pageWidth =
                    $fontMetrics->getTextWidth(
                        $pageLabel,
                        $regular,
                        $fontSize
                    );

                $canvas->text(
                    $right - $pageWidth,
                    $firstLineY,
                    $pageLabel,
                    $regular,
                    $fontSize,
                    $textColor
                );
            }
        );
    }
    private function paper(string $pageSize): string
    {
        return match ($pageSize) {
            'LETTER' => 'letter',
            'LEGAL' => 'legal',
            default => 'a4',
        };
    }

    private function seal(): ?string
    {
        /*
         * The compact seal, not the full-resolution one: it is drawn at 44pt,
         * so the large source adds nothing a reader can see while costing
         * dompdf a great deal of memory on every export.
         */
        foreach (['images/cspc-seal-document.png', 'images/cspc-logo.png'] as $candidate) {
            $path = public_path($candidate);

            if (is_file($path)) {
                return 'data:image/png;base64,'.base64_encode((string) file_get_contents($path));
            }
        }

        return null;
    }
}
