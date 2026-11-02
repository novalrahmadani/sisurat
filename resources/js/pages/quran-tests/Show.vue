<script setup lang="ts">
import PmbTestSessionSummary from '@/components/PmbTestSessionSummary.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, BookOpen, Info, Pencil, Plus, Printer } from '@lucide/vue';
import {
    quranDate,
    quranResultClass,
    quranResultLabel,
    quranScore,
    quranScoreFields,
    quranTimestamp,
    type QuranAssessment,
} from '@/lib/quranAssessments';
import {
    create,
    edit,
    index,
    print as printAssessment,
} from '@/routes/quran-tests';

defineProps<{ assessment: QuranAssessment }>();
</script>

<template>
    <main class="sisurat-page">
        <Head :title="`Hasil Tes Baca Al-Qur’an · ${assessment.name}`" />
        <div class="mx-auto flex w-full max-w-4xl flex-col gap-6">
            <Link :href="index()" class="sisurat-secondary-action self-start"
                ><ArrowLeft class="size-4" aria-hidden="true" />Daftar
                penilaian</Link
            >
            <header class="flex flex-wrap items-end justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <p class="sisurat-kicker">Hasil Tes Baca Al-Qur’an</p>
                    <h1 class="sisurat-title mt-2 break-words">
                        {{ assessment.name }}
                    </h1>
                    <span
                        class="mt-3 inline-flex rounded-full px-3 py-1.5 text-xs font-medium"
                        :class="quranResultClass(assessment.result)"
                        >{{ quranResultLabel(assessment.result) }}</span
                    >
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link
                        :href="edit({ quran_test: assessment.id })"
                        class="sisurat-secondary-action"
                        ><Pencil class="size-4" aria-hidden="true" />Ubah</Link
                    ><a
                        :href="
                            printAssessment.url({ quran_test: assessment.id })
                        "
                        target="_blank"
                        rel="noopener noreferrer"
                        class="sisurat-primary-action"
                        ><Printer class="size-4" aria-hidden="true" />Cetak /
                        Simpan PDF</a
                    >
                </div>
            </header>

            <section
                class="sisurat-panel p-5 sm:p-6"
                aria-labelledby="quran-identity-heading"
            >
                <h2 id="quran-identity-heading" class="text-sm font-semibold">
                    Identitas dan pelaksanaan tes
                </h2>
                <dl class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Nama lengkap
                        </dt>
                        <dd class="mt-2 text-sm font-medium break-words">
                            {{ assessment.name }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Nomor registrasi
                        </dt>
                        <dd class="mt-2 text-sm font-medium break-words">
                            {{ assessment.registration_number }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Program studi
                        </dt>
                        <dd class="mt-2 text-sm font-medium break-words">
                            {{ assessment.study_program }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Tanggal tes
                        </dt>
                        <dd class="mt-2 text-sm font-medium">
                            {{ quranDate(assessment.assessment_date) }}
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs text-muted-foreground">
                            Nama penguji
                        </dt>
                        <dd class="mt-2 text-sm font-medium break-words">
                            {{ assessment.examiner_name || 'Belum diisi' }}
                        </dd>
                    </div>
                </dl>
                <PmbTestSessionSummary
                    class="mt-5 border-t border-border pt-5"
                    :session="assessment"
                />
            </section>

            <section
                class="sisurat-panel p-5 sm:p-6"
                aria-labelledby="quran-reading-heading"
            >
                <h2
                    id="quran-reading-heading"
                    class="flex items-center gap-2 text-sm font-semibold"
                >
                    <BookOpen
                        class="size-4 text-primary"
                        aria-hidden="true"
                    />Surah dan ayat yang dibaca
                </h2>
                <p
                    class="mt-4 text-sm leading-7 break-words whitespace-pre-wrap"
                >
                    {{ assessment.reading_reference || 'Belum diisi' }}
                </p>
            </section>

            <section
                class="sisurat-panel p-5 sm:p-6"
                aria-labelledby="quran-scores-heading"
            >
                <h2 id="quran-scores-heading" class="text-sm font-semibold">
                    Nilai dan hasil penilaian
                </h2>
                <dl class="mt-5 grid gap-3 sm:grid-cols-2">
                    <div
                        v-for="field in quranScoreFields"
                        :key="field.key"
                        class="rounded-xl border border-border/70 bg-background p-4"
                    >
                        <dt class="text-xs text-muted-foreground">
                            {{ field.label }}
                        </dt>
                        <dd class="mt-3 text-2xl font-semibold tabular-nums">
                            {{ quranScore(assessment[field.key])
                            }}<span
                                v-if="assessment[field.key] !== null"
                                class="ml-1 text-xs font-normal text-muted-foreground"
                                >/ 100</span
                            >
                        </dd>
                        <p
                            class="mt-2 text-[11px] leading-5 text-muted-foreground"
                        >
                            {{ field.description }}
                        </p>
                    </div>
                </dl>
                <div
                    class="mt-4 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-primary/15 bg-primary/5 p-4"
                >
                    <div>
                        <p class="text-xs font-medium text-primary">
                            Rata-rata nilai
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Jumlah nilai Fasohah, Tajwid, Makhroj, dan
                            Kelancaran dibagi 4.
                        </p>
                    </div>
                    <p class="text-2xl font-semibold text-primary tabular-nums">
                        {{ quranScore(assessment.average_score)
                        }}<span
                            v-if="assessment.average_score !== null"
                            class="ml-1 text-xs font-normal text-muted-foreground"
                            >/ 100</span
                        >
                    </p>
                </div>
                <div class="mt-5">
                    <p class="text-xs text-muted-foreground">
                        Kategori MQ / status penilaian
                    </p>
                    <p class="mt-2 text-base font-semibold">
                        {{ quranResultLabel(assessment.result) }}
                    </p>
                    <p
                        class="mt-2 flex items-start gap-2 text-xs leading-6 text-muted-foreground"
                    >
                        <Info
                            class="mt-1 size-3.5 shrink-0"
                            aria-hidden="true"
                        />Rata-rata minimal 70: berhak mengikuti MQ 2. Rata-rata
                        di bawah 70: masuk MQ 1. Kategori dihitung otomatis
                        setelah empat nilai lengkap.
                    </p>
                </div>
                <p
                    v-if="assessment.average_score === null"
                    class="mt-4 rounded-xl bg-muted/50 px-4 py-3 text-xs leading-6 text-muted-foreground"
                >
                    Penilaian ini masih menunggu penyelesaian. Pilih Ubah untuk
                    melengkapi empat nilai. Data penilaian format lama tetap
                    tersimpan sampai diperbarui.
                </p>
            </section>

            <section
                class="sisurat-panel p-5 sm:p-6"
                aria-labelledby="quran-notes-heading"
            >
                <h2 id="quran-notes-heading" class="text-sm font-semibold">
                    Catatan penguji
                </h2>
                <p
                    class="mt-4 text-sm leading-7 break-words whitespace-pre-wrap"
                    :class="{ 'text-muted-foreground': !assessment.notes }"
                >
                    {{ assessment.notes || 'Tidak ada catatan.' }}
                </p>
            </section>
            <footer
                class="flex flex-wrap items-center justify-between gap-4 pb-3"
            >
                <div class="text-[11px] leading-6 text-muted-foreground">
                    <p>Dicatat: {{ quranTimestamp(assessment.created_at) }}</p>
                    <p>
                        Diperbarui: {{ quranTimestamp(assessment.updated_at) }}
                    </p>
                </div>
                <Link :href="create()" class="sisurat-secondary-action"
                    ><Plus class="size-4" aria-hidden="true" />Tambah penilaian
                    baru</Link
                >
            </footer>
        </div>
    </main>
</template>
