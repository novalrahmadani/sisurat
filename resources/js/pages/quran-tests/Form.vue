<script setup lang="ts">
import PmbTestSessionFields from '@/components/PmbTestSessionFields.vue';
import type { WaveInput } from '@/lib/pmbTestSession';
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BookOpen,
    CalendarDays,
    ClipboardCheck,
    Save,
    UserRound,
} from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import {
    newQuranSubmissionToken,
    quranAverage,
    quranPlacement,
    quranResultClass,
    quranResultLabel,
    quranScore,
    quranScoreFields,
    type QuranAssessment,
} from '@/lib/quranAssessments';
import { edit, index, show, store, update } from '@/routes/quran-tests';

type ScoreInput = number | '';
const props = defineProps<{
    assessment: QuranAssessment | null;
    studyPrograms: Array<{ id: number; name: string }>;
    today: string;
    defaultExaminer: string;
}>();
function formDefaults() {
    return {
        wave: (props.assessment?.wave ?? '') as WaveInput,
        period: props.assessment?.period ?? '',
        academic_year: props.assessment?.academic_year ?? '',
        campus: props.assessment?.campus ?? '',
        name: props.assessment?.name ?? '',
        registration_number: props.assessment?.registration_number ?? '',
        study_program_choice: props.assessment ? 'saved' : '',
        assessment_date: props.assessment?.assessment_date ?? props.today,
        examiner_name:
            props.assessment?.examiner_name ??
            (props.assessment ? '' : props.defaultExaminer),
        reading_reference: props.assessment?.reading_reference ?? '',
        fasohah_score: (props.assessment?.fasohah_score ?? '') as ScoreInput,
        makhraj_score: (props.assessment?.makhraj_score ?? '') as ScoreInput,
        tajwid_score: (props.assessment?.tajwid_score ?? '') as ScoreInput,
        fluency_score: (props.assessment?.fluency_score ?? '') as ScoreInput,
        notes: props.assessment?.notes ?? '',
        version: props.assessment?.version ?? null,
        submission_token: props.assessment ? null : newQuranSubmissionToken(),
    };
}
const form = useForm(formDefaults());
const requestError = ref('');
const errorSummary = ref<HTMLElement | null>(null);
const pageTitle = computed(() =>
    props.assessment ? 'Ubah penilaian' : 'Tambah penilaian',
);
const backLink = computed(() =>
    props.assessment ? show({ quran_test: props.assessment.id }) : index(),
);
const average = computed(() =>
    quranAverage([
        form.fasohah_score,
        form.tajwid_score,
        form.makhraj_score,
        form.fluency_score,
    ]),
);
const completed = computed(() => average.value !== null);
const placement = computed(() => quranPlacement(average.value));
const cannotCreate = computed(
    () => !props.assessment && !props.studyPrograms.length,
);
const validationErrors = computed(() => [
    ...new Set(Object.values(form.errors).filter(Boolean)),
]);
watch(
    () => props.assessment?.id ?? null,
    () => {
        form.defaults(formDefaults());
        form.reset();
        form.clearErrors();
        requestError.value = '';
    },
);
function fieldError(key: string): string | undefined {
    return (form.errors as Record<string, string | undefined>)[key];
}
function submit(): void {
    if (form.processing || cannotCreate.value) return;
    requestError.value = '';
    form.transform(({ study_program_choice, submission_token, ...data }) => ({
        ...data,
        name: data.name.trim(),
        registration_number: data.registration_number.trim(),
        examiner_name: data.examiner_name.trim() || null,
        reading_reference: data.reading_reference.trim() || null,
        notes: data.notes.trim() || null,
        fasohah_score: data.fasohah_score === '' ? null : data.fasohah_score,
        makhraj_score: data.makhraj_score === '' ? null : data.makhraj_score,
        tajwid_score: data.tajwid_score === '' ? null : data.tajwid_score,
        fluency_score: data.fluency_score === '' ? null : data.fluency_score,
        study_program_id:
            study_program_choice && study_program_choice !== 'saved'
                ? Number(study_program_choice)
                : null,
        retain_study_program: Boolean(
            props.assessment && study_program_choice === 'saved',
        ),
        ...(props.assessment ? {} : { submission_token }),
    }));
    const options = {
        preserveScroll: true,
        onError: () => {
            void nextTick(() => errorSummary.value?.focus());
        },
        onNetworkError: () => {
            requestError.value =
                'Koneksi terputus. Isian tetap tersedia di halaman ini. Periksa koneksi lalu simpan kembali.';
            return false;
        },
        onHttpException: () => {
            requestError.value =
                'Penilaian belum dapat disimpan. Isian tetap tersedia di halaman ini. Coba kembali.';
            return false;
        },
    };
    if (props.assessment)
        form.put(update.url({ quran_test: props.assessment.id }), options);
    else form.post(store.url(), options);
}
</script>

