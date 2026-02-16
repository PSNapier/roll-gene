<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
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

const sireGenesRaw = ref('Ee Aa nZ');
const damGenesRaw = ref('Ee Aa nZ');

const sireGenes = computed(() => parseGeneString(sireGenesRaw.value));
const damGenes = computed(() => parseGeneString(damGenesRaw.value));

const geneFormatPlaceholder = 'e.g. ee/aa/nZ or ee, aa, nZ';

const rolling = ref(false);

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
                    </div>
                </div>
                <div class="mt-4 flex flex-col items-end gap-2">
                    <button
                        type="button"
                        class="w-fit rounded-md border theme-border theme-bg-dark px-4 py-2 text-sm font-medium theme-text hover:opacity-90 disabled:opacity-50"
                        :disabled="rolling"
                        @click="doRoll"
                    >
                        {{ rolling ? 'Rolling…' : 'Roll' }}
                    </button>
                    <p v-if="props.errors?.sire_genes" class="text-sm text-destructive">
                        {{ props.errors.sire_genes }}
                    </p>
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
