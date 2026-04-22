<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch, computed, onMounted } from 'vue';
import { debounce, pickBy } from 'lodash';
import SelectInput from '@/Components/SelectInput.vue';
import { 
    UserGroupIcon, 
    BanknotesIcon, 
    FolderOpenIcon, 
    BuildingOffice2Icon, 
    TrophyIcon, 
    ChevronDownIcon, 
    MagnifyingGlassIcon, 
    FolderIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    filters: Object,
    rows: Object,
    totals: Object,
});

// --- Estado ---
const q = ref(props.filters?.q ?? '');
const sort = ref(props.filters?.sort ?? 'total_recovered');
const dir = ref(props.filters?.dir ?? 'desc');
const openRowId = ref(null);
const isMounted = ref(false);

// --- Filtros Automáticos ---
watch([q, sort, dir], debounce(() => {
    router.get(route('admin.gestores.index'), pickBy({
        q: q.value,
        sort: sort.value,
        dir: dir.value
    }), {
        preserveState: true,
        replace: true,
        only: ['rows', 'filters', 'totals']
    });
}, 300));

// --- Animación de Números (Contador) ---
const animatedTotalRecovered = ref(0);

const runAnimation = (endValue) => {
    const startValue = animatedTotalRecovered.value;
    const duration = 1500;
    let startTime = null;

    const animate = (timestamp) => {
        if (!startTime) startTime = timestamp;
        const progress = Math.min((timestamp - startTime) / duration, 1);
        const easeOut = 1 - Math.pow(1 - progress, 3);
        
        const currentValue = Math.floor(startValue + (endValue - startValue) * easeOut);
        animatedTotalRecovered.value = currentValue;

        if (progress < 1) {
            requestAnimationFrame(animate);
        } else {
            animatedTotalRecovered.value = endValue;
        }
    };
    requestAnimationFrame(animate);
};

watch(() => props.totals.totalRecovered, (newValue) => {
    runAnimation(newValue);
}, { immediate: true });

onMounted(() => {
    setTimeout(() => { isMounted.value = true; }, 100);
});

// --- Formato Moneda ---
const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-CO', { 
        style: 'currency', 
        currency: 'COP', 
        maximumFractionDigits: 0 
    }).format(value || 0);
};

const showRankingMedals = computed(() => {
    return sort.value === 'total_recovered' && dir.value === 'desc' && !q.value;
});

const roleBadgeClass = (role) => {
    const classes = {
        'admin': 'bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-300',
        'abogado': 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300',
        'gestor': 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300',
    };
    return classes[role] || 'bg-gray-100 text-gray-800';
};
</script>

