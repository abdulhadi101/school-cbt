<script setup lang="ts">
import { renderMath } from '@/support/math';
import { onMounted, ref, watch } from 'vue';

const props = defineProps<{ content: string | null | undefined }>();

const el = ref<HTMLElement | null>(null);

function render() {
    if (!el.value) return;
    el.value.innerHTML = props.content ?? '';
    renderMath(el.value);
}

onMounted(render);
watch(() => props.content, render);
</script>

<template>
    <span ref="el" class="math-content"></span>
</template>
