<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import QuranExamForm from '@/components/QuranExamForm.vue';
import { index } from '@/routes/quran-exams';
defineProps<{ today: string }>();
</script>
<template>
    <main class="sisurat-page">
        <Head title="Buat Tautan Penguji Al-Qur’an" />
        <div class="mx-auto max-w-2xl space-y-6">
            <Link :href="index()" class="sisurat-secondary-action"
                ><ArrowLeft class="size-4" />Kembali ke tautan penguji</Link
            >
            <header>
                <p class="sisurat-kicker">Tes Baca Al-Qur’an</p>
                <h1 class="sisurat-title mt-2">Buat tautan penguji</h1>
                <p class="sisurat-subtitle mt-3">
                    Satu tautan untuk satu kegiatan tes. Penguji dapat mengisi
                    nilai tanpa login ke SISURAT.
                </p>
            </header>
            <section class="sisurat-panel p-5 sm:p-7">
                <QuranExamForm :today="today" />
            </section>
        </div>
    </main>
</template>
