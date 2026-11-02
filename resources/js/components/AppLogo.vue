<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div class="flex min-w-0 items-center gap-3">
        <div
            class="flex aspect-square size-8 shrink-0 items-center justify-center rounded-lg bg-white text-[#005a36] shadow-sm"
        >
            <AppLogoIcon class="size-7" />
        </div>
        <div
            class="grid flex-1 gap-0.5 text-left group-data-[collapsible=icon]:hidden"
        >
            <span class="text-base leading-tight font-semibold tracking-wide"
                >SISURAT</span
            >
            <span class="text-[10px] leading-tight opacity-75"
                >Sekretaris PMB · UNUHA</span
            >
        </div>
    </div>
</template>
