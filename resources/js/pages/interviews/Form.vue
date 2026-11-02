<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowLeft,
    ArrowUp,
    Info,
    Plus,
    Save,
    Trash2,
} from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import {
    newInterviewQuestion,
    type InterviewForm,
    type InterviewQuestion,
} from '@/lib/interviews';
import { edit, index, show, store, update } from '@/routes/interviews';

const props = defineProps<{
    interview: InterviewForm | null;
    defaultQuestions?: InterviewQuestion[];
}>();
function formDefaults() {
    const questions = props.interview?.questions ??
        props.defaultQuestions ?? [
            newInterviewQuestion(
                'Apa alasan Anda memilih Universitas Nurul Huda?',
            ),
            newInterviewQuestion(
                'Mengapa Anda memilih program studi tersebut?',
            ),
            newInterviewQuestion(
                'Ceritakan pengalaman belajar atau kegiatan yang paling berkesan bagi Anda.',
            ),
            newInterviewQuestion(
                'Bagaimana rencana Anda menjalani perkuliahan?',
            ),
        ];
    return {
        title: props.interview?.title ?? '',
        description: props.interview?.description ?? '',
        is_open: props.interview?.is_open ?? true,
        questions: questions.map((question) => ({ ...question })),
        questions_version: props.interview?.questions_version ?? null,
    };
}
const form = useForm(formDefaults());
const requestError = ref('');
const questionNotice = ref('');
const pageTitle = computed(() =>
    props.interview ? 'Ubah form wawancara' : 'Buat form wawancara',
);
const backLink = computed(() =>
    props.interview ? show(props.interview.id) : index(),
);
const requiredCount = computed(
    () => form.questions.filter((question) => question.required).length,
);
watch(
    () => props.interview?.id ?? null,
    () => {
        form.defaults(formDefaults());
        form.reset();
        form.clearErrors();
        requestError.value = '';
    },
);
function fieldError(path: string): string | undefined {
    return (form.errors as Record<string, string | undefined>)[path];
}
function focusQuestion(id: string): void {
    void nextTick(() =>
        document.getElementById(`interview-question-${id}`)?.focus(),
    );
}
function addQuestion(): void {
    if (form.processing || form.questions.length >= 30) return;
    const question = newInterviewQuestion();
    form.questions.push(question);
    questionNotice.value = `Pertanyaan ${form.questions.length} ditambahkan.`;
    focusQuestion(question.id);
}
function removeQuestion(questionIndex: number): void {
    if (form.processing || form.questions.length <= 1) return;
    form.questions.splice(questionIndex, 1);
    form.clearErrors();
    questionNotice.value = 'Pertanyaan dihapus dari susunan formulir.';
    focusQuestion(
        form.questions[Math.min(questionIndex, form.questions.length - 1)].id,
    );
}
function moveQuestion(questionIndex: number, direction: -1 | 1): void {
    const targetIndex = questionIndex + direction;
    if (
        form.processing ||
        targetIndex < 0 ||
        targetIndex >= form.questions.length
    )
        return;
    const [question] = form.questions.splice(questionIndex, 1);
    form.questions.splice(targetIndex, 0, question);
    form.clearErrors();
    questionNotice.value = `Pertanyaan dipindahkan ke urutan ${targetIndex + 1}.`;
    focusQuestion(question.id);
}
function submit(): void {
    if (form.processing) return;
    requestError.value = '';
    form.transform((data) => ({
        ...data,
        title: data.title.trim(),
        description: data.description.trim(),
        questions: data.questions.map((question) => ({
            ...question,
            prompt: question.prompt.trim(),
        })),
    }));
    const options = {
        preserveScroll: true,
        onError: () => {
            void nextTick(() => {
                const key = Object.keys(form.errors)[0];
                const questionMatch = key?.match(/^questions\.(\d+)\./);
                const question = questionMatch
                    ? form.questions[Number(questionMatch[1])]
                    : null;
                document
                    .getElementById(
                        question
                            ? `interview-question-${question.id}`
                            : `interview-${key}`,
                    )
                    ?.focus();
            });
        },
        onNetworkError: () => {
            requestError.value =
                'Koneksi terputus. Isian Anda tetap tersedia di halaman ini. Periksa koneksi lalu simpan kembali.';
            return false;
        },
        onHttpException: () => {
            requestError.value =
                'Formulir belum dapat disimpan. Periksa koneksi atau coba kembali.';
            return false;
        },
    };
    if (props.interview) form.put(update.url(props.interview.id), options);
    else form.post(store.url(), options);
}
</script>

