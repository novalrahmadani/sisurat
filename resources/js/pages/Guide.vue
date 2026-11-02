<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    ArrowRight,
    BookOpen,
    CheckCheck,
    ChevronDown,
    Download,
    ExternalLink,
    FileSpreadsheet,
    FileText,
    GraduationCap,
    Printer,
    UsersRound,
    MessageSquareText,
    Play,
} from '@lucide/vue';
import {
    guideVideoChapters,
    guideVideoDuration,
    guideVideoTime,
} from '@/lib/guideVideo';
import { maxLettersPerBatch } from '@/lib/letters';
import {
    create as createLetter,
    general,
    index as lettersIndex,
    pkkmb,
} from '@/routes/letters';
import { index as masterData } from '@/routes/master-data';
import { index as attendanceIndex } from '@/routes/attendance';
import { index as interviewsIndex } from '@/routes/interviews';
import { index as quranTestsIndex } from '@/routes/quran-tests';
import { index as accountsIndex } from '@/routes/accounts';
import { index as letterTemplates } from '@/routes/master-data/letter-templates';
import { edit as editPmbSignatory } from '@/routes/master-data/pmb-signatory';
import { school as assignmentSchool } from '@/routes/letters/assignment';
import { school as permissionSchool } from '@/routes/letters/permission';
import { book as readGuideBook, video as playGuideVideo } from '@/routes/guide';
import { download as downloadGuideBook } from '@/routes/guide/book';
import {
    download as downloadGuideVideo,
    poster as guideVideoPoster,
} from '@/routes/guide/video';

const page = usePage();
const videoPlayer = ref<HTMLVideoElement | null>(null);
const videoTime = ref(0);
const videoFailed = ref(false);
const pendingChapter = ref<number | null>(null);
const videoHint = ref('');
const activeChapter = computed(() =>
    guideVideoChapters.findLastIndex(
        (chapter) => chapter.seconds <= videoTime.value,
    ),
);

async function openVideoChapter(seconds: number): Promise<void> {
    const player = videoPlayer.value;
    if (!player) return;

    videoFailed.value = false;
    videoHint.value = '';
    if (player.readyState < 1) {
        pendingChapter.value = seconds;
        player.load();
        return;
    }

    player.currentTime = seconds;
    videoTime.value = seconds;
    try {
        await player.play();
    } catch {
        videoHint.value =
            'Bab sudah dipilih. Tekan tombol Putar pada video untuk melanjutkan.';
    }
}

function videoLoaded(): void {
    if (pendingChapter.value === null) return;
    const seconds = pendingChapter.value;
    pendingChapter.value = null;
    void openVideoChapter(seconds);
}

function updateVideoTime(): void {
    videoTime.value = videoPlayer.value?.currentTime ?? 0;
}

