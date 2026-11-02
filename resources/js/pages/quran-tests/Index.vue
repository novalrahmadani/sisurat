<script setup lang="ts">
import PmbTestSessionSummary from '@/components/PmbTestSessionSummary.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    BookOpen,
    CheckCircle2,
    Clock3,
    Download,
    Eye,
    Pencil,
    Plus,
    Link2,
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
    type MasterDataPagination,
} from '@/lib/masterData';
import {
    quranDate,
    quranResultClass,
    quranResultLabel,
    quranResultOptions,
    quranScore,
    type QuranAssessmentSummary,
    type QuranResultFilter,
} from '@/lib/quranAssessments';
import {
    create,
    destroy,
    edit,
    exportMethod,
    pdf,
    index,
    show,
} from '@/routes/quran-tests';
import { index as examinerLinks } from '@/routes/quran-exams';

const props = defineProps<{
    assessments: MasterDataPagination<QuranAssessmentSummary>;
    filters: { q: string; result: QuranResultFilter; exam_id: number | null };
    exams: Array<{ id: number; title: string }>;
    stats: {
        total: number;
        pending: number;
        completed: number;
        mq1: number;
        mq2: number;
        legacy: number;
    };
}>();
const resultOptions = computed(() =>
    quranResultOptions.filter(
        ({ value }) =>
            props.stats.legacy > 0 ||
            ['pending', 'mq1', 'mq2', props.filters.result].includes(value),
    ),
);
const mqCategories = [
    { key: 'mq1', label: 'MQ 1 · Rata-rata < 70' },
    { key: 'mq2', label: 'Berhak mengikuti MQ 2 · Rata-rata ≥ 70' },
] as const;
const selectedExam = ref(
    props.filters.exam_id === null ? '' : String(props.filters.exam_id),
);
const pdfUrl = computed(() =>
    pdf.url({
        query: {
            q: props.filters.q || undefined,
            result: props.filters.result,
            exam_id: props.filters.exam_id ?? undefined,
        },
    }),
);
const query = ref(props.filters.q);
const result = ref<QuranResultFilter>(props.filters.result);
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
} = useMasterDataDeletion<QuranAssessmentSummary>(
    (id) =>
        destroy.url(
            { quran_test: id },
            {
                query: {
                    q: props.filters.q || undefined,
                    result: props.filters.result,
                    exam_id: props.filters.exam_id ?? undefined,
                    page: props.assessments.current_page,
                },
            },
        ),
    'quran-create',
);
const busy = computed(() => searching.value || deleteForm.processing);
const csvUrl = computed(() =>
    exportMethod.url({
        query: {
            q: props.filters.q || undefined,
            result: props.filters.result,
            exam_id: props.filters.exam_id ?? undefined,
        },
    }),
);
watch(
    () => props.filters,
    (filters) => {
        query.value = filters.q;
        result.value = filters.result;
        selectedExam.value =
            filters.exam_id === null ? '' : String(filters.exam_id);
    },
);
function search(): void {
    if (busy.value) return;
    requestError.value = '';
    router.get(
        index.url(),
        {
            q: query.value.trim() || undefined,
            result: result.value,
            exam_id: selectedExam.value
                ? Number(selectedExam.value)
                : undefined,
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
                    'Daftar penilaian belum dapat dimuat. Coba kembali.';
                return false;
            },
        },
    );
}
function resetFilters(): void {
    query.value = '';
    result.value = 'all';
    search();
}
</script>

