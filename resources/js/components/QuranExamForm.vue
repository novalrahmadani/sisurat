<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import type { QuranExam } from '@/lib/quranExams';
import { store, update } from '@/routes/quran-exams';
const props = defineProps<{ exam?: QuranExam; today?: string }>();
const form = useForm({
    title: props.exam?.title ?? '',
    description: props.exam?.description ?? '',
    assessment_date: props.exam?.assessment_date ?? props.today ?? '',
    is_open: props.exam?.is_open ?? true,
    version: props.exam?.version,
});
const networkError = ref('');
watch(
    () => props.exam?.version,
    () => {
        if (props.exam) {
            form.defaults({
                title: props.exam.title,
                description: props.exam.description ?? '',
                assessment_date: props.exam.assessment_date,
                is_open: props.exam.is_open,
                version: props.exam.version,
            });
            form.reset();
        }
    },
);
function submit() {
    networkError.value = '';
    const options = {
        preserveScroll: true,
        onNetworkError: () => {
            networkError.value =
                'Koneksi terputus. Isian belum disimpan. Silakan coba kembali.';
            return false;
        },
    };
    if (props.exam)
        form.put(update.url({ quran_exam: props.exam.id }), options);
    else
        form.transform(({ version: _version, ...data }) => data).post(
            store.url(),
            options,
        );
}
</script>
<template>
    <form @submit.prevent="submit" class="space-y-5">
        <div
            v-if="Object.keys(form.errors).length"
            role="alert"
            class="rounded-xl bg-destructive/10 p-4 text-sm text-destructive"
        >
            Periksa isian berikut sebelum menyimpan.
            <ul class="mt-2 list-disc pl-5">
                <li v-for="(error, key) in form.errors" :key="key">
                    {{ error }}
                </li>
            </ul>
        </div>
        <fieldset :disabled="form.processing" class="min-w-0 space-y-5">
            <div>
                <label
                    for="mq-exam-title"
                    class="mb-2 block text-sm font-medium"
                    >Nama kegiatan tes *</label
                ><input
                    id="mq-exam-title"
                    v-model="form.title"
                    required
                    maxlength="150"
                    class="sisurat-input"
                    placeholder="Contoh: Tes Baca Al-Qur’an Gelombang 1"
                /><InputError class="mt-2" :message="form.errors.title" />
            </div>
            <p
                class="rounded-xl border border-border bg-muted/20 p-4 text-xs leading-6 text-muted-foreground"
            >
                Gelombang, periode, dan kampus diisi oleh penguji untuk setiap
                peserta yang dinilai.
            </p>
            <div>
                <label for="mq-exam-date" class="mb-2 block text-sm font-medium"
                    >Tanggal tes *</label
                ><input
                    id="mq-exam-date"
                    v-model="form.assessment_date"
                    type="date"
                    required
                    :readonly="Boolean(exam?.assessments_count)"
                    class="sisurat-input"
                />
                <p
                    v-if="exam?.assessments_count"
                    class="mt-2 text-xs leading-6 text-muted-foreground"
                >
                    Tanggal dikunci karena sudah ada nilai peserta. Gunakan
                    kegiatan baru untuk tanggal tes lainnya.
                </p>
                <InputError
                    class="mt-2"
                    :message="form.errors.assessment_date"
                />
            </div>
            <div>
                <label
                    for="mq-exam-description"
                    class="mb-2 block text-sm font-medium"
                    >Petunjuk untuk penguji
                    <span class="font-normal text-muted-foreground"
                        >(opsional)</span
                    ></label
                ><textarea
                    id="mq-exam-description"
                    v-model="form.description"
                    rows="4"
                    maxlength="3000"
                    class="sisurat-input"
                    placeholder="Contoh: Bacaan yang digunakan dan petunjuk pelaksanaan."
                ></textarea
                ><InputError class="mt-2" :message="form.errors.description" />
            </div>
            <label
                class="flex items-start gap-3 rounded-xl border border-border p-4"
                ><input
                    v-model="form.is_open"
                    type="checkbox"
                    class="mt-1 size-4 accent-[#005A36]"
                /><span
                    ><span class="block text-sm font-semibold"
                        >Buka penilaian untuk penguji</span
                    ><span
                        class="mt-1 block text-xs leading-6 text-muted-foreground"
                        >Nonaktifkan setelah tes selesai untuk menghentikan
                        pengiriman nilai melalui tautan.</span
                    ></span
                ></label
            >
            <p
                v-if="networkError"
                role="alert"
                class="text-sm text-destructive"
            >
                {{ networkError }}
            </p>
            <button
                type="submit"
                class="sisurat-primary-action"
                :disabled="form.processing"
            >
                {{
                    form.processing
                        ? 'Menyimpan…'
                        : exam
                          ? 'Simpan pengaturan'
                          : 'Buat tautan penguji'
                }}
            </button>
        </fieldset>
    </form>
</template>
