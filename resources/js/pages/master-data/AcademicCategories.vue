<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    BookOpen,
    GraduationCap,
    Pencil,
    Plus,
    Search,
    Trash2,
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
    type MasterAcademicCategory,
    type MasterAcademicOption,
    type MasterDataPagination,
} from '@/lib/masterData';
import { index as masterIndex } from '@/routes/master-data';
import {
    create as createFaculty,
    destroy as destroyFaculty,
    edit as editFaculty,
    index as facultiesIndex,
} from '@/routes/master-data/faculties';
import {
    create as createStudyProgram,
    destroy as destroyStudyProgram,
    edit as editStudyProgram,
    index as studyProgramsIndex,
} from '@/routes/master-data/study-programs';

const props = defineProps<{
    category: MasterAcademicCategory;
    records: MasterDataPagination<MasterAcademicOption>;
    filters: { q: string };
}>();
const categoryLabel = computed(() =>
    props.category === 'faculties' ? 'Fakultas' : 'Program Studi',
);
const categoryIcon = computed(() =>
    props.category === 'faculties' ? GraduationCap : BookOpen,
);
const categoryRoutes = computed(() =>
    props.category === 'faculties'
        ? {
              index: facultiesIndex,
              create: createFaculty,
              edit: editFaculty,
              destroy: destroyFaculty,
          }
        : {
              index: studyProgramsIndex,
              create: createStudyProgram,
              edit: editStudyProgram,
              destroy: destroyStudyProgram,
          },
);
const query = ref(props.filters.q);
const searching = ref(false);
const requestError = ref('');
watch(
    () => props.filters.q,
    (value) => {
        query.value = value;
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
} = useMasterDataDeletion<MasterAcademicOption>(
    (id) =>
        categoryRoutes.value.destroy.url(id, {
            query: {
                q: query.value || undefined,
                page: props.records.current_page,
            },
        }),
    'add-academic-category',
);
function search(): void {
    if (searching.value || deleteForm.processing) return;
    requestError.value = '';
    router.get(
        categoryRoutes.value.index.url(),
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
                    'Data belum dapat dimuat. Muat ulang halaman atau coba kembali.';
                return false;
            },
        },
    );
}
function resetFilters(): void {
    query.value = '';
    search();
}
</script>

