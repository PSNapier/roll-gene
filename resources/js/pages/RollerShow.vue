<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { useSortable } from '@vueuse/integrations/useSortable';
import type { UseSortableOptions } from '@vueuse/integrations/useSortable';
import { GripVertical, Plus, Trash2, TriangleAlert } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import PunnettAllelesEditor from '@/components/PunnettAllelesEditor.vue';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { roll as rollerRoll, update as rollerUpdate } from '@/routes/rollers';

interface EditGeneRow {
    id: number | string;
    name: string;
    entry: GeneEntry;
}

interface GeneEntry {
    oddsType: 'punnett' | 'percentage';
    alleles: string[];
}

interface GeneticsData {
    odds: {
        punnett: Record<string, number>;
        percentage: Record<string, Record<string, number>>;
    };
    dict: Record<string, GeneEntry>;
}

interface BreedingOutcome {
    genotype: string[];
    probability: number;
    percentage: string;
}

interface RollerInfo {
    id: number;
    name: string;
    slug: string;
    is_core: boolean;
}

const props = defineProps<{
    roller: RollerInfo;
    genetics: GeneticsData;
    canEdit?: boolean;
    outcomes?: BreedingOutcome[];
    errors?: Record<string, string>;
}>();

/** Ordered list of genes for editing; synced from props after load/save. */
const editGenes = ref<EditGeneRow[]>([]);
let nextGeneId = 0;

function syncEditGenesFromProps(): void {
    const dict = JSON.parse(JSON.stringify(props.genetics.dict)) as Record<string, GeneEntry>;
    editGenes.value = Object.entries(dict).map(([name, entry]) => ({
        id: nextGeneId++,
        name,
        entry,
    }));
}

onMounted(syncEditGenesFromProps);
watch(
    () => props.genetics.dict,
    () => syncEditGenesFromProps(),
    { deep: true },
);

/** Dict derived from editGenes for validation and save. */
const computedDict = computed(() =>
    Object.fromEntries(editGenes.value.map((g) => [g.name, g.entry])),
);

const genesTbodyRef = ref<HTMLElement | null>(null);
useSortable(genesTbodyRef, editGenes, {
    handle: '.gene-drag-handle',
    animation: 150,
    ghostClass: 'opacity-50',
} as UseSortableOptions);

function setOddsType(rowIndex: number, oddsType: 'punnett' | 'percentage'): void {
    const row = editGenes.value[rowIndex];
    if (!row) return;
    row.entry.oddsType = oddsType;
    if (oddsType === 'percentage' && row.entry.alleles.length !== 1) {
        row.entry.alleles = [row.entry.alleles[0] ?? ''];
    }
}

function addAllele(rowIndex: number): void {
    const row = editGenes.value[rowIndex];
    if (row?.entry.oddsType === 'punnett') {
        row.entry.alleles = [...row.entry.alleles, ''];
    }
}

function removeAllele(rowIndex: number, alleleIndex: number): void {
    const row = editGenes.value[rowIndex];
    if (row?.entry.oddsType === 'punnett' && row.entry.alleles.length > 1) {
        row.entry.alleles = row.entry.alleles.filter((_, i) => i !== alleleIndex);
    }
}

function addGene(): void {
    editGenes.value = [
        ...editGenes.value,
        {
            id: nextGeneId++,
            name: '',
            entry: { oddsType: 'punnett', alleles: ['', ''] },
        },
    ];
}

function removeGene(rowIndex: number): void {
    editGenes.value = editGenes.value.filter((_, i) => i !== rowIndex);
}

