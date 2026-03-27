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
import DatePicker from '@/Components/DatePicker.vue';
import { ref, computed, watch, onMounted } from 'vue';
import { debounce, pickBy } from 'lodash';
import { Bar, Line } from 'vue-chartjs';
import { Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, PointElement, LineElement, ArcElement, Filler } from 'chart.js';
import {
    BanknotesIcon,
    ArrowPathIcon,
    UserCircleIcon,
    ChatBubbleLeftRightIcon,
    EnvelopeIcon,
    ArrowTrendingUpIcon,
    ArrowTrendingDownIcon,
    CalendarDaysIcon,
    TrophyIcon,
    BriefcaseIcon,
    ExclamationTriangleIcon,
    BuildingLibraryIcon
} from '@heroicons/vue/24/outline';

// Configuración ChartJS
ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, PointElement, LineElement, ArcElement, Filler);

const props = defineProps({
    kpis: Object,
    chartData: Object,
    cooperativas: Array,
    filters: Object,
    ranking: Array,
    userRole: String,
    serverError: String,
});

const user = computed(() => usePage().props.auth.user);
const isMounted = ref(false);

onMounted(() => {
    setTimeout(() => { isMounted.value = true; }, 100);
});

// --- Filtros (Debounce) ---
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
    const filters = pickBy(currentForm, (value) => value !== '' && value !== null);
    router.get(route('dashboard'), filters, { preserveState: true, replace: true, preserveScroll: true });
}, 400), { deep: true });

// --- Animación de Números ---
const animatedKpis = ref({});
const activeAnimations = new Set();
const animateValue = (key, endValue, isCurrency = false, isPercent = false) => {
    const startValue = animatedKpis.value[key] || 0;
    if (startValue === endValue) return;
    
    const duration = 1000;
    let startTime = null;
    const animate = (timestamp) => {
        if (!startTime) startTime = timestamp;
        const progress = Math.min((timestamp - startTime) / duration, 1);
        const easeOut = 1 - Math.pow(1 - progress, 3);
        const current = easeOut * (endValue - startValue) + startValue;
        
        animatedKpis.value[key] = isPercent ? parseFloat(current.toFixed(1)) : (isCurrency ? current : Math.floor(current));
        if (progress < 1) {
            requestAnimationFrame(animate);
        } else {
            animatedKpis.value[key] = endValue;
            activeAnimations.delete(key);
        }
    };
    
    if (!activeAnimations.has(key)) {
        activeAnimations.add(key);
        requestAnimationFrame(animate);
    }
};

watch(() => props.kpis, (newKpis) => {
    if (newKpis) {
        Object.entries(newKpis).forEach(([key, kpi]) => {
            const val = kpi?.value ?? kpi ?? 0;
            animateValue(key, val, key.includes('saldo'), key.includes('tasa'));
        });
    }
}, { immediate: true, deep: true });

// --- Modal Contacto ---
const mostrandoModalContacto = ref(false);
const contactForm = useForm({ asunto: '', mensaje: '' });
const enviarMensaje = () => {
    contactForm.post(route('contacto.cliente.enviar'), {
        preserveScroll: true,
        onSuccess: () => { mostrandoModalContacto.value = false; contactForm.reset(); },
    });
};

// --- Formatters ---
const money = (val) => new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(val || 0);
const num = (val) => new Intl.NumberFormat('es-CO').format(val || 0);

// --- Charts Config ---
const commonOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: '#1e293b',
            padding: 12,
            cornerRadius: 8,
            titleFont: { size: 13 },
            bodyFont: { size: 13 }
        }
    },
    scales: {
        x: { grid: { display: false }, ticks: { font: { size: 11 } } },
        y: { border: { display: false }, grid: { color: '#f1f5f9' }, ticks: { font: { size: 10 } } }
    },
    layout: { padding: 10 }
};

const barData = computed(() => ({
    labels: Object.keys(props.chartData?.casosPorEstado ?? {}),
    datasets: [{
        label: 'Casos',
        backgroundColor: '#6366f1',
        borderRadius: 6,
        data: Object.values(props.chartData?.casosPorEstado ?? {})
    }],
}));

