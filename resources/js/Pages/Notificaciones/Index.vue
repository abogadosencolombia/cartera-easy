<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import Pagination from '@/Components/Pagination.vue';
import Modal from '@/Components/Modal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

// --- IMPORTACIÓN DE ICONOS ---
import {
    BellAlertIcon, InboxIcon, EyeIcon, CheckCircleIcon, CheckBadgeIcon,
    ClockIcon, InformationCircleIcon, BriefcaseIcon,
    DocumentTextIcon, ExclamationTriangleIcon, BanknotesIcon, ScaleIcon,
    ChevronRightIcon, FunnelIcon, XMarkIcon, SparklesIcon, TrashIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    notificaciones: Object,
    filtros: Object,
    tipos_alerta: Array,
});

const user = usePage().props.auth.user;

// --- Estado de Modales ---
const confirmingAllDeletion = ref(false);
const confirmingSingleDeletion = ref(false);
const notifToDelete = ref(null);

// --- Lógica de Acciones ---
const marcarComoLeida = (notificacionId) => {
    router.patch(route('notificaciones.leer', notificacionId), {}, {
        preserveScroll: true,
        only: ['notificaciones'],
    });
};

const marcarComoAtendida = (notificacionId) => {
    router.patch(route('notificaciones.atender', notificacionId), {}, {
        preserveScroll: true,
        only: ['notificaciones'],
    });
};

// --- NUEVA FUNCIÓN: ELIMINAR ---
const eliminarNotificacion = (notificacionId) => {
    notifToDelete.value = notificacionId;
    confirmingSingleDeletion.value = true;
};

const confirmSingleDelete = () => {
    if (!notifToDelete.value) return;
    router.delete(route('notificaciones.destroy', notifToDelete.value), {
        preserveScroll: true,
        only: ['notificaciones'],
        onSuccess: () => {
            confirmingSingleDeletion.value = false;
            notifToDelete.value = null;
        }
    });
};

const limpiarTodas = () => {
    confirmingAllDeletion.value = true;
};

const confirmClearAll = () => {
    router.delete(route('notificaciones.clearAll', localFiltros.value), {
        preserveScroll: true,
        onSuccess: () => {
            confirmingAllDeletion.value = false;
        }
    });
};

// --- Lógica de Filtros ---
const localFiltros = ref({
    leido: props.filtros.leido || '',
    tipo: props.filtros.tipo || '',
    user_id: props.filtros.user_id || '',
});

const setFiltro = (campo, valor) => {
    localFiltros.value[campo] = valor;
};

watch(localFiltros, (newFilters) => {
    router.get(route('notificaciones.index'), newFilters, {
        preserveState: true,
        replace: true,
    });
}, { deep: true });


// --- CONFIGURACIÓN VISUAL (ICONOS Y COLORES) ---
const estilosVisuales = {
    'revision_proxima': { icon: ScaleIcon, color: 'text-indigo-600', bgIcon: 'bg-indigo-100', border: 'border-indigo-500', shadow: 'shadow-indigo-100', label: 'Gestión Legal' },
    'revision_hoy':     { icon: ScaleIcon, color: 'text-amber-600', bgIcon: 'bg-amber-100', border: 'border-amber-500', shadow: 'shadow-amber-100', label: 'Revisión Prioritaria' },
    'revision_vencida': { icon: ExclamationTriangleIcon, color: 'text-rose-600', bgIcon: 'bg-rose-100', border: 'border-rose-500', shadow: 'shadow-rose-100', label: 'Alerta Crítica' },
    'pago_proximo':     { icon: BanknotesIcon, color: 'text-emerald-600', bgIcon: 'bg-emerald-100', border: 'border-emerald-500', shadow: 'shadow-emerald-100', label: 'Cobro' },
    'pago_hoy':         { icon: BanknotesIcon, color: 'text-teal-600', bgIcon: 'bg-teal-100', border: 'border-teal-500', shadow: 'shadow-teal-100', label: 'Pago Hoy' },
    'pago_vencido':     { icon: BanknotesIcon, color: 'text-red-600', bgIcon: 'bg-red-100', border: 'border-red-500', shadow: 'shadow-red-100', label: 'Mora' },
    'vencimiento':      { icon: ClockIcon, color: 'text-rose-600', bgIcon: 'bg-rose-100', border: 'border-rose-500', shadow: 'shadow-rose-100', label: 'Vence' },
    'mora':             { icon: BanknotesIcon, color: 'text-red-600', bgIcon: 'bg-red-100', border: 'border-red-500', shadow: 'shadow-red-100', label: 'En Mora' },
    'alerta_manual':    { icon: BellAlertIcon, color: 'text-violet-600', bgIcon: 'bg-violet-100', border: 'border-violet-500', shadow: 'shadow-violet-100', label: 'Manual' },
    'default':          { icon: InformationCircleIcon, color: 'text-slate-500', bgIcon: 'bg-slate-100', border: 'border-slate-400', shadow: 'shadow-slate-100', label: 'Notificación' }
};

