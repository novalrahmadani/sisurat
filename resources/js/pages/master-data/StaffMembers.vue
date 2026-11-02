<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Pencil,
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
    useMasterDataDeletion,
    type MasterDataPagination,
    type MasterDistrictOption,
    type MasterStaffMember,
} from '@/lib/masterData';
import { index as masterIndex } from '@/routes/master-data';
import {
    create,
    destroy,
    edit,
    index,
} from '@/routes/master-data/staff-members';

const props = defineProps<{
    records: MasterDataPagination<MasterStaffMember>;
    filters: { q: string; district: string };
    districtOptions: MasterDistrictOption[];
}>();
const filters = ref({ ...props.filters });
const searching = ref(false);
const requestError = ref('');
const hasFilters = computed(() =>
    Boolean(filters.value.q || filters.value.district),
);
watch(
    () => props.filters,
    (value) => {
        filters.value = { ...value };
    },
);
const {
    deleteForm,
    deleteOpen,
    deleteTarget,
    deleteError,
    requestDeletion,
    updateDeleteDialog,
    restoreDeleteFocus,
    confirmDeletion,
} = useMasterDataDeletion<MasterStaffMember>(
    (id) =>
        destroy.url(id, {
            query: {
                q: filters.value.q || undefined,
                district: filters.value.district || undefined,
                page: props.records.current_page,
            },
        }),
    'add-staff',
);
function search(): void {
    if (searching.value || deleteForm.processing) return;
    requestError.value = '';
    router.get(
        index.url(),
        {
            q: filters.value.q.trim() || undefined,
            district: filters.value.district || undefined,
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
                    'Data belum dapat dimuat. Muat ulang halaman atau coba kembali.';
                return false;
            },
        },
    );
}
function resetFilters(): void {
    filters.value.q = '';
    filters.value.district = '';
    search();
}
</script>

