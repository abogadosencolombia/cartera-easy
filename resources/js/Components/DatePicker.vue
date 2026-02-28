<script setup>
import { VueDatePicker } from '@vuepic/vue-datepicker';
import { computed } from 'vue';

const props = defineProps({
    modelValue: [String, Date],
    placeholder: {
        type: String,
        default: 'Seleccionar fecha'
    }
});

const emit = defineEmits(['update:modelValue']);

// Usamos el modelValue directamente ya que model-type="yyyy-MM-dd" se encarga del formato
const date = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value)
});
</script>

<template>
    <div class="datepicker-container">
        <VueDatePicker
            v-model="date"
            :placeholder="placeholder"
            locale="es"
            auto-apply
            :enable-time-picker="false"
            format="yyyy-MM-dd"
            model-type="yyyy-MM-dd"
            input-class-name="dp-custom-input"
            teleport="body"
            select-text="Aceptar"
            cancel-text="Cancelar"
            :dark="true"
        />
    </div>
</template>

<style>
/* Forzamos el estilo para que coincida con los inputs de la página */
.dp-custom-input {
    width: 100% !important;
    height: 42px !important;
    padding: 0.5rem 0.75rem 0.5rem 2.5rem !important; /* Espacio para el icono */
    border-radius: 0.5rem !important;
    border: 1px solid #d1d5db !important;
    font-size: 0.875rem !important;
    line-height: 1.25rem !important;
    background-color: white !important;
}

.dark .dp-custom-input {
    background-color: #111827 !important;
    border-color: #374151 !important;
    color: #d1d5db !important;
}

/* Redondear el panel del calendario */
.dp__menu {
    border-radius: 0.75rem !important;
    box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1) !important;
    border: 1px solid #e5e7eb !important;
}

.dark .dp__menu {
    background-color: #1f2937 !important;
    border-color: #374151 !important;
}

/* Color de los días seleccionados (Indigo-600) */
.dp__theme_light {
    --dp-primary-color: #4f46e5;
}
.dp__theme_dark {
    --dp-primary-color: #6366f1;
}
</style>
