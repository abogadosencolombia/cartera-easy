<script setup>
import { computed, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

const isOpen = ref(false);
const isExpanded = ref(false);
const hasBooted = ref(false);

const page = usePage();
const isAuthenticated = computed(() => Boolean(page.props?.auth?.user));

onMounted(() => {
    hasBooted.value = sessionStorage.getItem('chatwoot_booted') === '1';
});

const toggle = () => {
    if (!hasBooted.value) {
        hasBooted.value = true;
        sessionStorage.setItem('chatwoot_booted', '1');
    }
    isOpen.value = !isOpen.value;
};

const toggleExpand = () => {
    isExpanded.value = !isExpanded.value;
};
</script>

<template>
    <div v-if="isAuthenticated" class="fixed bottom-5 right-5 z-[100]">
        <!-- Burbuja del Chat -->
        <button 
            type="button"
            @click="toggle"
            class="w-14 h-14 bg-[#0052FF] rounded-full shadow-2xl flex items-center justify-center hover:scale-110 active:scale-95 transition-all duration-200 focus:outline-none"
            :class="{ 'rotate-90': isOpen }"
        >
            <svg v-if="!isOpen" xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
            </svg>
            <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

        <!-- Ventana del Iframe -->
        <div
            class="absolute bottom-16 right-0 bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden transition-all duration-300 ease-in-out flex flex-col"
            :class="[
                isExpanded ? 'w-[90vw] h-[85vh] right-[-10px] bottom-[-10px]' : 'w-[450px] h-[650px]',
                isOpen ? 'opacity-100 pointer-events-auto translate-y-0' : 'opacity-0 pointer-events-none translate-y-2'
            ]"
        >
            <!-- Cabecera -->
            <div class="bg-gray-100 p-2 flex justify-between items-center border-b border-gray-200">
                <span class="text-xs font-bold text-gray-600 ml-2 uppercase">Chatwoot Business</span>
                <div class="flex gap-2">
                    <button type="button" @click="toggleExpand" class="p-1 hover:bg-gray-200 rounded">
                        <svg v-if="!isExpanded" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 4l-5-5m5 11v4m0 0h-4m4-4l-5 5M4 16v4m0 0h4m-4-4l5-5" />
                        </svg>
                        <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                    <button type="button" @click="toggle" class="p-1 hover:bg-gray-200 rounded">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Contenido del Iframe con Proxy Universal -->
            <div class="flex-grow relative bg-gray-50">
                <iframe
                    v-if="hasBooted"
                    src="/app/" 
                    class="w-full h-full border-none"
                    allow="camera; microphone; clipboard-read; clipboard-write; display-capture"
                ></iframe>
            </div>
        </div>
    </div>
</template>
