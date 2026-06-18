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
    ClipboardDocumentListIcon,
    ScaleIcon,
    PlusIcon,
    TrashIcon
} from '@heroicons/vue/24/outline';
import AppAlert from '@/Utils/appAlert';

// --- IMPORTAMOS LOS COMPONENTES DE PESTAÑA ---
import ResumenTab from './Tabs/ResumenTab.vue';
import PartesTab from './Tabs/PartesTab.vue';
import DocumentosTab from './Tabs/DocumentosTab.vue';
import ActuacionesTab from './Tabs/ActuacionesTab.vue';
import ActividadTab from './Tabs/ActividadTab.vue';
import ExpedienteIntegrityPanel from '@/Components/ExpedienteIntegrityPanel.vue';

const props = defineProps({
    proceso: { type: Object, required: true },
    etapas: { type: Array, required: true }, 
    auditoria: { type: Array, default: () => [] },
});

const page = usePage();
const user = computed(() => page.props.auth.user);

const validTabs = ['resumen', 'integridad', 'partes', 'documentos', 'actuaciones', 'actividad'];
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

// --- AUTOMATIZACIÓN: Sugerir fecha de revisión según la etapa seleccionada ---
watch(() => etapaForm.etapa_procesal_id, (newId) => {
    if (!newId) return;
    const etapa = props.etapas.find(e => e.id === newId);
    if (!etapa) return;

    let dias = 15; // Por defecto
    if (etapa.nombre.includes('DEMANDA')) dias = 10;
    if (etapa.nombre.includes('MANDAMIENTO')) dias = 30;
    if (etapa.nombre.includes('AUDIENCIA')) dias = 45;
    if (etapa.nombre.includes('SENTENCIA')) dias = 60;

    const fechaSugerida = new Date();
    fechaSugerida.setDate(fechaSugerida.getDate() + dias);
    etapaForm.fecha_proxima_revision = fechaSugerida.toISOString().split('T')[0];
});

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
const documentosTabRef = ref(null);
const handleUpload = (data) => {
    openCommitmentModal((date) => {
        const form = useForm({ 
            documentos: data.documentos,
            fecha_proxima_revision: date 
        });
        form.post(route('procesos.documentos.store', props.proceso.id), {
            forceFormData: true, 
            preserveScroll: true, 
            onSuccess: () => {
                router.reload({ only: ['proceso'], preserveState: true });
                if (documentosTabRef.value) {
                    documentosTabRef.value.clearSelectedFiles();
                }
            },
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
        AppAlert.fire({ title: '¡Copiado!', icon: 'success', toast: true, position: 'top-end', timer: 2000, showConfirmButton: false });
    });
};

const statusClasses = {
    'ACTIVO': 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-400/20',
    'ABIERTO': 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-400/20',
    'CERRADO': 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-950/40 dark:text-rose-300 dark:ring-rose-400/20',
    'SUSPENDIDO': 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-400/20',
};

const estadoBadgeClass = computed(() => statusClasses[props.proceso.estado] || statusClasses['ABIERTO']);
const revisionStatus = computed(() => getRevisionStatus(props.proceso.fecha_proxima_revision));
const integridadScore = computed(() => props.proceso.integridad_score || props.proceso.integridad_resumen?.score || 0);

const dashboardCards = computed(() => [
    {
        label: 'Etapa procesal',
        value: props.proceso.etapa_actual?.nombre || 'No definida',
        detail: props.proceso.juzgado?.nombre || 'Entidad sin registrar',
        icon: ArrowPathIcon,
        accentClass: 'border-indigo-100 bg-indigo-50 text-indigo-600 dark:border-indigo-900/60 dark:bg-indigo-950/40 dark:text-indigo-300',
    },
    {
        label: 'Proxima revision',
        value: revisionStatus.value.text,
        detail: props.proceso.fecha_proxima_revision ? formatDate(props.proceso.fecha_proxima_revision) : 'Sin fecha programada',
        icon: ClockIcon,
        accentClass: 'border-amber-100 bg-amber-50 text-amber-600 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-300',
        valueClass: revisionStatus.value.classes,
    },
    {
        label: 'Responsable',
        value: props.proceso.responsable_revision?.name || 'Sin asignar',
        detail: 'Seguimiento del expediente',
        icon: ShieldCheckIcon,
        accentClass: 'border-sky-100 bg-sky-50 text-sky-600 dark:border-sky-900/60 dark:bg-sky-950/40 dark:text-sky-300',
    },
    {
        label: 'Contrato',
        value: tieneContrato.value ? `Activo #${props.proceso.contrato.id}` : 'Sin vincular',
        detail: tieneContrato.value ? 'Honorarios registrados' : 'Pendiente de contrato',
        icon: BriefcaseIcon,
        accentClass: tieneContrato.value
            ? 'border-emerald-100 bg-emerald-50 text-emerald-600 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-300'
            : 'border-gray-200 bg-gray-50 text-gray-600 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-300',
    },
]);

const tabItems = computed(() => [
    { id: 'resumen', label: 'General', icon: InformationCircleIcon },
    { id: 'integridad', label: 'Integridad', icon: CheckCircleIcon, badge: `${integridadScore.value}%` },
    { id: 'partes', label: 'Partes', icon: UsersIcon },
    { id: 'documentos', label: 'Soportes', icon: FolderOpenIcon },
    { id: 'actuaciones', label: 'Actuaciones', icon: ClipboardDocumentListIcon },
    { id: 'actividad', label: 'Historial', icon: ClockIcon },
]);
</script>

<template>
  <Head :title="`Radicado ${proceso.radicado || ''}`" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div class="min-w-0 flex-1">
          <div class="flex items-start gap-3">
            <Link :href="route('procesos.index')" class="mt-1 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 shadow-sm transition hover:border-indigo-300 hover:text-indigo-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:text-indigo-300">
              <ArrowLeftIcon class="h-4 w-4" />
            </Link>
            <div class="min-w-0">
              <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Radicado judicial</p>
              <div class="mt-1 flex flex-wrap items-center gap-2">
                <h2 class="truncate text-xl font-black leading-tight text-gray-950 dark:text-white sm:text-2xl">
                  {{ proceso.radicado || 'Sin radicado' }}
                </h2>
                <span class="rounded-md px-2.5 py-1 text-[10px] font-black uppercase ring-1" :class="estadoBadgeClass">
                  {{ proceso.estado || 'Sin estado' }}
                </span>
                <span
                  v-if="proceso.a_favor_de"
                  class="rounded-md px-2.5 py-1 text-[10px] font-black uppercase ring-1"
                  :class="proceso.a_favor_de === 'DEMANDANTE'
                    ? 'bg-blue-50 text-blue-700 ring-blue-600/20 dark:bg-blue-950/40 dark:text-blue-300 dark:ring-blue-400/20'
                    : 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-950/40 dark:text-rose-300 dark:ring-rose-400/20'"
                >
                  A favor del {{ proceso.a_favor_de }}
                </span>
              </div>
              <p class="mt-1 max-w-4xl truncate text-sm font-medium text-gray-600 dark:text-gray-300" :title="proceso.asunto">
                {{ proceso.asunto || 'Sin descripcion registrada' }}
              </p>
            </div>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 lg:justify-end">
          <button @click="copyLegalInfo" class="inline-flex items-center rounded-lg border border-gray-200 bg-white px-3 py-2 text-[10px] font-black uppercase tracking-wider text-gray-700 shadow-sm transition hover:border-gray-300 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
            <DocumentDuplicateIcon class="mr-1.5 h-3.5 w-3.5 text-gray-500" /> Copiar info
          </button>

          <Link v-if="!proceso.contrato && !isClosed" :href="route('honorarios.contratos.create', { proceso_id: proceso.id })" class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-2 text-[10px] font-black uppercase tracking-wider text-white shadow-sm transition hover:bg-indigo-700">
            <BriefcaseIcon class="mr-1.5 h-3.5 w-3.5" /> Contrato
          </Link>
          <Link v-else-if="proceso.contrato" :href="route('honorarios.contratos.show', proceso.contrato.id)" class="inline-flex items-center rounded-lg bg-emerald-600 px-3 py-2 text-[10px] font-black uppercase tracking-wider text-white shadow-sm transition hover:bg-emerald-700">
            <CheckCircleIcon class="mr-1.5 h-3.5 w-3.5" /> Contrato #{{ proceso.contrato.id }}
          </Link>

          <PrimaryButton @click="openEtapaModal" v-if="!isClosed" class="!py-2 !text-[10px]">
            <ArrowPathIcon class="mr-1.5 h-3.5 w-3.5" /> Etapa
          </PrimaryButton>
          <Link :href="route('procesos.edit', proceso.id)" v-if="!isClosed">
            <SecondaryButton class="!py-2 !text-[10px]">
              <PencilSquareIcon class="mr-1.5 h-3.5 w-3.5" /> Editar
            </SecondaryButton>
          </Link>

          <button v-if="!isClosed && ['admin', 'abogado', 'gestor'].includes(user.tipo_usuario)" @click="openCloseModal" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 text-rose-600 transition hover:bg-rose-100 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-300" title="Finalizar expediente">
            <ClockIcon class="h-4 w-4" />
          </button>

          <template v-if="user.tipo_usuario === 'admin'">
            <button v-if="isClosed" @click="openReopenModal" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-600 transition hover:bg-emerald-100 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-300" title="Reabrir expediente">
              <LockOpenIcon class="h-4 w-4" />
            </button>
            <button @click="askDelete" class="inline-flex items-center rounded-lg border border-red-700 bg-red-600 px-3 py-2 text-[10px] font-black uppercase tracking-wider text-white shadow-sm transition hover:bg-red-700" title="Eliminar registro">
              <TrashIcon class="mr-1.5 h-3.5 w-3.5" /> Eliminar
            </button>
          </template>
        </div>
      </div>
    </template>

    <div class="py-6">
      <div class="mx-auto max-w-[1600px] space-y-5 px-4 sm:px-6 lg:px-8">
        <section class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
          <article
            v-for="item in dashboardCards"
            :key="item.label"
            class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">{{ item.label }}</p>
                <span
                  v-if="item.valueClass"
                  class="mt-1 inline-flex max-w-full truncate rounded-md px-2 py-1 text-xs font-black"
                  :class="item.valueClass"
                  :title="item.value"
                >
                  {{ item.value }}
                </span>
                <p v-else class="mt-1 truncate text-sm font-black text-gray-950 dark:text-white" :title="item.value">
                  {{ item.value }}
                </p>
                <p class="mt-1 truncate text-xs font-semibold text-gray-600 dark:text-gray-300" :title="item.detail">{{ item.detail }}</p>
              </div>
              <div class="rounded-lg border p-2" :class="item.accentClass">
                <component :is="item.icon" class="h-5 w-5" />
              </div>
            </div>
          </article>
        </section>

        <div v-if="proceso.info_incompleta" class="rounded-lg border border-amber-200 bg-amber-50 p-4 shadow-sm dark:border-amber-900/60 dark:bg-amber-950/30">
          <div class="flex items-start gap-3">
            <ExclamationTriangleIcon class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-300" />
            <div>
              <p class="text-xs font-black uppercase tracking-widest text-amber-900 dark:text-amber-200">Informacion incompleta</p>
              <p class="mt-1 text-sm font-medium text-amber-800 dark:text-amber-100">Este expediente requiere completar partes, datos de contacto o informacion clave antes de continuar el seguimiento.</p>
            </div>
          </div>
        </div>

        <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
          <div class="border-b border-gray-200 px-3 dark:border-gray-700">
            <nav class="flex gap-2 overflow-x-auto py-3 scrollbar-hide" aria-label="Secciones del radicado">
              <button
                v-for="tab in tabItems"
                :key="tab.id"
                @click="setActiveTab(tab.id)"
                :class="activeTab === tab.id
                  ? 'bg-indigo-600 text-white shadow-sm'
                  : 'text-gray-600 hover:bg-gray-50 hover:text-gray-950 dark:text-gray-300 dark:hover:bg-gray-700/70 dark:hover:text-white'"
                class="inline-flex shrink-0 items-center rounded-lg px-3 py-2 text-[11px] font-black uppercase tracking-wider transition"
              >
                <component :is="tab.icon" class="mr-2 h-4 w-4" :class="activeTab === tab.id ? 'text-white' : 'text-gray-400'" />
                {{ tab.label }}
                <span
                  v-if="tab.badge"
                  class="ml-2 rounded-full px-2 py-0.5 text-[9px] font-black"
                  :class="activeTab === tab.id ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-200'"
                >
                  {{ tab.badge }}
                </span>
              </button>
            </nav>
          </div>

          <div class="p-4 sm:p-5 lg:p-6">
            <ResumenTab v-show="activeTab === 'resumen'" :proceso="proceso" :formatDate="formatDate">
              <template #notificaciones>
                <div class="rounded-lg border border-indigo-200 bg-indigo-50 p-5 shadow-sm dark:border-indigo-900/60 dark:bg-indigo-950/30">
                  <div class="mb-4 flex items-center gap-2">
                    <div class="rounded-lg bg-white p-2 text-indigo-600 shadow-sm dark:bg-gray-900 dark:text-indigo-300">
                      <BellIcon class="h-4 w-4" />
                    </div>
                    <div>
                      <p class="text-[10px] font-bold uppercase tracking-widest text-indigo-700 dark:text-indigo-300">Seguimiento</p>
                      <h3 class="text-sm font-black text-gray-950 dark:text-white">Agendar recordatorio</h3>
                    </div>
                  </div>

                  <form @submit.prevent="submitNotification" class="space-y-3">
                    <DateTimePicker v-model="notifForm.fecha_programada" class="!rounded-lg !border-indigo-200 !bg-white text-xs dark:!border-indigo-900/60 dark:!bg-gray-900" />
                    <Textarea v-model="notifForm.mensaje" class="!rounded-lg !border-indigo-200 !bg-white text-sm text-gray-900 dark:!border-indigo-900/60 dark:!bg-gray-900 dark:text-white" rows="3" placeholder="Nota para el seguimiento..." required />
                    <PrimaryButton :disabled="notifForm.processing" class="w-full justify-center !rounded-lg !py-2.5 !text-[10px] !font-black !uppercase !tracking-widest">
                      <PlusIcon class="mr-1.5 h-3.5 w-3.5" /> Agendar
                    </PrimaryButton>
                  </form>
                </div>
              </template>
            </ResumenTab>

            <ExpedienteIntegrityPanel v-show="activeTab === 'integridad'" :summary="proceso.integridad_resumen" :edit-href="route('procesos.edit', proceso.id)" @go-tab="setActiveTab" />

            <PartesTab v-show="activeTab === 'partes'" :proceso="proceso" />

            <DocumentosTab v-show="activeTab === 'documentos'" ref="documentosTabRef" :proceso="proceso" :files="files" :isClosed="isClosed" :formatDate="formatDate" @upload="handleUpload" @delete="askDeleteDoc" />

            <ActuacionesTab v-show="activeTab === 'actuaciones'" :proceso="proceso" :actuaciones="actuaciones" :isClosed="isClosed" :fmtDateTime="fmtDateTime" :fmtDateSimple="fmtDateSimple" @save="handleSaveActuacion" @edit="abrirModalEditar" @delete="eliminarActuacion" />

            <ActividadTab v-show="activeTab === 'actividad'" :proceso="proceso" :auditoria="auditoria" />
          </div>
        </section>
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

    <!-- MODAL CAMBIO DE ETAPA (Rediseñado para mejor UI/UX) -->
    <Modal :show="showEtapaModal" @close="showEtapaModal = false" centered>
        <div class="p-8 overflow-visible">
            <!-- Encabezado con identidad visual -->
            <div class="flex items-center gap-3 mb-6 text-indigo-600">
                <div class="p-3 bg-indigo-50 rounded-2xl dark:bg-indigo-950/40">
                    <ArrowPathIcon class="h-8 w-8" />
                </div>
                <div>
                    <h2 class="text-xl font-black uppercase tracking-tight leading-none text-gray-900 dark:text-white">Avanzar<br/>Etapa</h2>
                </div>
            </div>

            <div class="space-y-6 overflow-visible">
                <div class="relative z-50">
                    <InputLabel for="nueva_etapa" value="Nueva Etapa Procesal *" class="text-[10px] font-black uppercase text-gray-500 mb-1 tracking-widest" />
                    <!-- Se usa teleport para evitar que el contenedor del modal corte el dropdown -->
                    <Dropdown align="left" width="full" teleport>
                        <template #trigger>
                            <button type="button" class="flex w-full items-center justify-between gap-2 rounded-xl border border-gray-200 bg-gray-50 px-4 py-4 text-sm font-bold shadow-sm hover:border-indigo-500 transition-all cursor-pointer dark:border-gray-700 dark:bg-gray-900/50 dark:text-white">
                                <span>{{ etapaForm.etapa_procesal_id ? etapas.find(e => e.id === etapaForm.etapa_procesal_id)?.nombre : 'Seleccione una etapa...' }}</span>
                                <ChevronDownIcon class="h-4 w-4 text-gray-400" />
                            </button>
                        </template>
                        <template #content>
                            <div class="p-2 border-b border-gray-100 sticky top-0 bg-white z-10 dark:bg-gray-800 dark:border-gray-700">
                                <TextInput v-model="searchEtapa" placeholder="Filtrar etapas..." class="w-full text-xs h-9 !rounded-lg" @click.stop />
                            </div>
                            <div class="py-1 max-h-60 overflow-y-auto">
                                <!-- Se eliminó la visualización del (riesgo) al lado del nombre por solicitud de usuario -->
                                <button v-for="etapa in filteredEtapas" :key="etapa.id" @click="etapaForm.etapa_procesal_id = etapa.id" class="block w-full text-left px-4 py-3 text-xs font-bold text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700" :class="{ 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300': etapaForm.etapa_procesal_id === etapa.id }">
                                    {{ etapa.nombre }}
                                </button>
                            </div>
                        </template>
                    </Dropdown>
                </div>
                <div>
                    <InputLabel for="obs_etapa" value="Observación del Cambio" class="text-[10px] font-black uppercase text-gray-500 mb-1 tracking-widest" />
                    <Textarea id="obs_etapa" v-model="etapaForm.observacion" class="w-full rounded-xl border-gray-200 bg-gray-50 text-sm shadow-sm focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900/50 dark:text-white" rows="3" placeholder="Contexto sobre el avance o decisión tomada..." />
                </div>
                <div class="flex flex-col gap-3 pt-4 border-t border-gray-100 dark:border-gray-700">
                    <PrimaryButton @click="submitEtapa" :disabled="etapaForm.processing || !etapaForm.etapa_procesal_id" class="w-full justify-center !py-4 !text-xs !font-black !uppercase !tracking-widest shadow-lg shadow-indigo-100 dark:shadow-none">
                        Actualizar Etapa del Proceso
                    </PrimaryButton>
                    <SecondaryButton @click="showEtapaModal = false" class="w-full justify-center !py-3 !text-[10px] !font-bold">Cancelar</SecondaryButton>
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
