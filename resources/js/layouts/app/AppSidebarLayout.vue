<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

withDefaults(defineProps<{ breadcrumbs?: BreadcrumbItem[] }>(), {
    breadcrumbs: () => [],
});
const page = usePage();
</script>

<template>
    <AppShell variant="sidebar">
        <a
            href="#main-content"
            class="sisurat-skip-link sr-only z-50 rounded-lg bg-card px-4 py-3 text-primary shadow-lg focus:not-sr-only focus:fixed focus:top-3 focus:left-3"
            >Lewati ke isi halaman</a
        >
        <AppSidebar />
        <AppContent
            variant="sidebar"
            class="sisurat-workspace min-w-0 overflow-x-clip"
        >
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <div
                :key="page.component"
                id="main-content"
                class="sisurat-page-enter flex min-w-0 flex-1 flex-col"
                tabindex="-1"
            >
                <slot />
            </div>
        </AppContent>
        <Toaster />
    </AppShell>
</template>
