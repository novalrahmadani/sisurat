<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    CalendarDays,
    ChevronDown,
    MapPin,
    Plus,
    Trash2,
    UsersRound,
} from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import {
    createAssignmentItem,
    districtCategoryLabel,
    institutionLabels,
    maxLettersPerSubmission,
    normalizeDistrict,
    type Assignee,
    type InstitutionType,
    type LetterItem,
} from '@/lib/letters';
import { index as masterDistricts } from '@/routes/master-data/districts';
import { create as createStaffMember } from '@/routes/master-data/staff-members';

type MasterStaffMember = {
    id: number;
    name: string;
    office: string;
    employee_number: string | null;
    district_id: number | null;
    district: string | null;
};

const props = withDefaults(
    defineProps<{
        destination: InstitutionType;
        districts: string[];
        staffMembers: MasterStaffMember[];
        errors: Record<string, string>;
        busy: boolean;
        numberPreview: string;
        sectionMode?: 'all' | 'settings' | 'data';
    }>(),
    { sectionMode: 'all' },
);
const items = defineModel<LetterItem[]>('items', { required: true });
const eventStart = defineModel<string>('eventStart', { required: true });
const eventEnd = defineModel<string>('eventEnd', { required: true });
const firstSequence = defineModel<number>('firstSequence', { required: true });
const numberPrefix = defineModel<string>('numberPrefix', { required: true });
const emit = defineEmits<{
    destinationChange: [destination: InstitutionType];
    clearErrors: [];
    clearNumberError: [];
    clearCodeError: [];
}>();
const openGroups = ref(new Set([0]));
const selectedStaffMembers = reactive(new WeakMap<Assignee, number>());
const invalidGroups = computed(
    () =>
        new Set(
            Object.keys(props.errors).flatMap((path) => {
                const match = /^items\.(\d+)\./.exec(path);
                return match ? [Number(match[1])] : [];
            }),
        ),
);
const firstInvalidGroup = computed(() =>
    invalidGroups.value.size ? Math.min(...invalidGroups.value) : -1,
);
const labelClass = 'mb-1.5 block text-sm font-medium text-foreground';
function errorFor(path: string): string | undefined {
    return props.errors[path];
}
function isExpanded(index: number): boolean {
    return openGroups.value.has(index) || firstInvalidGroup.value === index;
}
function toggleGroup(event: Event, index: number): void {
    const next = new Set(openGroups.value);
    if ((event.target as HTMLDetailsElement).open) next.add(index);
    else next.delete(index);
    openGroups.value = next;
}
function districtUsed(district: string, index: number): boolean {
    return items.value.some(
        (item, itemIndex) =>
            itemIndex !== index && item.recipient_district === district,
    );
}
function updateDistrict(index: number, event: Event): void {
    if (props.busy) return;
    const district = normalizeDistrict(
        (event.target as HTMLSelectElement).value,
    );
    if (districtUsed(district, index)) return;
    setGroupDistrict(index, district);
    emit('clearErrors');
}
function setGroupDistrict(index: number, district: string): void {
    const item = items.value[index];
    if (!item) return;
    item.recipient_district = district;
    item.assignees.forEach((person) => {
        person.district = district;
    });
}
function staffLabel(person: MasterStaffMember): string {
    return [
        person.name,
        person.employee_number ? `NIP/NIDN ${person.employee_number}` : '',
        person.office,
        person.district ? districtCategoryLabel(person.district) : '',
    ]
        .filter(Boolean)
        .join(' · ');
}
function staffUsed(
    staffId: number,
    groupIndex: number,
    personIndex: number,
): boolean {
    return Boolean(
        items.value[groupIndex]?.assignees.some(
            (person, index) =>
                index !== personIndex &&
                selectedStaffMembers.get(person) === staffId,
        ),
    );
}
function selectStaff(
    groupIndex: number,
    personIndex: number,
    event: Event,
): void {
    if (props.busy) return;
    const item = items.value[groupIndex];
    const person = item?.assignees[personIndex];
    if (!item || !person) return;
    const selection = (event.target as HTMLSelectElement).value;
    if (!selection) {
        selectedStaffMembers.delete(person);
        emit('clearErrors');
        return;
    }
    const staffId = Number(selection);
    const staff = props.staffMembers.find((member) => member.id === staffId);
    if (!staff || staffUsed(staffId, groupIndex, personIndex)) return;
    selectedStaffMembers.set(person, staffId);
    person.name = staff.name;
    person.office = staff.office;
    const staffDistrict = normalizeDistrict(staff.district ?? '');
    if (
        !item.recipient_district.trim() &&
        staffDistrict &&
        props.districts.includes(staffDistrict) &&
        !districtUsed(staffDistrict, groupIndex)
    ) {
        setGroupDistrict(groupIndex, staffDistrict);
    }
    emit('clearErrors');
}
function editStaff(person: Assignee): void {
    selectedStaffMembers.delete(person);
    emit('clearErrors');
}
function addGroup(): void {
    if (
        props.busy ||
        items.value.length >=
            Math.min(props.districts.length, maxLettersPerSubmission)
    )
        return;
    items.value = [...items.value, createAssignmentItem(props.destination)];
    openGroups.value = new Set([items.value.length - 1]);
    emit('clearErrors');
}
function removeGroup(index: number): void {
    if (props.busy || items.value.length <= 1) return;
    items.value = items.value.filter((_, itemIndex) => itemIndex !== index);
    openGroups.value = new Set([Math.max(0, index - 1)]);
    emit('clearErrors');
}
function addLecturer(index: number): void {
    const item = items.value[index];
    if (props.busy || !item || item.assignees.length >= 30) return;
    item.assignees.push({
        name: '',
        office: 'Dosen',
        district: item.recipient_district,
    });
    emit('clearErrors');
}
function removeLecturer(index: number, personIndex: number): void {
    const item = items.value[index];
    if (props.busy || !item || item.assignees.length <= 1) return;
    item.assignees.splice(personIndex, 1);
    emit('clearErrors');
}
function updateStart(event: Event): void {
    eventStart.value = (event.target as HTMLInputElement).value;
    if (eventEnd.value && eventEnd.value < eventStart.value)
        eventEnd.value = eventStart.value;
}
</script>

