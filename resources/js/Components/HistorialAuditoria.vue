<script setup>
import { ShieldCheckIcon, EyeIcon, ArrowDownTrayIcon, PencilSquareIcon, LockClosedIcon, UserIcon, ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/outline';
import { ref, computed } from 'vue';

const props = defineProps({
    eventos: { type: Array, default: () => [] },
});

// --- Paginación Local ---
const itemsPerPage = 6;
const currentPage = ref(1);
const totalPages = computed(() => Math.ceil(props.eventos.length / itemsPerPage));
const paginatedEventos = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    return props.eventos.slice(start, end);
});

const nextPage = () => { if (currentPage.value < totalPages.value) currentPage.value++; };
const prevPage = () => { if (currentPage.value > 1) currentPage.value--; };

const formatDateTime = (dateString) => {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleString('es-CO', { 
        month: 'short', day: 'numeric', 
        hour: '2-digit', minute: '2-digit' 
    });
};

const criticidadClasses = {
    alta: 'bg-red-500 shadow-[0_0_10px_rgba(239,68,68,0.5)]',
    media: 'bg-yellow-500 shadow-[0_0_10px_rgba(234,179,8,0.5)]',
    baja: 'bg-blue-500 shadow-[0_0_10px_rgba(59,130,246,0.5)]',
};

const eventoIconos = {
    'LOGIN_EXITOSO': LockClosedIcon,
    'DOCUMENTO_DESCARGADO': ArrowDownTrayIcon,
    'CUMPLIMIENTO_CORREGIDO': ShieldCheckIcon,
    'DEFAULT': EyeIcon
};

const formatKey = (key) => {
    const maps = {
        'user_id': 'Responsable',
        'abogado_id': 'Abogado',
        'deudor_id': 'Deudor',
        'cooperativa_id': 'Empresa/Cooperativa',
        'juzgado_id': 'Juzgado/Despacho',
        'tipo_proceso_id': 'Tipo de Proceso',
        'especialidad_id': 'Especialidad',
        'radicado': 'N° Radicado',
        'asunto': 'Asunto',
        'monto_total': 'Cuantía/Monto',
        'etapa_procesal_id': 'Etapa Actual',
        'etapa_actual_id': 'Etapa Actual',
        'responsable_revision_id': 'Supervisor de Revisión',
        'fecha_proxima_revision': 'Próxima Revisión',
        'fecha_radicado': 'Fecha Radicación',
        'link_expediente': 'Link Expediente',
        'ubicacion_drive': 'Carpeta Drive',
        'correo_radicacion': 'Correo Radicación',
    };
    return maps[key] || key.replace(/_/g, ' ').toUpperCase();
};

const getIcon = (evento) => eventoIconos[evento] || eventoIconos['DEFAULT'];
</script>

<template>
    <div class="flex flex-col h-full">
        <div class="relative border-l border-white/10 ml-3 flex-grow">
            <div v-if="!eventos || eventos.length === 0" class="pl-8 pb-4">
                <p class="text-xs text-gray-500 italic uppercase font-black tracking-widest">Sin registros técnicos</p>
            </div>
            <ol v-else class="space-y-8">
                <li v-for="evento in paginatedEventos" :key="evento.id" class="relative pl-8 group">
                    <span :class="['absolute -left-[4.5px] top-1.5 h-2 w-2 rounded-full ring-4 ring-gray-900', criticidadClasses[evento.criticidad]]"></span>
                    
                    <div class="space-y-3">
                        <div class="flex items-center gap-3">
                            <div class="p-1.5 bg-white/5 rounded-lg border border-white/10">
                                <component :is="getIcon(evento.evento)" class="h-3.5 w-3.5 text-gray-400" />
                            </div>
                            <p class="text-[11px] font-black text-white uppercase tracking-wide leading-tight">{{ evento.descripcion_breve }}</p>
                        </div>
                        
                        <div v-if="evento.detalle_nuevo && Object.keys(evento.detalle_nuevo).length > 0" class="overflow-hidden rounded-xl border border-white/5 bg-black/20">
                            <table class="w-full text-[9px] table-auto">
                                <thead class="bg-white/5 border-b border-white/5 text-gray-400">
                                    <tr>
                                        <th class="px-2 py-1 text-left font-black uppercase tracking-tighter w-1/4">Campo</th>
                                        <th class="px-2 py-1 text-left font-black uppercase tracking-tighter">Cambios</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    <tr v-for="(val, key) in evento.detalle_nuevo" :key="key">
                                        <td class="px-2 py-1.5 font-bold text-gray-500 uppercase" :title="formatKey(key)">{{ formatKey(key) }}</td>
                                        <td class="px-2 py-1.5 text-white/80 whitespace-normal break-words">
                                            <span class="text-red-400/70 line-through mr-1">{{ evento.detalle_anterior ? (evento.detalle_anterior[key] ?? '—') : '—' }}</span>
                                            <span class="text-green-400 font-bold">→ {{ val ?? '—' }}</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="flex items-center justify-between gap-4 pt-1">
                            <div class="flex items-center gap-1.5">
                                <div class="w-4 h-4 rounded-full bg-indigo-500/20 flex items-center justify-center border border-indigo-500/30">
                                    <UserIcon class="w-2.5 h-2.5 text-indigo-400" />
                                </div>
                                <span class="text-[9px] font-bold text-gray-400 uppercase tracking-tighter">
                                    {{ evento.usuario ? evento.usuario.name : 'Sistema / Automático' }} • <span class="opacity-60">{{ evento.direccion_ip }}</span>
                                </span>
                            </div>
                            <time class="text-[9px] font-black text-gray-600 uppercase tracking-widest">{{ formatDateTime(evento.created_at) }}</time>
                        </div>
                    </div>
                </li>
            </ol>
        </div>

        <!-- Controles de Paginación -->
        <div v-if="totalPages > 1" class="mt-8 pt-6 border-t border-white/5 flex items-center justify-between">
            <span class="text-[9px] font-black text-gray-500 uppercase tracking-[0.2em]">Pág {{ currentPage }} / {{ totalPages }}</span>
            <div class="flex gap-2">
                <button @click="prevPage" :disabled="currentPage === 1" class="p-1.5 bg-white/5 rounded-lg border border-white/10 text-gray-400 hover:text-white disabled:opacity-20 transition-all">
                    <ChevronLeftIcon class="w-4 h-4" />
                </button>
                <button @click="nextPage" :disabled="currentPage === totalPages" class="p-1.5 bg-white/5 rounded-lg border border-white/10 text-gray-400 hover:text-white disabled:opacity-20 transition-all">
                    <ChevronRightIcon class="w-4 h-4" />
                </button>
            </div>
        </div>
    </div>
</template>
