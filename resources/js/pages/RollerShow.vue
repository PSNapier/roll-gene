<script setup lang="ts">
import type { RequestPayload } from '@inertiajs/core';
import { Head, router } from '@inertiajs/vue3';
import { useSortable } from '@vueuse/integrations/useSortable';
import type { UseSortableOptions } from '@vueuse/integrations/useSortable';
import { GripVertical, Plus, Trash2, TriangleAlert, X } from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import PunnettAllelesEditor from '@/components/PunnettAllelesEditor.vue';
import SectionPhenoTable from '@/components/SectionPhenoTable.vue';
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
    oddsDict: {
        punnett: Record<string, number>;
        percentage: Record<string, Record<string, number>>;
    };
    genesDict: Record<string, GeneEntry>;
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

interface PhenoEntry {
    name: string;
    alleles: string[];
    locus_first?: boolean;
}

/** Section (grouping) of phenos; first-match within section = that grouping's result. */
interface PhenoSection {
    name: string;
    match_mode?: string;
    phenos: PhenoEntry[];
}

const props = defineProps<{
    roller: RollerInfo;
    genetics: GeneticsData;
    phenoSections?: PhenoSection[];
    canEdit?: boolean;
    outcomes?: BreedingOutcome[];
    errors?: Record<string, string>;
}>();

/** Ordered list of genes for editing; synced from props after load/save. */
const editGenes = ref<EditGeneRow[]>([]);
let nextGeneId = 0;

function syncEditGenesFromProps(): void {
    const genesDict = JSON.parse(
        JSON.stringify(props.genetics.genesDict),
    ) as Record<string, GeneEntry>;
    editGenes.value = Object.entries(genesDict).map(([name, entry]) => ({
        id: nextGeneId++,
        name,
        entry,
    }));
}

