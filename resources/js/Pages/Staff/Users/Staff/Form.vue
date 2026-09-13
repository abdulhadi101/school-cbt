<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

type Role = { id: number; name: string; label: string };

const props = defineProps<{
    user: {
        id: number;
        name: string;
        email: string;
        is_active: boolean;
        role_ids: number[];
        first_name: string | null;
        last_name: string | null;
        middle_name: string | null;
        staff_number: string | null;
        can_publish_exams: boolean;
    } | null;
    roles: Role[];
}>();

const form = useForm({
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    password: '',
    password_confirmation: '',
    role_ids: props.user?.role_ids ?? ([] as number[]),
    first_name: props.user?.first_name ?? '',
    last_name: props.user?.last_name ?? '',
    middle_name: props.user?.middle_name ?? '',
    staff_number: props.user?.staff_number ?? '',
    can_publish_exams: props.user?.can_publish_exams ?? false,
    is_active: props.user?.is_active ?? true,
});

const submit = () => {
    if (props.user) {
        form.put(route('staff.users.staff.update', props.user.id), { preserveScroll: true });
    } else {
        form.post(route('staff.users.staff.store'));
    }
};
</script>

<template>
    <Head :title="user ? 'Edit Staff' : 'Add Staff'" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ user ? 'Edit Staff' : 'Add Staff' }}</h2>
                    <p class="text-sm text-gray-500">{{ user ? 'Update account details and roles.' : 'Create a new staff account with role assignment.' }}</p>
                </div>
                <Link :href="route('staff.users.staff.index')" class="text-sm text-slate-600 hover:text-slate-900">Back to list</Link>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-6 rounded-xl bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-medium text-gray-900">Account Information</h3>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="name" value="Full Name *" />
                            <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>
                        <div>
                            <InputLabel for="email" value="Email *" />
                            <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required />
                            <InputError class="mt-2" :message="form.errors.email" />
                        </div>
                    </div>

                    <div v-if="!user" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="password" value="Password *" />
                            <TextInput id="password" v-model="form.password" type="password" class="mt-1 block w-full" required />
                            <InputError class="mt-2" :message="form.errors.password" />
                        </div>
                        <div>
                            <InputLabel for="password_confirmation" value="Confirm Password *" />
                            <TextInput id="password_confirmation" v-model="form.password_confirmation" type="password" class="mt-1 block w-full" required />
                        </div>
                    </div>

                    <div>
                        <InputLabel value="Roles *" />
                        <div class="mt-2 space-y-2">
                            <label v-for="role in roles" :key="role.id" class="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    :value="role.id"
                                    v-model="form.role_ids"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                />
                                <span class="text-sm text-gray-700">{{ role.label }}</span>
                            </label>
                        </div>
                        <InputError class="mt-2" :message="form.errors.role_ids" />
                    </div>

                    <hr class="my-6" />
                    <h3 class="text-lg font-medium text-gray-900">Profile Details</h3>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <InputLabel for="first_name" value="First Name" />
                            <TextInput id="first_name" v-model="form.first_name" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.first_name" />
                        </div>
                        <div>
                            <InputLabel for="last_name" value="Last Name" />
                            <TextInput id="last_name" v-model="form.last_name" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.last_name" />
                        </div>
                        <div>
                            <InputLabel for="middle_name" value="Middle Name" />
                            <TextInput id="middle_name" v-model="form.middle_name" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.middle_name" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="staff_number" value="Staff Number" />
                            <TextInput id="staff_number" v-model="form.staff_number" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.staff_number" />
                        </div>
                        <div class="flex items-end">
                            <label class="flex items-center gap-2">
                                <input type="checkbox" v-model="form.can_publish_exams" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                                <span class="text-sm text-gray-700">Can publish exams</span>
                            </label>
                        </div>
                    </div>

                    <div v-if="user" class="flex items-center gap-2">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" v-model="form.is_active" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                            <span class="text-sm text-gray-700">Account is active</span>
                        </label>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4">
                        <Link :href="route('staff.users.staff.index')" class="text-sm text-gray-600 hover:text-gray-900">Cancel</Link>
                        <PrimaryButton :disabled="form.processing">
                            {{ user ? 'Save Changes' : 'Create Staff' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
