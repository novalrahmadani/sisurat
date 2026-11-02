<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    BookOpen,
    Download,
    FileDown,
    FilePlus2,
    Pencil,
    Printer,
    Search,
    Trash2,
    ArrowLeft,
    ArrowRight,
    Files,
    PenLine,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    index,
    create,
    edit,
    destroy,
    exportMethod,
    pdf,
} from '@/routes/outgoing-letters';
import { batch } from '@/routes/letters';
import { useMasterDataDeletion } from '@/lib/masterData';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
type AgendaRow = {
    id: number;
    source: 'sisurat' | 'manual';
    batch_id: number | null;
    recipient: string;
    letter_date: string;
    subject: string;
    letter_number: string;
    type: string;
    is_trial: boolean;
    can_manage: boolean;
};
type Filters = {
    q: string;
    year: number | null;
    from: string;
    to: string;
    type: string;
    source: string;
    status: 'official' | 'trial' | 'all';
};
const props = defineProps<{
    records: {
        data: AgendaRow[];
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
        prev_page_url: string | null;
        next_page_url: string | null;
    };
    filters: Filters;
    stats: { total: number; automatic: number; manual: number };
    years: number[];
    types: Record<string, string>;
}>();
const search = ref({
    ...props.filters,
    year: props.filters.year?.toString() ?? '',
});
const loading = ref(false);
const requestError = ref('');
const exportQuery = computed(() => ({
    ...props.filters,
    year: props.filters.year ?? '',
}));
const {
    deleteForm,
    deleteOpen,
    deleteTarget,
    deleteError,
    requestDeletion,
    updateDeleteDialog,
    restoreDeleteFocus,
    confirmDeletion,
} = useMasterDataDeletion<{ id: number; name: string }>(
    (id) => destroy.url(id),
    'agenda-search',
);
function applyFilters(): void {
    if (loading.value) return;
    requestError.value = '';
    router.get(
        index.url(),
        { ...search.value, q: search.value.q.trim() },
        {
            preserveState: true,
            preserveScroll: true,
            onStart: () => {
                loading.value = true;
            },
            onFinish: () => {
                loading.value = false;
            },
            onError: (errors) => {
                requestError.value = Object.values(errors)
                    .filter(Boolean)
                    .join(' ');
            },
            onNetworkError: () => {
                requestError.value =
                    'Koneksi terputus. Periksa koneksi lalu coba kembali.';
                return false;
            },
            onHttpException: () => {
                requestError.value =
                    'Agenda belum dapat dimuat. Muat ulang halaman dan coba kembali.';
                return false;
            },
        },
    );
}
function resetFilters(): void {
    search.value = {
        q: '',
        year: new Date().getFullYear().toString(),
        from: '',
        to: '',
        type: '',
        source: '',
        status: 'official',
    };
    applyFilters();
}
function dateLabel(value: string): string {
    const [year, month, day] = value.split('-');
    return `${day}/${month}/${year}`;
}
</script>

