<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

defineProps<{ title?: string; description?: string }>();
</script>

<template>
    <div
        lang="id"
        translate="no"
        class="sisurat-workspace notranslate grid min-h-svh lg:grid-cols-[1fr_1fr]"
    >
        <aside
            class="sisurat-gradient relative hidden flex-col justify-between overflow-hidden p-12 lg:flex xl:p-16"
        >
            <Link
                :href="home()"
                class="flex items-center gap-3 self-start"
                aria-label="SISURAT PMB Universitas Nurul Huda, kembali ke beranda"
            >
                <span
                    class="flex size-11 items-center justify-center rounded-xl bg-white text-[#005a36]"
                    ><AppLogoIcon class="size-9"
                /></span>
                <span class="grid gap-1">
                    <span class="text-xl font-semibold tracking-wide"
                        >SISURAT</span
                    >
                    <span class="text-xs text-white/80"
                        >Sekretaris PMB · UNUHA</span
                    >
                </span>
            </Link>
            <div class="relative z-10 max-w-lg space-y-6 py-14">
                <div
                    class="flex size-28 items-center justify-center rounded-2xl bg-white p-3 shadow-lg"
                >
                    <AppLogoIcon class="size-full" />
                </div>
                <h2 class="text-4xl leading-snug font-semibold tracking-tight">
                    Surat tersusun rapi.<br />Pekerjaan jadi lebih ringan.
                </h2>
                <p class="text-base leading-8 text-white/80">
                    Ruang kerja Sekretaris Penerimaan Mahasiswa Baru (PMB)
                    Universitas Nurul Huda untuk membuat dan mengelola surat.
                </p>
                <ul class="space-y-4 text-sm text-white/90">
                    <li class="flex items-center gap-3">
                        <Check class="size-4" />Pilihan surat sesuai sekolah
                        atau pondok
                    </li>
                    <li class="flex items-center gap-3">
                        <Check class="size-4" />Impor daftar tujuan dari Excel
                        atau CSV
                    </li>
                    <li class="flex items-center gap-3">
                        <Check class="size-4" />Nomor berurutan dan cetak surat
                        massal
                    </li>
                </ul>
            </div>
            <p class="text-xs text-white/65">
                Penerimaan Mahasiswa Baru · Universitas Nurul Huda
            </p>
            <div
                aria-hidden="true"
                class="absolute -right-32 -bottom-32 size-96 rounded-full border border-white/10"
            ></div>
        </aside>
        <main
            class="sisurat-auth flex flex-col items-center justify-center px-5 py-10 sm:px-10"
        >
            <div class="sisurat-page-enter w-full max-w-md">
                <Link
                    :href="home()"
                    class="mb-8 flex items-center justify-center gap-3 text-primary lg:hidden dark:text-emerald-200"
                    aria-label="SISURAT PMB Universitas Nurul Huda, kembali ke beranda"
                >
                    <AppLogoIcon class="size-14 rounded-lg p-1" />
                    <span class="grid gap-1">
                        <span class="text-lg font-semibold">SISURAT</span>
                        <span class="text-xs text-muted-foreground"
                            >Sekretaris PMB · UNUHA</span
                        >
                    </span>
                </Link>
                <div class="sisurat-panel p-7 sm:p-9">
                    <div class="mb-8 space-y-3">
                        <p class="sisurat-kicker">Selamat datang</p>
                        <h1
                            class="text-2xl leading-snug font-semibold tracking-tight"
                        >
                            {{ title }}
                        </h1>
                        <p class="sisurat-subtitle">{{ description }}</p>
                    </div>
                    <slot />
                </div>
                <p
                    class="mt-7 text-center text-xs leading-6 text-muted-foreground"
                >
                    Sekretaris PMB · Universitas Nurul Huda
                </p>
            </div>
        </main>
    </div>
</template>
