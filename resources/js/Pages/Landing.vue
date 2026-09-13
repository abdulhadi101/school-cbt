<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

type UpcomingExam = {
    title: string;
    subject: string | null;
    exam_type: string;
    opens_at: string | null;
    closes_at: string | null;
    is_open: boolean;
    audience: string[];
};

defineProps<{
    stats: { students: number; subjects: number; published_exams: number; completed_attempts: number };
    upcoming: UpcomingExam[];
    canLogin: boolean;
}>();

const page = usePage();
const school = computed(() => (page.props.school as Record<string, unknown>) ?? {});

function formatDate(value: string | null): string {
    if (!value) return '—';
    return new Date(value).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
}
</script>

<template>
    <Head :title="(school.name as string)" />

    <div class="min-h-screen bg-slate-50 text-slate-900">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
                <div class="flex items-center gap-3">
                    <img v-if="school.logo_url" :src="school.logo_url as string" :alt="(school.name as string)" class="h-10 w-auto object-contain" />
                    <div v-else class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-700 text-lg font-bold text-white">
                        {{ (school.name as string).charAt(0) }}
                    </div>
                    <div>
                        <p class="font-bold leading-tight">{{ school.name }}</p>
                        <p class="text-xs uppercase tracking-wide text-slate-500">{{ school.code ?? 'CBT Portal' }}</p>
                    </div>
                </div>
                <nav v-if="canLogin" class="flex gap-2">
                    <Link v-if="$page.props.auth.user" :href="route('dashboard')" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Dashboard</Link>
                    <template v-else>
                        <Link :href="route('login')" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Log in</Link>
                    </template>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-6xl space-y-10 px-4 py-10">
            <section class="rounded-2xl bg-emerald-800 p-8 text-white sm:p-12">
                <p class="text-sm font-semibold uppercase tracking-widest text-emerald-200">Computer-Based Testing</p>
                <h1 class="mt-2 text-3xl font-bold sm:text-4xl">{{ school.name }} examination portal</h1>
                <p class="mt-3 max-w-2xl text-emerald-100">Take exams, track results, and stay informed about upcoming assessments — right here on the school network.</p>
                <div v-if="canLogin && !$page.props.auth.user" class="mt-6">
                    <Link :href="route('login')" class="rounded-lg bg-white px-5 py-2.5 text-sm font-semibold text-emerald-900">Sign in to start</Link>
                </div>
            </section>

            <section>
                <h2 class="text-lg font-bold">At a glance</h2>
                <div class="mt-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <div class="rounded-xl bg-white p-5 shadow-sm">
                        <p class="text-sm text-slate-500">Students</p>
                        <p class="mt-1 text-3xl font-bold">{{ stats.students }}</p>
                    </div>
                    <div class="rounded-xl bg-white p-5 shadow-sm">
                        <p class="text-sm text-slate-500">Subjects</p>
                        <p class="mt-1 text-3xl font-bold">{{ stats.subjects }}</p>
                    </div>
                    <div class="rounded-xl bg-white p-5 shadow-sm">
                        <p class="text-sm text-slate-500">Published exams</p>
                        <p class="mt-1 text-3xl font-bold">{{ stats.published_exams }}</p>
                    </div>
                    <div class="rounded-xl bg-white p-5 shadow-sm">
                        <p class="text-sm text-slate-500">Completed attempts</p>
                        <p class="mt-1 text-3xl font-bold">{{ stats.completed_attempts }}</p>
                    </div>
                </div>
            </section>

            <section>
                <h2 class="text-lg font-bold">Upcoming & open exams</h2>
                <div v-if="upcoming.length === 0" class="mt-4 rounded-xl bg-white p-8 text-center text-sm text-slate-500 shadow-sm">
                    No exams scheduled right now. Check back soon.
                </div>
                <div v-else class="mt-4 grid gap-4 md:grid-cols-2">
                    <div v-for="(exam, index) in upcoming" :key="index" class="rounded-xl bg-white p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ exam.subject ?? exam.exam_type }}</p>
                                <h3 class="mt-1 font-semibold">{{ exam.title }}</h3>
                                <p v-if="exam.audience.length" class="mt-1 text-sm text-slate-500">{{ exam.audience.join(', ') }}</p>
                            </div>
                            <span :class="exam.is_open ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'" class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold">
                                {{ exam.is_open ? 'Open now' : 'Upcoming' }}
                            </span>
                        </div>
                        <div class="mt-3 flex gap-6 text-sm text-slate-600">
                            <div><span class="text-slate-400">Opens:</span> {{ formatDate(exam.opens_at) }}</div>
                            <div><span class="text-slate-400">Closes:</span> {{ formatDate(exam.closes_at) }}</div>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-slate-200 bg-white">
            <div class="mx-auto max-w-6xl px-4 py-6 text-sm text-slate-500">
                {{ school.name }} · CBT Portal · {{ new Date().getFullYear() }}
            </div>
        </footer>
    </div>
</template>
