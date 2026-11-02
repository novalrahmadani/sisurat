<?php

namespace Database\Seeders;

use App\Models\Institution;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class InstitutionSeeder extends Seeder
{
    public function run(): void
    {
        $catalogPath = database_path('data/institutions.json');

        if (! is_file($catalogPath)) {
            throw new RuntimeException('Berkas katalog institusi tidak ditemukan.');
        }

        $catalogContents = file_get_contents($catalogPath);

        if ($catalogContents === false) {
            throw new RuntimeException('Berkas katalog institusi gagal dibaca.');
        }

        $catalog = json_decode($catalogContents, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($catalog) || ! array_is_list($catalog) || $catalog === []) {
            throw new RuntimeException('Katalog harus memuat daftar institusi yang tidak kosong.');
        }

        $validated = Validator::make(['institutions' => $catalog], [
            'institutions' => ['required', 'array', 'min:1'],
            'institutions.*' => ['required', 'array:source_number,institution_type,name,district,address'],
            'institutions.*.source_number' => ['required', 'integer', 'min:1', 'max:4294967295', 'distinct:strict'],
            'institutions.*.institution_type' => ['required', 'in:school,pesantren'],
            'institutions.*.name' => ['required', 'string', 'max:255'],
            'institutions.*.district' => ['required', 'string', 'max:150'],
            'institutions.*.address' => ['present', 'nullable', 'string', 'max:500'],
        ])->validate();

        $institutions = [];
        $seenSeedKeys = [];

        foreach ($validated['institutions'] as $index => $institution) {
            $sourceNumber = (int) $institution['source_number'];

            if ($sourceNumber !== $index + 1) {
                throw new RuntimeException('Nomor sumber katalog harus unik dan berurutan dari 1 hingga jumlah data katalog.');
            }

            $institution['source_number'] = $sourceNumber;
            $institution['name'] = trim($institution['name']);
            $institution['district'] = trim($institution['district']);
            $institution['address'] = filled($institution['address']) ? trim($institution['address']) : null;

            $seedKey = hash('sha256', implode("\0", [
                $this->normalizeSeedPart($institution['institution_type']),
                $this->normalizeSeedPart($institution['name']),
                $this->normalizeSeedPart($institution['district']),
                $this->normalizeSeedPart($institution['address'] ?? ''),
            ]));

            if (isset($seenSeedKeys[$seedKey])) {
                throw new RuntimeException("Institusi katalog nomor {$sourceNumber} tercatat dua kali.");
            }

            $seenSeedKeys[$seedKey] = true;
            $institutions[] = ['seed_key' => $seedKey, ...$institution];
        }

        DB::transaction(function () use ($institutions): void {
            foreach (array_chunk($institutions, 200) as $chunk) {
                Institution::query()->upsert(
                    $chunk,
                    ['seed_key'],
                    ['institution_type', 'name', 'district', 'address', 'source_number'],
                );
            }
        });

        $this->command?->info('Katalog institusi: '.count($institutions).' data disinkronkan tanpa menggandakan tujuan.');
    }

    private function normalizeSeedPart(string $value): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($value)) ?? $value, 'UTF-8');
    }
}
