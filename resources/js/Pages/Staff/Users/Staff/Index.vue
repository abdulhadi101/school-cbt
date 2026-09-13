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

type StaffUser = {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    roles: Record<string, string>;
    last_login_at: string | null;
};

defineProps<{
    users: { data: StaffUser[]; links: Record<string, unknown>[] };
    filters: { search: string };
}>();

const resetPasswordForm = useForm({ password: '', password_confirmation: '' });
const selectedUserId = ref<number | null>(null);
const showResetPassword = ref(false);
const showDeleteConfirm = ref(false);
const userToDelete = ref<StaffUser | null>(null);

const openResetPassword = (userId: number) => {
    selectedUserId.value = userId;
    resetPasswordForm.reset();
    showResetPassword.value = true;
};

const submitResetPassword = () => {
    if (! selectedUserId.value) return;
    resetPasswordForm.post(route('staff.users.staff.reset-password', selectedUserId.value), {
        onSuccess: () => {
            showResetPassword.value = false;
            resetPasswordForm.reset();
        },
    });
};

const toggleActive = (userId: number) => {
    router.post(route('staff.users.staff.toggle-active', userId), {}, { preserveScroll: true });
};

const confirmDelete = (user: StaffUser) => {
    userToDelete.value = user;
    showDeleteConfirm.value = true;
};

const deleteUser = () => {
    if (! userToDelete.value) return;
    router.delete(route('staff.users.staff.destroy', userToDelete.value.id), {
        onSuccess: () => {
            showDeleteConfirm.value = false;
            userToDelete.value = null;
        },
    });
};

const search = ref('');
const onSearch = () => {
    router.get(route('staff.users.staff.index'), { search: search.value }, { preserveState: true, replace: true });
};
</script>

<template>
    <Head title="Staff Management" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">Staff Management</h2>
                    <p class="text-sm text-gray-500">Manage staff accounts, roles, and permissions.</p>
                </div>
                <Link
                    :href="route('staff.users.staff.import-form')"
                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                >
                    Import CSV
                </Link>
                <Link
                    :href="route('staff.users.staff.create')"
                    class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"
                >
                    Add Staff
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div class="mb-4 flex items-center gap-3">
                    <TextInput
                        v-model="search"
                        placeholder="Search by name or email..."
                        class="max-w-sm"
                        @keyup.enter="onSearch"
                    />
                    <PrimaryButton @click="onSearch">Search</PrimaryButton>
                </div>

                <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Roles</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Last Login</th>
                                <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="user in users.data" :key="user.id" class="hover:bg-gray-50">
                                <td class="whitespace-nowrap px-6 py-4">
                                    <Link :href="route('staff.users.staff.edit', user.id)" class="font-medium text-slate-900 hover:underline">{{ user.name }}</Link>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ user.email }}</td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-wrap gap-1">
                                        <span v-for="(label, name) in user.roles" :key="name" class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">
                                            {{ label }}
                                        </span>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4">
                                    <span :class="user.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'" class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium">
                                        {{ user.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">{{ user.last_login_at ?? 'Never' }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                    <div class="flex items-center justify-end gap-2">
                                        <Link :href="route('staff.users.staff.edit', user.id)" class="text-slate-600 hover:text-slate-900">Edit</Link>
                                        <button @click="openResetPassword(user.id)" class="text-amber-600 hover:text-amber-800">Reset PW</button>
                                        <button @click="toggleActive(user.id)" class="text-gray-600 hover:text-gray-900">
                                            {{ user.is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                        <button @click="confirmDelete(user)" class="text-red-600 hover:text-red-800">Delete</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="users.data.length === 0">
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500">No staff found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <Modal :show="showResetPassword" @close="showResetPassword = false">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">Reset Password</h2>
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
                <h2 class="text-lg font-medium text-gray-900">Delete Staff Account</h2>
                <p class="mt-2 text-sm text-gray-600">
                    Are you sure you want to delete <strong>{{ userToDelete?.name }}</strong>? This action cannot be undone.
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="showDeleteConfirm = false">Cancel</SecondaryButton>
                    <DangerButton @click="deleteUser">Delete</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
