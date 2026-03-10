<script setup>
import { watch, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const page = usePage();

// Configuración global del Toast de SweetAlert2
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 4000,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.onmouseenter = Swal.stopTimer;
        toast.onmouseleave = Swal.resumeTimer;
    }
});

// Función centralizada para mostrar la alerta
const showAlert = (flash) => {
    if (!flash) return;
    
    if (flash.success) {
        Toast.fire({ icon: 'success', title: flash.success });
    } else if (flash.error) {
        Toast.fire({ icon: 'error', title: flash.error });
    } else if (flash.warning) {
        Toast.fire({ icon: 'warning', title: flash.warning });
    } else if (flash.info) {
        Toast.fire({ icon: 'info', title: flash.info });
    }
};

// Observamos cambios en los flashes al navegar por Inertia
watch(() => page.props.flash, (newFlash) => {
    showAlert(newFlash);
}, { deep: true });

// NUEVO: Observamos errores de validación globales
watch(() => page.props.errors, (newErrors) => {
    if (newErrors && Object.keys(newErrors).length > 0) {
        Toast.fire({
            icon: 'error',
            title: 'Error de validación',
            text: 'Hay campos con errores en el formulario. Por favor, revísalos antes de continuar.',
            timer: 5000
        });
    }
}, { deep: true });

// Revisamos si hay un flash inicial al cargar la página
onMounted(() => {
    if (page.props.flash) {
        showAlert(page.props.flash);
    }
});
</script>

<template>
  <!-- Este componente ya no necesita renderizar HTML, SweetAlert maneja el DOM -->
  <div style="display: none;"></div>
</template>
