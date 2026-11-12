<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { edit as editLetterNumbering } from '@/routes/letter-numbering';
import {
    ArrowLeft,
    ArrowRight,
    Check,
    ChevronDown,
    Download,
    FileText,
    Plus,
    Printer,
    RefreshCw,
    Trash2,
} from '@lucide/vue';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import InstitutionCatalogPicker from '@/components/InstitutionCatalogPicker.vue';
import AssignmentFormSection from '@/components/AssignmentFormSection.vue';
import LetterSteps from '@/components/LetterSteps.vue';
import LetterDraftBanner from '@/components/LetterDraftBanner.vue';
import { useLocalLetterDraft } from '@/composables/useLocalLetterDraft';
import {
    cloneLetterItems,
    createAssignmentItem,
    createLetterItem,
    csvTemplate,
    districtCategoryLabel,
    institutionLabels,
    letterTypeLabels,
    maxLettersPerBatch,
    maxLettersPerSubmission,
    normalizeDistrict,
    normalizeInstitutionIdentity,
    parseCsv,
    parseLetterRows,
    participantResultLabels,
    type InstitutionType,
    type LetterItem,
    type StandardLetterType,
    type MasterInstitution,
} from '@/lib/letters';
import { readXlsxSheet } from '@/lib/xlsx';
import {
    letterNumberingYear,
    nextLetterSequenceForDate,
    type YearlyLetterSequences,
} from '@/lib/letterNumbering';
import {
    create as chooseLetter,
    index as lettersIndex,
    store as storeLetters,
} from '@/routes/letters';
import { general, pkkmb } from '@/routes/letters';
import {
    school as permissionSchool,
    pesantren as permissionPesantren,
} from '@/routes/letters/permission';
import {
    school as assignmentSchool,
    pesantren as assignmentPesantren,
} from '@/routes/letters/assignment';
import { index as studyProgramsIndex } from '@/routes/master-data/study-programs';
import { index as facultiesIndex } from '@/routes/master-data/faculties';
import { index as letterTemplatesIndex } from '@/routes/master-data/letter-templates';

type AcademicOption = { id: number; name: string };

const props = defineProps<{
    today: string;
    nextSequence: number;
    nextSequencesByYear: YearlyLetterSequences;
    initialType?: StandardLetterType;
    initialInstitutionType?: InstitutionType;
    initialInstitutionName?: string;
    initialDistrict?: string;
    initialInstitutionId?: number | null;
    initialAddress?: string;
    sourceDraft?: {
        id: number;
        label: string;
        data: Record<string, unknown>;
    } | null;
    institutions: MasterInstitution[];
    districtOptions: string[];
    studyPrograms: AcademicOption[];
    faculties: AcademicOption[];
    staffMembers: Array<{
        id: number;
        name: string;
        office: string;
        employee_number: string | null;
        district_id: number | null;
        district: string | null;
    }>;
    defaults: {
        numberPrefix: string;
        pkkmbNumberPrefix: string;
        signatoryTitle: string;
        signatoryName: string;
        signatoryNumber: string;
    };
    pmbSignatory: {
        id: number;
        signature_url: string | null;
        stamp_url: string | null;
    };
}>();
const destination = ref<InstitutionType>('school');
const wizardStep = ref<1 | 2 | 3>(1);
const form = useForm({
    type: 'permission' as StandardLetterType,
    letter_date: props.today,
    first_sequence: nextLetterSequenceForDate(
        props.today,
        props.nextSequencesByYear,
    ),
    number_prefix: props.defaults.numberPrefix,
    signatory_title: props.defaults.signatoryTitle,
    signatory_name: props.defaults.signatoryName,
    signatory_number: props.defaults.signatoryNumber,
    include_signature: !!props.pmbSignatory.signature_url,
    include_stamp: !!props.pmbSignatory.stamp_url,
    event_start: props.today,
    event_end: props.today,
    pkkmb_theme: '',
    items: [createLetterItem()],
});
const numberingYear = computed(() => letterNumberingYear(form.letter_date));
const nextSequenceSuggestion = computed(() =>
    nextLetterSequenceForDate(form.letter_date, props.nextSequencesByYear),
);
let lastValidNumberingYear = letterNumberingYear(form.letter_date);
watch(
    () => form.letter_date,
    (date) => {
        const year = letterNumberingYear(date);
        if (year === null) return;
        if (year !== lastValidNumberingYear) {
            form.first_sequence = nextLetterSequenceForDate(
                date,
                props.nextSequencesByYear,
            );
            form.clearErrors('first_sequence');
        }
        lastValidNumberingYear = year;
    },
    { flush: 'sync' },
);
type TypeSettings = Pick<
    typeof form,
    | 'number_prefix'
    | 'signatory_title'
    | 'signatory_name'
    | 'signatory_number'
    | 'include_signature'
    | 'include_stamp'
    | 'event_start'
    | 'event_end'
    | 'pkkmb_theme'
>;
const drafts = new Map<string, LetterItem[]>();
const settings = new Map<StandardLetterType, TypeSettings>();
const csvMessage = ref('');
const importing = ref(false);
const refreshingAcademicOptions = ref(false);
const academicOptionsError = ref('');
const formBusy = computed(
    () => form.processing || importing.value || refreshingAcademicOptions.value,
);
const hasAcademicOptions = computed(
    () => props.studyPrograms.length > 0 && props.faculties.length > 0,
);
const importMode = ref<'append' | 'replace'>('append');
const openItems = ref(new Set([0]));
const catalogMessage = ref('');
const sourceError = ref('');
const requestError = ref('');
const draftRestored = ref(false);
const currentInstitutions = computed(() =>
    props.institutions.filter(
        (institution) => institution.institution_type === destination.value,
    ),
);
const isSimpleAssignment = computed(() => form.type === 'assignment');
const assignmentDistricts = computed(() =>
    Array.from(
        new Set(
            props.districtOptions
                .map((district) => normalizeDistrict(district))
                .filter(Boolean),
        ),
    ).sort((left, right) => left.localeCompare(right, 'id')),
);
const batchCount = computed(() =>
    Math.ceil(form.items.length / maxLettersPerBatch),
);
const matchesSignatureOwner = computed(
    () =>
        form.signatory_name.toLowerCase().replace(/[^a-z0-9]/g, '') ===
        props.defaults.signatoryName.toLowerCase().replace(/[^a-z0-9]/g, ''),
);
const finalSequence = computed(
    () => Number(form.first_sequence) + form.items.length - 1,
);
const hasValidSequenceRange = computed(
    () =>
        Number.isInteger(Number(form.first_sequence)) &&
        Number(form.first_sequence) >= 1 &&
        form.items.length > 0 &&
        finalSequence.value <= 99999,
);
const assignmentNumberPreview = computed(() => {
    const code = form.number_prefix.trim();
    if (
        numberingYear.value === null ||
        !hasValidSequenceRange.value ||
        !code ||
        code.length > 120
    )
        return '';
    const [year, month] = form.letter_date.split('-');
    const romanMonth = [
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
    ][Number(month) - 1];
    return `${String(form.first_sequence).padStart(3, '0')}/${code}/${romanMonth}/${year}`;
});
const inputClass = 'sisurat-input';
const labelClass = 'mb-1.5 block text-sm font-medium text-foreground';
const sectionClass = 'sisurat-panel p-5 sm:p-7';
const headingClass = 'text-base font-semibold text-foreground sm:text-lg';
const hintClass = 'mt-1 text-sm leading-6 text-muted-foreground';
const actionClass = 'sisurat-secondary-action';
const page = usePage();
const pageTitle = computed(() => letterTypeLabels[form.type]);
const targetLabel = computed(() =>
    form.type === 'pkkmb' ? 'Mahasiswa' : institutionLabels[destination.value],
);
const completedItems = computed(
    () =>
        form.items.filter((item) => {
            if (form.type === 'pkkmb')
                return (
                    item.participant_name.trim() &&
                    item.participant_nim.trim() &&
                    isAcademicSelection(
                        item.participant_study_program,
                        props.studyPrograms,
                    ) &&
                    isAcademicSelection(
                        item.participant_faculty,
                        props.faculties,
                    ) &&
                    Object.hasOwn(
                        participantResultLabels,
                        item.participant_result,
                    ) &&
                    ['attended', 'completed'].includes(
                        item.participation_status,
                    )
                );
            if (form.type === 'permission')
                return (
                    item.institution_name.trim() &&
                    item.recipient_position.trim()
                );
            return (
                item.recipient_district.trim() &&
                item.assignees.length > 0 &&
                item.assignees.every(
                    (person) =>
                        person.name.trim() &&
                        person.office.trim() &&
                        person.district.trim(),
                )
            );
        }).length,
);
const invalidItemIndices = computed(
    () =>
        new Set(
            Object.keys(form.errors).flatMap((key) => {
                const match = /^items\.(\d+)\./.exec(key);
                return match ? [Number(match[1])] : [];
            }),
        ),
);
const firstInvalidItemIndex = computed(() =>
    invalidItemIndices.value.size ? Math.min(...invalidItemIndices.value) : -1,
);
const visibleErrors = computed(() => Object.entries(form.errors).slice(0, 12));
const typeOptions: { value: StandardLetterType; description: string }[] = [
    {
        value: 'permission',
        description: 'Memohon izin sosialisasi kepada sekolah atau pondok.',
    },
    {
        value: 'assignment',
        description: 'Menugaskan dosen berdasarkan kabupaten/kota.',
    },
    {
        value: 'pkkmb',
        description: 'Menerangkan keikutsertaan dan hasil PKKMB mahasiswa.',
    },
];
type ItemTextKey =
    | 'institution_name'
    | 'recipient_district'
    | 'recipient_address'
    | 'recipient_position'
    | 'recipient_name'
    | 'activity_destination'
    | 'participant_name'
    | 'participant_nim'
    | 'participant_study_program'
    | 'participant_faculty';
