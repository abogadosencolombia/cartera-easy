<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3'; 

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import DateTimePicker from '@/Components/DateTimePicker.vue';
import DatePicker from '@/Components/DatePicker.vue';
import Textarea from '@/Components/Textarea.vue';
import Dropdown from '@/Components/Dropdown.vue';
import { useProcesos } from '@/composables/useProcesos';
import { addDaysToDate, addMonthsToDate } from '@/Utils/formatters';
import { 
    ArrowPathIcon, 
    ChevronDownIcon, 
    BellIcon, 
    CalendarDaysIcon, 
    DocumentDuplicateIcon, 
    ClockIcon, 
    ShieldCheckIcon,
    ArrowLeftIcon,
    PencilSquareIcon,
    BriefcaseIcon,
    CheckCircleIcon,
    LockOpenIcon,
    InformationCircleIcon,
    UsersIcon,
    FolderOpenIcon,
    ExclamationTriangleIcon,
    ScaleIcon,
    PlusIcon
} from '@heroicons/vue/24/outline';
import Swal from 'sweetalert2';

// --- IMPORTAMOS LOS COMPONENTES DE PESTAÑA ---
import ResumenTab from './Tabs/ResumenTab.vue';
import PartesTab from './Tabs/PartesTab.vue';
import DocumentosTab from './Tabs/DocumentosTab.vue';
import ActuacionesTab from './Tabs/ActuacionesTab.vue';
import ActividadTab from './Tabs/ActividadTab.vue';

const props = defineProps({
    proceso: { type: Object, required: true },
    etapas: { type: Array, required: true }, 
    auditoria: { type: Array, default: () => [] },
});

const page = usePage();
const user = computed(() => page.props.auth.user);

const validTabs = ['resumen', 'partes', 'documentos', 'actuaciones', 'actividad'];
const getInitialTab = () => {
    if (typeof window === 'undefined') return 'resumen';
    const params = new URLSearchParams(window.location.search);
    const tabParam = params.get('tab');
    return (tabParam && validTabs.includes(tabParam)) ? tabParam : 'resumen';
};

const activeTab = ref(getInitialTab());

const setActiveTab = (tab) => {
    activeTab.value = tab;
    const url = new URL(window.location);
    url.searchParams.set('tab', tab);
    window.history.replaceState({}, '', url);
};

// --- LÓGICA DE COMPROMISO DE REVISIÓN (OBLIGATORIO) ---
const showCommitmentModal = ref(false);
const globalRevisionDate = ref(props.proceso.fecha_proxima_revision || '');
let pendingAction = null;

const openCommitmentModal = (action) => {
    pendingAction = action;
    const today = new Date().toISOString().split('T')[0];
    if (!globalRevisionDate.value || globalRevisionDate.value < today) {
        globalRevisionDate.value = today;
    }
    showCommitmentModal.value = true;
};

const executeCommittedAction = () => {
    if (!globalRevisionDate.value) return;
    showCommitmentModal.value = false;
    pendingAction(globalRevisionDate.value);
};

// --- FORMULARIO PARA NOTIFICACIONES ---
const notifForm = useForm(`ProcesoNotifForm:${props.proceso.id}`, {
    fecha_programada: '',
    mensaje: '',
    prioridad: 'media',
});

const submitNotification = () => {
    notifForm.post(route('procesos.notificaciones.store', props.proceso.id), {
        preserveScroll: true,
        onSuccess: () => {
            notifForm.reset();
            router.reload({ only: ['auth'] });
        },
    });
};

const { formatDate, getRevisionStatus } = useProcesos();

const searchEtapa = ref('');
const filteredEtapas = computed(() => {
    return props.etapas.filter(e => 
        e.nombre.toLowerCase().includes(searchEtapa.value.toLowerCase())
    );
});