<template>
    <main class="sisurat-page space-y-6">
        <Head title="Master Dosen/Petugas" />
        <Link :href="masterIndex()" class="sisurat-secondary-action w-fit"
            ><ArrowLeft class="size-4" aria-hidden="true" />Master Data</Link
        >
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="sisurat-kicker">Master Data · Tim Pelaksana</p>
                <h1 class="sisurat-title mt-2">Dosen / Petugas</h1>
                <p class="sisurat-subtitle mt-2">
                    Kelola nama, dinas, identitas, dan wilayah petugas untuk
                    surat tugas.
                </p>
            </div>
            <Link id="add-staff" :href="create()" class="sisurat-primary-action"
                ><Plus class="size-4" aria-hidden="true" />Tambah
                dosen/petugas</Link
            >
        </header>
        <section
            class="sisurat-panel p-5 sm:p-6"
            aria-label="Pencarian dosen/petugas"
        >
            <form
                class="grid items-end gap-4 sm:grid-cols-[minmax(0,1fr)_minmax(170px,240px)_auto]"
                @submit.prevent="search"
            >
                <div>
                    <label
                        for="staff-search"
                        class="mb-2 block text-sm font-medium"
                        >Cari dosen/petugas</label
                    >
                    <div class="relative">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden="true"
                        /><input
                            id="staff-search"
                            v-model="filters.q"
                            type="search"
                            class="sisurat-input pl-10"
                            maxlength="150"
                            placeholder="Nama, dinas, atau identitas"
                            :disabled="searching || deleteForm.processing"
                        />
                    </div>
                </div>
                <div>
                    <label
                        for="staff-district"
                        class="mb-2 block text-sm font-medium"
                        >Kabupaten/kota</label
                    ><select
                        id="staff-district"
                        v-model="filters.district"
                        class="sisurat-input"
                        :disabled="searching || deleteForm.processing"
                    >
                        <option value="">Semua wilayah</option>
                        <option
                            v-for="district in districtOptions"
                            :key="district.id"
                            :value="String(district.id)"
                        >
                            {{ district.name }}
                        </option>
                    </select>
                </div>
                <button
                    type="submit"
                    class="sisurat-primary-action"
                    :disabled="searching || deleteForm.processing"
                    :aria-busy="searching"
                >
                    <Search class="size-4" aria-hidden="true" /><span>{{
                        searching ? 'Mencari…' : 'Cari'
                    }}</span>
                </button>
            </form>
            <div
                v-if="hasFilters"
                class="mt-4 flex items-center justify-between gap-3 border-t border-border pt-4"
            >
                <p class="text-xs text-muted-foreground">
                    {{ records.total }} data sesuai pencarian
                </p>
                <button
                    type="button"
                    class="inline-flex min-h-9 items-center gap-1.5 text-xs font-medium text-primary hover:underline dark:text-foreground"
                    :disabled="searching || deleteForm.processing"
                    @click="resetFilters"
                >
                    <X class="size-3.5" aria-hidden="true" />Hapus filter
                </button>
            </div>
            <p
                v-if="requestError"
                class="mt-3 text-sm text-destructive"
                role="alert"
            >
                {{ requestError }}
            </p>
        </section>
        <section
            class="sisurat-panel overflow-hidden"
            aria-labelledby="staff-table-heading"
        >
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b border-border px-5 py-4"
            >
                <h2 id="staff-table-heading" class="text-sm font-semibold">
                    Daftar dosen/petugas
                </h2>
                <span
                    class="rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary dark:text-foreground"
                    >{{ records.total.toLocaleString('id-ID') }} data</span
                >
            </div>
            <div v-if="records.data.length" class="overflow-x-auto">
                <table class="w-full min-w-200 text-left text-sm">
                    <caption class="sr-only">
                        Nama, dinas, identitas, dan kabupaten/kota dosen atau
                        petugas
                    </caption>
                    <thead class="bg-muted/50 text-xs text-muted-foreground">
                        <tr>
                            <th scope="col" class="px-5 py-3 font-medium">
                                Nama
                            </th>
                            <th scope="col" class="px-4 py-3 font-medium">
                                Dinas / Jabatan
                            </th>
                            <th scope="col" class="px-4 py-3 font-medium">
                                NIP / Identitas
                            </th>
                            <th scope="col" class="px-4 py-3 font-medium">
                                Kabupaten / Kota
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
                            v-for="record in records.data"
                            :key="record.id"
                            class="transition-colors hover:bg-muted/25"
                        >
                            <th scope="row" class="px-5 py-4 font-semibold">
                                {{ record.name }}
                            </th>
                            <td class="px-4 py-4">{{ record.office }}</td>
                            <td class="px-4 py-4 text-xs">
                                {{ record.employee_number || '—' }}
                            </td>
                            <td class="px-4 py-4">
                                <span v-if="record.district">{{
                                    record.district
                                }}</span
                                ><span
                                    v-else
                                    class="rounded-full bg-muted px-2.5 py-1 text-xs text-muted-foreground"
                                    >Belum ditentukan</span
                                >
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-2">
                                    <Link
                                        :href="edit(record.id)"
                                        class="sisurat-secondary-action min-h-10 px-3 text-xs"
                                        :aria-label="`Ubah ${record.name}`"
                                        ><Pencil
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />Ubah</Link
                                    ><button
                                        type="button"
                                        class="sisurat-secondary-action min-h-10 border-destructive/25 px-3 text-xs text-destructive hover:bg-destructive/10"
                                        :disabled="
                                            searching || deleteForm.processing
                                        "
                                        :aria-label="`Hapus ${record.name}`"
                                        @click="requestDeletion(record, $event)"
                                    >
                                        <Trash2
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />Hapus
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div
                v-else
                class="flex flex-col items-center px-6 py-14 text-center"
            >
                <span class="sisurat-icon-tile"
                    ><Users class="size-6" aria-hidden="true"
                /></span>
                <h3 class="mt-4 text-sm font-semibold">
                    {{
                        hasFilters
                            ? 'Dosen/petugas tidak ditemukan'
                            : 'Belum ada data dosen/petugas'
                    }}
                </h3>
                <p
                    class="mt-2 max-w-md text-xs leading-7 text-muted-foreground"
                >
                    {{
                        hasFilters
                            ? 'Coba kata kunci atau wilayah lain, atau hapus filter pencarian.'
                            : 'Tambahkan petugas agar nama dan dinas dapat dipilih saat membuat surat tugas.'
                    }}
                </p>
                <button
                    v-if="hasFilters"
                    type="button"
                    class="sisurat-secondary-action mt-5"
                    @click="resetFilters"
                >
                    Hapus filter</button
                ><Link
                    v-else
                    :href="create()"
                    class="sisurat-primary-action mt-5"
                    ><Plus class="size-4" aria-hidden="true" />Tambah
                    dosen/petugas</Link
                >
            </div>
        </section>
        <nav
            v-if="records.data.length"
            class="flex flex-wrap items-center justify-between gap-4"
            aria-label="Halaman dosen/petugas"
        >
            <p class="text-xs text-muted-foreground">
                {{ records.from }}–{{ records.to }} dari
                {{ records.total }} data
            </p>
            <div class="flex items-center gap-3">
                <Link
                    v-if="records.prev_page_url"
                    :href="records.prev_page_url"
                    preserve-scroll
                    class="sisurat-secondary-action min-h-9 px-3 text-xs"
                    ><ArrowLeft
                        class="size-3.5"
                        aria-hidden="true"
                    />Sebelumnya</Link
                ><span class="text-xs text-muted-foreground"
                    >{{ records.current_page }} / {{ records.last_page }}</span
                ><Link
                    v-if="records.next_page_url"
                    :href="records.next_page_url"
                    preserve-scroll
                    class="sisurat-secondary-action min-h-9 px-3 text-xs"
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
                    ><DialogTitle>Hapus dosen/petugas?</DialogTitle
                    ><DialogDescription
                        >Petugas akan dihapus dari pilihan master data. Surat
                        tugas yang sudah dibuat tetap menyimpan nama dan
                        dinasnya.</DialogDescription
                    ></DialogHeader
                >
                <div class="rounded-xl border border-border bg-muted/40 p-4">
                    <p class="text-sm font-semibold break-words">
                        {{ deleteTarget?.name }}
                    </p>
                    <p class="mt-1 text-xs leading-6 text-muted-foreground">
                        {{ deleteTarget?.office
                        }}<span v-if="deleteTarget?.district">
                            · {{ deleteTarget.district }}</span
                        >
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
                        ><Trash2 class="size-4" aria-hidden="true" /><span>{{
                            deleteForm.processing
                                ? 'Menghapus…'
                                : 'Hapus dosen/petugas'
                        }}</span></Button
                    ></DialogFooter
                >
            </DialogContent>
        </Dialog>
    </main>
</template>
