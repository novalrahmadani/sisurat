<script setup lang="ts">
import { RotateCcw, Save, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';

const props = withDefaults(
    defineProps<{
        hasSavedDraft: boolean;
        needsDecision: boolean;
        timestamp: string | null;
        error: string;
        available: boolean;
        disabled?: boolean;
    }>(),
    { disabled: false },
);
const emit = defineEmits<{ restore: []; discard: []; save: [] }>();
const savedAt = computed(() => {
    if (!props.timestamp) return '';
    const date = new Date(props.timestamp);
    return Number.isFinite(date.getTime())
        ? new Intl.DateTimeFormat('id-ID', {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(date)
        : '';
});
</script>

<template>
    <div
        class="sisurat-panel flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
    >
        <div class="min-w-0 text-sm">
            <p class="font-medium text-foreground">
                {{
                    needsDecision
                        ? 'Draf tersimpan tersedia'
                        : hasSavedDraft
                          ? 'Draf tersimpan di browser ini'
                          : 'Draf lokal'
                }}
            </p>
            <p v-if="error" class="mt-1 text-sm text-destructive" role="alert">
                {{ error }}
            </p>
            <p v-else class="mt-1 leading-5 text-muted-foreground">
                <template v-if="needsDecision"
                    >Lanjutkan draft tersimpan atau hapus draft lama untuk
                    memulai yang baru.<span v-if="savedAt">
                        Disimpan {{ savedAt }}.</span
                    ></template
                >
                <template v-else-if="savedAt"
                    >Terakhir disimpan {{ savedAt }}. Draf hanya tersedia pada
                    browser dan akun ini.</template
                >
                <template v-else-if="available"
                    >Perubahan disimpan otomatis pada browser dan akun
                    ini.</template
                >
                <template v-else
                    >Penyimpanan draf browser belum tersedia.</template
                >
            </p>
        </div>
        <div
            v-if="available"
            class="flex shrink-0 flex-wrap items-center gap-2"
        >
            <template v-if="needsDecision">
                <Button
                    type="button"
                    size="sm"
                    :disabled="disabled"
                    @click="emit('restore')"
                    ><RotateCcw class="size-4" />Lanjutkan draft</Button
                >
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="disabled"
                    @click="emit('discard')"
                    ><Trash2 class="size-4" />Hapus draft lama</Button
                >
            </template>
            <Button
                v-else
                type="button"
                variant="outline"
                size="sm"
                :disabled="disabled"
                @click="emit('save')"
                ><Save class="size-4" />Simpan draf</Button
            >
        </div>
    </div>
</template>
