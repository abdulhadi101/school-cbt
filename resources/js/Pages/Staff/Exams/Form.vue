<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Lookup = { id: number; name: string };
type ReadyQuestion = {
    id: number;
    label: string;
    subject_id: number | null;
    subject: string | null;
    class_level: string | null;
    type: string;
    difficulty: string | null;
    default_marks: string;
};
type Slot = { question_version_id: number | null; marks_per_question: string };
type ExamForm = {
    title: string;
    description: string;
    instructions: string;
    subject_id: number | null;
    term_id: number | null;
    exam_type: string;
    duration_minutes: number;
    total_marks: string;
    pass_percentage: string;
    max_attempts: number;
    opens_at: string;
    closes_at: string;
    shuffle_questions: boolean;
    shuffle_options: boolean;
    score_release_policy: string;
    score_release_at: string;
    show_responses: boolean;
    show_correct_answers: boolean;
    show_feedback: boolean;
    slots: Slot[];
};
type Exam = Omit<ExamForm, 'slots'> & {
    id: number;
    status: string;
    review_notes: string | null;
    slots: (Slot & { question_label: string | null })[];
};

const props = defineProps<{
    exam: Exam | null;
    lookups: {
        subjects: Lookup[];
        terms: Lookup[];
        ready_questions: ReadyQuestion[];
        exam_types: string[];
        release_policies: string[];
    };
    actions: {
        can_create: boolean;
        can_edit: boolean;
        can_submit: boolean;
        can_approve: boolean;
        can_publish: boolean;
    };
}>();

const selectedQuestionId = ref<number | null>(null);
const reviewNotes = ref(props.exam?.review_notes ?? '');
const form = useForm<ExamForm>({
    title: props.exam?.title ?? '',
    description: props.exam?.description ?? '',
    instructions: props.exam?.instructions ?? '',
    subject_id: props.exam?.subject_id ?? null,
    term_id: props.exam?.term_id ?? null,
    exam_type: props.exam?.exam_type ?? 'ca',
    duration_minutes: props.exam?.duration_minutes ?? 30,
    total_marks: props.exam?.total_marks ?? '1',
    pass_percentage: props.exam?.pass_percentage ?? '50',
    max_attempts: props.exam?.max_attempts ?? 1,
    opens_at: props.exam?.opens_at ?? '',
    closes_at: props.exam?.closes_at ?? '',
    shuffle_questions: props.exam?.shuffle_questions ?? false,
    shuffle_options: props.exam?.shuffle_options ?? false,
    score_release_policy: props.exam?.score_release_policy ?? 'manual',
    score_release_at: props.exam?.score_release_at ?? '',
    show_responses: props.exam?.show_responses ?? false,
    show_correct_answers: props.exam?.show_correct_answers ?? false,
    show_feedback: props.exam?.show_feedback ?? false,
    slots: props.exam?.slots?.length
        ? props.exam.slots.map((slot) => ({ question_version_id: slot.question_version_id, marks_per_question: slot.marks_per_question }))
        : [],
});

const filteredQuestions = computed(() => props.lookups.ready_questions.filter((question) => !form.subject_id || question.subject_id === form.subject_id));
const slotTotal = computed(() => form.slots.reduce((total, slot) => total + Number(slot.marks_per_question || 0), 0).toFixed(2));
const examError = computed(() => (form.errors as Record<string, string>).exam);

function questionLabel(questionId: number | null) {
    const question = props.lookups.ready_questions.find((candidate) => candidate.id === questionId);

    if (!question) return 'Select a ready question';

    return `${question.label} (${question.subject ?? 'No subject'}${question.class_level ? `, ${question.class_level}` : ''})`;
}

function addSlot() {
    const question = props.lookups.ready_questions.find((candidate) => candidate.id === selectedQuestionId.value);

    if (!question) return;

    form.slots.push({
        question_version_id: question.id,
        marks_per_question: question.default_marks,
    });
    selectedQuestionId.value = null;
}

function removeSlot(index: number) {
    form.slots.splice(index, 1);
}

function submit() {
    if (!props.actions.can_edit) return;

    if (props.exam) {
        form.put(route('staff.exams.update', props.exam.id));
        return;
    }

    form.post(route('staff.exams.store'));
}

function submitForApproval() {
    if (props.exam) router.post(route('staff.exams.submit', props.exam.id));
}

function approve() {
    if (props.exam) router.post(route('staff.exams.approve', props.exam.id), { review_notes: reviewNotes.value });
}

function publish() {
    if (props.exam) router.post(route('staff.exams.draft_publish', props.exam.id));
}
</script>

