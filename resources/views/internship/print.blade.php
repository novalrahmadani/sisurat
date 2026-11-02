<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rekap Absensi Magang — {{ $monthLabel }}</title>
    <style>
        @page { size: A4 landscape; margin: 12mm; }
        * { box-sizing: border-box; } body { margin: 0; color: #172c24; background: #edf3ef; font-family: Cambria, 'Times New Roman', serif; }
        .tools { max-width: 1100px; margin: 24px auto; display: flex; justify-content: space-between; align-items: center; gap: 15px; padding: 0 12px; font: 14px Arial,sans-serif; }
        .tools button { padding: 13px 20px; border: 0; border-radius: 10px; color: white; background: #005a36; cursor: pointer; } a { color: #005a36; }
        main { max-width: 1100px; margin: 0 auto 30px; padding: 30px; background: white; }
        .letterhead { width: 100%; max-height: 125px; object-fit: contain; } h1 { margin: 22px 0 7px; text-align: center; font-size: 17px; } .subtitle { margin: 5px 0; text-align: center; font-size: 13px; } .filter { font-size: 11px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 20px; font-size: 10px; } th, td { border: 1px solid #97aaa0; padding: 6px; overflow-wrap: anywhere; vertical-align: middle; } th { background: #e7efe9; } thead { display: table-header-group; } tr { break-inside: avoid; } .center { text-align: center; } .signature { display: block; margin: 3px auto; width: 95px; height: 35px; object-fit: contain; } .muted { color: #61746a; } .empty { padding: 28px; text-align: center; }
        @media print { body { background: white; } .tools { display: none; } main { max-width: none; margin: 0; padding: 0; } .letterhead { max-height: 105px; } th { print-color-adjust: exact; -webkit-print-color-adjust: exact; } }
    </style>
</head>
<body>
    <div class="tools"><a href="{{ route('internships.index', ['month' => $filters['month'], 'q' => $filters['q']]) }}">← Kembali ke rekap</a><button type="button" onclick="window.print()">Cetak / Simpan PDF</button></div>
    <main>
        <img class="letterhead" src="{{ asset('images/universitas-nurul-huda-letterhead.png') }}" alt="Kop Universitas Nurul Huda">
        <h1>REKAP ABSENSI MAGANG</h1><p class="subtitle">PUSAT PENERIMAAN MAHASISWA BARU UNIVERSITAS NURUL HUDA</p><p class="subtitle">Periode: {{ $monthLabel }} · Waktu Indonesia Barat (WIB)</p>
        @if ($filters['q'])<p class="filter">Pencarian peserta: {{ $filters['q'] }}</p>@endif
        <table>
            <colgroup><col style="width:4%"><col style="width:10%"><col style="width:20%"><col style="width:17%"><col style="width:14%"><col style="width:14%"><col style="width:21%"></colgroup>
            <thead><tr><th>No.</th><th>Tanggal</th><th>Nama / Identitas</th><th>Kategori / Asal Institusi</th><th>Masuk &amp; Tanda Tangan</th><th>Pulang &amp; Tanda Tangan</th><th>Keterangan</th></tr></thead>
            <tbody>
                @forelse ($records as $record)
                    <tr><td class="center">{{ $loop->iteration }}</td><td class="center">{{ $record->attendance_date->format('d/m/Y') }}</td><td><strong>{{ $record->participant->name }}</strong><br>{{ $record->participant->student_number }}</td><td>{{ $record->participant->categoryLabel() }}<br>{{ $record->participant->institution }}<br><span class="muted">{{ $record->participant->study_program }}</span></td><td class="center">{{ $record->checked_in_at->timezone('Asia/Jakarta')->format('H:i:s') }}<img class="signature" src="data:image/png;base64,{{ $record->check_in_signature }}" alt="Tanda tangan masuk"></td><td class="center">@if ($record->checked_out_at){{ $record->checked_out_at->timezone('Asia/Jakarta')->format('H:i:s') }}<img class="signature" src="data:image/png;base64,{{ $record->check_out_signature }}" alt="Tanda tangan pulang">@else<span class="muted">Belum absen pulang</span>@endif</td><td>@if ($record->check_in_note)<strong>Masuk:</strong> {{ $record->check_in_note }}<br>@endif @if ($record->check_out_note)<strong>Pulang:</strong> {{ $record->check_out_note }}@endif</td></tr>
                @empty<tr><td colspan="7" class="empty">Belum ada catatan absensi pada pilihan ini.</td></tr>@endforelse
            </tbody>
        </table>
        <p class="filter muted">Rekap mencakup seluruh catatan pada bulan dan pencarian yang dipilih. Hari tanpa absensi tidak dimasukkan sebagai catatan kehadiran.</p>
    </main>
</body>
</html>
