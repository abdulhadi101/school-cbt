<script setup lang="ts">
import MathContent from '@/Components/MathContent.vue';
import MathToolbar from '@/Components/MathToolbar.vue';
import { insertAtCursor } from '@/support/math';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

type Option = { option_text: string; fraction: number; feedback: string };
type Lookup = { id: number; name: string };
type QuestionForm = {
    title: string;
    subject_id: number | null;
    class_level_id: number | null;
    category_id: number | null;
    tags: string;
    type: string;
    difficulty: string | null;
    question_text: string;
    default_marks: string;
    negative_marks: string;
    grading_rules: string;
    general_feedback: string;
    explanation: string;
    options: Option[];
};
type Entry = {
    id: number;
    status: string;
    title: string;
    subject_id: number | null;
    class_level_id: number | null;
    category_id: number | null;
    tags: string;
    version: {
        type: string;
        difficulty: string | null;
        question_text: string;
        default_marks: string;
        negative_marks: string;
        grading_rules: Record<string, unknown> | null;
        general_feedback: string | null;
        explanation: string | null;
        options: Option[];
    } | null;
};

const props = defineProps<{
    entry: Entry | null;
    lookups: {
        subjects: Lookup[];
        class_levels: Lookup[];
        categories: (Lookup & { subject_id: number | null; class_level_id: number | null })[];
        types: { value: string; label: string }[];
        difficulties: string[];
    };
}>();

const form = useForm<QuestionForm>({
    title: props.entry?.title ?? '',
    subject_id: props.entry?.subject_id ?? null,
    class_level_id: props.entry?.class_level_id ?? null,
    category_id: props.entry?.category_id ?? null,
    tags: props.entry?.tags ?? '',
    type: props.entry?.version?.type ?? 'single_choice',
    difficulty: props.entry?.version?.difficulty ?? 'medium',
    question_text: props.entry?.version?.question_text ?? '',
    default_marks: props.entry?.version?.default_marks ?? '1',
    negative_marks: props.entry?.version?.negative_marks ?? '0',
    grading_rules: JSON.stringify(props.entry?.version?.grading_rules ?? {}),
    general_feedback: props.entry?.version?.general_feedback ?? '',
    explanation: props.entry?.version?.explanation ?? '',
    options: props.entry?.version?.options?.length ? props.entry.version.options : [
        { option_text: '', fraction: 1, feedback: '' },
        { option_text: '', fraction: 0, feedback: '' },
    ],
});

function addOption() {
    form.options.push({ option_text: '', fraction: 0, feedback: '' });
}

function removeOption(index: number) {
    form.options.splice(index, 1);
}

function submit() {
    if (props.entry) {
        form.put(route('staff.questions.update', props.entry.id));
        return;
    }

    form.post(route('staff.questions.store'));
}

function markReady() {
    if (props.entry) router.post(route('staff.questions.ready', props.entry.id));
}

const activeField = ref<HTMLTextAreaElement | HTMLInputElement | null>(null);

function trackField(event: FocusEvent) {
    const target = event.target as HTMLElement | null;
    if (target instanceof HTMLTextAreaElement || target instanceof HTMLInputElement) {
        activeField.value = target;
    }
}

function insertSnippet(snippet: string) {
    const fallback = document.getElementById('question-text-input');
    const field = activeField.value ?? (fallback instanceof HTMLTextAreaElement ? fallback : null);
    if (field) insertAtCursor(field, snippet);
}

function retire() {
    if (props.entry) router.post(route('staff.questions.retire', props.entry.id));
}
</script>

