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
import { ref, computed, watch, onMounted } from 'vue';
import { debounce, pickBy } from 'lodash';
import { Bar, Line } from 'vue-chartjs';
import { Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, PointElement, LineElement, ArcElement, Filler, Colors } from 'chart.js';
import {
    FolderOpenIcon,
    BanknotesIcon,
    ArrowPathIcon,
    UserCircleIcon,
    ChatBubbleLeftRightIcon,
    EnvelopeIcon,
    ArrowUpIcon,
    ArrowDownIcon,
    CalendarDaysIcon,
    TrophyIcon,
    ReceiptPercentIcon,
    ArchiveBoxIcon,
    ExclamationTriangleIcon
} from '@heroicons/vue/24/outline';

// Registro de componentes de ChartJS
ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, PointElement, LineElement, ArcElement, Filler, Colors);

const props = defineProps({
    kpis: Object,
    chartData: Object,
    cooperativas: Array,
    filters: Object,
    ranking: Array,
    userRole: String,
    serverError: String, // Prop para manejar errores del servidor de forma segura
});

const user = computed(() => usePage().props.auth.user);
const isMounted = ref(false);

onMounted(() => {
    // Activa las animaciones de entrada después de que el componente se monte.
    setTimeout(() => { isMounted.value = true; }, 100);
});

// --- Lógica de Filtros ---
const form = useForm({
    cooperativa_id: props.filters?.cooperativa_id || '',
    fecha_desde: props.filters?.fecha_desde || '',
    fecha_hasta: props.filters?.fecha_hasta || '',
});
const resetFilters = () => {
    form.cooperativa_id = '';
    form.fecha_desde = '';
    form.fecha_hasta = '';
};
watch(() => form.data(), debounce(function (currentForm) {
    router.get(route('dashboard'), pickBy(currentForm), { preserveState: true, replace: true });
}, 500), { deep: true });

// --- Animación de Conteo para KPIs (mejorada con easing) ---
const animatedKpis = ref({});
const animateValue = (key, endValue, isCurrency = false, isPercent = false) => {
    const startValue = animatedKpis.value[key] || 0;
    const duration = 1500;
    let startTime = null;
    const animate = (timestamp) => {
        if (!startTime) startTime = timestamp;
        const progress = Math.min((timestamp - startTime) / duration, 1);
        const easeOutProgress = 1 - Math.pow(1 - progress, 4); // Easing function (easeOutQuart)
        const currentValue = easeOutProgress * (endValue - startValue) + startValue;
        
        if (isPercent) {
             animatedKpis.value[key] = parseFloat(currentValue.toFixed(1));
        } else {
            animatedKpis.value[key] = isCurrency ? currentValue : Math.floor(currentValue);
        }
       
        if (progress < 1) requestAnimationFrame(animate);
        else animatedKpis.value[key] = endValue; // Asegura el valor final exacto
    };
    requestAnimationFrame(animate);
};

// Observa cambios en los KPIs y dispara las animaciones.
watch(() => props.kpis, (newKpis) => {
    if (newKpis) {
        for (const key in newKpis) {
            const isCurrency = key.includes('saldo');
            const isPercent = key.includes('tasa');
            const targetValue = newKpis[key]?.value ?? newKpis[key] ?? 0;
            animateValue(key, targetValue, isCurrency, isPercent);
        }
    }
}, { immediate: true, deep: true });

// --- Lógica de Modal de Contacto para Clientes ---
const mostrandoModalContacto = ref(false);
const contactForm = useForm({ asunto: '', mensaje: '' });
const abrirModalContacto = () => {
    contactForm.asunto = `Consulta sobre mi caso - Cliente: ${user.value.name}`;
    mostrandoModalContacto.value = true;
};
const enviarMensaje = () => {
    contactForm.post(route('contacto.cliente.enviar'), {
        preserveScroll: true,
        onSuccess: () => mostrandoModalContacto.value = false,
    });
};

// --- Helpers de Formato ---
const formatCurrency = (value) => new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(value || 0);
const formatNumber = (value) => new Intl.NumberFormat('es-CO').format(Math.round(value) || 0);

