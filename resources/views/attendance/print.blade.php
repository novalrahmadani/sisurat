@php
    $attendanceTitle = match ($event->type) {
        \App\Models\AttendanceEvent::TYPE_PMB_TEST => 'Daftar Hadir Tes PMB',
        \App\Models\AttendanceEvent::TYPE_OTHER => 'Daftar Hadir Kegiatan Lainnya',
        default => 'Daftar Hadir Rapat',
    };
    $isPmbTest = $event->type === \App\Models\AttendanceEvent::TYPE_PMB_TEST;
    $rowsPerPage = 15;
    $pageCount = max(1, (int) ceil($records->count() / $rowsPerPage));
    $eventDate = $event->event_date->locale('id');
@endphp
<!DOCTYPE html>
<html lang="id" translate="no">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $attendanceTitle }} — {{ $event->title }}</title>
    <link rel="icon" type="image/png" href="{{ asset('pmb-logo.png') }}?v=36b41cc8a125">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; color: #111; background: #eef4f1; font-family: Cambria, Cochin, Georgia, 'Times New Roman', serif; font-size: 11pt; line-height: 1.3; }
        .toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; max-width: 210mm; margin: 20px auto; padding: 0 14px; font: 14px Arial, sans-serif; }
        .toolbar a { color: #005a36; }
        .toolbar button { border: 0; border-radius: 8px; padding: 12px 18px; background: #005a36; color: #fff; font: inherit; cursor: pointer; }
        .toolbar button:disabled { cursor: wait; opacity: .6; }
        .status, .print-info { width: 100%; margin: 0; color: #45554d; line-height: 1.5; }
        .page { width: 210mm; max-width: 100%; min-height: 297mm; margin: 0 auto 24px; padding: 12mm 14mm 14mm; background: #fff; box-shadow: 0 6px 30px #00281516; }
        .letterhead { display: block; width: 100%; height: auto; margin: 0 0 5mm; }
        .document-heading { margin: 0 0 6mm; text-align: center; font-weight: bold; text-transform: uppercase; overflow-wrap: anywhere; }
        .document-heading h1 { margin: 0 0 3mm; font-size: 13pt; line-height: 1.2; }
        .event-title, .event-schedule { margin: 0; font-size: 11.5pt; line-height: 1.4; }
        .event-schedule { margin-top: .5mm; }
        .attendance { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 10.5pt; line-height: 1.2; }
        .attendance col.number-column { width: 6%; }
        .attendance col.name-column { width: 36%; }
        .attendance col.position-column { width: 34%; }
        .attendance col.signature-column { width: 24%; }
        .attendance.pmb-test { font-size: 9.5pt; line-height: 1.15; }
        .attendance.pmb-test col.number-column { width: 5%; }
        .attendance.pmb-test col.name-column { width: 22%; }
        .attendance.pmb-test col.study-program-column { width: 24%; }
        .attendance.pmb-test col.school-column { width: 18%; }
        .attendance.pmb-test col.phone-column { width: 16%; }
        .attendance.pmb-test col.signature-column { width: 15%; }
        .attendance th, .attendance td { border: 1px solid #333; padding: 1mm 2mm; vertical-align: middle; overflow-wrap: anywhere; }
        .attendance thead th { height: 8mm; background: #c9dfef; font-weight: bold; text-align: center; }
        .attendance tbody td { height: 9mm; }
        .attendance .number { padding-inline: 1mm; text-align: center; white-space: nowrap; overflow-wrap: normal; }
        .attendance .signature-cell { padding: .5mm 1.5mm; }
        .signature-entry { display: grid; grid-template-columns: 5mm minmax(0, 24mm); align-items: center; justify-content: start; gap: 1mm; width: 100%; height: 8mm; }
        .signature-entry.even { justify-content: end; }
        .signature-number { font-size: 9pt; white-space: nowrap; }
        .participant-signature { display: block; width: 24mm; max-width: 100%; height: 7.5mm; object-fit: contain; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        .pmb-test .signature-entry { grid-template-columns: 3.5mm minmax(0, 16mm); gap: .5mm; }
        .pmb-test .participant-signature { width: 16mm; height: 7.5mm; }
        .pmb-test .signature-number { font-size: 8pt; }
        .approval { isolation: isolate; display: grid; grid-template-columns: 24mm minmax(0, 1fr); width: 88mm; max-width: 100%; margin: 8mm 0 0 auto; break-inside: avoid; page-break-inside: avoid; }
        .approval-details { position: relative; grid-area: 1 / 2; min-width: 0; }
        .approval-details p { margin: 0; font-size: 10.5pt; line-height: 1.3; }
        .approval-artwork { position: relative; width: 100%; height: 20mm; margin-top: 1mm; }
        .pmb-stamp { position: relative; z-index: 2; grid-area: 1 / 1; align-self: start; width: 32mm; max-width: none; height: auto; margin-top: 3mm; object-fit: contain; }
        .pmb-signature { position: absolute; top: 1mm; left: 2mm; z-index: 1; width: 24mm; height: 17mm; object-fit: contain; mix-blend-mode: multiply; }
        .signatory-name { font-weight: bold; text-decoration: underline; }
        .page-number { margin: 4mm 0 0; font-size: 8.5pt; color: #555; text-align: right; }
        @page { size: A4 portrait; margin: 0; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .page { width: 100%; max-width: none; min-height: 0; margin: 0; padding: 12mm 14mm 14mm; box-shadow: none; break-after: page; page-break-after: always; }
            .page:last-of-type { break-after: auto; page-break-after: auto; }
            thead { display: table-header-group; }
            tr { break-inside: avoid; page-break-inside: avoid; }
            .letterhead, .document-heading { break-inside: avoid; page-break-inside: avoid; break-after: avoid; page-break-after: avoid; }
            .attendance th, .approval-artwork, .pmb-stamp { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        }
        @media screen and (max-width: 700px) {
            .page { width: 210mm; min-width: 210mm; max-width: none; }
            .toolbar { margin: 16px 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('attendance.show', $event) }}">← Kembali ke kegiatan</a>
        <button id="print-button" type="button" disabled>Cetak / Simpan PDF</button>
        <p class="print-info">Jumlah peserta terdaftar: {{ $records->count() }} orang. Setiap halaman memuat 15 baris; baris kosong dapat digunakan untuk tambahan peserta.</p>
        <p class="print-info">Cetak dengan kertas A4 dan skala 100%. Pada Setelan lainnya, nonaktifkan Header dan footer agar tanggal, judul, serta alamat halaman tidak ikut tercetak. Simpan ulang PDF dari halaman ini untuk memperoleh revisi terbaru.</p>
        <p class="status" id="print-status" role="status">Menyiapkan kop dan merapikan tanda tangan peserta…</p>
    </div>
    <main>
    @for ($pageIndex = 0; $pageIndex < $pageCount; $pageIndex++)
        @php
            $pageRecords = $records->slice($pageIndex * $rowsPerPage, $rowsPerPage)->values();
        @endphp
        <article class="page" aria-label="Daftar hadir halaman {{ $pageIndex + 1 }}">
            <img class="letterhead" src="{{ asset('images/universitas-nurul-huda-letterhead.png') }}" alt="Kop surat Universitas Nurul Huda">
            <header class="document-heading">
                <h1>DAFTAR HADIR</h1>
                <p class="event-title">{{ $event->title }}</p>
                <p class="event-schedule">{{ $eventDate->translatedFormat('l, d F Y') }}@if ($event->location) di {{ $event->location }}@endif</p>
            </header>
            <table class="attendance{{ $isPmbTest ? ' pmb-test' : '' }}" aria-label="Peserta {{ $attendanceTitle }}">
                <colgroup>
                    <col class="number-column">
                    <col class="name-column">
                    @if ($isPmbTest)
                        <col class="study-program-column">
                        <col class="school-column">
                        <col class="phone-column">
                    @else
                        <col class="position-column">
                    @endif
                    <col class="signature-column">
                </colgroup>
                <thead>
                    <tr>
                        <th scope="col" class="number">No.</th>
                        <th scope="col">Nama</th>
                        @if ($isPmbTest)
                            <th scope="col">Program Studi/Jurusan<br>yang Diambil</th>
                            <th scope="col">Asal Sekolah</th>
                            <th scope="col">No. HP/<br>WhatsApp</th>
                        @else
                            <th scope="col">Jabatan</th>
                        @endif
                        <th scope="col">Tanda tangan</th>
                    </tr>
                </thead>
                <tbody>
                    @for ($rowIndex = 0; $rowIndex < $rowsPerPage; $rowIndex++)
                        @php
                            $record = $pageRecords->get($rowIndex);
                            $rowNumber = $pageIndex * $rowsPerPage + $rowIndex + 1;
                        @endphp
                        <tr>
                            <td class="number">{{ $rowNumber }}.</td>
                            <td>{{ $record?->name }}</td>
                            @if ($isPmbTest)
                                <td>{{ $record?->study_program }}</td>
                                <td>{{ $record?->school_origin }}</td>
                                <td>{{ $record?->phone_number }}</td>
                            @else
                                <td>{{ $record?->position }}</td>
                            @endif
                            <td class="signature-cell">
                                <div class="signature-entry{{ $rowNumber % 2 === 0 ? ' even' : '' }}">
                                    <span class="signature-number">{{ $rowNumber }}.</span>
                                    @if ($record !== null)
                                        <img class="participant-signature" src="{{ route('attendance.signature', ['attendance' => $event, 'record' => $record]) }}" alt="Tanda tangan {{ $record->name }}">
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endfor
                </tbody>
            </table>
            <section class="approval" aria-label="Pengesahan Kepala Pusat PMB">
                @if ($approval['stamp_url'])<img class="pmb-stamp" src="{{ $approval['stamp_url'] }}" alt="Cap PMB Universitas Nurul Huda">@endif
                <div class="approval-details">
                    <p>OKU Timur, {{ $eventDate->translatedFormat('d F Y') }}</p>
                    <p>{{ $approval['title'] }},</p>
                    <div class="approval-artwork">
                        @if ($approval['signature_url'])<img class="pmb-signature" src="{{ $approval['signature_url'] }}" alt="Tanda tangan {{ $approval['name'] }}">@endif
                    </div>
                    <p class="signatory-name">{{ $approval['name'] }}</p>
                    @if ($approval['number'])<p>{{ $approval['number'] }}</p>@endif
                </div>
            </section>
            @if ($pageCount > 1)
                <p class="page-number">Halaman {{ $pageIndex + 1 }} dari {{ $pageCount }}</p>
            @endif
        </article>
    @endfor
    </main>
    <script>
        const printButton = document.getElementById('print-button');
        const printStatus = document.getElementById('print-status');
        const images = Array.from(document.images);
        const imageReady = (image) => image.complete
            ? Promise.resolve(image.naturalWidth > 0)
            : new Promise((resolve) => {
                image.addEventListener('load', () => resolve(true), { once: true });
                image.addEventListener('error', () => resolve(false), { once: true });
            });
        function inkStrength(pixels, offset) {
            const luminance = 0.2126 * pixels[offset] + 0.7152 * pixels[offset + 1] + 0.0722 * pixels[offset + 2];
            return (255 - luminance) * pixels[offset + 3] / 255;
        }

        function thinInk(mask, width, height) {
            let changed = true;
            for (let iteration = 0; changed && iteration < 64; iteration++) {
                changed = false;
                for (let step = 0; step < 2; step++) {
                    const removals = [];
                    for (let y = 1; y < height - 1; y++) {
                        for (let x = 1; x < width - 1; x++) {
                            const index = y * width + x;
                            if (mask[index] === 0) continue;
                            const north = mask[index - width];
                            const northEast = mask[index - width + 1];
                            const east = mask[index + 1];
                            const southEast = mask[index + width + 1];
                            const south = mask[index + width];
                            const southWest = mask[index + width - 1];
                            const west = mask[index - 1];
                            const northWest = mask[index - width - 1];
                            const neighbors = [north, northEast, east, southEast, south, southWest, west, northWest];
                            const count = neighbors.reduce((sum, value) => sum + value, 0);
                            if (count < 2 || count > 6) continue;
                            let transitions = 0;
                            for (let direction = 0; direction < 8; direction++) {
                                if (neighbors[direction] === 0 && neighbors[(direction + 1) % 8] === 1) transitions++;
                            }
                            if (transitions !== 1) continue;
                            const removable = step === 0
                                ? north * east * south === 0 && east * south * west === 0
                                : north * east * west === 0 && north * south * west === 0;
                            if (removable) removals.push(index);
                        }
                    }
                    if (removals.length > 0) changed = true;
                    for (const index of removals) mask[index] = 0;
                }
            }
        }

        async function prepareParticipantSignature(image) {
            const source = document.createElement('canvas');
            const scale = Math.min(1, 1600 / Math.max(image.naturalWidth, image.naturalHeight));
            source.width = Math.max(1, Math.round(image.naturalWidth * scale));
            source.height = Math.max(1, Math.round(image.naturalHeight * scale));
            const sourceContext = source.getContext('2d', { willReadFrequently: true });
            if (!sourceContext) throw new Error('Bidang pengolahan tanda tangan tidak tersedia.');
            sourceContext.drawImage(image, 0, 0, source.width, source.height);
            const sourcePixels = sourceContext.getImageData(0, 0, source.width, source.height).data;
            let left = source.width;
            let top = source.height;
            let right = -1;
            let bottom = -1;
            for (let y = 0; y < source.height; y++) {
                for (let x = 0; x < source.width; x++) {
                    if (inkStrength(sourcePixels, (y * source.width + x) * 4) <= 28) continue;
                    left = Math.min(left, x);
                    top = Math.min(top, y);
                    right = Math.max(right, x);
                    bottom = Math.max(bottom, y);
                }
            }
            if (right < left || bottom < top) throw new Error('Goresan tanda tangan peserta belum dapat dibaca.');

            const output = document.createElement('canvas');
            output.width = 384;
            output.height = 120;
            const context = output.getContext('2d', { willReadFrequently: true });
            if (!context) throw new Error('Bidang tanda tangan tidak tersedia.');
            const cropWidth = right - left + 1;
            const cropHeight = bottom - top + 1;
            const fit = Math.min((output.width - 24) / cropWidth, (output.height - 20) / cropHeight);
            const drawWidth = Math.max(1, Math.round(cropWidth * fit));
            const drawHeight = Math.max(1, Math.round(cropHeight * fit));
            context.drawImage(source, left, top, cropWidth, cropHeight,
                Math.floor((output.width - drawWidth) / 2), Math.floor((output.height - drawHeight) / 2), drawWidth, drawHeight);
            const resizedPixels = context.getImageData(0, 0, output.width, output.height).data;
            const mask = new Uint8Array(output.width * output.height);
            for (let index = 0; index < mask.length; index++) {
                mask[index] = inkStrength(resizedPixels, index * 4) > 28 ? 1 : 0;
            }
            thinInk(mask, output.width, output.height);
            const result = context.createImageData(output.width, output.height);
            const inkRadius = image.closest('.pmb-test') ? 2 : 1;
            for (let y = inkRadius; y < output.height - inkRadius; y++) {
                for (let x = inkRadius; x < output.width - inkRadius; x++) {
                    if (mask[y * output.width + x] === 0) continue;
                    for (let dy = -inkRadius; dy <= inkRadius; dy++) {
                        for (let dx = -inkRadius; dx <= inkRadius; dx++) {
                            if (dx * dx + dy * dy > inkRadius * inkRadius) continue;
                            result.data[((y + dy) * output.width + x + dx) * 4 + 3] = 255;
                        }
                    }
                }
            }
            context.putImageData(result, 0, 0);
            image.src = output.toDataURL('image/png');
            if (typeof image.decode === 'function') {
                await image.decode();
            } else if (!await imageReady(image)) {
                throw new Error('Tanda tangan yang dirapikan belum berhasil dimuat.');
            }
        }

        Promise.all(images.map(imageReady)).then(async (results) => {
            if (!results.every(Boolean)) throw new Error('Gambar belum berhasil dimuat.');
            const participantSignatures = Array.from(document.querySelectorAll('.participant-signature'));
            for (const [index, image] of participantSignatures.entries()) {
                printStatus.textContent = `Merapikan tanda tangan peserta ${index + 1} dari ${participantSignatures.length}…`;
                await prepareParticipantSignature(image);
            }
            printButton.disabled = false;
            printStatus.textContent = 'Siap dicetak. Tanda tangan peserta sudah dirapikan. Pilih Simpan sebagai PDF untuk membuat PDF terbaru.';
        }).catch((error) => {
            printButton.disabled = true;
            printStatus.textContent = `${error.message} Muat ulang halaman sebelum mencetak.`;
        });
        printButton.addEventListener('click', () => window.print());
    </script>
</body>
</html>
