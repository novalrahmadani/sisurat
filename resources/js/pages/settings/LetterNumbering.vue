<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { Save } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    edit,
    update,
} from '@/actions/App/Http/Controllers/Settings/LetterNumberingController';

const props = defineProps<{
    year: number;
    nextSequence: number;
    configured: boolean;
    lastOfficialSequence: number;
    trialBatchCount: number;
}>();
const years = Array.from({ length: 101 }, (_, index) => 2000 + index);
const form = useForm({
    year: props.year,
    next_sequence: props.configured ? (props.nextSequence as number | '') : '',
    expected_next_sequence: props.nextSequence,
});
const submitError = ref('');
const example = computed(() =>
    Number.isInteger(form.next_sequence) && Number(form.next_sequence) > 0
        ? `${form.next_sequence}, ${Number(form.next_sequence) + 1}, ${Number(form.next_sequence) + 2}, …`
        : 'Nomor akan mengikuti angka yang Anda simpan.',
);
watch(
    () => props.nextSequence,
    (value) => {
        form.expected_next_sequence = value;
        form.next_sequence = props.configured ? value : '';
    },
);
function changeYear(event: Event): void {
    router.get(
        edit.url({
            query: { year: Number((event.target as HTMLSelectElement).value) },
        }),
    );
}
function save(): void {
    submitError.value = '';
    form.put(update.url(), {
        preserveScroll: true,
        onSuccess: () => {
            form.expected_next_sequence = props.nextSequence;
        },
        onNetworkError: () => {
            submitError.value =
                'Koneksi terputus. Periksa koneksi lalu coba kembali.';
            return false;
        },
        onHttpException: () => {
            submitError.value =
                'Pengaturan belum dapat disimpan. Muat ulang halaman dan coba kembali.';
            return false;
        },
    });
}
</script>

<template>
    <div class="space-y-6">
        <Head title="Penomoran surat" />
        <header>
            <h1 class="text-xl font-semibold">Penomoran surat</h1>
            <p class="mt-2 text-sm leading-6 text-muted-foreground">
                Lanjutkan nomor dari buku agenda manual. Pengaturan ini berlaku
                untuk semua jenis surat pada tahun yang dipilih.
            </p>
        </header>
        <div>
            <label for="numbering-year" class="mb-2 block text-sm font-medium"
                >Tahun surat</label
            >
            <select
                id="numbering-year"
                :value="year"
                class="sisurat-input max-w-48"
                :disabled="form.processing"
                @change="changeYear"
            >
                <option v-for="option in years" :key="option" :value="option">
                    {{ option }}
                </option>
            </select>
        </div>
        <section
            class="rounded-xl border border-border bg-muted/30 p-4"
            aria-label="Status penomoran tahun terpilih"
        >
            <p class="text-sm font-medium">
                Nomor berikutnya saat ini: <strong>{{ nextSequence }}</strong>
            </p>
            <p class="mt-2 text-xs leading-6 text-muted-foreground">
                {{
                    lastOfficialSequence
                        ? `Nomor surat resmi terakhir yang digunakan atau dicadangkan: ${lastOfficialSequence}.`
                        : 'Belum ada nomor surat resmi yang digunakan pada tahun ini.'
                }}
            </p>
            <p
                v-if="trialBatchCount"
                class="mt-2 text-xs leading-6 text-muted-foreground"
            >
                {{ trialBatchCount }} batch uji coba tersimpan terpisah dan
                tidak memengaruhi urutan resmi.
            </p>
        </section>
        <form class="space-y-5" @submit.prevent="save">
            <div>
                <label
                    for="next-official-sequence"
                    class="mb-2 block text-sm font-medium"
                    >Nomor surat berikutnya</label
                >
                <input
                    id="next-official-sequence"
                    v-model.number="form.next_sequence"
                    type="number"
                    min="1"
                    max="99999"
                    step="1"
                    required
                    class="sisurat-input"
                    placeholder="Isi setelah nomor manual dipastikan"
                    aria-describedby="numbering-help numbering-error"
                    :aria-invalid="!!form.errors.next_sequence"
                    :disabled="form.processing"
                />
                <p
                    id="numbering-help"
                    class="mt-2 text-xs leading-6 text-muted-foreground"
                >
                    Isi nomor yang akan digunakan selanjutnya. Contoh: jika
                    nomor terakhir pada buku agenda 454, isi 455. Anda bisa
                    mengisinya nanti saat siap menggunakan surat resmi.
                </p>
                <p
                    v-if="form.errors.next_sequence"
                    id="numbering-error"
                    role="alert"
                    class="mt-2 text-sm text-destructive"
                >
                    {{ form.errors.next_sequence }}
                </p>
            </div>
            <div class="rounded-xl border border-primary/15 bg-primary/5 p-4">
                <p class="text-xs font-medium text-primary">
                    Contoh urutan berikutnya
                </p>
                <p class="mt-2 text-sm font-semibold" aria-live="polite">
                    {{ example }}
                </p>
            </div>
            <p v-if="submitError" role="alert" class="text-sm text-destructive">
                {{ submitError }}
            </p>
            <Button
                type="submit"
                :disabled="form.processing || form.next_sequence === ''"
                ><Save class="size-4" aria-hidden="true" />{{
                    form.processing ? 'Menyimpan…' : 'Simpan nomor berikutnya'
                }}</Button
            >
        </form>
        <p class="text-xs leading-6 text-muted-foreground">
            Surat yang sudah digunakan atau dihapus dari arsip resmi tetap
            mencadangkan nomornya. Jika masih membuat surat manual, sesuaikan
            nomor berikutnya sebelum membuat surat melalui SISURAT. Pada tahun
            baru, urutan dimulai dari 001 jika belum ada pengaturan khusus.
        </p>
    </div>
</template>
