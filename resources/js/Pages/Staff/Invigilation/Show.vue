<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

type Attempt = {
    id: number;
    student_name: string;
    admission_number: string | null;
    status: string;
    remaining_seconds: number | null;
    last_seen_at: string | null;
    submitted_at: string | null;
};
type Dashboard = {
    exam: { id: number; title: string; status: string };
    summary: Record<string, number>;
    attempts: Attempt[];
};

const props = defineProps<{ dashboard: Dashboard }>();
const dashboard = ref(props.dashboard);
const refreshing = ref(false);

async function refresh() {
    refreshing.value = true;
    const response = await fetch(route('staff.exams.invigilation.status', dashboard.value.exam.id), {
        headers: { Accept: 'application/json' },
    });
    if (response.ok) dashboard.value = await response.json();
    refreshing.value = false;
}
</script>

<template>
    <Head :title="`Invigilation - ${dashboard.exam.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Invigilation</h2>
                    <p class="text-sm text-gray-500">{{ dashboard.exam.title }}</p>
                </div>
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white" :disabled="refreshing" @click="refresh">
                    {{ refreshing ? 'Refreshing...' : 'Refresh' }}
                </button>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div class="grid gap-4 md:grid-cols-4">
                    <div v-for="(value, key) in dashboard.summary" :key="key" class="rounded-xl bg-white p-5 shadow-sm">
                        <p class="text-sm capitalize text-gray-500">{{ String(key).replaceAll('_', ' ') }}</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900">{{ value }}</p>
                    </div>
                </div>

                <div class="overflow-hidden rounded-xl bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Student</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Remaining</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Last Seen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="attempt in dashboard.attempts" :key="attempt.id">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900">{{ attempt.student_name || 'Unknown student' }}</div>
                                    <div class="text-sm text-gray-500">{{ attempt.admission_number }}</div>
                                </td>
                                <td class="px-4 py-3"><span class="rounded-full bg-gray-100 px-3 py-1 text-sm">{{ attempt.status }}</span></td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ attempt.remaining_seconds ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ attempt.last_seen_at ?? '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