<template>
    <Head title="Dashboard de Rendimiento" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                <h2 class="font-semibold text-xl text-blue-500 dark:text-gray-200 leading-tight flex items-center">
                    <TrophyIcon class="h-6 w-6 mr-3 text-yellow-500" />
                    Muro de Campeones
                </h2>
                <div class="flex items-center gap-4 w-full sm:w-auto">
                    <div class="relative flex-grow sm:w-64">
                         <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                        <input v-model="q" type="text" class="w-full pl-10 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="Buscar gestor...">
                    </div>
                    <SelectInput v-model="sort" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="total_recovered">Mayor Recuperación</option>
                        <option value="casos_count">Más Casos</option>
                        <option value="name">Nombre (A-Z)</option>
                    </SelectInput>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- KPIs Globales -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Total Recuperado -->
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-lg border-b-4 border-green-500 hover:scale-105 transition-transform duration-300">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-green-100 dark:bg-green-900/50 p-3 rounded-full">
                                <BanknotesIcon class="h-8 w-8 text-green-600 dark:text-green-400" />
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total Recuperado</p>
                                <p class="text-3xl font-extrabold text-gray-900 dark:text-white mt-1">{{ formatCurrency(animatedTotalRecovered) }}</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Total Casos -->
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-lg border-b-4 border-indigo-500 hover:scale-105 transition-transform duration-300">
                         <div class="flex items-center">
                            <div class="flex-shrink-0 bg-indigo-100 dark:bg-indigo-900/50 p-3 rounded-full">
                                <FolderOpenIcon class="h-8 w-8 text-indigo-600 dark:text-indigo-400" />
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Total Casos</p>
                                <p class="text-3xl font-extrabold text-gray-900 dark:text-white mt-1">{{ totals.totalCasos }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Total Gestores -->
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-lg border-b-4 border-sky-500 hover:scale-105 transition-transform duration-300">
                         <div class="flex items-center">
                            <div class="flex-shrink-0 bg-sky-100 dark:bg-sky-900/50 p-3 rounded-full">
                                <UserGroupIcon class="h-8 w-8 text-sky-600 dark:text-sky-400" />
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Equipo Activo</p>
                                <p class="text-3xl font-extrabold text-gray-900 dark:text-white mt-1">{{ totals.totalUsers }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lista de Gestores -->
                <div v-if="rows.data.length === 0" class="text-center py-20 bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-dashed border-gray-300">
                    <UserGroupIcon class="h-16 w-16 mx-auto text-gray-300" />
                    <p class="mt-4 text-lg font-medium text-gray-500">No se encontraron gestores</p>
                </div>
                
                <div v-else>
                    <transition-group name="list" tag="div" class="space-y-4">
                        <div v-for="(row, index) in rows.data" :key="row.id" 
                             class="bg-white dark:bg-gray-800 rounded-xl shadow-md border border-gray-100 dark:border-gray-700 overflow-hidden hover:shadow-lg transition-shadow duration-300">
                            
                            <div @click="openRowId = openRowId === row.id ? null : row.id" 
                                 class="p-5 flex items-center space-x-6 cursor-pointer select-none">
                                
                                <!-- Ranking (Medallas) -->
                                <div class="w-10 text-center flex-shrink-0">
                                    <span v-if="showRankingMedals && (rows.current_page - 1) * rows.per_page + index === 0" class="text-4xl drop-shadow-sm" title="1er Lugar">🥇</span>
                                    <span v-else-if="showRankingMedals && (rows.current_page - 1) * rows.per_page + index === 1" class="text-4xl drop-shadow-sm" title="2do Lugar">🥈</span>
                                    <span v-else-if="showRankingMedals && (rows.current_page - 1) * rows.per_page + index === 2" class="text-4xl drop-shadow-sm" title="3er Lugar">🥉</span>
                                    <span v-else class="text-xl font-bold text-gray-400">#{{ (rows.current_page - 1) * rows.per_page + index + 1 }}</span>
                                </div>

                                <!-- Avatar e Info -->
                                <div class="flex-grow flex items-center min-w-0">
                                    <div class="h-12 w-12 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold text-lg mr-4 shadow-sm">
                                        {{ row.name.charAt(0).toUpperCase() }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-bold text-lg text-gray-900 dark:text-white truncate">{{ row.name }}</div>
                                        <div class="flex items-center gap-2 mt-0.5">
                                            <span class="text-xs font-medium px-2.5 py-0.5 rounded-full uppercase tracking-wider" :class="roleBadgeClass(row.tipo_usuario)">
                                                {{ row.tipo_usuario }}
                                            </span>
                                            <span class="text-sm text-gray-500 dark:text-gray-400 truncate">{{ row.email }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- KPIs Individuales -->
                                <div class="hidden md:flex gap-8 text-right flex-shrink-0">
                                    <div>
                                        <div class="text-xs text-gray-400 uppercase font-semibold">Recuperado</div>
                                        <div class="font-bold text-lg text-green-600 dark:text-green-400">{{ formatCurrency(row.total_recovered) }}</div>
                                    </div>
                                    <div class="w-20">
                                        <div class="text-xs text-gray-400 uppercase font-semibold">Casos</div>
                                        <div class="font-bold text-lg text-gray-800 dark:text-gray-200">{{ row.casos_count }}</div>
                                    </div>
                                    <div class="w-20">
                                        <div class="text-xs text-gray-400 uppercase font-semibold">Coops</div>
                                        <div class="font-bold text-lg text-gray-800 dark:text-gray-200">{{ row.cooperativas_count }}</div>
                                    </div>
                                </div>

                                <!-- Flecha -->
                                <div class="w-8 text-center flex-shrink-0 text-gray-400">
                                    <ChevronDownIcon class="h-5 w-5 transition-transform duration-300" :class="{ 'rotate-180': openRowId === row.id }" />
                                </div>
                            </div>

                            <!-- Panel Desplegable -->
                            <transition name="fade">
                                <div v-if="openRowId === row.id" class="bg-gray-50 dark:bg-gray-900/50 border-t dark:border-gray-700 p-6 grid md:grid-cols-2 gap-8">
                                    
                                    <!-- Lista de Cooperativas -->
                                    <div>
                                        <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3 flex items-center">
                                            <BuildingOffice2Icon class="h-4 w-4 mr-2" /> Cooperativas Asignadas
                                        </h4>
                                        <ul v-if="row.cooperativas.length" class="space-y-2">
                                            <li v-for="coop in row.cooperativas" :key="coop.id">
                                                <Link :href="route('cooperativas.show', coop.id)" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline flex items-center">
                                                    <span class="w-1.5 h-1.5 bg-indigo-400 rounded-full mr-2"></span>
                                                    {{ coop.nombre }}
                                                </Link>
                                            </li>
                                        </ul>
                                        <p v-else class="text-sm text-gray-400 italic">Sin asignaciones.</p>
                                    </div>

                                    <!-- Lista de Casos (Últimos 50) -->
                                    <div>
                                        <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3 flex items-center">
                                            <FolderIcon class="h-4 w-4 mr-2" /> Casos y Procesos
                                        </h4>
                                        <div v-if="row.casos.length" class="max-h-40 overflow-y-auto custom-scrollbar pr-2">
                                            <ul class="space-y-2">
                                                <li v-for="caso in row.casos" :key="caso.id" class="flex justify-between text-sm p-2 bg-white dark:bg-gray-800 rounded shadow-sm hover:bg-gray-50">
                                                    <Link :href="caso.link" class="font-medium text-gray-700 dark:text-gray-300 hover:text-indigo-600">
                                                        {{ caso.referencia }}
                                                    </Link>
                                                    <span class="text-xs text-gray-400 bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded">{{ caso.proceso }}</span>
                                                </li>
                                            </ul>
                                        </div>
                                        <p v-else class="text-sm text-gray-400 italic">No hay casos activos.</p>
                                    </div>

                                </div>
                            </transition>
                        </div>
                    </transition-group>
                </div>
                
                <!-- Paginación -->
                <div v-if="rows.links.length > 3" class="flex justify-center mt-8">
                     <div class="flex rounded-lg shadow bg-white dark:bg-gray-800 overflow-hidden">
                        <Link v-for="(link, k) in rows.links" :key="k" :href="link.url" v-html="link.label"
                            class="px-4 py-2 text-sm font-medium transition border-r dark:border-gray-700 last:border-r-0"
                            :class="{ 'bg-indigo-600 text-white': link.active, 'text-gray-700 dark:text-gray-300 hover:bg-gray-50': !link.active && link.url, 'text-gray-300 cursor-not-allowed': !link.url }" />
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: all 0.3s ease; overflow: hidden; }
.fade-enter-from, .fade-leave-to { opacity: 0; max-height: 0; padding: 0; margin: 0; }
.fade-enter-to, .fade-leave-from { max-height: 500px; opacity: 1; }

.list-enter-active, .list-leave-active { transition: all 0.4s ease; }
.list-enter-from { opacity: 0; transform: translateY(20px); }
.list-leave-to { opacity: 0; transform: scale(0.95); }

.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 10px; }
.dark .custom-scrollbar::-webkit-scrollbar-thumb { background-color: #4b5563; }
</style>