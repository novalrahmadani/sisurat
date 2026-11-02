<?php

namespace App\Http\Requests;

use App\Models\QuranExam;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class QuranExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        $exam = $this->route('quran_exam');

        return $exam instanceof QuranExam ? ($this->user()?->can('update', $exam) ?? false) : $this->user() !== null;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'wave' => ['nullable', 'integer', Rule::in([1, 2, 3])],
            'period' => ['nullable', 'string', 'max:100'],
            'campus' => ['nullable', 'string', Rule::in(['B', 'C'])],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:3000'],
            'assessment_date' => ['required', 'date_format:Y-m-d'],
            'is_open' => ['required', 'boolean'],
            'version' => [$this->route('quran_exam') instanceof QuranExam ? 'required' : 'prohibited', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return ['wave.integer' => 'Pilih Gelombang 1, 2, atau 3.',
            'wave.in' => 'Pilih Gelombang 1, 2, atau 3.',
            'period.string' => 'Periode harus berupa teks.',
            'period.max' => 'Periode maksimal 100 karakter.',
            'campus.string' => 'Pilih Kampus B atau Kampus C.',
            'campus.in' => 'Pilih Kampus B atau Kampus C.',
            'title.required' => 'Nama kegiatan tes wajib diisi.', 'title.max' => 'Nama kegiatan maksimal 150 karakter.',
            'description.max' => 'Petunjuk maksimal 3.000 karakter.', 'assessment_date.required' => 'Tanggal tes wajib diisi.',
            'assessment_date.date_format' => 'Tanggal tes tidak valid.', 'version.required' => 'Muat ulang pengaturan sebelum menyimpan.',
            'version.uuid' => 'Versi pengaturan tidak valid. Muat ulang halaman.'];
    }

    protected function prepareForValidation(): void
    {
        $clean = [];
        foreach (['title', 'description', 'period', 'campus'] as $field) {
            if (is_string($this->input($field))) {
                $clean[$field] = $field === 'title' ? Str::squish($this->input($field)) : Str::trim($this->input($field));
            }
        }
        $this->merge($clean);
    }
}
