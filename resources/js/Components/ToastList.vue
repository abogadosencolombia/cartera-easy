<script setup>
import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Toast from './Toast.vue';

const page = usePage();
const toasts = ref([]);
let toastId = 0;

const removeToast = (id) => {
  toasts.value = toasts.value.filter(toast => toast.id !== id);
};

// Observa los flash messages de Inertia
watch(() => page.props.flash, (flash) => {
  if (flash && (flash.success || flash.error || flash.info)) {
    let message = '';
    let type = '';
    if (flash.success) { message = flash.success; type = 'success'; }
    if (flash.error) { message = flash.error; type = 'error'; }
    if (flash.info) { message = flash.info; type = 'info'; }
    
    toasts.value.push({ id: toastId++, message, type });
  }
}, { deep: true });

</script>

<template>
  <div class="fixed top-4 right-4 z-50 space-y-3 w-full max-w-sm">
    <Toast
      v-for="toast in toasts"
      :key="toast.id"
      :message="toast.message"
      :type="toast.type"
      @remove="removeToast(toast.id)"
    />
  </div>
</template>
