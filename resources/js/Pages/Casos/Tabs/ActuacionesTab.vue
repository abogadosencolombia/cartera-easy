<script setup>
import Modal from '@/Components/Modal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Textarea from '@/Components/Textarea.vue';
import DatePicker from '@/Components/DatePicker.vue';
import { useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { 
    ChatBubbleBottomCenterTextIcon, 
    PencilIcon, 
    TrashIcon,
    PlusIcon,
    ClockIcon,
    BookmarkIcon,
    PencilSquareIcon,
    SparklesIcon
} from '@heroicons/vue/24/outline';
import Swal from 'sweetalert2';

const props = defineProps({
    caso: Object,
    actuaciones: Array,
    isFormDisabled: Boolean,
});

const formatDateTime = (s) => {
    if (!s) return 'N/A';
    const date = new Date(s);
    return date.toLocaleString('es-CO', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const quickTexts = [
    'Se libra mandamiento de pago.',
    'Se radica demanda ante el despacho.',
    'Se solicita medidas cautelares.',
    'Se contesta demanda.',
    'Audiencia inicial celebrada.',
    'Sentencia favorable ejecutoriada.',
];

const appendQuickText = (text) => {
    actuacionForm.nota = actuacionForm.nota ? (actuacionForm.nota + ' ' + text) : text;
};

const actuacionForm = useForm({ nota: '', fecha_actuacion: new Date().toISOString().slice(0, 10) });

const generarNotaInteligente = () => {
    const c = props.caso;
    const etapa = c.etapa_procesal || 'Cobro';
    let texto = `En seguimiento al expediente administrativo, se verifica el estado actual en ${etapa}. `;
    
    if (etapa.includes('PREJUR')) {
        texto += "Se intensifican gestiones de cobro persuasivo mediante llamadas y correos electrónicos. El deudor manifiesta voluntad de pago pero no concreta acuerdo.";
    } else if (etapa.includes('DEMANDA')) {
        texto += "Se prepara el paquete documental para remitir al equipo jurídico y proceder con la radicación formal de la demanda ejecutiva.";
    } else {
        texto += "Se actualiza el saldo de la deuda y se verifica la vigencia de los datos de contacto de los deudores y codeudores.";
    }

    actuacionForm.nota = texto;
    
    Swal.fire({
        title: 'Nota Generada',
        text: 'He redactado una nota para este caso. Puedes ajustarla.',
        icon: 'success',
        toast: true,
        position: 'top-end',
        timer: 3000
    });
};

const guardarActuacion = () => {
    actuacionForm.post(route('casos.actuaciones.store', props.caso.id), {
        preserveScroll: true,
        onSuccess: () => { actuacionForm.reset(); Swal.fire({ title: 'Registrado', icon: 'success', timer: 1000, showConfirmButton: false }); }
    });
};

const editActuacionModalAbierto = ref(false);
const actuacionEnEdicion = ref(null);
const editActuacionForm = useForm({ nota: '', fecha_actuacion: '' });

const abrirModalEditar = (actuacion) => {
    actuacionEnEdicion.value = actuacion;
    editActuacionForm.nota = actuacion.nota;
    editActuacionForm.fecha_actuacion = actuacion.fecha_actuacion ? String(actuacion.fecha_actuacion).split('T')[0] : '';
    editActuacionModalAbierto.value = true;
};

const actualizarActuacion = () => {
    editActuacionForm.put(route('casos.actuaciones.update', actuacionEnEdicion.value.id), {
        preserveScroll: true,
        onSuccess: () => { editActuacionModalAbierto.value = false; router.reload({ only: ['actuaciones'] }); }
    });
};

const eliminarActuacion = (id) => {
    Swal.fire({ title: '¿Eliminar?', icon: 'warning', showCancelButton: true }).then((result) => {
        if (result.isConfirmed) router.delete(route('casos.actuaciones.destroy', id));
    });
};
</script>

<template>
    <div class="space-y-10 animate-in fade-in duration-500">
        
        <!-- REGISTRO RÁPIDO COMPACTO -->
        <div v-if="!isFormDisabled" class="bg-gray-50/50 dark:bg-gray-900/20 p-6 rounded-xl border border-gray-200 dark:border-gray-700">
            <h3 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider mb-4 flex items-center justify-between">
                <div class="flex items-center gap-2"><PencilSquareIcon class="w-4 h-4 text-indigo-500" /> Nueva Actuación Procesal</div>
                <button type="button" @click="generarNotaInteligente" class="text-[8px] bg-indigo-600 text-white px-2 py-1 rounded flex items-center gap-1 hover:bg-indigo-700 transition-all">
                    <SparklesIcon class="w-3 h-3" /> Autocompletar
                </button>
            </h3>

            <form @submit.prevent="guardarActuacion" class="grid grid-cols-1 lg:grid-cols-4 gap-4">
                <div class="lg:col-span-3 space-y-3">
                    <Textarea v-model="actuacionForm.nota" rows="2" class="w-full rounded-lg border-gray-200 bg-white dark:bg-gray-800 text-xs p-3" placeholder="Detalle del evento..." required />
                    <div class="flex flex-wrap gap-1.5">
                        <button v-for="txt in quickTexts" :key="txt" type="button" @click="appendQuickText(txt)" class="text-[9px] font-bold bg-white dark:bg-gray-700 text-gray-500 border border-gray-200 px-2 py-1 rounded shadow-sm hover:border-indigo-300">
                            {{ txt.substring(0, 20) }}...
                        </button>
                    </div>
                </div>
                <div class="space-y-3">
                    <DatePicker v-model="actuacionForm.fecha_actuacion" class="w-full !text-xs h-10" required />
                    <PrimaryButton :disabled="actuacionForm.processing" class="w-full !py-2.5 !bg-indigo-600 !rounded-lg !text-[10px] !font-bold uppercase tracking-widest transition-all">
                        Registrar
                    </PrimaryButton>
                </div>
            </form>
        </div>

        <!-- LINEA DE TIEMPO COMPACTA -->
        <div class="relative space-y-6 before:absolute before:inset-0 before:ml-4 before:-translate-x-px before:h-full before:w-0.5 before:bg-gray-100 dark:before:bg-gray-700">
            <div v-if="!actuaciones.length" class="py-10 text-center text-gray-400 text-xs font-bold uppercase">Sin historial registrado</div>
            
            <div v-for="act in actuaciones" :key="act.id" class="relative pl-10 group">
                <div class="absolute left-0 top-1 h-8 w-8 rounded-lg bg-white dark:bg-gray-800 border border-indigo-500 shadow-sm flex items-center justify-center z-10 group-hover:bg-indigo-600 group-hover:text-white transition-all">
                    <BookmarkIcon class="w-4 h-4" />
                </div>

                <div class="bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm group-hover:border-indigo-100 transition-all">
                    <div class="flex justify-between items-start mb-2">
                        <span class="text-[9px] font-black text-indigo-600 uppercase tracking-widest">{{ new Date(act.fecha_actuacion).toLocaleDateString('es-CO', {month: 'short', day: 'numeric', year: 'numeric'}) }}</span>
                        <div v-if="!isFormDisabled" class="flex gap-1 opacity-0 group-hover:opacity-100 transition-all">
                            <button @click="abrirModalEditar(act)" class="p-1 text-gray-400 hover:text-indigo-600"><PencilIcon class="w-3.5 h-3.5" /></button>
                            <button @click="eliminarActuacion(act.id)" class="p-1 text-gray-400 hover:text-rose-600"><TrashIcon class="w-3.5 h-3.5" /></button>
                        </div>
                    </div>
                    <p class="text-xs text-gray-700 dark:text-gray-300 leading-normal font-medium whitespace-pre-wrap">{{ act.nota }}</p>
                    <div class="mt-3 pt-3 border-t border-gray-50 dark:border-gray-700 flex justify-between items-center text-[8px] font-bold text-gray-400 uppercase">
                        <span>Por: {{ act.user?.name || 'Sistema' }}</span>
                        <span>{{ formatDateTime(act.created_at) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL EDICIÓN COMPACTO -->
        <Modal :show="editActuacionModalAbierto" @close="editActuacionModalAbierto = false" max-width="md">
             <div class="p-6">
                 <h2 class="text-sm font-bold text-gray-900 uppercase mb-6 border-b pb-2">Editar Registro</h2>
                 <form @submit.prevent="actualizarActuacion" class="space-y-4">
                     <Textarea v-model="editActuacionForm.nota" rows="4" class="w-full rounded-lg border-gray-200 text-xs" required />
                     <DatePicker v-model="editActuacionForm.fecha_actuacion" class="w-full text-xs" required />
                     <div class="flex justify-end gap-2 pt-4">
                          <SecondaryButton @click="editActuacionModalAbierto = false" class="!text-[10px]">Cerrar</SecondaryButton>
                          <PrimaryButton type="submit" class="!bg-indigo-600 !text-[10px]" :disabled="editActuacionForm.processing">Actualizar</PrimaryButton>
                     </div>
                 </form>
             </div>
        </Modal>
    </div>
</template>