<template>
    <main class="sisurat-page">
        <Head :title="pageTitle" />
        <div class="mx-auto flex w-full max-w-4xl flex-col gap-6">
            <Link
                :href="backLink"
                class="sisurat-secondary-action self-start"
                :class="{ 'pointer-events-none opacity-50': form.processing }"
                :aria-disabled="form.processing"
                @click="form.processing && $event.preventDefault()"
                ><ArrowLeft class="size-4" aria-hidden="true" />{{
                    interview
                        ? 'Kembali ke form wawancara'
                        : 'Daftar form wawancara'
                }}</Link
            >
            <header>
                <p class="sisurat-kicker">Tes Wawancara · Mahasiswa Baru</p>
                <h1 class="sisurat-title mt-2">{{ pageTitle }}</h1>
                <p class="sisurat-subtitle mt-2">
                    Mahasiswa mengisi identitas dan mengetik jawaban melalui
                    tautan yang Anda bagikan.
                </p>
            </header>
            <form class="space-y-6" @submit.prevent="submit">
                <fieldset :disabled="form.processing" class="min-w-0 space-y-6">
                    <section
                        class="sisurat-panel space-y-6 p-5 sm:p-7"
                        aria-labelledby="interview-info-heading"
                    >
                        <header>
                            <h2
                                id="interview-info-heading"
                                class="text-base font-semibold"
                            >
                                Informasi formulir
                            </h2>
                            <p
                                class="mt-2 text-xs leading-6 text-muted-foreground"
                            >
                                Tanda
                                <span class="text-destructive">*</span>
                                menunjukkan kolom wajib.
                            </p>
                        </header>
                        <div>
                            <label
                                for="interview-title"
                                class="mb-2 block text-sm font-medium"
                                >Judul wawancara
                                <span class="text-destructive">*</span></label
                            ><input
                                id="interview-title"
                                v-model="form.title"
                                type="text"
                                class="sisurat-input"
                                maxlength="200"
                                required
                                placeholder="Contoh: Wawancara PMB Gelombang 1 Tahun 2027"
                                :aria-invalid="Boolean(form.errors.title)"
                                aria-describedby="interview-title-error"
                            /><InputError
                                id="interview-title-error"
                                class="mt-2"
                                :message="form.errors.title"
                            />
                        </div>
                        <p
                            class="rounded-xl border border-border bg-muted/20 p-4 text-xs leading-6 text-muted-foreground"
                        >
                            Gelombang, periode, tahun akademik, dan kampus diisi
                            oleh peserta pada formulir wawancara.
                        </p>
                        <div>
                            <label
                                for="interview-description"
                                class="mb-2 block text-sm font-medium"
                                >Petunjuk untuk mahasiswa
                                <span
                                    class="text-xs font-normal text-muted-foreground"
                                    >(opsional)</span
                                ></label
                            ><textarea
                                id="interview-description"
                                v-model="form.description"
                                rows="4"
                                maxlength="2000"
                                class="sisurat-input resize-y"
                                placeholder="Tuliskan petunjuk pengisian atau informasi yang perlu diketahui peserta."
                                :aria-invalid="Boolean(form.errors.description)"
                                aria-describedby="interview-description-error"
                            /><InputError
                                id="interview-description-error"
                                class="mt-2"
                                :message="form.errors.description"
                            />
                        </div>
                        <div
                            class="rounded-xl border border-border bg-muted/30 p-4"
                        >
                            <label
                                for="interview-is_open"
                                class="flex cursor-pointer items-start gap-3"
                                ><input
                                    id="interview-is_open"
                                    v-model="form.is_open"
                                    type="checkbox"
                                    class="mt-1 size-4 shrink-0 accent-[#005a36]"
                                    :aria-invalid="Boolean(form.errors.is_open)"
                                    aria-describedby="interview-is_open-hint interview-is_open-error"
                                /><span
                                    ><span class="block text-sm font-medium"
                                        >Buka pengisian wawancara</span
                                    ><span
                                        id="interview-is_open-hint"
                                        class="mt-1 block text-xs leading-6 text-muted-foreground"
                                        >{{
                                            form.is_open
                                                ? 'Mahasiswa dapat mengirim jawaban melalui tautan.'
                                                : 'Pengiriman jawaban ditutup. Hasil yang sudah masuk tetap dapat Anda lihat.'
                                        }}</span
                                    ></span
                                ></label
                            ><InputError
                                id="interview-is_open-error"
                                class="mt-2"
                                :message="form.errors.is_open"
                            />
                        </div>
                        <div
                            class="flex gap-3 rounded-xl bg-primary/5 p-4 text-xs leading-6 text-muted-foreground"
                        >
                            <Info
                                class="mt-1 size-4 shrink-0 text-primary"
                                aria-hidden="true"
                            />
                            <p>
                                Kolom identitas disediakan otomatis: nama, nomor
                                registrasi, dan program studi dari Master Data.
                                Setiap nomor registrasi dapat mengirim satu
                                jawaban pada formulir ini.
                            </p>
                        </div>
                    </section>

                    <section
                        class="sisurat-panel p-5 sm:p-7"
                        aria-labelledby="interview-questions-heading"
                    >
                        <header
                            class="flex flex-wrap items-start justify-between gap-3"
                        >
                            <div>
                                <h2
                                    id="interview-questions-heading"
                                    class="text-base font-semibold"
                                >
                                    Daftar pertanyaan
                                </h2>
                                <p
                                    class="mt-2 text-xs leading-6 text-muted-foreground"
                                >
                                    {{ form.questions.length }} dari maksimal 30
                                    pertanyaan · {{ requiredCount }} wajib
                                    dijawab
                                </p>
                            </div>
                            <span
                                class="rounded-lg bg-muted px-3 py-2 text-xs text-muted-foreground"
                                >Jawaban berupa teks</span
                            >
                        </header>
                        <p
                            v-if="interview && interview.submissions_count > 0"
                            class="mt-4 rounded-xl border border-amber-500/25 bg-amber-500/10 px-4 py-3 text-xs leading-6 text-amber-800 dark:text-amber-200"
                        >
                            Sudah ada {{ interview.submissions_count }} jawaban.
                            Perubahan pertanyaan berlaku untuk pengisian
                            berikutnya. Pertanyaan dan jawaban yang telah
                            dikirim tetap tersimpan sesuai versi saat diisi.
                        </p>
                        <InputError
                            id="interview-questions-error"
                            class="mt-4"
                            :message="form.errors.questions"
                        />
                        <div id="interview-questions_version" tabindex="-1">
                            <InputError
                                class="mt-4"
                                :message="form.errors.questions_version"
                            />
                            <div
                                v-if="
                                    form.errors.questions_version && interview
                                "
                                class="mt-3 space-y-2"
                            >
                                <p
                                    class="text-xs leading-6 text-muted-foreground"
                                >
                                    Muat ulang untuk mengambil versi terbaru.
                                    Perubahan yang belum Anda simpan di halaman
                                    ini akan diganti.
                                </p>
                                <Link
                                    :href="edit(interview.id)"
                                    :preserve-state="false"
                                    class="sisurat-secondary-action"
                                    :class="{
                                        'pointer-events-none opacity-50':
                                            form.processing,
                                    }"
                                    :aria-disabled="form.processing"
                                    @click="
                                        form.processing &&
                                        $event.preventDefault()
                                    "
                                    >Muat ulang form</Link
                                >
                            </div>
                        </div>
                        <div class="mt-5 space-y-4">
                            <article
                                v-for="(
                                    question, questionIndex
                                ) in form.questions"
                                :key="question.id"
                                class="rounded-xl border border-border bg-background p-4 sm:p-5"
                            >
                                <header
                                    class="mb-3 flex flex-wrap items-center justify-between gap-3"
                                >
                                    <label
                                        :for="`interview-question-${question.id}`"
                                        class="text-sm font-semibold"
                                        >Pertanyaan {{ questionIndex + 1 }}
                                        <span class="text-destructive"
                                            >*</span
                                        ></label
                                    >
                                    <div class="flex items-center gap-1">
                                        <button
                                            type="button"
                                            class="sisurat-secondary-action min-h-10 px-2.5"
                                            :disabled="questionIndex === 0"
                                            :aria-label="`Pindahkan pertanyaan ${questionIndex + 1} ke atas`"
                                            @click="
                                                moveQuestion(questionIndex, -1)
                                            "
                                        >
                                            <ArrowUp
                                                class="size-4"
                                                aria-hidden="true"
                                            /></button
                                        ><button
                                            type="button"
                                            class="sisurat-secondary-action min-h-10 px-2.5"
                                            :disabled="
                                                questionIndex ===
                                                form.questions.length - 1
                                            "
                                            :aria-label="`Pindahkan pertanyaan ${questionIndex + 1} ke bawah`"
                                            @click="
                                                moveQuestion(questionIndex, 1)
                                            "
                                        >
                                            <ArrowDown
                                                class="size-4"
                                                aria-hidden="true"
                                            /></button
                                        ><button
                                            type="button"
                                            class="sisurat-secondary-action min-h-10 border-destructive/25 px-2.5 text-destructive hover:bg-destructive/10"
                                            :disabled="
                                                form.questions.length <= 1
                                            "
                                            :aria-label="`Hapus pertanyaan ${questionIndex + 1}`"
                                            @click="
                                                removeQuestion(questionIndex)
                                            "
                                        >
                                            <Trash2
                                                class="size-4"
                                                aria-hidden="true"
                                            />
                                        </button>
                                    </div>
                                </header>
                                <textarea
                                    :id="`interview-question-${question.id}`"
                                    v-model="question.prompt"
                                    rows="3"
                                    maxlength="1000"
                                    required
                                    class="sisurat-input resize-y"
                                    placeholder="Tuliskan pertanyaan wawancara di sini…"
                                    :aria-invalid="
                                        Boolean(
                                            fieldError(
                                                `questions.${questionIndex}.prompt`,
                                            ),
                                        )
                                    "
                                    :aria-describedby="`interview-question-${question.id}-error`"
                                />
                                <InputError
                                    :id="`interview-question-${question.id}-error`"
                                    class="mt-2"
                                    :message="
                                        fieldError(
                                            `questions.${questionIndex}.prompt`,
                                        ) ||
                                        fieldError(
                                            `questions.${questionIndex}.id`,
                                        ) ||
                                        fieldError(
                                            `questions.${questionIndex}.required`,
                                        )
                                    "
                                />
                                <div
                                    class="mt-3 flex flex-wrap items-center justify-between gap-3"
                                >
                                    <label
                                        :for="`interview-required-${question.id}`"
                                        class="flex cursor-pointer items-center gap-2 text-xs"
                                        ><input
                                            :id="`interview-required-${question.id}`"
                                            v-model="question.required"
                                            type="checkbox"
                                            class="size-4 accent-[#005a36]"
                                        />Wajib dijawab</label
                                    ><span
                                        class="text-[11px] text-muted-foreground"
                                        >{{ question.prompt.length }} / 1.000
                                        karakter</span
                                    >
                                </div>
                            </article>
                        </div>
                        <button
                            type="button"
                            class="sisurat-secondary-action mt-5"
                            :disabled="form.questions.length >= 30"
                            @click="addQuestion"
                        >
                            <Plus class="size-4" aria-hidden="true" />Tambah
                            pertanyaan
                        </button>
                        <p
                            v-if="form.questions.length >= 30"
                            class="mt-2 text-xs text-muted-foreground"
                        >
                            Batas 30 pertanyaan telah tercapai.
                        </p>
                        <p class="sr-only" role="status" aria-live="polite">
                            {{ questionNotice }}
                        </p>
                    </section>
                    <p
                        v-if="requestError"
                        class="rounded-xl bg-destructive/10 px-4 py-3 text-sm text-destructive"
                        role="alert"
                    >
                        {{ requestError }}
                    </p>
                    <footer
                        class="flex flex-wrap items-center justify-between gap-4"
                    >
                        <p class="text-xs leading-6 text-muted-foreground">
                            {{
                                interview
                                    ? 'Tautan pengisian tetap sama setelah perubahan disimpan.'
                                    : 'Tautan pengisian tersedia setelah formulir disimpan.'
                            }}
                        </p>
                        <button
                            type="submit"
                            class="sisurat-primary-action"
                            :aria-busy="form.processing"
                        >
                            <Save class="size-4" aria-hidden="true" />{{
                                form.processing
                                    ? 'Menyimpan…'
                                    : 'Simpan form wawancara'
                            }}
                        </button>
                    </footer>
                </fieldset>
            </form>
        </div>
    </main>
</template>
