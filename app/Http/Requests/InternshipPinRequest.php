<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class InternshipPinRequest extends FormRequest
{
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
        return [
            'pin' => ['required', 'string', 'regex:/^[0-9]{8}$/D'],
        ];
    }

    public function messages(): array
    {
        return ['pin.required' => 'Masukkan PIN pribadi Anda.', 'pin.string' => 'PIN harus berupa 8 angka.', 'pin.regex' => 'PIN harus terdiri dari 8 angka.'];
    }
}
