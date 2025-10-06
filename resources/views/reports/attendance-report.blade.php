<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            color: #2c3e50;
        }
        .header .period {
            font-size: 14px;
            color: #7f8c8d;
            margin-top: 5px;
        }
        .summary {
            background-color: #f8f9fa;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .summary-grid {
            display: table;
            width: 100%;
        }
        .summary-row {
            display: table-row;
        }
        .summary-cell {
            display: table-cell;
            padding: 8px;
            width: 25%;
        }
        .summary-label {
            font-weight: bold;
            color: #555;
        }
        .summary-value {
            font-size: 18px;
            color: #2c3e50;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th {
            background-color: #34495e;
            color: white;
            padding: 10px;
            text-align: left;
            font-weight: bold;
        }
        td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .status-present { color: #27ae60; font-weight: bold; }
        .status-absent { color: #e74c3c; font-weight: bold; }
        .status-half-day { color: #f39c12; font-weight: bold; }
        .status-on-leave { color: #3498db; font-weight: bold; }
        .status-holiday { color: #95a5a6; font-weight: bold; }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #7f8c8d;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Attendance Report</h1>
        <div class="period">
            Period: {{ $data['period']['from'] }} to {{ $data['period']['to'] }}
        </div>
        @if(!empty($data['filters']))
            <div class="period" style="margin-top: 5px;">
                Filters:
                @if(isset($data['filters']['employee_id']))
                    Employee ID: {{ $data['filters']['employee_id'] }}
                @endif
                @if(isset($data['filters']['department_id']))
                    Department ID: {{ $data['filters']['department_id'] }}
                @endif
                @if(isset($data['filters']['status']))
                    Status: {{ ucfirst($data['filters']['status']) }}
                @endif
            </div>
        @endif
    </div>

    <div class="summary">
        <h3 style="margin-top: 0;">Summary Statistics</h3>
        <div class="summary-grid">
            <div class="summary-row">
                <div class="summary-cell">
                    <div class="summary-label">Total Employees</div>
                    <div class="summary-value">{{ number_format($data['summary']['total_employees']) }}</div>
                </div>
                <div class="summary-cell">
                    <div class="summary-label">Total Records</div>
                    <div class="summary-value">{{ number_format($data['summary']['total_records']) }}</div>
                </div>
                <div class="summary-cell">
                    <div class="summary-label">Total Work Hours</div>
                    <div class="summary-value">{{ number_format($data['summary']['total_work_hours'], 2) }}</div>
                </div>
                <div class="summary-cell">
                    <div class="summary-label">Attendance Rate</div>
                    <div class="summary-value">{{ number_format($data['summary']['attendance_rate'], 1) }}%</div>
                </div>
            </div>
            <div class="summary-row">
                <div class="summary-cell">
                    <div class="summary-label">Avg Hours/Employee</div>
                    <div class="summary-value">{{ number_format($data['summary']['average_work_hours_per_employee'], 2) }}</div>
                </div>
                <div class="summary-cell">
                    <div class="summary-label">Total Overtime</div>
                    <div class="summary-value">{{ number_format($data['summary']['total_overtime_hours'], 2) }}</div>
                </div>
                <div class="summary-cell" colspan="2">
                    <div class="summary-label">Status Breakdown</div>
                    <div class="summary-value" style="font-size: 12px;">
                        @foreach($data['summary']['status_breakdown'] as $status => $count)
                            <span class="status-{{ $status }}">{{ ucfirst($status) }}: {{ $count }}</span>
                            @if(!$loop->last) | @endif
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Employee</th>
                <th>Department</th>
                <th>Check In</th>
                <th>Check Out</th>
                <th>Work Hours</th>
                <th>Break Hours</th>
                <th>Overtime</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['records'] as $record)
            <tr>
                <td>{{ $record['date'] }}</td>
                <td>{{ $record['employee_name'] }}<br><small>{{ $record['employee_code'] }}</small></td>
                <td>{{ $record['department'] }}</td>
                <td>{{ $record['first_check_in'] ?? '-' }}</td>
                <td>{{ $record['last_check_out'] ?? '-' }}</td>
                <td>{{ number_format($record['total_work_hours'], 2) }}</td>
                <td>{{ number_format($record['total_break_hours'], 2) }}</td>
                <td>{{ number_format($record['overtime_hours'], 2) }}</td>
                <td><span class="status-{{ $record['status'] }}">{{ ucfirst($record['status']) }}</span></td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Generated on {{ now()->format('Y-m-d H:i:s') }} | Multi-Tenant Attendance System
    </div>
</body>
</html>
