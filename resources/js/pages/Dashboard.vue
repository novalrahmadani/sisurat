<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    BookOpen,
    CalendarDays,
    Database,
    FilePlus2,
    FileText,
    Files,
    History,
    ClipboardList,
    MessageSquareText,
} from '@lucide/vue';
import { computed } from 'vue';
import { letterTypeLabels, type LetterType } from '@/lib/letters';
import { guide } from '@/routes';
import {
    batch as showBatch,
    create as createLetter,
    index as lettersIndex,
} from '@/routes/letters';
import { index as masterData } from '@/routes/master-data';
import { index as attendanceIndex } from '@/routes/attendance';
import { index as interviewsIndex } from '@/routes/interviews';
import { index as quranTestsIndex } from '@/routes/quran-tests';
import { index as internshipsIndex } from '@/routes/internships';

const page = usePage();
const user = computed(() => page.props.auth.user);
const props = defineProps<{
    stats: {
        lettersThisYear: number;
        batchesThisYear: number;
        lettersToday: number;
    };
    recentBatches: Array<{
        id: number;
        type: LetterType;
        letter_date: string;
        letters_count: number;
    }>;
}>();
const currentDate = new Intl.DateTimeFormat('id-ID', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
}).format(new Date());
const mainActions = [
    {
        title: 'Buat Surat',
        description:
            'Pilih jenis surat, isi data, lalu periksa sebelum disimpan dan dicetak.',
        action: 'Mulai membuat surat',
        icon: FilePlus2,
        href: createLetter(),
        primary: true,
    },
    {
        title: 'Arsip Surat',
        description:
            'Cari, cetak kembali, atau gunakan surat lama untuk membuat surat serupa.',
        action: 'Buka arsip surat',
        icon: Files,
        href: lettersIndex(),
        primary: false,
    },
    {
        title: 'Daftar Hadir',
        description:
            'Kelola daftar hadir rapat, tes PMB, atau kegiatan lainnya. Bagikan tautan dan rekap kehadiran peserta.',
        action: 'Kelola daftar hadir',
        icon: ClipboardList,
        href: attendanceIndex(),
        primary: false,
    },
    {
        title: 'Tes Wawancara PMB',
        description:
            'Atur pertanyaan, bagikan tautan kepada mahasiswa baru, dan lihat jawaban tertulis mereka.',
        action: 'Kelola tes wawancara',
        icon: MessageSquareText,
        href: interviewsIndex(),
        primary: false,
    },
    {
        title: 'Tes Baca Al-Qur’an',
        description:
            'Catat penilaian bacaan calon mahasiswa baru, lihat hasil, dan cetak hasil tes.',
        action: 'Kelola tes baca Al-Qur’an',
        icon: BookOpen,
        href: quranTestsIndex(),
        primary: false,
    },
    ...(page.props.can?.manage_internships
        ? [
              {
                  title: 'Absensi Magang',
                  description:
                      'Kelola peserta magang, kode absensi, serta rekap jam masuk dan pulang harian.',
                  action: 'Kelola absensi magang',
                  icon: ClipboardList,
                  href: internshipsIndex(),
                  primary: false,
              },
          ]
        : []),
    {
        title: 'Master Data',
        description:
            'Kelola sekolah, pondok, kabupaten, dosen, program studi, dan fakultas.',
        action: 'Kelola data',
        icon: Database,
        href: masterData(),
        primary: false,
    },
];
const statistics = computed(() => [
    { label: 'Surat tahun ini', value: props.stats.lettersThisYear },
    { label: 'Pembuatan tahun ini', value: props.stats.batchesThisYear },
    { label: 'Surat hari ini', value: props.stats.lettersToday },
]);
function formatDate(value: string): string {
    return new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(new Date(`${value.slice(0, 10)}T00:00:00`));
}
</script>

