<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    CalendarDays,
    ClipboardList,
    Download,
    MapPin,
    Pencil,
    Printer,
    RefreshCw,
    Search,
    Trash2,
    Users,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import PublicFormShare from '@/components/PublicFormShare.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    attendanceDate,
    attendanceIndexRoute,
    attendanceTimestamp,
    attendanceTypeLabel,
    type AttendanceEvent,
    type AttendanceRecord,
} from '@/lib/attendance';
import {
    useMasterDataDeletion,
    type MasterDataPagination,
} from '@/lib/masterData';
import {
    destroy,
    edit,
    exportMethod as exportAttendance,
    print as printAttendance,
    show,
    qr,
    flyer,
} from '@/routes/attendance';
import { destroy as destroyRecord } from '@/routes/attendance/records';

type EventDeletionTarget = { id: number; name: string; entries_count: number };

const props = defineProps<{
    event: AttendanceEvent;
    records: MasterDataPagination<AttendanceRecord>;
    filters: { q: string };
    public_url: string;
}>();
const query = ref(props.filters.q);
const searching = ref(false);
const refreshing = ref(false);
const requestError = ref('');
const failedSignatures = ref(new Set<number>());
const {
    deleteForm: eventDeleteForm,
    deleteOpen: eventDeleteOpen,
    deleteTarget: eventDeleteTarget,
    deleteError: eventDeleteError,
    requestDeletion: requestEventDeletion,
    updateDeleteDialog: updateEventDeleteDialog,
    restoreDeleteFocus: restoreEventDeleteFocus,
    confirmDeletion: confirmEventDeletion,
} = useMasterDataDeletion<EventDeletionTarget>(
    (id) => destroy.url(id),
    'attendance-back',
);
const {
    deleteForm: recordDeleteForm,
    deleteOpen: recordDeleteOpen,
    deleteTarget: recordDeleteTarget,
    deleteError: recordDeleteError,
    requestDeletion: requestRecordDeletion,
    updateDeleteDialog: updateRecordDeleteDialog,
    restoreDeleteFocus: restoreRecordDeleteFocus,
    confirmDeletion: confirmRecordDeletion,
} = useMasterDataDeletion<AttendanceRecord>(
    (id) =>
        destroyRecord.url(
            { attendance: props.event.id, record: id },
            {
                query: {
                    q: props.filters.q || undefined,
                    page: props.records.current_page,
                },
            },
        ),
    'attendance-participant-search',
);
const busy = computed(
    () =>
        searching.value ||
        refreshing.value ||
        eventDeleteForm.processing ||
        recordDeleteForm.processing,
);
const isPmbTest = computed(() => props.event.type === 'pmb_test');

watch(
    () => props.filters.q,
    (value) => {
        query.value = value;
    },
);
watch(
    () => props.records,
    () => {
        failedSignatures.value = new Set();
    },
);
function search(): void {
    if (busy.value) return;
    requestError.value = '';
    router.get(
        show.url(props.event.id),
        { q: query.value.trim() || undefined },
        {
            preserveState: true,
            preserveScroll: true,
            onStart: () => {
                searching.value = true;
            },
            onFinish: () => {
                searching.value = false;
            },
            onError: (errors) => {
                requestError.value = Object.values(errors)
                    .filter(Boolean)
                    .join(' ');
            },
            onNetworkError: () => {
                requestError.value =
                    'Koneksi terputus. Periksa koneksi lalu cari kembali.';
                return false;
            },
            onHttpException: () => {
                requestError.value =
                    'Data peserta belum dapat dimuat. Muat ulang halaman atau coba kembali.';
                return false;
            },
        },
    );
}

function refresh(): void {
    if (busy.value) return;
    requestError.value = '';
    router.reload({
        only: ['event', 'records', 'filters', 'public_url'],
        onStart: () => {
            refreshing.value = true;
        },
        onFinish: () => {
            refreshing.value = false;
        },
        onNetworkError: () => {
            requestError.value =
                'Koneksi terputus. Data peserta belum diperbarui. Coba kembali.';
            return false;
        },
        onHttpException: () => {
            requestError.value =
                'Data peserta belum dapat diperbarui. Muat ulang halaman atau coba kembali.';
            return false;
        },
    });
}

