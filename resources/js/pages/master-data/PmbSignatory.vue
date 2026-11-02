<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onUnmounted, ref, watch } from 'vue';
import { ArrowLeft, Save } from '@lucide/vue';
import { index as masterData } from '@/routes/master-data';
import { update } from '@/actions/App/Http/Controllers/PmbSignatoryController';
import { Button } from '@/components/ui/button';

type Signatory = {
    id: number;
    name: string;
    title: string;
    number: string | null;
    signature_url: string | null;
    stamp_url: string | null;
};
const props = defineProps<{ signatory: Signatory }>();
const form = useForm({
    revision: props.signatory.id,
    name: props.signatory.name,
    title: props.signatory.title,
    number: props.signatory.number ?? '',
    signature: null as File | null,
    stamp: null as File | null,
    remove_signature: false,
    remove_stamp: false,
});
const inputVersion = ref(0);
const error = ref('');
const signaturePreview = ref<string | null>(null);
const stampPreview = ref<string | null>(null);
const normalize = (value: string) =>
    value.toLocaleLowerCase('id-ID').replace(/[^\p{L}\p{N}]/gu, '');
const nameChanged = computed(
    () => normalize(form.name) !== normalize(props.signatory.name),
);
const signatureUrl = computed(() =>
    form.remove_signature
        ? null
        : (signaturePreview.value ??
          (nameChanged.value ? null : props.signatory.signature_url)),
);
const stampUrl = computed(() =>
    form.remove_stamp
        ? null
        : (stampPreview.value ?? props.signatory.stamp_url),
);
watch(
    () => form.signature,
    (file) => {
        if (signaturePreview.value) URL.revokeObjectURL(signaturePreview.value);
        signaturePreview.value = file ? URL.createObjectURL(file) : null;
    },
);
watch(
    () => form.stamp,
    (file) => {
        if (stampPreview.value) URL.revokeObjectURL(stampPreview.value);
        stampPreview.value = file ? URL.createObjectURL(file) : null;
    },
);
onUnmounted(() => {
    if (signaturePreview.value) URL.revokeObjectURL(signaturePreview.value);
    if (stampPreview.value) URL.revokeObjectURL(stampPreview.value);
});
watch(
    () => props.signatory,
    (value) => {
        form.defaults({
            revision: value.id,
            name: value.name,
            title: value.title,
            number: value.number ?? '',
            signature: null,
            stamp: null,
            remove_signature: false,
            remove_stamp: false,
        });
        form.reset();
        inputVersion.value++;
    },
);
function chooseFile(kind: 'signature' | 'stamp', event: Event): void {
    const input = event.target as HTMLInputElement;
    form[kind] = input.files?.[0] ?? null;
    form[kind === 'signature' ? 'remove_signature' : 'remove_stamp'] = false;
    form.clearErrors(kind);
}
function reset(): void {
    form.reset();
    form.clearErrors();
    inputVersion.value++;
    error.value = '';
}
function save(): void {
    error.value = '';
    form.post(update.url(), {
        forceFormData: true,
        preserveScroll: true,
        onNetworkError: () => {
            error.value =
                'Koneksi terputus. Periksa koneksi lalu coba simpan kembali.';
            return false;
        },
        onHttpException: () => {
            error.value =
                'Data belum dapat disimpan. Muat ulang halaman atau coba kembali.';
            return false;
        },
    });
}
</script>

