export const guideVideoDuration = 374;

export const guideVideoChapters = [
    { title: 'Mulai & login', seconds: 0 },
    { title: 'Persiapan & Master Data', seconds: 28 },
    { title: 'Membuat surat', seconds: 110 },
    { title: 'Mengelola arsip', seconds: 218 },
    { title: 'Daftar hadir online', seconds: 240 },
    { title: 'Tes wawancara', seconds: 310 },
    { title: 'Tes baca Al-Qur’an', seconds: 344 },
    { title: 'Akun & bantuan', seconds: 356 },
] as const;

export function guideVideoTime(seconds: number): string {
    return `${Math.floor(seconds / 60)}:${String(Math.floor(seconds % 60)).padStart(2, '0')}`;
}
