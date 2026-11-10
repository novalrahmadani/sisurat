<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { CheckCircle2, GraduationCap, LoaderCircle, Send } from '@lucide/vue';
import { nextTick, ref } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { submit } from '@/routes/pmb-prospects';

type StudyProgram = { id: number; name: string };

const props = defineProps<{
    isOpen: boolean;
    studyPrograms: StudyProgram[];
    submitted: boolean;
}>();

function newSubmissionToken(): string {
    return globalThis.crypto.randomUUID();
}

const form = useForm({
    name: '',
    study_program_id: '',
    school_name: '',
    school_district: '',
    school_address: '',
    affiliate_name: '',
    submission_token: newSubmissionToken(),
});
const submitted = ref(props.submitted);
const nameInput = ref<HTMLInputElement | null>(null);
const errorSummary = ref<HTMLElement | null>(null);
const submissionError = ref('');

function focusErrors(): void {
    void nextTick(() => errorSummary.value?.focus());
}

function send(): void {
    if (form.processing || !props.isOpen || props.studyPrograms.length === 0) return;
    submissionError.value = '';

    form.transform((data) => ({
        ...data,
        name: data.name.trim(),
        school_name: data.school_name.trim(),
        school_district: data.school_district.trim(),
        school_address: data.school_address.trim(),
        affiliate_name: data.affiliate_name.trim(),
    }));
    form.post(submit.url(), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            submitted.value = true;
            void nextTick(() => document.getElementById('form-receipt')?.focus());
        },
        onError: focusErrors,
        onNetworkError: () => {
            submissionError.value = 'Koneksi terputus. Isian Anda masih tersedia. Periksa koneksi lalu kirim kembali.';
            focusErrors();
            return false;
        },
        onHttpException: (response) => {
            submissionError.value = response.status === 429
                ? 'Terlalu banyak pengiriman. Tunggu satu menit lalu coba kembali.'
                : 'Data belum dapat dikirim. Isian Anda masih tersedia; coba kembali atau hubungi panitia PMB.';
            focusErrors();
            return false;
        },
    });
}

function fillAnother(): void {
    form.reset();
    form.submission_token = newSubmissionToken();
    form.defaults({ submission_token: form.submission_token });
    form.clearErrors();
    submissionError.value = '';
    submitted.value = false;
    void nextTick(() => nameInput.value?.focus());
}
</script>

