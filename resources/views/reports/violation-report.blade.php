<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Violation Report</title>
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
            color: #c0392b;
        }
        .header .period {
            font-size: 14px;
            color: #7f8c8d;
            margin-top: 5px;
        }
        .summary {
            background-color: #fff5f5;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            border-left: 4px solid #e74c3c;
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
            width: 33%;
        }
        .summary-label {
            font-weight: bold;
            color: #555;
        }
        .summary-value {
            font-size: 18px;
            color: #c0392b;
        }
        .repeat-offenders {
            background-color: #fff9e6;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            border-left: 4px solid #f39c12;
        }
        .offender-list {
            margin-top: 10px;
        }
        .offender-item {
            padding: 8px;
            border-bottom: 1px solid #eee;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th {
            background-color: #c0392b;
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
        .severity-minor { color: #95a5a6; }
        .severity-moderate { color: #f39c12; font-weight: bold; }
        .severity-major { color: #e67e22; font-weight: bold; }
        .severity-critical { color: #c0392b; font-weight: bold; }
        .status-pending { color: #e74c3c; }
        .status-acknowledged { color: #f39c12; }
        .status-disputed { color: #3498db; }
        .status-resolved { color: #27ae60; }
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
        <h1>Violation Report</h1>
        <div class="period">
            Period: {{ $data['period']['from'] }} to {{ $data['period']['to'] }}
        </div>
        @if(!empty($data['filters']))
            <div class="period" style="margin-top: 5px;">
                Filters:
                @if(isset($data['filters']['employee_id']))
                    Employee ID: {{ $data['filters']['employee_id'] }}
                @endif
                @if(isset($data['filters']['type']))
                    Type: {{ ucfirst(str_replace('_', ' ', $data['filters']['type'])) }}
                @endif
                @if(isset($data['filters']['severity']))
                    Severity: {{ ucfirst($data['filters']['severity']) }}
                @endif
            </div>
        @endif
    </div>

    <div class="summary">
        <h3 style="margin-top: 0;">Summary Statistics</h3>
        <div class="summary-grid">
            <div class="summary-row">
                <div class="summary-cell">
                    <div class="summary-label">Total Violations</div>
                    <div class="summary-value">{{ number_format($data['summary']['total_violations']) }}</div>
                </div>
                <div class="summary-cell">
                    <div class="summary-label">Employees Affected</div>
                    <div class="summary-value">{{ number_format($data['summary']['employees_with_violations']) }}</div>
                </div>
                <div class="summary-cell">
                    <div class="summary-label">Repeat Offenders</div>
                    <div class="summary-value">{{ count($data['summary']['repeat_offenders']) }}</div>
                </div>
            </div>
        </div>

        <div style="margin-top: 15px;">
            <div style="display: inline-block; width: 48%; vertical-align: top;">
                <strong>Type Breakdown:</strong><br>
                @foreach($data['summary']['type_breakdown'] as $type => $count)
                    <span style="font-size: 11px;">{{ ucfirst(str_replace('_', ' ', $type)) }}: {{ $count }}</span><br>
                @endforeach
            </div>
            <div style="display: inline-block; width: 48%; vertical-align: top;">
                <strong>Severity Breakdown:</strong><br>
                @foreach($data['summary']['severity_breakdown'] as $severity => $count)
                    <span class="severity-{{ $severity }}" style="font-size: 11px;">{{ ucfirst($severity) }}: {{ $count }}</span><br>
                @endforeach
            </div>
        </div>
    </div>

    @if(!empty($data['summary']['repeat_offenders']))
    <div class="repeat-offenders">
        <h3 style="margin-top: 0;">Repeat Offenders (5+ Violations)</h3>
        <div class="offender-list">
            @foreach($data['summary']['repeat_offenders'] as $offender)
            <div class="offender-item">
                <strong>{{ $offender['employee_name'] }}</strong> ({{ $offender['employee_code'] }})
                - <span style="color: #c0392b;">{{ $offender['violation_count'] }} violations</span>
                - Most common: {{ ucfirst(str_replace('_', ' ', $offender['most_common_type'])) }}
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Employee</th>
                <th>Department</th>
                <th>Type</th>
                <th>Severity</th>
                <th>Deviation</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['records'] as $record)
            <tr>
                <td>{{ $record['violation_date'] }}</td>
                <td>{{ $record['employee_name'] }}<br><small>{{ $record['employee_code'] }}</small></td>
                <td>{{ $record['department'] }}</td>
                <td>{{ ucfirst(str_replace('_', ' ', $record['type'])) }}</td>
                <td><span class="severity-{{ $record['severity'] }}">{{ ucfirst($record['severity']) }}</span></td>
                <td>{{ $record['minutes_deviation'] }} min</td>
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
