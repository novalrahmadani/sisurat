<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('letter_templates')) {
            Schema::create('letter_templates', function (Blueprint $table) {
                $table->id();
                $table->string('key', 60)->unique();
                $table->json('content');
                $table->unsignedInteger('revision')->default(1);
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('letter_batches', 'content_templates')) {
            Schema::table('letter_batches', function (Blueprint $table) {
                $table->json('content_templates')->nullable();
            });
        }

        $templates = [
            'permission-school' => [
                'subject' => 'Permohonan Izin Sosialisasi dan Promosi',
                'attachment' => '-',
                'opening_greeting' => 'Assalamu’alaikum Warahmatullahi Wabarakatuh',
                'introduction' => 'Salam silaturahim kami sampaikan, semoga kita semua tetap dalam lindungan dan ridho Allah subhanahu wa ta’ala.',
                'description' => 'Dalam rangka persiapan Penerimaan Mahasiswa Baru (PMB) Universitas Nurul Huda, kami bermaksud mengadakan sosialisasi dan promosi kampus kami. Oleh karena itu, kami memohon kepada Bapak/Ibu Kepala Sekolah berkenan memberikan izin kepada kami untuk melaksanakan acara Sosialisasi dan Promosi Universitas Nurul Huda.',
                'closing' => 'Demikian surat permohonan ini kami sampaikan. Atas perhatian dan kerja samanya, kami ucapkan terima kasih.',
                'closing_greeting' => 'Wassalamu’alaikum Warahmatullahi Wabarakatuh',
            ],
            'permission-pesantren' => [
                'subject' => 'Permohonan Izin Sosialisasi dan Promosi',
                'attachment' => '-',
                'opening_greeting' => 'Assalamu’alaikum Warahmatullahi Wabarakatuh',
                'introduction' => 'Salam silaturahim kami sampaikan, semoga kita semua tetap dalam lindungan dan ridho Allah subhanahu wa ta’ala.',
                'description' => 'Dalam rangka persiapan Penerimaan Mahasiswa Baru (PMB) Universitas Nurul Huda, kami bermaksud bersilaturahmi serta mengadakan sosialisasi dan promosi kampus kami. Oleh karena itu, kami memohon kepada Pimpinan Pondok Pesantren berkenan memberikan izin kepada kami untuk melaksanakan acara Sosialisasi dan Promosi Universitas Nurul Huda.',
                'closing' => 'Demikian surat permohonan ini kami sampaikan. Atas perhatian dan kerja samanya, kami ucapkan terima kasih.',
                'closing_greeting' => 'Wassalamu’alaikum Warahmatullahi Wabarakatuh',
            ],
            'assignment-school' => [
                'title' => 'Surat Tugas',
                'basis' => "Pembukaan Penerimaan Mahasiswa Baru\nSosialisasi Kampus ke Masyarakat",
                'introduction' => 'Maka, {{jabatan_penandatangan}} memberikan tugas kepada:',
                'description' => 'Untuk melaksanakan kegiatan sosialisasi dan promosi kampus Universitas Nurul Huda ke sekolah-sekolah tingkat SMA, SMK, dan MA yang dilaksanakan pada {{periode_kegiatan}}.',
                'closing' => 'Demikian surat tugas ini dibuat agar dilaksanakan dengan penuh tanggung jawab dan melaporkan hasil kegiatan kepada {{jabatan_pelaporan}}.',
            ],
            'assignment-pesantren' => [
                'title' => 'Surat Tugas',
                'basis' => "Pembukaan Penerimaan Mahasiswa Baru\nSosialisasi Kampus ke Masyarakat",
                'introduction' => 'Maka, {{jabatan_penandatangan}} memberikan tugas kepada:',
                'description' => 'Untuk melaksanakan kegiatan sosialisasi dan promosi kampus Universitas Nurul Huda ke {{nama_instansi}} yang dilaksanakan pada {{periode_kegiatan}}.',
                'closing' => 'Demikian surat tugas ini dibuat agar dilaksanakan dengan penuh tanggung jawab dan melaporkan hasil kegiatan kepada {{jabatan_pelaporan}}.',
            ],
            'pkkmb' => [
                'title' => 'SURAT KETERANGAN',
                'introduction' => '{{jabatan_penandatangan}} dengan ini menerangkan bahwa:',
                'description' => 'Benar mahasiswa tersebut di atas telah mengikuti kegiatan Pengenalan Kehidupan Kampus Bagi Mahasiswa Baru (PKKMB) dengan tema “{{tema_pkkmb}}” pada tanggal {{periode_kegiatan}} dan telah dinyatakan “{{hasil_pkkmb}}”.',
                'closing' => 'Demikian surat keterangan ini dibuat dengan sebenar-benarnya.',
            ],
        ];

        DB::transaction(function () use ($templates): void {
            $timestamp = now();
            foreach ($templates as $key => $content) {
                if (DB::table('letter_templates')->where('key', $key)->exists()) {
                    continue;
                }
                DB::table('letter_templates')->insert([
                    'key' => $key,
                    'content' => json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'revision' => 1,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            }
        }, 5);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('letter_batches', 'content_templates')) {
            Schema::table('letter_batches', function (Blueprint $table) {
                $table->dropColumn('content_templates');
            });
        }
        Schema::dropIfExists('letter_templates');
    }
};
