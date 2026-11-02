<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save, FilePenLine } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { index, store, update } from '@/routes/outgoing-letters';
const props = defineProps<{
    entry: {
        id: number;
        recipient: string;
        letter_date: string;
        subject: string;
        letter_number: string;
        type: string;
        version: number;
    } | null;
    today: string;
    types: Record<string, string>;
}>();
const form = useForm({
    recipient: props.entry?.recipient ?? '',
    letter_date: props.entry?.letter_date ?? props.today,
    subject: props.entry?.subject ?? '',
    letter_number: props.entry?.letter_number ?? '',
    type: props.entry?.type ?? 'finance',
    expected_version: props.entry?.version ?? null,
});
function submit(): void {
    if (props.entry) form.put(update.url(props.entry.id));
    else form.post(store.url());
}
</script>
<template>
    <main class="sisurat-page">
        <Head :title="entry ? 'Ubah Catatan Agenda' : 'Catat Surat Manual'" />
        <div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
            <Link :href="index()" class="sisurat-secondary-action w-fit"
                ><ArrowLeft class="size-4" aria-hidden="true" />Kembali ke
                agenda</Link
            >
            <header>
                <p class="sisurat-kicker">
                    Agenda Surat Keluar · Catatan manual
                </p>
                <h1 class="sisurat-title mt-2">
                    {{ entry ? 'Ubah catatan surat' : 'Catat surat manual' }}
                </h1>
                <p class="sisurat-subtitle mt-2">
                    Catat data surat dari bagian Keuangan atau surat lain yang
                    diterbitkan di luar SISURAT. Cukup data agenda tanpa
                    mengunggah berkas.
                </p>
            </header>
            <form class="sisurat-panel p-5 sm:p-8" @submit.prevent="submit">
                <div
                    class="mb-6 flex gap-3 rounded-xl bg-primary/5 p-4 text-sm leading-6"
                >
                    <FilePenLine
                        class="mt-1 size-5 shrink-0 text-primary"
                        aria-hidden="true"
                    />
                    <p>
                        Nomor urut surat Keuangan memakai urutan yang sama
                        dengan surat PMB. Salin nomor lengkap dan kode dari
                        surat aslinya. Surat yang dibuat melalui SISURAT sudah
                        masuk agenda secara otomatis.
                    </p>
                </div>
                <div class="space-y-5">
                    <div>
                        <label
                            for="manual-recipient"
                            class="mb-2 block text-xs font-medium"
                            >Alamat / Penerima
                            <span aria-hidden="true">*</span></label
                        ><input
                            id="manual-recipient"
                            v-model="form.recipient"
                            required
                            maxlength="500"
                            class="sisurat-input"
                            placeholder="Contoh: Rektor Universitas Nurul Huda"
                            :aria-invalid="!!form.errors.recipient"
                        /><InputError :message="form.errors.recipient" />
                    </div>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label
                                for="manual-date"
                                class="mb-2 block text-xs font-medium"
                                >Tanggal surat
                                <span aria-hidden="true">*</span></label
                            ><input
                                id="manual-date"
                                v-model="form.letter_date"
                                type="date"
                                required
                                min="2000-01-01"
                                max="2100-12-31"
                                class="sisurat-input"
                                :aria-invalid="!!form.errors.letter_date"
                            /><InputError :message="form.errors.letter_date" />
                        </div>
                        <div>
                            <label
                                for="manual-type"
                                class="mb-2 block text-xs font-medium"
                                >Jenis surat
                                <span aria-hidden="true">*</span></label
                            ><select
                                id="manual-type"
                                v-model="form.type"
                                class="sisurat-input"
                            >
                                <option
                                    v-for="(label, key) in types"
                                    :key="key"
                                    :value="key"
                                >
                                    {{ label }}
                                </option></select
                            ><InputError :message="form.errors.type" />
                        </div>
                    </div>
                    <div>
                        <label
                            for="manual-subject"
                            class="mb-2 block text-xs font-medium"
                            >Perihal <span aria-hidden="true">*</span></label
                        ><textarea
                            id="manual-subject"
                            v-model="form.subject"
                            required
                            maxlength="500"
                            rows="3"
                            class="sisurat-input h-auto py-3"
                            placeholder="Contoh: Permohonan Izin Sosialisasi dan Promosi"
                            :aria-invalid="!!form.errors.subject"
                        /><InputError :message="form.errors.subject" />
                    </div>
                    <div>
                        <label
                            for="manual-number"
                            class="mb-2 block text-xs font-medium"
                            >Nomor surat lengkap
                            <span aria-hidden="true">*</span></label
                        ><input
                            id="manual-number"
                            v-model="form.letter_number"
                            required
                            maxlength="200"
                            class="sisurat-input"
                            placeholder="466/UNUHA.11/KU/X/2026"
                            :aria-invalid="!!form.errors.letter_number"
                        />
                        <p class="mt-2 text-xs leading-6 text-muted-foreground">
                            Kode surat boleh berbeda, misalnya UNUHA.11/KU untuk
                            Keuangan. Nomor urut tetap satu jalur dengan surat
                            PMB dan tidak boleh digunakan dua kali pada tahun
                            yang sama. Tahun nomor harus sesuai tanggal surat.
                        </p>
                        <InputError :message="form.errors.letter_number" />
                    </div>
                    <InputError :message="form.errors.expected_version" />
                </div>
                <div
                    class="mt-8 flex flex-wrap items-center justify-end gap-3 border-t border-border pt-5"
                >
                    <Link
                        :href="index()"
                        class="sisurat-secondary-action"
                        :class="{
                            'pointer-events-none opacity-50': form.processing,
                        }"
                        >Batal</Link
                    ><button
                        type="submit"
                        class="sisurat-primary-action"
                        :disabled="form.processing"
                    >
                        <Save class="size-4" aria-hidden="true" />{{
                            form.processing
                                ? 'Menyimpan…'
                                : entry
                                  ? 'Simpan perubahan'
                                  : 'Simpan ke agenda'
                        }}
                    </button>
                </div>
            </form>
        </div>
    </main>
</template>
