<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { size: A4 landscape; margin: 10mm 9mm 12mm; }
        * { box-sizing: border-box; }
        body { color: #18241c; font-family: Cambria, "DejaVu Serif", serif; font-size: 9pt; line-height: 1.32; }
        .letterhead-wrap { width: 100%; margin: 0 0 4mm; text-align: center; }
        .letterhead { display: inline-block; width: 220mm; max-width: 100%; height: auto; vertical-align: top; }
        h1 { margin: 0; color: #174f37; text-align: center; font-size: 16pt; font-weight: 700; }
        .subtitle { margin: 2mm 0 5mm; text-align: center; color: #506057; font-size: 9pt; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th, td { border: .25mm solid #aab8af; padding: 2.1mm 1.6mm; vertical-align: top; overflow-wrap: anywhere; }
        th { background: #e8f1ec; color: #174f37; font-size: 8pt; line-height: 1.25; text-align: center; vertical-align: middle; }
        tbody tr:nth-child(even) td { background: #f8fbf9; }
        .number { width: 8mm; text-align: center; }
        .program { width: 32mm; }
        .name { width: 36mm; }
        .school { width: 38mm; }
        .district { width: 31mm; }
        .address { width: 52mm; }
        .affiliate { width: 45mm; }
        .date { width: 37mm; text-align: center; white-space: nowrap; }
        .empty { padding: 9mm; text-align: center; color: #506057; }
        .footer { margin-top: 4.5mm; text-align: right; color: #506057; font-size: 8.5pt; }
    </style>
</head>
<body>
    @if ($letterhead)
        <div class="letterhead-wrap">
            <img class="letterhead" src="{{ $letterhead }}" alt="Kop Universitas Nurul Huda">
        </div>
    @endif
    <h1>DATA CALON MAHASISWA BARU</h1>
    <p class="subtitle">Universitas Nurul Huda · {{ $submissions->count() }} data · Dicetak {{ $exportedAt->format('d-m-Y H:i') }} WIB</p>
    <table>
        <thead>
            <tr>
                <th class="number">No.</th>
                <th class="name">Nama calon mahasiswa</th>
                <th class="program">Program studi</th>
                <th class="school">Asal sekolah</th>
                <th class="district">Kabupaten/kota</th>
                <th class="address">Alamat sekolah</th>
                <th class="affiliate">Dosen/guru pembawa</th>
                <th class="date">Waktu masuk</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($submissions as $index => $submission)
                <tr>
                    <td class="number">{{ $index + 1 }}</td>
                    <td>{{ $submission->name }}</td>
                    <td>{{ $submission->study_program }}</td>
                    <td>{{ $submission->school_name }}</td>
                    <td>{{ $submission->school_district }}</td>
                    <td>{{ $submission->school_address }}</td>
                    <td>{{ $submission->affiliate_name }}</td>
                    <td>{{ $submission->submitted_at->timezone('Asia/Jakarta')->format('d-m-Y H:i') }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty">Belum ada data calon mahasiswa yang masuk.</td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="footer">Sekretariat PMB · Universitas Nurul Huda</p>
</body>
</html>