<template>
    <main class="sisurat-page space-y-6">
        <Head title="Tes Baca Al-Qur’an" />
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="sisurat-kicker">Penerimaan Mahasiswa Baru</p>
                <h1 class="sisurat-title mt-2">Tes Baca Al-Qur’an</h1>
                <p class="sisurat-subtitle mt-2">
                    Catat penilaian baca Al-Qur’an calon mahasiswa saat tes
                    tatap muka.
                </p>
            </div>
            <Link :href="examinerLinks()" class="sisurat-secondary-action"
                ><Link2 class="size-4" />Tautan penguji</Link
            >
            <Link
                id="quran-create"
                :href="create()"
                class="sisurat-primary-action"
                :class="{ 'pointer-events-none opacity-50': busy }"
                :aria-disabled="busy"
                @click="busy && $event.preventDefault()"
            >
                <Plus class="size-4" aria-hidden="true" />Tambah penilaian
            </Link>
        </header>

        <section
            class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
            aria-label="Ringkasan penilaian"
        >
            <article
                class="sisurat-panel flex items-center justify-between gap-3 px-5 py-4"
            >
                <div>
                    <p class="text-xs text-muted-foreground">Total penilaian</p>
                    <strong
                        class="mt-2 block text-2xl font-semibold tabular-nums"
                        >{{ stats.total.toLocaleString('id-ID') }}</strong
                    >
                </div>
                <BookOpen class="size-6 text-primary" aria-hidden="true" />
            </article>
            <article
                class="sisurat-panel flex items-center justify-between gap-3 px-5 py-4"
            >
                <div>
                    <p class="text-xs text-muted-foreground">
                        Menunggu penilaian
                    </p>
                    <strong
                        class="mt-2 block text-2xl font-semibold tabular-nums"
                        >{{ stats.pending.toLocaleString('id-ID') }}</strong
                    >
                </div>
                <Clock3 class="size-6 text-primary" aria-hidden="true" />
            </article>
            <article
                v-for="category in mqCategories"
                :key="category.key"
                class="sisurat-panel flex items-center justify-between gap-3 px-5 py-4"
            >
                <div>
                    <p class="text-xs text-muted-foreground">
                        {{ category.label }}
                    </p>
                    <strong
                        class="mt-2 block text-2xl font-semibold tabular-nums"
                        >{{
                            stats[category.key].toLocaleString('id-ID')
                        }}</strong
                    >
                </div>
                <CheckCircle2 class="size-6 text-primary" aria-hidden="true" />
            </article>
        </section>

        <p
            v-if="stats.legacy"
            class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900"
        >
            {{ stats.legacy }} penilaian format lama tetap tersimpan. Lengkapi
            nilai Fasohah melalui Ubah penilaian untuk menentukan kategori MQ.
        </p>

        <section class="sisurat-panel p-5 sm:p-6" aria-label="Cari penilaian">
            <form @submit.prevent="search">
                <fieldset
                    :disabled="busy"
                    class="flex min-w-0 flex-wrap items-end gap-3"
                >
                    <div class="min-w-0 flex-1 basis-64">
                        <label
                            for="quran-exam-filter"
                            class="mb-2 block text-sm font-medium"
                            >Kegiatan tes</label
                        ><select
                            id="quran-exam-filter"
                            v-model="selectedExam"
                            class="sisurat-input"
                        >
                            <option value="">Semua kegiatan</option>
                            <option
                                v-for="exam in exams"
                                :key="exam.id"
                                :value="String(exam.id)"
                            >
                                {{ exam.title }}
                            </option>
                        </select>
                    </div>
                    <div class="min-w-0 flex-1 basis-64">
                        <label
                            for="quran-search"
                            class="mb-2 block text-sm font-medium"
                            >Cari peserta</label
                        >
                        <div class="relative">
                            <Search
                                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                aria-hidden="true"
                            /><input
                                id="quran-search"
                                v-model="query"
                                type="search"
                                maxlength="200"
                                class="sisurat-input pl-10"
                                placeholder="Nama atau nomor registrasi"
                            />
                        </div>
                    </div>
                    <div class="min-w-48">
                        <label
                            for="quran-result-filter"
                            class="mb-2 block text-sm font-medium"
                            >Hasil penilaian</label
                        ><select
                            id="quran-result-filter"
                            v-model="result"
                            class="sisurat-input"
                        >
                            <option value="all">Semua hasil</option>
                            <option
                                v-for="option in resultOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
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
                        v-if="query || result !== 'all'"
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
            aria-labelledby="quran-list-heading"
            :aria-busy="busy"
        >
            <header
                class="flex flex-wrap items-center justify-between gap-3 border-b border-border/60 px-5 py-4"
            >
                <div>
                    <h2 id="quran-list-heading" class="text-sm font-semibold">
                        Daftar penilaian
                    </h2>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ assessments.total.toLocaleString('id-ID') }} data
                        sesuai filter
                    </p>
                </div>
                <a
                    :href="pdfUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    download
                    class="sisurat-secondary-action"
                    ><Download class="size-4" />Unduh rekap PDF</a
                >
                <a
                    :href="csvUrl"
                    class="sisurat-secondary-action min-h-9 px-3 text-xs"
                    :class="{ 'pointer-events-none opacity-50': busy }"
                    :aria-disabled="busy"
                    @click="busy && $event.preventDefault()"
                    ><Download class="size-3.5" aria-hidden="true" />Unduh rekap
                    CSV</a
                >
            </header>
            <div
                v-if="assessments.data.length"
                class="divide-y divide-border/60"
            >
                <article
                    v-for="assessment in assessments.data"
                    :key="assessment.id"
                    class="flex flex-col gap-4 p-5 transition-colors hover:bg-muted/20 xl:flex-row xl:items-center xl:justify-between"
                >
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-sm font-semibold break-words">
                                {{ assessment.name }}
                            </h3>
                            <PmbTestSessionSummary
                                class="mt-3"
                                :session="assessment"
                            />
                            <span
                                class="rounded-full px-2.5 py-1 text-[11px] font-medium"
                                :class="quranResultClass(assessment.result)"
                                >{{ quranResultLabel(assessment.result) }}</span
                            >
                        </div>
                        <p
                            class="mt-2 text-xs leading-6 break-words text-muted-foreground"
                        >
                            {{ assessment.registration_number }} ·
                            {{ assessment.study_program }}
                        </p>
                        <div
                            class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs leading-6 text-muted-foreground"
                        >
                            <span>{{
                                quranDate(assessment.assessment_date)
                            }}</span
                            ><span
                                >Penguji:
                                {{
                                    assessment.examiner_name || 'Belum diisi'
                                }}</span
                            ><span
                                >Rata-rata:
                                <strong
                                    class="font-semibold text-foreground tabular-nums"
                                    >{{
                                        quranScore(assessment.average_score)
                                    }}</strong
                                ></span
                            >
                        </div>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-2">
                        <Link
                            :href="show({ quran_test: assessment.id })"
                            class="sisurat-secondary-action min-h-9 px-3 text-xs"
                            :class="{ 'pointer-events-none opacity-50': busy }"
                            :aria-disabled="busy"
                            :aria-label="`Lihat penilaian ${assessment.name}`"
                            @click="busy && $event.preventDefault()"
                            ><Eye
                                class="size-3.5"
                                aria-hidden="true"
                            />Detail</Link
                        >
                        <Link
                            :href="edit({ quran_test: assessment.id })"
                            class="sisurat-secondary-action min-h-9 px-3 text-xs"
                            :class="{ 'pointer-events-none opacity-50': busy }"
                            :aria-disabled="busy"
                            :aria-label="`Ubah penilaian ${assessment.name}`"
                            @click="busy && $event.preventDefault()"
                            ><Pencil
                                class="size-3.5"
                                aria-hidden="true"
                            />Ubah</Link
                        >
                        <button
                            type="button"
                            class="sisurat-secondary-action min-h-9 border-destructive/25 px-3 text-xs text-destructive hover:bg-destructive/10"
                            :disabled="busy"
                            :aria-label="`Hapus penilaian ${assessment.name}`"
                            @click="requestDeletion(assessment, $event)"
                        >
                            <Trash2 class="size-3.5" aria-hidden="true" />Hapus
                        </button>
                    </div>
                </article>
            </div>
            <div
                v-else
                class="flex flex-col items-center px-6 py-14 text-center"
            >
                <span class="sisurat-icon-tile"
                    ><BookOpen class="size-6" aria-hidden="true"
                /></span>
                <h3 class="mt-4 text-sm font-semibold">
                    {{
                        filters.q || filters.result !== 'all'
                            ? 'Penilaian tidak ditemukan'
                            : 'Belum ada penilaian'
                    }}
                </h3>
                <p
                    class="mt-2 max-w-md text-xs leading-7 text-muted-foreground"
                >
                    {{
                        filters.q || filters.result !== 'all'
                            ? 'Coba nama atau nomor registrasi lain, atau hapus filter pencarian.'
                            : 'Tambahkan identitas peserta dan empat nilai. Kategori MQ dihitung otomatis dari nilai rata-rata.'
                    }}
                </p>
                <button
                    v-if="filters.q || filters.result !== 'all'"
                    type="button"
                    class="sisurat-secondary-action mt-5"
                    :disabled="busy"
                    @click="resetFilters"
                >
                    Hapus filter
                </button>
                <Link
                    v-else
                    :href="create()"
                    class="sisurat-primary-action mt-5"
                    >Tambah penilaian pertama</Link
                >
            </div>
        </section>

        <nav
            v-if="assessments.data.length"
            class="flex flex-wrap items-center justify-between gap-4"
            aria-label="Halaman penilaian"
        >
            <p class="text-xs text-muted-foreground">
                {{ assessments.from }}–{{ assessments.to }} dari
                {{ assessments.total }} penilaian
            </p>
            <div class="flex items-center gap-3">
                <Link
                    v-if="assessments.prev_page_url"
                    :href="assessments.prev_page_url"
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
                    >{{ assessments.current_page }} /
                    {{ assessments.last_page }}</span
                ><Link
                    v-if="assessments.next_page_url"
                    :href="assessments.next_page_url"
                    preserve-scroll
                    class="sisurat-secondary-action min-h-9 px-3 text-xs"
                    :class="{ 'pointer-events-none opacity-50': busy }"
                    :aria-disabled="busy"
                    @click="busy && $event.preventDefault()"
                    >Berikutnya<ArrowRight class="size-3.5" aria-hidden="true"
                /></Link>
            </div>
        </nav>
        <p class="text-xs leading-6 text-muted-foreground">
            Peserta yang mengikuti tes ulang dapat ditambahkan sebagai penilaian
            baru agar riwayat hasil tetap tersimpan.
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
                    ><DialogTitle>Hapus penilaian ini?</DialogTitle
                    ><DialogDescription
                        >Identitas, nilai, dan catatan pada penilaian ini akan
                        dihapus permanen.</DialogDescription
                    ></DialogHeader
                >
                <div class="rounded-xl border border-border bg-muted/40 p-4">
                    <p class="text-sm font-semibold break-words">
                        {{ deleteTarget?.name }}
                    </p>
                    <p class="mt-1 text-xs break-words text-muted-foreground">
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
                                : 'Hapus penilaian'
                        }}</Button
                    ></DialogFooter
                ></DialogContent
            ></Dialog
        >
    </main>
</template>
