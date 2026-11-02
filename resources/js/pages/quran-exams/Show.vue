<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Download, Settings2 } from '@lucide/vue';
import PublicFormShare from '@/components/PublicFormShare.vue';
import QuranExamForm from '@/components/QuranExamForm.vue';
import type { QuranExam } from '@/lib/quranExams';
import type { MasterDataPagination } from '@/lib/masterData';
import {
    quranDate,
    quranScore,
    quranResultLabel,
    quranResultClass,
    type QuranAssessmentSummary,
} from '@/lib/quranAssessments';
import { index, qr, flyer } from '@/routes/quran-exams';
import {
    pdf,
    show as assessmentShow,
    index as assessmentIndex,
} from '@/routes/quran-tests';
defineProps<{
    exam: QuranExam;
    publicUrl: string;
    assessments: MasterDataPagination<QuranAssessmentSummary>;
}>();
</script>
<template>
    <main class="sisurat-page">
        <Head :title="exam.title" /><Link
            :href="index()"
            class="sisurat-secondary-action self-start"
            ><ArrowLeft class="size-4" />Tautan penguji</Link
        >
        <header class="my-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="sisurat-kicker">Tes Baca Al-Qur’an · Penguji</p>
                <h1 class="sisurat-title mt-2">{{ exam.title }}</h1>
                <p class="sisurat-subtitle mt-3">
                    {{ quranDate(exam.assessment_date) }} ·
                    {{ exam.assessments_count }} peserta dinilai ·
                    {{
                        exam.is_open ? 'Penilaian dibuka' : 'Penilaian ditutup'
                    }}
                </p>
            </div>
            <a
                :href="pdf.url({ query: { exam_id: exam.id } })"
                target="_blank"
                rel="noopener noreferrer"
                download
                class="sisurat-primary-action"
                ><Download class="size-4" />Unduh rekap PDF</a
            >
        </header>
        <PublicFormShare
            class="sisurat-panel p-5 sm:p-6"
            title="Bagikan formulir kepada penguji"
            description="Penguji mengisi identitas peserta, empat nilai, dan catatan tanpa akun SISURAT. Bagikan tautan ini hanya kepada penguji yang ditunjuk."
            :url="publicUrl"
            :qr-url="qr.url({ quran_exam: exam.id })"
            :download-url="
                qr.url({ quran_exam: exam.id }, { query: { download: 1 } })
            "
            link-label="Tautan penilaian penguji"
            open-label="Buka halaman penguji"
            :flyer-url="flyer.url({ quran_exam: exam.id })"
            :flyer-download-url="
                flyer.url({ quran_exam: exam.id }, { query: { download: 1 } })
            "
        />
        <details class="sisurat-panel mt-6 p-5 sm:p-6">
            <summary
                class="flex cursor-pointer items-center gap-2 text-sm font-semibold"
            >
                <Settings2 class="size-4" />Pengaturan kegiatan dan status
                penilaian
            </summary>
            <div class="mt-5 max-w-2xl"><QuranExamForm :exam="exam" /></div>
        </details>
        <section class="sisurat-panel mt-6 overflow-hidden">
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b border-border p-5"
            >
                <div>
                    <h2 class="font-semibold">Nilai peserta</h2>
                    <p class="mt-1 text-xs leading-6 text-muted-foreground">
                        Rekap PDF mencakup seluruh peserta kegiatan ini.
                    </p>
                </div>
                <Link
                    :href="assessmentIndex({ query: { exam_id: exam.id } })"
                    class="sisurat-secondary-action"
                    >Lihat semua nilai</Link
                >
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[680px] text-left text-sm">
                    <thead class="bg-muted/50 text-xs text-muted-foreground">
                        <tr>
                            <th class="px-5 py-4">Nama / registrasi</th>
                            <th class="px-5 py-4">Program studi</th>
                            <th class="px-5 py-4">Penguji</th>
                            <th class="px-5 py-4">Rata-rata</th>
                            <th class="px-5 py-4">Hasil</th>
                            <th class="px-5 py-4">Detail</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="row in assessments.data" :key="row.id">
                            <td class="px-5 py-4">
                                <strong class="block font-medium">{{
                                    row.name
                                }}</strong
                                ><span
                                    class="mt-1 block text-xs text-muted-foreground"
                                    >{{ row.registration_number }}</span
                                >
                            </td>
                            <td class="px-5 py-4">{{ row.study_program }}</td>
                            <td class="px-5 py-4">{{ row.examiner_name }}</td>
                            <td class="px-5 py-4 tabular-nums">
                                {{ quranScore(row.average_score) }}
                            </td>
                            <td class="px-5 py-4">
                                <span
                                    class="rounded-full px-3 py-1 text-xs font-semibold whitespace-nowrap"
                                    :class="quranResultClass(row.result)"
                                    >{{ quranResultLabel(row.result) }}</span
                                >
                            </td>
                            <td class="px-5 py-4">
                                <Link
                                    :href="
                                        assessmentShow({ quran_test: row.id })
                                    "
                                    class="font-medium text-primary"
                                    >Lihat</Link
                                >
                            </td>
                        </tr>
                        <tr v-if="!assessments.data.length">
                            <td
                                colspan="6"
                                class="p-8 text-center text-muted-foreground"
                            >
                                Belum ada nilai yang dikirim penguji.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p
                v-if="exam.assessments_count > assessments.data.length"
                class="border-t border-border p-5 text-xs leading-6 text-muted-foreground"
            >
                Halaman ini menampilkan nilai terbaru. Gunakan Lihat semua nilai
                untuk pencarian dan halaman berikutnya.
            </p>
        </section>
    </main>
</template>
