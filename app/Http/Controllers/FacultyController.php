<?php

namespace App\Http\Controllers;

use App\Http\Requests\FacultyRequest;
use App\Models\Faculty;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FacultyController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:150']]);
        $search = trim($filters['q'] ?? '');

        return Inertia::render('master-data/AcademicCategories', [
            'category' => 'faculties',
            'records' => Faculty::query()->select(['id', 'name'])
                ->when($search !== '', fn (Builder $query) => $query->where('name', 'like', '%'.$search.'%'))
                ->orderBy('name')->orderBy('id')->paginate(20)->withQueryString(),
            'filters' => ['q' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('master-data/AcademicCategoryForm', [
            'category' => 'faculties',
            'record' => null,
        ]);
    }

    public function store(FacultyRequest $request): RedirectResponse
    {
        try {
            $name = $request->validated('name');
            Faculty::query()->create(['name' => $name, 'identity_key' => Faculty::identityKey($name)]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'Fakultas dengan nama ini sudah tersedia.']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Fakultas berhasil ditambahkan.']);

        return to_route('master-data.faculties.index');
    }

    public function edit(Faculty $faculty): Response
    {
        return Inertia::render('master-data/AcademicCategoryForm', [
            'category' => 'faculties',
            'record' => $faculty->only(['id', 'name']),
        ]);
    }

    public function update(FacultyRequest $request, Faculty $faculty): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $faculty): void {
                $faculty = Faculty::query()->lockForUpdate()->findOrFail($faculty->id);
                $name = $request->validated('name');
                $faculty->update(['name' => $name, 'identity_key' => Faculty::identityKey($name)]);
            }, 5);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'Fakultas dengan nama ini sudah tersedia.']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Fakultas berhasil diperbarui.']);

        return to_route('master-data.faculties.index');
    }

    public function destroy(Request $request, Faculty $faculty): RedirectResponse
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:150']]);
        DB::transaction(function () use ($faculty): void {
            $faculty = Faculty::query()->lockForUpdate()->findOrFail($faculty->id);
            $faculty->delete();
        }, 5);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Fakultas berhasil dihapus. Surat yang sudah dibuat tetap tersimpan.']);

        return to_route('master-data.faculties.index', $filters);
    }
}