const lineData = computed(() => {
    const raw = props.chartData?.recuperacionPorMes ?? {};
    // Generar últimos 12 meses de forma segura
    const labels = [], data = [];
    const baseDate = new Date();
    baseDate.setDate(1); // Evitar saltos si hoy es 31 y el mes anterior tiene 30
    
    for (let i = 11; i >= 0; i--) {
        const d = new Date(baseDate);
        d.setMonth(baseDate.getMonth() - i);
        const key = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
        labels.push(d.toLocaleString('es-CO', { month: 'short' }));
        data.push(raw[key] || 0);
    }
    return {
        labels,
        datasets: [{
            label: 'Recuperado',
            borderColor: '#10b981',
            backgroundColor: (ctx) => {
                const gradient = ctx.chart.ctx.createLinearGradient(0, 0, 0, 300);
                gradient.addColorStop(0, 'rgba(16, 185, 129, 0.2)');
                gradient.addColorStop(1, 'rgba(16, 185, 129, 0)');
                return gradient;
            },
            borderWidth: 2,
            pointRadius: 3,
            fill: true,
            tension: 0.4,
            data
        }],
    };
});
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <!-- Header Minimalista -->
        <template #header>
            <div class="flex justify-between items-center">
                <h2 class="font-bold text-2xl text-blue-500 dark:text-gray-100 tracking-tight">
                    Resumen General
                </h2>
                <div class="hidden sm:flex items-center text-sm text-gray-500 bg-white dark:bg-gray-800 px-3 py-1 rounded-full shadow-sm">
                    <CalendarDaysIcon class="w-4 h-4 mr-2 text-indigo-500"/>
                    {{ new Date().toLocaleDateString('es-CO', { dateStyle: 'long' }) }}
                </div>
            </div>
        </template>

        <div class="py-10 bg-gray-50/50 dark:bg-gray-900 min-h-screen">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">

                <!-- Alerta de Error -->
                <div v-if="serverError" class="rounded-xl bg-red-50 p-4 border border-red-100 flex items-start gap-3">
                    <ExclamationTriangleIcon class="h-5 w-5 text-red-600 mt-0.5"/>
                    <div>
                        <h3 class="text-sm font-bold text-red-800">Atención requerida</h3>
                        <p class="text-sm text-red-700 mt-1">{{ serverError }}</p>
                    </div>
                </div>

                <!-- ================= VISTA CLIENTE (Minimalista & Premium) ================= -->
                <template v-if="userRole === 'cliente' && kpis">
                    <div class="max-w-4xl mx-auto space-y-8" :class="isMounted ? 'animate-in' : 'opacity-0'">
                        
                        <!-- Hero Card Cliente -->
                        <div class="relative overflow-hidden bg-white dark:bg-gray-800 rounded-3xl shadow-xl p-8 sm:p-12 text-center border border-gray-100 dark:border-gray-700">
                            <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-blue-500 to-indigo-600"></div>
                            
                            <div class="inline-block p-1 rounded-full bg-gradient-to-br from-gray-100 to-gray-50 mb-6">
                                <img :src="`https://ui-avatars.com/api/?name=${user.name}&background=6366f1&color=fff&size=128&bold=true`" class="w-24 h-24 rounded-full border-4 border-white dark:border-gray-800 shadow-sm" alt="Profile">
                            </div>
                            
                            <h1 class="text-3xl sm:text-4xl font-extrabold text-gray-900 dark:text-white tracking-tight mb-2">
                                Hola, {{ user.name.split(' ')[0] }}
                            </h1>
                            <p class="text-gray-500 dark:text-gray-400 text-lg mb-10">
                                Estado actual de su cuenta
                            </p>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-8 mb-10">
                                <div class="p-6 rounded-2xl bg-red-50 dark:bg-red-900/20 border border-red-100 dark:border-red-900/30">
                                    <p class="text-sm font-semibold text-red-600 dark:text-red-400 uppercase tracking-wider mb-2">Saldo Pendiente</p>
                                    <p class="text-3xl sm:text-4xl font-bold text-gray-900 dark:text-white">
                                        {{ money(animatedKpis.saldo_total_pendiente) }}
                                    </p>
                                </div>
                                <div class="p-6 rounded-2xl bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-900/30">
                                    <p class="text-sm font-semibold text-blue-600 dark:text-blue-400 uppercase tracking-wider mb-2">Casos Activos</p>
                                    <p class="text-3xl sm:text-4xl font-bold text-gray-900 dark:text-white">
                                        {{ num(animatedKpis.casos_activos) }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                                <button @click="mostrandoModalContacto = true" 
                                        aria-label="Contactar con un gestor"
                                        class="inline-flex justify-center items-center px-6 py-3 border border-transparent text-base font-medium rounded-xl text-white bg-blue-500 hover:bg-blue-600 shadow-lg shadow-blue-200 dark:shadow-none transition-all">
                                    <EnvelopeIcon class="w-5 h-5 mr-2"/> Contactar Gestor
                                </button>
                                <a href="https://wa.me/573152819233" 
                                   target="_blank" 
                                   aria-label="Contactar por WhatsApp"
                                   class="inline-flex justify-center items-center px-6 py-3 border border-gray-200 dark:border-gray-600 text-base font-medium rounded-xl text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition-all">
                                    <ChatBubbleLeftRightIcon class="w-5 h-5 mr-2 text-green-500"/> WhatsApp
                                </a>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- ================= VISTA ADMIN/GESTOR (Operativa) ================= -->
                <template v-else-if="kpis">
                    
                    <!-- Barra de Filtros Flotante -->
                    <div class="sticky top-4 z-10 bg-white/80 dark:bg-gray-800/90 backdrop-blur-md rounded-2xl shadow-sm border border-gray-200/60 dark:border-gray-700 p-4 mb-8 flex flex-col md:flex-row gap-4 justify-between items-center transition-all duration-300" :class="isMounted ? 'translate-y-0 opacity-100' : '-translate-y-4 opacity-0'">
                        <div class="flex items-center gap-2 w-full md:w-auto">
                            <BuildingLibraryIcon class="w-5 h-5 text-indigo-600"/>
                            <select v-model="form.cooperativa_id" class="form-select-minimal w-full md:w-64">
                                <option value="">Todas las Cooperativas</option>
                                <option v-for="c in cooperativas" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                            </select>
                        </div>
                        <div class="flex items-center gap-3 w-full md:w-auto">
                            <DatePicker v-model="form.fecha_desde" class="w-full md:w-40" placeholder="Desde" />
                            <span class="text-gray-400">-</span>
                            <DatePicker v-model="form.fecha_hasta" class="w-full md:w-40" placeholder="Hasta" />
                            <button @click="resetFilters" class="p-2 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-colors" title="Limpiar">
                                <ArrowPathIcon class="w-5 h-5"/>
                            </button>
                        </div>
                    </div>

                    <!-- KPIs Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div v-for="(kpi, key, idx) in kpis" :key="key" 
                             class="bg-white dark:bg-gray-800 rounded-2xl p-6 shadow-sm border border-gray-100 dark:border-gray-700 hover:shadow-md transition-shadow relative overflow-hidden group"
                             :class="isMounted ? 'animate-in' : 'opacity-0'" :style="{ animationDelay: `${idx * 100}ms` }">
                            
                            <!-- Icono de Fondo Sutil -->
                            <div class="absolute -right-4 -bottom-4 opacity-5 group-hover:opacity-10 transition-opacity transform group-hover:scale-110">
                                <BanknotesIcon v-if="key.includes('saldo')" class="w-24 h-24"/>
                                <BriefcaseIcon v-else-if="key.includes('activos')" class="w-24 h-24"/>
                                <ArrowPathIcon v-else-if="key.includes('tasa')" class="w-24 h-24"/>
                                <TrophyIcon v-else class="w-24 h-24"/>
                            </div>

                            <div class="flex justify-between items-start mb-4">
                                <p class="text-xs font-bold text-gray-500 uppercase tracking-wider">{{ key.replace(/_/g, ' ') }}</p>
                                <span v-if="kpi.trend !== null" 
                                      class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium" 
                                      :class="kpi.direction === 'up' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
                                    <component :is="kpi.direction === 'up' ? ArrowTrendingUpIcon : ArrowTrendingDownIcon" class="w-3 h-3 mr-1"/>
                                    {{ kpi.trend }}%
                                </span>
                            </div>
                            
                            <h3 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">
                                <span v-if="key.includes('saldo')">{{ money(animatedKpis[key]) }}</span>
                                <span v-else-if="key.includes('tasa')">{{ (animatedKpis[key] || 0).toFixed(1) }}%</span>
                                <span v-else>{{ num(animatedKpis[key]) }}</span>
                            </h3>
                        </div>
                    </div>

                    <!-- Gráficos y Ranking -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Recuperación (Línea) -->
                        <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6" :class="isMounted ? 'animate-in' : 'opacity-0'" style="animation-delay: 400ms">
                            <h3 class="font-semibold text-gray-800 dark:text-white mb-6">Tendencia de Recuperación</h3>
                            <div class="h-72">
                                <Line :data="lineData" :options="commonOptions" />
                            </div>
                        </div>

                        <!-- Ranking Gestores -->
                        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6 flex flex-col" :class="isMounted ? 'animate-in' : 'opacity-0'" style="animation-delay: 500ms">
                            <h3 class="font-semibold text-blue-500 dark:text-white mb-4 flex items-center">
                                <TrophyIcon class="w-5 h-5 text-amber-400 mr-2"/> Top Usuarios
                            </h3>
                            <div class="flex-1 overflow-y-auto space-y-4 pr-2 custom-scrollbar">
                                <div v-for="(gestor, idx) in ranking" :key="gestor.id" class="flex items-center p-3 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                    <div class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-full font-bold text-sm" 
                                         :class="idx === 0 ? 'bg-amber-100 text-amber-700' : (idx === 1 ? 'bg-gray-100 text-gray-600' : 'bg-orange-50 text-orange-700')">
                                        {{ idx + 1 }}
                                    </div>
                                    <div class="ml-3 flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ gestor.name }}</p>
                                        <p class="text-xs text-gray-500">Recuperado</p>
                                    </div>
                                    <div class="text-sm font-bold text-emerald-600 dark:text-emerald-400">
                                        {{ money(gestor.total_recuperado) }}
                                    </div>
                                </div>
                                <div v-if="!ranking.length" class="text-center py-10 text-gray-400 text-sm">Sin datos para mostrar</div>
                            </div>
                        </div>

                        <!-- Distribución (Barras) - Full Width Bottom -->
                        <div class="lg:col-span-3 bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700 p-6" :class="isMounted ? 'animate-in' : 'opacity-0'" style="animation-delay: 600ms">
                             <h3 class="font-semibold text-gray-800 dark:text-white mb-6">Casos por Etapa Procesal</h3>
                             <div class="h-64">
                                <Bar :data="barData" :options="commonOptions" />
                             </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Modal Contacto Cliente -->
        <Modal :show="mostrandoModalContacto" @close="mostrandoModalContacto = false">
            <div class="p-6">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Nuevo Mensaje</h2>
                <form @submit.prevent="enviarMensaje" class="space-y-4">
                    <div>
                        <InputLabel value="Asunto" />
                        <TextInput v-model="contactForm.asunto" class="w-full mt-1" placeholder="Ej: Duda sobre mi saldo" />
                        <InputError :message="contactForm.errors.asunto" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel value="Mensaje" />
                        <Textarea v-model="contactForm.mensaje" class="w-full mt-1" rows="4" placeholder="Escriba su consulta aquí..." />
                        <InputError :message="contactForm.errors.mensaje" class="mt-2" />
                    </div>
                    <div class="flex justify-end gap-3 mt-6">
                        <SecondaryButton @click="mostrandoModalContacto = false">Cancelar</SecondaryButton>
                        <PrimaryButton :disabled="contactForm.processing">Enviar</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Estilos personalizados para inputs minimalistas */
.form-select-minimal, .form-input-minimal {
    @apply border-0 bg-gray-100 dark:bg-gray-700 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 py-2 px-3 text-gray-700 dark:text-gray-200;
}

/* Animación de entrada suave */
.animate-in {
    animation: fadeInSlideUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes fadeInSlideUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Scrollbar fina para el ranking */
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { @apply bg-gray-200 dark:bg-gray-600 rounded-full; }
</style>