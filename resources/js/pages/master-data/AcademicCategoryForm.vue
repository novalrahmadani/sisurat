<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import type {
    MasterAcademicCategory,
    MasterAcademicOption,
} from '@/lib/masterData';
import {
    index as facultiesIndex,
    store as storeFaculty,
    update as updateFaculty,
} from '@/routes/master-data/faculties';
import {
    index as studyProgramsIndex,
    store as storeStudyProgram,
    update as updateStudyProgram,
} from '@/routes/master-data/study-programs';

const props = defineProps<{
    category: MasterAcademicCategory;
    record: MasterAcademicOption | null;
}>();
const categoryLabel = computed(() =>
    props.category === 'faculties' ? 'Fakultas' : 'Program Studi',
);
const pageTitle = computed(
    () =>
        `${props.record ? 'Ubah' : 'Tambah'} ${categoryLabel.value.toLowerCase()}`,
);
const categoryRoutes = computed(() =>
    props.category === 'faculties'
        ? { index: facultiesIndex, store: storeFaculty, update: updateFaculty }
        : {
              index: studyProgramsIndex,
              store: storeStudyProgram,
              update: updateStudyProgram,
          },
);
const form = useForm({ name: props.record?.name ?? '' });
const requestError = ref('');
function submit(): void {
    if (form.processing) return;
    requestError.value = '';
    form.transform((data) => ({ name: data.name.trim() }));
    const options = {
        preserveScroll: true,
        onError: () => {
            void nextTick(() =>
                document.getElementById('academic-name')?.focus(),
            );
        },
        onNetworkError: () => {
            requestError.value =
                'Koneksi terputus. Periksa koneksi lalu simpan kembali.';
            return false;
        },
        onHttpException: () => {
            requestError.value =
                'Data belum dapat disimpan. Muat ulang halaman atau coba kembali.';
            return false;
        },
    };
    if (props.record)
        form.put(categoryRoutes.value.update.url(props.record.id), options);
    else form.post(categoryRoutes.value.store.url(), options);
}
</script>

<template>
    <main class="sisurat-page">
        <Head :title="pageTitle" />
        <div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
            <Link
                :href="categoryRoutes.index()"
                class="sisurat-secondary-action self-start"
                :class="{ 'pointer-events-none opacity-50': form.processing }"
                :aria-disabled="form.processing"
                @click="form.processing && $event.preventDefault()"
                ><ArrowLeft class="size-4" aria-hidden="true" />Kembali ke
                {{ categoryLabel.toLowerCase() }}</Link
            >
            <header>
                <p class="sisurat-kicker">Master Data · {{ categoryLabel }}</p>
                <h1 class="sisurat-title mt-2">{{ pageTitle }}</h1>
                <p class="sisurat-subtitle mt-2">
                    Nama {{ categoryLabel.toLowerCase() }} tersedia sebagai
                    pilihan data mahasiswa saat membuat surat keterangan PKKMB.
                </p>
            </header>
            <form class="sisurat-panel p-5 sm:p-7" @submit.prevent="submit">
                <fieldset
                    :disabled="form.processing"
                    class="flex min-w-0 flex-col gap-6"
                >
                    <div>
                        <label
                            for="academic-name"
                            class="mb-2 block text-sm font-medium"
                            >Nama {{ categoryLabel.toLowerCase() }}
                            <span class="text-destructive">*</span></label
                        >
                        <input
                            id="academic-name"
                            v-model="form.name"
                            type="text"
                            class="sisurat-input"
                            required
                            maxlength="150"
                            :placeholder="
                                category === 'faculties'
                                    ? 'Contoh: Fakultas Ilmu Pendidikan'
                                    : 'Contoh: Pendidikan Bahasa Inggris'
                            "
                            :aria-invalid="Boolean(form.errors.name)"
                            aria-describedby="academic-name-hint academic-name-error"
                        />
                        <p
                            id="academic-name-hint"
                            class="mt-2 text-xs leading-6 text-muted-foreground"
                        >
                            Gunakan nama resmi yang lengkap. Nama yang sudah
                            tersedia tidak perlu ditambahkan kembali.
                        </p>
                        <InputError
                            id="academic-name-error"
                            class="mt-2"
                            :message="form.errors.name"
                        />
                    </div>
                    <div
                        v-if="form.hasErrors || requestError"
                        class="rounded-xl bg-destructive/10 px-4 py-3 text-sm text-destructive"
                        role="alert"
                    >
                        <p>
                            {{
                                requestError ||
                                'Data belum tersimpan. Periksa isian berikut.'
                            }}
                        </p>
                        <ul
                            v-if="form.hasErrors"
                            class="mt-2 list-inside list-disc"
                        >
                            <li
                                v-for="(message, key) in form.errors"
                                :key="key"
                            >
                                {{ message }}
                            </li>
                        </ul>
                    </div>
                    <div
                        class="flex flex-wrap justify-end gap-3 border-t border-border pt-5"
                    >
                        <Link
                            :href="categoryRoutes.index()"
                            class="sisurat-secondary-action"
                            :class="{
                                'pointer-events-none opacity-50':
                                    form.processing,
                            }"
                            :aria-disabled="form.processing"
                            @click="form.processing && $event.preventDefault()"
                            >Batal</Link
                        ><button
                            type="submit"
                            class="sisurat-primary-action"
                            :disabled="form.processing"
                            :aria-busy="form.processing"
                        >
                            <Save class="size-4" aria-hidden="true" /><span>{{
                                form.processing
                                    ? 'Menyimpan…'
                                    : `Simpan ${categoryLabel.toLowerCase()}`
                            }}</span>
                        </button>
                    </div>
                </fieldset>
            </form>
        </div>
    </main>
</template>
