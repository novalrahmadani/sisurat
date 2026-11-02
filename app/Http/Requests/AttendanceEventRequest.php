<?php

namespace App\Http\Requests;

use App\Models\AttendanceEvent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttendanceEventRequest extends FormRequest
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
            'type' => ['required', 'string', Rule::in(AttendanceEvent::TYPES)],
            'title' => ['required', 'string', 'max:200'],
            'event_date' => ['required', 'date_format:Y-m-d'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_open' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Pilih kategori daftar hadir.',
            'type.in' => 'Kategori daftar hadir harus Rapat, Tes PMB, atau Kegiatan Lainnya.',
            'title.required' => 'Nama kegiatan wajib diisi.',
            'title.max' => 'Nama kegiatan maksimal 200 karakter.',
            'event_date.required' => 'Tanggal kegiatan wajib diisi.',
            'event_date.date_format' => 'Tanggal kegiatan tidak valid.',
            'location.max' => 'Lokasi maksimal 255 karakter.',
            'description.max' => 'Keterangan maksimal 2.000 karakter.',
            'is_open.required' => 'Pilih status daftar hadir.',
            'is_open.boolean' => 'Status daftar hadir tidak valid.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach (['title', 'location', 'description'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $normalized[$field] = trim($value);
            }
        }
        $this->merge($normalized);
    }
}
