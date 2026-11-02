<?php

namespace App\Http\Requests;

use App\Models\ManualOutgoingLetter;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ManualOutgoingLetterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $entry = $this->route('entry');

        return $this->user()?->can($entry instanceof ManualOutgoingLetter ? 'update' : 'create', $entry ?? ManualOutgoingLetter::class) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'recipient' => ['required', 'string', 'max:500'],
            'letter_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'subject' => ['required', 'string', 'max:500'],
            'letter_number' => ['required', 'string', 'max:200', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ManualOutgoingLetter::numberParts($value) === null) {
                    $fail('Gunakan nomor lengkap, misalnya 455/UNUHA.11/TU.2.1/X/2026. Nomor urut harus 1–99999 dan tahun 2000–2100.');
                }
            }],
            'type' => ['required', Rule::in(['permission', 'assignment', 'pkkmb', 'general', 'finance'])],
            'expected_version' => [$this->isMethod('post') ? 'nullable' : 'required', 'integer', 'min:1'],
        ];
    }

    /** @return array<Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['letter_number', 'letter_date'])) {
                return;
            }
            $parts = ManualOutgoingLetter::numberParts((string) $this->input('letter_number'));
            if ($parts !== null && $parts['year'] !== (int) substr((string) $this->input('letter_date'), 0, 4)) {
                $validator->errors()->add('letter_number', 'Tahun pada nomor surat harus sama dengan tahun tanggal surat.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        foreach (['recipient', 'subject', 'letter_number'] as $key) {
            if (is_string($this->input($key))) {
                $values[$key] = $key === 'letter_number' ? trim($this->input($key)) : (preg_replace('/\s+/u', ' ', trim($this->input($key))) ?? trim($this->input($key)));
            }
        }
        $this->merge($values);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['recipient.required' => 'Isi alamat atau nama penerima surat.', 'subject.required' => 'Isi perihal surat.', 'letter_date.required' => 'Pilih tanggal surat.', 'letter_date.date_format' => 'Tanggal surat tidak valid.', 'letter_number.required' => 'Isi nomor lengkap surat manual.', 'type.required' => 'Pilih jenis surat.', 'type.in' => 'Pilih jenis surat yang tersedia.', 'expected_version.required' => 'Muat ulang halaman sebelum mengubah catatan.'];
    }
}
