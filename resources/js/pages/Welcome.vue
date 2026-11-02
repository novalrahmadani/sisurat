<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    FileText,
    ClipboardCheck,
    BookOpen,
    Layers3,
    ShieldCheck,
    Check,
} from '@lucide/vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { information, login } from '@/routes';
import { show as attendance } from '@/routes/internship';
const features = [
    {
        icon: FileText,
        title: 'Persuratan yang tertata',
        text: 'Siapkan surat izin sosialisasi, surat tugas, keterangan PKKMB, dan surat umum melalui ruang admin.',
    },
    {
        icon: ClipboardCheck,
        title: 'Kehadiran yang tercatat',
        text: 'Kelola daftar hadir kegiatan dan absensi magang dalam satu sistem.',
    },
    {
        icon: BookOpen,
        title: 'Penilaian yang terstruktur',
        text: 'Dukung pelaksanaan tes PMB dengan formulir dan rekap hasil penilaian.',
    },
];
</script>
<template>
    <PublicLayout active="home"
        ><Head title="SISURAT · Administrasi PMB Universitas Nurul Huda" />
        <section
            class="mx-auto grid max-w-7xl items-center gap-12 px-5 py-12 sm:px-8 sm:py-20 lg:grid-cols-[1.05fr_.95fr] lg:gap-16"
            aria-labelledby="welcome-title"
        >
            <div>
                <p
                    class="inline-flex items-center gap-2 rounded-full border border-primary/15 bg-primary/5 px-4 py-2 text-xs font-medium text-primary dark:text-emerald-200"
                >
                    <span class="size-1.5 rounded-full bg-primary"></span
                    >Sekretariat PMB · Universitas Nurul Huda
                </p>
                <h1
                    id="welcome-title"
                    class="mt-6 text-4xl leading-[1.15] font-semibold tracking-tight sm:text-5xl lg:text-6xl"
                >
                    Administrasi surat,<br /><span
                        class="text-primary dark:text-emerald-300"
                        >lebih tertata.</span
                    >
                </h1>
                <p
                    class="mt-6 max-w-xl text-sm leading-8 text-muted-foreground sm:text-base"
                >
                    SISURAT membantu pengelolaan surat dan administrasi
                    Penerimaan Mahasiswa Baru Universitas Nurul Huda. Dari
                    pembuatan surat, pencatatan kehadiran, hingga rekap
                    penilaian dalam satu ruang kerja.
                </p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <Link :href="information()" class="sisurat-primary-action"
                        >Informasi SISURAT<ArrowRight class="size-4" /></Link
                    ><Link :href="attendance()" class="sisurat-secondary-action"
                        ><ClipboardCheck class="size-4" />Absensi Magang</Link
                    >
                </div>
                <p
                    class="mt-5 flex items-center gap-2 text-xs leading-6 text-muted-foreground"
                >
                    <ShieldCheck class="size-4 shrink-0" />Pengelolaan surat dan
                    data dilakukan melalui akun admin.
                </p>
            </div>
            <div
                class="relative rounded-[2rem] border border-primary/10 bg-linear-to-br from-emerald-50 via-white to-[#e8f0ea] p-5 sm:p-8 dark:from-emerald-950 dark:via-card dark:to-card"
            >
                <div class="mb-4 flex items-center justify-between gap-3">
                    <p
                        class="flex items-center gap-2 text-xs font-semibold text-primary dark:text-emerald-200"
                    >
                        <Layers3 class="size-4" />Dokumen administrasi PMB
                    </p>
                    <span
                        class="text-[10px] tracking-widest text-muted-foreground uppercase"
                        >Ilustrasi</span
                    >
                </div>
                <div
                    class="rounded-lg border border-emerald-950/10 bg-white px-6 pt-6 pb-8 text-[#1f2c24] shadow-xl shadow-emerald-950/8 sm:px-8"
                >
                    <img
                        src="/images/universitas-nurul-huda-letterhead.png"
                        alt="Kop surat Universitas Nurul Huda"
                        class="w-full"
                    />
                    <div class="mt-6 space-y-2 font-serif text-xs">
                        <p>
                            <span class="inline-block w-14">Nomor</span
                            ><span class="mr-2">:</span>Sesuai agenda surat
                        </p>
                        <p>
                            <span class="inline-block w-14">Lampiran</span
                            ><span class="mr-2">:</span>-
                        </p>
                        <p>
                            <span class="inline-block w-14">Perihal</span
                            ><span class="mr-2">:</span>Permohonan Izin
                            Sosialisasi
                        </p>
                    </div>
                    <div class="mt-6 font-serif text-xs leading-6">
                        <p>Kepada Yth.<br />Pimpinan Instansi<br />di Tempat</p>
                        <p class="mt-4 font-bold italic">
                            Assalamu’alaikum Warahmatullahi Wabarakatuh
                        </p>
                        <p class="mt-3 text-justify">
                            Dalam rangka Penerimaan Mahasiswa Baru, Universitas
                            Nurul Huda bermaksud melaksanakan kegiatan
                            sosialisasi dan promosi kampus.
                        </p>
                    </div>
                    <div
                        class="mt-6 flex items-center gap-2 border-t border-emerald-950/10 pt-4 text-[10px] text-emerald-900"
                    >
                        <Check class="size-3.5" />Format surat mengikuti
                        kebutuhan administrasi PMB
                    </div>
                </div>
            </div>
        </section>
        <section
            class="mx-auto max-w-7xl px-5 pb-14 sm:px-8"
            aria-labelledby="features-title"
        >
            <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="sisurat-kicker">Tentang layanan</p>
                    <h2
                        id="features-title"
                        class="mt-2 text-2xl font-semibold tracking-tight"
                    >
                        Satu sistem, kebutuhan administrasi yang terhubung.
                    </h2>
                </div>
                <Link
                    :href="information()"
                    class="inline-flex items-center gap-2 text-sm font-semibold text-primary dark:text-emerald-200"
                    >Lihat informasi<ArrowRight class="size-4"
                /></Link>
            </div>
            <div class="grid gap-5 md:grid-cols-3">
                <article
                    v-for="feature in features"
                    :key="feature.title"
                    class="sisurat-panel p-6"
                >
                    <span
                        class="grid size-11 place-items-center rounded-xl bg-primary/7 text-primary dark:text-emerald-200"
                        ><component :is="feature.icon" class="size-5"
                    /></span>
                    <h3 class="mt-5 text-base font-semibold">
                        {{ feature.title }}
                    </h3>
                    <p class="mt-3 text-sm leading-7 text-muted-foreground">
                        {{ feature.text }}
                    </p>
                </article>
            </div>
        </section>
        <section class="mx-auto max-w-7xl px-5 pb-8 sm:px-8">
            <div
                class="flex flex-wrap items-center justify-between gap-6 rounded-3xl bg-[#005A36] px-6 py-8 text-white sm:p-10"
            >
                <div class="max-w-2xl">
                    <p
                        class="text-xs font-medium tracking-widest text-emerald-200 uppercase"
                    >
                        Untuk peserta magang
                    </p>
                    <h2 class="mt-3 text-2xl font-semibold">
                        Catat kehadiran harian Anda.
                    </h2>
                    <p class="mt-3 text-sm leading-7 text-emerald-50/80">
                        Siswa SMA, SMK, dan mahasiswa dapat mendaftarkan data
                        diri serta mencatat jam masuk dan pulang melalui Absensi
                        Magang, tanpa akun admin.
                    </p>
                </div>
                <Link
                    :href="attendance()"
                    class="inline-flex items-center gap-2 rounded-xl bg-white px-5 py-3.5 text-sm font-semibold text-[#005A36]"
                    >Buka Absensi Magang<ArrowRight class="size-4"
                /></Link>
            </div>
            <div
                class="mt-5 flex flex-wrap items-center justify-between gap-3 px-1 text-xs text-muted-foreground"
            >
                <p>
                    Petugas PMB dapat mengakses pengelolaan melalui ruang admin.
                </p>
                <Link
                    :href="login()"
                    class="font-semibold text-primary dark:text-emerald-200"
                    >Login Admin →</Link
                >
            </div>
        </section>
    </PublicLayout>
</template>
