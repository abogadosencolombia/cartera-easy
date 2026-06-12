<script setup>
import HistorialAuditoria from '@/Components/HistorialAuditoria.vue';
import {
    ClockIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    ShieldCheckIcon,
    UserIcon,
    DocumentMagnifyingGlassIcon,
    BookmarkIcon,
    ClipboardDocumentListIcon,
    CalendarDaysIcon,
    FingerPrintIcon,
} from '@heroicons/vue/24/outline';
import { ref, computed, watch } from 'vue';

const props = defineProps({
    proceso: { type: Object, required: true },
    auditoria: { type: Array, default: () => [] },
});

const itemsPerPage = 6;
const currentPage = ref(1);

const actuacionesList = computed(() => props.proceso.actuaciones || []);
const auditoriaList = computed(() => props.auditoria || []);
const totalPages = computed(() => actuacionesList.value.length ? Math.ceil(actuacionesList.value.length / itemsPerPage) : 0);
const paginatedActuaciones = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return actuacionesList.value.slice(start, start + itemsPerPage);
});

const ultimaActuacion = computed(() => {
    if (!actuacionesList.value.length) return null;
    return [...actuacionesList.value].sort((a, b) => new Date(b.fecha_actuacion || b.created_at) - new Date(a.fecha_actuacion || a.created_at))[0];
});

const ultimaAuditoria = computed(() => auditoriaList.value?.[0] || null);

const nextPage = () => { if (currentPage.value < totalPages.value) currentPage.value++; };
const prevPage = () => { if (currentPage.value > 1) currentPage.value--; };

watch(() => actuacionesList.value.length, () => {
    if (totalPages.value && currentPage.value > totalPages.value) currentPage.value = totalPages.value;
    if (!totalPages.value) currentPage.value = 1;
});