const savingDict = ref(false);
function saveDict(): void {
    const dict: Record<string, GeneEntry> = {};
    for (const g of editGenes.value) {
        const name = g.name.trim() || `gene_${g.id}`;
        const e = g.entry;
        if (e.oddsType === 'punnett') {
            e.alleles = e.alleles.filter((a) => a.trim() !== '');
            if (e.alleles.length === 0) e.alleles = [''];
        } else {
            e.alleles = [e.alleles[0]?.trim() ?? ''];
        }
        dict[name] = e;
    }
    savingDict.value = true;
    router.patch(rollerUpdate.url({ roller: props.roller.slug }), { dictionary: dict }, {
        preserveScroll: true,
        onFinish: () => {
            savingDict.value = false;
        },
    });
}

/** Rows for the table: editGenes when canEdit, else from props. */
const genesTableRows = computed(() => {
    if (props.canEdit) {
        return editGenes.value;
    }
    return Object.entries(props.genetics.dict).map(([name, entry]) => ({
        id: name,
        name,
        entry,
    })) as EditGeneRow[];
});

/** Parse gene string: accepts ee/aa/nZ, ee aa nZ, ee,aa,nZ, ee, aa, nZ */
function parseGeneString(raw: string): string[] {
    if (!raw.trim()) return [];
    return raw
        .split(/[/,\s]+/)
        .map((s) => s.trim())
        .filter(Boolean);
}

/** Check if genotype string is two valid alleles (longest-first match, mirrors backend). */
function isValidGenotype(genotype: string, alleles: string[]): boolean {
    const g = genotype.trim();
    if (!g) return false;
    const byLength = [...alleles].sort((a, b) => b.length - a.length);
    for (const first of byLength) {
        if (g.startsWith(first)) {
            const remainder = g.slice(first.length);
            for (const second of byLength) {
                if (remainder === second) return true;
            }
        }
    }
    return false;
}

/** Check if genotype is valid for a percentage gene (dom=AA, rec=nA, none=empty). */
function isValidPercentageGenotype(
    genotype: string,
    alleles: string[],
): boolean {
    const g = genotype.trim();
    if (alleles.length === 0) return false;
    const a = alleles[0];
    return g === '' || g === a + a || g === 'n' + a;
}

const sireGenesRaw = ref('Ee Aa nZ');
const damGenesRaw = ref('Ee Aa nZ');

const sireGenes = computed(() => parseGeneString(sireGenesRaw.value));
const damGenes = computed(() => parseGeneString(damGenesRaw.value));

const currentDict = computed(() =>
    props.canEdit ? computedDict.value : props.genetics.dict,
);
const geneNames = computed(() => Object.keys(currentDict.value));

const punnettGeneNames = computed(() =>
    geneNames.value.filter(
        (name) => currentDict.value[name]?.oddsType === 'punnett',
    ),
);

function assignTokensToGenes(
    tokens: string[],
    dict: Record<string, GeneEntry>,
): { warnings: string[] } {
    const warnings: string[] = [];
    const used = new Set<number>();

    for (const name of punnettGeneNames.value) {
        const alleles = dict[name].alleles;
        let found = -1;
        for (let j = 0; j < tokens.length; j++) {
            if (used.has(j)) continue;
            if (isValidGenotype(tokens[j], alleles)) {
                found = j;
                break;
            }
        }
        if (found >= 0) {
            used.add(found);
        } else {
            warnings.push(
                `Missing value for ${name} (expected two alleles: ${alleles.join(', ')})`,
            );
        }
    }

    const percentageGeneNames = geneNames.value.filter(
        (name) => dict[name].oddsType === 'percentage',
    );
    for (let j = 0; j < tokens.length; j++) {
        if (used.has(j)) continue;
        const recognized = percentageGeneNames.some((name) =>
            isValidPercentageGenotype(tokens[j], dict[name].alleles),
        );
        if (!recognized) {
            warnings.push(`Unrecognized genotype: "${tokens[j]}"`);
        }
    }

    return { warnings };
}

const validationWarnings = computed(() => {
    const dict = currentDict.value;
    const sire = sireGenes.value;
    const dam = damGenes.value;

    const sireResult = assignTokensToGenes(sire, dict);
    const damResult = assignTokensToGenes(dam, dict);

    return { sire: sireResult.warnings, dam: damResult.warnings };
});

