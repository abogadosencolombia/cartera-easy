<script setup>
import HistorialAuditoria from '@/Components/HistorialAuditoria.vue';
import {
    ClockIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    ShieldCheckIcon,
    ArrowPathIcon,
    FingerPrintIcon,
    UserIcon,
    DocumentMagnifyingGlassIcon,
    ClipboardDocumentListIcon,
    CalendarDaysIcon,
    CheckBadgeIcon
} from '@heroicons/vue/24/outline';
import { ref, computed } from 'vue';

const props = defineProps({
    bitacoras: { type: Array, default: () => [] },
    auditoria: { type: Array, default: () => [] },
});

const itemsPerPage = 8;
const currentPage = ref(1);
const totalBitacoras = computed(() => props.bitacoras?.length || 0);
const totalAuditoria = computed(() => props.auditoria?.length || 0);
const totalPages = computed(() => totalBitacoras.value ? Math.ceil(totalBitacoras.value / itemsPerPage) : 0);
const paginatedBitacoras = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return (props.bitacoras || []).slice(start, start + itemsPerPage);
});

const ultimaBitacora = computed(() => props.bitacoras?.[0] || null);
const ultimoEventoAuditoria = computed(() => props.auditoria?.[0] || null);

const nextPage = () => { if (currentPage.value < totalPages.value) currentPage.value++; };
const prevPage = () => { if (currentPage.value > 1) currentPage.value--; };

