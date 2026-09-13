<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

type StudentItem = {
    id: number;
    admission_number: string;
    first_name: string;
    last_name: string;
    middle_name: string | null;
    status: string;
    has_account: boolean;
    email: string | null;
    is_active: boolean;
    current_enrollment: { section: string; class_level: string } | null;
};

defineProps<{
    students: { data: StudentItem[]; links: Record<string, unknown>[] };
    filters: { search: string; status: string };
    statuses: { value: string; label: string }[];
}>();

const resetPasswordForm = useForm({ password: '', password_confirmation: '' });
const selectedStudentId = ref<number | null>(null);
const showResetPassword = ref(false);
const showDeleteConfirm = ref(false);
const studentToDelete = ref<StudentItem | null>(null);

const openResetPassword = (studentId: number) => {
    selectedStudentId.value = studentId;
    resetPasswordForm.reset();
    showResetPassword.value = true;
};

const submitResetPassword = () => {
    if (! selectedStudentId.value) return;
    resetPasswordForm.post(route('staff.users.students.reset-password', selectedStudentId.value), {
        onSuccess: () => {
            showResetPassword.value = false;
            resetPasswordForm.reset();
        },
    });
};

const createAccount = (studentId: number) => {
    router.post(route('staff.users.students.create-account', studentId), {}, { preserveScroll: true });
};

const toggleActive = (studentId: number) => {
    router.post(route('staff.users.students.toggle-active', studentId), {}, { preserveScroll: true });
};

const confirmDelete = (student: StudentItem) => {
    studentToDelete.value = student;
    showDeleteConfirm.value = true;
};

const deleteStudent = () => {
    if (! studentToDelete.value) return;
    router.delete(route('staff.users.students.destroy', studentToDelete.value.id), {
        onSuccess: () => {
            showDeleteConfirm.value = false;
            studentToDelete.value = null;
        },
    });
};

const search = ref('');
const statusFilter = ref('');
const onSearch = () => {
    router.get(route('staff.users.students.index'), { search: search.value, status: statusFilter.value }, { preserveState: true, replace: true });
};
const onStatusChange = () => {
    router.get(route('staff.users.students.index'), { search: search.value, status: statusFilter.value }, { preserveState: true, replace: true });
};
</script>

<template>
    <Head title="Student Management" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Student Management</h2>
                    <p class="text-sm text-gray-500">Manage student accounts and enrollments.</p>
                </div>
                <Link
                    :href="route('staff.users.students.import-form')"
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                >
                    Import CSV
                </Link>
                <Link
                    :href="route('staff.users.students.create')"
                    class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"
                >
                    Add Student
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="mb-4 flex items-center gap-3">
                    <TextInput
                        v-model="search"
                        placeholder="Search by name or admission number..."
                        class="max-w-sm"
                        @keyup.enter="onSearch"
                    />
                    <select v-model="statusFilter" @change="onStatusChange" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All Statuses</option>
                        <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                    <PrimaryButton @click="onSearch">Search</PrimaryButton>
                </div>

                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Adm. No.</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Class</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Account</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="student in students.data" :key="student.id" class="hover:bg-gray-50">
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-slate-900">{{ student.admission_number }}</td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <Link :href="route('staff.users.students.edit', student.id)" class="font-medium text-slate-900 hover:underline">
                                        {{ student.last_name }}, {{ student.first_name }}{{ student.middle_name ? ' ' + student.middle_name : '' }}
                                    </Link>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                    {{ student.current_enrollment ? student.current_enrollment.class_level + ' - ' + student.current_enrollment.section : 'Not enrolled' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span :class="{
                                        'bg-green-100 text-green-800': student.status === 'active',
                                        'bg-red-100 text-red-800': student.status === 'inactive',
                                        'bg-blue-100 text-blue-800': student.status === 'graduated',
                                        'bg-gray-100 text-gray-800': student.status === 'withdrawn',
                                    }" class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium">
                                        {{ student.status }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm">
                                    <span v-if="student.has_account" :class="student.is_active ? 'text-green-600' : 'text-red-600'">
                                        {{ student.email }} ({{ student.is_active ? 'Active' : 'Inactive' }})
                                    </span>
                                    <span v-else class="text-gray-400">No account</span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                    <div class="flex items-center justify-end gap-2">
                                        <Link :href="route('staff.users.students.edit', student.id)" class="text-slate-600 hover:text-slate-900">Edit</Link>
                                        <button v-if="!student.has_account" @click="createAccount(student.id)" class="text-blue-600 hover:text-blue-800">
                                            Create Account
                                        </button>
                                        <template v-if="student.has_account">
                                            <button @click="openResetPassword(student.id)" class="text-amber-600 hover:text-amber-800">Reset PW</button>
                                            <button @click="toggleActive(student.id)" class="text-gray-600 hover:text-gray-900">
                                                {{ student.is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </template>
                                        <button @click="confirmDelete(student)" class="text-red-600 hover:text-red-800">Delete</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="students.data.length === 0">
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500">No students found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <Modal :show="showResetPassword" @close="showResetPassword = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Reset Student Password</h2>
                <form @submit.prevent="submitResetPassword" class="mt-4 space-y-4">
                    <div>
                        <InputLabel for="reset-password" value="New Password" />
                        <TextInput id="reset-password" v-model="resetPasswordForm.password" type="password" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="resetPasswordForm.errors.password" />
                    </div>
                    <div>
                        <InputLabel for="reset-password-confirm" value="Confirm Password" />
                        <TextInput id="reset-password-confirm" v-model="resetPasswordForm.password_confirmation" type="password" class="mt-1 block w-full" required />
                    </div>
                    <div class="flex justify-end gap-3">
                        <SecondaryButton @click="showResetPassword = false">Cancel</SecondaryButton>
                        <PrimaryButton :disabled="resetPasswordForm.processing">Reset Password</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="showDeleteConfirm" @close="showDeleteConfirm = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Delete Student</h2>
                <p class="mt-2 text-sm text-gray-600">
                    Are you sure you want to delete <strong>{{ studentToDelete?.first_name }} {{ studentToDelete?.last_name }}</strong> ({{ studentToDelete?.admission_number }})? This action cannot be undone.
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="showDeleteConfirm = false">Cancel</SecondaryButton>
                    <DangerButton @click="deleteStudent">Delete</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
