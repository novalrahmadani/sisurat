<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    CalendarDays,
    CheckCircle2,
    Clock3,
    Copy,
    KeyRound,
    Info,
    LocateFixed,
    LogOut,
    ShieldCheck,
    UserRound,
} from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import SignaturePad from '@/components/SignaturePad.vue';
import InputError from '@/components/InputError.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { home } from '@/routes';
import {
    register,
    submit,
    leave,
    recover,
    verifyPin,
    issuePin,
    session as internshipSession,
} from '@/routes/internship';

type Participant = {
    id: number;
    name: string;
    student_number: string;
    institution: string;
    study_program: string | null;
    participant_category: string | null;
    category_label: string;
    class_level: string | null;
    start_date: string;
    end_date: string;
    can_attend: boolean;
    has_pin: boolean;
};
type Attendance = {
    checked_in_at: string;
    checked_out_at: string | null;
    duration_minutes: number | null;
};
type CheckData = {
    action: 'check_in' | 'check_out';
    signature: string;
    note: string;
    latitude: number | null;
    longitude: number | null;
    accuracy: number | null;
    position_captured_at: number | null;
    access?: string;
    location?: string;
};
const props = defineProps<{
    participant: Participant | null;
    attendance: Attendance | null;
    today: { date: string; label: string; server_time: string };
    receipt: string | null;
    sessionNotice: string | null;
    location: { configured: boolean; radius_meters: number };
    recovery: { token: string; available: boolean } | null;
    issuedPin: string | null;
}>();
const registration = useForm({
    name: '',
    participant_category: '',
    student_number: '',
    institution: '',
    study_program: '',
    class_level: '',
    start_date: props.today.date,
    end_date: '',
});
const checkForm = useForm<CheckData>({
    action: 'check_in',
    signature: '',
    note: '',
    latitude: null,
    longitude: null,
    accuracy: null,
    position_captured_at: null,
});
const recoveryForm = useForm<{ access?: string }>({});
const pinForm = useForm({ pin: '' });
const pinIssueForm = useForm<{ access?: string }>({});
const showPin = ref(false);
const copyMessage = ref('');
const refreshingSession = ref(false);
const sessionError = ref('');
const readingLocation = ref(false);
const secureLocation = ref(false);
const locationError = ref('');
const signatureKey = ref(0);
const returningParticipant = ref(true);
const action = computed(() => (props.attendance ? 'check_out' : 'check_in'));
const complete = computed(() => Boolean(props.attendance?.checked_out_at));
const university = computed(
    () => registration.participant_category === 'university',
);
const fields = computed(() => [
    {
        key: 'name' as const,
        label: 'Nama lengkap',
        max: 150,
        required: true,
        placeholder: 'Sesuai identitas peserta',
        autocomplete: 'name',
    },
    {
        key: 'student_number' as const,
        label: university.value ? 'NIM' : 'NIS / NISN',
        max: 100,
        required: true,
        placeholder: university.value
            ? 'Nomor induk mahasiswa'
            : 'Nomor induk siswa / nomor induk siswa nasional',
        autocomplete: 'off',
    },
    {
        key: 'institution' as const,
        label: university.value ? 'Asal perguruan tinggi' : 'Asal sekolah',
        max: 200,
        required: true,
        placeholder: university.value
            ? 'Nama perguruan tinggi'
            : 'Nama SMA / SMK',
        autocomplete: 'organization',
    },
    {
        key: 'study_program' as const,
        label: university.value
            ? 'Program studi'
            : registration.participant_category === 'smk'
              ? 'Jurusan / Kompetensi keahlian'
              : 'Jurusan / Peminatan',
        max: 150,
        required: false,
        placeholder: 'Diisi sesuai data pendidikan',
        autocomplete: 'off',
    },
    {
        key: 'class_level' as const,
        label: university.value ? 'Semester' : 'Kelas',
        max: 50,
        required: false,
        placeholder: university.value
            ? 'Contoh: Semester 7'
            : 'Contoh: Kelas XI',
        autocomplete: 'off',
    },
]);
onMounted(() => {
    secureLocation.value = window.isSecureContext && 'geolocation' in navigator;
});
watch(
    [
        () => props.attendance?.checked_in_at,
        () => props.attendance?.checked_out_at,
        () => props.participant?.id,
    ],
    () => {
        checkForm.resetAndClearErrors();
        signatureKey.value++;
        locationError.value = '';
        copyMessage.value = '';
    },
);
async function prepareSession(): Promise<boolean> {
    if (refreshingSession.value) return false;
    refreshingSession.value = true;
    sessionError.value = '';
    const controller = new AbortController();
    const timeout = window.setTimeout(() => controller.abort(), 10000);
    try {
        const response = await fetch(internshipSession.url(), {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        });
        if (response.status !== 204) throw new Error('Session refresh failed');
        return true;
    } catch {
        sessionError.value =
            'Koneksi belum dapat diperbarui. Periksa internet dan coba lagi. Data formulir belum dikirim.';
        return false;
    } finally {
        window.clearTimeout(timeout);
        refreshingSession.value = false;
    }
}
async function registerParticipant() {
    if (registration.processing || !(await prepareSession())) return;
    registration.post(register.url(), {
        preserveScroll: false,
        onSuccess: () => {
            registration.resetAndClearErrors();
            returningParticipant.value = true;
        },
    });
}
async function enterWithPin() {
    if (pinForm.processing || !(await prepareSession())) return;
    pinForm.post(verifyPin.url(), {
        preserveScroll: false,
        onFinish: () => pinForm.reset('pin'),
    });
}
async function createPin() {
    if (pinIssueForm.processing || refreshingSession.value) return;
    if (
        props.participant?.has_pin &&
        !window.confirm(
            'Buat PIN baru? PIN sebelumnya akan berhenti berlaku. Simpan PIN baru setelah ditampilkan.',
        )
    )
        return;
    if (!(await prepareSession())) return;
    pinIssueForm.post(issuePin.url(), {
        preserveScroll: true,
        onSuccess: () => {
            copyMessage.value = '';
        },
    });
}
async function copyPin() {
    if (!props.issuedPin) return;
    try {
        await navigator.clipboard.writeText(props.issuedPin);
        copyMessage.value = 'PIN berhasil disalin.';
    } catch {
        copyMessage.value =
            'Silakan catat atau salin PIN yang ditampilkan secara manual.';
    }
}
async function activateDevice() {
    if (
        props.recovery?.available &&
        !recoveryForm.processing &&
        (await prepareSession())
    )
        recoveryForm.post(recover.url(props.recovery.token));
}
async function recordAttendance() {
    if (
        checkForm.processing ||
        readingLocation.value ||
        refreshingSession.value
    )
        return;
    locationError.value = '';
    checkForm.clearErrors();
    if (!secureLocation.value) {
        locationError.value =
            'GPS memerlukan alamat HTTPS dan izin lokasi. Hubungi admin PMB untuk alamat absensi yang dapat digunakan dari HP.';
        return;
    }
    if (!checkForm.signature) {
        checkForm.setError(
            'signature',
            'Bubuhkan tanda tangan terlebih dahulu.',
        );
        return;
    }
    readingLocation.value = true;
    try {
        const position = await new Promise<GeolocationPosition>(
            (resolve, reject) =>
                navigator.geolocation.getCurrentPosition(resolve, reject, {
                    enableHighAccuracy: true,
                    maximumAge: 0,
                    timeout: 20000,
                }),
        );
        checkForm.action = action.value;
        checkForm.latitude = position.coords.latitude;
        checkForm.longitude = position.coords.longitude;
        checkForm.accuracy = position.coords.accuracy;
        checkForm.position_captured_at = Math.floor(position.timestamp / 1000);
        if (!(await prepareSession())) return;
        checkForm.post(submit.url(), { preserveScroll: true });
    } catch (error) {
        const code = (error as GeolocationPositionError).code;
        locationError.value =
            code === 1
                ? 'Izin lokasi belum diberikan. Izinkan lokasi presisi untuk SISURAT pada pengaturan browser, kemudian coba kembali.'
                : code === 3
                  ? 'Pembacaan lokasi melewati batas waktu. Pastikan GPS aktif lalu coba kembali.'
                  : 'Lokasi belum dapat dibaca. Aktifkan layanan lokasi dan coba kembali di area terbuka dekat kantor PMB.';
    } finally {
        readingLocation.value = false;
    }
}
async function releaseDevice() {
    if (recoveryForm.processing || refreshingSession.value) return;
    if (
        window.confirm(
            props.participant?.has_pin
                ? 'Keluar dari data peserta pada perangkat ini? Anda dapat masuk kembali menggunakan PIN pribadi.'
                : 'Peserta ini belum memiliki PIN. Buat dan simpan PIN terlebih dahulu agar dapat masuk kembali tanpa bantuan admin. Tetap keluar?',
        )
    ) {
        if (!(await prepareSession())) return;
        recoveryForm.post(leave.url(), {
            onSuccess: () => {
                returningParticipant.value = true;
                pinForm.resetAndClearErrors();
            },
        });
    }
}
function dateLabel(date: string) {
    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'long',
        timeZone: 'Asia/Jakarta',
    }).format(new Date(date + 'T12:00:00+07:00'));
}
</script>

