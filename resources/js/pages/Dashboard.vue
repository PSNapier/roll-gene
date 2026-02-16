<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';

const odds = {
    base: {
        roll1: 25,
        roll2: 25,
        roll3: 25,
        roll4: 25,
    },
    percentage: {
        domXdom: { dom: 100 },
        domXrec: { dom: 100 },
        domXnone: { rec: 50 },
        recXrec: { dom: 50, rec: 50 },
        recXnone: { rec: 50 },
    },
};

const base = odds.base;
const percentage = odds.percentage;

type GeneType = typeof base | typeof percentage;
class Gene {
    constructor(
        public odds: GeneType,
        public alleles: string[],
    ) {}
}

const dict = {
    black: new Gene(base, ['E', 'e']),
    agouti: new Gene(base, ['At', 'A', 'a']),
    silver: new Gene(percentage, ['Z']),
};

console.log(dict);
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout></AppLayout>
</template>
