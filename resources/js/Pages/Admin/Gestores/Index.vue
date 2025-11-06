<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import { debounce, pickBy } from 'lodash';
import { 
    UserGroupIcon, 
    BanknotesIcon, 
    FolderOpenIcon, 
    BuildingOffice2Icon, 
    TrophyIcon, 
    ChevronDownIcon, 
    MagnifyingGlassIcon, 
    FolderIcon // <-- Este es el que añadimos
} from '@heroicons/vue/24/outline';

const props = defineProps({
    filters: Object,
    rows: Object, // Paginator object
    totals: Object,
});

// --- Estado de UI y Filtros ---
const q = ref(props.filters?.q ?? '');
const sort = ref(props.filters?.sort ?? 'total_recovered');
const dir = ref(props.filters?.dir ?? 'desc');
const openRowId = ref(null);
const isMounted = ref(false);

// --- Lógica de Filtros Automáticos ---
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

// --- Animación de Conteo para KPIs ---
const animatedTotalRecovered = ref(0);
watch(() => props.totals.totalRecovered, (newValue) => {
    const startValue = animatedTotalRecovered.value;
    const duration = 1500;
    let startTime = null;

    const animate = (timestamp) => {
        if (!startTime) startTime = timestamp;
        const progress = Math.min((timestamp - startTime) / duration, 1);
        const currentValue = Math.floor(progress * (newValue - startValue) + startValue);
        animatedTotalRecovered.value = currentValue;
        if (progress < 1) {
            requestAnimationFrame(animate);
        }
    };
    requestAnimationFrame(animate);
}, { immediate: true });

onMounted(() => {
    // Para evitar que la animación de entrada se ejecute en la carga inicial
    setTimeout(() => {
        isMounted.value = true;
    }, 100);
});

// --- Funciones de Ayuda ---
const formatCurrency = (value) => {
    return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(value || 0);
};

// --- Medallas de Ranking ---
const showRankingMedals = computed(() => {
    return sort.value === 'total_recovered' && dir.value === 'desc' && q.value === '';
});

// --- Insignias de Rol ---
const roleBadgeClass = (role) => {
    return {
        'admin': 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300',
        'abogado': 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300',
        'gestor': 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300',
    }[role] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
};
</script>