<template>
    <Head :title="exam ? 'Edit Exam Draft' : 'New Exam Draft'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ exam ? 'Edit Exam Draft' : 'New Exam Draft' }}</h2>
                    <p class="text-sm text-gray-500">Configure exam settings and lock ready question versions into draft slots.</p>
                </div>
                <Link :href="route('staff.exams.index')" class="text-sm font-semibold text-gray-600 hover:text-gray-900">Back to exams</Link>
            </div>
        </template>

        <div class="py-8">
            <form class="mx-auto max-w-6xl space-y-6 sm:px-6 lg:px-8" @submit.prevent="submit">
                <div v-if="exam" class="rounded-xl bg-white p-4 shadow-sm">
                    <span class="rounded-full bg-gray-100 px-3 py-1 text-sm capitalize text-gray-700">{{ exam.status }}</span>
                    <span class="ml-3 text-sm text-gray-500">Draft total: {{ slotTotal }} / {{ form.total_marks }} marks</span>
                </div>

                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <div class="grid gap-4 md:grid-cols-3">
                        <label class="md:col-span-3">
                            <span class="text-sm font-medium text-gray-700">Title</span>
                            <input v-model="form.title" :disabled="!actions.can_edit" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                            <span class="text-sm text-red-600">{{ form.errors.title }}</span>
                        </label>

                        <label>
                            <span class="text-sm font-medium text-gray-700">Subject</span>
                            <select v-model="form.subject_id" :disabled="!actions.can_edit" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                <option :value="null">Select subject</option>
                                <option v-for="subject in lookups.subjects" :key="subject.id" :value="subject.id">{{ subject.name }}</option>
                            </select>
                            <span class="text-sm text-red-600">{{ form.errors.subject_id }}</span>
                        </label>

                        <label>
                            <span class="text-sm font-medium text-gray-700">Term</span>
                            <select v-model="form.term_id" :disabled="!actions.can_edit" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                <option :value="null">No term</option>
                                <option v-for="term in lookups.terms" :key="term.id" :value="term.id">{{ term.name }}</option>
                            </select>
                        </label>

                        <label>
                            <span class="text-sm font-medium text-gray-700">Type</span>
                            <select v-model="form.exam_type" :disabled="!actions.can_edit" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                <option v-for="type in lookups.exam_types" :key="type" :value="type">{{ type }}</option>
                            </select>
                        </label>

                        <label>
                            <span class="text-sm font-medium text-gray-700">Duration Minutes</span>
                            <input v-model="form.duration_minutes" :disabled="!actions.can_edit" type="number" min="1" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                        </label>

                        <label>
                            <span class="text-sm font-medium text-gray-700">Total Marks</span>
                            <input v-model="form.total_marks" :disabled="!actions.can_edit" type="number" min="0.01" step="0.01" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                            <span class="text-sm text-red-600">{{ form.errors.total_marks }}</span>
                        </label>

                        <label>
                            <span class="text-sm font-medium text-gray-700">Pass Percentage</span>
                            <input v-model="form.pass_percentage" :disabled="!actions.can_edit" type="number" min="0" max="100" step="0.01" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                        </label>

                        <label>
                            <span class="text-sm font-medium text-gray-700">Max Attempts</span>
                            <input v-model="form.max_attempts" :disabled="!actions.can_edit" type="number" min="1" max="10" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                        </label>

                        <label>
                            <span class="text-sm font-medium text-gray-700">Opens At</span>
                            <input v-model="form.opens_at" :disabled="!actions.can_edit" type="datetime-local" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                        </label>

                        <label>
                            <span class="text-sm font-medium text-gray-700">Closes At</span>
                            <input v-model="form.closes_at" :disabled="!actions.can_edit" type="datetime-local" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                            <span class="text-sm text-red-600">{{ form.errors.closes_at }}</span>
                        </label>

                        <label>
                            <span class="text-sm font-medium text-gray-700">Release Policy</span>
                            <select v-model="form.score_release_policy" :disabled="!actions.can_edit" class="mt-1 w-full rounded-md border-gray-300 shadow-sm">
                                <option v-for="policy in lookups.release_policies" :key="policy" :value="policy">{{ policy }}</option>
                            </select>
                        </label>

                        <label v-if="form.score_release_policy === 'scheduled'">
                            <span class="text-sm font-medium text-gray-700">Release At</span>
                            <input v-model="form.score_release_at" :disabled="!actions.can_edit" type="datetime-local" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                            <span class="text-sm text-red-600">{{ form.errors.score_release_at }}</span>
                        </label>

                        <label class="md:col-span-3">
                            <span class="text-sm font-medium text-gray-700">Description</span>
                            <textarea v-model="form.description" :disabled="!actions.can_edit" rows="3" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                        </label>

                        <label class="md:col-span-3">
                            <span class="text-sm font-medium text-gray-700">Instructions</span>
                            <textarea v-model="form.instructions" :disabled="!actions.can_edit" rows="4" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                        </label>
                    </div>
                </div>

                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <h3 class="font-semibold text-gray-900">Delivery and Review Controls</h3>
                    <div class="mt-4 grid gap-3 md:grid-cols-2 lg:grid-cols-5">
                        <label class="flex items-center gap-2 text-sm text-gray-700"><input v-model="form.shuffle_questions" :disabled="!actions.can_edit" type="checkbox" class="rounded border-gray-300" /> Shuffle questions</label>
                        <label class="flex items-center gap-2 text-sm text-gray-700"><input v-model="form.shuffle_options" :disabled="!actions.can_edit" type="checkbox" class="rounded border-gray-300" /> Shuffle options</label>
                        <label class="flex items-center gap-2 text-sm text-gray-700"><input v-model="form.show_responses" :disabled="!actions.can_edit" type="checkbox" class="rounded border-gray-300" /> Show responses</label>
                        <label class="flex items-center gap-2 text-sm text-gray-700"><input v-model="form.show_correct_answers" :disabled="!actions.can_edit" type="checkbox" class="rounded border-gray-300" /> Show answers</label>
                        <label class="flex items-center gap-2 text-sm text-gray-700"><input v-model="form.show_feedback" :disabled="!actions.can_edit" type="checkbox" class="rounded border-gray-300" /> Show feedback</label>
                    </div>
                </div>

                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <h3 class="font-semibold text-gray-900">Question Slots</h3>
                            <p class="text-sm text-gray-500">Only ready, non-retired question versions for the selected subject can be saved.</p>
                        </div>
                        <div class="flex flex-1 gap-2 md:max-w-xl" v-if="actions.can_edit">
                            <select v-model="selectedQuestionId" class="w-full rounded-md border-gray-300 shadow-sm">
                                <option :value="null">Select ready question</option>
                                <option v-for="question in filteredQuestions" :key="question.id" :value="question.id">
                                    {{ question.label }} · {{ question.class_level ?? 'No class' }} · {{ question.default_marks }} mark(s)
                                </option>
                            </select>
                            <button type="button" class="rounded-md border px-3 py-2 text-sm font-semibold" @click="addSlot">Add</button>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div v-for="(slot, index) in form.slots" :key="index" class="grid gap-3 rounded-lg border p-3 md:grid-cols-[1fr_140px_auto]">
                            <div>
                                <div class="font-medium text-gray-900">{{ index + 1 }}. {{ questionLabel(slot.question_version_id) }}</div>
                            </div>
                            <input v-model="slot.marks_per_question" :disabled="!actions.can_edit" type="number" min="0.01" step="0.01" class="rounded-md border-gray-300 shadow-sm" />
                            <button v-if="actions.can_edit" type="button" class="rounded-md border px-3 py-2 text-sm" @click="removeSlot(index)">Remove</button>
                        </div>
                        <div v-if="form.slots.length === 0" class="rounded-lg border border-dashed p-6 text-center text-sm text-gray-500">No questions selected yet.</div>
                    </div>
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-3 text-sm">
                        <span class="text-red-600">{{ form.errors.slots }}</span>
                        <span class="font-semibold text-gray-700">Slot total: {{ slotTotal }} marks</span>
                    </div>
                </div>

                <div v-if="actions.can_approve" class="rounded-xl bg-white p-6 shadow-sm">
                    <label>
                        <span class="text-sm font-medium text-gray-700">Review Notes</span>
                        <textarea v-model="reviewNotes" rows="3" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                    </label>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <span class="text-sm text-red-600">{{ examError }}</span>
                    <div class="flex flex-wrap gap-2">
                        <button v-if="actions.can_edit" type="submit" :disabled="form.processing" class="rounded-lg bg-slate-900 px-5 py-2 text-sm font-semibold text-white">
                            {{ form.processing ? 'Saving...' : 'Save Draft' }}
                        </button>
                        <button v-if="actions.can_submit" type="button" class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-800" @click="submitForApproval">Submit for Approval</button>
                        <button v-if="actions.can_approve" type="button" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-800" @click="approve">Approve</button>
                        <button v-if="actions.can_publish" type="button" class="rounded-lg border border-purple-200 bg-purple-50 px-4 py-2 text-sm font-semibold text-purple-800" @click="publish">Publish Revision</button>
                    </div>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
