<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Copy, Printer, Trash2 } from '@lucide/vue';
import { computed, ref, shallowRef } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import LetterTemplateText from '@/components/LetterTemplateText.vue';
import { templateParagraphs } from '@/lib/letterTemplates';
import type {
    LetterContentTemplates,
    LetterTemplateFields,
    LetterTemplateKey,
    LetterTemplateTokens,
} from '@/lib/letterTemplates';
import { districtCategoryLabel, letterTypeLabels } from '@/lib/letters';
import type { LetterType, ParticipationStatus } from '@/lib/letters';
import { useMasterDataDeletion } from '@/lib/masterData';
import { general, index as lettersIndex, pkkmb } from '@/routes/letters';
import assignment from '@/routes/letters/assignment';
import { destroy as destroyBatch } from '@/routes/letters/batches';
import { destroy as destroyLetter } from '@/routes/letters/batches/letters';
import permission from '@/routes/letters/permission';

type Assignee = {
    id: number;
    name: string;
    office: string;
    district: string;
};

type Letter = {
    id: number;
    letter_number: string;
    recipient_position: string | null;
    recipient_name: string | null;
    recipient_salutation?: string | null;
    institution_type: 'school' | 'pesantren' | null;
    institution_name: string | null;
    recipient_district: string | null;
    recipient_address?: string | null;
    activity_destination: string | null;
    participant_name: string | null;
    participant_nim: string | null;
    participant_study_program: string | null;
    participant_faculty?: string | null;
    participant_result?: 'passed' | 'not_passed' | null;
    participation_status: ParticipationStatus | null;
    assignees: Assignee[];
};

type Batch = {
    id: number;
    can_delete: boolean;
    is_trial?: boolean;
    type: LetterType;
    subject?: string | null;
    attachment?: string | null;
    body?: string | null;
    general_options?: {
        version?: number;
        category?: string;
        letter_title?: string | null;
        opening_greeting?: string | null;
        closing_greeting?: string | null;
        closing_body?: string | null;
        event_date?: string | null;
        event_time?: string | null;
        event_location?: string | null;
        event_agenda?: string | null;
    } | null;
    letter_date: string;
    event_start: string | null;
    event_end: string | null;
    event_location: string | null;
    academic_year: string | null;
    pkkmb_options?: { version: number; theme: string } | null;
    content_templates?: LetterContentTemplates | null;
    signatory_title: string;
    signatory_name: string;
    signatory_number: string | null;
    include_signature?: boolean | null;
    include_stamp?: boolean | null;
    letters: Letter[];
};
type DeletionTarget = {
    id: number;
    name: string;
    kind: 'batch' | 'letter';
    recipient?: string;
    count?: number;
};

const props = defineProps<{
    batch: Batch;
    letterheadUrl: string;
    signatureOwnerName?: string;
    pmbSignatory: {
        id: number;
        signature_url: string | null;
        stamp_url: string | null;
    };
}>();
const deleteTrigger = shallowRef<HTMLElement | null>(null);
const deleteNeighborId = ref<number | null>(null);
const {
    deleteForm,
    deleteOpen,
    deleteTarget,
    deleteError,
    requestDeletion,
    updateDeleteDialog,
    confirmDeletion,
} = useMasterDataDeletion<DeletionTarget>(deletionUrl, 'batch-back-to-archive');

function deletionUrl(id: number): string {
    return deleteTarget.value?.kind === 'letter'
        ? destroyLetter.url({ batch: props.batch.id, letter: id })
        : destroyBatch.url({ batch: id });
}

function letterRecipient(letter: Letter): string {
    if (props.batch.type === 'pkkmb') {
        return letter.participant_name || 'Mahasiswa PKKMB';
    }
    if (props.batch.type === 'assignment') {
        return (
            letter.assignees
                .map((person) => person.name)
                .filter(Boolean)
                .join(', ') ||
            letter.recipient_district ||
            'Petugas surat tugas'
        );
    }
    return (
        letter.institution_name ||
        letter.recipient_name ||
        letter.recipient_position ||
        'Penerima surat'
    );
}

function requestBatchDeletion(event: Event): void {
    if (
        !props.batch.can_delete ||
        deleteForm.processing ||
        preparingPrint.value
    )
        return;
    deleteTrigger.value = event.currentTarget as HTMLElement;
    deleteNeighborId.value = null;
    requestDeletion(
        {
            id: props.batch.id,
            name: `${letterTypeLabels[props.batch.type]} · Batch #${props.batch.id}`,
            kind: 'batch',
            count: props.batch.letters.length,
        },
        event,
    );
}

function requestLetterDeletion(letter: Letter, event: Event): void {
    if (
        !props.batch.can_delete ||
        deleteForm.processing ||
        preparingPrint.value
    )
        return;
    const position = props.batch.letters.findIndex(
        (item) => item.id === letter.id,
    );
    deleteNeighborId.value =
        props.batch.letters[position + 1]?.id ??
        props.batch.letters[position - 1]?.id ??
        null;
    deleteTrigger.value = event.currentTarget as HTMLElement;
    requestDeletion(
        {
            id: letter.id,
            name: letter.letter_number,
            kind: 'letter',
            recipient: letterRecipient(letter),
        },
        event,
    );
}

