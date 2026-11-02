<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    CalendarDays,
    CheckCircle2,
    ClipboardCheck,
    LoaderCircle,
    LockKeyhole,
    MapPin,
    PenLine,
    Send,
    UserRound,
} from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import SignaturePad from '@/components/SignaturePad.vue';
import {
    attendanceDate,
    attendanceTypeLabel,
    type AttendanceType,
} from '@/lib/attendance';
import { submit as submitAttendance } from '@/routes/attendance';

const props = defineProps<{
    event: {
        type: AttendanceType;
        title: string;
        event_date: string;
        location: string | null;
        description: string | null;
        is_open: boolean;
    };
    token: string;
    submitted: boolean;
    studyPrograms: Array<{ id: number; name: string }>;
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

const receiptVisible = ref(props.submitted);
const submissionError = ref('');
const typeChangedNotice = ref(false);
const form = useForm({
    name: '',
    position: '',
    institution: '',
    registration_number: '',
    study_program_id: '',
    school_origin: '',
    phone_number: '',
    signature: '',
    submission_token: newSubmissionToken(),
});
const signaturePad = ref<InstanceType<typeof SignaturePad> | null>(null);
const nameInput = ref<HTMLInputElement | null>(null);
const registrationInput = ref<HTMLInputElement | null>(null);
const studyProgramSelect = ref<HTMLSelectElement | null>(null);
const schoolInput = ref<HTMLInputElement | null>(null);
const phoneInput = ref<HTMLInputElement | null>(null);
const positionInput = ref<HTMLInputElement | null>(null);
const institutionInput = ref<HTMLInputElement | null>(null);
const signatureSection = ref<HTMLElement | null>(null);
const errorSummary = ref<HTMLDivElement | null>(null);
const receipt = ref<HTMLElement | null>(null);
const errorMessages = computed(() =>
    Array.from(new Set(Object.values(form.errors).filter(Boolean))),
);
const eventDate = computed(() => attendanceDate(props.event.event_date));
const isPmbTest = computed(() => props.event.type === 'pmb_test');
const hasStudyPrograms = computed(() => props.studyPrograms.length > 0);

watch(
    () => props.event.type,
    (type, previousType) => {
        if ((type === 'pmb_test') === (previousType === 'pmb_test')) return;
        typeChangedNotice.value = true;
        if (type === 'pmb_test') form.clearErrors('position', 'institution');
        else
            form.clearErrors(
                'registration_number',
                'study_program_id',
                'school_origin',
                'phone_number',
            );
    },
);

function focusErrors(): void {
    void nextTick(() => {
        if (!submissionError.value) {
            const errorFields = [
                { key: 'name', element: nameInput.value },
                ...(isPmbTest.value
                    ? [
                          {
                              key: 'registration_number',
                              element: registrationInput.value,
                          },
                          {
                              key: 'study_program_id',
                              element: studyProgramSelect.value,
                          },
                          { key: 'school_origin', element: schoolInput.value },
                          { key: 'phone_number', element: phoneInput.value },
                      ]
                    : [
                          { key: 'position', element: positionInput.value },
                          {
                              key: 'institution',
                              element: institutionInput.value,
                          },
                      ]),
                { key: 'signature', element: signatureSection.value },
            ];
            const field = errorFields.find(
                ({ key, element }) =>
                    element &&
                    !element.matches(':disabled') &&
                    form.errors[key as keyof typeof form.errors],
            );
            if (field?.element) {
                field.element.focus();
                return;
            }
        }
        errorSummary.value?.focus();
    });
}

function submit(): void {
    if (form.processing || !props.event.is_open) return;
    submissionError.value = '';
    form.clearErrors();
    form.name = form.name.trim();
    if (!form.name) form.setError('name', 'Nama lengkap wajib diisi.');
    if (isPmbTest.value) {
        form.registration_number = form.registration_number.trim();
        if (!form.registration_number) {
            form.setError(
                'registration_number',
                'Nomor registrasi wajib diisi.',
            );
        }
        if (!form.study_program_id) {
            form.setError('study_program_id', 'Pilih prodi yang diambil.');
        } else if (
            !props.studyPrograms.some(
                (program) => String(program.id) === form.study_program_id,
            )
        ) {
            form.setError(
                'study_program_id',
                'Pilihan prodi sudah tidak tersedia. Silakan pilih kembali.',
            );
        }
        form.school_origin = form.school_origin.trim();
        form.phone_number = form.phone_number.trim().replace(/[\s().-]+/g, '');
        if (!form.school_origin) {
            form.setError('school_origin', 'Asal sekolah wajib diisi.');
        }
        if (!/^\+?[0-9]{8,15}$/.test(form.phone_number)) {
            form.setError(
                'phone_number',
                'Isi nomor HP/WhatsApp dengan 8–15 digit, misalnya 081234567890 atau +6281234567890.',
            );
        }
    } else {
        form.position = form.position.trim();
        form.institution = form.institution.trim();
        if (!form.position) form.setError('position', 'Jabatan wajib diisi.');
        if (!form.institution) {
            form.setError(
                'institution',
                'Instansi atau institusi wajib diisi.',
            );
        }
    }
    form.signature = signaturePad.value?.getSignature() ?? '';
    if (!form.signature) {
        form.setError(
            'signature',
            'Silakan tulis tanda tangan terlebih dahulu.',
        );
    }
    if (form.hasErrors) {
        focusErrors();
        return;
    }
    form.transform((data) => {
        const identity = {
            name: data.name,
            signature: data.signature,
            submission_token: data.submission_token,
        };
        return isPmbTest.value
            ? {
                  ...identity,
                  registration_number: data.registration_number,
                  study_program_id: data.study_program_id,
                  school_origin: data.school_origin,
                  phone_number: data.phone_number,
              }
            : {
                  ...identity,
                  position: data.position,
                  institution: data.institution,
              };
    });
    form.post(submitAttendance.url({ token: props.token }), {
        preserveScroll: true,
        onSuccess: () => {
            signaturePad.value?.clear();
            form.reset();
            form.clearErrors();
            typeChangedNotice.value = false;
            receiptVisible.value = true;
            void nextTick(() => receipt.value?.focus());
        },
        onError: focusErrors,
        onNetworkError: () => {
            submissionError.value =
                'Koneksi terputus. Data Anda masih terisi. Periksa koneksi lalu kirim kembali.';
            focusErrors();
            return false;
        },
        onHttpException: (response) => {
            submissionError.value =
                response.status === 429
                    ? 'Terlalu banyak pengiriman. Tunggu satu menit lalu coba kembali.'
                    : response.status === 419
                      ? 'Sesi halaman telah berakhir. Muat ulang halaman dan isi kembali daftar hadir.'
                      : 'Daftar hadir belum dapat disimpan. Data Anda masih terisi; coba kirim kembali atau hubungi panitia.';
            focusErrors();
            return false;
        },
    });
}

function fillAnotherParticipant(): void {
    form.reset();
    form.clearErrors();
    form.submission_token = newSubmissionToken();
    form.defaults('submission_token', form.submission_token);
    submissionError.value = '';
    typeChangedNotice.value = false;
    receiptVisible.value = false;
    void nextTick(() => nameInput.value?.focus());
}
</script>

<template>
    <div
        lang="id"
        translate="no"
        class="notranslate sisurat-workspace min-h-dvh text-foreground"
    >
        <Head
            :title="`Daftar Hadir ${attendanceTypeLabel(event.type)} · ${event.title}`"
        />
        <a
            href="#attendance-content"
            class="sr-only z-20 rounded-lg bg-card p-3 text-primary focus:not-sr-only focus:absolute focus:top-4 focus:left-4"
            >Langsung ke formulir</a
        >
        <header class="border-b border-border/70 bg-card/90">
            <div
                class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-4 sm:px-6 lg:px-8"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <span
                        class="flex size-12 shrink-0 items-center justify-center rounded-xl border border-border bg-white p-1.5 sm:size-14"
                    >
                        <AppLogoIcon class="size-full" />
                    </span>
                    <div class="grid min-w-0 gap-0.5">
                        <p
                            class="text-sm font-semibold tracking-wide sm:text-base"
                        >
                            SISURAT PMB
                        </p>
                        <p
                            class="text-xs leading-5 text-muted-foreground sm:text-sm"
                        >
                            Universitas Nurul Huda
                        </p>
                    </div>
                </div>
                <span
                    class="hidden items-center gap-2 rounded-full border border-border bg-secondary px-3 py-2 text-xs font-medium text-secondary-foreground sm:inline-flex"
                >
                    <ClipboardCheck class="size-4" aria-hidden="true" />
                    Daftar hadir online
                </span>
            </div>
        </header>

        <main
            id="attendance-content"
            class="mx-auto grid max-w-6xl min-w-0 items-start gap-5 px-4 py-5 sm:gap-6 sm:px-6 sm:py-8 lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.35fr)] lg:gap-8 lg:px-8 lg:py-10"
        >
            <aside
                aria-label="Informasi kegiatan"
                class="grid min-w-0 gap-5 lg:sticky lg:top-6"
            >
                <section
                    class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm"
                >
                    <div
                        class="sisurat-gradient grid gap-4 p-5 text-white sm:p-7"
                    >
                        <div
                            class="flex flex-wrap items-center justify-between gap-3"
                        >
                            <span
                                class="inline-flex items-center gap-2 text-xs font-medium tracking-wide text-white/90"
                            >
                                <ClipboardCheck
                                    class="size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                Daftar Hadir
                                {{ attendanceTypeLabel(event.type) }}
                            </span>
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full border border-white/20 bg-white/10 px-2.5 py-1 text-xs font-medium"
                            >
                                <span
                                    class="size-1.5 rounded-full"
                                    :class="
                                        receiptVisible
                                            ? 'bg-white'
                                            : event.is_open
                                              ? 'bg-emerald-300'
                                              : 'bg-amber-200'
                                    "
                                    aria-hidden="true"
                                ></span>
                                {{
                                    receiptVisible
                                        ? 'Sudah tercatat'
                                        : event.is_open
                                          ? 'Dibuka'
                                          : 'Ditutup'
                                }}
                            </span>
                        </div>
                        <h1
                            class="text-xl leading-relaxed font-semibold wrap-anywhere sm:text-2xl"
                        >
                            {{ event.title }}
                        </h1>
                    </div>
                    <div class="grid gap-5 p-5 sm:p-7">
                        <dl class="grid gap-4">
                            <div class="flex min-w-0 items-start gap-3">
                                <span
                                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-secondary text-secondary-foreground"
                                    ><CalendarDays
                                        class="size-4"
                                        aria-hidden="true"
                                /></span>
                                <div class="grid min-w-0 gap-1">
                                    <dt class="text-xs text-muted-foreground">
                                        Tanggal kegiatan
                                    </dt>
                                    <dd
                                        class="text-sm leading-6 font-medium wrap-anywhere"
                                    >
                                        {{ eventDate }}
                                    </dd>
                                </div>
                            </div>
                            <div
                                v-if="event.location"
                                class="flex min-w-0 items-start gap-3"
                            >
                                <span
                                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-secondary text-secondary-foreground"
                                    ><MapPin class="size-4" aria-hidden="true"
                                /></span>
                                <div class="grid min-w-0 gap-1">
                                    <dt class="text-xs text-muted-foreground">
                                        Tempat
                                    </dt>
                                    <dd
                                        class="text-sm leading-6 font-medium wrap-anywhere"
                                    >
                                        {{ event.location }}
                                    </dd>
                                </div>
                            </div>
                        </dl>
                        <p
                            v-if="event.description"
                            class="border-t border-border pt-4 text-sm leading-7 wrap-anywhere whitespace-pre-line text-muted-foreground"
                        >
                            {{ event.description }}
                        </p>
                    </div>
                </section>
                <section
                    v-if="event.is_open && !receiptVisible"
                    aria-label="Petunjuk pengisian"
                    class="hidden rounded-2xl border border-border/80 bg-card/60 p-6 lg:block"
                >
                    <h2 class="text-sm font-semibold">
                        Cara mengisi daftar hadir
                    </h2>
                    <ol
                        class="mt-4 grid gap-4 text-sm leading-6 text-muted-foreground"
                    >
                        <li class="flex gap-3">
                            <UserRound
                                class="mt-1 size-4 shrink-0 text-primary"
                                aria-hidden="true"
                            /><span>Lengkapi identitas sesuai data Anda.</span>
                        </li>
                        <li class="flex gap-3">
                            <PenLine
                                class="mt-1 size-4 shrink-0 text-primary"
                                aria-hidden="true"
                            /><span
                                >Bubuhkan tanda tangan pada bidang yang
                                tersedia.</span
                            >
                        </li>
                        <li class="flex gap-3">
                            <CheckCircle2
                                class="mt-1 size-4 shrink-0 text-primary"
                                aria-hidden="true"
                            /><span
                                >Simpan dan tunggu konfirmasi kehadiran berhasil
                                dicatat.</span
                            >
                        </li>
                    </ol>
                </section>
            </aside>

            <div class="grid min-w-0 gap-5">
                <section
                    v-if="receiptVisible"
                    ref="receipt"
                    tabindex="-1"
                    aria-labelledby="receipt-title"
                    class="sisurat-panel grid min-w-0 justify-items-center gap-4 p-6 text-center outline-none focus-visible:ring-3 focus-visible:ring-ring/30 sm:px-10 sm:py-12"
                >
                    <span
                        class="flex size-16 items-center justify-center rounded-full bg-secondary text-primary"
                    >
                        <CheckCircle2 class="size-8" aria-hidden="true" />
                    </span>
                    <h2 id="receipt-title" class="text-xl font-semibold">
                        Kehadiran berhasil dicatat
                    </h2>
                    <p class="max-w-sm text-sm leading-7 text-muted-foreground">
                        Terima kasih. Data dan tanda tangan telah tersimpan
                        dalam daftar hadir kegiatan ini.
                    </p>
                    <p
                        class="w-full rounded-xl border border-border bg-secondary px-4 py-3 text-sm leading-6 text-secondary-foreground"
                    >
                        Anda sudah selesai mengisi daftar hadir kegiatan ini.
                    </p>
                    <button
                        v-if="event.is_open"
                        type="button"
                        class="sisurat-secondary-action mt-2 min-h-12 w-full whitespace-normal sm:w-auto"
                        @click="fillAnotherParticipant"
                    >
                        Isi untuk peserta berikutnya
                    </button>
                </section>

                <section
                    v-else-if="!event.is_open"
                    class="sisurat-panel grid min-w-0 justify-items-center gap-4 p-6 text-center sm:px-10 sm:py-12"
                >
                    <span
                        class="flex size-14 items-center justify-center rounded-2xl bg-secondary text-secondary-foreground"
                        ><LockKeyhole class="size-6" aria-hidden="true"
                    /></span>
                    <h2 class="text-lg font-semibold">
                        Daftar hadir sudah ditutup
                    </h2>
                    <p class="text-sm leading-7 text-muted-foreground">
                        Pengisian kehadiran untuk kegiatan ini telah ditutup.
                        Hubungi panitia jika Anda memerlukan bantuan.
                    </p>
                </section>

                <form
                    v-else
                    class="sisurat-panel attendee-form grid min-w-0 gap-6 p-5 sm:gap-7 sm:p-7 lg:p-8"
                    @submit.prevent="submit"
                >
                    <div class="grid gap-2">
                        <p
                            class="text-xs font-medium tracking-wider text-primary uppercase"
                        >
                            Formulir kehadiran
                        </p>
                        <h2 class="text-xl leading-8 font-semibold sm:text-2xl">
                            Isi kehadiran Anda
                        </h2>
                        <p class="text-sm leading-6 text-muted-foreground">
                            Lengkapi identitas dan bubuhkan tanda tangan. Semua
                            kolom wajib diisi.
                        </p>
                    </div>

                    <p
                        v-if="typeChangedNotice"
                        role="status"
                        class="rounded-xl border border-border bg-secondary p-4 text-sm leading-7 text-secondary-foreground"
                    >
                        Panitia memperbarui jenis daftar hadir. Periksa kembali
                        kolom isian sebelum mengirim. Isian Anda tetap
                        dipertahankan.
                    </p>

                    <div
                        v-if="submissionError || errorMessages.length"
                        ref="errorSummary"
                        role="alert"
                        tabindex="-1"
                        class="grid gap-2 rounded-xl border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive outline-none focus-visible:ring-2 focus-visible:ring-destructive/30"
                    >
                        <p class="font-semibold">Kehadiran belum tersimpan</p>
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
                        class="grid min-w-0 gap-5 sm:grid-cols-2"
                    >
                        <legend class="mb-5 w-full border-b border-border pb-4">
                            <span
                                class="flex items-center gap-3 text-sm font-semibold"
                                ><span
                                    class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-secondary text-secondary-foreground"
                                    >1</span
                                >Identitas peserta</span
                            >
                        </legend>
                        <div
                            class="grid min-w-0 content-start gap-2 sm:col-span-2"
                        >
                            <label
                                for="attendee-name"
                                class="text-sm font-medium"
                                >{{
                                    isPmbTest ? 'Nama' : 'Nama lengkap'
                                }}</label
                            >
                            <input
                                id="attendee-name"
                                ref="nameInput"
                                v-model="form.name"
                                name="name"
                                type="text"
                                autocomplete="name"
                                :placeholder="
                                    isPmbTest
                                        ? 'Nama sesuai pendaftaran'
                                        : 'Nama lengkap beserta gelar'
                                "
                                maxlength="150"
                                required
                                :aria-invalid="Boolean(form.errors.name)"
                                :aria-describedby="
                                    form.errors.name
                                        ? 'attendee-name-error'
                                        : undefined
                                "
                                class="sisurat-input"
                            />
                            <p
                                v-if="form.errors.name"
                                id="attendee-name-error"
                                class="text-sm text-destructive"
                            >
                                {{ form.errors.name }}
                            </p>
                        </div>
                        <template v-if="isPmbTest">
                            <div class="grid min-w-0 content-start gap-2">
                                <label
                                    for="attendee-registration-number"
                                    class="text-sm font-medium"
                                    >Nomor registrasi</label
                                >
                                <input
                                    id="attendee-registration-number"
                                    ref="registrationInput"
                                    v-model="form.registration_number"
                                    name="registration_number"
                                    type="text"
                                    autocomplete="off"
                                    placeholder="No. registrasi PMB"
                                    maxlength="100"
                                    required
                                    :aria-invalid="
                                        Boolean(form.errors.registration_number)
                                    "
                                    :aria-describedby="
                                        form.errors.registration_number
                                            ? 'attendee-registration-number-error'
                                            : undefined
                                    "
                                    class="sisurat-input"
                                />
                                <p
                                    v-if="form.errors.registration_number"
                                    id="attendee-registration-number-error"
                                    class="text-sm text-destructive"
                                >
                                    {{ form.errors.registration_number }}
                                </p>
                            </div>
                            <div class="grid min-w-0 content-start gap-2">
                                <label
                                    for="attendee-study-program"
                                    class="text-sm font-medium"
                                    >Program studi yang diambil</label
                                >
                                <select
                                    id="attendee-study-program"
                                    ref="studyProgramSelect"
                                    v-model="form.study_program_id"
                                    name="study_program_id"
                                    required
                                    :disabled="!hasStudyPrograms"
                                    :aria-invalid="
                                        Boolean(form.errors.study_program_id)
                                    "
                                    :aria-describedby="
                                        !hasStudyPrograms
                                            ? 'attendee-study-program-unavailable'
                                            : form.errors.study_program_id
                                              ? 'attendee-study-program-error'
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
                                    id="attendee-study-program-unavailable"
                                    role="status"
                                    class="text-sm leading-7 text-muted-foreground"
                                >
                                    Pilihan program studi belum tersedia.
                                    Hubungi panitia, lalu muat ulang halaman
                                    setelah pilihan disiapkan.
                                </p>
                                <p
                                    v-if="form.errors.study_program_id"
                                    id="attendee-study-program-error"
                                    class="text-sm text-destructive"
                                >
                                    {{ form.errors.study_program_id }}
                                </p>
                            </div>
                            <div class="grid min-w-0 content-start gap-2">
                                <label
                                    for="attendee-school"
                                    class="text-sm font-medium"
                                    >Asal sekolah</label
                                >
                                <input
                                    id="attendee-school"
                                    ref="schoolInput"
                                    v-model="form.school_origin"
                                    name="school_origin"
                                    type="text"
                                    autocomplete="off"
                                    placeholder="Nama sekolah asal"
                                    maxlength="200"
                                    required
                                    :aria-invalid="
                                        Boolean(form.errors.school_origin)
                                    "
                                    :aria-describedby="
                                        form.errors.school_origin
                                            ? 'attendee-school-error'
                                            : undefined
                                    "
                                    class="sisurat-input"
                                />
                                <p
                                    v-if="form.errors.school_origin"
                                    id="attendee-school-error"
                                    class="text-sm text-destructive"
                                >
                                    {{ form.errors.school_origin }}
                                </p>
                            </div>
                            <div class="grid min-w-0 content-start gap-2">
                                <label
                                    for="attendee-phone"
                                    class="text-sm font-medium"
                                    >No. HP/WhatsApp</label
                                >
                                <input
                                    id="attendee-phone"
                                    ref="phoneInput"
                                    v-model="form.phone_number"
                                    name="phone_number"
                                    type="tel"
                                    inputmode="tel"
                                    autocomplete="tel"
                                    placeholder="Contoh: 081234567890"
                                    maxlength="30"
                                    required
                                    :aria-invalid="
                                        Boolean(form.errors.phone_number)
                                    "
                                    :aria-describedby="
                                        form.errors.phone_number
                                            ? 'attendee-phone-help attendee-phone-error'
                                            : 'attendee-phone-help'
                                    "
                                    class="sisurat-input"
                                />
                                <p
                                    id="attendee-phone-help"
                                    class="text-xs leading-6 text-muted-foreground"
                                >
                                    Gunakan nomor aktif yang dapat dihubungi
                                    melalui WhatsApp.
                                </p>
                                <p
                                    v-if="form.errors.phone_number"
                                    id="attendee-phone-error"
                                    class="text-sm text-destructive"
                                >
                                    {{ form.errors.phone_number }}
                                </p>
                            </div>
                        </template>
                        <template v-else>
                            <div class="grid min-w-0 content-start gap-2">
                                <label
                                    for="attendee-position"
                                    class="text-sm font-medium"
                                    >Jabatan</label
                                >
                                <input
                                    id="attendee-position"
                                    ref="positionInput"
                                    v-model="form.position"
                                    name="position"
                                    type="text"
                                    autocomplete="organization-title"
                                    placeholder="Contoh: Dosen atau Staf"
                                    maxlength="150"
                                    required
                                    :aria-invalid="
                                        Boolean(form.errors.position)
                                    "
                                    :aria-describedby="
                                        form.errors.position
                                            ? 'attendee-position-error'
                                            : undefined
                                    "
                                    class="sisurat-input"
                                />
                                <p
                                    v-if="form.errors.position"
                                    id="attendee-position-error"
                                    class="text-sm text-destructive"
                                >
                                    {{ form.errors.position }}
                                </p>
                            </div>
                            <div class="grid min-w-0 content-start gap-2">
                                <label
                                    for="attendee-institution"
                                    class="text-sm font-medium"
                                    >Instansi / institusi</label
                                >
                                <input
                                    id="attendee-institution"
                                    ref="institutionInput"
                                    v-model="form.institution"
                                    name="institution"
                                    type="text"
                                    autocomplete="organization"
                                    placeholder="Nama instansi/institusi"
                                    maxlength="255"
                                    required
                                    :aria-invalid="
                                        Boolean(form.errors.institution)
                                    "
                                    :aria-describedby="
                                        form.errors.institution
                                            ? 'attendee-institution-error'
                                            : undefined
                                    "
                                    class="sisurat-input"
                                />
                                <p
                                    v-if="form.errors.institution"
                                    id="attendee-institution-error"
                                    class="text-sm text-destructive"
                                >
                                    {{ form.errors.institution }}
                                </p>
                            </div>
                        </template>
                    </fieldset>

                    <section
                        ref="signatureSection"
                        tabindex="-1"
                        aria-labelledby="signature-label"
                        class="grid min-w-0 scroll-mt-6 gap-4 rounded-xl outline-none focus-visible:ring-3 focus-visible:ring-ring/30"
                    >
                        <h3
                            id="signature-label"
                            class="flex items-center gap-3 border-b border-border pb-4 text-sm font-semibold"
                        >
                            <span
                                class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-secondary text-secondary-foreground"
                                >2</span
                            >Tanda tangan
                        </h3>
                        <SignaturePad
                            ref="signaturePad"
                            v-model="form.signature"
                            :disabled="form.processing"
                            :error="form.errors.signature"
                        />
                    </section>

                    <div class="grid min-w-0 gap-4 border-t border-border pt-5">
                        <p class="text-sm leading-6 text-muted-foreground">
                            Pastikan identitas dan tanda tangan sudah benar
                            sebelum disimpan.
                        </p>
                        <button
                            type="submit"
                            :disabled="
                                form.processing ||
                                (isPmbTest && !hasStudyPrograms)
                            "
                            :aria-busy="form.processing"
                            class="sisurat-primary-action min-h-12 w-full text-base sm:text-sm"
                        >
                            <LoaderCircle
                                v-if="form.processing"
                                class="size-4 animate-spin"
                                aria-hidden="true"
                            />
                            <Send v-else class="size-4" aria-hidden="true" />
                            {{
                                form.processing
                                    ? 'Menyimpan kehadiran...'
                                    : 'Simpan kehadiran'
                            }}
                        </button>
                        <p
                            class="flex items-start justify-center gap-2 text-center text-xs leading-5 text-muted-foreground"
                        >
                            <LockKeyhole
                                class="mt-0.5 size-3.5 shrink-0"
                                aria-hidden="true"
                            /><span
                                >Data digunakan panitia untuk administrasi
                                kehadiran kegiatan.</span
                            >
                        </p>
                    </div>
                </form>
            </div>

            <footer
                class="pt-1 text-center text-xs leading-6 text-muted-foreground lg:col-span-2"
            >
                Sekretariat PMB · Universitas Nurul Huda
            </footer>
        </main>
    </div>
</template>

<style scoped>
.attendee-form :is(input, select) {
    min-width: 0;
    min-height: 3rem;
    font-size: 1rem;
    scroll-margin-top: 1.5rem;
}

.attendee-form :is(input, select)[aria-invalid='true'] {
    border-color: var(--destructive);
}

@media (min-width: 640px) {
    .attendee-form :is(input, select) {
        font-size: 0.875rem;
    }
}
</style>
