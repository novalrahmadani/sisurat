<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save } from '@lucide/vue';
import { ref } from 'vue';
import { index as masterData } from '@/routes/master-data';
import { update } from '@/routes/master-data/letterhead';

const props = defineProps<{ letterheadUrl: string }>();
const form = useForm({ letterhead: null as File | null });
const inputVersion = ref(0);
const previewUrl = ref<string | null>(null);

function chooseFile(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
    previewUrl.value = file ? URL.createObjectURL(file) : null;
    form.letterhead = file;
    form.clearErrors('letterhead');
}

function save(): void {
    form.post(update.url(), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
            previewUrl.value = null;
            inputVersion.value++;
        },
    });
}
</script>

<template>
    <main class="sisurat-page space-y-6">
        <Head title="Kop Surat" />
        <Link :href="masterData()" class="sisurat-secondary-action w-fit">
            <ArrowLeft class="size-4" aria-hidden="true" />Master Data
        </Link>
        <header>
            <p class="sisurat-kicker">Master Data</p>
            <h1 class="sisurat-title mt-2">Kop Surat</h1>
            <p class="sisurat-subtitle mt-2 max-w-3xl">
                Gambar ini digunakan pada surat dan dokumen cetak Universitas
                Nurul Huda. Unggah kop lebar agar proporsional pada kertas A4.
            </p>
        </header>
        <form class="sisurat-panel max-w-4xl space-y-5 p-5 sm:p-7" @submit.prevent="save">
            <div>
                <h2 class="text-base font-semibold">Pratinjau kop surat A4</h2>
                <div class="mt-3 overflow-hidden rounded-xl border border-border bg-white p-3">
                    <img
                        :src="previewUrl ?? props.letterheadUrl"
                        alt="Pratinjau kop surat"
                        class="h-auto max-h-32 w-full object-contain object-center"
                    />
                </div>
            </div>
            <div>
                <label for="letterhead" class="mb-2 block text-sm font-medium">
                    Unggah kop surat baru (PNG, maksimal 5 MB)
                </label>
                <input
                    :key="inputVersion"
                    id="letterhead"
                    type="file"
                    accept="image/png,.png"
                    required
                    class="sisurat-input h-auto! py-3! text-xs"
                    :disabled="form.processing"
                    @change="chooseFile"
                />
                <p v-if="form.errors.letterhead" role="alert" class="mt-2 text-xs text-destructive">
                    {{ form.errors.letterhead }}
                </p>
                <p class="mt-2 text-xs leading-6 text-muted-foreground">
                    PNG maksimal 5.000 × 1.500 piksel dengan rasio lebar minimal 5:1. Kop akan disesuaikan ke area cetak A4 tanpa mengubah proporsi.
                </p>
            </div>
            <div class="flex justify-end border-t border-border/60 pt-4">
                <button
                    type="submit"
                    class="sisurat-primary-action"
                    :disabled="form.processing || !form.letterhead"
                >
                    <Save class="size-4" aria-hidden="true" />
                    {{ form.processing ? 'Menyimpan…' : 'Simpan kop surat' }}
                </button>
            </div>
        </form>
    </main>
</template>