function restoreDeleteFocus(event: Event): void {
    event.preventDefault();
    const neighbor =
        deleteNeighborId.value === null
            ? null
            : document.getElementById(
                  `delete-letter-${deleteNeighborId.value}`,
              );
    const element = deleteTrigger.value?.isConnected
        ? deleteTrigger.value
        : neighbor ||
          document.getElementById('batch-back-to-archive') ||
          document.getElementById('history-heading');
    element?.focus({ preventScroll: true });
}
const similarLetterRoute = computed(() => {
    const options = { query: { from_batch: props.batch.id } };
    if (props.batch.type === 'general') return general(options);
    if (props.batch.type === 'pkkmb') return pkkmb(options);
    if (props.batch.type !== 'permission' && props.batch.type !== 'assignment')
        return null;
    const destination = props.batch.letters[0]?.institution_type;
    if (
        (destination !== 'school' && destination !== 'pesantren') ||
        props.batch.letters.some(
            (letter) => letter.institution_type !== destination,
        )
    )
        return null;
    return props.batch.type === 'assignment'
        ? assignment[destination](options)
        : permission[destination](options);
});
const isReferencePkkmb = computed(
    () =>
        props.batch.type === 'pkkmb' &&
        props.batch.pkkmb_options?.version === 1,
);
const isPmbSigner = computed(() => {
    const normalized = (value: string) =>
        value.toLowerCase().replace(/[^a-z0-9]/g, '');
    return (
        !!props.signatureOwnerName &&
        normalized(props.batch.signatory_name) ===
            normalized(props.signatureOwnerName)
    );
});
const includeSignature = computed(
    () =>
        (props.batch.include_signature ?? isPmbSigner.value) &&
        isPmbSigner.value &&
        !!props.pmbSignatory.signature_url,
);
const includeStamp = computed(
    () =>
        (props.batch.include_stamp ?? isPmbSigner.value) &&
        isPmbSigner.value &&
        !!props.pmbSignatory.stamp_url,
);
const preparingPrint = ref(false);
const printError = ref('');
function bodyParagraphs(value: string | null | undefined): string[] {
    return (value ?? '')
        .replace(/\r\n?/g, '\n')
        .split(/\n\s*\n/)
        .map((paragraph) => paragraph.trim())
        .filter(Boolean);
}
const generalParagraphs = computed(() => bodyParagraphs(props.batch.body));
const generalClosingParagraphs = computed(() =>
    bodyParagraphs(props.batch.general_options?.closing_body),
);
const generalOpening = computed(() =>
    props.batch.general_options
        ? props.batch.general_options.opening_greeting || ''
        : 'Assalamu’alaikum Warahmatullahi Wabarakatuh',
);
const generalClosing = computed(() =>
    props.batch.general_options
        ? props.batch.general_options.closing_greeting || ''
        : 'Wassalamu’alaikum Warahmatullahi Wabarakatuh',
);
const generalEventRows = computed(() => {
    const details = props.batch.general_options;
    return [
        { label: 'Tanggal', value: formatDate(details?.event_date || null) },
        { label: 'Waktu', value: details?.event_time },
        { label: 'Tempat', value: details?.event_location },
        { label: 'Agenda', value: details?.event_agenda },
    ].filter((row) => row.value);
});

async function printLetters() {
    if (preparingPrint.value || deleteForm.processing || deleteOpen.value)
        return;
    preparingPrint.value = true;
    printError.value = '';
    try {
        const images = Array.from(
            document.querySelectorAll<HTMLImageElement>('.letter-page img'),
        );
        await Promise.all(images.map((img) => img.decode()));
        await document.fonts.ready;
        window.print();
    } catch {
        printError.value =
            'Gambar surat belum berhasil dimuat. Muat ulang halaman lalu coba cetak kembali.';
    } finally {
        preparingPrint.value = false;
    }
}

function formatDate(
    value: string | null,
    day: 'numeric' | '2-digit' = 'numeric',
) {
    if (!value) return '';

    return new Intl.DateTimeFormat('id-ID', {
        day,
        month: 'long',
        year: 'numeric',
    }).format(new Date(`${value.slice(0, 10)}T00:00:00`));
}

function recipientPosition(letter: Letter): string {
    const position = letter.recipient_position?.trim();

    if (
        letter.institution_type === 'pesantren' &&
        position === 'Pimpinan Pondok Pesantren' &&
        /^(pondok\s+pesantren|ponpes|pesantren)\b/i.test(
            letter.institution_name ?? '',
        )
    ) {
        return 'Pimpinan';
    }

    return (
        position ||
        (letter.institution_type === 'pesantren' ? 'Pimpinan' : 'Kepala')
    );
}

function eventPeriod(start: string | null, end: string | null): string {
    const firstDate = formatDate(start);
    return start?.slice(0, 10) === end?.slice(0, 10)
        ? firstDate
        : `${firstDate} sampai ${formatDate(end)}`;
}

function assignmentPeriod(start: string | null, end: string | null): string {
    if (!start || !end || start.slice(0, 10) === end.slice(0, 10)) {
        return formatDate(start ?? end);
    }

    if (start.slice(0, 4) !== end.slice(0, 4)) {
        return `${formatDate(start)} – ${formatDate(end)}`;
    }

    const firstDate = new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'long',
    }).format(new Date(`${start.slice(0, 10)}T00:00:00`));

    return `${firstDate} – ${formatDate(end)}`;
}

function pkkmbPeriod(start: string | null, end: string | null): string {
    if (!start || !end || start.slice(0, 10) === end.slice(0, 10)) {
        return formatDate(start ?? end);
    }

    if (start.slice(0, 7) === end.slice(0, 7)) {
        return `${Number(start.slice(8, 10))} – ${formatDate(end)}`;
    }

    return assignmentPeriod(start, end);
}

const isPmbTitle = computed(() =>
    /^ketua\s+(?:pusat\s+)?(?:pmb|penerimaan\s+mahasiswa\s+baru)$/i.test(
        props.batch.signatory_title.trim(),
    ),
);
const assignmentAuthorTitle = computed(() =>
    isPmbTitle.value
        ? 'Ketua Pusat Penerimaan Mahasiswa Baru'
        : props.batch.signatory_title,
);
const pkkmbAuthorTitle = computed(() =>
    isPmbTitle.value
        ? 'Ketua Pusat Penerimaan Mahasiswa Baru dan Pembinaan Mahasiswa'
        : props.batch.signatory_title,
);
const assignmentReportTitle = computed(() =>
    isPmbTitle.value ? 'Ketua PMB' : props.batch.signatory_title,
);

