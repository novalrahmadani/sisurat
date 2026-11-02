<?php

namespace App\Http\Controllers;

use App\Http\Requests\ManualOutgoingLetterRequest;
use App\Http\Requests\OutgoingLetterFilterRequest;
use App\Models\Letter;
use App\Models\LetterBatch;
use App\Models\LetterNumbering;
use App\Models\ManualOutgoingLetter;
use App\Support\OutgoingLetterAgenda;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OutgoingLetterController extends Controller
{
    private const HEADERS = ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'X-Robots-Tag' => 'noindex, nofollow'];

    public function __construct(private OutgoingLetterAgenda $agenda) {}

    public function index(OutgoingLetterFilterRequest $request): Response
    {
        $filters = $request->filters();
        $query = $this->agenda->query($filters);
        $records = $this->agenda->ordered(clone $query)->paginate(25)->withQueryString();
        $records->setCollection($this->agenda->present($records->getCollection())->map(function (array $row) use ($request): array {
            $row['can_manage'] = $row['source'] === 'manual' && ($request->user()->isAdministrator() || $row['created_by'] === (int) $request->user()->id);
            unset($row['created_by']);

            return $row;
        }));
        $years = LetterBatch::query()->select('letter_date')->distinct()->get()->toBase()->map(fn (LetterBatch $batch): int => Carbon::parse($batch->letter_date)->year)
            ->concat(ManualOutgoingLetter::query()->distinct()->pluck('letter_year'))->concat(LetterNumbering::query()->pluck('year'))->push(now('Asia/Jakarta')->year)->unique()->sortDesc()->values();

        return Inertia::render('outgoing-letters/Index', ['records' => $records, 'filters' => $filters, 'stats' => $this->agenda->counts($query), 'years' => $years, 'types' => OutgoingLetterAgenda::TYPES]);
    }

    public function create(): Response
    {
        Gate::authorize('create', ManualOutgoingLetter::class);

        return Inertia::render('outgoing-letters/Form', ['entry' => null, 'today' => now('Asia/Jakarta')->toDateString(), 'types' => OutgoingLetterAgenda::TYPES]);
    }

    public function edit(ManualOutgoingLetter $entry): Response
    {
        Gate::authorize('update', $entry);

        return Inertia::render('outgoing-letters/Form', ['entry' => [...$entry->only(['id', 'recipient', 'subject', 'letter_number', 'type', 'version']), 'letter_date' => Carbon::parse($entry->letter_date)->toDateString()], 'today' => now('Asia/Jakarta')->toDateString(), 'types' => OutgoingLetterAgenda::TYPES]);
    }

    public function store(ManualOutgoingLetterRequest $request): RedirectResponse
    {
        $entry = $this->saveManual($request);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Surat manual sudah dicatat. Penomoran SISURAT telah disesuaikan.']);

        return to_route('outgoing-letters.index', ['year' => $entry->letter_year]);
    }

    public function update(ManualOutgoingLetterRequest $request, ManualOutgoingLetter $entry): RedirectResponse
    {
        $saved = $this->saveManual($request, $entry);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Catatan agenda berhasil diperbarui.']);

        return to_route('outgoing-letters.index', ['year' => $saved->letter_year]);
    }

    public function destroy(ManualOutgoingLetter $entry): RedirectResponse
    {
        Gate::authorize('delete', $entry);
        DB::transaction(function () use ($entry): void {
            LetterNumbering::lockForYear((int) $entry->letter_year);
            ManualOutgoingLetter::query()->whereKey($entry->id)->lockForUpdate()->firstOrFail()->delete();
        }, 5);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Catatan dihapus dari agenda. Nomornya tetap dicadangkan agar tidak digunakan ulang.']);

        return to_route('outgoing-letters.index', ['year' => $entry->letter_year]);
    }

    public function csv(OutgoingLetterFilterRequest $request): StreamedResponse
    {
        $filters = $request->filters();

        return response()->streamDownload(function () use ($filters): void {
            $output = fopen('php://output', 'wb');
            if ($output === false) {
                throw new RuntimeException('Berkas agenda belum dapat dibuat. Silakan coba kembali.');
            }
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['No.', 'Alamat/Penerima', 'Tanggal', 'Perihal', 'Nomor Surat'], ',', '"', '');
            $number = 0;
            foreach ($this->agenda->rows($filters) as $row) {
                fputcsv($output, [++$number, $this->csvCell($row['recipient']), date('d/m/Y', strtotime($row['letter_date'])), $this->csvCell($row['subject'].($row['is_trial'] ? ' [UJI COBA]' : '')), $this->csvCell($row['letter_number'])], ',', '"', '');
            }
            fclose($output);
        }, 'agenda-surat-keluar-'.($filters['year'] ?? 'semua-tahun').'.csv', [...self::HEADERS, 'Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function pdf(OutgoingLetterFilterRequest $request): SymfonyResponse
    {
        $filters = $request->filters();
        if ($request->hasHeader('X-Inertia')) {
            return Inertia::location(route('outgoing-letters.pdf', [...$filters, 'year' => $filters['year'] ?? '', 'inline' => $request->boolean('inline') ? 1 : 0]));
        }
        $directory = storage_path('framework/cache/dompdf');
        File::ensureDirectoryExists($directory);
        $options = new Options;
        $options->setDefaultFont('DejaVu Serif');
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setIsFontSubsettingEnabled(true);
        $options->setChroot(public_path());
        $options->setTempDir($directory);
        $options->setFontCache($directory);
        $pdf = new Dompdf($options);
        $pdf->setPaper('A4', 'landscape');
        $pdf->loadHtml(view('outgoing-letters.report', ['rows' => iterator_to_array($this->agenda->rows($filters)), 'filters' => $filters])->render(), 'UTF-8');
        $pdf->render();
        $canvas = $pdf->getCanvas();
        $font = $pdf->getFontMetrics()->getFont('DejaVu Serif');
        $canvas->page_text(40, $canvas->get_height() - 27, 'PMB Universitas Nurul Huda', $font, 8, [0.3, 0.35, 0.32]);
        $canvas->page_text($canvas->get_width() - 138, $canvas->get_height() - 27, 'Halaman {PAGE_NUM} / {PAGE_COUNT}', $font, 8, [0.3, 0.35, 0.32]);

        return response($pdf->output(), 200, [...self::HEADERS, 'Content-Type' => 'application/pdf', 'Content-Disposition' => HeaderUtils::makeDisposition($request->boolean('inline') ? 'inline' : 'attachment', 'agenda-surat-keluar-'.($filters['year'] ?? 'semua-tahun').'.pdf')]);
    }

    private function saveManual(ManualOutgoingLetterRequest $request, ?ManualOutgoingLetter $entry = null): ManualOutgoingLetter
    {
        $data = $request->validated();
        $parts = ManualOutgoingLetter::numberParts($data['letter_number']);
        if ($parts === null) {
            throw ValidationException::withMessages(['letter_number' => 'Periksa kembali nomor surat lengkap.']);
        }

        return DB::transaction(function () use ($request, $data, $parts, $entry): ManualOutgoingLetter {
            $years = array_unique([$parts['year'], $entry !== null ? (int) $entry->letter_year : $parts['year']]);
            sort($years, SORT_NUMERIC);
            $numberings = [];
            foreach ($years as $year) {
                $numberings[$year] = LetterNumbering::lockForYear($year);
            }
            $current = $entry !== null ? ManualOutgoingLetter::query()->whereKey($entry->id)->lockForUpdate()->firstOrFail() : null;
            if ($current !== null && (int) $current->version !== (int) $data['expected_version']) {
                throw ValidationException::withMessages(['expected_version' => 'Catatan sudah diubah oleh pengguna lain. Muat ulang halaman sebelum menyimpan.']);
            }
            $manualExists = ManualOutgoingLetter::withTrashed()->where('letter_year', $parts['year'])->where('sequence_number', $parts['sequence'])
                ->when($current !== null, fn ($query) => $query->where('id', '!=', $current->id))->exists();
            $automaticExists = Letter::withTrashed()->join('letter_batches', 'letters.letter_batch_id', '=', 'letter_batches.id')
                ->where('letters.numbering_scope', 'official')->where('letter_batches.is_trial', false)
                ->whereYear('letter_batches.letter_date', $parts['year'])->where('letters.sequence_number', $parts['sequence'])->exists();
            if ($manualExists || $automaticExists) {
                throw ValidationException::withMessages(['letter_number' => 'Nomor urut '.$parts['sequence'].' tahun '.$parts['year'].' sudah digunakan atau dicadangkan. Periksa buku agenda dan Arsip Surat.']);
            }
            $values = ['recipient' => $data['recipient'], 'letter_date' => $data['letter_date'], 'subject' => $data['subject'], 'letter_number' => $data['letter_number'], 'type' => $data['type'], 'sequence_number' => $parts['sequence'], 'letter_year' => $parts['year'], 'created_by' => $current !== null ? $current->created_by : $request->user()->id, 'version' => $current !== null ? $current->version + 1 : 1];
            if ($current !== null && ($current->sequence_number !== $parts['sequence'] || $current->letter_year !== $parts['year'])) {
                $current->delete();
                $saved = ManualOutgoingLetter::query()->create($values);
            } elseif ($current !== null) {
                $current->update($values);
                $saved = $current;
            } else {
                $saved = ManualOutgoingLetter::query()->create($values);
            }
            $numbering = $numberings[$parts['year']];
            $numbering->update(['next_sequence' => max($numbering->minimumNextSequence(), $parts['sequence'] + 1)]);

            return $saved;
        }, 5);
    }

    private function csvCell(string $value): string
    {
        return preg_match('/^[\s\x00-\x1F\x{FEFF}\x{200B}]*[=+@\-]/u', $value) === 1 ? "'".$value : $value;
    }
}
