<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage, router, Link } from '@inertiajs/vue3';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Textarea from '@/Components/Textarea.vue';
import InputLabel from '@/Components/InputLabel.vue';
import SelectInput from '@/Components/SelectInput.vue';
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
    BuildingLibraryIcon,
    UsersIcon,
    ChartBarSquareIcon,
    AdjustmentsHorizontalIcon
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
        if (progress < 1) requestAnimationFrame(animate);
        else animatedKpis.value[key] = endValue;
    };
    requestAnimationFrame(animate);
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
            cornerRadius: 12,
            titleFont: { size: 12, weight: 'bold' },
            bodyFont: { size: 12 }
        }
    },
    scales: {
        x: { grid: { display: false }, ticks: { font: { size: 10, weight: 'bold' }, color: '#94a3b8' } },
        y: { border: { display: false }, grid: { color: '#f1f5f9' }, ticks: { font: { size: 10 }, color: '#94a3b8' } }
    },
    layout: { padding: 10 }
};

const barData = computed(() => ({
    labels: Object.keys(props.chartData?.casosPorEstado ?? {}),
    datasets: [{
        label: 'Casos',
        backgroundColor: '#6366f1',
        hoverBackgroundColor: '#4f46e5',
        borderRadius: 8,
        data: Object.values(props.chartData?.casosPorEstado ?? {})
    }],
}));

const lineData = computed(() => {
    const raw = props.chartData?.recuperacionPorMes ?? {};
    const labels = [], data = [];
    const now = new Date();
    
    // Generar últimos 12 meses (de hace 11 meses hasta hoy)
    for (let i = 11; i >= 0; i--) {
        const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const key = `${year}-${month}`; // Formato exacto YYYY-MM
        
        labels.push(d.toLocaleString('es-CO', { month: 'short' }).toUpperCase());
        data.push(raw[key] || 0);
    }
    return {
        labels,
        datasets: [{
            label: 'Recuperado',
            borderColor: '#10b981',
            backgroundColor: (ctx) => {
                const gradient = ctx.chart.ctx.createLinearGradient(0, 0, 0, 300);
                gradient.addColorStop(0, 'rgba(16, 185, 129, 0.1)');
                gradient.addColorStop(1, 'rgba(16, 185, 129, 0)');
                return gradient;
            },
            borderWidth: 3,
            pointBackgroundColor: '#10b981',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6,
            fill: true,
            tension: 0.4,
            data
        }],
    };
});
</script>

