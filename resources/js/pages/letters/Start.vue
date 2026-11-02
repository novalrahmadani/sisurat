<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Building2,
    ClipboardCheck,
    FileBadge,
    FileText,
    GraduationCap,
    School,
} from '@lucide/vue';
import { general, index as lettersIndex, pkkmb } from '@/routes/letters';
import {
    school as permissionSchool,
    pesantren as permissionPesantren,
} from '@/routes/letters/permission';
import {
    school as assignmentSchool,
    pesantren as assignmentPesantren,
} from '@/routes/letters/assignment';

const letters = [
    {
        title: 'Izin Sosialisasi',
        icon: FileBadge,
        description:
            'Memohon izin sosialisasi dan promosi kampus kepada sekolah atau pondok.',
        choices: [
            { label: 'Ke Sekolah', icon: School, href: permissionSchool() },
            {
                label: 'Ke Pondok',
                icon: Building2,
                href: permissionPesantren(),
            },
        ],
    },
    {
        title: 'Surat Tugas',
        icon: ClipboardCheck,
        description:
            'Menugaskan dosen atau petugas berdasarkan kabupaten dan tanggal kegiatan.',
        choices: [
            { label: 'Ke Sekolah', icon: School, href: assignmentSchool() },
            {
                label: 'Ke Pondok',
                icon: Building2,
                href: assignmentPesantren(),
            },
        ],
    },
    {
        title: 'Keterangan PKKMB',
        icon: GraduationCap,
        description:
            'Membuat surat keterangan mahasiswa dengan program studi, fakultas, dan hasil PKKMB.',
        choices: [
            { label: 'Buat Keterangan PKKMB', icon: ArrowRight, href: pkkmb() },
        ],
    },
    {
        title: 'Surat Umum',
        icon: FileText,
        description:
            'Membuat undangan, rapat, atau surat lainnya dengan penerima dan isi yang Anda tentukan.',
        choices: [
            { label: 'Buat Surat Umum', icon: ArrowRight, href: general() },
        ],
    },
];
</script>

<template>
    <main class="sisurat-page space-y-6">
        <Head title="Buat Surat" />
        <header>
            <p class="sisurat-kicker">
                Sekretaris PMB · Universitas Nurul Huda
            </p>
            <h1 class="sisurat-title mt-2">Surat apa yang ingin dibuat?</h1>
            <p class="sisurat-subtitle mt-2">
                Pilih jenis surat untuk membuka formulir yang sesuai.
            </p>
        </header>
        <section
            class="sisurat-panel grid gap-4 p-5 sm:grid-cols-3 sm:p-6"
            aria-label="Alur pembuatan surat"
        >
            <div
                v-for="(step, index) in [
                    'Jenis & pengaturan',
                    'Isi data',
                    'Periksa & buat',
                ]"
                :key="step"
                class="flex items-center gap-3"
            >
                <span
                    class="grid size-9 shrink-0 place-items-center rounded-full bg-primary/10 text-sm font-semibold text-primary dark:text-foreground"
                    >{{ index + 1 }}</span
                >
                <span class="text-sm font-medium">{{ step }}</span>
            </div>
        </section>
        <div class="grid gap-5 lg:grid-cols-2">
            <section
                v-for="letter in letters"
                :key="letter.title"
                class="sisurat-panel flex flex-col gap-5 p-5 sm:p-7"
            >
                <div class="flex items-start gap-4">
                    <span class="sisurat-icon-tile shrink-0"
                        ><component
                            :is="letter.icon"
                            class="size-6"
                            aria-hidden="true"
                    /></span>
                    <div>
                        <h2 class="text-lg font-semibold">
                            {{ letter.title }}
                        </h2>
                        <p class="mt-2 text-sm leading-6 text-muted-foreground">
                            {{ letter.description }}
                        </p>
                    </div>
                </div>
                <div
                    class="mt-auto flex flex-wrap gap-3 border-t border-border pt-5"
                >
                    <Link
                        v-for="choice in letter.choices"
                        :key="choice.label"
                        :href="choice.href"
                        class="sisurat-primary-action flex-1 whitespace-nowrap"
                        ><component
                            :is="choice.icon"
                            class="size-4"
                            aria-hidden="true"
                        />{{ choice.label }}</Link
                    >
                </div>
            </section>
        </div>
        <section
            class="rounded-2xl border border-primary/15 bg-primary/5 p-5 text-sm leading-7"
        >
            <p class="font-semibold">Ingin memakai surat sebelumnya?</p>
            <p class="mt-1 text-muted-foreground">
                Buka Arsip Surat, lalu pilih “Buat surat serupa”. Isi surat
                disalin untuk Anda periksa, dengan nomor baru.
            </p>
            <Link
                :href="lettersIndex()"
                class="mt-3 inline-flex items-center gap-2 font-semibold text-primary hover:underline dark:text-foreground"
                >Buka Arsip Surat<ArrowRight class="size-4" aria-hidden="true"
            /></Link>
        </section>
    </main>
</template>