const formatDateTime = (s) => {
    if (!s) return 'N/A';
    const date = new Date(s);
    if (Number.isNaN(date.getTime())) return 'N/A';
    return date.toLocaleString('es-CO', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const formatDate = (s) => {
    if (!s) return 'N/A';
    const date = new Date(s);
    if (Number.isNaN(date.getTime())) return 'N/A';
    return date.toLocaleDateString('es-CO', { year: 'numeric', month: 'short', day: 'numeric' });
};

const formatAction = (value) => {
    if (!value) return 'Movimiento registrado';
    return String(value).replace(/[_-]/g, ' ').toLowerCase();
};

const userInitial = (name) => (name || 'Sistema').trim().charAt(0).toUpperCase();

const actionTone = (value) => {
    const action = String(value || '').toLowerCase();
    if (action.includes('elimin') || action.includes('borr')) return 'text-rose-700 bg-rose-50 border-rose-100 dark:text-rose-300 dark:bg-rose-900/20 dark:border-rose-900/40';
    if (action.includes('actual') || action.includes('edit') || action.includes('modif')) return 'text-amber-700 bg-amber-50 border-amber-100 dark:text-amber-300 dark:bg-amber-900/20 dark:border-amber-900/40';
    if (action.includes('cre') || action.includes('registr') || action.includes('agreg')) return 'text-emerald-700 bg-emerald-50 border-emerald-100 dark:text-emerald-300 dark:bg-emerald-900/20 dark:border-emerald-900/40';
    return 'text-indigo-700 bg-indigo-50 border-indigo-100 dark:text-indigo-300 dark:bg-indigo-900/20 dark:border-indigo-900/40';
};

const summaryCards = computed(() => [
    {
        label: 'Último movimiento',
        value: ultimaBitacora.value ? formatAction(ultimaBitacora.value.accion) : 'Sin movimientos',
        subtext: ultimaBitacora.value ? formatDateTime(ultimaBitacora.value.created_at) : 'Bitácora pendiente',
        icon: ClockIcon,
        iconClass: 'text-indigo-500'
    },
    {
        label: 'Eventos procesales',
        value: totalBitacoras.value,
        subtext: totalBitacoras.value === 1 ? 'evento registrado' : 'eventos registrados',
        icon: ClipboardDocumentListIcon,
        iconClass: 'text-sky-500'
    },
    {
        label: 'Responsable reciente',
        value: ultimaBitacora.value?.user?.name || 'Sistema',
        subtext: ultimaBitacora.value ? 'último registro' : 'sin responsable',
        icon: UserIcon,
        iconClass: 'text-emerald-500'
    },
    {
        label: 'Integridad',
        value: totalAuditoria.value,
        subtext: ultimoEventoAuditoria.value ? formatDate(ultimoEventoAuditoria.value.created_at) : 'sin cambios técnicos',
        icon: ShieldCheckIcon,
        iconClass: 'text-amber-500'
    },
]);
</script>

<template>
    <div class="space-y-5 animate-in fade-in duration-500">
        <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
            <div
                v-for="item in summaryCards"
                :key="item.label"
                class="min-w-0 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm"
            >
                <div class="flex items-start gap-3">
                    <div class="shrink-0 rounded-lg bg-gray-50 dark:bg-gray-900 p-2">
                        <component :is="item.icon" class="h-5 w-5" :class="item.iconClass" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">{{ item.label }}</p>
                        <p class="mt-1 break-words text-sm font-black uppercase leading-5 text-gray-900 dark:text-white">{{ item.value }}</p>
                        <p class="mt-0.5 break-words text-[10px] font-semibold text-gray-500 dark:text-gray-400">{{ item.subtext }}</p>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 xl:grid-cols-12 gap-5">
            <section class="xl:col-span-7 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                <div class="flex flex-col gap-3 border-b border-gray-100 dark:border-gray-700 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <ClockIcon class="w-5 h-5 text-indigo-500" />
                        <div>
                            <h3 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-tight">Bitácora Procesal</h3>
                            <p class="text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-widest">Movimientos del expediente</p>
                        </div>
                    </div>
                    <span class="w-fit rounded-full bg-gray-100 dark:bg-gray-900 px-3 py-1 text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">
                        {{ totalBitacoras }} evento{{ totalBitacoras !== 1 ? 's' : '' }}
                    </span>
                </div>

                <div class="p-5">
                    <div v-if="!bitacoras.length" class="flex min-h-64 flex-col items-center justify-center rounded-lg border border-dashed border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/30 text-center">
                        <DocumentMagnifyingGlassIcon class="w-12 h-12 mb-3 text-gray-300" />
                        <p class="text-[11px] font-black uppercase tracking-widest text-gray-400">Sin registros de actividad</p>
                    </div>

                    <ol v-else class="relative space-y-4 before:absolute before:bottom-0 before:left-4 before:top-0 before:w-px before:bg-gray-100 dark:before:bg-gray-700">
                        <li v-for="item in paginatedBitacoras" :key="item.id" class="relative pl-10">
                            <div class="absolute left-0 top-1 h-8 w-8 rounded-lg border border-indigo-200 dark:border-indigo-800 bg-white dark:bg-gray-800 shadow-sm flex items-center justify-center z-10 text-indigo-600 dark:text-indigo-300">
                                <FingerPrintIcon class="w-4 h-4" />
                            </div>

                            <article class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/30 p-4">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                    <span class="w-fit rounded-full border px-2.5 py-1 text-[10px] font-black uppercase tracking-widest" :class="actionTone(item.accion)">
                                        {{ formatAction(item.accion) }}
                                    </span>
                                    <time class="text-[10px] font-black uppercase tracking-widest text-gray-400">{{ formatDateTime(item.created_at) }}</time>
                                </div>

                                <p v-if="item.comentario" class="mt-3 whitespace-pre-wrap break-words text-sm leading-6 text-gray-700 dark:text-gray-300">{{ item.comentario }}</p>
                                <p v-else class="mt-3 text-xs font-semibold uppercase tracking-widest text-gray-400">Sin comentario adicional</p>

                                <div class="mt-4 flex flex-col gap-2 border-t border-gray-100 dark:border-gray-700 pt-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="flex items-center gap-2">
                                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-[10px] font-black uppercase text-gray-500 dark:text-gray-300">
                                            {{ userInitial(item.user?.name) }}
                                        </div>
                                        <div>
                                            <p class="text-[9px] font-black uppercase tracking-widest text-gray-400">Responsable</p>
                                            <p class="text-[11px] font-bold uppercase text-gray-700 dark:text-gray-300">{{ item.user?.name || 'Sistema' }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1.5 text-[10px] font-semibold text-gray-500 dark:text-gray-400">
                                        <CalendarDaysIcon class="h-3.5 w-3.5" />
                                        {{ formatDate(item.created_at) }}
                                    </div>
                                </div>
                            </article>
                        </li>
                    </ol>
                </div>

                <div v-if="totalPages > 1" class="flex items-center justify-between border-t border-gray-100 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-900/30 px-5 py-3">
                    <span class="text-[10px] font-black uppercase tracking-widest text-gray-400">Página {{ currentPage }} de {{ totalPages }}</span>
                    <div class="flex gap-2">
                        <button @click="prevPage" :disabled="currentPage === 1" class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-2 text-gray-500 disabled:opacity-30 hover:text-indigo-600" title="Anterior">
                            <ChevronLeftIcon class="h-4 w-4" />
                        </button>
                        <button @click="nextPage" :disabled="currentPage === totalPages" class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-2 text-gray-500 disabled:opacity-30 hover:text-indigo-600" title="Siguiente">
                            <ChevronRightIcon class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </section>

            <aside class="xl:col-span-5 space-y-5">
                <section class="rounded-lg border border-slate-800 bg-slate-950 p-5 shadow-sm">
                    <div class="flex flex-col gap-3 border-b border-white/10 pb-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-3">
                            <ShieldCheckIcon class="w-5 h-5 text-emerald-400" />
                            <div>
                                <h3 class="text-sm font-bold text-white uppercase tracking-tight">Registro de Integridad</h3>
                                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Cambios sensibles y trazabilidad</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-3 py-1">
                            <CheckBadgeIcon class="h-3.5 w-3.5 text-emerald-400" />
                            <span class="text-[9px] font-black uppercase tracking-widest text-emerald-300">Activo</span>
                        </div>
                    </div>

                    <div class="mt-4 max-h-[680px] overflow-y-auto pr-2 custom-scrollbar-dark">
                        <div v-if="!auditoria" class="flex min-h-64 flex-col items-center justify-center opacity-60">
                            <ArrowPathIcon class="w-8 h-8 mb-2 text-emerald-400 animate-spin" />
                            <p class="text-[10px] font-bold uppercase tracking-widest text-emerald-500/70">Analizando registros</p>
                        </div>
                        <HistorialAuditoria v-else :eventos="auditoria" class="!text-[10px]" />
                    </div>
                </section>
            </aside>
        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar-dark::-webkit-scrollbar { width: 4px; }
.custom-scrollbar-dark::-webkit-scrollbar-thumb { background-color: rgba(255, 255, 255, 0.14); border-radius: 10px; }
</style>
