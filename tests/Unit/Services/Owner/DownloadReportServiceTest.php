<?php

namespace Tests\Unit\Services\Owner;

use App\Services\FinancialReportService;
use App\Services\Owner\DownloadReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Unit\Services\ServiceTestData;
use Tests\Unit\Services\ServiceTestCase;

class DownloadReportServiceTest extends ServiceTestCase
{
    public function test_generate_pdf_report(): void
    {
        $owner = $this->createUser(ServiceTestData::ROLE_OWNER, ServiceTestData::EMAIL_OWNER);
        $token = explode('|', $owner->createToken(ServiceTestData::REPORT_TOKEN)->plainTextToken, ServiceTestData::TWO)[1];

        $reportService = Mockery::mock(FinancialReportService::class);
        $reportService->shouldReceive('getMonthlyReport')
            ->once()
            ->with(ServiceTestData::REPORT_MONTH, ServiceTestData::REPORT_YEAR)
            ->andReturn([
                'summary' => [
                    'gross_income' => ServiceTestData::ONE_HUNDRED_THOUSAND,
                    'total_expense' => ServiceTestData::FIFTEEN_THOUSAND,
                    'net_profit' => ServiceTestData::EIGHTEEN_THOUSAND,
                ],
                'transactions' => [
                    ['type' => 'income', 'amount' => ServiceTestData::ONE_HUNDRED_THOUSAND],
                    ['type' => 'expense', 'amount' => ServiceTestData::FIFTEEN_THOUSAND],
                ],
            ]);

        $pdf = Mockery::mock(DomPdf::class);
        $pdf->shouldReceive('output')->once()->andReturn(ServiceTestData::REPORT_PDF_CONTENT);
        Pdf::shouldReceive('loadHtml')->once()->andReturn($pdf);

        $result = new DownloadReportService($reportService)->generatePdfReport([
            't' => $token,
            'bulan' => ServiceTestData::REPORT_MONTH,
            'tahun' => ServiceTestData::REPORT_YEAR,
        ]);

        $this->assertSame(ServiceTestData::REPORT_PDF_CONTENT, $result['content']);
        $this->assertSame('application/pdf', $result['headers']['Content-Type']);
        $this->assertStringContainsString((string) ServiceTestData::REPORT_MONTH, $result['headers']['Content-Disposition']);
        $this->assertStringContainsString((string) ServiceTestData::REPORT_YEAR, $result['headers']['Content-Disposition']);

        $tenant = $this->createUser(ServiceTestData::ROLE_TENANT, ServiceTestData::EMAIL_TENANT_BUDI, ServiceTestData::CUSTOMER_BUDI);
        $tenantToken = explode('|', $tenant->createToken(ServiceTestData::REPORT_TOKEN)->plainTextToken, ServiceTestData::TWO)[1];

        try {
            new DownloadReportService($reportService)->generatePdfReport([
                't' => $tenantToken,
                'bulan' => ServiceTestData::REPORT_MONTH,
                'tahun' => ServiceTestData::REPORT_YEAR,
            ]);
            $this->fail('Token tenant seharusnya ditolak untuk laporan owner.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }
}
