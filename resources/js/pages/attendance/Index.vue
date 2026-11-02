<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    CalendarDays,
    ClipboardList,
    Link2,
    MapPin,
    Plus,
    Search,
    Trash2,
    Users,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    attendanceCreateRoute,
    attendanceDate,
    attendanceIndexRoute,
    attendanceTypeLabel,
    type AttendanceEvent,
    type AttendanceType,
} from '@/lib/attendance';
import {
    useMasterDataDeletion,
    type MasterDataPagination,
} from '@/lib/masterData';
import { destroy, show } from '@/routes/attendance';

type AttendanceStatus = 'all' | 'open' | 'closed';
type EventDeletionTarget = { id: number; name: string; entries_count: number };
const props = defineProps<{
    attendanceType: AttendanceType;
    events: MasterDataPagination<AttendanceEvent>;
    filters: { q: string; status: AttendanceStatus };
    stats: { events: number; open: number; participants: number };
}>();
const query = ref(props.filters.q);
const status = ref<AttendanceStatus>(props.filters.status);
const searching = ref(false);
const requestError = ref('');
const {
    deleteForm,
    deleteOpen,
    deleteTarget,
    deleteError,
    requestDeletion,
    updateDeleteDialog,
    restoreDeleteFocus,
    confirmDeletion,
} = useMasterDataDeletion<EventDeletionTarget>(
    (id) =>
        destroy.url(id, {
            query: {
                q: props.filters.q || undefined,
                status: props.filters.status,
                page: props.events.current_page,
            },
        }),
    'attendance-create',
);
const busy = computed(() => searching.value || deleteForm.processing);
const categories: AttendanceType[] = ['meeting', 'pmb_test', 'other'];
const categoryLabel = computed(() => attendanceTypeLabel(props.attendanceType));
const pageTitle = computed(() => `Daftar Hadir ${categoryLabel.value}`);
const createLabel = computed(() =>
    props.attendanceType === 'pmb_test'
        ? 'Buat tes PMB'
        : props.attendanceType === 'other'
          ? 'Buat kegiatan'
          : 'Buat rapat',
);
watch(
    () => [props.attendanceType, props.filters] as const,
    ([, filters]) => {
        query.value = filters.q;
        status.value = filters.status;
        requestError.value = '';
    },
);

function search(): void {
    if (busy.value) return;
    requestError.value = '';
    router.get(
        attendanceIndexRoute(props.attendanceType).url,
        { q: query.value.trim() || undefined, status: status.value },
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
                    'Daftar kegiatan belum dapat dimuat. Muat ulang halaman atau coba kembali.';
                return false;
            },
        },
    );
}

function resetFilters(): void {
    if (busy.value) return;
    query.value = '';
    status.value = 'all';
    search();
}

function requestEventDeletion(record: AttendanceEvent, event: Event): void {
    if (busy.value) return;
    requestDeletion(
        {
            id: record.id,
            name: record.title,
            entries_count: record.entries_count,
        },
        event,
    );
}
</script>

