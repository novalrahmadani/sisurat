<script setup lang="ts">
import type { PmbTestSession } from '@/lib/pmbTestSession';
import PmbTestSessionFields from '@/components/PmbTestSessionFields.vue';
import type { WaveInput } from '@/lib/pmbTestSession';
import { Head, useForm } from '@inertiajs/vue3';
import { CheckCircle2, ClipboardList, LoaderCircle, Send } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { submit as submitInterview } from '@/routes/interviews';

type InterviewQuestion = {
    id: string;
    prompt: string;
    required: boolean;
};

const props = defineProps<{
    interview: PmbTestSession & {
        title: string;
        description: string | null;
        is_open: boolean;
        questions: InterviewQuestion[];
        questions_version: string;
    };
    token: string;
    studyPrograms: Array<{ id: number; name: string }>;
    submitted: boolean;
}>();

function newSubmissionToken(): string {
    if (typeof globalThis.crypto?.randomUUID === 'function') {
        return globalThis.crypto.randomUUID();
    }
    const bytes = new Uint8Array(16);
    if (typeof globalThis.crypto?.getRandomValues === 'function') {
        globalThis.crypto.getRandomValues(bytes);
    } else {
        for (let index = 0; index < bytes.length; index++) {
            bytes[index] = Math.floor(Math.random() * 256);
        }
    }
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0'));
    return `${hex.slice(0, 4).join('')}-${hex.slice(4, 6).join('')}-${hex.slice(6, 8).join('')}-${hex.slice(8, 10).join('')}-${hex.slice(10).join('')}`;
}

function emptyAnswers() {
    return props.interview.questions.map((question) => ({
        question_id: question.id,
        answer: '',
    }));
}

const form = useForm({
    wave: '' as WaveInput,
    period: '',
    academic_year: '',
    campus: '',
    name: '',
    registration_number: '',
    study_program_id: '',
    questions_version: props.interview.questions_version,
    submission_token: newSubmissionToken(),
    answers: emptyAnswers(),
});
const receiptVisible = ref(props.submitted);
const questionsUpdated = ref(false);
const submissionError = ref('');
const nameInput = ref<HTMLInputElement | null>(null);
const errorSummary = ref<HTMLElement | null>(null);
const receipt = ref<HTMLElement | null>(null);
const hasStudyPrograms = computed(() => props.studyPrograms.length > 0);
const requiredQuestions = computed(() =>
    props.interview.questions.filter((question) => question.required),
);
const answeredCount = computed(() => {
    const trackedQuestions = requiredQuestions.value.length
        ? requiredQuestions.value
        : props.interview.questions;
    return trackedQuestions.filter((question) =>
        form.answers.some(
            (answer) =>
                answer.question_id === question.id && answer.answer.trim(),
        ),
    ).length;
});
const progressTotal = computed(
    () => requiredQuestions.value.length || props.interview.questions.length,
);
const progressPercent = computed(() =>
    progressTotal.value
        ? Math.round((answeredCount.value / progressTotal.value) * 100)
        : 0,
);
const errorMessages = computed(() =>
    Array.from(
        new Set(
            Object.values(form.errors).flatMap((message) =>
                Array.isArray(message) ? message : message ? [message] : [],
            ),
        ),
    ),
);

watch(
    () => props.interview.questions_version,
    (version, previousVersion) => {
        if (version === previousVersion) return;
        const previousAnswers = new Map(
            form.answers.map((answer) => [answer.question_id, answer.answer]),
        );
        form.answers = props.interview.questions.map((question) => ({
            question_id: question.id,
            answer: previousAnswers.get(question.id) ?? '',
        }));
        form.questions_version = version;
        questionsUpdated.value = true;
    },
);

function answerError(index: number): string {
    if (questionsUpdated.value) return '';
    const message = form.errors[`answers.${index}.answer`];
    return Array.isArray(message) ? message.join(' ') : (message ?? '');
}

function focusErrors(): void {
    void nextTick(() => errorSummary.value?.focus());
}

