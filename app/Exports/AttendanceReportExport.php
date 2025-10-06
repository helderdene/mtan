<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AttendanceReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected $reportData;

    public function __construct($reportData)
    {
        $this->reportData = $reportData;
    }

    /**
     * Return the collection of records
     */
    public function collection()
    {
        return collect($this->reportData['records']);
    }

    /**
     * Define the column headings
     */
    public function headings(): array
    {
        return [
            'Date',
            'Employee Name',
            'Employee Code',
            'Department',
            'Check In',
            'Check Out',
            'Work Hours',
            'Break Hours',
            'Overtime Hours',
            'Status',
        ];
    }

    /**
     * Map each record to the desired format
     */
    public function map($record): array
    {
        return [
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
        ];
    }

    /**
     * Apply styles to the worksheet
     */
    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '34495e'],
                ],
                'font' => ['color' => ['rgb' => 'FFFFFF'], 'bold' => true],
            ],
        ];
    }

    /**
     * Set the worksheet title
     */
    public function title(): string
    {
        return 'Attendance Report';
    }
}
