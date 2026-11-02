<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CheckCircle2,
    Info,
    LocateFixed,
    MapPin,
    Save,
} from '@lucide/vue';
import { onMounted, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { index } from '@/routes/internships';
import { update } from '@/routes/internships/access';

const props = defineProps<{
    setting: {
        latitude: number | null;
        longitude: number | null;
        radius_meters: number;
    };
}>();
const form = useForm({
    latitude: props.setting.latitude?.toString() ?? '',
    longitude: props.setting.longitude?.toString() ?? '',
    radius_meters: props.setting.radius_meters,
});
const locating = ref(false);
const message = ref('');
const secure = ref(false);
onMounted(() => {
    secure.value = window.isSecureContext && 'geolocation' in navigator;
});
function save() {
    form.put(update.url(), { preserveScroll: true });
}
function currentLocation() {
    if (!secure.value) {
        message.value =
            'Pembacaan GPS membutuhkan HTTPS. Anda tetap dapat mengisi koordinat kantor PMB secara manual.';
        return;
    }
    locating.value = true;
    message.value = '';
    navigator.geolocation.getCurrentPosition(
        (position) => {
            if (position.coords.accuracy > 100) {
                message.value =
                    'Posisi belum cukup akurat. Aktifkan lokasi presisi lalu coba kembali.';
                locating.value = false;
                return;
            }
            form.latitude = position.coords.latitude.toFixed(7);
            form.longitude = position.coords.longitude.toFixed(7);
            message.value =
                'Koordinat terisi dengan akurasi sekitar ' +
                Math.ceil(position.coords.accuracy) +
                ' meter. Pastikan Anda berada di kantor PMB, periksa titiknya, kemudian simpan.';
            locating.value = false;
        },
        (error) => {
            message.value =
                error.code === 1
                    ? 'Izin lokasi belum diberikan. Izinkan lokasi atau isi koordinat secara manual.'
                    : 'Lokasi belum dapat dibaca. Coba kembali di area terbuka dekat kantor PMB.';
            locating.value = false;
        },
        { enableHighAccuracy: true, maximumAge: 0, timeout: 20000 },
    );
}
</script>

<template>
    <div class="mx-auto w-full max-w-4xl space-y-6 p-5 sm:p-8">
        <Head title="Lokasi Absensi Magang" />
        <Link
            :href="index()"
            class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-primary"
            ><ArrowLeft class="size-4" />Absensi Magang</Link
        >
        <div>
            <p
                class="text-xs font-semibold tracking-widest text-primary uppercase dark:text-emerald-300"
            >
                Pengaturan kehadiran
            </p>
            <h1 class="mt-2 text-3xl font-semibold tracking-tight">
                Lokasi Absensi Magang
            </h1>
            <p class="mt-3 max-w-2xl text-sm leading-7 text-muted-foreground">
                Tetapkan titik kantor PMB dan jarak yang diperbolehkan. GPS akan
                diperiksa pada absensi masuk maupun pulang.
            </p>
        </div>
        <div
            v-if="setting.latitude === null || setting.longitude === null"
            class="flex gap-3 rounded-2xl border border-amber-300 bg-amber-50 p-5 text-sm leading-7 text-amber-950"
        >
            <Info class="mt-1 size-5 shrink-0" />
            <p>
                <strong>Titik PMB belum ditetapkan.</strong> Pendaftaran peserta
                tetap tersedia. Absensi masuk dan pulang baru dapat disimpan
                setelah lokasi ditetapkan.
            </p>
        </div>
        <div
            v-else
            class="flex gap-3 rounded-2xl border border-emerald-300 bg-emerald-50 p-5 text-sm leading-7 text-emerald-950 dark:bg-emerald-950 dark:text-emerald-100"
        >
            <CheckCircle2 class="mt-1 size-5 shrink-0" />
            <p>
                Titik lokasi sudah ditetapkan. Radius yang berlaku:
                <strong>{{ setting.radius_meters }} meter</strong>.
            </p>
        </div>
        <section class="rounded-3xl border border-border bg-card p-5 sm:p-8">
            <h2 class="flex items-center gap-2 text-lg font-semibold">
                <MapPin
                    class="size-5 text-primary dark:text-emerald-300"
                />Titik kantor PMB
            </h2>
            <p class="mt-3 text-sm leading-7 text-muted-foreground">
                Gunakan koordinat yang tepat dari pin Google Maps kantor PMB.
                Pilihan lain, ambil posisi saat ini ketika Anda benar-benar
                berada di kantor PMB.
            </p>
            <button
                class="sisurat-secondary-action mt-5 min-h-12"
                type="button"
                :disabled="locating || form.processing"
                @click="currentLocation"
            >
                <LocateFixed class="size-4" />{{
                    locating
                        ? 'Membaca posisi…'
                        : 'Ambil posisi saat berada di PMB'
                }}
            </button>
            <p
                v-if="message"
                role="status"
                class="mt-3 rounded-xl bg-muted/70 p-4 text-sm leading-7"
            >
                {{ message }}
            </p>
            <form class="mt-6 space-y-5" @submit.prevent="save">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <label
                            for="pmb-latitude"
                            class="mb-2 block text-sm font-medium"
                            >Lintang (latitude)</label
                        ><input
                            id="pmb-latitude"
                            v-model="form.latitude"
                            type="number"
                            step="any"
                            min="-90"
                            max="90"
                            class="sisurat-input min-h-12 w-full"
                            placeholder="Koordinat lintang kantor PMB"
                            required
                        /><InputError :message="form.errors.latitude" />
                    </div>
                    <div>
                        <label
                            for="pmb-longitude"
                            class="mb-2 block text-sm font-medium"
                            >Bujur (longitude)</label
                        ><input
                            id="pmb-longitude"
                            v-model="form.longitude"
                            type="number"
                            step="any"
                            min="-180"
                            max="180"
                            class="sisurat-input min-h-12 w-full"
                            placeholder="Koordinat bujur kantor PMB"
                            required
                        /><InputError :message="form.errors.longitude" />
                    </div>
                </div>
                <div>
                    <label
                        for="pmb-radius"
                        class="mb-2 block text-sm font-medium"
                        >Radius absensi (meter)</label
                    ><input
                        id="pmb-radius"
                        v-model="form.radius_meters"
                        type="number"
                        min="20"
                        max="1000"
                        class="sisurat-input min-h-12 w-full sm:max-w-xs"
                        required
                    /><InputError :message="form.errors.radius_meters" />
                    <p class="mt-2 text-xs leading-6 text-muted-foreground">
                        Tentukan radius sesuai area kantor dan kondisi pembacaan
                        GPS, antara 20–1.000 meter. Nilai awal 100 meter dapat
                        disesuaikan.
                    </p>
                </div>
                <a
                    v-if="form.latitude && form.longitude"
                    :href="
                        'https://www.google.com/maps?q=' +
                        encodeURIComponent(form.latitude + ',' + form.longitude)
                    "
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-2 text-sm font-medium text-primary underline underline-offset-4 dark:text-emerald-300"
                    ><MapPin class="size-4" />Periksa titik di Google Maps</a
                >
                <div class="border-t border-border pt-5">
                    <button
                        class="sisurat-primary-action min-h-12 w-full justify-center sm:w-auto"
                        type="submit"
                        :disabled="form.processing || locating"
                    >
                        <Save class="size-4" />{{
                            form.processing
                                ? 'Menyimpan…'
                                : 'Simpan lokasi dan radius'
                        }}
                    </button>
                </div>
            </form>
        </section>
        <div
            class="rounded-2xl border border-border bg-muted/50 p-5 text-sm leading-7 text-muted-foreground"
        >
            <h2 class="font-semibold text-foreground">
                Ketentuan penggunaan GPS
            </h2>
            <p class="mt-2">
                Peserta perlu membuka SISURAT melalui alamat HTTPS, mengaktifkan
                layanan lokasi, dan mengizinkan lokasi presisi pada browser.
                Alamat HTTP berbasis IP lokal belum dapat digunakan untuk GPS di
                HP. Koordinat peserta hanya digunakan untuk pemeriksaan jarak
                dan tidak disimpan dalam rekap kehadiran.
            </p>
        </div>
    </div>
</template>
