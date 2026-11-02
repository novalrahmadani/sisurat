<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class LetterTemplate extends Model
{
    protected $fillable = ['key', 'content', 'revision'];

    protected function casts(): array
    {
        return ['content' => 'array', 'revision' => 'integer'];
    }

    /**
     * @return array<int, array{key: string, name: string, type: string, destination: ?string, fields: array<int, array{id: string, label: string, limit: int, required: bool}>, tokens: array<int, array{key: string, label: string, sample: string}>}>
     */
    public static function catalog(): array
    {
        $permissionFields = [
            ['id' => 'subject', 'label' => 'Perihal', 'limit' => 255, 'required' => true],
            ['id' => 'attachment', 'label' => 'Lampiran', 'limit' => 120, 'required' => false],
            ['id' => 'opening_greeting', 'label' => 'Salam pembuka', 'limit' => 255, 'required' => false],
            ['id' => 'introduction', 'label' => 'Paragraf pembuka', 'limit' => 3000, 'required' => true],
            ['id' => 'description', 'label' => 'Isi permohonan', 'limit' => 4000, 'required' => true],
            ['id' => 'closing', 'label' => 'Paragraf penutup', 'limit' => 3000, 'required' => true],
            ['id' => 'closing_greeting', 'label' => 'Salam penutup', 'limit' => 255, 'required' => false],
        ];
        $assignmentFields = [
            ['id' => 'title', 'label' => 'Judul surat', 'limit' => 255, 'required' => true],
            ['id' => 'basis', 'label' => 'Dasar penugasan (satu dasar per baris)', 'limit' => 5000, 'required' => true],
            ['id' => 'introduction', 'label' => 'Pengantar penugasan', 'limit' => 3000, 'required' => true],
            ['id' => 'description', 'label' => 'Isi penugasan', 'limit' => 4000, 'required' => true],
            ['id' => 'closing', 'label' => 'Paragraf penutup', 'limit' => 3000, 'required' => true],
        ];
        $pkkmbFields = [
            ['id' => 'title', 'label' => 'Judul surat', 'limit' => 255, 'required' => true],
            ['id' => 'introduction', 'label' => 'Paragraf pembuka', 'limit' => 3000, 'required' => true],
            ['id' => 'description', 'label' => 'Isi keterangan PKKMB', 'limit' => 4000, 'required' => true],
            ['id' => 'closing', 'label' => 'Paragraf penutup', 'limit' => 3000, 'required' => true],
        ];
        $commonTokens = [
            ['key' => 'nomor_surat', 'label' => 'Nomor surat', 'sample' => '001/'.LetterBatch::DEFAULT_NUMBER_PREFIX.'/X/2026'],
            ['key' => 'tanggal_surat', 'label' => 'Tanggal surat', 'sample' => '9 Oktober 2026'],
            ['key' => 'nama_penandatangan', 'label' => 'Nama penandatangan', 'sample' => 'Wardianto, M.Kom'],
        ];
        $permissionTokens = [
            ...$commonTokens,
            ['key' => 'jabatan_penandatangan', 'label' => 'Jabatan penandatangan', 'sample' => 'Ketua Pusat PMB'],
            ['key' => 'nama_instansi', 'label' => 'Nama sekolah atau pondok', 'sample' => 'SMA Negeri 1 Belitang'],
            ['key' => 'sapaan_penerima', 'label' => 'Sapaan pada isi permohonan', 'sample' => 'Bapak/Ibu Kepala Sekolah'],
            ['key' => 'kabupaten', 'label' => 'Kabupaten/kota tujuan', 'sample' => 'OKU Timur'],
        ];
        $assignmentTokens = [
            ...$commonTokens,
            ['key' => 'jabatan_penandatangan', 'label' => 'Jabatan pemberi tugas', 'sample' => 'Ketua Pusat Penerimaan Mahasiswa Baru'],
            ['key' => 'jabatan_pelaporan', 'label' => 'Jabatan penerima laporan', 'sample' => 'Ketua PMB'],
            ['key' => 'nama_instansi', 'label' => 'Tujuan sosialisasi', 'sample' => 'Pondok Pesantren'],
            ['key' => 'kabupaten', 'label' => 'Kabupaten/kota tujuan', 'sample' => 'OKU Timur'],
            ['key' => 'periode_kegiatan', 'label' => 'Periode kegiatan', 'sample' => '9 sampai 10 Oktober 2026'],
        ];
        $permissionPesantrenTokens = array_map(fn (array $token): array => match ($token['key']) {
            'nama_instansi' => [...$token, 'sample' => 'Pondok Pesantren Nurul Huda'],
            'sapaan_penerima' => [...$token, 'sample' => 'Pimpinan Pondok Pesantren'],
            default => $token,
        }, $permissionTokens);
        $assignmentSchoolTokens = array_map(fn (array $token): array => $token['key'] === 'nama_instansi'
            ? [...$token, 'sample' => 'sekolah-sekolah tingkat SMA, SMK, dan MA']
            : $token, $assignmentTokens);
        $pkkmbTokens = [
            ...$commonTokens,
            ['key' => 'jabatan_penandatangan', 'label' => 'Jabatan pemberi keterangan', 'sample' => 'Ketua Pusat Penerimaan Mahasiswa Baru dan Pembinaan Mahasiswa'],
            ['key' => 'periode_kegiatan', 'label' => 'Periode PKKMB', 'sample' => '09–10 Oktober 2026'],
            ['key' => 'tema_pkkmb', 'label' => 'Tema PKKMB', 'sample' => 'Membentuk Mahasiswa Berkarakter'],
            ['key' => 'nama_mahasiswa', 'label' => 'Nama mahasiswa', 'sample' => 'Ahmad Fauzi'],
            ['key' => 'nim', 'label' => 'NIM', 'sample' => '20260001'],
            ['key' => 'program_studi', 'label' => 'Program studi', 'sample' => 'Informatika'],
            ['key' => 'fakultas', 'label' => 'Fakultas', 'sample' => 'Fakultas Sains dan Teknologi'],
            ['key' => 'hasil_pkkmb', 'label' => 'Hasil PKKMB', 'sample' => 'LULUS'],
        ];

        return [
            ['key' => 'permission-school', 'name' => 'Izin Sosialisasi ke Sekolah', 'type' => 'permission', 'destination' => 'school', 'fields' => $permissionFields, 'tokens' => $permissionTokens],
            ['key' => 'permission-pesantren', 'name' => 'Izin Sosialisasi ke Pondok Pesantren', 'type' => 'permission', 'destination' => 'pesantren', 'fields' => $permissionFields, 'tokens' => $permissionPesantrenTokens],
            ['key' => 'assignment-school', 'name' => 'Surat Tugas ke Sekolah', 'type' => 'assignment', 'destination' => 'school', 'fields' => $assignmentFields, 'tokens' => $assignmentSchoolTokens],
            ['key' => 'assignment-pesantren', 'name' => 'Surat Tugas ke Pondok Pesantren', 'type' => 'assignment', 'destination' => 'pesantren', 'fields' => $assignmentFields, 'tokens' => $assignmentTokens],
            ['key' => 'pkkmb', 'name' => 'Keterangan PKKMB', 'type' => 'pkkmb', 'destination' => null, 'fields' => $pkkmbFields, 'tokens' => $pkkmbTokens],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function definitionFor(string $key): ?array
    {
        return collect(self::catalog())->firstWhere('key', $key);
    }

    /**
     * @param  array<int, string>  $institutionTypes
     * @return array{version: int, templates: array<string, array<string, string>>}
     */
    public static function snapshotFor(string $type, array $institutionTypes): array
    {
        $definitions = collect(self::catalog())->filter(fn (array $definition): bool => $definition['type'] === $type
            && ($definition['destination'] === null || in_array($definition['destination'], $institutionTypes, true)));
        $records = self::query()->whereIn('key', $definitions->pluck('key'))->get(['key', 'content'])->keyBy('key');
        $templates = [];

        foreach ($definitions as $definition) {
            $record = $records->get($definition['key']);
            $content = $record?->content;
            if (! is_array($content)) {
                throw ValidationException::withMessages([
                    'type' => 'Template '.$definition['name'].' belum tersedia. Pastikan pembaruan Master Data template surat telah dipasang.',
                ]);
            }

            foreach ($definition['fields'] as $field) {
                if (! isset($content[$field['id']]) || ! is_string($content[$field['id']])) {
                    throw ValidationException::withMessages([
                        'type' => 'Isi template '.$definition['name'].' belum lengkap. Periksa kembali melalui Master Data sebelum membuat surat.',
                    ]);
                }
            }
            $templates[$definition['key']] = $content;
        }

        return ['version' => 1, 'templates' => $templates];
    }
}
