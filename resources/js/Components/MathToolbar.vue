<script setup lang="ts">
import { MATH_SNIPPETS } from '@/support/math';

defineEmits<{ insert: [snippet: string] }>();
</script>

<template>
    <div class="flex flex-wrap items-center gap-1">
        <span class="mr-1 text-xs font-semibold uppercase tracking-wide text-gray-400">Math</span>
        <button
            v-for="snippet in MATH_SNIPPETS"
            :key="snippet.label"
            type="button"
            :title="snippet.hint"
            class="rounded-md border border-gray-200 bg-gray-50 px-2 py-1 text-sm text-gray-700 hover:bg-gray-100"
            @click="$emit('insert', snippet.insert)"
        >
            {{ snippet.label }}
        </button>
    </div>
</template>
