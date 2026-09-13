<script setup lang="ts">
import { ref } from 'vue';
import SidebarNav from '@/Components/SidebarNav.vue';

const mobileNavOpen = ref(false);
</script>

<template>
    <div class="min-h-screen bg-slate-100">
        <SidebarNav :mobile-open="mobileNavOpen" @close="mobileNavOpen = false" />

        <!-- Main content -->
        <div class="lg:pl-72">
            <!-- Top bar -->
            <header class="sticky top-0 z-30 flex h-16 items-center gap-4 border-b border-slate-200 bg-white/80 backdrop-blur-md px-4 sm:px-6 lg:px-8">
                <!-- Mobile hamburger -->
                <button
                    @click="mobileNavOpen = !mobileNavOpen"
                    class="inline-flex items-center justify-center rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700 lg:hidden"
                >
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>

                <div class="flex flex-1 items-center justify-end gap-4">
                    <slot name="actions" />
                </div>
            </header>

            <!-- Page header -->
            <div v-if="$slots.header" class="border-b border-slate-200 bg-white">
                <div class="px-4 py-5 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </div>

            <!-- Page content -->
            <main class="p-4 sm:p-6 lg:p-8">
                <slot />
            </main>
        </div>
    </div>
</template>
