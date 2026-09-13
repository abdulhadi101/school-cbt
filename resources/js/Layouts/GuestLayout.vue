<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const school = computed(() => (page.props.school as Record<string, unknown>) ?? {});
const schoolName = computed(() => (school.value.name as string) ?? 'School CBT');
const schoolMotto = computed(() => ((school.value.settings as Record<string, string>)?.motto) ?? 'Excellence in Education');
</script>

<template>
    <div class="flex min-h-screen">
        <!-- Left panel — branding -->
        <div class="relative hidden w-1/2 lg:flex lg:flex-col lg:justify-between overflow-hidden bg-slate-900 p-12">
            <!-- Animated background -->
            <div class="absolute inset-0 overflow-hidden">
                <!-- Grid pattern -->
                <div class="absolute inset-0 opacity-[0.03]" style="background-image: url(&quot;data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='1'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E&quot;)" />

                <!-- Floating orbs -->
                <div class="orb-1 absolute -left-32 -top-32 h-96 w-96 rounded-full bg-indigo-500/20 blur-3xl" />
                <div class="orb-2 absolute -bottom-32 -right-32 h-96 w-96 rounded-full bg-purple-500/20 blur-3xl" />
                <div class="orb-3 absolute left-1/2 top-1/2 h-64 w-64 -translate-x-1/2 -translate-y-1/2 rounded-full bg-indigo-600/10 blur-2xl" />

                <!-- Subtle ring -->
                <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 h-[500px] w-[500px] rounded-full border border-white/[0.04] ring-1 ring-white/[0.02]" />
                <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 h-[700px] w-[700px] rounded-full border border-white/[0.03]" />
            </div>

            <!-- Logo -->
            <div class="relative z-10 animate-fade-in-down">
                <Link href="/" class="inline-flex items-center gap-3">
                    <div class="logo-pulse flex h-10 w-10 items-center justify-center rounded-xl bg-white/10 backdrop-blur-sm">
                        <ApplicationLogo class="h-6 w-6" :show-label="false" />
                    </div>
                    <span class="text-lg font-semibold text-white">School CBT</span>
                </Link>
            </div>

            <!-- Main content -->
            <div class="relative z-10">
                <h1 class="animate-fade-in-up text-3xl font-bold text-white leading-tight">
                    <span class="inline-block animate-fade-in-up" style="animation-delay: 0.1s">Computer-Based</span>
                    <br />
                    <span class="inline-block animate-fade-in-up" style="animation-delay: 0.2s">Testing Platform</span>
                </h1>
                <p class="animate-fade-in-up mt-4 max-w-md text-base text-slate-300" style="animation-delay: 0.35s">
                    {{ schoolName }} — {{ schoolMotto }}
                </p>

                <div class="mt-8 flex items-center gap-6">
                    <div class="animate-fade-in-up flex items-center gap-2 text-sm text-slate-400" style="animation-delay: 0.45s">
                        <svg class="h-5 w-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z" />
                        </svg>
                        Secure Exams
                    </div>
                    <div class="animate-fade-in-up flex items-center gap-2 text-sm text-slate-400" style="animation-delay: 0.55s">
                        <svg class="h-5 w-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                        </svg>
                        Instant Results
                    </div>
                    <div class="animate-fade-in-up flex items-center gap-2 text-sm text-slate-400" style="animation-delay: 0.65s">
                        <svg class="h-5 w-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                        </svg>
                        Multi-Role Access
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="relative z-10 animate-fade-in-up text-sm text-slate-500" style="animation-delay: 0.8s">
                &copy; {{ new Date().getFullYear() }} {{ schoolName }}. All rights reserved.
            </div>
        </div>

        <!-- Right panel — form -->
        <div class="flex w-full flex-col justify-center bg-white px-6 py-12 sm:px-12 lg:w-1/2 lg:px-16 xl:px-20">
            <!-- Mobile logo -->
            <div class="mb-8 lg:hidden">
                <Link href="/" class="inline-flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600">
                        <ApplicationLogo class="h-6 w-6" :show-label="false" />
                    </div>
                    <span class="text-lg font-semibold text-slate-900">School CBT</span>
                </Link>
            </div>

            <div class="mx-auto w-full max-w-sm">
                <slot />
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Floating orbs — slow drift */
.orb-1 {
    animation: drift-1 20s ease-in-out infinite;
}
.orb-2 {
    animation: drift-2 25s ease-in-out infinite;
}
.orb-3 {
    animation: drift-3 18s ease-in-out infinite;
}

@keyframes drift-1 {
    0%, 100% { transform: translate(0, 0) scale(1); }
    33% { transform: translate(40px, 30px) scale(1.05); }
    66% { transform: translate(-20px, 50px) scale(0.95); }
}
@keyframes drift-2 {
    0%, 100% { transform: translate(0, 0) scale(1); }
    33% { transform: translate(-50px, -30px) scale(1.1); }
    66% { transform: translate(30px, -50px) scale(0.9); }
}
@keyframes drift-3 {
    0%, 100% { transform: translate(-50%, -50%) scale(1); }
    50% { transform: translate(-45%, -55%) scale(1.15); }
}

/* Logo pulse glow */
.logo-pulse {
    animation: pulse-glow 3s ease-in-out infinite;
}
@keyframes pulse-glow {
    0%, 100% { box-shadow: 0 0 0 0 rgba(255, 255, 255, 0); }
    50% { box-shadow: 0 0 20px 4px rgba(139, 92, 246, 0.3); }
}

/* Fade in up */
.animate-fade-in-up {
    animation: fadeInUp 0.7s ease-out both;
}
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(16px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Fade in down (for logo) */
.animate-fade-in-down {
    animation: fadeInDown 0.6s ease-out both;
}
@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-12px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
