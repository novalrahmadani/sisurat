<?php

namespace App\Http\Requests;

use App\Models\District;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InstitutionRequest extends FormRequest
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
            'institution_type' => ['required', Rule::in(['school', 'pesantren'])],
            'name' => ['required', 'string', 'max:255'],
            'district' => ['required', 'string', 'max:150', Rule::exists('districts', 'name')],
            'address' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'institution_type.required' => 'Pilih jenis tujuan: sekolah atau pondok.',
            'institution_type.in' => 'Jenis tujuan harus sekolah atau pondok.',
            'name.required' => 'Nama sekolah atau pondok wajib diisi.',
            'name.max' => 'Nama tujuan maksimal 255 karakter.',
            'district.required' => 'Kabupaten/kota wajib diisi.',
            'district.max' => 'Kabupaten/kota maksimal 150 karakter.',
            'district.exists' => 'Pilih kabupaten/kota yang tersedia. Tambahkan kabupaten/kota baru melalui Master Data.',
            'address.max' => 'Alamat maksimal 500 karakter.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['name', 'district', 'address'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
                $normalized[$field] = $field === 'address' && $value === '' ? null : $value;
            }
        }

        if (isset($normalized['district'])) {
            $normalized['district'] = District::normalizeName($normalized['district']);
        }

        $this->merge($normalized);
    }
}
