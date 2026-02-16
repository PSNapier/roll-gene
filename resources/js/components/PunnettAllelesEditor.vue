<script setup lang="ts">
import {
    useSortable,
    type UseSortableOptions,
} from '@vueuse/integrations/useSortable';
import { GripVertical, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Input } from '@/components/ui/input';

const props = defineProps<{
    geneName: string;
    alleles: string[];
}>();

const emit = defineEmits<{
    'update:alleles': [value: string[]];
    remove: [index: number];
}>();

const listRef = ref<string[]>([...props.alleles]);
const containerRef = ref<HTMLElement | null>(null);

useSortable(containerRef, listRef, {
    handle: '.drag-handle',
    animation: 150,
    ghostClass: 'opacity-50',
} as UseSortableOptions);

watch(
    () => props.alleles,
    (next) => {
        listRef.value = [...next];
    },
    { deep: true },
);

watch(
    listRef,
    (next) => {
        emit('update:alleles', [...next]);
    },
    { deep: true },
);

function remove(index: number): void {
    emit('remove', index);
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <div ref="containerRef" class="flex h-9 flex-wrap items-center gap-2">
            <div
                v-for="(allele, idx) in listRef"
                :key="idx"
                class="allele-box theme-border flex h-9 min-w-0 items-center gap-1 rounded border bg-transparent pr-1"
            >
                <span
                    class="drag-handle theme-text-dark shrink-0 cursor-grab touch-none p-1 active:cursor-grabbing"
                    aria-label="Drag to reorder"
                >
                    <GripVertical class="size-4" />
                </span>
                <Input
                    v-model="listRef[idx]"
                    class="theme-text allele-input max-w-[4rem] min-w-0 min-w-[2rem] border-0 bg-transparent py-1 text-sm shadow-none focus-visible:ring-0"
                    placeholder="e.g. E"
                />
                <button
                    type="button"
                    class="theme-text-dark hover:theme-text shrink-0 cursor-pointer rounded p-1 disabled:opacity-40"
                    :disabled="listRef.length <= 1"
                    :aria-label="'Remove allele ' + (idx + 1)"
                    @click="remove(idx)"
                >
                    <X class="size-4" />
                </button>
            </div>
        </div>
        <div class="flex h-9 items-center">
            <slot />
        </div>
    </div>
</template>
