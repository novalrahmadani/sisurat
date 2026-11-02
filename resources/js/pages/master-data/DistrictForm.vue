<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import type { MasterDistrictOption } from '@/lib/masterData';
import { index, store, update } from '@/routes/master-data/districts';

const props = defineProps<{ district: MasterDistrictOption | null }>();
const form = useForm({ name: props.district?.name ?? '' });
const requestError = ref('');
const pageTitle = computed(() =>
    props.district ? 'Ubah kabupaten/kota' : 'Tambah kabupaten/kota',
);
function submit(): void {
    if (form.processing) return;
    requestError.value = '';
    form.transform((data) => ({ name: data.name.trim() }));
    const options = {
        preserveScroll: true,
        onError: () => {
            void nextTick(() =>
                document.getElementById('district-name')?.focus(),
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
    if (props.district) form.put(update.url(props.district.id), options);
    else form.post(store.url(), options);
}
</script>

<template>
    <main class="sisurat-page">
        <Head :title="pageTitle" />
        <div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
            <Link
                :href="index()"
                class="sisurat-secondary-action self-start"
                :class="{ 'pointer-events-none opacity-50': form.processing }"
                :aria-disabled="form.processing"
                @click="form.processing && $event.preventDefault()"
                ><ArrowLeft class="size-4" aria-hidden="true" />Kembali ke
                kabupaten/kota</Link
            >
            <header>
                <p class="sisurat-kicker">Master Data · Kabupaten / Kota</p>
                <h1 class="sisurat-title mt-2">{{ pageTitle }}</h1>
                <p class="sisurat-subtitle mt-2">
                    Kabupaten/kota menjadi pilihan wilayah untuk sekolah,
                    pondok, dan dosen/petugas.
                </p>
            </header>
            <form class="sisurat-panel p-5 sm:p-7" @submit.prevent="submit">
                <fieldset
                    :disabled="form.processing"
                    class="flex min-w-0 flex-col gap-6"
                >
                    <div>
                        <label
                            for="district-name"
                            class="mb-2 block text-sm font-medium"
                            >Nama kabupaten/kota
                            <span class="text-destructive">*</span></label
                        >
                        <input
                            id="district-name"
                            v-model="form.name"
                            type="text"
                            class="sisurat-input"
                            required
                            maxlength="150"
                            autocomplete="address-level2"
                            placeholder="Contoh: Kabupaten Ogan Komering Ulu Timur"
                            :aria-invalid="Boolean(form.errors.name)"
                            aria-describedby="district-name-hint district-name-error"
                        />
                        <p
                            id="district-name-hint"
                            class="mt-2 text-xs leading-6 text-muted-foreground"
                        >
                            Gunakan nama lengkap agar wilayah mudah dikenali.
                            Nama yang sama tidak perlu ditambahkan kembali.
                        </p>
                        <InputError
                            id="district-name-error"
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
                            :href="index()"
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
                                    : 'Simpan kabupaten/kota'
                            }}</span>
                        </button>
                    </div>
                </fieldset>
            </form>
        </div>
    </main>
</template>