// --- Opciones y Datos para Gráficas ---
const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: '#111827',
            titleColor: '#f9fafb',
            bodyColor: '#d1d5db',
            padding: 12,
            cornerRadius: 8,
            boxPadding: 5,
            usePointStyle: true,
        }
    },
    interaction: { intersect: false, mode: 'index' },
    scales: {
        x: { grid: { display: false }, ticks: { color: '#6b7280' } },
        y: { beginAtZero: true, grid: { color: 'rgba(209, 213, 219, 0.1)' }, ticks: { color: '#9ca3af' } }
    }
};

const casosPorEstadoData = computed(() => ({
    labels: Object.keys(props.chartData?.casosPorEstado ?? {}).map(s => s.charAt(0).toUpperCase() + s.slice(1)),
    datasets: [{
        label: 'Total de Casos',
        backgroundColor: ['#4f46e5', '#818cf8', '#a5b4fc', '#c7d2fe'],
        borderRadius: 5,
        borderSkipped: false,
        data: Object.values(props.chartData?.casosPorEstado ?? {})
    }],
}));

const recuperacionPorMesData = computed(() => {
    const data = props.chartData?.recuperacionPorMes ?? {};
    const labels = Array.from({ length: 12 }, (_, i) => {
        const d = new Date();
        d.setMonth(d.getMonth() - 11 + i);
        return d.toLocaleString('es-CO', { month: 'short', year: '2-digit' }).replace('.', '');
    });
    const monthKeys = Array.from({ length: 12 }, (_, i) => {
        const d = new Date();
        d.setMonth(d.getMonth() - 11 + i);
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
    });
    
    return {
        labels,
        datasets: [{
            label: 'Recuperado',
            borderColor: '#10b981',
            backgroundColor: (context) => {
                const gradient = context.chart.ctx.createLinearGradient(0, 0, 0, 300);
                gradient.addColorStop(0, 'rgba(16, 185, 129, 0.4)');
                gradient.addColorStop(1, 'rgba(16, 185, 129, 0)');
                return gradient;
            },
            pointBackgroundColor: '#10b981',
            pointBorderColor: '#fff',
            pointHoverRadius: 6,
            fill: true,
            tension: 0.4,
            data: monthKeys.map(key => data[key] || 0)
        }],
    };
});
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Centro de Mando
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

                 <!-- Alerta de Error del Servidor -->
                <div v-if="serverError" class="bg-red-100 dark:bg-red-900/40 border-l-4 border-red-500 text-red-700 dark:text-red-300 p-4 rounded-r-lg" role="alert">
                    <div class="flex items-center">
                        <ExclamationTriangleIcon class="h-6 w-6 mr-3"/>
                        <div>
                            <p class="font-bold">Error del Servidor</p>
                            <p>{{ serverError }}</p>
                        </div>
                    </div>
                </div>

                <!-- =============================================================== -->
                <!-- ============ VISTA PARA ADMINS, ABOGADOS Y GESTORES =========== -->
                <!-- =============================================================== -->
                <template v-if="userRole !== 'cliente' && kpis">
                    <!-- Cabecera y Filtros -->
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-lg border dark:border-gray-700">
                         <div class="flex flex-col md:flex-row justify-between md:items-center gap-4">
                            <div>
                                <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Bienvenido, {{ user.name.split(' ')[0] }}</h1>
                                <p class="text-gray-500 dark:text-gray-400 mt-1 flex items-center">
                                    <CalendarDaysIcon class="h-5 w-5 mr-2" />
                                    <span>Hoy es {{ new Date().toLocaleDateString('es-CO', { weekday: 'long', day: 'numeric', month: 'long' }) }}</span>
                                </p>
                            </div>
                            <div class="flex-shrink-0">
                                <SecondaryButton @click="resetFilters">
                                    <ArrowPathIcon class="h-5 w-5 mr-2" /> Reiniciar Filtros
                                </SecondaryButton>
                            </div>
                        </div>
                        <div class="border-t border-gray-200 dark:border-gray-700 mt-4 pt-4">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <InputLabel for="cooperativa_id" value="Cooperativa" class="mb-1"/>
                                    <select v-model="form.cooperativa_id" id="cooperativa_id" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <option value="">Todas las Cooperativas</option>
                                        <option v-for="cooperativa in cooperativas" :key="cooperativa.id" :value="cooperativa.id">{{ cooperativa.nombre }}</option>
                                    </select>
                                </div>
                                <div>
                                    <InputLabel for="fecha_desde" value="Desde" class="mb-1"/>
                                    <TextInput type="date" v-model="form.fecha_desde" id="fecha_desde" class="w-full"/>
                                </div>
                                <div>
                                    <InputLabel for="fecha_hasta" value="Hasta" class="mb-1"/>
                                    <TextInput type="date" v-model="form.fecha_hasta" id="fecha_hasta" class="w-full"/>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- KPIs -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div v-for="(kpi, key, index) in kpis" :key="key" class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-lg flex flex-col justify-between transition-all duration-300 hover:shadow-2xl hover:-translate-y-1 relative" :class="isMounted ? 'animate-fade-in-up' : 'opacity-0'" :style="{ animationDelay: `${index * 100}ms` }">
                            <div class="flex justify-between items-start">
                                <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 capitalize">{{ key.replace(/_/g, ' ') }}</h3>
                                <div v-if="kpi.trend !== null" class="flex items-center text-xs font-semibold px-2 py-1 rounded-full" :class="kpi.direction === 'up' ? 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300'">
                                    <ArrowUpIcon v-if="kpi.direction === 'up'" class="h-4 w-4" /> <ArrowDownIcon v-else class="h-4 w-4" />
                                    <span>{{ kpi.trend }}%</span>
                                </div>
                            </div>
                            <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">
                                <span v-if="key.includes('saldo')">{{ formatCurrency(animatedKpis[key]) }}</span>
                                <span v-else-if="key.includes('tasa')">{{ (animatedKpis[key] || 0).toFixed(1) }}%</span>
                                <span v-else>{{ formatNumber(animatedKpis[key]) }}</span>
                            </p>
                        </div>
                    </div>

                    <!-- Gráficas y Ranking -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="lg:col-span-2 space-y-6">
                            <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-lg border dark:border-gray-700" :class="isMounted ? 'animate-fade-in-up' : 'opacity-0'" style="animation-delay: 500ms;"><h3 class="font-bold text-lg text-gray-900 dark:text-white mb-4">Recuperación Mensual (Último Año)</h3><div class="h-80"><Line :data="recuperacionPorMesData" :options="chartOptions" /></div></div>
                            <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-lg border dark:border-gray-700" :class="isMounted ? 'animate-fade-in-up' : 'opacity-0'" style="animation-delay: 600ms;"><h3 class="font-bold text-lg text-gray-900 dark:text-white mb-4">Distribución de Casos por Estado</h3><div class="h-80"><Bar :data="casosPorEstadoData" :options="chartOptions" /></div></div>
                        </div>
                        <div class="lg:col-span-1" :class="isMounted ? 'animate-fade-in-up' : 'opacity-0'" style="animation-delay: 700ms;">
                           <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-lg border dark:border-gray-700">
                                <h3 class="font-bold text-lg text-gray-900 dark:text-white mb-2 flex items-center"><TrophyIcon class="h-6 w-6 mr-2 text-amber-500"/> Podio de Gestores</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Top 3 por monto recuperado en el período.</p>
                                <ul class="space-y-6">
                                    <li v-for="(abogado, index) in ranking" :key="abogado.id" class="flex items-center">
                                        <div class="text-4xl w-12 text-center">{{ ['🥇', '🥈', '🥉'][index] }}</div>
                                        <div class="ml-4 flex-grow min-w-0">
                                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ abogado.name }}</p>
                                            <p class="text-sm font-bold text-emerald-600 dark:text-emerald-400">{{ formatCurrency(abogado.total_recuperado) }}</p>
                                        </div>
                                    </li>
                                    <li v-if="!ranking || ranking.length === 0" class="text-center text-gray-500 dark:text-gray-400 py-10 italic">No hay datos de recuperación en este período.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- =============================================================== -->
                <!-- ================= VISTA PARA CLIENTES ========================= -->
                <!-- =============================================================== -->
                <template v-if="userRole === 'cliente' && kpis">
                     <div class="space-y-6">
                        <div class="bg-white dark:bg-gray-800 p-8 rounded-2xl shadow-lg text-center" :class="isMounted ? 'animate-fade-in-up' : 'opacity-0'">
                            <img :src="`https://ui-avatars.com/api/?name=${user.name}&background=f3f4f6&color=111827&size=96&bold=true`" class="mx-auto rounded-full -mt-16 mb-4 border-4 border-white dark:border-gray-800 shadow-md" alt="Avatar">
                            <h1 class="text-3xl font-bold text-gray-800 dark:text-white">Bienvenido(a), {{ user.name }}</h1>
                            <p class="text-gray-500 dark:text-gray-400 mt-2 max-w-xl mx-auto">Este es su portal personal. Aquí encontrará un resumen de su estado de cuenta y podrá contactar a su gestor asignado.</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-lg" :class="isMounted ? 'animate-fade-in-up' : 'opacity-0'" style="animation-delay: 100ms;">
                                <h3 class="text-base font-medium text-gray-500 dark:text-gray-400">Saldo Pendiente Total</h3>
                                <p class="mt-2 text-5xl font-bold text-red-600 dark:text-red-400">{{ formatCurrency(animatedKpis.saldo_total_pendiente) }}</p>
                            </div>
                            <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-lg" :class="isMounted ? 'animate-fade-in-up' : 'opacity-0'" style="animation-delay: 200ms;">
                                <h3 class="text-base font-medium text-gray-500 dark:text-gray-400">Casos Activos a su nombre</h3>
                                <p class="mt-2 text-5xl font-bold text-gray-900 dark:text-white">{{ formatNumber(animatedKpis.casos_activos) }}</p>
                            </div>
                        </div>

                        <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-lg" :class="isMounted ? 'animate-fade-in-up' : 'opacity-0'" style="animation-delay: 300ms;">
                            <h3 class="font-bold text-lg text-gray-900 dark:text-white mb-4">Contactar a la Firma</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                <a href="https://wa.me/573152819233" target="_blank" class="group flex items-center p-4 rounded-lg transition-colors duration-200 hover:bg-gray-100 dark:hover:bg-gray-700/50">
                                    <div class="flex-shrink-0 bg-green-100 dark:bg-green-900/50 p-3 rounded-full"><ChatBubbleLeftRightIcon class="h-6 w-6 text-green-600 dark:text-green-400"/></div>
                                    <span class="ml-4 font-medium text-gray-800 dark:text-gray-200">Escribir por WhatsApp</span>
                                </a>
                                <button @click="abrirModalContacto" class="w-full text-left group flex items-center p-4 rounded-lg transition-colors duration-200 hover:bg-gray-100 dark:hover:bg-gray-700/50">
                                    <div class="flex-shrink-0 bg-blue-100 dark:bg-blue-900/50 p-3 rounded-full"><EnvelopeIcon class="h-6 w-6 text-blue-600 dark:text-blue-400"/></div>
                                    <span class="ml-4 font-medium text-gray-800 dark:text-gray-200">Enviar un Mensaje</span>
                                </button>
                                <Link :href="route('profile.edit')" class="group flex items-center p-4 rounded-lg transition-colors duration-200 hover:bg-gray-100 dark:hover:bg-gray-700/50">
                                    <div class="flex-shrink-0 bg-gray-100 dark:bg-gray-700 p-3 rounded-full"><UserCircleIcon class="h-6 w-6 text-gray-600 dark:text-gray-400"/></div>
                                    <span class="ml-4 font-medium text-gray-800 dark:text-gray-200">Editar mi Perfil</span>
                                </Link>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
        
        <!-- Modal de Contacto -->
        <Modal :show="mostrandoModalContacto" @close="() => mostrandoModalContacto = false">
             <form @submit.prevent="enviarMensaje" class="p-6 bg-white dark:bg-gray-800 rounded-lg">
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
                    <SecondaryButton @click="() => mostrandoModalContacto = false"> Cancelar </SecondaryButton>
                    <PrimaryButton class="ms-3" :class="{ 'opacity-25': contactForm.processing }" :disabled="contactForm.processing">
                        Enviar Mensaje
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>

<style>
/* Animación de entrada para las tarjetas y gráficos */
@keyframes fade-in-up {
    0% {
        opacity: 0;
        transform: translateY(20px);
    }
    100% {
        opacity: 1;
        transform: translateY(0);
    }
}
.animate-fade-in-up {
    /* Usa 'both' para que el estado inicial (opacity 0) se aplique antes de que empiece la animación */
    animation: fade-in-up 0.6s ease-out both;
}
</style>

