<script setup lang="ts">
import MathContent from '@/Components/MathContent.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

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
    client_sequence: number;
};
type AttemptPayload = {
    attempt_id: number;
    status: string;
    deadline_at: string | null;
    exam: { title: string; subject?: string | null; instructions?: string | null };
    questions: Question[];
};

const props = defineProps<{ attempt: AttemptPayload }>();

const currentIndex = ref(0);
const saving = ref(false);
const submitting = ref(false);
const saveMessage = ref('Ready');
const responses = ref<Record<number, Record<string, unknown>>>({});
const sequences = ref<Record<number, number>>({});

for (const question of props.attempt.questions) {
    responses.value[question.attempt_question_id] = question.response ?? {};
    sequences.value[question.attempt_question_id] = question.client_sequence ?? 0;
}

const currentQuestion = computed(() => props.attempt.questions[currentIndex.value]);
const answeredCount = computed(
    () => Object.values(responses.value).filter((response) => Object.keys(response).length > 0).length,
);

function selectOption(question: Question, optionId: number) {
    responses.value[question.attempt_question_id] = { selected_option_id: optionId };
    void save(question);
}

function updateText(question: Question, value: string) {
    responses.value[question.attempt_question_id] = { answer: value };
}

function textAnswer(question: Question): string {
    const value = responses.value[question.attempt_question_id]?.answer;

    return typeof value === 'string' ? value : '';
}

async function save(question: Question) {
    saving.value = true;
    saveMessage.value = 'Saving...';
    const nextSequence = (sequences.value[question.attempt_question_id] ?? 0) + 1;
    sequences.value[question.attempt_question_id] = nextSequence;

    try {
        const response = await fetch(route('attempts.answers', props.attempt.attempt_id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify({
                attempt_question_id: question.attempt_question_id,
                response: responses.value[question.attempt_question_id],
                client_sequence: nextSequence,
                client_answered_at: new Date().toISOString(),
                time_spent_seconds: 0,
            }),
        });

        if (!response.ok) throw new Error('Save failed');
        saveMessage.value = `Saved ${new Date().toLocaleTimeString()}`;
    } catch {
        saveMessage.value = 'Save failed. Try again before submitting.';
    } finally {
        saving.value = false;
    }
}

function submitAttempt() {
    if (!confirm('Submit this attempt now? You cannot continue editing after submission.')) return;
    submitting.value = true;
    router.post(route('attempts.submit', props.attempt.attempt_id), {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="attempt.exam.title" />

    <div class="min-h-screen bg-slate-950 text-white">
        <header class="border-b border-white/10 bg-slate-900/80 px-4 py-4">
            <div class="mx-auto flex max-w-6xl flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm text-slate-300">{{ attempt.exam.subject || 'CBT Exam' }}</p>
                    <h1 class="text-2xl font-bold">{{ attempt.exam.title }}</h1>
                </div>
                <div class="rounded-lg bg-white/10 px-4 py-2 text-sm">
                    {{ answeredCount }} / {{ attempt.questions.length }} answered
                </div>
            </div>
        </header>

        <main class="mx-auto grid max-w-6xl gap-6 px-4 py-6 lg:grid-cols-[1fr_18rem]">
            <section class="rounded-2xl bg-white p-6 text-slate-950 shadow-xl">
                <div class="mb-4 flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold text-slate-500">Question {{ currentQuestion.position }}</p>
                        <MathContent :content="currentQuestion.question_text" class="mt-2 block text-xl font-semibold" />
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-medium">{{ currentQuestion.marks }} marks</span>
                </div>

                <div v-if="currentQuestion.options.length" class="mt-6 space-y-3">
                    <button
                        v-for="option in currentQuestion.options"
                        :key="option.id"
                        type="button"
                        class="block w-full rounded-xl border px-4 py-3 text-left transition"
                        :class="responses[currentQuestion.attempt_question_id]?.selected_option_id === option.id ? 'border-blue-600 bg-blue-50' : 'border-slate-200 hover:bg-slate-50'"
                        @click="selectOption(currentQuestion, option.id)"
                    >
                        <MathContent :content="option.option_text" class="block" />
                    </button>
                </div>

                <textarea
                    v-else
                    class="mt-6 min-h-48 w-full rounded-xl border-slate-300"
                    :value="textAnswer(currentQuestion)"
                    @input="updateText(currentQuestion, ($event.target as HTMLTextAreaElement).value)"
                    @blur="save(currentQuestion)"
                    placeholder="Type your answer here"
                />

                <div class="mt-8 flex flex-col gap-3 border-t pt-5 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm" :class="saving ? 'text-blue-600' : 'text-slate-500'">{{ saveMessage }}</p>
                    <div class="flex gap-2">
                        <button class="rounded-lg border px-4 py-2 disabled:opacity-40" :disabled="currentIndex === 0" @click="currentIndex--">Previous</button>
                        <button class="rounded-lg border px-4 py-2 disabled:opacity-40" :disabled="currentIndex === attempt.questions.length - 1" @click="currentIndex++">Next</button>
                        <button class="rounded-lg bg-emerald-600 px-4 py-2 text-white disabled:opacity-60" :disabled="submitting" @click="submitAttempt">Submit</button>
                    </div>
                </div>
            </section>

            <aside class="rounded-2xl bg-white/10 p-4">
                <h2 class="font-semibold">Question Palette</h2>
                <div class="mt-4 grid grid-cols-5 gap-2">
                    <button
                        v-for="(question, index) in attempt.questions"
                        :key="question.attempt_question_id"
                        type="button"
                        class="rounded-lg px-3 py-2 text-sm font-semibold"
                        :class="[
                            index === currentIndex ? 'bg-white text-slate-950' : 'bg-white/10 text-white',
                            responses[question.attempt_question_id] && Object.keys(responses[question.attempt_question_id]).length > 0 ? 'ring-2 ring-emerald-400' : '',
                        ]"
                        @click="currentIndex = index"
                    >
                        {{ index + 1 }}
                    </button>
                </div>
            </aside>
        </main>
    </div>
</template>
