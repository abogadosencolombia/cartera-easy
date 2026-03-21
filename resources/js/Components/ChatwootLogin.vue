<script setup>
import { ref } from 'vue';

const isOpen = ref(false);

const toggle = () => {
    isOpen.value = !isOpen.value;
};
</script>

<template>
    <div class="fixed bottom-5 right-5 z-[100]">
        <!-- Burbuja del Chat -->
        <button 
            @click="toggle"
            class="w-14 h-14 bg-[#0052FF] rounded-full shadow-2xl flex items-center justify-center hover:scale-110 transition-transform duration-200 focus:outline-none"
            title="Chatwoot Login"
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
            v-if="isOpen"
            class="absolute bottom-16 right-0 w-[400px] h-[600px] bg-white rounded-2xl shadow-2xl border border-gray-200 overflow-hidden transition-all duration-300 ease-in-out"
        >
            <div class="w-full h-full">
                <iframe 
                    src="https://chatwoot.servilutioncrm.cloud/app/login" 
                    class="w-full h-full border-none"
                    allow="camera; microphone; clipboard-read; clipboard-write; display-capture"
                ></iframe>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Estilos opcionales para la transición si se desea animar */
</style>
