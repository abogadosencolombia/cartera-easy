<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import {
    ClockIcon,
    FunnelIcon,
    UserGroupIcon,
    BoltIcon,
    PauseCircleIcon,
    ArrowPathIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    sessions: Object,
    users: Array,
    filters: Object,
    summary: Object,
});

const filterForm = ref({
    periodo: props.filters?.periodo || 'hoy',
    user_id: props.filters?.user_id || '',
    fecha_desde: props.filters?.fecha_desde || '',
    fecha_hasta: props.filters?.fecha_hasta || '',
});

const applyFilters = () => {
    const query = {};
    Object.entries(filterForm.value).forEach(([key, value]) => {
        if (value !== '' && value !== null && value !== undefined) {
            query[key] = value;
        }
    });

    router.get(route('admin.jornadas.index'), query, {
        preserveState: true,
        replace: true,
    });
};

const clearFilters = () => {
    filterForm.value = {
        periodo: 'hoy',
        user_id: '',
        fecha_desde: '',
        fecha_hasta: '',
    };
    applyFilters();
};

const formatDateTime = (dateString) => {
    if (!dateString) return '-';

    return new Intl.DateTimeFormat('es-CO', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
    }).format(new Date(String(dateString).replace(' ', 'T')));
};

const statusClasses = (status) => {
    return status === 'activa'
        ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-300'
        : 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300';
};

const reasonLabel = (reason) => {
    const labels = {
        manual: 'Cierre manual',
        inactivity: 'Cierre por inactividad',
        turno_finalizado: 'Turno finalizado',
        nueva_sesion: 'Reemplazada por nueva sesión',
        sesion_obsoleta: 'Cierre por sesión obsoleta',
    };

    return labels[reason] || 'Sin cierre';
};

const statCards = computed(() => [
    {
        label: 'Turnos',
        value: props.summary?.sessions_count ?? 0,
        subtext: `${props.summary?.active_sessions_count ?? 0} activos`,
        icon: ClockIcon,
        class: 'text-indigo-600 bg-indigo-50 dark:bg-indigo-950/40 dark:text-indigo-300',
    },
    {
        label: 'Usuarios',
        value: props.summary?.users_count ?? 0,
        subtext: 'Con actividad en el rango',
        icon: UserGroupIcon,
        class: 'text-sky-600 bg-sky-50 dark:bg-sky-950/40 dark:text-sky-300',
    },
    {
        label: 'Uso activo',
        value: props.summary?.active_human ?? '0s',
        subtext: `${props.summary?.total_human ?? '0s'} total`,
        icon: BoltIcon,
        class: 'text-emerald-600 bg-emerald-50 dark:bg-emerald-950/40 dark:text-emerald-300',
    },
    {
        label: 'Inactividad',
        value: props.summary?.idle_human ?? '0s',
        subtext: `${props.summary?.idle_percentage ?? 0}% del tiempo`,
        icon: PauseCircleIcon,
        class: 'text-amber-600 bg-amber-50 dark:bg-amber-950/40 dark:text-amber-300',
    },
]);
</script>