<template>
    <main class="sisurat-page space-y-6">
        <Head :title="`Master ${categoryLabel}`" />
        <Link :href="masterIndex()" class="sisurat-secondary-action w-fit"
            ><ArrowLeft class="size-4" aria-hidden="true" />Master Data</Link
        >
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="sisurat-kicker">Master Data · Akademik</p>
                <h1 class="sisurat-title mt-2">{{ categoryLabel }}</h1>
                <p class="sisurat-subtitle mt-2">
                    Kelola pilihan {{ categoryLabel.toLowerCase() }} mahasiswa
                    untuk surat keterangan PKKMB.
                </p>
            </div>
            <Link
                id="add-academic-category"
                :href="categoryRoutes.create()"
                class="sisurat-primary-action"
                ><Plus class="size-4" aria-hidden="true" />Tambah
                {{ categoryLabel.toLowerCase() }}</Link
            >
        </header>
        <section
            class="sisurat-panel p-5 sm:p-6"
            :aria-label="`Pencarian ${categoryLabel.toLowerCase()}`"
        >
            <form
                class="flex flex-wrap items-end gap-3"
                @submit.prevent="search"
            >
                <div class="min-w-0 flex-1 basis-64">
                    <label
                        for="academic-search"
                        class="mb-2 block text-sm font-medium"
                        >Cari {{ categoryLabel.toLowerCase() }}</label
                    >
                    <div class="relative">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden="true"
                        /><input
                            id="academic-search"
                            v-model="query"
                            type="search"
                            class="sisurat-input pl-10"
                            maxlength="150"
                            :placeholder="`Nama ${categoryLabel.toLowerCase()}`"
                            :disabled="searching || deleteForm.processing"
                        />
                    </div>
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
                <button
                    v-if="query"
                    type="button"
                    class="sisurat-secondary-action"
                    :disabled="searching || deleteForm.processing"
                    @click="resetFilters"
                >
                    <X class="size-4" aria-hidden="true" />Hapus filter
                </button>
            </form>
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
            aria-labelledby="academic-table-heading"
        >
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b border-border px-5 py-4"
            >
                <h2 id="academic-table-heading" class="text-sm font-semibold">
                    Daftar {{ categoryLabel.toLowerCase() }}
                </h2>
                <span
                    class="rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary dark:text-foreground"
                    >{{ records.total.toLocaleString('id-ID') }} data</span
                >
            </div>
            <div v-if="records.data.length" class="overflow-x-auto">
                <table class="w-full min-w-120 text-left text-sm">
                    <caption class="sr-only">
                        Daftar
                        {{
                            categoryLabel.toLowerCase()
                        }}
                        beserta tindakan pengelolaan
                    </caption>
                    <thead class="bg-muted/50 text-xs text-muted-foreground">
                        <tr>
                            <th
                                scope="col"
                                class="w-16 px-5 py-3 font-medium"
                                translate="no"
                            >
                                No
                            </th>
                            <th scope="col" class="px-5 py-3 font-medium">
                                Nama {{ categoryLabel }}
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
                            v-for="(record, recordIndex) in records.data"
                            :key="record.id"
                            class="transition-colors hover:bg-muted/25"
                        >
                            <td
                                class="px-5 py-4 text-muted-foreground tabular-nums"
                            >
                                {{ (records.from ?? 1) + recordIndex }}
                            </td>
                            <th
                                scope="row"
                                class="px-5 py-4 text-sm font-semibold"
                            >
                                {{ record.name }}
                            </th>
                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-2">
                                    <Link
                                        :href="categoryRoutes.edit(record.id)"
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
                    ><component
                        :is="categoryIcon"
                        class="size-6"
                        aria-hidden="true"
                /></span>
                <h3 class="mt-4 text-sm font-semibold">
                    {{
                        query
                            ? `${categoryLabel} tidak ditemukan`
                            : `Belum ada ${categoryLabel.toLowerCase()}`
                    }}
                </h3>
                <p
                    class="mt-2 max-w-md text-xs leading-7 text-muted-foreground"
                >
                    {{
                        query
                            ? 'Coba kata kunci lain atau hapus filter pencarian.'
                            : `Tambahkan ${categoryLabel.toLowerCase()} agar dapat dipilih untuk data mahasiswa pada surat PKKMB.`
                    }}
                </p>
                <button
                    v-if="query"
                    type="button"
                    class="sisurat-secondary-action mt-5"
                    :disabled="searching"
                    @click="resetFilters"
                >
                    Hapus filter</button
                ><Link
                    v-else
                    :href="categoryRoutes.create()"
                    class="sisurat-primary-action mt-5"
                    ><Plus class="size-4" aria-hidden="true" />Tambah
                    {{ categoryLabel.toLowerCase() }}</Link
                >
            </div>
        </section>
        <nav
            v-if="records.data.length"
            class="flex flex-wrap items-center justify-between gap-4"
            :aria-label="`Halaman ${categoryLabel.toLowerCase()}`"
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
        <p class="text-xs leading-6 text-muted-foreground">
            Perubahan master data berlaku untuk pilihan surat berikutnya. Data
            pada surat yang sudah tersimpan tetap utuh.
        </p>
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
                    ><DialogTitle
                        >Hapus {{ categoryLabel.toLowerCase() }}?</DialogTitle
                    ><DialogDescription
                        >Data akan dihapus dari pilihan master
                        {{ categoryLabel.toLowerCase() }}. Surat keterangan
                        PKKMB yang sudah dibuat tetap menyimpan data
                        aslinya.</DialogDescription
                    ></DialogHeader
                >
                <div class="rounded-xl border border-border bg-muted/40 p-4">
                    <p class="text-sm font-semibold break-words">
                        {{ deleteTarget?.name }}
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
                            deleteForm.processing ? 'Menghapus…' : 'Hapus data'
                        }}</span></Button
                    ></DialogFooter
                >
            </DialogContent>
        </Dialog>
    </main>
</template>
