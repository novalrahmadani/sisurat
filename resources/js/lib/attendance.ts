import {
    create as createMeeting,
    index as meetingIndex,
} from '@/routes/attendance/meetings';
import {
    create as createPmbTest,
    index as pmbTestIndex,
} from '@/routes/attendance/pmb-tests';
import {
    create as createOtherEvent,
    index as otherEventIndex,
} from '@/routes/attendance/other';
import type { RouteDefinition, RouteQueryOptions } from '@/wayfinder';

export type AttendanceType = 'meeting' | 'pmb_test' | 'other';

export type AttendanceEvent = {
    id: number;
    type: AttendanceType;
    title: string;
    event_date: string;
    location: string | null;
    description: string | null;
    is_open: boolean;
    entries_count: number;
};

export function attendanceTypeLabel(type: AttendanceType): string {
    if (type === 'other') return 'Kegiatan Lainnya';
    return type === 'pmb_test' ? 'Tes PMB' : 'Rapat';
}

export function attendanceIndexRoute(
    type: AttendanceType,
    options?: RouteQueryOptions,
): RouteDefinition<'get'> {
    if (type === 'other') return otherEventIndex(options);
    return type === 'pmb_test' ? pmbTestIndex(options) : meetingIndex(options);
}

export function attendanceCreateRoute(
    type: AttendanceType,
    options?: RouteQueryOptions,
): RouteDefinition<'get'> {
    if (type === 'other') return createOtherEvent(options);
    return type === 'pmb_test'
        ? createPmbTest(options)
        : createMeeting(options);
}

export type AttendanceRecord = {
    id: number;
    name: string;
    position: string | null;
    institution: string | null;
    registration_number: string | null;
    study_program: string | null;
    school_origin: string | null;
    phone_number: string | null;
    signed_at: string;
    signature_url: string;
};

export function attendanceDate(value: string): string {
    const date = new Date(`${value.slice(0, 10)}T00:00:00`);
    if (Number.isNaN(date.getTime())) return value;
    return new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(date);
}

export function attendanceTimestamp(value: string): string {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
}

export function attendanceToday(): string {
    const date = new Date();
    return [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getDate()).padStart(2, '0'),
    ].join('-');
}
