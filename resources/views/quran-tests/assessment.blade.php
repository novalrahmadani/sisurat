<!DOCTYPE html>
<html lang="id" translate="no">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Hasil Tes Baca Al-Qur’an — {{ $assessment->name }}</title>
    <link rel="icon" type="image/png" href="{{ asset('pmb-logo.png') }}?v=36b41cc8a125">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #eef4f1; color: #18241c; font-family: Cambria, Cochin, Georgia, 'Times New Roman', serif; font-size: 11pt; line-height: 1.5; }
        .toolbar { max-width: 210mm; margin: 20px auto; padding: 0 14px; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; font-family: Arial, sans-serif; font-size: 14px; }
        .toolbar a { color: #005a36; }
        .toolbar button { border: 0; border-radius: 8px; padding: 12px 18px; background: #005a36; color: #fff; font: inherit; cursor: pointer; }
        .toolbar button:disabled { cursor: wait; opacity: .6; }
        .print-status { width: 100%; margin: 0; color: #45554d; }
        .page { max-width: 210mm; min-height: 297mm; margin: 0 auto 30px; padding: 18mm 18mm 20mm; background: #fff; box-shadow: 0 6px 30px #00281516; }
        .letterhead { display: block; width: 100%; height: auto; margin-bottom: 6mm; }
        h1 { margin: 0; font-size: 14pt; text-align: center; }
        .subtitle { margin: 0 0 7mm; text-align: center; font-weight: bold; }
        .metadata { width: 100%; margin: 0 0 6mm; border-collapse: collapse; table-layout: fixed; }
        .metadata th { width: 36mm; font-weight: normal; text-align: left; }
        .metadata th, .metadata td { padding: .8mm 0; vertical-align: top; overflow-wrap: anywhere; }
        .metadata .separator { width: 4mm; }
        .scores { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 5mm; }
        .scores th, .scores td { border: 1px solid #5e6d64; padding: 2mm 3mm; vertical-align: top; }
        .scores th { background: #eaf0ec; text-align: left; }
        .scores .number { width: 12mm; text-align: center; white-space: nowrap; }
        .scores .score { width: 37mm; text-align: center; }
        .scores tfoot th { background: #f2f5f3; }
        .result { border: 1px solid #5e6d64; margin: 0 0 6mm; padding: 3mm; break-inside: avoid; page-break-inside: avoid; }
        .result h2, .notes h2 { margin: 0 0 1.5mm; font-size: 11pt; }
        .result p { margin: 0; font-weight: bold; }
        .result small { display: block; margin-top: 1.5mm; font-size: 9.5pt; }
        .notes h2 { break-after: avoid; page-break-after: avoid; }
        .notes p { margin: 0; padding: 3mm; border: 1px solid #d3dbd6; white-space: pre-wrap; overflow-wrap: anywhere; orphans: 3; widows: 3; }
        .muted { color: #637269; font-style: italic; }
        @page { size: A4 portrait; margin: 18mm 18mm 20mm; }
        @media print {
            body { background: #fff; color: #111; }
            .toolbar { display: none; }
            .page { max-width: none; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
            .letterhead, .metadata, .scores { break-inside: avoid; page-break-inside: avoid; }
            h1, .subtitle, .metadata, .scores { break-after: avoid; page-break-after: avoid; }
            .scores th, .scores tfoot th { background: #eee; print-color-adjust: exact; }
        }
        @media screen and (max-width: 700px) {
            .page { margin: 0; padding: 24px 20px; min-height: 0; }
            .metadata th { width: 31%; }
            .metadata .separator { width: 5%; }
            .scores .number { width: 10%; }
            .scores .score { width: 30%; }
        }
    </style>
</head>
<body>
    @php
        $session = $assessment->testSessionDetails();
        $average = $assessment->averageScore();
        $resultLabel = \App\Models\QuranAssessment::RESULT_LABELS[$assessment->result];
    @endphp
    <div class="toolbar">
        <a href="{{ route('quran-tests.show', ['quran_test' => $assessment]) }}">← Kembali ke hasil tes</a>
        <button id="print-button" type="button" disabled>Cetak / Simpan PDF</button>
        <p class="print-status" id="print-status" role="status">Memuat kop surat…</p>
    </div>
    <main class="page">
        <img class="letterhead" src="{{ asset('images/universitas-nurul-huda-letterhead.png') }}" alt="Kop surat Universitas Nurul Huda">
        <h1>HASIL TES BACA AL-QUR’AN</h1>
        <p class="subtitle">CALON MAHASISWA BARU</p>
        <table class="metadata" aria-label="Identitas peserta tes">
            @if ($session['wave'] !== null)<tr><th scope="row">Gelombang</th><td class="separator">:</td><td>Gelombang {{ $session['wave'] }}</td></tr>@endif
            @if ($session['period'] !== null)<tr><th scope="row">Periode</th><td class="separator">:</td><td>{{ $session['period'] }}</td></tr>@endif
            @if ($session['academic_year'] !== null)<tr><th scope="row">Tahun Akademik</th><td class="separator">:</td><td>{{ $session['academic_year'] }}</td></tr>@endif
            @if ($session['campus'] !== null)<tr><th scope="row">Lokasi Kampus</th><td class="separator">:</td><td>Kampus {{ $session['campus'] }}</td></tr>@endif
            <tr><th scope="row">Nama</th><td class="separator">:</td><td>{{ $assessment->name }}</td></tr>
            <tr><th scope="row">Nomor Registrasi</th><td class="separator">:</td><td>{{ $assessment->registration_number }}</td></tr>
            <tr><th scope="row">Program Studi</th><td class="separator">:</td><td>{{ $assessment->study_program }}</td></tr>
            <tr><th scope="row">Tanggal Tes</th><td class="separator">:</td><td>{{ $assessment->assessment_date?->locale('id')->translatedFormat('d F Y') ?? 'Belum ditentukan' }}</td></tr>
            <tr><th scope="row">Penguji</th><td class="separator">:</td><td>{{ $assessment->examiner_name ?? 'Belum ditentukan' }}</td></tr>
            <tr><th scope="row">Surah / Ayat</th><td class="separator">:</td><td>{{ $assessment->reading_reference ?? 'Belum ditentukan' }}</td></tr>
        </table>
        <table class="scores" aria-label="Nilai tes baca Al-Qur’an">
            <thead>
                <tr><th scope="col" class="number">No</th><th scope="col">Aspek Penilaian</th><th scope="col" class="score">Nilai (1–100)</th></tr>
            </thead>
            <tbody>
                @foreach (['fasohah_score' => 'Fasohah', 'tajwid_score' => 'Tajwid', 'makhraj_score' => 'Makhroj', 'fluency_score' => 'Kelancaran'] as $field => $label)
                    <tr><td class="number">{{ $loop->iteration }}</td><td>{{ $label }}</td><td class="score">{{ $assessment->{$field} !== null ? $assessment->{$field} : 'Belum diisi' }}</td></tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr><th colspan="2" scope="row">Total (maksimal 400)</th><td class="score">{{ $assessment->totalScore() ?? 'Belum lengkap' }}</td></tr>
                <tr><th colspan="2" scope="row">Rata-rata</th><td class="score">{{ $average !== null ? number_format($average, 2, ',', '.') : 'Belum lengkap' }}</td></tr>
            </tfoot>
        </table>
        <section class="result" aria-labelledby="result-heading">
            <h2 id="result-heading">Kategori MQ / Status Penilaian</h2>
            <p>{{ $resultLabel }}</p>
            @if ($average === null)
                <small>Empat nilai belum lengkap. Lengkapi nilai melalui Ubah penilaian untuk menentukan kategori MQ. Data penilaian format lama tetap tersimpan.</small>
            @else
                <small>Rata-rata = jumlah empat nilai ÷ 4. Minimal 70: berhak mengikuti MQ 2. Di bawah 70: masuk MQ 1.</small>
            @endif
        </section>
        <section class="notes" aria-labelledby="notes-heading">
            <h2 id="notes-heading">Catatan / Rekomendasi Penguji</h2>
            @if ($assessment->notes !== null && $assessment->notes !== '')
                <p>{{ $assessment->notes }}</p>
            @else
                <p class="muted">Tidak ada catatan.</p>
            @endif
        </section>
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
