<?php

namespace App\Http\Controllers;

use App\Http\Requests\InstitutionRequest;
use App\Models\District;
use App\Models\Institution;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InstitutionController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'district' => ['nullable', 'string', 'max:150'],
        ]);
        $search = trim($filters['q'] ?? '');
        $destination = $request->route('institutionType') ?? 'school';
        $district = trim($filters['district'] ?? '');
        $destinations = Institution::query()
            ->select(['id', 'institution_type', 'name as institution_name', 'district as recipient_district', 'address as recipient_address'])
            ->withCount('letters')->withMax('letters as last_used', 'created_at')
            ->where('institution_type', $destination)
            ->when($district !== '', fn (Builder $query) => $query->where('district', $district))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('district', 'like', '%'.$search.'%')
                        ->orWhere('address', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('district')->orderBy('name')->paginate(20)->withQueryString();
        $schoolCount = Institution::query()->where('institution_type', 'school')->count();
        $pesantrenCount = Institution::query()->where('institution_type', 'pesantren')->count();

        return Inertia::render('letters/Directory', [
            'destinations' => $destinations,
            'districtOptions' => $this->districtOptions(),
            'stats' => ['schoolCount' => $schoolCount, 'pesantrenCount' => $pesantrenCount, 'totalCount' => $schoolCount + $pesantrenCount],
            'filters' => ['q' => $search, 'destination' => $destination, 'district' => $district],
        ]);
    }

    public function create(Request $request): Response
    {
        $data = $request->validate([
            'institution_type' => ['nullable', Rule::in(['school', 'pesantren'])],
        ]);

        return Inertia::render('letters/InstitutionForm', [
            'institution' => null,
            'initialType' => $data['institution_type'] ?? 'school',
            'districtOptions' => $this->districtOptions(),
        ]);
    }

    public function store(InstitutionRequest $request): RedirectResponse
    {
        $institution = $this->saveInstitution($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Data sekolah atau pondok berhasil ditambahkan.',
        ]);

        return to_route($institution->institution_type === 'school' ? 'master-data.schools' : 'master-data.pesantren');
    }

    public function edit(Institution $institution): Response
    {
        return Inertia::render('letters/InstitutionForm', [
            'institution' => $institution->only(['id', 'institution_type', 'name', 'district', 'address']),
            'initialType' => $institution->institution_type,
            'districtOptions' => $this->districtOptions(),
        ]);
    }

    public function update(InstitutionRequest $request, Institution $institution): RedirectResponse
    {
        $institution = $this->saveInstitution($request->validated(), $institution);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Data sekolah atau pondok berhasil diperbarui.',
        ]);

        return to_route($institution->institution_type === 'school' ? 'master-data.schools' : 'master-data.pesantren');
    }

    public function destroy(Request $request, Institution $institution): RedirectResponse
    {
        $filters = $request->validate([
            'destination' => ['nullable', Rule::in(['school', 'pesantren'])],
            'q' => ['nullable', 'string', 'max:150'],
            'district' => ['nullable', 'string', 'max:150'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        unset($filters['page'], $filters['destination']);
        DB::transaction(function () use ($institution): void {
            if ($institution->district_id !== null) {
                District::query()->whereKey($institution->district_id)->lockForUpdate()->first();
            }
            Institution::query()->lockForUpdate()->findOrFail($institution->id)->delete();
        }, 5);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Data tujuan dihapus. Surat yang sudah dibuat tetap tersimpan.',
        ]);

        return to_route($institution->institution_type === 'school' ? 'master-data.schools' : 'master-data.pesantren', $filters);
    }

    /**
     * @return list<string>
     */
    private function districtOptions(): array
    {
        return District::query()->orderBy('name')->pluck('name')->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveInstitution(array $data, ?Institution $institution = null): Institution
    {
        $data['address'] = $data['address'] ?? null;

        try {
            return DB::transaction(function () use ($data, $institution): Institution {
                $targetKey = District::identityKey($data['district']);
                $districts = District::query()->where(function (Builder $query) use ($targetKey, $institution): void {
                    $query->where('identity_key', $targetKey);
                    if ($institution?->district_id !== null) {
                        $query->orWhereKey($institution->district_id);
                    }
                })->orderBy('id')->lockForUpdate()->get();
                $district = $districts->firstWhere('identity_key', $targetKey);
                if ($district === null) {
                    throw ValidationException::withMessages(['district' => 'Kabupaten/kota yang dipilih sudah tidak tersedia. Pilih dari Master Data Kabupaten/Kota.']);
                }
                $data['district_id'] = $district->id;
                $data['district'] = $district->name;
                $candidates = Institution::query()
                    ->where('institution_type', $data['institution_type'])
                    ->lockForUpdate()
                    ->get(['id', 'name', 'district', 'address']);

                foreach ($candidates as $candidate) {
                    if ($candidate->id !== $institution?->id &&
                        $this->identityPart($candidate->name) === $this->identityPart($data['name']) &&
                        $this->identityPart($candidate->district) === $this->identityPart($data['district']) &&
                        $this->identityPart($candidate->address ?? '') === $this->identityPart($data['address'] ?? '')) {
                        throw ValidationException::withMessages([
                            'name' => 'Tujuan dengan jenis, nama, kabupaten/kota, dan alamat yang sama sudah tersedia.',
                        ]);
                    }
                }

                $data['seed_key'] = hash('sha256', implode("\0", array_map(
                    fn (string $value): string => mb_strtolower(preg_replace('/\s+/u', ' ', trim($value)) ?? $value, 'UTF-8'),
                    [$data['institution_type'], $data['name'], $data['district'], $data['address'] ?? ''],
                )));

                if ($institution === null) {
                    return Institution::query()->create($data);
                }

                $institution->fill($data)->save();

                return $institution;
            }, 5);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'name' => 'Tujuan dengan jenis, nama, kabupaten/kota, dan alamat yang sama sudah tersedia.',
            ]);
        }
    }

    private function identityPart(string $value): string
    {
        return preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($value, 'UTF-8')) ?? $value;
    }
}
