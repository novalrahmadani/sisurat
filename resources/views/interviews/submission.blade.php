<!DOCTYPE html>
<html lang="id" translate="no">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Hasil Wawancara — {{ $submission->name }}</title>
    <link rel="icon" type="image/png" href="{{ asset('pmb-logo.png') }}?v=36b41cc8a125">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #eef4f1; color: #18241c; font-family: Cambria, Cochin, Georgia, 'Times New Roman', serif; font-size: 11pt; line-height: 1.5; }
        .toolbar { max-width: 210mm; margin: 20px auto; padding: 0 14px; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; font-family: Arial, sans-serif; font-size: 14px; }
        .toolbar a { color: #005a36; }
        .toolbar button { border: 0; border-radius: 8px; padding: 12px 18px; background: #005a36; color: #fff; font: inherit; cursor: pointer; }
        .toolbar button:disabled { cursor: wait; opacity: .6; }
        .status { width: 100%; margin: 0; color: #45554d; }
        .page { max-width: 210mm; min-height: 297mm; margin: 0 auto 30px; padding: 18mm 18mm 20mm; background: #fff; box-shadow: 0 6px 30px #00281516; }
        .letterhead { display: block; width: 100%; height: auto; margin-bottom: 6mm; }
        h1 { font-size: 14pt; text-align: center; margin: 0 0 2mm; }
        .form-title { margin: 0 0 6mm; text-align: center; overflow-wrap: anywhere; }
        .metadata { width: 100%; margin: 0 0 7mm; border-collapse: collapse; table-layout: fixed; }
        .metadata th { width: 33mm; text-align: left; font-weight: normal; }
        .metadata th, .metadata td { padding: .8mm 0; vertical-align: top; overflow-wrap: anywhere; }
        .metadata .separator { width: 4mm; }
        .answer { margin: 0 0 6mm; }
        .answer h2 { margin: 0 0 2mm; font-size: 11pt; break-after: avoid; page-break-after: avoid; overflow-wrap: anywhere; white-space: pre-wrap; }
        .answer p { margin: 0; padding: 3mm; border: 1px solid #d3dbd6; white-space: pre-wrap; overflow-wrap: anywhere; orphans: 3; widows: 3; }
        .empty-answer { color: #69766f; font-style: italic; }
        @page { size: A4 portrait; margin: 18mm 18mm 20mm; }
        @media print {
            body { background: #fff; color: #111; }
            .toolbar { display: none; }
            .page { max-width: none; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
            .letterhead, h1, .form-title, .metadata { break-inside: avoid; page-break-inside: avoid; }
            h1, .form-title, .metadata { break-after: avoid; page-break-after: avoid; }
        }
        @media screen and (max-width: 700px) {
            .page { padding: 24px 20px; margin: 0; min-height: 0; }
            .metadata th { width: 30%; }
            .metadata .separator { width: 5%; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('interviews.submissions.show', ['interview' => $interview, 'submission' => $submission]) }}">← Kembali ke hasil wawancara</a>
        <button id="print-button" type="button" disabled>Cetak / Simpan PDF</button>
        <p class="status" id="print-status" role="status">Memuat kop surat…</p>
    </div>
    <main class="page">
        <img class="letterhead" src="{{ asset('images/universitas-nurul-huda-letterhead.png') }}" alt="Kop surat Universitas Nurul Huda">
        <h1>HASIL TES WAWANCARA MAHASISWA BARU</h1>
        <p class="form-title">{{ $interview->title }}</p>
        @php($session = $submission->testSessionDetails())
        <table class="metadata">
            @if ($session['wave'] !== null)<tr><th>Gelombang</th><td class="separator">:</td><td>Gelombang {{ $session['wave'] }}</td></tr>@endif
            @if ($session['period'] !== null)<tr><th>Periode</th><td class="separator">:</td><td>{{ $session['period'] }}</td></tr>@endif
            @if ($session['academic_year'] !== null)<tr><th>Tahun Akademik</th><td class="separator">:</td><td>{{ $session['academic_year'] }}</td></tr>@endif
            @if ($session['campus'] !== null)<tr><th>Lokasi Kampus</th><td class="separator">:</td><td>Kampus {{ $session['campus'] }}</td></tr>@endif
            <tr><th>Nama</th><td class="separator">:</td><td>{{ $submission->name }}</td></tr>
            <tr><th>Nomor Registrasi</th><td class="separator">:</td><td>{{ $submission->registration_number }}</td></tr>
            <tr><th>Program Studi</th><td class="separator">:</td><td>{{ $submission->study_program }}</td></tr>
            <tr><th>Waktu Pengiriman</th><td class="separator">:</td><td>{{ $submission->submitted_at->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d F Y, H:i') }} WIB</td></tr>
        </table>
        @foreach ($submission->answers as $answer)
            <section class="answer">
                <h2>{{ $loop->iteration }}. {{ $answer['question'] }}</h2>
                @if ($answer['answer'] !== '')
                    <p>{{ $answer['answer'] }}</p>
                @else
                    <p class="empty-answer">Tidak dijawab (pertanyaan opsional).</p>
                @endif
            </section>
        @endforeach
    </main>
    <script>
        const printButton = document.getElementById('print-button');
        const printStatus = document.getElementById('print-status');
        const letterhead = document.querySelector('.letterhead');
        function updatePrintAvailability() {
            const ready = letterhead.naturalWidth > 0;
            printButton.disabled = !ready;
            printStatus.textContent = ready
                ? 'Siap dicetak. Pilih Simpan sebagai PDF pada menu cetak untuk mengunduh.'
                : 'Kop surat belum berhasil dimuat. Muat ulang halaman sebelum mencetak.';
        }
        if (letterhead.complete) {
            updatePrintAvailability();
        } else {
            letterhead.addEventListener('load', updatePrintAvailability, { once: true });
            letterhead.addEventListener('error', updatePrintAvailability, { once: true });
        }
        printButton.addEventListener('click', () => window.print());
    </script>
</body>
</html>
