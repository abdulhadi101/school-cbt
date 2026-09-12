<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

type Attempt = {
    id: number;
    exam_title: string;
    subject: string | null;
    status: string;
    score: string | number | null;
    max_score: string | number | null;
    percentage: string | number | null;
    submitted_at: string | null;
    graded_at: string | null;
    released_at: string | null;
};

defineProps<{ attempts: Attempt[] }>();

function formatDate(date: string | null): string {
    if (!date) return '-';
    return new Date(date).toLocaleString();
}
</script>

<template>
    <Head title="My Results" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">My Results</h2>
                <p class="text-sm text-gray-500">View your released exam results.</p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                <div v-if="attempts.length === 0" class="rounded-xl bg-white p-10 text-center text-sm text-gray-500 shadow-sm">
                    No results available yet.
                </div>

                <div v-for="attempt in attempts" :key="attempt.id" class="rounded-xl bg-white shadow-sm">
                    <div class="flex items-center justify-between px-6 py-4">
                        <div>
                            <h3 class="font-semibold text-gray-900">{{ attempt.exam_title }}</h3>
                            <p class="text-sm text-gray-500">{{ attempt.subject ?? 'No subject' }}</p>
                        </div>
                        <div class="text-right">
                            <div v-if="attempt.score !== null" class="text-2xl font-bold text-gray-900">
                                {{ attempt.percentage }}%
                            </div>
                            <div v-if="attempt.score !== null" class="text-sm text-gray-500">
                                {{ attempt.score }} / {{ attempt.max_score }}
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center justify-between border-t border-gray-100 px-6 py-3">
                        <div class="flex gap-4 text-xs text-gray-500">
                            <span>Submitted: {{ formatDate(attempt.submitted_at) }}</span>
                            <span v-if="attempt.released_at">Released: {{ formatDate(attempt.released_at) }}</span>
                        </div>
                        <Link :href="route('student.results.show', attempt.id)" class="text-sm font-semibold text-slate-700 hover:text-slate-950">
                            View Details
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
