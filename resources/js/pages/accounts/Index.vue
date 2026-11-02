<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    KeyRound,
    Pencil,
    Plus,
    Search,
    ShieldCheck,
    Users,
    UserCheck,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import type { MasterDataPagination } from '@/lib/masterData';
import { create, edit, index } from '@/routes/accounts';

type Account = {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'staff';
    is_active: boolean;
    is_primary_admin: boolean;
    created_at: string;
};
type AccountFilters = {
    q: string;
    status: 'all' | 'active' | 'inactive';
    role: 'all' | 'admin' | 'staff';
};

const props = defineProps<{
    accounts: MasterDataPagination<Account>;
    filters: AccountFilters;
    stats: { total: number; active: number; admins: number };
}>();
const page = usePage();
const filters = ref<AccountFilters>({ ...props.filters });
const searching = ref(false);
const requestError = ref('');
const hasFilters = computed(() =>
    Boolean(
        filters.value.q ||
        filters.value.status !== 'all' ||
        filters.value.role !== 'all',
    ),
);
const statCards = computed(() => [
    { label: 'Semua akun', value: props.stats.total, icon: Users },
    { label: 'Akun aktif', value: props.stats.active, icon: UserCheck },
    { label: 'Admin aktif', value: props.stats.admins, icon: ShieldCheck },
]);
watch(
    () => props.filters,
    (value) => {
        filters.value = { ...value };
    },
);

function search(): void {
    if (searching.value) return;
    requestError.value = '';
    router.get(
        index.url(),
        {
            q: filters.value.q.trim() || undefined,
            status: filters.value.status,
            role: filters.value.role,
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
                    'Koneksi terputus. Periksa koneksi lalu coba kembali.';
                return false;
            },
            onHttpException: () => {
                requestError.value =
                    'Daftar akun belum dapat dimuat. Muat ulang halaman atau coba kembali.';
                return false;
            },
        },
    );
}

function resetFilters(): void {
    filters.value = { q: '', status: 'all', role: 'all' };
    search();
}

function createdDate(value: string): string {
    const date = new Date(value);
    return Number.isNaN(date.getTime())
        ? '—'
        : new Intl.DateTimeFormat('id-ID', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
              timeZone: 'Asia/Jakarta',
          }).format(date);
}
</script>

