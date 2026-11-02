<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { FilePlus2 } from '@lucide/vue';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { create as createLetter } from '@/routes/letters';
import type { BreadcrumbItem } from '@/types';

withDefaults(defineProps<{ breadcrumbs?: BreadcrumbItem[] }>(), {
    breadcrumbs: () => [],
});
const page = usePage();
const sectionTitle = computed(() => {
    const titles: Record<string, string> = {
        attendance: 'Daftar hadir kegiatan',
        'outgoing-letters': 'Agenda surat keluar',
        interviews: 'Tes wawancara mahasiswa baru',
        internship: 'Absensi peserta magang',
        accounts: 'Kelola akun dan akses',
        'quran-tests': 'Tes baca Al-Qur’an CAMABA',
        'quran-exams': 'Tautan penguji Tes Baca Al-Qur’an',
    };
    return titles[page.component.split('/')[0]] ?? 'Administrasi surat';
});
</script>

<template>
    <header
        class="sisurat-app-header sticky top-0 z-20 flex min-h-18 shrink-0 items-center justify-between gap-3 border-b border-border bg-card/95 px-5 py-3 backdrop-blur-sm sm:px-6 lg:px-8 print:hidden"
    >
        <div class="flex min-w-0 items-center gap-3">
            <SidebarTrigger
                class="-ml-1 size-9 rounded-lg"
                aria-label="Buka atau tutup menu"
            />
            <div class="min-w-0">
                <p
                    class="mb-1 hidden text-[10px] font-medium tracking-widest text-muted-foreground uppercase sm:block"
                >
                    Sekretaris PMB · Universitas Nurul Huda
                </p>
                <Breadcrumbs
                    v-if="breadcrumbs.length"
                    :breadcrumbs="breadcrumbs"
                />
                <span v-else class="text-sm font-medium">{{
                    sectionTitle
                }}</span>
            </div>
        </div>
        <Link
            :href="createLetter()"
            :preserve-state="page.component === 'letters/Create'"
            class="sisurat-primary-action min-h-9 px-3 text-xs"
        >
            <FilePlus2 class="size-4" />
            <span class="hidden sm:inline">Buat surat</span>
            <span class="sm:hidden">Buat</span>
        </Link>
    </header>
</template>
