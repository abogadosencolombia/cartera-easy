<script setup>
import {
    UsersIcon,
    UserIcon,
    PhoneIcon,
    EnvelopeIcon,
    IdentificationIcon,
    ShieldCheckIcon,
    ExclamationTriangleIcon,
    ClipboardDocumentListIcon,
    UserGroupIcon,
} from '@heroicons/vue/24/outline';
import { computed } from 'vue';
import PersonaCompletenessIndicator from '@/Components/PersonaCompletenessIndicator.vue';
import { hasIncompletePersonaInfo } from '@/Utils/personaCompleteness';

const props = defineProps({
    proceso: { type: Object, required: true },
});

const demandantes = computed(() => props.proceso.demandantes || []);
const demandados = computed(() => props.proceso.demandados || []);
const pendingCompletenessCount = computed(() => demandados.value.filter(hasIncompletePersonaInfo).length + demandantes.value.filter(hasIncompletePersonaInfo).length);

function hasPendingIdentification(persona) {
    return !persona?.numero_documento || String(persona.numero_documento).startsWith('TEMP-');
}

const documentLabel = (persona) => {
    if (hasPendingIdentification(persona)) return 'Identificación pendiente';
    return `${persona.tipo_documento || 'CC'} ${persona.numero_documento}`;
};

const primaryPhone = (persona) => persona.celular_1 || persona.celular_2 || persona.telefono || null;
const primaryEmail = (persona) => persona.correo_1 || persona.correo_2 || persona.email || null;
const initial = (name) => (name || 'S').trim().charAt(0).toUpperCase();

