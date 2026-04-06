<script setup>
import { onMounted, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { driver } from "driver.js";
import "driver.js/dist/driver.css";

const page = usePage();
const user = page.props.auth.user;

const startTour = () => {
    const driverObj = driver({
        showProgress: true,
        nextBtnText: 'Siguiente',
        prevBtnText: 'Anterior',
        doneBtnText: '¡Listo!',
        onDeselected: () => {
            finishTour();
        },
        steps: [
            { 
                element: '#tour-casos-title', 
                popover: { 
                    title: '¡Nueva Gestión de Casos!', 
                    description: 'Hemos rediseñado esta sección para que sea más robusta y segura.', 
                    side: "bottom", 
                    align: 'start' 
                } 
            },
            { 
                element: '#tour-btn-exportar', 
                popover: { 
                    title: 'Exportación con ID Único', 
                    description: 'Al exportar, cada caso lleva un ID oculto. Esto permite que puedas editar el Excel y volverlo a subir sin crear duplicados.', 
                    side: "bottom", 
                    align: 'center' 
                } 
            },
            { 
                element: '#tour-btn-importar', 
                popover: { 
                    title: 'Importación Inteligente', 
                    description: 'Aquí puedes subir tu Excel. El sistema comparará los datos y te avisará exactamente qué campos van a cambiar antes de guardar.', 
                    side: "bottom", 
                    align: 'center' 
                } 
            },
            { 
                popover: { 
                    title: 'Cero Margen de Error', 
                    description: 'Recuerda revisar siempre los semáforos (Rojo/Amarillo/Verde) en la previsualización antes de procesar.', 
                } 
            },
        ]
    });

    driverObj.drive();
};

const finishTour = async () => {
    try {
        await axios.patch(route('profile.tour.seen'));
        user.tour_seen = true;
    } catch (e) {
        console.error("Error al guardar estado del tour", e);
    }
};

// Solo iniciamos el tour si estamos en la página de Casos y no lo ha visto
onMounted(() => {
    const isCasosPage = window.location.pathname.endsWith('/casos');
    if (user && !user.tour_seen && isCasosPage) {
        setTimeout(startTour, 1500);
    }
});

// También vigilamos cambios de ruta por si llega a Casos después de navegar
watch(() => page.url, (newUrl) => {
    if (newUrl.endsWith('/casos') && user && !user.tour_seen) {
        setTimeout(startTour, 1000);
    }
});
</script>

<template>
    <!-- Este componente no renderiza nada visual propio, usa Driver.js -->
    <div class="hidden"></div>
</template>

<style>
/* Personalización premium para el tour */
.driver-popover {
    background-color: white !important;
    color: #111827 !important;
    border-radius: 1.5rem !important;
    padding: 20px !important;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25) !important;
    border: 1px solid #e5e7eb !important;
}
.driver-popover-title {
    font-weight: 900 !important;
    font-size: 1.1rem !important;
    text-transform: uppercase !important;
    letter-spacing: 0.05em !important;
    color: #4f46e5 !important;
}
.driver-popover-description {
    font-weight: 500 !important;
    line-height: 1.6 !important;
    color: #4b5563 !important;
}
.driver-popover-btn {
    border-radius: 0.75rem !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    font-size: 0.7rem !important;
    padding: 8px 16px !important;
}
.driver-popover-next-btn {
    background-color: #4f46e5 !important;
}
</style>
