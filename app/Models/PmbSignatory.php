<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PmbSignatory extends Model
{
    protected $fillable = ['name', 'title', 'number', 'signature_path', 'stamp_path', 'updated_by'];

    public static function current(): self
    {
        return self::query()->latest('id')->firstOrFail();
    }

    public static function legacy(): self
    {
        return self::query()->oldest('id')->firstOrFail();
    }

    public function matchesName(string $name): bool
    {
        $normalize = fn (string $value): string => preg_replace('/[^\pL\pN]/u', '', mb_strtolower($value)) ?? '';

        return $normalize($name) !== '' && $normalize($name) === $normalize($this->name);
    }

    /** @return array{id: int, name: string, title: string, number: string|null, signature_url: string|null, stamp_url: string|null} */
    public function presentation(): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'title' => $this->title, 'number' => $this->number,
            'signature_url' => $this->signature_path ? route('master-data.pmb-signatory.media', ['signatory' => $this->id, 'kind' => 'signature']) : null,
            'stamp_url' => $this->stamp_path ? route('master-data.pmb-signatory.media', ['signatory' => $this->id, 'kind' => 'stamp']) : null,
        ];
    }
}
