<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectInput from '@/Components/SelectInput.vue';
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    PencilSquareIcon,
    ArrowLeftIcon,
    BuildingOfficeIcon,
    IdentificationIcon,
    UserCircleIcon,
    EnvelopeIcon,
    PhoneIcon,
    CalendarDaysIcon,
    ShieldCheckIcon,
    BanknotesIcon,
    DocumentTextIcon,
    CloudArrowUpIcon,
    EyeIcon,
    TrashIcon,
    ArrowDownTrayIcon,
    BriefcaseIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
    ArrowPathIcon,
    LockClosedIcon
} from '@heroicons/vue/24/outline';
import Swal from 'sweetalert2';

const props = defineProps({
    cooperativa: { type: Object, required: true },
    can: { type: Object, required: true },
});

const page = usePage();

// --- Lógica de Subida de Documentos ---
const confirmingDocumentUpload = ref(false);
const docForm = useForm({
    tipo_documento: 'Poder',
    fecha_expedicion: '',
    fecha_vencimiento: '',
    archivo: null,
});

const handleFileChange = (e) => {
    const file = e.target.files[0];
    if (!file) return;
    docForm.archivo = file;
};

const submitDocument = () => {
    if (!docForm.archivo) return;
    docForm.post(route('cooperativas.documentos.store', props.cooperativa.id), {
        preserveScroll: true,
        onSuccess: () => {
            confirmingDocumentUpload.value = false;
            docForm.reset();
            Swal.fire({ title: '¡Subido!', text: 'Documento legal registrado.', icon: 'success', timer: 1500, showConfirmButton: false });
        },
    });
};

// --- Lógica de Borrado con SweetAlert2 ---
const deleteDocument = (doc) => {
    Swal.fire({
        title: '¿Eliminar documento?',
        text: `Se borrará el archivo "${doc.tipo_documento}".`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Sí, eliminar'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('documentos.destroy', doc.id), {
                preserveScroll: true,
                onSuccess: () => Swal.fire('Eliminado', 'El archivo ha sido removido.', 'success'),
            });
        }
    });
};

// --- Formatos ---
const formatDate = (dateString) => {
    if (!dateString) return 'No especificada';
    const date = new Date(dateString);
    return isNaN(date.getTime()) ? 'No especificada' : date.toLocaleDateString('es-CO', { year: 'numeric', month: 'long', day: 'numeric' });
};

const semaforoGeneral = computed(() => {
    const documentos = props.cooperativa.documentos;
    if (!documentos || documentos.length === 0) return { text: 'Sin Documentos', color: 'gray', icon: ExclamationTriangleIcon };
    if (documentos.some(d => d.status === 'vencido')) return { text: 'Docs Vencidos', color: 'red', icon: LockClosedIcon };
    if (documentos.some(d => d.status === 'por_vencer')) return { text: 'Docs por Vencer', color: 'amber', icon: ClockIcon };
    return { text: 'Documentación al Día', color: 'emerald', icon: ShieldCheckIcon };
});

const statusColors = {
    vigente: 'bg-emerald-500',
    por_vencer: 'bg-amber-500',
    vencido: 'bg-red-500',
};

const getRandomColor = (id) => {
  const colors = ['bg-indigo-600', 'bg-emerald-600', 'bg-violet-600', 'bg-amber-600', 'bg-rose-600'];
  return colors[id % colors.length];
};
</script>

