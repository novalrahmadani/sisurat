<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import {
    attendanceIndexRoute,
    attendanceToday,
    attendanceTypeLabel,
    type AttendanceEvent,
    type AttendanceType,
} from '@/lib/attendance';
import { show, store, update } from '@/routes/attendance';

const props = defineProps<{
    event: AttendanceEvent | null;
    attendanceType: AttendanceType;
}>();

function eventDefaults() {
    return {
        type: props.event?.type ?? props.attendanceType,
        title: props.event?.title ?? '',
        event_date: props.event?.event_date.slice(0, 10) ?? attendanceToday(),
        location: props.event?.location ?? '',
        description: props.event?.description ?? '',
        is_open: props.event?.is_open ?? true,
    };
}

const form = useForm(eventDefaults());
const requestError = ref('');
const categoryLabel = computed(() => attendanceTypeLabel(form.type));
const hasParticipants = computed(() => (props.event?.entries_count ?? 0) > 0);
const pageTitle = computed(
    () =>
        `${props.event ? 'Ubah' : 'Buat'} ${categoryLabel.value.toLowerCase()}`,
);
const backLink = computed(() =>
    props.event ? show(props.event.id) : attendanceIndexRoute(form.type),
);
watch([() => props.event?.id ?? null, () => props.attendanceType], () => {
    form.defaults(eventDefaults());
    form.reset();
    form.clearErrors();
    requestError.value = '';
});

function isCategoryDisabled(type: AttendanceType): boolean {
    if (!hasParticipants.value || !props.event) return false;
    return (props.event.type === 'pmb_test') !== (type === 'pmb_test');
}

function submit(): void {
    if (form.processing) return;
    requestError.value = '';
    form.transform((data) => ({
        ...data,
        title: data.title.trim(),
        location: data.location.trim(),
        description: data.description.trim(),
    }));
    const options = {
        preserveScroll: true,
        onError: () => {
            void nextTick(() => {
                const firstError = Object.keys(form.errors)[0];
                if (firstError)
                    document
                        .getElementById(`attendance-${firstError}`)
                        ?.focus();
            });
        },
        onNetworkError: () => {
            requestError.value =
                'Koneksi terputus. Periksa koneksi lalu simpan kembali.';
            return false;
        },
        onHttpException: () => {
            requestError.value =
                'Kegiatan belum dapat disimpan. Muat ulang halaman atau coba kembali.';
            return false;
        },
    };
    if (props.event) form.put(update.url(props.event.id), options);
    else form.post(store.url(), options);
}
</script>

