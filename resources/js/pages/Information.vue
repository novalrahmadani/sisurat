<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    FileText,
    ClipboardCheck,
    BookOpen,
    ShieldCheck,
} from '@lucide/vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { login } from '@/routes';
import { show as attendance } from '@/routes/internship';
const letters = [
    [
        'Permohonan Izin Sosialisasi',
        'Menyiapkan surat permohonan izin kepada sekolah, madrasah, atau pondok pesantren.',
    ],
    [
        'Surat Tugas',
        'Mencatat penugasan petugas, wilayah tujuan, dan waktu pelaksanaan.',
    ],
    [
        'Surat Keterangan PKKMB',
        'Menyiapkan keterangan keikutsertaan mahasiswa sesuai data kegiatan PKKMB.',
    ],
    [
        'Surat Umum',
        'Menyusun surat rapat, undangan, dan kebutuhan surat lainnya dengan isian admin.',
    ],
];
</script>
<template>
    <PublicLayout active="information"
        ><Head title="Informasi SISURAT" />
        <div class="mx-auto max-w-6xl px-5 py-12 sm:px-8 sm:py-16">
            <header class="max-w-3xl">
                <p class="sisurat-kicker">Informasi SISURAT</p>
                <h1
                    class="mt-4 text-3xl leading-tight font-semibold tracking-tight sm:text-5xl"
                >
                    Mendukung pekerjaan<br /><span
                        class="text-primary dark:text-emerald-300"
                        >Sekretariat PMB.</span
                    >
                </h1>
                <p
                    class="mt-6 text-sm leading-8 text-muted-foreground sm:text-base"
                >
                    SISURAT adalah sistem administrasi Sekretariat Penerimaan
                    Mahasiswa Baru Universitas Nurul Huda. Sistem ini membantu
                    penyiapan surat, pengarsipan, pencatatan kehadiran, dan
                    pengelolaan hasil tes PMB.
                </p>
            </header>
            <section class="mt-10" aria-labelledby="letter-services">
                <h2
                    id="letter-services"
                    class="flex items-center gap-3 text-xl font-semibold"
                >
                    <FileText class="size-5 text-primary" />Layanan persuratan
                </h2>
                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                    <article
                        v-for="[title, text] in letters"
                        :key="title"
                        class="sisurat-panel p-6"
                    >
                        <h3 class="font-semibold">{{ title }}</h3>
                        <p class="mt-3 text-sm leading-7 text-muted-foreground">
                            {{ text }}
                        </p>
                    </article>
                </div>
                <p class="mt-4 text-xs leading-7 text-muted-foreground">
                    Pembuatan surat, penomoran, arsip, dan agenda surat keluar
                    dikelola oleh admin melalui ruang kerja SISURAT.
                </p>
            </section>
            <section class="mt-10 grid gap-5 md:grid-cols-2">
                <article class="sisurat-panel p-6 sm:p-7">
                    <ClipboardCheck class="size-7 text-primary" />
                    <h2 class="mt-4 text-lg font-semibold">
                        Kehadiran kegiatan dan magang
                    </h2>
                    <p class="mt-3 text-sm leading-7 text-muted-foreground">
                        Daftar hadir kegiatan dibagikan melalui tautan atau QR
                        dari petugas. Peserta magang dapat membuka halaman
                        Absensi Magang, mendaftarkan data diri, dan mencatat jam
                        masuk serta pulang sesuai ketentuan lokasi PMB.
                    </p>
                    <Link
                        :href="attendance()"
                        class="sisurat-primary-action mt-5"
                        >Absensi Magang<ArrowRight class="size-4"
                    /></Link>
                </article>
                <article class="sisurat-panel p-6 sm:p-7">
                    <BookOpen class="size-7 text-primary" />
                    <h2 class="mt-4 text-lg font-semibold">
                        Pelaksanaan tes PMB
                    </h2>
                    <p class="mt-3 text-sm leading-7 text-muted-foreground">
                        SISURAT mendukung formulir wawancara dan penilaian Tes
                        Baca Al-Qur’an. Penguji yang ditunjuk dapat mengisi
                        nilai melalui tautan khusus dari admin tanpa membuat
                        akun.
                    </p>
                    <p class="mt-4 text-xs leading-7 text-muted-foreground">
                        Tautan penilaian tes diberikan oleh petugas PMB sesuai
                        kegiatan. Rekap hasil dikelola melalui ruang admin.
                    </p>
                </article>
            </section>
            <section
                class="mt-10 rounded-3xl border border-primary/15 bg-primary/5 p-6 sm:p-8"
            >
                <h2 class="flex items-center gap-2 text-lg font-semibold">
                    <ShieldCheck class="size-5 text-primary" />Akses sesuai
                    kebutuhan
                </h2>
                <div
                    class="mt-5 grid gap-5 text-sm leading-7 text-muted-foreground sm:grid-cols-3"
                >
                    <div>
                        <h3 class="font-semibold text-foreground">
                            Pengunjung
                        </h3>
                        <p class="mt-2">
                            Membaca informasi SISURAT dan membuka layanan publik
                            yang tersedia.
                        </p>
                    </div>
                    <div>
                        <h3 class="font-semibold text-foreground">
                            Peserta dan penguji
                        </h3>
                        <p class="mt-2">
                            Mengisi formulir sesuai tautan kegiatan dari
                            petugas, tanpa akun admin.
                        </p>
                    </div>
                    <div>
                        <h3 class="font-semibold text-foreground">Admin PMB</h3>
                        <p class="mt-2">
                            Mengelola surat, data, kegiatan, hasil tes, dan
                            rekap melalui akun yang diizinkan.
                        </p>
                    </div>
                </div>
                <Link :href="login()" class="sisurat-secondary-action mt-6"
                    >Login Admin<ArrowRight class="size-4"
                /></Link>
            </section></div
    ></PublicLayout>
</template>
