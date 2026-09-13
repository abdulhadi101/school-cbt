<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

type Stat = { label: string; value: string | number; icon: string; color: string };
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

const props = defineProps<{
    stats: Record<string, number>;
    recentAttempts: Attempt[];
    examsNeedingGrading: ExamGrading[];
}>();

const statCards = computed<Stat[]>(() => {
    const icons: Record<string, { icon: string; color: string }> = {
        total_exams: { icon: 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z', color: 'from-blue-500 to-blue-600' },
        total_students: { icon: 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z', color: 'from-emerald-500 to-emerald-600' },
        total_staff: { icon: 'M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z', color: 'from-violet-500 to-violet-600' },
        total_questions: { icon: 'M6.429 9.75 2.25 12l4.179 2.25m0-4.5 5.571 3 5.571-3m-11.142 0L2.25 7.5 12 2.25l9.75 5.25-4.179 2.25m0 0L21.75 12l-4.179 2.25m0 0 4.179 2.25L12 21.75 2.25 16.5l4.179-2.25m11.142 0-5.571 3-5.571-3', color: 'from-amber-500 to-amber-600' },
    };

    return Object.entries(props.stats).map(([key, value]) => ({
        label: key.replaceAll('_', ' '),
        value,
        icon: icons[key]?.icon ?? icons.total_exams.icon,
        color: icons[key]?.color ?? 'from-slate-500 to-slate-600',
    }));
});

function formatStatus(status: string): string {
    return status.replaceAll('_', ' ');
}

function formatDate(date: string | null): string {
    if (!date) return '-';
    return new Date(date).toLocaleString();
}

function statusColor(status: string): string {
    const colors: Record<string, string> = {
        submitted: 'bg-amber-50 text-amber-700 ring-amber-600/20',
        graded: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        in_progress: 'bg-blue-50 text-blue-700 ring-blue-600/20',
        expired: 'bg-red-50 text-red-700 ring-red-600/20',
    };
    return colors[status] ?? 'bg-slate-50 text-slate-700 ring-slate-600/20';
}
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-xl font-bold text-slate-900">Dashboard</h1>
            <p class="mt-0.5 text-sm text-slate-500">Welcome back. Here's what's happening today.</p>
        </template>

        <!-- Stats -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div
                v-for="stat in statCards"
                :key="stat.label"
                class="group relative overflow-hidden rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200/60 transition-all hover:shadow-md"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500 capitalize">{{ stat.label }}</p>
                        <p class="mt-2 text-3xl font-bold text-slate-900">{{ stat.value }}</p>
                    </div>
                    <div :class="['flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br shadow-sm', stat.color]">
                        <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="stat.icon" />
                        </svg>
                    </div>
                </div>
                <div class="absolute inset-x-0 bottom-0 h-0.5 bg-gradient-to-r opacity-0 transition-opacity group-hover:opacity-100" :class="stat.color" />
            </div>
        </div>

        <!-- Grading + Recent -->
        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <!-- Exams needing grading -->
            <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/60">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">Needs Grading</h2>
                        <p class="text-xs text-slate-500">Exams awaiting manual grading</p>
                    </div>
                    <span v-if="examsNeedingGrading.length" class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20">
                        {{ examsNeedingGrading.length }}
                    </span>
                </div>
                <div v-if="examsNeedingGrading.length" class="divide-y divide-slate-100">
                    <div v-for="exam in examsNeedingGrading" :key="exam.id" class="flex items-center justify-between px-6 py-3.5 transition-colors hover:bg-slate-50/50">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-slate-900">{{ exam.title }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ exam.pending_count }} of {{ exam.total_count }} pending</p>
                        </div>
                        <Link :href="route('staff.results.show', exam.id)" class="ml-4 shrink-0 rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-slate-700">
                            Grade
                        </Link>
                    </div>
                </div>
                <div v-else class="px-6 py-10 text-center">
                    <svg class="mx-auto h-10 w-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <p class="mt-3 text-sm text-slate-500">All caught up!</p>
                </div>
            </div>

            <!-- Recent attempts -->
            <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/60">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900">Recent Attempts</h2>
                        <p class="text-xs text-slate-500">Latest student submissions</p>
                    </div>
                </div>
                <div v-if="recentAttempts.length" class="divide-y divide-slate-100">
                    <div v-for="attempt in recentAttempts" :key="attempt.id" class="px-6 py-3.5 transition-colors hover:bg-slate-50/50">
                        <div class="flex items-center justify-between">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-slate-900">{{ attempt.student_name || 'Unknown' }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">{{ attempt.admission_number }}</p>
                            </div>
                            <div class="ml-4 flex items-center gap-3">
                                <span class="text-sm font-semibold text-slate-700">{{ attempt.score ?? '-' }}</span>
                                <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset capitalize', statusColor(attempt.status)]">
                                    {{ formatStatus(attempt.status) }}
                                </span>
                            </div>
                        </div>
                        <p class="mt-1 text-xs text-slate-400">{{ attempt.exam_title }}</p>
                    </div>
                </div>
                <div v-else class="px-6 py-10 text-center">
                    <svg class="mx-auto h-10 w-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    <p class="mt-3 text-sm text-slate-500">No recent activity</p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
