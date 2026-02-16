<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppHeader from '@/components/AppCustomHeader.vue';
import AppShell from '@/components/AppShell.vue';
</script>

<template>
    <AppShell class="flex-col">
        <AppHeader />
        <AppContent>
            <slot />
        </AppContent>
    </AppShell>
</template>