<template>
    <main class="sisurat-page">
        <Head :title="pageTitle" />
        <div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
            <Link
                :href="backLink"
                class="sisurat-secondary-action self-start"
                :class="{ 'pointer-events-none opacity-50': form.processing }"
                :aria-disabled="form.processing"
                @click="form.processing && $event.preventDefault()"
                ><ArrowLeft class="size-4" aria-hidden="true" />{{
                    event
                        ? 'Kembali ke daftar peserta'
                        : `Kembali ke daftar hadir ${categoryLabel.toLowerCase()}`
                }}</Link
            >
            <header>
                <p class="sisurat-kicker">
                    Daftar Hadir Online · {{ categoryLabel }}
                </p>
                <h1 class="sisurat-title mt-2">{{ pageTitle }}</h1>
                <p class="sisurat-subtitle mt-2">
                    Siapkan kegiatan, lalu bagikan tautan pengisian kepada
                    peserta.
                </p>
            </header>
            <form class="sisurat-panel p-5 sm:p-7" @submit.prevent="submit">
                <fieldset
                    :disabled="form.processing"
                    class="flex min-w-0 flex-col gap-6"
                >
                    <div>
                        <label
                            for="attendance-type"
                            class="mb-2 block text-sm font-medium"
                            >Jenis daftar hadir
                            <span class="text-destructive">*</span></label
                        >
                        <select
                            id="attendance-type"
                            v-model="form.type"
                            class="sisurat-input"
                            required
                            :aria-invalid="Boolean(form.errors.type)"
                            aria-describedby="attendance-type-hint attendance-type-error"
                        >
                            <option
                                value="meeting"
                                :disabled="isCategoryDisabled('meeting')"
                            >
                                Rapat
                            </option>
                            <option
                                value="pmb_test"
                                :disabled="isCategoryDisabled('pmb_test')"
                            >
                                Tes PMB
                            </option>
                            <option
                                value="other"
                                :disabled="isCategoryDisabled('other')"
                            >
                                Kegiatan Lainnya
                            </option>
                        </select>
                        <p
                            id="attendance-type-hint"
                            class="mt-2 text-xs leading-6 text-muted-foreground"
                        >
                            {{
                                hasParticipants
                                    ? event?.type === 'pmb_test'
                                        ? 'Sudah ada peserta tes PMB. Jenis ini tetap karena kolom data peserta berbeda dari daftar hadir umum.'
                                        : 'Sudah ada peserta. Jenis dapat dipindahkan antara Rapat dan Kegiatan Lainnya; perubahan menjadi Tes PMB tidak tersedia karena kolom data peserta berbeda.'
                                    : 'Rapat dan Kegiatan Lainnya meminta nama, jabatan, dan instansi/institusi. Tes PMB meminta nama, nomor registrasi, dan program studi.'
                            }}
                        </p>
                        <InputError
                            id="attendance-type-error"
                            class="mt-2"
                            :message="form.errors.type"
                        />
                    </div>
                    <div>
                        <label
                            for="attendance-title"
                            class="mb-2 block text-sm font-medium"
                            >Nama kegiatan
                            <span class="text-destructive">*</span></label
                        >
                        <input
                            id="attendance-title"
                            v-model="form.title"
                            type="text"
                            class="sisurat-input"
                            required
                            maxlength="200"
                            :placeholder="
                                form.type === 'pmb_test'
                                    ? 'Contoh: Tes PMB Gelombang 1'
                                    : form.type === 'other'
                                      ? 'Contoh: Sosialisasi penerimaan mahasiswa baru'
                                      : 'Contoh: Rapat koordinasi PMB'
                            "
                            :aria-invalid="Boolean(form.errors.title)"
                            aria-describedby="attendance-title-error"
                        />
                        <InputError
                            id="attendance-title-error"
                            class="mt-2"
                            :message="form.errors.title"
                        />
                    </div>
                    <div class="grid gap-6 sm:grid-cols-[190px_minmax(0,1fr)]">
                        <div>
                            <label
                                for="attendance-event_date"
                                class="mb-2 block text-sm font-medium"
                                >Tanggal kegiatan
                                <span class="text-destructive">*</span></label
                            >
                            <input
                                id="attendance-event_date"
                                v-model="form.event_date"
                                type="date"
                                class="sisurat-input"
                                required
                                :aria-invalid="Boolean(form.errors.event_date)"
                                aria-describedby="attendance-date-error"
                            />
                            <InputError
                                id="attendance-date-error"
                                class="mt-2"
                                :message="form.errors.event_date"
                            />
                        </div>
                        <div>
                            <label
                                for="attendance-location"
                                class="mb-2 block text-sm font-medium"
                                >Tempat kegiatan
                                <span
                                    class="text-xs font-normal text-muted-foreground"
                                    >(opsional)</span
                                ></label
                            >
                            <input
                                id="attendance-location"
                                v-model="form.location"
                                type="text"
                                class="sisurat-input"
                                maxlength="255"
                                placeholder="Contoh: Ruang rapat PMB UNUHA"
                                :aria-invalid="Boolean(form.errors.location)"
                                aria-describedby="attendance-location-error"
                            />
                            <InputError
                                id="attendance-location-error"
                                class="mt-2"
                                :message="form.errors.location"
                            />
                        </div>
                    </div>
                    <div>
                        <label
                            for="attendance-description"
                            class="mb-2 block text-sm font-medium"
                            >Keterangan
                            <span
                                class="text-xs font-normal text-muted-foreground"
                                >(opsional)</span
                            ></label
                        >
                        <textarea
                            id="attendance-description"
                            v-model="form.description"
                            rows="4"
                            class="sisurat-input min-h-28 resize-y py-3"
                            maxlength="2000"
                            placeholder="Tambahkan informasi atau petunjuk singkat untuk peserta."
                            :aria-invalid="Boolean(form.errors.description)"
                            aria-describedby="attendance-description-hint attendance-description-error"
                        ></textarea>
                        <p
                            id="attendance-description-hint"
                            class="mt-2 text-xs leading-6 text-muted-foreground"
                        >
                            Keterangan ini ditampilkan pada halaman pengisian
                            peserta. Maksimal 2.000 karakter.
                        </p>
                        <InputError
                            id="attendance-description-error"
                            class="mt-2"
                            :message="form.errors.description"
                        />
                    </div>
                    <div
                        class="rounded-xl border border-border bg-muted/30 p-4"
                    >
                        <label
                            for="attendance-is_open"
                            class="flex cursor-pointer items-start gap-3"
                            ><input
                                id="attendance-is_open"
                                v-model="form.is_open"
                                type="checkbox"
                                class="mt-0.5 size-4 shrink-0 accent-primary"
                                aria-describedby="attendance-status-hint attendance-status-error"
                                :aria-invalid="Boolean(form.errors.is_open)"
                            /><span
                                ><span class="block text-sm font-semibold"
                                    >Buka pengisian daftar hadir</span
                                ><span
                                    id="attendance-status-hint"
                                    class="mt-1 block text-xs leading-6 text-muted-foreground"
                                    >Peserta dapat mengisi selama dibuka. Tutup
                                    pengisian setelah kegiatan selesai; data
                                    peserta tetap tersimpan.</span
                                ></span
                            ></label
                        >
                        <InputError
                            id="attendance-status-error"
                            class="mt-2"
                            :message="form.errors.is_open"
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
                                'Kegiatan belum tersimpan. Periksa isian berikut.'
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
                            :href="backLink"
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
                            <Save class="size-4" aria-hidden="true" /><span>{{
                                form.processing
                                    ? 'Menyimpan…'
                                    : 'Simpan kegiatan'
                            }}</span>
                        </button>
                    </div>
                </fieldset>
            </form>
        </div>
    </main>
</template>