function submit(): void {
    if (
        form.processing ||
        !props.interview.is_open ||
        !hasStudyPrograms.value ||
        !props.interview.questions.length
    ) {
        return;
    }
    submissionError.value = '';
    questionsUpdated.value = false;
    form.clearErrors();
    form.name = form.name.trim();
    form.registration_number = form.registration_number.trim();
    form.answers = form.answers.map((answer) => ({
        ...answer,
        answer: answer.answer.trim(),
    }));
    if (!form.name) form.setError('name', 'Nama lengkap wajib diisi.');
    if (!form.registration_number) {
        form.setError('registration_number', 'Nomor registrasi wajib diisi.');
    }
    if (
        !props.studyPrograms.some(
            (program) => String(program.id) === form.study_program_id,
        )
    ) {
        form.setError('study_program_id', 'Pilih program studi yang diambil.');
    }
    props.interview.questions.forEach((question, index) => {
        const answer = form.answers[index]?.answer ?? '';
        if (question.required && !answer) {
            form.setError(
                `answers.${index}.answer`,
                `Jawaban pertanyaan ${index + 1} wajib diisi.`,
            );
        } else if (answer.length > 5000) {
            form.setError(
                `answers.${index}.answer`,
                `Jawaban pertanyaan ${index + 1} maksimal 5.000 karakter.`,
            );
        }
    });
    if (form.hasErrors) {
        focusErrors();
        return;
    }
    form.post(submitInterview.url({ token: props.token }), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.answers = emptyAnswers();
            form.questions_version = props.interview.questions_version;
            form.clearErrors();
            questionsUpdated.value = false;
            receiptVisible.value = true;
            void nextTick(() => receipt.value?.focus());
        },
        onError: focusErrors,
        onNetworkError: () => {
            submissionError.value =
                'Koneksi terputus. Isian Anda masih tersedia di halaman ini. Periksa koneksi lalu kirim kembali.';
            focusErrors();
            return false;
        },
        onHttpException: (response) => {
            submissionError.value =
                response.status === 429
                    ? 'Terlalu banyak pengiriman. Tunggu satu menit lalu coba kembali.'
                    : response.status === 419
                      ? 'Sesi halaman telah berakhir. Simpan sementara jawaban Anda sebelum memuat ulang halaman, lalu isi dan kirim kembali.'
                      : response.status === 404
                        ? 'Form wawancara sudah tidak tersedia. Hubungi panitia PMB untuk mendapatkan tautan yang sesuai.'
                        : 'Jawaban belum dapat disimpan. Isian Anda masih tersedia di halaman ini; coba kirim kembali atau hubungi panitia.';
            focusErrors();
            return false;
        },
    });
}

function fillAnotherParticipant(): void {
    form.reset();
    form.answers = emptyAnswers();
    form.questions_version = props.interview.questions_version;
    form.submission_token = newSubmissionToken();
    form.defaults({
        answers: emptyAnswers(),
        questions_version: props.interview.questions_version,
        submission_token: form.submission_token,
    });
    form.clearErrors();
    submissionError.value = '';
    questionsUpdated.value = false;
    receiptVisible.value = false;
    void nextTick(() => nameInput.value?.focus());
}
</script>

