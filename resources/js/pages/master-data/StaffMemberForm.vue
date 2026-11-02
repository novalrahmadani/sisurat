<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import type { MasterDistrictOption, MasterStaffMember } from '@/lib/masterData';
import { index as districtsIndex } from '@/routes/master-data/districts';
import { index, store, update } from '@/routes/master-data/staff-members';

const props = defineProps<{
    staffMember: MasterStaffMember | null;
    districtOptions: MasterDistrictOption[];
}>();
const form = useForm({
    name: props.staffMember?.name ?? '',
    office: props.staffMember?.office ?? 'Dosen',
    employee_number: props.staffMember?.employee_number ?? '',
    district_id:
        props.staffMember?.district_id === null || !props.staffMember
            ? ''
            : String(props.staffMember.district_id),
});
const requestError = ref('');
const pageTitle = computed(() =>
    props.staffMember ? 'Ubah dosen/petugas' : 'Tambah dosen/petugas',
);
const textFields = [
    {
        key: 'name' as const,
        label: 'Nama lengkap',
        limit: 150,
        required: true,
        placeholder: 'Contoh: Dr. Ahmad, M.Pd.',
    },
    {
        key: 'office' as const,
        label: 'Dinas / jabatan',
        limit: 150,
        required: true,
        placeholder: 'Contoh: Dosen',
    },
    {
        key: 'employee_number' as const,
        label: 'NIP / identitas pegawai (opsional)',
        limit: 100,
        required: false,
        placeholder: 'Nomor identitas pegawai',
    },
];
function focusErrors(): void {
    const key = Object.keys(form.errors)[0];
    if (key)
        void nextTick(() => document.getElementById(`staff-${key}`)?.focus());
}
function submit(): void {
    if (form.processing) return;
    requestError.value = '';
    form.transform((data) => ({
        name: data.name.trim(),
        office: data.office.trim(),
        employee_number: data.employee_number.trim(),
        district_id: data.district_id ? Number(data.district_id) : null,
    }));
    const options = {
        preserveScroll: true,
        onError: focusErrors,
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
    if (props.staffMember) form.put(update.url(props.staffMember.id), options);
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
                dosen/petugas</Link
            >
            <header>
                <p class="sisurat-kicker">Master Data · Dosen / Petugas</p>
                <h1 class="sisurat-title mt-2">{{ pageTitle }}</h1>
                <p class="sisurat-subtitle mt-2">
                    Simpan data petugas agar nama dan dinas dapat dipilih saat
                    membuat surat tugas.
                </p>
            </header>
            <form class="sisurat-panel p-5 sm:p-7" @submit.prevent="submit">
                <fieldset
                    :disabled="form.processing"
                    class="flex min-w-0 flex-col gap-6"
                >
                    <div v-for="field in textFields" :key="field.key">
                        <label
                            :for="`staff-${field.key}`"
                            class="mb-2 block text-sm font-medium"
                            >{{ field.label }}
                            <span v-if="field.required" class="text-destructive"
                                >*</span
                            ></label
                        >
                        <input
                            :id="`staff-${field.key}`"
                            v-model="form[field.key]"
                            type="text"
                            class="sisurat-input"
                            :required="field.required"
                            :maxlength="field.limit"
                            :placeholder="field.placeholder"
                            :aria-invalid="Boolean(form.errors[field.key])"
                            :aria-describedby="`staff-${field.key}-error`"
                        />
                        <InputError
                            :id="`staff-${field.key}-error`"
                            class="mt-2"
                            :message="form.errors[field.key]"
                        />
                    </div>
                    <div>
                        <label
                            for="staff-district_id"
                            class="mb-2 block text-sm font-medium"
                            >Kabupaten/kota (opsional)</label
                        >
                        <select
                            id="staff-district_id"
                            v-model="form.district_id"
                            class="sisurat-input"
                            :aria-invalid="Boolean(form.errors.district_id)"
                            aria-describedby="staff-district-hint staff-district_id-error"
                        >
                            <option value="">Belum ditentukan</option>
                            <option
                                v-for="district in districtOptions"
                                :key="district.id"
                                :value="String(district.id)"
                            >
                                {{ district.name }}
                            </option>
                        </select>
                        <p
                            id="staff-district-hint"
                            class="mt-2 text-xs leading-6 text-muted-foreground"
                        >
                            Wilayah dapat dipilih saat membuat surat tugas.
                            Kelola pilihan melalui
                            <Link
                                :href="districtsIndex()"
                                class="font-medium text-primary underline underline-offset-4 dark:text-foreground"
                                >Master Kabupaten/Kota</Link
                            >.
                        </p>
                        <InputError
                            id="staff-district_id-error"
                            class="mt-2"
                            :message="form.errors.district_id"
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
                                    : 'Simpan dosen/petugas'
                            }}</span>
                        </button>
                    </div>
                </fieldset>
            </form>
        </div>
    </main>
</template>
