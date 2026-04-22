<script setup>
import { 
    UsersIcon, 
    UserIcon, 
    PhoneIcon, 
    EnvelopeIcon,
    IdentificationIcon,
    ShieldCheckIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    proceso: { type: Object, required: true },
});
</script>

<template>
    <div class="space-y-10 animate-in fade-in duration-500">
        
        <!-- DEMANDANTES / ACCIONANTES -->
        <section>
            <div class="flex items-center gap-2 mb-6">
                <UsersIcon class="w-5 h-5 text-blue-500" />
                <h3 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-tight">Demandantes / Accionantes</h3>
            </div>

            <div v-if="!proceso.demandantes?.length" class="bg-gray-50 dark:bg-gray-900/30 rounded-xl border-2 border-dashed border-gray-200 dark:border-gray-700 py-10 text-center">
                <p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest">Sin demandantes registrados</p>
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="p in proceso.demandantes" :key="p.id" class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm hover:border-blue-200 transition-all">
                    <div class="flex items-start gap-3">
                        <div class="p-2 bg-blue-50 dark:bg-blue-900/30 rounded-lg text-blue-600">
                            <UserIcon class="w-5 h-5" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="text-xs font-bold text-gray-900 dark:text-white uppercase truncate" :title="p.nombre_completo">{{ p.nombre_completo }}</h4>
                            <p class="text-[10px] text-gray-500 font-mono mt-0.5">{{ p.tipo_documento || 'CC' }} {{ p.numero_documento || '—' }}</p>
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-t border-gray-50 dark:border-gray-700 flex flex-col gap-1.5">
                        <div v-if="p.celular_1" class="flex items-center gap-2 text-[10px] text-gray-600 dark:text-gray-400">
                            <PhoneIcon class="w-3 h-3" /> {{ p.celular_1 }}
                        </div>
                        <div v-if="p.correo_1" class="flex items-center gap-2 text-[10px] text-gray-600 dark:text-gray-400 truncate">
                            <EnvelopeIcon class="w-3 h-3" /> {{ p.correo_1 }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- DEMANDADOS / ACCIONADOS -->
        <section>
            <div class="flex items-center gap-2 mb-6">
                <UsersIcon class="w-5 h-5 text-rose-500" />
                <h3 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-tight">Demandados / Accionados</h3>
            </div>

            <div v-if="!proceso.demandados?.length" class="bg-gray-50 dark:bg-gray-900/30 rounded-xl border-2 border-dashed border-gray-200 dark:border-gray-700 py-10 text-center">
                <p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest">Sin demandados registrados</p>
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="p in proceso.demandados" :key="p.id" class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm hover:border-rose-200 transition-all relative overflow-hidden">
                    <div v-if="!p.numero_documento || p.numero_documento.startsWith('TEMP-')" class="absolute top-0 right-0 bg-amber-500 text-white text-[7px] font-black uppercase px-2 py-0.5 rounded-bl-lg">Pendiente ID</div>
                    
                    <div class="flex items-start gap-3">
                        <div class="p-2 bg-rose-50 dark:bg-rose-900/30 rounded-lg text-rose-600">
                            <UserIcon class="w-5 h-5" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="text-xs font-bold text-gray-900 dark:text-white uppercase truncate" :title="p.nombre_completo">{{ p.nombre_completo }}</h4>
                            <p class="text-[10px] text-gray-500 font-mono mt-0.5" v-if="p.numero_documento && !p.numero_documento.startsWith('TEMP-')">
                                {{ p.tipo_documento || 'CC' }} {{ p.numero_documento }}
                            </p>
                            <p class="text-[9px] text-amber-600 font-bold uppercase mt-0.5" v-else>Identificación Pendiente</p>
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-t border-gray-50 dark:border-gray-700 flex flex-col gap-1.5">
                        <div v-if="p.celular_1" class="flex items-center gap-2 text-[10px] text-gray-600 dark:text-gray-400">
                            <PhoneIcon class="w-3 h-3" /> {{ p.celular_1 }}
                        </div>
                        <div v-if="p.correo_1" class="flex items-center gap-2 text-[10px] text-gray-600 dark:text-gray-400 truncate">
                            <EnvelopeIcon class="w-3 h-3" /> {{ p.correo_1 }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- EQUIPO RESPONSABLE -->
        <section>
            <div class="flex items-center gap-2 mb-6">
                <ShieldCheckIcon class="w-5 h-5 text-indigo-500" />
                <h3 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-tight">Equipo Responsable</h3>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex items-center gap-4 p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                    <div class="w-10 h-10 bg-indigo-600 rounded-lg flex items-center justify-center text-white text-sm font-black shadow-md">
                        {{ (proceso.abogado?.name || 'S')[0] }}
                    </div>
                    <div>
                        <span class="text-[9px] font-bold text-indigo-500 uppercase tracking-widest block leading-none mb-1">Abogado Titular</span>
                        <h4 class="text-xs font-black text-gray-900 dark:text-white uppercase">{{ proceso.abogado?.name || 'Sin asignar' }}</h4>
                    </div>
                </div>

                <div class="flex items-center gap-4 p-4 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                    <div class="w-10 h-10 bg-amber-500 rounded-lg flex items-center justify-center text-white text-sm font-black shadow-md">
                        {{ (proceso.responsable_revision?.name || 'S')[0] }}
                    </div>
                    <div>
                        <span class="text-[9px] font-bold text-amber-600 uppercase tracking-widest block leading-none mb-1">Responsable Revisión</span>
                        <h4 class="text-xs font-black text-gray-900 dark:text-white uppercase">{{ proceso.responsable_revision?.name || 'Sin asignar' }}</h4>
                    </div>
                </div>
            </div>
        </section>

    </div>
</template>
