<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

type Exam = {
    id: number;
    title: string;
    subject: string | null;
    term: string | null;
    exam_type: string;
    duration_minutes: number;
    opens_at: string | null;
    closes_at: string | null;
    available: boolean;
    unavailable_reason: string | null;
};

defineProps<{ exams: Exam[] }>();
const errorMessage = ref<string | null>(null);
const startingExamId = ref<number | null>(null);

async function startExam(exam: Exam) {
    if (!exam.available) return;

    errorMessage.value = null;
    startingExamId.value = exam.id;

    const response = await fetch(route('attempts.start'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify({ exam_id: exam.id }),
    });

    const payload = await response.json();
    startingExamId.value = null;

    if (!response.ok) {
        errorMessage.value = payload.message ?? 'Unable to start this exam.';

        return;
    }

    router.visit(route('attempts.take', payload.attempt_id));
}
</script>

<template>
    <Head title="My Exams" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">My Exams</h2>
                <p class="text-sm text-gray-500">Published exams assigned to your current class or accommodation.</p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-4 sm:px-6 lg:px-8">
                <p v-if="errorMessage" class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700">{{ errorMessage }}</p>
                <div v-for="exam in exams" :key="exam.id" class="rounded-xl bg-white p-6 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">{{ exam.title }}</h3>
                            <p class="mt-1 text-sm text-gray-500">{{ exam.subject ?? 'No subject' }} · {{ exam.term ?? 'No term' }} · {{ exam.exam_type }}</p>
                            <p class="mt-2 text-sm text-gray-700">{{ exam.duration_minutes }} minutes</p>
                            <p class="mt-1 text-sm text-gray-500">Window: {{ exam.opens_at ?? 'Open now' }} to {{ exam.closes_at ?? 'No close time' }}</p>
                        </div>
                        <button :disabled="!exam.available || startingExamId === exam.id" class="rounded-lg px-4 py-2 text-sm font-semibold disabled:cursor-not-allowed disabled:bg-gray-200 disabled:text-gray-500 enabled:bg-slate-900 enabled:text-white" @click="startExam(exam)">
                            {{ startingExamId === exam.id ? 'Starting...' : exam.available ? 'Start or Resume' : 'Unavailable' }}
                        </button>
                    </div>
                    <p v-if="exam.unavailable_reason" class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">{{ exam.unavailable_reason }}</p>
                </div>
                <div v-if="exams.length === 0" class="rounded-xl bg-white p-10 text-center text-sm text-gray-500 shadow-sm">No assigned exams yet.</div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
