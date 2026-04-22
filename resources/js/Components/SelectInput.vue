<script setup>
import { onMounted, ref } from 'vue';

defineProps({
    modelValue: {
        type: [String, Number, null],
        required: true,
    },
});

defineEmits(['update:modelValue']);

const select = ref(null);

onMounted(() => {
    if (select.value.hasAttribute('autofocus')) {
        select.value.focus();
    }
});

defineExpose({ focus: () => select.value.focus() });
</script>

<template>
    <select
        v-bind="$attrs"
        class="w-full rounded-xl border-gray-200 dark:border-gray-700 dark:bg-gray-900 text-sm font-bold shadow-sm focus:border-indigo-500 focus:ring-indigo-500 transition-all dark:text-gray-300 h-[42px]"
        :value="modelValue"
        @change="$emit('update:modelValue', $event.target.value)"
        ref="select"
    >
        <slot />
    </select>
</template>
