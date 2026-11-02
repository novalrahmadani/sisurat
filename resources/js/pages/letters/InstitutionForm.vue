<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { institutionLabels, type InstitutionType } from '@/lib/letters';
import { pesantren, schools } from '@/routes/master-data';
import { index as districtsIndex } from '@/routes/master-data/districts';
import {
    store as storeInstitution,
    update as updateInstitution,
} from '@/routes/institutions';

type Institution = {
    id: number;
    institution_type: InstitutionType;
    name: string;
    district: string;
    address: string | null;
};
const props = defineProps<{
    institution: Institution | null;
    initialType: InstitutionType;
    districtOptions: string[];
}>();
const form = useForm({
    institution_type: props.institution?.institution_type ?? props.initialType,
    name: props.institution?.name ?? '',
    district: props.institution?.district ?? '',
    address: props.institution?.address ?? '',
});
const pageTitle = computed(() =>
    props.institution ? 'Ubah data lembaga' : 'Tambah data lembaga',
);
const returnRoute = computed(() =>
    (props.institution?.institution_type ?? props.initialType) === 'pesantren'
        ? pesantren()
        : schools(),
);
const requestError = ref('');
const labelClass = 'mb-2 block text-sm font-medium text-foreground';
function submit(): void {
    if (form.processing) return;
    requestError.value = '';
    form.transform((data) => ({
        institution_type: data.institution_type,
        name: data.name.trim(),
        district: data.district.trim(),
        address: data.address.trim(),
    }));
    const options = {
        preserveScroll: true,
        onError: () => {
            const key = Object.keys(form.errors)[0];
            const fieldIds: Record<string, string> = {
                name: 'institution_name',
                district: 'institution_district',
                address: 'institution_address',
                institution_type: 'institution_type',
            };
            if (key)
                void nextTick(() =>
                    document.getElementById(fieldIds[key] ?? key)?.focus(),
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
    if (props.institution) {
        form.put(updateInstitution.url(props.institution.id), options);
    } else {
        form.post(storeInstitution.url(), options);
    }
}
</script>

<template>
    <main class="sisurat-page">
        <Head :title="pageTitle" />
        <div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
            <Link
                :href="returnRoute"
                class="inline-flex min-h-11 items-center gap-2 self-start text-sm font-medium text-primary hover:underline"
                :class="{ 'pointer-events-none opacity-50': form.processing }"
                :aria-disabled="form.processing"
                @click="form.processing && $event.preventDefault()"
                ><ArrowLeft class="size-4" aria-hidden="true" />Kembali ke data
                lembaga</Link
            >
            <header>
                <p class="sisurat-kicker">Master Data · Sekolah dan Pondok</p>
                <h1 class="sisurat-title mt-2">{{ pageTitle }}</h1>
                <p class="sisurat-subtitle mt-2">
                    Lengkapi nama dan lokasi lembaga agar mudah dipilih saat
                    membuat surat.
                </p>
            </header>
            <form class="sisurat-panel p-5 sm:p-7" @submit.prevent="submit">
                <fieldset
                    :disabled="form.processing"
                    class="flex flex-col gap-6"
                >
                    <div>
                        <label for="institution_type" :class="labelClass"
                            >Jenis lembaga</label
                        >
                        <select
                            id="institution_type"
                            v-model="form.institution_type"
                            required
                            class="sisurat-input"
                            :aria-invalid="
                                Boolean(form.errors.institution_type)
                            "
                            :aria-describedby="
                                form.errors.institution_type
                                    ? 'institution_type_error'
                                    : undefined
                            "
                        >
                            <option value="school">
                                {{ institutionLabels.school }}
                            </option>
                            <option value="pesantren">
                                {{ institutionLabels.pesantren }}
                            </option>
                        </select>
                        <InputError
                            id="institution_type_error"
                            class="mt-2"
                            :message="form.errors.institution_type"
                        />
                    </div>
                    <div>
                        <label for="institution_name" :class="labelClass"
                            >Nama
                            {{
                                form.institution_type === 'school'
                                    ? 'sekolah / madrasah'
                                    : 'pondok pesantren'
                            }}</label
                        >
                        <input
                            id="institution_name"
                            v-model="form.name"
                            type="text"
                            required
                            maxlength="255"
                            autocomplete="organization"
                            class="sisurat-input"
                            placeholder="Nama lengkap lembaga"
                            :aria-invalid="Boolean(form.errors.name)"
                            :aria-describedby="
                                form.errors.name
                                    ? 'institution_name_error'
                                    : undefined
                            "
                        />
                        <InputError
                            id="institution_name_error"
                            class="mt-2"
                            :message="form.errors.name"
                        />
                    </div>
                    <div>
                        <label for="institution_district" :class="labelClass"
                            >Kabupaten/kota</label
                        >
                        <select
                            id="institution_district"
                            v-model="form.district"
                            required
                            class="sisurat-input"
                            :aria-invalid="Boolean(form.errors.district)"
                            :aria-describedby="
                                form.errors.district
                                    ? 'institution_district_hint institution_district_error'
                                    : 'institution_district_hint'
                            "
                        >
                            <option value="" disabled>
                                Pilih kabupaten/kota
                            </option>
                            <option
                                v-for="district in districtOptions"
                                :key="district"
                                :value="district"
                            >
                                {{ district }}
                            </option>
                        </select>
                        <p
                            id="institution_district_hint"
                            class="mt-2 text-xs leading-6 text-muted-foreground"
                        >
                            Pilih wilayah dari master data. Jika belum tersedia,
                            tambahkan melalui
                            <Link
                                :href="districtsIndex()"
                                class="font-medium text-primary underline underline-offset-4 dark:text-foreground"
                                >Master Kabupaten/Kota</Link
                            >.
                        </p>
                        <InputError
                            id="institution_district_error"
                            class="mt-1"
                            :message="form.errors.district"
                        />
                    </div>
                    <div>
                        <label for="institution_address" :class="labelClass"
                            >Alamat rinci (opsional)</label
                        >
                        <textarea
                            id="institution_address"
                            v-model="form.address"
                            rows="3"
                            maxlength="500"
                            autocomplete="street-address"
                            class="sisurat-input resize-y"
                            placeholder="Jalan, desa, atau kecamatan"
                            :aria-invalid="Boolean(form.errors.address)"
                            :aria-describedby="
                                form.errors.address
                                    ? 'institution_address_error'
                                    : undefined
                            "
                        />
                        <InputError
                            id="institution_address_error"
                            class="mt-2"
                            :message="form.errors.address"
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
                                'Data belum tersimpan. Periksa kolom yang ditandai.'
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
                            :href="returnRoute"
                            class="sisurat-secondary-action"
                            :class="{
                                'pointer-events-none opacity-50':
                                    form.processing,
                            }"
                            :aria-disabled="form.processing"
                            @click="form.processing && $event.preventDefault()"
                            >Batal</Link
                        >
                        <button
                            type="submit"
                            class="sisurat-primary-action"
                            :disabled="form.processing"
                            :aria-busy="form.processing"
                        >
                            <Save class="size-4" aria-hidden="true" />{{
                                form.processing ? 'Menyimpan…' : 'Simpan data'
                            }}
                        </button>
                    </div>
                </fieldset>
            </form>
        </div>
    </main>
</template>
