<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { edit as editLetterNumbering } from '@/routes/letter-numbering';
import {
    ArrowLeft,
    ArrowRight,
    CalendarDays,
    Check,
    ChevronDown,
    FileText,
    Hash,
    Plus,
    Signature,
    Trash2,
    Users,
} from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import LetterDraftBanner from '@/components/LetterDraftBanner.vue';
import LetterSteps from '@/components/LetterSteps.vue';
import { Button } from '@/components/ui/button';
import { useLocalLetterDraft } from '@/composables/useLocalLetterDraft';
import {
    letterNumberingYear,
    nextLetterSequenceForDate,
    type YearlyLetterSequences,
} from '@/lib/letterNumbering';
import { maxLettersPerBatch, maxLettersPerSubmission } from '@/lib/letters';
import { index as lettersIndex } from '@/routes/letters';
import { store as storeGeneral } from '@/routes/letters/general';

type GeneralCategory = 'general' | 'invitation' | 'meeting' | 'other';
type WizardStep = 1 | 2 | 3;
type RecipientField =
    | 'institution_name'
    | 'recipient_position'
    | 'recipient_name'
    | 'recipient_salutation'
    | 'recipient_address';
type GeneralRecipient = Record<RecipientField, string> & { ui_key: number };
type OptionalTextField =
    | 'closing_body'
    | 'letter_title'
    | 'attachment'
    | 'opening_greeting'
    | 'closing_greeting'
    | 'event_time'
    | 'event_location'
    | 'event_agenda'
    | 'signatory_number';

