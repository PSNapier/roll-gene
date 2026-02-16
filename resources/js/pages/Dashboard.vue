<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { TriangleAlert } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { roll } from '@/routes/dashboard';

interface GeneEntry {
    oddsType: 'base' | 'percentage';
    alleles: string[];
}

interface GeneticsData {
    odds: {
        base: Record<string, number>;
        percentage: Record<string, Record<string, number>>;
    };
    dict: Record<string, GeneEntry>;
}

interface BreedingOutcome {
    genotype: string[];
    probability: number;
    percentage: string;
}

const props = defineProps<{
    genetics: GeneticsData;
    outcomes?: BreedingOutcome[];
    errors?: Record<string, string>;
}>();

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

const sireGenesRaw = ref('Ee Aa nZ');
const damGenesRaw = ref('Ee Aa nZ');

const sireGenes = computed(() => parseGeneString(sireGenesRaw.value));
const damGenes = computed(() => parseGeneString(damGenesRaw.value));

const geneNames = computed(() => Object.keys(props.genetics.dict));

const baseGeneNames = computed(() =>
    geneNames.value.filter((name) => props.genetics.dict[name].oddsType === 'base'),
);

function assignTokensToGenes(
    tokens: string[],
    dict: Record<string, GeneEntry>,
): { warnings: string[] } {
    const warnings: string[] = [];
    const used = new Set<number>();

    for (const name of baseGeneNames.value) {
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
            warnings.push(`Missing value for ${name} (expected two alleles: ${alleles.join(', ')})`);
        }
    }

    for (let j = 0; j < tokens.length; j++) {
        if (!used.has(j)) {
            warnings.push(`Unrecognized genotype: "${tokens[j]}"`);
        }
    }

    return { warnings };
}

const validationWarnings = computed(() => {
    const dict = props.genetics.dict;
    const sire = sireGenes.value;
    const dam = damGenes.value;

    const sireResult = assignTokensToGenes(sire, dict);
    const damResult = assignTokensToGenes(dam, dict);

    return { sire: sireResult.warnings, dam: damResult.warnings };
});

const hasValidationWarnings = computed(
    () => validationWarnings.value.sire.length > 0 || validationWarnings.value.dam.length > 0,
);

const geneFormatPlaceholder = 'e.g. ee/aa/nZ or ee, aa, nZ';

const rolling = ref(false);
const canRoll = computed(() => !rolling.value && !hasValidationWarnings.value);

function doRoll(): void {
    rolling.value = true;
    router.post(roll.url(), {
        sire_genes: sireGenesRaw.value,
        dam_genes: damGenesRaw.value,
    }, {
        preserveState: true,
        onFinish: () => { rolling.value = false; },
    });
}
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout>
        <div class="flex flex-col gap-6 p-6">
            <h1 class="text-2xl font-semibold theme-text">Base Genetics Dictionary</h1>

            <section class="rounded-lg theme-bg-dark border theme-border p-4">
                <h2 class="text-lg font-medium theme-text-dark mb-3">Parent genes</h2>
                <p class="text-sm theme-text-dark mb-3">
                    One value per gene (same order as dictionary). Use slashes, spaces, or commas: ee/aa/nZ, ee aa nZ, or ee, aa, nZ.
                </p>
                <div class="flex flex-col gap-4 max-[1279px]:flex-col md:grid md:grid-cols-2">
                    <div class="flex flex-col gap-1.5">
                        <label for="sire-genes" class="text-sm font-medium theme-text">Sire</label>
                        <Input
                            id="sire-genes"
                            v-model="sireGenesRaw"
                            type="text"
                            class="theme-text"
                            :placeholder="geneFormatPlaceholder"
                        />
                        <span v-if="sireGenes.length" class="text-xs theme-text-dark">Parsed: {{ sireGenes.join(', ') }}</span>
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
                                    v-for="(msg, idx) in validationWarnings.sire"
                                    :key="idx"
                                >
                                    {{ msg }}
                                </li>
                            </ul>
                        </div>
                        <p v-if="props.errors?.sire_genes" class="mt-1 text-sm text-destructive">
                            {{ props.errors.sire_genes }}
                        </p>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label for="dam-genes" class="text-sm font-medium theme-text">Dam</label>
                        <Input
                            id="dam-genes"
                            v-model="damGenesRaw"
                            type="text"
                            class="theme-text"
                            :placeholder="geneFormatPlaceholder"
                        />
                        <span v-if="damGenes.length" class="text-xs theme-text-dark">Parsed: {{ damGenes.join(', ') }}</span>
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
                        <p v-if="props.errors?.dam_genes" class="mt-1 text-sm text-destructive">
                            {{ props.errors.dam_genes }}
                        </p>
                    </div>
                </div>
                <div class="mt-4 flex flex-col items-end gap-2">
                    <button
                        type="button"
                        class="w-fit rounded-md border theme-border theme-bg-dark px-4 py-2 text-sm font-medium theme-text hover:opacity-90 disabled:opacity-50"
                        :disabled="!canRoll"
                        @click="doRoll"
                    >
                        {{ rolling ? 'Rolling…' : 'Roll' }}
                    </button>
                </div>
            </section>

            <section
                v-if="props.outcomes?.length"
                class="rounded-lg theme-bg-dark border theme-border p-4"
            >
                <h2 class="text-lg font-medium theme-text-dark mb-3">Possible offspring</h2>
                <p class="text-sm theme-text-dark mb-3">
                    All combinations and their probabilities (base genes only).
                </p>
                <table class="w-max text-sm">
                    <thead>
                        <tr class="theme-text-dark border-b theme-border">
                            <th class="px-3 py-2 text-left font-medium theme-text">Genotype</th>
                            <th class="px-3 py-2 text-left font-medium theme-text">Probability</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(row, i) in props.outcomes"
                            :key="i"
                            class="even:bg-muted theme-text border-b theme-border last:border-b-0"
                        >
                            <td class="whitespace-nowrap px-3 py-2 font-medium theme-text">{{ row.genotype.join(' ') }}</td>
                            <td class="px-3 py-2 text-left theme-text-dark">{{ row.percentage }}%</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="rounded-lg theme-bg-dark border theme-border p-4">
                <h2 class="text-lg font-medium theme-text-dark mb-3">Genes</h2>
                <ul class="flex flex-col gap-2">
                    <li
                        v-for="(gene, name) in props.genetics.dict"
                        :key="name"
                        class="flex items-center gap-3 text-sm"
                    >
                        <span class="font-medium theme-text capitalize">{{ name }}</span>
                        <span class="theme-text-dark">({{ gene.oddsType }})</span>
                        <span class="theme-text-dark">{{ gene.alleles.join(', ') }}</span>
                    </li>
                </ul>
            </section>

            <section class="rounded-lg theme-bg-dark border theme-border p-4">
                <h2 class="text-lg font-medium theme-text-dark mb-3">Base Odds</h2>
                <pre class="text-sm theme-text overflow-x-auto">{{ JSON.stringify(props.genetics.odds, null, 2) }}</pre>
            </section>
        </div>
    </AppLayout>
</template>
