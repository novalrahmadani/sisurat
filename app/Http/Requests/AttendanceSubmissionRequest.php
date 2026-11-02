<?php

namespace App\Http\Requests;

use App\Models\AttendanceEvent;
use App\Rules\SignaturePng;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

class AttendanceSubmissionRequest extends FormRequest
{
    private ?SignaturePng $signatureRule = null;

    private ?string $attendanceType = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $this->attendanceType = AttendanceEvent::query()->where('public_token', $this->route('token'))->value('type');
        abort_unless(in_array($this->attendanceType, AttendanceEvent::TYPES, true), 404);

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $this->signatureRule = new SignaturePng;
        $isPmbTest = $this->attendanceType === AttendanceEvent::TYPE_PMB_TEST;

        return [
            'name' => ['required', 'string', 'max:150'],
            'position' => [$isPmbTest ? 'exclude' : 'required', 'string', 'max:150'],
            'institution' => [$isPmbTest ? 'exclude' : 'required', 'string', 'max:255'],
            'registration_number' => [$isPmbTest ? 'required' : 'exclude', 'string', 'max:100'],
            'study_program_id' => [$isPmbTest ? 'required' : 'exclude', 'integer'],
            'school_origin' => [$isPmbTest ? 'required' : 'exclude', 'string', 'max:200'],
            'phone_number' => [$isPmbTest ? 'required' : 'exclude', 'string', 'max:30', 'regex:/\A\+?[0-9]{8,15}\z/'],
            'signature' => ['bail', 'required', 'string', 'max:409622', $this->signatureRule],
            'submission_token' => ['required', 'uuid'],
        ];
    }

    public function canonicalSignature(): string
    {
        return $this->signatureRule?->canonical() ?? throw new LogicException('Signature must be validated before saving.');
    }

    public function validatedAttendanceType(): string
    {
        return $this->attendanceType ?? throw new LogicException('Attendance type must be resolved before saving.');
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama lengkap wajib diisi.',
            'name.max' => 'Nama lengkap maksimal 150 karakter.',
            'position.required' => 'Jabatan wajib diisi.',
            'position.max' => 'Jabatan maksimal 150 karakter.',
            'institution.required' => 'Instansi/institusi wajib diisi.',
            'institution.max' => 'Instansi/institusi maksimal 255 karakter.',
            'registration_number.required' => 'Nomor registrasi wajib diisi.',
            'registration_number.string' => 'Nomor registrasi harus berupa teks.',
            'registration_number.max' => 'Nomor registrasi maksimal 100 karakter.',
            'study_program_id.required' => 'Pilih program studi yang diambil.',
            'study_program_id.integer' => 'Pilihan program studi tidak valid.',
            'school_origin.required' => 'Asal sekolah wajib diisi.',
            'school_origin.max' => 'Nama sekolah maksimal 200 karakter.',
            'phone_number.required' => 'Nomor HP/WhatsApp wajib diisi.',
            'phone_number.string' => 'Nomor HP/WhatsApp harus berupa teks agar angka nol di awal tersimpan.',
            'phone_number.max' => 'Nomor HP/WhatsApp terlalu panjang.',
            'phone_number.regex' => 'Isi nomor HP/WhatsApp dengan 8–15 digit, misalnya 081234567890 atau +6281234567890.',
            'signature.required' => 'Bubuhkan tanda tangan pada kotak yang tersedia.',
            'signature.string' => 'Tanda tangan tidak valid. Hapus dan gambar kembali.',
            'signature.max' => 'Ukuran tanda tangan terlalu besar. Hapus dan gambar kembali.',
            'submission_token.required' => 'Muat ulang halaman sebelum mengirim daftar hadir.',
            'submission_token.uuid' => 'Muat ulang halaman sebelum mengirim daftar hadir.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];
        foreach (['name', 'position', 'institution', 'school_origin'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $normalized[$field] = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
            }
        }
        $submissionToken = $this->input('submission_token');
        if (is_string($submissionToken)) {
            $normalized['submission_token'] = strtolower(trim($submissionToken));
        }
        $registrationNumber = $this->input('registration_number');
        if (is_string($registrationNumber)) {
            $normalized['registration_number'] = trim($registrationNumber);
        }
        $phoneNumber = $this->input('phone_number');
        if (is_string($phoneNumber)) {
            $normalized['phone_number'] = preg_replace('/[\s().-]+/u', '', trim($phoneNumber)) ?? trim($phoneNumber);
        }
        $this->merge($normalized);
    }
}
