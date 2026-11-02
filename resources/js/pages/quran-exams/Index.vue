<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, Plus, Link2 } from '@lucide/vue';
import type { QuranExamSummary } from '@/lib/quranExams';
import type { MasterDataPagination } from '@/lib/masterData';
import { quranDate } from '@/lib/quranAssessments';
import { index as assessments } from '@/routes/quran-tests';
import { create, show } from '@/routes/quran-exams';
defineProps<{ exams: MasterDataPagination<QuranExamSummary> }>();
</script>
<template>
    <main class="sisurat-page">
        <Head title="Tautan Penguji Al-Qur’an" /><Link
            :href="assessments()"
            class="sisurat-secondary-action self-start"
            ><ArrowLeft class="size-4" />Daftar penilaian</Link
        >
        <header class="my-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="sisurat-kicker">Tes Baca Al-Qur’an</p>
                <h1 class="sisurat-title mt-2">Tautan penguji</h1>
                <p class="sisurat-subtitle mt-3">
                    Kelola kegiatan tes dan bagikan formulir penilaian tanpa
                    login kepada penguji.
                </p>
            </div>
            <Link :href="create()" class="sisurat-primary-action"
                ><Plus class="size-4" />Buat tautan penguji</Link
            >
        </header>
        <section class="sisurat-panel divide-y divide-border">
            <article
                v-for="exam in exams.data"
                :key="exam.id"
                class="flex flex-wrap items-center justify-between gap-5 p-5 sm:p-6"
            >
                <div class="min-w-0">
                    <span
                        :class="
                            exam.is_open
                                ? 'bg-emerald-50 text-emerald-800'
                                : 'bg-muted text-muted-foreground'
                        "
                        class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                        >{{ exam.is_open ? 'Dibuka' : 'Ditutup' }}</span
                    >
                    <h2 class="mt-3 text-lg font-semibold break-words">
                        {{ exam.title }}
                    </h2>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ quranDate(exam.assessment_date) }} ·
                        {{ exam.assessments_count }} peserta dinilai
                    </p>
                </div>
                <Link
                    :href="show({ quran_exam: exam.id })"
                    class="sisurat-secondary-action"
                    >Kelola & bagikan<ArrowRight class="size-4"
                /></Link>
            </article>
            <div v-if="!exams.data.length" class="p-10 text-center">
                <Link2 class="mx-auto size-9 text-primary" />
                <h2 class="mt-4 text-lg font-semibold">
                    Belum ada tautan penguji
                </h2>
                <p class="mt-2 text-sm leading-7 text-muted-foreground">
                    Buat kegiatan tes, lalu bagikan tautan atau QR kepada
                    penguji.
                </p>
            </div>
        </section>
        <nav
            v-if="exams.last_page > 1"
            aria-label="Halaman kegiatan tes"
            class="mt-5 flex flex-wrap items-center gap-3"
        >
            <Link
                v-if="exams.prev_page_url"
                :href="exams.prev_page_url"
                class="sisurat-secondary-action"
                >Sebelumnya</Link
            ><span class="text-sm text-muted-foreground"
                >Halaman {{ exams.current_page }} dari
                {{ exams.last_page }}</span
            ><Link
                v-if="exams.next_page_url"
                :href="exams.next_page_url"
                class="sisurat-secondary-action"
                >Berikutnya</Link
            >
        </nav>
    </main>
</template>
