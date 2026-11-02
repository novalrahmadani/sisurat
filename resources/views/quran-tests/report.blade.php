<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><title>Rekap Tes Baca Al-Qur'an</title>
<style>
@page { size: A4 landscape; margin: 12mm 13mm 16mm; }
body { font-family: 'DejaVu Serif', serif; font-size: 8.5pt; color: #17251d; line-height: 1.45; margin: 0; }
.letterhead { width: 195mm; height: auto; } .kop { text-align: center; margin-bottom: 4mm; }
h1 { text-align: center; margin: 0 0 1.5mm; font-size: 12pt; } .scope { text-align: center; font-weight: bold; font-size: 10pt; margin: 0 0 4mm; }
.meta { margin-bottom: 3mm; } .meta p { margin: .7mm 0; } .legend { font-size: 8pt; margin: 3mm 0; color: #45564b; }
table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 7.3pt; line-height: 1.25; }
thead { display: table-header-group; } th,td { border: .6pt solid #aebfb4; padding: 1.1mm .8mm; vertical-align: top; word-wrap: break-word; }
th { background: #e5efe9; text-align: center; } tbody tr { page-break-inside: avoid; } .center { text-align: center; }
.mq2 { color: #005a36; font-weight: bold; } .mq1 { color: #80570a; font-weight: bold; } .muted { color: #68756d; }
</style></head><body>
<div class="kop"><img class="letterhead" src="{{ $letterhead }}" alt="Kop resmi Universitas Nurul Huda"></div>
<h1>REKAP HASIL TES BACA AL-QUR'AN</h1><p class="scope">{{ $scope }}</p>
<div class="meta">
<p>Jumlah peserta: <strong>{{ $rows->count() }}</strong> &nbsp; | &nbsp; MQ 1: {{ $rows->where('result', 'mq1')->count() }} &nbsp; | &nbsp; MQ 2: {{ $rows->where('result', 'mq2')->count() }} &nbsp; | &nbsp; Belum memiliki kategori MQ: {{ $rows->whereNotIn('result', ['mq1', 'mq2'])->count() }}</p>
<p>Tanggal unduh: {{ $exportedAt->locale('id')->translatedFormat('d F Y, H:i') }} WIB</p>
@if (($filters['q'] ?? '') !== '')<p>Pencarian: {{ $filters['q'] }}</p>@endif
@if (($filters['result'] ?? 'all') !== 'all')<p>Filter hasil: {{ \App\Models\QuranAssessment::RESULT_LABELS[$filters['result']] }}</p>@endif
</div>
<table><thead><tr>
<th style="width:3%">No.</th><th style="width:13%">Nama Peserta</th><th style="width:10%">Nomor Registrasi</th><th style="width:11%">Program Studi</th><th style="width:17%">Tanggal Tes / Pelaksanaan</th>
<th style="width:6%">Fasohah</th><th style="width:5%">Tajwid</th><th style="width:6%">Makhroj</th><th style="width:7%">Kelancaran</th><th style="width:6%">Rata-rata</th><th style="width:9%">Kategori</th><th style="width:7%">Penguji</th>
</tr></thead><tbody>
@forelse ($rows as $row)
@php($session = $row->testSessionDetails())
<tr><td class="center">{{ $loop->iteration }}</td><td>{{ $row->name }}</td><td>{{ $row->registration_number }}</td><td>{{ $row->study_program }}</td><td>{{ $row->assessment_date->format('d/m/Y') }}
@if ($session['wave'] !== null)<br>Gelombang {{ $session['wave'] }}@endif
@if ($session['period'] !== null)<br>Periode: {{ $session['period'] }}@endif
@if ($session['academic_year'] !== null)<br>Tahun Akademik: {{ $session['academic_year'] }}@endif
@if ($session['campus'] !== null)<br>Kampus {{ $session['campus'] }}@endif
</td>
@foreach (['fasohah_score','tajwid_score','makhraj_score','fluency_score'] as $field)<td class="center">{{ $row->{$field} ?? '-' }}</td>@endforeach
<td class="center">{{ $row->averageScore() !== null ? number_format($row->averageScore(), 2, ',', '.') : '-' }}</td>
<td class="center {{ $row->result }}">@if ($row->result === 'mq2')Berhak mengikuti <span style="white-space: nowrap">MQ 2</span>@else{{ \App\Models\QuranAssessment::RESULT_LABELS[$row->result] }}@endif</td><td>{{ $row->examiner_name ?? '-' }}</td></tr>
@empty<tr><td colspan="12" class="center muted">Belum ada penilaian sesuai filter.</td></tr>@endforelse
</tbody></table>
<p class="legend">Rentang nilai setiap kriteria: 1-100. Rata-rata = (Fasohah + Tajwid + Makhroj + Kelancaran) / 4. Rata-rata minimal 70: berhak mengikuti MQ 2; di bawah 70: masuk MQ 1. Penilaian belum lengkap dan data format lama tidak ditetapkan sebagai kategori MQ.</p>
</body></html>