<template>
    <div
        lang="id"
        translate="no"
        class="notranslate sisurat-workspace min-h-dvh"
    >
        <Head :title="`Tes Wawancara PMB · ${interview.title}`" />
        <header
            class="mx-auto flex max-w-3xl items-center gap-3 px-5 py-5 sm:px-6"
        >
            <span
                class="rounded-xl border border-border bg-white p-2 shadow-sm"
            >
                <AppLogoIcon class="size-11" />
            </span>
            <div class="grid gap-1">
                <p class="text-sm font-semibold tracking-wide">SISURAT PMB</p>
                <p class="text-xs text-muted-foreground">
                    Universitas Nurul Huda
                </p>
            </div>
        </header>

        <main class="mx-auto grid max-w-3xl gap-5 px-5 pb-8 sm:px-6">
            <section class="sisurat-gradient grid gap-4 rounded-2xl p-6 sm:p-8">
                <div
                    class="flex items-center gap-2 text-xs font-medium text-white/85"
                >
                    <ClipboardList class="size-4" aria-hidden="true" />
                    TES WAWANCARA MAHASISWA BARU
                </div>
                <h1
                    class="text-xl leading-relaxed font-semibold break-words sm:text-2xl"
                >
                    {{ interview.title }}
                </h1>
                <p
                    v-if="interview.description"
                    class="text-sm leading-7 break-words whitespace-pre-line text-white/90"
                >
                    {{ interview.description }}
                </p>
            </section>

            <section
                v-if="receiptVisible"
                ref="receipt"
                tabindex="-1"
                aria-labelledby="receipt-title"
                class="sisurat-panel grid justify-items-center gap-4 p-6 text-center outline-none sm:p-8"
            >
                <span
                    class="flex size-16 items-center justify-center rounded-full bg-secondary text-primary"
                >
                    <CheckCircle2 class="size-8" aria-hidden="true" />
                </span>
                <h2 id="receipt-title" class="text-xl font-semibold">
                    Jawaban berhasil dikirim
                </h2>
                <p class="max-w-md text-sm leading-7 text-muted-foreground">
                    Terima kasih telah mengisi tes wawancara. Identitas dan
                    jawaban Anda telah tersimpan untuk ditinjau oleh panitia
                    PMB.
                </p>
                <button
                    v-if="interview.is_open"
                    type="button"
                    class="sisurat-secondary-action mt-2 w-full sm:w-auto"
                    @click="fillAnotherParticipant"
                >
                    Isi untuk peserta berikutnya
                </button>
            </section>

            <section
                v-else-if="!interview.is_open"
                class="sisurat-panel grid gap-3 p-6 sm:p-8"
            >
                <h2 class="text-lg font-semibold">
                    Pengisian wawancara sudah ditutup
                </h2>
                <p class="text-sm leading-7 text-muted-foreground">
                    Form ini sudah tidak menerima jawaban. Hubungi panitia PMB
                    jika Anda memerlukan bantuan.
                </p>
            </section>

            <section
                v-else-if="!interview.questions.length"
                class="sisurat-panel grid gap-3 p-6 sm:p-8"
            >
                <h2 class="text-lg font-semibold">
                    Pertanyaan sedang disiapkan
                </h2>
                <p class="text-sm leading-7 text-muted-foreground">
                    Form wawancara belum siap diisi. Hubungi panitia PMB untuk
                    informasi lebih lanjut.
                </p>
            </section>

            <form v-else class="grid gap-5" @submit.prevent="submit">
                <p
                    v-if="questionsUpdated"
                    role="status"
                    class="rounded-xl border border-border bg-secondary p-5 text-sm leading-7 text-secondary-foreground"
                >
                    Panitia memperbarui pertanyaan. Jawaban pada pertanyaan yang
                    masih tersedia tetap dipertahankan. Periksa kembali seluruh
                    pertanyaan dan isian Anda sebelum mengirim ulang.
                </p>

                <div
                    v-if="submissionError || errorMessages.length"
                    ref="errorSummary"
                    role="alert"
                    tabindex="-1"
                    class="grid gap-2 rounded-xl border border-destructive/30 bg-destructive/5 p-5 text-sm text-destructive outline-none focus-visible:ring-2 focus-visible:ring-destructive/30"
                >
                    <p class="font-semibold">Jawaban belum tersimpan</p>
                    <p v-if="submissionError" class="leading-6">
                        {{ submissionError }}
                    </p>
                    <ul
                        v-if="errorMessages.length"
                        class="list-disc space-y-1 pl-5"
                    >
                        <li v-for="message in errorMessages" :key="message">
                            {{ message }}
                        </li>
                    </ul>
                </div>

                <fieldset
                    :disabled="form.processing"
                    class="sisurat-panel grid min-w-0 gap-5 p-6 sm:p-8"
                >
                    <legend class="sr-only">Identitas mahasiswa baru</legend>
                    <div class="grid gap-2">
                        <h2 class="text-lg font-semibold">Identitas peserta</h2>
                        <p class="text-sm leading-7 text-muted-foreground">
                            Gunakan identitas sesuai pendaftaran PMB. Semua
                            kolom identitas wajib diisi.
                        </p>
                    </div>
                    <div
                        class="rounded-xl border border-border bg-muted/20 p-4"
                    >
                        <h3 class="mb-4 text-sm font-semibold">
                            Pelaksanaan tes Anda
                        </h3>
                        <PmbTestSessionFields
                            id-prefix="interview-public"
                            required
                            v-model:wave="form.wave"
                            v-model:period="form.period"
                            v-model:academic-year="form.academic_year"
                            v-model:campus="form.campus"
                            :errors="form.errors"
                        />
                        <p class="mt-3 text-xs leading-6 text-muted-foreground">
                            Pilih gelombang serta kampus dan ketik periode serta
                            tahun akademik sesuai pendaftaran Anda.
                        </p>
                    </div>
                    <div class="grid gap-2">
                        <label for="interview-name" class="text-sm font-medium"
                            >Nama lengkap</label
                        >
                        <input
                            id="interview-name"
                            ref="nameInput"
                            v-model="form.name"
                            name="name"
                            type="text"
                            autocomplete="name"
                            maxlength="150"
                            required
                            placeholder="Nama sesuai pendaftaran"
                            :aria-invalid="Boolean(form.errors.name)"
                            :aria-describedby="
                                form.errors.name
                                    ? 'interview-name-error'
                                    : undefined
                            "
                            class="sisurat-input"
                        />
                        <p
                            v-if="form.errors.name"
                            id="interview-name-error"
                            class="text-sm text-destructive"
                        >
                            {{ form.errors.name }}
                        </p>
                    </div>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="grid content-start gap-2">
                            <label
                                for="interview-registration"
                                class="text-sm font-medium"
                                >Nomor registrasi</label
                            >
                            <input
                                id="interview-registration"
                                v-model="form.registration_number"
                                name="registration_number"
                                type="text"
                                autocomplete="off"
                                maxlength="100"
                                required
                                placeholder="Nomor pendaftaran PMB"
                                :aria-invalid="
                                    Boolean(form.errors.registration_number)
                                "
                                :aria-describedby="
                                    form.errors.registration_number
                                        ? 'interview-registration-error'
                                        : undefined
                                "
                                class="sisurat-input"
                            />
                            <p
                                v-if="form.errors.registration_number"
                                id="interview-registration-error"
                                class="text-sm text-destructive"
                            >
                                {{ form.errors.registration_number }}
                            </p>
                        </div>
                        <div class="grid content-start gap-2">
                            <label
                                for="interview-study-program"
                                class="text-sm font-medium"
                                >Program studi yang diambil</label
                            >
                            <select
                                id="interview-study-program"
                                v-model="form.study_program_id"
                                name="study_program_id"
                                required
                                :disabled="!hasStudyPrograms"
                                :aria-invalid="
                                    Boolean(form.errors.study_program_id)
                                "
                                :aria-describedby="
                                    !hasStudyPrograms
                                        ? 'study-program-unavailable'
                                        : form.errors.study_program_id
                                          ? 'interview-study-program-error'
                                          : undefined
                                "
                                class="sisurat-input"
                            >
                                <option value="" disabled>
                                    Pilih program studi
                                </option>
                                <option
                                    v-for="program in studyPrograms"
                                    :key="program.id"
                                    :value="String(program.id)"
                                >
                                    {{ program.name }}
                                </option>
                            </select>
                            <p
                                v-if="!hasStudyPrograms"
                                id="study-program-unavailable"
                                role="status"
                                class="text-sm leading-7 text-muted-foreground"
                            >
                                Pilihan program studi belum tersedia. Hubungi
                                panitia, lalu muat ulang halaman setelah pilihan
                                disiapkan.
                            </p>
                            <p
                                v-if="form.errors.study_program_id"
                                id="interview-study-program-error"
                                class="text-sm text-destructive"
                            >
                                {{ form.errors.study_program_id }}
                            </p>
                        </div>
                    </div>
                </fieldset>

                <fieldset
                    :disabled="form.processing"
                    class="sisurat-panel grid min-w-0 gap-6 p-6 sm:p-8"
                >
                    <legend class="sr-only">Jawaban tes wawancara</legend>
                    <div class="grid gap-3 border-b border-border pb-5">
                        <h2 class="text-lg font-semibold">
                            Pertanyaan wawancara
                        </h2>
                        <p class="text-sm leading-7 text-muted-foreground">
                            Ketik jawaban Anda dengan jelas. Pertanyaan bertanda
                            Wajib harus dijawab. Jawaban dikirim setelah Anda
                            menekan tombol Kirim jawaban.
                        </p>
                        <div class="grid gap-2">
                            <p
                                id="interview-progress-label"
                                class="text-xs font-medium text-muted-foreground"
                            >
                                {{ answeredCount }} dari {{ progressTotal }}
                                {{
                                    requiredQuestions.length
                                        ? 'jawaban wajib terisi'
                                        : 'pertanyaan dijawab'
                                }}
                            </p>
                            <div
                                role="progressbar"
                                :aria-valuenow="answeredCount"
                                :aria-valuemin="0"
                                :aria-valuemax="progressTotal"
                                aria-labelledby="interview-progress-label"
                                class="h-1.5 overflow-hidden rounded-full bg-secondary"
                            >
                                <div
                                    class="h-full rounded-full bg-primary transition-[width] duration-300 motion-reduce:transition-none"
                                    :style="{ width: `${progressPercent}%` }"
                                />
                            </div>
                        </div>
                    </div>
                    <div
                        v-for="(question, index) in interview.questions"
                        :key="question.id"
                        class="grid min-w-0 gap-3"
                    >
                        <div class="flex items-start gap-3">
                            <span
                                class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-secondary text-xs font-semibold text-primary"
                                aria-hidden="true"
                                >{{ index + 1 }}</span
                            >
                            <div class="grid min-w-0 flex-1 gap-2">
                                <label
                                    :for="`question-${question.id}`"
                                    class="text-sm leading-7 font-medium break-words whitespace-pre-line"
                                    >{{ question.prompt }}</label
                                >
                                <span
                                    class="w-fit rounded-full bg-secondary px-2.5 py-1 text-[11px] font-medium text-secondary-foreground"
                                    >{{
                                        question.required ? 'Wajib' : 'Opsional'
                                    }}</span
                                >
                            </div>
                        </div>
                        <textarea
                            :id="`question-${question.id}`"
                            v-model="form.answers[index].answer"
                            :name="`answers[${index}][answer]`"
                            rows="5"
                            maxlength="5000"
                            :required="question.required"
                            placeholder="Ketik jawaban Anda di sini..."
                            :aria-invalid="Boolean(answerError(index))"
                            :aria-describedby="`question-count-${question.id}${answerError(index) ? ` question-error-${question.id}` : ''}`"
                            class="sisurat-input min-h-32 resize-y leading-7"
                        />
                        <div
                            class="flex flex-wrap items-start justify-between gap-2"
                        >
                            <p
                                v-if="answerError(index)"
                                :id="`question-error-${question.id}`"
                                class="text-sm text-destructive"
                            >
                                {{ answerError(index) }}
                            </p>
                            <p
                                :id="`question-count-${question.id}`"
                                class="ml-auto text-xs text-muted-foreground tabular-nums"
                            >
                                {{
                                    form.answers[
                                        index
                                    ].answer.length.toLocaleString('id-ID')
                                }}
                                / 5.000 karakter
                            </p>
                        </div>
                    </div>
                </fieldset>

                <section class="sisurat-panel grid gap-3 p-6 sm:p-8">
                    <button
                        type="submit"
                        :disabled="form.processing || !hasStudyPrograms"
                        :aria-busy="form.processing"
                        class="sisurat-primary-action w-full"
                    >
                        <LoaderCircle
                            v-if="form.processing"
                            class="size-4 animate-spin"
                            aria-hidden="true"
                        />
                        <Send v-else class="size-4" aria-hidden="true" />
                        {{
                            form.processing
                                ? 'Menyimpan jawaban...'
                                : 'Kirim jawaban'
                        }}
                    </button>
                    <p
                        class="text-center text-xs leading-6 text-muted-foreground"
                    >
                        Identitas dan jawaban digunakan oleh panitia untuk
                        proses wawancara PMB. Periksa kembali isian sebelum
                        mengirim.
                    </p>
                </section>
            </form>

            <footer class="text-center text-xs leading-6 text-muted-foreground">
                Sekretariat PMB · Universitas Nurul Huda
            </footer>
        </main>
    </div>
</template>