const accountGuide = {
    title: 'Akun dan Hak Akses',
    icon: UsersRound,
    href: accountsIndex(),
    actionLabel: 'Kelola Akun',
    points: [
        'Pendaftaran umum ditutup. Akun hanya dapat dibuat oleh admin melalui Pengaturan → Kelola Akun → Tambah akun.',
        'Isi nama, email, kata sandi dan konfirmasi, lalu pilih peran Admin atau Petugas. Admin dapat mengelola akun; Petugas menggunakan fitur operasional SISURAT.',
        'Aktifkan akun saat siap digunakan. Untuk menghentikan akses, ubah status menjadi Nonaktif. Sesi masuk akun tersebut akan dicabut.',
        'Admin dapat mengubah peran dan kata sandi akun lain. Kata sandi sendiri diubah melalui Pengaturan → Keamanan. Akun admin utama selalu aktif dan tetap berperan Admin.',
    ],
};
const pmbSignatoryGuide = {
    title: 'Penandatangan PMB',
    icon: UsersRound,
    href: editPmbSignatory(),
    actionLabel: 'Kelola Penandatangan PMB',
    points: [
        'Admin membuka Master Data → Penandatangan PMB untuk mengubah nama, jabatan, dan NIP/NIP.Y kepala atau ketua pusat PMB.',
        'Unggah tanda tangan dan cap dalam PNG, JPG, atau WebP, maksimal 2 MB per gambar. Gunakan PNG transparan jika tersedia. Periksa pratinjau sebelum menyimpan.',
        'Jika nama penandatangan diganti, unggah tanda tangan pejabat yang baru. Perubahan digunakan pada surat dan kegiatan baru; arsip mempertahankan profil saat dibuat.',
    ],
};
const steps = [
    {
        number: '01',
        title: 'Jenis & pengaturan',
        description:
            'Buka Buat Surat dan pilih jenisnya. Periksa tanggal, kode, serta nomor awal yang sudah disarankan. Untuk sosialisasi, pilih sekolah atau pondok.',
        icon: FileText,
    },
    {
        number: '02',
        title: 'Isi data',
        description:
            'Untuk izin, pilih lembaga dan lengkapi data surat. Untuk surat tugas, pilih kabupaten/kota, pilih dosen/petugas dari Master Data atau isi manual, lalu tentukan tanggal kegiatan.',
        icon: FileSpreadsheet,
    },
    {
        number: '03',
        title: 'Periksa & buat',
        description:
            'Periksa ringkasan penerima, wilayah, dan nomor. Pilih Simpan & lihat surat, lalu cetak hasil atau simpan sebagai PDF dari dialog cetak.',
        icon: Printer,
    },
];
const letterGuides = [
    {
        title: 'Tes Baca Al-Qur’an CAMABA',
        icon: BookOpen,
        href: quranTestsIndex(),
        actionLabel: 'Buka tes baca Al-Qur’an',
        points: [
            'Tes dilakukan secara tatap muka. Buka Tes Baca Al-Qur’an → Tambah hasil tes, lalu isi nama, nomor registrasi, program studi, dan tanggal tes.',
            'Isi nama penguji dan surah/ayat yang dibaca. Catat nilai makhraj, tajwid, serta kelancaran dalam rentang 0–100. Rata-rata ditampilkan sebagai ringkasan nilai.',
            'Petugas menentukan hasil secara manual: Lancar, Cukup, atau Perlu bimbingan. Gunakan Menunggu penilaian jika tes belum selesai, dan tambahkan catatan bila diperlukan.',
            'Cari peserta melalui nama, nomor registrasi, program studi, atau penguji. Buka detail untuk mencetak hasil, atau unduh CSV dari daftar hasil.',
            'Gunakan Tambah hasil tes untuk mencatat tes ulang sebagai riwayat terpisah. Program studi tersimpan bersama hasil; perubahan Master Data tidak mengubah nama prodi pada hasil sebelumnya.',
        ],
    },
    {
        title: 'Tes Wawancara Mahasiswa Baru',
        icon: MessageSquareText,
        href: interviewsIndex(),
        actionLabel: 'Buka Tes Wawancara PMB',
        points: [
            'Buka Tes Wawancara PMB, lalu buat form. Isi judul dan petunjuk pengisian. Tambahkan, ubah, urutkan, atau hapus pertanyaan sesuai kebutuhan dan tentukan pertanyaan yang wajib dijawab.',
            'Aktifkan penerimaan jawaban dan salin tautan form untuk dibagikan. Mahasiswa mengisi nama, nomor registrasi, dan program studi, lalu mengetik jawaban tanpa perlu login.',
            'Program studi diambil dari Master Data. Nomor registrasi yang sama hanya dapat mengirim satu hasil pada setiap form. Mahasiswa perlu memeriksa jawaban sebelum mengirim.',
            'Buka hasil form untuk mencari peserta, membaca seluruh jawaban, mencetak hasil, atau mengekspor CSV. Tutup penerimaan setelah selesai.',
            'Perubahan pertanyaan berlaku untuk pengisian berikutnya. Hasil yang sudah masuk menyimpan pertanyaan dan jawaban sesuai saat dikirim.',
            'Tautan harus memakai alamat SISURAT yang dapat diakses mahasiswa. Alamat localhost hanya dapat dibuka dari komputer yang menjalankan aplikasi.',
        ],
    },
    {
        title: 'Daftar Hadir Online',
        icon: UsersRound,
        href: attendanceIndex(),
        actionLabel: 'Buka Daftar Hadir',
        points: [
            'Buka menu Daftar Hadir, lalu pilih Rapat, Tes PMB (Penerimaan Mahasiswa Baru), atau Kegiatan Lainnya. Setiap kategori memiliki daftar kegiatan dan rekap peserta sendiri.',
            'Buat kegiatan pada kategori yang dipilih, lalu isi nama kegiatan, tanggal, dan tempat. Kegiatan yang sudah memiliki peserta mempertahankan format kolomnya. Aktifkan penerimaan peserta saat formulir siap dibagikan.',
            'Salin tautan pengisian dan bagikan kepada peserta. Untuk Tes PMB, peserta mengisi nama, nomor registrasi, program studi dari Master Data, asal sekolah, dan nomor HP/WhatsApp. Untuk Rapat dan Kegiatan Lainnya, peserta mengisi nama, jabatan, dan instansi/institusi.',
            'Hasil cetak Tes PMB memuat No, Nama, Program Studi/Jurusan yang Diambil, Asal Sekolah, No. HP/WhatsApp, dan Tanda tangan. Pengesahan kepala pusat PMB mengikuti profil saat kegiatan dibuat. Kolom sekolah dan nomor HP kosong pada data lama yang belum memuat keduanya.',
            'Peserta menggambar tanda tangan dengan jari, pena digital, atau mouse tanpa perlu login. Nomor registrasi tetap menyimpan angka nol di awal, dan program studi tersimpan sesuai pilihan saat peserta mengisi.',
            'Buka kegiatan untuk melihat peserta dan tanda tangan. Gunakan Cetak semua untuk rekap dengan tanda tangan atau Ekspor CSV untuk mengolah data peserta di Excel. Tutup penerimaan setelah kegiatan selesai.',
            'Gunakan Hapus pada baris peserta untuk menghapus satu data kehadiran beserta tanda tangannya. Hapus kegiatan akan menghapus seluruh peserta kegiatan tersebut dan membuat tautan pengisiannya tidak berlaku. Periksa nama yang ditampilkan pada konfirmasi sebelum melanjutkan.',
            'Tautan localhost hanya dapat dibuka dari komputer yang menjalankan SISURAT. Pengisian dari perangkat lain membutuhkan alamat SISURAT yang dapat diakses perangkat tersebut.',
        ],
    },
    {
        title: 'Draft & surat serupa',
        icon: FileText,
        href: lettersIndex(),
        actionLabel: 'Buka Arsip Surat',
        points: [
            'Draft formulir tersimpan otomatis di browser dan akun yang sedang dipakai. Buka kembali formulir yang sama, lalu pilih Lanjutkan draft. Periksa status penyimpanan pada formulir.',
            'Gunakan Buat surat serupa di Arsip Surat untuk menyalin isi dan penerima surat sebelumnya. Surat baru memakai tanggal hari ini dan saran nomor terbaru; periksa tanggal kegiatan serta isinya.',
            'Draft dan salinan baru masih perlu diperiksa sebelum disimpan sebagai surat. Setelah berhasil dibuat, hasil tersedia di Arsip Surat.',
            'Penomoran mengikuti tahun pada tanggal surat. Tahun yang belum memiliki surat dimulai dari 001; tahun yang sudah memiliki surat melanjutkan nomor terakhir. Saat tahun tanggal surat diubah, saran nomor awal ikut menyesuaikan. Perubahan tanggal dalam tahun yang sama mempertahankan nomor yang Anda isi.',
            'Di Arsip Surat, Hapus kelompok mengeluarkan seluruh surat dalam kelompok tersebut dari arsip. Untuk menghapus satu surat, buka kelompoknya lalu gunakan Hapus surat pada surat yang dipilih. Nomor surat tetap dicatat agar tidak digunakan ulang.',
            'Tombol Hapus tersedia untuk surat dan kegiatan yang Anda buat. Periksa rincian pada konfirmasi, lalu pilih Hapus atau Batal.',
        ],
    },
    {
        title: 'Master Data',
        icon: UsersRound,
        href: masterData(),
        actionLabel: 'Buka Master Data',
        points: [
            'Buka Master Data untuk mengelola Sekolah, Pondok/Ponpes, Kabupaten/Kota, Dosen/Petugas, Program Studi, Fakultas, dan Isi Surat. Admin juga dapat mengelola Penandatangan PMB.',
            'Daftarkan kabupaten/kota, lalu pilih saat menambahkan sekolah, pondok, atau wilayah dosen/petugas. Kabupaten/kota yang masih digunakan harus dipindahkan dari data terkait sebelum dapat dihapus.',
            'Data sekolah dan pondok tersedia di formulir izin. Dosen/petugas dapat dipilih pada surat tugas untuk mengisi nama dan dinas otomatis, atau tetap diisi manual.',
            'Tambahkan program studi dan fakultas melalui Master Data agar dapat dipilih pada surat keterangan PKKMB. Program studi juga menjadi pilihan pada daftar hadir Tes PMB dan form tes wawancara mahasiswa baru.',
            'Penghapusan memerlukan konfirmasi. Perubahan dan penghapusan Master Data tidak mengubah isi surat yang sudah tersimpan di Arsip Surat.',
        ],
    },
    {
        title: 'Mengubah isi surat',
        icon: FileText,
        href: letterTemplates(),
        actionLabel: 'Buka Isi Surat',
        points: [
            'Buka Master Data → Isi Surat, pilih surat izin sekolah atau pondok, surat tugas sekolah atau pondok, atau surat keterangan PKKMB, lalu pilih Ubah isi surat.',
            'Ubah kalimat pada kolom yang tersedia. Klik kolom isi, lalu gunakan tombol Data otomatis untuk menyisipkan nama, tanggal, wilayah, tema, atau data lain dari formulir.',
            'Pertahankan data periode kegiatan pada keterangan surat tugas. Keterangan PKKMB harus memuat periode kegiatan, tema PKKMB, dan hasil PKKMB. Bagian bertanda dua kurung kurawal diisi otomatis.',
            'Periksa contoh isi, lalu simpan. Perubahan dipakai untuk surat yang dibuat berikutnya; isi surat yang sudah tersimpan di Arsip Surat tetap mengikuti saat surat dibuat.',
        ],
    },
    {
        title: 'Surat Umum',
        icon: FileText,
        href: general(),
        points: [
            'Pilih surat umum, undangan, rapat, atau lainnya. Isi judul, perihal, pengantar surat, serta isi penutup pada dua kolom terpisah. Detail kegiatan dicetak di antara pengantar dan penutup.',
            'Isi sapaan Yth., tujuan/instansi, jabatan, nama penerima, dan alamat secara manual. Tambahkan penerima jika ingin membuat beberapa surat.',
            'Atur tanggal, nomor urut awal, kode, dan penandatangan. Tanggal kegiatan, waktu, tempat, serta agenda bersifat opsional. Tinjau hasil sebelum mencetak.',
        ],
    },
    {
        title: 'Permohonan izin sosialisasi',
        icon: GraduationCap,
        href: permissionSchool(),
        points: [
            'Pilih sekolah / madrasah atau pondok pesantren.',
            'Pilih nama lembaga dari database untuk mengisi kabupaten/kota otomatis. Alamat rinci dan penerima bisa dilengkapi sebelum membuat surat.',
            'Satu tujuan menghasilkan satu surat dengan nomor berurutan.',
        ],
    },
    {
        title: 'Surat tugas',
        icon: UsersRound,
        href: assignmentSchool(),
        points: [
            'Pilih sekolah atau pondok, lalu tentukan tanggal kegiatan dari awal sampai akhir.',
            'Pilih kategori kabupaten/kota dan dosen/petugas dari Master Data untuk mengisi nama serta dinas. Isian dapat diubah manual. Kabupaten/kota petugas hanya diisi otomatis jika kelompok masih kosong.',
            'Satu kelompok kabupaten/kota menghasilkan satu surat. Atur nomor urut awal dan kode surat; nomor berikutnya berurutan dan penandatangan diisi otomatis.',
        ],
    },
    {
        title: 'Surat Keterangan PKKMB',
        icon: CheckCheck,
        href: pkkmb(),
        points: [
            'Isi tanggal pelaksanaan dan tema PKKMB. Atur tanggal surat, nomor awal, serta kode PPMB-UNUHA/TU.1.1 sesuai kebutuhan. Penandatangan, tanda tangan, dan cap memakai default PMB.',
            'Masukkan nama mahasiswa dan NIM, lalu pilih program studi serta fakultas dari Master Data. Pilih hasil PKKMB LULUS atau TIDAK LULUS sesuai data.',
            'CSV menggunakan kolom nama_mahasiswa, nim, program_studi, fakultas, dan hasil. Simpan NIM sebagai teks agar nol di depan tetap utuh. CSV lama masih dapat diimpor; lengkapi fakultas sebelum menyimpan. Status registered tidak dapat digunakan untuk surat keterangan telah mengikuti PKKMB.',
        ],
    },
];
const visibleGuides = computed(() => [
    ...(page.props.can?.manage_accounts ? [accountGuide] : []),
    ...(page.props.can?.manage_pmb_signatory ? [pmbSignatoryGuide] : []),
    ...letterGuides,
]);
</script>

