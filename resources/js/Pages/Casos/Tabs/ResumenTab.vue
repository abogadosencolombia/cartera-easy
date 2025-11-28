<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import Textarea from '@/Components/Textarea.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import CumplimientoLegal from '@/Components/CumplimientoLegal.vue';
import { 
    ScaleIcon, 
    UserCircleIcon, 
    UsersIcon, 
    PhoneIcon, 
    EnvelopeIcon, 
    MapPinIcon, 
    ChevronDownIcon,
    BellIcon,
    ArrowTopRightOnSquareIcon 
} from '@heroicons/vue/24/outline';

const props = defineProps({
    caso: { type: Object, required: true },
    resumen_financiero: { type: Object, required: true },
    formatCurrency: { type: Function, required: true },
    formatDate: { type: Function, required: true },
    formatLabel: { type: Function, required: true },
});

// --- Lógica para desplegable de Codeudor ---
const codeudorAbiertoId = ref(null);

const toggleCodeudor = (id) => {
    if (codeudorAbiertoId.value === id) {
        codeudorAbiertoId.value = null; // Cierra si ya está abierto
    } else {
        codeudorAbiertoId.value = id; // Abre el nuevo
    }
};

// Helper para parsear JSON de forma segura
const safeJsonParse = (jsonString) => {
    if (!jsonString) return [];
    if (typeof jsonString === 'object') return jsonString;
    try {
        const parsed = JSON.parse(jsonString);
        return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
        return [];
    }
};

// --- FORMULARIO PARA NOTIFICACIONES (NUEVO) ---
const notifForm = useForm({
    fecha_programada: '',
    mensaje: '',
    prioridad: 'media',
});

const submitNotification = () => {
    notifForm.post(route('casos.notificaciones.store', props.caso.id), {
        preserveScroll: true,
        onSuccess: () => notifForm.reset(),
    });
};
</script>