<template>
    <PublicLayout active="internship">
        <Head title="Daftar Hadir Peserta Magang" />
        <div class="mx-auto max-w-6xl px-5 pt-8 pb-8 sm:px-8 sm:pt-12">
            <Link
                :href="home()"
                class="inline-flex items-center gap-2 text-xs text-muted-foreground hover:text-primary"
                ><ArrowLeft class="size-4" />Halaman utama</Link
            >
            <div
                class="mt-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-end"
            >
                <div>
                    <p
                        class="text-xs font-semibold tracking-widest text-primary uppercase dark:text-emerald-300"
                    >
                        Administrasi peserta magang
                    </p>
                    <h1
                        class="mt-2 text-3xl font-semibold tracking-tight sm:text-4xl"
                    >
                        Daftar Hadir Peserta Magang
                    </h1>
                    <p
                        class="mt-3 max-w-xl text-sm leading-6 text-muted-foreground"
                    >
                        Pendaftaran dan pencatatan kehadiran siswa SMA, siswa
                        SMK, serta mahasiswa di PMB Universitas Nurul Huda.
                    </p>
                </div>
                <span
                    class="inline-flex items-center gap-2 self-start rounded-full border border-border bg-card px-4 py-2.5 text-xs"
                    ><CalendarDays
                        class="size-4 shrink-0 text-primary dark:text-emerald-300"
                    />{{ today.label }} · WIB</span
                >
            </div>
            <div
                class="mt-8 grid items-start gap-6 lg:grid-cols-[.75fr_1.25fr]"
            >
                <aside
                    class="order-2 rounded-3xl bg-linear-to-br from-[#005A36] to-[#104936] p-6 text-white sm:p-8 lg:order-1"
                >
                    <span
                        class="grid size-12 place-items-center rounded-2xl bg-white/15"
                        ><ShieldCheck class="size-6"
                    /></span>
                    <h2 class="mt-5 text-xl font-semibold">
                        Alur pencatatan kehadiran
                    </h2>
                    <ol class="mt-6 space-y-5 text-sm">
                        <li
                            v-for="(step, i) in [
                                {
                                    title: 'Daftarkan data diri',
                                    text: 'Lengkapi identitas dan periode magang satu kali, lalu simpan PIN pribadi yang diberikan.',
                                },
                                {
                                    title: 'Catat kehadiran masuk',
                                    text: 'Saat tiba di PMB, bubuhkan tanda tangan. Lokasi diperiksa ketika absensi disimpan.',
                                },
                                {
                                    title: 'Catat kehadiran pulang',
                                    text: 'Buka kembali absensi. Jika data belum dikenali, cukup masukkan PIN. Lokasi diperiksa kembali saat absensi pulang.',
                                },
                            ]"
                            :key="step.title"
                            class="flex gap-3"
                        >
                            <span
                                class="grid size-6 shrink-0 place-items-center rounded-full bg-white/15 text-xs"
                                >{{ i + 1 }}</span
                            >
                            <div>
                                <h3 class="font-medium">{{ step.title }}</h3>
                                <p
                                    class="mt-1 text-xs leading-6 text-emerald-50/80"
                                >
                                    {{ step.text }}
                                </p>
                            </div>
                        </li>
                    </ol>
                    <div
                        class="mt-7 border-t border-white/15 pt-5 text-xs leading-6 text-emerald-50/85"
                    >
                        <LocateFixed class="mb-2 size-4" />Absensi menggunakan
                        GPS dalam radius {{ location.radius_meters }} meter dari
                        titik PMB yang ditetapkan admin. Lokasi hanya dibaca
                        saat penyimpanan; koordinat peserta tidak disimpan.
                    </div>
                    <div
                        class="mt-5 rounded-2xl bg-white/10 p-4 text-xs leading-6"
                    >
                        <h3 class="font-semibold text-white">
                            Sudah terdaftar atau berganti HP?
                        </h3>
                        Cukup masukkan PIN pribadi 8 angka. Data peserta akan
                        terbaca otomatis tanpa mengisi identitas kembali.
                    </div>
                </aside>
                <section
                    class="order-1 min-w-0 rounded-3xl border border-border bg-card p-5 sm:p-8 lg:order-2"
                    aria-labelledby="form-title"
                >
                    <div
                        v-if="receipt"
                        role="status"
                        class="mb-6 flex gap-3 rounded-2xl border border-emerald-300/50 bg-emerald-50 p-4 text-sm leading-6 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-100"
                    >
                        <CheckCircle2 class="mt-0.5 size-5 shrink-0" />{{
                            receipt
                        }}
                    </div>
                    <div
                        v-if="sessionNotice"
                        role="status"
                        class="mb-5 flex gap-3 rounded-2xl border border-amber-300/50 bg-amber-50 p-4 text-sm leading-6 text-amber-950 dark:bg-amber-950 dark:text-amber-100"
                    >
                        <Info class="mt-0.5 size-5 shrink-0" />{{
                            sessionNotice
                        }}
                    </div>
                    <InputError
                        class="mb-4"
                        role="alert"
                        :message="sessionError"
                    />
                    <InputError
                        class="mb-4"
                        :message="pinIssueForm.errors.access"
                    />
                    <div
                        v-if="participant && issuedPin && !recovery"
                        role="status"
                        class="mb-6 rounded-2xl border border-primary/25 bg-primary/5 p-5"
                    >
                        <p
                            class="flex items-center gap-2 text-sm font-semibold text-primary dark:text-emerald-300"
                        >
                            <KeyRound class="size-4" />PIN pribadi Anda
                        </p>
                        <p
                            class="my-4 font-mono text-3xl font-bold tracking-[.2em] text-foreground sm:text-4xl"
                            data-testid="issued-pin"
                        >
                            {{ issuedPin }}
                        </p>
                        <p class="text-xs leading-6 text-muted-foreground">
                            Simpan PIN ini sekarang. PIN hanya ditampilkan saat
                            dibuat. Gunakan untuk membuka absensi dari HP atau
                            browser lain. Jangan bagikan kepada peserta lain.
                        </p>
                        <button
                            type="button"
                            class="sisurat-secondary-action mt-4 min-h-11"
                            @click="copyPin"
                        >
                            <Copy class="size-4" />Salin PIN
                        </button>
                        <p
                            v-if="copyMessage"
                            role="status"
                            class="mt-2 text-xs leading-6"
                        >
                            {{ copyMessage }}
                        </p>
                    </div>
                    <div v-if="recovery">
                        <h2 id="form-title" class="text-xl font-semibold">
                            Hubungkan perangkat absensi
                        </h2>
                        <p class="mt-3 text-sm leading-7 text-muted-foreground">
                            {{
                                recovery.available
                                    ? 'Tautan dari admin menghubungkan perangkat ini dengan data peserta yang sudah terdaftar. Tautan hanya dapat digunakan sekali.'
                                    : 'Tautan sudah kedaluwarsa, telah digunakan, atau peserta tidak aktif. Silakan meminta tautan baru kepada admin PMB.'
                            }}
                        </p>
                        <InputError
                            class="mt-3"
                            :message="recoveryForm.errors.access"
                        />
                        <button
                            v-if="recovery.available"
                            class="sisurat-primary-action mt-6 w-full justify-center"
                            :disabled="
                                recoveryForm.processing || refreshingSession
                            "
                            @click="activateDevice"
                        >
                            Gunakan perangkat ini<ArrowRight class="size-4" />
                        </button>
                        <Link
                            :href="home()"
                            class="mt-5 inline-block text-sm text-primary dark:text-emerald-300"
                            >Kembali ke halaman utama</Link
                        >
                    </div>
                    <div v-else-if="!participant">
                        <div
                            class="mb-6 grid grid-cols-2 gap-2 rounded-2xl bg-muted p-1.5"
                            role="group"
                            aria-label="Pilihan peserta magang"
                        >
                            <button
                                type="button"
                                class="min-h-12 rounded-xl px-3 py-2 text-sm font-medium"
                                :class="
                                    returningParticipant
                                        ? 'bg-card text-primary shadow-sm'
                                        : 'text-muted-foreground'
                                "
                                :aria-pressed="returningParticipant"
                                @click="returningParticipant = true"
                            >
                                Masuk dengan PIN
                            </button>
                            <button
                                type="button"
                                class="min-h-12 rounded-xl px-3 py-2 text-sm font-medium"
                                :class="
                                    !returningParticipant
                                        ? 'bg-card text-primary shadow-sm'
                                        : 'text-muted-foreground'
                                "
                                :aria-pressed="!returningParticipant"
                                @click="returningParticipant = false"
                            >
                                Peserta baru
                            </button>
                        </div>
                        <div v-if="returningParticipant">
                            <h2 id="form-title" class="text-xl font-semibold">
                                Masukkan PIN pribadi
                            </h2>
                            <p
                                class="mt-3 text-sm leading-7 text-muted-foreground"
                            >
                                Masukkan 8 angka PIN Anda. Sistem akan langsung
                                membuka identitas dan absensi yang sudah
                                terdaftar.
                            </p>
                            <InputError
                                class="mt-3"
                                :message="checkForm.errors.access"
                            />
                            <form
                                class="mt-6 space-y-4"
                                @submit.prevent="enterWithPin"
                            >
                                <div>
                                    <label
                                        for="attendance-pin"
                                        class="mb-2 block text-sm font-medium"
                                        >PIN absensi</label
                                    >
                                    <input
                                        id="attendance-pin"
                                        v-model="pinForm.pin"
                                        :type="showPin ? 'text' : 'password'"
                                        inputmode="numeric"
                                        pattern="[0-9]{8}"
                                        minlength="8"
                                        maxlength="8"
                                        autocomplete="off"
                                        required
                                        class="sisurat-input min-h-14 w-full font-mono text-2xl tracking-[.2em]"
                                        placeholder="8 angka"
                                        :aria-invalid="
                                            Boolean(pinForm.errors.pin)
                                        "
                                        aria-describedby="pin-help pin-error"
                                    />
                                    <InputError
                                        id="pin-error"
                                        class="mt-2"
                                        :message="pinForm.errors.pin"
                                    />
                                </div>
                                <label
                                    class="flex min-h-11 items-center gap-3 text-sm text-muted-foreground"
                                    ><input
                                        v-model="showPin"
                                        type="checkbox"
                                        class="size-4 accent-primary"
                                    />Tampilkan PIN</label
                                >
                                <button
                                    type="submit"
                                    :disabled="
                                        pinForm.processing || refreshingSession
                                    "
                                    class="sisurat-primary-action min-h-12 w-full justify-center"
                                >
                                    <KeyRound class="size-4" />{{
                                        refreshingSession
                                            ? 'Menyiapkan akses…'
                                            : pinForm.processing
                                              ? 'Memeriksa PIN…'
                                              : 'Buka absensi'
                                    }}<ArrowRight class="size-4" />
                                </button>
                                <p
                                    id="pin-help"
                                    class="text-xs leading-6 text-muted-foreground"
                                >
                                    Tidak memerlukan akun atau login admin.
                                    Lokasi PMB tetap diperiksa saat Anda
                                    menyimpan absensi.
                                </p>
                            </form>
                            <div
                                class="mt-5 rounded-2xl border border-border bg-muted/50 p-4 text-sm leading-7"
                            >
                                <p class="font-semibold">
                                    Lupa PIN atau peserta lama belum punya PIN?
                                </p>
                                <p class="mt-2 text-muted-foreground">
                                    Buka dari browser yang masih mengenali data
                                    Anda, lalu pilih “Buat PIN” atau “Ganti
                                    PIN”. Jika semua akses hilang, hubungi admin
                                    PMB untuk pemulihan satu kali. Data tidak
                                    perlu didaftarkan ulang.
                                </p>
                            </div>
                            <p
                                class="mt-5 text-xs leading-6 text-muted-foreground"
                            >
                                Gunakan alamat website SISURAT yang sama untuk
                                pendaftaran dan absensi berikutnya.
                            </p>
                        </div>
                        <div v-else>
                            <p
                                class="text-xs font-semibold tracking-wide text-primary uppercase dark:text-emerald-300"
                            >
                                Langkah awal
                            </p>
                            <h2
                                id="form-title"
                                class="mt-2 text-xl font-semibold"
                            >
                                Pendaftaran Peserta Magang
                            </h2>
                            <p
                                class="mt-2 text-sm leading-6 text-muted-foreground"
                            >
                                Lengkapi data sesuai identitas dan surat
                                pengantar magang. Data akan tercatat pada
                                administrasi PMB.
                            </p>
                            <InputError
                                class="mt-3"
                                :message="checkForm.errors.access"
                            />
                            <form
                                class="mt-6 space-y-5"
                                @submit.prevent="registerParticipant"
                            >
                                <div>
                                    <label
                                        for="participant-category"
                                        class="mb-2 block text-sm font-medium"
                                        >Kategori peserta<span
                                            class="ml-1 text-red-600"
                                            aria-hidden="true"
                                            >*</span
                                        ></label
                                    >
                                    <select
                                        id="participant-category"
                                        v-model="
                                            registration.participant_category
                                        "
                                        class="sisurat-input min-h-12 w-full"
                                        required
                                    >
                                        <option value="" disabled>
                                            Pilih kategori peserta
                                        </option>
                                        <option value="smk">Siswa SMK</option>
                                        <option value="sma">Siswa SMA</option>
                                        <option value="university">
                                            Mahasiswa
                                        </option>
                                    </select>
                                    <InputError
                                        :message="
                                            registration.errors
                                                .participant_category
                                        "
                                    />
                                </div>
                                <div class="grid gap-5 sm:grid-cols-2">
                                    <div
                                        v-for="field in fields"
                                        :key="field.key"
                                        :class="
                                            field.key === 'institution'
                                                ? 'sm:col-span-2'
                                                : ''
                                        "
                                    >
                                        <label
                                            :for="'registration-' + field.key"
                                            class="mb-2 block text-sm font-medium"
                                            >{{ field.label
                                            }}<span
                                                v-if="field.required"
                                                class="ml-1 text-red-600"
                                                aria-hidden="true"
                                                >*</span
                                            ><span
                                                v-else
                                                class="ml-1 text-xs font-normal text-muted-foreground"
                                                >(opsional)</span
                                            ></label
                                        >
                                        <input
                                            :id="'registration-' + field.key"
                                            v-model="registration[field.key]"
                                            class="sisurat-input min-h-12 w-full"
                                            :required="field.required"
                                            :maxlength="field.max"
                                            :placeholder="field.placeholder"
                                            :autocomplete="field.autocomplete"
                                            :aria-invalid="
                                                Boolean(
                                                    registration.errors[
                                                        field.key
                                                    ],
                                                )
                                            "
                                        />
                                        <InputError
                                            :message="
                                                registration.errors[field.key]
                                            "
                                        />
                                    </div>
                                </div>
                                <fieldset
                                    class="rounded-2xl border border-border p-4"
                                >
                                    <legend class="px-1 text-sm font-medium">
                                        Periode magang
                                    </legend>
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <label
                                                for="registration-start"
                                                class="mb-2 block text-sm"
                                                >Tanggal mulai<span
                                                    class="ml-1 text-red-600"
                                                    >*</span
                                                ></label
                                            ><input
                                                id="registration-start"
                                                v-model="
                                                    registration.start_date
                                                "
                                                type="date"
                                                class="sisurat-input min-h-12 w-full min-w-0"
                                                required
                                            /><InputError
                                                :message="
                                                    registration.errors
                                                        .start_date
                                                "
                                            />
                                        </div>
                                        <div>
                                            <label
                                                for="registration-end"
                                                class="mb-2 block text-sm"
                                                >Tanggal selesai<span
                                                    class="ml-1 text-red-600"
                                                    >*</span
                                                ></label
                                            ><input
                                                id="registration-end"
                                                v-model="registration.end_date"
                                                type="date"
                                                class="sisurat-input min-h-12 w-full min-w-0"
                                                :min="registration.start_date"
                                                required
                                            /><InputError
                                                :message="
                                                    registration.errors.end_date
                                                "
                                            />
                                        </div>
                                    </div>
                                </fieldset>
                                <p
                                    class="flex gap-2 rounded-xl bg-muted/60 p-3 text-xs leading-6 text-muted-foreground"
                                >
                                    <Info class="mt-1 size-4 shrink-0" />Gunakan
                                    perangkat pribadi. Setelah pendaftaran,
                                    simpan PIN 8 angka yang dibuat otomatis. PIN
                                    digunakan jika Anda berganti HP atau data
                                    belum dikenali pada browser.
                                </p>
                                <button
                                    type="submit"
                                    class="sisurat-primary-action min-h-12 w-full justify-center"
                                    :disabled="
                                        registration.processing ||
                                        refreshingSession
                                    "
                                >
                                    {{
                                        refreshingSession
                                            ? 'Menyiapkan formulir…'
                                            : registration.processing
                                              ? 'Menyimpan data peserta…'
                                              : 'Daftar dan lanjutkan ke absensi'
                                    }}<ArrowRight class="size-4" />
                                </button>
                            </form>
                        </div>
                    </div>
                    <div v-else>
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p
                                    class="text-xs font-semibold tracking-wide text-primary uppercase dark:text-emerald-300"
                                >
                                    Pencatatan kehadiran
                                </p>
                                <h2
                                    id="form-title"
                                    class="mt-2 text-xl font-semibold"
                                >
                                    {{
                                        complete
                                            ? 'Kehadiran hari ini lengkap'
                                            : action === 'check_in'
                                              ? 'Absensi Masuk'
                                              : 'Absensi Pulang'
                                    }}
                                </h2>
                            </div>
                            <button
                                type="button"
                                class="inline-flex items-center gap-2 rounded-xl border border-border p-3 text-xs text-muted-foreground"
                                aria-label="Ganti peserta dan lepaskan akses perangkat"
                                title="Ganti peserta dan lepaskan akses perangkat"
                                :disabled="
                                    recoveryForm.processing || refreshingSession
                                "
                                @click="releaseDevice"
                            >
                                <LogOut class="size-4" />Ganti peserta
                            </button>
                        </div>
                        <div
                            class="mt-5 flex gap-3 rounded-2xl bg-muted/60 p-4"
                        >
                            <UserRound
                                class="mt-1 size-5 shrink-0 text-primary dark:text-emerald-300"
                            />
                            <div class="min-w-0">
                                <h3 class="font-semibold break-words">
                                    {{ participant.name }}
                                </h3>
                                <p
                                    class="mt-1 text-xs leading-6 text-muted-foreground"
                                >
                                    {{ participant.category_label }} ·
                                    {{
                                        participant.participant_category ===
                                        'university'
                                            ? 'NIM'
                                            : 'NIS/NISN'
                                    }}
                                    {{ participant.student_number }}
                                </p>
                                <p
                                    class="mt-1 text-xs leading-6 break-words text-muted-foreground"
                                >
                                    {{ participant.institution
                                    }}<span v-if="participant.study_program">
                                        · {{ participant.study_program }}</span
                                    ><span v-if="participant.class_level">
                                        · {{ participant.class_level }}</span
                                    >
                                </p>
                                <p
                                    class="mt-3 border-t border-border pt-3 text-xs leading-6 text-muted-foreground"
                                >
                                    {{ dateLabel(participant.start_date) }} –
                                    {{ dateLabel(participant.end_date) }}
                                </p>
                            </div>
                        </div>
                        <div
                            class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border p-4"
                        >
                            <p
                                class="min-w-0 flex-1 text-xs leading-6 text-muted-foreground"
                            >
                                {{
                                    participant.has_pin
                                        ? 'Gunakan PIN pribadi jika membuka absensi dari perangkat lain.'
                                        : 'Buat PIN agar dapat membuka data ini dari perangkat lain tanpa bantuan admin.'
                                }}
                            </p>
                            <button
                                type="button"
                                class="sisurat-secondary-action min-h-11 shrink-0"
                                :disabled="
                                    pinIssueForm.processing || refreshingSession
                                "
                                @click="createPin"
                            >
                                <KeyRound class="size-4" />{{
                                    pinIssueForm.processing
                                        ? 'Membuat PIN…'
                                        : participant.has_pin
                                          ? 'Ganti PIN'
                                          : 'Buat PIN'
                                }}
                            </button>
                        </div>
                        <div class="mt-5 grid grid-cols-2 gap-3">
                            <div
                                v-for="item in [
                                    {
                                        label: 'Jam masuk',
                                        time: attendance?.checked_in_at,
                                    },
                                    {
                                        label: 'Jam pulang',
                                        time: attendance?.checked_out_at,
                                    },
                                ]"
                                :key="item.label"
                                class="rounded-2xl border border-border p-4"
                            >
                                <p
                                    class="flex items-center gap-1 text-xs text-muted-foreground"
                                >
                                    <Clock3 class="size-3.5" />{{ item.label }}
                                </p>
                                <p
                                    class="mt-2 text-base font-semibold tabular-nums sm:text-lg"
                                >
                                    {{ item.time ?? 'Belum tercatat' }}
                                </p>
                                <span
                                    v-if="item.time"
                                    class="text-xs text-muted-foreground"
                                    >WIB</span
                                >
                            </div>
                        </div>
                        <p
                            v-if="!participant.can_attend"
                            role="alert"
                            class="mt-5 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm leading-6 text-amber-950"
                        >
                            Hari ini berada di luar periode magang Anda. Hubungi
                            admin PMB untuk penyesuaian data.
                        </p>
                        <div
                            v-else-if="complete"
                            class="mt-6 flex gap-3 rounded-2xl bg-accent p-5 text-primary dark:text-emerald-100"
                        >
                            <CheckCircle2 class="size-5 shrink-0" />
                            <p class="text-sm leading-6">
                                Terima kasih. Jam masuk dan pulang hari ini
                                telah tersimpan.
                            </p>
                        </div>
                        <form
                            v-else
                            class="mt-6 space-y-5"
                            @submit.prevent="recordAttendance"
                        >
                            <div
                                v-if="!location.configured || !secureLocation"
                                role="alert"
                                class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm leading-6 text-amber-950"
                            >
                                <h3 class="font-semibold">
                                    Absensi GPS belum dapat digunakan
                                </h3>
                                <p v-if="!location.configured" class="mt-2">
                                    Admin perlu menetapkan titik lokasi kantor
                                    PMB terlebih dahulu.
                                </p>
                                <p v-if="!secureLocation" class="mt-2">
                                    Untuk akses HP, buka SISURAT melalui alamat
                                    HTTPS dan aktifkan izin lokasi. Hubungi
                                    admin PMB untuk alamat yang sesuai.
                                </p>
                            </div>
                            <p
                                v-else
                                class="flex gap-2 rounded-xl bg-accent p-4 text-xs leading-6 text-primary dark:text-emerald-100"
                            >
                                <LocateFixed class="mt-1 size-4 shrink-0" />Saat
                                menyimpan, izinkan lokasi presisi. Kehadiran
                                diterima dalam radius
                                {{ location.radius_meters }} meter dari kantor
                                PMB.
                            </p>
                            <InputError
                                :message="
                                    checkForm.errors.access ||
                                    checkForm.errors.action
                                "
                            />
                            <div>
                                <label
                                    for="internship-signature"
                                    class="mb-2 block text-sm font-medium"
                                    >Tanda tangan
                                    {{
                                        action === 'check_in'
                                            ? 'masuk'
                                            : 'pulang'
                                    }}</label
                                ><SignaturePad
                                    id="internship-signature"
                                    :key="signatureKey"
                                    v-model="checkForm.signature"
                                    :disabled="
                                        checkForm.processing || readingLocation
                                    "
                                    :error="checkForm.errors.signature"
                                />
                            </div>
                            <div>
                                <label
                                    for="attendance-note"
                                    class="mb-2 block text-sm font-medium"
                                    >Keterangan
                                    <span
                                        class="font-normal text-muted-foreground"
                                        >(opsional)</span
                                    ></label
                                ><textarea
                                    id="attendance-note"
                                    v-model="checkForm.note"
                                    class="sisurat-input min-h-24 w-full resize-y"
                                    maxlength="500"
                                    placeholder="Kegiatan atau keterangan kehadiran"
                                /><InputError
                                    :message="checkForm.errors.note"
                                />
                            </div>
                            <p
                                v-if="locationError"
                                role="alert"
                                class="text-sm leading-6 text-red-600 dark:text-red-400"
                            >
                                {{ locationError }}
                            </p>
                            <InputError
                                :message="
                                    checkForm.errors.location ||
                                    checkForm.errors.latitude ||
                                    checkForm.errors.longitude ||
                                    checkForm.errors.accuracy ||
                                    checkForm.errors.position_captured_at
                                "
                            />
                            <button
                                type="submit"
                                class="sisurat-primary-action min-h-12 w-full justify-center"
                                :disabled="
                                    checkForm.processing ||
                                    refreshingSession ||
                                    readingLocation ||
                                    !checkForm.signature ||
                                    !location.configured ||
                                    !secureLocation
                                "
                            >
                                {{
                                    readingLocation
                                        ? 'Memeriksa lokasi perangkat…'
                                        : checkForm.processing
                                          ? 'Menyimpan kehadiran…'
                                          : action === 'check_in'
                                            ? 'Periksa lokasi & simpan masuk'
                                            : 'Periksa lokasi & simpan pulang'
                                }}<LocateFixed class="size-4 shrink-0" />
                            </button>
                            <p
                                class="text-center text-xs leading-6 text-muted-foreground"
                            >
                                Jam dicatat otomatis menurut waktu sistem (WIB).
                                Lokasi dibaca hanya pada saat Anda menyimpan
                                kehadiran.
                            </p>
                        </form>
                    </div>
                </section>
            </div>
        </div>
    </PublicLayout>
</template>