<template>
    <main class="sisurat-page">
        <Head title="Agenda Surat Keluar" />
        <div class="mx-auto flex w-full max-w-7xl flex-col gap-6">
            <header class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="sisurat-kicker">Buku agenda · Sekretariat PMB</p>
                    <h1 class="sisurat-title mt-2">Agenda Surat Keluar</h1>
                    <p class="sisurat-subtitle mt-2 max-w-2xl">
                        Surat dari SISURAT tercatat otomatis. Lengkapi buku
                        agenda dengan surat manual yang sudah diterbitkan.
                    </p>
                </div>
                <Link :href="create()" class="sisurat-primary-action"
                    ><FilePlus2 class="size-4" aria-hidden="true" />Catat surat
                    manual</Link
                >
            </header>
            <section
                class="grid gap-3 sm:grid-cols-3"
                aria-label="Ringkasan agenda"
            >
                <div
                    v-for="card in [
                        {
                            label: 'Surat sesuai filter',
                            value: stats.total,
                            icon: BookOpen,
                        },
                        {
                            label: 'Otomatis dari SISURAT',
                            value: stats.automatic,
                            icon: Files,
                        },
                        {
                            label: 'Dicatat manual',
                            value: stats.manual,
                            icon: PenLine,
                        },
                    ]"
                    :key="card.label"
                    class="sisurat-panel flex items-center justify-between gap-3 p-5"
                >
                    <div>
                        <p class="text-xs text-muted-foreground">
                            {{ card.label }}
                        </p>
                        <p class="mt-2 text-3xl font-semibold">
                            {{ card.value.toLocaleString('id-ID') }}
                        </p>
                    </div>
                    <component
                        :is="card.icon"
                        class="size-6 text-primary"
                        aria-hidden="true"
                    />
                </div>
            </section>
            <form
                class="sisurat-panel p-5 sm:p-6"
                aria-label="Filter agenda"
                @submit.prevent="applyFilters"
            >
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="sm:col-span-2">
                        <label
                            for="agenda-search"
                            class="mb-2 block text-xs font-medium"
                            >Cari surat</label
                        ><input
                            id="agenda-search"
                            v-model="search.q"
                            type="search"
                            maxlength="200"
                            placeholder="Nomor surat, alamat/penerima, perihal, atau nama petugas"
                            class="sisurat-input"
                        />
                    </div>
                    <div>
                        <label
                            for="agenda-year"
                            class="mb-2 block text-xs font-medium"
                            >Tahun</label
                        ><select
                            id="agenda-year"
                            v-model="search.year"
                            class="sisurat-input"
                        >
                            <option value="">Semua tahun</option>
                            <option
                                v-for="year in years"
                                :key="year"
                                :value="String(year)"
                            >
                                {{ year }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label
                            for="agenda-status"
                            class="mb-2 block text-xs font-medium"
                            >Status surat</label
                        ><select
                            id="agenda-status"
                            v-model="search.status"
                            class="sisurat-input"
                        >
                            <option value="official">Surat resmi</option>
                            <option value="trial">Uji coba</option>
                            <option value="all">Resmi dan uji coba</option>
                        </select>
                    </div>
                    <div>
                        <label
                            for="agenda-from"
                            class="mb-2 block text-xs font-medium"
                            >Dari tanggal</label
                        ><input
                            id="agenda-from"
                            v-model="search.from"
                            type="date"
                            class="sisurat-input"
                        />
                    </div>
                    <div>
                        <label
                            for="agenda-to"
                            class="mb-2 block text-xs font-medium"
                            >Sampai tanggal</label
                        ><input
                            id="agenda-to"
                            v-model="search.to"
                            type="date"
                            class="sisurat-input"
                        />
                    </div>
                    <div>
                        <label
                            for="agenda-type"
                            class="mb-2 block text-xs font-medium"
                            >Jenis surat</label
                        ><select
                            id="agenda-type"
                            v-model="search.type"
                            class="sisurat-input"
                        >
                            <option value="">Semua jenis</option>
                            <option
                                v-for="(label, key) in types"
                                :key="key"
                                :value="key"
                            >
                                {{ label }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label
                            for="agenda-source"
                            class="mb-2 block text-xs font-medium"
                            >Sumber pencatatan</label
                        ><select
                            id="agenda-source"
                            v-model="search.source"
                            class="sisurat-input"
                        >
                            <option value="">Semua sumber</option>
                            <option value="sisurat">Otomatis SISURAT</option>
                            <option value="manual">Surat manual</option>
                        </select>
                    </div>
                </div>
                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <button
                        type="submit"
                        class="sisurat-primary-action"
                        :disabled="loading"
                    >
                        <Search class="size-4" aria-hidden="true" />{{
                            loading ? 'Memuat…' : 'Terapkan filter'
                        }}</button
                    ><button
                        type="button"
                        class="sisurat-secondary-action"
                        :disabled="loading"
                        @click="resetFilters"
                    >
                        Reset filter
                    </button>
                    <p class="text-xs leading-6 text-muted-foreground">
                        Agenda diurutkan berdasarkan tanggal dan nomor surat.
                    </p>
                </div>
                <p
                    v-if="requestError"
                    role="alert"
                    class="mt-3 text-sm text-destructive"
                >
                    {{ requestError }}
                </p>
            </form>
            <section
                class="sisurat-panel overflow-hidden"
                aria-label="Daftar agenda surat keluar"
            >
                <div
                    class="flex flex-wrap items-start justify-between gap-4 border-b border-border p-5 sm:p-6"
                >
                    <div>
                        <h2 class="font-semibold">Buku Agenda Surat Keluar</h2>
                        <p class="mt-2 text-xs leading-6 text-muted-foreground">
                            {{
                                filters.year
                                    ? 'Tahun ' + filters.year
                                    : 'Semua tahun'
                            }}
                            ·
                            {{ records.total.toLocaleString('id-ID') }} surat.
                            Unduhan mencakup seluruh hasil filter, termasuk
                            halaman lain.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a
                            :href="
                                pdf.url({
                                    query: { ...exportQuery, inline: 1 },
                                })
                            "
                            target="_blank"
                            rel="noopener noreferrer"
                            class="sisurat-secondary-action"
                            ><Printer class="size-4" aria-hidden="true" />Cetak
                            / Lihat PDF</a
                        ><a
                            :href="pdf.url({ query: exportQuery })"
                            target="_blank"
                            rel="noopener noreferrer"
                            download
                            class="sisurat-primary-action"
                            ><FileDown class="size-4" aria-hidden="true" />Unduh
                            PDF</a
                        ><a
                            :href="exportMethod.url({ query: exportQuery })"
                            class="sisurat-secondary-action"
                            ><Download class="size-4" aria-hidden="true" />Unduh
                            CSV</a
                        >
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[950px] table-fixed text-sm">
                        <caption class="sr-only">
                            Agenda surat keluar sesuai periode dan filter yang
                            dipilih
                        </caption>
                        <thead
                            class="bg-muted/60 text-xs text-muted-foreground"
                        >
                            <tr>
                                <th
                                    scope="col"
                                    class="w-[5%] px-3 py-4 text-center"
                                >
                                    No.
                                </th>
                                <th
                                    scope="col"
                                    class="w-[24%] px-4 py-4 text-left"
                                >
                                    Alamat/Penerima
                                </th>
                                <th
                                    scope="col"
                                    class="w-[12%] px-3 py-4 text-left"
                                >
                                    Tanggal
                                </th>
                                <th
                                    scope="col"
                                    class="w-[23%] px-4 py-4 text-left"
                                >
                                    Perihal
                                </th>
                                <th
                                    scope="col"
                                    class="w-[25%] px-4 py-4 text-left"
                                >
                                    Nomor Surat
                                </th>
                                <th
                                    scope="col"
                                    class="w-[11%] px-3 py-4 text-right"
                                >
                                    Tindakan
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="(row, position) in records.data"
                                :key="`${row.source}-${row.id}`"
                                class="align-top"
                            >
                                <td
                                    class="px-3 py-5 text-center text-muted-foreground"
                                >
                                    {{ (records.from ?? 1) + position }}.
                                </td>
                                <td class="px-4 py-5">
                                    <p class="font-medium break-words">
                                        {{ row.recipient }}
                                    </p>
                                    <span
                                        class="mt-2 inline-flex rounded-full bg-muted px-2 py-1 text-[10px] text-muted-foreground"
                                        >{{
                                            row.source === 'manual'
                                                ? row.type === 'finance'
                                                    ? 'Keuangan · Catatan manual'
                                                    : 'Catatan manual'
                                                : 'SISURAT'
                                        }}</span
                                    ><span
                                        v-if="row.is_trial"
                                        class="ml-1 inline-flex rounded-full bg-amber-500/10 px-2 py-1 text-[10px] text-amber-800 dark:text-amber-200"
                                        >Uji coba</span
                                    >
                                </td>
                                <td class="px-3 py-5 whitespace-nowrap">
                                    {{ dateLabel(row.letter_date) }}
                                </td>
                                <td class="px-4 py-5 break-words">
                                    {{ row.subject }}
                                </td>
                                <td class="px-4 py-5 font-medium break-all">
                                    {{ row.letter_number }}
                                </td>
                                <td class="px-3 py-4">
                                    <div class="flex flex-col items-end gap-2">
                                        <Link
                                            v-if="
                                                row.source === 'sisurat' &&
                                                row.batch_id
                                            "
                                            :href="batch(row.batch_id)"
                                            class="sisurat-secondary-action min-h-9 px-2 text-xs"
                                            >Lihat surat</Link
                                        ><template v-else-if="row.can_manage"
                                            ><Link
                                                :href="edit(row.id)"
                                                class="sisurat-secondary-action min-h-9 px-2 text-xs"
                                                ><Pencil
                                                    class="size-3.5"
                                                    aria-hidden="true"
                                                />Ubah</Link
                                            ><button
                                                type="button"
                                                :aria-label="
                                                    'Hapus catatan ' +
                                                    row.letter_number
                                                "
                                                class="inline-flex min-h-9 items-center gap-1 rounded-lg px-2 text-xs text-destructive hover:bg-destructive/10"
                                                :disabled="
                                                    deleteForm.processing
                                                "
                                                @click="
                                                    requestDeletion(
                                                        {
                                                            id: row.id,
                                                            name: row.letter_number,
                                                        },
                                                        $event,
                                                    )
                                                "
                                            >
                                                <Trash2
                                                    class="size-3.5"
                                                    aria-hidden="true"
                                                />Hapus
                                            </button></template
                                        ><span
                                            v-else
                                            class="text-xs text-muted-foreground"
                                            >—</span
                                        >
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!records.data.length">
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <BookOpen
                                        class="mx-auto size-8 text-primary/50"
                                        aria-hidden="true"
                                    />
                                    <p class="mt-4 font-semibold">
                                        Belum ada surat sesuai filter
                                    </p>
                                    <p
                                        class="mt-2 text-sm text-muted-foreground"
                                    >
                                        Ubah filter atau catat surat manual yang
                                        sudah diterbitkan.
                                    </p>
                                    <Link
                                        :href="create()"
                                        class="sisurat-primary-action mt-5"
                                        >Catat surat manual</Link
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <nav
                    class="flex flex-wrap items-center justify-between gap-3 border-t border-border px-5 py-4 text-xs text-muted-foreground"
                    aria-label="Halaman agenda"
                >
                    <p>
                        {{ records.from ?? 0 }}–{{ records.to ?? 0 }} dari
                        {{ records.total }} surat
                    </p>
                    <div class="flex items-center gap-3">
                        <Link
                            v-if="records.prev_page_url"
                            :href="records.prev_page_url"
                            preserve-scroll
                            class="sisurat-secondary-action min-h-9 px-3"
                            aria-label="Halaman sebelumnya"
                            ><ArrowLeft
                                class="size-4"
                                aria-hidden="true" /></Link
                        ><span
                            >{{ records.current_page }} /
                            {{ records.last_page }}</span
                        ><Link
                            v-if="records.next_page_url"
                            :href="records.next_page_url"
                            preserve-scroll
                            class="sisurat-secondary-action min-h-9 px-3"
                            aria-label="Halaman berikutnya"
                            ><ArrowRight class="size-4" aria-hidden="true"
                        /></Link>
                    </div>
                </nav>
            </section>
        </div>
        <Dialog :open="deleteOpen" @update:open="updateDeleteDialog"
            ><DialogContent @close-auto-focus="restoreDeleteFocus"
                ><DialogHeader
                    ><DialogTitle>Hapus catatan surat manual?</DialogTitle
                    ><DialogDescription
                        >Catatan {{ deleteTarget?.name }} akan dihapus dari
                        agenda. Nomornya tetap dicadangkan agar tidak terpakai
                        ulang.</DialogDescription
                    ></DialogHeader
                >
                <p
                    v-if="deleteError"
                    role="alert"
                    class="text-sm text-destructive"
                >
                    {{ deleteError }}
                </p>
                <DialogFooter
                    ><Button
                        variant="outline"
                        :disabled="deleteForm.processing"
                        @click="updateDeleteDialog(false)"
                        >Batal</Button
                    ><Button
                        variant="destructive"
                        :disabled="deleteForm.processing"
                        @click="confirmDeletion"
                        >{{
                            deleteForm.processing
                                ? 'Menghapus…'
                                : 'Hapus catatan'
                        }}</Button
                    ></DialogFooter
                ></DialogContent
            ></Dialog
        >
    </main>
</template>