function letterTemplate(letter: Letter): LetterTemplateFields | null {
    const snapshot = props.batch.content_templates;
    if (snapshot?.version !== 1) return null;
    let key: LetterTemplateKey;

    if (props.batch.type === 'pkkmb') {
        if (!isReferencePkkmb.value) return null;
        key = 'pkkmb';
    } else if (
        (props.batch.type === 'permission' ||
            props.batch.type === 'assignment') &&
        (letter.institution_type === 'school' ||
            letter.institution_type === 'pesantren')
    ) {
        key = `${props.batch.type}-${letter.institution_type}`;
    } else {
        return null;
    }

    return snapshot.templates[key] ?? null;
}

function templateTokens(letter: Letter): LetterTemplateTokens {
    const type = props.batch.type;
    const isPesantren = letter.institution_type === 'pesantren';
    return {
        nomor_surat: letter.letter_number,
        tanggal_surat: formatDate(props.batch.letter_date),
        nama_penandatangan: props.batch.signatory_name,
        jabatan_penandatangan:
            type === 'assignment'
                ? assignmentAuthorTitle.value
                : type === 'pkkmb'
                  ? pkkmbAuthorTitle.value
                  : props.batch.signatory_title,
        nama_instansi:
            type === 'assignment'
                ? letter.activity_destination ||
                  (isPesantren
                      ? 'Pondok Pesantren'
                      : 'sekolah-sekolah tingkat SMA, SMK, dan MA')
                : letter.institution_name || '',
        sapaan_penerima: isPesantren
            ? 'Pimpinan Pondok Pesantren'
            : 'Bapak/Ibu Kepala Sekolah',
        kabupaten: districtCategoryLabel(letter.recipient_district || ''),
        periode_kegiatan:
            type === 'pkkmb'
                ? pkkmbPeriod(props.batch.event_start, props.batch.event_end)
                : assignmentPeriod(
                      props.batch.event_start,
                      props.batch.event_end,
                  ),
        jabatan_pelaporan: assignmentReportTitle.value,
        tema_pkkmb: props.batch.pkkmb_options?.theme || '',
        nama_mahasiswa: letter.participant_name || '',
        nim: letter.participant_nim || '',
        program_studi: letter.participant_study_program || '',
        fakultas: letter.participant_faculty || '',
        hasil_pkkmb:
            letter.participant_result === 'not_passed'
                ? 'TIDAK LULUS'
                : 'LULUS',
    };
}

function templateBasis(letter: Letter): string[] {
    return (letterTemplate(letter)?.basis ?? '')
        .replace(/\r\n?/g, '\n')
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean)
        .map((line, index) =>
            /^[a-z]+\.\s/i.test(line) ? line : `${basisPrefix(index)}. ${line}`,
        );
}

function basisPrefix(index: number): string {
    let remaining = index + 1;
    let prefix = '';
    while (remaining > 0) {
        remaining--;
        prefix = String.fromCharCode(97 + (remaining % 26)) + prefix;
        remaining = Math.floor(remaining / 26);
    }
    return prefix;
}

function assignmentRows(letter: Letter) {
    const rows = letter.assignees.map((person, index) => ({
        person,
        index,
        district: districtCategoryLabel(
            person.district || letter.recipient_district || '',
        ),
        districtRowspan: 1,
    }));

    let firstInGroup = 0;

    for (let index = 1; index < rows.length; index++) {
        if (
            rows[index].district.toLocaleLowerCase('id-ID') ===
            rows[firstInGroup].district.toLocaleLowerCase('id-ID')
        ) {
            rows[firstInGroup].districtRowspan++;
            rows[index].districtRowspan = 0;
        } else {
            firstInGroup = index;
        }
    }

    return rows;
}
</script>