type TextField = {
    key: ItemTextKey;
    label: string;
    placeholder: string;
    required?: boolean;
    max: number;
};
function isAcademicField(field: ItemTextKey): boolean {
    return (
        field === 'participant_study_program' || field === 'participant_faculty'
    );
}
function academicOptions(field: ItemTextKey): AcademicOption[] {
    return field === 'participant_faculty'
        ? props.faculties
        : props.studyPrograms;
}
function isAcademicSelection(
    value: string,
    options: AcademicOption[],
): boolean {
    return options.some((option) => option.name === value);
}
function canonicalAcademicName(
    value: string,
    options: AcademicOption[],
): string {
    const normalizedName = (name: string) =>
        name.trim().replace(/\s+/gu, ' ').toLocaleLowerCase('id-ID');
    const match = options.find(
        (option) => normalizedName(option.name) === normalizedName(value),
    );
    return match?.name ?? value;
}
function normalizePkkmbAcademicItem(item: LetterItem): LetterItem {
    return {
        ...item,
        participant_study_program: canonicalAcademicName(
            item.participant_study_program,
            props.studyPrograms,
        ),
        participant_faculty: canonicalAcademicName(
            item.participant_faculty,
            props.faculties,
        ),
    };
}
function refreshAcademicOptions(): void {
    if (form.processing || importing.value || refreshingAcademicOptions.value)
        return;
    academicOptionsError.value = '';
    router.reload({
        only: ['studyPrograms', 'faculties'],
        onStart: () => {
            refreshingAcademicOptions.value = true;
        },
        onSuccess: () => {
            if (form.type === 'pkkmb') {
                form.items = form.items.map(normalizePkkmbAcademicItem);
            }
            form.clearErrors();
        },
        onFinish: () => {
            refreshingAcademicOptions.value = false;
        },
        onError: () => {
            academicOptionsError.value =
                'Kategori belum dapat dimuat. Coba muat ulang kategori kembali.';
        },
        onNetworkError: () => {
            academicOptionsError.value =
                'Koneksi terputus. Periksa koneksi lalu muat ulang kategori.';
            return false;
        },
        onHttpException: () => {
            academicOptionsError.value =
                'Kategori belum dapat dimuat. Coba muat ulang kategori kembali.';
            return false;
        },
    });
}
const itemFields = computed<TextField[]>(() => {
    if (form.type === 'pkkmb')
        return [
            {
                key: 'participant_name',
                label: 'Nama mahasiswa',
                placeholder: 'Nama lengkap mahasiswa',
                required: true,
                max: 150,
            },
            {
                key: 'participant_nim',
                label: 'NIM',
                placeholder: 'Contoh: 00123456',
                required: true,
                max: 50,
            },
            {
                key: 'participant_study_program',
                label: 'Program studi',
                placeholder: 'Nama program studi',
                required: true,
                max: 150,
            },
            {
                key: 'participant_faculty',
                label: 'Fakultas',
                placeholder: 'Nama fakultas mahasiswa',
                required: true,
                max: 150,
            },
        ];
    const institutionExample =
        destination.value === 'pesantren'
            ? 'Pondok Pesantren Thawalib Darussalam Serasan'
            : 'MAN 1 Ogan Komering Ulu';
    return [
        {
            key: 'institution_name',
            label:
                destination.value === 'pesantren'
                    ? 'Nama pondok pesantren'
                    : 'Nama sekolah / madrasah',
            placeholder: institutionExample,
            required: true,
            max: 255,
        },
        {
            key: 'recipient_district',
            label: 'Kabupaten/kota tujuan (opsional)',
            placeholder: 'Kabupaten Ogan Komering Ulu',
            max: 150,
        },
        {
            key: 'recipient_address',
            label: 'Alamat rinci tujuan (opsional)',
            placeholder: 'Jalan, desa, atau kecamatan jika tersedia',
            max: 500,
        },
        {
            key: 'recipient_position',
            label: 'Jabatan penerima',
            placeholder:
                destination.value === 'pesantren' ? 'Pimpinan' : 'Kepala',
            required: true,
            max: 150,
        },
        {
            key: 'recipient_name',
            label: 'Nama penerima (opsional)',
            placeholder: 'Boleh dikosongkan',
            max: 150,
        },
    ];
});
function isValidDate(value: string): boolean {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false;
    const date = new Date(`${value}T12:00:00`);
    return (
        !Number.isNaN(date.getTime()) &&
        date.getFullYear() === Number(value.slice(0, 4)) &&
        date.getMonth() + 1 === Number(value.slice(5, 7)) &&
        date.getDate() === Number(value.slice(8, 10))
    );
}
function displayDate(value: string): string {
    return isValidDate(value)
        ? new Intl.DateTimeFormat('id-ID', {
              day: 'numeric',
              month: 'long',
              year: 'numeric',
          }).format(new Date(`${value}T12:00:00`))
        : 'Belum diisi';
}
function previewNumber(sequence: number): string {
    if (
        !isValidDate(form.letter_date) ||
        !form.number_prefix.trim() ||
        !Number.isInteger(sequence)
    )
        return 'Belum lengkap';
    const months = [
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
    return `${String(sequence).padStart(3, '0')}/${form.number_prefix.trim()}/${months[Number(form.letter_date.slice(5, 7)) - 1]}/${form.letter_date.slice(0, 4)}`;
}
const reviewDetails = computed(() => {
    const details = [
        {
            label: 'Jenis surat',
            value: `${pageTitle.value}${form.type === 'pkkmb' ? '' : ` · ${targetLabel.value}`}`,
        },
        {
            label: 'Jumlah surat',
            value: `${form.items.length.toLocaleString('id-ID')} surat · ${batchCount.value} batch`,
        },
        { label: 'Tanggal surat', value: displayDate(form.letter_date) },
        {
            label: 'Nomor pertama',
            value: previewNumber(Number(form.first_sequence)),
        },
        { label: 'Nomor terakhir', value: previewNumber(finalSequence.value) },
        {
            label: 'Penandatangan',
            value: `${form.signatory_name} · ${form.signatory_title}`,
        },
    ];
    if (form.type !== 'permission')
        details.push({
            label: 'Periode kegiatan',
            value: `${displayDate(form.event_start)} – ${displayDate(form.event_end)}`,
        });
    if (form.type === 'pkkmb')
        details.push({ label: 'Tema PKKMB', value: form.pkkmb_theme });
    const isPmbSigner =
        form.signatory_name.toLowerCase().replace(/[^a-z0-9]/g, '') ===
        props.defaults.signatoryName.toLowerCase().replace(/[^a-z0-9]/g, '');
    details.push({
        label: 'Gambar PMB',
        value: isPmbSigner
            ? [
                  form.include_signature ? 'Tanda tangan' : '',
                  form.include_stamp ? 'Cap' : '',
              ]
                  .filter(Boolean)
                  .join(' & ') || 'Tidak digunakan'
            : 'Tidak digunakan untuk penandatangan ini',
    });
    return details;
});
const reviewEntries = computed(() =>
    form.items.map((item) => {
        if (form.type === 'pkkmb')
            return {
                title: item.participant_name,
                lines: [
                    `NIM: ${item.participant_nim}`,
                    `${item.participant_study_program} · ${item.participant_faculty}`,
                    `Hasil PKKMB: ${item.participant_result ? participantResultLabels[item.participant_result] : 'Belum dipilih'}`,
                ],
            };
        if (form.type === 'assignment')
            return {
                title: districtCategoryLabel(item.recipient_district),
                lines: item.assignees.map(
                    (person) => `${person.name} · ${person.office}`,
                ),
            };
        return {
            title: `${item.recipient_position} ${item.recipient_name || item.institution_name}`.trim(),
            lines: [
                item.recipient_name ? item.institution_name : '',
                item.recipient_district,
                'Tujuan pada surat: di Tempat',
            ].filter(Boolean),
        };
    }),
);
function errorStep(key: string): 1 | 2 {
    return key === 'items' || key.startsWith('items.') ? 2 : 1;
}
function focusValidationError(): void {
    const key = Object.keys(form.errors)[0];
    if (!key) return;
    wizardStep.value = errorStep(key);
    const match = /^items\.(\d+)\.(.+)$/.exec(key);
    if (match)
        openItems.value = new Set([...openItems.value, Number(match[1])]);
    void nextTick(() => {
        let fieldId = key;
        if (match) {
            const person = /^assignees\.(\d+)\.(name|office)$/.exec(match[2]);
            fieldId =
                form.type === 'assignment'
                    ? person
                        ? `assignment_${person[2]}_${match[1]}_${person[1]}`
                        : match[2] === 'recipient_district'
                          ? `assignment_county_${match[1]}`
                          : 'assignment-fields'
                    : match[2] === 'institution_id'
                      ? `catalog_institution_${match[1]}`
                      : `${match[2]}_${match[1]}`;
        }
        const target =
            document.getElementById(fieldId) ??
            document.getElementById(`letter-step-${wizardStep.value}`);
        const details = target?.closest('details');
        if (details) details.open = true;
        target?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        target?.focus();
    });
}
function applyValidationErrors(errors: Record<string, string>): boolean {
    form.clearErrors();
    if (!Object.keys(errors).length) return true;
    form.setError(errors as typeof form.errors);
    focusValidationError();
    return false;
}
function validateSettings(): boolean {
    const errors: Record<string, string> = {};
    if (!isValidDate(form.letter_date))
        errors.letter_date = 'Pilih tanggal surat yang valid.';
    if (!hasValidSequenceRange.value)
        errors.first_sequence =
            'Isi nomor awal 1–99999. Rentang seluruh surat tidak boleh melebihi 99999.';
    for (const [key, label, limit, required] of [
        ['number_prefix', 'Kode surat', 120, true],
        ['signatory_title', 'Jabatan penandatangan', 150, true],
        ['signatory_name', 'Nama penandatangan', 150, true],
        ['signatory_number', 'NIP / identitas penandatangan', 100, false],
    ] as const) {
        if (required && !form[key].trim())
            errors[key] = `${label} wajib diisi.`;
        else if (form[key].length > limit)
            errors[key] = `${label} maksimal ${limit} karakter.`;
    }
    if (form.type !== 'permission') {
        if (!isValidDate(form.event_start))
            errors.event_start = 'Pilih tanggal mulai kegiatan yang valid.';
        if (!isValidDate(form.event_end))
            errors.event_end = 'Pilih tanggal selesai kegiatan yang valid.';
        else if (form.event_end < form.event_start)
            errors.event_end =
                'Tanggal selesai harus sama dengan atau setelah tanggal mulai.';
    }
    if (form.type === 'pkkmb') {
        if (!form.pkkmb_theme.trim())
            errors.pkkmb_theme = 'Tema PKKMB wajib diisi.';
        else if (form.pkkmb_theme.length > 1000)
            errors.pkkmb_theme = 'Tema PKKMB maksimal 1000 karakter.';
    }
    return applyValidationErrors(errors);
}
function preparePermissionSubmission(): boolean {
    const errors: Record<string, string> = {};
    const identities = new Set<string>();
    if (!form.items.length || form.items.length > maxLettersPerSubmission)
        errors.items = 'Isi 1 sampai 1.000 penerima surat.';
    form.items.forEach((item, index) => {
        const prefix = `Penerima ke-${index + 1}: `;
        for (const [key, label, limit, required] of [
            ['institution_name', 'Nama sekolah / pondok', 255, true],
            ['recipient_position', 'Jabatan penerima', 150, true],
            ['recipient_name', 'Nama penerima', 150, false],
            ['recipient_district', 'Kabupaten/kota', 150, false],
            ['recipient_address', 'Alamat', 500, false],
        ] as const) {
            if (required && !item[key].trim())
                errors[`items.${index}.${key}`] =
                    `${prefix}${label} wajib diisi.`;
            else if (item[key].length > limit)
                errors[`items.${index}.${key}`] =
                    `${prefix}${label} maksimal ${limit} karakter.`;
        }
        if (item.institution_type !== destination.value)
            errors[`items.${index}.institution_name`] =
                `${prefix}Jenis tujuan harus sesuai pilihan sekolah atau pondok.`;
        if (
            item.institution_id !== null &&
            !currentInstitutions.value.some(
                (institution) => institution.id === item.institution_id,
            )
        )
            errors[`items.${index}.institution_id`] =
                `${prefix}Data tujuan sudah tidak tersedia. Pilih tujuan lain atau gunakan data manual.`;
        const identity = `${targetIdentity(item)}|${item.recipient_position.trim().toLowerCase()}|${item.recipient_name.trim().toLowerCase()}`;
        if (identities.has(identity))
            errors[`items.${index}.institution_name`] =
                `${prefix}Penerima sudah ada dalam daftar. Hapus data yang sama sebelum melanjutkan.`;
        identities.add(identity);
    });
    return applyValidationErrors(errors);
}
function validateRecipients(): boolean {
    return form.type === 'permission'
        ? preparePermissionSubmission()
        : form.type === 'assignment'
          ? prepareAssignmentSubmission()
          : preparePkkmbSubmission();
}
function goToStep(step: 1 | 2 | 3): void {
    if (formBusy.value) return;
    if (
        step > wizardStep.value &&
        (!validateSettings() || (step === 3 && !validateRecipients()))
    )
        return;
    wizardStep.value = step;
    void nextTick(() => {
        const section = document.getElementById(`letter-step-${step}`);
        section?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        section?.focus();
    });
}

function draftKey(type = form.type, target = destination.value): string {
    return type === 'pkkmb' ? type : `${type}:${target}`;
}
function navigateToForm(type: StandardLetterType, target = destination.value) {
    if (
        form.processing ||
        importing.value ||
        refreshingAcademicOptions.value ||
        (form.type === type &&
            (type === 'pkkmb' || destination.value === target))
    )
        return;
    const route =
        type === 'pkkmb'
            ? pkkmb()
            : type === 'assignment'
              ? target === 'school'
                  ? assignmentSchool()
                  : assignmentPesantren()
              : target === 'school'
                ? permissionSchool()
                : permissionPesantren();
    router.get(route.url, {}, { preserveState: true, preserveScroll: true });
}
function setType(type: StandardLetterType) {
    if (form.type === type) return;
    draftRestored.value = false;
    requestError.value = '';
    drafts.set(draftKey(), cloneLetterItems(form.items));
    settings.set(form.type, {
        number_prefix: form.number_prefix,
        signatory_title: form.signatory_title,
        signatory_name: form.signatory_name,
        signatory_number: form.signatory_number,
        include_signature: form.include_signature,
        include_stamp: form.include_stamp,
        event_start: form.event_start,
        event_end: form.event_end,
        pkkmb_theme: form.pkkmb_theme,
    });
    form.type = type;
    if (type === 'pkkmb') destination.value = 'school';
    wizardStep.value = 1;
    form.items = cloneLetterItems(
        drafts.get(draftKey()) ?? [
            type === 'assignment'
                ? createAssignmentItem(destination.value)
                : createLetterItem(destination.value),
        ],
    );
    Object.assign(
        form,
        settings.get(type) ?? {
            number_prefix:
                type === 'pkkmb'
                    ? props.defaults.pkkmbNumberPrefix
                    : props.defaults.numberPrefix,
            signatory_title: props.defaults.signatoryTitle,
            signatory_name: props.defaults.signatoryName,
            signatory_number: props.defaults.signatoryNumber,
            include_signature: true,
            include_stamp: true,
            event_start: props.today,
            event_end: props.today,
            pkkmb_theme: '',
        },
    );
    form.clearErrors();
    csvMessage.value = '';
    catalogMessage.value = '';
    openItems.value = new Set([0]);
}
function setDestination(target: InstitutionType) {
    if (destination.value === target) return;
    draftRestored.value = false;
    requestError.value = '';
    drafts.set(draftKey(), cloneLetterItems(form.items));
    destination.value = target;
    wizardStep.value = 1;
    form.items = cloneLetterItems(
        drafts.get(draftKey()) ?? [
            form.type === 'assignment'
                ? createAssignmentItem(target)
                : createLetterItem(target),
        ],
    );
    form.clearErrors();
    csvMessage.value = '';
    catalogMessage.value = '';
    openItems.value = new Set([0]);
}
function addItem() {
    if (form.items.length < maxLettersPerSubmission) {
        form.items.push(createLetterItem(destination.value));
        openItems.value = new Set([...openItems.value, form.items.length - 1]);
    }
}
function removeItem(index: number) {
    if (form.items.length > 1) form.items.splice(index, 1);
    openItems.value = new Set(
        Array.from(openItems.value)
            .filter((itemIndex) => itemIndex !== index)
            .map((itemIndex) =>
                itemIndex > index ? itemIndex - 1 : itemIndex,
            ),
    );
    form.clearErrors();
}
function errorFor(path: string): string | undefined {
    return (form.errors as Record<string, string>)[path];
}
function isBlankItem(item: LetterItem): boolean {
    const defaults = createLetterItem(item.institution_type);
    return (
        !item.institution_name.trim() &&
        item.institution_id === null &&
        !item.recipient_address.trim() &&
        !item.participant_name.trim() &&
        !item.participant_nim.trim() &&
        !item.recipient_district.trim() &&
        !item.recipient_name.trim() &&
        item.activity_destination === defaults.activity_destination &&
        item.recipient_position === defaults.recipient_position &&
        !item.participant_study_program.trim() &&
        !item.participant_faculty.trim() &&
        item.participant_result === defaults.participant_result &&
        item.participation_status === defaults.participation_status &&
        item.assignees.every(
            (person) =>
                !person.name.trim() &&
                !person.district.trim() &&
                person.office === 'Dosen',
        )
    );
}
function institutionItem(institution: MasterInstitution): LetterItem {
    return {
        ...createLetterItem(institution.institution_type),
        institution_id: institution.id,
        institution_name: institution.name,
        recipient_district: normalizeDistrict(institution.district),
        recipient_address: institution.address ?? '',
        activity_destination: institution.name,
    };
}
function targetIdentity(item: LetterItem): string {
    return normalizeInstitutionIdentity(
        item.institution_type,
        item.institution_name || item.activity_destination,
        item.recipient_district,
        item.recipient_address,
    );
}
function attachKnownInstitution(item: LetterItem): LetterItem {
    if (form.type === 'pkkmb') return item;
    const name = item.institution_name || item.activity_destination;
    const candidates = currentInstitutions.value.filter(
        (institution) =>
            normalizeInstitutionIdentity(
                institution.institution_type,
                institution.name,
                '',
            ) === normalizeInstitutionIdentity(item.institution_type, name, ''),
    );
    const districtCandidates = item.recipient_district.trim()
        ? candidates.filter(
              (candidate) =>
                  normalizeInstitutionIdentity(
                      candidate.institution_type,
                      candidate.name,
                      candidate.district,
                  ) ===
                  normalizeInstitutionIdentity(
                      item.institution_type,
                      name,
                      item.recipient_district,
                  ),
          )
        : candidates;
    const hasAddress = item.recipient_address.trim().length > 0;
    const addressCandidates = hasAddress
        ? districtCandidates.filter(
              (candidate) =>
                  normalizeInstitutionIdentity(
                      item.institution_type,
                      '',
                      '',
                      candidate.address ?? '',
                  ) ===
                  normalizeInstitutionIdentity(
                      item.institution_type,
                      '',
                      '',
                      item.recipient_address,
                  ),
          )
        : [];
    const institution =
        addressCandidates.length === 1
            ? addressCandidates[0]
            : !hasAddress && districtCandidates.length === 1
              ? districtCandidates[0]
              : undefined;
    if (!institution) return item;
    return {
        ...item,
        institution_id: institution.id,
        institution_name: item.institution_name || institution.name,
        recipient_district: normalizeDistrict(
            item.recipient_district || institution.district,
        ),
        recipient_address: item.recipient_address || institution.address || '',
    };
}
function applyCatalogInstitutions(ids: number[]): void {
    if (form.type !== 'permission' || form.processing || importing.value)
        return;
    const institutionsById = new Map(
        currentInstitutions.value.map((institution) => [
            institution.id,
            institution,
        ]),
    );
    const chosen = ids
        .map((id) => institutionsById.get(id))
        .filter((institution) => institution !== undefined);
    const existing = form.items.filter((item) => !isBlankItem(item));
    const existingIds = new Set(existing.map((item) => item.institution_id));
    const existingNames = new Set(existing.map(targetIdentity));
    const additions = chosen
        .filter((institution) => {
            const key = normalizeInstitutionIdentity(
                institution.institution_type,
                institution.name,
                institution.district,
                institution.address ?? '',
            );
            if (existingIds.has(institution.id) || existingNames.has(key))
                return false;
            existingIds.add(institution.id);
            existingNames.add(key);
            return true;
        })
        .map(institutionItem);
    if (!additions.length) {
        catalogMessage.value =
            'Semua tujuan yang dipilih sudah ada dalam daftar surat.';
        return;
    }
    const remainingAdditions = [...additions];
    const preservedItems = existing.map((item) => {
        const defaultTarget = createLetterItem(
            item.institution_type,
        ).activity_destination;
        const hasNoNamedTarget =
            !item.institution_name.trim() &&
            (!item.activity_destination.trim() ||
                item.activity_destination === defaultTarget);
        const target = hasNoNamedTarget
            ? remainingAdditions.shift()
            : undefined;
        if (!target) return item;
        return {
            ...item,
            institution_id: target.institution_id,
            institution_name: target.institution_name,
            activity_destination: target.activity_destination,
            recipient_district: target.recipient_district,
            recipient_address: target.recipient_address,
        };
    });
    const combined = [...preservedItems, ...remainingAdditions];
    if (combined.length > maxLettersPerSubmission) {
        catalogMessage.value = `Daftar akan menjadi ${combined.length} surat. Batasnya ${maxLettersPerSubmission.toLocaleString('id-ID')} surat; kurangi pilihan atau hapus tujuan yang tidak diperlukan. Data yang sudah diisi tetap tersimpan.`;
        return;
    }
    form.items = cloneLetterItems(combined);
    openItems.value = new Set([0]);
    form.clearErrors();
    const skipped = chosen.length - additions.length;
    catalogMessage.value = `${additions.length} ${destination.value === 'school' ? 'sekolah' : 'pondok'} diterapkan${skipped ? `; ${skipped} tujuan yang sudah ada dilewati` : ''}. Nama dan kabupaten/kota terisi otomatis. Daftar sekarang berisi ${form.items.length} surat.`;
}
function selectItemInstitution(item: LetterItem, event: Event): void {
    const selectedId = Number((event.target as HTMLSelectElement).value);
    const institution = currentInstitutions.value.find(
        (candidate) => candidate.id === selectedId,
    );
    if (!institution) {
        item.institution_id = null;
        return;
    }
    const defaults = createLetterItem(institution.institution_type);
    Object.assign(item, {
        institution_id: institution.id,
        institution_type: institution.institution_type,
        institution_name: institution.name,
        activity_destination: institution.name,
        recipient_district: normalizeDistrict(institution.district),
        recipient_address: institution.address ?? '',
        recipient_position: defaults.recipient_position,
        recipient_name: '',
    });
    form.clearErrors();
}
function handleItemTextInput(
    item: LetterItem,
    field: ItemTextKey,
    event: Event,
): void {
    if (
        ![
            'institution_name',
            'activity_destination',
            'recipient_district',
            'recipient_address',
        ].includes(field)
    )
        return;
    const value = (event.target as HTMLInputElement).value;
    const institution = currentInstitutions.value.find(
        (candidate) => candidate.id === item.institution_id,
    );
    if (institution) {
        const matchesInstitution =
            field === 'recipient_address'
                ? normalizeInstitutionIdentity(
                      item.institution_type,
                      '',
                      '',
                      value,
                  ) ===
                  normalizeInstitutionIdentity(
                      item.institution_type,
                      '',
                      '',
                      institution.address ?? '',
                  )
                : field === 'recipient_district'
                  ? normalizeInstitutionIdentity(
                        item.institution_type,
                        '',
                        value,
                    ) ===
                    normalizeInstitutionIdentity(
                        item.institution_type,
                        '',
                        institution.district,
                    )
                  : value.trim() === institution.name.trim();
        if (!matchesInstitution) item.institution_id = null;
    }
    if (field === 'activity_destination') item.institution_name = value;
}
function isItemExpanded(index: number): boolean {
    return openItems.value.has(index) || firstInvalidItemIndex.value === index;
}
function toggleItem(event: Event, index: number): void {
    const expanded = (event.currentTarget as HTMLDetailsElement).open;
    const nextOpenItems = new Set(openItems.value);
    if (expanded) nextOpenItems.add(index);
    else nextOpenItems.delete(index);
    openItems.value = nextOpenItems;
}
async function importDataFile(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;
    csvMessage.value = '';
    const isXlsx = file.name.toLowerCase().endsWith('.xlsx');
    if (!isXlsx && !file.name.toLowerCase().endsWith('.csv')) {
        csvMessage.value = 'Pilih file CSV UTF-8 atau XLSX.';
        input.value = '';
        return;
    }
    if (file.size > (isXlsx ? 10_000_000 : 2_000_000)) {
        csvMessage.value = isXlsx
            ? 'Ukuran XLSX maksimal 10 MB.'
            : 'Ukuran CSV maksimal 2 MB.';
        input.value = '';
        return;
    }
    importing.value = true;
    const importDraftKey = draftKey();
    const importType = form.type;
    const importDestination = destination.value;
    try {
        const rows = isXlsx
            ? await readXlsxSheet(
                  file,
                  importType === 'pkkmb' ? undefined : 'UNTK LPJ PMB',
                  true,
              )
            : parseCsv(await file.text());
        if (draftKey() !== importDraftKey) {
            throw new Error(
                'Pilihan jenis atau tujuan surat berubah saat file dibaca. Impor ulang pada pilihan yang sesuai.',
            );
        }
        const imported = parseLetterRows(rows, importType, importDestination);
        const selected = (
            form.type === 'pkkmb'
                ? imported
                : imported.filter(
                      (item) => item.institution_type === destination.value,
                  )
        ).map((item) =>
            importType === 'pkkmb'
                ? normalizePkkmbAcademicItem(item)
                : attachKnownInstitution(item),
        );
        if (!selected.length)
            throw new Error(
                `Tidak ada data ${institutionLabels[destination.value].toLowerCase()} pada file. Pilih tujuan yang sesuai.`,
            );
        const existing = form.items.filter((item) => !isBlankItem(item));
        const combined =
            importMode.value === 'append'
                ? [...existing, ...selected]
                : selected;
        if (combined.length > maxLettersPerSubmission)
            throw new Error(
                `Daftar melebihi ${maxLettersPerSubmission.toLocaleString('id-ID')} surat. Kurangi data atau pilih Ganti daftar saat mengimpor.`,
            );
        form.items = cloneLetterItems(combined);
        openItems.value = new Set([0]);
        form.clearErrors();
        const skipped = imported.length - selected.length;
        csvMessage.value = `${selected.length} data dimuat dari ${file.name}${skipped ? `; ${skipped} data tujuan lain dilewati` : ''}. Daftar sekarang berisi ${combined.length} surat. Periksa data sebelum menyimpan.`;
    } catch (error) {
        csvMessage.value =
            error instanceof Error
                ? error.message
                : 'File tidak dapat dibaca. Periksa format dan coba kembali.';
    } finally {
        importing.value = false;
        input.value = '';
    }
}
function downloadCsvTemplate() {
    const file = new Blob(
        ['\uFEFF', csvTemplate(form.type, destination.value)],
        { type: 'text/csv;charset=utf-8' },
    );
    const url = URL.createObjectURL(file);
    const link = document.createElement('a');
    link.href = url;
    link.download = `format-${form.type}-${form.type === 'pkkmb' ? 'mahasiswa' : destination.value}.csv`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 1000);
}
function itemHasError(index: number): boolean {
    return invalidItemIndices.value.has(index);
}
function revealInvalidItem(event: Event) {
    const target = event.target;
    if (target instanceof HTMLElement) {
        const details = target.closest('details');
        if (details) details.open = true;
    }
}
watch(
    () => [
        props.initialType,
        props.initialInstitutionType,
        props.initialInstitutionName,
        props.initialDistrict,
        props.initialInstitutionId,
        props.initialAddress,
    ],
    () => {
        setType(props.initialType ?? 'permission');
        if (form.type !== 'pkkmb')
            setDestination(props.initialInstitutionType ?? 'school');
        if (form.type === 'assignment') {
            const district = normalizeDistrict(props.initialDistrict ?? '');
            if (
                district &&
                !form.items.some((item) => item.recipient_district === district)
            ) {
                const existing = form.items.filter(
                    (item) =>
                        item.recipient_district.trim() ||
                        item.assignees.some(
                            (person) =>
                                person.name.trim() ||
                                person.office.trim() !== 'Dosen',
                        ),
                );
                if (existing.length < maxLettersPerSubmission) {
                    form.items = [
                        ...existing,
                        createAssignmentItem(destination.value, district),
                    ];
                }
            }
            return;
        }
        if (props.initialInstitutionId && form.type === 'permission') {
            applyCatalogInstitutions([props.initialInstitutionId]);
        } else if (props.initialInstitutionName && form.type !== 'pkkmb') {
            const item = createLetterItem(destination.value);
            item.institution_name = props.initialInstitutionName;
            item.activity_destination = props.initialInstitutionName;
            item.recipient_district = props.initialDistrict ?? '';
            item.recipient_address = props.initialAddress ?? '';
            const existing = form.items.filter(
                (candidate) => !isBlankItem(candidate),
            );
            if (
                !existing.some(
                    (candidate) =>
                        targetIdentity(candidate) === targetIdentity(item),
                ) &&
                existing.length < maxLettersPerSubmission
            ) {
                form.items = [...existing, item];
                openItems.value = new Set([0]);
            }
        }
    },
    { immediate: true },
);
function prepareAssignmentSubmission(): boolean {
    const errors: Record<string, string> = {};
    const selectedDistricts = new Set<string>();
    const firstSequence = Number(form.first_sequence);
    if (
        !Number.isInteger(firstSequence) ||
        firstSequence < 1 ||
        firstSequence > 99999
    )
        errors.first_sequence =
            'Nomor urut awal harus berupa angka 1 sampai 99999.';
    else if (finalSequence.value > 99999)
        errors.first_sequence =
            'Rentang nomor surat melebihi 99999. Kurangi nomor urut awal.';
    if (!form.number_prefix.trim())
        errors.number_prefix = 'Kode surat wajib diisi.';
    else if (form.number_prefix.trim().length > 120)
        errors.number_prefix = 'Kode surat maksimal 120 karakter.';
    if (!form.event_start)
        errors.event_start = 'Tanggal mulai kegiatan wajib diisi.';
    if (!form.event_end)
        errors.event_end = 'Tanggal selesai kegiatan wajib diisi.';
    else if (form.event_start && form.event_end < form.event_start)
        errors.event_end =
            'Tanggal selesai harus sama dengan atau setelah tanggal mulai.';
    if (!form.items.length || form.items.length > maxLettersPerSubmission)
        errors.items = `Pilih minimal satu kelompok dan maksimal ${maxLettersPerSubmission.toLocaleString('id-ID')} surat.`;
    form.items.forEach((item, itemIndex) => {
        const district = normalizeDistrict(item.recipient_district);
        if (!district || !assignmentDistricts.value.includes(district))
            errors[`items.${itemIndex}.recipient_district`] =
                'Pilih kabupaten/kota yang tersedia.';
        else if (selectedDistricts.has(district))
            errors[`items.${itemIndex}.recipient_district`] =
                'Kabupaten/kota ini sudah dipilih. Gabungkan dosen dalam satu kelompok.';
        selectedDistricts.add(district);
        if (!item.assignees.length || item.assignees.length > 30)
            errors[`items.${itemIndex}.assignees`] =
                'Isi 1 sampai 30 dosen untuk setiap kabupaten/kota.';
        item.assignees.forEach((person, personIndex) => {
            if (!person.name.trim())
                errors[`items.${itemIndex}.assignees.${personIndex}.name`] =
                    'Nama dosen wajib diisi.';
            else if (person.name.length > 150)
                errors[`items.${itemIndex}.assignees.${personIndex}.name`] =
                    'Nama dosen maksimal 150 karakter.';
            if (!person.office.trim())
                errors[`items.${itemIndex}.assignees.${personIndex}.office`] =
                    'Dinas wajib diisi.';
            else if (person.office.length > 150)
                errors[`items.${itemIndex}.assignees.${personIndex}.office`] =
                    'Dinas maksimal 150 karakter.';
        });
    });
    return applyValidationErrors(errors);
}
function submit() {
    if (form.processing || importing.value || refreshingAcademicOptions.value)
        return;
    if (wizardStep.value < 3) {
        goToStep((wizardStep.value + 1) as 2 | 3);
        return;
    }
    if (!validateSettings() || !validateRecipients()) return;
    requestError.value = '';
    form.transform((data) => {
        const common = {
            type: data.type,
            letter_date: data.letter_date,
            first_sequence: data.first_sequence,
            number_prefix: data.number_prefix,
            signatory_title: data.signatory_title,
            signatory_name: data.signatory_name,
            signatory_number: data.signatory_number,
            include_signature:
                data.include_signature && matchesSignatureOwner.value,
            include_stamp: data.include_stamp && matchesSignatureOwner.value,
        };
        const eventDates = {
            event_start: data.event_start,
            event_end: data.event_end,
        };
        if (data.type === 'pkkmb') {
            return {
                ...common,
                ...eventDates,
                pkkmb_theme: data.pkkmb_theme,
                items: data.items.map((item) => ({
                    participant_name: item.participant_name,
                    participant_nim: item.participant_nim,
                    participant_study_program: item.participant_study_program,
                    participant_faculty: item.participant_faculty,
                    participant_result: item.participant_result,
                    participation_status: item.participation_status,
                })),
            };
        }
        const items = data.items.map((item) => {
            if (data.type === 'assignment') {
                const district = normalizeDistrict(item.recipient_district);
                return {
                    institution_type: item.institution_type,
                    recipient_district: district,
                    assignees: item.assignees.map((person) => ({
                        name: person.name,
                        office: person.office,
                        district,
                    })),
                };
            }
            return {
                institution_id: item.institution_id,
                institution_type: item.institution_type,
                recipient_district: normalizeDistrict(item.recipient_district),
                recipient_address: item.recipient_address,
                institution_name: item.institution_name,
                recipient_position: item.recipient_position,
                recipient_name: item.recipient_name,
            };
        });
        return data.type === 'assignment'
            ? {
                  ...common,
                  ...eventDates,
                  items,
              }
            : { ...common, items };
    }).post(storeLetters.url(), {
        preserveScroll: true,
        forceFormData: false,
        headers: { 'Content-Type': 'application/json' },
        onError: focusValidationError,
        onSuccess: () => {
            if (!localDraft.needsDecision.value) localDraft.clearDraft();
            else localDraft.pause();
        },
        onNetworkError: () => {
            requestError.value =
                'Koneksi terputus. Isian surat tetap tersedia. Periksa koneksi lalu coba simpan kembali.';
            return false;
        },
        onHttpException: () => {
            requestError.value =
                'Surat belum dapat disimpan. Periksa isian atau coba simpan kembali.';
            return false;
        },
    });
}
function updateSignatureDefaults(event: Event) {
    const input = event.target as HTMLInputElement;
    const normalizedName = (name: string) =>
        name.toLowerCase().replace(/[^a-z0-9]/g, '');
    const matchesPmbSigner =
        normalizedName(input.value) ===
        normalizedName(props.defaults.signatoryName);
    form.include_signature =
        matchesPmbSigner && !!props.pmbSignatory.signature_url;
    form.include_stamp = matchesPmbSigner && !!props.pmbSignatory.stamp_url;
}
function preparePkkmbSubmission(): boolean {
    const errors: Record<string, string> = {};
    if (!form.pkkmb_theme.trim())
        errors.pkkmb_theme = 'Tema PKKMB wajib diisi.';
    else if (form.pkkmb_theme.length > 1000)
        errors.pkkmb_theme = 'Tema PKKMB maksimal 1000 karakter.';
    if (!form.event_start)
        errors.event_start = 'Tanggal mulai PKKMB wajib diisi.';
    if (!form.event_end)
        errors.event_end = 'Tanggal selesai PKKMB wajib diisi.';
    else if (form.event_start && form.event_end < form.event_start)
        errors.event_end =
            'Tanggal selesai harus sama dengan atau setelah tanggal mulai.';
    if (!hasValidSequenceRange.value)
        errors.first_sequence =
            'Isi nomor awal 1–99999 dengan rentang seluruh surat tidak melebihi 99999.';
    if (!form.number_prefix.trim())
        errors.number_prefix = 'Kode surat wajib diisi.';
    else if (form.number_prefix.length > 120)
        errors.number_prefix = 'Kode surat maksimal 120 karakter.';
    if (!form.items.length || form.items.length > maxLettersPerSubmission)
        errors.items = `Isi 1–${maxLettersPerSubmission.toLocaleString('id-ID')} mahasiswa.`;
    form.items.forEach((item, index) => {
        for (const field of itemFields.value) {
            if (field.required && !item[field.key].trim())
                errors[`items.${index}.${field.key}`] =
                    `${field.label} wajib diisi.`;
            else if (item[field.key].length > field.max)
                errors[`items.${index}.${field.key}`] =
                    `${field.label} maksimal ${field.max} karakter.`;
            else if (
                isAcademicField(field.key) &&
                !isAcademicSelection(
                    item[field.key],
                    academicOptions(field.key),
                )
            )
                errors[`items.${index}.${field.key}`] =
                    `Pilih ${field.label.toLowerCase()} yang tersedia di Master Data.`;
        }
        if (!Object.hasOwn(participantResultLabels, item.participant_result))
            errors[`items.${index}.participant_result`] =
                'Pilih hasil PKKMB LULUS atau TIDAK LULUS.';
        if (!['attended', 'completed'].includes(item.participation_status))
            errors[`items.${index}.participation_status`] =
                'Surat keterangan hanya untuk mahasiswa yang telah mengikuti PKKMB.';
    });
    return applyValidationErrors(errors);
}

