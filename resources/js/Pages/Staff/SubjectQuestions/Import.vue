<script setup lang="ts">
import MathContent from '@/Components/MathContent.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

type PreviewQuestion = {
    title: string | null;
    question_text: string;
    type: string;
    options: { option_text: string; fraction: number; feedback: string | null }[];
    general_feedback: string | null;
};

const props = defineProps<{
    context: {
        subject: { id: number; name: string; code: string | null };
        class_level: { id: number; name: string };
        category: { id: number; name: string } | null;
    };
    preview: {
        format: string;
        questions: PreviewQuestion[];
        errors: { block: number; message: string }[];
        truncated: boolean;
    } | null;
    defaults: { default_marks: string; difficulty: string | null; tags: string; format: string };
}>();

const form = useForm({
    format: props.defaults.format ?? 'aiken',
    default_marks: props.defaults.default_marks ?? '1',
    difficulty: props.defaults.difficulty ?? 'medium',
    tags: props.defaults.tags ?? '',
    content: '',
    file: null as File | null,
});

function submitPreview() {
    form.post(route('staff.subject-questions.preview', [props.context.subject.id, props.context.class_level.id]), {
        preserveScroll: true,
        forceFormData: true,
    });
}

function importQuestions() {
    form.post(route('staff.subject-questions.store', [props.context.subject.id, props.context.class_level.id]), {
        preserveScroll: true,
        forceFormData: true,
    });
}

function setFile(event: Event) {
    const input = event.target as HTMLInputElement;
    form.file = input.files?.[0] ?? null;
}
</script>

<template>
    <Head :title="`Import ${context.subject.name} ${context.class_level.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                        {{ context.subject.code ?? 'Subject' }} · {{ context.class_level.name }} · {{ context.category?.name ?? 'Auto category' }}
                    </div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Import into {{ context.subject.name }} {{ context.class_level.name }}</h2>
                    <p class="text-sm text-gray-500">Subject and class are locked by this page. Paste text or upload a file, preview, then import.</p>
                </div>
                <Link :href="route('staff.subject-questions.index', [context.subject.id, context.class_level.id])" class="text-sm font-semibold text-gray-600 hover:text-gray-900">Back to questions</Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <div class="grid gap-4 md:grid-cols-4">
                        <label>
                            <span class="text-sm font-medium text-gray-700">Format</span>
                            <select v-model="form.format" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                <option value="aiken">Aiken</option>
                                <option value="gift">GIFT</option>
                                <option value="xml">Moodle XML</option>
                            </select>
                        </label>
                        <label>
                            <span class="text-sm font-medium text-gray-700">Marks each</span>
                            <input v-model="form.default_marks" type="number" min="0.01" step="0.01" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                        </label>
                        <label>
                            <span class="text-sm font-medium text-gray-700">Difficulty</span>
                            <select v-model="form.difficulty" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                <option value="easy">easy</option>
                                <option value="medium">medium</option>
                                <option value="hard">hard</option>
                            </select>
                        </label>
                        <label>
                            <span class="text-sm font-medium text-gray-700">Tags</span>
                            <input v-model="form.tags" placeholder="first term, week 1" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                        </label>
                    </div>

                    <div class="mt-4 grid gap-4">
                        <label>
                            <span class="text-sm font-medium text-gray-700">Paste questions</span>
                            <textarea v-model="form.content" rows="12" placeholder="Q1. What is ...?&#10;A. ...&#10;B. ...&#10;ANSWER: B" class="mt-1 w-full rounded-md border-gray-300 font-mono text-sm shadow-sm" />
                            <span class="text-sm text-red-600">{{ form.errors.content }}</span>
                        </label>
                        <label>
                            <span class="text-sm font-medium text-gray-700">Or upload .txt / .gift / .xml</span>
                            <input type="file" accept=".txt,.gift,.xml,text/plain" class="mt-1 block w-full text-sm" @input="setFile" />
                            <span class="text-sm text-red-600">{{ form.errors.file }}</span>
                        </label>
                    </div>

                    <div class="mt-4 rounded-lg bg-gray-50 p-4 text-xs text-gray-600">
                        <p class="font-semibold">Math supported: <span class="font-mono">\(x^2\)</span> inline, <span class="font-mono">\[…\]</span> display (KaTeX, Moodle-compatible).</p>
                        <p class="mt-2 font-semibold">Aiken example</p>
                        <pre class="mt-1 whitespace-pre-wrap">Q1. What is a pawpaw?
A. a place
B. a town
C. a fruit
D. All of the above
ANSWER: C</pre>
                    </div>

                    <div class="mt-4 flex gap-2">
                        <button type="button" :disabled="form.processing" class="rounded-lg border px-5 py-2 text-sm font-semibold" @click="submitPreview">
                            {{ form.processing ? 'Working...' : 'Preview' }}
                        </button>
                        <button type="button" :disabled="form.processing || !preview?.questions?.length" class="rounded-lg bg-slate-900 px-5 py-2 text-sm font-semibold text-white" @click="importQuestions">
                            Import {{ preview?.questions?.length ?? '' }}
                        </button>
                    </div>
                </div>

                <div v-if="preview" class="rounded-xl bg-white p-6 shadow-sm">
                    <h3 class="font-semibold text-gray-900">Preview: {{ preview.questions.length }} valid, {{ preview.errors.length }} errors</h3>
                    <p v-if="preview.truncated" class="mt-1 text-sm text-amber-700">Truncated to first 200 questions.</p>
                    <div v-if="preview.errors.length" class="mt-4 space-y-2">
                        <div v-for="(error, index) in preview.errors" :key="index" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                            Block {{ error.block }}: {{ error.message }}
                        </div>
                    </div>
                    <div class="mt-4 space-y-4">
                        <div v-for="(question, index) in preview.questions" :key="index" class="rounded-lg border p-4">
                            <div class="text-xs font-semibold uppercase text-gray-500">{{ question.type }}</div>
                            <MathContent :content="question.question_text" class="mt-1 block font-medium text-gray-900" />
                            <ul class="mt-2 space-y-1 text-sm">
                                <li v-for="(option, optionIndex) in question.options" :key="optionIndex" class="flex gap-1" :class="option.fraction > 0 ? 'font-semibold text-emerald-700' : 'text-gray-700'">
                                    <span>{{ option.fraction > 0 ? '✓' : '·' }}</span>
                                    <MathContent :content="`${option.option_text} (${option.fraction})`" class="block" />
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
