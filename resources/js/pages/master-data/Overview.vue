<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { edit as pmbSignatory } from '@/routes/master-data/pmb-signatory';
import {
    ArrowRight,
    BookOpen,
    Building2,
    Database,
    FileText,
    GraduationCap,
    Landmark,
    MapPin,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import { pesantren, schools } from '@/routes/master-data';
import { index as districtsIndex } from '@/routes/master-data/districts';
import { index as staffIndex } from '@/routes/master-data/staff-members';
import { index as studyProgramsIndex } from '@/routes/master-data/study-programs';
import { index as facultiesIndex } from '@/routes/master-data/faculties';
import { index as letterTemplatesIndex } from '@/routes/master-data/letter-templates';
import { edit as letterheadEdit } from '@/routes/master-data/letterhead';

const props = defineProps<{
    counts: {
        schools: number;
        pondok: number;
        districts: number;
        staff: number;
        studyPrograms: number;
        faculties: number;
        letterTemplates: number;
    };
}>();
const page = usePage();
const categories = computed(() => [
    {
        title: 'Sekolah / Madrasah',
        count: props.counts.schools,
        icon: GraduationCap,
        route: schools(),
        description: 'Kelola nama, kabupaten/kota, dan alamat tujuan sekolah.',
        action: 'Kelola sekolah',
    },
    {
        title: 'Pondok Pesantren',
        count: props.counts.pondok,
        icon: Landmark,
        route: pesantren(),
        description:
            'Kelola data pondok pesantren untuk surat izin sosialisasi.',
        action: 'Kelola pondok',
    },
    {
        title: 'Kabupaten / Kota',
        count: props.counts.districts,
        icon: MapPin,
        route: districtsIndex(),
        description:
            'Atur wilayah yang digunakan sekolah, pondok, dan petugas.',
        action: 'Kelola kabupaten/kota',
    },
    {
        title: 'Dosen / Petugas',
        count: props.counts.staff,
        icon: Users,
        route: staffIndex(),
        description:
            'Simpan nama, dinas, identitas, dan wilayah dosen atau petugas.',
        action: 'Kelola dosen/petugas',
    },
    {
        title: 'Program Studi',
        count: props.counts.studyPrograms,
        icon: BookOpen,
        route: studyProgramsIndex(),
        description:
            'Kelola pilihan program studi mahasiswa untuk surat keterangan PKKMB.',
        action: 'Kelola program studi',
    },
    {
        title: 'Fakultas',
        count: props.counts.faculties,
        icon: Building2,
        route: facultiesIndex(),
        description:
            'Kelola pilihan fakultas mahasiswa agar data PKKMB konsisten.',
        action: 'Kelola fakultas',
    },
    {
        title: 'Isi Surat',
        count: props.counts.letterTemplates,
        icon: FileText,
        route: letterTemplatesIndex(),
        description:
            'Atur kalimat surat izin, surat tugas, dan surat keterangan PKKMB.',
        action: 'Kelola isi surat',
    },
    ...(page.props.can?.manage_pmb_signatory
        ? [
              {
                  title: 'Penandatangan PMB',
                  count: 1,
                  icon: Users,
                  route: pmbSignatory(),
                  description:
                      'Atur nama, jabatan, NIP, tanda tangan, dan cap kepala pusat PMB.',
                  action: 'Kelola penandatangan',
              },
          ]
        : []),
    ...(page.props.can?.manage_pmb_signatory
        ? [
              {
                  title: 'Kop Surat',
                  count: 1,
                  icon: FileText,
                  route: letterheadEdit(),
                  description:
                      'Ganti gambar kop surat yang digunakan pada surat dan dokumen cetak.',
                  action: 'Kelola kop surat',
              },
          ]
        : []),
]);
</script>

<template>
    <main class="sisurat-page space-y-6">
        <Head title="Master Data" />
        <header>
            <p class="sisurat-kicker">Ruang kerja SISURAT</p>
            <h1 class="sisurat-title mt-2">Master Data</h1>
            <p class="sisurat-subtitle mt-2 max-w-3xl">
                Kelola data yang digunakan saat membuat surat dan daftar hadir.
                Pilih kategori untuk melihat dan mengelola data serta isi surat.
            </p>
        </header>
        <section
            class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
            aria-label="Kategori master data"
        >
            <Link
                v-for="category in categories"
                :key="category.title"
                :href="category.route"
                class="sisurat-panel group flex flex-col p-5 transition-colors hover:border-primary/40 sm:p-7"
            >
                <div class="flex items-start justify-between gap-4">
                    <span class="sisurat-icon-tile"
                        ><component
                            :is="category.icon"
                            class="size-6"
                            aria-hidden="true"
                    /></span>
                    <span
                        class="rounded-full bg-primary/10 px-3 py-1.5 text-sm font-semibold text-primary tabular-nums dark:text-foreground"
                        >{{ category.count.toLocaleString('id-ID') }} data</span
                    >
                </div>
                <h2 class="mt-5 text-lg font-semibold">{{ category.title }}</h2>
                <p class="mt-2 flex-1 text-sm leading-7 text-muted-foreground">
                    {{ category.description }}
                </p>
                <span
                    class="mt-5 flex items-center gap-2 border-t border-border/60 pt-4 text-sm font-semibold text-primary dark:text-foreground"
                    >{{ category.action
                    }}<ArrowRight
                        class="size-4 transition-transform group-hover:translate-x-1 motion-reduce:transform-none"
                        aria-hidden="true"
                /></span>
            </Link>
        </section>
        <section
            class="flex items-start gap-3 rounded-2xl border border-primary/15 bg-primary/5 p-5"
            aria-label="Panduan pengelolaan data"
        >
            <Database
                class="mt-1 size-5 shrink-0 text-primary dark:text-foreground"
                aria-hidden="true"
            />
            <div class="space-y-1 text-sm leading-7 text-muted-foreground">
                <p class="font-medium text-foreground">
                    Lengkapi data wilayah, lembaga, petugas, program studi, dan
                    fakultas, lalu sesuaikan isi surat.
                </p>
                <p>
                    Data terbaru langsung tersedia di formulir surat. Surat yang
                    sudah dibuat tetap menyimpan data sesuai saat surat dibuat.
                </p>
            </div>
        </section>
    </main>
</template>
