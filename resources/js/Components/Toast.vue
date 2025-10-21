<script setup>
import { onMounted, ref } from 'vue';

const props = defineProps({
  message: { type: String, required: true },
  type: { type: String, default: 'success' }, // 'success', 'error', 'info'
  duration: { type: Number, default: 4000 },
});

const emit = defineEmits(['remove']);

const visible = ref(false);

const typeClasses = {
  success: 'bg-green-500 border-green-600',
  error: 'bg-red-500 border-red-600',
  info: 'bg-blue-500 border-blue-600',
};

onMounted(() => {
  // Animar la entrada
  visible.value = true;
  // Programar la salida
  setTimeout(() => {
    visible.value = false;
    // Dar tiempo a la animación de salida antes de eliminar el componente
    setTimeout(() => emit('remove'), 500);
  }, props.duration);
});
</script>

<template>
  <transition
    enter-active-class="transition ease-out duration-300"
    enter-from-class="transform opacity-0 translate-x-full"
    enter-to-class="transform opacity-100 translate-x-0"
    leave-active-class="transition ease-in duration-300"
    leave-from-class="transform opacity-100"
    leave-to-class="transform opacity-0 scale-90"
  >
    <div
      v-if="visible"
      :class="['text-white rounded-lg shadow-2xl p-4 border-l-4 flex items-start gap-4', typeClasses[type]]"
    >
      <div class="flex-shrink-0">
        <!-- Íconos -->
        <svg v-if="type === 'success'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <svg v-if="type === 'error'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
        <svg v-if="type === 'info'" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
      </div>
      <div class="flex-grow">
        <p class="font-bold">{{ message }}</p>
      </div>
      <button @click="visible = false" class="ml-4 -mr-1 p-1 rounded-full hover:bg-white/20 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
      </button>
    </div>
  </transition>
</template>
