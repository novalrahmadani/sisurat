<?php

namespace App\Http\Requests;

use App\Models\StudyProgram;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;

class StudyProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'bail', 'required', 'string', 'max:150',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $record = $this->route('study_program');
                    if (StudyProgram::query()->where('identity_key', StudyProgram::identityKey($value))
                        ->when($record instanceof StudyProgram, fn (Builder $query) => $query->whereKeyNot($record->id))
                        ->exists()) {
                        $fail('Program studi dengan nama ini sudah tersedia.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama program studi wajib diisi.',
            'name.string' => 'Nama program studi harus berupa teks.',
            'name.max' => 'Nama program studi maksimal 150 karakter.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => StudyProgram::normalizeName($this->input('name'))]);
        }
    }
}