<template>
    <Head title="Panel de Control" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h2 class="font-black text-3xl text-gray-900 dark:text-white tracking-tighter">
                        Dashboard Operativo
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">Visualización de indicadores en tiempo real.</p>
                </div>
                <div class="flex items-center px-4 py-2 bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700">
                    <CalendarDaysIcon class="w-5 h-5 mr-2 text-indigo-500"/>
                    <span class="text-sm font-bold text-gray-700 dark:text-gray-300">{{ new Date().toLocaleDateString('es-CO', { day: 'numeric', month: 'long', year: 'numeric' }) }}</span>
                </div>
            </div>
        </template>

        <div class="py-8 bg-slate-50/50 dark:bg-gray-900 min-h-screen">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

                <!-- Alerta de Error -->
                <div v-if="serverError" class="rounded-2xl bg-rose-50 p-4 border border-rose-100 flex items-center gap-4 animate-bounce">
                    <div class="p-2 bg-rose-500 rounded-lg text-white">
                        <ExclamationTriangleIcon class="h-6 w-6"/>
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-rose-900 uppercase tracking-widest">Sincronización Fallida</h3>
                        <p class="text-xs text-rose-700 font-medium">{{ serverError }}</p>
                    </div>
                </div>

                <!-- ================= VISTA CLIENTE ================= -->
                <template v-if="userRole === 'cliente' && kpis">
                    <div class="max-w-5xl mx-auto" :class="isMounted ? 'animate-in' : 'opacity-0'">
                        <div class="relative bg-white dark:bg-gray-800 rounded-[3rem] shadow-2xl shadow-indigo-100 dark:shadow-none border border-gray-100 dark:border-gray-700 p-8 md:p-16 overflow-hidden">
                            <!-- Decoración de fondo -->
                            <div class="absolute -top-24 -right-24 w-64 h-64 bg-indigo-50 dark:bg-indigo-900/10 rounded-full blur-3xl"></div>
                            <div class="absolute -bottom-24 -left-24 w-64 h-64 bg-emerald-50 dark:bg-emerald-900/10 rounded-full blur-3xl"></div>

                            <div class="relative z-10 text-center max-w-2xl mx-auto">
                                <div class="inline-block p-1.5 rounded-[2rem] bg-gradient-to-tr from-indigo-500 to-purple-600 mb-8 shadow-xl">
                                    <img :src="`https://ui-avatars.com/api/?name=${user.name}&background=fff&color=6366f1&size=128&bold=true`" class="w-28 h-28 rounded-[1.8rem] border-4 border-white dark:border-gray-800 shadow-inner" alt="Profile">
                                </div>
                                
                                <h1 class="text-4xl md:text-5xl font-black text-gray-900 dark:text-white tracking-tighter mb-4">
                                    ¡Bienvenido, {{ user.name.split(' ')[0] }}!
                                </h1>
                                <p class="text-gray-500 dark:text-gray-400 text-lg font-medium mb-12 leading-relaxed">
                                    Aquí puede monitorear el progreso de sus requerimientos jurídicos y saldos de forma transparente.
                                </p>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 mb-12">
                                    <div class="p-8 rounded-[2.5rem] bg-slate-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-700 group hover:border-indigo-200 transition-all">
                                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-3">Saldo Total en Gestión</p>
                                        <p class="text-3xl md:text-4xl font-black text-indigo-600 dark:text-indigo-400 tabular-nums">
                                            {{ money(animatedKpis.saldo_total_pendiente) }}
                                        </p>
                                    </div>
                                    <div class="p-8 rounded-[2.5rem] bg-slate-50 dark:bg-gray-900/50 border border-gray-100 dark:border-gray-700 group hover:border-emerald-200 transition-all">
                                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-3">Casos Bajo Supervisión</p>
                                        <p class="text-3xl md:text-4xl font-black text-emerald-600 dark:text-emerald-400 tabular-nums">
                                            {{ num(animatedKpis.casos_activos) }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                                    <button @click="mostrandoModalContacto = true" class="px-8 py-4 bg-indigo-600 text-white rounded-2xl font-black text-sm uppercase tracking-widest hover:bg-indigo-700 transition-all shadow-xl shadow-indigo-200 dark:shadow-none flex items-center justify-center">
                                        <EnvelopeIcon class="w-5 h-5 mr-3"/> Solicitar Información
                                    </button>
                                    <a href="https://wa.me/573152819233" target="_blank" class="px-8 py-4 bg-white dark:bg-gray-700 text-gray-700 dark:text-white border-2 border-gray-100 dark:border-gray-600 rounded-2xl font-black text-sm uppercase tracking-widest hover:bg-gray-50 dark:hover:bg-gray-600 transition-all flex items-center justify-center">
                                        <ChatBubbleLeftRightIcon class="w-5 h-5 mr-3 text-emerald-500"/> Soporte WhatsApp
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- ================= VISTA ADMIN/GESTOR ================= -->
                <template v-else-if="kpis">
                    
                    <!-- Barra de Filtros Inteligente -->
                    <div class="sticky top-4 z-[60] bg-white/80 dark:bg-gray-800/90 backdrop-blur-xl rounded-3xl shadow-xl shadow-slate-200/50 dark:shadow-none border border-white/50 dark:border-gray-700 p-4 mb-10 flex flex-col md:flex-row gap-4 justify-between items-center transition-all duration-500" :class="isMounted ? 'translate-y-0 opacity-100' : '-translate-y-10 opacity-0'">
                        <div class="flex items-center gap-4 w-full md:w-auto">
                            <div class="p-2.5 bg-indigo-600 rounded-xl text-white shadow-lg shadow-indigo-200">
                                <AdjustmentsHorizontalIcon class="w-5 h-5"/>
                            </div>
                            <div class="relative flex-1 md:flex-none">
                                <SelectInput v-model="form.cooperativa_id" class="!rounded-2xl !bg-slate-100 dark:!bg-gray-900 !border-none">
                                    <option value="">Todas las Entidades</option>
                                    <option v-for="c in cooperativas" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                                </SelectInput>
                                <ChevronDownIcon class="absolute right-3 top-3 w-4 h-4 text-gray-400 pointer-events-none"/>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 w-full md:w-auto">
                            <DatePicker v-model="form.fecha_desde" class="w-full md:w-40" placeholder="Inicio" />
                            <div class="h-1 w-2 bg-gray-300 rounded-full"></div>
                            <DatePicker v-model="form.fecha_hasta" class="w-full md:w-40" placeholder="Fin" />
                            <button @click="resetFilters" class="p-2.5 text-gray-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-900/20 rounded-xl transition-all" title="Limpiar Filtros">
                                <ArrowPathIcon class="w-5 h-5"/>
                            </button>
                        </div>
                    </div>

                    <!-- KPIs Operativos -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <div v-for="(kpi, key, idx) in kpis" :key="key" 
                             class="bg-white dark:bg-gray-800 rounded-[2.5rem] p-8 shadow-sm border border-gray-100 dark:border-gray-700 group hover:shadow-xl hover:-translate-y-1 transition-all relative overflow-hidden"
                             :class="isMounted ? 'animate-in' : 'opacity-0'" :style="{ animationDelay: `${idx * 100}ms` }">
                            
                            <div class="absolute -right-6 -bottom-6 opacity-[0.03] group-hover:opacity-[0.08] transition-all transform group-hover:scale-125 group-hover:-rotate-12 dark:text-white">
                                <BanknotesIcon v-if="key.includes('saldo')" class="w-32 h-32"/>
                                <BriefcaseIcon v-else-if="key.includes('activos')" class="w-32 h-32"/>
                                <ChartBarSquareIcon v-else-if="key.includes('tasa')" class="w-32 h-32"/>
                                <TrophyIcon v-else class="w-32 h-32"/>
                            </div>

                            <div class="flex justify-between items-start mb-6">
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] leading-none">{{ key.replace(/_/g, ' ') }}</p>
                                <div v-if="kpi.trend !== undefined" 
                                      class="flex items-center px-2 py-1 rounded-lg text-[10px] font-black" 
                                      :class="kpi.direction === 'up' ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/20' : 'bg-rose-50 text-rose-600 dark:bg-rose-900/20'">
                                    <component :is="kpi.direction === 'up' ? ArrowTrendingUpIcon : ArrowTrendingDownIcon" class="w-3 h-3 mr-1"/>
                                    {{ kpi.trend }}%
                                </div>
                            </div>
                            
                            <h3 class="text-3xl font-black text-gray-900 dark:text-white tabular-nums tracking-tighter">
                                <span v-if="key.includes('saldo')">{{ money(animatedKpis[key]) }}</span>
                                <span v-else-if="key.includes('tasa')">{{ (animatedKpis[key] || 0).toFixed(1) }}%</span>
                                <span v-else>{{ num(animatedKpis[key]) }}</span>
                            </h3>
                        </div>
                    </div>

                    <!-- Gráficos y Leaderboard -->
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                        <!-- Tendencia (Línea) -->
                        <div class="lg:col-span-8 bg-white dark:bg-gray-800 rounded-[3rem] shadow-sm border border-gray-100 dark:border-gray-700 p-10 transition-all" :class="isMounted ? 'animate-in' : 'opacity-0'" style="animation-delay: 400ms">
                            <div class="flex items-center justify-between mb-10">
                                <h3 class="font-black text-xl text-gray-900 dark:text-white tracking-tighter uppercase">Curva de Recuperación</h3>
                                <div class="flex gap-2">
                                    <div class="flex items-center text-[10px] font-bold text-gray-400 italic">
                                        <span class="w-3 h-1 bg-emerald-500 rounded-full mr-2"></span> Tendencia mensual
                                    </div>
                                </div>
                            </div>
                            <div class="h-80">
                                <Line :data="lineData" :options="commonOptions" />
                            </div>
                        </div>

                        <!-- Ranking (Podio) -->
                        <div class="lg:col-span-4 bg-white dark:bg-gray-800 rounded-[3rem] shadow-sm border border-gray-100 dark:border-gray-700 p-8 flex flex-col transition-all" :class="isMounted ? 'animate-in' : 'opacity-0'" style="animation-delay: 550ms">
                            <div class="flex items-center mb-8">
                                <div class="p-3 bg-amber-50 dark:bg-amber-900/20 rounded-2xl mr-4">
                                    <TrophyIcon class="w-6 h-6 text-amber-500"/>
                                </div>
                                <h3 class="font-black text-xl text-gray-900 dark:text-white uppercase tracking-tighter">
                                    {{ userRole === 'admin' ? 'Top Rendimiento' : 'Mi Rendimiento' }}
                                </h3>
                            </div>
                            
                            <div class="flex-1 overflow-y-auto space-y-3 pr-2 custom-scrollbar">
                                <div v-for="(gestor, idx) in ranking" :key="gestor.id" 
                                     class="flex items-center p-4 rounded-2xl hover:bg-slate-50 dark:hover:bg-gray-900 transition-all border border-transparent hover:border-gray-100 group">
                                    <div class="flex-shrink-0 w-10 h-10 flex items-center justify-center rounded-xl font-black text-sm shadow-sm" 
                                         :class="idx === 0 ? 'bg-amber-100 text-amber-700' : (idx === 1 ? 'bg-slate-100 text-slate-600' : 'bg-orange-50 text-orange-700')">
                                        {{ idx + 1 }}
                                    </div>
                                    <div class="ml-4 flex-1 min-w-0">
                                        <p class="text-sm font-black text-gray-900 dark:text-white truncate group-hover:text-indigo-600 transition-colors uppercase">{{ gestor.name }}</p>
                                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mt-0.5">Recuperado</p>
                                    </div>
                                    <div class="text-sm font-black text-emerald-600 dark:text-emerald-400 tabular-nums">
                                        {{ money(gestor.total_recuperado) }}
                                    </div>
                                </div>
                                <div v-if="!ranking.length" class="text-center py-20 text-gray-400 font-bold italic text-sm">Aún no hay datos de cierre</div>
                            </div>
                        </div>

                        <!-- Casos por Estado (Barras) -->
                        <div class="lg:col-span-12 bg-white dark:bg-gray-800 rounded-[3rem] shadow-sm border border-gray-100 dark:border-gray-700 p-10 transition-all" :class="isMounted ? 'animate-in' : 'opacity-0'" style="animation-delay: 700ms">
                             <h3 class="font-black text-xl text-gray-900 dark:text-white tracking-tighter uppercase mb-10">Distribución de Cartera por Etapa</h3>
                             <div class="h-72">
                                <Bar :data="barData" :options="commonOptions" />
                             </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Modal Contacto -->
        <Modal :show="mostrandoModalContacto" @close="mostrandoModalContacto = false">
            <div class="p-8">
                <h2 class="text-2xl font-black text-gray-900 dark:text-white tracking-tighter mb-6">Nuevo Requerimiento</h2>
                <form @submit.prevent="enviarMensaje" class="space-y-6">
                    <div>
                        <InputLabel value="Motivo de consulta" class="text-[10px] font-black uppercase text-gray-400 mb-2" />
                        <TextInput v-model="contactForm.asunto" class="w-full" placeholder="Ej: Aclaración de pagos realizados" />
                        <InputError :message="contactForm.errors.asunto" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel value="Descripción detallada" class="text-[10px] font-black uppercase text-gray-400 mb-2" />
                        <Textarea v-model="contactForm.mensaje" class="w-full" rows="5" placeholder="Describa su solicitud para que un gestor pueda atenderla..." />
                        <InputError :message="contactForm.errors.mensaje" class="mt-2" />
                    </div>
                    <div class="flex justify-end gap-4 mt-8">
                        <SecondaryButton @click="mostrandoModalContacto = false" class="rounded-xl border-2 font-bold">Cerrar</SecondaryButton>
                        <PrimaryButton :disabled="contactForm.processing" class="!rounded-xl !bg-indigo-600 !px-8 font-bold">Enviar Solicitud</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Animaciones de entrada ultra-suaves */
.animate-in {
    animation: slideInUp 1s cubic-bezier(0.19, 1, 0.22, 1) forwards;
}

@keyframes slideInUp {
    from { opacity: 0; transform: translateY(40px); filter: blur(10px); }
    to { opacity: 1; transform: translateY(0); filter: blur(0); }
}

/* Scrollbar invisible pero funcional para el ranking */
.custom-scrollbar::-webkit-scrollbar { width: 3px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { @apply bg-indigo-100 dark:bg-gray-700 rounded-full; }

/* Estilo para los inputs estilizados */
input, select, textarea {
    @apply transition-all duration-300;
}
</style>