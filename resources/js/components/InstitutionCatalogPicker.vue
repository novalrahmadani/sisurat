<script setup lang="ts">
import { Building2, Check, MapPin, Search } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import {
    institutionLabels,
    maxLettersPerSubmission,
    normalizeInstitutionIdentity,
} from '@/lib/letters';
import type {
    InstitutionType,
    LetterItem,
    MasterInstitution,
} from '@/lib/letters';

const props = defineProps<{
    institutions: MasterInstitution[];
    destination: InstitutionType;
    existingItems: LetterItem[];
    busy: boolean;
}>();
const emit = defineEmits<{ apply: [ids: number[]] }>();
const query = ref('');
const district = ref('');
const selectedIds = ref<number[]>([]);
const typeInstitutions = computed(() =>
    props.institutions.filter(
        (institution) => institution.institution_type === props.destination,
    ),
);
const districtOptions = computed(() =>
    Array.from(
        new Set(
            typeInstitutions.value
                .map((institution) => institution.district)
                .filter(Boolean),
        ),
    ).sort((left, right) => left.localeCompare(right, 'id')),
);
const existingIds = computed(
    () =>
        new Set(
            props.existingItems
                .map((item) => item.institution_id)
                .filter((id): id is number => id !== null),
        ),
);
const existingKeys = computed(
    () =>
        new Set(
            props.existingItems.map((item) =>
                normalizeInstitutionIdentity(
                    item.institution_type,
                    item.institution_name || item.activity_destination,
                    item.recipient_district,
                    item.recipient_address,
                ),
            ),
        ),
);
const filteredInstitutions = computed(() => {
    const search = query.value.trim().toLocaleLowerCase('id-ID');
    return typeInstitutions.value.filter(
        (institution) =>
            (!district.value || institution.district === district.value) &&
            (!search ||
                `${institution.name} ${institution.district} ${institution.address ?? ''}`
                    .toLocaleLowerCase('id-ID')
                    .includes(search)),
    );
});
const availableIds = computed(() =>
    typeInstitutions.value
        .filter((institution) => !alreadyInList(institution))
        .map((institution) => institution.id),
);
const shortLabel = computed(() =>
    props.destination === 'school' ? 'sekolah' : 'pondok',
);
const hasFilters = computed(() =>
    Boolean(query.value.trim() || district.value),
);
const selectedCount = computed(() => selectedIds.value.length);
const filteredAvailableIds = computed(() =>
    filteredInstitutions.value
        .filter((institution) => !alreadyInList(institution))
        .map((institution) => institution.id),
);
const hiddenSelectedCount = computed(() => {
    const visibleIds = new Set(
        filteredInstitutions.value.map((institution) => institution.id),
    );
    return selectedIds.value.filter((id) => !visibleIds.has(id)).length;
});
const selectedDistrictCounts = computed(() => {
    const selected = new Set(selectedIds.value);
    const counts = new Map<string, number>();
    typeInstitutions.value.forEach((institution) => {
        if (!selected.has(institution.id)) return;
        const label = institution.district || 'Wilayah belum tersedia';
        counts.set(label, (counts.get(label) ?? 0) + 1);
    });
    return Array.from(counts, ([label, count]) => ({ label, count }));
});

function alreadyInList(institution: MasterInstitution): boolean {
    return (
        existingIds.value.has(institution.id) ||
        existingKeys.value.has(
            normalizeInstitutionIdentity(
                institution.institution_type,
                institution.name,
                institution.district,
                institution.address ?? '',
            ),
        )
    );
}
function toggleInstitution(id: number, event: Event): void {
    if (props.busy) return;
    const checked = (event.target as HTMLInputElement).checked;
    selectedIds.value = checked
        ? Array.from(new Set([...selectedIds.value, id]))
        : selectedIds.value.filter((selectedId) => selectedId !== id);
}
function selectAll(): void {
    if (props.busy) return;
    selectedIds.value = [...availableIds.value];
}
function selectFiltered(): void {
    if (props.busy) return;
    const matchingIds = filteredAvailableIds.value;
    selectedIds.value = Array.from(
        new Set([...selectedIds.value, ...matchingIds]),
    );
}
watch(
    () => props.destination,
    () => {
        selectedIds.value = [];
        query.value = '';
        district.value = '';
    },
);
watch(availableIds, (ids) => {
    const allowedIds = new Set(ids);
    selectedIds.value = selectedIds.value.filter((id) => allowedIds.has(id));
});
</script>

