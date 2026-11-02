<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('districts')) {
            Schema::create('districts', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150);
                $table->string('identity_key', 64)->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('institutions', 'district_id')) {
            Schema::table('institutions', function (Blueprint $table) {
                $table->foreignId('district_id')->nullable();
            });
        }
        $hasDistrictForeignKey = collect(Schema::getForeignKeys('institutions'))
            ->contains(fn (array $foreignKey): bool => $foreignKey['columns'] === ['district_id']);
        if (! $hasDistrictForeignKey) {
            Schema::table('institutions', function (Blueprint $table) {
                $table->foreign('district_id')->references('id')->on('districts')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('staff_members')) {
            Schema::create('staff_members', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150);
                $table->string('office', 150)->default('Dosen');
                $table->string('employee_number', 100)->nullable()->unique();
                $table->foreignId('district_id')->nullable()->constrained('districts')->restrictOnDelete();
                $table->timestamps();
            });
        }

        DB::transaction(function (): void {
            $timestamp = now();
            $districts = [];
            foreach (DB::table('institutions')->distinct()->orderBy('district')->pluck('district') as $name) {
                $normalized = $this->normalizeDistrictName($name);
                if ($normalized === '') {
                    continue;
                }
                $key = hash('sha256', mb_strtolower($normalized, 'UTF-8'));
                $districtId = DB::table('districts')->where('identity_key', $key)->value('id');
                if ($districtId === null) {
                    $districtId = DB::table('districts')->insertGetId([
                        'name' => $normalized,
                        'identity_key' => $key,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]);
                }
                $districts[$key] = (int) $districtId;
                DB::table('institutions')->where('district', $name)->whereNull('district_id')->update(['district_id' => $districtId]);
            }

            $staffGroups = [];
            DB::table('letter_assignees')->orderBy('id')->chunkById(200, function (Collection $rows) use (&$staffGroups, $districts): void {
                foreach ($rows as $row) {
                    $name = preg_replace('/\s+/u', ' ', trim($row->name)) ?? trim($row->name);
                    $office = preg_replace('/\s+/u', ' ', trim($row->office)) ?? trim($row->office);
                    if ($name === '' || $office === '') {
                        continue;
                    }
                    $key = $this->staffIdentity($name, $office);
                    $staffGroups[$key] ??= ['name' => $name, 'office' => $office, 'districts' => [], 'unknown' => false];
                    $districtKey = hash('sha256', mb_strtolower($this->normalizeDistrictName($row->district), 'UTF-8'));
                    if (isset($districts[$districtKey])) {
                        $staffGroups[$key]['districts'][$districts[$districtKey]] = true;
                    } else {
                        $staffGroups[$key]['unknown'] = true;
                    }
                }
            });

            $existingStaff = [];
            DB::table('staff_members')->select(['id', 'name', 'office'])->orderBy('id')->chunkById(200, function (Collection $rows) use (&$existingStaff): void {
                foreach ($rows as $row) {
                    $existingStaff[$this->staffIdentity($row->name, $row->office)] = true;
                }
            });
            $newStaff = [];
            foreach ($staffGroups as $key => $person) {
                if (isset($existingStaff[$key])) {
                    continue;
                }
                $districtIds = array_keys($person['districts']);
                $newStaff[] = [
                    'name' => $person['name'],
                    'office' => $person['office'],
                    'employee_number' => null,
                    'district_id' => ! $person['unknown'] && count($districtIds) === 1 ? $districtIds[0] : null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }
            foreach (array_chunk($newStaff, 200) as $chunk) {
                DB::table('staff_members')->insert($chunk);
            }
        }, 5);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_members');
        if (Schema::hasColumn('institutions', 'district_id')) {
            $hasDistrictForeignKey = collect(Schema::getForeignKeys('institutions'))
                ->contains(fn (array $foreignKey): bool => $foreignKey['columns'] === ['district_id']);
            Schema::table('institutions', function (Blueprint $table) use ($hasDistrictForeignKey) {
                if ($hasDistrictForeignKey) {
                    $table->dropForeign(['district_id']);
                }
                $table->dropColumn('district_id');
            });
        }
        Schema::dropIfExists('districts');
    }

    private function staffIdentity(string $name, string $office): string
    {
        return hash('sha256', implode("\0", array_map(
            fn (string $value): string => mb_strtolower(preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value), 'UTF-8'),
            [$name, $office],
        )));
    }

    private function normalizeDistrictName(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
        $name = preg_replace('/^(?:kabupaten|kab|kota)(?:\.\s*|\s+)/iu', '', $value) ?? $value;
        $aliases = [
            'OKI' => 'Kabupaten Ogan Komering Ilir',
            'OGAN KOMERING ILIR' => 'Kabupaten Ogan Komering Ilir',
            'OKU' => 'Kabupaten Ogan Komering Ulu',
            'OGAN KOMERING ULU' => 'Kabupaten Ogan Komering Ulu',
            'OKUT' => 'Kabupaten Ogan Komering Ulu Timur',
            'OKU TIMUR' => 'Kabupaten Ogan Komering Ulu Timur',
            'OGAN KOMERING ULU TIMUR' => 'Kabupaten Ogan Komering Ulu Timur',
            'OKUS' => 'Kabupaten Ogan Komering Ulu Selatan',
            'OKU SELATAN' => 'Kabupaten Ogan Komering Ulu Selatan',
            'OGAN KOMERING ULU SELATAN' => 'Kabupaten Ogan Komering Ulu Selatan',
            'WAY KANAN' => 'Kabupaten Way Kanan',
            'MUARA ENIM' => 'Kabupaten Muara Enim',
            'PRABUMULIH' => 'Kota Prabumulih',
        ];
        if (isset($aliases[mb_strtoupper($name, 'UTF-8')])) {
            return $aliases[mb_strtoupper($name, 'UTF-8')];
        }
        if (preg_match('/^kota(?:\.\s*|\s+)/iu', $value) === 1) {
            return 'Kota '.$name;
        }
        if (preg_match('/^(?:kabupaten|kab)(?:\.\s*|\s+)/iu', $value) === 1) {
            return 'Kabupaten '.$name;
        }

        return $value;
    }
};
