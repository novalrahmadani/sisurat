<?php

namespace App\Http\Requests;

use App\Rules\SignaturePng;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;

class InternshipAttendanceRequest extends FormRequest
{
    private ?SignaturePng $signatureRule = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
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

        return [
            'action' => ['required', Rule::in(['check_in', 'check_out'])],
            'note' => ['nullable', 'string', 'max:500'],
            'signature' => ['bail', 'required', 'string', 'max:409622', $this->signatureRule],
        ];
    }

    public function canonicalSignature(): string
    {
        return $this->signatureRule?->canonical() ?? throw new LogicException('Signature must be validated before saving.');
    }

    public function messages(): array
    {
        return ['action.required' => 'Pilih absensi masuk atau pulang.', 'action.in' => 'Jenis absensi tidak valid.',
            'signature.required' => 'Bubuhkan tanda tangan sebelum menyimpan absensi.', 'signature.max' => 'Tanda tangan terlalu besar. Hapus dan gambar kembali.',
            'note.max' => 'Keterangan maksimal 500 karakter.'];
    }
}
