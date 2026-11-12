<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { BookOpen, Download, FileText, Search, School, Users } from '@lucide/vue';
import { ref, watch } from 'vue';
import PublicFormShare from '@/components/PublicFormShare.vue';
import type { MasterDataPagination } from '@/lib/masterData';
import { index } from '@/routes/pmb-prospects';
import { update as updateSettings } from '@/routes/pmb-prospects/settings';

type ProspectSubmission = {
    id: number;
    name: string;
    study_program: string;
    school_name: string;
    school_district: string;
    school_address: string;
    affiliate_name: string;
    submitted_at: string;
};

const props = defineProps<{
    submissions: MasterDataPagination<ProspectSubmission>;
    filters: { q: string };
    isOpen: boolean;
    stats: { total: number; schools: number; programs: number };
    publicUrl: string;
    qrUrl: string;
    qrDownloadUrl: string;
    flyerUrl: string;
    flyerDownloadUrl: string;
    pdfViewUrl: string;
    pdfDownloadUrl: string;
}>();

const query = ref(props.filters.q);
const searching = ref(false);
const requestError = ref('');
const settingsForm = useForm({ is_open: props.isOpen });
watch(() => props.filters.q, (value) => { query.value = value; });
watch(() => props.isOpen, (value) => { settingsForm.is_open = value; });

function search(): void {
    requestError.value = '';
    router.get(index.url(), { q: query.value.trim() || undefined }, {
        preserveState: true,
        preserveScroll: true,
        onStart: () => { searching.value = true; },
        onFinish: () => { searching.value = false; },
        onNetworkError: () => {
            requestError.value = 'Koneksi terputus. Periksa koneksi lalu coba kembali.';
            return false;
        },
        onHttpException: () => {
            requestError.value = 'Data calon mahasiswa belum dapat dimuat. Coba kembali.';
            return false;
        },
    });
}

function saveSettings(): void {
    settingsForm.patch(updateSettings.url(), { preserveScroll: true });
}

</script>

