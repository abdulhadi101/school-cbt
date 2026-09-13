<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

type Section = { id: number; name: string; class_level_id: number; classLevel?: { id: number; name: string } };

const props = defineProps<{
    preview: {
        total: number;
        rows: Record<string, string>[];
        headers: string[];
    } | null;
    sections: Section[];
}>();

const localPreview = ref(props.preview);

const page = usePage();
const importResult = ref<{ created: number; errors: { row: number; message: string }[] } | null>(
    (page.props as Record<string, unknown>).import_result as { created: number; errors: { row: number; message: string }[] } | null ?? null
);

const previewForm = useForm({ csv_file: null as File | null });
const importForm = useForm({ csv_file: null as File | null });
const previewFile = ref<File | null>(null);

const onPreviewFileChange = (e: Event) => {
    const input = e.target as HTMLInputElement;
    previewFile.value = input.files?.[0] ?? null;
};

const submitPreview = () => {
    if (! previewFile.value) return;
    previewForm.csv_file = previewFile.value;
    previewForm.post(route('staff.users.students.preview'));
};

const submitImport = () => {
    if (! previewFile.value) return;
    importForm.csv_file = previewFile.value;
    importForm.post(route('staff.users.students.import'));
};
</script>

<template>
    <Head title="Import Students" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Import Students</h2>
                    <p class="text-sm text-gray-500">Upload a CSV file to bulk-create student records.</p>
                </div>
                <Link :href="route('staff.users.students.index')" class="text-sm text-slate-600 hover:text-slate-900">Back to list</Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl sm:px-6 lg:px-8 space-y-6">
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-medium text-gray-900">Template & Instructions</h3>
                    <p class="mt-2 text-sm text-gray-600">Download the template to see the expected column format. The CSV must include a header row.</p>
                    <div class="mt-4 flex gap-3">
                        <a :href="route('staff.users.students.template')" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Download Template
                        </a>
                        <a :href="route('staff.users.students.export')" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Export Current Students
                        </a>
                    </div>
                    <div class="mt-4 rounded-lg bg-slate-50 p-4 text-xs text-slate-600">
                        <p class="font-semibold">Expected columns:</p>
                        <p class="mt-1"><code>admission_number</code>, <code>first_name</code>, <code>last_name</code>, <code>middle_name</code>, <code>email</code> (optional for login), <code>password</code> (optional, defaults to student12345), <code>section</code> (e.g. "JSS1-A")</p>
                        <p class="mt-2">If <code>email</code> is provided, a login account will be created automatically.</p>
                    </div>
                </div>

                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-medium text-gray-900">Upload CSV</h3>
                    <form @submit.prevent="submitPreview" class="mt-4 space-y-4">
                        <div>
                            <input type="file" accept=".csv,.txt" @change="onPreviewFileChange" class="block w-full text-sm text-gray-500 file:mr-4 file:rounded-lg file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-slate-800" />
                            <InputError class="mt-2" :message="previewForm.errors.csv_file" />
                        </div>
                        <div class="flex gap-3">
                            <PrimaryButton :disabled="!previewFile || previewForm.processing">
                                {{ previewForm.processing ? 'Processing...' : 'Preview Import' }}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>

                <div v-if="importResult" class="rounded-xl bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-medium text-gray-900">Import Result</h3>
                    <div class="mt-4 space-y-2">
                        <p class="text-sm text-green-700">{{ importResult.created }} students imported successfully.</p>
                        <div v-if="importResult.errors.length > 0" class="rounded-lg bg-red-50 p-4">
                            <p class="text-sm font-medium text-red-800">{{ importResult.errors.length }} row(s) had errors:</p>
                            <ul class="mt-2 space-y-1">
                                <li v-for="err in importResult.errors" :key="err.row" class="text-xs text-red-600">
                                    Row {{ err.row }}: {{ err.message }}
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="mt-4">
                        <Link :href="route('staff.users.students.index')" class="text-sm text-slate-600 hover:text-slate-900">Go to Students List</Link>
                    </div>
                </div>

                <div v-if="localPreview" class="rounded-xl bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium text-gray-900">Preview ({{ localPreview.total }} rows)</h3>
                        <div class="flex gap-3">
                            <SecondaryButton @click="localPreview = null">Cancel</SecondaryButton>
                            <form @submit.prevent="submitImport" class="inline">
                                <input type="hidden" :value="previewFile?.name" />
                                <DangerButton :disabled="importForm.processing">
                                    {{ importForm.processing ? 'Importing...' : 'Import All' }}
                                </DangerButton>
                            </form>
                        </div>
                    </div>

                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-xs">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium text-gray-500">#</th>
                                    <th v-for="header in localPreview.headers" :key="header" class="px-3 py-2 text-left font-medium text-gray-500">{{ header }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <tr v-for="(row, i) in localPreview.rows" :key="i" class="hover:bg-gray-50">
                                    <td class="whitespace-nowrap px-3 py-2 text-gray-400">{{ i + 1 }}</td>
                                    <td v-for="header in localPreview.headers" :key="header" class="whitespace-nowrap px-3 py-2 text-gray-700">
                                        {{ row[header] || '—' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-if="localPreview.total > 200" class="mt-2 text-xs text-gray-400">Showing first 200 of {{ localPreview.total }} rows.</p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