<template>
    <main class="sisurat-page space-y-7">
        <Head title="Panduan penggunaan" />
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="sisurat-kicker">Pusat bantuan</p>
                <h1 class="sisurat-title mt-2">Panduan penggunaan</h1>
                <p class="sisurat-subtitle mt-2">
                    Langkah singkat untuk membuat surat dengan cepat dan tepat.
                </p>
            </div>
            <Link :href="createLetter()" class="sisurat-primary-action"
                >Mulai membuat surat<ArrowRight
                    class="size-4"
                    aria-hidden="true"
            /></Link>
        </header>
        <section
            class="sisurat-panel overflow-hidden"
            aria-labelledby="guide-video-heading"
        >
            <div
                class="flex flex-wrap items-start justify-between gap-5 p-6 sm:p-8"
            >
                <div class="max-w-2xl">
                    <p class="sisurat-kicker">Video tutorial MP4</p>
                    <h2
                        id="guide-video-heading"
                        class="mt-2 text-xl font-semibold tracking-tight"
                    >
                        Ikuti tampilannya, praktikkan langkahnya.
                    </h2>
                    <p class="mt-3 text-sm leading-6 text-muted-foreground">
                        Panduan visual dengan kotak bernomor dan kursor
                        bergerak. Tanpa narasi suara; petunjuk tertulis tampil
                        di dalam video. Jeda saat praktik dan gunakan layar
                        penuh agar tulisan lebih jelas.
                    </p>
                    <p class="mt-3 text-xs text-muted-foreground">
                        {{ guideVideoTime(guideVideoDuration) }} menit · 33
                        langkah · 8 bab · Full HD 1080p
                    </p>
                </div>
                <a
                    :href="downloadGuideVideo.url()"
                    class="sisurat-primary-action justify-center"
                >
                    <Download class="size-4" aria-hidden="true" />Unduh MP4
                </a>
            </div>
            <div class="border-y border-border bg-slate-950">
                <video
                    ref="videoPlayer"
                    controls
                    playsinline
                    preload="metadata"
                    :poster="guideVideoPoster.url()"
                    class="mx-auto aspect-video w-full max-w-6xl"
                    aria-label="Video tutorial penggunaan SISURAT dengan petunjuk tertulis"
                    @loadedmetadata="videoLoaded"
                    @timeupdate="updateVideoTime"
                    @error="videoFailed = true"
                >
                    <source :src="playGuideVideo.url()" type="video/mp4" />
                    Browser Anda belum mendukung pemutar video.
                    <a :href="downloadGuideVideo.url()">Unduh video MP4</a>
                    untuk menontonnya.
                </video>
            </div>
            <div class="p-6 sm:p-8">
                <p
                    v-if="videoFailed"
                    role="alert"
                    class="mb-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-900"
                >
                    Video belum dapat diputar. Muat ulang halaman atau gunakan
                    Unduh MP4 untuk membukanya melalui pemutar video di
                    perangkat Anda.
                </p>
                <p
                    v-if="videoHint"
                    role="status"
                    class="mb-4 text-sm text-muted-foreground"
                >
                    {{ videoHint }}
                </p>
                <h3 class="text-sm font-semibold">
                    Langsung ke bab yang dibutuhkan
                </h3>
                <div class="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                    <button
                        v-for="(chapter, index) in guideVideoChapters"
                        :key="chapter.seconds"
                        type="button"
                        :aria-label="`Putar bab ${chapter.title}, mulai ${guideVideoTime(chapter.seconds)}`"
                        :aria-pressed="activeChapter === index"
                        class="flex min-h-16 items-center gap-3 rounded-xl border p-3 text-left text-sm transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                        :class="
                            activeChapter === index
                                ? 'border-primary/30 bg-primary/5 text-primary'
                                : 'border-border hover:bg-muted'
                        "
                        @click="openVideoChapter(chapter.seconds)"
                    >
                        <Play class="size-4 shrink-0" aria-hidden="true" />
                        <span class="flex-1 font-medium">{{
                            chapter.title
                        }}</span>
                        <span
                            class="text-xs text-muted-foreground tabular-nums"
                            >{{ guideVideoTime(chapter.seconds) }}</span
                        >
                    </button>
                </div>
                <p class="mt-4 text-xs leading-5 text-muted-foreground">
                    Pilih bab untuk mulai memutar bagian tersebut. Angka dan
                    data pada video adalah contoh tampilan saat direkam;
                    sesuaikan isian dengan data kegiatan Anda. Buku PDF di bawah
                    menyediakan petunjuk yang dapat dibaca tanpa memutar video.
                </p>
            </div>
        </section>
        <section
            class="sisurat-panel grid gap-6 overflow-hidden p-6 sm:p-8 lg:grid-cols-[1fr_auto] lg:items-center"
            aria-labelledby="guide-book-heading"
        >
            <div class="flex min-w-0 items-start gap-4 sm:gap-5">
                <span class="sisurat-icon-tile shrink-0"
                    ><BookOpen class="size-6" aria-hidden="true"
                /></span>
                <div class="min-w-0">
                    <p class="sisurat-kicker">Buku panduan PDF</p>
                    <h2
                        id="guide-book-heading"
                        class="mt-2 text-xl font-semibold tracking-tight"
                    >
                        Panduan SISURAT, langkah demi langkah
                    </h2>
                    <p
                        class="mt-3 max-w-2xl text-sm leading-6 text-muted-foreground"
                    >
                        Ikuti tutorial dengan screenshot, kotak penanda
                        bernomor, dan penjelasan singkat. Mulai dari login,
                        Master Data, pembuatan surat, daftar hadir, sampai tes
                        mahasiswa baru.
                    </p>
                    <ul
                        class="mt-4 flex flex-wrap gap-x-4 gap-y-2 text-xs text-muted-foreground"
                        aria-label="Informasi buku panduan"
                    >
                        <li>46 halaman</li>
                        <li>42 screenshot bernomor</li>
                        <li>PDF · 7,8 MB</li>
                        <li>Edisi 27 Oktober 2026</li>
                    </ul>
                </div>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row lg:flex-col">
                <a
                    :href="readGuideBook.url()"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="sisurat-primary-action justify-center"
                >
                    Baca PDF
                    <ExternalLink class="size-4" aria-hidden="true" />
                    <span class="sr-only">(buka di tab baru)</span>
                </a>
                <a
                    :href="downloadGuideBook.url()"
                    class="sisurat-secondary-action justify-center"
                >
                    <Download class="size-4" aria-hidden="true" />
                    Unduh PDF
                </a>
            </div>
        </section>
        <section
            class="sisurat-gradient flex items-center gap-5 rounded-3xl px-6 py-7 text-white sm:px-8"
        >
            <span
                class="hidden size-14 shrink-0 place-items-center rounded-2xl border border-white/20 bg-white/10 sm:grid"
                ><BookOpen class="size-7" aria-hidden="true"
            /></span>
            <div>
                <h2 class="text-xl font-semibold">
                    Tiga langkah, surat siap digunakan.
                </h2>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-white/80">
                    Gunakan formulir sesuai kebutuhan. SISURAT membantu
                    mengurutkan nomor, menyesuaikan isi, dan menyimpan hasilnya.
                </p>
            </div>
        </section>
        <section
            class="grid gap-4 lg:grid-cols-3"
            aria-label="Tahapan pembuatan surat"
        >
            <article
                v-for="step in steps"
                :key="step.number"
                class="sisurat-panel p-6"
            >
                <div class="flex items-center justify-between">
                    <span class="sisurat-icon-tile"
                        ><component
                            :is="step.icon"
                            class="size-5"
                            aria-hidden="true" /></span
                    ><span
                        class="text-2xl font-semibold tracking-tight text-primary/20"
                        >{{ step.number }}</span
                    >
                </div>
                <h2 class="mt-5 text-sm font-semibold">{{ step.title }}</h2>
                <p class="mt-3 text-xs leading-6 text-muted-foreground">
                    {{ step.description }}
                </p>
            </article>
        </section>
        <section aria-labelledby="letter-guide-heading">
            <h2 id="letter-guide-heading" class="mb-4 text-lg font-semibold">
                Data yang perlu disiapkan
            </h2>
            <div class="grid gap-4 lg:grid-cols-3">
                <article
                    v-for="letter in visibleGuides"
                    :key="letter.title"
                    class="sisurat-panel flex flex-col p-6"
                >
                    <component
                        :is="letter.icon"
                        class="size-5 text-primary"
                        aria-hidden="true"
                    />
                    <h3 class="mt-4 text-sm font-semibold">
                        {{ letter.title }}
                    </h3>
                    <ul
                        class="mt-4 flex-1 space-y-3 text-xs leading-6 text-muted-foreground"
                    >
                        <li
                            v-for="point in letter.points"
                            :key="point"
                            class="flex gap-2"
                        >
                            <span
                                class="mt-2 size-1.5 shrink-0 rounded-full bg-primary/50"
                                aria-hidden="true"
                            ></span
                            >{{ point }}
                        </li>
                    </ul>
                    <Link
                        :href="letter.href"
                        class="mt-6 inline-flex items-center gap-2 border-t border-border pt-4 text-xs font-semibold text-primary"
                        >{{ letter.actionLabel ?? 'Buka formulir'
                        }}<ArrowRight class="size-4" aria-hidden="true"
                    /></Link>
                </article>
            </div>
        </section>
        <section
            class="sisurat-panel overflow-hidden"
            aria-labelledby="help-heading"
        >
            <div class="border-b border-border px-6 py-5">
                <h2 id="help-heading" class="font-semibold">
                    Petunjuk praktis
                </h2>
                <p class="mt-1 text-xs text-muted-foreground">
                    Impor data, penomoran, dan pencetakan.
                </p>
            </div>
            <div class="divide-y divide-border">
                <details class="group px-6 py-5" open>
                    <summary
                        class="flex cursor-pointer list-none items-center justify-between gap-4 text-sm font-medium"
                    >
                        Bagaimana mengimpor Excel atau CSV?<ChevronDown
                            class="size-4 shrink-0 text-primary transition group-open:rotate-180"
                            aria-hidden="true"
                        />
                    </summary>
                    <div
                        class="mt-4 max-w-3xl space-y-3 text-xs leading-6 text-muted-foreground"
                    >
                        <p>
                            Impor tersedia untuk surat izin dan PKKMB. Pilih
                            jenis surat dan tujuan terlebih dahulu, lalu gunakan
                            <strong class="font-medium text-foreground"
                                >Unduh format CSV</strong
                            >
                            pada formulir agar kolom sesuai kebutuhan. Isi
                            datanya dan impor kembali. File Excel berformat XLSX
                            juga dapat digunakan.
                        </p>
                        <p>
                            Rekap sekolah dan pondok dapat dibaca dari sheet
                            <strong class="font-medium text-foreground"
                                >UNTK LPJ PMB</strong
                            >. Jika sheet tersebut tidak tersedia, sistem
                            membaca sheet pertama. Hanya baris yang sesuai
                            pilihan sekolah atau pondok yang dimasukkan.
                        </p>
                        <p>
                            Untuk PKKMB, gunakan kolom nama mahasiswa, NIM,
                            program studi, fakultas, dan hasil LULUS / TIDAK
                            LULUS. Gunakan nama program studi dan fakultas yang
                            tersedia di Master Data. Simpan NIM sebagai teks
                            agar angka nol di awal tetap ada.
                        </p>
                        <p>
                            Pilih untuk menambahkan data ke daftar atau
                            mengganti daftar. Batas impor: CSV 2 MB, XLSX 10 MB,
                            dan maksimal 1.000 surat dalam satu pembuatan.
                        </p>
                    </div>
                </details>
                <details class="group px-6 py-5">
                    <summary
                        class="flex cursor-pointer list-none items-center justify-between gap-4 text-sm font-medium"
                    >
                        Bagaimana nomor dan batch diatur?<ChevronDown
                            class="size-4 shrink-0 text-primary transition group-open:rotate-180"
                            aria-hidden="true"
                        />
                    </summary>
                    <div
                        class="mt-4 max-w-3xl space-y-3 text-xs leading-6 text-muted-foreground"
                    >
                        <p>
                            Nomor awal disarankan dari nomor terakhir pada tahun
                            berjalan. Sistem mengurutkan nomor untuk setiap
                            surat. Periksa kode surat dan tanggal sebelum
                            menyimpan.
                        </p>
                        <p>
                            Satu pembuatan dapat memuat hingga 1.000 surat.
                            Sistem membaginya menjadi batch berisi maksimal
                            {{ maxLettersPerBatch.toLocaleString('id-ID') }}
                            surat agar hasil mudah dibuka dan dicetak.
                        </p>
                    </div>
                </details>
                <details class="group px-6 py-5">
                    <summary
                        class="flex cursor-pointer list-none items-center justify-between gap-4 text-sm font-medium"
                    >
                        Bagaimana mencetak atau menyimpan PDF?<ChevronDown
                            class="size-4 shrink-0 text-primary transition group-open:rotate-180"
                            aria-hidden="true"
                        />
                    </summary>
                    <div
                        class="mt-4 max-w-3xl space-y-3 text-xs leading-6 text-muted-foreground"
                    >
                        <p>
                            Buka
                            <Link
                                :href="lettersIndex()"
                                class="font-medium text-primary underline"
                                >Arsip Surat</Link
                            >, pilih batch, lalu periksa tampilan surat. Gunakan
                            tombol
                            <strong class="font-medium text-foreground"
                                >Cetak semua</strong
                            >. Jika hanya membutuhkan halaman tertentu, atur
                            rentang halaman di dialog cetak.
                        </p>
                        <p>
                            Pada dialog cetak, pilih kertas A4. Untuk menyimpan
                            berkas, pilih tujuan
                            <strong class="font-medium text-foreground"
                                >Simpan sebagai PDF</strong
                            >. Nonaktifkan header dan footer bawaan browser agar
                            alamat halaman tidak ikut tercetak.
                        </p>
                    </div>
                </details>
                <details class="group px-6 py-5">
                    <summary
                        class="flex cursor-pointer list-none items-center justify-between gap-4 text-sm font-medium"
                    >
                        Bisakah memakai tujuan atau petugas
                        sebelumnya?<ChevronDown
                            class="size-4 shrink-0 text-primary transition group-open:rotate-180"
                            aria-hidden="true"
                        />
                    </summary>
                    <div
                        class="mt-4 max-w-3xl space-y-3 text-xs leading-6 text-muted-foreground"
                    >
                        <p>
                            Menu Master Data menampilkan daftar sekolah, pondok,
                            kabupaten/kota, dosen/petugas, program studi, dan
                            fakultas yang tersimpan. Pada sekolah atau pondok,
                            pilih Buat izin agar nama tujuan, kabupaten/kota,
                            dan alamat yang tersedia langsung terisi. Di
                            formulir, kamu juga bisa memilih beberapa tujuan
                            atau semua sekolah maupun pondok sekaligus.
                        </p>
                        <p>
                            Nama lembaga, kabupaten/kota, dan alamat dari data
                            sumber akan terisi otomatis. Jika alamat belum
                            tersedia, lengkapi jalan, desa, atau kecamatan pada
                            tujuan sebelum membuat surat.
                        </p>
                        <p>
                            Surat tugas memakai kelompok kabupaten/kota dan
                            daftar dosen/petugas. Pilih data petugas tersimpan
                            untuk mengisi nama dan dinas, lalu sesuaikan jika
                            diperlukan. Kabupaten/kota yang sudah dipilih tetap
                            digunakan untuk seluruh anggota kelompok. Draft
                            tersimpan otomatis pada browser yang dipakai.
                            Periksa status draft, lalu pilih Lanjutkan draft
                            saat membuka formulir kembali.
                        </p>
                    </div>
                </details>
            </div>
        </section>
    </main>
</template>
