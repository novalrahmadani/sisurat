<?php

namespace App\Http\Requests;

use App\Models\LetterTemplate;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LetterTemplateRequest extends FormRequest
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
        $record = $this->route('letter_template');
        $definition = $record instanceof LetterTemplate ? LetterTemplate::definitionFor($record->key) : null;
        abort_if($definition === null, 404);
        $allowedTokens = array_column($definition['tokens'], 'key');
        $requiredTokens = match ($definition['type']) {
            'assignment' => ['periode_kegiatan'],
            'pkkmb' => ['periode_kegiatan', 'tema_pkkmb', 'hasil_pkkmb'],
            default => [],
        };
        $rules = [
            'revision' => ['required', 'integer', 'min:1', 'max:2147483646'],
            'content' => ['required', 'array:'.implode(',', array_column($definition['fields'], 'id'))],
        ];

        foreach ($definition['fields'] as $field) {
            $rules['content.'.$field['id']] = [
                'bail', $field['required'] ? 'required' : 'present', 'string', 'max:'.$field['limit'],
                'not_regex:/<\/?[a-z][^>]*>|<!--|<!doctype/iu',
                function (string $attribute, mixed $value, Closure $fail) use ($allowedTokens, $requiredTokens, $field): void {
                    if (! is_string($value)) {
                        return;
                    }
                    preg_match_all('/(?<!{){{([a-z][a-z0-9_]*)}}(?!})/u', $value, $matches);
                    $remainder = preg_replace('/(?<!{){{([a-z][a-z0-9_]*)}}(?!})/u', '', $value) ?? $value;
                    if (str_contains($remainder, '{{') || str_contains($remainder, '}}')) {
                        $fail('Penanda pada '.$field['label'].' tidak sesuai. Gunakan format {{nama_penanda}} dari daftar penanda yang tersedia.');

                        return;
                    }
                    foreach (array_unique($matches[1]) as $token) {
                        if (! in_array($token, $allowedTokens, true)) {
                            $fail('Penanda {{'.$token.'}} tidak tersedia untuk jenis surat ini.');

                            return;
                        }
                    }
                    if ($field['id'] === 'description') {
                        foreach ($requiredTokens as $token) {
                            if (! in_array($token, $matches[1], true)) {
                                $fail($field['label'].' wajib memuat penanda {{'.$token.'}} agar data surat tetap lengkap.');

                                return;
                            }
                        }
                    }
                },
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'revision.required' => 'Revisi template wajib dikirim. Muat ulang halaman lalu coba kembali.',
            'revision.integer' => 'Revisi template tidak valid. Muat ulang halaman lalu coba kembali.',
            'revision.min' => 'Revisi template tidak valid. Muat ulang halaman lalu coba kembali.',
            'revision.max' => 'Revisi template tidak valid. Muat ulang halaman lalu coba kembali.',
            'content.required' => 'Isi template surat wajib dikirim.',
            'content.array' => 'Isian template tidak sesuai dengan jenis surat yang dipilih.',
            'content.*.required' => ':attribute wajib diisi.',
            'content.*.present' => ':attribute wajib dikirim, meskipun dikosongkan.',
            'content.*.string' => ':attribute harus berupa teks.',
            'content.*.max' => ':attribute maksimal :max karakter.',
            'content.*.not_regex' => 'Gunakan teks biasa untuk :attribute, tanpa tag HTML.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $record = $this->route('letter_template');
        $definition = $record instanceof LetterTemplate ? LetterTemplate::definitionFor($record->key) : null;
        $attributes = [];
        foreach ($definition['fields'] ?? [] as $field) {
            $attributes['content.'.$field['id']] = $field['label'];
        }

        return $attributes;
    }

    protected function prepareForValidation(): void
    {
        $content = $this->input('content');
        if (! is_array($content)) {
            return;
        }
        foreach ($content as $field => $value) {
            if ($value === null) {
                $content[$field] = '';
            } elseif (is_string($value)) {
                $value = trim(str_replace(["\r\n", "\r"], "\n", $value));
                $content[$field] = preg_replace('/(?<!{){{\s*([a-z][a-z0-9_]*)\s*}}(?!})/u', '{{$1}}', $value) ?? $value;
            }
        }
        $this->merge(['content' => $content]);
    }
}
