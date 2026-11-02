<?php

namespace App\Http\Controllers;

use App\Http\Requests\StaffMemberRequest;
use App\Models\District;
use App\Models\StaffMember;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StaffMemberController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'district' => ['nullable', 'integer', 'min:1'],
        ]);
        $search = trim($filters['q'] ?? '');
        $district = isset($filters['district']) ? (string) $filters['district'] : '';
        $records = StaffMember::query()->with('district:id,name')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('office', 'like', '%'.$search.'%')
                        ->orWhere('employee_number', 'like', '%'.$search.'%');
                });
            })
            ->when($district !== '', fn (Builder $query) => $query->where('district_id', (int) $district))
            ->orderBy('name')->orderBy('id')->paginate(20)->withQueryString()
            ->through(fn (StaffMember $person): array => $this->record($person));

        return Inertia::render('master-data/StaffMembers', [
            'records' => $records,
            'filters' => ['q' => $search, 'district' => $district],
            'districtOptions' => District::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('master-data/StaffMemberForm', [
            'staffMember' => null,
            'districtOptions' => District::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StaffMemberRequest $request): RedirectResponse
    {
        $this->saveStaffMember($request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dosen/petugas berhasil ditambahkan.']);

        return to_route('master-data.staff-members.index');
    }

    public function edit(StaffMember $staffMember): Response
    {
        return Inertia::render('master-data/StaffMemberForm', [
            'staffMember' => $this->record($staffMember->load('district:id,name')),
            'districtOptions' => District::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(StaffMemberRequest $request, StaffMember $staffMember): RedirectResponse
    {
        $this->saveStaffMember($request->validated(), $staffMember);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dosen/petugas berhasil diperbarui. Surat yang sudah dibuat tetap memakai data saat pembuatan.']);

        return to_route('master-data.staff-members.index');
    }

    public function destroy(Request $request, StaffMember $staffMember): RedirectResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'district' => ['nullable', 'integer', 'min:1'],
        ]);
        DB::transaction(function () use ($staffMember): void {
            if ($staffMember->district_id !== null) {
                District::query()->whereKey($staffMember->district_id)->lockForUpdate()->first();
            }
            StaffMember::query()->lockForUpdate()->findOrFail($staffMember->id)->delete();
        }, 5);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dosen/petugas dihapus dari master data. Riwayat surat tugas tetap tersimpan.']);

        return to_route('master-data.staff-members.index', $filters);
    }

    /**
     * @return array{id: int, name: string, office: string, employee_number: ?string, district_id: ?int, district: ?string}
     */
    private function record(StaffMember $person): array
    {
        return [
            ...$person->only(['id', 'name', 'office', 'employee_number', 'district_id']),
            'district' => $person->district?->name,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function saveStaffMember(array $data, ?StaffMember $person = null): void
    {
        $data['employee_number'] = $data['employee_number'] ?? null;
        $data['district_id'] = $data['district_id'] ?? null;
        try {
            DB::transaction(function () use ($data, $person): void {
                $districtIds = array_values(array_unique(array_filter([$person?->district_id, $data['district_id']])));
                $districts = District::query()->whereKey($districtIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                if ($data['district_id'] !== null && ! $districts->has($data['district_id'])) {
                    throw ValidationException::withMessages(['district_id' => 'Kabupaten/kota yang dipilih sudah tidak tersedia.']);
                }
                if ($person === null) {
                    StaffMember::query()->create($data);
                } else {
                    StaffMember::query()->lockForUpdate()->findOrFail($person->id)->update($data);
                }
            }, 5);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['employee_number' => 'NIP/identitas ini sudah digunakan oleh dosen/petugas lain.']);
        }
    }
}
