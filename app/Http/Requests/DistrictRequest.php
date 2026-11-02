<?php

namespace App\Http\Requests;

use App\Models\District;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;

class DistrictRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'bail', 'required', 'string', 'max:150',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $district = $this->route('district');
                    if (District::query()->where('identity_key', District::identityKey($value))
                        ->when($district instanceof District, fn (Builder $query) => $query->whereKeyNot($district->id))
                        ->exists()) {
                        $fail('Kabupaten/kota dengan nama ini sudah tersedia.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama kabupaten/kota wajib diisi.',
            'name.string' => 'Nama kabupaten/kota harus berupa teks.',
            'name.max' => 'Nama kabupaten/kota maksimal 150 karakter.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => District::normalizeName($this->input('name'))]);
        }
    }
}
