<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowUpRight, ShieldCheck } from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home, login, dashboard, information } from '@/routes';
import { show as publicAttendance } from '@/routes/internship';
defineProps<{ active?: 'home' | 'internship' | 'information' }>();
const page = usePage();
</script>

<template>
    <div class="sisurat-workspace flex min-h-screen flex-col">
        <a
            href="#main-content"
            class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:rounded-xl focus:bg-card focus:p-4"
            >Langsung ke isi halaman</a
        >
        <header class="border-b border-border/70 bg-card/90">
            <div
                class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-5 py-5 sm:px-8"
            >
                <Link
                    :href="home()"
                    class="flex items-center gap-3"
                    aria-label="Halaman utama SISURAT"
                >
                    <span
                        class="grid size-12 shrink-0 place-items-center rounded-2xl border border-border bg-white"
                        ><AppLogoIcon class="size-10"
                    /></span>
                    <span
                        ><span class="block text-lg font-bold tracking-wide"
                            >SISURAT</span
                        ><span
                            class="text-[10px] text-muted-foreground sm:text-xs"
                            >PMB · Universitas Nurul Huda</span
                        ></span
                    >
                </Link>
                <nav
                    class="flex flex-wrap items-center gap-2 sm:gap-4"
                    aria-label="Navigasi utama"
                >
                    <Link
                        :href="home()"
                        class="hidden rounded-lg px-3 py-2 text-sm sm:block"
                        :aria-current="active === 'home' ? 'page' : undefined"
                        >Beranda</Link
                    >
                    <Link
                        :href="information()"
                        class="rounded-lg px-3 py-2 text-xs font-medium hover:bg-accent sm:text-sm"
                        :aria-current="
                            active === 'information' ? 'page' : undefined
                        "
                        >Informasi</Link
                    >
                    <Link
                        :href="publicAttendance()"
                        class="rounded-lg px-3 py-2 text-xs font-medium hover:bg-accent sm:text-sm"
                        :aria-current="
                            active === 'internship' ? 'page' : undefined
                        "
                        >Absensi Magang</Link
                    >
                    <Link
                        :href="page.props.auth?.user ? dashboard() : login()"
                        class="sisurat-primary-action !gap-2 !px-4 !py-3 !text-xs sm:!text-sm"
                        ><ShieldCheck class="size-4" />{{
                            page.props.auth?.user
                                ? 'Dashboard Admin'
                                : 'Login Admin'
                        }}</Link
                    >
                </nav>
            </div>
        </header>
        <main id="main-content" class="sisurat-page-enter flex-1">
            <slot />
        </main>
        <footer class="mt-12 border-t border-border bg-card/70">
            <div
                class="mx-auto flex max-w-7xl flex-col gap-2 px-5 py-6 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between sm:px-8"
            >
                <p>SISURAT · Sekretariat PMB Universitas Nurul Huda</p>
                <a
                    href="https://pmb.unuha.ac.id"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-1.5 hover:text-primary"
                    >Informasi pendaftaran mahasiswa baru
                    <ArrowUpRight class="size-3.5"
                /></a>
            </div>
        </footer>
    </div>
</template>