const formatDateTime = (s) => {
    if (!s) return 'N/A';
    const date = new Date(String(s).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return 'N/A';
    return date.toLocaleString('es-CO', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const formatDate = (s) => {
    if (!s) return 'N/A';
    const date = new Date(String(s).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return 'N/A';
    return date.toLocaleDateString('es-CO', { year: 'numeric', month: 'short', day: 'numeric' });
};

const userInitial = (name) => (name || 'Sistema').trim().charAt(0).toUpperCase();

const summaryCards = computed(() => [
    {
        label: 'Actuaciones',
        value: actuacionesList.value.length,
        subtext: actuacionesList.value.length === 1 ? 'hito procesal' : 'hitos procesales',
        icon: ClipboardDocumentListIcon,
        iconClass: 'text-indigo-500',
    },
    {
        label: 'Último hito',
        value: ultimaActuacion.value ? formatDate(ultimaActuacion.value.fecha_actuacion) : 'Sin registros',
        subtext: ultimaActuacion.value ? `Por ${ultimaActuacion.value.user?.name || 'Sistema'}` : 'Bitácora pendiente',
        icon: CalendarDaysIcon,
        iconClass: 'text-amber-500',
    },
    {
        label: 'Auditoría',
        value: auditoriaList.value.length,
        subtext: ultimaAuditoria.value ? formatDateTime(ultimaAuditoria.value.created_at) : 'Sin historial técnico',
        icon: ShieldCheckIcon,
        iconClass: 'text-emerald-500',
    },
]);
</script>

<template>
    <div class="space-y-5 animate-in fade-in duration-500">
        <section class="grid grid-cols-1 gap-3 md:grid-cols-3">
            <div
                v-for="item in summaryCards"
                :key="item.label"
                class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <div class="flex items-start gap-3">
                    <div class="shrink-0 rounded-lg bg-gray-50 p-2 dark:bg-gray-900">
                        <component :is="item.icon" class="h-5 w-5" :class="item.iconClass" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">{{ item.label }}</p>
                        <p class="mt-1 break-words text-sm font-black leading-5 text-gray-900 dark:text-white">{{ item.value }}</p>
                        <p class="mt-0.5 break-words text-[10px] font-semibold text-gray-600 dark:text-gray-400">{{ item.subtext }}</p>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid grid-cols-1 gap-5 xl:grid-cols-12">
            <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 xl:col-span-7">
                <div class="flex flex-col gap-3 border-b border-gray-100 p-5 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <ClockIcon class="h-5 w-5 text-indigo-500" />
                        <div>
                            <h3 class="text-sm font-bold uppercase tracking-tight text-gray-900 dark:text-white">Bitácora procesal</h3>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-600 dark:text-gray-400">Actuaciones visibles del expediente</p>
                        </div>
                    </div>
                    <span class="w-fit rounded-full bg-gray-100 px-3 py-1 text-[10px] font-black uppercase tracking-widest text-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        {{ actuacionesList.length }} evento{{ actuacionesList.length !== 1 ? 's' : '' }}
                    </span>
                </div>

                <div class="p-5">
                    <div v-if="!actuacionesList.length" class="flex min-h-80 flex-col items-center justify-center rounded-lg border border-dashed border-gray-300 bg-white text-center dark:border-gray-700 dark:bg-gray-900/30">
                        <DocumentMagnifyingGlassIcon class="mb-3 h-12 w-12 text-gray-400 dark:text-gray-500" />
                        <p class="text-[11px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">Sin hitos registrados</p>
                    </div>

                    <ol v-else class="relative space-y-4 before:absolute before:bottom-0 before:left-4 before:top-0 before:w-px before:bg-gray-300 dark:before:bg-gray-700">
                        <li v-for="item in paginatedActuaciones" :key="item.id" class="relative grid grid-cols-[2rem_1fr] gap-3">
                            <div class="relative z-10 flex h-8 w-8 items-center justify-center rounded-lg border border-indigo-100 bg-indigo-50 text-indigo-600 dark:border-indigo-800 dark:bg-indigo-900/20 dark:text-indigo-300">
                                <BookmarkIcon class="h-4 w-4" />
                            </div>

                            <article class="min-w-0 rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900/40">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <p class="text-[10px] font-black uppercase tracking-widest text-indigo-600 dark:text-indigo-300">Actuación procesal</p>
                                        <p class="mt-1 text-[11px] font-bold text-gray-600 dark:text-gray-300">{{ formatDate(item.fecha_actuacion) }}</p>
                                    </div>
                                    <p class="text-[10px] font-semibold text-gray-600 dark:text-gray-400">Registro: {{ formatDateTime(item.created_at) }}</p>
                                </div>

                                <p class="mt-3 whitespace-pre-wrap break-words text-sm font-medium leading-6 text-gray-800 dark:text-gray-200">{{ item.nota }}</p>

                                <div class="mt-4 flex items-center gap-2 border-t border-gray-100 pt-3 dark:border-gray-700">
                                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-gray-200 bg-white text-[10px] font-black uppercase text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                        {{ userInitial(item.user?.name) }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[9px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">Responsable</p>
                                        <p class="truncate text-[11px] font-bold uppercase text-gray-700 dark:text-gray-300">{{ item.user?.name || 'Sistema' }}</p>
                                    </div>
                                </div>
                            </article>
                        </li>
                    </ol>
                </div>

                <div v-if="totalPages > 1" class="flex items-center justify-between border-t border-gray-200 bg-white px-5 py-3 dark:border-gray-700 dark:bg-gray-900/20">
                    <span class="text-[10px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">Página {{ currentPage }} de {{ totalPages }}</span>
                    <div class="flex gap-2">
                        <button @click="prevPage" :disabled="currentPage === 1" class="rounded-lg border border-gray-200 bg-white p-2 text-gray-500 disabled:opacity-30 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                            <ChevronLeftIcon class="h-4 w-4" />
                        </button>
                        <button @click="nextPage" :disabled="currentPage === totalPages" class="rounded-lg border border-gray-200 bg-white p-2 text-gray-500 disabled:opacity-30 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                            <ChevronRightIcon class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-lg border border-slate-800 bg-slate-950 shadow-sm xl:col-span-5">
                <div class="flex flex-col gap-3 border-b border-white/10 p-5 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <FingerPrintIcon class="h-5 w-5 text-emerald-400" />
                        <div>
                            <h3 class="text-sm font-bold uppercase tracking-tight text-white">Auditoría técnica</h3>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-300">Trazabilidad interna e integridad</p>
                        </div>
                    </div>
                    <span class="w-fit rounded-full border border-white/10 bg-white/10 px-3 py-1 text-[10px] font-black uppercase tracking-widest text-slate-200">
                        {{ auditoriaList.length }} registro{{ auditoriaList.length !== 1 ? 's' : '' }}
                    </span>
                </div>

                <div class="p-5">
                    <div v-if="!auditoriaList.length" class="flex min-h-80 flex-col items-center justify-center rounded-lg border border-dashed border-white/10 bg-white/5 text-center">
                        <ShieldCheckIcon class="mb-3 h-12 w-12 text-slate-500" />
                        <p class="text-[11px] font-black uppercase tracking-widest text-slate-300">Sin historial técnico</p>
                    </div>
                    <div v-else class="max-h-[620px] overflow-y-auto pr-2 custom-scrollbar-dark">
                        <HistorialAuditoria :eventos="auditoriaList" class="!text-[10px]" />
                    </div>

                    <div class="mt-5 rounded-lg border border-emerald-400/20 bg-emerald-400/10 p-3">
                        <div class="flex items-center gap-2">
                            <span class="relative flex h-2 w-2">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                            </span>
                            <span class="text-[9px] font-black uppercase tracking-widest text-emerald-200">Monitoreo técnico activo</span>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background-color: rgba(79, 70, 229, 0.28); border-radius: 10px; }
.custom-scrollbar-dark::-webkit-scrollbar { width: 4px; }
.custom-scrollbar-dark::-webkit-scrollbar-thumb { background-color: rgba(255, 255, 255, 0.2); border-radius: 10px; }
</style>
