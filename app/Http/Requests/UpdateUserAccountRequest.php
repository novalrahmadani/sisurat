<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $account = $this->route('account');

        return $account instanceof User && ($this->user()?->can('update', $account) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $account = $this->route('account');
        $isOwnAccount = $account instanceof User && (int) $account->id === (int) $this->user()?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($account)],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_STAFF])],
            'is_active' => ['required', 'boolean'],
            'password' => $isOwnAccount
                ? ['prohibited']
                : ['nullable', 'string', 'min:8', 'max:255', Password::default(), 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama pengguna wajib diisi.',
            'name.max' => 'Nama pengguna maksimal 255 karakter.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Masukkan alamat email yang benar.',
            'email.unique' => 'Email ini sudah digunakan oleh akun lain.',
            'email.max' => 'Email maksimal 255 karakter.',
            'role.required' => 'Pilih peran akun.',
            'role.in' => 'Peran akun tidak tersedia.',
            'is_active.required' => 'Pilih status akun.',
            'is_active.boolean' => 'Status akun tidak valid.',
            'password.prohibited' => 'Ubah kata sandi akun Anda melalui menu Keamanan.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.max' => 'Kata sandi maksimal 255 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak sama.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }

        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }
}