<template>
    <section
        class="sisurat-panel p-5 sm:p-7"
        aria-labelledby="catalog-picker-heading"
    >
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-start gap-3">
                <span class="sisurat-icon-tile"
                    ><Building2 class="size-6" aria-hidden="true"
                /></span>
                <div>
                    <h2
                        id="catalog-picker-heading"
                        class="text-base font-semibold sm:text-lg"
                    >
                        Pilih {{ shortLabel }} dari Master Data
                    </h2>
                    <p class="mt-2 text-sm leading-6 text-muted-foreground">
                        Pilih satu, beberapa, atau semua tujuan. Nama,
                        kabupaten/kota, dan alamat yang tersedia akan terisi
                        otomatis.
                    </p>
                </div>
            </div>
            <span
                class="rounded-full bg-accent px-3 py-1.5 text-xs font-medium text-accent-foreground"
                >{{ typeInstitutions.length }} {{ shortLabel }} tersedia</span
            >
        </div>
        <div v-if="typeInstitutions.length" class="mt-5 space-y-4">
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label
                        for="catalog-search"
                        class="mb-1.5 block text-sm font-medium"
                        >Cari {{ shortLabel }}</label
                    >
                    <div class="relative">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                            aria-hidden="true"
                        /><input
                            id="catalog-search"
                            v-model="query"
                            :disabled="busy"
                            type="search"
                            class="sisurat-input pl-10"
                            placeholder="Cari nama atau kabupaten/kota"
                            maxlength="150"
                        />
                    </div>
                </div>
                <div>
                    <label
                        for="catalog-district"
                        class="mb-1.5 block text-sm font-medium"
                        >Kabupaten/kota</label
                    >
                    <select
                        id="catalog-district"
                        v-model="district"
                        :disabled="busy"
                        class="sisurat-input"
                    >
                        <option value="">Semua kabupaten/kota</option>
                        <option
                            v-for="option in districtOptions"
                            :key="option"
                            :value="option"
                        >
                            {{ option }}
                        </option>
                    </select>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    class="sisurat-secondary-action min-h-10 text-xs"
                    :disabled="busy || !availableIds.length"
                    @click="selectAll"
                >
                    <Check class="size-4" aria-hidden="true" />Pilih semua data
                    {{ shortLabel }} ({{ availableIds.length }})
                </button>
                <button
                    v-if="hasFilters"
                    type="button"
                    class="sisurat-secondary-action min-h-10 text-xs"
                    :disabled="busy || !filteredAvailableIds.length"
                    @click="selectFiltered"
                >
                    Pilih semua hasil filter ({{ filteredAvailableIds.length }})
                </button>
                <button
                    v-if="selectedCount"
                    :disabled="busy"
                    type="button"
                    class="min-h-10 px-3 text-xs font-medium text-muted-foreground hover:text-foreground"
                    @click="selectedIds = []"
                >
                    Batalkan pilihan
                </button>
                <span class="ml-auto text-xs text-muted-foreground"
                    >{{ filteredInstitutions.length }} tujuan ditampilkan</span
                >
            </div>
            <p class="text-xs leading-6 text-muted-foreground">
                “Pilih semua data” mencakup semua kabupaten/kota, termasuk data
                di luar filter. “Pilih semua hasil filter” menambahkan tujuan
                yang sedang sesuai pencarian. Tujuan yang sudah ada dalam daftar
                surat tidak ditambahkan lagi.
            </p>
            <div
                v-if="selectedCount"
                class="rounded-xl border border-primary/15 bg-accent/50 p-3"
                role="status"
            >
                <p class="text-sm font-medium">
                    {{ selectedCount }} tujuan dipilih
                </p>
                <p
                    v-if="hiddenSelectedCount"
                    class="mt-1 text-xs leading-6 text-muted-foreground"
                >
                    {{ hiddenSelectedCount }} pilihan berada di luar filter saat
                    ini dan tetap akan diterapkan.
                </p>
                <ul
                    class="mt-2 flex flex-wrap gap-2"
                    aria-label="Jumlah pilihan per kabupaten/kota"
                >
                    <li
                        v-for="region in selectedDistrictCounts"
                        :key="region.label"
                        class="rounded-full bg-card px-3 py-1 text-xs text-foreground"
                    >
                        {{ region.label }}: {{ region.count }}
                    </li>
                </ul>
            </div>
            <div
                v-if="filteredInstitutions.length"
                class="grid max-h-80 gap-2 overflow-y-auto rounded-xl border border-border bg-background/45 p-3 md:grid-cols-2"
                aria-label="Daftar tujuan dalam basis data"
            >
                <label
                    v-for="institution in filteredInstitutions"
                    :key="institution.id"
                    :class="[
                        'flex cursor-pointer items-start gap-3 rounded-lg border p-3',
                        alreadyInList(institution)
                            ? 'cursor-default border-border/70 bg-muted/50 opacity-75'
                            : selectedIds.includes(institution.id)
                              ? 'border-primary/40 bg-accent'
                              : 'border-transparent bg-card hover:border-primary/30',
                    ]"
                >
                    <input
                        type="checkbox"
                        class="mt-1 size-4 shrink-0 accent-[#005a36]"
                        :checked="selectedIds.includes(institution.id)"
                        :disabled="busy || alreadyInList(institution)"
                        @change="toggleInstitution(institution.id, $event)"
                    />
                    <span class="min-w-0 flex-1"
                        ><span class="block text-xs leading-6 font-medium">{{
                            institution.name
                        }}</span
                        ><span
                            class="mt-1 flex items-start gap-1.5 text-[11px] leading-5 text-muted-foreground"
                            ><MapPin
                                class="mt-0.5 size-3 shrink-0"
                                aria-hidden="true"
                            />{{
                                institution.district ||
                                'Kabupaten/kota belum tersedia'
                            }}</span
                        ><span
                            v-if="institution.address"
                            class="mt-1 block text-[11px] leading-5 text-muted-foreground"
                            >{{ institution.address }}</span
                        ><span
                            v-if="alreadyInList(institution)"
                            class="mt-1 block text-[10px] font-medium text-primary"
                            >Sudah di daftar surat</span
                        ></span
                    >
                </label>
            </div>
            <div
                v-else
                class="rounded-xl border border-dashed border-border px-5 py-8 text-center text-sm text-muted-foreground"
            >
                Tidak ada {{ shortLabel }} sesuai pencarian. Coba nama atau
                kabupaten/kota lain.
            </div>
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4"
            >
                <div>
                    <p class="text-sm font-semibold">
                        {{ selectedCount }} {{ shortLabel }} dipilih
                    </p>
                    <p class="mt-1 text-xs leading-6 text-muted-foreground">
                        Pilihan akan ditambahkan tanpa menggandakan tujuan yang
                        sudah ada. Maksimal
                        {{ maxLettersPerSubmission.toLocaleString('id-ID') }}
                        surat.
                    </p>
                </div>
                <button
                    type="button"
                    class="sisurat-primary-action"
                    :disabled="busy || !selectedCount"
                    @click="emit('apply', selectedIds)"
                >
                    Terapkan ke daftar<Check
                        class="size-4"
                        aria-hidden="true"
                    />
                </button>
            </div>
        </div>
        <p
            v-else
            class="mt-5 rounded-xl bg-muted/60 p-4 text-sm leading-7 text-muted-foreground"
        >
            Belum ada data
            {{ institutionLabels[destination].toLowerCase() }} dalam basis data.
            Anda tetap dapat mengisi tujuan secara manual atau melalui impor.
        </p>
    </section>
</template>
