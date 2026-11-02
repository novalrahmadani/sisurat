<?php

namespace App\Http\Requests;

use App\Models\QuranAssessment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class QuranAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assessment = $this->route('quran_test');

        return $assessment instanceof QuranAssessment
            ? ($this->user()?->can('update', $assessment) ?? false)
            : $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $editing = $this->route('quran_test') instanceof QuranAssessment;
        $completed = collect(['fasohah_score', 'tajwid_score', 'makhraj_score', 'fluency_score'])
            ->every(fn (string $field): bool => $this->filled($field));

        return [
            'wave' => ['nullable', 'integer', Rule::in([1, 2, 3])],
            'academic_year' => ['nullable', 'string', 'max:9', 'regex:/^[0-9]{4}\/[0-9]{4}$/D'],
            'period' => ['nullable', 'string', 'max:100'],
            'campus' => ['nullable', 'string', Rule::in(['B', 'C'])],
            'name' => ['required', 'string', 'max:150'],
            'registration_number' => ['required', 'string', 'max:100'],
            'study_program_id' => [Rule::requiredIf(! $editing || ! $this->boolean('retain_study_program')), 'nullable', 'integer', 'min:1'],
            'retain_study_program' => ['required', 'boolean'],
            'assessment_date' => ['required', 'date_format:Y-m-d'],
            'examiner_name' => [Rule::requiredIf($completed), 'nullable', 'string', 'max:150'],
            'reading_reference' => [Rule::requiredIf($completed), 'nullable', 'string', 'max:255'],
            'fasohah_score' => ['nullable', 'integer', 'between:1,100'],
            'makhraj_score' => ['nullable', 'integer', 'between:1,100'],
            'tajwid_score' => ['nullable', 'integer', 'between:1,100'],
            'fluency_score' => ['nullable', 'integer', 'between:1,100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'version' => [$editing ? 'required' : 'nullable', 'uuid'],
            'submission_token' => [$editing ? 'prohibited' : 'required', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'wave.integer' => 'Pilih Gelombang 1, 2, atau 3.',
            'wave.in' => 'Pilih Gelombang 1, 2, atau 3.',
            'academic_year.required' => 'Tahun akademik wajib diisi.',
            'academic_year.string' => 'Tahun akademik harus berupa teks.',
            'academic_year.max' => 'Gunakan format tahun akademik, misalnya 2027/2028.',
            'academic_year.regex' => 'Gunakan format tahun akademik, misalnya 2027/2028.',
            'period.string' => 'Periode harus berupa teks.',
            'period.max' => 'Periode maksimal 100 karakter.',
            'campus.string' => 'Pilih Kampus B atau Kampus C.',
            'campus.in' => 'Pilih Kampus B atau Kampus C.',
            'name.required' => 'Nama calon mahasiswa wajib diisi.',
            'name.max' => 'Nama maksimal 150 karakter.',
            'registration_number.required' => 'Nomor registrasi wajib diisi.',
            'registration_number.string' => 'Nomor registrasi harus berupa teks agar angka nol di awal tetap tersimpan.',
            'registration_number.max' => 'Nomor registrasi maksimal 100 karakter.',
            'study_program_id.required' => 'Pilih program studi dari Master Data.',
            'study_program_id.integer' => 'Pilihan program studi tidak valid.',
            'study_program_id.min' => 'Pilihan program studi tidak valid.',
            'retain_study_program.required' => 'Pilih apakah program studi lama tetap digunakan.',
            'retain_study_program.boolean' => 'Pilihan untuk mempertahankan program studi tidak valid.',
            'assessment_date.required' => 'Tanggal tes wajib diisi.',
            'assessment_date.date_format' => 'Tanggal tes harus berupa tanggal yang valid.',
            'examiner_name.required' => 'Nama penguji wajib diisi sebelum penilaian diselesaikan.',
            'examiner_name.max' => 'Nama penguji maksimal 150 karakter.',
            'reading_reference.required' => 'Surah atau ayat yang dibaca wajib diisi sebelum penilaian diselesaikan.',
            'reading_reference.max' => 'Keterangan bacaan maksimal 255 karakter.',
            'fasohah_score.integer' => 'Nilai Fasohah harus berupa bilangan bulat.',
            'fasohah_score.between' => 'Nilai Fasohah harus berada di antara 1 dan 100.',
            'makhraj_score.integer' => 'Nilai makhroj harus berupa bilangan bulat.',
            'tajwid_score.integer' => 'Nilai tajwid harus berupa bilangan bulat.',
            'fluency_score.integer' => 'Nilai kelancaran harus berupa bilangan bulat.',
            'makhraj_score.between' => 'Nilai makhroj harus berada di antara 1 dan 100.',
            'tajwid_score.between' => 'Nilai tajwid harus berada di antara 1 dan 100.',
            'fluency_score.between' => 'Nilai kelancaran harus berada di antara 1 dan 100.',
            'notes.max' => 'Catatan maksimal 5.000 karakter.',
            'version.required' => 'Muat ulang halaman sebelum menyimpan perubahan.',
            'version.uuid' => 'Versi penilaian tidak valid. Muat ulang halaman.',
            'submission_token.required' => 'Identitas pengiriman belum tersedia. Muat ulang form.',
            'submission_token.uuid' => 'Identitas pengiriman tidak valid. Muat ulang form.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach (['period', 'academic_year', 'campus', 'name', 'registration_number', 'assessment_date', 'examiner_name', 'reading_reference', 'notes', 'version', 'submission_token'] as $field) {
            if (! is_string($this->input($field))) {
                continue;
            }
            $value = $field === 'notes' ? Str::trim($this->input($field)) : Str::squish($this->input($field));
            if (in_array($field, ['version', 'submission_token'], true)) {
                $value = strtolower($value);
            }
            $normalized[$field] = $value === '' ? null : $value;
        }
        $this->merge($normalized);
    }
}
