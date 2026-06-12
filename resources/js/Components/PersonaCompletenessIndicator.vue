<script setup>
import { Link } from '@inertiajs/vue3';
import { ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import { computed } from 'vue';
import { getPersonaMissingFields, personaCompletenessTitle } from '@/Utils/personaCompleteness';

const props = defineProps({
    persona: { type: Object, default: null },
    compact: { type: Boolean, default: false },
    href: { type: String, default: null },
});

const missingFields = computed(() => getPersonaMissingFields(props.persona));
const title = computed(() => personaCompletenessTitle(props.persona));
const editHref = computed(() => props.href || (props.persona?.id ? route('personas.edit', props.persona.id) : null));
</script>

<template>
    <Link
        v-if="missingFields.length && editHref"
        :href="editHref"
        class="inline-flex w-fit items-center gap-1.5 rounded-md border border-amber-200 bg-amber-50 px-2 py-1 text-[9px] font-black uppercase leading-4 text-amber-700 transition hover:border-amber-300 hover:bg-amber-100 dark:border-amber-900/40 dark:bg-amber-900/10 dark:text-amber-300 dark:hover:bg-amber-900/20"
        :title="title"
        @click.stop
    >
        <ExclamationTriangleIcon class="h-3.5 w-3.5 shrink-0" />
        <span v-if="!compact">Completar datos</span>
        <span class="rounded bg-white/70 px-1 font-black dark:bg-black/20">{{ missingFields.length }}</span>
    </Link>
</template>
