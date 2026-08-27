<?php

namespace App\Services\Owner;

use App\Services\FinancialReportService;
use App\Enums\UserRole;
use Carbon\Carbon;
use Laravel\Sanctum\PersonalAccessToken;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DownloadReportService
{
    protected FinancialReportService $financialReportService;

    public function __construct(FinancialReportService $financialReportService)
    {
        $this->financialReportService = $financialReportService;
    }

    public function generatePdfReport(array $validatedData): array
    {
        $token = PersonalAccessToken::findToken($validatedData['t']);

        if (!$token || !$token->tokenable || $token->tokenable->role !== UserRole::OWNER->value) {
            throw new HttpException(403, 'Akses Ditolak: Sesi Anda tidak valid atau Anda bukan Owner.');
        }

        $data = $this->financialReportService->getMonthlyData((int) $validatedData['bulan'], (int) $validatedData['tahun']);

        $html = view('pdf.laporan-bulanan', [
            'monthName'     => $data['month'],
            'year'          => $data['year'],
            'total_income'  => $data['total_income'],
            'total_expense' => $data['total_expense'],
            'net_profit'    => $data['net_profit'],
            'income'        => $data['details']['income'],
            'expense'       => $data['details']['expense'],
            'generateAt'    => Carbon::now()->format('d/m/Y H:i'),
        ])->render();

        $pdf = Pdf::loadHtml($html);

        return [
            'content' => $pdf->output(),
            'headers' => [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="laporan-bulanan-' . $validatedData['bulan'] . '-' . $validatedData['tahun'] . '.pdf"',
            ]
        ];
    }
}
