<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

type LiveExam = {
    id: number;
    title: string;
    subject: string | null;
    subject_code: string | null;
    exam_type: string;
    duration_minutes: number;
    opens_at: string | null;
    closes_at: string | null;
    is_open: boolean;
    total_attempts: number;
    in_progress_count: number;
    submitted_count: number;
    expired_count: number;
};

defineProps<{ exams: LiveExam[] }>();
</script>

<template>
    <Head title="Live Exams" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Live Exams</h2>
                <p class="text-sm text-gray-500">Every exam currently going on across classes. Open one to watch and act.</p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div v-if="exams.length === 0" class="rounded-xl bg-white p-10 text-center text-sm text-gray-500 shadow-sm">
                    No exams going on right now.
                </div>
                <div v-else class="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <div v-for="exam in exams" :key="exam.id" class="flex flex-col gap-4 rounded-xl bg-white p-6 shadow-sm">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ exam.subject_code ?? exam.exam_type }} · {{ exam.subject ?? 'No subject' }}</div>
                                <h3 class="mt-1 text-lg font-semibold text-gray-900">{{ exam.title }}</h3>
                                <p class="mt-1 text-sm text-gray-500">{{ exam.duration_minutes }} minutes</p>
                            </div>
                            <span :class="exam.is_open ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-700'" class="rounded-full px-3 py-1 text-xs font-semibold">
                                {{ exam.is_open ? 'Open' : 'Closed window' }}
                            </span>
                        </div>
                        <div class="flex gap-6 text-sm">
                            <div><span class="text-2xl font-bold text-gray-900">{{ exam.in_progress_count }}</span> <span class="text-gray-500">live</span></div>
                            <div><span class="text-2xl font-bold text-gray-900">{{ exam.submitted_count }}</span> <span class="text-gray-500">submitted</span></div>
                            <div><span class="text-2xl font-bold text-gray-900">{{ exam.total_attempts }}</span> <span class="text-gray-500">total</span></div>
                        </div>
                        <Link :href="route('staff.exams.invigilation', exam.id)" class="rounded-lg bg-slate-900 px-4 py-2 text-center text-sm font-semibold text-white">Watch live</Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
