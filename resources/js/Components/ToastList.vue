<script setup>
import { watch, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Swal from 'sweetalert2';

const page = usePage();

// Configuración global del Toast de SweetAlert2 (Estilo Premium)
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 4500,
    timerProgressBar: true,
    showClass: {
        popup: 'animate__animated animate__fadeInRight animate__faster'
    },
    hideClass: {
        popup: 'animate__animated animate__fadeOutRight animate__faster'
    },
    didOpen: (toast) => {
        toast.onmouseenter = Swal.stopTimer;
        toast.onmouseleave = Swal.resumeTimer;
    }
});

// Función centralizada para mostrar la alerta
const showAlert = (flash) => {
    if (!flash) return;
    
    if (flash.success) {
        Toast.fire({ icon: 'success', title: 'Operación Exitosa', text: flash.success });
    } else if (flash.error) {
        Toast.fire({ icon: 'error', title: 'Error de Sistema', text: flash.error });
    } else if (flash.warning) {
        Toast.fire({ icon: 'warning', title: 'Atención', text: flash.warning });
    } else if (flash.info) {
        Toast.fire({ icon: 'info', title: 'Información', text: flash.info });
    }
};

// Observamos cambios en los flashes al navegar por Inertia
watch(() => page.props.flash, (newFlash) => {
    if (newFlash && (newFlash.success || newFlash.error || newFlash.warning || newFlash.info)) {
        showAlert(newFlash);
    }
}, { deep: true });

// NUEVO: Observamos errores de validación globales con estilo mejorado
watch(() => page.props.errors, (newErrors) => {
    if (newErrors && Object.keys(newErrors).length > 0) {
        Toast.fire({
            icon: 'warning',
            title: 'Formulario Incompleto',
            text: 'Hay errores en los datos ingresados. Por favor, revisa los campos marcados en rojo.',
            timer: 6000
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
