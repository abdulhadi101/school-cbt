<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

type Stat = { label: string; value: string | number; color?: string };
type Attempt = {
    id: number;
    exam_title: string;
    student_name: string;
    admission_number: string | null;
    status: string;
    score: string | number | null;
    submitted_at: string | null;
};
type ExamGrading = {
    id: number;
    title: string;
    pending_count: number;
    total_count: number;
};

defineProps<{
    stats: Record<string, number>;
    recentAttempts: Attempt[];
    examsNeedingGrading: ExamGrading[];
}>();

function formatStatus(status: string): string {
    return status.replaceAll('_', ' ');
}

function formatDate(date: string | null): string {
    if (!date) return '-';
    return new Date(date).toLocaleString();
}
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Dashboard</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div v-for="(value, key) in stats" :key="key" class="rounded-xl bg-white p-5 shadow-sm">
                        <p class="text-sm capitalize text-gray-500">{{ String(key).replaceAll('_', ' ') }}</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900">{{ value }}</p>
                    </div>
                </div>

                <div v-if="examsNeedingGrading.length > 0" class="rounded-xl bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">Exams Needing Grading</h3>
                    <p class="mt-1 text-sm text-gray-500">Exams with submissions waiting to be graded.</p>
                    <div class="mt-4 overflow-hidden rounded-lg border border-gray-100">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Exam</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Pending</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Total</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="exam in examsNeedingGrading" :key="exam.id">
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ exam.title }}</td>
                                    <td class="px-4 py-3 text-sm text-amber-600 font-semibold">{{ exam.pending_count }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ exam.total_count }}</td>
                                    <td class="px-4 py-3 text-right text-sm">
                                        <Link :href="route('staff.results.show', exam.id)" class="font-semibold text-slate-700 hover:text-slate-950">View</Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="recentAttempts.length > 0" class="rounded-xl bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">Recent Attempts</h3>
                    <div class="mt-4 overflow-hidden rounded-lg border border-gray-100">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Student</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Exam</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Score</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Submitted</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="attempt in recentAttempts" :key="attempt.id">
                                    <td class="px-4 py-3">
                                        <div class="text-sm font-medium text-gray-900">{{ attempt.student_name || 'Unknown' }}</div>
                                        <div class="text-xs text-gray-500">{{ attempt.admission_number }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ attempt.exam_title }}</td>
                                    <td class="px-4 py-3">
                                        <span class="rounded-full bg-gray-100 px-3 py-1 text-xs capitalize text-gray-700">{{ formatStatus(attempt.status) }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ attempt.score ?? '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ formatDate(attempt.submitted_at) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="examsNeedingGrading.length === 0 && recentAttempts.length === 0" class="rounded-xl bg-white p-10 text-center shadow-sm">
                    <p class="text-gray-500">No recent activity to display.</p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
