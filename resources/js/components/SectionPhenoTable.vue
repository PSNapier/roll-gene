<script setup lang="ts">
import type { UseSortableOptions } from '@vueuse/integrations/useSortable';
import { useSortable } from '@vueuse/integrations/useSortable';
import { GripVertical, Plus, Trash2, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Input } from '@/components/ui/input';

interface EditPhenoRow {
    id: number | string;
    name: string;
    alleles: string[];
    locusFirst: boolean;
}

const props = withDefaults(
    defineProps<{
        section: { phenos: EditPhenoRow[] };
        sectionIndex: number;
        canEdit?: boolean;
    }>(),
    { canEdit: false },
);

const emit = defineEmits<{
    'update:phenos': [value: EditPhenoRow[]];
    'remove-pheno': [rowIndex: number];
    'add-locus': [rowIndex: number];
    'remove-locus': [rowIndex: number, locusIndex: number];
}>();

const localPhenos = ref<EditPhenoRow[]>([...props.section.phenos]);
const tbodyRef = ref<HTMLElement | null>(null);
let skipEmit = false;

useSortable(tbodyRef, localPhenos, {
    handle: '.pheno-drag-handle',
    animation: 150,
    ghostClass: 'opacity-50',
} as UseSortableOptions);

watch(
    () => props.section.phenos,
    (p) => {
        skipEmit = true;
        localPhenos.value = [...p];
        queueMicrotask(() => {
            skipEmit = false;
        });
    },
    { deep: true },
);

watch(
    localPhenos,
    (v) => {
        if (!skipEmit) emit('update:phenos', v);
    },
    { deep: true },
);
</script>

<template>
    <tbody ref="tbodyRef">
        <tr
            v-for="(row, i) in localPhenos"
            :key="row.id"
            class="theme-text theme-border border-b last:border-b-0 even:bg-muted"
        >
            <td v-if="canEdit" class="px-1 py-2">
                <span
                    class="pheno-drag-handle theme-text-dark cursor-grab touch-none p-1 active:cursor-grabbing"
                    aria-label="Drag to reorder pheno"
                >
                    <GripVertical class="size-4" />
                </span>
            </td>
            <td class="px-3 py-2 font-medium">
                <Input
                    v-if="canEdit"
                    v-model="row.name"
                    class="theme-text h-8 w-28 text-sm capitalize"
                    placeholder="e.g. black"
                />
                <span v-else class="capitalize">{{ row.name }}</span>
            </td>
            <td v-if="canEdit" class="w-28 px-2 py-2">
                <span
                    class="theme-border flex h-8 cursor-pointer overflow-hidden rounded-md border"
                    role="group"
                    aria-label="Prepend or append locus to pheno"
                >
                    <button
                        type="button"
                        class="theme-text flex flex-1 items-center justify-center px-2 text-xs font-medium transition-colors"
                        :class="
                            row.locusFirst
                                ? 'theme-bg-dark theme-text'
                                : 'theme-text-dark hover:theme-text bg-transparent'
                        "
                        @click="row.locusFirst = true"
                    >
                        Prepend
                    </button>
                    <button
                        type="button"
                        class="theme-text flex flex-1 items-center justify-center px-2 text-xs font-medium transition-colors"
                        :class="
                            !row.locusFirst
                                ? 'theme-bg-dark theme-text'
                                : 'theme-text-dark hover:theme-text bg-transparent'
                        "
                        @click="row.locusFirst = false"
                    >
                        Append
                    </button>
                </span>
            </td>
            <td class="px-3 py-2">
                <template v-if="canEdit">
                    <div class="flex flex-wrap items-center gap-2">
                        <div
                            v-for="(_, locusIdx) in row.alleles"
                            :key="locusIdx"
                            class="theme-border flex h-9 min-w-0 items-center gap-1 rounded border bg-transparent pr-1"
                        >
                            <Input
                                v-model="row.alleles[locusIdx]"
                                class="theme-text max-w-[4rem] min-w-0 border-0 bg-transparent py-1 text-sm shadow-none focus-visible:ring-0"
                                placeholder="e.g. EE|Ee"
                            />
                            <button
                                type="button"
                                class="theme-text-dark hover:theme-text shrink-0 cursor-pointer rounded p-1 disabled:opacity-40"
                                :disabled="row.alleles.length <= 1"
                                :aria-label="'Remove locus ' + (locusIdx + 1)"
                                @click="emit('remove-locus', i, locusIdx)"
                            >
                                <X class="size-4" />
                            </button>
                        </div>
                        <button
                            type="button"
                            class="theme-text-dark hover:theme-text theme-border flex h-9 cursor-pointer items-center gap-1 rounded border px-2 text-sm font-medium"
                            aria-label="Add locus"
                            @click="emit('add-locus', i)"
                        >
                            <Plus class="size-3" />
                            Add locus
                        </button>
                    </div>
                </template>
                <span v-else class="theme-text-dark">{{
                    row.alleles?.join(', ') || '—'
                }}</span>
            </td>
            <td v-if="canEdit" class="px-1 py-2">
                <button
                    type="button"
                    class="theme-text-dark hover:theme-text cursor-pointer rounded p-1 disabled:opacity-40"
                    :disabled="localPhenos.length <= 1"
                    :aria-label="'Remove pheno ' + row.name"
                    @click="emit('remove-pheno', i)"
                >
                    <Trash2 class="size-4" />
                </button>
            </td>
        </tr>
    </tbody>
</template>
