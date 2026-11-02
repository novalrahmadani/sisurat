<?php

namespace App\Http\Requests;

use App\Models\Faculty;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;

class FacultyRequest extends FormRequest
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
                    $record = $this->route('faculty');
                    if (Faculty::query()->where('identity_key', Faculty::identityKey($value))
                        ->when($record instanceof Faculty, fn (Builder $query) => $query->whereKeyNot($record->id))
                        ->exists()) {
                        $fail('Fakultas dengan nama ini sudah tersedia.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama fakultas wajib diisi.',
            'name.string' => 'Nama fakultas harus berupa teks.',
            'name.max' => 'Nama fakultas maksimal 150 karakter.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => Faculty::normalizeName($this->input('name'))]);
        }
    }
}
