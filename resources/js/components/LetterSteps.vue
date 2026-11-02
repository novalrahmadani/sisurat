<script setup lang="ts">
import { Check } from '@lucide/vue';

withDefaults(
    defineProps<{
        modelValue: 1 | 2 | 3;
        busy?: boolean;
        steps?: readonly string[];
    }>(),
    {
        busy: false,
        steps: () => ['Jenis & pengaturan', 'Isi data', 'Periksa & buat'],
    },
);
const emit = defineEmits<{
    'update:modelValue': [value: 1 | 2 | 3];
}>();
</script>

<template>
    <nav aria-label="Langkah pembuatan surat" class="sisurat-panel p-3 sm:p-4">
        <ol class="grid gap-2 sm:grid-cols-3">
            <li v-for="(label, index) in steps.slice(0, 3)" :key="label">
                <button
                    type="button"
                    :disabled="busy"
                    :aria-current="
                        modelValue === index + 1 ? 'step' : undefined
                    "
                    :class="[
                        'flex min-h-14 w-full items-center gap-3 rounded-xl border px-3 py-3 text-left text-sm transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary disabled:cursor-wait disabled:opacity-60',
                        modelValue === index + 1
                            ? 'border-primary/30 bg-accent text-primary'
                            : 'border-transparent text-muted-foreground hover:border-border hover:bg-muted/50',
                    ]"
                    @click="emit('update:modelValue', (index + 1) as 1 | 2 | 3)"
                >
                    <span
                        :class="[
                            'grid size-8 shrink-0 place-items-center rounded-full text-xs font-semibold',
                            modelValue >= index + 1
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-muted text-muted-foreground',
                        ]"
                        aria-hidden="true"
                    >
                        <Check v-if="modelValue > index + 1" class="size-4" />
                        <template v-else>{{ index + 1 }}</template>
                    </span>
                    <span class="font-medium">{{ label }}</span>
                    <span v-if="modelValue === index + 1" class="sr-only"
                        >, langkah saat ini</span
                    >
                </button>
            </li>
        </ol>
    </nav>
</template>