<template>
    <Head title="Dashboard de Rendimiento" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight flex items-center">
                    <TrophyIcon class="h-6 w-6 mr-3 text-indigo-500" />
                    Muro de Campeones
                </h2>
                <div class="flex items-center gap-4 w-full sm:w-auto">
                    <div class="relative flex-grow sm:w-64">
                         <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                        <input v-model="q" type="text" class="w-full pl-10 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="Buscar por nombre...">
                    </div>
                    <select v-model="sort" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="total_recovered">Mayor Recuperación</option>
                        <option value="casos_count">Más Casos</option>
                        <option value="name">Nombre (A-Z)</option>
                    </select>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- KPIs Globales -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-lg border border-transparent hover:border-green-500 transition-all duration-300">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-green-100 dark:bg-green-900/50 p-3 rounded-full">
                                <BanknotesIcon class="h-7 w-7 text-green-600 dark:text-green-400" />
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Recuperado</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ formatCurrency(animatedTotalRecovered) }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-lg border border-transparent hover:border-indigo-500 transition-all duration-300">
                         <div class="flex items-center">
                            <div class="flex-shrink-0 bg-indigo-100 dark:bg-indigo-900/50 p-3 rounded-full">
                                <FolderOpenIcon class="h-7 w-7 text-indigo-600 dark:text-indigo-400" />
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Casos</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ totals.totalCasos }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-lg border border-transparent hover:border-sky-500 transition-all duration-300">
                         <div class="flex items-center">
                            <div class="flex-shrink-0 bg-sky-100 dark:bg-sky-900/50 p-3 rounded-full">
                                <UserGroupIcon class="h-7 w-7 text-sky-600 dark:text-sky-400" />
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Gestores</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ totals.totalUsers }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lista de Gestores -->
                <div v-if="rows.data.length === 0" class="text-center py-20 text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 rounded-2xl shadow-lg">
                    <UserGroupIcon class="h-16 w-16 mx-auto text-gray-400" />
                    <p class="mt-4 text-lg font-semibold">No se encontraron resultados</p>
                    <p class="mt-1 text-sm">Prueba con otro término de búsqueda.</p>
                </div>
                
                <div v-else>
                    <!-- EL CAMBIO CLAVE ESTÁ AQUÍ: La clase "space-y-6" ahora está en el transition-group -->
                    <transition-group name="list" tag="div" class="space-y-6">
                        <div v-for="(row, index) in rows.data" :key="row.id" class="bg-white dark:bg-gray-800 rounded-2xl shadow-lg transition-all duration-300 hover:shadow-2xl hover:-translate-y-1" :style="{ transitionDelay: isMounted ? `${index * 50}ms` : '0ms' }">
                            <div @click="openRowId = openRowId === row.id ? null : row.id" class="p-4 flex items-center space-x-4 cursor-pointer">
                                <!-- Ranking -->
                                <div class="w-12 text-center flex-shrink-0">
                                    <span v-if="showRankingMedals && (rows.current_page - 1) * rows.per_page + index === 0" class="text-3xl">🥇</span>
                                    <span v-else-if="showRankingMedals && (rows.current_page - 1) * rows.per_page + index === 1" class="text-3xl">🥈</span>
                                    <span v-else-if="showRankingMedals && (rows.current_page - 1) * rows.per_page + index === 2" class="text-3xl">🥉</span>
                                    <span v-else class="font-bold text-gray-500 text-lg">{{ (rows.current_page - 1) * rows.per_page + index + 1 }}</span>
                                </div>

                                <!-- Info Usuario -->
                                <div class="flex-grow flex items-center min-w-0">
                                    <img :src="`https://ui-avatars.com/api/?name=${row.name}&background=random`" alt="Avatar" class="h-12 w-12 rounded-full mr-4">
                                    <div class="min-w-0">
                                        <div class="font-bold text-lg text-gray-900 dark:text-white truncate">{{ row.name }}</div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm text-gray-500 dark:text-gray-400 truncate">{{ row.email }}</span>
                                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full" :class="roleBadgeClass(row.tipo_usuario)">{{ row.tipo_usuario }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- KPIs Individuales -->
                                <div class="hidden md:grid grid-cols-3 gap-4 text-center flex-shrink-0 w-1/2">
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

                                <!-- Chevron -->
                                <div class="w-12 text-center flex-shrink-0">
                                   <ChevronDownIcon class="h-6 w-6 text-gray-400 transition-transform mx-auto" :class="{ 'rotate-180': openRowId === row.id }" />
                                </div>
                            </div>

                            <!-- Panel Desplegable -->
                            <transition name="fade">
    <div v-if="openRowId === row.id" class="p-6 pt-2 transition-all duration-300 ease-in-out bg-slate-50 dark:bg-slate-800/50 rounded-b-2xl">
        
        <h4 class="text-sm font-semibold text-slate-800 dark:text-slate-200 border-b dark:border-slate-700 pb-2">
            Detalle de Cooperativas
        </h4>
        <div v-if="row.cooperativas && row.cooperativas.length" class="flow-root mt-3">
            <ul role="list" class="-my-2 divide-y divide-slate-200 dark:divide-slate-700">
                <li v-for="coop in row.cooperativas" :key="coop.id" class="py-2.5">
                    <Link :href="route('cooperativas.show', coop.id)" class="group flex items-center text-sm">
                        <BuildingOffice2Icon class="h-5 w-5 mr-3 text-slate-400 group-hover:text-indigo-500 transition-colors" />
                        <span class="text-slate-700 dark:text-slate-300 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">{{ coop.nombre }}</span>
                    </Link>
                </li>
            </ul>
        </div>
        <div v-else class="text-sm text-slate-500 italic py-4 text-center">
            No tiene cooperativas asignadas.
        </div>

        <h4 class="mt-6 text-sm font-semibold text-slate-800 dark:text-slate-200 border-b dark:border-slate-700 pb-2">
            Detalle de Casos
        </h4>
        <div v-if="row.casos && row.casos.length" class="flow-root mt-3">
            <ul role="list" class="-my-2 divide-y divide-slate-200 dark:divide-slate-700">
                <li v-for="caso in row.casos" :key="caso.id" class="py-2.5">
                    <Link :href="route('casos.show', caso.id)" class="group flex items-center justify-between text-sm">
                        <div class="flex items-center">
                            <FolderIcon class="h-5 w-5 mr-3 text-slate-400 group-hover:text-indigo-500 transition-colors" />
                            <span class="text-slate-700 dark:text-slate-300 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">{{ caso.proceso }}</span>
                        </div>
                        <span class="text-xs text-slate-500 dark:text-slate-400 group-hover:text-indigo-600">{{ caso.referencia }}</span>
                    </Link>
                </li>
            </ul>
        </div>
        <div v-else class="text-sm text-slate-500 italic py-4 text-center">
            No tiene casos asignados.
        </div>
        </div>
</transition>
                        </div>
                    </transition-group>
                </div>
                
                 <!-- Paginación -->
                <div v-if="rows.links.length > 3" class="flex justify-center mt-8">
                    <div class="flex rounded-lg shadow">
                         <Link
                            v-for="(link, k) in rows.links"
                            :key="k"
                            :href="link.url"
                            v-html="link.label"
                            class="px-4 py-2 text-sm font-medium transition"
                            :class="{
                                'bg-indigo-600 text-white': link.active,
                                'bg-white text-gray-700 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700': !link.active && link.url,
                                'bg-white text-gray-400 cursor-not-allowed dark:bg-gray-800 dark:text-gray-500': !link.url,
                                'rounded-l-lg': k === 0,
                                'rounded-r-lg': k === rows.links.length - 1
                            }"
                        />
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: all 0.3s ease-in-out;
    overflow: hidden;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
    max-height: 0;
    padding-top: 0;
    padding-bottom: 0;
    margin-top: 0;
}

.fade-enter-to,
.fade-leave-from {
    max-height: 500px;
}

.list-move,
.list-enter-active,
.list-leave-active {
    transition: all 0.5s cubic-bezier(0.55, 0, 0.1, 1);
}

.list-enter-from,
.list-leave-to {
    opacity: 0;
    transform: scale(0.95) translateY(30px);
}

.list-leave-active {
    position: absolute;
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