const page = usePage();
const props = defineProps<{
    today: string;
    nextSequence: number;
    nextSequencesByYear: YearlyLetterSequences;
    defaults: {
        numberPrefix: string;
        signatoryTitle: string;
        signatoryName: string;
        signatoryNumber: string;
    };
    pmbSignatory: {
        id: number;
        signature_url: string | null;
        stamp_url: string | null;
    };
    sourceDraft?: {
        id: number;
        label: string;
        data: Record<string, unknown>;
    } | null;
}>();
let nextRecipientKey = 0;
function createRecipient(): GeneralRecipient {
    return {
        ui_key: nextRecipientKey++,
        institution_name: '',
        recipient_position: '',
        recipient_name: '',
        recipient_salutation: 'Kepada Yth.',
        recipient_address: 'Tempat',
    };
}
const form = useForm({
    type: 'general' as const,
    category: 'general' as GeneralCategory,
    letter_title: '',
    letter_date: props.today,
    first_sequence: nextLetterSequenceForDate(
        props.today,
        props.nextSequencesByYear,
    ),
    number_prefix: props.defaults.numberPrefix,
    subject: '',
    attachment: '-',
    opening_greeting: 'Dengan hormat,',
    body: '',
    closing_body: '',
    closing_greeting: 'Hormat kami,',
    event_date: '',
    event_time: '',
    event_location: '',
    event_agenda: '',
    signatory_title: props.defaults.signatoryTitle,
    signatory_name: props.defaults.signatoryName,
    signatory_number: props.defaults.signatoryNumber,
    include_signature: !!props.pmbSignatory.signature_url,
    include_stamp: !!props.pmbSignatory.stamp_url,
    items: [createRecipient()],
});
const inputClass = 'sisurat-input';
const labelClass = 'mb-1.5 block text-sm font-medium text-foreground';
const sectionClass = 'sisurat-panel scroll-mt-24 p-5 sm:p-7';
const headingClass = 'text-base font-semibold text-foreground sm:text-lg';
const hintClass = 'mt-1 text-sm leading-6 text-muted-foreground';
const openRecipients = ref(new Set([form.items[0].ui_key]));
const eventOpen = ref(false);
const signerOpen = ref(false);
const step = ref<WizardStep>(1);
const draftRestored = ref(false);
const requestError = ref('');
let applyingSnapshot = false;
let lastValidLetterYear = letterNumberingYear(form.letter_date);
const selectedLetterYear = computed(() =>
    letterNumberingYear(form.letter_date),
);
const suggestedSequence = computed(() =>
    nextLetterSequenceForDate(form.letter_date, props.nextSequencesByYear),
);
watch(
    () => form.letter_date,
    (date) => {
        const year = letterNumberingYear(date);
        if (year === null) return;
        const yearChanged = year !== lastValidLetterYear;
        lastValidLetterYear = year;
        if (!yearChanged || applyingSnapshot) return;
        form.first_sequence = nextLetterSequenceForDate(
            date,
            props.nextSequencesByYear,
        );
        form.clearErrors('first_sequence');
    },
    { flush: 'sync' },
);
const categories: { value: GeneralCategory; label: string; title: string }[] = [
    { value: 'general', label: 'Surat umum', title: '' },
    { value: 'invitation', label: 'Undangan', title: 'Surat Undangan' },
    { value: 'meeting', label: 'Rapat', title: 'Undangan Rapat' },
    { value: 'other', label: 'Lainnya', title: '' },
];
watch(
    () => form.category,
    (category) => {
        if (!applyingSnapshot && !form.letter_title.trim())
            form.letter_title =
                categories.find((item) => item.value === category)?.title ?? '';
    },
    { flush: 'sync' },
);
const recipientFields: {
    key: RecipientField;
    label: string;
    limit: number;
    placeholder: string;
}[] = [
    {
        key: 'recipient_salutation',
        label: 'Sapaan / Yth.',
        limit: 150,
        placeholder: 'Contoh: Kepada Yth.',
    },
    {
        key: 'institution_name',
        label: 'Tujuan / instansi',
        limit: 255,
        placeholder: 'Contoh: Seluruh dosen Universitas Nurul Huda',
    },
    {
        key: 'recipient_position',
        label: 'Jabatan penerima',
        limit: 150,
        placeholder: 'Contoh: Ketua Program Studi',
    },
    {
        key: 'recipient_name',
        label: 'Nama penerima',
        limit: 150,
        placeholder: 'Contoh: Bapak Ahmad, M.Pd.',
    },
    {
        key: 'recipient_address',
        label: 'Alamat / tempat tujuan',
        limit: 500,
        placeholder: 'Tempat, atau tulis alamat tanpa kata di',
    },
];
const eventFields: {
    key: 'event_time' | 'event_location' | 'event_agenda';
    label: string;
    limit: number;
    placeholder: string;
}[] = [
    {
        key: 'event_time',
        label: 'Waktu',
        limit: 120,
        placeholder: 'Contoh: 09.00 WIB – selesai',
    },
    {
        key: 'event_location',
        label: 'Tempat kegiatan',
        limit: 500,
        placeholder: 'Contoh: Ruang Rapat Rektorat',
    },
    {
        key: 'event_agenda',
        label: 'Agenda',
        limit: 1000,
        placeholder: 'Contoh: Koordinasi persiapan penerimaan mahasiswa baru',
    },
];
const signerFields: {
    key: 'signatory_title' | 'signatory_name' | 'signatory_number';
    label: string;
    limit: number;
    required: boolean;
}[] = [
    {
        key: 'signatory_title',
        label: 'Jabatan penandatangan',
        limit: 150,
        required: true,
    },
    {
        key: 'signatory_name',
        label: 'Nama penandatangan',
        limit: 150,
        required: true,
    },
    {
        key: 'signatory_number',
        label: 'NIP / identitas (opsional)',
        limit: 100,
        required: false,
    },
];
const completedItems = computed(
    () =>
        form.items.filter(
            (item) =>
                item.institution_name.trim() || item.recipient_name.trim(),
        ).length,
);
const finalSequence = computed(
    () => Number(form.first_sequence) + form.items.length - 1,
);
const validSequenceRange = computed(
    () =>
        Number.isInteger(Number(form.first_sequence)) &&
        Number(form.first_sequence) >= 1 &&
        form.items.length >= 1 &&
        finalSequence.value <= 99999,
);
const romanMonths = [
    'I',
    'II',
    'III',
    'IV',
    'V',
    'VI',
    'VII',
    'VIII',
    'IX',
    'X',
    'XI',
    'XII',
];
function validDate(value: string): boolean {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
    if (!match) return false;
    const year = Number(match[1]);
    const month = Number(match[2]);
    const day = Number(match[3]);
    if (year < 1000 || month < 1 || month > 12 || day < 1) return false;
    return day <= new Date(year, month, 0).getDate();
}
function previewNumber(sequence: number): string {
    const code = form.number_prefix.trim();
    if (
        !validSequenceRange.value ||
        !code ||
        code.length > 120 ||
        !validDate(form.letter_date)
    )
        return '';
    const [year, month] = form.letter_date.split('-');
    return `${String(sequence).padStart(3, '0')}/${code}/${romanMonths[Number(month) - 1]}/${year}`;
}
const firstNumber = computed(() => previewNumber(Number(form.first_sequence)));
const lastNumber = computed(() => previewNumber(finalSequence.value));
const categoryLabel = computed(
    () =>
        categories.find((item) => item.value === form.category)?.label ??
        'Surat umum',
);
const eventIsFilled = computed(() =>
    [
        form.event_date,
        form.event_time,
        form.event_location,
        form.event_agenda,
    ].some((value) => value.trim()),
);
const matchesSignatureOwner = computed(() => {
    const normalize = (value: string) =>
        value.toLowerCase().replace(/[^a-z0-9]/g, '');
    return (
        Boolean(props.defaults.signatoryName.trim()) &&
        normalize(form.signatory_name) ===
            normalize(props.defaults.signatoryName)
    );
});
watch(
    matchesSignatureOwner,
    (matches) => {
        if (applyingSnapshot) return;
        form.include_signature = matches && !!props.pmbSignatory.signature_url;
        form.include_stamp = matches && !!props.pmbSignatory.stamp_url;
    },
    { immediate: true, flush: 'sync' },
);
const errorMessages = computed<Record<string, string>>(() =>
    Object.fromEntries(
        Object.entries(form.errors).map(([key, message]) => [
            key,
            Array.isArray(message) ? message.join(' ') : String(message),
        ]),
    ),
);
const visibleErrors = computed(() =>
    Object.entries(errorMessages.value).slice(0, 10),
);
function recipientError(index: number, field: RecipientField): string {
    return errorMessages.value[`items.${index}.${field}`] ?? '';
}
function recipientHasErrors(index: number): boolean {
    return Object.keys(errorMessages.value).some((key) =>
        key.startsWith(`items.${index}.`),
    );
}
function clearRecipientErrors(): void {
    for (const key of Object.keys(form.errors))
        if (key === 'items' || key.startsWith('items.'))
            form.clearErrors(key as keyof typeof form.errors);
}
function addRecipient(): void {
    if (form.processing || form.items.length >= maxLettersPerSubmission) return;
    const item = createRecipient();
    form.items.push(item);
    openRecipients.value.add(item.ui_key);
    void nextTick(() =>
        document
            .getElementById(`recipient-${item.ui_key}-institution_name`)
            ?.focus(),
    );
}
function removeRecipient(key: number): void {
    if (form.processing) return;
    form.items = form.items.filter((item) => item.ui_key !== key);
    openRecipients.value.delete(key);
    if (!form.items.length) {
        const item = createRecipient();
        form.items = [item];
        openRecipients.value.add(item.ui_key);
    }
    clearRecipientErrors();
}
function trackRecipientToggle(key: number, event: Event): void {
    const details = event.target;
    if (!(details instanceof HTMLDetailsElement)) return;
    if (details.open) openRecipients.value.add(key);
    else openRecipients.value.delete(key);
}
function fieldStep(key: string): WizardStep {
    if (key.startsWith('signatory_') || key.startsWith('include_')) return 3;
    if (
        [
            'category',
            'letter_title',
            'subject',
            'attachment',
            'letter_date',
            'first_sequence',
            'number_prefix',
        ].includes(key)
    )
        return 1;
    return 2;
}
function focusError(key: string): void {
    step.value = fieldStep(key);
    const match = /^items\.(\d+)(?:\.(.+))?$/.exec(key);
    if (match) {
        const item = form.items[Number(match[1])];
        if (!item) return;
        openRecipients.value.add(item.ui_key);
        void nextTick(() => {
            const field =
                document.getElementById(
                    `recipient-${item.ui_key}-${match[2] ?? 'institution_name'}`,
                ) ??
                document.getElementById(
                    `recipient-${item.ui_key}-institution_name`,
                );
            field?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            field?.focus();
        });
        return;
    }
    if (key.startsWith('signatory_') || key.startsWith('include_'))
        signerOpen.value = true;
    if (key.startsWith('event_')) eventOpen.value = true;
    void nextTick(() => {
        const target = document.getElementById(
            key === 'items' ? 'general-recipients' : `general-${key}`,
        );
        target?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        target?.focus();
    });
}
function revealErrors(): void {
    for (const key of Object.keys(form.errors)) {
        const match = /^items\.(\d+)(?:\.|$)/.exec(key);
        if (match) {
            const item = form.items[Number(match[1])];
            if (item) openRecipients.value.add(item.ui_key);
        }
        if (key.startsWith('signatory_') || key.startsWith('include_'))
            signerOpen.value = true;
        if (key.startsWith('event_')) eventOpen.value = true;
    }
    const firstError = Object.keys(form.errors)[0];
    if (firstError) focusError(firstError);
}
function validateForm(upToStep: WizardStep = 3): boolean {
    const errors: Record<string, string> = {};
    const requiredFields: {
        key:
            | 'subject'
            | 'body'
            | 'number_prefix'
            | 'signatory_title'
            | 'signatory_name';
        label: string;
        limit: number;
    }[] = [
        { key: 'subject', label: 'Perihal', limit: 255 },
        { key: 'body', label: 'Pengantar surat', limit: 20000 },
        { key: 'number_prefix', label: 'Kode surat', limit: 120 },
        { key: 'signatory_title', label: 'Jabatan penandatangan', limit: 150 },
        { key: 'signatory_name', label: 'Nama penandatangan', limit: 150 },
    ];
    for (const field of requiredFields) {
        if (!form[field.key].trim())
            errors[field.key] = `${field.label} wajib diisi.`;
        else if (form[field.key].length > field.limit)
            errors[field.key] =
                `${field.label} maksimal ${field.limit.toLocaleString('id-ID')} karakter.`;
    }
    const optionalFields: {
        key: OptionalTextField;
        label: string;
        limit: number;
    }[] = [
        { key: 'letter_title', label: 'Judul surat', limit: 255 },
        { key: 'attachment', label: 'Lampiran', limit: 120 },
        { key: 'closing_body', label: 'Isi penutup surat', limit: 20000 },
        { key: 'opening_greeting', label: 'Salam pembuka', limit: 255 },
        { key: 'closing_greeting', label: 'Salam penutup', limit: 255 },
        { key: 'signatory_number', label: 'NIP / identitas', limit: 100 },
        ...eventFields,
    ];
    for (const field of optionalFields)
        if (form[field.key].length > field.limit)
            errors[field.key] =
                `${field.label} maksimal ${field.limit.toLocaleString('id-ID')} karakter.`;
    if (!validDate(form.letter_date))
        errors.letter_date = 'Isi tanggal surat yang valid.';
    if (form.event_date && !validDate(form.event_date))
        errors.event_date = 'Isi tanggal kegiatan yang valid atau kosongkan.';
    if (!validSequenceRange.value)
        errors.first_sequence =
            'Isi nomor awal 1–99999 dengan rentang seluruh surat tidak melebihi 99999.';
    if (!categories.some((item) => item.value === form.category))
        errors.category = 'Pilih jenis surat yang tersedia.';
    if (!form.items.length || form.items.length > maxLettersPerSubmission)
        errors.items = `Isi 1–${maxLettersPerSubmission.toLocaleString('id-ID')} penerima.`;
    form.items.forEach((item, index) => {
        if (!item.institution_name.trim() && !item.recipient_name.trim())
            errors[`items.${index}.institution_name`] =
                `Penerima ${index + 1}: isi tujuan / instansi atau nama penerima.`;
        for (const field of recipientFields)
            if (item[field.key].length > field.limit)
                errors[`items.${index}.${field.key}`] =
                    `Penerima ${index + 1}: ${field.label} maksimal ${field.limit} karakter.`;
    });
    for (const key of Object.keys(errors)) {
        if (fieldStep(key) > upToStep) delete errors[key];
    }
    form.clearErrors();
    if (!Object.keys(errors).length) return true;
    form.setError(errors as typeof form.errors);
    revealErrors();
    return false;
}
function changeStep(target: WizardStep): void {
    if (form.processing || target === step.value) return;
    if (target > step.value && !validateForm((target - 1) as WizardStep))
        return;
    step.value = target;
    void nextTick(() => {
        const heading = document.getElementById(`general-step-${target}`);
        heading?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        heading?.focus({ preventScroll: true });
    });
}
function formatDate(value: string): string {
    if (!validDate(value)) return value || 'Belum diisi';
    return new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }).format(new Date(`${value}T00:00:00`));
}
const draftTextFields = {
    letter_title: 255,
    number_prefix: 120,
    subject: 255,
    attachment: 120,
    opening_greeting: 255,
    body: 20000,
    closing_body: 20000,
    closing_greeting: 255,
    event_date: 10,
    event_time: 120,
    event_location: 500,
    event_agenda: 1000,
    signatory_title: 150,
    signatory_name: 150,
    signatory_number: 100,
} as const;
function currentDraftData() {
    const { items, ...shared } = form.data();
    return {
        version: 1,
        ...shared,
        items: items.map(({ ui_key: _key, ...item }) => item),
    };
}
function isRecord(value: unknown): value is Record<string, unknown> {
    return Boolean(value) && typeof value === 'object' && !Array.isArray(value);
}
function applyDraftData(value: unknown, fromSource = false): void {
    if (
        !isRecord(value) ||
        (!fromSource && value.version !== 1) ||
        value.type !== 'general' ||
        !categories.some((category) => category.value === value.category) ||
        !Array.isArray(value.items) ||
        value.items.length < 1 ||
        value.items.length > maxLettersPerSubmission
    )
        throw new Error(
            'Format draf surat umum tidak sesuai. Anda dapat menghapus draf ini dan mengisi formulir kembali.',
        );
    const textValues = {} as Record<keyof typeof draftTextFields, string>;
    for (const [key, limit] of Object.entries(draftTextFields)) {
        const text = value[key];
        if (typeof text !== 'string' || text.length > limit)
            throw new Error(
                'Draf berisi isian yang tidak sesuai batas formulir. Hapus draf ini untuk memulai kembali.',
            );
        textValues[key as keyof typeof draftTextFields] = text;
    }
    if (
        typeof value.include_signature !== 'boolean' ||
        typeof value.include_stamp !== 'boolean' ||
        (!fromSource &&
            (typeof value.letter_date !== 'string' ||
                value.letter_date.length > 10))
    )
        throw new Error(
            'Pengaturan draf tidak sesuai. Hapus draf ini untuk memulai kembali.',
        );
    const items = value.items.map((raw) => {
        if (!isRecord(raw))
            throw new Error('Data penerima dalam draf tidak sesuai.');
        const item = createRecipient();
        for (const field of recipientFields) {
            const text = raw[field.key];
            if (typeof text !== 'string' || text.length > field.limit)
                throw new Error(
                    'Data penerima dalam draf tidak sesuai batas formulir.',
                );
            item[field.key] = text;
        }
        return item;
    });
    const restoredDate = fromSource
        ? props.today
        : (value.letter_date as string);
    applyingSnapshot = true;
    Object.assign(form, textValues, {
        category: value.category,
        letter_date: restoredDate,
        first_sequence: nextLetterSequenceForDate(
            restoredDate,
            props.nextSequencesByYear,
        ),
        items,
        include_signature: value.include_signature,
        include_stamp: value.include_stamp,
    });
    if (!matchesSignatureOwner.value) {
        form.include_signature = false;
        form.include_stamp = false;
    }
    applyingSnapshot = false;
    openRecipients.value = new Set([items[0].ui_key]);
    eventOpen.value = eventIsFilled.value;
    signerOpen.value = false;
    form.clearErrors();
    requestError.value = '';
    step.value = 1;
    draftRestored.value = !fromSource;
}
const emptyDraftData = JSON.stringify(currentDraftData());
const draft = useLocalLetterDraft({
    key: 'general',
    getData: currentDraftData,
    applyData: (value: unknown) => applyDraftData(value),
    isMeaningful: () => JSON.stringify(currentDraftData()) !== emptyDraftData,
});
const sourceError = ref('');
watch(
    () => props.sourceDraft?.id,
    () => {
        const source = props.sourceDraft;
        if (!source) return;
        draft.pause();
        try {
            applyDraftData(source.data, true);
            sourceError.value = '';
            if (!draft.needsDecision.value) draft.resume();
        } catch (error) {
            sourceError.value =
                error instanceof Error
                    ? error.message
                    : 'Data surat sumber belum dapat disalin.';
        }
    },
    { immediate: true },
);
function saveSourceDraft(): void {
    if (form.processing) return;
    if (draft.needsDecision.value && !draft.discardDraft()) return;
    draft.resume();
    draft.saveDraft();
}
function submit(): void {
    if (form.processing) return;
    if (step.value < 3) {
        changeStep((step.value + 1) as WizardStep);
        return;
    }
    if (!validateForm()) return;
    requestError.value = '';
    form.transform((data) => ({
        type: data.type,
        category: data.category,
        letter_title: data.letter_title.trim(),
        letter_date: data.letter_date,
        first_sequence: Number(data.first_sequence),
        number_prefix: data.number_prefix.trim(),
        subject: data.subject.trim(),
        attachment: data.attachment.trim(),
        opening_greeting: data.opening_greeting.trim(),
        body: data.body,
        closing_body: data.closing_body,
        closing_greeting: data.closing_greeting.trim(),
        event_date: data.event_date,
        event_time: data.event_time.trim(),
        event_location: data.event_location.trim(),
        event_agenda: data.event_agenda.trim(),
        signatory_title: data.signatory_title.trim(),
        signatory_name: data.signatory_name.trim(),
        signatory_number: data.signatory_number.trim(),
        include_signature:
            data.include_signature && matchesSignatureOwner.value,
        include_stamp: data.include_stamp && matchesSignatureOwner.value,
        items: data.items.map((item) => ({
            institution_name: item.institution_name.trim(),
            recipient_position: item.recipient_position.trim(),
            recipient_name: item.recipient_name.trim(),
            recipient_salutation: item.recipient_salutation.trim(),
            recipient_address: item.recipient_address.trim(),
        })),
    })).post(storeGeneral.url(), {
        preserveScroll: true,
        onError: revealErrors,
        onSuccess: () => {
            if (!draft.needsDecision.value) draft.clearDraft();
        },
        onNetworkError: () => {
            requestError.value =
                'Surat belum tersimpan. Periksa koneksi lalu coba kembali; isian Anda tetap tersedia.';
            return false;
        },
        onHttpException: () => {
            requestError.value =
                'Surat belum tersimpan. Server mengalami kendala; coba kembali setelah beberapa saat.';
            return false;
        },
    });
}
</script>

