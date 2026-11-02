<?php

namespace App\Http\Controllers;

use App\Http\Requests\DistrictRequest;
use App\Models\District;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DistrictController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:150']]);
        $search = trim($filters['q'] ?? '');

        return Inertia::render('master-data/Districts', [
            'records' => District::query()->select(['id', 'name'])
                ->withCount(['institutions', 'staffMembers'])
                ->when($search !== '', fn (Builder $query) => $query->where('name', 'like', '%'.$search.'%'))
                ->orderBy('name')->paginate(20)->withQueryString(),
            'filters' => ['q' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('master-data/DistrictForm', ['district' => null]);
    }

    public function store(DistrictRequest $request): RedirectResponse
    {
        try {
            $name = $request->validated('name');
            District::query()->create(['name' => $name, 'identity_key' => District::identityKey($name)]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'Kabupaten/kota dengan nama ini sudah tersedia.']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kabupaten/kota berhasil ditambahkan.']);

        return to_route('master-data.districts.index');
    }

    public function edit(District $district): Response
    {
        return Inertia::render('master-data/DistrictForm', ['district' => $district->only(['id', 'name'])]);
    }

    public function update(DistrictRequest $request, District $district): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $district): void {
                $district = District::query()->lockForUpdate()->findOrFail($district->id);
                $name = $request->validated('name');
                $institutions = $district->institutions()->lockForUpdate()->get();
                $district->update(['name' => $name, 'identity_key' => District::identityKey($name)]);

                foreach ($institutions as $institution) {
                    $seedKey = hash('sha256', implode("\0", array_map(
                        fn (string $value): string => mb_strtolower(preg_replace('/\s+/u', ' ', trim($value)) ?? $value, 'UTF-8'),
                        [$institution->institution_type, $institution->name, $name, $institution->address ?? ''],
                    )));
                    $institution->update(['district' => $name, 'seed_key' => $seedKey]);
                }
            }, 5);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'Nama kabupaten/kota ini sudah tersedia atau menyebabkan data tujuan duplikat.']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kabupaten/kota diperbarui. Data lembaga mengikuti nama terbaru; surat lama tetap tersimpan.']);

        return to_route('master-data.districts.index');
    }

    public function destroy(Request $request, District $district): RedirectResponse
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:150']]);
        DB::transaction(function () use ($district): void {
            $district = District::query()->lockForUpdate()->findOrFail($district->id);
            if ($district->institutions()->exists() || $district->staffMembers()->exists()) {
                throw ValidationException::withMessages([
                    'district' => 'Kabupaten/kota masih digunakan oleh sekolah, pondok, atau dosen/petugas. Pindahkan data tersebut sebelum menghapus kabupaten/kota.',
                ]);
            }
            $district->delete();
        }, 5);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kabupaten/kota berhasil dihapus.']);

        return to_route('master-data.districts.index', $filters);
    }
}
