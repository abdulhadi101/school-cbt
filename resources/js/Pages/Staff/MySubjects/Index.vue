<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

type Item = {
    subject: { id: number; name: string; code: string | null };
    class_level: { id: number; name: string };
    category_id: number | null;
    category_name: string | null;
    questions_count: number;
};

defineProps<{
    items: Item[];
    is_privileged: boolean;
}>();
</script>

<template>
    <Head title="My Subjects" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">My Subjects</h2>
                <p class="text-sm text-gray-500">Subjects assigned to you per class. Open one to manage or import questions.</p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div v-if="items.length === 0" class="rounded-xl bg-white p-10 text-center text-sm text-gray-500 shadow-sm">
                    No teaching assignments yet. Contact your admin to assign subjects per class.
                </div>
                <div v-else class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                    <div v-for="item in items" :key="`${item.subject.id}-${item.class_level.id}`" class="flex flex-col gap-4 rounded-xl bg-white p-6 shadow-sm">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ item.subject.code ?? 'Subject' }} · {{ item.class_level.name }}</div>
                            <h3 class="mt-1 text-lg font-semibold text-gray-900">{{ item.subject.name }}</h3>
                            <p class="mt-1 text-sm text-gray-500">{{ item.category_name ?? 'No category yet' }} · {{ item.questions_count }} questions</p>
                        </div>
                        <div class="flex gap-2">
                            <Link
                                :href="route('staff.subject-questions.index', [item.subject.id, item.class_level.id])"
                                class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white"
                            >
                                Manage
                            </Link>
                            <Link
                                :href="route('staff.subject-questions.import', [item.subject.id, item.class_level.id])"
                                class="rounded-lg border px-4 py-2 text-sm font-semibold text-slate-700"
                            >
                                Import
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
