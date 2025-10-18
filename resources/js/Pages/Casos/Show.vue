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
import Checkbox from '@/Components/Checkbox.vue';
import Textarea from '@/Components/Textarea.vue';
import CumplimientoLegal from '@/Components/CumplimientoLegal.vue';
import HistorialAuditoria from '@/Components/HistorialAuditoria.vue';
import { Head, Link, useForm, usePage, useRemember } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

// Iconos
import {
  InformationCircleIcon,
  FolderOpenIcon,
  CreditCardIcon,
  ClipboardDocumentListIcon,
  ClockIcon,
  LockClosedIcon,
  DocumentDuplicateIcon,
  ArrowDownTrayIcon,
  BanknotesIcon,
  BellAlertIcon,
  UserCircleIcon,
  ScaleIcon,
  TrashIcon,
  CameraIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
  caso: { type: Object, required: true },
  can: { type: Object, required: true },
  plantillas: { type: Array, default: () => [] },
});

const page = usePage();
const activeTab = useRemember('resumen', 'casoShowTab:' + props.caso.id);

// --------- Modales y formularios ---------
const confirmingDocumentUpload = ref(false);
const docFormProgress = ref(null);
const docForm = useForm({
  tipo_documento: 'pagaré',
  fecha_carga: new Date().toISOString().slice(0, 10),
  archivo: null,
});
const openUploadModal = () => {
  confirmingDocumentUpload.value = true;
};
const closeUploadModal = () => {
  confirmingDocumentUpload.value = false;
  docFormProgress.value = null;
  docForm.reset({
    tipo_documento: 'pagaré',
    fecha_carga: new Date().toISOString().slice(0, 10),
    archivo: null,
  });
};
const MAX_5MB = 5 * 1024 * 1024;
const submitDocument = () => {
  if (docForm.archivo && docForm.archivo.size > MAX_5MB) {
    docForm.setError('archivo', 'Tamaño máximo permitido: 5MB.');
    return;
  }
  docForm.post(route('casos.documentos.store', props.caso.id), {
    preserveScroll: true,
    forceFormData: true,
    onProgress: (e) => (docFormProgress.value = e?.percentage ?? null),
    onSuccess: () => closeUploadModal(),
    onFinish: () => (docFormProgress.value = null),
  });
};

const confirmingDocumentDeletion = ref(false);
const documentToDelete = ref(null);
const confirmDocumentDeletion = (documento) => {
  documentToDelete.value = documento;
  confirmingDocumentDeletion.value = true;
};
const closeDeleteModal = () => {
  confirmingDocumentDeletion.value = false;
  documentToDelete.value = null;
};
const deleteDocument = () => {
  useForm({}).delete(route('documentos-caso.destroy', documentToDelete.value.id), {
    preserveScroll: true,
    onSuccess: () => closeDeleteModal(),
  });
};

const mostrandoModalGenerar = ref(false);
const generarDocForm = useForm({
  plantilla_id: null,
  caso_id: props.caso.id,
  es_confidencial: false,
  observaciones: '',
});
const abrirModalGenerar = () => {
  generarDocForm.reset();
  generarDocForm.caso_id = props.caso.id;
  mostrandoModalGenerar.value = true;
};
const cerrarModalGenerar = () => {
  mostrandoModalGenerar.value = false;
};
const submitGenerarDocumento = () => {
  generarDocForm.post(route('documentos.generar'), {
    preserveScroll: true,
    onSuccess: () => cerrarModalGenerar(),
  });
};

const mostrandoModalPago = ref(false);
const pagoFormProgress = ref(null);
const pagoForm = useForm({
  monto_pagado: '',
  fecha_pago: new Date().toISOString().slice(0, 10),
  motivo_pago: 'parcial',
  comprobante_pago: null,
});
const abrirModalPago = () => {
  pagoForm.reset({
    fecha_pago: new Date().toISOString().slice(0, 10),
    motivo_pago: 'parcial',
    comprobante_pago: null,
  });
  pagoFormProgress.value = null;
  mostrandoModalPago.value = true;
};
const cerrarModalPago = () => {
  mostrandoModalPago.value = false;
  pagoFormProgress.value = null;
};

const submitPago = () => {
  if (pagoForm.comprobante_pago && pagoForm.comprobante_pago.size > MAX_5MB) {
    pagoForm.setError('comprobante_pago', 'Tamaño máximo permitido: 5MB.');
    return;
  }
  // Normaliza el monto a "###.##" para el backend
  pagoForm.monto_pagado = parseMoney(pagoForm.monto_pagado).toFixed(2);

  pagoForm.post(route('casos.pagos.store', props.caso.id), {
    preserveScroll: true,
    forceFormData: true,
    onProgress: (e) => (pagoFormProgress.value = e?.percentage ?? null),
    onSuccess: () => cerrarModalPago(),
    onFinish: () => (pagoFormProgress.value = null),
  });
};

