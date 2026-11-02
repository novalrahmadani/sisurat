<?php

namespace App\Http\Controllers;

use App\Http\Requests\LetterTemplateRequest;
use App\Models\LetterTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LetterTemplateController extends Controller
{
    public function index(): Response
    {
        $catalog = LetterTemplate::catalog();
        $records = LetterTemplate::query()->whereIn('key', array_column($catalog, 'key'))->get()->keyBy('key');
        $templates = [];

        foreach ($catalog as $definition) {
            $record = $records->get($definition['key']);
            if ($record !== null) {
                $templates[] = $this->templateRecord($record, $definition);
            }
        }

        return Inertia::render('master-data/LetterTemplates', [
            'templates' => $templates,
            'catalog' => $catalog,
        ]);
    }

    public function edit(LetterTemplate $letterTemplate): Response
    {
        $definition = LetterTemplate::definitionFor($letterTemplate->key);
        abort_if($definition === null, 404);

        return Inertia::render('master-data/LetterTemplateForm', [
            'record' => $this->templateRecord($letterTemplate, $definition),
            'definition' => $definition,
        ]);
    }

    public function update(LetterTemplateRequest $request, LetterTemplate $letterTemplate): RedirectResponse
    {
        $data = $request->validated();
        $updated = LetterTemplate::query()->whereKey($letterTemplate->id)->where('revision', $data['revision'])->update([
            'content' => json_encode($data['content'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'revision' => (int) $data['revision'] + 1,
        ]);
        if ($updated !== 1) {
            throw ValidationException::withMessages([
                'revision' => 'Template telah diperbarui oleh pengguna lain. Salin isian Anda bila diperlukan, lalu muat ulang halaman sebelum menyimpan kembali.',
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Template surat berhasil diperbarui. Surat yang sudah dibuat tetap memakai isi yang tersimpan.']);

        return to_route('master-data.letter-templates.index');
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array{id: int, key: string, name: string, type: string, destination: ?string, revision: int, updated_at: ?string, content: array<string, string>}
     */
    private function templateRecord(LetterTemplate $record, array $definition): array
    {
        return [
            'id' => $record->id,
            'key' => $record->key,
            'name' => $definition['name'],
            'type' => $definition['type'],
            'destination' => $definition['destination'],
            'revision' => $record->revision,
            'updated_at' => $record->updated_at?->toIso8601String(),
            'content' => $record->content,
        ];
    }
}
