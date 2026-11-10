<?php

namespace App\Http\Requests;

use App\Models\InternshipParticipant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InternshipParticipantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdministrator() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $category = is_string($this->input('participant_category')) ? $this->input('participant_category') : '';
        $institution = is_string($this->input('institution')) ? $this->input('institution') : '';

        return [
            'name' => ['required', 'string', 'max:150'],
            'participant_category' => ['required', Rule::in(array_keys(InternshipParticipant::CATEGORIES))],
            'student_number' => ['required', 'string', 'max:100', Rule::unique('internship_participants')->where(fn ($query) => $query->where('institution', $institution)->where(fn ($categories) => $categories->where('participant_category', $category)->orWhereNull('participant_category')))->ignore($this->route('participant'))],
            'institution' => ['required', 'string', 'max:200'],
            'study_program' => ['nullable', 'string', 'max:150'],
            'class_level' => ['nullable', 'string', 'max:50'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'is_active' => ['required', 'boolean'],
            'pin' => ['nullable', 'digits:6', 'confirmed'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = [];
        foreach (['name', 'student_number', 'institution', 'study_program', 'class_level'] as $field) {
            if (is_string($this->input($field))) {
                $data[$field] = trim($this->input($field));
            }
        }
        $this->merge($data);
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Isi nama lengkap peserta.', 'student_number.required' => 'Isi NIS/NISN atau NIM sesuai kategori peserta.',
            'participant_category.required' => 'Pilih kategori peserta magang.', 'participant_category.in' => 'Pilih kategori SMK, SMA, atau mahasiswa.',
            'student_number.unique' => 'Identitas ini sudah terdaftar pada institusi dan kategori tersebut. Gunakan perangkat pendaftaran sebelumnya atau hubungi admin PMB.', 'institution.required' => 'Isi nama sekolah atau perguruan tinggi asal.',
            'start_date.required' => 'Pilih tanggal mulai magang.', 'end_date.required' => 'Pilih tanggal selesai magang.',
            'end_date.after_or_equal' => 'Tanggal selesai harus sama atau setelah tanggal mulai.',
            'pin.digits' => 'PIN absensi harus terdiri dari 6 angka.',
            'pin.confirmed' => 'Konfirmasi PIN tidak sama.',
        ];
    }
}

