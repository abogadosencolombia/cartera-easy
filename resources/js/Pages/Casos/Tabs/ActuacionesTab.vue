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
    CalendarIcon, 
    UserIcon, 
    PencilIcon, 
    TrashIcon,
    PlusIcon,
    ClockIcon,
    CheckCircleIcon
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
    return date.toLocaleString('es-CO', {
        year: 'numeric', month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
};

const fmtDateSimple = (d) => {
    if (!d) return 'N/A';
    const dateObj = new Date(d);
    return dateObj.toLocaleDateString('es-CO', { year: 'numeric', month: 'long', day: 'numeric' });
};

const today = new Date().toISOString().slice(0, 10);

// Lógica Formulario
const actuacionForm = useForm({ nota: '', fecha_actuacion: today });
const guardarActuacion = () => {
    actuacionForm.post(route('casos.actuaciones.store', props.caso.id), {
        preserveScroll: true,
        onSuccess: () => {
            actuacionForm.reset();
            actuacionForm.fecha_actuacion = today;
            Swal.fire({ title: '¡Registrada!', text: 'La actuación se ha guardado.', icon: 'success', timer: 1500, showConfirmButton: false });
            router.reload({ only: ['actuaciones', 'caso'], preserveState: true });
        }
    });
};

// Edición y Eliminación
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
        onSuccess: () => {
            editActuacionModalAbierto.value = false;
            Swal.fire({ title: '¡Actualizado!', icon: 'success', timer: 1000, showConfirmButton: false });
            router.reload({ only: ['actuaciones', 'caso'], preserveState: true });
        }
    });
};

const eliminarActuacion = (id) => {
    Swal.fire({
        title: '¿Eliminar actuación?',
        text: "Esta acción no se puede deshacer.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Sí, eliminar'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('casos.actuaciones.destroy', id), {
                onSuccess: () => Swal.fire('Eliminado', 'La actuación ha sido borrada.', 'success')
            });
        }
    });
};
</script>