<template>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- COLUMNA IZQUIERDA -->
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-gray-50 dark:bg-gray-900/50 p-6 rounded-lg border border-gray-100 dark:border-gray-700">
                <h3 class="text-lg font-bold mb-4 flex items-center text-gray-800 dark:text-gray-100">
                    <ScaleIcon class="h-6 w-6 mr-2 text-indigo-500" />Detalles del Proceso
                </h3>

                <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Numero De Pagare</p>
                        <p class="font-semibold text-gray-900 dark:text-gray-200">{{ caso.referencia_credito || 'N/A' }}</p>
                    </div>
                    
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Número de Radicado</p>
                        <p class="font-semibold text-gray-900 dark:text-gray-200">{{ caso.radicado || 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Especialidad</p>
                        <p class="font-semibold text-gray-900 dark:text-gray-200">{{ caso.especialidad ? caso.especialidad.nombre : formatLabel(caso.especialidad_nombre) || 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Tipo de Proceso</p>
                        <p class="font-semibold text-gray-900 dark:text-gray-200">{{ formatLabel(caso.tipo_proceso) }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Proceso</p>
                        <p class="font-semibold text-gray-900 dark:text-gray-200">{{ formatLabel(caso.subtipo_proceso) }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Subproceso (Detalle)</p>
                        <p class="font-semibold text-gray-900 dark:text-gray-200">{{ formatLabel(caso.subproceso) }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Etapa Procesal</p>
                        <p class="font-bold text-indigo-600 dark:text-indigo-400">{{ formatLabel(caso.etapa_procesal) }}</p>
                    </div>
                    <div class="md:col-span-2">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Juzgado</p>
                        <p class="font-semibold text-gray-900 dark:text-gray-200">{{ caso.juzgado ? caso.juzgado.nombre : 'No especificado' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Fecha de Demanda</p>
                        <p class="font-semibold text-gray-900 dark:text-gray-200">{{ formatDate(caso.fecha_apertura) }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Fecha de Vencimiento</p>
                        <p class="font-semibold text-gray-900 dark:text-gray-200">{{ formatDate(caso.fecha_vencimiento) }}</p>
                    </div>
                    
                    <!-- FINANCIERO (Actualizado) -->
                    <div class="md:col-span-2 border-t border-gray-200 dark:border-gray-700 pt-4 mt-2">
                         <h4 class="text-xs font-bold text-gray-400 uppercase mb-3">Resumen Financiero Actual</h4>
                         <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Monto Inicial</p>
                                <p class="font-semibold text-gray-900 dark:text-gray-100">{{ formatCurrency(caso.monto_total) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Deuda Actual</p>
                                <p class="font-bold text-red-600 dark:text-red-400">{{ formatCurrency(resumen_financiero.saldo_pendiente) }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Total Pagado</p>
                                <p class="font-bold text-green-600 dark:text-green-400">{{ formatCurrency(resumen_financiero.total_pagado) }}</p>
                            </div>
                         </div>
                    </div>

                    <div class="md:col-span-2 grid grid-cols-1 sm:grid-cols-2 gap-4 mt-2">
                         <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Fecha Inicio Crédito</p>
                            <p class="font-semibold text-gray-900 dark:text-gray-200">{{ formatDate(caso.fecha_inicio_credito) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Tipo de Garantía</p>
                            <p class="font-semibold capitalize text-gray-900 dark:text-gray-200">{{ caso.tipo_garantia_asociada }}</p>
                        </div>
                    </div>

                    <!-- ✅ LINK DRIVE (NUEVO) -->
                    <div class="md:col-span-2 border-t border-gray-200 dark:border-gray-700 pt-4 mt-2" v-if="caso.link_drive">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2 uppercase">Carpeta Digital</p>
                        <a :href="caso.link_drive" target="_blank" class="inline-flex items-center px-4 py-2 bg-blue-50 border border-blue-200 rounded-md font-semibold text-xs text-blue-700 uppercase tracking-widest hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:bg-blue-900/30 dark:border-blue-800 dark:text-blue-300 dark:hover:bg-blue-900/50 transition ease-in-out duration-150 w-full justify-center sm:w-auto">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/1/12/Google_Drive_icon_%282020%29.svg" alt="Drive" class="w-5 h-5 mr-2">
                            Abrir Carpeta en Drive
                            <ArrowTopRightOnSquareIcon class="w-4 h-4 ml-2" />
                        </a>
                    </div>

                </dl>
            </div>

            <div class="bg-gray-50 dark:bg-gray-900/50 p-6 rounded-lg border border-gray-100 dark:border-gray-700">
                <CumplimientoLegal :validaciones="caso.validaciones_legales" />
            </div>
        </div>

        <!-- COLUMNA DERECHA -->
        <div class="lg:col-span-1 space-y-6">
            <!-- Partes -->
            <div class="bg-gray-50 dark:bg-gray-900/50 p-6 rounded-lg border border-gray-100 dark:border-gray-700">
                <h3 class="text-lg font-bold mb-4 flex items-center text-gray-800 dark:text-gray-100">
                    <UsersIcon class="h-5 w-5 mr-2 text-indigo-500"/> Partes Involucradas
                </h3>
                <ul class="space-y-4">
                    <li class="flex items-center">
                        <UserCircleIcon class="h-6 w-6 mr-3 text-indigo-500" />
                        <div class="text-sm">
                            <span class="font-medium text-gray-900 dark:text-white">Deudor:</span>
                            <div class="text-gray-600 dark:text-gray-300">{{ caso.deudor ? caso.deudor.nombre_completo : 'N/A' }}</div>
                        </div>
                    </li>
                    
                    <li v-if="!caso.codeudores || caso.codeudores.length === 0" class="flex items-center">
                        <UsersIcon class="h-6 w-6 mr-3 text-gray-400" />
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            No hay codeudores asignados.
                        </div>
                    </li>
                    
                    <li v-else v-for="(codeudor, index) in caso.codeudores" :key="codeudor.id" class="flex flex-col items-start w-full border-t border-gray-200 dark:border-gray-700 pt-2 mt-2 first:border-0 first:pt-0 first:mt-0">
                        <button @click="toggleCodeudor(codeudor.id)" type="button" class="flex items-center w-full text-left p-1 -ml-1 rounded-md hover:bg-gray-200 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors">
                            <UsersIcon class="h-5 w-5 mr-3 text-gray-500 flex-shrink-0" />
                            <div class="text-sm flex-grow">
                                <span class="font-medium text-gray-900 dark:text-white">Codeudor {{ index + 1 }}:</span>
                                <div class="text-gray-600 dark:text-gray-300 text-xs">{{ codeudor.nombre_completo }}</div>
                            </div>
                            <ChevronDownIcon class="h-4 w-4 text-gray-400 transition-transform duration-200" :class="{ 'rotate-180': codeudorAbiertoId === codeudor.id }" />
                        </button>
                        
                        <div v-show="codeudorAbiertoId === codeudor.id" class="w-full pl-9 pr-2 pt-2 pb-2 text-sm space-y-2 bg-white dark:bg-gray-800 rounded-md mt-1 border border-gray-200 dark:border-gray-700 shadow-sm">
                            <div class="text-xs text-gray-500 mb-1 font-mono">{{ codeudor.numero_documento }}</div>
                            <div v-if="codeudor.celular" class="flex items-center">
                                <PhoneIcon class="h-3 w-3 mr-2 text-gray-400" />
                                <span class="text-gray-700 dark:text-gray-300 text-xs">{{ codeudor.celular }}</span>
                            </div>
                            <div v-if="codeudor.correo" class="flex items-center">
                                <EnvelopeIcon class="h-3 w-3 mr-2 text-gray-400" />
                                <span class="text-gray-700 dark:text-gray-300 text-xs break-all">{{ codeudor.correo }}</span>
                            </div>
                            
                            <div v-if="safeJsonParse(codeudor.addresses) && safeJsonParse(codeudor.addresses).length > 0">
                                <div v-for="(addr, idx) in safeJsonParse(codeudor.addresses)" :key="idx" class="flex items-start mt-1">
                                    <MapPinIcon class="h-3 w-3 mr-2 text-gray-400 flex-shrink-0 mt-0.5" />
                                    <div class="text-gray-700 dark:text-gray-300 text-xs">
                                        <span class="font-semibold">{{ addr.label || 'Dirección' }}:</span>
                                        <span> {{ addr.address }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>

                    <li class="flex items-center pt-4 border-t border-gray-200 dark:border-gray-700">
                        <UserCircleIcon class="h-6 w-6 mr-3 text-gray-400" />
                        <div class="text-sm">
                            <span class="font-medium text-gray-900 dark:text-white">Abogado:</span>
                            <div class="text-gray-600 dark:text-gray-300">{{ caso.user ? caso.user.name : 'N/A' }}</div>
                        </div>
                    </li>
                </ul>
            </div>

            <!-- ✅ PROGRAMAR NOTIFICACIÓN (NUEVO) -->
            <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-bold mb-4 flex items-center text-gray-800 dark:text-gray-100">
                    <BellIcon class="h-5 w-5 mr-2 text-amber-500" />
                    Programar Recordatorio
                </h3>
                
                <form @submit.prevent="submitNotification" class="space-y-4">
                    <div>
                        <InputLabel for="fecha_programada" value="Fecha y Hora" class="!text-xs" />
                        <TextInput 
                            id="fecha_programada" 
                            type="datetime-local" 
                            v-model="notifForm.fecha_programada" 
                            class="mt-1 block w-full text-sm" 
                            required 
                        />
                        <InputError :message="notifForm.errors.fecha_programada" class="mt-1" />
                    </div>
                    
                    <div>
                        <InputLabel for="mensaje" value="Mensaje / Tarea" class="!text-xs" />
                        <Textarea 
                            id="mensaje" 
                            v-model="notifForm.mensaje" 
                            class="mt-1 block w-full text-sm" 
                            rows="3" 
                            placeholder="Ej: Llamar al cliente para cobrar..." 
                            required 
                        />
                        <InputError :message="notifForm.errors.mensaje" class="mt-1" />
                    </div>

                    <div class="flex justify-end">
                        <PrimaryButton :class="{ 'opacity-25': notifForm.processing }" :disabled="notifForm.processing" class="w-full justify-center">
                            Agendar
                        </PrimaryButton>
                    </div>
                </form>
            </div>

        </div>
    </div>
</template>