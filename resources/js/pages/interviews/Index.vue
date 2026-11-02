<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    ClipboardList,
    ListChecks,
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
import { interviewTimestamp, type InterviewSummary } from '@/lib/interviews';
import {
    useMasterDataDeletion,
    type MasterDataPagination,
} from '@/lib/masterData';
import { create, destroy, index, show } from '@/routes/interviews';

type Status = 'all' | 'open' | 'closed';
type DeletionTarget = { id: number; name: string; submissions_count: number };
const props = defineProps<{
    interviews: MasterDataPagination<InterviewSummary>;
    filters: { q: string; status: Status };
    stats: { forms: number; open: number; submissions: number };
}>();
const query = ref(props.filters.q);
const status = ref<Status>(props.filters.status);
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
} = useMasterDataDeletion<DeletionTarget>(
    (id) =>
        destroy.url(id, {
            query: {
                q: props.filters.q || undefined,
                status: props.filters.status,
                page: props.interviews.current_page,
            },
        }),
    'interview-create',
);
const busy = computed(() => searching.value || deleteForm.processing);
watch(
    () => props.filters,
    (filters) => {
        query.value = filters.q;
        status.value = filters.status;
    },
);
function search(): void {
    if (busy.value) return;
    requestError.value = '';
    router.get(
        index.url(),
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
                    'Koneksi terputus. Periksa koneksi lalu coba kembali.';
                return false;
            },
            onHttpException: () => {
                requestError.value =
                    'Daftar formulir belum dapat dimuat. Coba kembali.';
                return false;
            },
        },
    );
}
function resetFilters(): void {
    query.value = '';
    status.value = 'all';
    search();
}
function openDeletion(interview: InterviewSummary, event: Event): void {
    if (busy.value) return;
    requestDeletion(
        {
            id: interview.id,
            name: interview.title,
            submissions_count: interview.submissions_count,
        },
        event,
    );
}
</script>

