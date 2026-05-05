<script setup>
import { 
    PlusIcon, 
    ClockIcon, 
    CheckCircleIcon, 
    UserIcon, 
    PencilIcon, 
    TrashIcon,
    ChatBubbleBottomCenterTextIcon,
    ArrowPathIcon,
    SparklesIcon
} from '@heroicons/vue/24/outline';
import { useForm } from '@inertiajs/vue3';
import DatePicker from '@/Components/DatePicker.vue';
import Textarea from '@/Components/Textarea.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    proceso: { type: Object, required: true },
    actuaciones: { type: Array, default: () => [] },
    isClosed: { type: Boolean, default: false },
    fmtDateTime: { type: Function, required: true },
    fmtDateSimple: { type: Function, required: true },
});

const emit = defineEmits(['save', 'edit', 'delete']);

const quickTexts = [
    'Se libra mandamiento de pago.',
    'Se radica demanda ante el despacho.',
    'Se solicita medidas cautelares.',
    'Se contesta demanda.',
    'Audiencia inicial celebrada.',
    'Liquidación de crédito aprobada.',
    'Sentencia favorable ejecutoriada.',
];

const appendQuickText = (text) => {
    if (actuacionForm.nota) {
        actuacionForm.nota += ' ' + text;
    } else {
        actuacionForm.nota = text;
    }
};

const actuacionForm = useForm({ 
    nota: '', 
    fecha_actuacion: new Date().toISOString().slice(0, 10)
});

const generarNotaInteligente = () => {
    const p = props.proceso;
    const etapa = p.etapa_actual?.nombre || 'Trámite';
    let texto = `En la fecha, se verifica el estado del proceso en la etapa de ${etapa}. `;
    
    if (etapa.includes('MANDAMIENTO')) {
        texto += "Se observa que el despacho ha librado mandamiento de pago conforme a las pretensiones de la demanda. Se procede con el trámite de notificación.";
    } else if (etapa.includes('DEMANDA')) {
        texto += "Se confirma la radicación exitosa del libelo demandatorio. Se queda a la espera del auto admisorio.";
    } else if (etapa.includes('AUDIENCIA')) {
        texto += "Se asiste a la diligencia judicial programada, dejando constancia de los puntos tratados y las decisiones del juez.";
    } else {
        texto += "Se realiza seguimiento de rutina al expediente digital, constatando que el proceso sigue su curso normal sin novedades extraordinarias.";
    }

    actuacionForm.nota = texto;
    
    Swal.fire({
        title: 'Nota Generada',
        text: 'He redactado una nota profesional basada en la etapa actual. Puedes editarla antes de guardar.',
        icon: 'success',
        toast: true,
        position: 'top-end',
        timer: 3000
    });
};

const handleSave = () => {
    if (!actuacionForm.nota) return;
    emit('save', { ...actuacionForm.data() });
    // El padre limpia tras éxito
};
</script>