const getAlertaMeta = (notificacion) => {
    if (!notificacion || !notificacion.tipo) return estilosVisuales['default'];
    const tipoBD = notificacion.tipo; 
    const mensaje = (notificacion.mensaje || '').toLowerCase();

    if (tipoBD === 'vencimiento' || tipoBD === 'mora') {
        if (mensaje.includes('cuota') || mensaje.includes('pago') || mensaje.includes('cobro') || mensaje.includes('honorarios')) {
            if (mensaje.includes('mora') || mensaje.includes('no pagó') || mensaje.includes('vencid')) return estilosVisuales['pago_vencido'];
            if (mensaje.includes('hoy')) return estilosVisuales['pago_hoy'];
            return estilosVisuales['pago_proximo'];
        }
        if (mensaje.includes('revisión') || mensaje.includes('proceso') || mensaje.includes('radicado')) {
            if (mensaje.includes('vencida') || mensaje.includes('atrasada')) return estilosVisuales['revision_vencida'];
            if (mensaje.includes('hoy') || mensaje.includes('atención')) return estilosVisuales['revision_hoy'];
            return estilosVisuales['revision_proxima'];
        }
    }
    return estilosVisuales[tipoBD] || estilosVisuales['default'];
};

const formatTime = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleTimeString('es-CO', { hour: 'numeric', minute: '2-digit', hour12: true }).toLowerCase();
};

const groupedNotifications = computed(() => {
    const groups = { Hoy: [], Ayer: [], 'Esta Semana': [], 'Anteriores': [] };
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime();
    const yesterday = today - 86400000;
    const weekAgo = today - (86400000 * 7);

    (props.notificaciones?.data || []).forEach(n => {
        const d = new Date(n.created_at);
        const t = new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime();
        if (t === today) groups.Hoy.push(n);
        else if (t === yesterday) groups.Ayer.push(n);
        else if (t > weekAgo) groups['Esta Semana'].push(n);
        else groups['Anteriores'].push(n);
    });
    return Object.entries(groups).filter(g => g[1].length).map(([title, notifications]) => ({ title, notifications }));
});

const esNotificacionDeSistema = (notificacion) => {
    return notificacion.type && notificacion.type.startsWith('App\\Notifications');
};
</script>

