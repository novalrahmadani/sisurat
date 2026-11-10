<?php

namespace App\Http\Controllers;

use App\Models\PmbProspectSetting;
use App\Models\PmbProspectSubmission;
use App\Support\PublicFormLinks;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

class PmbProspectController extends Controller
{
    public function __construct(private PublicFormLinks $links) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isAdministrator(), 403);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $search = trim($filters['q'] ?? '');
        $submissions = PmbProspectSubmission::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    foreach (['name', 'study_program', 'school_name', 'school_district', 'affiliate_name'] as $column) {
                        $query->orWhere($column, 'like', '%'.$search.'%');
                    }
                });
            })
            ->orderByDesc('submitted_at')->orderByDesc('id')->paginate(20)->withQueryString()
            ->through(fn (PmbProspectSubmission $submission): array => [
                ...$submission->only(['id', 'name', 'study_program', 'school_name', 'school_district', 'school_address', 'affiliate_name']),
                'submitted_at' => $submission->submitted_at->timezone('Asia/Jakarta')->format('d M Y, H:i'),
            ]);
        $settings = PmbProspectSetting::query()->findOrFail(1);

        return $this->privatePage($request, 'pmb-prospects/Index', [
            'submissions' => $submissions,
            'filters' => ['q' => $search],
            'isOpen' => $settings->is_open,
            'stats' => [
                'total' => PmbProspectSubmission::query()->count(),
                'schools' => PmbProspectSubmission::query()->distinct('school_name')->count('school_name'),
                'programs' => PmbProspectSubmission::query()->distinct('study_program')->count('study_program'),
            ],
            'publicUrl' => $this->links->url('pmb-prospects.join'),
            'qrUrl' => route('pmb-prospects.qr'),
            'qrDownloadUrl' => route('pmb-prospects.qr', ['download' => 1]),
            'flyerUrl' => route('pmb-prospects.flyer'),
            'flyerDownloadUrl' => route('pmb-prospects.flyer', ['download' => 1]),
            'pdfViewUrl' => route('pmb-prospects.pdf'),
            'pdfDownloadUrl' => route('pmb-prospects.pdf', ['download' => 1]),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->isAdministrator(), 403);
        $data = $request->validate([
            'is_open' => ['required', 'boolean'],
        ]);
        DB::transaction(function () use ($data): void {
            $settings = PmbProspectSetting::query()->lockForUpdate()->findOrFail(1);
            $settings->update(['is_open' => (bool) $data['is_open']]);
        }, 5);

        Inertia::flash('toast', ['type' => 'success', 'message' => $data['is_open'] ? 'Formulir calon mahasiswa dibuka.' : 'Formulir calon mahasiswa ditutup.']);

        return to_route('pmb-prospects.index');
    }

    public function pdf(Request $request): Response
    {
        abort_unless($request->user()?->isAdministrator(), 403);
        $cachePath = storage_path('framework/cache/dompdf');
        File::ensureDirectoryExists($cachePath);
        $options = new Options;
        $options->setDefaultFont('DejaVu Sans');
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setChroot(public_path());
        $options->setTempDir($cachePath);
        $options->setFontCache($cachePath);
        $pdf = new Dompdf($options);
        $letterheadPath = public_path('images/universitas-nurul-huda-letterhead.png');
        $pdf->setPaper('A4', 'landscape');
        $pdf->loadHtml(view('pmb-prospects.report', [
            'submissions' => PmbProspectSubmission::query()->orderBy('submitted_at')->orderBy('id')->get(),
            'exportedAt' => now('Asia/Jakarta'),
            'letterhead' => File::exists($letterheadPath)
                ? 'data:image/png;base64,'.base64_encode(File::get($letterheadPath))
                : null,
        ])->render(), 'UTF-8');
        $pdf->render();

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                $request->boolean('download') ? 'attachment' : 'inline',
                'data-calon-mahasiswa.pdf',
            ),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    /** @param array<string, mixed> $props */
    private function privatePage(Request $request, string $component, array $props): Response
    {
        $response = Inertia::render($component, $props)->toResponse($request);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}

