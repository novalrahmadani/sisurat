import type { PmbTestSession } from '@/lib/pmbTestSession';
export type QuranExamSummary = PmbTestSession & {
    id: number;
    title: string;
    assessment_date: string;
    is_open: boolean;
    assessments_count: number;
};
export type QuranExam = QuranExamSummary & {
    description: string | null;
    version: string;
};