<template>
    <div class="space-y-12 animate-in fade-in duration-500">
        
        <!-- FORMULARIO DE REGISTRO RÁPIDO -->
        <div v-if="!isFormDisabled" class="bg-indigo-50/50 dark:bg-indigo-900/10 p-8 rounded-3xl border border-indigo-100 dark:border-indigo-900/30">
            <h3 class="text-sm font-black text-indigo-900 dark:text-indigo-300 uppercase tracking-widest mb-6 flex items-center gap-2">
                <PlusIcon class="w-5 h-5" /> Nueva Actuación Procesal
            </h3>
            <form @submit.prevent="guardarActuacion" class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="md:col-span-3 space-y-2">
                    <InputLabel value="Descripción de la Actuación *" class="font-bold text-[10px] uppercase" />
                    <Textarea v-model="actuacionForm.nota" rows="2" class="w-full rounded-2xl border-gray-200 focus:ring-indigo-500 text-sm" placeholder="Escriba aquí el detalle de lo sucedido en el proceso..." required />
                    <InputError :message="actuacionForm.errors.nota" />
                </div>
                <div class="space-y-2">
                    <InputLabel value="Fecha de Actuación *" class="font-bold text-[10px] uppercase" />
                    <DatePicker v-model="actuacionForm.fecha_actuacion" class="w-full" required />
                    <InputError :message="actuacionForm.errors.fecha_actuacion" />
                    <PrimaryButton :disabled="actuacionForm.processing" class="w-full !mt-4 !py-3 !bg-indigo-600 !rounded-xl !text-[10px] !font-black uppercase tracking-widest">
                        Registrar
                    </PrimaryButton>
                </div>
            </form>
        </div>

        <!-- LÍNEA DE TIEMPO (TIMELINE) -->
        <div class="relative">
            <h3 class="text-sm font-black text-gray-900 dark:text-white uppercase tracking-widest mb-10 flex items-center gap-2">
                <ClockIcon class="w-5 h-5 text-indigo-500" /> Historial Cronológico
            </h3>

            <div v-if="!actuaciones.length" class="text-center py-20 bg-gray-50 dark:bg-gray-900/30 rounded-3xl border-2 border-dashed border-gray-200 dark:border-gray-700">
                <ChatBubbleBottomCenterTextIcon class="w-12 h-12 text-gray-300 mx-auto mb-3" />
                <p class="text-sm font-bold text-gray-400">No hay actuaciones registradas en este expediente.</p>
            </div>

            <div v-else class="relative space-y-8 before:absolute before:inset-0 before:ml-5 before:-translate-x-px md:before:mx-auto md:before:translate-x-0 before:h-full before:w-0.5 before:bg-gradient-to-b before:from-transparent before:via-gray-200 dark:before:via-gray-700 before:to-transparent">
                
                <div v-for="(act, idx) in actuaciones" :key="act.id" class="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group animate-in slide-in-from-bottom-4" :style="{animationDelay: (idx * 50) + 'ms'}">
                    <!-- Icono Timeline -->
                    <div class="flex items-center justify-center w-10 h-10 rounded-full border border-white dark:border-gray-800 bg-gray-50 dark:bg-gray-700 text-indigo-500 shadow shrink-0 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2 transition-transform group-hover:scale-110 group-hover:bg-indigo-600 group-hover:text-white z-10">
                        <CheckCircleIcon class="w-5 h-5" />
                    </div>

                    <!-- Contenido -->
                    <div class="w-[calc(100%-4rem)] md:w-[calc(50%-2.5rem)] bg-white dark:bg-gray-800 p-6 rounded-2xl border border-gray-100 dark:border-gray-700 shadow-sm group-hover:shadow-md transition-all">
                        <div class="flex items-center justify-between space-x-2 mb-2">
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] font-black text-indigo-600 dark:text-indigo-400 uppercase tracking-widest bg-indigo-50 dark:bg-indigo-900/30 px-2 py-0.5 rounded-md">
                                    {{ fmtDateSimple(act.fecha_actuacion) }}
                                </span>
                            </div>
                            <div v-if="!isFormDisabled" class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button @click="abrirModalEditar(act)" class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg"><PencilIcon class="w-3.5 h-3.5" /></button>
                                <button @click="eliminarActuacion(act.id)" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg"><TrashIcon class="w-3.5 h-3.5" /></button>
                            </div>
                        </div>
                        <div class="text-gray-700 dark:text-gray-300 text-sm leading-relaxed whitespace-pre-wrap font-medium">
                            {{ act.nota }}
                        </div>
                        <div class="mt-4 pt-4 border-t border-gray-50 dark:border-gray-700 flex items-center justify-between text-[9px] font-bold text-gray-400 uppercase tracking-tighter">
                            <span class="flex items-center gap-1"><UserIcon class="w-3 h-3" /> {{ act.user?.name || 'Sistema' }}</span>
                            <span>Registro: {{ formatDateTime(act.created_at) }}</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- MODAL EDICIÓN -->
        <Modal :show="editActuacionModalAbierto" @close="editActuacionModalAbierto = false">
             <div class="p-8">
                 <h2 class="text-xl font-black text-gray-900 mb-6 flex items-center gap-2">
                     <PencilIcon class="w-6 h-6 text-indigo-500" /> Editar Actuación
                 </h2>
                 <form @submit.prevent="actualizarActuacion" class="space-y-6">
                     <div class="space-y-2">
                         <InputLabel value="Descripción de la Actuación *" class="font-bold text-xs uppercase" />
                         <Textarea v-model="editActuacionForm.nota" rows="5" class="w-full rounded-xl border-gray-200" required />
                         <InputError :message="editActuacionForm.errors.nota" />
                     </div>
                     <div class="space-y-2">
                         <InputLabel value="Fecha *" class="font-bold text-xs uppercase" />
                         <DatePicker v-model="editActuacionForm.fecha_actuacion" class="w-full" required />
                         <InputError :message="editActuacionForm.errors.fecha_actuacion" />
                     </div>
                     <div class="flex justify-end gap-3 pt-4 border-t">
                          <SecondaryButton @click="editActuacionModalAbierto = false" class="!rounded-xl !px-6">Cancelar</SecondaryButton>
                          <PrimaryButton type="submit" class="!bg-indigo-600 !rounded-xl !px-10 !font-black" :disabled="editActuacionForm.processing">Guardar Cambios</PrimaryButton>
                     </div>
                 </form>
             </div>
        </Modal>
    </div>
</template>