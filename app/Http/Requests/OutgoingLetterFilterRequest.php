<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OutgoingLetterFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->is_active;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:200'], 'year' => ['nullable', 'integer', 'between:2000,2100'],
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', ...($this->filled('from') ? ['after_or_equal:from'] : [])],
            'type' => ['nullable', Rule::in(['permission', 'assignment', 'pkkmb', 'general', 'finance'])],
            'source' => ['nullable', Rule::in(['sisurat', 'manual'])], 'status' => ['nullable', Rule::in(['official', 'trial', 'all'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array{q: string, year: int|null, from: string, to: string, type: string, source: string, status: string} */
    public function filters(): array
    {
        $data = $this->validated();

        return ['q' => trim($data['q'] ?? ''), 'year' => $this->exists('year') ? (isset($data['year']) ? (int) $data['year'] : null) : now('Asia/Jakarta')->year,
            'from' => $data['from'] ?? '', 'to' => $data['to'] ?? '', 'type' => $data['type'] ?? '', 'source' => $data['source'] ?? '', 'status' => $data['status'] ?? 'official'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['to.after_or_equal' => 'Tanggal akhir harus sama atau setelah tanggal awal.', 'year.between' => 'Pilih tahun antara 2000 dan 2100.', 'status.in' => 'Pilih status surat yang tersedia.', 'source.in' => 'Pilih sumber surat yang tersedia.'];
    }
}
