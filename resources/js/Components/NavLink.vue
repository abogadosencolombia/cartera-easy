<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: {
        type: String,
        required: true,
    },
    active: {
        type: Boolean,
    },
});

const classes = computed(() =>
    props.active
        ? 'inline-flex h-16 shrink-0 items-center border-b-2 border-indigo-400 px-2 text-xs font-semibold leading-5 text-gray-900 transition duration-150 ease-in-out focus:outline-none focus:border-indigo-700 xl:text-sm'
        : 'inline-flex h-16 shrink-0 items-center border-b-2 border-transparent px-2 text-xs font-semibold leading-5 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 xl:text-sm',
);
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
