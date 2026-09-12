<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

type Attempt = {
    id: number;
    student_name: string;
    admission_number: string | null;
    status: string;
    score: string | number | null;
    max_score: string | number | null;
    percentage: string | number | null;
    attempt_number: number;
    submitted_at: string | null;
    graded_at: string | null;
    released_at: string | null;
};

type Exam = {
    id: number;
    title: string;
    subject: string | null;
    status: string;
    score_release_policy: string;
};

const props = defineProps<{
    exam: Exam;
    attempts: Attempt[];
    summary: {
        total: number;
        submitted: number;
        grading: number;
        graded: number;
        released: number;
        average_score: number | null;
    };
}>();

const releasing = ref<number | null>(null);
const releasingAll = ref(false);
const filter = ref<string>('all');

const filteredAttempts = ref<Attempt[]>(props.attempts);

function setFilter(status: string) {
    filter.value = status;
    filteredAttempts.value = status === 'all' ? props.attempts : props.attempts.filter((a) => a.status === status);
}

async function releaseAttempt(attemptId: number) {
    releasing.value = attemptId;
    try {
        const response = await fetch(route('staff.results.release', props.exam.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({ attempt_id: attemptId }),
        });
        const data = await response.json();
        if (!response.ok) {
            alert(data.message ?? 'Failed to release result.');
            return;
        }
        const attempt = props.attempts.find((a) => a.id === attemptId);
        if (attempt) {
            attempt.status = data.status;
            attempt.released_at = data.released_at;
        }
    } finally {
        releasing.value = null;
    }
}

async function releaseAllGraded() {
    if (!confirm('Release all graded results for this exam?')) return;
    releasingAll.value = true;
    try {
        const response = await fetch(route('staff.results.release-all', props.exam.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
            },
        });
        const data = await response.json();
        if (!response.ok) {
            alert(data.message ?? 'Failed to release results.');
            return;
        }
        router.reload();
    } finally {
        releasingAll.value = false;
    }
}

function formatStatus(status: string): string {
    return status.replaceAll('_', ' ');
}

function formatDate(date: string | null): string {
    if (!date) return '-';
    return new Date(date).toLocaleString();
}
</script>

<template>
    <Head :title="`Results - ${exam.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Results</h2>
                    <p class="text-sm text-gray-500">{{ exam.title }} <span v-if="exam.subject" class="text-gray-400">/ {{ exam.subject }}</span></p>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('staff.exams.index')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Back to Exams</Link>
                    <button v-if="summary.graded > 0" :disabled="releasingAll" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50" @click="releaseAllGraded">
                        {{ releasingAll ? 'Releasing...' : `Release All Graded (${summary.graded})` }}
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div class="grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
                    <div v-for="(value, key) in summary" :key="key" class="rounded-xl bg-white p-5 shadow-sm">
                        <p class="text-sm capitalize text-gray-500">{{ String(key).replaceAll('_', ' ') }}</p>
                        <p class="mt-2 text-3xl font-bold text-gray-900">{{ value ?? '-' }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button v-for="status in ['all', 'submitted', 'grading', 'graded', 'released']" :key="status"
                        class="rounded-full px-4 py-1.5 text-sm font-medium transition"
                        :class="filter === status ? 'bg-slate-900 text-white' : 'bg-white text-gray-600 hover:bg-gray-100 shadow-sm'"
                        @click="setFilter(status)">
                        {{ formatStatus(status) }}
                    </button>
                </div>

                <div class="overflow-hidden rounded-xl bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Student</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Score</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Submitted</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Graded</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Released</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="attempt in filteredAttempts" :key="attempt.id">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900">{{ attempt.student_name || 'Unknown' }}</div>
                                    <div class="text-xs text-gray-500">{{ attempt.admission_number }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-3 py-1 text-xs capitalize"
                                        :class="{
                                            'bg-amber-100 text-amber-700': attempt.status === 'submitted' || attempt.status === 'grading',
                                            'bg-blue-100 text-blue-700': attempt.status === 'graded',
                                            'bg-emerald-100 text-emerald-700': attempt.status === 'released',
                                            'bg-gray-100 text-gray-700': !['submitted', 'grading', 'graded', 'released'].includes(attempt.status),
                                        }">
                                        {{ formatStatus(attempt.status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    <span v-if="attempt.score !== null">{{ attempt.score }} / {{ attempt.max_score }}</span>
                                    <span v-else>-</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ formatDate(attempt.submitted_at) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ formatDate(attempt.graded_at) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ formatDate(attempt.released_at) }}</td>
                                <td class="px-4 py-3 text-right text-sm">
                                    <button v-if="attempt.status === 'graded'" :disabled="releasing === attempt.id"
                                        class="font-semibold text-emerald-700 hover:text-emerald-900 disabled:opacity-50"
                                        @click="releaseAttempt(attempt.id)">
                                        {{ releasing === attempt.id ? 'Releasing...' : 'Release' }}
                                    </button>
                                    <span v-else-if="attempt.status === 'released'" class="text-sm text-emerald-600">Released</span>
                                    <span v-else class="text-sm text-gray-400">-</span>
                                </td>
                            </tr>
                            <tr v-if="filteredAttempts.length === 0">
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500">No attempts match this filter.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
