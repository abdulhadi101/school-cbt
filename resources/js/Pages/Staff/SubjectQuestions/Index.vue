<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

type Entry = {
    id: number;
    title: string | null;
    status: string;
    category: string | null;
    tags: string[];
    latest_version: {
        version_number: number;
        type: string;
        difficulty: string | null;
        default_marks: string;
        question_text: string;
    } | null;
};

defineProps<{
    context: {
        subject: { id: number; name: string; code: string | null };
        class_level: { id: number; name: string };
        category: { id: number; name: string } | null;
    };
    entries: { data: Entry[]; links: { url: string | null; label: string; active: boolean }[] };
}>();
</script>

<template>
    <Head :title="`${context.subject.name} ${context.class_level.name} Questions`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                        {{ context.subject.code ?? 'Subject' }} · {{ context.class_level.name }} · {{ context.category?.name ?? 'No category' }}
                    </div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ context.subject.name }} {{ context.class_level.name }}</h2>
                    <p class="text-sm text-gray-500">Questions in this subject and class context.</p>
                </div>
                <div class="flex gap-2">
                    <Link :href="route('staff.my-subjects.index')" class="rounded-lg border px-4 py-2 text-sm font-semibold text-slate-700">My Subjects</Link>
                    <Link :href="route('staff.subject-questions.import', [context.subject.id, context.class_level.id])" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Import</Link>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div class="overflow-hidden rounded-xl bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Question</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="entry in entries.data" :key="entry.id">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900">{{ entry.title }}</div>
                                    <div class="mt-1 text-sm text-gray-500">{{ entry.latest_version?.question_text }}</div>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <span v-for="tag in entry.tags" :key="tag" class="rounded-full bg-gray-100 px-2 py-1 text-xs text-gray-600">{{ tag }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="rounded-full bg-gray-100 px-3 py-1 capitalize text-gray-700">{{ entry.status }}</span>
                                    <div v-if="entry.latest_version" class="mt-2 text-xs text-gray-500">
                                        v{{ entry.latest_version.version_number }} · {{ entry.latest_version.type }} · {{ entry.latest_version.default_marks }} marks
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right text-sm">
                                    <Link :href="route('staff.questions.edit', entry.id)" class="font-semibold text-slate-700 hover:text-slate-950">Edit</Link>
                                </td>
                            </tr>
                            <tr v-if="entries.data.length === 0">
                                <td colspan="3" class="px-4 py-10 text-center text-sm text-gray-500">No questions yet in this context.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
