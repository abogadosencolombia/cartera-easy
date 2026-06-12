<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    ShieldCheckIcon,
    ExclamationTriangleIcon,
    CheckCircleIcon,
    ArrowRightIcon,
    BellAlertIcon,
    ClipboardDocumentCheckIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    summary: { type: Object, default: null },
    editHref: { type: String, default: '' },
});

const emit = defineEmits(['go-tab']);

const data = computed(() => props.summary || {
    score: 0,
    estado: 'critico',
    faltantes: [],
    avisos: [],
    fortalezas: [],
    proxima_accion: 'Recalcular integridad del expediente.',
});

const score = computed(() => Math.max(0, Math.min(100, Number(data.value.score || 0))));
const missingItems = computed(() => data.value.faltantes || []);
const notices = computed(() => data.value.avisos || []);
const strengths = computed(() => data.value.fortalezas || []);
const highPriorityCount = computed(() => missingItems.value.filter((item) => item.prioridad === 'alta').length);

const tone = computed(() => {
    if (data.value.estado === 'completo') {
        return {
            bar: 'bg-emerald-500',
            text: 'text-emerald-700 dark:text-emerald-300',
            softText: 'text-emerald-800 dark:text-emerald-200',
            bg: 'bg-emerald-50 dark:bg-emerald-950/30',
            border: 'border-emerald-200 dark:border-emerald-900/60',
            icon: CheckCircleIcon,
            label: 'Completo',
            description: 'El expediente tiene la informacion esencial para seguimiento.',
        };
    }

    if (data.value.estado === 'riesgo') {
        return {
            bar: 'bg-amber-500',
            text: 'text-amber-700 dark:text-amber-300',
            softText: 'text-amber-900 dark:text-amber-100',
            bg: 'bg-amber-50 dark:bg-amber-950/30',
            border: 'border-amber-200 dark:border-amber-900/60',
            icon: ExclamationTriangleIcon,
            label: 'Revisar',
            description: 'Hay datos pendientes que pueden afectar la gestion del expediente.',
        };
    }

    return {
        bar: 'bg-rose-500',
        text: 'text-rose-700 dark:text-rose-300',
        softText: 'text-rose-900 dark:text-rose-100',
        bg: 'bg-rose-50 dark:bg-rose-950/30',
        border: 'border-rose-200 dark:border-rose-900/60',
        icon: BellAlertIcon,
        label: 'Critico',
        description: 'Falta informacion clave para confiar en el expediente.',
    };
});

const metricCards = computed(() => [
    {
        label: 'Pendientes',
        value: missingItems.value.length,
        detail: highPriorityCount.value ? `${highPriorityCount.value} de alta prioridad` : 'Sin urgentes marcados',
        class: highPriorityCount.value
            ? 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-300'
            : 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-200',
    },
    {
        label: 'Avisos',
        value: notices.value.length,
        detail: notices.value.length ? 'Requieren vigilancia' : 'Sin alertas adicionales',
        class: notices.value.length
            ? 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-300'
            : 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-200',
    },
    {
        label: 'Fortalezas',
        value: strengths.value.length,
        detail: strengths.value.length ? 'Datos confiables' : 'Aun sin destacar',
        class: strengths.value.length
            ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-300'
            : 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-200',
    },
]);

const priorityClass = (priority) => {
    if (priority === 'alta') return 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-300';
    if (priority === 'media') return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-300';
    return 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-300';
};

const priorityLabel = (priority) => {
    if (priority === 'alta') return 'Alta';
    if (priority === 'media') return 'Media';
    return 'Baja';
};

const handleAction = (item) => {
    if (item.accion_tipo === 'tab' && item.tab) {
        emit('go-tab', item.tab);
    }
};
</script>

