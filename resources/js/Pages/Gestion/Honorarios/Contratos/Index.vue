<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import TextInput from '@/Components/TextInput.vue'; // <-- Importar TextInput

// Usar debounce para evitar búsquedas en cada tecla
import { debounce } from 'lodash';

const props = defineProps({
    contratos: { type: Object, default: () => ({ data: [] }) },
    filters:   { type: Object, default: () => ({ q: '', estado: '' }) },
    stats:     { type: Object, default: () => ({ activeValue: 0, activeCount: 0, closedCount: 0 }) },
})

const q = ref(props.filters?.q ?? '')
const estado = ref(props.filters?.estado ?? '')

const buscarContratos = () => {
    // Ajustar la ruta si es necesario, parece que era 'gestion.honorarios.index' antes.
    // Asegúrate de que la ruta 'honorarios.contratos.index' exista y sea la correcta.
    router.get(route('honorarios.contratos.index'),
        { q: q.value, estado: estado.value },
        { preserveState: true, preserveScroll: true, replace: true } // Añadir replace: true para no llenar el historial
    )
}


const debouncedSearch = debounce(buscarContratos, 300);

watch(q, debouncedSearch); // Buscar con debounce al cambiar 'q'
watch(estado, buscarContratos); // Buscar inmediatamente al cambiar 'estado'

const limpiarFiltros = () => {
    q.value = ''
    estado.value = ''
    // No es necesario llamar a buscarContratos aquí si los watchers ya lo hacen.
}

const fmtMoney = (n) => new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(Number(n || 0))

// ===== FUNCIÓN CORREGIDA =====
const fmtDateShort = (d) => {
    if (!d) return 'N/A';
    try {
        // Intentar parsear directamente la fecha que viene del backend
        const dateObj = new Date(d);
        // Verificar si la fecha es válida
        if (isNaN(dateObj.getTime())) {
             // Si falla, intentar reemplazar espacio por T (formato YYYY-MM-DD HH:MM:SS)
             const fallbackDateObj = new Date(String(d).replace(' ', 'T'));
             if (isNaN(fallbackDateObj.getTime())) return 'Fecha Inválida';
             // Usar el objeto de fecha del fallback si es válido
             return fallbackDateObj.toLocaleDateString('es-CO', { month: 'short', day: 'numeric', year: 'numeric', timeZone: 'UTC' });
        }
        // Usar el objeto de fecha original si es válido
        return dateObj.toLocaleDateString('es-CO', { month: 'short', day: 'numeric', year: 'numeric', timeZone: 'UTC' });
    } catch (e) {
        console.error("Error parsing date:", d, e); // Loguear error para depuración
        return 'Fecha Inválida';
    }
}
// =============================


const estadoClass = (e) => {
    const s = String(e || '').toUpperCase()
    if (s === 'EN_MORA') return 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300'
    if (s === 'CERRADO') return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
    if (s === 'PAGOS_PENDIENTES') return 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300'
    // Por defecto, considerar 'ACTIVO' y otros como estado normal/positivo
    return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'
}


const calcularProgreso = (contrato) => {
    // Asegurarse de que monto_total y total_pagado sean números
    const total = Number(contrato.monto_total || 0);
    const pagado = Number(contrato.total_pagado || 0);

    // Evitar división por cero y manejar contratos sin monto (ej. LITIS puro sin resolver)
    if (total === 0) {
         // Si no hay monto total pero hay pagos (abonos generales?), mostrar 0% o 100%?
         // Por ahora, si no hay monto, el progreso es 0%
         return 0;
    }

    // Calcular porcentaje y asegurarse que esté entre 0 y 100
    const progreso = (pagado / total) * 100;
    return Math.max(0, Math.min(100, progreso));
}
</script>

