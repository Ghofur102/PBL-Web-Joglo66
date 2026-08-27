<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Owner\DownloadReportRequest;
use App\Services\Owner\DownloadReportService;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class DownloadReportController extends Controller
{
    protected DownloadReportService $downloadReportService;

    public function __construct(DownloadReportService $downloadReportService)
    {
        $this->downloadReportService = $downloadReportService;
    }

    public function download(DownloadReportRequest $request): Response
    {
        $status = 200;
        $headers = [];
        $content = '';

        try {
            $result = $this->downloadReportService->generatePdfReport($request->validated());

            $content = $result['content'];
            $headers = $result['headers'];
        } catch (HttpException $e) {
            $status = $e->getStatusCode();
            $content = $e->getMessage();
            $headers = ['Content-Type' => 'text/plain'];
        } catch (Throwable $e) {
            $status = 500;
            $content = 'Terjadi kesalahan internal server: ' . $e->getMessage();
            $headers = ['Content-Type' => 'text/plain'];
        }

        return response($content, $status, $headers);
    }
}
