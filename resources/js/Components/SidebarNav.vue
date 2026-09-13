<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

defineProps<{
    mobileOpen: boolean;
}>();

const emit = defineEmits<{
    close: [];
}>();

const page = usePage();
const permissions = computed(() => (page.props.auth as Record<string, unknown>).permissions as string[] ?? []);
const isStaff = computed(() => permissions.value.includes('exams.view'));
const isStudent = computed(() => permissions.value.includes('attempts.take'));
const userName = computed(() => ((page.props.auth as Record<string, unknown>).user as Record<string, string>)?.name ?? '');
const userEmail = computed(() => ((page.props.auth as Record<string, unknown>).user as Record<string, string>)?.email ?? '');
const schoolName = computed(() => ((page.props.school as Record<string, unknown>)?.name as string) ?? 'School CBT');

type NavItem = { href: string; label: string; active: boolean; icon: string };
type NavGroup = { label: string; items: NavItem[] };

const staffGroups = computed<NavGroup[]>(() => {
    const groups: NavGroup[] = [];
    const dashboard: NavItem[] = [
        { href: route('dashboard'), label: 'Dashboard', active: route().current('dashboard'), icon: 'M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6Zm0 9.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6Zm0 9.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z' },
    ];

    const academic: NavItem[] = [];
    if (permissions.value.includes('exams.view')) {
        academic.push({ href: route('staff.exams.index'), label: 'Exams', active: route().current('staff.exams.*'), icon: 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z' });
    }
    if (permissions.value.includes('attempts.invigilate')) {
        academic.push({ href: route('staff.invigilation.index'), label: 'Invigilation', active: route().current('staff.invigilation.*') || route().current('staff.exams.invigilation*'), icon: 'M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178ZM15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z' });
    }
    if (permissions.value.includes('grades.grade')) {
        academic.push({ href: route('staff.grading.index'), label: 'Grading', active: route().current('staff.grading.*'), icon: 'M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z' });
    }

    const questions: NavItem[] = [];
    if (permissions.value.includes('questions.view')) {
        questions.push({ href: route('staff.my-subjects.index'), label: 'My Subjects', active: route().current('staff.my-subjects.*') || route().current('staff.subject-questions.*'), icon: 'M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25' });
        questions.push({ href: route('staff.questions.index'), label: 'Question Bank', active: route().current('staff.questions.*'), icon: 'M6.429 9.75 2.25 12l4.179 2.25m0-4.5 5.571 3 5.571-3m-11.142 0L2.25 7.5 12 2.25l9.75 5.25-4.179 2.25m0 0L21.75 12l-4.179 2.25m0 0 4.179 2.25L12 21.75 2.25 16.5l4.179-2.25m11.142 0-5.571 3-5.571-3' });
    }

    const management: NavItem[] = [];
    if (permissions.value.includes('users.manage')) {
        management.push({ href: route('staff.users.staff.index'), label: 'Staff', active: route().current('staff.users.staff.*'), icon: 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z' });
        management.push({ href: route('staff.users.students.index'), label: 'Students', active: route().current('staff.users.students.*'), icon: 'M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5' });
    }
    if (permissions.value.includes('results.release')) {
        management.push({ href: route('staff.audit-logs.index'), label: 'Audit Log', active: route().current('staff.audit-logs.*'), icon: 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15a2.25 2.25 0 0 1 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z' });
    }
    if (permissions.value.includes('system.manage')) {
        management.push({ href: route('staff.settings.school'), label: 'Settings', active: route().current('staff.settings.*'), icon: 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28ZM15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z' });
    }

    groups.push({ label: '', items: dashboard });
    if (academic.length) groups.push({ label: 'Academic', items: academic });
    if (questions.length) groups.push({ label: 'Questions', items: questions });
    if (management.length) groups.push({ label: 'Management', items: management });
    return groups;
});

const studentGroups = computed<NavGroup[]>(() => {
    const links: NavItem[] = [
        { href: route('dashboard'), label: 'Dashboard', active: route().current('dashboard'), icon: 'M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6Zm0 9.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6Zm0 9.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z' },
    ];
    if (permissions.value.includes('attempts.take')) {
        links.push({ href: route('student.exams.index'), label: 'My Exams', active: route().current('student.exams.*'), icon: 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z' });
        links.push({ href: route('student.results.index'), label: 'Results', active: route().current('student.results.*'), icon: 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z' });
    }
    return [{ label: '', items: links }];
});

const groups = computed(() => isStaff.value ? staffGroups.value : studentGroups.value);

const initials = computed(() => userName.value.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2));
</script>

<template>
    <!-- Mobile overlay -->
    <Transition name="fade">
        <div v-if="mobileOpen" class="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm lg:hidden" @click="emit('close')" />
    </Transition>

    <!-- Sidebar -->
    <aside
        :class="[
            'fixed inset-y-0 left-0 z-50 flex w-72 flex-col bg-slate-900 transition-transform duration-300 ease-in-out lg:translate-x-0',
            mobileOpen ? 'translate-x-0' : '-translate-x-full',
        ]"
    >
        <!-- Logo area -->
        <div class="flex h-16 items-center gap-3 border-b border-white/10 px-6">
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 shadow-lg shadow-indigo-500/25">
                <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342" />
                </svg>
            </div>
            <div class="min-w-0 flex-1">
                <div class="text-sm font-semibold text-white truncate">School CBT</div>
                <div class="text-xs text-slate-400 truncate">{{ schoolName }}</div>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-6">
            <div v-for="(group, gi) in groups" :key="gi">
                <div v-if="group.label" class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                    {{ group.label }}
                </div>
                <div class="space-y-0.5">
                    <Link
                        v-for="item in group.items"
                        :key="item.href"
                        :href="item.href"
                        :class="[
                            'group flex items-center gap-3 rounded-lg px-3 py-2.5 text-[13px] font-medium transition-all duration-150',
                            item.active
                                ? 'bg-white/10 text-white shadow-sm'
                                : 'text-slate-400 hover:bg-white/5 hover:text-slate-200',
                        ]"
                    >
                        <svg
                            :class="[
                                'h-[18px] w-[18px] shrink-0 transition-colors',
                                item.active ? 'text-indigo-400' : 'text-slate-500 group-hover:text-slate-300',
                            ]"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                        </svg>
                        <span>{{ item.label }}</span>
                    </Link>
                </div>
            </div>
        </nav>

        <!-- User -->
        <div class="shrink-0 border-t border-white/10 p-4">
            <div class="flex items-center gap-3 rounded-lg px-3 py-2">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 text-xs font-bold text-white ring-2 ring-white/10">
                    {{ initials }}
                </div>
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-medium text-white truncate">{{ userName }}</div>
                    <div class="text-xs text-slate-400 truncate">{{ userEmail }}</div>
                </div>
            </div>
            <div class="mt-2 flex gap-2 px-3">
                <Link
                    :href="route('profile.edit')"
                    class="flex-1 rounded-lg border border-white/10 px-3 py-1.5 text-center text-xs font-medium text-slate-300 transition-colors hover:bg-white/5 hover:text-white"
                >
                    Profile
                </Link>
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="flex-1 rounded-lg border border-white/10 px-3 py-1.5 text-center text-xs font-medium text-slate-300 transition-colors hover:bg-red-500/10 hover:text-red-400 hover:border-red-500/20"
                >
                    Log Out
                </Link>
            </div>
        </div>
    </aside>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active {
    transition: opacity 0.2s ease;
}
.fade-enter-from, .fade-leave-to {
    opacity: 0;
}
</style>
