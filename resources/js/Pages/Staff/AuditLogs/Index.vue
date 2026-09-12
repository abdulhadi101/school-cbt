<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

type Log = {
    id: number;
    action: string;
    auditable_type: string;
    auditable_id: number | null;
    user_name: string;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    metadata: Record<string, unknown> | null;
    ip_address: string | null;
    occurred_at: string | null;
};

type Paginated = {
    data: Log[];
    links: { url: string | null; label: string; active: boolean }[];
};

const props = defineProps<{
    logs: Paginated;
    actions: string[];
    filters: { action?: string; search?: string };
}>();

const search = ref(props.filters.search ?? '');
const selectedAction = ref(props.filters.action ?? '');

function applyFilters() {
    router.get(route('staff.audit-logs.index'), {
        search: search.value || undefined,
        action: selectedAction.value || undefined,
    }, { preserveState: true, replace: true });
}

function formatDate(date: string | null): string {
    if (!date) return '-';
    return new Date(date).toLocaleString();
}

function formatValues(values: Record<string, unknown> | null): string {
    if (!values) return '-';
    return Object.entries(values).map(([k, v]) => `${k}: ${JSON.stringify(v)}`).join(', ');
}
</script>

<template>
    <Head title="Audit Log" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Audit Log</h2>
                <p class="text-sm text-gray-500">Track all system activity and changes.</p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div class="flex flex-wrap gap-3 rounded-xl bg-white p-4 shadow-sm">
                    <input v-model="search" type="text" placeholder="Search actions or users..." class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500" @keyup.enter="applyFilters" />
                    <select v-model="selectedAction" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        <option value="">All actions</option>
                        <option v-for="action in actions" :key="action" :value="action">{{ action }}</option>
                    </select>
                    <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800" @click="applyFilters">Filter</button>
                </div>

                <div class="overflow-hidden rounded-xl bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Time</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Action</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">User</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Target</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Changes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="log in logs.data" :key="log.id">
                                <td class="whitespace-nowrap px-4 py-3 text-xs text-gray-500">{{ formatDate(log.occurred_at) }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">{{ log.action }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ log.user_name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    <span v-if="log.auditable_type">{{ log.auditable_type }} #{{ log.auditable_id }}</span>
                                    <span v-else class="text-gray-400">-</span>
                                </td>
                                <td class="max-w-xs truncate px-4 py-3 text-xs text-gray-500">{{ formatValues(log.new_values) }}</td>
                            </tr>
                            <tr v-if="logs.data.length === 0">
                                <td colspan="5" class="px-4 py-10 text-center text-sm text-gray-500">No audit logs found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="logs.links.length > 3" class="flex justify-center gap-1">
                    <template v-for="link in logs.links" :key="link.label">
                        <span v-if="!link.url" class="rounded-lg px-3 py-2 text-sm text-gray-400">{{ link.label }}</span>
                        <Link v-else :href="link.url" class="rounded-lg px-3 py-2 text-sm" :class="link.active ? 'bg-slate-900 text-white' : 'bg-white text-gray-700 hover:bg-gray-50 shadow-sm'" v-html="link.label" />
                    </template>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
