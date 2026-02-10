<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';

const singleLocus = 'singleLocus';
const domRec = 'domRec';

type GeneType = typeof singleLocus | typeof domRec;
class Gene {
    constructor(
        public type: GeneType,
        public alleles: string[],
    ) {}
}

// gene type
const dict = {
    black: new Gene(singleLocus, ['E', 'e']),
    agouti: new Gene(singleLocus, ['At', 'A', 'a']),
    silver: new Gene(domRec, ['Z']),
};

const odds = {
    realistic: {
        roll1: 25,
        roll2: 25,
        roll3: 25,
        roll4: 25,
    },
};

console.log(dict);

// pheno overrides

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs"> </AppLayout>
</template>
