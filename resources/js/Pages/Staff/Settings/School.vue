<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    school: {
        id: number;
        name: string;
        code: string | null;
        address: string | null;
        phone: string | null;
        email: string | null;
        logo_url: string | null;
        settings: Record<string, string> | null;
    };
}>();

const form = useForm({
    name: props.school.name,
    code: props.school.code ?? '',
    address: props.school.address ?? '',
    phone: props.school.phone ?? '',
    email: props.school.email ?? '',
    logo: null as File | null,
    remove_logo: false,
    settings: {
        motto: props.school.settings?.motto ?? '',
        website: props.school.settings?.website ?? '',
        academic_year: props.school.settings?.academic_year ?? '',
    },
});

const logoPreview = ref<string | null>(props.school.logo_url);
const logoInput = ref<HTMLInputElement | null>(null);

const onLogoChange = (e: Event) => {
    const input = e.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    form.logo = file;
    form.remove_logo = false;

    if (file) {
        const reader = new FileReader();
        reader.onload = (ev) => {
            logoPreview.value = ev.target?.result as string;
        };
        reader.readAsDataURL(file);
    }
};

const removeLogo = () => {
    form.logo = null;
    form.remove_logo = true;
    logoPreview.value = null;
    if (logoInput.value) {
        logoInput.value.value = '';
    }
};

const submit = () => {
    form.post(route('staff.settings.school.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="School Settings" />

    <AuthenticatedLayout>
        <template #header>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">School Settings</h2>
                <p class="text-sm text-gray-500">Configure school name, logo, and general information.</p>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-6 rounded-xl bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-medium text-gray-900">School Information</h3>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="name" value="School Name *" />
                            <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>
                        <div>
                            <InputLabel for="code" value="School Code" />
                            <TextInput id="code" v-model="form.code" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.code" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="address" value="Address" />
                        <TextInput id="address" v-model="form.address" type="text" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="form.errors.address" />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="phone" value="Phone" />
                            <TextInput id="phone" v-model="form.phone" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.phone" />
                        </div>
                        <div>
                            <InputLabel for="email" value="Email" />
                            <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.email" />
                        </div>
                    </div>

                    <hr class="my-6" />
                    <h3 class="text-lg font-medium text-gray-900">School Logo</h3>

                    <div class="flex items-start gap-6">
                        <div class="shrink-0">
                            <div v-if="logoPreview" class="relative h-24 w-24 overflow-hidden rounded-lg border border-gray-200 bg-gray-50">
                                <img :src="logoPreview" class="h-full w-full object-contain" alt="Logo preview" />
                                <button type="button" @click="removeLogo" class="absolute top-1 right-1 rounded-full bg-red-500 p-0.5 text-white hover:bg-red-600">
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                </button>
                            </div>
                            <div v-else class="flex h-24 w-24 items-center justify-center rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 text-gray-400">
                                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                            </div>
                        </div>
                        <div class="flex-1">
                            <input ref="logoInput" type="file" accept="image/*" @change="onLogoChange" class="block w-full text-sm text-gray-500 file:mr-4 file:rounded-lg file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-slate-800" />
                            <p class="mt-1 text-xs text-gray-400">PNG, JPG, SVG, or WebP. Max 2MB. Recommended: 200x200px or square ratio.</p>
                            <InputError class="mt-2" :message="form.errors.logo" />
                        </div>
                    </div>

                    <hr class="my-6" />
                    <h3 class="text-lg font-medium text-gray-900">Additional Details</h3>

                    <div>
                        <InputLabel for="motto" value="School Motto" />
                        <TextInput id="motto" v-model="form.settings.motto" type="text" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="form.errors['settings.motto']" />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="website" value="Website" />
                            <TextInput id="website" v-model="form.settings.website" type="url" class="mt-1 block w-full" placeholder="https://..." />
                            <InputError class="mt-2" :message="form.errors['settings.website']" />
                        </div>
                        <div>
                            <InputLabel for="academic_year" value="Academic Year" />
                            <TextInput id="academic_year" v-model="form.settings.academic_year" type="text" class="mt-1 block w-full" placeholder="2025/2026" />
                            <InputError class="mt-2" :message="form.errors['settings.academic_year']" />
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-4">
                        <PrimaryButton :disabled="form.processing">
                            Save Settings
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