<template>
    <main class="sisurat-page letter-workspace">
        <Head :title="`Pratinjau ${letterTypeLabels[batch.type]}`" />
        <div
            class="print-toolbar sisurat-panel mx-auto mb-6 flex w-full max-w-5xl flex-wrap items-center justify-between gap-4 p-4 sm:p-5 print:hidden"
        >
            <Link
                id="batch-back-to-archive"
                :href="lettersIndex()"
                class="sisurat-secondary-action"
            >
                <ArrowLeft class="size-4" aria-hidden="true" /> Kembali ke Arsip
                Surat
            </Link>
            <div class="flex flex-wrap items-center gap-3">
                <div class="text-right">
                    <p class="text-sm font-semibold text-foreground">
                        {{ letterTypeLabels[batch.type] }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ batch.letters.length }} surat ·
                        {{ formatDate(batch.letter_date) }}
                    </p>
                </div>
                <Link
                    v-if="similarLetterRoute"
                    :href="similarLetterRoute"
                    class="sisurat-secondary-action"
                    ><Copy class="size-4" aria-hidden="true" />Buat surat
                    serupa</Link
                >
                <Button
                    v-if="batch.can_delete"
                    type="button"
                    variant="outline"
                    class="sisurat-secondary-action text-destructive hover:bg-destructive/10"
                    :disabled="deleteForm.processing || preparingPrint"
                    @click="requestBatchDeletion($event)"
                >
                    <Trash2 class="size-4" aria-hidden="true" />Hapus batch
                </Button>
                <Button
                    type="button"
                    @click="printLetters"
                    :disabled="
                        preparingPrint || deleteForm.processing || deleteOpen
                    "
                    class="sisurat-primary-action"
                    ><Printer class="mr-2 size-4" aria-hidden="true" />
                    {{
                        preparingPrint ? 'Menyiapkan cetak…' : 'Cetak semua'
                    }}</Button
                >
            </div>
        </div>
        <p
            v-if="printError"
            class="print-toolbar mx-auto mb-5 max-w-5xl rounded-xl border border-destructive/30 bg-card p-4 text-sm text-destructive"
            role="alert"
        >
            {{ printError }}
        </p>

        <section
            class="letter-stack mx-auto flex w-full max-w-5xl flex-col gap-5"
        >
            <p
                v-if="batch.is_trial"
                class="print-toolbar rounded-xl border border-amber-300/50 bg-amber-50 p-4 text-sm text-amber-800 dark:bg-amber-950/30 dark:text-amber-200"
            >
                Arsip uji coba. Nomor dalam batch ini terpisah dari penomoran
                surat resmi.
            </p>
            <article
                v-for="letter in batch.letters"
                :key="letter.id"
                class="letter-page notranslate"
                lang="id"
                translate="no"
            >
                <p
                    v-if="batch.is_trial"
                    class="mb-4 border border-dashed border-gray-400 p-2 text-center font-sans text-xs font-bold text-gray-600"
                >
                    ARSIP UJI COBA — BUKAN SURAT RESMI
                </p>
                <div
                    v-if="batch.can_delete"
                    class="print-toolbar mb-5 flex flex-wrap items-center justify-between gap-3 border-b border-border pb-4 font-sans text-sm"
                >
                    <div class="min-w-0 flex-1">
                        <p class="font-medium break-words">
                            {{ letter.letter_number }}
                        </p>
                        <p
                            class="mt-1 text-xs leading-6 break-words text-muted-foreground"
                        >
                            {{ letterRecipient(letter) }}
                        </p>
                    </div>
                    <button
                        :id="`delete-letter-${letter.id}`"
                        type="button"
                        class="sisurat-secondary-action min-h-9! px-3! text-xs! text-destructive hover:bg-destructive/10"
                        :disabled="deleteForm.processing || preparingPrint"
                        :aria-label="`Hapus surat ${letter.letter_number} untuk ${letterRecipient(letter)}`"
                        @click="requestLetterDeletion(letter, $event)"
                    >
                        <Trash2 class="size-3.5" aria-hidden="true" />Hapus
                        surat
                    </button>
                </div>
                <img
                    class="letterhead"
                    :src="letterheadUrl"
                    alt="Kop surat Universitas Nurul Huda"
                />

                <template v-if="batch.type === 'permission'">
                    <div class="letter-meta">
                        <p>
                            <span>Nomor</span><span>:</span>
                            <span>{{ letter.letter_number }}</span>
                        </p>
                        <p>
                            <span>Lampiran</span><span>:</span>
                            <span v-if="letterTemplate(letter)">
                                <LetterTemplateText
                                    :text="letterTemplate(letter)?.attachment"
                                    :tokens="templateTokens(letter)"
                                />
                            </span>
                            <span v-else>-</span>
                        </p>
                        <p>
                            <span>Perihal</span><span>:</span>
                            <span v-if="letterTemplate(letter)">
                                <LetterTemplateText
                                    :text="letterTemplate(letter)?.subject"
                                    :tokens="templateTokens(letter)"
                                />
                            </span>
                            <span v-else
                                >Permohonan Izin Sosialisasi dan Promosi</span
                            >
                        </p>
                    </div>

                    <div class="recipient">
                        <p>Kepada Yth.</p>
                        <p>
                            {{ recipientPosition(letter) }}
                            {{ letter.institution_name }}
                        </p>
                        <p v-if="letter.recipient_name">
                            {{ letter.recipient_name }}
                        </p>
                        <p class="recipient-place-prefix">di</p>
                        <p>Tempat</p>
                    </div>

                    <template v-if="letterTemplate(letter)">
                        <p
                            v-for="(paragraph, index) in templateParagraphs(
                                letterTemplate(letter)?.opening_greeting,
                            )"
                            :key="`opening-${index}`"
                            class="salutation"
                        >
                            <LetterTemplateText
                                :text="paragraph"
                                :tokens="templateTokens(letter)"
                            />
                        </p>
                        <template
                            v-for="field in [
                                'introduction',
                                'description',
                                'closing',
                            ] as const"
                            :key="field"
                        >
                            <p
                                v-for="(paragraph, index) in templateParagraphs(
                                    letterTemplate(letter)?.[field],
                                )"
                                :key="`${field}-${index}`"
                                class="body-paragraph"
                            >
                                <LetterTemplateText
                                    :text="paragraph"
                                    :tokens="templateTokens(letter)"
                                />
                            </p>
                        </template>
                        <p
                            v-for="(paragraph, index) in templateParagraphs(
                                letterTemplate(letter)?.closing_greeting,
                            )"
                            :key="`greeting-${index}`"
                            class="closing"
                        >
                            <LetterTemplateText
                                :text="paragraph"
                                :tokens="templateTokens(letter)"
                            />
                        </p>
                    </template>
                    <template v-else>
                        <p class="salutation">
                            Assalamu’alaikum Warahmatullahi Wabarakatuh
                        </p>
                        <p class="body-paragraph">
                            Salam silaturahim kami sampaikan, semoga kita semua
                            tetap dalam lindungan dan ridho Allah subhanahu wa
                            ta’ala.
                        </p>
                        <p
                            v-if="letter.institution_type === 'pesantren'"
                            class="body-paragraph"
                        >
                            Dalam rangka persiapan Penerimaan Mahasiswa Baru
                            (PMB) Universitas Nurul Huda, kami bermaksud
                            bersilaturahmi serta mengadakan sosialisasi dan
                            promosi kampus kami. Oleh karena itu, kami memohon
                            kepada Pimpinan Pondok Pesantren berkenan memberikan
                            izin kepada kami untuk melaksanakan acara
                            Sosialisasi dan Promosi Universitas Nurul Huda.
                        </p>
                        <p v-else class="body-paragraph">
                            Dalam rangka persiapan Penerimaan Mahasiswa Baru
                            (PMB) Universitas Nurul Huda, kami bermaksud
                            mengadakan sosialisasi dan promosi kampus kami. Oleh
                            karena itu, kami memohon kepada Bapak/Ibu Kepala
                            Sekolah berkenan memberikan izin kepada kami untuk
                            melaksanakan acara Sosialisasi dan Promosi
                            Universitas Nurul Huda.
                        </p>
                        <p class="body-paragraph">
                            Demikian surat permohonan ini kami sampaikan. Atas
                            perhatian dan kerja samanya, kami ucapkan terima
                            kasih.
                        </p>
                        <p class="closing">
                            Wassalamu’alaikum Warahmatullahi Wabarakatuh
                        </p>
                    </template>
                </template>

                <template v-else-if="batch.type === 'general'">
                    <h1
                        v-if="batch.general_options?.letter_title"
                        class="general-title"
                    >
                        {{ batch.general_options.letter_title }}
                    </h1>
                    <div class="letter-meta">
                        <p>
                            <span>Nomor</span><span>:</span>
                            <span>{{ letter.letter_number }}</span>
                        </p>
                        <p>
                            <span>Lampiran</span><span>:</span>
                            <span>{{ batch.attachment || '-' }}</span>
                        </p>
                        <p>
                            <span>Perihal</span><span>:</span>
                            <span>{{ batch.subject }}</span>
                        </p>
                    </div>
                    <div class="recipient">
                        <p v-if="letter.recipient_salutation ?? 'Kepada Yth.'">
                            {{ letter.recipient_salutation ?? 'Kepada Yth.' }}
                        </p>
                        <template v-if="batch.general_options">
                            <p
                                v-if="
                                    letter.recipient_position ||
                                    letter.recipient_name
                                "
                            >
                                {{ letter.recipient_position }}
                                {{ letter.recipient_name }}
                            </p>
                            <p v-if="letter.institution_name">
                                {{ letter.institution_name }}
                            </p>
                        </template>
                        <template v-else>
                            <p>
                                {{ letter.recipient_position }}
                                {{ letter.institution_name }}
                            </p>
                            <p v-if="letter.recipient_name">
                                {{ letter.recipient_name }}
                            </p>
                        </template>
                        <p class="recipient-place-prefix">di</p>
                        <p class="general-address">
                            {{
                                batch.general_options
                                    ? letter.recipient_address || 'Tempat'
                                    : 'Tempat'
                            }}
                        </p>
                    </div>
                    <p v-if="generalOpening" class="salutation">
                        {{ generalOpening }}
                    </p>
                    <p
                        v-for="(paragraph, paragraphIndex) in generalParagraphs"
                        :key="paragraphIndex"
                        class="body-paragraph general-paragraph"
                    >
                        {{ paragraph }}
                    </p>
                    <dl
                        v-if="generalEventRows.length"
                        class="identity-list general-event"
                    >
                        <div v-for="row in generalEventRows" :key="row.label">
                            <dt>{{ row.label }}</dt>
                            <dd>{{ row.value }}</dd>
                        </div>
                    </dl>
                    <p
                        v-for="(
                            paragraph, paragraphIndex
                        ) in generalClosingParagraphs"
                        :key="`closing-${paragraphIndex}`"
                        class="body-paragraph general-paragraph"
                    >
                        {{ paragraph }}
                    </p>
                    <p v-if="generalClosing" class="closing">
                        {{ generalClosing }}
                    </p>
                </template>

                <template v-else-if="batch.type === 'assignment'">
                    <div class="assignment-title">
                        <h1 class="assignment-heading">
                            <LetterTemplateText
                                v-if="letterTemplate(letter)"
                                :text="letterTemplate(letter)?.title"
                                :tokens="templateTokens(letter)"
                            />
                            <template v-else>Surat Tugas</template>
                        </h1>
                        <p>{{ letter.letter_number }}</p>
                    </div>
                    <div class="assignment-body">
                        <div
                            v-if="
                                !letterTemplate(letter) ||
                                templateBasis(letter).length
                            "
                            class="assignment-basis"
                        >
                            <span>Dasar</span>
                            <span>:</span>
                            <div v-if="letterTemplate(letter)">
                                <p
                                    v-for="(line, index) in templateBasis(
                                        letter,
                                    )"
                                    :key="index"
                                >
                                    <LetterTemplateText
                                        :text="line"
                                        :tokens="templateTokens(letter)"
                                    />
                                </p>
                            </div>
                            <div v-else>
                                <p>a. Pembukaan Penerimaan Mahasiswa Baru</p>
                                <p>b. Sosialisasi Kampus ke Masyarakat</p>
                            </div>
                        </div>
                        <template v-if="letterTemplate(letter)">
                            <p
                                v-for="(paragraph, index) in templateParagraphs(
                                    letterTemplate(letter)?.introduction,
                                )"
                                :key="index"
                                class="assignment-intro"
                            >
                                <LetterTemplateText
                                    :text="paragraph"
                                    :tokens="templateTokens(letter)"
                                />
                            </p>
                        </template>
                        <p v-else class="assignment-intro">
                            Maka, {{ assignmentAuthorTitle }} memberikan tugas
                            kepada:
                        </p>
                        <table class="assignee-table">
                            <thead>
                                <tr>
                                    <th scope="col">No</th>
                                    <th scope="col">Nama</th>
                                    <th scope="col">Dinas</th>
                                    <th scope="col">Kabupaten</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in assignmentRows(letter)"
                                    :key="row.person.id"
                                >
                                    <td>{{ row.index + 1 }}.</td>
                                    <td>{{ row.person.name }}</td>
                                    <td class="assignee-office">
                                        {{ row.person.office }}
                                    </td>
                                    <td
                                        v-if="row.districtRowspan"
                                        :rowspan="row.districtRowspan"
                                        class="assignee-district"
                                    >
                                        {{ row.district }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <template v-if="letterTemplate(letter)">
                            <template
                                v-for="field in [
                                    'description',
                                    'closing',
                                ] as const"
                                :key="field"
                            >
                                <p
                                    v-for="(
                                        paragraph, index
                                    ) in templateParagraphs(
                                        letterTemplate(letter)?.[field],
                                    )"
                                    :key="`${field}-${index}`"
                                    class="body-paragraph"
                                >
                                    <LetterTemplateText
                                        :text="paragraph"
                                        :tokens="templateTokens(letter)"
                                    />
                                </p>
                            </template>
                        </template>
                        <template v-else>
                            <p class="body-paragraph">
                                Untuk melaksanakan kegiatan sosialisasi dan
                                promosi kampus Universitas Nurul Huda ke
                                {{
                                    letter.institution_type === 'pesantren'
                                        ? letter.activity_destination ||
                                          'Pondok Pesantren'
                                        : 'sekolah-sekolah tingkat SMA, SMK, dan MA'
                                }}
                                yang dilaksanakan pada
                                {{
                                    assignmentPeriod(
                                        batch.event_start,
                                        batch.event_end,
                                    )
                                }}.
                            </p>
                            <p class="body-paragraph">
                                Demikian surat tugas ini dibuat agar
                                dilaksanakan dengan penuh tanggung jawab dan
                                melaporkan hasil kegiatan kepada
                                {{ assignmentReportTitle }}.
                            </p>
                        </template>
                    </div>
                </template>

                <template v-else-if="isReferencePkkmb">
                    <div class="assignment-title pkkmb-reference-title">
                        <h1>
                            <LetterTemplateText
                                v-if="letterTemplate(letter)"
                                :text="letterTemplate(letter)?.title"
                                :tokens="templateTokens(letter)"
                            />
                            <template v-else>SURAT KETERANGAN</template>
                        </h1>
                        <p class="pkkmb-reference-number">
                            Nomor: {{ letter.letter_number }}
                        </p>
                    </div>
                    <div class="certificate-body pkkmb-reference-body">
                        <template v-if="letterTemplate(letter)">
                            <p
                                v-for="(paragraph, index) in templateParagraphs(
                                    letterTemplate(letter)?.introduction,
                                )"
                                :key="index"
                                class="body-paragraph"
                            >
                                <LetterTemplateText
                                    :text="paragraph"
                                    :tokens="templateTokens(letter)"
                                />
                            </p>
                        </template>
                        <p v-else class="body-paragraph">
                            {{ pkkmbAuthorTitle }} dengan ini menerangkan bahwa:
                        </p>
                        <dl class="identity-list pkkmb-reference-identity">
                            <div>
                                <dt>Nama</dt>
                                <dd>{{ letter.participant_name }}</dd>
                            </div>
                            <div>
                                <dt>NIM</dt>
                                <dd>{{ letter.participant_nim }}</dd>
                            </div>
                            <div>
                                <dt>Prodi</dt>
                                <dd>{{ letter.participant_study_program }}</dd>
                            </div>
                            <div>
                                <dt>Fakultas</dt>
                                <dd>{{ letter.participant_faculty }}</dd>
                            </div>
                        </dl>
                        <template v-if="letterTemplate(letter)">
                            <template
                                v-for="field in [
                                    'description',
                                    'closing',
                                ] as const"
                                :key="field"
                            >
                                <p
                                    v-for="(
                                        paragraph, index
                                    ) in templateParagraphs(
                                        letterTemplate(letter)?.[field],
                                    )"
                                    :key="`${field}-${index}`"
                                    class="body-paragraph"
                                >
                                    <LetterTemplateText
                                        :text="paragraph"
                                        :tokens="templateTokens(letter)"
                                    />
                                </p>
                            </template>
                        </template>
                        <template v-else>
                            <p class="body-paragraph">
                                Benar mahasiswa tersebut di atas telah mengikuti
                                kegiatan Pengenalan Kehidupan Kampus Bagi
                                Mahasiswa Baru (PKKMB) dengan tema “{{
                                    batch.pkkmb_options?.theme
                                }}” pada tanggal
                                {{
                                    pkkmbPeriod(
                                        batch.event_start,
                                        batch.event_end,
                                    )
                                }}
                                dan telah dinyatakan “<strong>{{
                                    letter.participant_result === 'not_passed'
                                        ? 'TIDAK LULUS'
                                        : 'LULUS'
                                }}</strong
                                >”.
                            </p>
                            <p class="body-paragraph">
                                Demikian surat keterangan ini dibuat dengan
                                sebenar-benarnya.
                            </p>
                        </template>
                    </div>
                </template>

                <template v-else-if="batch.type === 'pkkmb'">
                    <div class="assignment-title">
                        <h1>SURAT KETERANGAN PKKMB</h1>
                        <p class="certificate-number">
                            <span>Nomor</span><span>:</span>
                            <span>{{ letter.letter_number }}</span>
                        </p>
                    </div>
                    <div class="certificate-body">
                        <p>Yang bertanda tangan di bawah ini:</p>
                        <dl class="identity-list">
                            <div>
                                <dt>Nama</dt>
                                <dd>{{ batch.signatory_name }}</dd>
                            </div>
                            <div>
                                <dt>Jabatan</dt>
                                <dd>{{ batch.signatory_title }}</dd>
                            </div>
                        </dl>
                        <p>Menerangkan bahwa:</p>
                        <dl class="identity-list">
                            <div>
                                <dt>Nama mahasiswa</dt>
                                <dd>{{ letter.participant_name }}</dd>
                            </div>
                            <div>
                                <dt>NIM</dt>
                                <dd>{{ letter.participant_nim }}</dd>
                            </div>
                            <div>
                                <dt>Program studi</dt>
                                <dd>{{ letter.participant_study_program }}</dd>
                            </div>
                            <div>
                                <dt>Tahun akademik</dt>
                                <dd>{{ batch.academic_year }}</dd>
                            </div>
                        </dl>
                        <p class="body-paragraph">
                            <template
                                v-if="
                                    letter.participation_status === 'completed'
                                "
                                >Telah menyelesaikan seluruh rangkaian</template
                            >
                            <template
                                v-else-if="
                                    letter.participation_status === 'registered'
                                "
                                >Terdaftar untuk mengikuti</template
                            >
                            <template v-else>Telah mengikuti</template>
                            kegiatan Pengenalan Kehidupan Kampus bagi Mahasiswa
                            Baru (PKKMB) Universitas Nurul Huda tahun akademik
                            {{ batch.academic_year }} yang diselenggarakan di
                            {{ batch.event_location }} pada
                            {{
                                eventPeriod(batch.event_start, batch.event_end)
                            }}.
                        </p>
                        <p class="body-paragraph">
                            Demikian surat keterangan ini dibuat untuk dapat
                            dipergunakan sebagaimana mestinya.
                        </p>
                    </div>
                </template>

                <div
                    class="signature"
                    :class="{ 'signature-with-stamp': includeStamp }"
                >
                    <img
                        v-if="includeStamp"
                        class="pmb-stamp"
                        :src="pmbSignatory.stamp_url!"
                        alt="Cap PMB Universitas Nurul Huda"
                    />
                    <div class="signature-details">
                        <p>
                            OKU Timur,
                            {{
                                formatDate(
                                    batch.letter_date,
                                    isReferencePkkmb ? '2-digit' : 'numeric',
                                )
                            }}
                        </p>
                        <p>{{ batch.signatory_title }},</p>
                        <div
                            v-if="includeSignature || includeStamp"
                            class="signature-artwork"
                        >
                            <img
                                v-if="includeSignature"
                                class="pmb-signature"
                                :src="pmbSignatory.signature_url!"
                                alt="Tanda tangan penandatangan PMB"
                            />
                        </div>
                        <div v-else class="signature-space"></div>
                        <p class="signatory-name">{{ batch.signatory_name }}</p>
                        <p v-if="batch.signatory_number">
                            {{ batch.signatory_number }}
                        </p>
                    </div>
                </div>
            </article>
        </section>
        <Dialog :open="deleteOpen" @update:open="updateDeleteDialog">
            <DialogContent
                :show-close-button="false"
                class="rounded-2xl print:hidden"
                @interact-outside="
                    deleteForm.processing && $event.preventDefault()
                "
                @escape-key-down="
                    deleteForm.processing && $event.preventDefault()
                "
                @close-auto-focus="restoreDeleteFocus"
            >
                <DialogHeader>
                    <DialogTitle>{{
                        deleteTarget?.kind === 'letter'
                            ? 'Hapus surat ini?'
                            : 'Hapus batch surat?'
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            deleteTarget?.kind === 'letter'
                                ? 'Surat ini akan dihapus dari arsip dan tidak disertakan saat mencetak batch.'
                                : 'Semua surat dalam batch ini akan dihapus dari arsip.'
                        }}
                        Nomor surat tetap tercatat dan tidak dipakai ulang.
                    </DialogDescription>
                </DialogHeader>
                <div class="rounded-xl border border-border bg-muted/40 p-4">
                    <p class="text-sm font-semibold break-words">
                        {{ deleteTarget?.name }}
                    </p>
                    <p
                        v-if="deleteTarget?.kind === 'letter'"
                        class="mt-2 text-xs leading-6 break-words text-muted-foreground"
                    >
                        Tujuan: {{ deleteTarget.recipient }}
                    </p>
                    <p
                        v-else
                        class="mt-2 text-xs leading-6 text-muted-foreground"
                    >
                        {{ formatDate(batch.letter_date) }} ·
                        {{ deleteTarget?.count }} surat
                    </p>
                </div>
                <p
                    v-if="deleteError"
                    role="alert"
                    class="rounded-xl bg-destructive/10 px-4 py-3 text-sm text-destructive"
                >
                    {{ deleteError }}
                </p>
                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="deleteForm.processing"
                        @click="updateDeleteDialog(false)"
                        >Batal</Button
                    >
                    <Button
                        type="button"
                        variant="destructive"
                        :disabled="deleteForm.processing"
                        :aria-busy="deleteForm.processing"
                        @click="confirmDeletion"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                        {{
                            deleteForm.processing
                                ? 'Menghapus…'
                                : deleteTarget?.kind === 'letter'
                                  ? 'Hapus surat'
                                  : 'Hapus batch'
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </main>
</template>

<style>
.letter-page {
    box-sizing: border-box;
    width: 210mm;
    min-height: 297mm;
    margin-inline: auto;
    padding: 18mm 22mm 20mm;
    background: #fff;
    color: #111;
    font-family: Cambria, Georgia, serif;
    font-size: 12pt;
    line-height: 1.28;
    box-shadow: 0 8px 30px rgb(0 0 0 / 12%);
}

.letterhead {
    display: block;
    width: 100%;
    height: auto;
    margin: 0 0 16px;
}

.letter-meta p,
.recipient p,
.signature p {
    margin: 0;
}

.letter-meta p,
.certificate-number {
    display: grid;
    grid-template-columns: 22mm 4mm minmax(0, 1fr);
    align-items: start;
}

.letter-meta p > span:last-child,
.certificate-number > span:last-child {
    overflow-wrap: anywhere;
}

.recipient {
    margin-top: 24px;
}

.recipient .recipient-place-prefix {
    margin-top: 3px;
}

.salutation,
.closing {
    font-weight: 700;
    font-style: italic;
}

.salutation {
    margin: 22px 0 14px;
}

.body-paragraph {
    margin: 0 0 12px;
    text-align: justify;
    text-indent: 34px;
}

.general-paragraph {
    white-space: pre-line;
    overflow-wrap: anywhere;
}

.general-title {
    margin: 16px 0;
    text-align: center;
    font-size: 14pt;
    font-weight: 700;
    overflow-wrap: anywhere;
}

.general-event {
    margin: 12px 0 16px;
}

.general-event dd,
.general-address {
    white-space: pre-line;
    overflow-wrap: anywhere;
}

.closing {
    margin-top: 14px;
}

.assignment-title {
    margin-top: 16px;
    text-align: center;
}

.assignment-title h1 {
    margin: 0;
    font-size: 14pt;
    text-decoration: underline;
}

.assignment-title p {
    margin: 2px 0 0;
}

.assignment-title .assignment-heading {
    font-weight: 700;
}

.assignment-title .certificate-number {
    display: inline-grid;
    max-width: 100%;
    text-align: left;
}

.assignment-body {
    margin-top: 25px;
}

.certificate-body {
    margin-top: 25px;
}

.certificate-body > p:not(.body-paragraph) {
    margin: 0 0 8px;
}

.identity-list {
    margin: 0 0 18px 24px;
}

.identity-list > div {
    display: flex;
    gap: 8px;
    margin-bottom: 4px;
}

.identity-list dt {
    flex: 0 0 140px;
}

.identity-list dd {
    margin: 0;
    overflow-wrap: anywhere;
}

.identity-list dd::before {
    content: ': ';
}

.pkkmb-reference-title h1 {
    font-size: 12pt;
    font-weight: 700;
}

.pkkmb-reference-title .pkkmb-reference-number {
    margin-top: 4px;
    overflow-wrap: anywhere;
}

.pkkmb-reference-body .body-paragraph {
    text-indent: 12.7mm;
}

.pkkmb-reference-identity {
    margin-left: 12.7mm;
}

.pkkmb-reference-identity > div {
    display: grid;
    grid-template-columns: 22mm minmax(0, 1fr);
    align-items: start;
    gap: 0;
}

.pkkmb-reference-identity dd {
    position: relative;
    padding-left: 4mm;
}

.pkkmb-reference-identity dd::before {
    position: absolute;
    left: 0;
    content: ':';
}

.assignment-body p {
    margin: 0 0 6px;
}

.assignment-basis {
    display: grid;
    grid-template-columns: 22mm 4mm minmax(0, 1fr);
    align-items: start;
}

.assignment-basis p {
    margin: 0;
}

.assignment-intro {
    margin-top: 20px !important;
}

.assignment-body .body-paragraph {
    margin: 16px 0 0;
    text-indent: 0;
}

.assignee-table {
    width: 100%;
    margin: 8px 0 18px;
    border-collapse: collapse;
    table-layout: fixed;
    font-size: 10pt;
    line-height: 1.2;
}

.assignee-table th,
.assignee-table td {
    padding: 3px 5px;
    border: 1px solid #222;
    vertical-align: middle;
    overflow-wrap: anywhere;
}

.assignee-table th {
    text-align: center;
}

.assignee-table th:first-child,
.assignee-table td:first-child {
    width: 8%;
    text-align: center;
}

.assignee-table th:nth-child(2) {
    width: 47%;
}

.assignee-table th:nth-child(3) {
    width: 15%;
}

.assignee-table th:nth-child(4) {
    width: 30%;
}

.assignee-table th {
    background: #dedede;
    print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
}

.assignee-table .assignee-office,
.assignee-table .assignee-district {
    text-align: center;
    vertical-align: middle;
}

.assignee-table .assignee-district {
    width: 30%;
}

.signature {
    isolation: isolate;
    width: 64mm;
    max-width: 100%;
    margin: 28px 0 0 auto;
}

.signature-with-stamp {
    display: grid;
    grid-template-columns: 27.2727% minmax(0, 1fr);
    width: 88mm;
}

.signature-details {
    position: relative;
    min-width: 0;
}

.signature-with-stamp .signature-details {
    grid-area: 1 / 2;
}

.signature-space {
    height: 72px;
}

.signature-artwork {
    position: relative;
    width: 100%;
    max-width: 100%;
    height: 20mm;
    margin-top: 1mm;
    print-color-adjust: exact;
    -webkit-print-color-adjust: exact;
}

.pmb-stamp {
    position: relative;
    z-index: 2;
    grid-area: 1 / 1;
    align-self: start;
    width: 133.3333%;
    max-width: 32mm;
    height: auto;
    margin-top: 3mm;
    object-fit: contain;
    mix-blend-mode: normal;
}

.pmb-signature {
    position: absolute;
    top: 1mm;
    left: 2mm;
    z-index: 1;
    width: 24mm;
    max-width: calc(100% - 2mm);
    height: 17mm;
    object-fit: contain;
    mix-blend-mode: multiply;
}

.signatory-name {
    font-weight: 700;
    text-decoration: underline;
}

@media print {
    @page {
        size: A4 portrait;
        margin: 0;
    }

    body {
        background: #fff !important;
        overflow: visible !important;
        padding-right: 0 !important;
        margin-right: 0 !important;
    }

    [data-slot='sidebar'],
    [data-sidebar='sidebar'],
    [data-slot='sidebar-gap'],
    .sisurat-app-header,
    .sisurat-skip-link {
        display: none !important;
    }

    [data-slot='sidebar-inset'] {
        display: block !important;
        width: 100% !important;
        margin: 0 !important;
        border-radius: 0 !important;
        box-shadow: none !important;
    }

    .letter-workspace {
        padding: 0 !important;
        background: #fff !important;
    }

    .print-toolbar,
    [data-slot='dialog-overlay'],
    [data-slot='dialog-content'] {
        display: none !important;
    }

    .letter-stack {
        display: block !important;
        max-width: none !important;
        gap: 0 !important;
    }

    .letter-page {
        width: 210mm;
        height: auto;
        min-height: 297mm;
        margin: 0;
        padding: 18mm 22mm 20mm;
        box-shadow: none;
        break-after: page;
        page-break-after: always;
    }

    .letter-page:last-child {
        break-after: auto;
        page-break-after: auto;
    }

    .assignee-table thead {
        display: table-header-group;
    }

    .assignee-table tr,
    .signature,
    .identity-list > div {
        break-inside: avoid;
        page-break-inside: avoid;
    }
}

@media screen {
    .letter-page {
        max-width: 100%;
    }
}

@media screen and (max-width: 720px) {
    .letter-page {
        width: 100%;
        min-height: auto;
        padding: 22px;
        font-size: 11pt;
    }
}
</style>

