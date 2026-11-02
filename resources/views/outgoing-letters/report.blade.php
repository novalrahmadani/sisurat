<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Agenda Surat Keluar</title>
    <style>
        @page { margin: 13mm 14mm 16mm; }
        body { color: #17251d; font-family: 'DejaVu Serif', serif; font-size: 9.2pt; line-height: 1.45; }
        h1 { margin: 0 0 2mm; text-align: center; font-size: 14pt; }
        .subtitle { margin: 0 0 5mm; text-align: center; font-size: 10pt; }
        .meta { margin: 0 0 4mm; font-size: 8.5pt; line-height: 1.7; }
        .trial { font-size: 8pt; color: #935b0c; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead { display: table-header-group; }
        th, td { border: .7pt solid #47564c; padding: 2mm; vertical-align: top; word-wrap: break-word; }
        th { background: #e6eee9; font-size: 9pt; text-align: center; }
        tbody tr { page-break-inside: avoid; }
        .center { text-align: center; }
        .empty { padding: 8mm; color: #647169; text-align: center; }
    </style>
</head>
<body>
    <h1>AGENDA SURAT KELUAR</h1>
    <p class="subtitle">Pusat Penerimaan Mahasiswa Baru - Universitas Nurul Huda</p>
    <div class="meta">
        <div><strong>Periode:</strong> {{ $filters['year'] ? 'Tahun '.$filters['year'] : 'Semua tahun' }}@if($filters['from']) | Mulai {{ date('d/m/Y', strtotime($filters['from'])) }}@endif @if($filters['to']) | Sampai {{ date('d/m/Y', strtotime($filters['to'])) }}@endif</div>
        <div><strong>Jumlah:</strong> {{ count($rows) }} surat | <strong>Status:</strong> {{ ['official' => 'Resmi', 'trial' => 'Uji coba', 'all' => 'Resmi dan uji coba'][$filters['status']] }} | <strong>Sumber:</strong> {{ ['' => 'SISURAT dan manual', 'sisurat' => 'SISURAT', 'manual' => 'Manual'][$filters['source']] }}</div>
        @if($filters['q'])<div><strong>Pencarian:</strong> {{ $filters['q'] }}</div>@endif
        @if($filters['type'])<div><strong>Jenis:</strong> {{ \App\Support\OutgoingLetterAgenda::TYPES[$filters['type']] }}</div>@endif
    </div>
    <table>
        <thead><tr><th style="width: 5%">NO.</th><th style="width: 25%">ALAMAT/PENERIMA</th><th style="width: 11%">TANGGAL</th><th style="width: 25%">PERIHAL</th><th style="width: 34%">NOMOR SURAT</th></tr></thead>
        <tbody>
            @forelse($rows as $row)
                <tr><td class="center">{{ $loop->iteration }}.</td><td>{{ $row['recipient'] }}</td><td class="center">{{ date('d/m/Y', strtotime($row['letter_date'])) }}</td><td>{{ $row['subject'] }}@if($row['is_trial'])<br><span class="trial">UJI COBA</span>@endif</td><td>{{ $row['letter_number'] }}</td></tr>
            @empty
                <tr><td colspan="5" class="empty">Belum ada surat pada periode dan filter yang dipilih.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
