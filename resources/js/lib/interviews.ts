import type { PmbTestSession } from '@/lib/pmbTestSession';
export type InterviewQuestion = {
    id: string;
    prompt: string;
    required: boolean;
};

export type InterviewSummary = PmbTestSession & {
    id: number;
    title: string;
    description: string | null;
    is_open: boolean;
    questions_count: number;
    submissions_count: number;
    created_at: string;
};

export type InterviewForm = Omit<
    InterviewSummary,
    'questions_count' | 'created_at'
> & {
    questions: InterviewQuestion[];
    questions_version: string;
};

export type InterviewSubmission = PmbTestSession & {
    id: number;
    name: string;
    registration_number: string;
    study_program: string;
    submitted_at: string;
};

export type InterviewAnswer = {
    question_id: string;
    question: string;
    answer: string;
    required?: boolean;
};

export function interviewTimestamp(value: string): string {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
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

export function newInterviewQuestion(prompt = ''): InterviewQuestion {
    const bytes = globalThis.crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = Array.from(bytes, (byte) =>
        byte.toString(16).padStart(2, '0'),
    ).join('');
    return {
        id: `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`,
        prompt,
        required: true,
    };
}
