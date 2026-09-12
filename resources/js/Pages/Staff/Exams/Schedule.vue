<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

type Lookup = { id: number; name: string };
type Section = Lookup & { class_level_id: number };
type Audience = { class_level_id: number | null; section_id: number | null };
type Accommodation = {
    student_id: number | null;
    extra_minutes: number;
    opens_at: string;
    closes_at: string;
    extra_attempts: number;
    reason: string;
};

const props = defineProps<{
    exam: {
        id: number;
        title: string;
        status: string;
        subject: string | null;
        opens_at: string | null;
        closes_at: string | null;
        audiences: Audience[];
        accommodations: Accommodation[];
    };
    lookups: {
        class_levels: Lookup[];
        sections: Section[];
        students: Lookup[];
    };
}>();

const form = useForm({
    opens_at: props.exam.opens_at ?? '',
    closes_at: props.exam.closes_at ?? '',
    audiences: props.exam.audiences.length ? props.exam.audiences : [{ class_level_id: null, section_id: null }],
    accommodations: props.exam.accommodations,
});

const sectionOptions = computed(() => (audience: Audience) => props.lookups.sections.filter((section) => section.class_level_id === audience.class_level_id));

function addAudience() {
    form.audiences.push({ class_level_id: null, section_id: null });
}

function removeAudience(index: number) {
    form.audiences.splice(index, 1);
}

function addAccommodation() {
    form.accommodations.push({ student_id: null, extra_minutes: 0, opens_at: '', closes_at: '', extra_attempts: 0, reason: '' });
}

function removeAccommodation(index: number) {
    form.accommodations.splice(index, 1);
}

function save() {
    form.put(route('staff.exams.schedule.update', props.exam.id));
}
</script>

<template>
    <Head :title="`Schedule - ${exam.title}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Schedule Exam</h2>
                    <p class="text-sm text-gray-500">{{ exam.title }} · {{ exam.subject ?? 'No subject' }} · {{ exam.status }}</p>
                </div>
                <Link :href="route('staff.exams.edit', exam.id)" class="text-sm font-semibold text-gray-600 hover:text-gray-900">Back to draft</Link>
            </div>
        </template>

        <div class="py-8">
            <form class="mx-auto max-w-6xl space-y-6 sm:px-6 lg:px-8" @submit.prevent="save">
                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <h3 class="font-semibold text-gray-900">Exam Window</h3>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <label>
                            <span class="text-sm font-medium text-gray-700">Opens At</span>
                            <input v-model="form.opens_at" type="datetime-local" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                        </label>
                        <label>
                            <span class="text-sm font-medium text-gray-700">Closes At</span>
                            <input v-model="form.closes_at" type="datetime-local" class="mt-1 w-full rounded-md border-gray-300 shadow-sm" />
                            <span class="text-sm text-red-600">{{ form.errors.closes_at }}</span>
                        </label>
                    </div>
                </div>

                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <h3 class="font-semibold text-gray-900">Audience</h3>
                            <p class="text-sm text-gray-500">Assign the exam to whole classes or specific arms.</p>
                        </div>
                        <button type="button" class="rounded-md border px-3 py-2 text-sm font-semibold" @click="addAudience">Add Audience</button>
                    </div>
                    <div class="space-y-3">
                        <div v-for="(audience, index) in form.audiences" :key="index" class="grid gap-3 rounded-lg border p-3 md:grid-cols-[1fr_1fr_auto]">
                            <select v-model="audience.class_level_id" class="rounded-md border-gray-300 shadow-sm" @change="audience.section_id = null">
                                <option :value="null">Select class</option>
                                <option v-for="level in lookups.class_levels" :key="level.id" :value="level.id">{{ level.name }}</option>
                            </select>
                            <select v-model="audience.section_id" class="rounded-md border-gray-300 shadow-sm">
                                <option :value="null">All sections</option>
                                <option v-for="section in sectionOptions(audience)" :key="section.id" :value="section.id">{{ section.name }}</option>
                            </select>
                            <button type="button" class="rounded-md border px-3 py-2 text-sm" @click="removeAudience(index)">Remove</button>
                        </div>
                    </div>
                    <span class="text-sm text-red-600">{{ form.errors.audiences }}</span>
                </div>

                <div class="rounded-xl bg-white p-6 shadow-sm">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <h3 class="font-semibold text-gray-900">Student Accommodations</h3>
                            <p class="text-sm text-gray-500">Optional individual overrides for time, attempts, or exam window.</p>
                        </div>
                        <button type="button" class="rounded-md border px-3 py-2 text-sm font-semibold" @click="addAccommodation">Add Accommodation</button>
                    </div>
                    <div class="space-y-3">
                        <div v-for="(accommodation, index) in form.accommodations" :key="index" class="grid gap-3 rounded-lg border p-3 lg:grid-cols-6">
                            <select v-model="accommodation.student_id" class="rounded-md border-gray-300 shadow-sm lg:col-span-2">
                                <option :value="null">Select student</option>
                                <option v-for="student in lookups.students" :key="student.id" :value="student.id">{{ student.name }}</option>
                            </select>
                            <input v-model="accommodation.extra_minutes" type="number" min="0" placeholder="Extra minutes" class="rounded-md border-gray-300 shadow-sm" />
                            <input v-model="accommodation.extra_attempts" type="number" min="0" placeholder="Extra attempts" class="rounded-md border-gray-300 shadow-sm" />
                            <input v-model="accommodation.opens_at" type="datetime-local" class="rounded-md border-gray-300 shadow-sm" />
                            <button type="button" class="rounded-md border px-3 py-2 text-sm" @click="removeAccommodation(index)">Remove</button>
                            <input v-model="accommodation.closes_at" type="datetime-local" class="rounded-md border-gray-300 shadow-sm lg:col-span-2" />
                            <input v-model="accommodation.reason" placeholder="Reason" class="rounded-md border-gray-300 shadow-sm lg:col-span-4" />
                        </div>
                        <div v-if="form.accommodations.length === 0" class="rounded-lg border border-dashed p-6 text-center text-sm text-gray-500">No accommodations configured.</div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" :disabled="form.processing" class="rounded-lg bg-slate-900 px-5 py-2 text-sm font-semibold text-white">
                        {{ form.processing ? 'Saving...' : 'Save Schedule' }}
                    </button>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
