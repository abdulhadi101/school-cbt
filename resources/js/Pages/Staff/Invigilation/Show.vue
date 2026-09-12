<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage, usePoll } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Attempt = {
    id: number;
    student_name: string;
    admission_number: string | null;
    status: string;
    attempt_number: number;
    remaining_seconds: number | null;
    seconds_since_seen: number | null;
    is_idle: boolean;
    last_seen_at: string | null;
    submitted_at: string | null;
    can_extend: boolean;
    can_reopen: boolean;
    can_invalidate: boolean;
};
type Dashboard = {
    exam: { id: number; title: string; status: string };
    summary: Record<string, number>;
    attempts: Attempt[];
};

const props = defineProps<{ dashboard: Dashboard }>();

usePoll(15000, { only: ['dashboard'] });

const page = usePage();
const permissions = computed(() => (page.props.auth as Record<string, unknown>).permissions as string[] ?? []);
const canReopen = computed(() => permissions.value.includes('attempts.reopen'));
const canInvalidate = computed(() => permissions.value.includes('attempts.invalidate'));
const flashStatus = computed(() => (page.props.flash as Record<string, unknown> | undefined)?.status as string | undefined);

const modal = ref<{ attempt: Attempt; action: 'extend' | 'reopen' | 'invalidate' } | null>(null);
const extraMinutes = ref(15);
const reason = ref('');
const saving = ref(false);

function openModal(attempt: Attempt, action: 'extend' | 'reopen' | 'invalidate') {
    modal.value = { attempt, action };
    extraMinutes.value = 15;
    reason.value = '';
}

function closeModal() {
    modal.value = null;
    reason.value = '';
    saving.value = false;
}

function confirmAction() {
    if (!modal.value || !reason.value.trim()) return;
    saving.value = true;
    const { attempt, action } = modal.value;
    const data = action === 'extend' ? { extra_minutes: extraMinutes.value, reason: reason.value } : { reason: reason.value };
    router.post(route(`staff.attempts.${action}`, attempt.id), data, {
        preserveScroll: true,
        onFinish: () => closeModal(),
    });
}

function refresh() {
    router.reload({ only: ['dashboard'] });
}

function formatRemaining(seconds: number | null): string {
    if (seconds === null) return '-';
    const minutes = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${minutes}:${String(secs).padStart(2, '0')}`;
}

function formatSeen(seconds: number | null): string {
    if (seconds === null) return '-';
    if (seconds < 60) return `${seconds}s ago`;
    return `${Math.floor(seconds / 60)}m ago`;
}
</script>

<template>
    <Head :title="`Invigilation - ${props.dashboard.exam.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Invigilation</h2>
                    <p class="text-sm text-gray-500">{{ props.dashboard.exam.title }} · auto-refreshes every 15s</p>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('staff.invigilation.index')" class="rounded-lg border px-4 py-2 text-sm font-semibold text-slate-700">All live</Link>
                    <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white" @click="refresh">Refresh</button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div v-if="flashStatus" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800">{{ flashStatus }}</div>

                <div class="grid gap-4 md:grid-cols-4">
                    <div v-for="(value, key) in props.dashboard.summary" :key="key" class="rounded-xl bg-white p-5 shadow-sm">
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
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="attempt in props.dashboard.attempts" :key="attempt.id">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900">{{ attempt.student_name || 'Unknown student' }}</div>
                                    <div class="text-sm text-gray-500">{{ attempt.admission_number }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full bg-gray-100 px-3 py-1 text-sm">{{ attempt.status }}</span>
                                    <span v-if="attempt.is_idle" class="ml-2 rounded-full bg-amber-100 px-3 py-1 text-sm font-semibold text-amber-800">Idle</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    {{ formatRemaining(attempt.remaining_seconds) }}
                                    <span v-if="attempt.status === 'in_progress' && attempt.remaining_seconds !== null && attempt.remaining_seconds < 300" class="ml-2 font-semibold text-red-600">Ending soon</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ formatSeen(attempt.seconds_since_seen) }}</td>
                                <td class="px-4 py-3 text-right text-sm">
                                    <div class="flex justify-end gap-2">
                                        <button v-if="attempt.can_extend && canReopen" class="rounded-md border px-3 py-1 font-semibold text-slate-700" @click="openModal(attempt, 'extend')">Extend</button>
                                        <button v-if="attempt.can_reopen && canReopen" class="rounded-md border px-3 py-1 font-semibold text-slate-700" @click="openModal(attempt, 'reopen')">Reopen</button>
                                        <button v-if="attempt.can_invalidate && canInvalidate" class="rounded-md border border-red-200 bg-red-50 px-3 py-1 font-semibold text-red-700" @click="openModal(attempt, 'invalidate')">Invalidate</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="props.dashboard.attempts.length === 0">
                                <td colspan="5" class="px-4 py-10 text-center text-sm text-gray-500">No attempts yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div v-if="modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="closeModal">
            <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                <h3 class="text-lg font-semibold capitalize text-gray-900">{{ modal.action }} — {{ modal.attempt.student_name }}</h3>
                <label v-if="modal.action === 'extend'" class="mt-4 block">
                    <span class="text-sm font-medium text-gray-700">Extra minutes</span>
                    <input v-model.number="extraMinutes" type="number" min="1" max="180" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                </label>
                <label class="mt-4 block">
                    <span class="text-sm font-medium text-gray-700">Reason (required, audited)</span>
                    <textarea v-model="reason" rows="3" placeholder="e.g. network outage in JSS 1A hall" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                </label>
                <div class="mt-4 flex justify-end gap-2">
                    <button class="rounded-lg border px-4 py-2 text-sm font-semibold" @click="closeModal">Cancel</button>
                    <button :disabled="saving || !reason.trim()" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" @click="confirmAction">
                        {{ saving ? 'Saving...' : 'Confirm' }}
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
