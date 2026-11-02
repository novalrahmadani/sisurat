<script setup lang="ts">
import type { PmbTestSession } from '@/lib/pmbTestSession';
import PmbTestSessionSummary from '@/components/PmbTestSessionSummary.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CalendarDays, Printer } from '@lucide/vue';
import {
    interviewTimestamp,
    type InterviewAnswer,
    type InterviewSubmission,
} from '@/lib/interviews';
import { show } from '@/routes/interviews';
import { print as printSubmission } from '@/routes/interviews/submissions';

defineProps<{
    interview: PmbTestSession & { id: number; title: string };
    submission: InterviewSubmission & { answers: InterviewAnswer[] };
}>();
</script>

<template>
    <main class="sisurat-page">
        <Head :title="`Hasil Wawancara · ${submission.name}`" />
        <div class="mx-auto flex w-full max-w-4xl flex-col gap-6">
            <Link
                :href="show(interview.id)"
                class="sisurat-secondary-action self-start"
                ><ArrowLeft class="size-4" aria-hidden="true" />Kembali ke hasil
                wawancara</Link
            >
            <header class="flex flex-wrap items-end justify-between gap-4">
                <div class="min-w-0 flex-1">
                    <p class="sisurat-kicker">Hasil Tes Wawancara</p>
                    <h1 class="sisurat-title mt-2 break-words">
                        {{ submission.name }}
                    </h1>
                    <p
                        class="mt-2 text-sm leading-7 break-words text-muted-foreground"
                    >
                        {{ interview.title }}
                    </p>
                </div>
                <a
                    :href="
                        printSubmission.url({
                            interview: interview.id,
                            submission: submission.id,
                        })
                    "
                    target="_blank"
                    rel="noopener noreferrer"
                    class="sisurat-primary-action"
                    ><Printer class="size-4" aria-hidden="true" />Cetak / Simpan
                    PDF</a
                >
            </header>
            <PmbTestSessionSummary :session="submission" />
            <section
                class="sisurat-panel p-5 sm:p-7"
                aria-labelledby="interview-student-heading"
            >
                <h2
                    id="interview-student-heading"
                    class="text-base font-semibold"
                >
                    Identitas mahasiswa
                </h2>
                <dl class="mt-5 grid gap-5 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Nama lengkap
                        </dt>
                        <dd class="mt-2 text-sm font-medium break-words">
                            {{ submission.name }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Nomor registrasi
                        </dt>
                        <dd class="mt-2 text-sm font-medium break-words">
                            {{ submission.registration_number }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Program studi
                        </dt>
                        <dd class="mt-2 text-sm font-medium break-words">
                            {{ submission.study_program }}
                        </dd>
                    </div>
                    <div>
                        <dt
                            class="flex items-center gap-1.5 text-xs text-muted-foreground"
                        >
                            <CalendarDays
                                class="size-3.5"
                                aria-hidden="true"
                            />Waktu pengisian
                        </dt>
                        <dd class="mt-2 text-sm font-medium">
                            {{ interviewTimestamp(submission.submitted_at) }}
                        </dd>
                    </div>
                </dl>
            </section>
            <section
                class="space-y-4"
                aria-labelledby="interview-answers-heading"
            >
                <header>
                    <h2
                        id="interview-answers-heading"
                        class="text-base font-semibold"
                    >
                        Pertanyaan dan jawaban
                    </h2>
                    <p class="mt-2 text-xs leading-6 text-muted-foreground">
                        Berikut salinan pertanyaan saat mahasiswa mengirim
                        formulir. Perubahan pertanyaan berikutnya tidak mengubah
                        hasil ini.
                    </p>
                </header>
                <article
                    v-for="(answer, answerIndex) in submission.answers"
                    :key="answer.question_id"
                    class="sisurat-panel overflow-hidden"
                >
                    <header
                        class="flex gap-3 border-b border-border/60 p-5 sm:px-7"
                    >
                        <span
                            class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-xs font-semibold text-primary"
                            >{{ answerIndex + 1 }}</span
                        >
                        <h3
                            class="pt-0.5 text-sm leading-7 font-semibold break-words whitespace-pre-wrap"
                        >
                            {{ answer.question }}
                        </h3>
                    </header>
                    <div class="p-5 sm:px-7">
                        <p
                            class="mb-3 text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            Jawaban mahasiswa
                        </p>
                        <p
                            v-if="answer.answer"
                            class="text-sm leading-8 break-words whitespace-pre-wrap"
                        >
                            {{ answer.answer }}
                        </p>
                        <p
                            v-else
                            class="text-sm leading-7 text-muted-foreground"
                        >
                            Tidak diisi.
                        </p>
                    </div>
                </article>
            </section>
        </div>
    </main>
</template>