<template>
    <Head :title="entry ? 'Edit Question' : 'New Question'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ entry ? 'Edit Question' : 'New Question' }}</h2>
                    <p class="text-sm text-gray-500">Keep answer keys here. Student attempt pages receive safe snapshots only.</p>
                </div>
                <Link :href="route('staff.questions.index')" class="text-sm font-semibold text-gray-600 hover:text-gray-900">Back to bank</Link>
            </div>
        </template>

        <div class="py-8">
            <form class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8" @submit.prevent="submit" @focusin="trackField">
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <div class="grid gap-4 md:grid-cols-3">
                        <label class="md:col-span-3">
                            <span class="text-sm font-medium text-gray-700">Title</span>
                            <input v-model="form.title" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                            <span class="text-sm text-red-600">{{ form.errors.title }}</span>
                        </label>

                        <label>
                            <span class="text-sm font-medium text-gray-700">Subject</span>
                            <select v-model="form.subject_id" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                <option :value="null">None</option>
                                <option v-for="subject in lookups.subjects" :key="subject.id" :value="subject.id">{{ subject.name }}</option>
                            </select>
                        </label>

                        <label>
                            <span class="text-sm font-medium text-gray-700">Class</span>
                            <select v-model="form.class_level_id" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                <option :value="null">None</option>
                                <option v-for="level in lookups.class_levels" :key="level.id" :value="level.id">{{ level.name }}</option>
                            </select>
                        </label>

                        <label>
                            <span class="text-sm font-medium text-gray-700">Category</span>
                            <select v-model="form.category_id" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                <option :value="null">None</option>
                                <option v-for="category in lookups.categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                            </select>
                        </label>

                        <label>
                            <span class="text-sm font-medium text-gray-700">Type</span>
                            <select v-model="form.type" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                <option v-for="type in lookups.types" :key="type.value" :value="type.value">{{ type.label }}</option>
                            </select>
                        </label>

                        <label>
                            <span class="text-sm font-medium text-gray-700">Difficulty</span>
                            <select v-model="form.difficulty" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                <option :value="null">None</option>
                                <option v-for="difficulty in lookups.difficulties" :key="difficulty" :value="difficulty">{{ difficulty }}</option>
                            </select>
                        </label>

                        <label>
                            <span class="text-sm font-medium text-gray-700">Marks</span>
                            <input v-model="form.default_marks" type="number" min="0.01" step="0.01" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                        </label>

                        <label class="md:col-span-3">
                            <span class="text-sm font-medium text-gray-700">Tags</span>
                            <input v-model="form.tags" placeholder="algebra, first term" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                        </label>

                        <label class="md:col-span-3">
                            <span class="text-sm font-medium text-gray-700">Question Text</span>
                            <MathToolbar class="mt-2" @insert="insertSnippet" />
                            <textarea id="question-text-input" v-model="form.question_text" rows="5" class="mt-2 w-full rounded-md border-gray-300 shadow-sm" />
                            <p class="mt-1 text-xs text-gray-500">Math: <span class="font-mono">\(x^2\)</span> inline, <span class="font-mono">\[…\]</span> display. Click any field, then a symbol to insert.</p>
                            <div class="mt-2 rounded-lg border border-gray-200 bg-gray-50 p-3">
                                <p class="text-xs font-semibold uppercase text-gray-400">Preview</p>
                                <MathContent :content="form.question_text" class="mt-1 block text-sm text-gray-900" />
                            </div>
                            <span class="text-sm text-red-600">{{ form.errors.question_text }}</span>
                        </label>
                    </div>
                </div>

                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <h3 class="font-semibold text-gray-900">Options and Fractions</h3>
                            <p class="text-sm text-gray-500">Set fraction to 1 for correct options, 0 for incorrect options, or partial values for multiple-response questions.</p>
                        </div>
                        <button type="button" class="rounded-md border px-3 py-2 text-sm font-semibold" @click="addOption">Add Option</button>
                    </div>

                    <div class="space-y-3">
                        <div v-for="(option, index) in form.options" :key="index" class="grid gap-3 rounded-lg border p-3 md:grid-cols-[1fr_120px_auto]">
                            <input v-model="option.option_text" placeholder="Option text" class="rounded-md border-gray-300 shadow-sm" />
                            <input v-model="option.fraction" type="number" min="0" max="1" step="0.0001" class="rounded-md border-gray-300 shadow-sm" />
                            <button type="button" class="rounded-md border px-3 py-2 text-sm" @click="removeOption(index)">Remove</button>
                        </div>
                    </div>
                    <span class="text-sm text-red-600">{{ form.errors.options }}</span>
                </div>

                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <div class="grid gap-4 md:grid-cols-2">
                        <label>
                            <span class="text-sm font-medium text-gray-700">Explanation</span>
                            <textarea v-model="form.explanation" rows="4" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                        </label>
                        <label>
                            <span class="text-sm font-medium text-gray-700">General Feedback</span>
                            <textarea v-model="form.general_feedback" rows="4" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                        </label>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex gap-2" v-if="entry">
                        <button type="button" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-800" @click="markReady">Mark Ready</button>
                        <button type="button" class="rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-800" @click="retire">Retire</button>
                    </div>
                    <button type="submit" :disabled="form.processing" class="rounded-lg bg-slate-900 px-5 py-2 text-sm font-semibold text-white">
                        {{ form.processing ? 'Saving...' : 'Save Question' }}
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
