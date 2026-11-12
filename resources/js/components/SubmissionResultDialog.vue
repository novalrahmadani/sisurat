<script setup lang="ts">
import { CircleAlert, CheckCircle2 } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

withDefaults(
    defineProps<{
        open: boolean;
        variant: 'success' | 'error';
        title: string;
        description: string;
        eyebrow?: string;
    }>(),
    { eyebrow: undefined },
);

const emit = defineEmits<{ 'update:open': [open: boolean] }>();
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-w-md rounded-2xl p-6 sm:p-8">
            <DialogHeader class="justify-items-center text-center">
                <span
                    :class="[
                        'mb-2 grid size-16 place-items-center rounded-full',
                        variant === 'success'
                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                            : 'bg-destructive/10 text-destructive',
                    ]"
                    aria-hidden="true"
                >
                    <CheckCircle2
                        v-if="variant === 'success'"
                        class="size-8"
                    />
                    <CircleAlert v-else class="size-8" />
                </span>
                <p
                    v-if="eyebrow"
                    :class="[
                        'text-xs font-semibold tracking-widest uppercase',
                        variant === 'success'
                            ? 'text-emerald-700 dark:text-emerald-300'
                            : 'text-destructive',
                    ]"
                >
                    {{ eyebrow }}
                </p>
                <DialogTitle class="text-center text-xl font-semibold">
                    {{ title }}
                </DialogTitle>
                <DialogDescription
                    class="max-w-sm text-center text-sm leading-7"
                >
                    {{ description }}
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="sm:justify-center">
                <Button
                    class="sisurat-primary-action w-full justify-center sm:w-auto"
                    @click="emit('update:open', false)"
                >
                    Mengerti
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
