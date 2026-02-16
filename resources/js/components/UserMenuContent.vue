<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import {
    Crown,
    LayoutGrid,
    List,
    LogOut,
    Settings,
    Sparkle,
    Sparkles,
} from 'lucide-vue-next';
import { computed } from 'vue';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { dashboard, logout } from '@/routes';
import { edit } from '@/routes/profile';
import { index as rollersIndex } from '@/routes/rollers';
import type { User } from '@/types';

type Props = {
    user: User;
};

const handleLogout = () => {
    router.flushAll();
};

const props = defineProps<Props>();

const badgeIcons = computed(() => {
    const icons: { component: typeof Crown; title: string }[] = [];
    if (props.user.is_admin) {
        icons.push({ component: Crown, title: 'Admin' });
    }
    if (props.user.is_super_supporter) {
        icons.push({ component: Sparkles, title: 'Super supporter' });
    }
    if (props.user.is_premium) {
        icons.push({ component: Sparkle, title: 'Premium' });
    }
    return icons;
});
</script>

<template>
    <DropdownMenuLabel class="p-0 font-normal">
        <div class="grid px-1 py-1.5 text-left text-sm leading-tight">
            <div class="flex items-center gap-1 truncate">
                <component
                    v-for="(badge, i) in badgeIcons"
                    :key="i"
                    :is="badge.component"
                    class="h-4 w-4 shrink-0 text-muted-foreground"
                    :title="badge.title"
                />
                <span class="truncate font-medium">{{ user.name }}</span>
            </div>
            <span class="truncate text-xs text-muted-foreground">{{
                user.email
            }}</span>
        </div>
    </DropdownMenuLabel>
    <DropdownMenuSeparator />
    <DropdownMenuGroup>
        <DropdownMenuItem :as-child="true">
            <Link
                class="block w-full cursor-pointer"
                :href="dashboard()"
                prefetch
            >
                <LayoutGrid class="mr-2 h-4 w-4" />
                Dashboard
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem :as-child="true">
            <Link
                class="block w-full cursor-pointer"
                :href="rollersIndex()"
                prefetch
            >
                <List class="mr-2 h-4 w-4" />
                All Rollers
            </Link>
        </DropdownMenuItem>
        <DropdownMenuItem :as-child="true">
            <Link class="block w-full cursor-pointer" :href="edit()" prefetch>
                <Settings class="mr-2 h-4 w-4" />
                Settings
            </Link>
        </DropdownMenuItem>
    </DropdownMenuGroup>
    <DropdownMenuSeparator />
    <DropdownMenuItem :as-child="true">
        <Link
            class="block w-full cursor-pointer"
            :href="logout()"
            @click="handleLogout"
            as="button"
            data-test="logout-button"
        >
            <LogOut class="mr-2 h-4 w-4" />
            Log out
        </Link>
    </DropdownMenuItem>
</template>