<template>
    <main class="sisurat-page space-y-6">
        <Head :title="pageTitle" />
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="sisurat-kicker">
                    Daftar Hadir Online · {{ categoryLabel }}
                </p>
                <h1 class="sisurat-title mt-2">{{ pageTitle }}</h1>
                <p class="sisurat-subtitle mt-2">
                    {{
                        attendanceType === 'pmb_test'
                            ? 'Kelola nama, nomor registrasi, program studi, dan tanda tangan peserta tes Penerimaan Mahasiswa Baru.'
                            : attendanceType === 'other'
                              ? 'Kelola kehadiran peserta seminar, sosialisasi, dan kegiatan lainnya dengan tanda tangan dari ponsel.'
                              : 'Kelola kehadiran peserta rapat dan tanda tangan langsung dari ponsel.'
                    }}
                </p>
            </div>
            <Link
                id="attendance-create"
                :href="attendanceCreateRoute(attendanceType)"
                class="sisurat-primary-action"
                :class="{ 'pointer-events-none opacity-50': busy }"
                :aria-disabled="busy"
                @click="busy && $event.preventDefault()"
                ><Plus class="size-4" aria-hidden="true" />{{
                    createLabel
                }}</Link
            >
        </header>

        <nav
            class="flex w-fit flex-wrap gap-2 rounded-xl border border-border bg-card p-1.5"
            aria-label="Jenis daftar hadir"
        >
            <Link
                v-for="category in categories"
                :key="category"
                :href="attendanceIndexRoute(category)"
                :aria-current="attendanceType === category ? 'page' : undefined"
                class="inline-flex min-h-10 items-center justify-center rounded-lg px-4 py-2 text-sm font-medium transition-colors focus-visible:ring-3 focus-visible:ring-ring/30 focus-visible:outline-none"
                :class="[
                    attendanceType === category
                        ? 'bg-[#005a36] text-white shadow-sm'
                        : 'text-muted-foreground hover:bg-accent hover:text-foreground',
                    { 'pointer-events-none opacity-50': busy },
                ]"
                :aria-disabled="busy"
                @click="busy && $event.preventDefault()"
                >{{ attendanceTypeLabel(category) }}</Link
            >
        </nav>

        <section
            class="grid gap-4 sm:grid-cols-3"
            aria-label="Ringkasan daftar hadir"
        >
            <article
                class="sisurat-panel flex items-center justify-between gap-3 px-5 py-4"
            >
                <div>
                    <p class="text-xs text-muted-foreground">
                        Total {{ categoryLabel.toLowerCase() }}
                    </p>
                    <strong
                        class="mt-2 block text-2xl font-semibold tabular-nums"
                        >{{ stats.events.toLocaleString('id-ID') }}</strong
                    >
                </div>
                <CalendarDays class="size-6 text-primary" aria-hidden="true" />
            </article>
            <article
                class="sisurat-panel flex items-center justify-between gap-3 px-5 py-4"
            >
                <div>
                    <p class="text-xs text-muted-foreground">
                        Pendaftaran dibuka
                    </p>
                    <strong
                        class="mt-2 block text-2xl font-semibold tabular-nums"
                        >{{ stats.open.toLocaleString('id-ID') }}</strong
                    >
                </div>
                <Link2 class="size-6 text-primary" aria-hidden="true" />
            </article>
            <article
                class="sisurat-panel flex items-center justify-between gap-3 px-5 py-4"
            >
                <div>
                    <p class="text-xs text-muted-foreground">
                        Peserta tercatat
                    </p>
                    <strong
                        class="mt-2 block text-2xl font-semibold tabular-nums"
                        >{{
                            stats.participants.toLocaleString('id-ID')
                        }}</strong
                    >
                </div>
                <Users class="size-6 text-primary" aria-hidden="true" />
            </article>
        </section>

        <section
            class="sisurat-panel grid gap-4 p-5 sm:grid-cols-3 sm:p-6"
            aria-label="Cara menggunakan daftar hadir"
        >
            <div class="flex gap-3">
                <span
                    class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary"
                    >1</span
                >
                <div>
                    <h2 class="text-sm font-semibold">{{ createLabel }}</h2>
                    <p class="mt-1 text-xs leading-6 text-muted-foreground">
                        Isi nama, tanggal, dan tempat kegiatan.
                    </p>
                </div>
            </div>
            <div class="flex gap-3">
                <span
                    class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary"
                    >2</span
                >
                <div>
                    <h2 class="text-sm font-semibold">Bagikan tautan</h2>
                    <p class="mt-1 text-xs leading-6 text-muted-foreground">
                        Peserta mengisi data dan menandatangani di layar.
                    </p>
                </div>
            </div>
            <div class="flex gap-3">
                <span
                    class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary"
                    >3</span
                >
                <div>
                    <h2 class="text-sm font-semibold">Lihat peserta</h2>
                    <p class="mt-1 text-xs leading-6 text-muted-foreground">
                        Pantau kehadiran, cetak, atau ekspor data peserta.
                    </p>
                </div>
            </div>
        </section>

        <section
            class="sisurat-panel p-5 sm:p-6"
            aria-label="Pencarian kegiatan"
        >
            <form @submit.prevent="search">
                <fieldset
                    :disabled="busy"
                    class="flex min-w-0 flex-wrap items-end gap-3"
                >
                    <div class="min-w-0 flex-1 basis-64">
                        <label
                            for="attendance-search"
                            class="mb-2 block text-sm font-medium"
                            >Cari kegiatan</label
                        >
                        <div class="relative">
                            <Search
                                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                aria-hidden="true"
                            /><input
                                id="attendance-search"
                                v-model="query"
                                type="search"
                                maxlength="200"
                                class="sisurat-input pl-10"
                                placeholder="Nama atau tempat kegiatan"
                            />
                        </div>
                    </div>
                    <div class="min-w-40">
                        <label
                            for="attendance-status"
                            class="mb-2 block text-sm font-medium"
                            >Status pendaftaran</label
                        ><select
                            id="attendance-status"
                            v-model="status"
                            class="sisurat-input"
                        >
                            <option value="all">Semua status</option>
                            <option value="open">Dibuka</option>
                            <option value="closed">Ditutup</option>
                        </select>
                    </div>
                    <button
                        type="submit"
                        class="sisurat-primary-action"
                        :aria-busy="searching"
                    >
                        <Search class="size-4" aria-hidden="true" />{{
                            searching ? 'Mencari…' : 'Cari'
                        }}
                    </button>
                    <button
                        v-if="query || status !== 'all'"
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
        </section>

        <section
            class="sisurat-panel overflow-hidden"
            aria-labelledby="attendance-list-heading"
            :aria-busy="busy"
        >
            <header
                class="flex items-center justify-between gap-3 border-b border-border/60 px-5 py-4"
            >
                <h2 id="attendance-list-heading" class="text-sm font-semibold">
                    Daftar {{ categoryLabel.toLowerCase() }}
                </h2>
                <span class="text-xs text-muted-foreground"
                    >{{ events.total.toLocaleString('id-ID') }} kegiatan</span
                >
            </header>
            <div v-if="events.data.length" class="divide-y divide-border/60">
                <article
                    v-for="event in events.data"
                    :key="event.id"
                    class="flex flex-col gap-4 p-5 transition-colors hover:bg-muted/20 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3
                                class="min-w-0 text-sm font-semibold break-words"
                            >
                                {{ event.title }}
                            </h3>
                            <span
                                class="rounded-full px-2.5 py-1 text-[11px] font-medium"
                                :class="
                                    event.is_open
                                        ? 'bg-primary/10 text-primary'
                                        : 'bg-muted text-muted-foreground'
                                "
                                >{{
                                    event.is_open ? 'Dibuka' : 'Ditutup'
                                }}</span
                            >
                        </div>
                        <div
                            class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-xs leading-6 text-muted-foreground"
                        >
                            <span class="inline-flex items-center gap-1.5"
                                ><CalendarDays
                                    class="size-3.5 shrink-0"
                                    aria-hidden="true"
                                />{{ attendanceDate(event.event_date) }}</span
                            ><span
                                v-if="event.location"
                                class="inline-flex min-w-0 items-center gap-1.5 break-words"
                                ><MapPin
                                    class="size-3.5 shrink-0"
                                    aria-hidden="true"
                                />{{ event.location }}</span
                            ><span class="inline-flex items-center gap-1.5"
                                ><Users
                                    class="size-3.5 shrink-0"
                                    aria-hidden="true"
                                />{{
                                    event.entries_count.toLocaleString('id-ID')
                                }}
                                peserta</span
                            >
                        </div>
                    </div>
                    <div
                        class="flex shrink-0 flex-wrap gap-2 self-start sm:self-auto"
                    >
                        <Link
                            :href="show(event.id)"
                            class="sisurat-secondary-action"
                            :class="{ 'pointer-events-none opacity-50': busy }"
                            :aria-disabled="busy"
                            :aria-label="`Kelola daftar hadir ${event.title}`"
                            @click="busy && $event.preventDefault()"
                            >Kelola daftar hadir<ArrowRight
                                class="size-4"
                                aria-hidden="true"
                        /></Link>
                        <button
                            type="button"
                            class="sisurat-secondary-action border-destructive/25 text-destructive hover:bg-destructive/10"
                            :disabled="busy"
                            :aria-label="`Hapus kegiatan ${event.title}`"
                            @click="requestEventDeletion(event, $event)"
                        >
                            <Trash2 class="size-4" aria-hidden="true" />Hapus
                            kegiatan
                        </button>
                    </div>
                </article>
            </div>
            <div
                v-else
                class="flex flex-col items-center px-6 py-14 text-center"
            >
                <span class="sisurat-icon-tile"
                    ><ClipboardList class="size-6" aria-hidden="true"
                /></span>
                <h3 class="mt-4 text-sm font-semibold">
                    {{
                        filters.q || filters.status !== 'all'
                            ? 'Kegiatan tidak ditemukan'
                            : `Belum ada ${categoryLabel.toLowerCase()}`
                    }}
                </h3>
                <p
                    class="mt-2 max-w-md text-xs leading-7 text-muted-foreground"
                >
                    {{
                        filters.q || filters.status !== 'all'
                            ? 'Coba kata kunci lain atau hapus filter pencarian.'
                            : `Buat ${categoryLabel.toLowerCase()} pertama untuk mengumpulkan kehadiran dan tanda tangan peserta.`
                    }}
                </p>
                <button
                    v-if="filters.q || filters.status !== 'all'"
                    type="button"
                    class="sisurat-secondary-action mt-5"
                    :disabled="busy"
                    @click="resetFilters"
                >
                    Hapus filter
                </button>
                <Link
                    v-else
                    :href="attendanceCreateRoute(attendanceType)"
                    class="sisurat-primary-action mt-5"
                    :class="{ 'pointer-events-none opacity-50': busy }"
                    :aria-disabled="busy"
                    @click="busy && $event.preventDefault()"
                    ><Plus class="size-4" aria-hidden="true" />{{
                        createLabel
                    }}</Link
                >
            </div>
        </section>

        <nav
            v-if="events.data.length"
            class="flex flex-wrap items-center justify-between gap-4"
            aria-label="Halaman kegiatan"
        >
            <p class="text-xs text-muted-foreground">
                {{ events.from }}–{{ events.to }} dari
                {{ events.total }} kegiatan
            </p>
            <div class="flex items-center gap-3">
                <Link
                    v-if="events.prev_page_url"
                    :href="events.prev_page_url"
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
                    >{{ events.current_page }} / {{ events.last_page }}</span
                ><Link
                    v-if="events.next_page_url"
                    :href="events.next_page_url"
                    preserve-scroll
                    class="sisurat-secondary-action min-h-9 px-3 text-xs"
                    :class="{ 'pointer-events-none opacity-50': busy }"
                    :aria-disabled="busy"
                    @click="busy && $event.preventDefault()"
                    >Berikutnya<ArrowRight class="size-3.5" aria-hidden="true"
                /></Link>
            </div>
        </nav>
        <Dialog :open="deleteOpen" @update:open="updateDeleteDialog">
            <DialogContent
                :show-close-button="false"
                class="rounded-2xl"
                @interact-outside="
                    deleteForm.processing && $event.preventDefault()
                "
                @escape-key-down="
                    deleteForm.processing && $event.preventDefault()
                "
                @close-auto-focus="restoreDeleteFocus"
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
                        {{ deleteTarget?.name }}
                    </p>
                    <p class="mt-1 text-xs leading-6 text-muted-foreground">
                        {{ deleteTarget?.entries_count ?? 0 }} peserta akan ikut
                        dihapus.
                    </p>
                </div>
                <p
                    v-if="deleteError"
                    class="rounded-xl bg-destructive/10 px-4 py-3 text-sm text-destructive"
                    role="alert"
                >
                    {{ deleteError }}
                </p>
                <DialogFooter class="gap-2"
                    ><Button
                        type="button"
                        variant="outline"
                        :disabled="deleteForm.processing"
                        @click="updateDeleteDialog(false)"
                        >Batal</Button
                    ><Button
                        type="button"
                        variant="destructive"
                        :disabled="deleteForm.processing"
                        :aria-busy="deleteForm.processing"
                        @click="confirmDeletion"
                        ><Trash2 class="size-4" aria-hidden="true" />{{
                            deleteForm.processing
                                ? 'Menghapus…'
                                : 'Hapus kegiatan'
                        }}</Button
                    ></DialogFooter
                >
            </DialogContent>
        </Dialog>
    </main>
</template>