<template>
    <main class="sisurat-page space-y-6">
        <Head title="Tes Wawancara Mahasiswa Baru" />
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="sisurat-kicker">Penerimaan Mahasiswa Baru</p>
                <h1 class="sisurat-title mt-2">Tes Wawancara</h1>
                <p class="sisurat-subtitle mt-2">
                    Susun pertanyaan, bagikan tautan, dan baca jawaban mahasiswa
                    baru dalam satu tempat.
                </p>
            </div>
            <Link
                id="interview-create"
                :href="create()"
                class="sisurat-primary-action"
                :class="{ 'pointer-events-none opacity-50': busy }"
                :aria-disabled="busy"
                @click="busy && $event.preventDefault()"
                ><Plus class="size-4" aria-hidden="true" />Buat form
                wawancara</Link
            >
        </header>

        <section
            class="grid gap-4 sm:grid-cols-3"
            aria-label="Ringkasan tes wawancara"
        >
            <article
                class="sisurat-panel flex items-center justify-between gap-3 px-5 py-4"
            >
                <div>
                    <p class="text-xs text-muted-foreground">Form wawancara</p>
                    <strong
                        class="mt-2 block text-2xl font-semibold tabular-nums"
                        >{{ stats.forms.toLocaleString('id-ID') }}</strong
                    >
                </div>
                <ClipboardList class="size-6 text-primary" aria-hidden="true" />
            </article>
            <article
                class="sisurat-panel flex items-center justify-between gap-3 px-5 py-4"
            >
                <div>
                    <p class="text-xs text-muted-foreground">
                        Pengisian dibuka
                    </p>
                    <strong
                        class="mt-2 block text-2xl font-semibold tabular-nums"
                        >{{ stats.open.toLocaleString('id-ID') }}</strong
                    >
                </div>
                <ListChecks class="size-6 text-primary" aria-hidden="true" />
            </article>
            <article
                class="sisurat-panel flex items-center justify-between gap-3 px-5 py-4"
            >
                <div>
                    <p class="text-xs text-muted-foreground">Jawaban masuk</p>
                    <strong
                        class="mt-2 block text-2xl font-semibold tabular-nums"
                        >{{ stats.submissions.toLocaleString('id-ID') }}</strong
                    >
                </div>
                <Users class="size-6 text-primary" aria-hidden="true" />
            </article>
        </section>

        <section
            class="sisurat-panel grid gap-5 p-5 sm:grid-cols-3 sm:p-6"
            aria-label="Cara menggunakan form wawancara"
        >
            <div
                v-for="(step, stepIndex) in [
                    {
                        title: 'Siapkan pertanyaan',
                        text: 'Buat pertanyaan dan tentukan mana yang wajib dijawab.',
                    },
                    {
                        title: 'Bagikan tautan',
                        text: 'Mahasiswa mengisi identitas dan mengetik jawaban dari ponsel.',
                    },
                    {
                        title: 'Tinjau jawaban',
                        text: 'Cari peserta, baca hasil, cetak, atau unduh rekap CSV.',
                    },
                ]"
                :key="step.title"
                class="flex gap-3"
            >
                <span
                    class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary"
                    >{{ stepIndex + 1 }}</span
                >
                <div>
                    <h2 class="text-sm font-semibold">{{ step.title }}</h2>
                    <p class="mt-1 text-xs leading-6 text-muted-foreground">
                        {{ step.text }}
                    </p>
                </div>
            </div>
        </section>

        <section
            class="sisurat-panel p-5 sm:p-6"
            aria-label="Pencarian form wawancara"
        >
            <form @submit.prevent="search">
                <fieldset
                    :disabled="busy"
                    class="flex min-w-0 flex-wrap items-end gap-3"
                >
                    <div class="min-w-0 flex-1 basis-64">
                        <label
                            for="interview-search"
                            class="mb-2 block text-sm font-medium"
                            >Cari form wawancara</label
                        >
                        <div class="relative">
                            <Search
                                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                aria-hidden="true"
                            /><input
                                id="interview-search"
                                v-model="query"
                                type="search"
                                maxlength="200"
                                class="sisurat-input pl-10"
                                placeholder="Judul wawancara"
                            />
                        </div>
                    </div>
                    <div class="min-w-40">
                        <label
                            for="interview-status"
                            class="mb-2 block text-sm font-medium"
                            >Status pengisian</label
                        ><select
                            id="interview-status"
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
            aria-labelledby="interview-list-heading"
            :aria-busy="busy"
        >
            <header
                class="flex items-center justify-between gap-3 border-b border-border/60 px-5 py-4"
            >
                <h2 id="interview-list-heading" class="text-sm font-semibold">
                    Daftar form wawancara
                </h2>
                <span class="text-xs text-muted-foreground"
                    >{{
                        interviews.total.toLocaleString('id-ID')
                    }}
                    formulir</span
                >
            </header>
            <div
                v-if="interviews.data.length"
                class="divide-y divide-border/60"
            >
                <article
                    v-for="interview in interviews.data"
                    :key="interview.id"
                    class="flex flex-col gap-4 p-5 transition-colors hover:bg-muted/20 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-sm font-semibold break-words">
                                {{ interview.title }}
                            </h3>
                            <span
                                class="rounded-full px-2.5 py-1 text-[11px] font-medium"
                                :class="
                                    interview.is_open
                                        ? 'bg-primary/10 text-primary'
                                        : 'bg-muted text-muted-foreground'
                                "
                                >{{
                                    interview.is_open ? 'Dibuka' : 'Ditutup'
                                }}</span
                            >
                        </div>
                        <p
                            v-if="interview.description"
                            class="mt-2 line-clamp-2 text-xs leading-6 break-words text-muted-foreground"
                        >
                            {{ interview.description }}
                        </p>
                        <p class="mt-3 text-xs leading-6 text-muted-foreground">
                            {{ interview.questions_count }} pertanyaan ·
                            {{ interview.submissions_count }} jawaban masuk
                        </p>
                        <p class="mt-1 text-[11px] text-muted-foreground">
                            Dibuat
                            {{ interviewTimestamp(interview.created_at) }}
                        </p>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-2">
                        <Link
                            :href="show(interview.id)"
                            class="sisurat-secondary-action"
                            :class="{ 'pointer-events-none opacity-50': busy }"
                            :aria-disabled="busy"
                            @click="busy && $event.preventDefault()"
                            >Kelola form<ArrowRight
                                class="size-4"
                                aria-hidden="true" /></Link
                        ><button
                            type="button"
                            class="sisurat-secondary-action border-destructive/25 text-destructive hover:bg-destructive/10"
                            :disabled="busy"
                            :aria-label="`Hapus form ${interview.title}`"
                            @click="openDeletion(interview, $event)"
                        >
                            <Trash2 class="size-4" aria-hidden="true" />Hapus
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
                            ? 'Form tidak ditemukan'
                            : 'Belum ada form wawancara'
                    }}
                </h3>
                <p
                    class="mt-2 max-w-md text-xs leading-7 text-muted-foreground"
                >
                    {{
                        filters.q || filters.status !== 'all'
                            ? 'Coba kata kunci lain atau hapus filter pencarian.'
                            : 'Buat form pertama, sesuaikan pertanyaannya, lalu bagikan tautannya kepada mahasiswa baru.'
                    }}
                </p>
                <button
                    v-if="filters.q || filters.status !== 'all'"
                    type="button"
                    class="sisurat-secondary-action mt-5"
                    :disabled="busy"
                    @click="resetFilters"
                >
                    Hapus filter</button
                ><Link
                    v-else
                    :href="create()"
                    class="sisurat-primary-action mt-5"
                    >Buat form wawancara</Link
                >
            </div>
        </section>

        <nav
            v-if="interviews.data.length"
            class="flex flex-wrap items-center justify-between gap-4"
            aria-label="Halaman form wawancara"
        >
            <p class="text-xs text-muted-foreground">
                {{ interviews.from }}–{{ interviews.to }} dari
                {{ interviews.total }} formulir
            </p>
            <div class="flex items-center gap-3">
                <Link
                    v-if="interviews.prev_page_url"
                    :href="interviews.prev_page_url"
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
                    >{{ interviews.current_page }} /
                    {{ interviews.last_page }}</span
                ><Link
                    v-if="interviews.next_page_url"
                    :href="interviews.next_page_url"
                    preserve-scroll
                    class="sisurat-secondary-action min-h-9 px-3 text-xs"
                    :class="{ 'pointer-events-none opacity-50': busy }"
                    :aria-disabled="busy"
                    @click="busy && $event.preventDefault()"
                    >Berikutnya<ArrowRight class="size-3.5" aria-hidden="true"
                /></Link>
            </div>
        </nav>

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
                    ><DialogTitle>Hapus form wawancara?</DialogTitle
                    ><DialogDescription
                        >Formulir dan seluruh jawaban peserta akan dihapus
                        permanen. Tautan pengisian tidak dapat digunakan
                        kembali.</DialogDescription
                    ></DialogHeader
                >
                <div class="rounded-xl border border-border bg-muted/40 p-4">
                    <p class="text-sm font-semibold break-words">
                        {{ deleteTarget?.name }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ deleteTarget?.submissions_count ?? 0 }} jawaban akan
                        ikut dihapus.
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
                            deleteForm.processing ? 'Menghapus…' : 'Hapus form'
                        }}</Button
                    ></DialogFooter
                ></DialogContent
            ></Dialog
        >
    </main>
</template>
