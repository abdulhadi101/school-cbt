<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

type Attempt = {
    id: number;
    student_name: string;
    admission_number: string | null;
    status: string;
    score: string | number | null;
    max_score: string | number | null;
    percentage: string | number | null;
    needs_grading: boolean;
};

defineProps<{
    exam: { id: number; title: string; subject: string | null };
    attempts: Attempt[];
    pendingCount: number;
}>();

function formatStatus(status: string): string {
    return status.replaceAll('_', ' ');
}
</script>

<template>
    <Head :title="`Grading - ${exam.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Grading</h2>
                    <p class="text-sm text-gray-500">{{ exam.title }} <span v-if="exam.subject" class="text-gray-400">/ {{ exam.subject }}</span></p>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('staff.grading.index')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Back to Queue</Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-xl bg-white p-5 shadow-sm">
                        <p class="text-sm text-gray-500">Pending</p>
                        <p class="mt-2 text-3xl font-bold text-amber-600">{{ pendingCount }}</p>
                    </div>
                    <div class="rounded-xl bg-white p-5 shadow-sm">
                        <p class="text-sm text-gray-500">Total Attempts</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900">{{ attempts.length }}</p>
                    </div>
                    <div class="rounded-xl bg-white p-5 shadow-sm">
                        <p class="text-sm text-gray-500">Graded</p>
                        <p class="mt-2 text-3xl font-bold text-emerald-600">{{ attempts.length - pendingCount }}</p>
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Student</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Score</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="attempt in attempts" :key="attempt.id" :class="{ 'bg-amber-50': attempt.needs_grading }">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900">{{ attempt.student_name || 'Unknown' }}</div>
                                    <div class="text-xs text-gray-500">{{ attempt.admission_number }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-3 py-1 text-xs capitalize"
                                        :class="{
                                            'bg-amber-100 text-amber-700': attempt.needs_grading,
                                            'bg-blue-100 text-blue-700': attempt.status === 'graded' && !attempt.needs_grading,
                                            'bg-gray-100 text-gray-600': !attempt.needs_grading && attempt.status !== 'graded',
                                        }">
                                        {{ attempt.needs_grading ? 'Needs Grading' : formatStatus(attempt.status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    <span v-if="attempt.score !== null">{{ attempt.score }} / {{ attempt.max_score }}</span>
                                    <span v-else>-</span>
                                </td>
                                <td class="px-4 py-3 text-right text-sm">
                                    <Link :href="route('staff.grading.attempt', attempt.id)" class="font-semibold text-slate-700 hover:text-slate-950">
                                        {{ attempt.needs_grading ? 'Grade' : 'View' }}
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="attempts.length === 0">
                                <td colspan="4" class="px-4 py-10 text-center text-sm text-gray-500">No attempts found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