let draftMounted = false;
const draftTextLimits = {
    number_prefix: 120,
    signatory_title: 150,
    signatory_name: 150,
    signatory_number: 100,
    event_start: 10,
    event_end: 10,
    pkkmb_theme: 1000,
} as const;
const itemTextLimits = {
    institution_name: 255,
    recipient_position: 150,
    recipient_name: 150,
    recipient_district: 150,
    recipient_address: 500,
    activity_destination: 255,
    participant_name: 150,
    participant_nim: 50,
    participant_study_program: 150,
    participant_faculty: 150,
} as const;
function isDraftRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}
function currentDraftData() {
    return { version: 1, destination: destination.value, ...form.data() };
}
function applyDraftData(value: unknown, fromSource = false): void {
    if (
        !isDraftRecord(value) ||
        (!fromSource && value.version !== 1) ||
        value.type !== form.type ||
        value.destination !== destination.value ||
        !Array.isArray(value.items) ||
        value.items.length < 1 ||
        value.items.length > maxLettersPerSubmission
    ) {
        throw new Error(
            'Format draft tidak sesuai dengan jenis dan tujuan surat ini.',
        );
    }
    const textValues = {} as Record<keyof typeof draftTextLimits, string>;
    for (const [key, limit] of Object.entries(draftTextLimits)) {
        const text = value[key];
        if (typeof text !== 'string' || text.length > limit)
            throw new Error(
                'Draft berisi isian yang tidak sesuai batas formulir.',
            );
        textValues[key as keyof typeof draftTextLimits] = text;
    }
    if (
        typeof value.include_signature !== 'boolean' ||
        typeof value.include_stamp !== 'boolean' ||
        (!fromSource &&
            (typeof value.letter_date !== 'string' ||
                value.letter_date.length > 10))
    ) {
        throw new Error('Pengaturan draft tidak sesuai dengan formulir.');
    }
    const items: LetterItem[] = value.items.map((raw: unknown) => {
        if (
            !isDraftRecord(raw) ||
            raw.institution_type !== destination.value ||
            !Array.isArray(raw.assignees) ||
            raw.assignees.length > 30
        )
            throw new Error('Data penerima dalam draft tidak sesuai.');
        const item = createLetterItem(destination.value);
        for (const [key, limit] of Object.entries(itemTextLimits)) {
            const text = raw[key];
            if (typeof text !== 'string' || text.length > limit)
                throw new Error(
                    'Data penerima dalam draft melewati batas formulir.',
                );
            item[key as keyof typeof itemTextLimits] = text;
        }
        if (
            raw.institution_id !== null &&
            (!Number.isInteger(raw.institution_id) ||
                Number(raw.institution_id) < 1)
        )
            throw new Error('Data tujuan dalam draft tidak sesuai.');
        item.institution_id = raw.institution_id as number | null;
        if (
            typeof raw.participant_result !== 'string' ||
            !['passed', 'not_passed', ''].includes(raw.participant_result) ||
            typeof raw.participation_status !== 'string' ||
            !['attended', 'completed', 'registered', ''].includes(
                raw.participation_status,
            )
        )
            throw new Error('Data PKKMB dalam draft tidak sesuai.');
        item.participant_result =
            raw.participant_result as LetterItem['participant_result'];
        item.participation_status =
            raw.participation_status as LetterItem['participation_status'];
        item.assignees = raw.assignees.map((person: unknown) => {
            if (
                !isDraftRecord(person) ||
                typeof person.name !== 'string' ||
                person.name.length > 150 ||
                typeof person.office !== 'string' ||
                person.office.length > 150 ||
                typeof person.district !== 'string' ||
                person.district.length > 150
            )
                throw new Error('Data dosen dalam draft tidak sesuai.');
            return {
                name: person.name,
                office: person.office,
                district: person.district,
            };
        });
        return form.type === 'pkkmb' ? normalizePkkmbAcademicItem(item) : item;
    });
    if (
        form.type !== 'pkkmb' &&
        textValues.number_prefix.trim() === 'PPMBPM-UNUHA/TU.2.1'
    ) {
        textValues.number_prefix = props.defaults.numberPrefix;
    }
    const restoredLetterDate = fromSource
        ? props.today
        : (value.letter_date as string);
    Object.assign(form, textValues, {
        letter_date: restoredLetterDate,
        first_sequence: nextLetterSequenceForDate(
            restoredLetterDate,
            props.nextSequencesByYear,
        ),
        include_signature: value.include_signature,
        include_stamp: value.include_stamp,
        items,
    });
    const matchesPmb =
        form.signatory_name.toLowerCase().replace(/[^a-z0-9]/g, '') ===
        props.defaults.signatoryName.toLowerCase().replace(/[^a-z0-9]/g, '');
    if (!matchesPmb) {
        form.include_signature = false;
        form.include_stamp = false;
    }
    drafts.set(draftKey(), cloneLetterItems(items));
    openItems.value = new Set([0]);
    form.clearErrors();
    requestError.value = '';
    wizardStep.value = 1;
    draftRestored.value = !fromSource;
    if (!fromSource) sourceError.value = '';
}
const localDraft = useLocalLetterDraft({
    key: computed(() => draftKey()),
    getData: currentDraftData,
    applyData: (value: unknown) => applyDraftData(value),
    isMeaningful: (data) =>
        data.items.some((item) => !isBlankItem(item)) ||
        data.pkkmb_theme.trim() !== '' ||
        data.letter_date !== props.today ||
        data.first_sequence !==
            nextLetterSequenceForDate(
                data.letter_date,
                props.nextSequencesByYear,
            ) ||
        data.number_prefix !==
            (data.type === 'pkkmb'
                ? props.defaults.pkkmbNumberPrefix
                : props.defaults.numberPrefix) ||
        data.event_start !== props.today ||
        data.event_end !== props.today ||
        data.signatory_name !== props.defaults.signatoryName ||
        data.signatory_title !== props.defaults.signatoryTitle ||
        data.signatory_number !== props.defaults.signatoryNumber ||
        !data.include_signature ||
        !data.include_stamp,
});
watch(
    () => props.sourceDraft?.id,
    () => {
        const source = props.sourceDraft;
        if (!source) {
            if (draftMounted) localDraft.resume();
            return;
        }
        localDraft.pause();
        try {
            applyDraftData(source.data, true);
            sourceError.value = '';
            if (draftMounted && !localDraft.needsDecision.value)
                localDraft.resume();
        } catch (error) {
            sourceError.value =
                error instanceof Error
                    ? error.message
                    : 'Surat sumber belum dapat disalin.';
        }
    },
    { immediate: true },
);
onMounted(() => {
    draftMounted = true;
    if (
        props.sourceDraft &&
        !sourceError.value &&
        !localDraft.needsDecision.value
    )
        localDraft.resume();
});
function useCurrentDraft(): void {
    if (
        formBusy.value ||
        (localDraft.needsDecision.value && !localDraft.discardDraft())
    )
        return;
    localDraft.resume();
    localDraft.saveDraft();
}
function discardSavedDraft(): void {
    if (formBusy.value || !localDraft.discardDraft()) return;
    if (!sourceError.value) localDraft.resume();
}
</script>

