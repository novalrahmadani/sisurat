<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StaffMemberRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'office' => ['required', 'string', 'max:150'],
            'employee_number' => [
                'nullable', 'string', 'max:100',
                Rule::unique('staff_members', 'employee_number')->ignore($this->route('staff_member')),
            ],
            'district_id' => ['nullable', 'integer', Rule::exists('districts', 'id')],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama dosen/petugas wajib diisi.',
            'name.string' => 'Nama dosen/petugas harus berupa teks.',
            'name.max' => 'Nama dosen/petugas maksimal 150 karakter.',
            'office.required' => 'Dinas atau jabatan wajib diisi.',
            'office.string' => 'Dinas atau jabatan harus berupa teks.',
            'office.max' => 'Dinas atau jabatan maksimal 150 karakter.',
            'employee_number.max' => 'NIP/identitas maksimal 100 karakter.',
            'employee_number.string' => 'NIP/identitas harus berupa teks.',
            'employee_number.unique' => 'NIP/identitas ini sudah digunakan oleh dosen/petugas lain.',
            'district_id.integer' => 'Pilihan kabupaten/kota tidak valid.',
            'district_id.exists' => 'Kabupaten/kota yang dipilih sudah tidak tersedia.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach (['name', 'office', 'employee_number'] as $field) {
            $value = $this->input($field, $field === 'office' ? 'Dosen' : null);
            if (is_string($value)) {
                $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
                $normalized[$field] = $field === 'employee_number' && $value === '' ? null : $value;
            }
        }
        $this->merge($normalized);
    }
}