<template>
    <Head title="Centro de Notificaciones" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <h2 class="text-2xl font-bold text-blue-500 dark:text-white tracking-tight">
                            Hola, {{ user.name.split(' ')[0] }}
                        </h2>
                        <SparklesIcon class="h-5 w-5 text-amber-400" />
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Gestiona tus avisos y alertas pendientes.
                    </p>
                </div>
                <div class="flex-shrink-0">
                    <button 
                        @click="limpiarTodas"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl text-sm font-bold shadow-lg transition-all"
                    >
                        <TrashIcon class="w-4 h-4" />
                        Borrar todo
                    </button>
                </div>
            </div>
        </template>

        <div class="min-h-screen bg-gray-50/80 dark:bg-gray-900 py-8">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <!-- BARRA DE FILTROS -->
                <div class="sticky top-4 z-20 mb-8 bg-white/80 dark:bg-gray-800/80 backdrop-blur-md p-2 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 flex flex-wrap items-center justify-between gap-3">
                    
                    <div class="flex flex-wrap items-center gap-1">
                        <span class="text-xs font-bold text-gray-400 uppercase tracking-wider px-3 mr-1 flex items-center gap-1">
                            <FunnelIcon class="h-3 w-3" /> Filtros
                        </span>

                        <button @click="setFiltro('leido', '')" 
                            class="px-3 py-1.5 rounded-xl text-xs font-medium transition-all duration-200"
                            :class="localFiltros.leido === '' ? 'bg-gray-900 text-white shadow-md' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700'">
                            Todas
                        </button>
                        <button @click="setFiltro('leido', 'no')" 
                            class="px-3 py-1.5 rounded-xl text-xs font-medium transition-all duration-200 relative"
                            :class="localFiltros.leido === 'no' ? 'bg-gray-900 text-white shadow-md' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700'">
                            No leídas
                            <span v-if="localFiltros.leido !== 'no'" class="absolute top-1 right-1 h-1.5 w-1.5 rounded-full bg-red-500"></span>
                        </button>

                        <div class="w-px h-4 bg-gray-200 dark:bg-gray-700 mx-2 hidden sm:block"></div>

                        <button @click="setFiltro('tipo', 'vencimiento')" 
                             class="px-3 py-1.5 rounded-xl text-xs font-medium transition-all duration-200 flex items-center gap-1.5"
                             :class="localFiltros.tipo === 'vencimiento' ? 'bg-indigo-100 text-indigo-700 border border-indigo-200' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700'">
                             <ScaleIcon class="w-3.5 h-3.5" /> Legal
                        </button>
                        <button @click="setFiltro('tipo', 'mora')" 
                             class="px-3 py-1.5 rounded-xl text-xs font-medium transition-all duration-200 flex items-center gap-1.5"
                             :class="localFiltros.tipo === 'mora' ? 'bg-emerald-100 text-emerald-700 border border-emerald-200' : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700'">
                             <BanknotesIcon class="w-3.5 h-3.5" /> Pagos
                        </button>
                    </div>

                    <button v-if="localFiltros.leido || localFiltros.tipo" 
                        @click="localFiltros = { leido: '', tipo: '' }"
                        class="px-3 py-1.5 text-xs font-medium text-gray-500 hover:text-red-500 flex items-center gap-1 transition-colors">
                        <XMarkIcon class="w-3 h-3" /> Limpiar filtros
                    </button>
                </div>

                <!-- LISTADO -->
                <div v-if="notificaciones.data && notificaciones.data.length > 0" class="space-y-10 pb-20">
                    <div v-for="group in groupedNotifications" :key="group.title" class="relative">
                        
                        <div class="flex items-center mb-5 ml-2">
                            <div class="h-2 w-2 rounded-full bg-gray-300 dark:bg-gray-600 mr-3"></div>
                            <h3 class="text-xs font-bold uppercase tracking-widest text-gray-400">
                                {{ group.title }}
                            </h3>
                        </div>

                        <div class="grid grid-cols-1 gap-4">
                            <transition-group 
                                enter-active-class="transition ease-out duration-300 delay-75"
                                enter-from-class="opacity-0 translate-y-4"
                                enter-to-class="opacity-100 translate-y-0"
                                leave-active-class="transition ease-in duration-200"
                                leave-from-class="opacity-100 translate-y-0"
                                leave-to-class="opacity-0 translate-y-4">
                                
                                <div v-for="notif in group.notifications" :key="notif.id" 
                                    class="group relative overflow-hidden rounded-2xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-[0_2px_8px_-2px_rgba(0,0,0,0.05)] hover:shadow-[0_8px_16px_-4px_rgba(0,0,0,0.1)] transition-all duration-300 hover:-translate-y-0.5">
                                    
                                    <!-- SISTEMA -->
                                    <template v-if="esNotificacionDeSistema(notif)">
                                        <div class="flex p-5 gap-5" :class="{'opacity-70 bg-gray-50/50': notif.read_at}">
                                            <div class="absolute left-0 top-0 bottom-0 w-1 bg-gradient-to-b from-gray-200 to-transparent group-hover:from-indigo-400 transition-all duration-500"></div>

                                            <div class="flex-shrink-0">
                                                <div class="h-10 w-10 rounded-xl flex items-center justify-center bg-gray-50 border border-gray-200 text-gray-500 group-hover:bg-white group-hover:shadow-sm transition-all">
                                                    <BriefcaseIcon class="h-5 w-5" />
                                                </div>
                                            </div>
                                            <div class="flex-grow min-w-0">
                                                <div class="flex justify-between items-start">
                                                    <h4 class="text-sm font-bold text-gray-900 dark:text-white truncate">
                                                        {{ notif.data.title }}
                                                    </h4>
                                                    <span class="text-[10px] font-medium text-gray-400 bg-gray-50 px-2 py-0.5 rounded border border-gray-100">
                                                        {{ formatTime(notif.created_at) }}
                                                    </span>
                                                </div>
                                                <p class="text-sm text-gray-600 dark:text-gray-300 mt-1 line-clamp-2">
                                                    {{ notif.data.message }}
                                                </p>
                                                <div class="mt-3 flex gap-4 opacity-60 group-hover:opacity-100 transition-opacity">
                                                    <button v-if="!notif.read_at" @click="marcarComoLeida(notif.id)" class="text-xs font-semibold text-indigo-600 hover:underline">Marcar leída</button>
                                                    <button @click="eliminarNotificacion(notif.id)" class="text-xs font-semibold text-red-500 hover:underline flex items-center gap-1">
                                                        <TrashIcon class="w-3 h-3" /> Eliminar
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- CASOS -->
                                    <template v-else>
                                        <div class="absolute left-0 top-0 bottom-0 w-1 transition-all duration-300"
                                             :class="getAlertaMeta(notif).color.replace('text-', 'bg-')"></div>

                                        <div class="flex p-5 gap-4 sm:gap-6 items-start" 
                                             :class="[notif.leido ? 'bg-gray-50/50 grayscale-[0.3]' : 'bg-white']">
                                            
                                            <div class="flex-shrink-0 relative mt-1">
                                                <div class="h-12 w-12 rounded-2xl flex items-center justify-center transition-all duration-300 shadow-sm group-hover:scale-110"
                                                     :class="[getAlertaMeta(notif).bgIcon, getAlertaMeta(notif).color]">
                                                    <component :is="getAlertaMeta(notif).icon" class="h-6 w-6" stroke-width="2" />
                                                </div>
                                            </div>

                                            <div class="flex-grow min-w-0">
                                                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-1 gap-2">
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-[10px] font-extrabold uppercase tracking-wider px-2 py-0.5 rounded border bg-opacity-50"
                                                              :class="[getAlertaMeta(notif).bgIcon, getAlertaMeta(notif).border, getAlertaMeta(notif).color]">
                                                            {{ getAlertaMeta(notif).label }}
                                                        </span>
                                                        <span v-if="!notif.leido" class="relative flex h-2 w-2">
                                                          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75"></span>
                                                          <span class="relative inline-flex rounded-full h-2 w-2 bg-indigo-500"></span>
                                                        </span>
                                                    </div>
                                                    <span class="text-xs font-mono text-gray-400 hidden sm:block">
                                                        {{ formatTime(notif.created_at) }}
                                                    </span>
                                                </div>

                                                <p class="text-sm leading-relaxed mb-3 transition-colors" 
                                                   :class="notif.leido ? 'text-gray-500 font-normal' : 'text-gray-800 dark:text-gray-100 font-medium'">
                                                    {{ notif.mensaje }}
                                                </p>

                                                <div class="flex items-center justify-between pt-3 border-t border-dashed border-gray-100 dark:border-gray-700 transition-opacity duration-300 sm:opacity-50 sm:group-hover:opacity-100">
                                                    
                                                    <Link v-if="notif.caso_id" :href="route('casos.show', notif.caso_id)" 
                                                          class="text-xs font-bold text-gray-400 hover:text-indigo-600 transition-colors flex items-center gap-1">
                                                        VER CASO <ChevronRightIcon class="h-3 w-3" />
                                                    </Link>
                                                    <Link v-else-if="notif.proceso_id" :href="route('procesos.show', notif.proceso_id)" 
                                                          class="text-xs font-bold text-gray-400 hover:text-indigo-600 transition-colors flex items-center gap-1">
                                                        VER RADICADO <ChevronRightIcon class="h-3 w-3" />
                                                    </Link>
                                                    <span v-else class="text-xs text-gray-300 italic">Notificación general</span>

                                                    <div class="flex items-center gap-2">
                                                        <button @click="eliminarNotificacion(notif.id)" 
                                                                title="Eliminar notificación"
                                                                class="h-8 w-8 flex items-center justify-center rounded-full text-gray-400 hover:text-red-600 hover:bg-red-50 transition-all">
                                                            <TrashIcon class="h-4 w-4" />
                                                        </button>

                                                        <button v-if="!notif.leido" 
                                                                @click="marcarComoLeida(notif.id)" 
                                                                title="Archivar"
                                                                class="h-8 w-8 flex items-center justify-center rounded-full text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 transition-all">
                                                            <EyeIcon class="h-4 w-4" />
                                                        </button>

                                                        <button v-if="!notif.atendida_en" 
                                                                @click="marcarComoAtendida(notif.id)" 
                                                                title="Marcar Resuelto"
                                                                class="h-8 px-3 flex items-center gap-1 rounded-full text-xs font-bold text-gray-400 hover:text-white hover:bg-emerald-500 transition-all border border-gray-200 hover:border-transparent">
                                                            <CheckCircleIcon class="h-4 w-4" />
                                                            <span class="hidden sm:inline">HECHO</span>
                                                        </button>

                                                        <span v-if="notif.atendida_en" class="flex items-center gap-1 text-xs font-bold text-emerald-600 px-2 py-1 bg-emerald-50 rounded-md">
                                                            <CheckBadgeIcon class="h-4 w-4" /> RESUELTA
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </transition-group>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="flex flex-col items-center justify-center min-h-[50vh] text-center">
                    <div class="relative mb-8 group">
                        <div class="absolute inset-0 bg-gradient-to-r from-indigo-200 to-purple-200 rounded-full blur-2xl opacity-20 group-hover:opacity-30 transition-opacity duration-500"></div>
                        <div class="relative bg-white dark:bg-gray-800 p-8 rounded-[2rem] shadow-xl border border-gray-50 dark:border-gray-700 transform transition-transform duration-500 group-hover:scale-105">
                            <InboxIcon class="h-20 w-20 text-indigo-300 dark:text-indigo-700" stroke-width="0.8" />
                        </div>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-3">Estás totalmente al día</h3>
                    <p class="text-gray-500 dark:text-gray-400 max-w-sm mx-auto leading-relaxed">
                        No hay notificaciones con los filtros seleccionados.
                    </p>
                </div>

                <!-- Pagination -->
                <div v-if="notificaciones.data && notificaciones.data.length > 0" class="flex justify-center mt-8 pb-10">
                    <Pagination :links="notificaciones.links" />
                </div>
            </div>
        </div>

        <!-- MODAL CONFIRMAR BORRAR TODO -->
        <Modal :show="confirmingAllDeletion" @close="confirmingAllDeletion = false">
            <div class="p-6">
                <div class="flex items-center gap-3 text-red-600 mb-4">
                    <ExclamationTriangleIcon class="h-8 w-8" />
                    <h2 class="text-lg font-bold">¿Eliminar todas las notificaciones?</h2>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Esta acción eliminará permanentemente todos tus avisos y alertas. 
                    <span class="font-bold">No se puede deshacer.</span>
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="confirmingAllDeletion = false"> Cancelar </SecondaryButton>
                    <DangerButton @click="confirmClearAll"> Sí, borrar todo </DangerButton>
                </div>
            </div>
        </Modal>

        <!-- MODAL CONFIRMAR BORRAR UNA -->
        <Modal :show="confirmingSingleDeletion" @close="confirmingSingleDeletion = false">
            <div class="p-6">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Eliminar notificación</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    ¿Estás seguro de que deseas eliminar este aviso?
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton @click="confirmingSingleDeletion = false"> Cancelar </SecondaryButton>
                    <DangerButton @click="confirmSingleDelete"> Eliminar </DangerButton>
                </div>
            </div>
        </Modal>

    </AuthenticatedLayout>
</template>

<style scoped>
::-webkit-scrollbar { width: 8px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 20px; }
</style>