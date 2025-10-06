<?php

namespace App\Domain\Reporting\Services;

use App\Exports\AttendanceReportExport;
use App\Exports\ViolationReportExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class ReportExporter
{
    /**
     * Export report to PDF format
     *
     * @param  array  $reportData  Report data array from DTO
     * @param  string  $templateName  Blade template name (attendance-report or violation-report)
     * @return string  Path to generated PDF file
     */
    public function exportToPdf(array $reportData, string $templateName): string
    {
        $pdf = Pdf::loadView("reports.{$templateName}", ['data' => $reportData]);

        $filename = $this->generateFilename($templateName, 'pdf');
        $path = storage_path("app/reports/{$filename}");

        // Ensure directory exists
        if (! file_exists(storage_path('app/reports'))) {
            mkdir(storage_path('app/reports'), 0755, true);
        }

        $pdf->save($path);

        return $path;
    }

    /**
     * Export report to Excel format
     *
     * @param  array  $reportData  Report data array from DTO
     * @param  string  $reportType  Type of report (attendance or violation)
     * @return string  Path to generated Excel file
     */
    public function exportToExcel(array $reportData, string $reportType): string
    {
        $filename = $this->generateFilename("{$reportType}-report", 'xlsx');
        $path = "reports/{$filename}";

        $exportClass = $reportType === 'attendance'
            ? new AttendanceReportExport($reportData)
            : new ViolationReportExport($reportData);

        Excel::store($exportClass, $path, 'local');

        return storage_path("app/{$path}");
    }

    /**
     * Export report to CSV format
     *
     * @param  array  $reportData  Report data array from DTO
     * @param  string  $reportType  Type of report (attendance or violation)
     * @return string  Path to generated CSV file
     */
    public function exportToCsv(array $reportData, string $reportType): string
    {
        $filename = $this->generateFilename("{$reportType}-report", 'csv');
        $path = storage_path("app/reports/{$filename}");

        // Ensure directory exists
        if (! file_exists(storage_path('app/reports'))) {
            mkdir(storage_path('app/reports'), 0755, true);
        }

        $file = fopen($path, 'w');

        // Determine headers based on report type
        if ($reportType === 'attendance') {
            $headers = [
                'Date', 'Employee Name', 'Employee Code', 'Department',
                'Check In', 'Check Out', 'Work Hours', 'Break Hours',
                'Overtime Hours', 'Status',
            ];
            fputcsv($file, $headers);

            foreach ($reportData['records'] as $record) {
                fputcsv($file, [
                    $record['date'],
                    $record['employee_name'],
                    $record['employee_code'],
                    $record['department'],
                    $record['first_check_in'] ?? '-',
                    $record['last_check_out'] ?? '-',
                    number_format($record['total_work_hours'], 2),
                    number_format($record['total_break_hours'], 2),
                    number_format($record['overtime_hours'], 2),
                    ucfirst($record['status']),
                ]);
            }
        } else {
            // Violation report
            $headers = [
                'Violation ID', 'Date', 'Employee Name', 'Employee Code',
                'Department', 'Type', 'Severity', 'Deviation (min)',
                'Status', 'Notes',
            ];
            fputcsv($file, $headers);

            foreach ($reportData['records'] as $record) {
                fputcsv($file, [
                    $record['violation_id'],
                    $record['violation_date'],
                    $record['employee_name'],
                    $record['employee_code'],
                    $record['department'],
                    ucfirst(str_replace('_', ' ', $record['type'])),
                    ucfirst($record['severity']),
                    $record['minutes_deviation'],
                    ucfirst($record['status']),
                    $record['notes'] ?? '-',
                ]);
            }
        }

        fclose($file);

        return $path;
    }

    /**
     * Generate unique filename for export
     */
    protected function generateFilename(string $baseName, string $extension): string
    {
        return $baseName.'-'.now()->format('Y-m-d-His').'.'.$extension;
    }

    /**
     * Clean up old report files (older than X days)
     *
     * @param  int  $days  Number of days to keep files
     */
    public function cleanupOldReports(int $days = 7): int
    {
        $path = storage_path('app/reports');

        if (! file_exists($path)) {
            return 0;
        }

        $files = glob("{$path}/*");
        $now = time();
        $deleted = 0;

        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) >= 60 * 60 * 24 * $days) {
                    unlink($file);
                    $deleted++;
                }
            }
        }

        return $deleted;
    }
}
