<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { home, login, register } from '@/routes';

const tiers = [
    {
        name: 'Hobbyist',
        price: 'Free',
        priceNote: null,
        features: [
            'Access to all public user-created rollers',
            'Access to private rollers you are added to',
            '1 custom roller (core odds only, no custom odds and no pheno-overrides)',
        ],
        cta: 'Get started',
        ctaHref: () => register(),
        highlighted: false,
    },
    {
        name: 'Premium',
        price: '$9',
        priceNote: '/month',
        features: [
            'Everything in Hobbyist',
            '3 custom rollers',
            'Custom odds and pheno-overrides',
        ],
        cta: 'Subscribe',
        ctaHref: () => register(),
        highlighted: true,
    },
    {
        name: 'Super supporter',
        price: '$33',
        priceNote: '/month',
        features: [
            'Everything in Premium',
            'Unlimited rollers',
            'Custom odds and pheno-overrides',
        ],
        cta: 'Subscribe',
        ctaHref: () => register(),
        highlighted: false,
    },
];
</script>

<template>
    <Head title="Join — Pricing" />

    <div
        class="theme-bg theme-text flex min-h-screen flex-col"
    >
        <header class="theme-border flex w-full justify-end border-b p-4">
            <nav class="flex items-center gap-4">
                <Link
                    :href="home()"
                    class="theme-text-dark text-sm hover:opacity-90"
                >
                    Home
                </Link>
                <Link
                    :href="login()"
                    class="theme-text-dark text-sm hover:opacity-90"
                >
                    Log in
                </Link>
                <Link
                    :href="register()"
                    class="bg-primary text-primary-foreground rounded-md px-4 py-2 text-sm font-medium hover:opacity-90"
                >
                    Register
                </Link>
            </nav>
        </header>

        <main class="mx-auto flex w-full max-w-5xl flex-1 flex-col items-center px-4 py-12">
            <h1 class="theme-text mb-2 text-3xl font-semibold">
                Choose your plan
            </h1>
            <p class="theme-text-dark mb-10 text-center text-sm">
                Start free, upgrade when you need more custom rollers.
            </p>

            <div
                class="grid w-full grid-cols-1 gap-6 sm:grid-cols-3 xl:max-w-5xl"
            >
                <article
                    v-for="tier in tiers"
                    :key="tier.name"
                    class="theme-border theme-bg-dark theme-text flex flex-col rounded-xl border p-6 shadow-sm"
                    :class="{
                        'ring-2 ring-primary ring-offset-2 ring-offset-[var(--bg)]': tier.highlighted,
                    }"
                >
                    <h2 class="theme-text text-lg font-semibold">
                        {{ tier.name }}
                    </h2>
                    <div class="theme-text mt-2 flex items-baseline gap-1">
                        <span class="text-3xl font-bold">{{ tier.price }}</span>
                        <span
                            v-if="tier.priceNote"
                            class="theme-text-dark text-sm"
                        >
                            {{ tier.priceNote }}
                        </span>
                    </div>
                    <ul class="theme-text-dark mt-6 flex flex-1 flex-col gap-3 text-sm">
                        <li
                            v-for="(feature, i) in tier.features"
                            :key="i"
                            class="flex gap-2"
                        >
                            <span class="text-primary shrink-0" aria-hidden="true">✓</span>
                            <span>{{ feature }}</span>
                        </li>
                    </ul>
                    <Link
                        :href="tier.ctaHref()"
                        class="mt-6 block w-full rounded-md py-2.5 text-center text-sm font-medium transition"
                        :class="
                            tier.highlighted
                                ? 'bg-primary text-primary-foreground hover:opacity-90'
                                : 'theme-border theme-bg theme-text border hover:opacity-90'
                        "
                    >
                        {{ tier.cta }}
                    </Link>
                </article>

                <article
                    class="theme-border theme-bg-dark theme-text col-span-full flex flex-col items-center rounded-xl border p-6 text-center shadow-sm"
                >
                    <h2 class="theme-text text-lg font-semibold">Custom</h2>
                    <p class="theme-text-dark mt-2 max-w-2xl text-sm">
                        Looking for a fully custom roller, stand-alone or integrated into your own site? Or looking for a fully featured ARPG website? Contact me at
                        <a
                            href="mailto:abaturestudio@gmail.com"
                            class="text-primary underline underline-offset-2 hover:opacity-90"
                        >
                            AbatureStudio@gmail.com
                        </a>
                        to discuss your project.
                    </p>
                    <a
                        href="mailto:abaturestudio@gmail.com"
                        class="theme-border theme-bg theme-text mt-4 rounded-md border px-5 py-2.5 text-sm font-medium transition hover:opacity-90"
                    >
                        Contact Me
                    </a>
                </article>
            </div>
        </main>
    </div>
</template>
