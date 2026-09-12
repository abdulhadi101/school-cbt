<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

type Exam = {
    id: number;
    title: string;
    status: string;
    exam_type: string;
    subject: string | null;
    term: string | null;
    duration_minutes: number;
    total_marks: string;
    draft_slots_count: number;
};

defineProps<{
    exams: { data: Exam[]; links: { url: string | null; label: string; active: boolean }[] };
    filters: { status: string };
    actions: { can_create: boolean; can_invigilate?: boolean };
}>();
</script>

<template>
    <Head title="Exam Drafts" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Exam Drafts</h2>
                    <p class="text-sm text-gray-500">Assemble ready question versions into reviewable exams.</p>
                </div>
                <Link v-if="actions.can_create" :href="route('staff.exams.create')" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">New Exam</Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-xl bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Exam</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Catalog</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Settings</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="exam in exams.data" :key="exam.id">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900">{{ exam.title }}</div>
                                    <div class="mt-2">
                                        <span class="rounded-full bg-gray-100 px-3 py-1 text-xs capitalize text-gray-700">{{ exam.status }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    <div>{{ exam.subject ?? 'No subject' }}</div>
                                    <div class="text-gray-500">{{ exam.term ?? 'No term' }} · {{ exam.exam_type }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    <div>{{ exam.duration_minutes }} minutes</div>
                                    <div class="text-gray-500">{{ exam.total_marks }} marks · {{ exam.draft_slots_count }} slots</div>
                                </td>
                                <td class="px-4 py-3 text-right text-sm">
                                    <Link :href="route('staff.exams.edit', exam.id)" class="font-semibold text-slate-700 hover:text-slate-950">Open</Link>
                                    <Link :href="route('staff.exams.schedule.edit', exam.id)" class="ml-4 font-semibold text-slate-700 hover:text-slate-950">Schedule</Link>
                                    <Link :href="route('staff.results.show', exam.id)" class="ml-4 font-semibold text-slate-700 hover:text-slate-950">Results</Link>
                                    <Link v-if="actions.can_invigilate" :href="route('staff.exams.invigilation', exam.id)" class="ml-4 font-semibold text-slate-700 hover:text-slate-950">Invigilate</Link>
                                </td>
                            </tr>
                            <tr v-if="exams.data.length === 0">
                                <td colspan="4" class="px-4 py-10 text-center text-sm text-gray-500">No exam drafts yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
