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
    entity: { type: Object, default: () => ({}) } // Datos adicionales para auto-check
});

// Configuración de Guías por Etapa
const guias = {
    'DEMANDA PRESENTADA': [
        { label: 'Verificar radicado de 23 dígitos en el sistema.', auto: (e) => e.radicado?.replace(/[^0-9]/g, '').length === 23 },
        { label: 'Adjuntar PDF del acta de radicación o pantallazo de envío.', auto: (e) => e.documentos?.some(d => d.tipo_documento?.toLowerCase().includes('demanda') || d.file_name?.toLowerCase().includes('radic')) },
        { label: 'Revisar si el juzgado solicitó subsanación (término de 5 días).', auto: null },
        { label: 'Cargar el archivo del proceso a la carpeta de Drive vinculada.', auto: (e) => !!(e.link_drive || e.ubicacion_drive) }
    ],
    'MANDAMIENTO DE PAGO': [
        { label: 'Descargar el auto de mandamiento de pago del expediente digital.', auto: (e) => e.documentos?.some(d => d.tipo_documento?.toLowerCase().includes('auto')) },
        { label: 'Notificar al deudor principal mediante correo certificado o personal.', auto: (e) => e.notificaciones?.some(n => n.atendida_en) },
        { label: 'Preparar oficios de medidas cautelares (si aplica).', auto: null },
        { label: 'Registrar la fecha de notificación en las actuaciones.', auto: (e) => e.actuaciones?.some(a => a.nota?.toLowerCase().includes('notific')) }
    ],
    'AUDIENCIA INICIAL (ART. 372 CGP)': [
        { label: 'Revisar fijación de fecha y hora en estados electrónicos.', auto: null },
        { label: 'Coordinar con el abogado asignado la preparación de pruebas.', auto: null },
        { label: 'Verificar que el cliente (Cooperativa) tenga el apoderado vigente.', auto: (e) => !!e.cooperativa_id },
        { label: 'Revisar conexión a Microsoft Teams / Enlace de la audiencia.', auto: null }
    ],
    'TERMINADO POR PAGO TOTAL': [
        { label: 'Verificar el ingreso del dinero en la cuenta de la Cooperativa.', auto: (e) => e.pagos?.length > 0 },
        { label: 'Solicitar el levantamiento de medidas cautelares al juzgado.', auto: null },
        { label: 'Entregar los títulos valores (Pagarés) al cliente.', auto: null },
        { label: 'Cerrar definitivamente el expediente en este software.', auto: (e) => e.estado === 'CERRADO' || !!e.nota_cierre }
    ],
    'SENTENCIA': [
        { label: 'Leer detalladamente el fallo y verificar condena en costas.', auto: (e) => e.documentos?.some(d => d.tipo_documento?.toLowerCase().includes('sentencia')) },
        { label: 'Notificar la sentencia a todas las partes involucradas.', auto: null },
        { label: 'Solicitar la liquidación del crédito actualizada.', auto: null },
        { label: 'Verificar si hay lugar a recurso de apelación.', auto: null }
    ]
};

const checklist = computed(() => {
    const etapaNormalizada = props.etapa?.toUpperCase() || '';
    const key = Object.keys(guias).find(k => etapaNormalizada.includes(k));
    const items = guias[key] || [
        { label: 'Revisar el último estado del proceso en la página de la Rama Judicial.', auto: (e) => !!e.link_expediente },
        { label: 'Asegurarse de tener todos los documentos de soporte cargados.', auto: (e) => e.documentos?.length >= 3 },
        { label: 'Planificar la siguiente actuación según el código de procedimiento.', auto: null },
        { label: 'Mantener informadas a las partes interesadas sobre el avance.', auto: null }
    ];
    return items;
});

const isCompleted = (index) => {
    const item = checklist.value[index];
    // Si tiene lógica automática y se cumple, está completado
    if (item.auto && item.auto(props.entity)) return true;
    // Si no, verificamos lo manual
    return props.checklistCompletados.includes(index);
};

const toggleItem = (index) => {
    const item = checklist.value[index];
    if (item.auto && item.auto(props.entity)) return; // No se puede quitar lo automático

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
    let count = 0;
    checklist.value.forEach((item, idx) => {
        if (isCompleted(idx)) count++;
    });
    return Math.round((count / checklist.value.length) * 100);
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
                <InformationCircleIcon class="w-3 h-3" /> Verificación Inteligente
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
                <div class="flex-1">
                    <p 
                        class="text-sm font-medium leading-relaxed transition-colors"
                        :class="isCompleted(idx) ? 'text-emerald-800 dark:text-emerald-300 line-through opacity-70' : 'text-gray-700 dark:text-gray-300'"
                    >
                        {{ item.label }}
                    </p>
                    <span v-if="item.auto && item.auto(entity)" class="text-[8px] font-black text-emerald-600 uppercase tracking-tighter flex items-center gap-1 mt-1">
                        <CheckCircleIcon class="w-2.5 h-2.5" /> Validado Automáticamente
                    </span>
                </div>
            </div>
        </div>

        <div class="px-6 py-4 bg-indigo-100/30 dark:bg-indigo-900/20 border-t border-indigo-100 dark:border-indigo-900/30">
            <p class="text-[9px] text-indigo-700 dark:text-indigo-400 font-bold uppercase tracking-tighter italic">
                El sistema marca automáticamente las tareas según los datos ingresados. Las tareas sin icono de rayo requieren confirmación manual.
            </p>
        </div>
    </div>
</template>