function resetFilters(): void {
    if (busy.value) return;
    query.value = '';
    search();
}

function openEventDeletion(event: Event): void {
    if (busy.value) return;
    requestEventDeletion(
        {
            id: props.event.id,
            name: props.event.title,
            entries_count: props.event.entries_count,
        },
        event,
    );
}

function openRecordDeletion(record: AttendanceRecord, event: Event): void {
    if (busy.value) return;
    requestRecordDeletion(record, event);
}
</script>

<template>
    <main class="sisurat-page space-y-6">
        <Head
            :title="`Daftar Hadir ${attendanceTypeLabel(event.type)} · ${event.title}`"
        />
        <Link
            id="attendance-back"
            :href="attendanceIndexRoute(event.type)"
            class="sisurat-secondary-action w-fit"
            :class="{ 'pointer-events-none opacity-50': busy }"
            :aria-disabled="busy"
            @click="busy && $event.preventDefault()"
            ><ArrowLeft class="size-4" aria-hidden="true" />Daftar hadir
            {{ attendanceTypeLabel(event.type).toLowerCase() }}</Link
        >
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div class="min-w-0 flex-1">
                <p class="sisurat-kicker">
                    Daftar Hadir Online · {{ attendanceTypeLabel(event.type) }}
                </p>
                <h1 class="sisurat-title mt-2 break-words">
                    {{ event.title }}
                </h1>
                <div
                    class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-xs leading-6 text-muted-foreground"
                >
                    <span class="inline-flex items-center gap-1.5"
                        ><CalendarDays
                            class="size-4 shrink-0"
                            aria-hidden="true"
                        />{{ attendanceDate(event.event_date) }}</span
                    ><span
                        v-if="event.location"
                        class="inline-flex items-center gap-1.5 break-words"
                        ><MapPin class="size-4 shrink-0" aria-hidden="true" />{{
                            event.location
                        }}</span
                    >
                </div>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                <Link
                    :href="edit(event.id)"
                    class="sisurat-secondary-action"
                    :class="{ 'pointer-events-none opacity-50': busy }"
                    :aria-disabled="busy"
                    @click="busy && $event.preventDefault()"
                    ><Pencil class="size-4" aria-hidden="true" />Ubah
                    kegiatan</Link
                >
                <button
                    type="button"
                    class="sisurat-secondary-action border-destructive/25 text-destructive hover:bg-destructive/10"
                    :disabled="busy"
                    @click="openEventDeletion($event)"
                >
                    <Trash2 class="size-4" aria-hidden="true" />Hapus kegiatan
                </button>
            </div>
        </header>

        <section
            class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_240px]"
            aria-label="Informasi dan tautan kegiatan"
        >
            <div class="sisurat-panel min-w-0 p-5 sm:p-6">
                <PublicFormShare
                    title="Bagikan daftar hadir kepada peserta"
                    description="Peserta dapat mengisi identitas dan tanda tangan tanpa akun SISURAT. Bagikan tautan atau QR sesuai kegiatan ini."
                    :url="public_url"
                    :qr-url="qr.url(event.id)"
                    :download-url="qr.url(event.id, { query: { download: 1 } })"
                    link-label="Tautan daftar hadir"
                    open-label="Buka halaman peserta"
                    :disabled="busy"
                    :flyer-url="flyer.url(event.id)"
                    :flyer-download-url="
                        flyer.url(event.id, { query: { download: 1 } })
                    "
                />
                <p
                    v-if="event.description"
                    class="mt-4 border-t border-border pt-4 text-xs leading-7 whitespace-pre-wrap text-muted-foreground"
                >
                    {{ event.description }}
                </p>
            </div>
            <div class="sisurat-panel flex flex-col gap-5 p-5 sm:p-6">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-xs text-muted-foreground"
                        >Status pengisian</span
                    ><span
                        class="rounded-full px-2.5 py-1 text-[11px] font-medium"
                        :class="
                            event.is_open
                                ? 'bg-primary/10 text-primary'
                                : 'bg-muted text-muted-foreground'
                        "
                        >{{ event.is_open ? 'Dibuka' : 'Ditutup' }}</span
                    >
                </div>
                <div>
                    <div
                        class="flex items-center gap-2 text-xs text-muted-foreground"
                    >
                        <Users class="size-4" aria-hidden="true" />Peserta
                        tercatat
                    </div>
                    <strong
                        class="mt-2 block text-3xl font-semibold tabular-nums"
                        >{{
                            event.entries_count.toLocaleString('id-ID')
                        }}</strong
                    >
                </div>
                <p class="text-xs leading-6 text-muted-foreground">
                    {{
                        event.is_open
                            ? 'Peserta dapat mengisi daftar hadir. Tutup pengisian melalui Ubah kegiatan setelah selesai.'
                            : 'Pengisian sudah ditutup. Data peserta tetap tersedia untuk dicetak dan diekspor.'
                    }}
                </p>
            </div>
        </section>

        <section
            class="sisurat-panel overflow-hidden"
            aria-labelledby="attendance-records-heading"
            :aria-busy="busy"
        >
            <header
                class="flex flex-wrap items-center justify-between gap-4 border-b border-border/60 px-5 py-4"
            >
                <div>
                    <h2
                        id="attendance-records-heading"
                        class="text-sm font-semibold"
                    >
                        Daftar peserta
                    </h2>
                    <p class="mt-1 text-xs leading-6 text-muted-foreground">
                        Cetak dan ekspor mencakup semua peserta kegiatan.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="sisurat-secondary-action min-h-10 px-3 text-xs"
                        :disabled="busy"
                        :aria-busy="refreshing"
                        @click="refresh"
                    >
                        <RefreshCw
                            class="size-3.5"
                            :class="{
                                'animate-spin motion-reduce:animate-none':
                                    refreshing,
                            }"
                            aria-hidden="true"
                        />{{ refreshing ? 'Memperbarui…' : 'Perbarui' }}</button
                    ><a
                        v-if="event.entries_count"
                        :href="printAttendance.url(event.id)"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="sisurat-secondary-action min-h-10 px-3 text-xs"
                        :class="{ 'pointer-events-none opacity-50': busy }"
                        :aria-disabled="busy"
                        @click="busy && $event.preventDefault()"
                        ><Printer class="size-3.5" aria-hidden="true" />Cetak
                        semua</a
                    ><a
                        v-if="event.entries_count"
                        :href="exportAttendance.url(event.id)"
                        class="sisurat-secondary-action min-h-10 px-3 text-xs"
                        :class="{ 'pointer-events-none opacity-50': busy }"
                        :aria-disabled="busy"
                        @click="busy && $event.preventDefault()"
                        ><Download class="size-3.5" aria-hidden="true" />Ekspor
                        CSV</a
                    >
                </div>
            </header>
            <div class="p-5">
                <form @submit.prevent="search">
                    <fieldset
                        :disabled="busy"
                        class="flex min-w-0 flex-wrap items-end gap-3"
                    >
                        <div class="min-w-0 flex-1 basis-60">
                            <label
                                for="attendance-participant-search"
                                class="mb-2 block text-xs font-medium"
                                >Cari peserta</label
                            >
                            <div class="relative">
                                <Search
                                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden="true"
                                /><input
                                    id="attendance-participant-search"
                                    v-model="query"
                                    type="search"
                                    maxlength="200"
                                    class="sisurat-input pl-10"
                                    :placeholder="
                                        isPmbTest
                                            ? 'Nama, nomor registrasi, prodi, sekolah, atau nomor HP'
                                            : 'Nama, jabatan, atau instansi/institusi'
                                    "
                                />
                            </div>
                        </div>
                        <button
                            type="submit"
                            class="sisurat-primary-action"
                            :aria-busy="searching"
                        >
                            <Search class="size-4" aria-hidden="true" />{{
                                searching ? 'Mencari…' : 'Cari'
                            }}</button
                        ><button
                            v-if="query"
                            type="button"
                            class="sisurat-secondary-action"
                            @click="resetFilters"
                        >
                            <X class="size-4" aria-hidden="true" />Hapus filter
                        </button>
                    </fieldset>
                </form>
                <p
                    v-if="requestError"
                    class="mt-4 rounded-xl bg-destructive/10 px-4 py-3 text-sm text-destructive"
                    role="alert"
                >
                    {{ requestError }}
                </p>
            </div>
            <div
                v-if="records.data.length"
                class="overflow-x-auto border-t border-border/60"
            >
                <table
                    class="w-full text-left text-sm"
                    :class="isPmbTest ? 'min-w-280' : 'min-w-200'"
                >
                    <caption class="sr-only">
                        {{
                            isPmbTest
                                ? 'Nama, nomor registrasi, prodi yang diambil, asal sekolah, nomor HP/WhatsApp, waktu pengisian, dan tanda tangan peserta'
                                : 'Nama, jabatan, instansi/institusi, waktu pengisian, dan tanda tangan peserta'
                        }}
                        {{
                            event.title
                        }}
                    </caption>
                    <thead class="bg-muted/50 text-xs text-muted-foreground">
                        <tr>
                            <th scope="col" class="px-5 py-3 font-medium">
                                No
                            </th>
                            <th scope="col" class="px-4 py-3 font-medium">
                                Nama
                            </th>
                            <th scope="col" class="px-4 py-3 font-medium">
                                {{ isPmbTest ? 'Nomor Registrasi' : 'Jabatan' }}
                            </th>
                            <th scope="col" class="px-4 py-3 font-medium">
                                {{
                                    isPmbTest
                                        ? 'Program Studi/Jurusan'
                                        : 'Instansi / Institusi'
                                }}
                            </th>
                            <template v-if="isPmbTest">
                                <th scope="col" class="px-4 py-3 font-medium">
                                    Asal Sekolah
                                </th>
                                <th scope="col" class="px-4 py-3 font-medium">
                                    No. HP/WhatsApp
                                </th>
                            </template>
                            <th scope="col" class="px-4 py-3 font-medium">
                                Waktu pengisian
                            </th>
                            <th scope="col" class="px-5 py-3 font-medium">
                                Tanda tangan
                            </th>
                            <th
                                scope="col"
                                class="px-5 py-3 text-right font-medium"
                            >
                                Tindakan
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        <tr
                            v-for="(record, rowIndex) in records.data"
                            :key="record.id"
                            class="transition-colors hover:bg-muted/25"
                        >
                            <td class="px-5 py-4 text-xs tabular-nums">
                                {{ (records.from ?? 1) + rowIndex }}
                            </td>
                            <th
                                scope="row"
                                class="max-w-64 px-4 py-4 font-semibold break-words"
                            >
                                {{ record.name }}
                            </th>
                            <td
                                class="max-w-48 px-4 py-4 text-xs leading-6 break-words"
                            >
                                {{
                                    (isPmbTest
                                        ? record.registration_number
                                        : record.position) ?? '—'
                                }}
                            </td>
                            <td
                                class="max-w-64 px-4 py-4 text-xs leading-6 break-words"
                            >
                                {{
                                    (isPmbTest
                                        ? record.study_program
                                        : record.institution) ?? '—'
                                }}
                            </td>
                            <template v-if="isPmbTest">
                                <td
                                    class="max-w-64 px-4 py-4 text-xs leading-6 break-words"
                                >
                                    {{ record.school_origin ?? '—' }}
                                </td>
                                <td
                                    class="px-4 py-4 text-xs leading-6 whitespace-nowrap"
                                >
                                    {{ record.phone_number ?? '—' }}
                                </td>
                            </template>
                            <td
                                class="px-4 py-4 text-xs leading-6 text-muted-foreground"
                            >
                                {{ attendanceTimestamp(record.signed_at) }}
                            </td>
                            <td class="px-5 py-4">
                                <span
                                    v-if="failedSignatures.has(record.id)"
                                    class="block w-20 text-[10px] leading-5 text-muted-foreground"
                                    >TTD belum dapat dimuat.</span
                                ><img
                                    v-else
                                    :src="record.signature_url"
                                    :alt="`Tanda tangan ${record.name}`"
                                    width="80"
                                    height="50"
                                    loading="lazy"
                                    decoding="async"
                                    class="h-12.5 w-20 rounded border border-border bg-white object-contain"
                                    @error="failedSignatures.add(record.id)"
                                />
                            </td>
                            <td class="px-5 py-4 text-right">
                                <button
                                    type="button"
                                    class="sisurat-secondary-action min-h-10 border-destructive/25 px-3 text-xs text-destructive hover:bg-destructive/10"
                                    :disabled="busy"
                                    :aria-label="`Hapus peserta ${record.name}`"
                                    @click="openRecordDeletion(record, $event)"
                                >
                                    <Trash2
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />Hapus
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div
                v-else
                class="flex flex-col items-center px-6 py-12 text-center"
            >
                <span class="sisurat-icon-tile"
                    ><ClipboardList class="size-6" aria-hidden="true"
                /></span>
                <h3 class="mt-4 text-sm font-semibold">
                    {{
                        filters.q
                            ? 'Peserta tidak ditemukan'
                            : 'Belum ada peserta'
                    }}
                </h3>
                <p
                    class="mt-2 max-w-md text-xs leading-7 text-muted-foreground"
                >
                    {{
                        filters.q
                            ? 'Coba kata kunci lain atau hapus filter pencarian.'
                            : event.is_open
                              ? 'Bagikan tautan kegiatan kepada peserta. Klik Perbarui untuk melihat isian terbaru.'
                              : 'Buka kembali pengisian melalui Ubah kegiatan jika peserta masih perlu mengisi.'
                    }}
                </p>
                <button
                    v-if="filters.q"
                    type="button"
                    class="sisurat-secondary-action mt-5"
                    :disabled="busy"
                    @click="resetFilters"
                >
                    Hapus filter
                </button>
            </div>
            <footer
                v-if="records.data.length"
                class="flex flex-wrap items-center justify-between gap-4 border-t border-border/60 px-5 py-4"
            >
                <p class="text-xs text-muted-foreground">
                    {{ records.from }}–{{ records.to }} dari
                    {{ records.total }} peserta{{
                        filters.q ? ' hasil pencarian' : ''
                    }}
                </p>
                <nav
                    class="flex items-center gap-3"
                    aria-label="Halaman peserta"
                >
                    <Link
                        v-if="records.prev_page_url"
                        :href="records.prev_page_url"
                        preserve-scroll
                        class="sisurat-secondary-action min-h-9 px-3 text-xs"
                        :class="{ 'pointer-events-none opacity-50': busy }"
                        :aria-disabled="busy"
                        @click="busy && $event.preventDefault()"
                        ><ArrowLeft
                            class="size-3.5"
                            aria-hidden="true"
                        />Sebelumnya</Link
                    ><span class="text-xs text-muted-foreground"
                        >{{ records.current_page }} /
                        {{ records.last_page }}</span
                    ><Link
                        v-if="records.next_page_url"
                        :href="records.next_page_url"
                        preserve-scroll
                        class="sisurat-secondary-action min-h-9 px-3 text-xs"
                        :class="{ 'pointer-events-none opacity-50': busy }"
                        :aria-disabled="busy"
                        @click="busy && $event.preventDefault()"
                        >Berikutnya<ArrowRight
                            class="size-3.5"
                            aria-hidden="true"
                    /></Link>
                </nav>
            </footer>
        </section>
        <p class="text-xs leading-6 text-muted-foreground">
            Ekspor CSV berisi data peserta dan waktu pengisian. Tanda tangan
            disertakan pada tampilan cetak.
        </p>
        <Dialog :open="eventDeleteOpen" @update:open="updateEventDeleteDialog">
            <DialogContent
                :show-close-button="false"
                class="rounded-2xl"
                @interact-outside="
                    eventDeleteForm.processing && $event.preventDefault()
                "
                @escape-key-down="
                    eventDeleteForm.processing && $event.preventDefault()
                "
                @close-auto-focus="restoreEventDeleteFocus"
            >
                <DialogHeader
                    ><DialogTitle>Hapus kegiatan?</DialogTitle
                    ><DialogDescription
                        >Kegiatan, seluruh data peserta, dan tanda tangannya
                        akan dihapus secara permanen. Tautan pengisian kegiatan
                        tidak dapat dipakai kembali.</DialogDescription
                    ></DialogHeader
                >
                <div class="rounded-xl border border-border bg-muted/40 p-4">
                    <p class="text-sm font-semibold break-words">
                        {{ eventDeleteTarget?.name }}
                    </p>
                    <p class="mt-1 text-xs leading-6 text-muted-foreground">
                        {{ eventDeleteTarget?.entries_count ?? 0 }} peserta akan
                        ikut dihapus.
                    </p>
                </div>
                <p
                    v-if="eventDeleteError"
                    class="rounded-xl bg-destructive/10 px-4 py-3 text-sm text-destructive"
                    role="alert"
                >
                    {{ eventDeleteError }}
                </p>
                <DialogFooter class="gap-2"
                    ><Button
                        type="button"
                        variant="outline"
                        :disabled="eventDeleteForm.processing"
                        @click="updateEventDeleteDialog(false)"
                        >Batal</Button
                    ><Button
                        type="button"
                        variant="destructive"
                        :disabled="eventDeleteForm.processing"
                        :aria-busy="eventDeleteForm.processing"
                        @click="confirmEventDeletion"
                        ><Trash2 class="size-4" aria-hidden="true" />{{
                            eventDeleteForm.processing
                                ? 'Menghapus…'
                                : 'Hapus kegiatan'
                        }}</Button
                    ></DialogFooter
                >
            </DialogContent>
        </Dialog>
        <Dialog
            :open="recordDeleteOpen"
            @update:open="updateRecordDeleteDialog"
        >
            <DialogContent
                :show-close-button="false"
                class="rounded-2xl"
                @interact-outside="
                    recordDeleteForm.processing && $event.preventDefault()
                "
                @escape-key-down="
                    recordDeleteForm.processing && $event.preventDefault()
                "
                @close-auto-focus="restoreRecordDeleteFocus"
            >
                <DialogHeader
                    ><DialogTitle>Hapus peserta?</DialogTitle
                    ><DialogDescription
                        >Data kehadiran dan tanda tangan peserta ini akan
                        dihapus secara permanen.</DialogDescription
                    ></DialogHeader
                >
                <div class="rounded-xl border border-border bg-muted/40 p-4">
                    <p class="text-sm font-semibold break-words">
                        {{ recordDeleteTarget?.name }}
                    </p>
                    <p
                        v-if="
                            isPmbTest && recordDeleteTarget?.registration_number
                        "
                        class="mt-1 text-xs leading-6 break-words text-muted-foreground"
                    >
                        Nomor registrasi:
                        {{ recordDeleteTarget.registration_number }}
                    </p>
                </div>
                <p
                    v-if="recordDeleteError"
                    class="rounded-xl bg-destructive/10 px-4 py-3 text-sm text-destructive"
                    role="alert"
                >
                    {{ recordDeleteError }}
                </p>
                <DialogFooter class="gap-2"
                    ><Button
                        type="button"
                        variant="outline"
                        :disabled="recordDeleteForm.processing"
                        @click="updateRecordDeleteDialog(false)"
                        >Batal</Button
                    ><Button
                        type="button"
                        variant="destructive"
                        :disabled="recordDeleteForm.processing"
                        :aria-busy="recordDeleteForm.processing"
                        @click="confirmRecordDeletion"
                        ><Trash2 class="size-4" aria-hidden="true" />{{
                            recordDeleteForm.processing
                                ? 'Menghapus…'
                                : 'Hapus peserta'
                        }}</Button
                    ></DialogFooter
                >
            </DialogContent>
        </Dialog>
    </main>
</template>
