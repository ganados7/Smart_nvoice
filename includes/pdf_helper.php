<?php
declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

class PdfHelper
{
    private Dompdf $dompdf;

    public function __construct()
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'sans-serif');
        $this->dompdf = new Dompdf($options);
    }

    public function loadHtml(string $html): void
    {
        $this->dompdf->loadHtml($html);
    }

    public function setPaper(string $size = 'A4', string $orientation = 'portrait'): void
    {
        $this->dompdf->setPaper($size, $orientation);
    }

    public function render(): void
    {
        $this->dompdf->render();
    }

    public function output(): void
    {
        $this->dompdf->output();
    }

    public function stream(string $filename): void
    {
        $this->dompdf->stream($filename, ['Attachment' => true]);
    }

    public function getCanvas(): \Dompdf\Adapter\CPDF
    {
        return $this->dompdf->getCanvas();
    }

    public static function getPdfStyles(): string
    {
        return '
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: sans-serif; font-size: 11px; color: #1e293b; line-height: 1.5; }
            .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #6366f1; padding-bottom: 16px; margin-bottom: 20px; }
            .header h1 { font-size: 22px; color: #6366f1; margin-bottom: 2px; }
            .header p { font-size: 10px; color: #64748b; }
            .meta { text-align: right; }
            .meta .label { font-size: 9px; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; }
            .meta .value { font-size: 12px; font-weight: bold; }
            table { width: 100%; border-collapse: collapse; margin-top: 12px; }
            th { background: #f1f5f9; font-size: 9px; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; padding: 8px 10px; text-align: left; border-bottom: 1px solid #e2e8f0; }
            th.right, td.right { text-align: right; }
            td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; font-size: 11px; }
            tr:last-child td { border-bottom: none; }
            .total-row td { font-weight: bold; border-top: 2px solid #e2e8f0; padding-top: 10px; font-size: 12px; }
            .summary-box { margin-top: 20px; padding: 14px 18px; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc; }
            .summary-box .big { font-size: 18px; font-weight: 800; }
            .summary-box .label { font-size: 10px; color: #64748b; text-transform: uppercase; letter-spacing: 1px; }
            .footer { margin-top: 30px; text-align: center; font-size: 9px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 12px; }
            .text-muted { color: #64748b; }
            .text-right { text-align: right; }
            .text-green { color: #16a34a; }
            .text-red { color: #dc2626; }
            .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9px; font-weight: 600; }
            .badge-paid { background: #dcfce7; color: #16a34a; }
            .badge-pending { background: #fef3c7; color: #d97706; }
            .badge-partial { background: #dbeafe; color: #2563eb; }
            .badge-overdue { background: #fee2e2; color: #dc2626; }
        </style>';
    }

    public static function badge(string $status): string
    {
        $class = match($status) {
            'Paid' => 'badge-paid',
            'Overdue' => 'badge-overdue',
            'Partial' => 'badge-partial',
            default => 'badge-pending',
        };
        return '<span class="badge ' . $class . '">' . htmlspecialchars($status) . '</span>';
    }
}
