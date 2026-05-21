<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Textarea from '@/Components/Textarea.vue';
import InputLabel from '@/Components/InputLabel.vue';
import SelectInput from '@/Components/SelectInput.vue';
import InputError from '@/Components/InputError.vue';
import DatePicker from '@/Components/DatePicker.vue';
import { ref, computed, watch } from 'vue';
import { debounce, pickBy } from 'lodash';
import { Bar, Line } from 'vue-chartjs';
import { Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, PointElement, LineElement, Filler } from 'chart.js';
import {
    ArrowPathIcon,
    ArrowTrendingDownIcon,
    ArrowTrendingUpIcon,
    BanknotesIcon,
    BriefcaseIcon,
    CalendarDaysIcon,
    ChartBarSquareIcon,
    ChatBubbleLeftRightIcon,
    ClipboardDocumentCheckIcon,
    EnvelopeIcon,
    ExclamationTriangleIcon,
    FunnelIcon,
    ScaleIcon,
    TrophyIcon,
    UserGroupIcon,
} from '@heroicons/vue/24/outline';

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, PointElement, LineElement, Filler);

const props = defineProps({
    kpis: Object,
    chartData: Object,
    cooperativas: Array,
    filters: Object,
    ranking: Array,
    userRole: String,
    serverError: String,
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const pendingGestionDiaria = computed(() => Number(page.props.auth?.pendingGestionDiaria ?? 0));
const rankingList = computed(() => props.ranking ?? []);

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

watch(() => form.data(), debounce((currentForm) => {
    const filters = pickBy(currentForm, (value) => value !== '' && value !== null);
    router.get(route('dashboard'), filters, {
        preserveState: true,
        replace: true,
        preserveScroll: true,
    });
}, 400), { deep: true });

const mostrandoModalContacto = ref(false);
const contactForm = useForm({ asunto: '', mensaje: '' });

const enviarMensaje = () => {
    contactForm.post(route('contacto.cliente.enviar'), {
        preserveScroll: true,
        onSuccess: () => {
            mostrandoModalContacto.value = false;
            contactForm.reset();
        },
    });
};

const money = (val) => new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    maximumFractionDigits: 0,
}).format(val || 0);

const num = (val) => new Intl.NumberFormat('es-CO').format(val || 0);

const formatKpiValue = (key, rawValue) => {
    const value = rawValue?.value ?? rawValue ?? 0;

    if (key.includes('saldo')) return money(value);
    if (key.includes('tasa')) return `${Number(value || 0).toFixed(1)}%`;

    return num(value);
};

const kpiMeta = {
    saldo_bajo_gestion: {
        label: 'Saldo bajo gestión',
        helper: 'Cartera activa filtrada',
        icon: BanknotesIcon,
    },
    tasa_recuperacion: {
        label: 'Tasa de recuperación',
        helper: 'Recuperado sobre asignado',
        icon: ChartBarSquareIcon,
    },
    casos_asignados: {
        label: 'Casos asignados',
        helper: 'Carga operativa actual',
        icon: BriefcaseIcon,
    },
    casos_cerrados: {
        label: 'Casos cerrados',
        helper: 'Resultados del periodo',
        icon: ClipboardDocumentCheckIcon,
    },
    saldo_total_pendiente: {
        label: 'Saldo en gestión',
        helper: 'Valor pendiente asociado',
        icon: BanknotesIcon,
    },
    casos_activos: {
        label: 'Casos activos',
        helper: 'Procesos en seguimiento',
        icon: ScaleIcon,
    },
};

const kpiCards = computed(() => Object.entries(props.kpis ?? {}).map(([key, kpi]) => ({
    key,
    value: formatKpiValue(key, kpi),
    trend: kpi?.trend,
    direction: kpi?.direction,
    ...(kpiMeta[key] ?? {
        label: key.replace(/_/g, ' '),
        helper: 'Indicador operativo',
        icon: ChartBarSquareIcon,
    }),
})));