const summaryCards = computed(() => [
    {
        label: 'Demandantes',
        value: demandantes.value.length,
        subtext: demandantes.value.length === 1 ? 'parte activa' : 'partes activas',
        icon: UsersIcon,
        iconClass: 'text-blue-500',
    },
    {
        label: 'Demandados',
        value: demandados.value.length,
        subtext: demandados.value.length === 1 ? 'parte pasiva' : 'partes pasivas',
        icon: UserGroupIcon,
        iconClass: 'text-rose-500',
    },
    {
        label: 'Datos pendientes',
        value: pendingCompletenessCount.value,
        subtext: pendingCompletenessCount.value === 1 ? 'persona por completar' : 'personas por completar',
        icon: ExclamationTriangleIcon,
        iconClass: pendingCompletenessCount.value ? 'text-amber-500' : 'text-emerald-500',
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
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">{{ item.label }}</p>
                        <p class="mt-1 break-words text-sm font-black leading-5 text-gray-900 dark:text-white">{{ item.value }}</p>
                        <p class="mt-0.5 break-words text-[10px] font-semibold text-gray-600 dark:text-gray-400">{{ item.subtext }}</p>
                    </div>
                </div>
            </div>
        </section>

        <div v-if="pendingCompletenessCount" class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/40 dark:bg-amber-900/10">
            <div class="flex items-start gap-3">
                <ExclamationTriangleIcon class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-300" />
                <div>
                    <p class="text-xs font-black uppercase tracking-widest text-amber-800 dark:text-amber-200">Datos de partes por completar</p>
                    <p class="mt-1 text-xs font-semibold text-amber-700 dark:text-amber-300">Hay {{ pendingCompletenessCount }} persona{{ pendingCompletenessCount !== 1 ? 's' : '' }} con datos básicos pendientes.</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-5 xl:grid-cols-2">
            <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex flex-col gap-3 border-b border-gray-100 p-5 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <UsersIcon class="h-5 w-5 text-blue-500" />
                        <div>
                            <h3 class="text-sm font-bold uppercase tracking-tight text-gray-900 dark:text-white">Demandantes / Accionantes</h3>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-600 dark:text-gray-400">Parte activa del proceso</p>
                        </div>
                    </div>
                    <span class="w-fit rounded-full bg-blue-50 px-3 py-1 text-[10px] font-black uppercase tracking-widest text-blue-700 dark:bg-blue-900/20 dark:text-blue-300">
                        {{ demandantes.length }} registro{{ demandantes.length !== 1 ? 's' : '' }}
                    </span>
                </div>

                <div class="p-5">
                    <div v-if="!demandantes.length" class="flex min-h-56 flex-col items-center justify-center rounded-lg border border-dashed border-gray-300 bg-white text-center dark:border-gray-700 dark:bg-gray-900/30">
                        <UsersIcon class="mb-3 h-10 w-10 text-gray-400 dark:text-gray-500" />
                        <p class="text-[11px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">Sin demandantes registrados</p>
                    </div>

                    <div v-else class="space-y-3">
                        <article v-for="p in demandantes" :key="p.id" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900/30">
                            <div class="flex items-start gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-sm font-black uppercase text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                                    {{ initial(p.nombre_completo) }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="min-w-0">
                                            <h4 class="break-words text-sm font-black uppercase leading-5 text-gray-900 dark:text-white" :title="p.nombre_completo">{{ p.nombre_completo || 'Sin nombre' }}</h4>
                                            <p class="mt-1 inline-flex items-center gap-1.5 rounded bg-gray-100 px-2 py-1 text-[10px] font-bold text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                                <IdentificationIcon class="h-3.5 w-3.5" />
                                                {{ documentLabel(p) }}
                                            </p>
                                        </div>
                                        <PersonaCompletenessIndicator :persona="p" />
                                    </div>

                                    <div class="mt-4 grid grid-cols-1 gap-2 border-t border-gray-100 pt-3 dark:border-gray-700 sm:grid-cols-2">
                                        <div class="flex items-center gap-2 text-xs font-semibold text-gray-700 dark:text-gray-300">
                                            <PhoneIcon class="h-4 w-4 shrink-0 text-gray-400" />
                                            <span class="truncate">{{ primaryPhone(p) || 'Sin teléfono' }}</span>
                                        </div>
                                        <div class="flex items-center gap-2 text-xs font-semibold text-gray-700 dark:text-gray-300">
                                            <EnvelopeIcon class="h-4 w-4 shrink-0 text-gray-400" />
                                            <span class="truncate">{{ primaryEmail(p) || 'Sin correo' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex flex-col gap-3 border-b border-gray-100 p-5 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3">
                        <UserGroupIcon class="h-5 w-5 text-rose-500" />
                        <div>
                            <h3 class="text-sm font-bold uppercase tracking-tight text-gray-900 dark:text-white">Demandados / Accionados</h3>
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-600 dark:text-gray-400">Parte pasiva del proceso</p>
                        </div>
                    </div>
                    <span class="w-fit rounded-full bg-rose-50 px-3 py-1 text-[10px] font-black uppercase tracking-widest text-rose-700 dark:bg-rose-900/20 dark:text-rose-300">
                        {{ demandados.length }} registro{{ demandados.length !== 1 ? 's' : '' }}
                    </span>
                </div>

                <div class="p-5">
                    <div v-if="!demandados.length" class="flex min-h-56 flex-col items-center justify-center rounded-lg border border-dashed border-gray-300 bg-white text-center dark:border-gray-700 dark:bg-gray-900/30">
                        <UserGroupIcon class="mb-3 h-10 w-10 text-gray-400 dark:text-gray-500" />
                        <p class="text-[11px] font-black uppercase tracking-widest text-gray-600 dark:text-gray-400">Sin demandados registrados</p>
                    </div>

                    <div v-else class="space-y-3">
                        <article v-for="p in demandados" :key="p.id" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900/30">
                            <div class="flex items-start gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-sm font-black uppercase text-rose-700 dark:bg-rose-900/30 dark:text-rose-300">
                                    {{ initial(p.nombre_completo) }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                        <div class="min-w-0">
                                            <h4 class="break-words text-sm font-black uppercase leading-5 text-gray-900 dark:text-white" :title="p.nombre_completo">{{ p.nombre_completo || 'Sin nombre' }}</h4>
                                            <p class="mt-1 inline-flex items-center gap-1.5 rounded bg-gray-100 px-2 py-1 text-[10px] font-bold text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                                <IdentificationIcon class="h-3.5 w-3.5" />
                                                {{ documentLabel(p) }}
                                            </p>
                                        </div>
                                        <PersonaCompletenessIndicator :persona="p" />
                                    </div>

                                    <div class="mt-4 grid grid-cols-1 gap-2 border-t border-gray-100 pt-3 dark:border-gray-700 sm:grid-cols-2">
                                        <div class="flex items-center gap-2 text-xs font-semibold text-gray-700 dark:text-gray-300">
                                            <PhoneIcon class="h-4 w-4 shrink-0 text-gray-400" />
                                            <span class="truncate">{{ primaryPhone(p) || 'Sin teléfono' }}</span>
                                        </div>
                                        <div class="flex items-center gap-2 text-xs font-semibold text-gray-700 dark:text-gray-300">
                                            <EnvelopeIcon class="h-4 w-4 shrink-0 text-gray-400" />
                                            <span class="truncate">{{ primaryEmail(p) || 'Sin correo' }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
            </section>
        </div>

        <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="flex flex-col gap-3 border-b border-gray-100 p-5 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <ShieldCheckIcon class="h-5 w-5 text-indigo-500" />
                    <div>
                        <h3 class="text-sm font-bold uppercase tracking-tight text-gray-900 dark:text-white">Equipo responsable</h3>
                        <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-600 dark:text-gray-400">Abogado titular y responsable de revisión</p>
                    </div>
                </div>
                <ClipboardDocumentListIcon class="h-5 w-5 text-gray-400" />
            </div>

            <div class="grid grid-cols-1 gap-4 p-5 md:grid-cols-2">
                <div class="rounded-lg border border-indigo-100 bg-indigo-50/50 p-4 dark:border-indigo-900/40 dark:bg-indigo-900/10">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-indigo-600 text-sm font-black uppercase text-white">
                            {{ initial(proceso.abogado?.name) }}
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] font-black uppercase tracking-widest text-indigo-700 dark:text-indigo-300">Abogado titular</p>
                            <p class="mt-1 break-words text-sm font-black uppercase text-gray-900 dark:text-white">{{ proceso.abogado?.name || 'Sin asignar' }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-amber-100 bg-amber-50/60 p-4 dark:border-amber-900/40 dark:bg-amber-900/10">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-amber-500 text-sm font-black uppercase text-white">
                            {{ initial(proceso.responsable_revision?.name) }}
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] font-black uppercase tracking-widest text-amber-700 dark:text-amber-300">Responsable revisión</p>
                            <p class="mt-1 break-words text-sm font-black uppercase text-gray-900 dark:text-white">{{ proceso.responsable_revision?.name || 'Sin asignar' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