onMounted(syncEditGenesFromProps);
watch(
    () => props.genetics.genesDict,
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

function setOddsType(
    rowIndex: number,
    oddsType: 'punnett' | 'percentage',
): void {
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
        row.entry.alleles = row.entry.alleles.filter(
            (_, i) => i !== alleleIndex,
        );
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

/** Pheno Reader: sections (groupings); dominance is first-match within each section. */
interface EditPhenoRow extends PhenoEntry {
    id: number | string;
    locusFirst: boolean;
}
interface EditPhenoSection {
    id: number | string;
    name: string;
    match_mode: string;
    phenos: EditPhenoRow[];
}
const editSections = ref<EditPhenoSection[]>([]);
let nextPhenoId = 0;
let nextSectionId = 0;

const PHENO_MODE_OVERRIDE = 'override';
const PHENO_MODE_ALL_MATCHES = 'all_matches';

function syncEditPhenosFromProps(): void {
    const sections = (props.phenoSections ?? []) as PhenoSection[];
    editSections.value = sections.map((sec) => ({
        id: nextSectionId++,
        name: sec.name ?? 'Grouping',
        match_mode:
            sec.match_mode === PHENO_MODE_ALL_MATCHES
                ? PHENO_MODE_ALL_MATCHES
                : PHENO_MODE_OVERRIDE,
        phenos: (sec.phenos ?? []).map((p) => ({
            id: nextPhenoId++,
            name: p.name,
            alleles: [...(p.alleles ?? [])],
            locusFirst: p.locus_first ?? false,
        })),
    }));
}

onMounted(syncEditPhenosFromProps);
watch(
    () => props.phenoSections,
    () => syncEditPhenosFromProps(),
    { deep: true },
);

function addSection(): void {
    editSections.value = [
        ...editSections.value,
        {
            id: nextSectionId++,
            name: 'Grouping',
            match_mode: PHENO_MODE_OVERRIDE,
            phenos: [
                {
                    id: nextPhenoId++,
                    name: '',
                    alleles: [''],
                    locusFirst: false,
                },
            ],
        },
    ];
}

function removeSection(sectionIndex: number): void {
    if (editSections.value.length <= 1) return;
    editSections.value = editSections.value.filter(
        (_, i) => i !== sectionIndex,
    );
}

function addPheno(sectionIndex: number): void {
    const sec = editSections.value[sectionIndex];
    if (sec)
        sec.phenos = [
            ...sec.phenos,
            {
                id: nextPhenoId++,
                name: '',
                alleles: [''],
                locusFirst: false,
            },
        ];
}

function removePheno(sectionIndex: number, rowIndex: number): void {
    const sec = editSections.value[sectionIndex];
    if (sec && sec.phenos.length > 1) {
        sec.phenos = sec.phenos.filter((_, i) => i !== rowIndex);
    }
}

function addPhenoLocus(sectionIndex: number, rowIndex: number): void {
    const row = editSections.value[sectionIndex]?.phenos[rowIndex];
    if (row) row.alleles = [...row.alleles, ''];
}

function removePhenoLocus(
    sectionIndex: number,
    rowIndex: number,
    alleleIndex: number,
): void {
    const row = editSections.value[sectionIndex]?.phenos[rowIndex];
    if (row && row.alleles.length > 1) {
        row.alleles = row.alleles.filter((_, i) => i !== alleleIndex);
    }
}

function updateSectionPhenos(
    sectionIndex: number,
    phenos: EditPhenoRow[],
): void {
    const sec = editSections.value[sectionIndex];
    if (sec) sec.phenos = phenos;
}

const savingPhenos = ref(false);
function savePhenos(): void {
    const phenoDict = editSections.value.map((sec) => ({
        name: sec.name.trim() || 'Grouping',
        match_mode: sec.match_mode,
        phenos: sec.phenos.map((p) => {
            const alleles = p.alleles.map((a) => a.trim()).filter(Boolean);
            return {
                name: p.name.trim() || `pheno_${p.id}`,
                alleles: alleles.length ? alleles : [''],
                locus_first: p.locusFirst,
            };
        }),
    }));
    savingPhenos.value = true;
    router.patch(
        rollerUpdate.url({ roller: props.roller.slug }),
        { phenoDict } as unknown as RequestPayload,
        {
            preserveScroll: true,
            onFinish: () => {
                savingPhenos.value = false;
            },
        },
    );
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
    router.patch(
        rollerUpdate.url({ roller: props.roller.slug }),
        { genesDict: dict } as unknown as RequestPayload,
        {
            preserveScroll: true,
            onFinish: () => {
                savingDict.value = false;
            },
        },
    );
}

/** Rows for the table: editGenes when canEdit, else from props. */
const genesTableRows = computed(() => {
    if (props.canEdit) {
        return editGenes.value;
    }
    return Object.entries(props.genetics.genesDict).map(([name, entry]) => ({
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
    props.canEdit ? computedDict.value : props.genetics.genesDict,
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

/**
 * Per grouping (section): Override = first matching pheno wins; All Matches = all matching phenos.
 * Returns display strings in section order. When namesOnly is false, each string is pheno name with
 * locus (genotype) prepended or appended per pheno's locus_first. When namesOnly is true, returns
 * only pheno names (for use where genotype is shown separately). Match is case-sensitive; use '|'
 * for OR (e.g. 'EE|Ee').
 * When a pheno has fewer locus specs than genotype length, each spec is matched against any gene
 * (distinct indices) so e.g. Cream with ["CrCr|nCr"] matches the Cream gene regardless of position.
 */
function getMatchingPhenos(genotype: string[], namesOnly = false): string[] {
    const sections = props.phenoSections ?? [];
    const allowedSetForLocus = (locusAlleles: string) =>
        new Set(
            (locusAlleles ?? '')
                .split('|')
                .map((part) => part.trim())
                .filter(Boolean),
        );
    const result: string[] = [];
    for (const sec of sections) {
        const allMatches = sec.match_mode === PHENO_MODE_ALL_MATCHES;
        for (const p of sec.phenos ?? []) {
            const alleleSpecs = p.alleles ?? [];
            if (alleleSpecs.length === 0) continue;
            if (genotype.length < alleleSpecs.length) continue;

            let matched: boolean;
            let matchedLocusPart: string;
            if (alleleSpecs.length === genotype.length) {
                matched = alleleSpecs.every((spec, idx) => {
                    const set = allowedSetForLocus(spec);
                    return (
                        set.size > 0 && set.has((genotype[idx] ?? '').trim())
                    );
                });
                matchedLocusPart = genotype
                    .slice(0, alleleSpecs.length)
                    .join(' ')
                    .trim();
            } else {
                const usedIndices = new Set<number>();
                const matchedTokens: string[] = [];
                matched = alleleSpecs.every((spec) => {
                    const set = allowedSetForLocus(spec);
                    if (set.size === 0) return true;
                    for (let i = 0; i < genotype.length; i++) {
                        if (usedIndices.has(i)) continue;
                        const token = (genotype[i] ?? '').trim();
                        if (set.has(token)) {
                            usedIndices.add(i);
                            matchedTokens.push(token);
                            return true;
                        }
                    }
                    return false;
                });
                matchedLocusPart = matched ? matchedTokens.join(' ') : '';
            }

            if (matched) {
                if (namesOnly) {
                    result.push(p.name ?? '');
                } else {
                    const phenoName = p.name ?? '';
                    const display =
                        p.locus_first && matchedLocusPart
                            ? `${matchedLocusPart} ${phenoName}`
                            : matchedLocusPart
                              ? `${phenoName} ${matchedLocusPart}`
                              : phenoName;
                    result.push(display);
                }
                if (!allMatches) break;
            }
        }
    }
    return result;
}

const matchingPhenosByOutcome = computed(() =>
    (props.outcomes ?? []).map((row) => getMatchingPhenos(row.genotype, true)),
);

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
                    One value per gene (same order as genesDict). Use slashes,
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
                                <div>{{ row.genotype.join(' ') }}</div>
                                <div class="theme-text-dark mt-0.5 text-xs">
                                    {{
                                        matchingPhenosByOutcome[i]?.length
                                            ? matchingPhenosByOutcome[i].join(
                                                  ', ',
                                              )
                                            : '—'
                                    }}
                                </div>
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
                                    @change="setOddsType(i, row.entry.oddsType)"
                                >
                                    <option value="punnett">Punnett</option>
                                    <option value="percentage">Percent</option>
                                </select>
                                <span
                                    v-else
                                    class="theme-text-dark capitalize"
                                    >{{
                                        row.entry.oddsType === 'percentage'
                                            ? 'percent'
                                            : row.entry.oddsType
                                    }}</span
                                >
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
                                    <div v-else class="flex items-center gap-2">
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
                                            {{ row.entry.alleles[0].trim()
                                            }}{{ row.entry.alleles[0].trim() }},
                                            n{{ row.entry.alleles[0].trim() }}
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
                    Pheno Reader
                </h2>
                <p class="theme-text-dark mb-3 text-sm">
                    Groupings are table sections; order within each grouping =
                    dominance (first match wins). A genotype matches a pheno if
                    every token is in that pheno's list; one result per
                    grouping. Use
                    <code class="theme-border rounded px-1">|</code> in a locus
                    for OR (e.g.
                    <code class="theme-border rounded px-1">EE|Ee</code>).
                </p>
                <template
                    v-for="(sec, sectionIndex) in canEdit
                        ? editSections
                        : (phenoSections ?? [])"
                    :key="canEdit ? (sec as EditPhenoSection).id : sectionIndex"
                >
                    <div
                        class="theme-border relative mb-4 rounded-lg border p-4 last:mb-0"
                    >
                        <button
                            v-if="canEdit && editSections.length > 1"
                            type="button"
                            class="theme-text-dark hover:theme-text absolute top-2 right-2 cursor-pointer rounded p-1"
                            aria-label="Remove grouping"
                            @click="removeSection(sectionIndex)"
                        >
                            <X class="size-4" />
                        </button>
                        <div
                            class="theme-text-dark mb-2 flex flex-wrap items-center gap-2 pr-6"
                        >
                            <Input
                                v-if="canEdit"
                                v-model="(sec as EditPhenoSection).name"
                                class="theme-text h-8 w-28 text-sm capitalize"
                                placeholder="e.g. Grouping"
                            />
                            <Input
                                v-else
                                :model-value="(sec as PhenoSection).name"
                                class="theme-text h-8 w-28 text-sm capitalize"
                                readonly
                            />
                            <select
                                v-if="canEdit"
                                v-model="(sec as EditPhenoSection).match_mode"
                                class="theme-text theme-border h-8 cursor-pointer rounded-md border bg-transparent px-2 text-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-ring/50"
                                aria-label="Grouping mode"
                            >
                                <option :value="PHENO_MODE_OVERRIDE">
                                    Override
                                </option>
                                <option :value="PHENO_MODE_ALL_MATCHES">
                                    All Matches
                                </option>
                            </select>
                            <span v-else class="theme-text text-sm">
                                {{
                                    (sec as PhenoSection).match_mode ===
                                    PHENO_MODE_ALL_MATCHES
                                        ? 'All Matches'
                                        : 'Override'
                                }}
                            </span>
                        </div>
                        <table class="w-full table-fixed text-sm">
                            <thead>
                                <tr
                                    class="theme-text-dark theme-border border-b"
                                >
                                    <th
                                        v-if="canEdit"
                                        class="theme-text w-8 px-1 py-2"
                                        aria-label="Drag to reorder"
                                    ></th>
                                    <th
                                        class="theme-text w-28 px-3 py-2 text-left font-medium"
                                    >
                                        Pheno name
                                    </th>
                                    <th
                                        v-if="canEdit"
                                        class="theme-text w-28 px-2 py-2 text-left font-medium"
                                    >
                                        Order
                                    </th>
                                    <th
                                        class="theme-text w-96 px-3 py-2 text-left font-medium"
                                    >
                                        Locus values
                                    </th>
                                    <th
                                        v-if="canEdit"
                                        class="theme-text w-8 px-1 py-2"
                                        aria-label="Remove pheno"
                                    ></th>
                                </tr>
                            </thead>
                            <SectionPhenoTable
                                v-if="canEdit"
                                :section="sec as EditPhenoSection"
                                :section-index="sectionIndex"
                                :can-edit="true"
                                @update:phenos="
                                    updateSectionPhenos(
                                        sectionIndex,
                                        $event as EditPhenoRow[],
                                    )
                                "
                                @remove-pheno="
                                    removePheno(sectionIndex, $event)
                                "
                                @add-locus="addPhenoLocus(sectionIndex, $event)"
                                @remove-locus="
                                    (ri, ai) =>
                                        removePhenoLocus(sectionIndex, ri, ai)
                                "
                            />
                            <tbody v-else>
                                <tr
                                    v-for="(row, i) in (sec as PhenoSection)
                                        .phenos"
                                    :key="i"
                                    class="theme-text theme-border border-b last:border-b-0 even:bg-muted"
                                >
                                    <td
                                        class="px-3 py-2 font-medium capitalize"
                                    >
                                        {{ row.name }}
                                    </td>
                                    <td class="theme-text-dark px-3 py-2">
                                        {{ row.alleles?.join(', ') || '—' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div v-if="canEdit" class="mt-2 flex justify-end">
                            <button
                                type="button"
                                class="theme-text-dark hover:theme-text theme-border flex cursor-pointer items-center gap-1 rounded-md border px-4 py-2 text-sm font-medium"
                                aria-label="Add pheno to this grouping"
                                @click="addPheno(sectionIndex)"
                            >
                                <Plus class="size-3" />
                                Add pheno
                            </button>
                        </div>
                    </div>
                </template>
                <div
                    v-if="canEdit"
                    class="mt-3 flex flex-wrap items-center justify-end gap-2"
                >
                    <button
                        type="button"
                        class="theme-text-dark hover:theme-text theme-border flex cursor-pointer items-center gap-1 rounded-md border px-4 py-2 text-sm font-medium"
                        aria-label="Add grouping"
                        @click="addSection"
                    >
                        <Plus class="size-3" />
                        Add grouping
                    </button>
                    <button
                        type="button"
                        class="theme-border theme-bg-dark theme-text cursor-pointer rounded-md border px-4 py-2 text-sm font-medium hover:opacity-90 disabled:opacity-50"
                        :disabled="savingPhenos"
                        @click="savePhenos"
                    >
                        {{ savingPhenos ? 'Saving…' : 'Save phenos' }}
                    </button>
                </div>
            </section>

            <section class="theme-border rounded-lg border bg-transparent p-4">
                <h2 class="theme-text-dark mb-3 text-lg font-medium">
                    Roll Odds
                </h2>
                <pre class="theme-text overflow-x-auto text-sm">{{
                    JSON.stringify(props.genetics.oddsDict, null, 2)
                }}</pre>
            </section>
        </div>
    </AppLayout>
</template>