<template>
    <main class="sisurat-page pb-28">
        <Head :title="`${pageTitle} · ${targetLabel}`" />
        <div class="mx-auto flex w-full max-w-6xl flex-col gap-6">
            <header class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-start gap-3">
                    <Link
                        :href="sourceDraft ? lettersIndex() : chooseLetter()"
                        class="sisurat-secondary-action shrink-0 px-3"
                    >
                        <ArrowLeft class="size-4" aria-hidden="true" /><span
                            class="sr-only"
                            >{{
                                sourceDraft
                                    ? 'Kembali ke arsip surat'
                                    : 'Kembali ke pilihan surat'
                            }}</span
                        >
                    </Link>
                    <div>
                        <p class="sisurat-kicker">
                            Buat surat · {{ targetLabel }}
                        </p>
                        <h1 class="sisurat-title mt-1">{{ pageTitle }}</h1>
                        <p class="sisurat-subtitle mt-2">
                            {{
                                isSimpleAssignment
                                    ? 'Pilih kabupaten/kota, isi dosen, dan tentukan periode sosialisasi.'
                                    : 'Lengkapi data sekali, hasilkan surat siap cetak untuk setiap tujuan.'
                            }}
                        </p>
                    </div>
                </div>
                <span
                    class="inline-flex items-center gap-2 rounded-full border border-border bg-card px-3 py-2 text-xs font-medium text-primary"
                    ><FileText class="size-4" aria-hidden="true" />{{
                        form.items.length
                    }}
                    surat dalam daftar</span
                >
            </header>
            <LetterSteps
                :model-value="wizardStep"
                :busy="formBusy"
                @update:model-value="goToStep"
            />
            <div
                v-if="sourceDraft && !draftRestored"
                class="rounded-2xl border border-primary/20 bg-primary/5 p-4 sm:p-5"
            >
                <p class="text-sm font-semibold">
                    Salinan dari {{ sourceDraft.label }}
                </p>
                <p class="mt-1 text-sm leading-6 text-muted-foreground">
                    Isi dan penerima disalin. Nomor awal mengikuti nomor terbaru
                    untuk tahun hari ini dan tanggal surat memakai hari ini.
                    Periksa kembali periode kegiatan, penerima, dan
                    penandatangan.
                </p>
            </div>
            <LetterDraftBanner
                :has-saved-draft="localDraft.hasSavedDraft.value"
                :needs-decision="localDraft.needsDecision.value"
                :timestamp="localDraft.timestamp.value"
                :error="localDraft.error.value"
                :available="localDraft.available.value"
                :disabled="formBusy"
                @restore="localDraft.restoreDraft"
                @discard="discardSavedDraft"
                @save="useCurrentDraft"
            />
            <div
                v-if="
                    sourceDraft &&
                    !draftRestored &&
                    localDraft.needsDecision.value &&
                    !sourceError
                "
                class="flex flex-wrap items-center gap-3 text-sm"
            >
                <p class="text-muted-foreground">
                    Draf lama tetap tersimpan sampai Anda memilih.
                </p>
                <Button
                    type="button"
                    variant="outline"
                    :disabled="formBusy"
                    @click="useCurrentDraft"
                >
                    Ganti draf tersimpan dengan salinan ini
                </Button>
            </div>
            <p
                v-if="draftRestored"
                class="rounded-xl bg-accent/60 px-4 py-3 text-sm leading-6 text-muted-foreground"
            >
                Draf dilanjutkan. Tanggal surat dan kegiatan mengikuti draf;
                nomor awal diperbarui mengikuti nomor terbaru untuk tahun
                tanggal draf. Periksa kembali sebelum menyimpan.
            </p>
            <p
                v-if="sourceError"
                role="alert"
                class="rounded-xl border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive"
            >
                {{ sourceError }}
            </p>
            <form novalidate @submit.prevent="submit">
                <fieldset
                    :disabled="
                        form.processing ||
                        importing ||
                        refreshingAcademicOptions
                    "
                    class="flex min-w-0 flex-col gap-6"
                >
                    <div
                        v-show="wizardStep === 1"
                        id="letter-step-1"
                        class="flex flex-col gap-6"
                        tabindex="-1"
                    >
                        <section id="jenis-surat" :class="sectionClass">
                            <h2 :class="headingClass">
                                Jenis dan tujuan surat
                            </h2>
                            <p :class="hintClass">
                                Kalimat surat mengikuti pengaturan
                                <Link
                                    :href="letterTemplatesIndex()"
                                    class="font-semibold text-primary underline underline-offset-4"
                                    >Master Data → Isi Surat</Link
                                >.
                            </p>
                            <div
                                class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                            >
                                <button
                                    v-for="option in typeOptions"
                                    :key="option.value"
                                    type="button"
                                    @click="navigateToForm(option.value)"
                                    :aria-pressed="form.type === option.value"
                                    :class="[
                                        'relative rounded-xl border p-4 text-left transition-colors duration-200',
                                        form.type === option.value
                                            ? 'border-primary bg-accent ring-1 ring-primary/20'
                                            : 'border-border hover:border-primary/40 hover:bg-muted/60',
                                    ]"
                                >
                                    <span
                                        class="block pr-5 font-semibold text-foreground"
                                        >{{
                                            letterTypeLabels[option.value]
                                        }}</span
                                    ><span
                                        class="mt-2 block text-sm leading-6 text-muted-foreground"
                                        >{{ option.description }}</span
                                    >
                                    <Check
                                        v-if="form.type === option.value"
                                        class="absolute top-4 right-3 size-4 text-primary"
                                        aria-hidden="true"
                                    />
                                </button>
                                <Link
                                    :href="general()"
                                    class="rounded-xl border border-border p-4 transition-colors duration-200 hover:border-primary/40 hover:bg-muted/60"
                                >
                                    <span
                                        class="block font-semibold text-foreground"
                                        >Surat Umum</span
                                    >
                                    <span
                                        class="mt-2 block text-sm leading-6 text-muted-foreground"
                                        >Perihal, penerima, dan isi bebas.</span
                                    >
                                    <span
                                        class="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-primary"
                                        >Buka formulir
                                        <ArrowRight
                                            class="size-3.5"
                                            aria-hidden="true"
                                    /></span>
                                </Link>
                            </div>
                            <div
                                v-if="form.type !== 'pkkmb'"
                                class="mt-5 border-t border-border/60 pt-5"
                            >
                                <h3 :class="labelClass">Tujuan surat</h3>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <button
                                        v-for="target in [
                                            'school',
                                            'pesantren',
                                        ] as const"
                                        :key="target"
                                        type="button"
                                        @click="
                                            navigateToForm(form.type, target)
                                        "
                                        :aria-pressed="destination === target"
                                        :class="[
                                            'min-h-11 rounded-xl border px-4 py-3 text-left font-semibold',
                                            destination === target
                                                ? 'border-primary bg-accent text-primary ring-1 ring-primary/20'
                                                : 'border-border text-foreground transition-colors hover:bg-muted',
                                        ]"
                                    >
                                        Ke {{ institutionLabels[target] }}
                                    </button>
                                </div>
                                <p :class="hintClass">
                                    Sapaan dan isi surat mengikuti tujuan ini.
                                    Draf setiap pilihan disimpan pada browser
                                    dan akun ini.
                                </p>
                            </div>
                        </section>
                        <section id="detail-surat" :class="sectionClass">
                            <h2 :class="headingClass">Tanggal dan penomoran</h2>
                            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                                <div>
                                    <label for="letter_date" :class="labelClass"
                                        >Tanggal surat</label
                                    ><input
                                        id="letter_date"
                                        v-model="form.letter_date"
                                        :aria-invalid="
                                            Boolean(errorFor('letter_date'))
                                        "
                                        aria-describedby="error_letter_date"
                                        type="date"
                                        required
                                        :class="inputClass"
                                    />
                                    <p
                                        v-if="errorFor('letter_date')"
                                        id="error_letter_date"
                                        class="mt-1 text-sm text-destructive"
                                    >
                                        {{ errorFor('letter_date') }}
                                    </p>
                                </div>
                                <div>
                                    <label
                                        for="first_sequence"
                                        :class="labelClass"
                                        >Nomor urut awal</label
                                    ><input
                                        id="first_sequence"
                                        v-model.number="form.first_sequence"
                                        :aria-invalid="
                                            Boolean(errorFor('first_sequence'))
                                        "
                                        aria-describedby="numbering-year-hint error_first_sequence"
                                        type="number"
                                        min="1"
                                        max="99999"
                                        required
                                        :class="inputClass"
                                    />
                                    <p
                                        v-if="errorFor('first_sequence')"
                                        id="error_first_sequence"
                                        class="mt-1 text-sm text-destructive"
                                    >
                                        {{ errorFor('first_sequence') }}
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
                                        for="number_prefix"
                                        :class="labelClass"
                                        >Kode surat</label
                                    ><input
                                        id="number_prefix"
                                        v-model="form.number_prefix"
                                        :aria-invalid="
                                            Boolean(errorFor('number_prefix'))
                                        "
                                        aria-describedby="error_number_prefix"
                                        type="text"
                                        maxlength="120"
                                        required
                                        :class="inputClass"
                                        placeholder="Isi kode surat sesuai ketentuan"
                                    />
                                    <p
                                        v-if="errorFor('number_prefix')"
                                        id="error_number_prefix"
                                        class="mt-1 text-sm text-destructive"
                                    >
                                        {{ errorFor('number_prefix') }}
                                    </p>
                                </div>
                            </div>
                            <p id="numbering-year-hint" :class="hintClass">
                                <template v-if="numberingYear !== null"
                                    >Nomor berikutnya untuk tahun
                                    {{ numberingYear }}:
                                    {{
                                        String(nextSequenceSuggestion).padStart(
                                            3,
                                            '0',
                                        )
                                    }}.
                                    {{
                                        nextSequenceSuggestion === 1
                                            ? 'Tahun ini belum memiliki nomor terpakai, sehingga dimulai dari 001.'
                                            : 'Nomor berlanjut setelah nomor yang sudah terpakai pada tahun ini.'
                                    }}</template
                                >
                                <template v-else
                                    >Pilih tanggal surat yang valid untuk
                                    melihat nomor berikutnya.</template
                                >
                                {{
                                    isSimpleAssignment
                                        ? 'Setiap kabupaten/kota mendapat satu nomor surat.'
                                        : 'Setiap tujuan atau mahasiswa mendapat satu nomor surat.'
                                }}
                            </p>
                            <p :class="hintClass">
                                Penomoran dimulai dari 001 pada tahun baru yang
                                belum memiliki surat. Saat tahun tanggal surat
                                berubah, nomor awal mengikuti tahun tersebut;
                                perubahan hari atau bulan dalam tahun yang sama
                                tetap mempertahankan nomor yang Anda isi.
                            </p>
                            <details
                                class="mt-5 border-t border-border/60 pt-4"
                                id="signatory-settings"
                            >
                                <summary
                                    class="min-h-11 cursor-pointer font-semibold text-primary dark:text-foreground"
                                >
                                    Penandatangan:
                                    {{ form.signatory_name || 'lengkapi nama' }}
                                </summary>
                                <div class="mt-3 grid gap-4 sm:grid-cols-3">
                                    <div>
                                        <label
                                            for="signatory_title"
                                            :class="labelClass"
                                            >Jabatan</label
                                        ><input
                                            id="signatory_title"
                                            v-model="form.signatory_title"
                                            :aria-invalid="
                                                Boolean(
                                                    errorFor('signatory_title'),
                                                )
                                            "
                                            aria-describedby="error_signatory_title"
                                            type="text"
                                            required
                                            maxlength="150"
                                            :class="inputClass"
                                        />
                                        <p
                                            v-if="errorFor('signatory_title')"
                                            id="error_signatory_title"
                                            class="mt-1 text-sm text-destructive"
                                        >
                                            {{ errorFor('signatory_title') }}
                                        </p>
                                    </div>
                                    <div>
                                        <label
                                            for="signatory_name"
                                            :class="labelClass"
                                            >Nama penandatangan</label
                                        ><input
                                            id="signatory_name"
                                            v-model="form.signatory_name"
                                            :aria-invalid="
                                                Boolean(
                                                    errorFor('signatory_name'),
                                                )
                                            "
                                            aria-describedby="error_signatory_name"
                                            @input="updateSignatureDefaults"
                                            type="text"
                                            required
                                            maxlength="150"
                                            :class="inputClass"
                                        />
                                        <p
                                            v-if="errorFor('signatory_name')"
                                            id="error_signatory_name"
                                            class="mt-1 text-sm text-destructive"
                                        >
                                            {{ errorFor('signatory_name') }}
                                        </p>
                                    </div>
                                    <div>
                                        <label
                                            for="signatory_number"
                                            :class="labelClass"
                                            >NIP / identitas (opsional)</label
                                        ><input
                                            id="signatory_number"
                                            v-model="form.signatory_number"
                                            :aria-invalid="
                                                Boolean(
                                                    errorFor(
                                                        'signatory_number',
                                                    ),
                                                )
                                            "
                                            aria-describedby="error_signatory_number"
                                            type="text"
                                            maxlength="100"
                                            :class="inputClass"
                                        />
                                        <p
                                            v-if="errorFor('signatory_number')"
                                            id="error_signatory_number"
                                            class="mt-1 text-sm text-destructive"
                                        >
                                            {{ errorFor('signatory_number') }}
                                        </p>
                                    </div>
                                </div>
                                <div
                                    class="mt-5 rounded-xl border border-border bg-muted/40 p-4"
                                >
                                    <h3
                                        class="text-sm font-semibold text-foreground"
                                    >
                                        Tanda tangan &amp; cap otomatis
                                    </h3>
                                    <p
                                        class="mt-1 text-xs leading-6 text-muted-foreground"
                                    >
                                        Gambar PMB akan ditambahkan di bagian
                                        penandatangan setiap surat. Pilihan ini
                                        tersimpan bersama batch.
                                    </p>
                                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                        <label
                                            class="flex min-h-12 cursor-pointer items-center gap-3 rounded-lg border border-border bg-card p-3 text-sm font-medium"
                                        >
                                            <input
                                                id="include_signature"
                                                v-model="form.include_signature"
                                                type="checkbox"
                                                :disabled="
                                                    !matchesSignatureOwner ||
                                                    !pmbSignatory.signature_url
                                                "
                                                :aria-invalid="
                                                    Boolean(
                                                        errorFor(
                                                            'include_signature',
                                                        ),
                                                    )
                                                "
                                                class="size-4 accent-[#005A36]"
                                            />
                                            Tanda tangan PMB
                                            <img
                                                v-if="
                                                    pmbSignatory.signature_url
                                                "
                                                :src="
                                                    pmbSignatory.signature_url
                                                "
                                                alt="Contoh tanda tangan PMB"
                                                class="ml-auto h-10 w-16 object-contain mix-blend-multiply dark:rounded dark:bg-white"
                                            />
                                        </label>
                                        <label
                                            class="flex min-h-12 cursor-pointer items-center gap-3 rounded-lg border border-border bg-card p-3 text-sm font-medium"
                                        >
                                            <input
                                                id="include_stamp"
                                                v-model="form.include_stamp"
                                                type="checkbox"
                                                :disabled="
                                                    !matchesSignatureOwner ||
                                                    !pmbSignatory.stamp_url
                                                "
                                                :aria-invalid="
                                                    Boolean(
                                                        errorFor(
                                                            'include_stamp',
                                                        ),
                                                    )
                                                "
                                                class="size-4 accent-[#005A36]"
                                            />
                                            Cap PMB
                                            <img
                                                v-if="pmbSignatory.stamp_url"
                                                :src="pmbSignatory.stamp_url"
                                                alt="Contoh cap PMB"
                                                class="ml-auto size-10 object-contain dark:rounded dark:bg-white"
                                            />
                                        </label>
                                    </div>
                                    <p
                                        class="mt-2 text-xs leading-6 text-muted-foreground"
                                    >
                                        Tanda tangan dan cap otomatis tersedia
                                        untuk {{ defaults.signatoryName }}.
                                    </p>
                                    <p
                                        v-if="
                                            errorFor('include_signature') ||
                                            errorFor('include_stamp')
                                        "
                                        class="mt-1 text-sm text-destructive"
                                    >
                                        {{
                                            errorFor('include_signature') ||
                                            errorFor('include_stamp')
                                        }}
                                    </p>
                                </div>
                            </details>
                        </section>
                        <section
                            v-if="form.type !== 'permission'"
                            :class="sectionClass"
                        >
                            <h2 :class="headingClass">
                                Periode
                                {{
                                    form.type === 'pkkmb'
                                        ? 'PKKMB'
                                        : 'sosialisasi'
                                }}
                            </h2>
                            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="event_start" :class="labelClass"
                                        >Tanggal mulai</label
                                    ><input
                                        id="event_start"
                                        v-model="form.event_start"
                                        :aria-invalid="
                                            Boolean(errorFor('event_start'))
                                        "
                                        aria-describedby="error_event_start"
                                        type="date"
                                        required
                                        :class="inputClass"
                                    />
                                    <p
                                        v-if="errorFor('event_start')"
                                        id="error_event_start"
                                        class="mt-1 text-sm text-destructive"
                                    >
                                        {{ errorFor('event_start') }}
                                    </p>
                                </div>
                                <div>
                                    <label for="event_end" :class="labelClass"
                                        >Tanggal selesai</label
                                    ><input
                                        id="event_end"
                                        v-model="form.event_end"
                                        :aria-invalid="
                                            Boolean(errorFor('event_end'))
                                        "
                                        aria-describedby="error_event_end"
                                        type="date"
                                        :min="form.event_start"
                                        required
                                        :class="inputClass"
                                    />
                                    <p
                                        v-if="errorFor('event_end')"
                                        id="error_event_end"
                                        class="mt-1 text-sm text-destructive"
                                    >
                                        {{ errorFor('event_end') }}
                                    </p>
                                </div>
                                <template v-if="form.type === 'pkkmb'">
                                    <div class="sm:col-span-2">
                                        <label
                                            for="pkkmb_theme"
                                            :class="labelClass"
                                            >Tema PKKMB</label
                                        ><textarea
                                            id="pkkmb_theme"
                                            v-model="form.pkkmb_theme"
                                            rows="3"
                                            required
                                            maxlength="1000"
                                            :class="inputClass"
                                            :aria-invalid="
                                                Boolean(form.errors.pkkmb_theme)
                                            "
                                            aria-describedby="pkkmb_theme_hint pkkmb_theme_error"
                                            placeholder="Tema kegiatan sesuai pelaksanaan PKKMB"
                                        />
                                        <p
                                            id="pkkmb_theme_hint"
                                            :class="hintClass"
                                        >
                                            Tema dan periode pelaksanaan
                                            digunakan pada surat setiap
                                            mahasiswa.
                                        </p>
                                        <p
                                            v-if="form.errors.pkkmb_theme"
                                            id="pkkmb_theme_error"
                                            class="mt-1 text-sm text-destructive"
                                        >
                                            {{ form.errors.pkkmb_theme }}
                                        </p>
                                    </div>
                                </template>
                            </div>
                        </section>
                    </div>
                    <div
                        v-show="wizardStep === 2"
                        id="letter-step-2"
                        class="flex flex-col gap-6"
                        tabindex="-1"
                    >
                        <AssignmentFormSection
                            v-if="isSimpleAssignment"
                            :key="destination"
                            section-mode="data"
                            v-model:items="form.items"
                            v-model:event-start="form.event_start"
                            v-model:event-end="form.event_end"
                            v-model:first-sequence="form.first_sequence"
                            v-model:number-prefix="form.number_prefix"
                            :number-preview="assignmentNumberPreview"
                            :destination="destination"
                            :districts="assignmentDistricts"
                            :staff-members="staffMembers"
                            :errors="form.errors"
                            :busy="formBusy"
                            @destination-change="
                                navigateToForm('assignment', $event)
                            "
                            @clear-errors="form.clearErrors()"
                            @clear-number-error="
                                form.clearErrors('first_sequence')
                            "
                            @clear-code-error="
                                form.clearErrors('number_prefix')
                            "
                        />
                        <template v-if="!isSimpleAssignment">
                            <section
                                v-if="form.type === 'pkkmb'"
                                class="rounded-2xl border border-primary/15 bg-accent/40 p-5"
                                aria-labelledby="academic-categories-heading"
                            >
                                <div
                                    class="flex flex-wrap items-start justify-between gap-4"
                                >
                                    <div>
                                        <h2
                                            id="academic-categories-heading"
                                            class="text-sm font-semibold"
                                        >
                                            Kategori program studi dan fakultas
                                        </h2>
                                        <p
                                            class="mt-2 text-sm leading-6 text-muted-foreground"
                                        >
                                            Pilih kedua kategori secara terpisah
                                            pada setiap mahasiswa. Setelah
                                            menambahkan kategori di tab baru,
                                            muat ulang kategori di sini agar
                                            draft tetap tersimpan.
                                        </p>
                                        <p
                                            v-if="!hasAcademicOptions"
                                            class="mt-2 text-sm font-medium text-destructive"
                                            role="status"
                                        >
                                            {{
                                                !studyPrograms.length
                                                    ? 'Kategori program studi belum tersedia. '
                                                    : ''
                                            }}
                                            {{
                                                !faculties.length
                                                    ? 'Kategori fakultas belum tersedia. '
                                                    : ''
                                            }}
                                            Tambahkan kategori melalui Master
                                            Data sebelum membuat surat.
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        :class="actionClass"
                                        :disabled="
                                            form.processing ||
                                            importing ||
                                            refreshingAcademicOptions
                                        "
                                        :aria-busy="refreshingAcademicOptions"
                                        @click="refreshAcademicOptions"
                                    >
                                        <RefreshCw
                                            class="size-4"
                                            :class="{
                                                'animate-spin motion-reduce:animate-none':
                                                    refreshingAcademicOptions,
                                            }"
                                            aria-hidden="true"
                                        />
                                        {{
                                            refreshingAcademicOptions
                                                ? 'Memuat kategori…'
                                                : 'Muat ulang kategori'
                                        }}
                                    </button>
                                </div>
                                <div
                                    class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-sm"
                                >
                                    <Link
                                        :href="studyProgramsIndex()"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="font-semibold text-primary underline underline-offset-4"
                                    >
                                        Kelola Program Studi (tab baru)
                                    </Link>
                                    <Link
                                        :href="facultiesIndex()"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="font-semibold text-primary underline underline-offset-4"
                                    >
                                        Kelola Fakultas (tab baru)
                                    </Link>
                                </div>
                                <p
                                    v-if="academicOptionsError"
                                    class="mt-3 text-sm text-destructive"
                                    role="alert"
                                >
                                    {{ academicOptionsError }}
                                </p>
                            </section>
                            <InstitutionCatalogPicker
                                v-if="form.type !== 'pkkmb'"
                                id="data-surat"
                                :institutions="institutions"
                                :destination="destination"
                                :existing-items="form.items"
                                :busy="form.processing || importing"
                                @apply="applyCatalogInstitutions"
                            />
                            <p
                                v-if="catalogMessage && form.type !== 'pkkmb'"
                                class="rounded-xl border border-primary/15 bg-accent px-5 py-4 text-sm leading-7 text-accent-foreground"
                                role="status"
                            >
                                {{ catalogMessage }}
                            </p>
                            <section
                                :id="
                                    form.type === 'pkkmb'
                                        ? 'data-surat'
                                        : undefined
                                "
                                :class="sectionClass"
                            >
                                <div
                                    class="flex flex-wrap items-start justify-between gap-3"
                                >
                                    <div>
                                        <h2 :class="headingClass">
                                            {{
                                                form.type === 'pkkmb'
                                                    ? 'Masukkan data mahasiswa'
                                                    : 'Impor atau isi tujuan secara manual'
                                            }}
                                        </h2>
                                        <p :class="hintClass">
                                            Isi manual atau impor CSV / XLSX.
                                            Maksimal
                                            {{
                                                maxLettersPerSubmission.toLocaleString(
                                                    'id-ID',
                                                )
                                            }}
                                            surat sekali pembuatan.
                                        </p>
                                        <p :class="hintClass">
                                            {{
                                                form.type === 'pkkmb'
                                                    ? 'Gunakan kolom nama_mahasiswa, nim, program_studi, fakultas, dan hasil (LULUS / TIDAK LULUS). CSV lama tetap bisa dimuat; lengkapi fakultas sebelum menyimpan. Simpan NIM sebagai teks di Excel agar nol di depan tetap utuh.'
                                                    : 'Workbook rekap dibaca dari sheet UNTK LPJ PMB. Data hanya dimuat untuk tujuan Sekolah atau Pondok yang dipilih.'
                                            }}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        :class="actionClass"
                                        @click="downloadCsvTemplate"
                                    >
                                        <Download
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                        Unduh format CSV
                                    </button>
                                </div>
                                <div
                                    class="mt-4 grid gap-4 sm:grid-cols-[1fr_auto]"
                                >
                                    <div>
                                        <label
                                            for="import_file"
                                            :class="labelClass"
                                            >Impor data
                                            {{
                                                form.type === 'pkkmb'
                                                    ? 'mahasiswa'
                                                    : institutionLabels[
                                                          destination
                                                      ].toLowerCase()
                                            }}</label
                                        ><input
                                            id="import_file"
                                            type="file"
                                            accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                            @change="importDataFile"
                                            :class="inputClass"
                                        />
                                    </div>
                                    <div>
                                        <label
                                            for="import_mode"
                                            :class="labelClass"
                                            >Cara memuat</label
                                        ><select
                                            id="import_mode"
                                            v-model="importMode"
                                            :class="inputClass"
                                        >
                                            <option value="append">
                                                Tambahkan ke daftar
                                            </option>
                                            <option value="replace">
                                                Ganti daftar
                                            </option>
                                        </select>
                                    </div>
                                </div>
                                <p
                                    v-if="importing || csvMessage"
                                    class="mt-3 rounded-lg bg-muted/60 px-3 py-2 text-sm text-primary dark:bg-background/50 dark:text-foreground"
                                    role="status"
                                >
                                    {{
                                        importing ? 'Membaca file…' : csvMessage
                                    }}
                                </p>
                            </section>
                            <details
                                v-for="(item, itemIndex) in form.items"
                                :key="`${draftKey()}:${itemIndex}`"
                                :class="[sectionClass, 'group/item']"
                                :open="isItemExpanded(itemIndex)"
                                @toggle="toggleItem($event, itemIndex)"
                            >
                                <summary
                                    class="flex cursor-pointer list-none items-center justify-between gap-3 [&::-webkit-details-marker]:hidden"
                                >
                                    <div class="min-w-0">
                                        <h2 :class="headingClass">
                                            {{
                                                form.type === 'pkkmb'
                                                    ? 'Mahasiswa'
                                                    : 'Tujuan surat'
                                            }}
                                            {{ itemIndex + 1 }}
                                        </h2>
                                        <p
                                            class="mt-1 truncate text-sm font-medium text-primary"
                                        >
                                            {{
                                                item.participant_name ||
                                                item.institution_name ||
                                                item.activity_destination ||
                                                'Klik untuk melengkapi data'
                                            }}
                                        </p>
                                        <p :class="hintClass">
                                            Nomor
                                            {{
                                                String(
                                                    Number(
                                                        form.first_sequence,
                                                    ) + itemIndex,
                                                ).padStart(3, '0')
                                            }}
                                            / {{ form.number_prefix || 'KODE' }}
                                        </p>
                                        <span
                                            v-if="itemHasError(itemIndex)"
                                            class="mt-2 inline-block rounded-full bg-destructive/10 px-2 py-1 text-xs font-medium text-destructive"
                                            >Periksa data tujuan ini</span
                                        >
                                    </div>
                                    <ChevronDown
                                        class="size-5 shrink-0 text-muted-foreground transition-transform duration-200 group-open/item:rotate-180"
                                        aria-hidden="true"
                                    />
                                </summary>
                                <div v-if="isItemExpanded(itemIndex)">
                                    <div
                                        class="mt-4 flex justify-end border-t border-border pt-4"
                                    >
                                        <button
                                            v-if="form.items.length > 1"
                                            type="button"
                                            class="inline-flex min-h-11 items-center gap-2 rounded-lg px-3 text-sm font-semibold text-red-700 hover:bg-red-50"
                                            @click="removeItem(itemIndex)"
                                            :aria-label="`Hapus data ${itemIndex + 1}`"
                                        >
                                            <Trash2
                                                class="size-4"
                                                aria-hidden="true"
                                            />
                                            Hapus
                                        </button>
                                    </div>
                                    <div
                                        v-if="form.type !== 'pkkmb'"
                                        class="mt-4 rounded-xl border border-border bg-muted/35 p-4"
                                    >
                                        <label
                                            :for="`catalog_institution_${itemIndex}`"
                                            :class="labelClass"
                                            >Pilih
                                            {{
                                                destination === 'school'
                                                    ? 'sekolah'
                                                    : 'pondok'
                                            }}
                                            dari basis data</label
                                        >
                                        <select
                                            :id="`catalog_institution_${itemIndex}`"
                                            :value="item.institution_id ?? ''"
                                            :class="inputClass"
                                            :aria-invalid="
                                                !!errorFor(
                                                    `items.${itemIndex}.institution_id`,
                                                )
                                            "
                                            :aria-describedby="
                                                errorFor(
                                                    `items.${itemIndex}.institution_id`,
                                                )
                                                    ? `error_institution_id_${itemIndex}`
                                                    : undefined
                                            "
                                            @change="
                                                selectItemInstitution(
                                                    item,
                                                    $event,
                                                )
                                            "
                                        >
                                            <option value="">
                                                Isi tujuan secara manual
                                            </option>
                                            <option
                                                v-for="institution in currentInstitutions"
                                                :key="institution.id"
                                                :value="institution.id"
                                            >
                                                {{ institution.name }} ·
                                                {{ institution.district
                                                }}{{
                                                    institution.address
                                                        ? ` · ${institution.address}`
                                                        : ''
                                                }}
                                            </option>
                                        </select>
                                        <p
                                            v-if="
                                                errorFor(
                                                    `items.${itemIndex}.institution_id`,
                                                )
                                            "
                                            :id="`error_institution_id_${itemIndex}`"
                                            class="mt-1 text-sm text-red-700"
                                        >
                                            {{
                                                errorFor(
                                                    `items.${itemIndex}.institution_id`,
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="mt-2 text-xs leading-6 text-muted-foreground"
                                        >
                                            Nama dan kabupaten/kota terisi
                                            otomatis saat memilih dari basis
                                            data. Anda tetap dapat menyunting
                                            data di bawah.
                                        </p>
                                    </div>
                                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                        <div
                                            v-for="field in itemFields"
                                            :key="field.key"
                                        >
                                            <label
                                                :for="`${field.key}_${itemIndex}`"
                                                :class="labelClass"
                                                >{{ field.label }}</label
                                            >
                                            <template
                                                v-if="
                                                    form.type === 'pkkmb' &&
                                                    isAcademicField(field.key)
                                                "
                                            >
                                                <select
                                                    :id="`${field.key}_${itemIndex}`"
                                                    v-model="item[field.key]"
                                                    required
                                                    :class="inputClass"
                                                    :aria-invalid="
                                                        Boolean(
                                                            errorFor(
                                                                `items.${itemIndex}.${field.key}`,
                                                            ),
                                                        )
                                                    "
                                                    :aria-describedby="`hint_${field.key}_${itemIndex} error_${field.key}_${itemIndex}`"
                                                    @change="
                                                        form.clearErrors(
                                                            `items.${itemIndex}.${field.key}`,
                                                        )
                                                    "
                                                >
                                                    <option value="" disabled>
                                                        {{
                                                            field.key ===
                                                            'participant_faculty'
                                                                ? 'Pilih fakultas'
                                                                : 'Pilih program studi'
                                                        }}
                                                    </option>
                                                    <option
                                                        v-if="
                                                            item[field.key] &&
                                                            !isAcademicSelection(
                                                                item[field.key],
                                                                academicOptions(
                                                                    field.key,
                                                                ),
                                                            )
                                                        "
                                                        :value="item[field.key]"
                                                        disabled
                                                    >
                                                        {{ item[field.key] }}
                                                        (belum tersedia)
                                                    </option>
                                                    <option
                                                        v-for="category in academicOptions(
                                                            field.key,
                                                        )"
                                                        :key="category.id"
                                                        :value="category.name"
                                                    >
                                                        {{ category.name }}
                                                    </option>
                                                </select>
                                                <p
                                                    :id="`hint_${field.key}_${itemIndex}`"
                                                    class="mt-2 text-xs leading-6 text-muted-foreground"
                                                >
                                                    <span
                                                        v-if="
                                                            !academicOptions(
                                                                field.key,
                                                            ).length
                                                        "
                                                        >Kategori
                                                        {{
                                                            field.label.toLowerCase()
                                                        }}
                                                        belum tersedia.
                                                    </span>
                                                    Pilih kategori yang tersedia
                                                    atau tambahkan melalui
                                                    Master Data.
                                                    <Link
                                                        :href="
                                                            field.key ===
                                                            'participant_faculty'
                                                                ? facultiesIndex()
                                                                : studyProgramsIndex()
                                                        "
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        class="font-semibold text-primary underline underline-offset-4"
                                                    >
                                                        {{
                                                            field.key ===
                                                            'participant_faculty'
                                                                ? 'Kelola Fakultas'
                                                                : 'Kelola Program Studi'
                                                        }}
                                                        (tab baru)
                                                    </Link>
                                                </p>
                                            </template>
                                            <input
                                                v-else
                                                :id="`${field.key}_${itemIndex}`"
                                                v-model="item[field.key]"
                                                type="text"
                                                :required="field.required"
                                                :maxlength="field.max"
                                                :placeholder="field.placeholder"
                                                :class="inputClass"
                                                @input="
                                                    handleItemTextInput(
                                                        item,
                                                        field.key,
                                                        $event,
                                                    )
                                                "
                                                :aria-invalid="
                                                    !!errorFor(
                                                        `items.${itemIndex}.${field.key}`,
                                                    )
                                                "
                                                :aria-describedby="
                                                    errorFor(
                                                        `items.${itemIndex}.${field.key}`,
                                                    )
                                                        ? `error_${field.key}_${itemIndex}`
                                                        : undefined
                                                "
                                            />
                                            <p
                                                v-if="
                                                    field.key ===
                                                    'recipient_address'
                                                "
                                                class="mt-1 text-xs leading-6 text-muted-foreground"
                                            >
                                                Boleh dikosongkan. Alamat dan
                                                kabupaten/kota disimpan sebagai
                                                data tujuan.
                                                <span
                                                    v-if="
                                                        form.type ===
                                                        'permission'
                                                    "
                                                >
                                                    Tujuan pada surat ditulis
                                                    “di Tempat”.
                                                </span>
                                            </p>
                                            <p
                                                v-if="
                                                    errorFor(
                                                        `items.${itemIndex}.${field.key}`,
                                                    )
                                                "
                                                :id="`error_${field.key}_${itemIndex}`"
                                                class="mt-1 text-sm text-red-700"
                                            >
                                                {{
                                                    errorFor(
                                                        `items.${itemIndex}.${field.key}`,
                                                    )
                                                }}
                                            </p>
                                        </div>
                                        <div v-if="form.type === 'pkkmb'">
                                            <label
                                                :for="`participant_result_${itemIndex}`"
                                                :class="labelClass"
                                                >Hasil PKKMB</label
                                            ><select
                                                :id="`participant_result_${itemIndex}`"
                                                v-model="
                                                    item.participant_result
                                                "
                                                :class="inputClass"
                                                required
                                                :aria-invalid="
                                                    Boolean(
                                                        errorFor(
                                                            `items.${itemIndex}.participant_result`,
                                                        ),
                                                    )
                                                "
                                                :aria-describedby="`participant_result_hint_${itemIndex} participant_result_error_${itemIndex}`"
                                            >
                                                <option value="" disabled>
                                                    Pilih hasil PKKMB
                                                </option>
                                                <option
                                                    v-for="(
                                                        label, status
                                                    ) in participantResultLabels"
                                                    :key="status"
                                                    :value="status"
                                                >
                                                    {{ label }}
                                                </option>
                                            </select>
                                            <p
                                                :id="`participant_result_hint_${itemIndex}`"
                                                :class="hintClass"
                                            >
                                                Pilih sesuai hasil PKKMB
                                                mahasiswa.
                                            </p>
                                            <p
                                                v-if="
                                                    errorFor(
                                                        `items.${itemIndex}.participant_result`,
                                                    )
                                                "
                                                :id="`participant_result_error_${itemIndex}`"
                                                class="mt-1 text-sm text-destructive"
                                            >
                                                {{
                                                    errorFor(
                                                        `items.${itemIndex}.participant_result`,
                                                    )
                                                }}
                                            </p>
                                            <div
                                                v-if="
                                                    ![
                                                        'attended',
                                                        'completed',
                                                    ].includes(
                                                        item.participation_status,
                                                    ) ||
                                                    errorFor(
                                                        `items.${itemIndex}.participation_status`,
                                                    )
                                                "
                                                class="mt-4"
                                            >
                                                <label
                                                    :for="`participation_status_${itemIndex}`"
                                                    :class="labelClass"
                                                    >Keikutsertaan PKKMB</label
                                                >
                                                <select
                                                    :id="`participation_status_${itemIndex}`"
                                                    v-model="
                                                        item.participation_status
                                                    "
                                                    :class="inputClass"
                                                    required
                                                    :aria-invalid="
                                                        Boolean(
                                                            errorFor(
                                                                `items.${itemIndex}.participation_status`,
                                                            ),
                                                        )
                                                    "
                                                    :aria-describedby="`participation_status_error_${itemIndex}`"
                                                    @change="
                                                        form.clearErrors(
                                                            `items.${itemIndex}.participation_status`,
                                                        )
                                                    "
                                                >
                                                    <option value="" disabled>
                                                        Pilih keikutsertaan
                                                    </option>
                                                    <option
                                                        v-if="
                                                            item.participation_status ===
                                                            'registered'
                                                        "
                                                        value="registered"
                                                        disabled
                                                    >
                                                        Terdaftar — konfirmasi
                                                        keikutsertaan
                                                    </option>
                                                    <option value="attended">
                                                        Telah mengikuti PKKMB
                                                    </option>
                                                    <option value="completed">
                                                        Telah menyelesaikan
                                                        PKKMB
                                                    </option>
                                                </select>
                                            </div>
                                            <p
                                                v-if="
                                                    errorFor(
                                                        `items.${itemIndex}.participation_status`,
                                                    )
                                                "
                                                :id="`participation_status_error_${itemIndex}`"
                                                class="mt-1 text-sm text-destructive"
                                            >
                                                {{
                                                    errorFor(
                                                        `items.${itemIndex}.participation_status`,
                                                    )
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </details>
                            <div
                                class="flex flex-wrap items-center justify-between gap-3"
                            >
                                <button
                                    type="button"
                                    :class="actionClass"
                                    :disabled="
                                        form.items.length >=
                                        maxLettersPerSubmission
                                    "
                                    @click="addItem"
                                >
                                    <Plus class="size-4" aria-hidden="true" />
                                    Tambah
                                    {{
                                        form.type === 'pkkmb'
                                            ? 'mahasiswa'
                                            : 'tujuan'
                                    }}</button
                                ><span :class="hintClass"
                                    >{{ form.items.length }} surat ·
                                    {{ batchCount }} batch · nomor
                                    {{ form.first_sequence }}–{{
                                        finalSequence
                                    }}</span
                                >
                            </div>
                        </template>
                    </div>
                    <section
                        v-show="wizardStep === 3"
                        id="letter-step-3"
                        :class="sectionClass"
                        tabindex="-1"
                        aria-labelledby="review-heading"
                    >
                        <h2 id="review-heading" :class="headingClass">
                            Periksa sebelum menyimpan
                        </h2>
                        <p :class="hintClass">
                            Surat belum disimpan. Periksa ringkasan ini, lalu
                            simpan untuk membuka tampilan surat yang dapat
                            dicetak. Setiap batch memuat maksimal
                            {{ maxLettersPerBatch.toLocaleString('id-ID') }}
                            surat.
                        </p>
                        <dl
                            class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
                        >
                            <div
                                v-for="detail in reviewDetails"
                                :key="detail.label"
                                class="rounded-xl bg-muted/40 p-4"
                            >
                                <dt class="text-xs text-muted-foreground">
                                    {{ detail.label }}
                                </dt>
                                <dd
                                    class="notranslate mt-2 text-sm font-semibold break-words"
                                    translate="no"
                                >
                                    {{ detail.value }}
                                </dd>
                            </div>
                        </dl>
                        <h3 class="mt-6 text-sm font-semibold">
                            {{
                                form.type === 'pkkmb'
                                    ? 'Daftar mahasiswa'
                                    : isSimpleAssignment
                                      ? 'Tim per kabupaten/kota'
                                      : 'Daftar penerima'
                            }}
                            ({{ form.items.length }})
                        </h3>
                        <ol
                            class="mt-3 max-h-96 divide-y divide-border overflow-y-auto rounded-xl border border-border"
                            aria-label="Data surat yang akan disimpan"
                        >
                            <li
                                v-for="(entry, index) in reviewEntries"
                                :key="index"
                                class="flex gap-3 px-4 py-3 text-sm"
                            >
                                <span
                                    class="min-w-6 font-medium text-muted-foreground"
                                    >{{ index + 1 }}.</span
                                >
                                <div class="min-w-0">
                                    <p class="font-medium break-words">
                                        {{ entry.title }}
                                    </p>
                                    <p
                                        v-for="(line, lineIndex) in entry.lines"
                                        :key="lineIndex"
                                        class="mt-1 text-xs leading-6 break-words text-muted-foreground"
                                    >
                                        {{ line }}
                                    </p>
                                </div>
                            </li>
                        </ol>
                    </section>
                    <div
                        v-if="requestError"
                        class="rounded-xl border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive"
                        role="alert"
                    >
                        {{ requestError }}
                    </div>
                    <div
                        v-if="Object.keys(form.errors).length"
                        class="rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-800"
                        role="alert"
                    >
                        <p class="font-semibold">
                            Periksa data berikut sebelum membuat surat:
                        </p>
                        <ul class="mt-2 list-inside list-disc">
                            <li
                                v-for="[key, message] in visibleErrors"
                                :key="key"
                            >
                                {{ message }}
                            </li>
                        </ul>
                        <p
                            v-if="
                                Object.keys(form.errors).length >
                                visibleErrors.length
                            "
                            class="mt-3 text-xs leading-6"
                        >
                            Menampilkan {{ visibleErrors.length }} dari
                            {{ Object.keys(form.errors).length }} catatan.
                            Tujuan yang perlu diperbaiki ditandai dalam daftar
                            surat.
                        </p>
                    </div>
                    <div
                        class="sticky bottom-4 z-10 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-border bg-card/95 p-4 shadow-lg shadow-primary/10 backdrop-blur-sm sm:px-6"
                    >
                        <div>
                            <p class="text-sm font-semibold text-foreground">
                                {{ completedItems }} dari
                                {{ form.items.length }} data lengkap
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                <template v-if="isSimpleAssignment">
                                    Satu surat per kabupaten/kota ·
                                    <template v-if="hasValidSequenceRange">
                                        Nomor {{ form.first_sequence
                                        }}<template v-if="form.items.length > 1"
                                            >–{{ finalSequence }}</template
                                        >
                                    </template>
                                    <template v-else>
                                        Isi nomor awal yang valid
                                    </template>
                                </template>
                                <template v-else>
                                    Nomor {{ form.first_sequence }}–{{
                                        finalSequence
                                    }}
                                    · Tinjau sebelum mencetak
                                </template>
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <Button
                                v-if="wizardStep > 1"
                                type="button"
                                variant="outline"
                                :disabled="formBusy"
                                @click="goToStep((wizardStep - 1) as 1 | 2)"
                            >
                                <ArrowLeft
                                    class="mr-2 size-4"
                                    aria-hidden="true"
                                />Kembali
                            </Button>
                            <Button
                                type="submit"
                                class="sisurat-primary-action min-h-12"
                                :disabled="
                                    form.processing ||
                                    importing ||
                                    refreshingAcademicOptions ||
                                    (wizardStep === 3 &&
                                        form.type === 'pkkmb' &&
                                        !hasAcademicOptions)
                                "
                                ><Printer
                                    v-if="wizardStep === 3"
                                    class="mr-2 size-4"
                                    aria-hidden="true" />
                                {{
                                    form.processing
                                        ? 'Menyimpan…'
                                        : wizardStep === 3
                                          ? batchCount > 1
                                              ? 'Simpan & lihat arsip'
                                              : 'Simpan & lihat surat'
                                          : 'Lanjutkan'
                                }}<ArrowRight
                                    class="ml-2 size-4"
                                    aria-hidden="true"
                            /></Button>
                        </div>
                    </div>
                </fieldset>
            </form>
        </div>
    </main>
</template>
