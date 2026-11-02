import type { PmbTestSession } from '@/lib/pmbTestSession';
export type QuranResult =
    | 'pending'
    | 'mq1'
    | 'mq2'
    | 'fluent'
    | 'fair'
    | 'needs_guidance';

export type QuranAssessmentSummary = PmbTestSession & {
    id: number;
    name: string;
    registration_number: string;
    study_program: string;
    assessment_date: string;
    examiner_name: string | null;
    result: QuranResult;
    average_score: number | null;
};

export type QuranAssessment = QuranAssessmentSummary & {
    quran_exam_id: number | null;
    reading_reference: string | null;
    fasohah_score: number | null;
    makhraj_score: number | null;
    tajwid_score: number | null;
    fluency_score: number | null;
    notes: string | null;
    version: string;
    created_at: string;
    updated_at: string;
};

export type QuranResultFilter = 'all' | QuranResult;

export const quranResultOptions: Array<{ value: QuranResult; label: string }> =
    [
        { value: 'pending', label: 'Menunggu penilaian MQ' },
        { value: 'mq1', label: 'MQ 1' },
        { value: 'mq2', label: 'Berhak mengikuti MQ 2' },
        { value: 'fluent', label: 'Penilaian lama: Lancar' },
        { value: 'fair', label: 'Penilaian lama: Cukup' },
        { value: 'needs_guidance', label: 'Penilaian lama: Perlu bimbingan' },
    ];

export const quranScoreFields = [
    {
        key: 'fasohah_score',
        label: 'Fasohah',
        description: 'Kefasihan dan kejelasan bacaan',
    },
    {
        key: 'tajwid_score',
        label: 'Tajwid',
        description: 'Penerapan kaidah bacaan',
    },
    {
        key: 'makhraj_score',
        label: 'Makhroj',
        description: 'Ketepatan pengucapan huruf',
    },
    {
        key: 'fluency_score',
        label: 'Kelancaran',
        description: 'Kelancaran saat membaca',
    },
] as const;

export function quranPlacement(average: number | null): QuranResult {
    return average === null ? 'pending' : average >= 70 ? 'mq2' : 'mq1';
}

export function quranResultLabel(result: QuranResult): string {
    return (
        quranResultOptions.find((option) => option.value === result)?.label ??
        'Belum tersedia'
    );
}

export function quranResultClass(result: QuranResult): string {
    switch (result) {
        case 'mq2':
        case 'fluent':
            return 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-200';
        case 'fair':
            return 'bg-sky-50 text-sky-800 dark:bg-sky-950/60 dark:text-sky-200';
        case 'mq1':
        case 'needs_guidance':
            return 'bg-amber-50 text-amber-800 dark:bg-amber-950/60 dark:text-amber-200';
        default:
            return 'bg-muted text-muted-foreground';
    }
}

export function quranScore(value: number | null): string {
    if (value === null || !Number.isFinite(value)) return '—';
    return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(
        value,
    );
}

export function quranAverage(values: Array<number | '' | null>): number | null {
    if (
        values.length !== 4 ||
        values.some(
            (value) =>
                value === '' ||
                value === null ||
                !Number.isInteger(value) ||
                value < 1 ||
                value > 100,
        )
    )
        return null;
    return (
        Math.round(
            (values.reduce<number>((sum, value) => sum + Number(value), 0) /
                4) *
                100,
        ) / 100
    );
}

export function quranDate(value: string): string {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return '—';
    const [year, month, day] = value.split('-').map(Number);
    const date = new Date(Date.UTC(year, month - 1, day));
    if (
        date.getUTCFullYear() !== year ||
        date.getUTCMonth() !== month - 1 ||
        date.getUTCDate() !== day
    )
        return '—';
    return new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(date);
}

export function quranTimestamp(value: string): string {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return (
        new Intl.DateTimeFormat('id-ID', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            timeZone: 'Asia/Jakarta',
        }).format(date) + ' WIB'
    );
}

export function newQuranSubmissionToken(): string {
    if (typeof globalThis.crypto?.randomUUID === 'function')
        return globalThis.crypto.randomUUID();
    const bytes = new Uint8Array(16);
    if (typeof globalThis.crypto?.getRandomValues === 'function') {
        globalThis.crypto.getRandomValues(bytes);
    } else {
        for (let index = 0; index < bytes.length; index++)
            bytes[index] = Math.floor(Math.random() * 256);
    }
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0'));
    return `${hex.slice(0, 4).join('')}-${hex.slice(4, 6).join('')}-${hex.slice(6, 8).join('')}-${hex.slice(8, 10).join('')}-${hex.slice(10).join('')}`;
}
