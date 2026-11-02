<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PublicQuranAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'wave' => ['required', 'integer', Rule::in([1, 2, 3])],
            'academic_year' => ['required', 'string', 'max:9', 'regex:/^[0-9]{4}\/[0-9]{4}$/D'],
            'period' => ['required', 'string', 'max:100'],
            'campus' => ['required', 'string', Rule::in(['B', 'C'])],
            'name' => ['required', 'string', 'max:150'], 'registration_number' => ['required', 'string', 'max:100'],
            'study_program_id' => ['required', 'integer', 'min:1'], 'examiner_name' => ['required', 'string', 'max:150'],
            'reading_reference' => ['required', 'string', 'max:255'],
            'fasohah_score' => ['required', 'integer', 'between:1,100'], 'tajwid_score' => ['required', 'integer', 'between:1,100'],
            'makhraj_score' => ['required', 'integer', 'between:1,100'], 'fluency_score' => ['required', 'integer', 'between:1,100'],
            'notes' => ['nullable', 'string', 'max:5000'], 'submission_token' => ['required', 'uuid'], 'exam_version' => ['required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        $messages = [
            'wave.required' => 'Pilih gelombang tes.',
            'wave.integer' => 'Pilih Gelombang 1, 2, atau 3.',
            'wave.in' => 'Pilih Gelombang 1, 2, atau 3.',
            'period.required' => 'Periode tes wajib diisi.',
            'academic_year.required' => 'Tahun akademik wajib diisi.',
            'academic_year.string' => 'Tahun akademik harus berupa teks.',
            'academic_year.max' => 'Gunakan format tahun akademik, misalnya 2027/2028.',
            'academic_year.regex' => 'Gunakan format tahun akademik, misalnya 2027/2028.',
            'period.string' => 'Periode harus berupa teks.',
            'period.max' => 'Periode maksimal 100 karakter.',
            'campus.required' => 'Pilih lokasi kampus.',
            'campus.string' => 'Pilih Kampus B atau Kampus C.',
            'campus.in' => 'Pilih Kampus B atau Kampus C.',
            'name.required' => 'Nama peserta wajib diisi.', 'registration_number.required' => 'Nomor registrasi wajib diisi.',
            'registration_number.string' => 'Nomor registrasi harus berupa teks.', 'study_program_id.required' => 'Pilih program studi peserta.',
            'examiner_name.required' => 'Nama penguji wajib diisi.', 'reading_reference.required' => 'Isi surah dan ayat yang dibaca.',
            'exam_version.required' => 'Muat ulang halaman tes sebelum mengirim nilai.', 'submission_token.required' => 'Muat ulang formulir sebelum mengirim nilai.',
        ];
        foreach (['fasohah_score' => 'Fasohah', 'tajwid_score' => 'Tajwid', 'makhraj_score' => 'Makhroj', 'fluency_score' => 'Kelancaran'] as $field => $label) {
            $messages[$field.'.required'] = 'Nilai '.$label.' wajib diisi.';
            $messages[$field.'.integer'] = 'Nilai '.$label.' harus berupa bilangan bulat.';
            $messages[$field.'.between'] = 'Nilai '.$label.' harus di antara 1 dan 100.';
        }

        return $messages;
    }

    protected function prepareForValidation(): void
    {
        $clean = [];
        foreach (['period', 'academic_year', 'campus', 'name', 'registration_number', 'examiner_name', 'reading_reference', 'notes'] as $field) {
            if (is_string($this->input($field))) {
                $clean[$field] = $field === 'notes' ? Str::trim($this->input($field)) : Str::squish($this->input($field));
            }
        }
        $this->merge($clean);
    }
}
