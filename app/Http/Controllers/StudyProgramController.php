<?php

namespace App\Http\Controllers;

use App\Http\Requests\StudyProgramRequest;
use App\Models\StudyProgram;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StudyProgramController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:150']]);
        $search = trim($filters['q'] ?? '');

        return Inertia::render('master-data/AcademicCategories', [
            'category' => 'study-programs',
            'records' => StudyProgram::query()->select(['id', 'name'])
                ->when($search !== '', fn (Builder $query) => $query->where('name', 'like', '%'.$search.'%'))
                ->orderBy('name')->orderBy('id')->paginate(20)->withQueryString(),
            'filters' => ['q' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('master-data/AcademicCategoryForm', [
            'category' => 'study-programs',
            'record' => null,
        ]);
    }

    public function store(StudyProgramRequest $request): RedirectResponse
    {
        try {
            $name = $request->validated('name');
            StudyProgram::query()->create(['name' => $name, 'identity_key' => StudyProgram::identityKey($name)]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'Program studi dengan nama ini sudah tersedia.']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Program studi berhasil ditambahkan.']);

        return to_route('master-data.study-programs.index');
    }

    public function edit(StudyProgram $studyProgram): Response
    {
        return Inertia::render('master-data/AcademicCategoryForm', [
            'category' => 'study-programs',
            'record' => $studyProgram->only(['id', 'name']),
        ]);
    }

    public function update(StudyProgramRequest $request, StudyProgram $studyProgram): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $studyProgram): void {
                $studyProgram = StudyProgram::query()->lockForUpdate()->findOrFail($studyProgram->id);
                $name = $request->validated('name');
                $studyProgram->update(['name' => $name, 'identity_key' => StudyProgram::identityKey($name)]);
            }, 5);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'Program studi dengan nama ini sudah tersedia.']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Program studi berhasil diperbarui.']);

        return to_route('master-data.study-programs.index');
    }

    public function destroy(Request $request, StudyProgram $studyProgram): RedirectResponse
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:150']]);
        DB::transaction(function () use ($studyProgram): void {
            $studyProgram = StudyProgram::query()->lockForUpdate()->findOrFail($studyProgram->id);
            $studyProgram->delete();
        }, 5);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Program studi berhasil dihapus. Surat yang sudah dibuat tetap tersimpan.']);

        return to_route('master-data.study-programs.index', $filters);
    }
}
