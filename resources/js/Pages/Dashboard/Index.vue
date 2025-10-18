<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage, router, Link } from '@inertiajs/vue3';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Textarea from '@/Components/Textarea.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { ref, computed, watch } from 'vue';
import { debounce, pickBy } from 'lodash';
import { Bar, Line, Pie } from 'vue-chartjs';
import { Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, PointElement, LineElement, ArcElement, Filler } from 'chart.js';
import {
    FolderIcon,
    ScaleIcon,
    BanknotesIcon,
    ShieldCheckIcon,
    UserGroupIcon,
    ArrowPathIcon,
    UserCircleIcon,
    ChatBubbleLeftRightIcon,
    EnvelopeIcon,
    ArrowUpIcon,
    ArrowDownIcon,
    CalendarDaysIcon
} from '@heroicons/vue/24/outline';

// Registro de todos los componentes de ChartJS, incluyendo 'Filler' para fondos de área.
ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, PointElement, LineElement, ArcElement, Filler);

const props = defineProps({
    kpis: Object,
    chartData: Object,
    cooperativas: Array,
    filters: Object,
    rankingAbogados: Array,
    userRole: String,
    // Datos de ejemplo para las tendencias y gráficos pequeños (sparklines)
    kpis_comparison: {
        type: Object,
        default: () => ({
            casos_activos: { value: '+5.2%', direction: 'up' },
            casos_demandados: { value: '-1.0%', direction: 'down' },
            mora_total: { value: '+2.1%', direction: 'up' },
            cumplimiento_legal: { value: '+0.5%', direction: 'up' },
        })
    },
    sparkline_data: {
        type: Object,
        default: () => ({
            casos: [30, 41, 35, 51, 49, 62, 69],
            demanda: [1, 2, 1, 3, 2, 4, 3],
            mora: [10.8, 12.1, 9.5, 11.3, 13.0, 12.5, 14.2],
            cumplimiento: [98, 99, 98.5, 99.5, 99, 100, 100],
        })
    }
});

const user = computed(() => usePage().props.auth.user);

// --- Lógica de Filtros ---
const form = useForm({
    cooperativa_id: props.filters?.cooperativa_id || '',
    fecha_desde: props.filters?.fecha_desde || '',
    fecha_hasta: props.filters?.fecha_hasta || '',
});
const resetFilters = () => form.reset();
watch(() => form.data(), debounce(function(currentForm) {
    router.get(route('dashboard'), pickBy(currentForm), { preserveState: true, replace: true });
}, 500), { deep: true });

// --- Lógica de Modal de Contacto para Clientes ---
const mostrandoModalContacto = ref(false);
const contactForm = useForm({ asunto: '', mensaje: '' });

const abrirModalContacto = () => {
    contactForm.asunto = `Consulta sobre mi caso - Cliente: ${user.value.name}`;
    mostrandoModalContacto.value = true;
};
const cerrarModalContacto = () => mostrandoModalContacto.value = false;
const enviarMensaje = () => {
    contactForm.post(route('contacto.cliente.enviar'), {
        preserveScroll: true,
        onSuccess: () => cerrarModalContacto(),
    });
};

// --- Helpers ---
const formatCurrency = (value) => {
    const numberValue = parseFloat(value);
    if (!isFinite(numberValue)) return '$ 0';
    return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(numberValue);
};

// --- Datos para Gráficas ---
const casosPorEstadoData = computed(() => ({
    labels: Object.keys(props.chartData?.casosPorEstado ?? {}).map(s => s.charAt(0).toUpperCase() + s.slice(1)),
    datasets: [{
        label: 'Total de Casos',
        backgroundColor: '#4f46e5',
        borderRadius: 4,
        data: Object.values(props.chartData?.casosPorEstado ?? {})
    }],
}));

const incidentesChartData = computed(() => ({
    labels: Object.keys(props.chartData?.incidentesPorMes ?? {}),
    datasets: [{
        label: 'Incidentes',
        borderColor: '#f97316',
        backgroundColor: 'rgba(249, 115, 22, 0.1)',
        fill: true,
        tension: 0.4,
        data: Object.values(props.chartData?.incidentesPorMes ?? {})
    }],
}));

const validacionesPorEstadoData = computed(() => {
    const data = props.chartData?.validacionesPorEstado ?? {};
    return {
        labels: ['Cumple', 'Incumple'],
        datasets: [{
            backgroundColor: ['#10b981', '#ef4444'],
            borderColor: ['#fff', '#fff'],
            borderWidth: 4,
            data: [data.cumple || 0, data.incumple || 0]
        }],
    };
});

