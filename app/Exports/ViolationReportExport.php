<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ViolationReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
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
            'Violation ID',
            'Date',
            'Employee Name',
            'Employee Code',
            'Department',
            'Type',
            'Severity',
            'Deviation (min)',
            'Status',
            'Notes',
        ];
    }

    /**
     * Map each record to the desired format
     */
    public function map($record): array
    {
        return [
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
                    'startColor' => ['rgb' => 'c0392b'],
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
        return 'Violation Report';
    }
}
