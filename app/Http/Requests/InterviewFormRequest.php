<?php

namespace App\Http\Requests;

use App\Models\InterviewForm;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class InterviewFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        $interview = $this->route('interview');

        return $interview instanceof InterviewForm
            ? ($this->user()?->can('update', $interview) ?? false)
            : $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'wave' => ['nullable', 'integer', Rule::in([1, 2, 3])],
            'period' => ['nullable', 'string', 'max:100'],
            'campus' => ['nullable', 'string', Rule::in(['B', 'C'])],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_open' => ['required', 'boolean'],
            'questions_version' => [$this->route('interview') instanceof InterviewForm ? 'required' : 'nullable', 'uuid'],
            'questions' => ['required', 'array', 'list', 'min:1', 'max:30'],
            'questions.*' => ['required', 'array:id,prompt,required'],
            'questions.*.id' => ['required', 'uuid', 'distinct'],
            'questions.*.prompt' => ['required', 'string', 'max:1000'],
            'questions.*.required' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'wave.integer' => 'Pilih Gelombang 1, 2, atau 3.',
            'wave.in' => 'Pilih Gelombang 1, 2, atau 3.',
            'period.string' => 'Periode harus berupa teks.',
            'period.max' => 'Periode maksimal 100 karakter.',
            'campus.string' => 'Pilih Kampus B atau Kampus C.',
            'campus.in' => 'Pilih Kampus B atau Kampus C.',
            'title.required' => 'Judul form wajib diisi.',
            'title.max' => 'Judul form maksimal 200 karakter.',
            'description.max' => 'Petunjuk pengisian maksimal 2.000 karakter.',
            'is_open.required' => 'Pilih status form wawancara.',
            'is_open.boolean' => 'Status form wawancara tidak valid.',
            'questions_version.required' => 'Muat ulang form sebelum menyimpan perubahan.',
            'questions_version.uuid' => 'Versi pertanyaan tidak valid. Muat ulang form.',
            'questions.required' => 'Tambahkan sedikitnya satu pertanyaan.',
            'questions.min' => 'Tambahkan sedikitnya satu pertanyaan.',
            'questions.max' => 'Satu form dapat memuat paling banyak 30 pertanyaan.',
            'questions.list' => 'Urutan pertanyaan tidak valid.',
            'questions.*.id.required' => 'Identitas pertanyaan tidak valid. Tambahkan ulang pertanyaan.',
            'questions.*.id.uuid' => 'Identitas pertanyaan tidak valid. Tambahkan ulang pertanyaan.',
            'questions.*.id.distinct' => 'Terdapat pertanyaan ganda. Tambahkan ulang salah satunya.',
            'questions.*.prompt.required' => 'Teks pertanyaan wajib diisi.',
            'questions.*.prompt.max' => 'Teks pertanyaan maksimal 1.000 karakter.',
            'questions.*.required.required' => 'Pilih apakah pertanyaan wajib dijawab.',
            'questions.*.required.boolean' => 'Status wajib jawab tidak valid.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach (['title', 'description', 'questions_version', 'period', 'campus'] as $field) {
            if (is_string($this->input($field))) {
                $value = Str::trim($this->input($field));
                $normalized[$field] = $field === 'questions_version' ? strtolower($value) : $value;
            }
        }
        if (is_array($this->input('questions'))) {
            $normalized['questions'] = array_map(function (mixed $question): mixed {
                if (! is_array($question)) {
                    return $question;
                }
                foreach (['id', 'prompt'] as $field) {
                    if (isset($question[$field]) && is_string($question[$field])) {
                        $question[$field] = $field === 'id' ? strtolower(trim($question[$field])) : Str::trim($question[$field]);
                    }
                }

                return $question;
            }, $this->input('questions'));
        }
        $this->merge($normalized);
    }
}