<template>
    <Head :title="'Ficha de ' + cooperativa.nombre" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                <div class="flex items-center gap-5">
                    <Link :href="route('cooperativas.index')" class="p-2.5 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-gray-400 hover:text-indigo-600 transition-all shadow-sm">
                        <ArrowLeftIcon class="w-6 h-6" />
                    </Link>
                    <div class="flex items-center gap-4">
                        <div :class="`w-16 h-16 rounded-2xl flex items-center justify-center text-white text-2xl font-black shadow-xl ${getRandomColor(cooperativa.id)}`">
                            {{ cooperativa.nombre[0] }}
                        </div>
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="px-2 py-0.5 bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 text-[10px] font-black uppercase rounded-md border border-indigo-200">ENTIDAD ALIADA</span>
                                <span v-if="cooperativa.estado_activo" class="px-2 py-0.5 bg-emerald-100 text-emerald-700 text-[10px] font-black uppercase rounded-md border border-emerald-200">Activa</span>
                                <span v-else class="px-2 py-0.5 bg-red-100 text-red-700 text-[10px] font-black uppercase rounded-md border border-red-200">Inactiva</span>
                            </div>
                            <h2 class="font-black text-2xl text-gray-900 dark:text-white leading-tight">
                                {{ cooperativa.nombre }}
                            </h2>
                        </div>
                    </div>
                </div>
                
                <div class="flex items-center gap-3 w-full md:w-auto">
                    <Link v-if="can.update" :href="route('cooperativas.edit', cooperativa.id)" class="flex-1 md:flex-none inline-flex items-center justify-center px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs uppercase tracking-widest transition-all shadow-lg shadow-indigo-200 dark:shadow-none">
                        <PencilSquareIcon class="w-4 h-4 mr-2" /> Editar Entidad
                    </Link>
                </div>
            </div>
        </template>
        
        <div class="py-12 bg-gray-50/50 dark:bg-gray-900/50 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
                
                <!-- DASHBOARD DE ESTADO DOCUMENTAL -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-sm relative overflow-hidden group">
                        <div class="absolute -right-4 -bottom-4 opacity-5 group-hover:scale-110 transition-transform"><BuildingOfficeIcon class="w-24 h-24" /></div>
                        <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Identificación</p>
                        <p class="text-lg font-black text-gray-900 dark:text-white">NIT: {{ cooperativa.NIT }}</p>
                        <p class="mt-2 text-[10px] font-bold text-indigo-600 uppercase tracking-tighter">{{ cooperativa.tipo_vigilancia }}</p>
                    </div>

                    <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-sm relative overflow-hidden group">
                        <div class="absolute -right-4 -bottom-4 opacity-5 group-hover:scale-110 transition-transform"><component :is="semaforoGeneral.icon" class="w-24 h-24" /></div>
                        <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Estatus Legal</p>
                        <div class="flex items-center gap-2">
                            <div :class="`w-2 h-2 rounded-full bg-${semaforoGeneral.color}-500 animate-pulse`"></div>
                            <p :class="`text-lg font-black text-${semaforoGeneral.color}-600`">{{ semaforoGeneral.text }}</p>
                        </div>
                        <p class="mt-2 text-[10px] font-bold text-gray-500 uppercase tracking-tighter">{{ cooperativa.documentos?.length || 0 }} archivos registrados</p>
                    </div>

                    <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-sm relative overflow-hidden group">
                        <div class="absolute -right-4 -bottom-4 opacity-5 group-hover:scale-110 transition-transform"><CalendarDaysIcon class="w-24 h-24" /></div>
                        <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Fundación</p>
                        <p class="text-lg font-black text-gray-900 dark:text-white">{{ formatDate(cooperativa.fecha_constitucion) }}</p>
                        <p class="mt-2 text-[10px] font-bold text-gray-500 uppercase tracking-tighter">Constitución oficial</p>
                    </div>

                    <div class="bg-white dark:bg-gray-800 p-6 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-sm relative overflow-hidden group">
                        <div class="absolute -right-4 -bottom-4 opacity-5 group-hover:scale-110 transition-transform"><IdentificationIcon class="w-24 h-24" /></div>
                        <p class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Matrícula</p>
                        <p class="text-lg font-black text-gray-900 dark:text-white">{{ cooperativa.numero_matricula_mercantil || 'N/A' }}</p>
                        <p class="mt-2 text-[10px] font-bold text-gray-500 uppercase tracking-tighter">Registro Mercantil</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                    <!-- COLUMNA IZQUIERDA: INFORMACIÓN CORE (8/12) -->
                    <div class="lg:col-span-8 space-y-8">
                        
                        <!-- Políticas de Cobranza -->
                        <div class="bg-white dark:bg-gray-800 rounded-[2.5rem] border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                            <div class="px-8 py-5 bg-gray-50/50 dark:bg-gray-800/50 border-b border-gray-100 dark:border-gray-700 flex items-center gap-3">
                                <BanknotesIcon class="w-5 h-5 text-indigo-500" />
                                <h3 class="font-black text-gray-900 dark:text-white uppercase tracking-wider text-xs">Políticas y Garantías de Cobro</h3>
                            </div>
                            <div class="p-8">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                                    <div class="space-y-6">
                                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-700">
                                            <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Usa Libranza</span>
                                            <span :class="cooperativa.usa_libranza ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-200 text-gray-500'" class="px-3 py-1 rounded-lg text-[10px] font-black uppercase">{{ cooperativa.usa_libranza ? 'Activado' : 'No Aplica' }}</span>
                                        </div>
                                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-700">
                                            <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Requiere Carta</span>
                                            <span :class="cooperativa.requiere_carta_instrucciones ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-200 text-gray-500'" class="px-3 py-1 rounded-lg text-[10px] font-black uppercase">{{ cooperativa.requiere_carta_instrucciones ? 'Sí' : 'No' }}</span>
                                        </div>
                                    </div>
                                    <div class="space-y-6">
                                        <div class="p-4 bg-gray-50 dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-700">
                                            <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1">Garantía Frecuente</span>
                                            <p class="text-sm font-bold text-gray-700 dark:text-gray-300 uppercase">{{ cooperativa.tipo_garantia_frecuente || 'Sin definir' }}</p>
                                        </div>
                                        <div class="p-4 bg-gray-50 dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-700">
                                            <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest block mb-1">Tasa Máxima (%)</span>
                                            <p class="text-sm font-bold text-red-600">{{ cooperativa.tasa_maxima_moratoria || '0.00' }}% efectivo anual</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Repositorio Digital -->
                        <div class="bg-white dark:bg-gray-800 rounded-[2.5rem] border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                            <div class="px-10 py-6 bg-gray-50/50 dark:bg-gray-800/50 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
                                <div class="flex items-center gap-3">
                                    <ShieldCheckIcon class="w-5 h-5 text-indigo-500" />
                                    <h3 class="font-black text-gray-900 dark:text-white uppercase tracking-wider text-xs">Documentos Legales y Soportes</h3>
                                </div>
                                <button @click="confirmingDocumentUpload = true" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-black text-[10px] uppercase tracking-widest transition-all shadow-sm">
                                    <CloudArrowUpIcon class="w-4 h-4 mr-2" /> Cargar Archivo
                                </button>
                            </div>
                            
                            <div class="p-10">
                                <div v-if="!cooperativa.documentos?.length" class="text-center py-16 opacity-40">
                                    <DocumentTextIcon class="w-16 h-16 mx-auto text-gray-300 mb-4" />
                                    <p class="text-xs font-black uppercase tracking-[0.2em] text-gray-400">Sin documentación registrada</p>
                                </div>
                                <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div v-for="doc in cooperativa.documentos" :key="doc.id" class="bg-white dark:bg-gray-800 p-5 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-sm hover:border-indigo-200 transition-all flex items-start gap-4 group">
                                        <div class="p-3 bg-gray-50 dark:bg-gray-900 rounded-2xl group-hover:bg-indigo-50 transition-colors relative">
                                            <DocumentTextIcon class="w-8 h-8 text-gray-400 group-hover:text-indigo-600" />
                                            <div :class="`absolute -top-1 -right-1 w-3 h-3 rounded-full border-2 border-white dark:border-gray-800 ${statusColors[doc.status]}`" :title="doc.status"></div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex justify-between items-start">
                                                <h4 class="text-xs font-black text-gray-900 dark:text-white truncate pr-2 uppercase">{{ doc.tipo_documento }}</h4>
                                                <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                                    <a :href="route('documentos-legales.show', doc.id)" target="_blank" class="p-1.5 text-gray-400 hover:text-indigo-600"><EyeIcon class="w-4 h-4" /></a>
                                                    <button @click="deleteDocument(doc)" class="p-1.5 text-gray-400 hover:text-red-600"><TrashIcon class="w-4 h-4" /></button>
                                                </div>
                                            </div>
                                            <div class="mt-2 space-y-1">
                                                <p class="text-[9px] font-bold text-gray-400 uppercase tracking-tighter">Exp: {{ formatDate(doc.fecha_expedicion) }}</p>
                                                <p v-if="doc.fecha_vencimiento" class="text-[9px] font-bold text-red-400 uppercase tracking-tighter">Ven: {{ formatDate(doc.fecha_vencimiento) }}</p>
                                            </div>
                                            <div class="mt-4 flex items-center gap-3">
                                                <a :href="route('documentos-legales.show', doc.id)" target="_blank" class="text-[9px] font-black uppercase text-indigo-600 hover:underline">Visualizar</a>
                                                <span class="text-[9px] font-bold text-gray-400 uppercase">{{ doc.status }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- COLUMNA DERECHA: REPRESENTACIÓN Y CONTACTO (4/12) -->
                    <div class="lg:col-span-4 space-y-8">
                        
                        <!-- Tarjeta: Representante Legal -->
                        <div class="bg-white dark:bg-gray-800 rounded-[2rem] border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                            <div class="px-8 py-5 border-b border-gray-50 dark:border-gray-700 flex items-center gap-3">
                                <UserCircleIcon class="w-5 h-5 text-indigo-500" />
                                <h3 class="font-black text-gray-900 dark:text-white uppercase tracking-wider text-[10px]">Representante Legal</h3>
                            </div>
                            <div class="p-8">
                                <div class="flex items-center gap-4 mb-6">
                                    <div class="w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                                        <UserIcon class="w-6 h-6 text-gray-400" />
                                    </div>
                                    <div class="min-w-0">
                                        <h4 class="text-sm font-black text-gray-900 dark:text-white truncate block">{{ cooperativa.representante_legal_nombre || 'No asignado' }}</h4>
                                        <p class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">{{ cooperativa.representante_legal_cedula || 'Sin cédula' }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tarjeta: Contacto Directo -->
                        <div class="bg-white dark:bg-gray-800 rounded-[2rem] border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                            <div class="px-8 py-5 border-b border-gray-50 dark:border-gray-700 flex items-center gap-3">
                                <EnvelopeIcon class="w-5 h-5 text-indigo-500" />
                                <h3 class="font-black text-gray-900 dark:text-white uppercase tracking-wider text-[10px]">Punto de Contacto</h3>
                            </div>
                            <div class="p-8 space-y-6">
                                <div class="space-y-4">
                                    <div class="flex flex-col gap-1">
                                        <span class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1">Atención Administrativa</span>
                                        <p class="text-sm font-bold text-gray-700 dark:text-gray-300">{{ cooperativa.contacto_nombre || 'General' }}</p>
                                        <div class="flex items-center gap-2 text-xs font-medium text-gray-500">
                                            <PhoneIcon class="w-3.5 h-3.5" /> {{ cooperativa.contacto_telefono || 'Sin teléfono' }}
                                        </div>
                                        <div class="flex items-center gap-2 text-xs font-medium text-gray-500 truncate" :title="cooperativa.contacto_correo">
                                            <EnvelopeIcon class="w-3.5 h-3.5" /> {{ cooperativa.contacto_correo || 'Sin correo' }}
                                        </div>
                                    </div>
                                    <div class="pt-4 border-t border-gray-50 dark:border-gray-700">
                                        <span class="text-[9px] font-black text-gray-400 uppercase tracking-widest ml-1 block mb-2">Canal Judicial Oficial</span>
                                        <div class="p-3 bg-gray-50 dark:bg-gray-900 rounded-xl border border-gray-100 dark:border-gray-700">
                                            <p class="text-xs font-bold text-indigo-600 truncate" :title="cooperativa.correo_notificaciones_judiciales">{{ cooperativa.correo_notificaciones_judiciales || 'Sin correo judicial' }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL: CARGAR DOCUMENTO -->
        <Modal :show="confirmingDocumentUpload" @close="confirmingDocumentUpload = false">
            <div class="p-8">
                <div class="flex items-center gap-3 mb-6">
                    <div class="p-2 bg-indigo-50 rounded-lg"><CloudArrowUpIcon class="w-6 h-6 text-indigo-600" /></div>
                    <h2 class="text-xl font-black text-gray-900">Registrar Documento Legal</h2>
                </div>
                <form @submit.prevent="submitDocument" class="space-y-6">
                    <div>
                        <InputLabel value="Tipo de Documento *" class="font-bold text-xs uppercase" />
                        <SelectInput v-model="docForm.tipo_documento" class="mt-1 block w-full rounded-xl border-gray-200 bg-gray-50 font-bold text-sm" required>
                            <option>Poder</option><option>Certificado Existencia</option><option>Carta Autorización</option><option>Protocolo Interno</option>
                        </SelectInput>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <InputLabel value="Fecha de Expedición *" class="font-bold text-xs uppercase" />
                            <TextInput type="date" v-model="docForm.fecha_expedicion" class="w-full rounded-xl" required />
                        </div>
                        <div>
                            <InputLabel value="Fecha de Vencimiento" class="font-bold text-xs uppercase" />
                            <TextInput type="date" v-model="docForm.fecha_vencimiento" class="w-full rounded-xl" />
                        </div>
                    </div>
                    <div>
                        <label class="block w-full cursor-pointer p-10 border-2 border-dashed border-indigo-200 bg-indigo-50/30 rounded-[2rem] text-center hover:border-indigo-400 transition-all group">
                            <input type="file" @input="handleFileChange" class="hidden" />
                            <div class="flex flex-col items-center gap-2">
                                <CloudArrowUpIcon class="w-10 h-10 text-indigo-400 group-hover:scale-110 transition-transform" />
                                <p class="text-[10px] font-black text-indigo-600 uppercase tracking-widest">{{ docForm.archivo ? docForm.archivo.name : 'Seleccionar PDF / Imagen (Máx 128MB)' }}</p>
                            </div>
                        </label>
                        <InputError :message="docForm.errors.archivo" class="mt-2" />
                    </div>
                    <div class="flex justify-end gap-3 pt-4 border-t">
                        <SecondaryButton @click="confirmingDocumentUpload = false" class="!rounded-xl !px-6">Cancelar</SecondaryButton>
                        <PrimaryButton class="!bg-indigo-600 !rounded-xl !px-10 !font-black" :disabled="docForm.processing">Guardar Documento</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

    </AuthenticatedLayout>
</template>

<style scoped>
.animate-in { animation-duration: 0.4s; animation-fill-mode: both; }
</style>

