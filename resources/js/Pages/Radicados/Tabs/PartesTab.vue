<script setup>
import { 
    UsersIcon, 
    UserIcon, 
    BriefcaseIcon,
    ScaleIcon,
    ShieldCheckIcon,
    PhoneIcon,
    EnvelopeIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    proceso: { type: Object, required: true },
});

const formatNames = (personas) => {
    if (!personas || personas.length === 0) return 'Sin asignar';
    return personas.map(p => p.nombre_completo).join(', ');
};
</script>

<template>
    <div class="space-y-12 animate-in fade-in duration-500">
        
        <!-- SECCIÓN: DEMANDANTES / ACCIONANTES -->
        <section>
            <div class="flex items-center gap-3 mb-6">
                <div class="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                    <UsersIcon class="w-6 h-6 text-blue-600" />
                </div>
                <div>
                    <h3 class="text-xl font-black text-gray-900 dark:text-white uppercase tracking-wider">Demandantes / Accionantes</h3>
                    <p class="text-xs text-gray-500 font-medium">Parte activa que promueve la acción judicial.</p>
                </div>
            </div>

            <div v-if="!proceso.demandantes?.length" class="bg-gray-50 dark:bg-gray-900/30 rounded-3xl border-2 border-dashed border-gray-200 dark:border-gray-700 py-12 text-center">
                <UserIcon class="w-12 h-12 text-gray-300 mx-auto mb-3" />
                <p class="text-sm font-bold text-gray-400">No hay demandantes registrados.</p>
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="p in proceso.demandantes" :key="p.id" class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-sm transition-all hover:border-blue-200">
                    <div class="flex items-start gap-4">
                        <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-2xl">
                            <UserIcon class="w-6 h-6 text-blue-600" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <span class="text-[9px] font-black text-blue-600 uppercase tracking-widest">Persona Natural/Jurídica</span>
                            <h4 class="text-sm font-black text-gray-900 dark:text-white truncate" :title="p.nombre_completo">{{ p.nombre_completo }}</h4>
                            <p class="text-[10px] font-mono text-gray-500 mt-1 uppercase">{{ p.tipo_documento || 'CC' }}: {{ p.numero_documento || 'No registra' }}</p>
                        </div>
                    </div>
                    <div class="mt-6 pt-4 border-t border-gray-50 dark:border-gray-700 flex flex-col gap-2">
                        <span v-if="p.celular_1" class="text-[11px] text-gray-600 flex items-center gap-2"><PhoneIcon class="w-3.5 h-3.5" /> {{ p.celular_1 }}</span>
                        <span v-if="p.correo_1" class="text-[11px] text-gray-600 flex items-center gap-2 truncate"><EnvelopeIcon class="w-3.5 h-3.5" /> {{ p.correo_1 }}</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- SECCIÓN: DEMANDADOS / ACCIONADOS -->
        <section>
            <div class="flex items-center gap-3 mb-6">
                <div class="p-2 bg-red-100 dark:bg-red-900/30 rounded-lg">
                    <UsersIcon class="w-6 h-6 text-red-600" />
                </div>
                <div>
                    <h3 class="text-xl font-black text-gray-900 dark:text-white uppercase tracking-wider">Demandados / Accionados</h3>
                    <p class="text-xs text-gray-500 font-medium">Contra quienes se dirige la pretensión del proceso.</p>
                </div>
            </div>

            <div v-if="!proceso.demandados?.length" class="bg-gray-50 dark:bg-gray-900/30 rounded-3xl border-2 border-dashed border-gray-200 dark:border-gray-700 py-12 text-center">
                <UserIcon class="w-12 h-12 text-gray-300 mx-auto mb-3" />
                <p class="text-sm font-bold text-gray-400">No hay demandados registrados.</p>
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="p in proceso.demandados" :key="p.id" class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-sm transition-all hover:border-red-200 relative overflow-hidden">
                    <div v-if="!p.numero_documento || p.numero_documento.startsWith('TEMP-')" class="absolute top-0 right-0 px-3 py-1 bg-amber-500 text-white text-[8px] font-black uppercase tracking-widest rounded-bl-xl">Por Identificar</div>
                    
                    <div class="flex items-start gap-4">
                        <div class="p-3 bg-red-50 dark:bg-red-900/20 rounded-2xl">
                            <UserIcon class="w-6 h-6 text-red-600" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <span class="text-[9px] font-black text-red-600 uppercase tracking-widest">Parte Demandada</span>
                            <h4 class="text-sm font-black text-gray-900 dark:text-white truncate" :title="p.nombre_completo">{{ p.nombre_completo }}</h4>
                            <p class="text-[10px] font-mono text-gray-500 mt-1 uppercase" v-if="p.numero_documento && !p.numero_documento.startsWith('TEMP-')">
                                {{ p.tipo_documento || 'CC' }}: {{ p.numero_documento }}
                            </p>
                            <p class="text-[10px] text-amber-600 font-black uppercase mt-1" v-else>Identificación Pendiente</p>
                        </div>
                    </div>
                    <div class="mt-6 pt-4 border-t border-gray-50 dark:border-gray-700 flex flex-col gap-2">
                        <span v-if="p.celular_1" class="text-[11px] text-gray-600 flex items-center gap-2"><PhoneIcon class="w-3.5 h-3.5" /> {{ p.celular_1 }}</span>
                        <span v-if="p.correo_1" class="text-[11px] text-gray-600 flex items-center gap-2 truncate"><EnvelopeIcon class="w-3.5 h-3.5" /> {{ p.correo_1 }}</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- SECCIÓN: RESPONSABLES INTERNOS -->
        <section>
            <div class="flex items-center gap-3 mb-6">
                <div class="p-2 bg-indigo-100 dark:bg-indigo-900/30 rounded-lg">
                    <ShieldCheckIcon class="w-6 h-6 text-indigo-600" />
                </div>
                <div>
                    <h3 class="text-xl font-black text-gray-900 dark:text-white uppercase tracking-wider">Equipo de Trabajo</h3>
                    <p class="text-xs text-gray-500 font-medium">Abogados y supervisores responsables del seguimiento.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Abogado Principal -->
                <div class="flex items-center gap-5 p-6 bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-sm transition-all hover:shadow-md">
                    <div class="w-16 h-16 bg-indigo-600 rounded-2xl flex items-center justify-center text-white text-2xl font-black shadow-lg shadow-indigo-100 dark:shadow-none">
                        {{ (proceso.abogado?.name || 'S')[0] }}
                    </div>
                    <div>
                        <span class="text-[10px] font-black text-indigo-600 uppercase tracking-widest block mb-1">Abogado Titular</span>
                        <h4 class="text-lg font-black text-gray-900 dark:text-white leading-tight">{{ proceso.abogado?.name || 'Sin asignar' }}</h4>
                        <p class="text-xs text-gray-500 mt-1">{{ proceso.abogado?.email || '—' }}</p>
                    </div>
                </div>

                <!-- Revisor -->
                <div class="flex items-center gap-5 p-6 bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-sm transition-all hover:shadow-md">
                    <div class="w-16 h-16 bg-amber-500 rounded-2xl flex items-center justify-center text-white text-2xl font-black shadow-lg shadow-amber-100 dark:shadow-none">
                        {{ (proceso.responsable_revision?.name || 'S')[0] }}
                    </div>
                    <div>
                        <span class="text-[10px] font-black text-amber-600 uppercase tracking-widest block mb-1">Responsable de Revisión</span>
                        <h4 class="text-lg font-black text-gray-900 dark:text-white leading-tight">{{ proceso.responsable_revision?.name || 'Sin asignar' }}</h4>
                        <p class="text-xs text-gray-500 mt-1">{{ proceso.responsable_revision?.email || '—' }}</p>
                    </div>
                </div>
            </div>
        </section>

    </div>
</template>