<template>
    <div id="assignment-fields" class="flex flex-col gap-6">
        <section v-if="sectionMode !== 'data'" class="sisurat-panel p-5 sm:p-7">
            <h2 class="text-base font-semibold sm:text-lg">
                1. Tujuan, nomor, dan tanggal kegiatan
            </h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <button
                    v-for="target in ['school', 'pesantren'] as const"
                    :key="target"
                    type="button"
                    :disabled="busy"
                    :aria-pressed="destination === target"
                    :class="[
                        'min-h-12 rounded-xl border px-4 py-3 text-left font-semibold transition-colors',
                        destination === target
                            ? 'border-primary bg-accent text-primary ring-1 ring-primary/20'
                            : 'border-border hover:bg-muted',
                    ]"
                    @click="emit('destinationChange', target)"
                >
                    Ke {{ institutionLabels[target] }}
                </button>
            </div>
            <div class="mt-6 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="assignment_first_sequence" :class="labelClass"
                        >Nomor urut awal</label
                    >
                    <input
                        id="assignment_first_sequence"
                        v-model.number="firstSequence"
                        type="number"
                        min="1"
                        max="99999"
                        step="1"
                        required
                        class="sisurat-input"
                        :aria-invalid="Boolean(errorFor('first_sequence'))"
                        :aria-describedby="
                            errorFor('first_sequence')
                                ? 'assignment_number_hint assignment_number_error'
                                : 'assignment_number_hint'
                        "
                        @input="emit('clearNumberError')"
                    />
                    <p
                        id="assignment_number_hint"
                        class="mt-2 text-sm leading-6 text-muted-foreground"
                    >
                        Isi angka awal saja. Nomor untuk kabupaten/kota
                        berikutnya berurutan.
                    </p>
                    <p
                        v-if="errorFor('first_sequence')"
                        id="assignment_number_error"
                        class="mt-1 text-sm text-destructive"
                        role="alert"
                    >
                        {{ errorFor('first_sequence') }}
                    </p>
                </div>
                <div>
                    <label for="assignment_number_prefix" :class="labelClass"
                        >Kode surat</label
                    >
                    <input
                        id="assignment_number_prefix"
                        v-model.trim="numberPrefix"
                        type="text"
                        maxlength="120"
                        required
                        placeholder="UNUHA.11/TU.2.1"
                        class="sisurat-input"
                        :aria-invalid="Boolean(errorFor('number_prefix'))"
                        :aria-describedby="
                            errorFor('number_prefix')
                                ? 'assignment_code_hint assignment_code_error'
                                : 'assignment_code_hint'
                        "
                        @input="emit('clearCodeError')"
                    />
                    <p
                        id="assignment_code_hint"
                        class="mt-2 text-sm leading-6 text-muted-foreground"
                    >
                        Kode dapat diubah sesuai kebutuhan. Bulan dan tahun
                        surat ditambahkan otomatis.
                    </p>
                    <p
                        v-if="errorFor('number_prefix')"
                        id="assignment_code_error"
                        class="mt-1 text-sm text-destructive"
                        role="alert"
                    >
                        {{ errorFor('number_prefix') }}
                    </p>
                </div>
            </div>
            <div
                v-if="numberPreview"
                class="mt-4 rounded-xl border border-primary/20 bg-accent/60 px-4 py-3"
            >
                <p class="text-xs text-muted-foreground">
                    Pratinjau nomor pertama
                </p>
                <p
                    class="notranslate mt-1 text-sm font-semibold break-all text-primary"
                    translate="no"
                >
                    {{ numberPreview }}
                </p>
            </div>
            <div class="mt-6 flex items-center gap-2 text-sm font-medium">
                <CalendarDays class="size-4 text-primary" aria-hidden="true" />
                Periode sosialisasi
            </div>
            <div class="mt-3 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="assignment_start" :class="labelClass"
                        >Dari tanggal</label
                    >
                    <input
                        id="assignment_start"
                        :value="eventStart"
                        type="date"
                        required
                        class="sisurat-input"
                        :aria-invalid="Boolean(errorFor('event_start'))"
                        @input="updateStart"
                    />
                    <p
                        v-if="errorFor('event_start')"
                        class="mt-1 text-sm text-destructive"
                    >
                        {{ errorFor('event_start') }}
                    </p>
                </div>
                <div>
                    <label for="assignment_end" :class="labelClass"
                        >Sampai tanggal</label
                    >
                    <input
                        id="assignment_end"
                        v-model="eventEnd"
                        type="date"
                        :min="eventStart || undefined"
                        required
                        class="sisurat-input"
                        :aria-invalid="Boolean(errorFor('event_end'))"
                    />
                    <p
                        v-if="errorFor('event_end')"
                        class="mt-1 text-sm text-destructive"
                    >
                        {{ errorFor('event_end') }}
                    </p>
                </div>
            </div>
        </section>
        <section
            v-if="sectionMode !== 'settings'"
            class="sisurat-panel p-5 sm:p-7"
        >
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold sm:text-lg">
                        2. Dosen / petugas per kabupaten/kota
                    </h2>
                    <p class="mt-2 text-sm leading-6 text-muted-foreground">
                        Pilih dosen atau petugas dari Master Data, atau isi
                        manual. Satu kelompok kabupaten/kota menghasilkan satu
                        surat.
                    </p>
                </div>
                <span
                    class="rounded-full bg-accent px-3 py-2 text-xs font-semibold text-primary"
                    >{{ items.length }} surat tugas</span
                >
            </div>
            <p
                v-if="!districts.length"
                class="mt-4 rounded-xl bg-muted p-4 text-sm text-muted-foreground"
            >
                Kategori kabupaten/kota belum tersedia.
                <Link
                    :href="masterDistricts()"
                    class="font-semibold text-primary underline"
                >
                    Tambahkan kabupaten/kota di Master Data.
                </Link>
            </p>
            <p
                v-if="!staffMembers.length"
                class="mt-4 rounded-xl bg-accent/50 p-4 text-sm leading-6 text-muted-foreground"
            >
                Belum ada dosen atau petugas di Master Data. Anda dapat mengisi
                nama dan dinas secara manual, atau
                <Link
                    :href="createStaffMember()"
                    class="font-semibold text-primary underline"
                >
                    menambahkan data dosen/petugas.
                </Link>
            </p>
            <div class="mt-5 flex flex-col gap-4">
                <details
                    v-for="(item, groupIndex) in items"
                    :key="groupIndex"
                    class="group/assignment rounded-xl border border-border"
                    :open="isExpanded(groupIndex)"
                    @toggle="toggleGroup($event, groupIndex)"
                >
                    <summary
                        class="flex min-h-16 cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 [&::-webkit-details-marker]:hidden"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <MapPin
                                class="size-5 shrink-0 text-primary"
                                aria-hidden="true"
                            />
                            <div class="min-w-0">
                                <h3 class="truncate font-semibold">
                                    {{
                                        districtCategoryLabel(
                                            item.recipient_district,
                                        ) || `Kelompok ${groupIndex + 1}`
                                    }}
                                </h3>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ item.assignees.length }} petugas · 1
                                    surat
                                </p>
                                <p
                                    v-if="invalidGroups.has(groupIndex)"
                                    class="mt-1 text-xs font-semibold text-destructive"
                                >
                                    Periksa data kelompok ini
                                </p>
                            </div>
                        </div>
                        <ChevronDown
                            class="size-5 shrink-0 text-muted-foreground transition-transform duration-200 group-open/assignment:rotate-180"
                            aria-hidden="true"
                        />
                    </summary>
                    <div
                        v-if="isExpanded(groupIndex)"
                        class="border-t border-border p-4 sm:p-5"
                    >
                        <div class="flex items-end gap-3">
                            <div class="min-w-0 flex-1">
                                <label
                                    :for="`assignment_county_${groupIndex}`"
                                    :class="labelClass"
                                    >Kabupaten/kota</label
                                >
                                <select
                                    :id="`assignment_county_${groupIndex}`"
                                    :value="item.recipient_district"
                                    required
                                    :disabled="busy"
                                    class="sisurat-input"
                                    :aria-invalid="
                                        Boolean(
                                            errorFor(
                                                `items.${groupIndex}.recipient_district`,
                                            ),
                                        )
                                    "
                                    @change="updateDistrict(groupIndex, $event)"
                                >
                                    <option value="" disabled>
                                        Pilih kabupaten/kota
                                    </option>
                                    <option
                                        v-if="
                                            item.recipient_district &&
                                            !districts.includes(
                                                item.recipient_district,
                                            )
                                        "
                                        :value="item.recipient_district"
                                        disabled
                                    >
                                        {{
                                            districtCategoryLabel(
                                                item.recipient_district,
                                            )
                                        }}
                                        (tidak tersedia)
                                    </option>
                                    <option
                                        v-for="district in districts"
                                        :key="district"
                                        :value="district"
                                        :disabled="
                                            districtUsed(district, groupIndex)
                                        "
                                    >
                                        {{ districtCategoryLabel(district) }}
                                    </option>
                                </select>
                            </div>
                            <button
                                v-if="items.length > 1"
                                type="button"
                                :disabled="busy"
                                class="sisurat-secondary-action min-h-11 text-destructive hover:bg-destructive/10"
                                :aria-label="`Hapus kelompok ${groupIndex + 1}`"
                                @click="removeGroup(groupIndex)"
                            >
                                <Trash2
                                    class="size-4"
                                    aria-hidden="true"
                                /><span class="hidden sm:inline">Hapus</span>
                            </button>
                        </div>
                        <p
                            v-if="
                                errorFor(
                                    `items.${groupIndex}.recipient_district`,
                                )
                            "
                            class="mt-2 text-sm text-destructive"
                        >
                            {{
                                errorFor(
                                    `items.${groupIndex}.recipient_district`,
                                )
                            }}
                        </p>
                        <div class="mt-5 flex flex-col gap-4">
                            <div
                                v-for="(person, personIndex) in item.assignees"
                                :key="personIndex"
                                class="grid items-start gap-3 rounded-xl bg-muted/35 p-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]"
                            >
                                <div
                                    v-if="staffMembers.length"
                                    class="sm:col-span-full"
                                >
                                    <label
                                        :for="`assignment_master_${groupIndex}_${personIndex}`"
                                        :class="labelClass"
                                        >Pilih dosen / petugas
                                        {{ personIndex + 1 }} dari Master
                                        Data</label
                                    >
                                    <select
                                        :id="`assignment_master_${groupIndex}_${personIndex}`"
                                        :value="
                                            selectedStaffMembers.get(person) ??
                                            ''
                                        "
                                        :disabled="busy"
                                        class="sisurat-input"
                                        :aria-describedby="`assignment_master_hint_${groupIndex}_${personIndex}`"
                                        @change="
                                            selectStaff(
                                                groupIndex,
                                                personIndex,
                                                $event,
                                            )
                                        "
                                    >
                                        <option value="">
                                            Isi manual / pilih data tersimpan
                                        </option>
                                        <option
                                            v-for="staff in staffMembers"
                                            :key="staff.id"
                                            :value="staff.id"
                                            :disabled="
                                                staffUsed(
                                                    staff.id,
                                                    groupIndex,
                                                    personIndex,
                                                )
                                            "
                                        >
                                            {{ staffLabel(staff) }}
                                        </option>
                                    </select>
                                    <p
                                        :id="`assignment_master_hint_${groupIndex}_${personIndex}`"
                                        class="mt-2 text-xs leading-5 text-muted-foreground"
                                    >
                                        Nama dan dinas terisi otomatis dan dapat
                                        diubah. Kabupaten/kota dari data petugas
                                        hanya terisi jika kelompok belum
                                        memiliki pilihan.
                                    </p>
                                </div>
                                <div>
                                    <label
                                        :for="`assignment_name_${groupIndex}_${personIndex}`"
                                        :class="labelClass"
                                        >Nama dosen / petugas
                                        {{ personIndex + 1 }}</label
                                    >
                                    <input
                                        :id="`assignment_name_${groupIndex}_${personIndex}`"
                                        v-model="person.name"
                                        type="text"
                                        required
                                        maxlength="150"
                                        placeholder="Nama lengkap dan gelar"
                                        class="sisurat-input"
                                        :aria-invalid="
                                            Boolean(
                                                errorFor(
                                                    `items.${groupIndex}.assignees.${personIndex}.name`,
                                                ),
                                            )
                                        "
                                        @input="editStaff(person)"
                                    />
                                    <p
                                        v-if="
                                            errorFor(
                                                `items.${groupIndex}.assignees.${personIndex}.name`,
                                            )
                                        "
                                        class="mt-1 text-sm text-destructive"
                                    >
                                        {{
                                            errorFor(
                                                `items.${groupIndex}.assignees.${personIndex}.name`,
                                            )
                                        }}
                                    </p>
                                </div>
                                <div>
                                    <label
                                        :for="`assignment_office_${groupIndex}_${personIndex}`"
                                        :class="labelClass"
                                        >Dinas</label
                                    >
                                    <input
                                        :id="`assignment_office_${groupIndex}_${personIndex}`"
                                        v-model="person.office"
                                        type="text"
                                        required
                                        maxlength="150"
                                        placeholder="Dosen"
                                        class="sisurat-input"
                                        :aria-invalid="
                                            Boolean(
                                                errorFor(
                                                    `items.${groupIndex}.assignees.${personIndex}.office`,
                                                ),
                                            )
                                        "
                                        @input="editStaff(person)"
                                    />
                                    <p
                                        v-if="
                                            errorFor(
                                                `items.${groupIndex}.assignees.${personIndex}.office`,
                                            )
                                        "
                                        class="mt-1 text-sm text-destructive"
                                    >
                                        {{
                                            errorFor(
                                                `items.${groupIndex}.assignees.${personIndex}.office`,
                                            )
                                        }}
                                    </p>
                                </div>
                                <button
                                    v-if="item.assignees.length > 1"
                                    type="button"
                                    :disabled="busy"
                                    class="sisurat-secondary-action min-h-11 justify-center text-destructive hover:bg-destructive/10 sm:mt-7"
                                    :aria-label="`Hapus dosen ${personIndex + 1} dari kelompok ${groupIndex + 1}`"
                                    @click="
                                        removeLecturer(groupIndex, personIndex)
                                    "
                                >
                                    <Trash2
                                        class="size-4"
                                        aria-hidden="true"
                                    /><span class="sm:hidden"
                                        >Hapus petugas</span
                                    >
                                </button>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="sisurat-secondary-action mt-4"
                            :disabled="item.assignees.length >= 30 || busy"
                            @click="addLecturer(groupIndex)"
                        >
                            <Plus class="size-4" aria-hidden="true" />Tambah
                            dosen / petugas
                        </button>
                        <p
                            v-if="errorFor(`items.${groupIndex}.assignees`)"
                            class="mt-2 text-sm text-destructive"
                        >
                            {{ errorFor(`items.${groupIndex}.assignees`) }}
                        </p>
                    </div>
                </details>
            </div>
            <button
                type="button"
                class="sisurat-secondary-action mt-5"
                :disabled="
                    busy ||
                    items.length >=
                        Math.min(districts.length, maxLettersPerSubmission)
                "
                @click="addGroup"
            >
                <UsersRound class="size-4" aria-hidden="true" />Tambah
                kabupaten/kota
            </button>
        </section>
    </div>
</template>
