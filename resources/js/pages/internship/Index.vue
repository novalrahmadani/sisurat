<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    CalendarDays,
    CheckCircle2,
    Clock3,
    Copy,
    Download,
    ExternalLink,
    KeyRound,
    Pencil,
    Plus,
    Printer,
    Search,
    Trash2,
    UsersRound,
    X,
    MapPin,
} from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PublicFormShare from '@/components/PublicFormShare.vue';
import {
    index,
    store,
    update,
    destroy as destroyParticipant,
    recoverAccess,
    resetPin as resetParticipantPin,
    exportMethod,
    print as printReport,
    signature,
    qr,
    flyer,
} from '@/routes/internships';
import { edit as accessSettings } from '@/routes/internships/access';
type Participant = {
    id: number;
    name: string;
    student_number: string;
    institution: string;
    study_program: string | null;
    participant_category: string | null;
    category_label: string;
    class_level: string | null;
    registration_source: string;
    start_date: string;
    end_date: string;
    is_active: boolean;
    attendance_count?: number;
};
type Record = {
    id: number;
    date: string;
    checked_in_at: string;
    checked_out_at: string | null;
    duration_minutes: number | null;
    check_in_note: string | null;
    check_out_note: string | null;
    participant: Participant;
};
type Pagination<T> = {
    data: T[];
    total: number;
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
const props = defineProps<{
    participants: Pagination<Participant>;
    records: Pagination<Record>;
    filters: { q: string; month: string; tab: 'records' | 'participants' };
    today: string;
    stats: { active: number; present: number; pending: number };
    issuedAccess: { name: string; url: string } | null;
    issuedPin: { name: string; pin: string } | null;
    location: { configured: boolean; radius_meters: number };
    publicAttendanceUrl: string;
}>();
const query = ref(props.filters.q);
const month = ref(props.filters.month);
const editorOpen = ref(false);
const editingId = ref<number | null>(null);
const deletingId = ref<number | null>(null);
const copied = ref('');
const codeInput = ref<HTMLInputElement | null>(null);
const pinInput = ref<HTMLInputElement | null>(null);
const issuingPinId = ref<number | null>(null);
const copiedPin = ref('');
const form = useForm({
    name: '',
    participant_category: '',
    class_level: '',
    student_number: '',
    institution: '',
    study_program: '',
    start_date: props.today,
    end_date: props.today,
    is_active: true,
});
const pagination = computed(() =>
    props.filters.tab === 'participants' ? props.participants : props.records,
);
const exportQuery = computed(() => ({
    q: props.filters.q,
    month: props.filters.month,
}));
function filter(tab = props.filters.tab) {
    router.get(
        index.url(),
        { q: query.value, month: month.value, tab },
        { preserveState: true, preserveScroll: true },
    );
}
function edit(participant?: Participant) {
    form.resetAndClearErrors();
    editingId.value = participant?.id ?? null;
    if (participant)
        Object.assign(form, {
            name: participant.name,
            participant_category: participant.participant_category ?? '',
            class_level: participant.class_level ?? '',
            student_number: participant.student_number,
            institution: participant.institution,
            study_program: participant.study_program ?? '',
            start_date: participant.start_date,
            end_date: participant.end_date,
            is_active: participant.is_active,
        });
    editorOpen.value = true;
    void nextTick(() => document.getElementById('intern-name')?.focus());
}
function saveParticipant() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            editorOpen.value = false;
            form.resetAndClearErrors();
            if (!editingId.value)
                window.scrollTo({ top: 0, behavior: 'smooth' });
        },
    };
    if (editingId.value) form.put(update.url(editingId.value), options);
    else form.post(store.url(), options);
}
function resetCode(participant: Participant) {
    if (
        window.confirm(
            `Buat tautan pemulihan akses untuk ${participant.name}? Akses perangkat sebelumnya akan dinonaktifkan. Tautan baru berlaku 30 menit dan hanya dapat digunakan sekali.`,
        )
    )
        router.post(recoverAccess.url(participant.id));
}
function changePin(participant: Participant) {
    if (
        issuingPinId.value !== null ||
        !window.confirm(
            `Buat PIN baru untuk ${participant.name}? PIN lama dan akses perangkat yang tersimpan akan dinonaktifkan. PIN baru hanya ditampilkan sekali.`,
        )
    ) {
        return;
    }

    issuingPinId.value = participant.id;
    router.post(resetParticipantPin.url(participant.id), {
        preserveScroll: true,
        onFinish: () => {
            issuingPinId.value = null;
        },
    });
}
function removeParticipant(participant: Participant) {
    if (
        deletingId.value !== null ||
        !window.confirm(
            `Hapus peserta magang "${participant.name}"? Data peserta, ${participant.attendance_count ?? 0} catatan kehadiran, dan tanda tangannya akan dihapus permanen. Tindakan ini tidak dapat dibatalkan.`,
        )
    ) {
        return;
    }

    deletingId.value = participant.id;
    router.delete(destroyParticipant.url(participant.id), {
        data: { q: props.filters.q, month: props.filters.month },
        preserveScroll: true,
        onSuccess: () => {
            if (editingId.value === participant.id) editorOpen.value = false;
        },
        onFinish: () => {
            deletingId.value = null;
        },
    });
}
async function copyCode() {
    if (!props.issuedAccess) return;
    try {
        await navigator.clipboard.writeText(props.issuedAccess.url);
        copied.value = 'Tautan berhasil disalin.';
    } catch {
        codeInput.value?.select();
        copied.value = 'Tautan sudah dipilih. Gunakan Salin atau Ctrl+C.';
    }
}
async function copyIssuedPin() {
    if (!props.issuedPin) return;

    try {
        await navigator.clipboard.writeText(props.issuedPin.pin);
        copiedPin.value = 'PIN berhasil disalin.';
    } catch {
        pinInput.value?.select();
        copiedPin.value = 'PIN sudah dipilih. Salin secara manual.';
    }
}
function dateLabel(value: string) {
    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeZone: 'Asia/Jakarta',
    }).format(new Date(`${value}T12:00:00+07:00`));
}
function duration(minutes: number | null) {
    return minutes === null
        ? '—'
        : `${Math.floor(minutes / 60)}j ${minutes % 60}m`;
}
</script>

