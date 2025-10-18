<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { PencilSquareIcon, EyeIcon, CheckCircleIcon } from '@heroicons/vue/24/outline';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Textarea from '@/Components/Textarea.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    validaciones: Array,
});

// --- Lógica de Modales ---
const mostrandoModalCorreccion = ref(false);
const mostrandoModalHistorial = ref(false);
const validacionSeleccionada = ref(null);

const formCorreccion = useForm({
    accion_correctiva: '',
});

const abrirModalCorreccion = (validacion) => {
    validacionSeleccionada.value = validacion;
    formCorreccion.reset();
    mostrandoModalCorreccion.value = true;
};

const cerrarModalCorreccion = () => {
    mostrandoModalCorreccion.value = false;
    validacionSeleccionada.value = null;
};

const submitCorreccion = () => {
    formCorreccion.put(route('validaciones.corregir', validacionSeleccionada.value.id), {
        preserveScroll: true,
        onSuccess: () => cerrarModalCorreccion(),
    });
};

const abrirModalHistorial = (validacion) => {
    validacionSeleccionada.value = validacion;
    mostrandoModalHistorial.value = true;
};

const cerrarModalHistorial = () => {
    mostrandoModalHistorial.value = false;
    validacionSeleccionada.value = null;
};


// --- Lógica de Estilos y Formato ---
const tiposValidacionNombres = {
    poder_vencido: 'Poder Vencido',
    tasa_usura: 'Tasa de Usura Excedida',
    sin_pagare: 'Falta Pagaré',
    sin_carta_instrucciones: 'Falta Carta de Instrucciones',
    sin_certificacion_saldo: 'Falta Certificación de Saldo',
    tipo_proceso_vs_garantia: 'Proceso vs. Garantía',
    plazo_excedido_sin_demanda: 'Plazo para Demandar Excedido',
    documento_faltante_para_radicar: 'Docs. Faltantes para Radicar',
};

const estadoClasses = {
    cumple: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    incumple: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300 animate-pulse',
    no_aplica: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
};

const riesgoClasses = {
    alto: 'bg-red-200 text-red-900',
    medio: 'bg-yellow-200 text-yellow-900',
    bajo: 'bg-blue-200 text-blue-900',
};

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const options = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
    return new Date(dateString).toLocaleString('es-CO', options);
};
</script>

<template>
    <div>
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">🚦 Cumplimiento Legal</h3>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Validación</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Riesgo</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Observación</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acción Correctiva</th>
                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                    <tr v-if="!validaciones || validaciones.length === 0">
                        <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">No hay validaciones para este caso aún.</td>
                    </tr>
                    <tr v-for="validacion in validaciones" :key="validacion.id">
                        <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ tiposValidacionNombres[validacion.tipo] || validacion.tipo }}</td>
                        <td class="px-4 py-4 whitespace-nowrap"><span :class="['px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full', estadoClasses[validacion.estado]]">{{ validacion.estado }}</span></td>
                        <td class="px-4 py-4 whitespace-nowrap"><span :class="['px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full capitalize', riesgoClasses[validacion.nivel_riesgo]]">{{ validacion.nivel_riesgo }}</span></td>
                        <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400 max-w-xs truncate" :title="validacion.observacion">{{ validacion.observacion }}</td>
                        <td class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">
                            <span v-if="validacion.accion_correctiva" :title="`Corregido el: ${formatDate(validacion.fecha_cumplimiento)}`">{{ validacion.accion_correctiva }}</span>
                            <span v-else>-</span>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-center text-sm font-medium space-x-2">
                            <button @click="abrirModalHistorial(validacion)" class="text-gray-500 hover:text-indigo-600" title="Ver Historial">
                                <EyeIcon class="h-5 w-5"/>
                            </button>
                            <button v-if="validacion.estado === 'incumple'" @click="abrirModalCorreccion(validacion)" class="text-green-500 hover:text-green-700" title="Corregir Falla">
                                <CheckCircleIcon class="h-5 w-5"/>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Modal :show="mostrandoModalCorreccion" @close="cerrarModalCorreccion">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    <PencilSquareIcon class="h-6 w-6 inline-block mr-2 text-indigo-500"/>
                    Registrar Acción Correctiva
                </h2>
                <p v-if="validacionSeleccionada" class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Estás corrigiendo la falla: <span class="font-bold">{{ tiposValidacionNombres[validacionSeleccionada.tipo] }}</span>.
                </p>
                <form @submit.prevent="submitCorreccion" class="mt-6 space-y-4">
                    <div>
                        <InputLabel for="accion_correctiva" value="Describe la solución aplicada" />
                        <Textarea id="accion_correctiva" v-model="formCorreccion.accion_correctiva" class="mt-1 block w-full" rows="4" required />
                        <InputError :message="formCorreccion.errors.accion_correctiva" class="mt-2" />
                    </div>
                    <div class="flex justify-end space-x-4">
                        <SecondaryButton @click="cerrarModalCorreccion">Cancelar</SecondaryButton>
                        <PrimaryButton :class="{ 'opacity-25': formCorreccion.processing }" :disabled="formCorreccion.processing">
                            Guardar Corrección
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
        
        <Modal :show="mostrandoModalHistorial" @close="cerrarModalHistorial">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    <EyeIcon class="h-6 w-6 inline-block mr-2 text-indigo-500"/>
                    Historial de la Validación
                </h2>
                 <p v-if="validacionSeleccionada" class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Mostrando trazabilidad para: <span class="font-bold">{{ tiposValidacionNombres[validacionSeleccionada.tipo] }}</span>.
                </p>
                <div class="mt-4 max-h-96 overflow-y-auto">
                    <div v-if="!validacionSeleccionada || !validacionSeleccionada.historial || !validacionSeleccionada.historial.length" class="text-center text-gray-500 py-4">No hay historial para mostrar.</div>
                    <ol v-else class="relative border-l border-gray-200 dark:border-gray-700 ml-3">
                        <li v-for="item in validacionSeleccionada.historial" :key="item.id" class="mb-6 ml-6">
                           <span class="absolute flex items-center justify-center w-6 h-6 bg-blue-100 rounded-full -left-3 ring-8 ring-white dark:ring-gray-900 dark:bg-blue-900">
                               <PencilSquareIcon class="w-3 h-3 text-blue-800 dark:text-blue-300"/>
                           </span>
                           <div class="p-4 bg-white border border-gray-200 rounded-lg shadow-sm dark:bg-gray-700 dark:border-gray-600">
                               <div class="items-center justify-between mb-3 sm:flex">
                                   <time class="mb-1 text-xs font-normal text-gray-400 sm:order-last sm:mb-0">{{ formatDate(item.created_at) }}</time>
                                   <div class="text-sm font-normal text-gray-500 lex dark:text-gray-300">
                                       {{ item.usuario ? item.usuario.name : 'Sistema' }} cambió el estado
                                       <span v-if="item.estado_anterior" class="font-semibold text-gray-900 dark:text-white">de '{{ item.estado_anterior }}' a</span>
                                       a <span class="font-semibold text-gray-900 dark:text-white">'{{ item.estado_nuevo }}'</span>
                                   </div>
                               </div>
                               <div class="p-3 text-xs italic font-normal text-gray-500 border border-gray-200 rounded-lg bg-gray-50 dark:bg-gray-600 dark:border-gray-500 dark:text-gray-300">
                                   {{ item.comentario }}
                               </div>
                           </div>
                       </li>
                    </ol>
                </div>
                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="cerrarModalHistorial">Cerrar</SecondaryButton>
                </div>
            </div>
        </Modal>

    </div>
</template>