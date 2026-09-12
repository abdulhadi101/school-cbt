<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

type Exam = {
    id: number;
    title: string;
    subject: string | null;
    pending_count: number;
    graded_count: number;
    total_count: number;
};

defineProps<{ exams: Exam[] }>();
</script>

<template>
    <Head title="Grading Queue" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Grading Queue</h2>
                <p class="text-sm text-gray-500">Exams with submissions pending manual grading.</p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div v-if="exams.length === 0" class="rounded-xl bg-white p-10 text-center shadow-sm">
                    <p class="text-gray-500">No exams need grading right now.</p>
                </div>

                <div v-for="exam in exams" :key="exam.id" class="rounded-xl bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                        <div>
                            <div class="font-semibold text-gray-900">{{ exam.title }}</div>
                            <div class="text-sm text-gray-500">{{ exam.subject ?? 'No subject' }}</div>
                        </div>
                        <div class="flex items-center gap-4 text-sm">
                            <span class="rounded-full bg-amber-100 px-3 py-1 font-semibold text-amber-700">{{ exam.pending_count }} pending</span>
                            <span class="text-gray-500">{{ exam.graded_count }} graded</span>
                            <span class="text-gray-400">{{ exam.total_count }} total</span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between px-6 py-3">
                        <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full bg-emerald-500" :style="{ width: exam.total_count > 0 ? `${(exam.graded_count / exam.total_count) * 100}%` : '0%' }" />
                        </div>
                        <Link :href="route('staff.grading.exam', exam.id)" class="ml-4 shrink-0 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                            Grade
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
