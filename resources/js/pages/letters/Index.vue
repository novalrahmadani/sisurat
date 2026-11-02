<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    FilePlus2,
    Copy,
    Files,
    Search,
    Trash2,
    X,
} from '@lucide/vue';
import { computed, ref, shallowRef, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { institutionLabels, letterTypeLabels } from '@/lib/letters';
import type { InstitutionType, LetterType } from '@/lib/letters';
import { useMasterDataDeletion } from '@/lib/masterData';
import {
    batch as showBatch,
    create as createLetter,
    index as lettersIndex,
} from '@/routes/letters';
import {
    destroy as destroyBatch,
    destroySelected as destroySelectedBatches,
} from '@/actions/App/Http/Controllers/LetterBatchController';

type LetterBatch = {
    id: number;
    type: LetterType;
    letter_date: string;
    first_sequence: number;
    number_prefix: string;
    subject?: string | null;
    letters_count: number;
    source_form_url?: string | null;
    can_delete: boolean;
    is_trial?: boolean;
};
type DeletionTarget = LetterBatch & { name: string };
type Pagination = {
    data: LetterBatch[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
type Filters = {
    q: string;
    type: LetterType | '';
    destination: InstitutionType | '';
};
const props = defineProps<{
    batches: Pagination;
    filters: Filters;
    stats: { lettersThisYear: number; batchesThisYear: number };
}>();
const filters = ref<Filters>({ ...props.filters });
const searching = ref(false);
const deleteTrigger = shallowRef<HTMLElement | null>(null);
const selectedBatchIds = ref<number[]>([]);
const bulkDeleteForm = useForm({ batch_ids: [] as number[] });
const bulkDeleteOpen = ref(false);
const bulkDeleteError = ref('');
const bulkDeleteTargets = shallowRef<LetterBatch[]>([]);
const selectableBatches = computed(() =>
    props.batches.data.filter((batch) => batch.can_delete),
);
const selectedBatches = computed(() =>
    selectableBatches.value.filter((batch) =>
        selectedBatchIds.value.includes(batch.id),
    ),
);
const selectedLettersCount = computed(() =>
    selectedBatches.value.reduce(
        (total, batch) => total + batch.letters_count,
        0,
    ),
);
const allPageSelected = computed(
    () =>
        selectableBatches.value.length > 0 &&
        selectedBatches.value.length === selectableBatches.value.length,
);
const somePageSelected = computed(
    () => selectedBatches.value.length > 0 && !allPageSelected.value,
);
const bulkDeleteLettersCount = computed(() =>
    bulkDeleteTargets.value.reduce(
        (total, batch) => total + batch.letters_count,
        0,
    ),
);
const {
    deleteForm,
    deleteOpen,
    deleteTarget,
    deleteError,
    requestDeletion,
    updateDeleteDialog,
    confirmDeletion,
} = useMasterDataDeletion<DeletionTarget>(
    (id) =>
        destroyBatch.url(
            { batch: id },
            {
                query: {
                    q: props.filters.q || undefined,
                    type: props.filters.type || undefined,
                    destination: props.filters.destination || undefined,
                    page: props.batches.current_page,
                },
            },
        ),
    'history-heading',
);
const archiveBusy = computed(
    () => searching.value || deleteForm.processing || bulkDeleteForm.processing,
);
watch(
    () => props.batches,
    () => {
        selectedBatchIds.value = [];
    },
);
watch(
    () => props.filters,
    (value) => {
        filters.value = { ...value };
    },
);
watch(
    () => filters.value.type,
    (type) => {
        if (type === 'pkkmb') {
            filters.value.destination = '';
        }
    },
);

function search(): void {
    if (archiveBusy.value) return;
    router.get(
        lettersIndex.url(),
        {
            q: filters.value.q || undefined,
            type: filters.value.type || undefined,
            destination: filters.value.destination || undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            onStart: () => {
                searching.value = true;
            },
            onFinish: () => {
                searching.value = false;
            },
        },
    );
}
function resetFilters(): void {
    filters.value = { q: '', type: '', destination: '' };
    search();
}
function formatDate(value: string): string {
    return new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(new Date(`${value.slice(0, 10)}T00:00:00`));
}

function requestBatchDeletion(batch: LetterBatch, event: Event): void {
    if (!batch.can_delete || archiveBusy.value) return;
    deleteTrigger.value = event.currentTarget as HTMLElement;
    requestDeletion(
        {
            ...batch,
            name: `${letterTypeLabels[batch.type]} · Batch #${batch.id}`,
        },
        event,
    );
}

function toggleBatch(batch: LetterBatch, event: Event): void {
    if (!batch.can_delete || archiveBusy.value) return;
    const checked = (event.target as HTMLInputElement).checked;
    selectedBatchIds.value = checked
        ? [...selectedBatchIds.value.filter((id) => id !== batch.id), batch.id]
        : selectedBatchIds.value.filter((id) => id !== batch.id);
}

function togglePageSelection(event: Event): void {
    if (archiveBusy.value) return;
    selectedBatchIds.value = (event.target as HTMLInputElement).checked
        ? selectableBatches.value.map((batch) => batch.id)
        : [];
}

function requestSelectedDeletion(event: Event): void {
    if (archiveBusy.value || !selectedBatches.value.length) return;
    deleteTrigger.value = event.currentTarget as HTMLElement;
    bulkDeleteTargets.value = [...selectedBatches.value];
    bulkDeleteForm.batch_ids = bulkDeleteTargets.value.map((batch) => batch.id);
    bulkDeleteForm.clearErrors();
    bulkDeleteError.value = '';
    bulkDeleteOpen.value = true;
}

function updateBulkDeleteDialog(open: boolean): void {
    if (bulkDeleteForm.processing) return;
    bulkDeleteOpen.value = open;
    if (!open) {
        bulkDeleteError.value = '';
        bulkDeleteForm.clearErrors();
    }
}

function confirmSelectedDeletion(): void {
    if (archiveBusy.value || !bulkDeleteForm.batch_ids.length) return;
    bulkDeleteError.value = '';
    bulkDeleteForm.delete(
        destroySelectedBatches.url({
            query: {
                q: props.filters.q || undefined,
                type: props.filters.type || undefined,
                destination: props.filters.destination || undefined,
                page: props.batches.current_page,
            },
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                bulkDeleteOpen.value = false;
                selectedBatchIds.value = [];
                bulkDeleteTargets.value = [];
                bulkDeleteForm.reset();
            },
            onError: (errors) => {
                bulkDeleteError.value =
                    Object.values(errors).filter(Boolean).join(' ') ||
                    'Batch belum dapat dihapus. Muat ulang arsip dan coba kembali.';
            },
            onNetworkError: () => {
                bulkDeleteError.value =
                    'Koneksi terputus. Periksa koneksi lalu coba kembali.';
                return false;
            },
            onHttpException: () => {
                bulkDeleteError.value =
                    'Batch belum dapat dihapus. Muat ulang arsip dan pilih kembali.';
                return false;
            },
        },
    );
}

function restoreDeleteFocus(event: Event): void {
    event.preventDefault();
    const element = deleteTrigger.value?.isConnected
        ? deleteTrigger.value
        : document.getElementById('history-heading');
    element?.focus({ preventScroll: true });
}
</script>

<template>
    <main class="sisurat-page space-y-6">
        <Head title="Arsip Surat" />
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="sisurat-kicker">Arsip persuratan</p>
                <h1 class="sisurat-title mt-2">Arsip Surat</h1>
                <p class="sisurat-subtitle mt-2">
                    Cari surat tersimpan, cetak ulang, atau gunakan isinya untuk
                    membuat surat serupa.
                </p>
            </div>
            <Link :href="createLetter()" class="sisurat-primary-action"
                ><FilePlus2 class="size-4" aria-hidden="true" />Buat surat
                baru</Link
            >
        </header>
        <section
            class="grid gap-4 sm:grid-cols-2"
            aria-label="Ringkasan surat tahun ini"
        >
            <article
                class="sisurat-panel flex items-center justify-between gap-4 px-5 py-4"
            >
                <span class="text-sm text-muted-foreground"
                    >Surat tahun ini</span
                ><strong class="text-2xl font-semibold tabular-nums">{{
                    stats.lettersThisYear.toLocaleString('id-ID')
                }}</strong>
            </article>
            <article
                class="sisurat-panel flex items-center justify-between gap-4 px-5 py-4"
            >
                <span class="text-sm text-muted-foreground"
                    >Batch tahun ini</span
                ><strong class="text-2xl font-semibold tabular-nums">{{
                    stats.batchesThisYear.toLocaleString('id-ID')
                }}</strong>
            </article>
        </section>
        <section
            class="sisurat-panel p-5 sm:p-6"
            aria-label="Pencarian arsip surat"
        >
            <form
                class="grid items-end gap-4 md:grid-cols-[minmax(0,1fr)_210px_180px_auto]"
                @submit.prevent="search"
            >
                <div>
                    <label
                        for="history-search"
                        class="mb-2 block text-xs font-medium"
                        >Cari surat</label
                    >
                    <div class="relative">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden="true"
                        /><input
                            id="history-search"
                            v-model="filters.q"
                            type="search"
                            maxlength="150"
                            class="sisurat-input pl-10"
                            placeholder="Nomor, perihal, nama tujuan, kabupaten, atau NIM"
                        />
                    </div>
                </div>
                <div>
                    <label
                        for="history-type"
                        class="mb-2 block text-xs font-medium"
                        >Jenis surat</label
                    ><select
                        id="history-type"
                        v-model="filters.type"
                        class="sisurat-input"
                    >
                        <option value="">Semua jenis</option>
                        <option
                            v-for="(label, type) in letterTypeLabels"
                            :key="type"
                            :value="type"
                        >
                            {{ label }}
                        </option>
                    </select>
                </div>
                <div>
                    <label
                        for="history-destination"
                        class="mb-2 block text-xs font-medium"
                        >Tujuan</label
                    ><select
                        id="history-destination"
                        v-model="filters.destination"
                        class="sisurat-input"
                        :disabled="filters.type === 'pkkmb'"
                    >
                        <option value="">
                            {{
                                filters.type === 'pkkmb'
                                    ? 'Mahasiswa PKKMB'
                                    : 'Semua tujuan'
                            }}
                        </option>
                        <option
                            v-for="(label, type) in institutionLabels"
                            :key="type"
                            :value="type"
                        >
                            {{ label }}
                        </option>
                    </select>
                </div>
                <button
                    type="submit"
                    class="sisurat-primary-action"
                    :disabled="archiveBusy"
                >
                    <Search class="size-4" aria-hidden="true" />{{
                        searching ? 'Mencari…' : 'Cari'
                    }}
                </button>
            </form>
            <div
                v-if="filters.q || filters.type || filters.destination"
                class="mt-4 flex items-center justify-between gap-3 border-t border-border pt-4"
            >
                <p class="text-xs text-muted-foreground">
                    {{ batches.total }} batch sesuai pencarian
                </p>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 text-xs font-medium text-primary hover:underline"
                    @click="resetFilters"
                >
                    <X class="size-3.5" aria-hidden="true" />Hapus filter
                </button>
            </div>
        </section>
        <section
            class="sisurat-panel overflow-hidden"
            aria-labelledby="history-heading"
        >
            <div
                class="flex items-center justify-between gap-3 border-b border-border px-5 py-4 sm:px-6"
            >
                <h2
                    id="history-heading"
                    tabindex="-1"
                    class="text-sm font-semibold outline-none"
                >
                    Daftar batch surat
                </h2>
                <span
                    class="rounded-full bg-primary/7 px-3 py-1 text-xs font-medium text-primary"
                    >{{ batches.total }} batch</span
                >
            </div>
            <div v-if="batches.data.length">
                <div
                    class="flex flex-wrap items-center justify-between gap-3 border-b border-border bg-primary/3 px-5 py-4 sm:px-6"
                >
                    <div class="space-y-2">
                        <label
                            class="inline-flex min-h-8 cursor-pointer items-center gap-3 text-xs font-medium"
                        >
                            <input
                                type="checkbox"
                                class="size-4 shrink-0 accent-primary"
                                :checked="allPageSelected"
                                :indeterminate="somePageSelected"
                                :disabled="
                                    archiveBusy || !selectableBatches.length
                                "
                                @change="togglePageSelection"
                            />
                            Pilih semua di halaman ini
                        </label>
                        <p
                            class="text-xs text-muted-foreground"
                            aria-live="polite"
                        >
                            {{ selectedBatches.length }} batch dipilih ·
                            {{ selectedLettersCount.toLocaleString('id-ID') }}
                            surat
                        </p>
                    </div>
                    <div class="flex w-full flex-wrap gap-2 sm:w-auto">
                        <Button
                            v-if="selectedBatches.length"
                            type="button"
                            variant="outline"
                            size="sm"
                            :disabled="archiveBusy"
                            @click="selectedBatchIds = []"
                            >Batal pilih</Button
                        >
                        <Button
                            type="button"
                            variant="destructive"
                            size="sm"
                            :disabled="archiveBusy || !selectedBatches.length"
                            @click="requestSelectedDeletion"
                        >
                            <Trash2 class="size-4" aria-hidden="true" />Hapus
                            batch terpilih
                        </Button>
                    </div>
                </div>
                <div
                    class="hidden grid-cols-[24px_minmax(0,1fr)_160px_80px_190px] gap-4 border-b border-border bg-muted/40 px-6 py-3 text-[11px] font-medium text-muted-foreground md:grid"
                >
                    <span>Pilih</span><span>Jenis dan nomor awal</span
                    ><span>Tanggal surat</span><span>Jumlah</span
                    ><span class="text-right">Tindakan</span>
                </div>
                <div class="divide-y divide-border">
                    <div
                        v-for="item in batches.data"
                        :key="item.id"
                        class="group grid grid-cols-[24px_minmax(0,1fr)] items-center gap-3 px-5 py-5 transition hover:bg-primary/4 sm:px-6 md:grid-cols-[24px_minmax(0,1fr)_160px_80px_190px] md:gap-4"
                        :class="
                            selectedBatchIds.includes(item.id)
                                ? 'bg-primary/5'
                                : ''
                        "
                    >
                        <label
                            class="flex min-h-10 items-center justify-center self-start md:self-center"
                        >
                            <input
                                type="checkbox"
                                class="size-4 accent-primary"
                                :checked="selectedBatchIds.includes(item.id)"
                                :disabled="!item.can_delete || archiveBusy"
                                :aria-label="`Pilih batch ${item.id} berisi ${item.letters_count} surat`"
                                @change="toggleBatch(item, $event)"
                            />
                        </label>
                        <div class="min-w-0">
                            <span
                                v-if="item.is_trial"
                                class="mb-2 inline-flex rounded-full border border-amber-300/50 bg-amber-50 px-2 py-0.5 text-[10px] font-medium text-amber-800 dark:bg-amber-950/30 dark:text-amber-200"
                                >Uji coba</span
                            >
                            <span class="block text-sm font-semibold">{{
                                letterTypeLabels[item.type]
                            }}</span>
                            <span
                                v-if="item.type === 'general' && item.subject"
                                class="mt-1 block truncate text-xs text-foreground"
                                >{{ item.subject }}</span
                            >
                            <span
                                class="mt-1.5 block truncate text-xs text-muted-foreground"
                                >Batch #{{ item.id }} · Mulai
                                {{
                                    String(item.first_sequence).padStart(
                                        3,
                                        '0',
                                    )
                                }}/{{ item.number_prefix }}</span
                            >
                        </div>
                        <span
                            class="col-start-2 text-xs text-muted-foreground md:col-start-auto"
                            >{{ formatDate(item.letter_date) }}</span
                        ><span
                            class="col-start-2 text-xs font-medium md:col-start-auto"
                            >{{ item.letters_count }} surat</span
                        >
                        <div
                            class="col-start-2 flex flex-wrap gap-2 md:col-start-auto md:justify-end"
                        >
                            <Link
                                :href="showBatch({ batch: item.id })"
                                class="sisurat-secondary-action min-h-9! px-3! text-xs!"
                                :aria-label="`Lihat dan cetak batch ${item.id}`"
                                >Lihat / cetak<ArrowRight
                                    class="size-3.5"
                                    aria-hidden="true"
                            /></Link>
                            <Link
                                v-if="item.source_form_url"
                                :href="item.source_form_url"
                                class="sisurat-secondary-action min-h-9! px-3! text-xs!"
                                :aria-label="`Buat surat serupa dari batch ${item.id}`"
                                ><Copy
                                    class="size-3.5"
                                    aria-hidden="true"
                                />Buat surat serupa</Link
                            >
                            <button
                                v-if="item.can_delete"
                                type="button"
                                class="sisurat-secondary-action min-h-9! px-3! text-xs! text-destructive hover:bg-destructive/10"
                                :disabled="archiveBusy"
                                :aria-label="`Hapus batch ${item.id} berisi ${item.letters_count} surat`"
                                @click="requestBatchDeletion(item, $event)"
                            >
                                <Trash2 class="size-3.5" aria-hidden="true" />
                                Hapus batch
                            </button>
                        </div>
                    </div>
                </div>
                <nav
                    class="flex flex-wrap items-center justify-between gap-4 border-t border-border px-5 py-4 sm:px-6"
                    aria-label="Halaman arsip surat"
                >
                    <p class="text-xs text-muted-foreground">
                        {{ batches.from }}–{{ batches.to }} dari
                        {{ batches.total }} batch
                    </p>
                    <div class="flex items-center gap-3">
                        <Link
                            v-if="batches.prev_page_url"
                            :href="batches.prev_page_url"
                            preserve-scroll
                            class="sisurat-secondary-action min-h-9! px-3! text-xs!"
                            ><ArrowLeft
                                class="size-3.5"
                                aria-hidden="true"
                            />Sebelumnya</Link
                        ><span class="text-xs text-muted-foreground"
                            >{{ batches.current_page }} /
                            {{ batches.last_page }}</span
                        ><Link
                            v-if="batches.next_page_url"
                            :href="batches.next_page_url"
                            preserve-scroll
                            class="sisurat-secondary-action min-h-9! px-3! text-xs!"
                            >Berikutnya<ArrowRight
                                class="size-3.5"
                                aria-hidden="true"
                        /></Link>
                    </div>
                </nav>
            </div>
            <div
                v-else
                class="flex flex-col items-center px-6 py-14 text-center"
            >
                <span class="sisurat-icon-tile"
                    ><Files class="size-6" aria-hidden="true"
                /></span>
                <h3 class="mt-4 text-sm font-semibold">
                    {{
                        batches.total === 0 &&
                        (filters.q || filters.type || filters.destination)
                            ? 'Surat belum ditemukan'
                            : 'Belum ada surat tersimpan'
                    }}
                </h3>
                <p
                    class="mt-2 max-w-md text-xs leading-6 text-muted-foreground"
                >
                    {{
                        filters.q || filters.type || filters.destination
                            ? 'Coba kata pencarian lain atau hapus filter untuk melihat semua arsip.'
                            : 'Buat surat pertama. SISURAT akan menyimpan setiap batch agar mudah dibuka dan dicetak kembali.'
                    }}
                </p>
                <button
                    v-if="filters.q || filters.type || filters.destination"
                    type="button"
                    class="sisurat-secondary-action mt-5"
                    @click="resetFilters"
                >
                    Tampilkan semua arsip</button
                ><Link
                    v-else
                    :href="createLetter()"
                    class="sisurat-primary-action mt-5"
                    >Buat surat pertama</Link
                >
            </div>
        </section>
        <Dialog :open="deleteOpen" @update:open="updateDeleteDialog">
            <DialogContent
                :show-close-button="false"
                class="rounded-2xl print:hidden"
                @interact-outside="
                    deleteForm.processing && $event.preventDefault()
                "
                @escape-key-down="
                    deleteForm.processing && $event.preventDefault()
                "
                @close-auto-focus="restoreDeleteFocus"
            >
                <DialogHeader>
                    <DialogTitle>Hapus batch surat?</DialogTitle>
                    <DialogDescription>
                        Semua surat dalam batch ini akan dihapus dari arsip.
                        Nomor surat tetap tercatat dan tidak dipakai ulang.
                    </DialogDescription>
                </DialogHeader>
                <div class="rounded-xl border border-border bg-muted/40 p-4">
                    <p class="text-sm font-semibold break-words">
                        {{ deleteTarget?.name }}
                    </p>
                    <p
                        v-if="deleteTarget"
                        class="mt-2 text-xs leading-6 text-muted-foreground"
                    >
                        {{ formatDate(deleteTarget.letter_date) }} ·
                        {{ deleteTarget.letters_count }} surat
                    </p>
                </div>
                <p
                    v-if="deleteError"
                    role="alert"
                    class="rounded-xl bg-destructive/10 px-4 py-3 text-sm text-destructive"
                >
                    {{ deleteError }}
                </p>
                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="deleteForm.processing"
                        @click="updateDeleteDialog(false)"
                    >
                        Batal
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        :disabled="deleteForm.processing"
                        :aria-busy="deleteForm.processing"
                        @click="confirmDeletion"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                        {{
                            deleteForm.processing ? 'Menghapus…' : 'Hapus batch'
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
        <Dialog :open="bulkDeleteOpen" @update:open="updateBulkDeleteDialog">
            <DialogContent
                :show-close-button="false"
                class="max-h-[90dvh] overflow-y-auto rounded-2xl print:hidden"
                @interact-outside="
                    bulkDeleteForm.processing && $event.preventDefault()
                "
                @escape-key-down="
                    bulkDeleteForm.processing && $event.preventDefault()
                "
                @close-auto-focus="restoreDeleteFocus"
            >
                <DialogHeader>
                    <DialogTitle
                        >Hapus {{ bulkDeleteTargets.length }} batch
                        terpilih?</DialogTitle
                    >
                    <DialogDescription>
                        Seluruh
                        {{ bulkDeleteLettersCount.toLocaleString('id-ID') }}
                        surat dalam batch pilihan akan dihapus dari arsip. Nomor
                        surat tetap dicadangkan dan tidak dipakai ulang.
                    </DialogDescription>
                </DialogHeader>
                <ul
                    class="max-h-56 space-y-2 overflow-y-auto rounded-xl border border-border bg-muted/40 p-4"
                    aria-label="Batch yang akan dihapus"
                >
                    <li
                        v-for="batch in bulkDeleteTargets"
                        :key="batch.id"
                        class="text-xs leading-6"
                    >
                        <span class="font-semibold">Batch #{{ batch.id }}</span>
                        · {{ letterTypeLabels[batch.type] }} ·
                        {{ batch.letters_count }} surat
                    </li>
                </ul>
                <p
                    v-if="bulkDeleteError"
                    role="alert"
                    class="rounded-xl bg-destructive/10 px-4 py-3 text-sm text-destructive"
                >
                    {{ bulkDeleteError }}
                </p>
                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="bulkDeleteForm.processing"
                        @click="updateBulkDeleteDialog(false)"
                        >Batal</Button
                    >
                    <Button
                        type="button"
                        variant="destructive"
                        :disabled="bulkDeleteForm.processing"
                        :aria-busy="bulkDeleteForm.processing"
                        @click="confirmSelectedDeletion"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />{{
                            bulkDeleteForm.processing
                                ? 'Menghapus…'
                                : 'Ya, hapus batch terpilih'
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </main>
</template>

<style>
@media print {
    body {
        overflow: visible !important;
        padding-right: 0 !important;
        margin-right: 0 !important;
    }

    [data-slot='dialog-overlay'],
    [data-slot='dialog-content'] {
        display: none !important;
    }
}
</style>