const openGestionDiaria = () => {
    window.dispatchEvent(new CustomEvent('open-gestion-diaria'));
};

const actionCards = computed(() => [
    {
        label: 'Gestión diaria',
        description: pendingGestionDiaria.value
            ? `${pendingGestionDiaria.value} pendientes por atender`
            : 'Crear tareas, plazos y recordatorios',
        icon: ClipboardDocumentCheckIcon,
        tone: 'emerald',
        action: openGestionDiaria,
    },
    {
        label: 'Revisión diaria',
        description: 'Auditar expedientes y próximos vencimientos',
        icon: CalendarDaysIcon,
        tone: 'sky',
        href: route('revision.index'),
    },
    {
        label: 'Nuevo caso',
        description: 'Registrar un proceso jurídico',
        icon: ScaleIcon,
        tone: 'violet',
        href: route('casos.create'),
    },
    {
        label: 'Analítica',
        description: 'Ver cartera, recuperación y comportamiento',
        icon: ChartBarSquareIcon,
        tone: 'amber',
        href: route('analytics.index'),
    },
]);

const handleAction = (action) => {
    if (action.action) {
        action.action();
        return;
    }

    router.visit(action.href);
};

const activeFilterCount = computed(() => [
    form.cooperativa_id,
    form.fecha_desde,
    form.fecha_hasta,
].filter(Boolean).length);

const commonOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: '#111827',
            padding: 12,
            cornerRadius: 8,
            titleFont: { size: 12, weight: 'bold' },
            bodyFont: { size: 12 },
        },
    },
    scales: {
        x: {
            grid: { display: false },
            ticks: { color: '#64748b', font: { size: 11, weight: '600' } },
        },
        y: {
            border: { display: false },
            grid: { color: '#e5e7eb' },
            ticks: { color: '#64748b', font: { size: 11 } },
        },
    },
};

const barData = computed(() => ({
    labels: Object.keys(props.chartData?.casosPorEstado ?? {}),
    datasets: [{
        label: 'Casos',
        backgroundColor: '#2563eb',
        hoverBackgroundColor: '#1d4ed8',
        borderRadius: 6,
        data: Object.values(props.chartData?.casosPorEstado ?? {}),
    }],
}));

const lineData = computed(() => {
    const raw = props.chartData?.recuperacionPorMes ?? {};
    const labels = [];
    const data = [];
    const now = new Date();

    for (let i = 11; i >= 0; i--) {
        const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const key = `${year}-${month}`;

        labels.push(d.toLocaleString('es-CO', { month: 'short' }).toUpperCase());
        data.push(raw[key] || 0);
    }

    return {
        labels,
        datasets: [{
            label: 'Recuperado',
            borderColor: '#059669',
            backgroundColor: 'rgba(5, 150, 105, 0.08)',
            borderWidth: 3,
            pointBackgroundColor: '#059669',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            pointRadius: 3,
            pointHoverRadius: 5,
            fill: true,
            tension: 0.35,
            data,
        }],
    };
});

const todayLabel = new Date().toLocaleDateString('es-CO', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
});
</script>