<template>
    <main class="sisurat-page space-y-6">
        <Head title="Kelola akun" />
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="sisurat-kicker">Pengaturan · Akses SISURAT</p>
                <h1 class="sisurat-title mt-2">Kelola akun</h1>
                <p class="sisurat-subtitle mt-2">
                    Tambahkan akun dan tentukan siapa yang dapat masuk ke ruang
                    kerja.
                </p>
            </div>
            <Link :href="create()" class="sisurat-primary-action"
                ><Plus class="size-4" aria-hidden="true" />Tambah akun</Link
            >
        </header>

        <section class="grid gap-4 sm:grid-cols-3" aria-label="Ringkasan akun">
            <article
                v-for="stat in statCards"
                :key="stat.label"
                class="sisurat-panel flex items-center gap-4 p-5"
            >
                <span class="sisurat-icon-tile"
                    ><component
                        :is="stat.icon"
                        class="size-5"
                        aria-hidden="true"
                /></span>
                <div>
                    <p class="text-xs text-muted-foreground">
                        {{ stat.label }}
                    </p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums">
                        {{ stat.value.toLocaleString('id-ID') }}
                    </p>
                </div>
            </article>
        </section>

        <div
            class="flex gap-3 rounded-xl border border-border bg-primary/5 p-4 text-sm leading-7"
        >
            <KeyRound
                class="mt-1 size-5 shrink-0 text-primary dark:text-emerald-200"
                aria-hidden="true"
            />
            <p>
                Pendaftaran umum ditutup. Akun baru hanya dapat dibuat oleh
                Admin. Nonaktifkan akun untuk menghentikan aksesnya; data
                pekerjaan yang sudah dibuat tetap tersimpan.
            </p>
        </div>

        <section class="sisurat-panel p-5 sm:p-6" aria-label="Pencarian akun">
            <form
                class="grid items-end gap-4 sm:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_170px_160px_auto]"
                @submit.prevent="search"
            >
                <div>
                    <label
                        for="account-search"
                        class="mb-2 block text-sm font-medium"
                        >Cari akun</label
                    >
                    <div class="relative">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden="true"
                        /><input
                            id="account-search"
                            v-model="filters.q"
                            type="search"
                            maxlength="150"
                            class="sisurat-input pl-10"
                            placeholder="Nama atau alamat email"
                            :disabled="searching"
                        />
                    </div>
                </div>
                <div>
                    <label
                        for="account-status"
                        class="mb-2 block text-sm font-medium"
                        >Status</label
                    ><select
                        id="account-status"
                        v-model="filters.status"
                        class="sisurat-input"
                        :disabled="searching"
                    >
                        <option value="all">Semua status</option>
                        <option value="active">Aktif</option>
                        <option value="inactive">Nonaktif</option>
                    </select>
                </div>
                <div>
                    <label
                        for="account-role"
                        class="mb-2 block text-sm font-medium"
                        >Peran</label
                    ><select
                        id="account-role"
                        v-model="filters.role"
                        class="sisurat-input"
                        :disabled="searching"
                    >
                        <option value="all">Semua peran</option>
                        <option value="admin">Admin</option>
                        <option value="staff">Petugas</option>
                    </select>
                </div>
                <button
                    type="submit"
                    class="sisurat-primary-action"
                    :disabled="searching"
                    :aria-busy="searching"
                >
                    <Search class="size-4" aria-hidden="true" />{{
                        searching ? 'Mencari…' : 'Cari'
                    }}
                </button>
            </form>
            <div
                v-if="hasFilters"
                class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4"
            >
                <p class="text-xs text-muted-foreground">
                    {{ accounts.total }} akun sesuai pencarian
                </p>
                <button
                    type="button"
                    class="inline-flex min-h-9 items-center gap-1.5 text-xs font-medium text-primary hover:underline dark:text-emerald-200"
                    :disabled="searching"
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
            aria-labelledby="accounts-heading"
        >
            <header
                class="flex items-center justify-between gap-3 border-b border-border px-5 py-4"
            >
                <h2 id="accounts-heading" class="text-sm font-semibold">
                    Daftar akun SISURAT
                </h2>
                <span class="text-xs text-muted-foreground"
                    >{{ accounts.total.toLocaleString('id-ID') }} akun</span
                >
            </header>
            <div v-if="accounts.data.length" class="overflow-x-auto">
                <table class="w-full min-w-200 text-left text-sm">
                    <caption class="sr-only">
                        Nama, email, peran, status, tanggal dibuat, dan tindakan
                        akun SISURAT.
                    </caption>
                    <thead class="bg-muted/50 text-xs text-muted-foreground">
                        <tr>
                            <th scope="col" class="px-5 py-3 font-medium">
                                Nama / Email
                            </th>
                            <th scope="col" class="px-4 py-3 font-medium">
                                Peran
                            </th>
                            <th scope="col" class="px-4 py-3 font-medium">
                                Status
                            </th>
                            <th scope="col" class="px-4 py-3 font-medium">
                                Dibuat
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
                            v-for="account in accounts.data"
                            :key="account.id"
                            class="transition-colors hover:bg-muted/25"
                        >
                            <th scope="row" class="px-5 py-4 font-normal">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold">{{
                                        account.name
                                    }}</span
                                    ><span
                                        v-if="
                                            Number(account.id) ===
                                            Number(page.props.auth.user.id)
                                        "
                                        class="text-xs text-muted-foreground"
                                        >(Anda)</span
                                    ><span
                                        v-if="account.is_primary_admin"
                                        class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-1 text-[10px] font-medium text-primary dark:text-emerald-200"
                                        ><ShieldCheck
                                            class="size-3"
                                            aria-hidden="true"
                                        />Admin utama</span
                                    >
                                </div>
                                <p
                                    class="mt-1.5 text-xs break-all text-muted-foreground"
                                >
                                    {{ account.email }}
                                </p>
                            </th>
                            <td class="px-4 py-4">
                                <span
                                    class="rounded-full bg-muted px-2.5 py-1 text-xs font-medium"
                                    >{{
                                        account.role === 'admin'
                                            ? 'Admin'
                                            : 'Petugas'
                                    }}</span
                                >
                            </td>
                            <td class="px-4 py-4">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="
                                        account.is_active
                                            ? 'bg-emerald-500/10 text-emerald-800 dark:text-emerald-200'
                                            : 'bg-muted text-muted-foreground'
                                    "
                                    ><span
                                        class="size-1.5 rounded-full"
                                        :class="
                                            account.is_active
                                                ? 'bg-emerald-600 dark:bg-emerald-400'
                                                : 'bg-muted-foreground'
                                        "
                                        aria-hidden="true"
                                    ></span
                                    >{{
                                        account.is_active ? 'Aktif' : 'Nonaktif'
                                    }}</span
                                >
                            </td>
                            <td class="px-4 py-4 text-xs text-muted-foreground">
                                {{ createdDate(account.created_at) }}
                            </td>
                            <td class="px-5 py-4 text-right">
                                <Link
                                    v-if="
                                        !account.is_primary_admin ||
                                        Number(account.id) ===
                                            Number(page.props.auth.user.id)
                                    "
                                    :href="edit(account.id)"
                                    class="sisurat-secondary-action min-h-10 px-3 text-xs"
                                    :aria-label="`Ubah akun ${account.name}`"
                                    ><Pencil
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />Ubah</Link
                                >
                                <span
                                    v-else
                                    class="text-xs text-muted-foreground"
                                    >Admin utama dilindungi</span
                                >
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
                    {{ hasFilters ? 'Akun tidak ditemukan' : 'Belum ada akun' }}
                </h3>
                <p
                    class="mt-2 max-w-md text-xs leading-7 text-muted-foreground"
                >
                    {{
                        hasFilters
                            ? 'Coba nama, email, status, atau peran lain.'
                            : 'Tambahkan akun untuk petugas yang memerlukan akses SISURAT.'
                    }}
                </p>
                <button
                    v-if="hasFilters"
                    type="button"
                    class="sisurat-secondary-action mt-5"
                    :disabled="searching"
                    @click="resetFilters"
                >
                    Hapus filter</button
                ><Link
                    v-else
                    :href="create()"
                    class="sisurat-primary-action mt-5"
                    ><Plus class="size-4" aria-hidden="true" />Tambah akun</Link
                >
            </div>
        </section>

        <nav
            v-if="accounts.data.length"
            class="flex flex-wrap items-center justify-between gap-4"
            aria-label="Halaman akun"
        >
            <p class="text-xs text-muted-foreground">
                {{ accounts.from }}–{{ accounts.to }} dari
                {{ accounts.total }} akun
            </p>
            <div class="flex items-center gap-3">
                <Link
                    v-if="accounts.prev_page_url"
                    :href="accounts.prev_page_url"
                    preserve-scroll
                    class="sisurat-secondary-action min-h-9 px-3 text-xs"
                    ><ArrowLeft
                        class="size-3.5"
                        aria-hidden="true"
                    />Sebelumnya</Link
                ><span class="text-xs text-muted-foreground"
                    >{{ accounts.current_page }} /
                    {{ accounts.last_page }}</span
                ><Link
                    v-if="accounts.next_page_url"
                    :href="accounts.next_page_url"
                    preserve-scroll
                    class="sisurat-secondary-action min-h-9 px-3 text-xs"
                    >Berikutnya<ArrowRight class="size-3.5" aria-hidden="true"
                /></Link>
            </div>
        </nav>
    </main>
</template>