<template>
    <main class="sisurat-page">
        <Head :title="`${pageTitle} · Tes Baca Al-Qur’an`" />
        <div class="mx-auto flex w-full max-w-4xl flex-col gap-6">
            <Link
                :href="backLink"
                class="sisurat-secondary-action self-start"
                :class="{ 'pointer-events-none opacity-50': form.processing }"
                :aria-disabled="form.processing"
                @click="form.processing && $event.preventDefault()"
                ><ArrowLeft class="size-4" aria-hidden="true" />{{
                    assessment
                        ? 'Kembali ke detail penilaian'
                        : 'Daftar penilaian'
                }}</Link
            >
            <header>
                <p class="sisurat-kicker">
                    Tes Baca Al-Qur’an · Calon Mahasiswa Baru
                </p>
                <h1 class="sisurat-title mt-2">{{ pageTitle }}</h1>
                <p class="sisurat-subtitle mt-2">
                    Isi identitas peserta dan catat hasil pengamatan penguji
                    saat tes tatap muka.
                </p>
            </header>

            <div
                v-if="validationErrors.length"
                id="quran-errors"
                ref="errorSummary"
                tabindex="-1"
                class="rounded-2xl border border-destructive/30 bg-destructive/5 p-5 focus-visible:outline-2 focus-visible:outline-destructive"
                role="alert"
            >
                <h2 class="text-sm font-semibold text-destructive">
                    Penilaian belum tersimpan
                </h2>
                <ul
                    class="mt-2 list-disc space-y-1 pl-5 text-sm leading-6 text-destructive"
                >
                    <li v-for="message in validationErrors" :key="message">
                        {{ message }}
                    </li>
                </ul>
                <div
                    v-if="form.errors.version && assessment"
                    class="mt-4 space-y-3"
                >
                    <p class="text-xs leading-6 text-muted-foreground">
                        Muat ulang untuk mengambil versi terbaru. Isian yang
                        belum disimpan di halaman ini akan diganti.
                    </p>
                    <Link
                        :href="edit({ quran_test: assessment.id })"
                        :preserve-state="false"
                        class="sisurat-secondary-action"
                        :class="{
                            'pointer-events-none opacity-50': form.processing,
                        }"
                        :aria-disabled="form.processing"
                        @click="form.processing && $event.preventDefault()"
                        >Muat ulang penilaian</Link
                    >
                </div>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <fieldset :disabled="form.processing" class="min-w-0 space-y-6">
                    <section
                        class="sisurat-panel p-5 sm:p-6"
                        aria-labelledby="quran-identity-heading"
                    >
                        <header class="mb-5 flex items-center gap-3">
                            <span
                                class="flex size-9 items-center justify-center rounded-xl bg-primary/10 text-primary"
                                ><UserRound class="size-4" aria-hidden="true"
                            /></span>
                            <div>
                                <h2
                                    id="quran-identity-heading"
                                    class="text-sm font-semibold"
                                >
                                    Identitas peserta
                                </h2>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Pastikan nama dan nomor registrasi sesuai
                                    data pendaftaran.
                                </p>
                            </div>
                        </header>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label
                                    for="quran-name"
                                    class="mb-2 block text-sm font-medium"
                                    >Nama lengkap
                                    <span class="text-destructive"
                                        >*</span
                                    ></label
                                ><input
                                    id="quran-name"
                                    v-model="form.name"
                                    type="text"
                                    maxlength="150"
                                    required
                                    autocomplete="off"
                                    class="sisurat-input"
                                    placeholder="Nama calon mahasiswa"
                                    :aria-invalid="Boolean(form.errors.name)"
                                    aria-describedby="quran-name-error"
                                /><InputError
                                    id="quran-name-error"
                                    class="mt-2"
                                    :message="form.errors.name"
                                />
                            </div>
                            <div>
                                <label
                                    for="quran-registration"
                                    class="mb-2 block text-sm font-medium"
                                    >Nomor registrasi
                                    <span class="text-destructive"
                                        >*</span
                                    ></label
                                ><input
                                    id="quran-registration"
                                    v-model="form.registration_number"
                                    type="text"
                                    maxlength="100"
                                    required
                                    autocomplete="off"
                                    class="sisurat-input"
                                    placeholder="Contoh: 001234"
                                    :aria-invalid="
                                        Boolean(form.errors.registration_number)
                                    "
                                    aria-describedby="quran-registration-error"
                                /><InputError
                                    id="quran-registration-error"
                                    class="mt-2"
                                    :message="form.errors.registration_number"
                                />
                            </div>
                            <div class="sm:col-span-2">
                                <label
                                    for="quran-study-program"
                                    class="mb-2 block text-sm font-medium"
                                    >Program studi
                                    <span class="text-destructive"
                                        >*</span
                                    ></label
                                ><select
                                    id="quran-study-program"
                                    v-model="form.study_program_choice"
                                    required
                                    class="sisurat-input"
                                    :aria-invalid="
                                        Boolean(
                                            fieldError('study_program_id') ||
                                            fieldError('retain_study_program'),
                                        )
                                    "
                                    aria-describedby="quran-study-program-help quran-study-program-error"
                                >
                                    <option disabled value="">
                                        Pilih program studi
                                    </option>
                                    <option v-if="assessment" value="saved">
                                        Prodi tersimpan:
                                        {{ assessment.study_program }}
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
                                    id="quran-study-program-help"
                                    class="mt-2 text-xs leading-6 text-muted-foreground"
                                >
                                    {{
                                        assessment
                                            ? 'Pertahankan prodi tersimpan untuk menjaga data penilaian ini, atau pilih prodi lain dari master data.'
                                            : 'Pilihan program studi mengikuti Master Data.'
                                    }}
                                </p>
                                <InputError
                                    id="quran-study-program-error"
                                    class="mt-2"
                                    :message="
                                        fieldError('study_program_id') ||
                                        fieldError('retain_study_program')
                                    "
                                />
                                <p
                                    v-if="cannotCreate"
                                    class="mt-2 text-xs leading-6 text-destructive"
                                    role="status"
                                >
                                    Belum ada program studi. Tambahkan program
                                    studi pada Master Data sebelum menyimpan
                                    penilaian baru.
                                </p>
                            </div>
                        </div>
                    </section>

                    <section
                        class="sisurat-panel p-5 sm:p-6"
                        aria-labelledby="quran-session-heading"
                    >
                        <header class="mb-5 flex items-center gap-3">
                            <span
                                class="flex size-9 items-center justify-center rounded-xl bg-primary/10 text-primary"
                                ><CalendarDays
                                    class="size-4"
                                    aria-hidden="true"
                            /></span>
                            <div>
                                <h2
                                    id="quran-session-heading"
                                    class="text-sm font-semibold"
                                >
                                    Pelaksanaan tes
                                </h2>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Tanggal tes, penguji, dan bacaan yang
                                    digunakan.
                                </p>
                            </div>
                        </header>
                        <PmbTestSessionFields
                            id-prefix="quran"
                            class="mb-5"
                            v-model:wave="form.wave"
                            v-model:period="form.period"
                            v-model:academic-year="form.academic_year"
                            v-model:campus="form.campus"
                            :errors="form.errors"
                        />
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label
                                    for="quran-assessment-date"
                                    class="mb-2 block text-sm font-medium"
                                    >Tanggal tes
                                    <span class="text-destructive"
                                        >*</span
                                    ></label
                                ><input
                                    id="quran-assessment-date"
                                    v-model="form.assessment_date"
                                    type="date"
                                    required
                                    class="sisurat-input"
                                    :aria-invalid="
                                        Boolean(form.errors.assessment_date)
                                    "
                                    aria-describedby="quran-assessment-date-error"
                                /><InputError
                                    id="quran-assessment-date-error"
                                    class="mt-2"
                                    :message="form.errors.assessment_date"
                                />
                            </div>
                            <div>
                                <label
                                    for="quran-examiner"
                                    class="mb-2 block text-sm font-medium"
                                    >Nama penguji
                                    <span
                                        v-if="completed"
                                        class="text-destructive"
                                        >*</span
                                    ></label
                                ><input
                                    id="quran-examiner"
                                    v-model="form.examiner_name"
                                    type="text"
                                    maxlength="150"
                                    :required="completed"
                                    autocomplete="off"
                                    class="sisurat-input"
                                    placeholder="Nama petugas / penguji"
                                    :aria-invalid="
                                        Boolean(form.errors.examiner_name)
                                    "
                                    aria-describedby="quran-examiner-error"
                                /><InputError
                                    id="quran-examiner-error"
                                    class="mt-2"
                                    :message="form.errors.examiner_name"
                                />
                            </div>
                            <div class="sm:col-span-2">
                                <label
                                    for="quran-reading-reference"
                                    class="mb-2 block text-sm font-medium"
                                    >Surah dan ayat
                                    <span
                                        v-if="completed"
                                        class="text-destructive"
                                        >*</span
                                    ></label
                                ><input
                                    id="quran-reading-reference"
                                    v-model="form.reading_reference"
                                    type="text"
                                    maxlength="255"
                                    :required="completed"
                                    class="sisurat-input"
                                    placeholder="Nama surah dan rentang ayat"
                                    :aria-invalid="
                                        Boolean(form.errors.reading_reference)
                                    "
                                    aria-describedby="quran-reading-reference-error"
                                /><InputError
                                    id="quran-reading-reference-error"
                                    class="mt-2"
                                    :message="form.errors.reading_reference"
                                />
                            </div>
                        </div>
                    </section>

                    <section
                        class="sisurat-panel p-5 sm:p-6"
                        aria-labelledby="quran-scoring-heading"
                    >
                        <header class="mb-5 flex items-center gap-3">
                            <span
                                class="flex size-9 items-center justify-center rounded-xl bg-primary/10 text-primary"
                                ><ClipboardCheck
                                    class="size-4"
                                    aria-hidden="true"
                            /></span>
                            <div>
                                <h2
                                    id="quran-scoring-heading"
                                    class="text-sm font-semibold"
                                >
                                    Penilaian penguji
                                </h2>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Masukkan nilai bulat 1–100 untuk setiap
                                    aspek.
                                </p>
                            </div>
                        </header>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div
                                v-for="field in quranScoreFields"
                                :key="field.key"
                            >
                                <label
                                    :for="`quran-${field.key}`"
                                    class="mb-2 block text-sm font-medium"
                                    >{{ field.label }}
                                    <span
                                        v-if="completed"
                                        class="text-destructive"
                                        >*</span
                                    ></label
                                ><input
                                    :id="`quran-${field.key}`"
                                    v-model.number="form[field.key]"
                                    type="number"
                                    min="1"
                                    max="100"
                                    step="1"
                                    inputmode="numeric"
                                    :required="completed"
                                    class="sisurat-input tabular-nums"
                                    placeholder="1–100"
                                    :aria-invalid="
                                        Boolean(fieldError(field.key))
                                    "
                                    :aria-describedby="`quran-${field.key}-help quran-${field.key}-error`"
                                />
                                <p
                                    :id="`quran-${field.key}-help`"
                                    class="mt-2 text-xs leading-6 text-muted-foreground"
                                >
                                    {{ field.description }}
                                </p>
                                <InputError
                                    :id="`quran-${field.key}-error`"
                                    class="mt-2"
                                    :message="fieldError(field.key)"
                                />
                            </div>
                        </div>
                        <div
                            class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-primary/15 bg-primary/5 px-4 py-3"
                            aria-live="polite"
                        >
                            <div>
                                <p class="text-xs font-medium text-primary">
                                    Rata-rata nilai
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Jumlah empat nilai dibagi 4. Bobot setiap
                                    kriteria sama.
                                </p>
                            </div>
                            <p
                                class="text-2xl font-semibold text-primary tabular-nums"
                            >
                                {{ quranScore(average)
                                }}<span
                                    v-if="average !== null"
                                    class="ml-1 text-xs font-normal text-muted-foreground"
                                    >/ 100</span
                                >
                            </p>
                        </div>
                        <div
                            class="mt-5 rounded-xl border border-border p-4"
                            aria-live="polite"
                        >
                            <p class="text-sm font-semibold">
                                Kategori MQ otomatis
                            </p>
                            <output
                                id="quran-result"
                                :class="quranResultClass(placement)"
                                class="mt-3 inline-flex rounded-full px-3 py-1.5 text-sm font-semibold"
                                >{{ quranResultLabel(placement) }}</output
                            >
                            <p
                                class="mt-3 text-xs leading-6 text-muted-foreground"
                            >
                                Rata-rata minimal 70: berhak mengikuti MQ 2.
                                Rata-rata di bawah 70: masuk MQ 1.
                            </p>
                            <p
                                v-if="average !== null"
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                Total nilai {{ quranScore(average * 4) }}
                                (maksimal 400). Rata-rata:
                                {{ quranScore(average * 4) }} ÷ 4 =
                                {{ quranScore(average) }}.
                            </p>
                            <p
                                class="mt-1 text-xs leading-6 text-muted-foreground"
                            >
                                Lengkapi empat nilai untuk menentukan kategori.
                                Isian yang belum lengkap disimpan dengan status
                                menunggu penilaian.
                            </p>
                            <p
                                v-if="
                                    assessment &&
                                    !['pending', 'mq1', 'mq2'].includes(
                                        assessment.result,
                                    )
                                "
                                class="mt-3 text-xs leading-6 text-amber-800"
                            >
                                Ini penilaian format lama. Nilai sebelumnya
                                tetap ditampilkan. Lengkapi Fasohah dan periksa
                                kembali seluruh nilai untuk menentukan kategori
                                MQ.
                            </p>
                        </div>
                        <div class="mt-5">
                            <label
                                for="quran-notes"
                                class="mb-2 block text-sm font-medium"
                                >Catatan penguji
                                <span
                                    class="text-xs font-normal text-muted-foreground"
                                    >(opsional)</span
                                ></label
                            ><textarea
                                id="quran-notes"
                                v-model="form.notes"
                                maxlength="5000"
                                rows="4"
                                class="sisurat-input resize-y"
                                placeholder="Catatan pengamatan atau saran bimbingan untuk peserta…"
                                :aria-invalid="Boolean(form.errors.notes)"
                                aria-describedby="quran-notes-error"
                            />
                            <div
                                class="mt-2 flex items-start justify-between gap-3"
                            >
                                <InputError
                                    id="quran-notes-error"
                                    :message="form.errors.notes"
                                /><span
                                    class="ml-auto shrink-0 text-[11px] text-muted-foreground"
                                    >{{
                                        form.notes.length.toLocaleString(
                                            'id-ID',
                                        )
                                    }}
                                    / 5.000</span
                                >
                            </div>
                        </div>
                        <p
                            class="mt-5 rounded-xl bg-muted/50 px-4 py-3 text-xs leading-6 text-muted-foreground"
                        >
                            <BookOpen
                                class="mr-1 inline size-3.5"
                                aria-hidden="true"
                            />Nama penguji serta surah dan ayat wajib diisi
                            setelah empat nilai lengkap.
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
                        class="flex flex-wrap items-center justify-between gap-4 pb-3"
                    >
                        <p class="text-xs text-muted-foreground">
                            <span class="text-destructive">*</span> Kolom wajib
                            diisi.
                        </p>
                        <button
                            type="submit"
                            class="sisurat-primary-action"
                            :disabled="form.processing || cannotCreate"
                            :aria-busy="form.processing"
                        >
                            <Save class="size-4" aria-hidden="true" />{{
                                form.processing
                                    ? 'Menyimpan…'
                                    : 'Simpan penilaian'
                            }}
                        </button>
                    </footer>
                </fieldset>
            </form>
        </div>
    </main>
</template>