// Opciones globales para las gráficas
const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'bottom',
            labels: { padding: 20, usePointStyle: true, pointStyle: 'circle' }
        }
    },
    interaction: { intersect: false, mode: 'index' },
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Dashboard
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

                <!-- =============================================================== -->
                <!-- ============ VISTA PARA ADMINS, ABOGADOS Y GESTORES =========== -->
                <!-- =============================================================== -->
                <template v-if="userRole !== 'cliente'">
                    <!-- Cabecera y Filtros -->
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border dark:border-gray-700">
                        <div class="flex flex-col md:flex-row justify-between md:items-center gap-4">
                            <div>
                                <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Bienvenido de nuevo, {{ user.name.split(' ')[0] }}</h1>
                                <p class="text-gray-500 dark:text-gray-400 mt-1 flex items-center">
                                    <CalendarDaysIcon class="h-5 w-5 mr-2" />
                                    <span>Hoy es {{ new Date().toLocaleDateString('es-CO', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}</span>
                                </p>
                            </div>
                            <div class="flex-shrink-0">
                                <SecondaryButton @click="resetFilters">
                                    <ArrowPathIcon class="h-5 w-5 mr-2"/> Reiniciar Filtros
                                </SecondaryButton>
                            </div>
                        </div>
                        <div class="border-t border-gray-200 dark:border-gray-700 mt-4 pt-4">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label for="cooperativa_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Cooperativa</label>
                                    <select v-model="form.cooperativa_id" id="cooperativa_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <option value="">Todas</option>
                                        <option v-for="cooperativa in cooperativas" :key="cooperativa.id" :value="cooperativa.id">{{ cooperativa.nombre }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="fecha_desde" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Desde</label>
                                    <TextInput type="date" v-model="form.fecha_desde" id="fecha_desde" class="mt-1 block w-full"/>
                                </div>
                                <div>
                                    <label for="fecha_hasta" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Hasta</label>
                                    <TextInput type="date" v-model="form.fecha_hasta" id="fecha_hasta" class="mt-1 block w-full"/>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- KPIs -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border dark:border-gray-700 flex flex-col justify-between transition-all duration-300 hover:shadow-xl hover:-translate-y-1">
                            <div>
                                <div class="flex justify-between items-start">
                                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Casos Activos</h3>
                                    <div class="flex items-center text-xs font-semibold px-2 py-1 rounded-full" :class="props.kpis_comparison.casos_activos.direction === 'up' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                                        <ArrowUpIcon v-if="props.kpis_comparison.casos_activos.direction === 'up'" class="h-4 w-4" />
                                        <ArrowDownIcon v-else class="h-4 w-4" />
                                        <span>{{ props.kpis_comparison.casos_activos.value }}</span>
                                    </div>
                                </div>
                                <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ kpis.casos_activos }}</p>
                            </div>
                            <div class="mt-4 h-12"><Line :data="{ labels: Array(7).fill(''), datasets: [{ data: props.sparkline_data.casos, borderColor: '#4f46e5', borderWidth: 2, tension: 0.4, pointRadius: 0 }] }" :options="{ responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { display: false }, y: { display: false } } }" /></div>
                        </div>
                        <!-- Repite la estructura para las otras 3 KPIs -->
                        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border dark:border-gray-700 flex flex-col justify-between transition-all duration-300 hover:shadow-xl hover:-translate-y-1">
                            <div>
                                <div class="flex justify-between items-start">
                                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Casos en Demanda</h3>
                                    <div class="flex items-center text-xs font-semibold px-2 py-1 rounded-full" :class="props.kpis_comparison.casos_demandados.direction === 'up' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                                        <ArrowUpIcon v-if="props.kpis_comparison.casos_demandados.direction === 'up'" class="h-4 w-4" />
                                        <ArrowDownIcon v-else class="h-4 w-4" />
                                        <span>{{ props.kpis_comparison.casos_demandados.value }}</span>
                                    </div>
                                </div>
                                <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ kpis.casos_demandados }}</p>
                            </div>
                            <div class="mt-4 h-12"><Line :data="{ labels: Array(7).fill(''), datasets: [{ data: props.sparkline_data.demanda, borderColor: '#f97316', borderWidth: 2, tension: 0.4, pointRadius: 0 }] }" :options="{ responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { display: false }, y: { display: false } } }" /></div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border dark:border-gray-700 flex flex-col justify-between transition-all duration-300 hover:shadow-xl hover:-translate-y-1">
                            <div>
                                <div class="flex justify-between items-start">
                                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Saldo Total Activo</h3>
                                    <div class="flex items-center text-xs font-semibold px-2 py-1 rounded-full" :class="props.kpis_comparison.mora_total.direction === 'up' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                                        <ArrowUpIcon v-if="props.kpis_comparison.mora_total.direction === 'up'" class="h-4 w-4" />
                                        <ArrowDownIcon v-else class="h-4 w-4" />
                                        <span>{{ props.kpis_comparison.mora_total.value }}</span>
                                    </div>
                                </div>
                                <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ formatCurrency(kpis.mora_total) }}</p>
                            </div>
                            <div class="mt-4 h-12"><Line :data="{ labels: Array(7).fill(''), datasets: [{ data: props.sparkline_data.mora, borderColor: '#ef4444', borderWidth: 2, tension: 0.4, pointRadius: 0 }] }" :options="{ responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { display: false }, y: { display: false } } }" /></div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border dark:border-gray-700 flex flex-col justify-between transition-all duration-300 hover:shadow-xl hover:-translate-y-1">
                            <div>
                                <div class="flex justify-between items-start">
                                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Nivel de Cumplimiento</h3>
                                    <div class="flex items-center text-xs font-semibold px-2 py-1 rounded-full" :class="props.kpis_comparison.cumplimiento_legal.direction === 'up' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
                                        <ArrowUpIcon v-if="props.kpis_comparison.cumplimiento_legal.direction === 'up'" class="h-4 w-4" />
                                        <ArrowDownIcon v-else class="h-4 w-4" />
                                        <span>{{ props.kpis_comparison.cumplimiento_legal.value }}</span>
                                    </div>
                                </div>
                                <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ kpis.cumplimiento_legal }}%</p>
                            </div>
                            <div class="mt-4 h-12"><Line :data="{ labels: Array(7).fill(''), datasets: [{ data: props.sparkline_data.cumplimiento, borderColor: '#10b981', borderWidth: 2, tension: 0.4, pointRadius: 0 }] }" :options="{ responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { display: false }, y: { display: false } } }" /></div>
                        </div>
                    </div>

                    <!-- Gráficas y Ranking -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="lg:col-span-2 space-y-6">
                            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border dark:border-gray-700"><h3 class="font-bold text-lg text-gray-900 dark:text-white mb-4">Distribución de Casos por Estado</h3><div class="h-80"><Bar :data="casosPorEstadoData" :options="chartOptions" /></div></div>
                            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border dark:border-gray-700"><h3 class="font-bold text-lg text-gray-900 dark:text-white">Tendencia de Incidentes</h3><p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Registros durante el último año</p><div class="h-80"><Line :data="incidentesChartData" :options="chartOptions" /></div></div>
                        </div>
                        <div class="lg:col-span-1 space-y-6">
                            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border dark:border-gray-700"><h3 class="font-bold text-lg text-gray-900 dark:text-white mb-4">Nivel de Cumplimiento Legal</h3><div class="h-80 flex items-center justify-center"><Pie :data="validacionesPorEstadoData" :options="chartOptions" /></div></div>
                            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border dark:border-gray-700">
                                <h3 class="font-bold text-lg text-gray-900 dark:text-white mb-2 flex items-center"><UserGroupIcon class="h-6 w-6 mr-2 text-yellow-500"/> Ranking de Abogados</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Top 3 por monto recuperado.</p>
                                <ul class="space-y-3">
                                    <li v-for="(abogado, index) in rankingAbogados" :key="abogado.id" class="flex items-center p-3 rounded-lg transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <div class="flex-shrink-0 h-10 w-10 rounded-full flex items-center justify-center text-white font-bold text-xl" :class="[{'bg-amber-400': index === 0, 'bg-slate-400': index === 1, 'bg-amber-600': index === 2}, 'ring-4 ring-white dark:ring-gray-800']"><span>{{ index + 1 }}</span></div>
                                        <div class="ml-4"><p class="text-sm font-medium text-gray-900 dark:text-white">{{ abogado.name }}</p><p class="text-sm text-gray-500 dark:text-gray-400">{{ formatCurrency(abogado.total_recuperado) }}</p></div>
                                    </li>
                                    <li v-if="!rankingAbogados || rankingAbogados.length === 0" class="text-center text-gray-500 dark:text-gray-400 py-8">No hay datos de recuperación.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- =============================================================== -->
                <!-- ================= VISTA PARA CLIENTES ========================= -->
                <!-- =============================================================== -->
                <template v-if="userRole === 'cliente'">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <div class="lg:col-span-2 space-y-6">
                            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border dark:border-gray-700">
                                <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Bienvenido(a), {{ user.name }}</h1>
                                <p class="text-gray-500 dark:text-gray-400 mt-1">Este es el resumen de tu estado de cuenta actual.</p>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border-l-4 border-red-500">
                                    <h3 class="text-base font-medium text-gray-500 dark:text-gray-400">Saldo Pendiente Total</h3>
                                    <p class="mt-2 text-4xl font-bold text-red-600 dark:text-red-400">{{ formatCurrency(kpis.saldo_total_pendiente) }}</p>
                                </div>
                                <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border-l-4 border-indigo-500">
                                    <h3 class="text-base font-medium text-gray-500 dark:text-gray-400">Casos Activos a tu nombre</h3>
                                    <p class="mt-2 text-4xl font-bold text-gray-900 dark:text-white">{{ kpis.casos_activos }}</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="lg:col-span-1 space-y-6">
                            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border dark:border-gray-700">
                                <h3 class="font-bold text-lg text-gray-900 dark:text-white mb-4">Contacte a su Gestor</h3>
                                <div class="space-y-3">
                                    <a href="https://wa.me/573152819233" target="_blank" class="w-full group flex items-center p-3 rounded-lg transition-colors duration-200 hover:bg-gray-100 dark:hover:bg-gray-700/50">
                                        <div class="flex-shrink-0 bg-green-100 dark:bg-green-900/50 p-2 rounded-full"><ChatBubbleLeftRightIcon class="h-6 w-6 text-green-600 dark:text-green-400"/></div>
                                        <span class="ml-4 text-sm font-medium text-gray-800 dark:text-gray-200">Escribir por WhatsApp</span>
                                    </a>
                                    <button @click="abrirModalContacto" class="w-full text-left flex items-center p-3 rounded-lg transition-colors duration-200 hover:bg-gray-100 dark:hover:bg-gray-700/50">
                                        <div class="flex-shrink-0 bg-blue-100 dark:bg-blue-900/50 p-2 rounded-full"><EnvelopeIcon class="h-6 w-6 text-blue-600 dark:text-blue-400"/></div>
                                        <span class="ml-4 text-sm font-medium text-gray-800 dark:text-gray-200">Enviar un Mensaje</span>
                                    </button>
                                </div>
                            </div>
                             <div class="bg-white dark:bg-gray-800 p-6 rounded-xl shadow-lg border dark:border-gray-700">
                                <h3 class="font-bold text-lg text-gray-900 dark:text-white mb-4">Mi Cuenta</h3>
                                <Link :href="route('profile.edit')" class="flex items-center p-3 rounded-lg transition-colors duration-200 hover:bg-gray-100 dark:hover:bg-gray-700/50">
                                    <div class="flex-shrink-0 bg-gray-100 dark:bg-gray-700 p-2 rounded-full"><UserCircleIcon class="h-6 w-6 text-gray-600 dark:text-gray-400"/></div>
                                    <span class="ml-4 text-sm font-medium text-gray-800 dark:text-gray-200">Editar mi Perfil</span>
                                </Link>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
        
        <Modal :show="mostrandoModalContacto" @close="cerrarModalContacto">
            <form @submit.prevent="enviarMensaje" class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Enviar Mensaje a mi Gestor</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Tu mensaje será enviado directamente a la firma de abogados.</p>
                <div class="mt-6">
                    <InputLabel for="asunto" value="Asunto" />
                    <TextInput id="asunto" v-model="contactForm.asunto" type="text" class="mt-1 block w-full" required />
                    <InputError class="mt-2" :message="contactForm.errors.asunto" />
                </div>
                <div class="mt-6">
                    <InputLabel for="mensaje" value="Mensaje" />
                    <Textarea id="mensaje" v-model="contactForm.mensaje" class="mt-1 block w-full" rows="6" required />
                    <InputError class="mt-2" :message="contactForm.errors.mensaje" />
                </div>
                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="cerrarModalContacto"> Cancelar </SecondaryButton>
                    <PrimaryButton class="ms-3" :class="{ 'opacity-25': contactForm.processing }" :disabled="contactForm.processing">
                        Enviar Mensaje
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>