<template>
    <div lang="id" translate="no" class="notranslate sisurat-workspace min-h-dvh">
        <Head title="Formulir Data Calon Mahasiswa" />
        <header class="mx-auto flex max-w-3xl items-center gap-3 px-5 py-5 sm:px-6">
            <span class="rounded-xl border border-border bg-white p-2 shadow-sm">
                <AppLogoIcon class="size-11" />
            </span>
            <div class="grid gap-1">
                <p class="text-sm font-semibold tracking-wide">SISURAT PMB</p>
                <p class="text-xs text-muted-foreground">Universitas Nurul Huda</p>
            </div>
        </header>

        <main id="main-content" class="mx-auto grid max-w-3xl gap-5 px-5 pb-8 sm:px-6">
            <section class="sisurat-gradient grid gap-3 rounded-2xl p-6 sm:p-8">
                <div class="flex items-center gap-2 text-xs font-medium text-white/85">
                    <GraduationCap class="size-4" aria-hidden="true" />PENERIMAAN MAHASISWA BARU
                </div>
                <h1 class="text-xl leading-relaxed font-semibold break-words sm:text-2xl">
                    Formulir Data Calon Mahasiswa
                </h1>
                <p class="text-sm leading-7 text-white/90">
                    Isi data diri, program studi yang diminati, sekolah asal, dan dosen atau guru pembawa.
                </p>
            </section>

            <section
                v-if="submitted"
                id="form-receipt"
                tabindex="-1"
                class="sisurat-panel grid justify-items-center gap-4 p-6 text-center outline-none sm:p-8"
            >
                <span class="flex size-16 items-center justify-center rounded-full bg-secondary text-primary">
                    <CheckCircle2 class="size-8" aria-hidden="true" />
                </span>
                <h2 class="text-xl font-semibold">Data berhasil dikirim</h2>
                <p class="max-w-md text-sm leading-7 text-muted-foreground">
                    Terima kasih. Data Anda sudah diterima oleh panitia Penerimaan Mahasiswa Baru Universitas Nurul Huda.
                </p>
                <button type="button" class="sisurat-secondary-action" @click="fillAnother">
                    Isi data calon mahasiswa lainnya
                </button>
            </section>

            <section v-else-if="!isOpen" class="sisurat-panel grid gap-3 p-6 sm:p-8">
                <h2 class="text-lg font-semibold">Formulir sedang ditutup</h2>
                <p class="text-sm leading-7 text-muted-foreground">
                    Pendaftaran melalui formulir ini belum dibuka. Silakan hubungi panitia PMB Universitas Nurul Huda.
                </p>
            </section>

            <form v-else class="sisurat-panel grid gap-5 p-5 sm:p-8" @submit.prevent="send">
                <div
                    v-if="form.hasErrors || submissionError"
                    ref="errorSummary"
                    tabindex="-1"
                    role="alert"
                    class="rounded-xl border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive outline-none"
                >
                    {{ submissionError || 'Periksa kembali data yang ditandai. Semua kolom wajib diisi.' }}
                </div>

                <div>
                    <label for="prospect-name" class="mb-2 block text-sm font-medium">Nama calon mahasiswa</label>
                    <input
                        id="prospect-name"
                        ref="nameInput"
                        v-model="form.name"
                        type="text"
                        maxlength="150"
                        autocomplete="name"
                        required
                        class="sisurat-input"
                        :aria-invalid="!!form.errors.name"
                        :disabled="form.processing"
                    />
                    <p v-if="form.errors.name" role="alert" class="mt-2 text-xs text-destructive">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label for="prospect-program" class="mb-2 block text-sm font-medium">Program studi yang diminati</label>
                    <select
                        id="prospect-program"
                        v-model="form.study_program_id"
                        required
                        class="sisurat-input"
                        :aria-invalid="!!form.errors.study_program_id"
                        :disabled="form.processing || !studyPrograms.length"
                    >
                        <option value="" disabled>Pilih program studi</option>
                        <option v-for="program in studyPrograms" :key="program.id" :value="String(program.id)">
                            {{ program.name }}
                        </option>
                    </select>
                    <p v-if="form.errors.study_program_id" role="alert" class="mt-2 text-xs text-destructive">{{ form.errors.study_program_id }}</p>
                    <p v-else-if="!studyPrograms.length" class="mt-2 text-xs text-destructive">Program studi belum tersedia. Hubungi panitia PMB.</p>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="prospect-school" class="mb-2 block text-sm font-medium">Asal sekolah</label>
                        <input id="prospect-school" v-model="form.school_name" type="text" maxlength="200" required class="sisurat-input" :aria-invalid="!!form.errors.school_name" :disabled="form.processing" />
                        <p v-if="form.errors.school_name" role="alert" class="mt-2 text-xs text-destructive">{{ form.errors.school_name }}</p>
                    </div>
                    <div>
                        <label for="prospect-district" class="mb-2 block text-sm font-medium">Kabupaten/kota sekolah</label>
                        <input id="prospect-district" v-model="form.school_district" type="text" maxlength="150" required class="sisurat-input" :aria-invalid="!!form.errors.school_district" :disabled="form.processing" placeholder="Contoh: Kabupaten OKU Timur" />
                        <p v-if="form.errors.school_district" role="alert" class="mt-2 text-xs text-destructive">{{ form.errors.school_district }}</p>
                    </div>
                </div>

                <div>
                    <label for="prospect-address" class="mb-2 block text-sm font-medium">Alamat sekolah</label>
                    <textarea id="prospect-address" v-model="form.school_address" maxlength="500" rows="3" required class="sisurat-input min-h-24 resize-y" :aria-invalid="!!form.errors.school_address" :disabled="form.processing" />
                    <p v-if="form.errors.school_address" role="alert" class="mt-2 text-xs text-destructive">{{ form.errors.school_address }}</p>
                </div>

                <div>
                    <label for="prospect-affiliate" class="mb-2 block text-sm font-medium">Nama dosen atau guru pembawa / affiliator</label>
                    <input id="prospect-affiliate" v-model="form.affiliate_name" type="text" maxlength="150" required class="sisurat-input" :aria-invalid="!!form.errors.affiliate_name" :disabled="form.processing" />
                    <p v-if="form.errors.affiliate_name" role="alert" class="mt-2 text-xs text-destructive">{{ form.errors.affiliate_name }}</p>
                </div>

                <div class="grid gap-3 border-t border-border/60 pt-5">
                    <button type="submit" class="sisurat-primary-action w-full" :disabled="form.processing || !studyPrograms.length">
                        <LoaderCircle v-if="form.processing" class="size-4 animate-spin" aria-hidden="true" />
                        <Send v-else class="size-4" aria-hidden="true" />
                        {{ form.processing ? 'Mengirim data...' : 'Kirim data calon mahasiswa' }}
                    </button>
                    <p class="text-center text-xs leading-6 text-muted-foreground">
                        Data digunakan oleh panitia untuk keperluan Penerimaan Mahasiswa Baru. Periksa kembali isian sebelum mengirim.
                    </p>
                </div>
            </form>

            <footer class="text-center text-xs leading-6 text-muted-foreground">
                Sekretariat PMB · Universitas Nurul Huda
            </footer>
        </main>
    </div>
</template>

