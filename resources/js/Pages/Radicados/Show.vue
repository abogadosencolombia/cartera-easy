<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import Textarea from '@/Components/Textarea.vue';
import { useProcesos } from '@/composables/useProcesos';

const props = defineProps({
  proceso: { type: Object, required: true },
});

// Usamos la lógica centralizada desde nuestro composable
const { formatDate, getRevisionStatus } = useProcesos();

// Propiedad computada para saber si el caso está cerrado
const isClosed = computed(() => props.proceso.estado === 'CERRADO');
const files = computed(() => props.proceso?.documentos ?? []);
const asText = (v) => v ?? '—';


// --- INICIO DE NUEVA LÓGICA ---

// Lógica para Cerrar Caso
const showCloseModal = ref(false);
const closeForm = useForm({ nota_cierre: '' });
const openCloseModal = () => {
    closeForm.reset();
    showCloseModal.value = true;
};
const submitCloseCase = () => {
    closeForm.patch(route('procesos.close', props.proceso.id), {
        preserveScroll: true,
        onSuccess: () => {
            showCloseModal.value = false;
        },
    });
};

// Lógica para Reabrir Caso
const showReopenModal = ref(false);
const reopenForm = useForm({});
const openReopenModal = () => (showReopenModal.value = true);
const reopenTheCase = () => {
    reopenForm.patch(route('procesos.reopen', props.proceso.id), {
        preserveScroll: true,
        onSuccess: () => (showReopenModal.value = false),
    });
};

// --- FIN DE NUEVA LÓGICA ---


// ===== Lógica de Eliminar Radicado =====
const confirmingDeletion = ref(false);
const deleteForm = useForm({});
const askDelete = () => (confirmingDeletion.value = true);
const closeDelete = () => (confirmingDeletion.value = false);
const doDelete = () => {
  deleteForm.delete(route('procesos.destroy', props.proceso.id), { onSuccess: () => closeDelete() });
};

// ===== Lógica de Subir Documento =====
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
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => {
      uploadForm.reset();
      if (fileInput.value) fileInput.value.value = '';
    },
  });
};

// ===== Lógica de Eliminar Documento =====
const deletingDoc = ref(null);
const delDocForm = useForm({});
const askDeleteDoc = (doc) => (deletingDoc.value = doc);
const closeDeleteDoc = () => (deletingDoc.value = null);
const doDeleteDoc = () => {
  if (!deletingDoc.value) return;
  delDocForm.delete(
    route('procesos.documentos.destroy', { proceso: props.proceso.id, documento: deletingDoc.value.id }),
    { preserveScroll: true, onSuccess: () => closeDeleteDoc() },
  );
};
</script>

