<script setup>
import Modal from '@/Components/Modal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Textarea from '@/Components/Textarea.vue';
import { useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useNotifications } from '@/composables/useNotifications';

const props = defineProps({
    caso: Object,
    actuaciones: Array,
    isFormDisabled: Boolean,
});

const { notify, confirm: confirmDestructive } = useNotifications();

// --- Lógica de formato (copiada) ---
const parseDate = (s) => {
    if (!s) return null;
    if (/^\d{4}-\d{2}-\d{2}$/.test(s)) {
        const [y, m, d] = s.split('-').map(Number);
        return new Date(y, m - 1, d);
    }
    return new Date(String(s).replace(' ', 'T'));
};
const formatDateTime = (s) =>
    parseDate(s)?.toLocaleString('es-CO', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    }) || 'N/A';

const fmtDateSimple = (d) => {
     if (!d) return 'N/A';
    const dateStr = String(d).replace(' ', 'T');
    const dateObj = new Date(dateStr);
    if (isNaN(dateObj.getTime())) {
        const dateOnlyMatch = String(d).match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (dateOnlyMatch) {
            const [, year, month, day] = dateOnlyMatch;
            const dateOnlyObj = new Date(Date.UTC(year, month - 1, day));
             if (!isNaN(dateOnlyObj.getTime())) {
                 return dateOnlyObj.toLocaleDateString('es-CO', { year: 'numeric', month: 'long', day: 'numeric', timeZone: 'UTC' });
             }
        }
        return 'Fecha Inválida';
    }
    return dateObj.toLocaleDateString('es-CO', { year: 'numeric', month: 'long', day: 'numeric', timeZone: 'UTC' });
};
const today = new Date().toISOString().slice(0, 10);
// --- FIN Lógica de formato ---

// --- Lógica para formulario de Actuaciones ---
const actuacionForm = useForm({
    nota: '',
    fecha_actuacion: today
});

const guardarActuacion = () => {
    actuacionForm.post(route('casos.actuaciones.store', props.caso.id), {
        preserveScroll: true,
        onSuccess: () => {
            actuacionForm.reset();
            actuacionForm.fecha_actuacion = today;
            router.reload({ only: ['actuaciones', 'caso'], preserveState: true });
        },
        onError: (errors) => {
             console.error("Error al guardar actuación:", errors);
        }
    });
};

// --- Lógica para Editar/Eliminar Actuación ---
const editActuacionModalAbierto = ref(false);
const actuacionEnEdicion = ref(null);
const editActuacionForm = useForm({
    nota: '',
    fecha_actuacion: '',
});

const abrirModalEditar = (actuacion) => {
    actuacionEnEdicion.value = actuacion;
    editActuacionForm.nota = actuacion.nota;
    editActuacionForm.fecha_actuacion = actuacion.fecha_actuacion ? String(actuacion.fecha_actuacion).split('T')[0] : '';
    editActuacionModalAbierto.value = true;
};

const cerrarModalEditar = () => {
    editActuacionModalAbierto.value = false;
    actuacionEnEdicion.value = null;
    editActuacionForm.reset();
};

const actualizarActuacion = () => {
    if (!actuacionEnEdicion.value) return;
    editActuacionForm.put(route('casos.actuaciones.update', actuacionEnEdicion.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            cerrarModalEditar();
            router.reload({ only: ['actuaciones', 'caso'], preserveState: true });
        },
        onError: (errors) => {
             console.error("Error al actualizar actuación:", errors);
        }
    });
};

const eliminarActuacion = async (actuacionId) => {
    const result = await confirmDestructive({
        title: '¿Eliminar actuación?',
        text: 'Esta acción no se puede deshacer y se borrará del historial.',
        confirmText: 'Sí, eliminar'
    });

    if (result.isConfirmed) {
        router.delete(route('casos.actuaciones.destroy', actuacionId), {
            preserveScroll: true,
            onSuccess: () => {
                 router.reload({ only: ['actuaciones', 'caso'], preserveState: true });
                 notify('success', 'La actuación ha sido eliminada.');
            },
            onError: (errors) => {
                 console.error("Error al eliminar actuación:", errors);
                 notify('error', 'No se pudo eliminar. Verifica tus permisos.');
            }
        });
    }
};
</script>