<template>
    <main class="sisurat-page space-y-6">
        <Head title="Penandatangan PMB" />
        <Link :href="masterData()" class="sisurat-secondary-action w-fit"
            ><ArrowLeft class="size-4" aria-hidden="true" />Master Data</Link
        >
        <header>
            <p class="sisurat-kicker">Master Data</p>
            <h1 class="sisurat-title mt-2">Penandatangan PMB</h1>
            <p class="sisurat-subtitle mt-2 max-w-3xl">
                Atur identitas kepala/ketua pusat PMB, tanda tangan, dan cap
                untuk surat serta daftar hadir baru.
            </p>
        </header>
        <form
            class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_360px]"
            @submit.prevent="save"
        >
            <section
                class="sisurat-panel space-y-5 p-5 sm:p-7"
                aria-label="Data kepala pusat PMB"
            >
                <div>
                    <label for="pmb-name" class="mb-2 block text-sm font-medium"
                        >Nama lengkap dan gelar</label
                    ><input
                        id="pmb-name"
                        v-model="form.name"
                        type="text"
                        maxlength="150"
                        required
                        class="sisurat-input"
                        :disabled="form.processing"
                        :aria-invalid="!!form.errors.name"
                    />
                    <p
                        v-if="form.errors.name"
                        role="alert"
                        class="mt-2 text-xs text-destructive"
                    >
                        {{ form.errors.name }}
                    </p>
                </div>
                <div>
                    <label
                        for="pmb-title"
                        class="mb-2 block text-sm font-medium"
                        >Jabatan</label
                    ><input
                        id="pmb-title"
                        v-model="form.title"
                        type="text"
                        maxlength="150"
                        required
                        class="sisurat-input"
                        :disabled="form.processing"
                        :aria-invalid="!!form.errors.title"
                    />
                    <p
                        v-if="form.errors.title"
                        role="alert"
                        class="mt-2 text-xs text-destructive"
                    >
                        {{ form.errors.title }}
                    </p>
                </div>
                <div>
                    <label
                        for="pmb-number"
                        class="mb-2 block text-sm font-medium"
                        >NIP / NIP.Y / identitas
                        <span class="text-muted-foreground"
                            >(opsional)</span
                        ></label
                    ><input
                        id="pmb-number"
                        v-model="form.number"
                        type="text"
                        maxlength="100"
                        class="sisurat-input"
                        :disabled="form.processing"
                        placeholder="Contoh: NIP.Y 00000000000000.0.000"
                    />
                    <p
                        v-if="form.errors.number"
                        role="alert"
                        class="mt-2 text-xs text-destructive"
                    >
                        {{ form.errors.number }}
                    </p>
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label
                            for="pmb-signature"
                            class="mb-2 block text-sm font-medium"
                            >Ganti tanda tangan</label
                        ><input
                            :key="`signature-${inputVersion}`"
                            id="pmb-signature"
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            class="sisurat-input h-auto! py-3! text-xs"
                            :disabled="form.processing"
                            @change="chooseFile('signature', $event)"
                        />
                        <p
                            v-if="form.errors.signature"
                            role="alert"
                            class="mt-2 text-xs text-destructive"
                        >
                            {{ form.errors.signature }}
                        </p>
                        <label
                            class="mt-3 flex min-h-9 items-center gap-2 text-xs"
                            ><input
                                v-model="form.remove_signature"
                                type="checkbox"
                                :disabled="form.processing || !!form.signature"
                                class="size-4 accent-primary"
                            />Kosongkan tanda tangan</label
                        >
                    </div>
                    <div>
                        <label
                            for="pmb-stamp"
                            class="mb-2 block text-sm font-medium"
                            >Ganti cap PMB</label
                        ><input
                            :key="`stamp-${inputVersion}`"
                            id="pmb-stamp"
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            class="sisurat-input h-auto! py-3! text-xs"
                            :disabled="form.processing"
                            @change="chooseFile('stamp', $event)"
                        />
                        <p
                            v-if="form.errors.stamp"
                            role="alert"
                            class="mt-2 text-xs text-destructive"
                        >
                            {{ form.errors.stamp }}
                        </p>
                        <label
                            class="mt-3 flex min-h-9 items-center gap-2 text-xs"
                            ><input
                                v-model="form.remove_stamp"
                                type="checkbox"
                                :disabled="form.processing || !!form.stamp"
                                class="size-4 accent-primary"
                            />Kosongkan cap</label
                        >
                    </div>
                </div>
                <p class="text-xs leading-6 text-muted-foreground">
                    Gunakan gambar PNG, JPG, atau WebP, maksimal 2 MB dan 5.000
                    × 5.000 piksel. PNG transparan disarankan agar hasil tanda
                    tangan dan cap terlihat rapi.
                </p>
                <p
                    v-if="nameChanged && !form.signature"
                    class="rounded-xl border border-amber-300/50 bg-amber-50 p-4 text-xs leading-6 text-amber-800 dark:bg-amber-950/30 dark:text-amber-200"
                >
                    Nama penandatangan berubah. Unggah tanda tangan yang sesuai.
                    Jika belum diunggah, surat baru akan memakai ruang tanda
                    tangan kosong.
                </p>
                <p
                    v-if="form.errors.revision || error"
                    role="alert"
                    class="text-sm text-destructive"
                >
                    {{ form.errors.revision || error }}
                </p>
                <p v-if="form.progress" class="text-xs text-muted-foreground">
                    Mengunggah gambar: {{ form.progress.percentage }}%
                </p>
                <div class="flex flex-wrap gap-3">
                    <Button type="submit" :disabled="form.processing"
                        ><Save class="size-4" aria-hidden="true" />{{
                            form.processing
                                ? 'Menyimpan…'
                                : 'Simpan penandatangan'
                        }}</Button
                    ><Button
                        type="button"
                        variant="outline"
                        :disabled="form.processing"
                        @click="reset"
                        >Batalkan perubahan</Button
                    >
                </div>
            </section>
            <aside
                class="sisurat-panel space-y-5 p-5 sm:p-6"
                aria-label="Pratinjau pengesahan PMB"
            >
                <div>
                    <h2 class="text-sm font-semibold">Pratinjau pengesahan</h2>
                    <p class="mt-2 text-xs leading-6 text-muted-foreground">
                        Cap menimpa tanda tangan sesuai format surat.
                    </p>
                </div>
                <div
                    class="overflow-hidden rounded-xl border border-border bg-white px-4 py-6 text-black"
                    style="font-family: Cambria, Georgia, serif"
                >
                    <div class="mx-auto w-full max-w-60">
                        <p class="text-sm">OKU Timur, …</p>
                        <p class="text-sm break-words">
                            {{ form.title || 'Jabatan penandatangan' }},
                        </p>
                        <div class="relative h-32">
                            <img
                                v-if="signatureUrl"
                                :src="signatureUrl"
                                alt="Pratinjau tanda tangan kepala PMB"
                                class="absolute top-3 left-2 z-10 h-20 w-28 object-contain mix-blend-multiply"
                            /><img
                                v-if="stampUrl"
                                :src="stampUrl"
                                alt="Pratinjau cap PMB"
                                class="absolute top-2 left-0 z-20 h-28 w-28 object-contain"
                            />
                        </div>
                        <p class="text-sm font-bold break-words underline">
                            {{ form.name || 'Nama penandatangan' }}
                        </p>
                        <p v-if="form.number" class="mt-1 text-xs break-words">
                            {{ form.number }}
                        </p>
                    </div>
                </div>
                <p class="text-xs leading-6 text-muted-foreground">
                    Perubahan digunakan pada dokumen baru. Surat dan daftar
                    hadir yang telah diarsipkan tetap memakai data dan gambar
                    penandatangan saat dibuat.
                </p>
            </aside>
        </form>
    </main>
</template>
