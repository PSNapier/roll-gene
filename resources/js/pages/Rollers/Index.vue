<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { show as rollerShow } from '@/routes/rollers';

interface Roller {
    id: number;
    name: string;
    slug: string;
    is_core: boolean;
    visibility: string;
}

const props = defineProps<{
    rollers: Roller[];
}>();
</script>

<template>
    <Head title="Rollers" />

    <AppLayout>
        <div class="flex flex-col gap-6 p-6">
            <h1 class="theme-text text-2xl font-semibold">Rollers</h1>

            <div class="theme-bg-dark theme-border overflow-hidden rounded-lg border">
                <table class="w-full min-w-[400px] text-sm">
                    <thead>
                        <tr class="theme-border theme-text-dark border-b">
                            <th
                                class="theme-text px-4 py-3 text-left font-medium"
                            >
                                Name
                            </th>
                            <th
                                class="theme-text px-4 py-3 text-left font-medium"
                            >
                                Visibility
                            </th>
                            <th
                                class="theme-text px-4 py-3 text-left font-medium"
                            >
                                Core
                            </th>
                            <th
                                class="theme-text px-4 py-3 text-right font-medium"
                            >
                                Action
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="roller in props.rollers"
                            :key="roller.id"
                            class="theme-text theme-border border-b last:border-b-0 hover:bg-muted/50"
                        >
                            <td class="px-4 py-3 font-medium">
                                <Link
                                    :href="rollerShow.url(roller.slug)"
                                    class="hover:underline"
                                >
                                    {{ roller.name }}
                                </Link>
                            </td>
                            <td class="theme-text-dark px-4 py-3 capitalize">
                                {{ roller.visibility }}
                            </td>
                            <td class="theme-text-dark px-4 py-3">
                                {{ roller.is_core ? 'Yes' : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link
                                    :href="rollerShow.url(roller.slug)"
                                    class="theme-text-dark text-sm hover:underline"
                                >
                                    Open
                                </Link>
                            </td>
                        </tr>
                        <tr
                            v-if="!props.rollers.length"
                            class="theme-text-dark"
                        >
                            <td
                                colspan="4"
                                class="px-4 py-8 text-center text-sm"
                            >
                                No rollers available.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
