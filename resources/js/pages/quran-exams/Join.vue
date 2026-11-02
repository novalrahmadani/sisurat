<script setup lang="ts">
import type { PmbTestSession } from '@/lib/pmbTestSession';
import PmbTestSessionFields from '@/components/PmbTestSessionFields.vue';
import type { WaveInput } from '@/lib/pmbTestSession';
import { Head, useForm } from '@inertiajs/vue3';
import { BookOpen, CalendarDays, CheckCircle2, Send } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import InputError from '@/components/InputError.vue';
import {
    newQuranSubmissionToken,
    quranAverage,
    quranPlacement,
    quranResultLabel,
    quranResultClass,
    quranScore,
    quranScoreFields,
    quranDate,
    type QuranResult,
} from '@/lib/quranAssessments';
import { submit as submitScores } from '@/routes/quran-exams';
type Score = number | '';
const props = defineProps<{
    exam: PmbTestSession & {
        title: string;
        description: string | null;
        assessment_date: string;
        is_open: boolean;
        version: string;
    };
    token: string;
    studyPrograms: Array<{ id: number; name: string }>;
    receipt: {
        name: string;
        registration_number: string;
        average_score: number;
        result: QuranResult;
    } | null;
}>();
const form = useForm({
    wave: '' as WaveInput,
    period: '',
    academic_year: '',
    campus: '',
    name: '',
    registration_number: '',
    study_program_id: '',
    examiner_name: '',
    reading_reference: '',
    fasohah_score: '' as Score,
    tajwid_score: '' as Score,
    makhraj_score: '' as Score,
    fluency_score: '' as Score,
    notes: '',
    submission_token: newQuranSubmissionToken(),
    exam_version: props.exam.version,
});
const average = computed(() =>
    quranAverage([
        form.fasohah_score,
        form.tajwid_score,
        form.makhraj_score,
        form.fluency_score,
    ]),
);
const placement = computed(() => quranPlacement(average.value));
const networkError = ref('');
const success = ref<HTMLElement | null>(null);
const errors = ref<HTMLElement | null>(null);
watch(
    () => props.exam.version,
    (version) => {
        form.exam_version = version;
    },
);
function fieldError(key: string) {
    return (form.errors as Record<string, string | undefined>)[key];
}
function submit() {
    if (form.processing) return;
    networkError.value = '';
    form.post(submitScores.url({ token: props.token }), {
        preserveScroll: true,
        onSuccess: () => {
            const examiner = form.examiner_name;
            const reading = form.reading_reference;
            const session = {
                wave: form.wave,
                period: form.period,
                academic_year: form.academic_year,
                campus: form.campus,
            };
            form.reset();
            form.examiner_name = examiner;
            form.reading_reference = reading;
            Object.assign(form, session);
            form.exam_version = props.exam.version;
            form.submission_token = newQuranSubmissionToken();
            void nextTick(() => success.value?.focus());
        },
        onError: () => {
            void nextTick(() => errors.value?.focus());
        },
        onNetworkError: () => {
            networkError.value =
                'Koneksi terputus. Isian tetap tersedia. Periksa koneksi lalu kirim kembali.';
            return false;
        },
        onHttpException: () => {
            networkError.value =
                'Nilai belum dapat dikirim. Muat ulang halaman jika sesi telah berakhir.';
            return false;
        },
    });
}
</script>
<template>
    <PublicLayout
        ><Head :title="`Penilaian Penguji · ${exam.title}`" />
        <div class="mx-auto max-w-6xl px-5 py-10 sm:px-8 sm:py-14">
            <header class="mb-8 max-w-3xl">
                <p class="sisurat-kicker">
                    Tes Baca Al-Qur’an · Formulir Penguji
                </p>
                <h1
                    class="mt-3 text-3xl leading-tight font-semibold tracking-tight sm:text-4xl"
                >
                    {{ exam.title }}
                </h1>
                <p
                    class="mt-4 flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <CalendarDays class="size-4" />{{
                        quranDate(exam.assessment_date)
                    }}
                </p>
                <p
                    v-if="exam.description"
                    class="mt-4 text-sm leading-7 break-words whitespace-pre-wrap text-muted-foreground"
                >
                    {{ exam.description }}
                </p>
            </header>
            <div
                v-if="receipt"
                ref="success"
                tabindex="-1"
                role="status"
                class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-950 focus-visible:outline-2 focus-visible:outline-primary"
            >
                <h2 class="flex items-center gap-2 font-semibold">
                    <CheckCircle2 class="size-5" />Nilai berhasil disimpan
                </h2>
                <p class="mt-2 text-sm leading-7">
                    {{ receipt.name }} · {{ receipt.registration_number }} ·
                    Rata-rata {{ quranScore(receipt.average_score) }} ·
                    {{ quranResultLabel(receipt.result) }}.
                </p>
                <p v-if="exam.is_open" class="mt-1 text-xs leading-6">
                    Formulir sudah siap untuk peserta berikutnya. Nama penguji
                    dan bacaan tetap tersedia.
                </p>
            </div>
            <section v-if="!exam.is_open" class="sisurat-panel p-7 text-center">
                <BookOpen class="mx-auto size-9 text-primary" />
                <h2 class="mt-4 text-xl font-semibold">Penilaian ditutup</h2>
                <p class="mt-3 text-sm leading-7 text-muted-foreground">
                    Formulir ini sedang tidak menerima nilai. Hubungi admin PMB
                    untuk informasi pelaksanaan tes.
                </p>
            </section>
            <div v-else class="grid items-start gap-6 lg:grid-cols-[1fr_280px]">
                <form @submit.prevent="submit" class="min-w-0 space-y-5">
                    <div
                        v-if="Object.keys(form.errors).length"
                        ref="errors"
                        tabindex="-1"
                        role="alert"
                        class="rounded-xl border border-destructive/20 bg-destructive/5 p-5 text-sm text-destructive focus-visible:outline-2 focus-visible:outline-destructive"
                    >
                        <h2 class="font-semibold">Penilaian belum disimpan</h2>
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            <li v-for="(error, key) in form.errors" :key="key">
                                {{ error }}
                            </li>
                        </ul>
                        <p v-if="form.errors.exam_version" class="mt-3">
                            Informasi terbaru sudah ditampilkan. Periksa kembali
                            isian sebelum mengirim ulang.
                        </p>
                    </div>
                    <fieldset
                        :disabled="form.processing"
                        class="min-w-0 space-y-5"
                    >
                        <section
                            class="sisurat-panel p-5 sm:p-7"
                            aria-labelledby="public-mq-examiner"
                        >
                            <h2 id="public-mq-examiner" class="font-semibold">
                                1. Data penguji dan bacaan
                            </h2>
                            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label
                                        for="public-mq-examiner-name"
                                        class="mb-2 block text-sm font-medium"
                                        >Nama penguji *</label
                                    ><input
                                        id="public-mq-examiner-name"
                                        v-model="form.examiner_name"
                                        required
                                        maxlength="150"
                                        autocomplete="name"
                                        class="sisurat-input"
                                        placeholder="Nama lengkap penguji"
                                    /><InputError
                                        class="mt-2"
                                        :message="form.errors.examiner_name"
                                    />
                                </div>
                                <div>
                                    <label
                                        for="public-mq-reading"
                                        class="mb-2 block text-sm font-medium"
                                        >Surah dan ayat *</label
                                    ><input
                                        id="public-mq-reading"
                                        v-model="form.reading_reference"
                                        required
                                        maxlength="255"
                                        class="sisurat-input"
                                        placeholder="Contoh: Al-Fatihah ayat 1–7"
                                    /><InputError
                                        class="mt-2"
                                        :message="form.errors.reading_reference"
                                    />
                                </div>
                            </div>
                        </section>
                        <section
                            class="sisurat-panel p-5 sm:p-7"
                            aria-labelledby="public-mq-student"
                        >
                            <h2 id="public-mq-student" class="font-semibold">
                                2. Identitas peserta
                            </h2>
                            <div
                                class="mt-5 rounded-xl border border-border bg-muted/20 p-4"
                            >
                                <h3 class="mb-4 text-sm font-semibold">
                                    Pelaksanaan tes peserta
                                </h3>
                                <PmbTestSessionFields
                                    id-prefix="public-mq"
                                    required
                                    v-model:wave="form.wave"
                                    v-model:period="form.period"
                                    v-model:academic-year="form.academic_year"
                                    v-model:campus="form.campus"
                                    :errors="form.errors"
                                />
                                <p
                                    class="mt-3 text-xs leading-6 text-muted-foreground"
                                >
                                    Penguji mengisi gelombang, periode, tahun
                                    akademik, dan lokasi kampus sesuai peserta
                                    yang sedang dinilai.
                                </p>
                            </div>
                            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label
                                        for="public-mq-name"
                                        class="mb-2 block text-sm font-medium"
                                        >Nama peserta *</label
                                    ><input
                                        id="public-mq-name"
                                        v-model="form.name"
                                        required
                                        maxlength="150"
                                        autocomplete="off"
                                        class="sisurat-input"
                                    /><InputError
                                        class="mt-2"
                                        :message="form.errors.name"
                                    />
                                </div>
                                <div>
                                    <label
                                        for="public-mq-registration"
                                        class="mb-2 block text-sm font-medium"
                                        >Nomor registrasi *</label
                                    ><input
                                        id="public-mq-registration"
                                        v-model="form.registration_number"
                                        required
                                        maxlength="100"
                                        autocomplete="off"
                                        class="sisurat-input"
                                        placeholder="Sesuai bukti pendaftaran"
                                    /><InputError
                                        class="mt-2"
                                        :message="
                                            form.errors.registration_number
                                        "
                                    />
                                </div>
                                <div class="sm:col-span-2">
                                    <label
                                        for="public-mq-program"
                                        class="mb-2 block text-sm font-medium"
                                        >Program studi *</label
                                    ><select
                                        id="public-mq-program"
                                        v-model="form.study_program_id"
                                        required
                                        class="sisurat-input"
                                    >
                                        <option value="" disabled>
                                            Pilih program studi peserta
                                        </option>
                                        <option
                                            v-for="program in studyPrograms"
                                            :key="program.id"
                                            :value="program.id"
                                        >
                                            {{ program.name }}
                                        </option></select
                                    ><InputError
                                        class="mt-2"
                                        :message="form.errors.study_program_id"
                                    />
                                </div>
                            </div>
                        </section>
                        <section
                            class="sisurat-panel p-5 sm:p-7"
                            aria-labelledby="public-mq-scores"
                        >
                            <h2 id="public-mq-scores" class="font-semibold">
                                3. Penilaian MQ
                            </h2>
                            <p
                                class="mt-2 text-xs leading-6 text-muted-foreground"
                            >
                                Masukkan nilai bulat 1–100 untuk setiap
                                kriteria.
                            </p>
                            <div class="mt-5 grid gap-5 sm:grid-cols-2">
                                <div
                                    v-for="field in quranScoreFields"
                                    :key="field.key"
                                >
                                    <label
                                        :for="`public-${field.key}`"
                                        class="mb-2 block text-sm font-medium"
                                        >{{ field.label }} *</label
                                    ><input
                                        :id="`public-${field.key}`"
                                        v-model.number="form[field.key]"
                                        type="number"
                                        min="1"
                                        max="100"
                                        step="1"
                                        inputmode="numeric"
                                        required
                                        class="sisurat-input tabular-nums"
                                        placeholder="1–100"
                                        :aria-describedby="`help-${field.key}`"
                                    />
                                    <p
                                        :id="`help-${field.key}`"
                                        class="mt-2 text-xs text-muted-foreground"
                                    >
                                        {{ field.description }}
                                    </p>
                                    <InputError
                                        class="mt-2"
                                        :message="fieldError(field.key)"
                                    />
                                </div>
                            </div>
                            <div
                                class="mt-6 flex flex-wrap items-center justify-between gap-4 rounded-xl bg-primary/5 p-4"
                                aria-live="polite"
                            >
                                <div>
                                    <p
                                        class="text-xs font-medium text-muted-foreground"
                                    >
                                        Rata-rata empat nilai
                                    </p>
                                    <p
                                        id="public-mq-average"
                                        class="mt-1 text-2xl font-semibold text-primary"
                                    >
                                        {{ quranScore(average)
                                        }}<span
                                            class="ml-1 text-xs font-normal text-muted-foreground"
                                            >/ 100</span
                                        >
                                    </p>
                                </div>
                                <output
                                    id="public-mq-result"
                                    :class="quranResultClass(placement)"
                                    class="rounded-full px-3 py-2 text-sm font-semibold"
                                    >{{ quranResultLabel(placement) }}</output
                                >
                            </div>
                            <p
                                class="mt-3 text-xs leading-6 text-muted-foreground"
                            >
                                Rata-rata minimal 70: berhak mengikuti MQ 2.
                                Rata-rata di bawah 70: masuk MQ 1.
                            </p>
                            <div class="mt-5">
                                <label
                                    for="public-mq-notes"
                                    class="mb-2 block text-sm font-medium"
                                    >Catatan penguji
                                    <span
                                        class="font-normal text-muted-foreground"
                                        >(opsional)</span
                                    ></label
                                ><textarea
                                    id="public-mq-notes"
                                    v-model="form.notes"
                                    maxlength="5000"
                                    rows="4"
                                    class="sisurat-input"
                                    placeholder="Catatan pengamatan atau saran bimbingan"
                                ></textarea
                                ><InputError
                                    class="mt-2"
                                    :message="form.errors.notes"
                                />
                            </div>
                        </section>
                        <p
                            v-if="networkError"
                            role="alert"
                            class="rounded-xl bg-destructive/10 p-4 text-sm text-destructive"
                        >
                            {{ networkError }}
                        </p>
                        <div
                            class="flex flex-wrap items-center justify-between gap-4"
                        >
                            <p class="text-xs leading-6 text-muted-foreground">
                                Periksa identitas peserta dan empat nilai
                                sebelum mengirim.
                            </p>
                            <button
                                type="submit"
                                class="sisurat-primary-action w-full sm:w-auto"
                                :disabled="
                                    form.processing || !studyPrograms.length
                                "
                            >
                                <Send class="size-4" />{{
                                    form.processing
                                        ? 'Menyimpan nilai…'
                                        : 'Simpan nilai peserta'
                                }}
                            </button>
                        </div>
                    </fieldset>
                </form>
                <aside class="sisurat-panel p-5 sm:p-6 lg:sticky lg:top-6">
                    <BookOpen class="size-7 text-primary" />
                    <h2 class="mt-4 font-semibold">Panduan singkat penguji</h2>
                    <ol
                        class="mt-4 list-decimal space-y-3 pl-5 text-sm leading-7 text-muted-foreground"
                    >
                        <li>Isi nama penguji dan bacaan yang digunakan.</li>
                        <li>
                            Cocokkan identitas dengan bukti pendaftaran peserta.
                        </li>
                        <li>Nilai empat kriteria setelah peserta membaca.</li>
                        <li>Simpan, lalu lanjutkan ke peserta berikutnya.</li>
                    </ol>
                    <p
                        class="mt-5 border-t border-border pt-4 text-xs leading-6 text-muted-foreground"
                    >
                        Satu nomor registrasi dinilai satu kali pada kegiatan
                        ini. Koreksi nilai dilakukan oleh admin PMB.
                    </p>
                </aside>
            </div>
        </div></PublicLayout
    >
</template>
