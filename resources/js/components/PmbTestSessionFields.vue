<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import type { WaveInput, CampusInput } from '@/lib/pmbTestSession';
defineProps<{
    idPrefix: string;
    required?: boolean;
    errors: {
        wave?: string;
        period?: string;
        academic_year?: string;
        campus?: string;
    };
}>();
const wave = defineModel<WaveInput>('wave', { required: true });
const period = defineModel<string>('period', { required: true });
const academicYear = defineModel<string>('academicYear', { required: true });
const campus = defineModel<CampusInput>('campus', { required: true });
</script>
<template>
    <div class="grid min-w-0 gap-5 sm:grid-cols-2">
        <div class="min-w-0">
            <label
                :for="`${idPrefix}-wave`"
                class="mb-2 block text-sm font-medium"
                >Gelombang<span v-if="required" class="text-destructive">
                    *</span
                ></label
            >
            <select
                :id="`${idPrefix}-wave`"
                v-model="wave"
                :required="required"
                class="sisurat-input"
                :aria-invalid="Boolean(errors.wave)"
                :aria-describedby="`${idPrefix}-wave-error`"
            >
                <option value="">Pilih gelombang</option>
                <option
                    v-for="number in [1, 2, 3]"
                    :key="number"
                    :value="number"
                >
                    Gelombang {{ number }}
                </option>
            </select>
            <InputError
                :id="`${idPrefix}-wave-error`"
                class="mt-2"
                :message="errors.wave"
            />
        </div>
        <div class="min-w-0">
            <label
                :for="`${idPrefix}-period`"
                class="mb-2 block text-sm font-medium"
                >Periode<span v-if="required" class="text-destructive">
                    *</span
                ></label
            >
            <input
                :id="`${idPrefix}-period`"
                v-model="period"
                :required="required"
                type="text"
                maxlength="100"
                class="sisurat-input"
                placeholder="Contoh: 1 atau 2"
                :aria-invalid="Boolean(errors.period)"
                :aria-describedby="`${idPrefix}-period-help ${idPrefix}-period-error`"
            />
            <p
                :id="`${idPrefix}-period-help`"
                class="mt-2 text-xs leading-5 text-muted-foreground"
            >
                Ketik nomor periode tes, misalnya 1 atau 2.
            </p>
            <InputError
                :id="`${idPrefix}-period-error`"
                class="mt-2"
                :message="errors.period"
            />
        </div>
        <div class="min-w-0">
            <label
                :for="idPrefix + '-academic-year'"
                class="mb-2 block text-sm font-medium"
            >
                Tahun akademik<span v-if="required" class="text-destructive">
                    *</span
                >
            </label>
            <input
                :id="idPrefix + '-academic-year'"
                v-model="academicYear"
                :required="required"
                type="text"
                maxlength="9"
                class="sisurat-input"
                placeholder="Contoh: 2027/2028"
                :aria-invalid="Boolean(errors.academic_year)"
                :aria-describedby="
                    idPrefix +
                    '-academic-year-help ' +
                    idPrefix +
                    '-academic-year-error'
                "
            />
            <p
                :id="idPrefix + '-academic-year-help'"
                class="mt-2 text-xs leading-5 text-muted-foreground"
            >
                Ketik tahun akademik pendaftaran, misalnya 2027/2028.
            </p>
            <InputError
                :id="idPrefix + '-academic-year-error'"
                class="mt-2"
                :message="errors.academic_year"
            />
        </div>
        <div class="min-w-0">
            <label
                :for="`${idPrefix}-campus`"
                class="mb-2 block text-sm font-medium"
                >Lokasi kampus<span v-if="required" class="text-destructive">
                    *</span
                ></label
            >
            <select
                :id="`${idPrefix}-campus`"
                v-model="campus"
                :required="required"
                class="sisurat-input"
                :aria-invalid="Boolean(errors.campus)"
                :aria-describedby="`${idPrefix}-campus-error`"
            >
                <option value="">Pilih kampus</option>
                <option value="B">Kampus B</option>
                <option value="C">Kampus C</option>
            </select>
            <InputError
                :id="`${idPrefix}-campus-error`"
                class="mt-2"
                :message="errors.campus"
            />
        </div>
    </div>
</template>
