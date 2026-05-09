<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    ShieldCheckIcon,
    ExclamationTriangleIcon,
    CheckCircleIcon,
    ArrowRightIcon,
    BellAlertIcon,
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

const tone = computed(() => {
    if (data.value.estado === 'completo') {
        return {
            bar: 'bg-emerald-500',
            ring: 'ring-emerald-100 dark:ring-emerald-900/40',
            text: 'text-emerald-700 dark:text-emerald-300',
            bg: 'bg-emerald-50 dark:bg-emerald-900/10',
            border: 'border-emerald-100 dark:border-emerald-900/40',
            icon: CheckCircleIcon,
            label: 'Completo',
        };
    }

    if (data.value.estado === 'riesgo') {
        return {
            bar: 'bg-amber-500',
            ring: 'ring-amber-100 dark:ring-amber-900/40',
            text: 'text-amber-700 dark:text-amber-300',
            bg: 'bg-amber-50 dark:bg-amber-900/10',
            border: 'border-amber-100 dark:border-amber-900/40',
            icon: ExclamationTriangleIcon,
            label: 'Revisar',
        };
    }

    return {
        bar: 'bg-rose-500',
        ring: 'ring-rose-100 dark:ring-rose-900/40',
        text: 'text-rose-700 dark:text-rose-300',
        bg: 'bg-rose-50 dark:bg-rose-900/10',
        border: 'border-rose-100 dark:border-rose-900/40',
        icon: BellAlertIcon,
        label: 'Crítico',
    };
});

const topMissing = computed(() => (data.value.faltantes || []).slice(0, 5));
const notices = computed(() => data.value.avisos || []);

const handleAction = (item) => {
    if (item.accion_tipo === 'tab' && item.tab) {
        emit('go-tab', item.tab);
    }
};
</script>

<template>
    <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-gray-100 dark:border-gray-700 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-4 min-w-0">
                <div class="h-12 w-12 rounded-xl flex items-center justify-center ring-4 shrink-0" :class="[tone.bg, tone.ring]">
                    <component :is="tone.icon" class="h-6 w-6" :class="tone.text" />
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-black uppercase tracking-wider text-gray-900 dark:text-white">Integridad del Expediente</h3>
                        <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded-full border" :class="[tone.bg, tone.border, tone.text]">{{ tone.label }}</span>
                    </div>
                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mt-1 truncate">{{ data.proxima_accion }}</p>
                </div>
            </div>

            <div class="w-full md:w-44 shrink-0">
                <div class="flex items-end justify-between mb-1">
                    <span class="text-[10px] font-black uppercase tracking-widest text-gray-400">Score</span>
                    <span class="text-2xl font-black text-gray-900 dark:text-white">{{ data.score || 0 }}%</span>
                </div>
                <div class="h-2 rounded-full bg-gray-100 dark:bg-gray-700 overflow-hidden">
                    <div class="h-full rounded-full transition-all" :class="tone.bar" :style="{ width: `${data.score || 0}%` }"></div>
                </div>
            </div>
        </div>

        <div class="p-5 grid grid-cols-1 lg:grid-cols-3 gap-5">
            <div class="lg:col-span-2 space-y-3">
                <div v-if="topMissing.length === 0" class="p-4 rounded-xl border border-emerald-100 dark:border-emerald-900/40 bg-emerald-50/60 dark:bg-emerald-900/10">
                    <p class="text-xs font-bold text-emerald-800 dark:text-emerald-300">No hay faltantes críticos registrados.</p>
                </div>

                <div
                    v-for="item in topMissing"
                    :key="item.titulo"
                    class="p-4 rounded-xl border bg-gray-50/80 dark:bg-gray-900/30 border-gray-100 dark:border-gray-700 flex flex-col sm:flex-row sm:items-start justify-between gap-3"
                >
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full shrink-0" :class="item.prioridad === 'alta' ? 'bg-rose-500' : (item.prioridad === 'media' ? 'bg-amber-500' : 'bg-gray-400')"></span>
                            <p class="text-xs font-black uppercase tracking-tight text-gray-900 dark:text-white">{{ item.titulo }}</p>
                        </div>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1 leading-relaxed">{{ item.detalle }}</p>
                    </div>

                    <Link
                        v-if="item.accion_tipo === 'edit' && editHref"
                        :href="editHref"
                        class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-[10px] font-black uppercase text-gray-600 dark:text-gray-300 hover:border-indigo-300 hover:text-indigo-600 transition-colors shrink-0"
                    >
                        {{ item.accion }}
                        <ArrowRightIcon class="h-3 w-3" />
                    </Link>
                    <button
                        v-else-if="item.accion_tipo === 'tab'"
                        type="button"
                        @click="handleAction(item)"
                        class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-800 text-[10px] font-black uppercase text-gray-600 dark:text-gray-300 hover:border-indigo-300 hover:text-indigo-600 transition-colors shrink-0"
                    >
                        {{ item.accion }}
                        <ArrowRightIcon class="h-3 w-3" />
                    </button>
                </div>
            </div>

            <div class="space-y-3">
                <div class="p-4 rounded-xl border border-gray-100 dark:border-gray-700 bg-gray-50/80 dark:bg-gray-900/30">
                    <div class="flex items-center gap-2 mb-3">
                        <ShieldCheckIcon class="h-4 w-4 text-emerald-500" />
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Fortalezas</p>
                    </div>
                    <div v-if="(data.fortalezas || []).length" class="space-y-2">
                        <p v-for="item in data.fortalezas" :key="item" class="text-[11px] font-semibold text-gray-600 dark:text-gray-300 flex items-start gap-2">
                            <CheckCircleIcon class="h-3.5 w-3.5 text-emerald-500 mt-0.5 shrink-0" />
                            <span>{{ item }}</span>
                        </p>
                    </div>
                    <p v-else class="text-[11px] text-gray-400 font-semibold">Aún no hay fortalezas suficientes para destacar.</p>
                </div>

                <div v-if="notices.length" class="p-4 rounded-xl border border-amber-100 dark:border-amber-900/40 bg-amber-50/60 dark:bg-amber-900/10">
                    <p class="text-[10px] font-black uppercase tracking-widest text-amber-600 dark:text-amber-300 mb-2">Pendiente de vigilar</p>
                    <p v-for="notice in notices" :key="notice.titulo" class="text-[11px] font-semibold text-amber-800 dark:text-amber-200 leading-relaxed">
                        {{ notice.titulo }}: {{ notice.detalle }}
                    </p>
                </div>
            </div>
        </div>
    </section>
</template>
