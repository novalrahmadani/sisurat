<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, ShieldCheck } from '@lucide/vue';
import { login } from '@/routes';

defineOptions({
    layout: {
        title: 'Akses SISURAT dikelola admin',
        description: 'Pendaftaran akun umum ditutup.',
    },
});
</script>

<template>
    <div lang="id" translate="no" class="notranslate space-y-6 text-center">
        <Head title="Pendaftaran ditutup" />
        <ShieldCheck class="mx-auto size-10 text-primary" aria-hidden="true" />
        <p class="text-sm leading-7 text-muted-foreground">
            Akses khusus akun yang dibuat oleh admin. Hubungi admin untuk
            mendapatkan akses ke SISURAT.
        </p>
        <Link :href="login()" class="sisurat-primary-action w-full">
            Kembali ke halaman masuk<ArrowRight
                class="size-4"
                aria-hidden="true"
            />
        </Link>
    </div>
</template>
