<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { debounce, pickBy } from 'lodash';

// --- Iconos para una UI más rica ---
import { UserGroupIcon, BanknotesIcon, FolderOpenIcon, BuildingOffice2Icon, TrophyIcon, ChevronDownIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    filters: Object,
    rows: Array,
});

// --- Estado de Filtros y UI ---
const q = ref(props.filters?.q ?? '');
const sort = ref(props.filters?.sort ?? 'total_recovered');
const dir = ref(props.filters?.dir ?? 'desc');
const openRowId = ref(null);

// --- Lógica de Filtros Automáticos ---
watch([q, sort, dir], debounce(() => {
    router.get(route('admin.gestores.index'), pickBy({ 
        q: q.value, 
        sort: sort.value, 
        dir: dir.value 
    }), { 
        preserveState: true, 
        replace: true, 
        only: ['rows', 'filters'] 
    });
}, 500));

// --- Prop Computada Segura ---
// Garantiza que `rows` siempre sea un array para evitar errores en el template.
const safeRows = computed(() => props.rows || []);

// --- Datos Calculados para KPIs ---
const totals = computed(() => {
    // Usamos safeRows aquí también para consistencia
    const totalRecovered = safeRows.value.reduce((sum, row) => sum + (row.total_recovered || 0), 0);
    const totalCasos = safeRows.value.reduce((sum, row) => sum + (row.casos_count || 0), 0);
    const coopIds = new Set();
    safeRows.value.forEach(row => (row.cooperativas || []).forEach(coop => coopIds.add(coop.id)));
    return { totalRecovered, totalCasos, totalCoops: coopIds.size };
});

// --- Funciones de Ayuda ---
const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(value || 0);
};

// Determina si las medallas de ranking deben mostrarse
const showRankingMedals = computed(() => {
    return sort.value === 'total_recovered' && dir.value === 'desc' && q.value === '';
});
</script>