// --- Helpers ---
const fmtDateTime = (d) => d ? new Date(String(d).replace(' ', 'T')).toLocaleString('es-CO', { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true }) : 'N/A';
const fmtDateSimple = (d) => d ? new Date(String(d).replace(' ', 'T')).toLocaleDateString('es-CO', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A';

const isClosed = computed(() => props.proceso.estado === 'CERRADO');
const files = computed(() => props.proceso?.documentos ?? []);
const tieneContrato = computed(() => !!props.proceso?.contrato?.id);
const actuaciones = computed(() => props.proceso?.actuaciones ?? []);

// --- LOGICA CAMBIO DE ETAPA ---
const showEtapaModal = ref(false);
const etapaForm = useForm(`ProcesoEtapaForm:${props.proceso.id}`, {
    etapa_procesal_id: props.proceso.etapa_procesal_id || '',
    observacion: '',
    fecha_proxima_revision: props.proceso.fecha_proxima_revision || '',
});

const openEtapaModal = () => {
    etapaForm.etapa_procesal_id = props.proceso.etapa_procesal_id;
    etapaForm.observacion = '';
    etapaForm.fecha_proxima_revision = props.proceso.fecha_proxima_revision;
    showEtapaModal.value = true;
};

const submitEtapa = () => {
    openCommitmentModal((date) => {
        etapaForm.fecha_proxima_revision = date;
        etapaForm.patch(route('procesos.update_etapa', props.proceso.id), {
            onSuccess: () => showEtapaModal.value = false,
            preserveScroll: true
        });
    });
};

// --- Lógica Cierre/Reapertura ---
const showCloseModal = ref(false);
const closeForm = useForm(`ProcesoCloseForm:${props.proceso.id}`, { nota_cierre: '' });
const openCloseModal = () => { closeForm.reset(); showCloseModal.value = true; };
const submitCloseCase = () => closeForm.patch(route('procesos.close', props.proceso.id), { preserveScroll: true, onSuccess: () => showCloseModal.value = false });

const showReopenModal = ref(false);
const reopenForm = useForm({});
const openReopenModal = () => (showReopenModal.value = true);
const reopenTheCase = () => reopenForm.patch(route('procesos.reopen', props.proceso.id), { preserveScroll: true, onSuccess: () => showReopenModal.value = false });

// --- Lógica Eliminación ---
const confirmingDeletion = ref(false);
const deleteForm = useForm({});
const askDelete = () => (confirmingDeletion.value = true);
const doDelete = () => deleteForm.delete(route('procesos.destroy', props.proceso.id), { onSuccess: () => confirmingDeletion.value = false });

// --- Documentos ---
const handleUpload = (data) => {
    openCommitmentModal((date) => {
        const form = useForm({ ...data, fecha_proxima_revision: date });
        form.post(route('procesos.documentos.store', props.proceso.id), {
            forceFormData: true, preserveScroll: true, onSuccess: () => router.reload({ only: ['proceso'], preserveState: true }),
        });
    });
};

const deletingDoc = ref(null);
const doDeleteDoc = () => {
  if (!deletingDoc.value) return;
  useForm({}).delete(route('procesos.documentos.destroy', { proceso: props.proceso.id, documento: deletingDoc.value.id }), {
      preserveScroll: true, onSuccess: () => { deletingDoc.value = null; router.reload({ only: ['proceso'], preserveState: true }); }
  });
};

// --- Actuaciones ---
const handleSaveActuacion = (data) => {
    openCommitmentModal((date) => {
        const form = useForm({ ...data, fecha_proxima_revision: date });
        form.post(route('procesos.actuaciones.store', props.proceso.id), { 
            preserveScroll: true, onSuccess: () => router.reload({ only: ['proceso'], preserveState: true }) 
        });
    });
};

const editActuacionModalAbierto = ref(false);
const actuacionEnEdicion = ref(null);
const editActuacionForm = useForm(`ProcesoEditActuacionForm:${props.proceso.id}`, { nota: '', fecha_actuacion: '' });

const abrirModalEditar = (actuacion) => {
    actuacionEnEdicion.value = actuacion;
    editActuacionForm.nota = actuacion.nota;
    editActuacionForm.fecha_actuacion = actuacion.fecha_actuacion ? String(actuacion.fecha_actuacion).split('T')[0] : '';
    editActuacionModalAbierto.value = true;
};
const actualizarActuacion = () => {
    if (!actuacionEnEdicion.value) return;
    editActuacionForm.put(route('procesos.actuaciones.update', actuacionEnEdicion.value.id), { preserveScroll: true, onSuccess: () => { editActuacionModalAbierto.value = false; router.reload({ only: ['proceso'], preserveState: true }); } });
};
const eliminarActuacion = (id) => {
    if (confirm('¿Eliminar actuación?')) router.delete(route('procesos.actuaciones.destroy', id), { preserveScroll: true, onSuccess: () => router.reload({ only: ['proceso'], preserveState: true }) });
};

const copyLegalInfo = () => {
    const radicado = props.proceso.radicado || 'SIN RADICADO';
    const juzgado = props.proceso.juzgado?.nombre || 'POR DEFINIR';
    const demandantes = props.proceso.demandantes?.map(p => p.nombre_completo).join(', ') || 'SIN REGISTRO';
    const demandados = props.proceso.demandados?.map(p => p.nombre_completo).join(', ') || 'SIN REGISTRO';
    const asunto = props.proceso.asunto || 'N/A';
    
    let text = `EXPEDIENTE JUDICIAL\nRadicado: ${radicado}\nJuzgado: ${juzgado}\nDemandantes: ${demandantes}\nDemandados: ${demandados}\nAsunto: ${asunto}`;
    navigator.clipboard.writeText(text).then(() => {
        Swal.fire({ title: '¡Copiado!', icon: 'success', toast: true, position: 'top-end', timer: 2000, showConfirmButton: false });
    });
};

const statusClasses = {
    'ABIERTO': 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    'CERRADO': 'bg-rose-50 text-rose-700 ring-rose-600/20',
    'SUSPENDIDO': 'bg-amber-50 text-amber-700 ring-amber-600/20',
};
</script>

<template>
  <Head :title="`Radicado ${proceso.radicado || ''}`" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex flex-col md:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-3 min-w-0 flex-1">
          <Link :href="route('procesos.index')" class="p-1.5 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 text-gray-400 hover:text-indigo-600 transition-all">
            <ArrowLeftIcon class="h-4 w-4" />
          </Link>
          <div class="min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <h2 class="font-bold text-xl text-gray-900 dark:text-white leading-tight truncate">Expediente <span class="text-indigo-600 dark:text-indigo-400">{{ proceso.radicado || '—' }}</span></h2>
              <span class="px-2 py-0.5 text-[9px] font-bold uppercase rounded ring-1" :class="statusClasses[proceso.estado] || statusClasses['ABIERTO']">
                {{ proceso.estado }}
              </span>
            </div>
            <p class="text-xs text-gray-500 font-medium truncate" :title="proceso.asunto">{{ proceso.asunto || 'Sin descripción' }}</p>
          </div>
        </div>

        <div class="flex items-center gap-2 flex-wrap justify-end">
          <button @click="copyLegalInfo" class="inline-flex items-center px-3 py-1.5 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg font-bold text-[10px] text-gray-600 dark:text-gray-300 uppercase tracking-wider hover:bg-white transition-all shadow-sm">
            <DocumentDuplicateIcon class="h-3.5 w-3.5 mr-1.5 text-gray-400" /> Copiar Info
          </button>
          
          <Link v-if="!proceso.contrato && !isClosed" :href="route('honorarios.contratos.create', { proceso_id: proceso.id })" class="inline-flex items-center px-3 py-1.5 bg-indigo-600 text-white rounded-lg font-bold text-[10px] uppercase tracking-wider hover:bg-indigo-700 transition-all shadow-sm">
            <BriefcaseIcon class="h-3.5 w-3.5 mr-1.5" /> Generar Contrato
          </Link>
          <Link v-else-if="proceso.contrato" :href="route('honorarios.contratos.show', proceso.contrato.id)" class="inline-flex items-center px-3 py-1.5 bg-emerald-600 text-white rounded-lg font-bold text-[10px] uppercase tracking-wider hover:bg-emerald-700 transition-all shadow-sm">
            <CheckCircleIcon class="h-3.5 w-3.5 mr-1.5" /> Contrato #{{ proceso.contrato.id }}
          </Link>

          <PrimaryButton @click="openEtapaModal" v-if="!isClosed" class="!py-1.5 !text-[10px]">
            <ArrowPathIcon class="h-3.5 w-3.5 mr-1.5" /> Etapa
          </PrimaryButton>
          <Link :href="route('procesos.edit', proceso.id)">
            <SecondaryButton v-if="!isClosed" class="!py-1.5 !text-[10px]">
              <PencilSquareIcon class="h-3.5 w-3.5 mr-1.5" /> Editar
            </SecondaryButton>
          </Link>
          
          <button v-if="!isClosed && ['admin', 'abogado', 'gestor'].includes(user.tipo_usuario)" @click="openCloseModal" class="p-1.5 bg-rose-50 text-rose-600 border border-rose-200 rounded-lg hover:bg-rose-100 transition-all" title="Finalizar Proceso"><ClockIcon class="h-4 w-4" /></button>
          
          <template v-if="user.tipo_usuario === 'admin'">
            <button v-if="isClosed" @click="openReopenModal" class="p-1.5 bg-emerald-50 text-emerald-600 border border-emerald-200 rounded-lg hover:bg-emerald-100 transition-all" title="Reabrir Caso"><LockOpenIcon class="h-4 w-4" /></button>
            <button @click="askDelete" class="p-2 bg-red-600 text-white border-2 border-red-800 rounded-lg hover:bg-red-700 transition-all shadow-lg animate-pulse" title="¡PELIGRO: ELIMINAR REGISTRO!">
                <div class="flex items-center gap-1">
                    <TrashIcon class="h-4 w-4" />
                    <span class="text-[9px] font-black uppercase">Eliminar</span>
                </div>
            </button>
          </template>
        </div>
      </div>
    </template>

    <div class="py-6">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        
        <!-- DASHBOARD COMPACTO -->
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-2xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="grid grid-cols-1 lg:grid-cols-4">
                <div class="p-5 bg-gray-50/50 dark:bg-gray-700/30 border-b lg:border-b-0 lg:border-r border-gray-200 dark:border-gray-700">
                    <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mb-1">Etapa Procesal Actual</p>
                    <div v-if="proceso.etapa_actual" class="flex items-center gap-2 mb-3">
                        <span class="px-2.5 py-0.5 text-[10px] font-black uppercase rounded-md bg-blue-50 text-blue-700 ring-1 ring-blue-600/20">
                            {{ proceso.etapa_actual.nombre }}
                        </span>
                    </div>
                    <p v-else class="text-xs font-bold text-gray-400 italic">No definida</p>
                    <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mb-0.5">Entidad / Juzgado</p>
                    <p class="text-xs font-bold text-gray-700 dark:text-gray-200 truncate">{{ proceso.juzgado?.nombre || '—' }}</p>
                </div>

                <div class="lg:col-span-3 p-5 grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="border-r border-gray-100 dark:border-gray-700 last:border-0 pr-4">
                        <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mb-1 flex items-center gap-1"><ClockIcon class="h-3 w-3" /> Próxima Revisión</p>
                        <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold" :class="getRevisionStatus(proceso.fecha_proxima_revision).classes">
                            {{ getRevisionStatus(proceso.fecha_proxima_revision).text }}
                        </span>
                    </div>
                    <div class="border-r border-gray-100 dark:border-gray-700 last:border-0 pr-4">
                        <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mb-1 flex items-center gap-1"><CalendarDaysIcon class="h-3 w-3" /> Radicación</p>
                        <p class="text-xs font-bold text-gray-700 dark:text-gray-200">{{ formatDate(proceso.fecha_radicado) }}</p>
                    </div>
                    <div class="border-r border-gray-100 dark:border-gray-700 last:border-0 pr-4">
                        <p class="text-[9px] font-bold text-indigo-500 uppercase tracking-widest mb-1 flex items-center gap-1"><ShieldCheckIcon class="h-3 w-3" /> Responsable</p>
                        <p class="text-xs font-bold text-gray-700 dark:text-gray-200 truncate">{{ proceso.responsable_revision?.name || '—' }}</p>
                    </div>
                    <div>
                        <p class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mb-1 flex items-center gap-1"><ScaleIcon class="h-3 w-3" /> Contrato</p>
                        <span v-if="tieneContrato" class="text-[10px] font-black text-emerald-600 uppercase">Activo #{{ proceso.contrato.id }}</span>
                        <span v-else class="text-[10px] font-black text-amber-500 uppercase">Sin Vincular</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ALERTAS -->
        <div v-if="proceso.info_incompleta" class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 p-3 rounded-xl flex items-center gap-3">
            <ExclamationTriangleIcon class="h-5 w-5 text-amber-500" />
            <div>
                <p class="text-[10px] font-black text-amber-800 dark:text-amber-200 uppercase tracking-widest leading-none mb-1">Información Incompleta</p>
                <p class="text-[10px] text-amber-700 dark:text-amber-300">Este expediente requiere identificar partes o datos de contacto.</p>
            </div>
        </div>

        <!-- NAVEGACIÓN POR PESTAÑAS -->
        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-2xl border border-gray-200 dark:border-gray-700 overflow-visible">
            <div class="border-b border-gray-100 dark:border-gray-700 px-4">
                <nav class="-mb-px flex space-x-6 overflow-x-auto scrollbar-hide">
                    <button v-for="tab in [
                        { id: 'resumen', label: 'General', icon: InformationCircleIcon },
                        { id: 'partes', label: 'Partes', icon: UsersIcon },
                        { id: 'documentos', label: 'Soportes', icon: FolderOpenIcon },
                        { id: 'actuaciones', label: 'Actuaciones', icon: ClipboardDocumentListIcon },
                        { id: 'actividad', label: 'Historial', icon: ClockIcon },
                    ]" :key="tab.id" @click="setActiveTab(tab.id)"
                    :class="[ activeTab === tab.id ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-400 hover:text-gray-600 hover:border-gray-300' ]"
                    class="flex items-center whitespace-nowrap py-4 px-1 border-b-2 font-bold text-[11px] uppercase tracking-wider transition-all group">
                        <component :is="tab.icon" :class="[activeTab === tab.id ? 'text-indigo-600' : 'text-gray-300 group-hover:text-gray-400']" class="h-4 w-4 mr-2 transition-colors" />
                        {{ tab.label }}
                    </button>
                </nav>
            </div>

            <div class="p-6">
                <!-- TAB: RESUMEN -->
                <ResumenTab v-show="activeTab === 'resumen'" :proceso="proceso" :formatDate="formatDate">
                    <template #notificaciones>
                        <div class="bg-indigo-900 p-6 rounded-xl shadow-lg relative overflow-visible group z-20">
                            <div class="relative z-10 space-y-4">
                                <h3 class="text-xs font-bold text-white uppercase tracking-widest flex items-center gap-2">
                                    <BellIcon class="h-4 w-4 text-amber-400" /> Agendar Recordatorio
                                </h3>
                                <form @submit.prevent="submitNotification" class="space-y-3">
                                    <DateTimePicker v-model="notifForm.fecha_programada" class="!bg-white/10 !border-white/10 !text-white !rounded-lg text-xs" />
                                    <Textarea v-model="notifForm.mensaje" class="!bg-white/10 !border-white/10 !text-white !rounded-lg text-[11px]" rows="2" placeholder="Nota rápida..." required />
                                    <PrimaryButton :disabled="notifForm.processing" class="w-full !bg-white !text-indigo-900 !rounded-lg !py-2.5 !text-[10px] !font-bold uppercase tracking-widest shadow-md">
                                        <PlusIcon class="h-3 w-3 mr-1.5" /> Agendar
                                    </PrimaryButton>
                                </form>
                            </div>
                        </div>
                    </template>
                </ResumenTab>

                <!-- TAB: PARTES -->
                <PartesTab v-show="activeTab === 'partes'" :proceso="proceso" />

                <!-- TAB: DOCUMENTOS -->
                <DocumentosTab v-show="activeTab === 'documentos'" :proceso="proceso" :files="files" :isClosed="isClosed" :formatDate="formatDate" @upload="handleUpload" @delete="askDeleteDoc" />

                <!-- TAB: ACTUACIONES -->
                <ActuacionesTab v-show="activeTab === 'actuaciones'" :proceso="proceso" :actuaciones="actuaciones" :isClosed="isClosed" :fmtDateTime="fmtDateTime" :fmtDateSimple="fmtDateSimple" @save="handleSaveActuacion" @edit="abrirModalEditar" @delete="eliminarActuacion" />

                <!-- TAB: ACTIVIDAD / AUDITORÍA -->
                <ActividadTab v-show="activeTab === 'actividad'" :proceso="proceso" :auditoria="auditoria" />
            </div>
        </div>
      </div>
    </div>

    <!-- MODALES -->
    <Modal :show="confirmingDeletion" @close="confirmingDeletion = false" centered>
       <div class="p-6 text-center">
          <ExclamationTriangleIcon class="h-12 w-12 text-rose-500 mx-auto mb-4" />
          <h3 class="text-lg font-bold uppercase tracking-tighter">Eliminar Expediente</h3>
          <p class="mt-2 text-sm text-gray-500">¿Seguro que deseas eliminar este radicado permanentemente? Esta acción no se puede deshacer.</p>
          <div class="mt-8 flex justify-center gap-3"><SecondaryButton @click="confirmingDeletion = false">Cancelar</SecondaryButton><DangerButton @click="doDelete">Sí, Eliminar</DangerButton></div>
       </div>
    </Modal>

    <Modal :show="!!deletingDoc" @close="deletingDoc = null" centered>
       <div class="p-6">
          <h3 class="text-lg font-bold">Eliminar Documento</h3>
          <p class="mt-2 text-sm text-gray-500">¿Deseas quitar "{{ deletingDoc?.file_name }}" de este expediente?</p>
          <div class="mt-6 flex justify-end gap-3"><SecondaryButton @click="deletingDoc = null">Cancelar</SecondaryButton><DangerButton @click="doDeleteDoc">Eliminar</DangerButton></div>
       </div>
    </Modal>

    <Modal :show="showCloseModal" @close="showCloseModal = false" centered>
        <div class="p-6">
            <h2 class="text-lg font-bold uppercase tracking-tight mb-4">Cerrar Expediente</h2>
            <div class="space-y-4">
                <Textarea v-model="closeForm.nota_cierre" class="w-full rounded-xl border-gray-200" rows="4" placeholder="Indica el motivo del cierre o decisión final..." />
                <div class="flex justify-end gap-3"><SecondaryButton @click="showCloseModal = false">Cancelar</SecondaryButton><DangerButton @click="submitCloseCase">Confirmar Cierre</DangerButton></div>
            </div>
        </div>
    </Modal>

    <Modal :show="showReopenModal" @close="showReopenModal = false" centered>
        <div class="p-6 text-center">
            <h2 class="text-lg font-bold uppercase tracking-tight mb-2">Reabrir Expediente</h2>
            <p class="text-sm text-gray-500 mb-6">El proceso volverá a estado ABIERTO para permitir nuevas actuaciones.</p>
            <div class="flex justify-center gap-3"><SecondaryButton @click="showReopenModal = false">Cancelar</SecondaryButton><PrimaryButton @click="reopenTheCase">Confirmar Reapertura</PrimaryButton></div>
        </div>
    </Modal>

    <Modal :show="editActuacionModalAbierto" @close="editActuacionModalAbierto = false" centered>
        <form @submit.prevent="actualizarActuacion" class="p-8">
             <h2 class="text-lg font-bold uppercase tracking-tight mb-6">Editar Actuación</h2>
             <div class="space-y-6">
                 <div><InputLabel value="Descripción" /><Textarea v-model="editActuacionForm.nota" rows="4" class="w-full rounded-xl border-gray-200 mt-1" required /></div>
                 <div><InputLabel value="Fecha" /><DatePicker v-model="editActuacionForm.fecha_actuacion" class="w-full mt-1" /></div>
             </div>
             <div class="mt-8 flex justify-end gap-3"><SecondaryButton @click="editActuacionModalAbierto = false">Cancelar</SecondaryButton><PrimaryButton type="submit">Guardar Cambios</PrimaryButton></div>
        </form>
    </Modal>

    <!-- MODAL DE COMPROMISO DE REVISIÓN (OBLIGATORIO) -->
    <Modal :show="showCommitmentModal" @close="showCommitmentModal = false" maxWidth="sm" centered>
        <div class="p-8">
            <div class="flex items-center gap-3 mb-4 text-indigo-600">
                <CalendarDaysIcon class="h-10 w-10" />
                <h2 class="text-xl font-black uppercase tracking-tight leading-none">Próxima<br/>Revisión</h2>
            </div>
            <p class="text-sm text-gray-600 mb-8 font-medium leading-relaxed">
                Para garantizar el seguimiento, es <strong>obligatorio</strong> definir cuándo se realizará la siguiente validación de este radicado.
            </p>
            
            <div class="space-y-6">
                <div class="p-5 bg-indigo-50 rounded-2xl border-2 border-indigo-100">
                    <InputLabel value="¿Cuándo volverás a revisar?" class="text-[10px] font-black text-indigo-700 uppercase mb-2 tracking-widest" />
                    <DatePicker v-model="globalRevisionDate" class="w-full !rounded-xl" required />
                </div>
                
                <div class="flex flex-col gap-3 pt-2">
                    <PrimaryButton @click="executeCommittedAction" class="w-full justify-center !py-4 !text-xs !font-black !uppercase !tracking-widest !bg-indigo-600 shadow-xl shadow-indigo-100" :disabled="!globalRevisionDate">
                        Confirmar y Continuar
                    </PrimaryButton>
                    <SecondaryButton @click="showCommitmentModal = false" class="w-full justify-center !py-3 !text-[10px]">Cancelar Acción</SecondaryButton>
                </div>
            </div>
        </div>
    </Modal>

    <!-- MODAL CAMBIO DE ETAPA -->
    <Modal :show="showEtapaModal" @close="showEtapaModal = false" centered>
        <div class="p-8">
            <h2 class="text-xl font-black uppercase tracking-tight text-gray-900 mb-6">Avanzar Etapa</h2>
            <div class="space-y-6">
                <div>
                    <InputLabel for="nueva_etapa" value="Nueva Etapa Procesal *" class="text-[10px] font-black uppercase text-gray-400" />
                    <Dropdown align="left" width="full">
                        <template #trigger>
                            <button type="button" class="mt-1 flex w-full items-center justify-between gap-2 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm font-bold shadow-sm hover:border-indigo-500 transition-all cursor-pointer">
                                <span>{{ etapaForm.etapa_procesal_id ? etapas.find(e => e.id === etapaForm.etapa_procesal_id)?.nombre : 'Seleccione una etapa...' }}</span>
                                <ChevronDownIcon class="h-4 w-4 text-gray-400" />
                            </button>
                        </template>
                        <template #content>
                            <div class="p-2 border-b border-gray-100 sticky top-0 bg-white z-10">
                                <TextInput v-model="searchEtapa" placeholder="Filtrar etapas..." class="w-full text-xs h-8" @click.stop />
                            </div>
                            <div class="py-1 max-h-60 overflow-y-auto">
                                <button v-for="etapa in filteredEtapas" :key="etapa.id" @click="etapaForm.etapa_procesal_id = etapa.id" class="block w-full text-left px-4 py-2.5 text-xs font-bold text-gray-700 hover:bg-gray-50" :class="{ 'bg-indigo-50 text-indigo-700': etapaForm.etapa_procesal_id === etapa.id }">
                                    {{ etapa.nombre }} <span class="text-[9px] font-normal opacity-50 ml-1">({{ etapa.riesgo }})</span>
                                </button>
                            </div>
                        </template>
                    </Dropdown>
                </div>
                <div>
                    <InputLabel for="obs_etapa" value="Observación del Cambio" class="text-[10px] font-black uppercase text-gray-400" />
                    <Textarea id="obs_etapa" v-model="etapaForm.observacion" class="w-full mt-1 rounded-xl border-gray-200 bg-gray-50 text-xs" rows="3" placeholder="Contexto sobre el avance..." />
                </div>
                <div class="flex items-center justify-end gap-3 pt-4 border-t">
                    <SecondaryButton @click="showEtapaModal = false">Cancelar</SecondaryButton>
                    <PrimaryButton @click="submitEtapa" :disabled="etapaForm.processing || !etapaForm.etapa_procesal_id">Actualizar Etapa</PrimaryButton>
                </div>
            </div>
        </div>
    </Modal>

  </AuthenticatedLayout>
</template>

<style>
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
</style>
