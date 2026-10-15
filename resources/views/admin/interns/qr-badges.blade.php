{{-- Every intern's QR ID badge, printable — the same badge the profile
     page shows (dark header, initials, name, student ID, code), grouped
     by office with a shared "No Office" section at the end. Rendered by
     DomPDF: inline CSS only, no Tailwind. --}}
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Intern QR Codes</title>
<style>
    @page { margin: 11mm 12mm; }

    body { font-family: sans-serif; color: #111827; margin: 0; }

    .office-head {
        font-size: 12pt; font-weight: bold; color: #111827;
        margin: 10px 0 8px; padding-bottom: 3px;
        border-bottom: 1.5px solid #111827;
        page-break-after: avoid;
    }
    .office-head .count { font-weight: normal; font-size: 9pt; color: #6b7280; }

    .row { page-break-inside: avoid; }

    .badge {
        width: 86mm; display: inline-block; vertical-align: top;
        margin: 0 4mm 5mm 0;
        border: 1px solid #e5e7eb; border-radius: 3mm;
        overflow: hidden; background: #ffffff;
        page-break-inside: avoid;
    }

    .badge-head { background: #111827; color: #ffffff; text-align: center; padding: 8px 10px; }
    .badge-head .brand { font-size: 6.5pt; color: #9ca3af; text-transform: uppercase; }
    .badge-head .title { font-size: 10.5pt; font-weight: bold; margin-top: 2px; }

    .badge-body { text-align: center; padding: 10px 8px 12px; }

    .avatar {
        width: 44px; height: 44px; margin: 0 auto 6px;
        background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 22px;
        color: #6b7280; font-size: 15pt; font-weight: bold; line-height: 44px;
    }

    .name { font-size: 10pt; font-weight: bold; line-height: 1.2; }
    .sid { font-size: 8pt; color: #6b7280; margin-top: 1px; }

    .qr-box { margin: 8px auto 0; padding: 6px; width: 46mm; border: 1px solid #f3f4f6; border-radius: 3mm; }
    .qr-box img { width: 42mm; height: 42mm; }

    .foot { font-size: 6.5pt; color: #9ca3af; text-transform: uppercase; margin-top: 8px; }
</style>
</head>
<body>

@foreach($groups as $officeName => $rows)
    <div class="office-head">
        {{ $officeName }} <span class="count">— {{ $rows->count() }} intern{{ $rows->count() === 1 ? '' : 's' }}</span>
    </div>

    @foreach($rows->chunk(2) as $pair)
        <div class="row">
            @foreach($pair as $intern)
                <div class="badge">
                    <div class="badge-head">
                        <div class="brand">NORSU CAS — OJT Tracker</div>
                        <div class="title">Time In / Out</div>
                    </div>
                    <div class="badge-body">
                        <div class="avatar">{{ $intern->initials }}</div>
                        <div class="name">{{ $intern->full_name }}</div>
                        @if($intern->student_id)
                            <div class="sid">{{ $intern->student_id }}</div>
                        @endif
                        <div class="qr-box">
                            <img src="{{ $intern->qrDataUri() }}" alt="QR code">
                        </div>
                        <div class="foot">Present at the office scanner</div>
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
@endforeach

</body>
</html>
