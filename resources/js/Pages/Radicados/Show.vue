<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3'; 

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import DatePicker from '@/Components/DatePicker.vue';
import Textarea from '@/Components/Textarea.vue';
import Dropdown from '@/Components/Dropdown.vue';
import { useProcesos } from '@/composables/useProcesos';
import { ArrowPathIcon, ChevronDownIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    proceso: { type: Object, required: true },
    etapas: { type: Array, required: true }, // Recibir etapas para el modal
});

const { formatDate, getRevisionStatus } = useProcesos();

// --- Helpers Fecha ---
const fmtDateTime = (d) => d ? new Date(String(d).replace(' ', 'T')).toLocaleString('es-CO', { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true }) : 'N/A';
const fmtDateSimple = (d) => d ? new Date(String(d).replace(' ', 'T')).toLocaleDateString('es-CO', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A';

const isClosed = computed(() => props.proceso.estado === 'CERRADO');
const files = computed(() => props.proceso?.documentos ?? []);
const tieneContrato = computed(() => !!props.proceso?.contrato?.id);
const actuaciones = computed(() => props.proceso?.actuaciones ?? []);
const asText = (v) => v ?? '—';

// --- LOGICA CAMBIO DE ETAPA ---
const showEtapaModal = ref(false);
const etapaForm = useForm({
    etapa_procesal_id: props.proceso.etapa_procesal_id || '',
    observacion: '',
});

const openEtapaModal = () => {
    etapaForm.etapa_procesal_id = props.proceso.etapa_procesal_id;
    etapaForm.observacion = '';
    showEtapaModal.value = true;
};

const submitEtapa = () => {
    etapaForm.patch(route('procesos.update_etapa', props.proceso.id), {
        onSuccess: () => showEtapaModal.value = false,
        preserveScroll: true
    });
};

// --- Lógica Cierre/Reapertura ---
const showCloseModal = ref(false);
const closeForm = useForm({ nota_cierre: '' });
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
const closeDelete = () => (confirmingDeletion.value = false);
const doDelete = () => deleteForm.delete(route('procesos.destroy', props.proceso.id), { onSuccess: () => closeDelete() });

// --- Subida Documentos ---
const uploadForm = useForm({ archivo: null, nombre: '', nota: '' });
const fileInput = ref(null);
const onPickFile = (e) => {
  const file = e.target.files?.[0];
  if (!file) return;
  uploadForm.archivo = file;
  if (!uploadForm.nombre) {
    const parts = file.name.split('.');
    parts.length > 1 ? parts.pop() : null;
    uploadForm.nombre = parts.join('.') || file.name;
  }
};
const submitUpload = () => {
  if (!uploadForm.archivo) return;
  uploadForm.post(route('procesos.documentos.store', props.proceso.id), {
    forceFormData: true, preserveScroll: true, onSuccess: () => {
      uploadForm.reset();
      if (fileInput.value) fileInput.value.value = '';
      router.reload({ only: ['proceso'], preserveState: true });
    },
  });
};

// --- Eliminar Documento ---
const deletingDoc = ref(null);
const delDocForm = useForm({});
const askDeleteDoc = (doc) => (deletingDoc.value = doc);
const closeDeleteDoc = () => (deletingDoc.value = null);
const doDeleteDoc = () => {
  if (!deletingDoc.value) return;
  delDocForm.delete(route('procesos.documentos.destroy', { proceso: props.proceso.id, documento: deletingDoc.value.id }), {
      preserveScroll: true, onSuccess: () => { closeDeleteDoc(); router.reload({ only: ['proceso'], preserveState: true }); }
  });
};

// --- Actuaciones ---
const actuacionForm = useForm({ nota: '', fecha_actuacion: new Date().toISOString().slice(0, 10) });
const guardarActuacion = () => actuacionForm.post(route('procesos.actuaciones.store', props.proceso.id), { preserveScroll: true, onSuccess: () => { actuacionForm.reset(); router.reload({ only: ['proceso'], preserveState: true }); } });

const editActuacionModalAbierto = ref(false);
const actuacionEnEdicion = ref(null);
const editActuacionForm = useForm({ nota: '', fecha_actuacion: '' });

const abrirModalEditar = (actuacion) => {
    actuacionEnEdicion.value = actuacion;
    editActuacionForm.nota = actuacion.nota;
    editActuacionForm.fecha_actuacion = actuacion.fecha_actuacion ? String(actuacion.fecha_actuacion).split('T')[0] : '';
    editActuacionModalAbierto.value = true;
};
const cerrarModalEditar = () => { editActuacionModalAbierto.value = false; actuacionEnEdicion.value = null; editActuacionForm.reset(); };
const actualizarActuacion = () => {
    if (!actuacionEnEdicion.value) return;
    editActuacionForm.put(route('procesos.actuaciones.update', actuacionEnEdicion.value.id), { preserveScroll: true, onSuccess: () => { cerrarModalEditar(); router.reload({ only: ['proceso'], preserveState: true }); } });
};
const eliminarActuacion = (actuacionId) => {
    if (confirm('¿Eliminar actuación?')) router.delete(route('procesos.actuaciones.destroy', actuacionId), { preserveScroll: true, onSuccess: () => router.reload({ only: ['proceso'], preserveState: true }) });
};
</script>

<template>
  <Head :title="`Radicado ${proceso.radicado || ''}`" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex flex-col md:flex-row items-center justify-between gap-4">
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-3 flex-wrap">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight truncate">
              Expediente <span class="text-indigo-600 dark:text-indigo-400">{{ proceso.radicado || '—' }}</span>
            </h2>
            <span class="inline-flex flex-shrink-0 items-center rounded-md px-2 py-1 text-xs font-bold"
              :class="isClosed ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300' : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'">
              {{ proceso.estado }}
            </span>
            <!-- Etiqueta Etapa Actual -->
            <span v-if="proceso.etapa_actual" class="inline-flex items-center rounded-md px-2 py-1 text-xs font-bold border border-blue-200 bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                {{ proceso.etapa_actual.nombre }}
            </span>
          </div>
          <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 truncate" :title="asText(proceso.asunto)">{{ asText(proceso.asunto) }}</p>
        </div>

        <div class="flex items-center gap-3 flex-shrink-0 flex-wrap justify-end">
          <!-- BOTÓN CAMBIAR ETAPA -->
          <PrimaryButton @click="openEtapaModal" v-if="!isClosed">
              <ArrowPathIcon class="w-4 h-4 mr-2" />
              Cambiar Etapa
          </PrimaryButton>

          <Link :href="route('procesos.edit', proceso.id)">
            <PrimaryButton :disabled="isClosed">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.5L14.732 3.732z" /></svg>
              Editar
            </PrimaryButton>
          </Link>
          
          <!-- Botón Contrato -->
          <Link v-if="!tieneContrato && !isClosed" :href="route('honorarios.contratos.create', { proceso_id: proceso.id, cliente_id: proceso.demandantes?.[0]?.id })">
            <PrimaryButton>Generar Contrato</PrimaryButton>
          </Link>
          <Link v-else-if="tieneContrato" :href="route('honorarios.contratos.show', proceso.contrato.id)">
             <PrimaryButton>Ver Contrato</PrimaryButton>
          </Link>

          <PrimaryButton @click="openCloseModal" v-if="!isClosed && $page.props.auth.user.tipo_usuario === 'admin'">Cerrar</PrimaryButton>
          <PrimaryButton @click="openReopenModal" v-if="isClosed && $page.props.auth.user.tipo_usuario === 'admin'">Reabrir</PrimaryButton>

          <DangerButton v-if="$page.props.auth.user.tipo_usuario === 'admin'" @click="askDelete" :disabled="isClosed">Eliminar</DangerButton>
          <Link :href="route('procesos.index')"><SecondaryButton>Volver</SecondaryButton></Link>
        </div>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Alerta de Información Incompleta -->
        <div v-if="proceso.info_incompleta" class="bg-amber-50 dark:bg-amber-900/20 border-l-4 border-amber-400 p-4 mb-6 rounded-r-lg shadow-sm">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-amber-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-bold text-amber-800 dark:text-amber-200 uppercase tracking-wide">
                        Expediente con información incompleta
                    </p>
                    <p class="text-xs text-amber-700 dark:text-amber-300">
                        Este proceso contiene demandados sin identificar o información de contacto pendiente.
                    </p>
                </div>
            </div>
        </div>

        <div v-if="isClosed" class="bg-yellow-50 dark:bg-gray-800 border-l-4 border-yellow-400 p-4 mb-8 rounded-r-lg">
           <div class="flex"><div class="ml-3"><p class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Caso cerrado.</p><div v-if="proceso.nota_cierre" class="mt-2 text-sm text-yellow-700 dark:text-yellow-300"><p class="font-semibold">Nota:</p><p>{{ proceso.nota_cierre }}</p></div></div></div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
          <!-- Columna Principal -->
          <div class="lg:col-span-2 space-y-8">
            <!-- Info -->
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
              <div class="p-6">
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                  <span>Información del Proceso</span>
                </h3>
                <dl class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                    <div class="md:col-span-2"><dt class="text-xs uppercase font-bold text-gray-700 dark:text-gray-300">Juzgado / Entidad</dt><dd class="text-gray-900 dark:text-gray-100 mt-1">{{ proceso.juzgado?.nombre || '—' }}</dd></div>
                    <div><dt class="text-xs uppercase font-bold text-gray-700 dark:text-gray-300">Tipo de Proceso</dt><dd class="text-gray-900 dark:text-gray-100 mt-1">{{ proceso.tipo_proceso?.nombre || '—' }}</dd></div>
                    <div><dt class="text-xs uppercase font-bold text-gray-700 dark:text-gray-300">Naturaleza</dt><dd class="text-gray-900 dark:text-gray-100 mt-1">{{ asText(proceso.naturaleza) }}</dd></div>
                    <div><dt class="text-xs uppercase font-bold text-gray-700 dark:text-gray-300">Correo de Radicación</dt><dd class="text-gray-900 dark:text-gray-100 mt-1">{{ asText(proceso.correo_radicacion) }}</dd></div>
                    <div><dt class="text-xs uppercase font-bold text-gray-700 dark:text-gray-300">Correo(s) del Juzgado</dt><dd class="text-gray-900 dark:text-gray-100 mt-1">{{ asText(proceso.correos_juzgado) }}</dd></div>
                    <div class="md:col-span-2"><dt class="text-xs uppercase font-bold text-gray-700 dark:text-gray-300">Observaciones</dt><dd class="text-gray-900 dark:text-gray-100 mt-1 whitespace-pre-wrap">{{ asText(proceso.observaciones) }}</dd></div>
                </dl>
              </div>
            </div>

            <!-- Documentos -->
            <fieldset :disabled="isClosed" :class="{ 'opacity-60': isClosed }">
              <div class="grid grid-cols-1 md:grid-cols-5 gap-8">
                <div class="md:col-span-2 bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                  <div class="p-6">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4">Agregar documento</h3>
                    <form @submit.prevent="submitUpload" class="space-y-4">
                      <div><input ref="fileInput" type="file" @change="onPickFile" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:bg-indigo-50 file:text-indigo-700" /><InputError :message="uploadForm.errors.archivo" class="mt-2" /></div>
                      <div><InputLabel for="nombre" value="Nombre" /><TextInput id="nombre" v-model="uploadForm.nombre" class="mt-1 block w-full" /><InputError :message="uploadForm.errors.nombre" class="mt-2" /></div>
                      <div><InputLabel for="nota" value="Nota" /><Textarea id="nota" v-model="uploadForm.nota" rows="2" class="mt-1 block w-full" /></div>
                      <PrimaryButton :disabled="uploadForm.processing || !uploadForm.archivo" type="submit">{{ uploadForm.processing ? 'Subiendo…' : 'Subir Documento' }}</PrimaryButton>
                    </form>
                  </div>
                </div>
                <div class="md:col-span-3 bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                  <div class="p-6">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4">Documentos</h3>
                    <div class="max-h-[450px] overflow-y-auto overflow-x-hidden pr-2 scrollbar-thin scrollbar-thumb-gray-300 dark:scrollbar-thumb-gray-600">
                        <div v-if="files.length === 0" class="text-center py-8 text-gray-500">No hay documentos.</div>
                        <ul v-else class="divide-y divide-gray-200 dark:divide-gray-700">
                          <li v-for="doc in files" :key="doc.id" class="py-3 flex items-start justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <a :href="route('documentos-proceso.view', doc.id)" target="_blank" class="font-medium text-gray-900 dark:text-gray-100 hover:underline break-all">{{ asText(doc.file_name) }}</a>
                                <p class="text-xs text-gray-500 mt-1">{{ formatDate(doc.created_at) }} <span v-if="doc.descripcion"> · {{ doc.descripcion }}</span></p>
                            </div>
                            <div class="shrink-0 flex items-center gap-2">
                                <a :href="route('documentos-proceso.view', doc.id)" target="_blank" class="text-gray-400 hover:text-indigo-600"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 5c-7.633 0-10 7-10 7s2.367 7 10 7 10-7 10-7-2.367-7-10-7Zm0 12a5 5 0 1 1 0-10 5 5 0 0 1 0 10Z"/></svg></a>
                                <button @click="askDeleteDoc(doc)" class="text-gray-400 hover:text-red-600"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg></button>
                            </div>
                          </li>
                        </ul>
                    </div>
                  </div>
                </div>
              </div>
            </fieldset>

            <!-- Actuaciones -->
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg" :class="{ 'opacity-60': isClosed }">
                <div class="p-6">
                   <fieldset :disabled="isClosed">
                        <form @submit.prevent="guardarActuacion" class="mb-6 pb-6 border-b dark:border-gray-700">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-2">Nueva Actuación</h3>
                            <div class="grid md:grid-cols-4 gap-4 mb-4">
                                <div class="md:col-span-3">
                                    <InputLabel value="Descripción" />
                                    <Textarea v-model="actuacionForm.nota" rows="3" class="mt-1 block w-full" required />
                                    <InputError :message="actuacionForm.errors.nota" class="mt-2" />
                                </div>
                                <div>
                                    <InputLabel value="Fecha" />
                                    <DatePicker v-model="actuacionForm.fecha_actuacion" class="mt-1 block w-full" />
                                    <InputError :message="actuacionForm.errors.fecha_actuacion" class="mt-2" />
                                </div>
                            </div>
                            <div class="flex justify-end"><PrimaryButton :disabled="actuacionForm.processing">Registrar</PrimaryButton></div>
                        </form>
                   </fieldset>
                   <h3 class="text-lg font-bold mb-4">Historial</h3>
                   <div v-if="!actuaciones.length" class="text-center py-8 text-gray-500">No hay actuaciones.</div>
                   <div v-else class="space-y-4">
                       <div v-for="actuacion in actuaciones" :key="actuacion.id" class="p-4 border rounded-lg bg-gray-50 dark:bg-gray-800/50">
                           <p class="text-sm text-gray-800 dark:text-gray-200 whitespace-pre-wrap">{{ actuacion.nota }}</p>
                           <div class="mt-2 text-xs text-gray-500 flex justify-between">
                               <span>Por: {{ actuacion.user?.name }} el {{ fmtDateTime(actuacion.created_at) }} | Fecha: {{ fmtDateSimple(actuacion.fecha_actuacion) }}</span>
                               <div v-if="!isClosed" class="flex gap-2">
                                   <button @click="abrirModalEditar(actuacion)" class="text-indigo-600">Editar</button>
                                   <button @click="eliminarActuacion(actuacion.id)" class="text-red-600">Eliminar</button>
                               </div>
                           </div>
                       </div>
                   </div>
                </div>
            </div>
          </div>

          <!-- Columna Lateral -->
          <div class="lg:col-span-1">
            <div class="sticky top-8 space-y-6">
              <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-bold mb-4">Seguimiento</h3>
                <dl class="space-y-4 text-sm">
                   <div><dt class="text-xs uppercase font-bold text-gray-700 dark:text-gray-300">Próxima Revisión</dt><dd><span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-semibold" :class="getRevisionStatus(proceso.fecha_proxima_revision).classes">{{ getRevisionStatus(proceso.fecha_proxima_revision).text }}</span></dd></div>
                   <div><dt class="text-xs uppercase font-bold text-gray-700 dark:text-gray-300">Última Revisión</dt><dd>{{ formatDate(proceso.fecha_revision) }}</dd></div>
                   <div><dt class="text-xs uppercase font-bold text-gray-700 dark:text-gray-300">Fecha Radicado</dt><dd>{{ formatDate(proceso.fecha_radicado) }}</dd></div>
                   <div><dt class="text-xs uppercase font-bold text-gray-700 dark:text-gray-300">Responsable Revisión</dt><dd>{{ proceso.responsable_revision?.name || '—' }}</dd></div>
                </dl>
              </div>
              <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                 <h3 class="text-lg font-bold mb-4">Partes</h3>
                 <dl class="space-y-4 text-sm">
                     <div>
                        <dt class="text-xs uppercase font-bold text-gray-700 dark:text-gray-300 mb-2">Demandantes</dt>
                        <dd>
                            <ul class="space-y-2">
                                <li v-for="p in proceso.demandantes" :key="p.id" class="flex flex-col">
                                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ p.nombre_completo }}</span>
                                    <span class="text-xs text-gray-500">{{ p.tipo_documento }} {{ p.numero_documento }}</span>
                                </li>
                            </ul>
                        </dd>
                    </div>
                     <div>
                        <dt class="text-xs uppercase font-bold text-gray-700 dark:text-gray-300 mb-2">Demandados</dt>
                        <dd>
                            <ul class="space-y-2">
                                <li v-for="p in proceso.demandados" :key="p.id" class="flex flex-col">
                                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ p.nombre_completo }}</span>
                                    <span v-if="p.numero_documento && !p.numero_documento.startsWith('TEMP-')" class="text-xs text-gray-500">{{ p.tipo_documento }} {{ p.numero_documento }}</span>
                                    <span v-else class="text-xs text-amber-600 font-bold uppercase">Por identificar</span>
                                </li>
                            </ul>
                        </dd>
                    </div>
                     <div><dt class="text-xs uppercase font-bold text-gray-700 dark:text-gray-300">Abogado / Gestor Principal</dt><dd>{{ proceso.abogado?.name || '—' }}</dd></div>
                 </dl>
              </div>
              <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                 <h3 class="text-lg font-bold mb-4">Enlaces</h3>
                 <dl class="space-y-4 text-sm">
                     <div><dt class="text-xs uppercase font-bold text-gray-700 dark:text-gray-300">Expediente Digital</dt><dd><a v-if="proceso.link_expediente" :href="proceso.link_expediente" target="_blank" class="text-indigo-600 hover:underline break-all">{{ proceso.link_expediente }}</a><span v-else>—</span></dd></div>
                     <div><dt class="text-xs uppercase font-bold text-gray-700 dark:text-gray-300">Drive</dt><dd><a v-if="proceso.ubicacion_drive" :href="proceso.ubicacion_drive" target="_blank" class="text-indigo-600 hover:underline break-all">{{ proceso.ubicacion_drive }}</a><span v-else>—</span></dd></div>
                 </dl>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- MODALES -->
    <Modal :show="confirmingDeletion" @close="closeDelete">
       <div class="p-6">
          <h3 class="text-lg font-medium">Eliminar radicado</h3>
          <p class="mt-2 text-sm text-gray-600">¿Seguro? Se borrará todo.</p>
          <div class="mt-6 flex justify-end"><SecondaryButton @click="closeDelete">Cancelar</SecondaryButton><DangerButton class="ms-3" @click="doDelete">Eliminar</DangerButton></div>
       </div>
    </Modal>
    <Modal :show="!!deletingDoc" @close="closeDeleteDoc">
       <div class="p-6">
          <h3 class="text-lg font-medium">Eliminar documento</h3>
          <p class="mt-2 text-sm text-gray-600">¿Eliminar {{ deletingDoc?.file_name }}?</p>
          <div class="mt-6 flex justify-end"><SecondaryButton @click="closeDeleteDoc">Cancelar</SecondaryButton><DangerButton class="ms-3" @click="doDeleteDoc">Eliminar</DangerButton></div>
       </div>
    </Modal>
    <Modal :show="showCloseModal" @close="showCloseModal = false">
        <div class="p-6">
            <h2 class="text-lg font-medium">Cerrar Radicado</h2>
            <div class="mt-6"><InputLabel value="Nota de Cierre" /><Textarea v-model="closeForm.nota_cierre" class="w-full mt-1" rows="4" /></div>
            <div class="mt-6 flex justify-end gap-3"><SecondaryButton @click="showCloseModal = false">Cancelar</SecondaryButton><DangerButton @click="submitCloseCase">Confirmar</DangerButton></div>
        </div>
    </Modal>
    <Modal :show="showReopenModal" @close="showReopenModal = false">
        <div class="p-6">
            <h2 class="text-lg font-medium">Reabrir Radicado</h2>
            <p class="mt-2 text-sm">¿Reactivar proceso?</p>
            <div class="mt-6 flex justify-end gap-3"><SecondaryButton @click="showReopenModal = false">Cancelar</SecondaryButton><PrimaryButton @click="reopenTheCase">Sí, Reabrir</PrimaryButton></div>
        </div>
    </Modal>
    <Modal :show="editActuacionModalAbierto" @close="cerrarModalEditar">
        <form @submit.prevent="actualizarActuacion" class="p-6">
             <h2 class="text-lg font-medium">Editar Actuación</h2>
             <div class="mt-6 space-y-4">
                 <div><InputLabel value="Descripción" /><Textarea v-model="editActuacionForm.nota" rows="4" class="w-full" required /></div>
                 <div><InputLabel value="Fecha" /><DatePicker v-model="editActuacionForm.fecha_actuacion" class="w-full" /></div>
             </div>
             <div class="mt-6 flex justify-end gap-3"><SecondaryButton @click="cerrarModalEditar">Cancelar</SecondaryButton><PrimaryButton type="submit">Guardar</PrimaryButton></div>
        </form>
    </Modal>

    <!-- MODAL CAMBIO DE ETAPA (NUEVO) -->
    <Modal :show="showEtapaModal" @close="showEtapaModal = false">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Actualizar Etapa Procesal</h2>
            <div class="mb-4">
                <InputLabel for="nueva_etapa" value="Selecciona la nueva etapa" />
                <Dropdown align="left" width="full">
                    <template #trigger>
                        <button type="button" class="mt-1 flex w-full items-center justify-between gap-2 rounded-md border border-gray-300 dark:border-gray-700 dark:bg-gray-900 bg-white px-3 py-2 text-sm shadow-sm hover:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all cursor-pointer dark:text-gray-300">
                            <span>{{ etapaForm.etapa_procesal_id ? etapas.find(e => e.id === etapaForm.etapa_procesal_id)?.nombre : 'Seleccione...' }}</span>
                            <ChevronDownIcon class="h-4 w-4 text-gray-400" />
                        </button>
                    </template>
                    <template #content>
                        <div class="py-1 bg-white dark:bg-gray-800 max-h-60 overflow-y-auto">
                            <button v-for="etapa in etapas" :key="etapa.id" @click="etapaForm.etapa_procesal_id = etapa.id" class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700" :class="{ 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-400 font-bold': etapaForm.etapa_procesal_id === etapa.id }">
                                {{ etapa.nombre }} <span class="text-[10px] opacity-60">({{ etapa.riesgo }})</span>
                            </button>
                        </div>
                    </template>
                </Dropdown>
                <p class="text-xs text-gray-500 mt-1">Al cambiar la etapa, se reiniciarán los contadores de vencimiento.</p>
            </div>
            <div class="mb-4">
                <InputLabel for="obs_etapa" value="Nota del cambio (Opcional)" />
                <Textarea id="obs_etapa" v-model="etapaForm.observacion" class="w-full mt-1" rows="3" placeholder="Escribe por qué avanza el proceso..." />
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton @click="showEtapaModal = false">Cancelar</SecondaryButton>
                <PrimaryButton @click="submitEtapa" :disabled="etapaForm.processing">Guardar Cambio</PrimaryButton>
            </div>
        </div>
    </Modal>

  </AuthenticatedLayout>
</template>