<template>
    <main class="sisurat-page space-y-7">
        <Head title="Beranda" />
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="sisurat-kicker">
                    Sekretaris PMB · Universitas Nurul Huda
                </p>
                <h1 class="sisurat-title mt-2">
                    Selamat datang, {{ user.name }}
                </h1>
                <p class="sisurat-subtitle mt-2">
                    Apa yang ingin Anda kerjakan hari ini?
                </p>
            </div>
            <p
                class="inline-flex items-center gap-2 text-xs text-muted-foreground capitalize"
            >
                <CalendarDays class="size-4" aria-hidden="true" />{{
                    currentDate
                }}
            </p>
        </header>
        <section
            class="grid gap-5 md:grid-cols-2 xl:grid-cols-3"
            aria-label="Menu utama SISURAT"
        >
            <Link
                v-for="action in mainActions"
                :key="action.title"
                :href="action.href"
                :class="[
                    'group flex flex-col gap-5 rounded-3xl border p-6 transition duration-200 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-primary motion-reduce:transition-none',
                    action.primary
                        ? 'sisurat-gradient border-primary text-white shadow-lg shadow-primary/10'
                        : 'sisurat-panel hover:border-primary/35 hover:shadow-lg',
                ]"
            >
                <span
                    :class="[
                        'grid size-12 place-items-center rounded-2xl',
                        action.primary
                            ? 'bg-white/15'
                            : 'bg-primary/10 text-primary dark:text-foreground',
                    ]"
                    ><component
                        :is="action.icon"
                        class="size-6"
                        aria-hidden="true"
                /></span>
                <div>
                    <h2 class="text-xl font-semibold">{{ action.title }}</h2>
                    <p
                        :class="[
                            'mt-3 text-sm leading-7',
                            action.primary
                                ? 'text-white/85'
                                : 'text-muted-foreground',
                        ]"
                    >
                        {{ action.description }}
                    </p>
                </div>
                <span
                    :class="[
                        'mt-auto flex items-center justify-between gap-2 border-t pt-5 text-sm font-semibold',
                        action.primary
                            ? 'border-white/20'
                            : 'border-border text-primary dark:text-foreground',
                    ]"
                    >{{ action.action
                    }}<ArrowRight class="size-4" aria-hidden="true"
                /></span>
            </Link>
        </section>
        <section
            class="sisurat-panel flex flex-wrap items-center justify-between gap-4 p-5"
            aria-label="Petunjuk singkat"
        >
            <div>
                <h2 class="text-sm font-semibold">Cukup tiga langkah</h2>
                <p class="mt-2 text-sm text-muted-foreground">
                    Jenis & pengaturan → Isi data → Periksa & buat
                </p>
            </div>
            <Link :href="guide()" class="sisurat-secondary-action"
                ><BookOpen class="size-4" aria-hidden="true" />Buka
                panduan</Link
            >
        </section>
        <section
            class="grid gap-4 sm:grid-cols-3"
            aria-label="Ringkasan persuratan"
        >
            <article
                v-for="statistic in statistics"
                :key="statistic.label"
                class="sisurat-panel p-5"
            >
                <p class="text-sm text-muted-foreground">
                    {{ statistic.label }}
                </p>
                <p class="mt-2 text-2xl font-semibold tabular-nums">
                    {{ statistic.value.toLocaleString('id-ID') }}
                </p>
            </article>
        </section>
        <section
            class="sisurat-panel overflow-hidden"
            aria-labelledby="recent-heading"
        >
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b border-border px-5 py-5 sm:px-6"
            >
                <div>
                    <h2 id="recent-heading" class="font-semibold">
                        Surat terbaru
                    </h2>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Buka surat untuk melihat hasil dan mencetaknya.
                    </p>
                </div>
                <Link
                    :href="lettersIndex()"
                    class="inline-flex items-center gap-2 text-xs font-semibold text-primary dark:text-foreground"
                    >Lihat semua arsip<ArrowRight
                        class="size-4"
                        aria-hidden="true"
                /></Link>
            </div>
            <div v-if="recentBatches.length" class="divide-y divide-border">
                <Link
                    v-for="batchItem in recentBatches"
                    :key="batchItem.id"
                    :href="showBatch({ batch: batchItem.id })"
                    class="group flex items-center justify-between gap-4 px-5 py-4 transition hover:bg-primary/4 sm:px-6"
                >
                    <span class="flex min-w-0 items-center gap-3"
                        ><span
                            class="grid size-10 shrink-0 place-items-center rounded-xl bg-muted text-primary dark:text-foreground"
                            ><FileText
                                class="size-4"
                                aria-hidden="true" /></span
                        ><span class="min-w-0"
                            ><span class="block truncate text-sm font-medium">{{
                                letterTypeLabels[batchItem.type]
                            }}</span
                            ><span
                                class="mt-1 block text-xs text-muted-foreground"
                                >{{ formatDate(batchItem.letter_date) }} ·
                                {{ batchItem.letters_count }} surat</span
                            ></span
                        ></span
                    ><ArrowRight
                        class="size-4 shrink-0 text-primary dark:text-foreground"
                        aria-hidden="true"
                    />
                </Link>
            </div>
            <div
                v-else
                class="flex flex-col items-center px-6 py-12 text-center"
            >
                <span class="sisurat-icon-tile"
                    ><History class="size-5" aria-hidden="true"
                /></span>
                <h3 class="mt-4 text-sm font-semibold">
                    Belum ada surat tersimpan
                </h3>
                <p
                    class="mt-2 max-w-sm text-xs leading-6 text-muted-foreground"
                >
                    Surat yang Anda buat akan tersedia di sini dan di Arsip
                    Surat.
                </p>
                <Link
                    :href="createLetter()"
                    class="sisurat-secondary-action mt-5"
                    >Buat surat pertama<ArrowRight
                        class="size-4"
                        aria-hidden="true"
                /></Link>
            </div>
        </section>
    </main>
</template>