const hasValidationWarnings = computed(
    () =>
        validationWarnings.value.sire.length > 0 ||
        validationWarnings.value.dam.length > 0,
);

const geneFormatPlaceholder = 'e.g. ee/aa/nZ or ee, aa, nZ';

const rolling = ref(false);
const canRoll = computed(() => !rolling.value && !hasValidationWarnings.value);

function doRoll(): void {
    rolling.value = true;
    router.post(
        rollerRoll.url({ roller: props.roller.slug }),
        {
            sire_genes: sireGenesRaw.value,
            dam_genes: damGenesRaw.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                rolling.value = false;
            },
        },
    );
}
</script>

<template>
    <Head :title="roller.name" />

    <AppLayout>
        <div class="flex flex-col gap-6 p-6">
            <h1 class="theme-text text-2xl font-semibold">
                {{ roller.name }}
                <span v-if="roller.is_core" class="theme-text-dark text-base"
                    >(Core)</span
                >
            </h1>

            <section class="theme-border rounded-lg border bg-transparent p-4">
                <h2 class="theme-text-dark mb-3 text-lg font-medium">
                    Parent genes
                </h2>
                <p class="theme-text-dark mb-3 text-sm">
                    One value per gene (same order as dictionary). Use slashes,
                    spaces, or commas: ee/aa/nZ, ee aa nZ, or ee, aa, nZ.
                </p>
                <div
                    class="flex flex-col gap-4 max-[1279px]:flex-col md:grid md:grid-cols-2"
                >
                    <div class="flex flex-col gap-1.5">
                        <label
                            for="sire-genes"
                            class="theme-text text-sm font-medium"
                            >Sire</label
                        >
                        <Input
                            id="sire-genes"
                            v-model="sireGenesRaw"
                            type="text"
                            class="theme-text"
                            :placeholder="geneFormatPlaceholder"
                        />
                        <span
                            v-if="sireGenes.length"
                            class="theme-text-dark text-xs"
                            >Parsed: {{ sireGenes.join(', ') }}</span
                        >
                        <div
                            v-if="validationWarnings.sire.length"
                            class="mt-2 flex flex-col gap-1 rounded-md border border-destructive/30 bg-destructive/5 p-2 text-sm text-destructive"
                        >
                            <div class="flex items-center gap-1.5 font-medium">
                                <TriangleAlert class="size-4 shrink-0" />
                                <span>Warning:</span>
                            </div>
                            <ul class="list-inside list-disc">
                                <li
                                    v-for="(
                                        msg, idx
                                    ) in validationWarnings.sire"
                                    :key="idx"
                                >
                                    {{ msg }}
                                </li>
                            </ul>
                        </div>
                        <p
                            v-if="props.errors?.sire_genes"
                            class="mt-1 text-sm text-destructive"
                        >
                            {{ props.errors.sire_genes }}
                        </p>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label
                            for="dam-genes"
                            class="theme-text text-sm font-medium"
                            >Dam</label
                        >
                        <Input
                            id="dam-genes"
                            v-model="damGenesRaw"
                            type="text"
                            class="theme-text"
                            :placeholder="geneFormatPlaceholder"
                        />
                        <span
                            v-if="damGenes.length"
                            class="theme-text-dark text-xs"
                            >Parsed: {{ damGenes.join(', ') }}</span
                        >
                        <div
                            v-if="validationWarnings.dam.length"
                            class="mt-2 flex flex-col gap-1 rounded-md border border-destructive/30 bg-destructive/5 p-2 text-sm text-destructive"
                        >
                            <div class="flex items-center gap-1.5 font-medium">
                                <TriangleAlert class="size-4 shrink-0" />
                                <span>Warning:</span>
                            </div>
                            <ul class="list-inside list-disc">
                                <li
                                    v-for="(msg, idx) in validationWarnings.dam"
                                    :key="idx"
                                >
                                    {{ msg }}
                                </li>
                            </ul>
                        </div>
                        <p
                            v-if="props.errors?.dam_genes"
                            class="mt-1 text-sm text-destructive"
                        >
                            {{ props.errors.dam_genes }}
                        </p>
                    </div>
                </div>
                <div class="mt-4 flex flex-col items-end gap-2">
                    <button
                        type="button"
                        class="theme-border theme-bg-dark theme-text w-fit rounded-md border px-4 py-2 text-sm font-medium hover:opacity-90 disabled:opacity-50"
                        :disabled="!canRoll"
                        @click="doRoll"
                    >
                        {{ rolling ? 'Rolling…' : 'Roll' }}
                    </button>
                </div>
            </section>

            <section
                v-if="props.outcomes?.length"
                class="theme-border rounded-lg border bg-transparent p-4"
            >
                <h2 class="theme-text-dark mb-3 text-lg font-medium">
                    Possible offspring
                </h2>
                <p class="theme-text-dark mb-3 text-sm">
                    All combinations and their probabilities (punnett genes
                    only).
                </p>
                <table class="w-max text-sm">
                    <thead>
                        <tr class="theme-text-dark theme-border border-b">
                            <th
                                class="theme-text px-3 py-2 text-left font-medium"
                            >
                                Genotype
                            </th>
                            <th
                                class="theme-text px-3 py-2 text-left font-medium"
                            >
                                Probability
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(row, i) in props.outcomes"
                            :key="i"
                            class="theme-text theme-border border-b last:border-b-0 even:bg-muted"
                        >
                            <td
                                class="theme-text px-3 py-2 font-medium whitespace-nowrap"
                            >
                                {{ row.genotype.join(' ') }}
                            </td>
                            <td class="theme-text-dark px-3 py-2 text-left">
                                {{ row.percentage }}%
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="theme-border rounded-lg border bg-transparent p-4">
                <h2 class="theme-text-dark mb-3 text-lg font-medium">Genes</h2>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="theme-text-dark theme-border border-b">
                            <th
                                v-if="props.canEdit"
                                class="theme-text w-8 px-1 py-2"
                                aria-label="Drag to reorder"
                            ></th>
                            <th
                                class="theme-text px-3 py-2 text-left font-medium"
                            >
                                Gene
                            </th>
                            <th
                                class="theme-text px-3 py-2 text-left font-medium"
                            >
                                Odds
                            </th>
                            <th
                                class="theme-text px-3 py-2 text-left font-medium"
                            >
                                Alleles
                            </th>
                            <th
                                v-if="props.canEdit"
                                class="theme-text w-8 px-1 py-2"
                                aria-label="Remove gene"
                            ></th>
                        </tr>
                    </thead>
                    <tbody ref="genesTbodyRef">
                        <tr
                            v-for="(row, i) in genesTableRows"
                            :key="row.id"
                            class="theme-text theme-border border-b last:border-b-0 even:bg-muted"
                        >
                            <td v-if="props.canEdit" class="px-1 py-2">
                                <span
                                    class="gene-drag-handle theme-text-dark cursor-grab touch-none p-1 active:cursor-grabbing"
                                    aria-label="Drag to reorder gene"
                                >
                                    <GripVertical class="size-4" />
                                </span>
                            </td>
                            <td class="px-3 py-2 font-medium">
                                <Input
                                    v-if="props.canEdit"
                                    v-model="row.name"
                                    class="theme-text h-8 w-28 text-sm capitalize"
                                    placeholder="e.g. black"
                                />
                                <span v-else class="capitalize">{{
                                    row.name
                                }}</span>
                            </td>
                            <td class="px-3 py-2">
                                <select
                                    v-if="props.canEdit"
                                    v-model="row.entry.oddsType"
                                    class="theme-border theme-bg theme-text h-9 rounded border px-2 text-sm"
                                    @change="
                                        setOddsType(i, row.entry.oddsType)
                                    "
                                >
                                    <option value="punnett">Punnett</option>
                                    <option value="percentage">Percent</option>
                                </select>
                                <span v-else class="theme-text-dark capitalize">{{
                                    row.entry.oddsType === 'percentage' ? 'percent' : row.entry.oddsType
                                }}</span>
                            </td>
                            <td class="px-3 py-2">
                                <template v-if="props.canEdit">
                                    <PunnettAllelesEditor
                                        v-if="row.entry.oddsType === 'punnett'"
                                        :gene-name="row.name"
                                        :alleles="row.entry.alleles"
                                        @update:alleles="
                                            (val) => (row.entry.alleles = val)
                                        "
                                        @remove="(idx) => removeAllele(i, idx)"
                                    >
                                        <button
                                            type="button"
                                            class="theme-text-dark hover:theme-text theme-border flex h-9 cursor-pointer items-center gap-1 rounded border px-2 text-xs"
                                            aria-label="Add allele"
                                            @click="addAllele(i)"
                                        >
                                            <Plus class="size-3" />
                                            Add
                                        </button>
                                    </PunnettAllelesEditor>
                                    <div
                                        v-else
                                        class="flex items-center gap-2"
                                    >
                                        <div
                                            class="theme-border flex h-9 w-16 min-w-0 items-center rounded border bg-transparent px-2"
                                        >
                                            <Input
                                                v-model="row.entry.alleles[0]"
                                                class="theme-text w-full min-w-0 border-0 bg-transparent py-1 text-sm shadow-none focus-visible:ring-0"
                                                placeholder="e.g. Z"
                                            />
                                        </div>
                                        <span
                                            v-if="row.entry.alleles[0]?.trim()"
                                            class="theme-text-dark text-sm"
                                        >
                                            {{ row.entry.alleles[0].trim() }}{{ row.entry.alleles[0].trim() }}, n{{ row.entry.alleles[0].trim() }}
                                        </span>
                                        <span
                                            v-else
                                            class="theme-text-dark text-sm"
                                        >
                                            —
                                        </span>
                                    </div>
                                </template>
                                <span v-else class="theme-text-dark">{{
                                    row.entry.alleles.join(', ')
                                }}</span>
                            </td>
                            <td v-if="props.canEdit" class="px-1 py-2">
                                <button
                                    type="button"
                                    class="theme-text-dark hover:theme-text cursor-pointer rounded p-1 disabled:opacity-40"
                                    :disabled="genesTableRows.length <= 1"
                                    :aria-label="'Remove gene ' + row.name"
                                    @click="removeGene(i)"
                                >
                                    <Trash2 class="size-4" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div
                    v-if="props.canEdit"
                    class="mt-3 flex flex-wrap items-center justify-end gap-2"
                >
                    <button
                        type="button"
                        class="theme-text-dark hover:theme-text theme-border flex cursor-pointer items-center gap-1 rounded-md border px-4 py-2 text-sm font-medium"
                        aria-label="Add gene"
                        @click="addGene"
                    >
                        <Plus class="size-3" />
                        Add gene
                    </button>
                    <button
                        type="button"
                        class="theme-border theme-bg-dark theme-text cursor-pointer rounded-md border px-4 py-2 text-sm font-medium hover:opacity-90 disabled:opacity-50"
                        :disabled="savingDict"
                        @click="saveDict"
                    >
                        {{ savingDict ? 'Saving…' : 'Save genes' }}
                    </button>
                </div>
            </section>

            <section class="theme-border rounded-lg border bg-transparent p-4">
                <h2 class="theme-text-dark mb-3 text-lg font-medium">
                    Roll Odds
                </h2>
                <pre class="theme-text overflow-x-auto text-sm">{{
                    JSON.stringify(props.genetics.odds, null, 2)
                }}</pre>
            </section>
        </div>
    </AppLayout>
</template>
