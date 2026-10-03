<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Daily Report — {{ $log->date->format('F j, Y') }} — {{ $intern->full_name }}</title>
    <style>
        * { font-family: Helvetica, Arial, sans-serif; }
        body { margin: 0; padding: 0; color: #1a2437; font-size: 11px; line-height: 1.45; }
        h1 { font-size: 20px; margin: 0; color: #0f172a; }
        h2 { font-size: 9px; margin: 0 0 4px 0; text-transform: uppercase; letter-spacing: 1.2px; color: #3d4c66; font-weight: bold; }
        .brand { font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 2px; color: #2563eb; }
        .subtitle { font-size: 10px; color: #5b6b87; margin-top: 2px; }

        .header { border-bottom: 3px solid #2563eb; padding-bottom: 14px; margin-bottom: 16px; }
        .header table { width: 100%; border-collapse: collapse; }
        .report-no { text-align: right; font-size: 10px; color: #5b6b87; vertical-align: top; }
        .report-no strong { color: #1a2437; }

        table.details { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.details td { padding: 3px 0; font-size: 10.5px; }
        table.details td.label { font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.8px; color: #5b6b87; font-weight: bold; }
        table.details td.value { font-weight: bold; color: #0f172a; }

        .photo { margin-bottom: 16px; }
        .photo img { max-width: 100%; border: 1px solid #c7cfdd; border-radius: 6px; }
        .photo .missing { border: 1.5px dashed #c7cfdd; border-radius: 6px; padding: 34px 0; text-align: center; color: #5b6b87; }

        table.times { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        table.times th { background: #f4f6f9; color: #3d4c66; text-align: left; padding: 7px 10px; font-size: 10px; }
        table.times td { padding: 7px 10px; border-bottom: 1px solid #e5e9f0; font-size: 10.5px; }
        table.times tr:last-child td { border-bottom: none; }

        .hours { margin-bottom: 16px; }
        .hours table { width: 100%; border-collapse: collapse; }
        .hours td { padding: 3px 0; font-size: 10px; }
        .hours .total { font-weight: bold; color: #0f172a; }

        .notes { border: 1px solid #e5e9f0; border-radius: 6px; padding: 10px 12px; min-height: 46px; color: #1a2437; white-space: pre-wrap; }

        .signatures { width: 100%; border-collapse: collapse; margin-top: 46px; }
        .signatures td { width: 50%; text-align: center; font-size: 10px; }
        .signatures .line { border-top: 1px solid #3d4c66; margin-top: 36px; padding-top: 4px; font-size: 8.5px; color: #5b6b87; text-transform: uppercase; letter-spacing: 0.8px; }
        .signatures .name { font-weight: bold; color: #1a2437; }

        .footer { margin-top: 22px; text-align: center; font-size: 8.5px; color: #5b6b87; }
    </style>
</head>
<body>

    <div class="header">
        <table>
            <tr>
                <td>
                    <div class="brand">NORSU CAS — OJT Tracker</div>
                    <h1>Daily Duty Report</h1>
                    <div class="subtitle">{{ $log->date->format('l, F j, Y') }}</div>
                </td>
                <td class="report-no">
                    Report No.<br>
                    <strong>DR-{{ $log->date->format('Ymd') }}-{{ str_pad((string) $log->id, 4, '0', STR_PAD_LEFT) }}</strong>
                </td>
            </tr>
        </table>
    </div>

    {{-- Intern details --}}
    <table class="details">
        <tr>
            <td width="25%"><div class="label">Intern</div><div class="value">{{ $intern->full_name }}</div></td>
            <td width="25%"><div class="label">Student ID</div><div class="value">{{ $intern->student_id }}</div></td>
            <td width="25%"><div class="label">OJT Set</div><div class="value">{{ $log->ojtEnrollment->label ?? '—' }}</div></td>
            <td width="25%"><div class="label">OJT Track</div><div class="value">{{ $intern->ojt_track_label }}</div></td>
        </tr>
    </table>

    {{-- Proof photo (embedded as a data URI so it always renders, on screen and in the PDF) --}}
    <div class="photo">
        <h2>Duty Proof Photo</h2>
        @if($photoDataUri)
            <img src="{{ $photoDataUri }}" alt="Duty proof photo">
        @else
            <div class="missing">No proof photo uploaded for this day</div>
        @endif
    </div>

    {{-- Duty times --}}
    <h2>Duty Times</h2>
    @php $t = fn($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('g:i A') : '—'; @endphp
    <table class="times">
        <thead>
            <tr>
                <th width="34%">Session</th>
                <th>Time In</th>
                <th>Time Out</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>AM</td>
                <td>{{ $t($log->am_time_in) }}</td>
                <td>{{ $t($log->am_time_out) }}</td>
            </tr>
            <tr>
                <td>PM</td>
                <td>{{ $t($log->pm_time_in) }}</td>
                <td>{{ $t($log->pm_time_out) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Hours --}}
    <div class="hours">
        <table>
            <tr>
                <td>Regular hours</td>
                <td align="right">{{ number_format($log->regular_hours, 2) }}h</td>
            </tr>
            @if($log->has_overtime)
                <tr>
                    <td>Overtime hours</td>
                    <td align="right">+{{ number_format($log->overtime_hours, 2) }}h</td>
                </tr>
            @endif
            <tr>
                <td class="total">Total hours rendered</td>
                <td align="right" class="total">{{ number_format($log->hours_rendered, 2) }}h</td>
            </tr>
        </table>
    </div>

    {{-- Notes --}}
    <h2>Notes / Accomplishments</h2>
    <div class="notes">{{ $log->notes ?: 'No notes recorded for this day.' }}</div>

    {{-- Signatures --}}
    <table class="signatures">
        <tr>
            <td>
                <div class="name">{{ $intern->full_name }}</div>
                <div class="line">Intern Signature</div>
            </td>
            <td>
                <div class="name">{{ $log->loggedBy?->full_name ?? '—' }}</div>
                <div class="line">Recorded by (QR Scan / Admin)</div>
            </td>
        </tr>
    </table>

    <div class="footer">Generated {{ $generatedAt->format('M j, Y g:i A') }}</div>

</body>
</html>