const mostrandoModalAlerta = ref(false);
const alertaForm = useForm({
  mensaje: '',
  programado_para: null,
  prioridad: 'media', // default
});
const abrirModalAlerta = () => {
  alertaForm.reset();
  mostrandoModalAlerta.value = true;
};
const cerrarModalAlerta = () => {
  mostrandoModalAlerta.value = false;
};
const submitAlerta = () => {
  alertaForm.post(route('casos.notificaciones.store', props.caso.id), {
    preserveScroll: true,
    onSuccess: () => cerrarModalAlerta(),
  });
};

// --------- Utilidades de formato robustas ---------
const parseDate = (s) => {
  if (!s) return null;
  if (/^\d{4}-\d{2}-\d{2}$/.test(s)) {
    const [y, m, d] = s.split('-').map(Number);
    return new Date(y, m - 1, d);
  }
  return new Date(s);
};
const formatDate = (s) =>
  parseDate(s)?.toLocaleDateString('es-CO', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  }) || 'No especificada';
const formatDateTime = (s) =>
  parseDate(s)?.toLocaleString('es-CO', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }) || 'N/A';

const statusColorClasses = {
  red: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
  yellow: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
  green: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
  blue: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
  gray: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
};

const puedeEditar = computed(() => props.can.update && !props.caso.bloqueado);

const docsGenerados = computed(() =>
  [...(props.caso.documentos_generados || [])].sort(
    (a, b) => new Date(b.created_at) - new Date(a.created_at)
  )
);
const adjuntos = computed(() =>
  [...(props.caso.documentos || [])].sort(
    (a, b) => new Date(b.fecha_carga) - new Date(a.fecha_carga)
  )
);

const parseMoney = (input) => {
  let s = String(input ?? '').trim();
  if (!s) return 0;
  s = s.replace(/\s/g, '');
  const hasComma = s.includes(',');
  const hasDot = s.includes('.');

  if (hasComma && hasDot) {
    // Decide separador decimal por el último símbolo
    const decimalSep = s.lastIndexOf(',') > s.lastIndexOf('.') ? ',' : '.';
    const thousandSep = decimalSep === ',' ? '.' : ',';
    s = s.replace(new RegExp('\\' + thousandSep, 'g'), '');
    if (decimalSep === ',') s = s.replace(',', '.');
  } else if (hasComma) {
    // Coma como decimal
    s = s.replace(/\./g, '');
    s = s.replace(',', '.');
  } else {
    // Punto como decimal o sin decimales
    s = s.replace(/,/g, '');
  }

  const n = Number(s);
  return Number.isFinite(n) ? n : 0;
};

const formatCurrency = (value) =>
  new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', minimumFractionDigits: 0 })
    .format(parseMoney(value));

const totalPagado = computed(() =>
  (props.caso.pagos || []).reduce((s, p) => s + parseMoney(p.monto_pagado), 0)
);

const saldoPendiente = computed(() =>
  Math.max(0, parseMoney(props.caso.monto_total) - totalPagado.value)
);

</script>

