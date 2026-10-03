<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Monthly Summary — {{ $intern->full_name }} — {{ $month->format('F Y') }}</title>
    <style>
        * { font-family: Helvetica, Arial, sans-serif; }
        body { margin: 0; padding: 0; color: #1a2437; font-size: 11px; line-height: 1.45; }
        h1 { font-size: 20px; margin: 0; color: #0f172a; }
        .brand { font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 2px; color: #4f46e5; }
        .subtitle { font-size: 10px; color: #5b6b87; margin-top: 2px; }

        .header { border-bottom: 3px solid #4f46e5; padding-bottom: 14px; margin-bottom: 16px; }
        .header table { width: 100%; border-collapse: collapse; }
        .report-no { text-align: right; font-size: 10px; color: #5b6b87; vertical-align: top; }
        .report-no strong { color: #1a2437; }

        table.details { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.details td { padding: 3px 0; font-size: 10.5px; }
        table.details td.label { font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.8px; color: #5b6b87; font-weight: bold; }
        table.details td.value { font-weight: bold; color: #0f172a; }

        table.totals { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.totals th { background: #f4f6f9; color: #3d4c66; text-align: left; padding: 7px 10px; font-size: 10px; }
        table.totals td { padding: 7px 10px; border-bottom: 1px solid #e5e9f0; font-size: 10.5px; }
        table.totals td.num { text-align: right; font-variant-numeric: tabular-nums; }
        table.totals tr.total td { font-weight: bold; color: #0f172a; border-bottom: none; }

        table.days { width: 100%; border-collapse: collapse; }
        table.days th { background: #f4f6f9; color: #3d4c66; text-align: left; padding: 6px 8px; font-size: 9.5px; text-transform: uppercase; letter-spacing: 0.6px; }
        table.days td { padding: 5px 8px; border-bottom: 1px solid #e5e9f0; font-size: 10px; }
        table.days td.num { text-align: right; font-variant-numeric: tabular-nums; }
        table.days tr:last-child td { border-bottom: none; }

        .progress { margin: 16px 0; }
        .progress .bar { width: 100%; height: 10px; background: #e5e9f0; border-radius: 5px; overflow: hidden; }
        .progress .fill { height: 10px; background: #4f46e5; border-radius: 5px; }
        .progress .caption { font-size: 9.5px; color: #5b6b87; margin-top: 4px; }

        .footer { margin-top: 22px; text-align: center; font-size: 8.5px; color: #5b6b87; }
    </style>
</head>
<body>

    <div class="header">
        <table>
            <tr>
                <td>
                    <div class="brand">NORSU CAS — OJT Tracker</div>
                    <h1>Monthly OJT Summary</h1>
                    <div class="subtitle">{{ $month->format('F Y') }}</div>
                </td>
                <td class="report-no">
                    Report No.<br>
                    <strong>MS-{{ $month->format('Ym') }}-{{ str_pad((string) $intern->id, 4, '0', STR_PAD_LEFT) }}</strong>
                </td>
            </tr>
        </table>
    </div>

    {{-- Intern details --}}
    <table class="details">
        <tr>
            <td width="25%"><div class="label">Intern</div><div class="value">{{ $intern->full_name }}</div></td>
            <td width="25%"><div class="label">Student ID</div><div class="value">{{ $intern->student_id }}</div></td>
            <td width="25%"><div class="label">OJT Track</div><div class="value">{{ $intern->ojt_track_label }}</div></td>
            <td width="25%"><div class="label">OJT Set</div><div class="value">{{ $intern->currentEnrollment?->label ?? '—' }}</div></td>
        </tr>
    </table>

    {{-- Month totals --}}
    <table class="totals">
        <thead>
            <tr>
                <th>Summary for {{ $month->format('F Y') }}</th>
                <th style="text-align: right;">Value</th>
            </tr>
        </thead>
        <tbody>
            <tr><td>Duty days rendered</td><td class="num">{{ $totals['days'] }}</td></tr>
            <tr><td>Regular hours</td><td class="num">{{ number_format($totals['regular'], 2) }}h</td></tr>
            <tr><td>Overtime hours</td><td class="num">{{ number_format($totals['overtime'], 2) }}h</td></tr>
            <tr class="total"><td>Total hours this month</td><td class="num">{{ number_format($totals['total'], 2) }}h</td></tr>
        </tbody>
    </table>

    {{-- Overall progress in the current set --}}
    <div class="progress">
        <div class="bar"><div class="fill" style="width: {{ min($intern->completion_percentage, 100) }}%"></div></div>
        <p class="caption">
            Overall progress: {{ number_format($intern->accumulated_hours, 1) }}h of {{ $intern->target_hours }}h
            ({{ $intern->completion_percentage }}%) on "{{ $intern->ojt_track_label }}"
        </p>
    </div>

    {{-- Per-day table --}}
    @php $t = fn($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('g:i A') : '—'; @endphp
    <table class="days">
        <thead>
            <tr>
                <th width="16%">Date</th>
                <th width="13%">AM In</th>
                <th width="13%">AM Out</th>
                <th width="13%">PM In</th>
                <th width="13%">PM Out</th>
                <th width="10%" style="text-align: right;">Hours</th>
                <th width="10%" style="text-align: right;">OT</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            @forelse($logs as $log)
                <tr>
                    <td>{{ $log->date->format('D, M d') }}</td>
                    <td>{{ $t($log->am_time_in) }}</td>
                    <td>{{ $t($log->am_time_out) }}</td>
                    <td>{{ $t($log->pm_time_in) }}</td>
                    <td>{{ $t($log->pm_time_out) }}</td>
                    <td class="num">{{ number_format($log->hours_rendered, 2) }}</td>
                    <td class="num">{{ $log->has_overtime ? number_format($log->overtime_hours, 2) : '—' }}</td>
                    <td>{{ $log->notes ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" style="text-align: center; padding: 20px 0; color: #5b6b87;">No duty days recorded this month.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">Generated {{ $generatedAt->format('M j, Y g:i A') }} — NORSU CAS OJT Tracker</div>

</body>
</html>
