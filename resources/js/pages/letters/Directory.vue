<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    FilePlus2,
    GraduationCap,
    Landmark,
    MapPin,
    Pencil,
    Plus,
    Search,
    Trash2,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { institutionLabels } from '@/lib/letters';
import type { InstitutionType } from '@/lib/letters';
import {
    create as createInstitution,
    destroy as destroyInstitution,
    edit as editInstitution,
} from '@/routes/institutions';
import { index as masterIndex, pesantren, schools } from '@/routes/master-data';
import {
    pesantren as assignmentPesantren,
    school as assignmentSchool,
} from '@/routes/letters/assignment';
import {
    pesantren as permissionPesantren,
    school as permissionSchool,
} from '@/routes/letters/permission';

type Destination = {
    id: number;
    institution_name: string;
    institution_type: InstitutionType;
    recipient_district: string | null;
    recipient_address: string | null;
    letters_count: number | string;
    last_used: string | null;
};
type Pagination = {
    data: Destination[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
type Filters = { q: string; destination: InstitutionType; district: string };
const props = defineProps<{
    destinations: Pagination;
    filters: Filters;
    districtOptions: string[];
    stats: { schoolCount: number; pesantrenCount: number; totalCount: number };
}>();
const filters = ref<Filters>({ ...props.filters });
const searching = ref(false);
const requestError = ref('');
const deleteForm = useForm({});
const deleteDialogOpen = ref(false);
const deleteTarget = ref<Destination | null>(null);
const deleteError = ref('');
const deleteReturnFocus = ref<HTMLElement | null>(null);
const shortLabel = computed(() =>
    filters.value.destination === 'pesantren' ? 'pondok' : 'sekolah',
);
const hasFilters = computed(() =>
    Boolean(filters.value.q || filters.value.district),
);
const permissionRoute = computed(() =>
    filters.value.destination === 'pesantren'
        ? permissionPesantren
        : permissionSchool,
);
const assignmentRoute = computed(() =>
    filters.value.destination === 'pesantren'
        ? assignmentPesantren
        : assignmentSchool,
);
const directoryRoute = computed(() =>
    filters.value.destination === 'pesantren' ? pesantren : schools,
);
watch(
    () => props.filters,
    (value) => {
        filters.value = { ...value };
    },
);

function search(): void {
    if (searching.value || deleteForm.processing) return;
    requestError.value = '';
    router.get(
        directoryRoute.value.url(),
        {
            q: filters.value.q || undefined,
            destination: filters.value.destination,
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
function changeDestination(destination: InstitutionType): void {
    if (searching.value || filters.value.destination === destination) return;
    filters.value.destination = destination;
    filters.value.q = '';
    filters.value.district = '';
    search();
}
function resetFilters(): void {
    filters.value.q = '';
    filters.value.district = '';
    search();
}
function createFor(destination: Destination) {
    const targetRoute =
        destination.institution_type === 'pesantren'
            ? permissionPesantren
            : permissionSchool;
    return targetRoute({
        query: {
            institution_id: destination.id,
            institution_name: destination.institution_name,
            district: destination.recipient_district || undefined,
            address: destination.recipient_address || undefined,
        },
    });
}
function formatDate(value: string): string {
    return new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(`${value.slice(0, 10)}T00:00:00`));
}
function requestDeletion(destination: Destination, event: Event): void {
    if (deleteForm.processing || searching.value) return;
    deleteTarget.value = destination;
    deleteReturnFocus.value = event.currentTarget as HTMLElement;
    deleteError.value = '';
    deleteForm.clearErrors();
    deleteDialogOpen.value = true;
}
function updateDeleteDialog(open: boolean): void {
    if (deleteForm.processing) return;
    deleteDialogOpen.value = open;
    if (!open) {
        deleteTarget.value = null;
        deleteError.value = '';
        deleteForm.clearErrors();
    }
}
function restoreDeleteFocus(event: Event): void {
    event.preventDefault();
    if (deleteReturnFocus.value?.isConnected) deleteReturnFocus.value.focus();
    else document.getElementById('add-institution')?.focus();
}
function confirmDeletion(): void {
    if (!deleteTarget.value || deleteForm.processing) return;
    deleteError.value = '';
    deleteForm.delete(
        destroyInstitution.url(deleteTarget.value.id, {
            query: {
                destination: filters.value.destination,
                q: filters.value.q || undefined,
                district: filters.value.district || undefined,
                page: props.destinations.current_page,
            },
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                deleteDialogOpen.value = false;
                deleteTarget.value = null;
            },
            onError: (errors) => {
                deleteError.value =
                    Object.values(errors).filter(Boolean).join(' ') ||
                    'Data belum dapat dihapus. Coba lagi.';
                toast.error(deleteError.value);
            },
            onNetworkError: () => {
                deleteError.value =
                    'Koneksi terputus. Periksa koneksi lalu coba hapus kembali.';
                return false;
            },
            onHttpException: () => {
                deleteError.value =
                    'Data belum dapat dihapus. Muat ulang halaman atau coba kembali.';
                return false;
            },
        },
    );
}
</script>

<template>
    <main class="sisurat-page space-y-6">
        <Head
            :title="
                filters.destination === 'pesantren'
                    ? 'Master Pondok'
                    : 'Master Sekolah'
            "
        />
        <Link :href="masterIndex()" class="sisurat-secondary-action w-fit"
            ><ArrowLeft class="size-4" aria-hidden="true" />Master Data</Link
        >
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="sisurat-kicker">Master Data · Tujuan Surat</p>
                <h1 class="sisurat-title mt-2">
                    {{
                        filters.destination === 'pesantren'
                            ? 'Pondok Pesantren'
                            : 'Sekolah / Madrasah'
                    }}
                </h1>
                <p class="sisurat-subtitle mt-2">
                    {{ stats.totalCount }} tujuan tersimpan. Pilih tujuan tanpa
                    mengetik ulang nama dan kabupaten/kotanya.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link
                    id="add-institution"
                    :href="
                        createInstitution({
                            query: { institution_type: filters.destination },
                        })
                    "
                    class="sisurat-primary-action"
                    ><Plus class="size-4" aria-hidden="true" />Tambah data</Link
                >
                <Link :href="permissionRoute()" class="sisurat-secondary-action"
                    ><FilePlus2 class="size-4" aria-hidden="true" />Buat izin
                    massal</Link
                ><Link
                    :href="assignmentRoute()"
                    class="sisurat-secondary-action"
                    ><FilePlus2 class="size-4" aria-hidden="true" />Buat surat
                    tugas</Link
                >
            </div>
        </header>
        <section
            class="flex items-start gap-3 rounded-2xl border border-primary/15 bg-primary/5 px-5 py-4"
        >
            <MapPin
                class="mt-0.5 size-5 shrink-0 text-primary"
                aria-hidden="true"
            />
            <p class="text-xs leading-7 text-muted-foreground">
                Pilih
                <strong class="font-semibold text-foreground">Buat izin</strong>
                untuk satu tujuan. Untuk beberapa atau semua tujuan, buka
                formulir izin lalu pilih daftar sekolah atau pondok. Surat tugas
                dibuat berdasarkan kabupaten/kota dan daftar dosen.
            </p>
        </section>
        <nav class="grid grid-cols-2 gap-3" aria-label="Jenis data tujuan">
            <button
                type="button"
                :disabled="searching"
                :aria-pressed="filters.destination === 'school'"
                :class="[
                    'sisurat-panel flex items-center gap-3 p-4 text-left sm:p-5',
                    filters.destination === 'school'
                        ? 'border-primary/40 bg-accent ring-1 ring-primary/20'
                        : 'hover:border-primary/25',
                ]"
                @click="changeDestination('school')"
            >
                <span class="sisurat-icon-tile size-10"
                    ><GraduationCap class="size-5" aria-hidden="true" /></span
                ><span class="min-w-0"
                    ><span class="block text-sm font-semibold"
                        >Sekolah / madrasah</span
                    ><span class="mt-1 block text-xs text-muted-foreground"
                        >{{ stats.schoolCount }} sekolah</span
                    ></span
                >
            </button>
            <button
                type="button"
                :disabled="searching"
                :aria-pressed="filters.destination === 'pesantren'"
                :class="[
                    'sisurat-panel flex items-center gap-3 p-4 text-left sm:p-5',
                    filters.destination === 'pesantren'
                        ? 'border-primary/40 bg-accent ring-1 ring-primary/20'
                        : 'hover:border-primary/25',
                ]"
                @click="changeDestination('pesantren')"
            >
                <span class="sisurat-icon-tile size-10"
                    ><Landmark class="size-5" aria-hidden="true" /></span
                ><span class="min-w-0"
                    ><span class="block text-sm font-semibold"
                        >Pondok pesantren</span
                    ><span class="mt-1 block text-xs text-muted-foreground"
                        >{{ stats.pesantrenCount }} pondok</span
                    ></span
                >
            </button>
        </nav>
        <section class="sisurat-panel p-5 sm:p-6" aria-label="Pencarian tujuan">
            <form
                class="grid items-end gap-4 sm:grid-cols-[minmax(0,1fr)_minmax(170px,240px)_auto]"
                @submit.prevent="search"
            >
                <div>
                    <label
                        for="destination-search"
                        class="mb-2 block text-xs font-medium"
                        >Cari {{ shortLabel }} atau kabupaten/kota</label
                    >
                    <div class="relative">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden="true"
                        /><input
                            id="destination-search"
                            v-model="filters.q"
                            :disabled="searching"
                            type="search"
                            maxlength="150"
                            class="sisurat-input pl-10"
                            placeholder="Nama tujuan atau kabupaten/kota"
                        />
                    </div>
                </div>
                <div>
                    <label
                        for="destination-district"
                        class="mb-2 block text-xs font-medium"
                        >Kabupaten/kota</label
                    ><select
                        id="destination-district"
                        v-model="filters.district"
                        :disabled="searching"
                        class="sisurat-input"
                    >
                        <option value="">Semua kabupaten/kota</option>
                        <option
                            v-for="district in districtOptions"
                            :key="district"
                            :value="district"
                        >
                            {{ district }}
                        </option>
                    </select>
                </div>
                <button
                    type="submit"
                    class="sisurat-primary-action"
                    :disabled="searching"
                >
                    <Search class="size-4" aria-hidden="true" />{{
                        searching ? 'Mencari…' : 'Cari'
                    }}
                </button>
            </form>
            <p
                v-if="requestError"
                class="mt-3 text-sm text-destructive"
                role="alert"
            >
                {{ requestError }}
            </p>
            <div
                v-if="hasFilters"
                class="mt-4 flex items-center justify-between gap-3 border-t border-border pt-4"
            >
                <p class="text-xs text-muted-foreground">
                    {{ destinations.total }} {{ shortLabel }} sesuai pencarian
                </p>
                <button
                    type="button"
                    :disabled="searching"
                    class="inline-flex items-center gap-1.5 text-xs font-medium text-primary hover:underline"
                    @click="resetFilters"
                >
                    <X class="size-3.5" aria-hidden="true" />Hapus filter
                </button>
            </div>
        </section>
        <section aria-labelledby="destinations-heading">
            <div class="mb-4 flex items-center justify-between">
                <h2 id="destinations-heading" class="text-sm font-semibold">
                    {{ institutionLabels[filters.destination] }}
                </h2>
                <span
                    class="rounded-full bg-primary/7 px-3 py-1 text-xs font-medium text-primary"
                    >{{ destinations.total }} tujuan</span
                >
            </div>
            <div
                v-if="destinations.data.length"
                class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
            >
                <article
                    v-for="destination in destinations.data"
                    :key="destination.id"
                    class="sisurat-panel flex flex-col p-5"
                >
                    <div class="flex items-center justify-between gap-3">
                        <span class="sisurat-icon-tile"
                            ><component
                                :is="
                                    destination.institution_type === 'pesantren'
                                        ? Landmark
                                        : GraduationCap
                                "
                                class="size-5"
                                aria-hidden="true" /></span
                        ><span
                            class="rounded-full bg-muted px-3 py-1 text-[10px] font-medium text-muted-foreground"
                            >{{
                                destination.institution_type === 'school'
                                    ? 'Sekolah'
                                    : 'Pondok'
                            }}</span
                        >
                    </div>
                    <h3 class="mt-4 text-sm leading-6 font-semibold">
                        {{ destination.institution_name }}
                    </h3>
                    <p
                        class="mt-2 flex items-start gap-1.5 text-xs leading-6 text-muted-foreground"
                    >
                        <MapPin
                            class="mt-1 size-3.5 shrink-0"
                            aria-hidden="true"
                        />{{
                            destination.recipient_district ||
                            'Kabupaten/kota belum tersedia'
                        }}
                    </p>
                    <p
                        v-if="destination.recipient_address"
                        class="mt-1 text-xs leading-6 text-muted-foreground"
                    >
                        {{ destination.recipient_address }}
                    </p>
                    <p
                        class="mt-4 flex-1 text-[11px] leading-6 text-muted-foreground"
                    >
                        {{
                            Number(destination.letters_count).toLocaleString(
                                'id-ID',
                            )
                        }}
                        surat terhubung<span v-if="destination.last_used">
                            · Terakhir
                            {{ formatDate(destination.last_used) }}</span
                        ><span v-else> · Belum digunakan dari basis data</span>
                    </p>
                    <div
                        class="mt-5 flex flex-wrap gap-2 border-t border-border pt-4"
                    >
                        <Link
                            :href="createFor(destination)"
                            class="sisurat-secondary-action min-h-10 px-3 text-xs"
                            >Buat izin<ArrowRight
                                class="size-3.5"
                                aria-hidden="true"
                        /></Link>
                        <Link
                            :href="editInstitution(destination.id)"
                            class="sisurat-secondary-action min-h-11 px-3 text-xs"
                            :aria-label="`Ubah ${destination.institution_name}`"
                            ><Pencil
                                class="size-3.5"
                                aria-hidden="true"
                            />Ubah</Link
                        >
                        <button
                            type="button"
                            class="sisurat-secondary-action min-h-11 border-destructive/25 px-3 text-xs text-destructive hover:bg-destructive/10"
                            :disabled="searching || deleteForm.processing"
                            :aria-label="`Hapus ${destination.institution_name}`"
                            @click="requestDeletion(destination, $event)"
                        >
                            <Trash2 class="size-3.5" aria-hidden="true" />Hapus
                        </button>
                    </div>
                </article>
            </div>
            <div
                v-else
                class="sisurat-panel flex flex-col items-center px-6 py-14 text-center"
            >
                <span class="sisurat-icon-tile"
                    ><MapPin class="size-6" aria-hidden="true"
                /></span>
                <h3 class="mt-4 text-sm font-semibold">
                    {{
                        hasFilters
                            ? 'Tujuan belum ditemukan'
                            : `Belum ada data ${shortLabel}`
                    }}
                </h3>
                <p
                    class="mt-2 max-w-md text-xs leading-7 text-muted-foreground"
                >
                    {{
                        hasFilters
                            ? 'Coba nama atau kabupaten/kota lain, atau hapus filter pencarian.'
                            : 'Tambahkan sekolah atau pondok agar mudah dipilih saat membuat surat.'
                    }}
                </p>
                <button
                    v-if="hasFilters"
                    type="button"
                    class="sisurat-secondary-action mt-5"
                    @click="resetFilters"
                >
                    Hapus filter pencarian</button
                ><Link
                    v-else
                    :href="
                        createInstitution({
                            query: { institution_type: filters.destination },
                        })
                    "
                    class="sisurat-primary-action mt-5"
                    ><Plus class="size-4" aria-hidden="true" />Tambah data</Link
                >
            </div>
            <nav
                v-if="destinations.data.length"
                class="mt-5 flex flex-wrap items-center justify-between gap-4"
                aria-label="Halaman data tujuan"
            >
                <p class="text-xs text-muted-foreground">
                    {{ destinations.from }}–{{ destinations.to }} dari
                    {{ destinations.total }} {{ shortLabel }}
                </p>
                <div class="flex items-center gap-3">
                    <Link
                        v-if="destinations.prev_page_url"
                        :href="destinations.prev_page_url"
                        preserve-scroll
                        class="sisurat-secondary-action min-h-9 px-3 text-xs"
                        ><ArrowLeft
                            class="size-3.5"
                            aria-hidden="true"
                        />Sebelumnya</Link
                    ><span class="text-xs text-muted-foreground"
                        >{{ destinations.current_page }} /
                        {{ destinations.last_page }}</span
                    ><Link
                        v-if="destinations.next_page_url"
                        :href="destinations.next_page_url"
                        preserve-scroll
                        class="sisurat-secondary-action min-h-9 px-3 text-xs"
                        >Berikutnya<ArrowRight
                            class="size-3.5"
                            aria-hidden="true"
                    /></Link>
                </div>
            </nav>
        </section>
        <Dialog :open="deleteDialogOpen" @update:open="updateDeleteDialog">
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
                <DialogHeader>
                    <DialogTitle>Hapus data tujuan?</DialogTitle>
                    <DialogDescription
                        >Data ini akan dihapus dari daftar sekolah dan pondok.
                        Surat yang sudah dibuat tetap
                        tersimpan.</DialogDescription
                    >
                </DialogHeader>
                <div class="rounded-xl border border-border bg-muted/40 p-4">
                    <p class="text-sm leading-6 font-semibold break-words">
                        {{ deleteTarget?.institution_name }}
                    </p>
                    <p class="mt-1 text-xs leading-6 text-muted-foreground">
                        {{ deleteTarget?.recipient_district }}
                    </p>
                </div>
                <p
                    v-if="deleteError"
                    class="rounded-xl bg-destructive/10 px-4 py-3 text-sm text-destructive"
                    role="alert"
                >
                    {{ deleteError }}
                </p>
                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="deleteForm.processing"
                        @click="updateDeleteDialog(false)"
                        >Batal</Button
                    >
                    <Button
                        type="button"
                        variant="destructive"
                        :disabled="deleteForm.processing"
                        @click="confirmDeletion"
                        ><Trash2 class="mr-2 size-4" aria-hidden="true" />{{
                            deleteForm.processing ? 'Menghapus…' : 'Hapus data'
                        }}</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </main>
</template>
