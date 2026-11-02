<script setup lang="ts">
import type { PmbTestSession } from '@/lib/pmbTestSession';
defineProps<{ session: PmbTestSession; inverted?: boolean }>();
</script>
<template>
    <dl
        v-if="
            session.wave ||
            session.period ||
            session.academic_year ||
            session.campus
        "
        class="flex min-w-0 flex-wrap gap-x-6 gap-y-3 text-xs leading-6"
        :class="inverted ? 'text-white' : 'text-foreground'"
    >
        <div v-if="session.wave">
            <dt class="opacity-70">Gelombang</dt>
            <dd class="font-semibold">Gelombang {{ session.wave }}</dd>
        </div>
        <div v-if="session.period" class="max-w-full min-w-0">
            <dt class="opacity-70">Periode</dt>
            <dd class="font-semibold break-words">{{ session.period }}</dd>
        </div>
        <div v-if="session.academic_year">
            <dt class="opacity-70">Tahun akademik</dt>
            <dd class="font-semibold">{{ session.academic_year }}</dd>
        </div>
        <div v-if="session.campus">
            <dt class="opacity-70">Lokasi kampus</dt>
            <dd class="font-semibold">Kampus {{ session.campus }}</dd>
        </div>
    </dl>
</template>
