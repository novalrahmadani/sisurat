<script setup lang="ts">
import PmbTestSessionSummary from '@/components/PmbTestSessionSummary.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    ClipboardList,
    Download,
    ListChecks,
    FileDown,
    Pencil,
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
    interviewTimestamp,
    type InterviewForm,
    type InterviewSubmission,
} from '@/lib/interviews';
import {
    useMasterDataDeletion,
    type MasterDataPagination,
} from '@/lib/masterData';
import {
    edit,
    exportMethod as exportInterviews,
    index,
    pdf as exportPdf,
    show,
    qr,
    flyer,
} from '@/routes/interviews';
import {
    destroy as destroySubmission,
    show as showSubmission,
} from '@/routes/interviews/submissions';

const props = defineProps<{
    interview: InterviewForm;
    submissions: MasterDataPagination<InterviewSubmission>;
    filters: { q: string };
    public_url: string;
}>();
const query = ref(props.filters.q);
const searching = ref(false);
const refreshing = ref(false);
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
} = useMasterDataDeletion<InterviewSubmission>(
    (id) =>
        destroySubmission.url(
            { interview: props.interview.id, submission: id },
            {
                query: {
                    q: props.filters.q || undefined,
                    page: props.submissions.current_page,
                },
            },
        ),
    'interview-participant-search',
);
const busy = computed(
    () => searching.value || refreshing.value || deleteForm.processing,
);
watch(
    () => props.filters.q,
    (value) => {
        query.value = value;
    },
);
function search(): void {
    if (busy.value) return;
    requestError.value = '';
    router.get(
        show.url(props.interview.id),
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
                    'Hasil wawancara belum dapat dimuat. Coba kembali.';
                return false;
            },
        },
    );
}
function refresh(): void {
    if (busy.value) return;
    requestError.value = '';
    router.reload({
        only: ['interview', 'submissions', 'filters', 'public_url'],
        onStart: () => {
            refreshing.value = true;
        },
        onFinish: () => {
            refreshing.value = false;
        },
        onNetworkError: () => {
            requestError.value =
                'Koneksi terputus. Hasil wawancara belum diperbarui. Coba kembali.';
            return false;
        },
        onHttpException: () => {
            requestError.value =
                'Hasil wawancara belum dapat diperbarui. Coba kembali.';
            return false;
        },
    });
}
function resetFilters(): void {
    query.value = '';
    search();
}
function openDeletion(submission: InterviewSubmission, event: Event): void {
    if (busy.value) return;
    requestDeletion(submission, event);
}
</script>