<template>
    <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="border-b border-gray-200 p-5 dark:border-gray-700">
            <div class="grid grid-cols-1 gap-5 xl:grid-cols-12 xl:items-center">
                <div class="xl:col-span-5">
                    <div class="flex items-start gap-4">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg border" :class="[tone.bg, tone.border]">
                            <component :is="tone.icon" class="h-6 w-6" :class="tone.text" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Integridad del expediente</p>
                            <div class="mt-1 flex flex-wrap items-center gap-2">
                                <h3 class="text-lg font-black text-gray-950 dark:text-white">{{ score }}% verificable</h3>
                                <span class="rounded-md border px-2.5 py-1 text-[10px] font-black uppercase" :class="[tone.bg, tone.border, tone.text]">
                                    {{ tone.label }}
                                </span>
                            </div>
                            <p class="mt-1 text-sm font-medium leading-relaxed text-gray-600 dark:text-gray-300">{{ tone.description }}</p>
                        </div>
                    </div>
                </div>

                <div class="xl:col-span-4">
                    <div class="rounded-lg border p-4" :class="[tone.bg, tone.border]">
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-[10px] font-black uppercase tracking-widest" :class="tone.text">Proxima accion</p>
                            <ClipboardDocumentCheckIcon class="h-4 w-4" :class="tone.text" />
                        </div>
                        <p class="mt-2 text-sm font-bold leading-relaxed" :class="tone.softText">{{ data.proxima_accion }}</p>
                    </div>
                </div>

                <div class="xl:col-span-3">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Score</p>
                            <p class="text-3xl font-black text-gray-950 dark:text-white">{{ score }}%</p>
                        </div>
                        <p class="text-right text-xs font-semibold text-gray-500 dark:text-gray-400">{{ missingItems.length }} pendiente{{ missingItems.length === 1 ? '' : 's' }}</p>
                    </div>
                    <div class="mt-3 h-3 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                        <div class="h-full rounded-full transition-all" :class="tone.bar" :style="{ width: `${score}%` }"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 border-b border-gray-200 p-5 dark:border-gray-700 md:grid-cols-3">
            <div
                v-for="metric in metricCards"
                :key="metric.label"
                class="rounded-lg border p-4"
                :class="metric.class"
            >
                <p class="text-[10px] font-black uppercase tracking-widest opacity-80">{{ metric.label }}</p>
                <p class="mt-1 text-2xl font-black">{{ metric.value }}</p>
                <p class="mt-1 text-xs font-semibold opacity-90">{{ metric.detail }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-5 p-5 xl:grid-cols-12">
            <div class="xl:col-span-8 space-y-3">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Pendientes por completar</p>
                        <h4 class="text-base font-black text-gray-950 dark:text-white">Datos que afectan la confiabilidad</h4>
                    </div>
                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ missingItems.length }} registro{{ missingItems.length === 1 ? '' : 's' }}</p>
                </div>

                <div v-if="missingItems.length === 0" class="rounded-lg border border-emerald-200 bg-emerald-50 p-5 dark:border-emerald-900/60 dark:bg-emerald-950/30">
                    <div class="flex items-start gap-3">
                        <CheckCircleIcon class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-300" />
                        <div>
                            <p class="text-sm font-black text-emerald-900 dark:text-emerald-100">No hay faltantes criticos registrados.</p>
                            <p class="mt-1 text-sm font-medium text-emerald-800 dark:text-emerald-200">El expediente conserva los datos necesarios para consulta y seguimiento.</p>
                        </div>
                    </div>
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="item in missingItems"
                        :key="`${item.titulo}-${item.detalle}`"
                        class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40"
                    >
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-md border px-2 py-0.5 text-[10px] font-black uppercase" :class="priorityClass(item.prioridad)">
                                        {{ priorityLabel(item.prioridad) }}
                                    </span>
                                    <p class="text-sm font-black text-gray-950 dark:text-white">{{ item.titulo }}</p>
                                </div>
                                <p class="mt-2 text-sm font-medium leading-relaxed text-gray-700 dark:text-gray-300">{{ item.detalle }}</p>
                            </div>

                            <Link
                                v-if="item.accion_tipo === 'edit' && editHref"
                                :href="editHref"
                                class="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-[10px] font-black uppercase text-gray-700 transition hover:border-indigo-300 hover:text-indigo-600 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:text-indigo-300"
                            >
                                {{ item.accion || 'Editar' }}
                                <ArrowRightIcon class="h-3.5 w-3.5" />
                            </Link>
                            <button
                                v-else-if="item.accion_tipo === 'tab'"
                                type="button"
                                @click="handleAction(item)"
                                class="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-[10px] font-black uppercase text-gray-700 transition hover:border-indigo-300 hover:text-indigo-600 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:text-indigo-300"
                            >
                                {{ item.accion || 'Revisar' }}
                                <ArrowRightIcon class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <aside class="xl:col-span-4 space-y-4">
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                    <div class="mb-3 flex items-center gap-2">
                        <ShieldCheckIcon class="h-5 w-5 text-emerald-500" />
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Fortalezas</p>
                            <h4 class="text-sm font-black text-gray-950 dark:text-white">Informacion confiable</h4>
                        </div>
                    </div>
                    <div v-if="strengths.length" class="space-y-2">
                        <p v-for="item in strengths" :key="item" class="flex items-start gap-2 text-sm font-medium leading-relaxed text-gray-700 dark:text-gray-300">
                            <CheckCircleIcon class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" />
                            <span>{{ item }}</span>
                        </p>
                    </div>
                    <p v-else class="text-sm font-medium leading-relaxed text-gray-500 dark:text-gray-400">Aun no hay fortalezas suficientes para destacar.</p>
                </div>

                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/60 dark:bg-amber-950/30">
                    <div class="mb-3 flex items-center gap-2">
                        <ExclamationTriangleIcon class="h-5 w-5 text-amber-600 dark:text-amber-300" />
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-amber-700 dark:text-amber-300">Avisos</p>
                            <h4 class="text-sm font-black text-amber-950 dark:text-amber-100">Pendiente de vigilar</h4>
                        </div>
                    </div>
                    <div v-if="notices.length" class="space-y-3">
                        <div v-for="notice in notices" :key="notice.titulo" class="rounded-lg border border-amber-200 bg-white/70 p-3 dark:border-amber-900/60 dark:bg-gray-900/30">
                            <p class="text-sm font-black text-amber-950 dark:text-amber-100">{{ notice.titulo }}</p>
                            <p class="mt-1 text-sm font-medium leading-relaxed text-amber-800 dark:text-amber-200">{{ notice.detalle }}</p>
                        </div>
                    </div>
                    <p v-else class="text-sm font-medium leading-relaxed text-amber-800 dark:text-amber-200">No hay avisos adicionales en este momento.</p>
                </div>
            </aside>
        </div>
    </section>
</template>