<template>
    <Head title="Honorarios · Contratos" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Gestión de Contratos de Honorarios
                </h2>
                <Link :href="route('honorarios.contratos.create')"
                      class="inline-flex items-center gap-2 px-4 py-2 rounded-md text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                    Nuevo Contrato
                </Link>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- KPIs Renovados -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-5 flex items-start gap-4">
                        <div class="bg-emerald-100 dark:bg-emerald-900/50 p-3 rounded-lg flex-shrink-0"><svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-emerald-600 dark:text-emerald-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg></div>
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Valor Total Activo</div>
                            <div class="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ fmtMoney(stats.activeValue) }}</div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-5 flex items-start gap-4">
                        <div class="bg-blue-100 dark:bg-blue-900/50 p-3 rounded-lg flex-shrink-0"><svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600 dark:text-blue-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg></div>
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Contratos Activos</div>
                            <div class="mt-1 text-2xl font-bold text-blue-600 dark:text-blue-400">{{ stats.activeCount }}</div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-5 flex items-start gap-4">
                        <div class="bg-gray-100 dark:bg-gray-700 p-3 rounded-lg flex-shrink-0"><svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-600 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" /></svg></div>
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Contratos Cerrados</div>
                            <div class="mt-1 text-2xl font-bold text-gray-600 dark:text-gray-400">{{ stats.closedCount }}</div>
                        </div>
                    </div>
                </div>

                <!-- Filtros y Lista de Contratos -->
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                    <div class="p-4 border-b dark:border-gray-700 flex flex-col md:flex-row items-center gap-4">
                        <div class="relative w-full md:flex-grow">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                            </div>
                            <TextInput
                                v-model="q"
                                type="text"
                                placeholder="Buscar por ID, nombre de cliente o radicado..."
                                class="w-full pl-10 pr-4 py-2 rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                        <select v-model="estado" class="w-full md:w-auto rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">Todos los Estados</option>
                            <option value="ACTIVO">Activo</option>
                            <option value="PAGOS_PENDIENTES">Pagos Pendientes</option>
                            <option value="EN_MORA">En Mora</option>
                            <option value="CERRADO">Cerrado</option>
                        </select>
                        <button v-if="q || estado" @click="limpiarFiltros" class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 flex-shrink-0">Limpiar</button>
                    </div>

                    <!-- Lista de Tarjetas de Contrato -->
                    <div v-if="contratos.data.length > 0" class="divide-y divide-gray-200 dark:divide-gray-700">
                        <div v-for="c in contratos.data" :key="c.id" class="p-4 hover:bg-gray-50 dark:hover:bg-gray-700/40 transition-colors duration-150 ease-in-out">
                            <div class="flex flex-col md:flex-row items-start md:items-center gap-4">
                                <!-- CORRECCIÓN: Comentarios en sintaxis HTML -->
                                <div class="flex-1 min-w-0"> <!-- Añadido min-w-0 para correcto truncado -->
                                    <div class="flex items-center gap-3 flex-wrap"> <!-- Añadido flex-wrap -->
                                        <Link :href="route('honorarios.contratos.show', c.id)" class="text-indigo-600 dark:text-indigo-400 font-bold hover:underline truncate" :title="c.persona_nombre">
                                            {{ c.persona_nombre }}
                                        </Link>
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold flex-shrink-0" :class="estadoClass(c.estado)">
                                            {{ c.estado.replace('_', ' ') }}
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 flex items-center gap-2 flex-wrap"> <!-- Añadido flex-wrap -->
                                        <span>Contrato #{{ c.id }}</span>
                                        <span class="text-gray-300 dark:text-gray-600">|</span>
                                        <span>Creado el {{ fmtDateShort(c.created_at) }}</span>
                                        
                                        <!-- ===== INICIO: MOSTRAR CASO ID ===== -->
                                        <template v-if="c.caso_id">
                                            <span class="text-gray-300 dark:text-gray-600">|</span>
                                            <Link :href="route('casos.show', c.caso_id)" class="text-green-600 dark:text-green-400 hover:underline flex items-center gap-1 text-xs" title="Ver caso asociado">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                                                Caso #{{ c.caso_id }}
                                            </Link>
                                        </template>
                                        <!-- ===== FIN: MOSTRAR CASO ID ===== -->
                                        
                                        <!-- ===== MODIFICACIÓN AQUÍ ===== -->
                                        <template v-if="c.proceso">
                                            <span class="text-gray-300 dark:text-gray-600">|</span>
                                            <Link :href="route('procesos.show', c.proceso.id)" class="text-blue-600 dark:text-blue-400 hover:underline flex items-center gap-1 text-xs" title="Ver radicado asociado">
                                                 <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" ><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" /></svg>
                                                Radicado #{{ c.proceso.numero }}
                                            </Link>
                                        </template>
                                        <!-- ============================= -->
                                    </p>
                                </div>
                                <div class="w-full md:w-auto md:min-w-[200px] flex-shrink-0"> <!-- Ajustes de ancho -->
                                    <div class="flex justify-between items-center text-sm mb-1 gap-2">
                                        <span class="font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ fmtMoney(c.total_pagado || 0) }}</span>
                                        <span class="text-gray-500 whitespace-nowrap">{{ fmtMoney(c.monto_total) }}</span>
                                    </div>
                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden"> <!-- Añadido overflow-hidden -->
                                        <div class="bg-emerald-500 h-2 rounded-full transition-all duration-300 ease-out" :style="{ width: calcularProgreso(c) + '%' }"></div>
                                    </div>
                                </div>
                                <div class="flex-shrink-0 self-start md:self-center"> <!-- Alinear botón -->
                                    <Link :href="route('honorarios.contratos.show', c.id)" class="inline-flex items-center px-3 py-1.5 rounded-md text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 transition-colors">
                                         <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" ><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                        Ver
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Estado Vacío -->
                    <div v-else class="text-center py-16 text-gray-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" /></svg>
                        <h3 class="mt-2 text-lg font-medium text-gray-900 dark:text-gray-100">No se encontraron contratos</h3>
                        <p class="mt-1 text-sm">Intenta ajustar los filtros o crea un nuevo contrato.</p>
                    </div>

                    <!-- Paginación -->
                    <div v-if="contratos.links && contratos.links.length > 3" class="p-4 border-t dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                         <Pagination :links="contratos.links" />
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