<template>
  <Head :title="`Radicado ${proceso.radicado || ''}`" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex flex-col md:flex-row items-center justify-between gap-4">
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight truncate">
              Expediente <span class="text-indigo-600 dark:text-indigo-400">{{ proceso.radicado || '—' }}</span>
            </h2>
            <!-- BADGE DE ESTADO -->
            <span
              class="inline-flex flex-shrink-0 items-center rounded-md px-2 py-1 text-xs font-bold"
              :class="{
                'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300': !isClosed,
                'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300': isClosed,
              }"
            >
              {{ proceso.estado }}
            </span>
          </div>
          <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 truncate">
            {{ asText(proceso.asunto) }}
          </p>
        </div>
        <div class="flex items-center gap-3 flex-shrink-0">
          <Link :href="route('procesos.edit', proceso.id)">
            <PrimaryButton :disabled="isClosed">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.5L14.732 3.732z" /></svg>
              Editar
            </PrimaryButton>
          </Link>
          
          <!-- BOTONES DE CERRAR/REABRIR -->
          <DangerButton @click="openCloseModal" v-if="!isClosed">Cerrar</DangerButton>
          <PrimaryButton @click="openReopenModal" v-if="isClosed" class="!bg-blue-600 hover:!bg-blue-700 focus:!bg-blue-700 active:!bg-blue-800 focus:!ring-blue-500">Reabrir</PrimaryButton>
          
          <DangerButton @click="askDelete" :disabled="isClosed">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
            Eliminar
          </DangerButton>
          
          <Link :href="route('procesos.index')">
            <SecondaryButton>Volver</SecondaryButton>
          </Link>
        </div>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <!-- NOTIFICACIÓN DE CASO CERRADO -->
        <div v-if="isClosed" class="bg-yellow-50 dark:bg-gray-800 border-l-4 border-yellow-400 p-4 mb-8 rounded-r-lg">
          <div class="flex">
            <div class="flex-shrink-0">
              <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.21 3.03-1.742 3.03H4.42c-1.532 0-2.492-1.696-1.742-3.03l5.58-9.92zM10 13a1 1 0 110-2 1 1 0 010 2zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="ml-3">
              <p class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Este caso fue cerrado.</p>
              <div v-if="proceso.nota_cierre" class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                <p class="font-semibold">Nota de cierre:</p>
                <p class="whitespace-pre-wrap">{{ proceso.nota_cierre }}</p>
              </div>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
          
          <!-- Columna Principal -->
          <div class="lg:col-span-2 space-y-8">
            <!-- Información del Proceso -->
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
              <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                  <span>Información del Proceso</span>
                </h3>
                <dl class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                  <div class="md:col-span-2">
                    <dt class="text-xs uppercase text-gray-500">Juzgado / Entidad</dt>
                    <dd class="text-gray-900 dark:text-gray-100 mt-1 break-words">{{ proceso.juzgado?.nombre || '—' }}</dd>
                  </div>
                  <div>
                    <dt class="text-xs uppercase text-gray-500">Tipo de proceso</dt>
                    <dd class="text-gray-900 dark:text-gray-100 mt-1 break-words">{{ proceso.tipo_proceso?.nombre || '—' }}</dd>
                  </div>
                  <div>
                    <dt class="text-xs uppercase text-gray-500">Naturaleza</dt>
                    <dd class="text-gray-900 dark:text-gray-100 mt-1 break-words">{{ asText(proceso.naturaleza) }}</dd>
                  </div>
                  <div class="md:col-span-2">
                    <dt class="text-xs uppercase text-gray-500">Última actuación</dt>
                    <dd class="text-gray-900 dark:text-gray-100 mt-1 whitespace-pre-wrap break-words">{{ asText(proceso.ultima_actuacion) }}</dd>
                  </div>
                  <div class="md:col-span-2">
                    <dt class="text-xs uppercase text-gray-500">Observaciones</dt>
                    <dd class="text-gray-900 dark:text-gray-100 mt-1 whitespace-pre-wrap break-words">{{ asText(proceso.observaciones) }}</dd>
                  </div>
                </dl>
              </div>
            </div>
            
            <!-- Documentos -->
            <fieldset :disabled="isClosed" :class="{ 'opacity-60': isClosed }">
              <div class="grid grid-cols-1 md:grid-cols-5 gap-8">
                <!-- Subir -->
                <div class="md:col-span-2 bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                  <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Agregar documento</h3>
                    <form @submit.prevent="submitUpload" class="space-y-4">
                      <div>
                        <InputLabel value="Archivo" for="file-upload" class="sr-only" />
                        <input
                          ref="fileInput"
                          id="file-upload"
                          type="file"
                          @change="onPickFile"
                          class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-gray-700 dark:file:text-gray-200 dark:hover:file:bg-gray-600"
                        />
                        <InputError :message="uploadForm.errors.archivo" class="mt-2" />
                      </div>
                      <div>
                        <InputLabel for="nombre" value="Nombre del documento" />
                        <TextInput id="nombre" v-model="uploadForm.nombre" class="mt-1 block w-full" type="text" />
                        <InputError :message="uploadForm.errors.nombre" class="mt-2" />
                      </div>
                      <div>
                        <InputLabel for="nota" value="Nota (opcional)" />
                        <Textarea id="nota" v-model="uploadForm.nota" rows="2" class="mt-1 block w-full" />
                        <InputError :message="uploadForm.errors.nota" class="mt-2" />
                      </div>
                      <PrimaryButton :disabled="uploadForm.processing || !uploadForm.archivo" :class="{ 'opacity-25': uploadForm.processing || !uploadForm.archivo }" type="submit">
                        {{ uploadForm.processing ? 'Subiendo…' : 'Subir Documento' }}
                      </PrimaryButton>
                    </form>
                  </div>
                </div>

                <!-- Listado -->
                <div class="md:col-span-3 bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                  <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Documentos del proceso</h3>
                    <div v-if="files.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
                      <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                      <p class="mt-2 text-sm">Aún no hay documentos adjuntos.</p>
                    </div>
                    <transition-group v-else tag="ul" class="divide-y divide-gray-200 dark:divide-gray-700"
                      enter-active-class="transition-all duration-300 ease-out"
                      enter-from-class="opacity-0 transform -translate-x-4"
                      enter-to-class="opacity-100 transform translate-x-0"
                      leave-active-class="transition-all duration-200 ease-in absolute w-full"
                      leave-from-class="opacity-100"
                      leave-to-class="opacity-0 transform scale-95"
                    >
                      <li v-for="doc in files" :key="doc.id" class="py-3 flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                          <a
                            :href="route('documentos-proceso.view', doc.id)"
                            target="_blank"
                            rel="noopener"
                            class="font-medium text-gray-900 dark:text-gray-100 break-all hover:underline"
                          >
                            {{ asText(doc.file_name) }}
                          </a>
                          <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 truncate">
                            {{ formatDate(doc.created_at) }}
                            <span v-if="doc.descripcion"> · {{ doc.descripcion }}</span>
                          </p>
                        </div>
                        <div class="shrink-0 flex items-center gap-2">
                          <a :href="route('documentos-proceso.view', doc.id)" target="_blank" rel="noopener" class="text-gray-400 hover:text-indigo-600" title="Ver">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 5c-7.633 0-10 7-10 7s2.367 7 10 7 10-7 10-7-2.367-7-10-7Zm0 12a5 5 0 1 1 0-10 5 5 0 0 1 0 10Z"/></svg>
                          </a>
                          <a :href="route('documentos-proceso.download', doc.id)" class="text-gray-400 hover:text-gray-700 dark:hover:text-gray-300" title="Descargar">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                          </a>
                          <button type="button" @click="askDeleteDoc(doc)" class="text-gray-400 hover:text-red-600" title="Eliminar">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                          </button>
                        </div>
                      </li>
                    </transition-group>
                  </div>
                </div>
              </div>
            </fieldset>
          </div>
          
          <!-- Columna Lateral -->
          <div class="lg:col-span-1">
            <div class="sticky top-8 space-y-6">
              <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0h18" /></svg>
                  <span>Seguimiento y Fechas</span>
                </h3>
                <dl class="mt-4 space-y-4 text-sm">
                  <div>
                    <dt class="text-xs uppercase text-gray-500">Próxima Revisión</dt>
                    <dd class="mt-1">
                      <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-semibold" :class="getRevisionStatus(proceso.fecha_proxima_revision).classes">
                        {{ getRevisionStatus(proceso.fecha_proxima_revision).text }}
                      </span>
                    </dd>
                  </div>
                  <div>
                    <dt class="text-xs uppercase text-gray-500">Fecha de Revisión</dt>
                    <dd class="text-gray-900 dark:text-gray-100 mt-1">{{ formatDate(proceso.fecha_revision) }}</dd>
                  </div>
                  <div>
                    <dt class="text-xs uppercase text-gray-500">Fecha de Radicado</dt>
                    <dd class="text-gray-900 dark:text-gray-100 mt-1">{{ formatDate(proceso.fecha_radicado) }}</dd>
                  </div>
                </dl>
              </div>

              <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197" /></svg>
                  <span>Partes y Responsables</span>
                </h3>
                <dl class="mt-4 space-y-4 text-sm">
                  <div>
                    <dt class="text-xs uppercase text-gray-500">Demandante / Denunciante</dt>
                    <dd class="text-gray-900 dark:text-gray-100 mt-1 break-words">{{ proceso.demandante?.nombre_completo || '—' }}</dd>
                  </div>
                  <div>
                    <dt class="text-xs uppercase text-gray-500">Demandado / Denunciado</dt>
                    <dd class="text-gray-900 dark:text-gray-100 mt-1 break-words">{{ proceso.demandado?.nombre_completo || '—' }}</dd>
                  </div>
                  <div>
                    <dt class="text-xs uppercase text-gray-500">Abogado / Gestor</dt>
                    <dd class="text-gray-900 dark:text-gray-100 mt-1 break-words">{{ proceso.abogado?.name || '—' }}</dd>
                  </div>
                  <div>
                    <dt class="text-xs uppercase text-gray-500">Responsable de Revisión</dt>
                    <dd class="text-gray-900 dark:text-gray-100 mt-1 break-words">{{ proceso.responsable_revision?.name || '—' }}</dd>
                  </div>
                </dl>
              </div>

              <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" /></svg>
                  <span>Enlaces y Contacto</span>
                </h3>
                <dl class="mt-4 space-y-4 text-sm">
                  <div>
                    <dt class="text-xs uppercase text-gray-500">Link expediente digital</dt>
                    <dd class="mt-1 min-w-0">
                      <a v-if="proceso.link_expediente" :href="proceso.link_expediente" target="_blank" rel="noopener" class="text-indigo-600 dark:text-indigo-400 hover:underline break-all">
                        {{ proceso.link_expediente }}
                      </a>
                      <span v-else>—</span>
                    </dd>
                  </div>
                  <div>
                    <dt class="text-xs uppercase text-gray-500">Ubicación en Drive</dt>
                    <dd class="mt-1 min-w-0">
                      <a v-if="proceso.ubicacion_drive" :href="proceso.ubicacion_drive" target="_blank" rel="noopener" class="text-indigo-600 dark:text-indigo-400 hover:underline break-all">
                        {{ proceso.ubicacion_drive }}
                      </a>
                      <span v-else>—</span>
                    </dd>
                  </div>
                  <div>
                    <dt class="text-xs uppercase text-gray-500">Correo de radicación</dt>
                    <dd class="text-gray-900 dark:text-gray-100 mt-1 break-all">{{ asText(proceso.correo_radicacion) }}</dd>
                  </div>
                  <div>
                    <dt class="text-xs uppercase text-gray-500">Correos del juzgado</dt>
                    <dd class="text-gray-900 dark:text-gray-100 mt-1 break-all">{{ asText(proceso.correos_juzgado) }}</dd>
                  </div>
                </dl>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modales -->
    <Modal :show="confirmingDeletion" @close="closeDelete">
      <div class="p-6">
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Eliminar radicado</h3>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
          ¿Seguro que quieres eliminar el radicado
          <span class="font-semibold">{{ asText(proceso.radicado) }}</span>? Esta acción no se puede deshacer y eliminará todos los documentos asociados.
        </p>
        <div class="mt-6 flex justify-end">
          <SecondaryButton @click="closeDelete">Cancelar</SecondaryButton>
          <DangerButton class="ms-3" :disabled="deleteForm.processing" @click="doDelete">Sí, eliminar</DangerButton>
        </div>
      </div>
    </Modal>

    <Modal :show="!!deletingDoc" @close="closeDeleteDoc">
      <div class="p-6">
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Eliminar documento</h3>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
          ¿Eliminar el documento <span class="font-semibold break-all">{{ deletingDoc?.file_name }}</span>?
        </p>
        <div class="mt-6 flex justify-end">
          <SecondaryButton @click="closeDeleteDoc">Cancelar</SecondaryButton>
          <DangerButton class="ms-3" :disabled="delDocForm.processing" @click="doDeleteDoc">Sí, eliminar</DangerButton>
        </div>
      </div>
    </Modal>

    <!-- INICIO DE NUEVOS MODALES -->
    <Modal :show="showCloseModal" @close="showCloseModal = false">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Cerrar Radicado</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Estás a punto de cerrar el radicado <span class="font-bold">{{ proceso?.radicado }}</span>. Por favor, añade una nota de cierre.
            </p>
            <div class="mt-6">
                <InputLabel for="nota_cierre_show" value="Nota de Cierre *" required />
                <Textarea v-model="closeForm.nota_cierre" id="nota_cierre_show" class="mt-1 block w-full" rows="4" />
                <InputError :message="closeForm.errors.nota_cierre" class="mt-2" />
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton @click="showCloseModal = false">Cancelar</SecondaryButton>
                <DangerButton @click="submitCloseCase" :disabled="closeForm.processing" :class="{ 'opacity-25': closeForm.processing }">
                    {{ closeForm.processing ? 'Cerrando...' : 'Confirmar Cierre' }}
                </DangerButton>
            </div>
        </div>
    </Modal>

    <Modal :show="showReopenModal" @close="showReopenModal = false">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Reabrir Radicado</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                ¿Estás seguro de que quieres reabrir este radicado? El estado volverá a "ACTIVO" y la nota de cierre será eliminada.
            </p>
            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton @click="showReopenModal = false">Cancelar</SecondaryButton>
                <PrimaryButton @click="reopenTheCase" :disabled="reopenForm.processing" :class="{ 'opacity-25': reopenForm.processing }" class="!bg-blue-600 hover:!bg-blue-700 focus:!bg-blue-700 active:!bg-blue-800 focus:!ring-blue-500">
                    {{ reopenForm.processing ? 'Reabriendo...' : 'Sí, Reabrir' }}
                </PrimaryButton>
            </div>
        </div>
    </Modal>
    <!-- FIN DE NUEVOS MODALES -->
    
  </AuthenticatedLayout>
</template>