<template>
    <Head title="Panel de Control" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase text-blue-600 dark:text-blue-400">
                        Operación diaria
                    </p>
                    <h2 class="text-2xl font-semibold text-gray-950 dark:text-white">
                        Panel de control jurídico
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Priorización, cartera y rendimiento para tomar decisiones durante el día.
                    </p>
                </div>
                <div class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                    <CalendarDaysIcon class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                    <span class="capitalize">{{ todayLabel }}</span>
                </div>
            </div>
        </template>

        <div class="min-h-screen bg-gray-50 py-6 dark:bg-gray-950">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <div
                    v-if="serverError"
                    class="flex items-start gap-3 rounded-lg border border-rose-200 bg-rose-50 p-4 text-rose-800 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-200"
                >
                    <ExclamationTriangleIcon class="mt-0.5 h-5 w-5 shrink-0" />
                    <div>
                        <p class="text-sm font-semibold">No se pudo sincronizar la información</p>
                        <p class="mt-1 text-sm">{{ serverError }}</p>
                    </div>
                </div>

                <template v-if="userRole === 'cliente' && kpis">
                    <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="grid gap-6 lg:grid-cols-[1.2fr_0.8fr] lg:items-center">
                            <div>
                                <p class="text-sm font-semibold text-blue-600 dark:text-blue-400">
                                    Hola, {{ user.name.split(' ')[0] }}
                                </p>
                                <h1 class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">
                                    Seguimiento de sus procesos jurídicos
                                </h1>
                                <p class="mt-2 max-w-2xl text-sm leading-6 text-gray-600 dark:text-gray-300">
                                    Consulte el estado general de sus casos y solicite información cuando necesite una actualización puntual.
                                </p>
                                <div class="mt-5 flex flex-col gap-3 sm:flex-row">
                                    <button
                                        type="button"
                                        @click="mostrandoModalContacto = true"
                                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700"
                                    >
                                        <EnvelopeIcon class="h-5 w-5" />
                                        Solicitar información
                                    </button>
                                    <a
                                        href="https://wa.me/573152819233"
                                        target="_blank"
                                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
                                    >
                                        <ChatBubbleLeftRightIcon class="h-5 w-5 text-emerald-600" />
                                        WhatsApp
                                    </a>
                                </div>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                                <div
                                    v-for="card in kpiCards"
                                    :key="card.key"
                                    class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950"
                                >
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ card.label }}</p>
                                            <p class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ card.value }}</p>
                                        </div>
                                        <component :is="card.icon" class="h-8 w-8 text-blue-600 dark:text-blue-400" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </template>

                <template v-else-if="kpis">
                    <section class="grid gap-4 lg:grid-cols-4">
                        <button
                            v-for="action in actionCards"
                            :key="action.label"
                            type="button"
                            @click="handleAction(action)"
                            class="group rounded-lg border border-gray-200 bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-md dark:border-gray-800 dark:bg-gray-900 dark:hover:border-blue-700"
                        >
                            <div class="flex items-start gap-3">
                                <span
                                    class="rounded-lg p-2"
                                    :class="{
                                        'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300': action.tone === 'emerald',
                                        'bg-sky-50 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300': action.tone === 'sky',
                                        'bg-violet-50 text-violet-700 dark:bg-violet-950/40 dark:text-violet-300': action.tone === 'violet',
                                        'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300': action.tone === 'amber',
                                    }"
                                >
                                    <component :is="action.icon" class="h-5 w-5" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-gray-950 dark:text-white">{{ action.label }}</span>
                                    <span class="mt-1 block text-sm leading-5 text-gray-500 dark:text-gray-400">{{ action.description }}</span>
                                </span>
                            </div>
                        </button>
                    </section>

                    <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                            <div class="flex items-center gap-3">
                                <div class="rounded-lg bg-blue-50 p-2 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300">
                                    <FunnelIcon class="h-5 w-5" />
                                </div>
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Filtros operativos</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ activeFilterCount ? `${activeFilterCount} filtro(s) activo(s)` : 'Vista general de la operación' }}
                                    </p>
                                </div>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[220px_160px_160px_44px]">
                                <SelectInput v-model="form.cooperativa_id" class="w-full">
                                    <option value="">Todas las entidades</option>
                                    <option v-for="c in cooperativas" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                                </SelectInput>
                                <DatePicker v-model="form.fecha_desde" class="w-full" placeholder="Desde" />
                                <DatePicker v-model="form.fecha_hasta" class="w-full" placeholder="Hasta" />
                                <button
                                    type="button"
                                    @click="resetFilters"
                                    class="inline-flex h-10 items-center justify-center rounded-lg border border-gray-300 bg-white text-gray-600 transition hover:bg-gray-50 hover:text-rose-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                                    title="Limpiar filtros"
                                >
                                    <ArrowPathIcon class="h-5 w-5" />
                                </button>
                            </div>
                        </div>
                    </section>

                    <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div
                            v-for="card in kpiCards"
                            :key="card.key"
                            class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ card.label }}</p>
                                    <p class="mt-2 text-2xl font-semibold tracking-tight text-gray-950 dark:text-white">
                                        {{ card.value }}
                                    </p>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ card.helper }}</p>
                                </div>
                                <div class="rounded-lg bg-gray-100 p-2 text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                    <component :is="card.icon" class="h-5 w-5" />
                                </div>
                            </div>

                            <div
                                v-if="card.trend !== undefined"
                                class="mt-4 inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-semibold"
                                :class="card.direction === 'up'
                                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                                    : 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300'"
                            >
                                <component :is="card.direction === 'up' ? ArrowTrendingUpIcon : ArrowTrendingDownIcon" class="h-4 w-4" />
                                {{ card.trend }}% frente al periodo anterior
                            </div>
                        </div>
                    </section>

                    <section class="grid grid-cols-1 gap-6 xl:grid-cols-12">
                        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 xl:col-span-8">
                            <div class="mb-5 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">Recuperación mensual</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Últimos 12 meses de recaudo registrado.</p>
                                </div>
                                <span class="inline-flex items-center gap-2 text-sm font-medium text-emerald-700 dark:text-emerald-300">
                                    <span class="h-2 w-2 rounded-full bg-emerald-600"></span>
                                    Recuperado
                                </span>
                            </div>
                            <div class="h-72">
                                <Line :data="lineData" :options="commonOptions" />
                            </div>
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 xl:col-span-4">
                            <div class="mb-5 flex items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                                        {{ userRole === 'admin' ? 'Rendimiento del equipo' : 'Mi rendimiento' }}
                                    </h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Recuperación por gestor.</p>
                                </div>
                                <TrophyIcon class="h-6 w-6 text-amber-500" />
                            </div>

                            <div class="max-h-72 space-y-2 overflow-y-auto pr-1 custom-scrollbar">
                                <div
                                    v-for="(gestor, idx) in rankingList"
                                    :key="gestor.id"
                                    class="flex items-center gap-3 rounded-lg border border-gray-100 p-3 dark:border-gray-800"
                                >
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-sm font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                        {{ idx + 1 }}
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ gestor.name }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Recuperado</p>
                                    </div>
                                    <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-300">
                                        {{ money(gestor.total_recuperado) }}
                                    </p>
                                </div>

                                <div v-if="!rankingList.length" class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                    Aún no hay datos de rendimiento para este filtro.
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 xl:col-span-12">
                            <div class="mb-5 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">Casos por estado</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Distribución de la cartera según etapa operativa.</p>
                                </div>
                                <UserGroupIcon class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                            </div>
                            <div class="h-72">
                                <Bar :data="barData" :options="commonOptions" />
                            </div>
                        </div>
                    </section>
                </template>
            </div>
        </div>

        <Modal :show="mostrandoModalContacto" @close="mostrandoModalContacto = false">
            <div class="p-6">
                <h2 class="text-xl font-semibold text-gray-950 dark:text-white">Nuevo requerimiento</h2>
                <form @submit.prevent="enviarMensaje" class="mt-6 space-y-5">
                    <div>
                        <InputLabel value="Motivo de consulta" />
                        <TextInput v-model="contactForm.asunto" class="mt-2 w-full" placeholder="Ej: Aclaración de pagos realizados" />
                        <InputError :message="contactForm.errors.asunto" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel value="Descripción detallada" />
                        <Textarea v-model="contactForm.mensaje" class="mt-2 w-full" rows="5" placeholder="Describa su solicitud para que un gestor pueda atenderla..." />
                        <InputError :message="contactForm.errors.mensaje" class="mt-2" />
                    </div>
                    <div class="flex justify-end gap-3 pt-2">
                        <SecondaryButton @click="mostrandoModalContacto = false">Cerrar</SecondaryButton>
                        <PrimaryButton :disabled="contactForm.processing">Enviar solicitud</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    @apply rounded-full bg-gray-300 dark:bg-gray-700;
}
</style>