<template>
    <div class="space-y-6">
        <div class="bg-gray-50 dark:bg-gray-900/50 p-6 rounded-lg">
            <fieldset :disabled="isFormDisabled">
                <form @submit.prevent="guardarActuacion" class="mb-6 pb-6 border-b dark:border-gray-700">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-2">Nueva Actuación Manual</h3>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                        <div class="md:col-span-3">
                            <InputLabel for="actuacion_nota" value="Descripción de la Actuación" />
                            <Textarea
                                id="actuacion_nota"
                                v-model="actuacionForm.nota"
                                rows="3"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError class="mt-2" :message="actuacionForm.errors.nota" />
                        </div>
                        <div>
                            <InputLabel for="fecha_actuacion" value="Fecha de Actuación" />
                            <TextInput
                                id="fecha_actuacion"
                                type="date"
                                v-model="actuacionForm.fecha_actuacion"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError class="mt-2" :message="actuacionForm.errors.fecha_actuacion" />
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <PrimaryButton :disabled="actuacionForm.processing">
                            {{ actuacionForm.processing ? 'Registrando...' : 'Registrar Actuación' }}
                        </PrimaryButton>
                    </div>
                </form>
            </fieldset>

            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4">Historial de Actuaciones</h3>
            <div v-if="!actuaciones || actuaciones.length === 0" class="text-center py-8 text-gray-500 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg">
                No hay actuaciones registradas para este caso.
            </div>
            <div v-else class="space-y-4">
                <div v-for="actuacion in actuaciones" :key="actuacion.id" class="p-4 border rounded-lg bg-white dark:bg-gray-800 dark:border-gray-700">
                    <p class="text-sm text-gray-800 dark:text-gray-200 whitespace-pre-wrap">{{ actuacion.nota }}</p>
                    <div class="mt-2 text-xs text-gray-500 dark:text-gray-400 flex items-center justify-between">
                        <span>
                            Registrado por: {{ actuacion.user?.name ?? 'Usuario desconocido' }} el {{ formatDateTime(actuacion.created_at) }}
                            <span v-if="actuacion.fecha_actuacion"> | Fecha Actuación: {{ fmtDateSimple(actuacion.fecha_actuacion) }}</span>
                        </span>
                        <div v-if="$page.props.auth.user && ['admin', 'gestor', 'abogado'].includes($page.props.auth.user.tipo_usuario)" class="flex-shrink-0 flex items-center gap-2">
                            <button @click="abrirModalEditar(actuacion)" type="button" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300" title="Editar">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                            </button>
                            <button @click="eliminarActuacion(actuacion.id)" type="button" class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300" title="Eliminar">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal para Editar Actuación -->
        <Modal :show="editActuacionModalAbierto" @close="cerrarModalEditar">
             <form @submit.prevent="actualizarActuacion" class="p-6">
                 <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                     Editar Actuación Manual
                 </h2>
                 <div class="mt-6 space-y-4">
                     <div>
                         <InputLabel for="edit_actuacion_nota" value="Descripción de la Actuación" />
                         <Textarea
                             id="edit_actuacion_nota"
                             v-model="editActuacionForm.nota"
                             rows="4"
                             class="mt-1 block w-full"
                             required
                         />
                         <InputError class="mt-2" :message="editActuacionForm.errors.nota" />
                     </div>
                     <div>
                         <InputLabel for="edit_fecha_actuacion" value="Fecha de Actuación" />
                         <TextInput
                             id="edit_fecha_actuacion"
                             type="date"
                             v-model="editActuacionForm.fecha_actuacion"
                             class="mt-1 block w-full"
                             required
                         />
                         <InputError class="mt-2" :message="editActuacionForm.errors.fecha_actuacion" />
                     </div>
                 </div>
                 <div class="mt-6 flex justify-end gap-3">
                      <SecondaryButton type="button" @click="cerrarModalEditar"> Cancelar </SecondaryButton>
                      <PrimaryButton type="submit" :disabled="editActuacionForm.processing">
                          {{ editActuacionForm.processing ? 'Guardando...' : 'Guardar Cambios' }}
                      </PrimaryButton>
                 </div>
             </form>
        </Modal>
    </div>
</template>