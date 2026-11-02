<?php

namespace App\Http\Requests;

use App\Models\InterviewForm;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class InterviewSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        abort_unless(InterviewForm::query()->where('public_token', $this->route('token'))->exists(), 404);

        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'wave' => ['required', 'integer', Rule::in([1, 2, 3])],
            'academic_year' => ['required', 'string', 'max:9', 'regex:/^[0-9]{4}\/[0-9]{4}$/D'],
            'period' => ['required', 'string', 'max:100'],
            'campus' => ['required', 'string', Rule::in(['B', 'C'])],
            'name' => ['required', 'string', 'max:150'],
            'registration_number' => ['required', 'string', 'max:100'],
            'study_program_id' => ['required', 'integer'],
            'questions_version' => ['required', 'uuid'],
            'submission_token' => ['required', 'uuid'],
            'answers' => ['required', 'array', 'list', 'min:1', 'max:30'],
            'answers.*' => ['required', 'array:question_id,answer'],
            'answers.*.question_id' => ['required', 'uuid', 'distinct'],
            'answers.*.answer' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
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
            'name.required' => 'Nama lengkap wajib diisi.',
            'name.max' => 'Nama lengkap maksimal 150 karakter.',
            'registration_number.required' => 'Nomor registrasi wajib diisi.',
            'registration_number.string' => 'Nomor registrasi harus berupa teks.',
            'registration_number.max' => 'Nomor registrasi maksimal 100 karakter.',
            'study_program_id.required' => 'Pilih program studi yang diambil.',
            'study_program_id.integer' => 'Pilihan program studi tidak valid.',
            'questions_version.required' => 'Muat ulang pertanyaan sebelum mengirim jawaban.',
            'questions_version.uuid' => 'Versi pertanyaan tidak valid. Muat ulang halaman.',
            'submission_token.required' => 'Muat ulang halaman sebelum mengirim jawaban.',
            'submission_token.uuid' => 'Muat ulang halaman sebelum mengirim jawaban.',
            'answers.required' => 'Jawab pertanyaan yang tersedia.',
            'answers.min' => 'Jawab pertanyaan yang tersedia.',
            'answers.max' => 'Jumlah jawaban tidak valid. Muat ulang pertanyaan.',
            'answers.list' => 'Urutan jawaban tidak valid. Muat ulang pertanyaan.',
            'answers.*.question_id.required' => 'Pertanyaan tidak valid. Muat ulang pertanyaan.',
            'answers.*.question_id.uuid' => 'Pertanyaan tidak valid. Muat ulang pertanyaan.',
            'answers.*.question_id.distinct' => 'Jawaban pertanyaan dikirim lebih dari sekali.',
            'answers.*.answer.string' => 'Jawaban harus berupa teks yang diketik.',
            'answers.*.answer.max' => 'Setiap jawaban maksimal 5.000 karakter.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach (['period', 'academic_year', 'campus', 'name', 'registration_number', 'questions_version', 'submission_token'] as $field) {
            if (is_string($this->input($field))) {
                $value = Str::trim($this->input($field));
                $normalized[$field] = match ($field) {
                    'name', 'registration_number' => Str::squish($value),
                    'questions_version', 'submission_token' => strtolower($value),
                    default => $value,
                };
            }
        }
        if (is_array($this->input('answers'))) {
            $normalized['answers'] = array_map(function (mixed $answer): mixed {
                if (! is_array($answer)) {
                    return $answer;
                }
                if (isset($answer['question_id']) && is_string($answer['question_id'])) {
                    $answer['question_id'] = strtolower(trim($answer['question_id']));
                }
                if (isset($answer['answer']) && is_string($answer['answer'])) {
                    $answer['answer'] = Str::trim($answer['answer']);
                }

                return $answer;
            }, $this->input('answers'));
        }
        $this->merge($normalized);
    }
}
