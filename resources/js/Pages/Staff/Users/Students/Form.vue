<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

type ClassLevel = { id: number; name: string };
type Section = { id: number; name: string; class_level_id: number; classLevel?: ClassLevel };

const props = defineProps<{
    student: {
        id: number;
        admission_number: string;
        first_name: string;
        last_name: string;
        middle_name: string | null;
        status: string;
        has_account: boolean;
        email: string | null;
        is_active: boolean;
        current_section_id: number | null;
    } | null;
    class_levels: ClassLevel[];
    sections: Section[];
    current_session: { id: number; name: string } | null;
}>();

const form = useForm({
    admission_number: props.student?.admission_number ?? '',
    first_name: props.student?.first_name ?? '',
    last_name: props.student?.last_name ?? '',
    middle_name: props.student?.middle_name ?? '',
    email: props.student?.email ?? '',
    password: '',
    password_confirmation: '',
    status: props.student?.status ?? 'active',
    section_id: props.student?.current_section_id ?? '',
});

const filteredSections = computed(() => {
    if (! form.section_id) return props.sections;
    return props.sections;
});

const submit = () => {
    if (props.student) {
        form.put(route('staff.users.students.update', props.student.id), { preserveScroll: true });
    } else {
        form.post(route('staff.users.students.store'));
    }
};
</script>

<template>
    <Head :title="student ? 'Edit Student' : 'Add Student'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ student ? 'Edit Student' : 'Add Student' }}</h2>
                    <p class="text-sm text-gray-500">{{ student ? 'Update student details and enrollment.' : 'Create a new student record with optional login account.' }}</p>
                </div>
                <Link :href="route('staff.users.students.index')" class="text-sm text-slate-600 hover:text-slate-900">Back to list</Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-6 rounded-xl bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-medium text-gray-900">Student Information</h3>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <InputLabel for="admission_number" value="Admission Number *" />
                            <TextInput id="admission_number" v-model="form.admission_number" type="text" class="mt-1 block w-full" required />
                            <InputError class="mt-2" :message="form.errors.admission_number" />
                        </div>
                        <div>
                            <InputLabel for="first_name" value="First Name *" />
                            <TextInput id="first_name" v-model="form.first_name" type="text" class="mt-1 block w-full" required />
                            <InputError class="mt-2" :message="form.errors.first_name" />
                        </div>
                        <div>
                            <InputLabel for="last_name" value="Last Name *" />
                            <TextInput id="last_name" v-model="form.last_name" type="text" class="mt-1 block w-full" required />
                            <InputError class="mt-2" :message="form.errors.last_name" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="middle_name" value="Middle Name" />
                            <TextInput id="middle_name" v-model="form.middle_name" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.middle_name" />
                        </div>
                        <div v-if="student">
                            <InputLabel for="status" value="Status" />
                            <select id="status" v-model="form.status" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="graduated">Graduated</option>
                                <option value="withdrawn">Withdrawn</option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.status" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="section_id" value="Enroll in Section" />
                        <select id="section_id" v-model="form.section_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Not enrolled</option>
                            <option v-for="section in sections" :key="section.id" :value="section.id">
                                {{ section.classLevel?.name ?? '' }} - {{ section.name }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.section_id" />
                        <p v-if="current_session" class="mt-1 text-xs text-gray-400">Session: {{ current_session.name }}</p>
                    </div>

                    <hr class="my-6" />
                    <h3 class="text-lg font-medium text-gray-900">Login Account {{ student?.has_account ? '(Already created)' : '(Optional)' }}</h3>

                    <div v-if="!student?.has_account" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="email" value="Email" />
                            <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.email" />
                            <p class="mt-1 text-xs text-gray-400">Leave blank to skip creating a login account.</p>
                        </div>
                        <div>
                            <InputLabel for="password" value="Password" />
                            <TextInput id="password" v-model="form.password" type="password" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.password" />
                        </div>
                    </div>

                    <div v-if="!student?.has_account && form.email" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="password_confirmation" value="Confirm Password" />
                            <TextInput id="password_confirmation" v-model="form.password_confirmation" type="password" class="mt-1 block w-full" />
                        </div>
                        <div></div>
                    </div>

                    <div v-if="student?.has_account" class="rounded-lg bg-green-50 p-4 text-sm text-green-700">
                        This student has a login account ({{ student.email }}). You can reset their password from the student list.
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4">
                        <Link :href="route('staff.users.students.index')" class="text-sm text-gray-600 hover:text-gray-900">Cancel</Link>
                        <PrimaryButton :disabled="form.processing">
                            {{ student ? 'Save Changes' : 'Create Student' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
