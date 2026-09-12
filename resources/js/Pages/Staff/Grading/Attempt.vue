<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

type Question = {
    attempt_question_id: number;
    position: number;
    marks: string | number;
    requires_manual_grading: boolean;
    snapshot: Record<string, unknown>;
    option_order: string[] | null;
    answer: {
        id: number;
        response: Record<string, unknown> | null;
        grading_status: string;
        score: string | number | null;
        is_correct: boolean | null;
        feedback: string | null;
    } | null;
};

const props = defineProps<{
    attempt_id: number;
    exam_id: number;
    status: string;
    score: string | number | null;
    max_score: string | number | null;
    percentage: string | number | null;
    questions: Question[];
    student_name: string;
    admission_number: string | null;
    exam_title: string;
}>();

const currentIndex = ref(0);
const gradingStates = ref<Record<number, { score: string; feedback: string; saving: boolean; saved: boolean }>>({});

for (const question of props.questions) {
    const answer = question.answer;
    gradingStates.value[question.attempt_question_id] = {
        score: answer?.score !== null && answer?.score !== undefined ? String(answer.score) : '',
        feedback: answer?.feedback ?? '',
        saving: false,
        saved: false,
    };
}

const currentQuestion = ref<Question>(props.questions[0]);
const gradedCount = ref(
    props.questions.filter((q) => q.answer && ['auto_graded', 'manually_graded'].includes(q.answer.grading_status)).length,
);

function selectQuestion(index: number) {
    currentIndex.value = index;
    currentQuestion.value = props.questions[index];
}

function selectedOptionText(question: Question): string {
    if (!question.answer?.response) return 'No response';
    const selectedId = (question.answer.response as Record<string, unknown>).selected_option_id;
    const options = (question.snapshot.options ?? []) as Array<{ id: number; option_text: string; position: number }>;
    const option = options.find((o) => o.id === Number(selectedId));
    return option?.option_text ?? `Option #${selectedId}`;
}

async function saveGrade(question: Question) {
    if (!question.answer) return;
    const state = gradingStates.value[question.attempt_question_id];
    if (state.score === '') return;
    state.saving = true;
    state.saved = false;

    const response = await fetch(route('staff.answers.grade', question.answer.id), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
        },
        body: JSON.stringify({
            score: parseFloat(state.score),
            feedback: state.feedback || null,
        }),
    });

    if (response.ok) {
        state.saved = true;
        gradedCount.value = props.questions.filter(
            (q) => q.answer && ['auto_graded', 'manually_graded'].includes(q.answer.grading_status),
        ).length;
    }
    state.saving = false;
}

function responseDisplay(response: Record<string, unknown> | null): string {
    if (!response) return 'No answer';
    if (response.answer) return String(response.answer);
    if (response.selected_option_id) return `Selected option #${response.selected_option_id}`;
    return JSON.stringify(response);
}
</script>