<template>
    <div class="space-y-12 animate-in fade-in duration-500">
        
        <!-- REGISTRO RÁPIDO -->
        <div v-if="!isClosed" class="bg-indigo-50/50 dark:bg-indigo-900/10 p-8 rounded-3xl border border-indigo-100 dark:border-indigo-900/30">
            <h3 class="text-sm font-black text-indigo-900 dark:text-indigo-300 uppercase tracking-widest mb-6 flex items-center justify-between">
                <div class="flex items-center gap-2"><PlusIcon class="w-5 h-5" /> Nueva Actuación Procesal</div>
                <button type="button" @click="generarNotaInteligente" class="text-[9px] bg-indigo-600 text-white px-3 py-1 rounded-lg flex items-center gap-1 hover:bg-indigo-700 transition-all shadow-md">
                    <SparklesIcon class="w-3.5 h-3.5" /> Redacción Mágica
                </button>
            </h3>
            <form @submit.prevent="handleSave" class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="md:col-span-3 space-y-2">
                    <div class="flex flex-wrap gap-1.5 mb-2">
                        <button v-for="txt in quickTexts" :key="txt" type="button" @click="appendQuickText(txt)" class="text-[9px] font-bold bg-indigo-100/50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 px-2 py-1 rounded-md hover:bg-indigo-200 transition-colors border border-indigo-100 dark:border-indigo-800">
                            + {{ txt.substring(0, 25) }}{{ txt.length > 25 ? '...' : '' }}
                        </button>
                    </div>
                    <InputLabel value="Descripción de la Actuación *" class="font-bold text-[10px] uppercase ml-1" />
                    <Textarea v-model="actuacionForm.nota" rows="2" class="w-full rounded-2xl border-gray-200 focus:ring-indigo-500 text-sm font-medium" placeholder="Describa el hito procesal..." required />
                </div>
                <div class="space-y-2">
                    <InputLabel value="Fecha *" class="font-bold text-[10px] uppercase ml-1" />
                    <DatePicker v-model="actuacionForm.fecha_actuacion" class="w-full" required />
                    <PrimaryButton :disabled="actuacionForm.processing" class="w-full !mt-4 !py-3 !bg-indigo-600 !rounded-xl !text-[10px] !font-black uppercase tracking-widest shadow-lg shadow-indigo-100 dark:shadow-none">
                        Registrar Hito
                    </PrimaryButton>
                </div>
            </form>
        </div>

        <!-- TIMELINE -->
        <div class="relative">
            <div class="flex items-center justify-between mb-10">
                <h3 class="text-xl font-black text-gray-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
                    <ClockIcon class="w-6 h-6 text-indigo-500" /> Historial de Actuaciones
                </h3>
                <span class="px-4 py-1.5 bg-gray-100 dark:bg-gray-700 rounded-full text-[10px] font-black text-gray-500 tracking-widest border border-gray-200 dark:border-gray-600 uppercase">
                    {{ actuaciones.length }} Eventos
                </span>
            </div>

            <div v-if="!actuaciones.length" class="bg-gray-50 dark:bg-gray-900/30 rounded-[3rem] border-2 border-dashed border-gray-200 dark:border-gray-700 py-32 text-center">
                <ChatBubbleBottomCenterTextIcon class="w-16 h-16 text-gray-200 mx-auto mb-4" />
                <p class="text-sm font-bold text-gray-400 uppercase tracking-[0.2em]">Sin actividad procesal registrada</p>
            </div>

            <div v-else class="relative space-y-8 before:absolute before:inset-0 before:ml-5 before:-translate-x-px md:before:mx-auto md:before:translate-x-0 before:h-full before:w-0.5 before:bg-gradient-to-b before:from-transparent before:via-gray-200 dark:before:via-gray-700 before:to-transparent">
                
                <div v-for="(act, idx) in actuaciones" :key="act.id" class="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group animate-in slide-in-from-bottom-4" :style="{animationDelay: (idx * 50) + 'ms'}">
                    <!-- Icono Timeline -->
                    <div class="flex items-center justify-center w-10 h-10 rounded-full border-4 border-white dark:border-gray-900 bg-gray-50 dark:bg-gray-800 text-indigo-500 shadow-sm shrink-0 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2 transition-all group-hover:scale-110 group-hover:bg-indigo-600 group-hover:text-white z-10">
                        <CheckCircleIcon class="w-5 h-5" />
                    </div>

                    <!-- Contenido -->
                    <div class="w-[calc(100%-4rem)] md:w-[calc(50%-2.5rem)] bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-sm group-hover:shadow-md transition-all">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-[10px] font-black text-indigo-600 dark:text-indigo-400 uppercase tracking-widest bg-indigo-50 dark:bg-indigo-900/30 px-3 py-1 rounded-lg border border-indigo-100 dark:border-indigo-800">
                                {{ fmtDateSimple(act.fecha_actuacion) }}
                            </span>
                            <div v-if="!isClosed" class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button @click="emit('edit', act)" class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg"><PencilIcon class="w-3.5 h-3.5" /></button>
                                <button @click="emit('delete', act.id)" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg"><TrashIcon class="w-3.5 h-3.5" /></button>
                            </div>
                        </div>
                        <div class="text-gray-700 dark:text-gray-300 text-sm leading-relaxed whitespace-pre-wrap font-medium">
                            {{ act.nota }}
                        </div>
                        <div class="mt-5 pt-4 border-t border-gray-50 dark:border-gray-700 flex items-center justify-between text-[9px] font-bold text-gray-400 uppercase tracking-[0.1em]">
                            <span class="flex items-center gap-1.5"><UserIcon class="w-3 h-3" /> {{ act.user?.name || 'Sistema' }}</span>
                            <span>REG: {{ fmtDateTime(act.created_at) }}</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>
</template>
