<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import { show as rollerShow } from '@/routes/rollers';

interface Roller {
    id: number;
    name: string;
    slug: string;
    is_core: boolean;
}

const props = defineProps<{
    rollers: Roller[];
}>();
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout>
        <div class="flex flex-col gap-6 p-6">
            <h1 class="theme-text text-2xl font-semibold">Rollers</h1>

            <div
                class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4"
            >
                <Link
                    v-for="roller in props.rollers"
                    :key="roller.id"
                    :href="rollerShow.url(roller.slug)"
                    class="theme-bg-dark theme-border theme-text flex flex-col rounded-lg border p-4 transition hover:opacity-90"
                >
                    <span class="font-medium">{{ roller.name }}</span>
                    <span
                        v-if="roller.is_core"
                        class="theme-text-dark mt-1 text-xs"
                    >
                        Core
                    </span>
                </Link>

                <span
                    class="theme-border theme-text-dark flex min-h-[80px] cursor-not-allowed flex-col items-center justify-center rounded-lg border border-dashed p-4 opacity-70"
                    aria-label="Create new roller (coming soon)"
                >
                    <Plus class="size-8" />
                    <span class="mt-2 text-sm font-medium">New roller</span>
                </span>
            </div>
        </div>
    </AppLayout>
</template>
