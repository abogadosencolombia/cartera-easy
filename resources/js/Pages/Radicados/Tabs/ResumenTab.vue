<script setup>
import { 
    ScaleIcon, 
    BuildingLibraryIcon,
    CalendarDaysIcon,
    EnvelopeIcon,
    GlobeAltIcon,
    ArrowTopRightOnSquareIcon,
    ChatBubbleBottomCenterTextIcon,
    ClipboardDocumentCheckIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    proceso: { type: Object, required: true },
    formatDate: { type: Function, required: true },
});

const asText = (v) => v ?? '—';
</script>

<template>
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 animate-in fade-in duration-500">
        
        <!-- COLUMNA IZQUIERDA: DETALLES TÉCNICOS (8/12) -->
        <div class="lg:col-span-8 space-y-8">
            
            <!-- Bloque: Datos del Expediente -->
            <div class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden transition-all hover:shadow-md">
                <div class="px-8 py-5 bg-gray-50/50 dark:bg-gray-800/50 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                    <h3 class="font-black text-gray-900 dark:text-white uppercase tracking-wider text-xs flex items-center gap-3">
                        <BuildingLibraryIcon class="w-5 h-5 text-indigo-500" /> 
                        Detalle Técnico del Expediente
                    </h3>
                    <span class="text-[10px] font-black text-indigo-600 bg-indigo-50 dark:bg-indigo-900/30 px-3 py-1 rounded-full uppercase tracking-tighter border border-indigo-100 dark:border-indigo-800">
                        {{ proceso.tipo_proceso?.nombre || 'General' }}
                    </span>
                </div>
                
                <div class="p-8">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-8">
                        <div class="flex flex-col gap-1.5">
                            <span class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">Despacho Judicial</span>
                            <p class="text-sm font-bold text-gray-900 dark:text-gray-100 leading-tight">
                                {{ proceso.juzgado?.nombre || 'Pendiente de asignación' }}
                            </p>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <span class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">Radicado Oficial</span>
                            <p class="text-sm font-mono font-black text-indigo-600 dark:text-indigo-400 tracking-tighter">
                                {{ proceso.radicado || 'SIN NÚMERO ASIGNADO' }}
                            </p>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <span class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">Naturaleza</span>
                            <p class="text-sm font-bold text-gray-700 dark:text-gray-300">
                                {{ asText(proceso.naturaleza) }}
                            </p>
                        </div>

                        <div class="flex flex-col gap-1.5">
                            <span class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">Fecha de Radicación</span>
                            <div class="flex items-center gap-2">
                                <CalendarDaysIcon class="w-4 h-4 text-gray-400" />
                                <p class="text-sm font-bold text-gray-700 dark:text-gray-300">
                                    {{ formatDate(proceso.fecha_radicado) || 'No registrada' }}
                                </p>
                            </div>
                        </div>

                        <div class="md:col-span-2 space-y-2">
                            <span class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">Asunto / Descripción</span>
                            <div class="p-4 bg-gray-50 dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-700">
                                <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed font-medium">
                                    {{ asText(proceso.asunto) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Enlaces de Interés -->
                    <div class="mt-10 pt-8 border-t border-gray-50 dark:border-gray-700 flex flex-wrap gap-4">
                        <a v-if="proceso.ubicacion_drive" :href="proceso.ubicacion_drive" target="_blank" class="inline-flex items-center px-5 py-2.5 bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 rounded-2xl text-xs font-black uppercase hover:bg-blue-100 transition-all shadow-sm">
                            <img src="https://upload.wikimedia.org/wikipedia/commons/1/12/Google_Drive_icon_%282020%29.svg" class="w-4 h-4 mr-2" /> Carpeta Drive
                        </a>
                        <a v-if="proceso.link_expediente" :href="proceso.link_expediente" target="_blank" class="inline-flex items-center px-5 py-2.5 bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-300 rounded-2xl text-xs font-black uppercase hover:bg-indigo-100 transition-all shadow-sm">
                            <GlobeAltIcon class="w-4 h-4 mr-2" /> Expediente Digital
                        </a>
                    </div>
                </div>
            </div>

            <!-- Bloque: Observaciones -->
            <div class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                <div class="px-8 py-5 border-b border-gray-50 dark:border-gray-700 flex items-center gap-3">
                    <ChatBubbleBottomCenterTextIcon class="w-5 h-5 text-indigo-500" />
                    <h3 class="font-black text-gray-900 dark:text-white uppercase tracking-wider text-xs">Observaciones Internas</h3>
                </div>
                <div class="p-8">
                    <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed italic">
                        {{ proceso.observaciones || 'No se han registrado observaciones adicionales para este expediente.' }}
                    </p>
                </div>
            </div>
        </div>

        <!-- COLUMNA DERECHA: CONTACTO Y COMUNICACIÓN (4/12) -->
        <div class="lg:col-span-4 space-y-8">
            
            <!-- Tarjeta: Canales de Comunicación -->
            <div class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-xl overflow-hidden">
                <div class="px-8 py-5 border-b border-gray-50 dark:border-gray-700 flex items-center gap-3">
                    <EnvelopeIcon class="w-5 h-5 text-indigo-500" />
                    <h3 class="font-black text-gray-900 dark:text-white uppercase tracking-wider text-[10px]">Canales de Comunicación</h3>
                </div>
                <div class="p-8 space-y-8">
                    <div class="space-y-4">
                        <div class="p-4 bg-gray-50 dark:bg-gray-900/50 rounded-2xl border border-gray-100 dark:border-gray-700">
                            <span class="text-[9px] font-black text-gray-400 uppercase tracking-widest block mb-1">Correo de Radicación</span>
                            <p class="text-xs font-bold text-gray-700 dark:text-gray-300 truncate" :title="proceso.correo_radicacion">
                                {{ proceso.correo_radicacion || 'No registra' }}
                            </p>
                        </div>
                        
                        <div class="p-4 bg-gray-50 dark:bg-gray-900/50 rounded-2xl border border-gray-100 dark:border-gray-700">
                            <span class="text-[9px] font-black text-gray-400 uppercase tracking-widest block mb-1">Correos de la Entidad</span>
                            <p class="text-xs font-medium text-gray-600 dark:text-gray-400 leading-tight">
                                {{ proceso.correos_juzgado || 'Sin correos adicionales' }}
                            </p>
                        </div>
                    </div>

                    <div class="pt-6 border-t border-gray-50 dark:border-gray-700">
                        <div class="flex items-center gap-3 p-4 bg-indigo-50 dark:bg-indigo-900/20 rounded-2xl border border-indigo-100 dark:border-indigo-800">
                            <div class="p-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm">
                                <ClipboardDocumentCheckIcon class="w-5 h-5 text-indigo-600" />
                            </div>
                            <div>
                                <p class="text-[9px] font-black text-indigo-900 dark:text-indigo-300 uppercase tracking-tighter">Estado de Integridad</p>
                                <p class="text-[11px] font-bold text-indigo-700 dark:text-indigo-400">
                                    {{ proceso.info_incompleta ? 'REVISIÓN REQUERIDA' : 'DATOS COMPLETOS' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Espacio para el widget de "Programar Notificación" que se inyectará desde el padre o se definirá como sub-componente -->
            <slot name="notificaciones" />

        </div>
    </div>
</template>
