<script setup>
import { useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import DateTimePicker from '@/Components/DateTimePicker.vue';
import Textarea from '@/Components/Textarea.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import CumplimientoLegal from '@/Components/CumplimientoLegal.vue';
import GuiaEtapa from '@/Components/GuiaEtapa.vue';
import { addDaysToDate, addMonthsToDate } from '@/Utils/formatters';
import { 
    ScaleIcon, 
    UserCircleIcon, 
    UsersIcon, 
    PhoneIcon, 
    EnvelopeIcon, 
    MapPinIcon, 
    ChevronDownIcon,
    BellIcon,
    ArrowTopRightOnSquareIcon,
    GlobeAltIcon
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
        onSuccess: () => {
            notifForm.reset();
            router.reload({ only: ['auth'] });
        },
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

                    <!-- ✅ LINKS EXTERNOS (DRIVE Y EXPEDIENTE) -->
                    <div class="md:col-span-2 border-t border-gray-200 dark:border-gray-700 pt-4 mt-2 flex flex-col sm:flex-row gap-4">
                        <div v-if="caso.link_drive" class="flex-1">
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2 uppercase">Carpeta Digital</p>
                            <a :href="caso.link_drive" target="_blank" class="inline-flex items-center px-4 py-2 bg-blue-50 border border-blue-200 rounded-md font-semibold text-xs text-blue-700 uppercase tracking-widest hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:bg-blue-900/30 dark:border-blue-800 dark:text-blue-300 dark:hover:bg-blue-900/50 transition ease-in-out duration-150 w-full justify-center">
                                <img src="https://upload.wikimedia.org/wikipedia/commons/1/12/Google_Drive_icon_%282020%29.svg" alt="Drive" class="w-5 h-5 mr-2">
                                Carpeta en Drive
                                <ArrowTopRightOnSquareIcon class="w-4 h-4 ml-2" />
                            </a>
                        </div>
                        
                        <div v-if="caso.link_expediente" class="flex-1">
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2 uppercase">Expediente Judicial</p>
                            <a :href="caso.link_expediente" target="_blank" class="inline-flex items-center px-4 py-2 bg-indigo-50 border border-indigo-200 rounded-md font-semibold text-xs text-indigo-700 uppercase tracking-widest hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:bg-indigo-900/30 dark:border-indigo-800 dark:text-indigo-300 dark:hover:bg-indigo-900/50 transition ease-in-out duration-150 w-full justify-center">
                                <GlobeAltIcon class="w-5 h-5 mr-2 text-indigo-500" />
                                Expediente Digital
                                <ArrowTopRightOnSquareIcon class="w-4 h-4 ml-2" />
                            </a>
                        </div>
                    </div>

                </dl>
            </div>

            <div class="bg-gray-50 dark:bg-gray-900/50 p-6 rounded-lg border border-gray-100 dark:border-gray-700">
                <CumplimientoLegal :validaciones="caso.validaciones_legales" />
            </div>

            <!-- GUÍA DE GESTIÓN POR ETAPA -->
            <GuiaEtapa 
                :etapa="caso.etapa_procesal" 
                :tipo-proceso="caso.tipo_proceso" 
                :checklist-completados="caso.checklist_seguimiento || []"
                :model-id="caso.id"
                model-type="caso"
            />
        </div>

        <!-- COLUMNA DERECHA -->
        <div class="lg:col-span-1 space-y-6">
            <!-- Partes -->
            <div class="bg-gray-50 dark:bg-gray-900/50 p-6 rounded-lg border border-gray-100 dark:border-gray-700">
                <h3 class="text-lg font-bold mb-4 flex items-center text-gray-800 dark:text-gray-100">
                    <UsersIcon class="h-5 w-5 mr-2 text-indigo-500"/> Partes Involucradas
                </h3>
                <ul class="space-y-4">
                    <li class="flex items-start">
                        <UserCircleIcon class="h-6 w-6 mr-3 text-indigo-500 flex-shrink-0" />
                        <div class="text-sm">
                            <span class="font-medium text-gray-900 dark:text-white">Deudor:</span>
                            <div class="text-gray-600 dark:text-gray-300 font-bold">{{ caso.deudor ? caso.deudor.nombre_completo : 'N/A' }}</div>
                            <div v-if="caso.deudor?.celular_1" class="flex items-center text-xs text-gray-500 mt-1">
                                <PhoneIcon class="h-3 w-3 mr-1 text-gray-400" /> {{ caso.deudor.celular_1 }}
                                <a :href="'https://wa.me/57' + caso.deudor.celular_1.replace(/\s/g, '')" target="_blank" class="ml-2 p-1 bg-green-100 text-green-600 rounded-full hover:bg-green-600 hover:text-white transition-colors" title="Enviar WhatsApp">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.438 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.397-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.808 2.876 2.056 3.223c.248.348 3.503 5.397 8.486 7.547 1.186.512 2.112.817 2.833 1.046 1.19.379 2.273.326 3.129.199.954-.141 2.031-.83 2.316-1.633.285-.804.285-1.493.199-1.633z"/></svg>
                                </a>
                            </div>
                            <div v-if="caso.deudor?.correo_1" class="flex items-center text-xs text-gray-500">
                                <EnvelopeIcon class="h-3 w-3 mr-1 text-gray-400" /> {{ caso.deudor.correo_1 }}
                            </div>
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
                                <a :href="'https://wa.me/57' + codeudor.celular.replace(/\s/g, '')" target="_blank" class="ml-2 p-1 bg-green-100 text-green-600 rounded-full hover:bg-green-600 hover:text-white transition-colors" title="Enviar WhatsApp">
                                    <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.438 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.397-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.808 2.876 2.056 3.223c.248.348 3.503 5.397 8.486 7.547 1.186.512 2.112.817 2.833 1.046 1.19.379 2.273.326 3.129.199.954-.141 2.031-.83 2.316-1.633.285-.804.285-1.493.199-1.633z"/></svg>
                                </a>
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
                            <span class="font-medium text-gray-900 dark:text-white">Responsable(s):</span>
                            <div class="flex flex-wrap gap-1 mt-1">
                                <template v-if="caso.users && caso.users.length > 0">
                                    <span v-for="u in caso.users" :key="u.id" class="px-2 py-0.5 bg-gray-100 dark:bg-gray-700 rounded text-[10px] font-bold">
                                        {{ u.name }}
                                    </span>
                                </template>
                                <div v-else class="text-gray-600 dark:text-gray-300">{{ caso.user ? caso.user.name : 'N/A' }}</div>
                            </div>
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
                        <DateTimePicker 
                            id="fecha_programada" 
                            v-model="notifForm.fecha_programada" 
                            placeholder="Clic para agendar..."
                        />
                        <div class="mt-1 flex gap-1">
                            <button type="button" @click="notifForm.fecha_programada = addDaysToDate(notifForm.fecha_programada, 3)" class="text-[9px] bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded font-bold hover:bg-indigo-100 text-gray-600">+3d</button>
                            <button type="button" @click="notifForm.fecha_programada = addDaysToDate(notifForm.fecha_programada, 5)" class="text-[9px] bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded font-bold hover:bg-indigo-100 text-gray-600">+5d</button>
                            <button type="button" @click="notifForm.fecha_programada = addMonthsToDate(notifForm.fecha_programada, 1)" class="text-[9px] bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded font-bold hover:bg-indigo-100 text-gray-600">+1m</button>
                        </div>
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