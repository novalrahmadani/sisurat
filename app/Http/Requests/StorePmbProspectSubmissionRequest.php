<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StorePmbProspectSubmissionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
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
            'study_program_id' => ['required', 'integer', Rule::exists('study_programs', 'id')],
            'school_name' => ['required', 'string', 'max:200'],
            'school_district' => ['required', 'string', 'max:150'],
            'school_address' => ['required', 'string', 'max:500'],
            'affiliate_name' => ['required', 'string', 'max:150'],
            'submission_token' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama calon mahasiswa wajib diisi.',
            'study_program_id.required' => 'Pilih program studi yang diminati.',
            'study_program_id.exists' => 'Program studi yang dipilih sudah tidak tersedia.',
            'school_name.required' => 'Asal sekolah wajib diisi.',
            'school_district.required' => 'Kabupaten/kota sekolah wajib diisi.',
            'school_address.required' => 'Alamat sekolah wajib diisi.',
            'affiliate_name.required' => 'Nama dosen atau guru pembawa wajib diisi.',
            'submission_token.required' => 'Muat ulang formulir sebelum mengirim.',
            'submission_token.uuid' => 'Identitas pengiriman tidak valid. Muat ulang formulir.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach (['name', 'school_name', 'school_district', 'school_address', 'affiliate_name'] as $field) {
            if (is_string($this->input($field))) {
                $normalized[$field] = Str::squish($this->input($field));
            }
        }
        if (is_string($this->input('submission_token'))) {
            $normalized['submission_token'] = strtolower(trim($this->input('submission_token')));
        }
        $this->merge($normalized);
    }
}
