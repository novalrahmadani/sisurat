<script setup lang="ts">
import { computed } from 'vue';
import type { LetterTemplateTokens } from '@/lib/letterTemplates';

const props = defineProps<{
    text: string | undefined;
    tokens: LetterTemplateTokens;
}>();

const segments = computed(() => {
    const text = props.text ?? '';
    const parts: Array<{ text: string; emphasized: boolean }> = [];
    const expression = /\{\{\s*([a-z_]+)\s*\}\}/g;
    let offset = 0;

    for (const match of text.matchAll(expression)) {
        const index = match.index;
        if (index > offset) {
            parts.push({ text: text.slice(offset, index), emphasized: false });
        }
        const token = match[1];
        const knownToken = Object.hasOwn(props.tokens, token);
        parts.push({
            text: knownToken ? props.tokens[token] : match[0],
            emphasized: knownToken && token === 'hasil_pkkmb',
        });
        offset = index + match[0].length;
    }

    if (offset < text.length) {
        parts.push({ text: text.slice(offset), emphasized: false });
    }

    return parts;
});
</script>

<template>
    <span class="letter-template-text">
        <template v-for="(segment, index) in segments" :key="index">
            <strong v-if="segment.emphasized">{{ segment.text }}</strong>
            <template v-else>{{ segment.text }}</template>
        </template>
    </span>
</template>

<style scoped>
.letter-template-text {
    white-space: pre-line;
}
</style>
