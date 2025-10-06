<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Reporting\Services\ReportExporter;
use App\Domain\Reporting\Services\ReportGenerator;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class ReportController extends Controller
{
    public function __construct(
        protected ReportGenerator $reportGenerator,
        protected ReportExporter $reportExporter
    ) {}

    /**
     * Generate attendance report
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function attendanceReport(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'from_date' => 'required|date|before_or_equal:to_date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'employee_id' => 'nullable|integer|exists:employees,id',
            'department_id' => 'nullable|integer|exists:departments,id',
            'status' => 'nullable|in:present,absent,half-day,on-leave,holiday',
            'format' => 'nullable|in:json,pdf,excel,csv',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $filters = $request->only(['employee_id', 'department_id', 'status']);
        $format = $request->input('format', 'json');

        try {
            $report = $this->reportGenerator->generateAttendanceReport(
                $request->input('from_date'),
                $request->input('to_date'),
                $filters
            );

            if ($format === 'json') {
                return response()->json([
                    'success' => true,
                    'data' => $report->toArray(),
                ]);
            }

            return $this->exportReport($report->toArray(), 'attendance', $format);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate violation report
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function violationReport(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'from_date' => 'required|date|before_or_equal:to_date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'employee_id' => 'nullable|integer|exists:employees,id',
            'department_id' => 'nullable|integer|exists:departments,id',
            'type' => 'nullable|in:late_arrival,early_departure,missing_checkout,extended_break,missing_checkin',
            'severity' => 'nullable|in:minor,moderate,major,critical',
            'status' => 'nullable|in:pending,acknowledged,disputed,resolved',
            'format' => 'nullable|in:json,pdf,excel,csv',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $filters = $request->only(['employee_id', 'department_id', 'type', 'severity', 'status']);
        $format = $request->input('format', 'json');

        try {
            $report = $this->reportGenerator->generateViolationReport(
                $request->input('from_date'),
                $request->input('to_date'),
                $filters
            );

            if ($format === 'json') {
                return response()->json([
                    'success' => true,
                    'data' => $report->toArray(),
                ]);
            }

            return $this->exportReport($report->toArray(), 'violation', $format);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download a generated report file
     *
     * @param  Request  $request
     * @return Response
     */
    public function downloadReport(Request $request): Response
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $filename = basename($request->input('file'));
        $path = storage_path("app/reports/{$filename}");

        if (! file_exists($path)) {
            return response([
                'success' => false,
                'message' => 'File not found',
            ], 404);
        }

        // Determine MIME type
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'csv' => 'text/csv',
        ];

        $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';

        return response()->download($path, $filename, [
            'Content-Type' => $mimeType,
        ]);
    }

    /**
     * Export report to specified format
     */
    protected function exportReport(array $reportData, string $reportType, string $format): JsonResponse
    {
        try {
            $path = match ($format) {
                'pdf' => $this->reportExporter->exportToPdf($reportData, "{$reportType}-report"),
                'excel' => $this->reportExporter->exportToExcel($reportData, $reportType),
                'csv' => $this->reportExporter->exportToCsv($reportData, $reportType),
                default => throw new \InvalidArgumentException('Invalid format'),
            };

            $filename = basename($path);

            return response()->json([
                'success' => true,
                'message' => 'Report generated successfully',
                'file' => $filename,
                'download_url' => route('api.v1.reports.download', ['file' => $filename]),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export report',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