<template>
    <Head title="Jornadas de Usuario" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <ClockIcon class="h-6 w-6 text-indigo-600 dark:text-indigo-400" />
                <h2 class="text-xl font-semibold leading-tight text-blue-500 dark:text-gray-200">
                    Jornadas de Usuario
                </h2>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <article
                        v-for="card in statCards"
                        :key="card.label"
                        class="rounded-lg border border-gray-100 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div class="flex items-start gap-3">
                            <div class="rounded-lg p-2" :class="card.class">
                                <component :is="card.icon" class="h-5 w-5" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">{{ card.label }}</p>
                                <p class="mt-1 break-words text-xl font-black text-gray-950 dark:text-white">{{ card.value }}</p>
                                <p class="mt-0.5 text-xs font-semibold text-gray-500 dark:text-gray-400">{{ card.subtext }}</p>
                            </div>
                        </div>
                    </article>
                </section>

                <section class="rounded-lg border border-gray-100 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="mb-5 flex items-center gap-2 border-b border-gray-100 pb-4 dark:border-gray-700">
                        <FunnelIcon class="h-5 w-5 text-gray-400" />
                        <h3 class="text-sm font-black uppercase tracking-widest text-gray-700 dark:text-gray-200">Filtros</h3>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
                        <div class="space-y-1.5">
                            <InputLabel for="periodo" value="Periodo" />
                            <select
                                id="periodo"
                                v-model="filterForm.periodo"
                                class="w-full rounded-md border-gray-300 bg-white text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
                            >
                                <option value="hoy">Hoy</option>
                                <option value="semana">Esta semana</option>
                                <option value="mes">Este mes</option>
                                <option value="personalizado">Personalizado</option>
                            </select>
                        </div>

                        <div class="space-y-1.5">
                            <InputLabel for="usuario" value="Usuario" />
                            <select
                                id="usuario"
                                v-model="filterForm.user_id"
                                class="w-full rounded-md border-gray-300 bg-white text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
                            >
                                <option value="">Todos</option>
                                <option v-for="user in users" :key="user.id" :value="user.id">
                                    {{ user.name }} - {{ user.tipo_usuario }}
                                </option>
                            </select>
                        </div>

                        <div class="space-y-1.5">
                            <InputLabel for="fecha_desde" value="Desde" />
                            <input
                                id="fecha_desde"
                                v-model="filterForm.fecha_desde"
                                type="date"
                                :disabled="filterForm.periodo !== 'personalizado'"
                                class="w-full rounded-md border-gray-300 bg-white text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-400 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:disabled:bg-gray-800"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <InputLabel for="fecha_hasta" value="Hasta" />
                            <input
                                id="fecha_hasta"
                                v-model="filterForm.fecha_hasta"
                                type="date"
                                :disabled="filterForm.periodo !== 'personalizado'"
                                class="w-full rounded-md border-gray-300 bg-white text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-400 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:disabled:bg-gray-800"
                            />
                        </div>

                        <div class="flex items-end gap-2">
                            <PrimaryButton type="button" class="justify-center" @click="applyFilters">
                                Aplicar
                            </PrimaryButton>
                            <SecondaryButton type="button" class="justify-center" @click="clearFilters">
                                Limpiar
                            </SecondaryButton>
                        </div>
                    </div>
                </section>

                <section class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-700">
                        <div>
                            <h3 class="text-sm font-black uppercase tracking-widest text-gray-800 dark:text-gray-100">Detalle de turnos</h3>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Inicio, finalización, uso activo e inactividad por usuario.</p>
                        </div>
                        <ArrowPathIcon class="h-5 w-5 text-gray-300" />
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900/60">
                                <tr>
                                    <th class="px-4 py-3 text-left text-[10px] font-black uppercase tracking-widest text-gray-500">Usuario</th>
                                    <th class="px-4 py-3 text-left text-[10px] font-black uppercase tracking-widest text-gray-500">Ingreso</th>
                                    <th class="px-4 py-3 text-left text-[10px] font-black uppercase tracking-widest text-gray-500">Salida</th>
                                    <th class="px-4 py-3 text-left text-[10px] font-black uppercase tracking-widest text-gray-500">Total</th>
                                    <th class="px-4 py-3 text-left text-[10px] font-black uppercase tracking-widest text-gray-500">Activo</th>
                                    <th class="px-4 py-3 text-left text-[10px] font-black uppercase tracking-widest text-gray-500">Inactivo</th>
                                    <th class="px-4 py-3 text-left text-[10px] font-black uppercase tracking-widest text-gray-500">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr v-for="session in sessions.data" :key="session.id" class="hover:bg-gray-50 dark:hover:bg-gray-900/40">
                                    <td class="px-4 py-3">
                                        <p class="text-sm font-bold text-gray-900 dark:text-white">{{ session.user?.name || 'Usuario eliminado' }}</p>
                                        <p class="text-xs text-gray-500">{{ session.user?.email || '-' }}</p>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ formatDateTime(session.started_at) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ formatDateTime(session.ended_at) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm font-black text-gray-950 dark:text-white">{{ session.total_human }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm font-bold text-emerald-700 dark:text-emerald-300">{{ session.active_human }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm font-bold text-amber-700 dark:text-amber-300">{{ session.idle_human }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full border px-2 py-1 text-[10px] font-black uppercase tracking-widest" :class="statusClasses(session.status)">
                                            {{ session.status }}
                                        </span>
                                        <p class="mt-1 text-[11px] text-gray-500">{{ reasonLabel(session.logout_reason) }}</p>
                                    </td>
                                </tr>
                                <tr v-if="!sessions.data.length">
                                    <td colspan="7" class="px-4 py-10 text-center text-sm font-semibold text-gray-500">
                                        No hay jornadas registradas para los filtros seleccionados.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="sessions.links?.length" class="border-t border-gray-100 px-4 py-3 dark:border-gray-700">
                        <Pagination :links="sessions.links" />
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