<template>
    <main class="sisurat-page pb-28">
        <Head title="Surat Umum" />
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
            <header class="flex flex-wrap items-start justify-between gap-4">
                <div class="max-w-3xl">
                    <p class="sisurat-kicker">Buat surat</p>
                    <h1 class="sisurat-title mt-1">Surat Umum</h1>
                    <p class="sisurat-subtitle mt-2">
                        Buat surat rapat, undangan, atau keperluan lainnya. Atur
                        nomor, judul, isi, dan penerima sesuai kebutuhan.
                    </p>
                </div>
                <Link
                    :href="lettersIndex()"
                    class="sisurat-secondary-action shrink-0 px-3"
                    ><ArrowLeft class="size-4" aria-hidden="true" />Arsip
                    Surat</Link
                >
            </header>
            <LetterSteps
                :model-value="step"
                :busy="form.processing"
                @update:model-value="changeStep"
            />
            <div
                v-if="sourceDraft"
                class="rounded-2xl border border-primary/20 bg-primary/5 p-4 sm:p-5"
            >
                <p class="text-sm font-semibold">
                    Salinan dari {{ sourceDraft.label }}
                </p>
                <p class="mt-1 text-sm leading-6 text-muted-foreground">
                    Isi dan penerima disalin. Nomor awal mengikuti nomor terbaru
                    dan tanggal surat memakai hari ini. Periksa kembali tanggal
                    kegiatan, isi, penerima, dan penandatangan.
                </p>
                <p
                    v-if="draft.needsDecision.value"
                    class="mt-2 text-xs text-muted-foreground"
                >
                    Draf sebelumnya tetap tersimpan di browser sampai Anda
                    memilih menggantinya.
                </p>
                <button
                    type="button"
                    class="sisurat-secondary-action mt-3 text-xs!"
                    :disabled="form.processing || Boolean(sourceError)"
                    @click="saveSourceDraft"
                >
                    {{
                        draft.needsDecision.value
                            ? 'Ganti draf tersimpan dengan salinan ini'
                            : 'Simpan draf salinan'
                    }}
                </button>
                <p
                    v-if="draft.error.value"
                    role="alert"
                    class="mt-2 text-sm text-destructive"
                >
                    {{ draft.error.value }}
                </p>
            </div>
            <LetterDraftBanner
                v-else
                :has-saved-draft="draft.hasSavedDraft.value"
                :needs-decision="draft.needsDecision.value"
                :timestamp="draft.timestamp.value"
                :error="draft.error.value"
                :available="draft.available.value"
                :disabled="form.processing"
                @restore="draft.restoreDraft"
                @discard="draft.discardDraft"
                @save="draft.saveDraft"
            />
            <p
                v-if="draftRestored"
                class="rounded-xl bg-accent/60 px-4 py-3 text-sm leading-6 text-muted-foreground"
            >
                Draf dilanjutkan. Tanggal surat dan tanggal kegiatan tetap
                mengikuti draf; nomor awal diperbarui mengikuti nomor terbaru
                pada tahun tanggal surat dalam draf. Periksa kembali sebelum
                menyimpan.
            </p>
            <p
                v-if="sourceError"
                role="alert"
                class="rounded-xl border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive"
            >
                {{ sourceError }}
            </p>
            <form @submit.prevent="submit" novalidate>
                <fieldset
                    :disabled="form.processing"
                    class="flex min-w-0 flex-col gap-6"
                >
                    <div v-show="step === 1" class="space-y-6">
                        <h2
                            id="general-step-1"
                            tabindex="-1"
                            class="scroll-mt-24 text-lg font-semibold"
                        >
                            1. Jenis &amp; pengaturan
                        </h2>
                        <section
                            id="general-settings"
                            :class="sectionClass"
                            aria-labelledby="general-settings-heading"
                        >
                            <div class="flex items-start gap-3">
                                <span class="sisurat-icon-tile"
                                    ><FileText
                                        class="size-6"
                                        aria-hidden="true"
                                /></span>
                                <div>
                                    <h2
                                        id="general-settings-heading"
                                        :class="headingClass"
                                    >
                                        Jenis, judul, dan perihal
                                    </h2>
                                    <p :class="hintClass">
                                        Judul dan perihal digunakan bersama
                                        untuk semua penerima.
                                    </p>
                                </div>
                            </div>
                            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label
                                        for="general-category"
                                        :class="labelClass"
                                        >Jenis surat</label
                                    >
                                    <select
                                        id="general-category"
                                        v-model="form.category"
                                        :class="inputClass"
                                        :aria-invalid="
                                            Boolean(form.errors.category)
                                        "
                                    >
                                        <option
                                            v-for="category in categories"
                                            :key="category.value"
                                            :value="category.value"
                                        >
                                            {{ category.label }}
                                        </option>
                                    </select>
                                    <p
                                        v-if="form.errors.category"
                                        class="mt-1 text-xs text-destructive"
                                    >
                                        {{ form.errors.category }}
                                    </p>
                                </div>
                                <div>
                                    <label
                                        for="general-letter_title"
                                        :class="labelClass"
                                        >Judul surat (opsional)</label
                                    >
                                    <input
                                        id="general-letter_title"
                                        v-model="form.letter_title"
                                        type="text"
                                        :class="inputClass"
                                        :aria-invalid="
                                            Boolean(form.errors.letter_title)
                                        "
                                        maxlength="255"
                                        placeholder="Contoh: Undangan Rapat Koordinasi"
                                    />
                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        Ditampilkan di bagian atas surat.
                                        Kosongkan jika tidak diperlukan.
                                    </p>
                                    <p
                                        v-if="form.errors.letter_title"
                                        class="mt-1 text-xs text-destructive"
                                    >
                                        {{ form.errors.letter_title }}
                                    </p>
                                </div>
                                <div>
                                    <label
                                        for="general-subject"
                                        :class="labelClass"
                                        >Perihal
                                        <span class="text-destructive"
                                            >*</span
                                        ></label
                                    >
                                    <input
                                        id="general-subject"
                                        v-model="form.subject"
                                        type="text"
                                        :class="inputClass"
                                        :aria-invalid="
                                            Boolean(form.errors.subject)
                                        "
                                        required
                                        maxlength="255"
                                        placeholder="Contoh: Undangan rapat koordinasi PMB"
                                    />
                                    <p
                                        v-if="form.errors.subject"
                                        class="mt-1 text-xs text-destructive"
                                    >
                                        {{ form.errors.subject }}
                                    </p>
                                </div>
                                <div>
                                    <label
                                        for="general-attachment"
                                        :class="labelClass"
                                        >Lampiran (opsional)</label
                                    >
                                    <input
                                        id="general-attachment"
                                        v-model="form.attachment"
                                        type="text"
                                        :class="inputClass"
                                        :aria-invalid="
                                            Boolean(form.errors.attachment)
                                        "
                                        maxlength="120"
                                        placeholder="Contoh: 1 berkas, atau -"
                                    />
                                    <p
                                        v-if="form.errors.attachment"
                                        class="mt-1 text-xs text-destructive"
                                    >
                                        {{ form.errors.attachment }}
                                    </p>
                                </div>
                            </div>
                        </section>
                        <section
                            id="general-numbering"
                            :class="sectionClass"
                            aria-labelledby="general-numbering-heading"
                        >
                            <div class="flex items-start gap-3">
                                <span class="sisurat-icon-tile"
                                    ><Hash class="size-6" aria-hidden="true"
                                /></span>
                                <div>
                                    <h2
                                        id="general-numbering-heading"
                                        :class="headingClass"
                                    >
                                        Nomor dan tanggal surat
                                    </h2>
                                    <p :class="hintClass">
                                        Setiap penerima mendapat satu surat
                                        dengan nomor berurutan. Penomoran
                                        dihitung per tahun sesuai tanggal surat;
                                        tahun yang belum memiliki surat dimulai
                                        dari 001.
                                    </p>
                                </div>
                            </div>
                            <div class="mt-5 grid gap-4 sm:grid-cols-3">
                                <div>
                                    <label
                                        for="general-letter_date"
                                        :class="labelClass"
                                        >Tanggal surat
                                        <span class="text-destructive"
                                            >*</span
                                        ></label
                                    >
                                    <input
                                        id="general-letter_date"
                                        v-model="form.letter_date"
                                        type="date"
                                        :class="inputClass"
                                        :aria-invalid="
                                            Boolean(form.errors.letter_date)
                                        "
                                        required
                                    />
                                    <p
                                        v-if="form.errors.letter_date"
                                        class="mt-1 text-xs text-destructive"
                                    >
                                        {{ form.errors.letter_date }}
                                    </p>
                                </div>
                                <div>
                                    <label
                                        for="general-first_sequence"
                                        :class="labelClass"
                                        >Nomor urut awal
                                        <span class="text-destructive"
                                            >*</span
                                        ></label
                                    >
                                    <input
                                        id="general-first_sequence"
                                        v-model.number="form.first_sequence"
                                        type="number"
                                        min="1"
                                        max="99999"
                                        step="1"
                                        :class="inputClass"
                                        :aria-invalid="
                                            Boolean(form.errors.first_sequence)
                                        "
                                        required
                                    />
                                    <p
                                        v-if="selectedLetterYear !== null"
                                        class="mt-2 text-xs leading-6 text-muted-foreground"
                                    >
                                        Nomor berikutnya tahun
                                        {{ selectedLetterYear }}:
                                        <strong
                                            class="font-medium text-foreground"
                                            >{{
                                                String(
                                                    suggestedSequence,
                                                ).padStart(3, '0')
                                            }}</strong
                                        >. Saat tahun tanggal surat diubah,
                                        nomor awal mengikuti penomoran tahun
                                        tersebut.
                                    </p>
                                    <p
                                        v-if="form.errors.first_sequence"
                                        class="mt-1 text-xs text-destructive"
                                    >
                                        {{ form.errors.first_sequence }}
                                    </p>
                                    <Link
                                        v-if="
                                            page.props.can
                                                ?.manage_letter_numbering
                                        "
                                        :href="editLetterNumbering()"
                                        class="mt-2 inline-flex text-xs font-medium text-primary hover:underline"
                                        >Atur nomor berikutnya dari buku agenda
                                        →</Link
                                    >
                                </div>
                                <div>
                                    <label
                                        for="general-number_prefix"
                                        :class="labelClass"
                                        >Kode surat
                                        <span class="text-destructive"
                                            >*</span
                                        ></label
                                    >
                                    <input
                                        id="general-number_prefix"
                                        v-model="form.number_prefix"
                                        type="text"
                                        :class="inputClass"
                                        :aria-invalid="
                                            Boolean(form.errors.number_prefix)
                                        "
                                        required
                                        maxlength="120"
                                        placeholder="Contoh: PPMBPM-UNUHA/TU.2.1"
                                    />
                                    <p
                                        v-if="form.errors.number_prefix"
                                        class="mt-1 text-xs text-destructive"
                                    >
                                        {{ form.errors.number_prefix }}
                                    </p>
                                </div>
                            </div>
                            <div
                                class="mt-4 rounded-xl border border-primary/15 bg-accent/70 px-4 py-3 text-sm"
                            >
                                <p class="font-medium">Nomor surat pertama</p>
                                <p
                                    class="mt-1 font-semibold break-all text-primary dark:text-foreground"
                                    translate="no"
                                >
                                    {{
                                        firstNumber ||
                                        'Lengkapi nomor, kode, dan tanggal surat.'
                                    }}
                                </p>
                                <p
                                    v-if="firstNumber && form.items.length > 1"
                                    class="mt-1 text-xs break-all text-muted-foreground"
                                    translate="no"
                                >
                                    Sampai {{ lastNumber }}
                                </p>
                            </div>
                        </section>
                    </div>
                    <div v-show="step === 2" class="space-y-6">
                        <h2
                            id="general-step-2"
                            tabindex="-1"
                            class="scroll-mt-24 text-lg font-semibold"
                        >
                            2. Isi data
                        </h2>
                        <section
                            id="general-recipients"
                            :class="sectionClass"
                            aria-labelledby="general-recipients-heading"
                            tabindex="-1"
                        >
                            <div
                                class="flex flex-wrap items-start justify-between gap-4"
                            >
                                <div class="flex items-start gap-3">
                                    <span class="sisurat-icon-tile"
                                        ><Users
                                            class="size-6"
                                            aria-hidden="true"
                                    /></span>
                                    <div>
                                        <h2
                                            id="general-recipients-heading"
                                            :class="headingClass"
                                        >
                                            Tujuan dan penerima
                                        </h2>
                                        <p :class="hintClass">
                                            Isi tujuan / instansi atau nama
                                            penerima. Jabatan dan sapaan bisa
                                            disesuaikan.
                                        </p>
                                    </div>
                                </div>
                                <span
                                    class="rounded-full bg-primary/10 px-3 py-1.5 text-xs font-semibold text-primary dark:text-foreground"
                                    >{{ completedItems }} /
                                    {{ form.items.length }} lengkap</span
                                >
                            </div>
                            <p
                                v-if="form.errors.items"
                                class="mt-3 text-sm text-destructive"
                            >
                                {{ form.errors.items }}
                            </p>
                            <div class="mt-5 flex flex-col gap-3">
                                <div
                                    v-for="(item, index) in form.items"
                                    :key="item.ui_key"
                                    class="flex min-w-0 items-start gap-2 rounded-xl border bg-background p-2 sm:gap-3 sm:p-3"
                                    :class="
                                        recipientHasErrors(index)
                                            ? 'border-destructive/50'
                                            : 'border-border/70'
                                    "
                                >
                                    <details
                                        :id="`recipient-${item.ui_key}`"
                                        :open="openRecipients.has(item.ui_key)"
                                        class="group min-w-0 flex-1"
                                        @toggle="
                                            trackRecipientToggle(
                                                item.ui_key,
                                                $event,
                                            )
                                        "
                                    >
                                        <summary
                                            class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 px-2"
                                        >
                                            <span class="min-w-0"
                                                ><span
                                                    class="block text-xs font-medium text-muted-foreground"
                                                    >Penerima
                                                    {{ index + 1 }}</span
                                                ><span
                                                    class="mt-0.5 block truncate text-sm font-semibold"
                                                    >{{
                                                        item.institution_name.trim() ||
                                                        item.recipient_name.trim() ||
                                                        'Lengkapi tujuan atau nama penerima'
                                                    }}</span
                                                ></span
                                            ><ChevronDown
                                                class="size-4 shrink-0 text-muted-foreground transition-transform group-open:rotate-180"
                                                aria-hidden="true"
                                            />
                                        </summary>
                                        <div
                                            class="grid gap-4 px-2 pt-4 pb-2 sm:grid-cols-2"
                                        >
                                            <div
                                                v-for="field in recipientFields"
                                                :key="field.key"
                                                :class="{
                                                    'sm:col-span-2':
                                                        field.key ===
                                                        'recipient_address',
                                                }"
                                            >
                                                <label
                                                    :for="`recipient-${item.ui_key}-${field.key}`"
                                                    :class="labelClass"
                                                    >{{ field.label }}</label
                                                >
                                                <textarea
                                                    v-if="
                                                        field.key ===
                                                        'recipient_address'
                                                    "
                                                    :id="`recipient-${item.ui_key}-${field.key}`"
                                                    v-model="item[field.key]"
                                                    :class="inputClass"
                                                    :aria-invalid="
                                                        Boolean(
                                                            recipientError(
                                                                index,
                                                                field.key,
                                                            ),
                                                        )
                                                    "
                                                    :maxlength="field.limit"
                                                    :placeholder="
                                                        field.placeholder
                                                    "
                                                    rows="2"
                                                />
                                                <input
                                                    v-else
                                                    :id="`recipient-${item.ui_key}-${field.key}`"
                                                    v-model="item[field.key]"
                                                    type="text"
                                                    :class="inputClass"
                                                    :aria-invalid="
                                                        Boolean(
                                                            recipientError(
                                                                index,
                                                                field.key,
                                                            ),
                                                        )
                                                    "
                                                    :maxlength="field.limit"
                                                    :placeholder="
                                                        field.placeholder
                                                    "
                                                />
                                                <p
                                                    v-if="
                                                        recipientError(
                                                            index,
                                                            field.key,
                                                        )
                                                    "
                                                    class="mt-1 text-xs text-destructive"
                                                >
                                                    {{
                                                        recipientError(
                                                            index,
                                                            field.key,
                                                        )
                                                    }}
                                                </p>
                                            </div>
                                            <p
                                                class="text-xs leading-6 text-muted-foreground sm:col-span-2"
                                            >
                                                Tujuan dan nama penerima boleh
                                                diisi bersama. Isi alamat tanpa
                                                kata “di”, karena kata tersebut
                                                ditambahkan otomatis. Gunakan
                                                “Tempat” jika tidak perlu alamat
                                                lengkap.
                                            </p>
                                        </div>
                                    </details>
                                    <button
                                        type="button"
                                        class="mt-1 flex size-10 shrink-0 items-center justify-center rounded-lg text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                        :aria-label="`Hapus penerima ${index + 1}`"
                                        @click="removeRecipient(item.ui_key)"
                                    >
                                        <Trash2
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                    </button>
                                </div>
                            </div>
                            <div class="mt-4 flex flex-wrap items-center gap-3">
                                <button
                                    type="button"
                                    class="sisurat-secondary-action min-h-11"
                                    :disabled="
                                        form.items.length >=
                                        maxLettersPerSubmission
                                    "
                                    @click="addRecipient"
                                >
                                    <Plus
                                        class="size-4"
                                        aria-hidden="true"
                                    />Tambah penerima
                                </button>
                                <p class="text-xs text-muted-foreground">
                                    Satu penerima = satu surat. Maksimal
                                    {{
                                        maxLettersPerSubmission.toLocaleString(
                                            'id-ID',
                                        )
                                    }}
                                    surat sekali simpan.
                                </p>
                            </div>
                        </section>
                        <section
                            id="general-content"
                            :class="sectionClass"
                            aria-labelledby="general-content-heading"
                        >
                            <div class="flex items-start gap-3">
                                <span class="sisurat-icon-tile"
                                    ><FileText
                                        class="size-6"
                                        aria-hidden="true"
                                /></span>
                                <div>
                                    <h2
                                        id="general-content-heading"
                                        :class="headingClass"
                                    >
                                        Isi surat
                                    </h2>
                                    <p :class="hintClass">
                                        Kop, nomor, penerima, dan tanda tangan
                                        ditata otomatis. Isi pengantar, detail
                                        kegiatan, dan penutup sesuai kebutuhan.
                                    </p>
                                </div>
                            </div>
                            <div class="mt-5">
                                <label
                                    for="general-opening_greeting"
                                    :class="labelClass"
                                    >Salam pembuka (opsional)</label
                                >
                                <input
                                    id="general-opening_greeting"
                                    v-model="form.opening_greeting"
                                    type="text"
                                    :class="inputClass"
                                    :aria-invalid="
                                        Boolean(form.errors.opening_greeting)
                                    "
                                    maxlength="255"
                                    placeholder="Contoh: Dengan hormat,"
                                />
                                <p
                                    v-if="form.errors.opening_greeting"
                                    class="mt-1 text-xs text-destructive"
                                >
                                    {{ form.errors.opening_greeting }}
                                </p>
                            </div>
                            <div class="mt-5">
                                <label for="general-body" :class="labelClass"
                                    >Isi surat 1 — Pengantar
                                    <span class="text-destructive"
                                        >*</span
                                    ></label
                                >
                                <textarea
                                    id="general-body"
                                    v-model="form.body"
                                    :class="inputClass"
                                    class="min-h-64 resize-y leading-7"
                                    :aria-invalid="Boolean(form.errors.body)"
                                    aria-describedby="general-body-hint"
                                    required
                                    maxlength="20000"
                                    rows="10"
                                    placeholder="Tulis pengantar dan maksud surat. Contoh: Dengan ini kami mengundang Bapak/Ibu untuk menghadiri rapat koordinasi."
                                />
                                <div
                                    class="mt-2 flex flex-wrap justify-between gap-2 text-xs leading-6 text-muted-foreground"
                                >
                                    <p id="general-body-hint" class="max-w-2xl">
                                        Bagian ini menjadi pengantar surat.
                                        Detail kegiatan, jika diisi, ditampilkan
                                        setelah pengantar dan sebelum isi
                                        penutup.
                                    </p>
                                    <span class="shrink-0 tabular-nums"
                                        >{{
                                            form.body.length.toLocaleString(
                                                'id-ID',
                                            )
                                        }}
                                        / 20.000 karakter</span
                                    >
                                </div>
                                <p
                                    v-if="form.errors.body"
                                    class="mt-1 text-xs text-destructive"
                                >
                                    {{ form.errors.body }}
                                </p>
                            </div>
                            <details
                                id="general-event"
                                :open="eventOpen"
                                class="group mt-5 rounded-xl border border-border/70 p-4"
                                @toggle="
                                    eventOpen = (
                                        $event.target as HTMLDetailsElement
                                    ).open
                                "
                            >
                                <summary
                                    class="flex min-h-10 cursor-pointer list-none items-center justify-between gap-3"
                                >
                                    <span
                                        class="flex items-center gap-2 text-sm font-semibold"
                                        ><CalendarDays
                                            class="size-4 text-primary dark:text-foreground"
                                            aria-hidden="true"
                                        />Detail kegiatan (opsional)<span
                                            v-if="eventIsFilled"
                                            class="rounded-full bg-primary/10 px-2 py-1 text-xs text-primary dark:text-foreground"
                                            >Terisi</span
                                        ></span
                                    ><ChevronDown
                                        class="size-4 shrink-0 transition-transform group-open:rotate-180"
                                        aria-hidden="true"
                                    />
                                </summary>
                                <p
                                    class="mt-2 text-xs leading-6 text-muted-foreground"
                                >
                                    Untuk undangan, rapat, atau kegiatan
                                    lainnya. Isi bagian yang diperlukan; kolom
                                    kosong tidak dicetak.
                                </p>
                                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label
                                            for="general-event_date"
                                            :class="labelClass"
                                            >Tanggal kegiatan</label
                                        >
                                        <input
                                            id="general-event_date"
                                            v-model="form.event_date"
                                            type="date"
                                            :class="inputClass"
                                            :aria-invalid="
                                                Boolean(form.errors.event_date)
                                            "
                                        />
                                        <p
                                            v-if="form.errors.event_date"
                                            class="mt-1 text-xs text-destructive"
                                        >
                                            {{ form.errors.event_date }}
                                        </p>
                                    </div>
                                    <div
                                        v-for="field in eventFields"
                                        :key="field.key"
                                        :class="{
                                            'sm:col-span-2':
                                                field.key === 'event_agenda',
                                        }"
                                    >
                                        <label
                                            :for="`general-${field.key}`"
                                            :class="labelClass"
                                            >{{ field.label }}</label
                                        >
                                        <textarea
                                            v-if="field.key === 'event_agenda'"
                                            :id="`general-${field.key}`"
                                            v-model="form[field.key]"
                                            :class="inputClass"
                                            :aria-invalid="
                                                Boolean(form.errors[field.key])
                                            "
                                            :maxlength="field.limit"
                                            :placeholder="field.placeholder"
                                            rows="3"
                                        />
                                        <input
                                            v-else
                                            :id="`general-${field.key}`"
                                            v-model="form[field.key]"
                                            type="text"
                                            :class="inputClass"
                                            :aria-invalid="
                                                Boolean(form.errors[field.key])
                                            "
                                            :maxlength="field.limit"
                                            :placeholder="field.placeholder"
                                        />
                                        <p
                                            v-if="form.errors[field.key]"
                                            class="mt-1 text-xs text-destructive"
                                        >
                                            {{ form.errors[field.key] }}
                                        </p>
                                    </div>
                                </div>
                            </details>
                            <div class="mt-5">
                                <label
                                    for="general-closing_body"
                                    :class="labelClass"
                                    >Isi surat 2 — Penutup (opsional)</label
                                >
                                <textarea
                                    id="general-closing_body"
                                    v-model="form.closing_body"
                                    :class="inputClass"
                                    class="min-h-40 resize-y leading-7"
                                    :aria-invalid="
                                        Boolean(form.errors.closing_body)
                                    "
                                    aria-describedby="general-closing_body-hint general-closing_body-error"
                                    maxlength="20000"
                                    rows="6"
                                    placeholder="Contoh: Demikian undangan ini kami sampaikan. Atas perhatian dan kehadiran Bapak/Ibu, kami ucapkan terima kasih."
                                />
                                <div
                                    class="mt-2 flex flex-wrap justify-between gap-2 text-xs leading-6 text-muted-foreground"
                                >
                                    <p id="general-closing_body-hint">
                                        Dicetak setelah detail kegiatan, sebelum
                                        salam penutup dan tanda tangan.
                                    </p>
                                    <span class="shrink-0 tabular-nums"
                                        >{{
                                            form.closing_body.length.toLocaleString(
                                                'id-ID',
                                            )
                                        }}
                                        / 20.000 karakter</span
                                    >
                                </div>
                                <p
                                    v-if="form.errors.closing_body"
                                    id="general-closing_body-error"
                                    class="mt-1 text-xs text-destructive"
                                >
                                    {{ form.errors.closing_body }}
                                </p>
                            </div>
                            <div class="mt-5">
                                <label
                                    for="general-closing_greeting"
                                    :class="labelClass"
                                    >Salam penutup (opsional)</label
                                >
                                <input
                                    id="general-closing_greeting"
                                    v-model="form.closing_greeting"
                                    type="text"
                                    :class="inputClass"
                                    :aria-invalid="
                                        Boolean(form.errors.closing_greeting)
                                    "
                                    maxlength="255"
                                    placeholder="Contoh: Hormat kami,"
                                />
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Salam pembuka dan penutup dapat diubah atau
                                    dikosongkan.
                                </p>
                                <p
                                    v-if="form.errors.closing_greeting"
                                    class="mt-1 text-xs text-destructive"
                                >
                                    {{ form.errors.closing_greeting }}
                                </p>
                            </div>
                        </section>
                    </div>
                    <div v-show="step === 3" class="space-y-6">
                        <h2
                            id="general-step-3"
                            tabindex="-1"
                            class="scroll-mt-24 text-lg font-semibold"
                        >
                            3. Periksa &amp; buat
                        </h2>
                        <section
                            :class="sectionClass"
                            aria-labelledby="general-summary-heading"
                        >
                            <div
                                class="flex flex-wrap items-start justify-between gap-3"
                            >
                                <div>
                                    <h2
                                        id="general-summary-heading"
                                        :class="headingClass"
                                    >
                                        Ringkasan surat
                                    </h2>
                                    <p :class="hintClass">
                                        Periksa isi dan seluruh penerima.
                                        Setelah disimpan, surat lengkap siap
                                        dipratinjau dan dicetak. Setiap batch
                                        memuat maksimal
                                        {{
                                            maxLettersPerBatch.toLocaleString(
                                                'id-ID',
                                            )
                                        }}
                                        surat.
                                    </p>
                                </div>
                                <span
                                    class="rounded-full bg-primary/10 px-3 py-1.5 text-xs font-semibold text-primary"
                                    >{{
                                        form.items.length.toLocaleString(
                                            'id-ID',
                                        )
                                    }}
                                    surat</span
                                >
                            </div>
                            <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        Jenis surat
                                    </dt>
                                    <dd class="mt-1 text-sm font-medium">
                                        {{ categoryLabel }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        Tanggal surat
                                    </dt>
                                    <dd class="mt-1 text-sm font-medium">
                                        {{ formatDate(form.letter_date) }}
                                    </dd>
                                </div>
                                <div class="sm:col-span-2">
                                    <dt class="text-xs text-muted-foreground">
                                        Nomor surat
                                    </dt>
                                    <dd
                                        class="mt-1 text-sm font-medium break-all"
                                        translate="no"
                                    >
                                        {{ firstNumber
                                        }}<span
                                            v-if="form.items.length > 1"
                                            class="block text-muted-foreground"
                                            >Sampai {{ lastNumber }}</span
                                        >
                                    </dd>
                                </div>
                                <div v-if="form.letter_title.trim()">
                                    <dt class="text-xs text-muted-foreground">
                                        Judul
                                    </dt>
                                    <dd
                                        class="mt-1 text-sm font-medium break-words"
                                    >
                                        {{ form.letter_title }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        Perihal
                                    </dt>
                                    <dd
                                        class="mt-1 text-sm font-medium break-words"
                                    >
                                        {{ form.subject }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        Lampiran
                                    </dt>
                                    <dd class="mt-1 text-sm font-medium">
                                        {{ form.attachment || '-' }}
                                    </dd>
                                </div>
                            </dl>
                            <div class="mt-5 border-t border-border pt-5">
                                <h3 class="text-sm font-semibold">
                                    Penerima ({{ form.items.length }})
                                </h3>
                                <ol
                                    class="mt-3 max-h-72 space-y-3 overflow-y-auto pr-2"
                                >
                                    <li
                                        v-for="(item, index) in form.items"
                                        :key="item.ui_key"
                                        class="rounded-xl border border-border bg-background px-4 py-3 text-sm leading-6"
                                    >
                                        <p class="font-semibold">
                                            {{ index + 1 }}.
                                            {{
                                                item.institution_name ||
                                                item.recipient_name
                                            }}
                                        </p>
                                        <p
                                            v-if="item.recipient_salutation"
                                            class="text-muted-foreground"
                                        >
                                            {{ item.recipient_salutation }}
                                        </p>
                                        <p
                                            v-if="
                                                item.recipient_position ||
                                                item.recipient_name
                                            "
                                        >
                                            {{
                                                [
                                                    item.recipient_position,
                                                    item.recipient_name,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' ')
                                            }}
                                        </p>
                                        <p
                                            class="whitespace-pre-wrap text-muted-foreground"
                                        >
                                            di
                                            {{
                                                item.recipient_address ||
                                                'Tempat'
                                            }}
                                        </p>
                                    </li>
                                </ol>
                            </div>
                            <div
                                class="mt-5 space-y-4 border-t border-border pt-5 text-sm leading-7 break-words"
                            >
                                <h3 class="font-semibold">Isi surat</h3>
                                <p
                                    v-if="form.opening_greeting.trim()"
                                    class="font-medium"
                                >
                                    {{ form.opening_greeting }}
                                </p>
                                <p class="whitespace-pre-wrap">
                                    {{ form.body }}
                                </p>
                                <dl
                                    v-if="eventIsFilled"
                                    class="grid gap-2 rounded-xl bg-muted/40 p-4 sm:grid-cols-[120px_minmax(0,1fr)]"
                                >
                                    <template v-if="form.event_date"
                                        ><dt class="text-muted-foreground">
                                            Tanggal kegiatan
                                        </dt>
                                        <dd>
                                            {{ formatDate(form.event_date) }}
                                        </dd></template
                                    >
                                    <template
                                        v-for="field in eventFields"
                                        :key="field.key"
                                        ><dt
                                            v-if="form[field.key].trim()"
                                            class="text-muted-foreground"
                                        >
                                            {{ field.label }}
                                        </dt>
                                        <dd
                                            v-if="form[field.key].trim()"
                                            class="whitespace-pre-wrap"
                                        >
                                            {{ form[field.key] }}
                                        </dd></template
                                    >
                                </dl>
                                <p
                                    v-if="form.closing_body.trim()"
                                    class="whitespace-pre-wrap"
                                >
                                    {{ form.closing_body }}
                                </p>
                                <p
                                    v-if="form.closing_greeting.trim()"
                                    class="font-medium"
                                >
                                    {{ form.closing_greeting }}
                                </p>
                            </div>
                            <p
                                class="mt-5 flex items-start gap-2 text-xs leading-6 text-muted-foreground"
                            >
                                <Check
                                    class="mt-1 size-4 shrink-0 text-primary"
                                    aria-hidden="true"
                                />{{
                                    form.items.length > maxLettersPerBatch
                                        ? 'Surat disimpan ke Arsip Surat dalam beberapa batch.'
                                        : 'Surat disimpan ke Arsip Surat dan langsung dibuka pada pratinjau.'
                                }}
                            </p>
                        </section>
                        <section
                            id="general-signer"
                            :class="sectionClass"
                            aria-labelledby="general-signer-heading"
                        >
                            <div class="flex items-start gap-3">
                                <span class="sisurat-icon-tile"
                                    ><Signature
                                        class="size-6"
                                        aria-hidden="true"
                                /></span>
                                <div>
                                    <h2
                                        id="general-signer-heading"
                                        :class="headingClass"
                                    >
                                        Penandatangan
                                    </h2>
                                    <p :class="hintClass">
                                        {{
                                            form.signatory_name ||
                                            'Lengkapi nama penandatangan'
                                        }}<span v-if="form.signatory_title">
                                            · {{ form.signatory_title }}</span
                                        >
                                    </p>
                                </div>
                            </div>
                            <details
                                :open="signerOpen"
                                class="group mt-4"
                                @toggle="
                                    signerOpen = (
                                        $event.target as HTMLDetailsElement
                                    ).open
                                "
                            >
                                <summary
                                    class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 text-sm font-semibold text-primary dark:text-foreground"
                                >
                                    <span
                                        >Atur penandatangan, tanda tangan, dan
                                        cap</span
                                    ><ChevronDown
                                        class="size-4 shrink-0 transition-transform group-open:rotate-180"
                                        aria-hidden="true"
                                    />
                                </summary>
                                <div class="mt-3 grid gap-4 sm:grid-cols-3">
                                    <div
                                        v-for="field in signerFields"
                                        :key="field.key"
                                    >
                                        <label
                                            :for="`general-${field.key}`"
                                            :class="labelClass"
                                            >{{ field.label }}
                                            <span
                                                v-if="field.required"
                                                class="text-destructive"
                                                >*</span
                                            ></label
                                        >
                                        <input
                                            :id="`general-${field.key}`"
                                            v-model="form[field.key]"
                                            type="text"
                                            :class="inputClass"
                                            :aria-invalid="
                                                Boolean(form.errors[field.key])
                                            "
                                            :maxlength="field.limit"
                                            :required="field.required"
                                        />
                                        <p
                                            v-if="form.errors[field.key]"
                                            class="mt-1 text-xs text-destructive"
                                        >
                                            {{ form.errors[field.key] }}
                                        </p>
                                    </div>
                                </div>
                                <div
                                    class="mt-4 flex flex-wrap gap-x-6 gap-y-3"
                                >
                                    <label
                                        class="flex min-h-10 cursor-pointer items-center gap-2 text-sm"
                                        ><input
                                            id="general-include_signature"
                                            v-model="form.include_signature"
                                            type="checkbox"
                                            class="size-4 accent-primary"
                                            :disabled="
                                                !matchesSignatureOwner ||
                                                !pmbSignatory.signature_url
                                            "
                                        />Tanda tangan otomatis</label
                                    ><label
                                        class="flex min-h-10 cursor-pointer items-center gap-2 text-sm"
                                        ><input
                                            id="general-include_stamp"
                                            v-model="form.include_stamp"
                                            type="checkbox"
                                            class="size-4 accent-primary"
                                            :disabled="
                                                !matchesSignatureOwner ||
                                                !pmbSignatory.stamp_url
                                            "
                                        />Cap PMB otomatis</label
                                    >
                                </div>
                                <p
                                    v-if="!matchesSignatureOwner"
                                    class="mt-2 text-xs leading-6 text-muted-foreground"
                                >
                                    Tanda tangan dan cap tersimpan untuk
                                    {{ props.defaults.signatoryName }}.
                                    Penandatangan lain menggunakan ruang tanda
                                    tangan kosong.
                                </p>
                                <p
                                    v-if="
                                        form.errors.include_signature ||
                                        form.errors.include_stamp
                                    "
                                    class="mt-2 text-xs text-destructive"
                                >
                                    {{
                                        form.errors.include_signature ||
                                        form.errors.include_stamp
                                    }}
                                </p>
                            </details>
                        </section>
                    </div>
                </fieldset>
                <p
                    v-if="requestError"
                    role="alert"
                    class="mt-5 rounded-xl border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive"
                >
                    {{ requestError }}
                </p>
                <section
                    v-if="visibleErrors.length"
                    class="mt-5 rounded-xl border border-destructive/30 bg-destructive/5 p-4"
                    role="alert"
                    aria-labelledby="general-errors-heading"
                >
                    <h2
                        id="general-errors-heading"
                        class="text-sm font-semibold text-destructive"
                    >
                        Periksa isian berikut sebelum menyimpan
                    </h2>
                    <ul class="mt-2 space-y-1 text-sm text-destructive">
                        <li v-for="[key, message] in visibleErrors" :key="key">
                            <button
                                type="button"
                                class="min-h-9 text-left underline decoration-destructive/40 underline-offset-4"
                                @click="focusError(key)"
                            >
                                {{ message }}
                            </button>
                        </li>
                    </ul>
                    <p
                        v-if="
                            Object.keys(form.errors).length >
                            visibleErrors.length
                        "
                        class="mt-2 text-xs text-destructive"
                    >
                        Isian lain yang perlu diperbaiki ditandai di formulir.
                    </p>
                </section>
                <div
                    class="sticky bottom-3 z-20 mt-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-primary/20 bg-card/95 p-4 shadow-xl backdrop-blur sm:px-6"
                >
                    <div class="min-w-0">
                        <p class="text-sm font-semibold">
                            {{ form.items.length.toLocaleString('id-ID') }}
                            surat akan dibuat
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{
                                completedItems === form.items.length
                                    ? 'Daftar penerima sudah lengkap.'
                                    : 'Lengkapi tujuan atau nama setiap penerima.'
                            }}
                        </p>
                    </div>
                    <div class="flex w-full gap-3 sm:w-auto">
                        <Button
                            v-show="step > 1"
                            type="button"
                            variant="outline"
                            class="min-h-11 flex-1 sm:flex-none"
                            :disabled="form.processing"
                            @click="changeStep((step - 1) as WizardStep)"
                            ><ArrowLeft
                                class="size-4"
                                aria-hidden="true"
                            />Kembali</Button
                        >
                        <Button
                            type="submit"
                            class="min-h-11 flex-1 sm:flex-none"
                            :disabled="form.processing"
                            :aria-busy="form.processing"
                            ><FileText
                                v-show="step === 3"
                                class="size-4"
                                aria-hidden="true" /><span>{{
                                form.processing
                                    ? 'Menyimpan surat…'
                                    : step === 3
                                      ? form.items.length > maxLettersPerBatch
                                          ? 'Simpan & lihat arsip'
                                          : 'Simpan & lihat surat'
                                      : 'Lanjut'
                            }}</span
                            ><ArrowRight class="size-4" aria-hidden="true"
                        /></Button>
                    </div>
                </div>
            </form>
        </div>
    </main>
</template>
