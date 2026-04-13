<script setup>
import { computed } from 'vue';
import { 
    CheckCircleIcon, 
    InformationCircleIcon, 
    ClipboardDocumentCheckIcon,
    ArrowRightCircleIcon,
    CheckIcon
} from '@heroicons/vue/24/outline';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    etapa: { type: String, required: true },
    tipoProceso: { type: String, default: 'General' },
    checklistCompletados: { type: Array, default: () => [] },
    modelId: { type: Number, required: true },
    modelType: { type: String, required: true }, // 'caso' o 'proceso'
});

// Configuración de Guías por Etapa
const guias = {
    'DEMANDA PRESENTADA': [
        'Verificar radicado de 23 dígitos en el sistema.',
        'Adjuntar PDF del acta de radicación o pantallazo de envío.',
        'Revisar si el juzgado solicitó subsanación (término de 5 días).',
        'Cargar el archivo del proceso a la carpeta de Drive vinculada.'
    ],
    'MANDAMIENTO DE PAGO': [
        'Descargar el auto de mandamiento de pago del expediente digital.',
        'Notificar al deudor principal mediante correo certificado o personal.',
        'Preparar oficios de medidas cautelares (si aplica).',
        'Registrar la fecha de notificación en las actuaciones.'
    ],
    'AUDIENCIA INICIAL (ART. 372 CGP)': [
        'Revisar fijación de fecha y hora en estados electrónicos.',
        'Coordinar con el abogado asignado la preparación de pruebas.',
        'Verificar que el cliente (Cooperativa) tenga el apoderado vigente.',
        'Revisar conexión a Microsoft Teams / Enlace de la audiencia.'
    ],
    'TERMINADO POR PAGO TOTAL': [
        'Verificar el ingreso del dinero en la cuenta de la Cooperativa.',
        'Solicitar el levantamiento de medidas cautelares al juzgado.',
        'Entregar los títulos valores (Pagarés) al cliente.',
        'Cerrar definitivamente el expediente en este software.'
    ],
    'SENTENCIA': [
        'Leer detalladamente el fallo y verificar condena en costas.',
        'Notificar la sentencia a todas las partes involucradas.',
        'Solicitar la liquidación del crédito actualizada.',
        'Verificar si hay lugar a recurso de apelación.'
    ]
};

const checklist = computed(() => {
    const etapaNormalizada = props.etapa?.toUpperCase() || '';
    const key = Object.keys(guias).find(k => etapaNormalizada.includes(k));
    return guias[key] || [
        'Revisar el último estado del proceso en la página de la Rama Judicial.',
        'Asegurarse de tener todos los documentos de soporte cargados.',
        'Planificar la siguiente actuación según el código de procedimiento.',
        'Mantener informadas a las partes interesadas sobre el avance.'
    ];
});

const isCompleted = (index) => {
    return props.checklistCompletados.includes(index);
};

const toggleItem = (index) => {
    let nuevosCompletados = [...props.checklistCompletados];
    if (nuevosCompletados.includes(index)) {
        nuevosCompletados = nuevosCompletados.filter(i => i !== index);
    } else {
        nuevosCompletados.push(index);
    }

    const routeName = props.modelType === 'caso' ? 'casos.checklist.update' : 'procesos.checklist.update';
    
    router.patch(route(routeName, props.modelId), {
        checklist: nuevosCompletados
    }, {
        preserveScroll: true,
        preserveState: true
    });
};

const progreso = computed(() => {
    if (checklist.value.length === 0) return 0;
    const completadosEnEstaEtapa = props.checklistCompletados.filter(i => i < checklist.value.length);
    return Math.round((completadosEnEstaEtapa.length / checklist.value.length) * 100);
});
</script>

<template>
    <div class="bg-indigo-50/50 dark:bg-indigo-900/10 rounded-3xl border border-indigo-100 dark:border-indigo-900/30 overflow-hidden shadow-sm">
        <div class="p-6 border-b border-indigo-100 dark:border-indigo-900/30 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-gray-800">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-indigo-600 rounded-xl shadow-lg shadow-indigo-200 dark:shadow-none">
                    <ClipboardDocumentCheckIcon class="w-5 h-5 text-white" />
                </div>
                <div>
                    <h3 class="text-sm font-black text-gray-900 dark:text-white uppercase tracking-wider">Protocolo: {{ etapa || 'Trámite General' }}</h3>
                    <div class="flex items-center gap-2 mt-0.5">
                        <div class="w-24 h-1.5 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                            <div class="h-full bg-indigo-500 transition-all duration-500" :style="{ width: progreso + '%' }"></div>
                        </div>
                        <span class="text-[9px] text-gray-500 font-black uppercase">{{ progreso }}% Completado</span>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-1 text-[10px] font-black text-indigo-600 uppercase bg-indigo-50 dark:bg-indigo-900/30 px-3 py-1 rounded-full w-fit">
                <InformationCircleIcon class="w-3 h-3" /> Confirmar Revisión
            </div>
        </div>

        <div class="p-6 space-y-3">
            <div 
                v-for="(item, idx) in checklist" 
                :key="idx" 
                @click="toggleItem(idx)"
                class="flex items-start gap-4 p-4 rounded-2xl border transition-all cursor-pointer group"
                :class="[
                    isCompleted(idx) 
                    ? 'bg-emerald-50/50 border-emerald-100 dark:bg-emerald-900/20 dark:border-emerald-800/50' 
                    : 'bg-white border-indigo-50 dark:bg-gray-800 dark:border-gray-700 hover:border-indigo-300'
                ]"
            >
                <div class="mt-0.5 flex-shrink-0">
                    <div 
                        class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition-colors"
                        :class="[
                            isCompleted(idx)
                            ? 'bg-emerald-500 border-emerald-500 text-white'
                            : 'border-gray-300 group-hover:border-indigo-500'
                        ]"
                    >
                        <CheckIcon v-if="isCompleted(idx)" class="w-3 h-3 stroke-[4]" />
                    </div>
                </div>
                <p 
                    class="text-sm font-medium leading-relaxed transition-colors"
                    :class="isCompleted(idx) ? 'text-emerald-800 dark:text-emerald-300 line-through opacity-70' : 'text-gray-700 dark:text-gray-300'"
                >
                    {{ item }}
                </p>
            </div>
        </div>

        <div class="px-6 py-4 bg-indigo-100/30 dark:bg-indigo-900/20 border-t border-indigo-100 dark:border-indigo-900/30">
            <p class="text-[9px] text-indigo-700 dark:text-indigo-400 font-bold uppercase tracking-tighter italic">
                Haga clic en cada item para confirmar que ya lo revisó. El sistema guardará su progreso automáticamente.
            </p>
        </div>
    </div>
</template>