<template>
    <Head title="Reporte de Gestores" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight flex items-center">
                    <TrophyIcon class="h-6 w-6 mr-3 text-indigo-500" />
                    Dashboard de Rendimiento
                </h2>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                
                <!-- PANEL UNIFICADO DE RENDIMIENTO -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-xl border dark:border-gray-700">
                    
                    <!-- KPIs Integrados -->
                    <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-6 border-b border-gray-200 dark:border-gray-700">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-green-100 dark:bg-green-900/50 p-3 rounded-full">
                                <BanknotesIcon class="h-7 w-7 text-green-600 dark:text-green-400" />
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Recuperado</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ formatCurrency(totals.totalRecovered) }}</p>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-indigo-100 dark:bg-indigo-900/50 p-3 rounded-full">
                                <FolderOpenIcon class="h-7 w-7 text-indigo-600 dark:text-indigo-400" />
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Casos Asignados</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ totals.totalCasos }}</p>
                            </div>
                        </div>
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-orange-100 dark:bg-orange-900/50 p-3 rounded-full">
                                <BuildingOffice2Icon class="h-7 w-7 text-orange-600 dark:text-orange-400" />
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Cooperativas Atendidas</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ totals.totalCoops }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Cabecera de la lista con filtros -->
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                            <div class="md:col-span-1">
                                <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Filtrar por nombre</label>
                                <input v-model="q" id="search" type="text" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="Buscar abogado...">
                            </div>
                            <div>
                                <label for="sort" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Ordenar por</label>
                                <select v-model="sort" id="sort" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="total_recovered">Monto recuperado</option>
                                    <option value="name">Nombre (A-Z)</option>
                                </select>
                            </div>
                            <div>
                                <label for="dir" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Dirección</label>
                                <select v-model="dir" id="dir" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="desc">Descendente</option>
                                    <option value="asc">Ascendente</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- LISTA DE RESULTADOS -->
                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                        <div v-if="safeRows.length === 0" class="text-center py-16 text-gray-500 dark:text-gray-400">
                            <UserGroupIcon class="h-12 w-12 mx-auto text-gray-400" />
                            <p class="mt-2">No se encontraron gestores con los filtros actuales.</p>
                        </div>
                        
                        <div v-for="(row, index) in safeRows" :key="row.id" class="flex flex-col">
                            <div @click="openRowId = openRowId === row.id ? null : row.id" class="p-4 flex flex-col md:flex-row items-center space-y-4 md:space-y-0 md:space-x-4 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-900/50 transition-colors">
                                <div class="w-12 text-center">
                                    <TrophyIcon v-if="showRankingMedals && index === 0" class="h-6 w-6 text-amber-400 mx-auto" />
                                    <TrophyIcon v-else-if="showRankingMedals && index === 1" class="h-6 w-6 text-slate-400 mx-auto" />
                                    <TrophyIcon v-else-if="showRankingMedals && index === 2" class="h-6 w-6 text-amber-600 mx-auto" />
                                    <span v-else class="font-bold text-gray-500">{{ index + 1 }}</span>
                                </div>

                                <div class="flex-grow flex items-center w-full md:w-1/3">
                                    <img :src="`https://ui-avatars.com/api/?name=${row.name}&background=random`" alt="Avatar" class="h-12 w-12 rounded-full mr-4">
                                    <div>
                                        <div class="font-bold text-lg text-gray-900 dark:text-white">{{ row.name }}</div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ row.email }}</div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-3 gap-4 text-center w-full md:w-auto md:flex-grow">
                                    <div>
                                        <div class="text-xs text-gray-500 uppercase tracking-wider">Recuperado</div>
                                        <div class="font-semibold text-lg text-gray-800 dark:text-gray-200">{{ formatCurrency(row.total_recovered) }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 uppercase tracking-wider">Casos</div>
                                        <div class="font-semibold text-lg text-gray-800 dark:text-gray-200">{{ row.casos_count }}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs text-gray-500 uppercase tracking-wider">Coops</div>
                                        <div class="font-semibold text-lg text-gray-800 dark:text-gray-200">{{ row.cooperativas_count }}</div>
                                    </div>
                                </div>

                                <div class="w-12 text-center">
                                   <ChevronDownIcon class="h-6 w-6 text-gray-400 transition-transform mx-auto" :class="{ 'rotate-180': openRowId === row.id }" />
                                </div>
                            </div>
                            
                            <transition name="fade">
                                <div v-if="openRowId === row.id" class="bg-gray-50 dark:bg-gray-900/50 p-6">
                                    <div class="grid md:grid-cols-2 gap-x-8 gap-y-6">
                                        <div>
                                            <h3 class="font-semibold mb-3 text-gray-800 dark:text-gray-200">Casos Asignados</h3>
                                            <div class="space-y-2 text-sm max-h-60 overflow-y-auto pr-2 custom-scrollbar">
                                                <div v-for="caso in row.cases" :key="caso.id" class="flex justify-between items-center p-2 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700">
                                                    <div>
                                                        <Link :href="route('casos.show', caso.id)" class="font-medium text-indigo-600 hover:underline">Caso #{{ caso.id }}</Link>
                                                        <span class="text-gray-500 text-xs block">{{ caso.cooperativa?.name || 'N/A' }}</span>
                                                    </div>
                                                    <span class="font-semibold text-gray-700 dark:text-gray-300">{{ formatCurrency(caso.recovered) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <h3 class="font-semibold mb-3 text-gray-800 dark:text-gray-200">Cooperativas Asignadas</h3>
                                            <ul class="space-y-2 text-sm">
                                                <li v-for="coop in row.cooperativas" :key="coop.id">
                                                    <Link :href="route('cooperativas.show', coop.id)" class="text-indigo-600 hover:underline flex items-center group">
                                                      <BuildingOffice2Icon class="h-4 w-4 mr-2 text-gray-400 group-hover:text-indigo-500" />
                                                      <span>{{ coop.name }}</span>
                                                    </Link>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </transition>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active {
  transition: all 0.3s ease-in-out;
  overflow: hidden;
}
.fade-enter-from, .fade-leave-to {
  opacity: 0;
  max-height: 0;
  padding-top: 0;
  padding-bottom: 0;
}
.fade-enter-to, .fade-leave-from {
  max-height: 1000px; /* Un valor grande para permitir la expansión */
}

/* Scrollbar minimalista */
.custom-scrollbar::-webkit-scrollbar {
    width: 5px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: #d1d5db; /* gray-300 */
    border-radius: 20px;
}
.dark .custom-scrollbar::-webkit-scrollbar-thumb {
    background-color: #4b5563; /* gray-600 */
}
</style>