<template>
    <main class="sisurat-page space-y-6">
        <Head title="Data Calon Mahasiswa" />
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="sisurat-kicker">Penerimaan Mahasiswa Baru</p>
                <h1 class="sisurat-title mt-2">Data Calon Mahasiswa</h1>
                <p class="sisurat-subtitle mt-2 max-w-3xl">
                    Bagikan formulir pendaftaran, pindai QR, unduh flyer, dan kelola data calon mahasiswa.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a :href="pdfViewUrl" target="_blank" rel="noopener noreferrer" class="sisurat-secondary-action">
                    <FileText class="size-4" aria-hidden="true" />Lihat PDF
                </a>
                <a :href="pdfDownloadUrl" class="sisurat-primary-action">
                    <Download class="size-4" aria-hidden="true" />Unduh PDF
                </a>
            </div>
        </header>

        <section class="sisurat-panel p-5 sm:p-7" aria-label="Bagikan formulir">
            <PublicFormShare
                title="Formulir data calon mahasiswa"
                description="Bagikan tautan atau QR ini kepada calon mahasiswa maupun dosen/guru affiliator. Isian otomatis masuk ke daftar data di bawah."
                :url="publicUrl"
                :qr-url="qrUrl"
                :download-url="qrDownloadUrl"
                :flyer-url="flyerUrl"
                :flyer-download-url="flyerDownloadUrl"
                link-label="Tautan formulir data calon mahasiswa"
                open-label="Buka formulir publik"
                :disabled="!isOpen"
            />
        </section>

        <section class="sisurat-panel flex flex-wrap items-center justify-between gap-4 p-5" aria-label="Status formulir">
            <div>
                <h2 class="font-semibold">Status formulir publik</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ isOpen ? 'Formulir sedang terbuka dan dapat diisi.' : 'Formulir sedang ditutup.' }}
                </p>
            </div>
            <form class="flex flex-wrap items-center gap-3" @submit.prevent="saveSettings">
                <label class="inline-flex min-h-11 items-center gap-3 rounded-xl border border-border px-4 text-sm font-medium">
                    <input v-model="settingsForm.is_open" type="checkbox" class="size-4 accent-primary" :disabled="settingsForm.processing" />
                    {{ settingsForm.is_open ? 'Buka formulir' : 'Tutup formulir' }}
                </label>
                <button type="submit" class="sisurat-primary-action" :disabled="settingsForm.processing || settingsForm.is_open === isOpen">
                    {{ settingsForm.processing ? 'Menyimpan...' : 'Simpan status' }}
                </button>
            </form>
        </section>

        <section class="grid gap-3 sm:grid-cols-3" aria-label="Ringkasan data calon mahasiswa">
            <article class="sisurat-panel flex items-center gap-4 p-5">
                <span class="sisurat-icon-tile"><Users class="size-5" aria-hidden="true" /></span>
                <div><p class="text-xs text-muted-foreground">Total calon mahasiswa</p><p class="mt-1 text-xl font-semibold tabular-nums">{{ stats.total.toLocaleString('id-ID') }}</p></div>
            </article>
            <article class="sisurat-panel flex items-center gap-4 p-5">
                <span class="sisurat-icon-tile"><School class="size-5" aria-hidden="true" /></span>
                <div><p class="text-xs text-muted-foreground">Asal sekolah</p><p class="mt-1 text-xl font-semibold tabular-nums">{{ stats.schools.toLocaleString('id-ID') }}</p></div>
            </article>
            <article class="sisurat-panel flex items-center gap-4 p-5">
                <span class="sisurat-icon-tile"><BookOpen class="size-5" aria-hidden="true" /></span>
                <div><p class="text-xs text-muted-foreground">Program studi diminati</p><p class="mt-1 text-xl font-semibold tabular-nums">{{ stats.programs.toLocaleString('id-ID') }}</p></div>
            </article>
        </section>

        <section class="sisurat-panel overflow-hidden">
            <div class="flex flex-wrap items-end justify-between gap-4 border-b border-border/70 p-5 sm:p-6">
                <div>
                    <h2 class="text-lg font-semibold">Data yang masuk</h2>
                    <p class="mt-1 text-sm text-muted-foreground">{{ submissions.total.toLocaleString('id-ID') }} data calon mahasiswa</p>
                </div>
                <form class="flex w-full gap-2 sm:w-auto" @submit.prevent="search">
                    <label class="relative min-w-0 flex-1 sm:w-80">
                        <span class="sr-only">Cari calon mahasiswa, program studi, sekolah, atau affiliator</span>
                        <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
                        <input v-model="query" type="search" class="sisurat-input pl-10" placeholder="Cari nama, sekolah, affiliator..." :disabled="searching" />
                    </label>
                    <button class="sisurat-secondary-action" type="submit" :disabled="searching">Cari</button>
                </form>
            </div>

            <p v-if="requestError" role="alert" class="m-5 rounded-xl border border-destructive/25 bg-destructive/5 p-4 text-sm text-destructive">{{ requestError }}</p>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1050px] border-collapse text-left text-sm">
                    <thead class="bg-muted/40 text-xs text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Nama calon mahasiswa</th>
                            <th class="px-4 py-3 font-semibold">Program studi</th>
                            <th class="px-4 py-3 font-semibold">Asal sekolah</th>
                            <th class="px-4 py-3 font-semibold">Kabupaten/kota</th>
                            <th class="px-4 py-3 font-semibold">Alamat sekolah</th>
                            <th class="px-4 py-3 font-semibold">Dosen/guru pembawa</th>
                            <th class="px-4 py-3 font-semibold">Waktu masuk</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/70">
                        <tr v-for="submission in submissions.data" :key="submission.id" class="align-top">
                            <td class="px-4 py-4 font-medium">{{ submission.name }}</td>
                            <td class="px-4 py-4">{{ submission.study_program }}</td>
                            <td class="px-4 py-4">{{ submission.school_name }}</td>
                            <td class="px-4 py-4">{{ submission.school_district }}</td>
                            <td class="max-w-64 px-4 py-4 whitespace-normal">{{ submission.school_address }}</td>
                            <td class="px-4 py-4">{{ submission.affiliate_name }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-xs text-muted-foreground">{{ submission.submitted_at }}</td>
                        </tr>
                        <tr v-if="submissions.data.length === 0">
                            <td colspan="7" class="px-5 py-14 text-center">
                                <Users class="mx-auto size-8 text-muted-foreground/70" aria-hidden="true" />
                                <p class="mt-3 font-medium">{{ filters.q ? 'Data tidak ditemukan' : 'Belum ada data calon mahasiswa' }}</p>
                                <p class="mt-1 text-sm text-muted-foreground">{{ filters.q ? 'Coba kata kunci lain.' : 'Bagikan tautan formulir untuk mulai menerima isian.' }}</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="submissions.last_page > 1" class="flex items-center justify-between border-t border-border/70 p-4">
                <p class="text-xs text-muted-foreground">Menampilkan {{ submissions.from }}–{{ submissions.to }} dari {{ submissions.total }}</p>
                <div class="flex gap-2">
                    <Link v-if="submissions.prev_page_url" :href="submissions.prev_page_url" class="sisurat-secondary-action min-h-9! px-3! text-xs!">Sebelumnya</Link>
                    <span v-else class="sisurat-secondary-action min-h-9! px-3! text-xs! opacity-50">Sebelumnya</span>
                    <Link v-if="submissions.next_page_url" :href="submissions.next_page_url" class="sisurat-secondary-action min-h-9! px-3! text-xs!">Berikutnya</Link>
                    <span v-else class="sisurat-secondary-action min-h-9! px-3! text-xs! opacity-50">Berikutnya</span>
                </div>
            </div>
        </section>
    </main>
</template>