<template>
    <Head :title="`Grade - ${student_name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Grade Attempt</h2>
                    <p class="text-sm text-gray-500">{{ student_name }} <span v-if="admission_number" class="text-gray-400">/ {{ admission_number }}</span> &mdash; {{ exam_title }}</p>
                </div>
                <Link :href="route('staff.grading.exam', exam_id)" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Back to Exam</Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl gap-6 sm:px-6 lg:px-8">
                <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
                    <div class="space-y-4">
                        <div class="rounded-xl bg-white p-6 shadow-sm">
                            <div class="mb-4 flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-sm font-semibold text-slate-500">Question {{ currentQuestion.position }} of {{ questions.length }}</p>
                                    <h3 class="mt-2 text-lg font-semibold text-gray-900" v-html="(currentQuestion.snapshot as Record<string, unknown>).question_text" />
                                </div>
                                <span class="shrink-0 rounded-full bg-gray-100 px-3 py-1 text-sm text-gray-600">{{ currentQuestion.marks }} marks</span>
                            </div>

                            <div v-if="currentQuestion.snapshot.type === 'essay'" class="mt-4 rounded-lg bg-gray-50 p-4">
                                <p class="text-xs font-semibold uppercase text-gray-500">Student Answer</p>
                                <p class="mt-2 whitespace-pre-wrap text-sm text-gray-800">{{ responseDisplay(currentQuestion.answer?.response ?? null) }}</p>
                            </div>

                            <div v-else-if="currentQuestion.snapshot.type === 'single_choice' || currentQuestion.snapshot.type === 'true_false'" class="mt-4 space-y-2">
                                <p class="text-xs font-semibold uppercase text-gray-500">Student Answer</p>
                                <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-800">{{ selectedOptionText(currentQuestion) }}</div>
                            </div>

                            <div v-else class="mt-4 rounded-lg bg-gray-50 p-4">
                                <p class="text-xs font-semibold uppercase text-gray-500">Student Answer</p>
                                <p class="mt-2 text-sm text-gray-800">{{ responseDisplay(currentQuestion.answer?.response ?? null) }}</p>
                            </div>
                        </div>

                        <div v-if="currentQuestion.answer" class="rounded-xl bg-white p-6 shadow-sm">
                            <h4 class="text-sm font-semibold text-gray-900">Grade This Answer</h4>
                            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Score (0 - {{ currentQuestion.marks }})</label>
                                    <input v-model="gradingStates[currentQuestion.attempt_question_id].score" type="number" :min="0" :max="Number(currentQuestion.marks)" step="0.5"
                                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-slate-500 focus:ring-slate-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Feedback (optional)</label>
                                    <input v-model="gradingStates[currentQuestion.attempt_question_id].feedback" type="text"
                                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-slate-500 focus:ring-slate-500" placeholder="Optional feedback" />
                                </div>
                            </div>
                            <div class="mt-4 flex items-center gap-3">
                                <button :disabled="gradingStates[currentQuestion.attempt_question_id].saving || gradingStates[currentQuestion.attempt_question_id].score === ''"
                                    class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50"
                                    @click="saveGrade(currentQuestion)">
                                    {{ gradingStates[currentQuestion.attempt_question_id].saving ? 'Saving...' : 'Save Grade' }}
                                </button>
                                <span v-if="gradingStates[currentQuestion.attempt_question_id].saved" class="text-sm text-emerald-600">Saved</span>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button v-for="(question, index) in questions" :key="question.attempt_question_id"
                                class="h-10 w-10 rounded-lg text-sm font-semibold transition"
                                :class="{
                                    'bg-slate-900 text-white': index === currentIndex,
                                    'bg-emerald-100 text-emerald-700': index !== currentIndex && question.answer && ['auto_graded', 'manually_graded'].includes(question.answer.grading_status),
                                    'bg-amber-100 text-amber-700': index !== currentIndex && question.requires_manual_grading && (!question.answer || question.answer.grading_status === 'ungraded' || question.answer.grading_status === 'needs_grading'),
                                    'bg-gray-100 text-gray-600': index !== currentIndex && !['auto_graded', 'manually_graded'].includes(question.answer?.grading_status ?? '') && !(question.requires_manual_grading),
                                }"
                                @click="selectQuestion(index)">
                                {{ question.position }}
                            </button>
                        </div>
                    </div>

                    <div class="rounded-xl bg-white p-6 shadow-sm">
                        <h3 class="text-sm font-semibold text-gray-900">Attempt Summary</h3>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div class="flex justify-between"><dt class="text-gray-500">Status</dt><dd class="font-medium capitalize text-gray-900">{{ status.replaceAll('_', ' ') }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">Score</dt><dd class="font-medium text-gray-900">{{ score ?? '-' }} / {{ max_score ?? '-' }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">Percentage</dt><dd class="font-medium text-gray-900">{{ percentage ?? '-' }}%</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">Graded</dt><dd class="font-medium text-gray-900">{{ gradedCount }} / {{ questions.length }}</dd></div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
