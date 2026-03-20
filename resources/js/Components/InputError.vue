<script setup>
import { ExclamationCircleIcon } from '@heroicons/vue/20/solid';

defineProps({
    message: {
        type: String,
    },
});
</script>

<template>
    <transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="transform -translate-y-2 opacity-0"
        enter-to-class="transform translate-y-0 opacity-100"
    >
        <div v-show="message" class="flex items-center gap-1.5 mt-1.5">
            <ExclamationCircleIcon class="h-4 w-4 text-red-500 shrink-0" />
            <p class="text-[11px] font-bold text-red-600 dark:text-red-400 uppercase tracking-tight leading-none">
                {{ message }}
            </p>
        </div>
    </transition>
</template>

<style scoped>
/* Animación de sacudida opcional para errores críticos */
.shake {
    animation: shake 0.5s cubic-bezier(.36,.07,.19,.97) both;
}

@keyframes shake {
    10%, 90% { transform: translate3d(-1px, 0, 0); }
    20%, 80% { transform: translate3d(2px, 0, 0); }
    30%, 50%, 70% { transform: translate3d(-4px, 0, 0); }
    40%, 60% { transform: translate3d(4px, 0, 0); }
}
</style>
