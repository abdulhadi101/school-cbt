<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

type Option = { id: number; option_text: string; position: number };
type Question = {
    attempt_question_id: number;
    position: number;
    marks: string | number;
    type: string;
    question_text: string;
    image_path?: string | null;
    options: Option[];
    response: Record<string, unknown> | null;
    awarded_score: string | number | null;
    is_correct: boolean | null;
    feedback: string | null;
    explanation: string | null;
    answer_key: number[] | null;
};

const props = defineProps<{
    attempt_id: number;
    status: string;
    released: boolean;
    score: string | number | null;
    max_score: string | number | null;
    percentage: string | number | null;
    questions: Question[];
    exam_title: string;
    subject: string | null;
    submitted_at: string | null;
}>();

const currentIndex = ref(0);
const currentQuestion = ref<Question>(props.questions[0]);
const answeredCount = computed(() => props.questions.filter((q) => q.response && Object.keys(q.response).length > 0).length);

function selectQuestion(index: number) {
    currentIndex.value = index;
    currentQuestion.value = props.questions[index];
}

function selectedOptionText(question: Question): string {
    if (!question.response) return 'No answer';
    const selectedId = (question.response as Record<string, unknown>).selected_option_id;
    const option = question.options.find((o) => o.id === Number(selectedId));
    return option?.option_text ?? `Option #${selectedId}`;
}

import { computed } from 'vue';
</script>

<template>
    <Head :title="`Result - ${exam_title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Exam Result</h2>
                    <p class="text-sm text-gray-500">{{ exam_title }} <span v-if="subject" class="text-gray-400">/ {{ subject }}</span></p>
                </div>
                <Link :href="route('student.results.index')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Back to Results</Link>
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
                                    <h3 class="mt-2 text-lg font-semibold text-gray-900" v-html="currentQuestion.question_text" />
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="rounded-full bg-gray-100 px-3 py-1 text-sm text-gray-600">{{ currentQuestion.marks }} marks</span>
                                    <span v-if="currentQuestion.is_correct === true" class="rounded-full bg-emerald-100 px-3 py-1 text-sm font-semibold text-emerald-700">Correct</span>
                                    <span v-else-if="currentQuestion.is_correct === false" class="rounded-full bg-red-100 px-3 py-1 text-sm font-semibold text-red-700">Incorrect</span>
                                </div>
                            </div>

                            <div class="mt-4 space-y-2">
                                <p class="text-xs font-semibold uppercase text-gray-500">Your Answer</p>
                                <div class="rounded-lg border p-3 text-sm"
                                    :class="{
                                        'border-emerald-200 bg-emerald-50 text-emerald-800': currentQuestion.is_correct === true,
                                        'border-red-200 bg-red-50 text-red-800': currentQuestion.is_correct === false,
                                        'border-gray-200 bg-gray-50 text-gray-700': currentQuestion.is_correct === null,
                                    }">
                                    {{ selectedOptionText(currentQuestion) }}
                                </div>
                            </div>

                            <div v-if="currentQuestion.awarded_score !== null" class="mt-3 text-sm text-gray-600">
                                Score: <span class="font-semibold">{{ currentQuestion.awarded_score }} / {{ currentQuestion.marks }}</span>
                            </div>

                            <div v-if="currentQuestion.feedback" class="mt-4 rounded-lg bg-blue-50 p-3 text-sm text-blue-800">
                                <p class="font-semibold">Feedback</p>
                                <p class="mt-1">{{ currentQuestion.feedback }}</p>
                            </div>

                            <div v-if="currentQuestion.explanation" class="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">
                                <p class="font-semibold">Explanation</p>
                                <p class="mt-1">{{ currentQuestion.explanation }}</p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button v-for="(question, index) in questions" :key="question.attempt_question_id"
                                class="h-10 w-10 rounded-lg text-sm font-semibold transition"
                                :class="{
                                    'bg-slate-900 text-white': index === currentIndex,
                                    'bg-emerald-100 text-emerald-700': index !== currentIndex && question.is_correct === true,
                                    'bg-red-100 text-red-700': index !== currentIndex && question.is_correct === false,
                                    'bg-gray-100 text-gray-600': index !== currentIndex && question.is_correct === null,
                                }"
                                @click="selectQuestion(index)">
                                {{ question.position }}
                            </button>
                        </div>
                    </div>

                    <div class="rounded-xl bg-white p-6 shadow-sm">
                        <h3 class="text-sm font-semibold text-gray-900">Summary</h3>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div class="flex justify-between"><dt class="text-gray-500">Score</dt><dd class="font-medium text-gray-900">{{ score ?? '-' }} / {{ max_score ?? '-' }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">Percentage</dt><dd class="font-medium text-gray-900">{{ percentage ?? '-' }}%</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">Answered</dt><dd class="font-medium text-gray-900">{{ answeredCount }} / {{ questions.length }}</dd></div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Correct</dt>
                                <dd class="font-medium text-emerald-600">{{ questions.filter((q) => q.is_correct === true).length }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Incorrect</dt>
                                <dd class="font-medium text-red-600">{{ questions.filter((q) => q.is_correct === false).length }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
