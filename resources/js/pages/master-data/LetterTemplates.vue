<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, FileText, Pencil } from '@lucide/vue';
import { index as masterIndex } from '@/routes/master-data';
import { edit } from '@/routes/master-data/letter-templates';

type TemplateRecord = {
    id: number;
    key: string;
    name: string;
    type: string;
    destination: string | null;
    revision: number;
    updated_at: string | null;
    content: Record<string, string>;
};
type TemplateDefinition = {
    key: string;
    name: string;
    type: string;
    destination: string | null;
    fields: { id: string; label: string; limit: number; required: boolean }[];
    tokens: { key: string; label: string; sample: string }[];
};
const props = defineProps<{
    templates: TemplateRecord[];
    catalog: TemplateDefinition[];
}>();
function fieldLabels(key: string): string {
    return (
        props.catalog
            .find((definition) => definition.key === key)
            ?.fields.map((field) => field.label)
            .join(' · ') ?? ''
    );
}
function lastUpdated(value: string | null): string {
    if (!value) return 'Isi awal';
    const date = new Date(value);
    if (!Number.isFinite(date.getTime())) return 'Isi awal';
    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(date);
}
</script>

<template>
    <main class="sisurat-page space-y-6">
        <Head title="Master Isi Surat" />
        <Link :href="masterIndex()" class="sisurat-secondary-action w-fit"
            ><ArrowLeft class="size-4" aria-hidden="true" />Master Data</Link
        >
        <header>
            <p class="sisurat-kicker">Master Data · Isi Surat</p>
            <h1 class="sisurat-title mt-2">Isi Surat</h1>
            <p class="sisurat-subtitle mt-2 max-w-3xl">
                Ubah pengantar, keterangan, dan penutup yang digunakan saat
                membuat surat izin, surat tugas, dan surat keterangan PKKMB.
            </p>
        </header>
        <section
            class="rounded-2xl border border-primary/15 bg-primary/5 p-5 text-sm leading-7 text-muted-foreground"
            aria-label="Penggunaan isi surat"
        >
            <p class="font-medium text-foreground">
                Perubahan digunakan untuk surat yang dibuat berikutnya.
            </p>
            <p>
                Nama, tanggal, wilayah, dan data lainnya diisi otomatis sesuai
                formulir. Surat yang sudah tersimpan di Arsip Surat tetap
                menyimpan isi saat surat dibuat.
            </p>
        </section>
        <section
            class="grid gap-4 lg:grid-cols-2"
            aria-label="Pilihan isi surat"
        >
            <article
                v-for="record in templates"
                :key="record.id"
                class="sisurat-panel flex min-w-0 flex-col p-5 sm:p-7"
            >
                <div class="flex items-start gap-3">
                    <span class="sisurat-icon-tile shrink-0"
                        ><FileText class="size-5" aria-hidden="true"
                    /></span>
                    <div class="min-w-0">
                        <h2 class="text-base font-semibold">
                            {{ record.name }}
                        </h2>
                        <p class="mt-1 text-xs leading-6 text-muted-foreground">
                            Terakhir disimpan:
                            {{ lastUpdated(record.updated_at) }}
                        </p>
                    </div>
                </div>
                <p class="mt-4 flex-1 text-sm leading-7 text-muted-foreground">
                    {{ fieldLabels(record.key) }}
                </p>
                <Link
                    :href="edit(record.id)"
                    class="sisurat-secondary-action mt-5 self-start"
                    :aria-label="`Ubah isi ${record.name}`"
                    ><Pencil class="size-4" aria-hidden="true" />Ubah isi
                    surat<ArrowRight class="size-4" aria-hidden="true"
                /></Link>
            </article>
        </section>
    </main>
</template>