<template>
    <div class="mx-auto w-full max-w-7xl space-y-6 p-5 sm:p-8">
        <Head title="Absensi Magang" />
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p
                    class="text-xs font-semibold tracking-widest text-primary uppercase dark:text-emerald-200"
                >
                    Kehadiran harian
                </p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight">
                    Absensi Magang
                </h1>
                <p class="mt-2 text-sm leading-6 text-muted-foreground">
                    Kelola peserta, periode magang, dan catatan jam masuk serta
                    pulang.
                </p>
            </div>
            <a
                :href="publicAttendanceUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="sisurat-secondary-action"
                ><ExternalLink class="size-4" />Buka absensi publik</a
            >
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
            <div
                v-for="item in [
                    {
                        label: 'Peserta aktif hari ini',
                        value: stats.active,
                        icon: UsersRound,
                    },
                    {
                        label: 'Sudah masuk hari ini',
                        value: stats.present,
                        icon: CheckCircle2,
                    },
                    {
                        label: 'Belum pulang hari ini',
                        value: stats.pending,
                        icon: Clock3,
                    },
                ]"
                :key="item.label"
                class="flex items-center justify-between rounded-2xl border border-border bg-card p-5"
            >
                <div>
                    <p class="text-xs text-muted-foreground">
                        {{ item.label }}
                    </p>
                    <p class="mt-2 text-3xl font-semibold">{{ item.value }}</p>
                </div>
                <component
                    :is="item.icon"
                    class="size-6 text-primary dark:text-emerald-200"
                />
            </div>
        </div>
        <section
            class="sisurat-panel p-5 sm:p-6"
            aria-label="Bagikan absensi magang"
        >
            <PublicFormShare
                title="Bagikan absensi magang"
                description="Peserta SMK, SMA, dan mahasiswa dapat mendaftar serta mencatat jam masuk dan pulang tanpa akun. Pemeriksaan lokasi tetap mengikuti pengaturan PMB."
                :url="publicAttendanceUrl"
                :qr-url="qr.url()"
                :download-url="qr.url({ query: { download: 1 } })"
                link-label="Tautan absensi magang"
                open-label="Buka absensi publik"
                :flyer-url="flyer.url()"
                :flyer-download-url="flyer.url({ query: { download: 1 } })"
            />
        </section>
        <div
            v-if="issuedAccess"
            role="status"
            class="rounded-2xl border border-emerald-300/70 bg-emerald-50 p-5 text-emerald-950 dark:bg-emerald-950 dark:text-emerald-100"
        >
            <h2 class="flex items-center gap-2 font-semibold">
                <KeyRound class="size-5" />Akses perangkat
                {{ issuedAccess.name }}
            </h2>
            <p class="mt-2 text-sm leading-6">
                Berikan tautan ini secara pribadi kepada peserta yang telah
                diverifikasi. Tautan berlaku 30 menit dan hanya dapat digunakan
                sekali untuk menghubungkan perangkat, tanpa akun atau kata
                sandi.
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
                <input
                    ref="codeInput"
                    :value="issuedAccess.url"
                    readonly
                    aria-label="Tautan akses perangkat"
                    class="sisurat-input min-w-0 flex-1 bg-white text-sm text-emerald-950"
                /><button
                    type="button"
                    class="sisurat-primary-action"
                    @click="copyCode"
                >
                    <Copy class="size-4" />Salin
                </button>
            </div>
            <p v-if="copied" class="mt-2 text-xs">{{ copied }}</p>
        </div>
        <div
            v-if="issuedPin"
            role="status"
            class="rounded-2xl border border-emerald-300/70 bg-emerald-50 p-5 text-emerald-950 dark:bg-emerald-950 dark:text-emerald-100"
        >
            <h2 class="flex items-center gap-2 font-semibold">
                <KeyRound class="size-5" />PIN baru untuk {{ issuedPin.name }}
            </h2>
            <p class="mt-2 text-sm leading-6">
                Berikan PIN ini secara pribadi kepada peserta. PIN hanya ditampilkan sekali.
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
                <input
                    ref="pinInput"
                    :value="issuedPin.pin"
                    readonly
                    aria-label="PIN absensi peserta"
                    class="sisurat-input min-w-0 flex-1 bg-white text-lg font-semibold tracking-[0.25em] text-emerald-950"
                /><button
                    type="button"
                    class="sisurat-primary-action"
                    @click="copyIssuedPin"
                >
                    <Copy class="size-4" />Salin PIN
                </button>
            </div>
            <p v-if="copiedPin" class="mt-2 text-xs">{{ copiedPin }}</p>
        </div>
        <div
            class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-border bg-card p-5"
        >
            <div class="flex items-start gap-3">
                <MapPin
                    class="mt-1 size-5 shrink-0 text-primary dark:text-emerald-300"
                />
                <div>
                    <h2 class="text-sm font-semibold">Pembatasan lokasi GPS</h2>
                    <p class="mt-1 text-xs leading-6 text-muted-foreground">
                        {{
                            location.configured
                                ? 'Titik PMB sudah ditetapkan. Radius absensi: ' +
                                  location.radius_meters +
                                  ' meter.'
                                : 'Titik kantor PMB belum ditetapkan. Atur titik sebelum peserta melakukan absensi.'
                        }}
                    </p>
                </div>
            </div>
            <Link :href="accessSettings()" class="sisurat-secondary-action"
                ><MapPin class="size-4" />Atur lokasi PMB</Link
            >
        </div>
        <div
            class="flex flex-wrap gap-2 border-b border-border pb-3"
            aria-label="Bagian absensi magang"
        >
            <button
                type="button"
                class="rounded-xl px-4 py-3 text-sm font-medium"
                :class="
                    filters.tab === 'records'
                        ? 'bg-primary text-white'
                        : 'bg-card text-muted-foreground'
                "
                :aria-pressed="filters.tab === 'records'"
                @click="filter('records')"
            >
                Rekap Kehadiran</button
            ><button
                type="button"
                class="rounded-xl px-4 py-3 text-sm font-medium"
                :class="
                    filters.tab === 'participants'
                        ? 'bg-primary text-white'
                        : 'bg-card text-muted-foreground'
                "
                :aria-pressed="filters.tab === 'participants'"
                @click="filter('participants')"
            >
                Peserta Magang
            </button>
        </div>
        <form
            class="flex flex-wrap items-end gap-3 rounded-2xl border border-border bg-card p-5"
            @submit.prevent="filter()"
        >
            <div class="min-w-48 flex-1">
                <label
                    for="internship-search"
                    class="mb-2 block text-xs font-medium"
                    >Cari peserta</label
                ><input
                    id="internship-search"
                    v-model="query"
                    class="sisurat-input w-full"
                    placeholder="Nama, NIS/NIM, sekolah atau perguruan tinggi"
                    maxlength="150"
                />
            </div>
            <div v-if="filters.tab === 'records'">
                <label
                    for="internship-month"
                    class="mb-2 block text-xs font-medium"
                    >Bulan kehadiran</label
                ><input
                    id="internship-month"
                    v-model="month"
                    class="sisurat-input"
                    type="month"
                    required
                />
            </div>
            <button class="sisurat-primary-action" type="submit">
                <Search class="size-4" />Cari</button
            ><button
                v-if="filters.tab === 'participants'"
                class="sisurat-secondary-action"
                type="button"
                @click="edit()"
            >
                <Plus class="size-4" />Tambah peserta
            </button>
        </form>
        <section
            v-if="editorOpen"
            class="rounded-2xl border border-primary/40 bg-card p-5 sm:p-7"
            aria-labelledby="participant-editor-title"
        >
            <div class="flex items-center justify-between gap-3">
                <h2 id="participant-editor-title" class="text-lg font-semibold">
                    {{
                        editingId
                            ? 'Ubah peserta magang'
                            : 'Tambah peserta magang'
                    }}
                </h2>
                <button
                    type="button"
                    aria-label="Tutup formulir peserta"
                    class="rounded-lg p-2 hover:bg-accent"
                    @click="editorOpen = false"
                >
                    <X class="size-5" />
                </button>
            </div>
            <form class="mt-5 space-y-5" @submit.prevent="saveParticipant">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label
                            for="intern-category"
                            class="mb-2 block text-sm font-medium"
                            >Kategori peserta</label
                        ><select
                            id="intern-category"
                            v-model="form.participant_category"
                            class="sisurat-input w-full"
                            required
                        >
                            <option value="" disabled>
                                Pilih kategori peserta
                            </option>
                            <option value="smk">Siswa SMK</option>
                            <option value="sma">Siswa SMA</option>
                            <option value="university">
                                Mahasiswa
                            </option></select
                        ><InputError
                            :message="form.errors.participant_category"
                        />
                    </div>
                    <div>
                        <label
                            for="intern-class"
                            class="mb-2 block text-sm font-medium"
                            >{{
                                form.participant_category === 'university'
                                    ? 'Semester'
                                    : 'Kelas'
                            }}
                            (opsional)</label
                        ><input
                            id="intern-class"
                            v-model="form.class_level"
                            class="sisurat-input w-full"
                            maxlength="50"
                        /><InputError :message="form.errors.class_level" />
                    </div>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label
                            for="intern-name"
                            class="mb-2 block text-sm font-medium"
                            >Nama lengkap</label
                        ><input
                            id="intern-name"
                            v-model="form.name"
                            class="sisurat-input w-full"
                            required
                            maxlength="150"
                        /><InputError :message="form.errors.name" />
                    </div>
                    <div>
                        <label
                            for="intern-number"
                            class="mb-2 block text-sm font-medium"
                            >{{
                                form.participant_category === 'university'
                                    ? 'NIM'
                                    : 'NIS / NISN'
                            }}</label
                        ><input
                            id="intern-number"
                            v-model="form.student_number"
                            class="sisurat-input w-full"
                            required
                            maxlength="100"
                        /><InputError :message="form.errors.student_number" />
                        <p
                            class="mt-1 text-[11px] leading-5 text-muted-foreground"
                        >
                            Gunakan nomor yang unik untuk setiap peserta.
                        </p>
                    </div>
                    <div>
                        <label
                            for="intern-institution"
                            class="mb-2 block text-sm font-medium"
                            >{{
                                form.participant_category === 'university'
                                    ? 'Asal perguruan tinggi'
                                    : 'Asal sekolah'
                            }}</label
                        ><input
                            id="intern-institution"
                            v-model="form.institution"
                            class="sisurat-input w-full"
                            required
                            maxlength="200"
                        /><InputError :message="form.errors.institution" />
                    </div>
                    <div>
                        <label
                            for="intern-program"
                            class="mb-2 block text-sm font-medium"
                            >{{
                                form.participant_category === 'university'
                                    ? 'Program studi'
                                    : form.participant_category === 'smk'
                                      ? 'Jurusan / Kompetensi keahlian'
                                      : 'Jurusan / Peminatan'
                            }}
                            (opsional)</label
                        ><input
                            id="intern-program"
                            v-model="form.study_program"
                            class="sisurat-input w-full"
                            maxlength="150"
                        /><InputError :message="form.errors.study_program" />
                    </div>
                    <div>
                        <label
                            for="intern-start"
                            class="mb-2 block text-sm font-medium"
                            >Mulai magang</label
                        ><input
                            id="intern-start"
                            v-model="form.start_date"
                            class="sisurat-input w-full"
                            type="date"
                            required
                        /><InputError :message="form.errors.start_date" />
                    </div>
                    <div>
                        <label
                            for="intern-end"
                            class="mb-2 block text-sm font-medium"
                            >Selesai magang</label
                        ><input
                            id="intern-end"
                            v-model="form.end_date"
                            class="sisurat-input w-full"
                            type="date"
                            :min="form.start_date"
                            required
                        /><InputError :message="form.errors.end_date" />
                    </div>
                </div>
                <label class="flex items-center gap-3 text-sm"
                    ><input
                        v-model="form.is_active"
                        type="checkbox"
                        class="size-4 accent-primary"
                    />Peserta aktif dan dapat melakukan absensi sesuai
                    periode</label
                ><InputError :message="form.errors.is_active" />
                <div class="flex flex-wrap gap-3">
                    <button
                        class="sisurat-primary-action"
                        type="submit"
                        :disabled="form.processing"
                    >
                        {{
                            form.processing ? 'Menyimpan…' : 'Simpan peserta'
                        }}</button
                    ><button
                        class="sisurat-secondary-action"
                        type="button"
                        @click="editorOpen = false"
                    >
                        Batal
                    </button>
                </div>
            </form>
        </section>
        <section v-if="filters.tab === 'participants'" class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">
                    Daftar peserta magang
                    <span class="text-sm font-normal text-muted-foreground"
                        >({{ participants.total }})</span
                    >
                </h2>
                <button
                    v-if="participants.total === 0"
                    type="button"
                    class="sisurat-primary-action"
                    @click="edit()"
                >
                    <Plus class="size-4" />Tambah peserta pertama
                </button>
            </div>
            <p
                v-if="participants.total === 0"
                class="rounded-2xl border border-dashed border-border bg-card p-8 text-center text-sm leading-7 text-muted-foreground"
            >
                Belum ada peserta yang sesuai. Peserta dapat mendaftarkan data
                diri melalui halaman publik tanpa akun. Admin juga dapat
                menambahkan peserta.
            </p>
            <div class="grid gap-4 lg:grid-cols-2">
                <article
                    v-for="participant in participants.data"
                    :key="participant.id"
                    class="min-w-0 rounded-2xl border border-border bg-card p-5"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="font-semibold break-words">
                                {{ participant.name }}
                            </h3>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ participant.category_label }} ·
                                {{ participant.student_number }}
                            </p>
                        </div>
                        <span
                            class="shrink-0 rounded-full px-2.5 py-1 text-[10px]"
                            :class="
                                participant.is_active
                                    ? 'bg-accent text-primary dark:text-emerald-200'
                                    : 'bg-muted text-muted-foreground'
                            "
                            >{{
                                participant.is_active ? 'Aktif' : 'Nonaktif'
                            }}</span
                        >
                    </div>
                    <p
                        class="mt-3 text-sm leading-6 break-words text-muted-foreground"
                    >
                        {{ participant.institution
                        }}<span v-if="participant.study_program">
                            · {{ participant.study_program }}</span
                        >
                        <span v-if="participant.class_level">
                            · {{ participant.class_level }}</span
                        >
                    </p>
                    <p
                        class="mt-3 flex items-start gap-2 text-xs leading-5 text-muted-foreground"
                    >
                        <CalendarDays class="mt-0.5 size-3.5 shrink-0" />{{
                            dateLabel(participant.start_date)
                        }}
                        – {{ dateLabel(participant.end_date) }}
                    </p>
                    <div
                        class="mt-4 flex flex-wrap gap-2 border-t border-border pt-4"
                    >
                        <button
                            type="button"
                            class="sisurat-secondary-action !px-3 !py-2 !text-xs"
                            @click="edit(participant)"
                        >
                            <Pencil class="size-3.5" />Ubah data</button
                        ><button
                            type="button"
                            class="sisurat-secondary-action !px-3 !py-2 !text-xs"
                            :disabled="issuingPinId !== null"
                            @click="changePin(participant)"
                        >
                            <KeyRound class="size-3.5" />{{
                                issuingPinId === participant.id ? 'Membuat PIN…' : 'Ganti PIN'
                            }}</button
                        ><button
                            type="button"
                            class="sisurat-secondary-action !px-3 !py-2 !text-xs"
                            @click="resetCode(participant)"
                        >
                            <KeyRound class="size-3.5" />Pulihkan akses
                            perangkat
                        </button>
                        <button
                            type="button"
                            class="sisurat-secondary-action !px-3 !py-2 !text-xs !text-destructive hover:!bg-destructive/10"
                            :disabled="deletingId !== null"
                            :aria-label="`Hapus peserta ${participant.name}`"
                            @click="removeParticipant(participant)"
                        >
                            <Trash2 class="size-3.5" />{{
                                deletingId === participant.id
                                    ? 'Menghapus…'
                                    : 'Hapus peserta'
                            }}
                        </button>
                    </div>
                </article>
            </div>
        </section>
        <section
            v-else
            class="overflow-hidden rounded-2xl border border-border bg-card"
        >
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b border-border p-5"
            >
                <div>
                    <h2 class="font-semibold">
                        Rekap kehadiran
                        <span class="text-sm font-normal text-muted-foreground"
                            >({{ records.total }} catatan)</span
                        >
                    </h2>
                    <p class="mt-1 text-xs leading-5 text-muted-foreground">
                        Waktu dalam WIB. Ekspor mencakup semua hasil pencarian
                        pada bulan terpilih.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a
                        :href="exportMethod.url({ query: exportQuery })"
                        class="sisurat-secondary-action !px-3 !py-2.5 !text-xs"
                        ><Download class="size-4" />Unduh CSV</a
                    ><a
                        :href="printReport.url({ query: exportQuery })"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="sisurat-secondary-action !px-3 !py-2.5 !text-xs"
                        ><Printer class="size-4" />Cetak / Simpan PDF</a
                    >
                </div>
            </div>
            <div
                v-if="records.data.length === 0"
                class="p-10 text-center text-sm leading-7 text-muted-foreground"
            >
                <Clock3 class="mx-auto mb-3 size-8" />Belum ada absensi pada
                pilihan ini. Daftarkan peserta di tab Peserta Magang dan bagikan
                akses perangkatnya.
            </div>
            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-220 text-left text-sm">
                    <thead class="bg-muted/50 text-xs text-muted-foreground">
                        <tr>
                            <th class="p-4 font-medium">Tanggal</th>
                            <th class="p-4 font-medium">Peserta</th>
                            <th class="p-4 font-medium">Masuk (WIB)</th>
                            <th class="p-4 font-medium">Pulang (WIB)</th>
                            <th class="p-4 font-medium">Durasi</th>
                            <th class="p-4 font-medium">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="record in records.data"
                            :key="record.id"
                            class="border-t border-border"
                        >
                            <td class="p-4 text-xs whitespace-nowrap">
                                {{ dateLabel(record.date) }}
                            </td>
                            <td class="p-4">
                                <strong class="font-medium">{{
                                    record.participant.name
                                }}</strong
                                ><span
                                    class="mt-1 block text-xs text-muted-foreground"
                                    >{{ record.participant.student_number }} ·
                                    {{ record.participant.institution }}</span
                                >
                            </td>
                            <td class="p-4">
                                <span class="text-xs tabular-nums">{{
                                    record.checked_in_at
                                }}</span
                                ><img
                                    :src="
                                        signature.url({
                                            attendance: record.id,
                                            kind: 'in',
                                        })
                                    "
                                    alt="Tanda tangan masuk"
                                    loading="lazy"
                                    class="mt-1 h-10 w-24 rounded bg-white object-contain"
                                />
                            </td>
                            <td class="p-4">
                                <span class="text-xs tabular-nums">{{
                                    record.checked_out_at ?? 'Belum tercatat'
                                }}</span
                                ><img
                                    v-if="record.checked_out_at"
                                    :src="
                                        signature.url({
                                            attendance: record.id,
                                            kind: 'out',
                                        })
                                    "
                                    alt="Tanda tangan pulang"
                                    loading="lazy"
                                    class="mt-1 h-10 w-24 rounded bg-white object-contain"
                                />
                            </td>
                            <td class="p-4 text-xs whitespace-nowrap">
                                {{ duration(record.duration_minutes) }}
                            </td>
                            <td
                                class="max-w-64 p-4 text-xs leading-6 break-words text-muted-foreground"
                            >
                                <p v-if="record.check_in_note">
                                    Masuk: {{ record.check_in_note }}
                                </p>
                                <p v-if="record.check_out_note">
                                    Pulang: {{ record.check_out_note }}
                                </p>
                                <span
                                    v-if="
                                        !record.check_in_note &&
                                        !record.check_out_note
                                    "
                                    >—</span
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
        <nav
            v-if="pagination.last_page > 1"
            class="flex flex-wrap items-center justify-between gap-3"
            aria-label="Halaman daftar absensi magang"
        >
            <p class="text-xs text-muted-foreground">
                Halaman {{ pagination.current_page }} dari
                {{ pagination.last_page }}
            </p>
            <div class="flex gap-2">
                <Link
                    v-if="pagination.prev_page_url"
                    :href="pagination.prev_page_url"
                    class="sisurat-secondary-action !text-xs"
                    preserve-scroll
                    >Sebelumnya</Link
                ><Link
                    v-if="pagination.next_page_url"
                    :href="pagination.next_page_url"
                    class="sisurat-secondary-action !text-xs"
                    preserve-scroll
                    >Berikutnya</Link
                >
            </div>
        </nav>
    </div>
</template>