<template>
  <Head :title="'Ficha del Caso #' + caso.id" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4">
        <div>
          <Link
            :href="route('casos.index')"
            class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 flex items-center mb-1"
          >
            &larr; Volver a Gestión de Casos
          </Link>
          <h2 class="font-semibold text-2xl text-gray-800 dark:text-gray-200 leading-tight">
            Ficha del Caso <span class="text-indigo-500">#{{ caso.id }}</span>
            <span
              v-if="caso.bloqueado"
              class="ml-2 px-2 py-0.5 text-xs rounded bg-red-100 text-red-700 align-middle"
            >Bloqueado</span>
          </h2>
        </div>
        <div class="flex items-center space-x-2 flex-shrink-0">
          <Link
            v-if="puedeEditar"
            :href="route('casos.clonar', caso.id)"
            class="inline-flex items-center px-4 py-2 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none disabled:opacity-25 transition ease-in-out duration-150"
          >
            Clonar
          </Link>
          <Link
            v-if="puedeEditar"
            :href="route('casos.edit', caso.id)"
            class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
          >
            Editar Caso
          </Link>
        </div>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div
          v-if="page.props.flash.success"
          class="mb-6 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg shadow-md"
          role="status"
          aria-live="polite"
        >
          <p class="font-bold">Éxito</p>
          <p>{{ page.props.flash.success }}</p>
        </div>
        <div
          v-if="page.props.flash.error"
          class="mb-6 bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg shadow-md"
          role="alert"
        >
          <p class="font-bold">Error</p>
          <p>{{ page.props.flash.error }}</p>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow-lg rounded-xl p-6 mb-8">
          <div class="flex flex-col md:flex-row justify-between md:items-center gap-4">
            <div class="flex-grow">
              <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                Deudor Principal
              </p>
              <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white">
                {{ caso.deudor ? caso.deudor.nombre_completo : 'No Asignado' }}
              </h1>
              <p class="text-sm text-gray-600 dark:text-gray-300">
                {{ caso.cooperativa ? caso.cooperativa.nombre : 'N/A' }}
              </p>
            </div>
            <div class="flex items-center justify-end flex-wrap gap-4 mt-4 md:mt-0">
              <div class="text-right">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Monto Total</p>
                <p class="text-xl font-semibold text-gray-800 dark:text-gray-100">
                  {{ formatCurrency(caso.monto_total) }}
                </p>
              </div>
              <div
                v-if="caso.dias_en_mora > 0"
                class="text-right bg-red-50 dark:bg-red-900/50 p-3 rounded-lg"
              >
                <p class="text-sm font-medium text-red-600 dark:text-red-300">Días en Mora</p>
                <p class="text-xl font-bold text-red-700 dark:text-red-200">{{ caso.dias_en_mora }}</p>
              </div>
              <div class="text-right" v-if="caso.semaforo">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Estado</p>
                <span
                  class="px-3 py-1.5 inline-flex text-sm leading-5 font-semibold rounded-full"
                  :class="statusColorClasses[caso.semaforo.color] || statusColorClasses['gray']"
                >{{ caso.semaforo.text }}</span>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md">
          <div class="border-b border-gray-200 dark:border-gray-700">
            <nav class="-mb-px flex space-x-6 overflow-x-auto px-6" aria-label="Tabs">
              <button
                @click="activeTab = 'resumen'"
                :class="[
                  activeTab === 'resumen'
                    ? 'border-indigo-500 text-indigo-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:hover:text-gray-200 dark:hover:border-gray-500',
                ]"
                class="flex items-center whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200"
              >
                <InformationCircleIcon class="h-5 w-5 mr-2" /> Resumen
              </button>
              <button
                @click="activeTab = 'documentos'"
                :class="[
                  activeTab === 'documentos'
                    ? 'border-indigo-500 text-indigo-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:hover:text-gray-200 dark:hover:border-gray-500',
                ]"
                class="flex items-center whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200"
              >
                <FolderOpenIcon class="h-5 w-5 mr-2" /> Documentos
              </button>
              <button
                @click="activeTab = 'financiero'"
                :class="[
                  activeTab === 'financiero'
                    ? 'border-indigo-500 text-indigo-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:hover:text-gray-200 dark:hover:border-gray-500',
                ]"
                class="flex items-center whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200"
              >
                <CreditCardIcon class="h-5 w-5 mr-2" /> Financiero
              </button>
              <button
                @click="activeTab = 'actividad'"
                :class="[
                  activeTab === 'actividad'
                    ? 'border-indigo-500 text-indigo-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:hover:text-gray-200 dark:hover:border-gray-500',
                ]"
                class="flex items-center whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-200"
              >
                <ClipboardDocumentListIcon class="h-5 w-5 mr-2" /> Actividad y Logs
              </button>
            </nav>
          </div>

          <div class="p-6">
            <!-- ====== RESUMEN ====== -->
            <div v-show="activeTab === 'resumen'" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
              <div class="lg:col-span-2 space-y-6">
                <div class="bg-gray-50 dark:bg-gray-900/50 p-6 rounded-lg">
                  <h3 class="text-lg font-bold mb-4 flex items-center">
                    <ScaleIcon class="h-6 w-6 mr-2 text-gray-500" />Detalles del Proceso
                  </h3>
                  <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4">
                    <div>
                      <p class="text-sm text-gray-500">Referencia del Crédito</p>
                      <p class="font-semibold">{{ caso.referencia_credito || 'N/A' }}</p>
                    </div>
                    <div>
                      <p class="text-sm text-gray-500">Tipo de Proceso</p>
                      <p class="font-semibold capitalize">{{ caso.tipo_proceso }}</p>
                    </div>
                    <div>
                      <p class="text-sm text-gray-500">Subtipo de Proceso</p>
                      <p class="font-semibold">{{ caso.subtipo_proceso || 'No especificado' }}</p>
                    </div>
                    <div>
                      <p class="text-sm text-gray-500">Etapa Procesal</p>
                      <p class="font-semibold">{{ caso.etapa_procesal || 'No especificada' }}</p>
                    </div>
                    <div class="md:col-span-2">
                      <p class="text-sm text-gray-500">Juzgado</p>
                      <p class="font-semibold">{{ caso.juzgado ? caso.juzgado.nombre : 'No especificado' }}</p>
                    </div>
                    <div>
                      <p class="text-sm text-gray-500">Fecha de Apertura</p>
                      <p class="font-semibold">{{ formatDate(caso.fecha_apertura) }}</p>
                    </div>
                    <div>
                      <p class="text-sm text-gray-500">Fecha de Vencimiento</p>
                      <p class="font-semibold">{{ formatDate(caso.fecha_vencimiento) }}</p>
                    </div>
                    <div class="md:col-span-2">
                      <p class="text-sm text-gray-500">Tipo de Garantía</p>
                      <p class="font-semibold capitalize">{{ caso.tipo_garantia_asociada }}</p>
                    </div>
                  </dl>
                </div>

                <div class="bg-gray-50 dark:bg-gray-900/50 p-6 rounded-lg">
                  <CumplimientoLegal :validaciones="caso.validaciones_legales" />
                </div>
              </div>

              <div class="lg:col-span-1 space-y-6">
                <div class="bg-gray-50 dark:bg-gray-900/50 p-6 rounded-lg">
                  <h3 class="text-lg font-bold mb-4">Partes Involucradas</h3>
                  <ul class="space-y-4">
                    <li class="flex items-center">
                      <UserCircleIcon class="h-6 w-6 mr-3 text-indigo-500" />
                      <div class="text-sm">
                        <span class="font-medium text-gray-900 dark:text-white">Deudor:</span>
                        {{ caso.deudor ? caso.deudor.nombre_completo : 'N/A' }}
                      </div>
                    </li>
                    <li class="flex items-center">
                      <UserCircleIcon class="h-6 w-6 mr-3 text-gray-400" />
                      <div class="text-sm">
                        <span class="font-medium text-gray-900 dark:text-white">Codeudor 1:</span>
                        {{ caso.codeudor1 ? caso.codeudor1.nombre_completo : 'No aplica' }}
                      </div>
                    </li>
                    <li class="flex items-center">
                      <UserCircleIcon class="h-6 w-6 mr-3 text-gray-400" />
                      <div class="text-sm">
                        <span class="font-medium text-gray-900 dark:text-white">Codeudor 2:</span>
                        {{ caso.codeudor2 ? caso.codeudor2.nombre_completo : 'No aplica' }}
                      </div>
                    </li>
                    <li class="flex items-center">
                      <UserCircleIcon class="h-6 w-6 mr-3 text-gray-400" />
                      <div class="text-sm">
                        <span class="font-medium text-gray-900 dark:text-white">Abogado:</span>
                        {{ caso.user ? caso.user.name : 'N/A' }}
                      </div>
                    </li>
                  </ul>
                </div>

                <PrimaryButton @click="abrirModalAlerta" class="w-full justify-center">
                  <BellAlertIcon class="h-5 w-5 mr-2" />Programar Alerta
                </PrimaryButton>
              </div>
            </div>

            <!-- ====== DOCUMENTOS ====== -->
            <div v-show="activeTab === 'documentos'" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
              <div class="bg-gray-50 dark:bg-gray-900/50 p-6 rounded-lg">
                <div class="flex justify-between items-center mb-4">
                  <h3 class="text-lg font-bold flex items-center">
                    <DocumentDuplicateIcon class="h-6 w-6 mr-2 text-indigo-500" />Documentos Generados
                  </h3>
                  <PrimaryButton v-if="puedeEditar" @click="abrirModalGenerar" class="!text-xs">
                    Generar +
                  </PrimaryButton>
                </div>

                <div
                  v-if="!docsGenerados.length"
                  class="text-center py-6 text-gray-500 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg"
                >
                  No hay documentos generados.
                </div>

                <ul v-else class="space-y-2">
                  <li
                    v-for="doc in docsGenerados"
                    :key="doc.id"
                    class="p-3 bg-white dark:bg-gray-800 rounded-md shadow-sm flex items-center justify-between"
                  >
                    <div class="flex items-center truncate">
                      <LockClosedIcon
                        v-if="doc.es_confidencial"
                        class="h-5 w-5 text-yellow-500 mr-3 flex-shrink-0"
                        title="Confidencial"
                      />
                      <div class="truncate">
                        <p
                          class="text-sm font-medium text-gray-900 dark:text-white truncate"
                          :title="`${doc.nombre_base}.docx`"
                        >
                          {{ doc.nombre_base }}.docx
                        </p>
                        <p class="text-xs text-gray-500">
                          Generado por {{ doc.usuario?.name || 'N/A' }} (v{{ doc.version_plantilla }})
                        </p>
                      </div>
                    </div>
                    <div class="flex items-center space-x-2 flex-shrink-0 ml-4">
                      <a
                        :href="route('documentos.descargar.docx', doc.id)"
                        class="text-blue-600 hover:text-blue-800 p-1.5 rounded-full hover:bg-blue-100 dark:hover:bg-blue-900/50"
                        title="Descargar .docx"
                        aria-label="Descargar DOCX"
                      >
                        <ArrowDownTrayIcon class="h-5 w-5" />
                      </a>
                      <a
                        :href="route('documentos.descargar.pdf', doc.id)"
                        class="text-red-600 hover:text-red-800 p-1.5 rounded-full hover:bg-red-100 dark:hover:bg-red-900/50"
                        title="Descargar .pdf"
                        aria-label="Descargar PDF"
                      >
                        <ArrowDownTrayIcon class="h-5 w-5" />
                      </a>
                    </div>
                  </li>
                </ul>
              </div>

              <div class="bg-gray-50 dark:bg-gray-900/50 p-6 rounded-lg">
                <div class="flex justify-between items-center mb-4">
                  <h3 class="text-lg font-bold">Documentos Adjuntos</h3>
                  <PrimaryButton v-if="puedeEditar" @click="openUploadModal" class="!text-xs">
                    Subir +
                  </PrimaryButton>
                </div>

                <div
                  v-if="!adjuntos.length"
                  class="text-center py-6 text-gray-500 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg"
                >
                  No hay documentos adjuntos.
                </div>

                <ul v-else class="space-y-2">
                  <li
                    v-for="doc in adjuntos"
                    :key="doc.id"
                    class="p-3 bg-white dark:bg-gray-800 rounded-md shadow-sm flex items-center justify-between"
                  >
                    <div>
                      <p class="text-sm font-medium text-gray-900 dark:text-white capitalize">
                        {{ doc.tipo_documento }}
                      </p>
                      <p class="text-xs text-gray-500">Subido el {{ formatDate(doc.fecha_carga) }}</p>
                    </div>
                    <div class="flex items-center space-x-2">
                      <a
                        :href="route('documentos-caso.view', doc.id)"
                        target="_blank"
                        class="inline-flex items-center px-3 py-1 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none disabled:opacity-25 transition ease-in-out duration-150"
                      >
                        Ver
                      </a>
                      <DangerButton
                        v-if="puedeEditar"
                        @click="confirmDocumentDeletion(doc)"
                        class="!py-2 !text-xs"
                        aria-label="Eliminar adjunto"
                      >
                        <TrashIcon class="h-4 w-4" />
                      </DangerButton>
                    </div>
                  </li>
                </ul>
              </div>
            </div>

            <!-- ====== FINANCIERO ====== -->
            <div v-show="activeTab === 'financiero'" class="space-y-6">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-gray-50 dark:bg-gray-900/50 p-4 rounded-lg text-center">
                  <p class="text-sm text-gray-500">Monto Total</p>
                  <p class="text-2xl font-bold">{{ formatCurrency(caso.monto_total) }}</p>
                </div>
                <div class="bg-green-50 dark:bg-green-900/50 p-4 rounded-lg text-center">
                  <p class="text-sm text-green-700 dark:text-green-300">Total Pagado</p>
                  <p class="text-2xl font-bold text-green-800 dark:text-green-200">
                    {{ formatCurrency(totalPagado) }}
                  </p>
                </div>
                <div class="bg-yellow-50 dark:bg-yellow-900/50 p-4 rounded-lg text-center">
                  <p class="text-sm text-yellow-700 dark:text-yellow-300">Saldo Pendiente</p>
                  <p class="text-2xl font-bold text-yellow-800 dark:text-yellow-200">
                    {{ formatCurrency(saldoPendiente) }}
                  </p>
                </div>
              </div>

              <div class="bg-gray-50 dark:bg-gray-900/50 p-6 rounded-lg">
                <div class="flex justify-between items-center mb-4">
                  <h3 class="text-lg font-bold flex items-center">
                    <BanknotesIcon class="h-6 w-6 mr-2 text-green-500" />Historial de Pagos
                  </h3>
                  <PrimaryButton v-if="puedeEditar" @click="abrirModalPago">Registrar Pago +</PrimaryButton>
                </div>

                <div
                  v-if="!caso.pagos || !caso.pagos.length"
                  class="text-center py-6 text-gray-500 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg"
                >
                  No hay pagos registrados para este caso.
                </div>

                <ul v-else class="divide-y divide-gray-200 dark:divide-gray-700">
                  <li
                    v-for="pago in caso.pagos"
                    :key="pago.id"
                    class="py-3 flex flex-wrap items-center justify-between gap-2"
                  >
                    <div class="flex-grow">
                      <p class="text-sm font-medium text-green-600 dark:text-green-400">
                        {{ formatCurrency(pago.monto_pagado) }}
                      </p>
                      <p class="text-xs text-gray-500 capitalize">
                        Registrado el {{ formatDate(pago.fecha_pago) }} ({{ pago.motivo_pago }})
                      </p>
                      <p class="text-xs text-gray-500">Por: {{ pago.usuario?.name || 'N/A' }}</p>
                    </div>

                    <a
                      v-if="pago.comprobante_url"
                      :href="pago.comprobante_url"
                      target="_blank"
                      class="inline-flex items-center px-3 py-1 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none disabled:opacity-25 transition ease-in-out duration-150"
                    >
                      Ver Comprobante
                    </a>
                  </li>
                </ul>
              </div>
            </div>

            <!-- ====== ACTIVIDAD ====== -->
            <div v-show="activeTab === 'actividad'" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
              <div class="bg-gray-50 dark:bg-gray-900/50 p-6 rounded-lg">
                <h3 class="text-lg font-bold mb-4 flex items-center">
                  <ClockIcon class="h-6 w-6 mr-2 text-gray-500" />Bitácora de Actividad
                </h3>
                <div
                  class="relative border-l-2 border-gray-200 dark:border-gray-700 ml-3 max-h-96 overflow-y-auto pr-2"
                >
                  <div v-if="!caso.bitacoras || !caso.bitacoras.length" class="pl-8 pb-4">
                    <p class="text-sm text-gray-500">No hay actividades.</p>
                  </div>
                  <ol v-else class="space-y-6">
                    <li v-for="item in caso.bitacoras" :key="item.id" class="relative pl-8">
                      <div
                        class="absolute -left-[7px] top-1 h-3 w-3 rounded-full bg-indigo-500 ring-4 ring-gray-50 dark:ring-gray-900/50"
                      ></div>
                      <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                        {{ item.accion }}
                      </p>
                      <p v-if="item.comentario" class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        {{ item.comentario }}
                      </p>
                      <p class="text-xs text-gray-400 dark:text-gray-500 mt-1.5">
                        Por <span class="font-medium">{{ item.user ? item.user.name : 'N/A' }}</span>
                      </p>
                      <p class="text-xs text-gray-400 dark:text-gray-500">
                        {{ formatDateTime(item.created_at) }}
                      </p>
                    </li>
                  </ol>
                </div>
              </div>

              <div class="bg-gray-50 dark:bg-gray-900/50 p-6 rounded-lg">
                <HistorialAuditoria :eventos="caso.auditoria" />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ====== MODALES ====== -->

    <!-- Subir adjunto -->
    <Modal :show="confirmingDocumentUpload" @close="closeUploadModal">
      <div class="p-6">
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
          Subir Nuevo Documento de Prueba
        </h2>

        <form @submit.prevent="submitDocument" class="mt-6 space-y-6">
          <div>
            <InputLabel for="tipo_documento_upload" value="Tipo de Documento" />
            <select
              v-model="docForm.tipo_documento"
              id="tipo_documento_upload"
              class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm"
            >
              <option>pagaré</option>
              <option>carta instrucciones</option>
              <option>certificación saldo</option>
              <option>libranza</option>
              <option>cédula deudor</option>
              <option>cédula codeudor</option>
              <option>otros</option>
            </select>
            <InputError :message="docForm.errors.tipo_documento" class="mt-2" />
          </div>

          <div>
            <InputLabel for="fecha_carga" value="Fecha de Carga" />
            <TextInput
              v-model="docForm.fecha_carga"
              id="fecha_carga"
              type="date"
              class="mt-1 block w-full"
              required
            />
            <InputError :message="docForm.errors.fecha_carga" class="mt-2" />
          </div>

          <div>
            <InputLabel for="archivo_upload" value="Archivo (Máx 5MB)" />
            <input
              id="archivo_upload"
              type="file"
              @input="docForm.archivo = $event.target.files[0]"
              class="mt-1 block w-full text-sm text-gray-500
                     file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0
                     file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
              accept=".pdf,.doc,.docx,image/*"
            />
            <InputError :message="docForm.errors.archivo" class="mt-2" />
            <div v-if="docFormProgress !== null" class="mt-2 w-full bg-gray-200 rounded h-2">
              <div class="h-2 rounded bg-indigo-600" :style="{ width: docFormProgress + '%' }"></div>
            </div>
          </div>

          <div class="mt-6 flex justify-end">
            <SecondaryButton @click="closeUploadModal">Cancelar</SecondaryButton>
            <PrimaryButton class="ms-3" :class="{ 'opacity-25': docForm.processing }" :disabled="docForm.processing">
              Guardar Documento
            </PrimaryButton>
          </div>
        </form>
      </div>
    </Modal>

    <!-- Confirmar eliminación -->
    <Modal :show="confirmingDocumentDeletion" @close="closeDeleteModal">
      <div class="p-6">
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">¿Eliminar Documento de Prueba?</h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400" v-if="documentToDelete">
          ¿Estás seguro de que quieres eliminar permanentemente el documento:
          <span class="font-medium">{{ documentToDelete.tipo_documento }}</span>?
        </p>
        <div class="mt-6 flex justify-end">
          <SecondaryButton @click="closeDeleteModal">Cancelar</SecondaryButton>
          <DangerButton class="ms-3" @click="deleteDocument">Eliminar Documento</DangerButton>
        </div>
      </div>
    </Modal>

    <!-- Generar desde plantilla -->
    <Modal :show="mostrandoModalGenerar" @close="cerrarModalGenerar">
      <div class="p-6">
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Generar Documento desde Plantilla</h2>
        <form @submit.prevent="submitGenerarDocumento" class="mt-6 space-y-6">
          <div>
            <InputLabel for="plantilla_id_generate" value="Plantilla a utilizar" />
            <SelectInput
              id="plantilla_id_generate"
              class="mt-1 block w-full"
              v-model="generarDocForm.plantilla_id"
              required
            >
              <option disabled :value="null">-- Seleccione una plantilla --</option>
              <option v-for="plantilla in plantillas" :key="plantilla.id" :value="plantilla.id">
                {{ plantilla.nombre }} (v{{ plantilla.version }})
              </option>
            </SelectInput>
            <InputError class="mt-2" :message="generarDocForm.errors.plantilla_id" />
          </div>

          <div>
            <InputLabel for="observaciones_generate" value="Observaciones (Opcional)" />
            <Textarea
              id="observaciones_generate"
              class="mt-1 block w-full"
              v-model="generarDocForm.observaciones"
              rows="3"
            />
            <InputError class="mt-2" :message="generarDocForm.errors.observaciones" />
          </div>

          <div class="flex items-start">
            <div class="flex items-center h-5">
              <Checkbox id="es_confidencial_generate" v-model:checked="generarDocForm.es_confidencial" />
            </div>
            <div class="ml-3 text-sm">
              <InputLabel for="es_confidencial_generate" value="Documento Confidencial" />
              <p class="text-xs text-gray-500 dark:text-gray-400">
                Si se marca, no será visible para usuarios con rol de cliente.
              </p>
            </div>
          </div>

          <div class="mt-6 flex justify-end">
            <SecondaryButton @click="cerrarModalGenerar">Cancelar</SecondaryButton>
            <PrimaryButton
              class="ms-3"
              :class="{ 'opacity-25': generarDocForm.processing }"
              :disabled="generarDocForm.processing"
            >
              Generar Documento
            </PrimaryButton>
          </div>
        </form>
      </div>
    </Modal>

    <!-- Registrar pago -->
    <Modal :show="mostrandoModalPago" @close="cerrarModalPago">
      <div class="p-6">
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Registrar Nuevo Pago</h2>
        <form @submit.prevent="submitPago" class="mt-6 space-y-6">
          <div>
            <InputLabel for="monto_pagado" value="Monto Pagado *" />
            <TextInput
              v-model="pagoForm.monto_pagado"
              id="monto_pagado"
              type="number"
              step="0.01"
              class="mt-1 block w-full"
              required
            />
            <InputError :message="pagoForm.errors.monto_pagado" class="mt-2" />
          </div>

          <div>
            <InputLabel for="fecha_pago" value="Fecha del Pago *" />
            <TextInput v-model="pagoForm.fecha_pago" id="fecha_pago" type="date" class="mt-1 block w-full" required />
            <InputError :message="pagoForm.errors.fecha_pago" class="mt-2" />
          </div>

          <div>
            <InputLabel for="motivo_pago" value="Motivo del Pago *" />
            <SelectInput v-model="pagoForm.motivo_pago" id="motivo_pago" class="mt-1 block w-full" required>
              <option value="parcial">Pago Parcial</option>
              <option value="acuerdo">Pago por Acuerdo</option>
              <option value="sentencia">Pago por Sentencia</option>
              <option value="total">Pago Total (Cierre de Caso)</option>
            </SelectInput>
            <InputError :message="pagoForm.errors.motivo_pago" class="mt-2" />
          </div>

          <div>
            <InputLabel for="comprobante_pago" value="Comprobante de Pago *" />
            <div
              class="mt-1 flex items-center justify-center px-6 pt-5 pb-6 border-2 border-gray-300 dark:border-gray-600 border-dashed rounded-md"
            >
              <div class="space-y-1 text-center">
                <CameraIcon class="mx-auto h-12 w-12 text-gray-400" />
                <div class="flex text-sm text-gray-600 dark:text-gray-400">
                  <label
                    for="comprobante_pago_file"
                    class="relative cursor-pointer bg-white dark:bg-gray-800 rounded-md font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500"
                  >
                    <span>Sube un archivo</span>
                    <input
                      id="comprobante_pago_file"
                      name="comprobante_pago"
                      type="file"
                      class="sr-only"
                      accept="image/*"
                      capture="environment"
                      @input="pagoForm.comprobante_pago = $event.target.files[0]"
                      required
                    />
                  </label>
                  <p class="pl-1">o arrastra y suelta</p>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-500">PNG, JPG, GIF hasta 5MB</p>
                <p v-if="pagoForm.comprobante_pago" class="text-sm font-medium text-green-600 pt-2">
                  Archivo seleccionado: {{ pagoForm.comprobante_pago.name }}
                </p>
              </div>
            </div>
            <InputError :message="pagoForm.errors.comprobante_pago" class="mt-2" />
            <div v-if="pagoFormProgress !== null" class="mt-2 w-full bg-gray-200 rounded h-2">
              <div class="h-2 rounded bg-indigo-600" :style="{ width: pagoFormProgress + '%' }"></div>
            </div>
          </div>

          <div class="mt-6 flex justify-end">
            <SecondaryButton @click="cerrarModalPago">Cancelar</SecondaryButton>
            <PrimaryButton class="ms-3" :class="{ 'opacity-25': pagoForm.processing }" :disabled="pagoForm.processing">
              Registrar Pago
            </PrimaryButton>
          </div>
        </form>
      </div>
    </Modal>

    <!-- Programar alerta -->
    <Modal :show="mostrandoModalAlerta" @close="cerrarModalAlerta">
      <div class="p-6">
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Programar Alerta Manual</h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
          Cree un recordatorio para usted mismo sobre este caso. La alerta aparecerá en su bandeja de
          notificaciones.
        </p>
        <form @submit.prevent="submitAlerta" class="mt-6 space-y-6">
          <div>
            <InputLabel for="mensaje_alerta" value="Mensaje del Recordatorio" />
            <Textarea v-model="alertaForm.mensaje" id="mensaje_alerta" class="mt-1 block w-full" required rows="4" />
            <InputError :message="alertaForm.errors.mensaje" class="mt-2" />
          </div>
          <InputLabel for="prioridad" value="Prioridad" />
            <SelectInput id="prioridad" v-model="alertaForm.prioridad" class="mt-1 block w-full">
              <option value="baja">Baja</option>
              <option value="media">Media</option>
              <option value="alta">Alta</option>
            </SelectInput>
          <InputError :message="alertaForm.errors.prioridad" class="mt-2" />
          <div>
            <InputLabel for="programado_para" value="Fecha de la Alerta (Opcional)" />
            <TextInput
                v-model="alertaForm.programado_para"
                id="programado_para"
                type="datetime-local"
                class="mt-1 block w-full"
            />
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
              Si no selecciona fecha, la alerta se creará inmediatamente.
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Te enviaremos un recordatorio diario hasta esta fecha y hora (zona horaria del sistema).
            </p>
            <InputError :message="alertaForm.errors.programado_para" class="mt-2" />
          </div>
          <div class="mt-6 flex justify-end">
            <SecondaryButton @click="cerrarModalAlerta">Cancelar</SecondaryButton>
            <PrimaryButton class="ms-3" :class="{ 'opacity-25': alertaForm.processing }" :disabled="alertaForm.processing">
              Programar Alerta
            </PrimaryButton>
          </div>
        </form>
      </div>
    </Modal>
  </AuthenticatedLayout>
</template>