<template>
    <main class="sisurat-page space-y-6">
        <Head :title="`Wawancara · ${interview.title}`" />
        <Link
            :href="index()"
            class="sisurat-secondary-action w-fit"
            :class="{ 'pointer-events-none opacity-50': busy }"
            :aria-disabled="busy"
            @click="busy && $event.preventDefault()"
            ><ArrowLeft class="size-4" aria-hidden="true" />Daftar form
            wawancara</Link
        >
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div class="min-w-0 flex-1">
                <p class="sisurat-kicker">Tes Wawancara · Mahasiswa Baru</p>
                <h1 class="sisurat-title mt-2 break-words">
                    {{ interview.title }}
                </h1>
            </div>
            <div class="flex max-w-full flex-wrap gap-2">
                <Link
                    :href="edit(interview.id)"
                    class="sisurat-secondary-action"
                    :class="{ 'pointer-events-none opacity-50': busy }"
                    :aria-disabled="busy"
                    @click="busy && $event.preventDefault()"
                    ><Pencil class="size-4" aria-hidden="true" />Ubah form &amp;
                    pertanyaan</Link
                ><a
                    :href="exportInterviews.url(interview.id)"
                    class="sisurat-secondary-action"
                    :class="{ 'pointer-events-none opacity-50': busy }"
                    :aria-disabled="busy"
                    @click="busy && $event.preventDefault()"
                    ><Download class="size-4" aria-hidden="true" />Unduh CSV</a
                ><a
                    :href="exportPdf.url(interview.id)"
                    target="_blank"
                    rel="noopener noreferrer"
                    download
                    class="sisurat-primary-action"
                    :class="{ 'pointer-events-none opacity-50': busy }"
                    :aria-disabled="busy"
                    @click="busy && $event.preventDefault()"
                    ><FileDown class="size-4" aria-hidden="true" />Unduh PDF
                    seluruh peserta</a
                >
            </div>
        </header>

        <section
            class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_280px]"
            aria-label="Informasi form wawancara"
        >
            <div class="sisurat-panel p-5 sm:p-6">
                <PublicFormShare
                    title="Bagikan form kepada mahasiswa"
                    description="Mahasiswa dapat mengisi identitas dan mengetik jawabannya tanpa akun SISURAT. Bagikan tautan atau QR formulir ini."
                    :url="public_url"
                    :qr-url="qr.url(interview.id)"
                    :download-url="
                        qr.url(interview.id, { query: { download: 1 } })
                    "
                    link-label="Tautan form wawancara"
                    open-label="Buka halaman mahasiswa"
                    :disabled="busy"
                    :flyer-url="flyer.url(interview.id)"
                    :flyer-download-url="
                        flyer.url(interview.id, { query: { download: 1 } })
                    "
                />
                <div
                    v-if="interview.description"
                    class="mt-4 border-t border-border pt-4"
                >
                    <h3 class="text-xs font-medium">Petunjuk pengisian</h3>
                    <p
                        class="mt-2 text-xs leading-7 break-words whitespace-pre-wrap text-muted-foreground"
                    >
                        {{ interview.description }}
                    </p>
                </div>
            </div>
            <div class="sisurat-panel flex flex-col gap-5 p-5 sm:p-6">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-xs text-muted-foreground"
                        >Status pengisian</span
                    ><span
                        class="rounded-full px-2.5 py-1 text-[11px] font-medium"
                        :class="
                            interview.is_open
                                ? 'bg-primary/10 text-primary'
                                : 'bg-muted text-muted-foreground'
                        "
                        >{{ interview.is_open ? 'Dibuka' : 'Ditutup' }}</span
                    >
                </div>
                <div>
                    <p
                        class="flex items-center gap-2 text-xs text-muted-foreground"
                    >
                        <Users class="size-4" aria-hidden="true" />Jawaban masuk
                    </p>
                    <strong
                        class="mt-2 block text-3xl font-semibold tabular-nums"
                        >{{
                            interview.submissions_count.toLocaleString('id-ID')
                        }}</strong
                    >
                </div>
                <p
                    class="flex items-center gap-2 text-xs text-muted-foreground"
                >
                    <ListChecks class="size-4" aria-hidden="true" />{{
                        interview.questions.length
                    }}
                    pertanyaan
                </p>
                <p
                    class="border-t border-border pt-4 text-xs leading-6 text-muted-foreground"
                >
                    Untuk membuka atau menutup pengisian, gunakan
                    <span class="font-medium text-foreground"
                        >Ubah form &amp; pertanyaan</span
                    >.
                </p>
            </div>
        </section>

        <details class="sisurat-panel group overflow-hidden">
            <summary
                class="cursor-pointer px-5 py-4 text-sm font-semibold focus-visible:ring-3 focus-visible:ring-ring/30 focus-visible:outline-none"
            >
                Lihat susunan pertanyaan saat ini ({{
                    interview.questions.length
                }})
            </summary>
            <div class="border-t border-border px-5 py-5">
                <ol class="space-y-4">
                    <li
                        v-for="(question, questionIndex) in interview.questions"
                        :key="question.id"
                        class="flex gap-3"
                    >
                        <span
                            class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-xs font-medium text-primary"
                            >{{ questionIndex + 1 }}</span
                        >
                        <div>
                            <p
                                class="text-sm leading-7 break-words whitespace-pre-wrap"
                            >
                                {{ question.prompt }}
                            </p>
                            <span class="text-[11px] text-muted-foreground">{{
                                question.required ? 'Wajib dijawab' : 'Opsional'
                            }}</span>
                        </div>
                    </li>
                </ol>
                <p class="mt-5 text-xs leading-6 text-muted-foreground">
                    Setiap hasil menyimpan salinan pertanyaan saat mahasiswa
                    mengirim jawaban. Perubahan pertanyaan tidak mengubah hasil
                    sebelumnya.
                </p>
            </div>
        </details>

        <section
            class="sisurat-panel overflow-hidden"
            aria-labelledby="interview-results-heading"
            :aria-busy="busy"
        >
            <div class="p-5 sm:p-6">
                <header
                    class="mb-5 flex flex-wrap items-center justify-between gap-3"
                >
                    <div>
                        <h2
                            id="interview-results-heading"
                            class="text-base font-semibold"
                        >
                            Hasil wawancara
                        </h2>
                        <p class="mt-1 text-xs leading-6 text-muted-foreground">
                            Buka detail peserta untuk membaca seluruh jawaban.
                            CSV dan PDF memuat seluruh peserta, termasuk yang
                            berada di halaman lain atau di luar hasil pencarian.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="sisurat-secondary-action"
                        :disabled="busy"
                        :aria-busy="refreshing"
                        @click="refresh"
                    >
                        <RefreshCw
                            class="size-4"
                            :class="{ 'motion-safe:animate-spin': refreshing }"
                            aria-hidden="true"
                        />{{ refreshing ? 'Memperbarui…' : 'Perbarui' }}
                    </button>
                </header>
                <form @submit.prevent="search">
                    <fieldset
                        :disabled="busy"
                        class="flex min-w-0 flex-wrap items-end gap-3"
                    >
                        <div class="min-w-0 flex-1 basis-64">
                            <label
                                for="interview-participant-search"
                                class="mb-2 block text-sm font-medium"
                                >Cari mahasiswa</label
                            >
                            <div class="relative">
                                <Search
                                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden="true"
                                /><input
                                    id="interview-participant-search"
                                    v-model="query"
                                    type="search"
                                    class="sisurat-input pl-10"
                                    maxlength="200"
                                    placeholder="Nama, nomor registrasi, atau program studi"
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
                v-if="submissions.data.length"
                class="overflow-x-auto border-t border-border/60"
            >
                <table class="w-full min-w-200 text-left text-sm">
                    <caption class="sr-only">
                        Hasil wawancara
                        {{
                            interview.title
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
                                Nomor registrasi
                            </th>
                            <th scope="col" class="px-4 py-3 font-medium">
                                Program studi
                            </th>
                            <th scope="col" class="px-4 py-3 font-medium">
                                Waktu pengisian
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
                            v-for="(
                                submission, submissionIndex
                            ) in submissions.data"
                            :key="submission.id"
                            class="transition-colors hover:bg-muted/25"
                        >
                            <td class="px-5 py-4 text-xs tabular-nums">
                                {{ (submissions.from ?? 1) + submissionIndex }}
                            </td>
                            <th
                                scope="row"
                                class="max-w-64 px-4 py-4 font-semibold break-words"
                            >
                                {{ submission.name }}
                            </th>
                            <td
                                class="max-w-48 px-4 py-4 text-xs leading-6 break-words"
                            >
                                {{ submission.registration_number }}
                            </td>
                            <td
                                class="max-w-64 px-4 py-4 text-xs leading-6 break-words"
                            >
                                {{ submission.study_program }}
                                <PmbTestSessionSummary
                                    class="mt-3"
                                    :session="submission"
                                />
                            </td>
                            <td
                                class="px-4 py-4 text-xs leading-6 text-muted-foreground"
                            >
                                {{
                                    interviewTimestamp(submission.submitted_at)
                                }}
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-2">
                                    <Link
                                        :href="
                                            showSubmission({
                                                interview: interview.id,
                                                submission: submission.id,
                                            })
                                        "
                                        class="sisurat-secondary-action min-h-10 px-3 text-xs"
                                        :class="{
                                            'pointer-events-none opacity-50':
                                                busy,
                                        }"
                                        :aria-disabled="busy"
                                        :aria-label="`Baca jawaban ${submission.name}`"
                                        @click="busy && $event.preventDefault()"
                                        >Lihat jawaban<ArrowRight
                                            class="size-3.5"
                                            aria-hidden="true" /></Link
                                    ><button
                                        type="button"
                                        class="sisurat-secondary-action min-h-10 border-destructive/25 px-3 text-xs text-destructive hover:bg-destructive/10"
                                        :disabled="busy"
                                        :aria-label="`Hapus jawaban ${submission.name}`"
                                        @click="
                                            openDeletion(submission, $event)
                                        "
                                    >
                                        <Trash2
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                    </button>
                                </div>
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
                            ? 'Mahasiswa tidak ditemukan'
                            : 'Belum ada jawaban masuk'
                    }}
                </h3>
                <p
                    class="mt-2 max-w-md text-xs leading-7 text-muted-foreground"
                >
                    {{
                        filters.q
                            ? 'Coba kata kunci lain atau hapus filter pencarian.'
                            : interview.is_open
                              ? 'Bagikan tautan kepada mahasiswa baru. Klik Perbarui untuk melihat jawaban terbaru.'
                              : 'Buka pengisian melalui Ubah form & pertanyaan agar mahasiswa dapat mengirim jawaban.'
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
                v-if="submissions.data.length"
                class="flex flex-wrap items-center justify-between gap-4 border-t border-border/60 px-5 py-4"
            >
                <p class="text-xs text-muted-foreground">
                    {{ submissions.from }}–{{ submissions.to }} dari
                    {{ submissions.total }} mahasiswa{{
                        filters.q ? ' hasil pencarian' : ''
                    }}
                </p>
                <nav
                    class="flex items-center gap-3"
                    aria-label="Halaman hasil wawancara"
                >
                    <Link
                        v-if="submissions.prev_page_url"
                        :href="submissions.prev_page_url"
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
                        >{{ submissions.current_page }} /
                        {{ submissions.last_page }}</span
                    ><Link
                        v-if="submissions.next_page_url"
                        :href="submissions.next_page_url"
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
            CSV berisi satu baris per peserta dengan seluruh pertanyaan dan
            jawaban. PDF berisi rekap dan rincian seluruh peserta. Ekspor tetap
            mencakup semua hasil meskipun daftar sedang dicari atau berpindah
            halaman. Cetak per mahasiswa tersedia pada detail jawaban.
        </p>

        <Dialog :open="deleteOpen" @update:open="updateDeleteDialog"
            ><DialogContent
                :show-close-button="false"
                class="rounded-2xl"
                @interact-outside="
                    deleteForm.processing && $event.preventDefault()
                "
                @escape-key-down="
                    deleteForm.processing && $event.preventDefault()
                "
                @close-auto-focus="restoreDeleteFocus"
                ><DialogHeader
                    ><DialogTitle>Hapus hasil wawancara?</DialogTitle
                    ><DialogDescription
                        >Identitas dan seluruh jawaban wawancara mahasiswa ini
                        akan dihapus secara permanen.</DialogDescription
                    ></DialogHeader
                >
                <div class="rounded-xl border border-border bg-muted/40 p-4">
                    <p class="text-sm font-semibold break-words">
                        {{ deleteTarget?.name }}
                    </p>
                    <p
                        class="mt-1 text-xs leading-6 break-words text-muted-foreground"
                    >
                        Nomor registrasi:
                        {{ deleteTarget?.registration_number }}
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
                        >{{
                            deleteForm.processing ? 'Menghapus…' : 'Hapus hasil'
                        }}</Button
                    ></DialogFooter
                ></DialogContent
            ></Dialog
        >
    </main>
</template>
