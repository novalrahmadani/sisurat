<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Hasil Wawancara - {{ $interview->title }}</title>
    <style>
        @page { size: A4 landscape; margin: 14mm 16mm 18mm; }
        body { margin: 0; color: #17251d; font-family: 'DejaVu Serif', serif; font-size: 10pt; line-height: 1.5; }
        .letterhead-wrap { margin: 0 0 3mm; text-align: center; }
        .letterhead { width: 190mm; height: auto; vertical-align: middle; }
        h1 { margin: 0 0 2mm; text-align: center; font-size: 13pt; line-height: 1.5; }
        .form-title { margin: 0 0 3mm; text-align: center; font-size: 11pt; font-weight: bold; word-wrap: break-word; }
        .report-meta { margin-bottom: 3mm; font-size: 9pt; }
        .report-meta p { margin: 1mm 0; }
        .report-meta .label { display: inline-block; width: 32mm; }
        .section-title { margin: 3mm 0 2mm; font-size: 11pt; color: #005a36; page-break-after: avoid; }
        .center { text-align: center; }
        .qa-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 9.2pt; line-height: 1.45; }
        .qa-table thead { display: table-header-group; }
        .qa-table th, .qa-table td { border: .6pt solid #bccbc1; padding: 1.5mm 2.2mm; vertical-align: top; word-wrap: break-word; }
        .qa-table th { background: #e7f0eb; text-align: left; font-weight: bold; }
        .qa-table th.number { padding-left: 1mm; padding-right: 1mm; text-align: center; white-space: nowrap; }
        .qa-table tbody tr { page-break-inside: avoid; }
        .qa-question, .qa-answer { margin: 0; white-space: pre-wrap; word-wrap: break-word; }
        .qa-question { font-weight: bold; }
        .identity { font-size: 8.3pt; line-height: 1.5; }
        .qa-meta { margin: 1.5mm 0 0; color: #647169; font-size: 7.2pt; line-height: 1.4; }
        .empty { color: #647169; font-style: italic; }
    </style>
</head>
<body>
    <div class="letterhead-wrap"><img class="letterhead" src="{{ $letterhead }}" alt="Kop resmi Universitas Nurul Huda"></div>
    <h1>LAPORAN HASIL TES WAWANCARA MAHASISWA BARU</h1>
    <p class="form-title">{{ $interview->title }}</p>
    <div class="report-meta">
        <p><span class="label">Jumlah Peserta</span>: {{ $submissions->count() }} peserta</p>
        <p><span class="label">Tanggal Unduh</span>: {{ $exportedAt->locale('id')->translatedFormat('d F Y, H:i') }} WIB</p>
    </div>
    <h2 class="section-title">REKAP PERTANYAAN DAN JAWABAN SELURUH PESERTA</h2>
    <table class="qa-table">
        <thead>
            <tr><th class="number" style="width: 4%" scope="col">No.</th><th style="width: 14%" scope="col">Nama Peserta</th><th style="width: 11%" scope="col">Nomor Registrasi</th><th style="width: 14%" scope="col">Program Studi</th><th style="width: 25%" scope="col">Pertanyaan</th><th style="width: 32%" scope="col">Jawaban</th></tr>
        </thead>
        <tbody>
            @forelse ($submissions as $submission)
                @php($participantNumber = $loop->iteration)
                @php($session = $submission->testSessionDetails())
                @forelse ($answerTable->rows($submission->answers) as $row)
                    <tr>
                        <td class="center identity">{{ $participantNumber }}</td>
                        <td class="identity">{{ $submission->name }}</td>
                        <td class="identity">{{ $submission->registration_number }}</td>
                        <td class="identity">{{ $submission->study_program }}
                            @if ($session['wave'] !== null || $session['period'] !== null || $session['academic_year'] !== null || $session['campus'] !== null)
                                <div class="qa-meta">
                                    @if ($session['wave'] !== null)Gelombang {{ $session['wave'] }}<br>@endif
                                    @if ($session['period'] !== null)Periode: {{ $session['period'] }}<br>@endif
@if ($session['academic_year'] !== null)Tahun Akademik: {{ $session['academic_year'] }}<br>@endif
                                    @if ($session['campus'] !== null)Kampus {{ $session['campus'] }}@endif
                                </div>
                            @endif
                        </td>
                        <td>
                            @if ($row['question'] !== '')
                                <p class="qa-question">{{ $row['number'] }}. {{ $row['question'] }}</p>
                                @if ($row['continuation'])<p class="qa-meta">Lanjutan pertanyaan {{ $row['number'] }}.</p>@endif
                            @else
                                <p class="qa-meta">Lanjutan jawaban pertanyaan {{ $row['number'] }}.</p>
                            @endif
                        </td>
                        <td><p class="qa-answer {{ $row['empty'] ? 'empty' : '' }}">{{ $row['answer'] }}</p></td>
                    </tr>
                @empty
                    <tr><td class="center identity">{{ $participantNumber }}</td><td class="identity">{{ $submission->name }}</td><td class="identity">{{ $submission->registration_number }}</td><td class="identity">{{ $submission->study_program }}
                            @if ($session['wave'] !== null || $session['period'] !== null || $session['academic_year'] !== null || $session['campus'] !== null)
                                <div class="qa-meta">
                                    @if ($session['wave'] !== null)Gelombang {{ $session['wave'] }}<br>@endif
                                    @if ($session['period'] !== null)Periode: {{ $session['period'] }}<br>@endif
@if ($session['academic_year'] !== null)Tahun Akademik: {{ $session['academic_year'] }}<br>@endif
                                    @if ($session['campus'] !== null)Kampus {{ $session['campus'] }}@endif
                                </div>
                            @endif
                        </td><td colspan="2" class="empty">Tidak ada salinan jawaban yang tersimpan untuk peserta ini.</td></tr>
                @endforelse
            @empty
                <tr><td colspan="6" class="center empty">Belum ada peserta yang mengirim jawaban.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
