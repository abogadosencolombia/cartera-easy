<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue'; // Import 'computed'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import Textarea from '@/Components/Textarea.vue';
import AsyncSelect from '@/Components/AsyncSelect.vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
  proceso: { type: Object, required: true },
});

// --- LÓGICA PARA EL MODAL DE CIERRE ---
const showCloseModal = ref(false);
const closeForm = useForm({
  nota_cierre: '',
});

const openCloseModal = () => {
  closeForm.reset();
  showCloseModal.value = true;
};

const closeTheCase = () => {
  closeForm.patch(route('procesos.close', props.proceso.id), {
    preserveScroll: true,
    onSuccess: () => {
      showCloseModal.value = false;
      closeForm.reset();
    },
  });
};
// --- FIN DE LÓGICA DEL MODAL ---


const formatDateForInput = (dateString) => {
  if (!dateString) return '';
  return dateString.substring(0, 10);
};

const mapToSelectOption = (obj, labelKey) => {
  if (!obj) return null;
  return {
    id: obj.id,
    label: obj[labelKey],
  };
};

const form = useForm({
  _method: 'PATCH',
  abogado_id: mapToSelectOption(props.proceso.abogado, 'name'),
  responsable_revision_id: mapToSelectOption(props.proceso.responsable_revision, 'name'),
  juzgado_id: mapToSelectOption(props.proceso.juzgado, 'nombre'),
  tipo_proceso_id: mapToSelectOption(props.proceso.tipo_proceso, 'nombre'),
  demandante_id: mapToSelectOption(props.proceso.demandante, 'nombre_completo'),
  demandado_id: mapToSelectOption(props.proceso.demandado, 'nombre_completo'),
  radicado: props.proceso.radicado ?? '',
  fecha_radicado: formatDateForInput(props.proceso.fecha_radicado),
  fecha_revision: formatDateForInput(props.proceso.fecha_revision),
  fecha_proxima_revision: formatDateForInput(props.proceso.fecha_proxima_revision),
  naturaleza: props.proceso.naturaleza ?? '',
  asunto: props.proceso.asunto ?? '',
  correo_radicacion: props.proceso.correo_radicacion ?? '',
  ultima_actuacion: props.proceso.ultima_actuacion ?? '',
  link_expediente: props.proceso.link_expediente ?? '',
  ubicacion_drive: props.proceso.ubicacion_drive ?? '',
  correos_juzgado: props.proceso.correos_juzgado ?? '',
  observaciones: props.proceso.observaciones ?? '',
});

const submit = () => {
  const payload = {
    ...form.data(),
    abogado_id: form.abogado_id?.id ?? null,
    responsable_revision_id: form.responsable_revision_id?.id ?? null,
    juzgado_id: form.juzgado_id?.id ?? null,
    tipo_proceso_id: form.tipo_proceso_id?.id ?? null,
    demandante_id: form.demandante_id?.id ?? null,
    demandado_id: form.demandado_id?.id ?? null,
  };
  
  router.post(route('procesos.update', props.proceso.id), payload, {
    preserveScroll: true,
  });
};

// Propiedad computada para saber si el caso está cerrado
const isClosed = computed(() => props.proceso.estado === 'CERRADO');

</script>

<template>
  <Head :title="`Editar Radicado ${proceso.radicado ?? ''}`" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex flex-col md:flex-row items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-3">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
              Editar Expediente <span class="text-indigo-600 dark:text-indigo-400">{{ proceso.radicado ?? '—' }}</span>
            </h2>
            <!-- BADGE DE ESTADO -->
            <span
              class="inline-flex items-center rounded-md px-2 py-1 text-xs font-bold"
              :class="{
                'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300': !isClosed,
                'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300': isClosed,
              }"
            >
              {{ proceso.estado }}
            </span>
          </div>
          <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Actualiza la información y el estado del proceso.</p>
        </div>
        <div class="flex items-center gap-3">
          <Link :href="route('procesos.show', proceso.id)">
            <SecondaryButton>Cancelar</SecondaryButton>
          </Link>
          <PrimaryButton @click="submit" :disabled="form.processing || isClosed" :class="{ 'opacity-25': form.processing || isClosed }">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
            </svg>
            {{ form.processing ? 'Guardando…' : 'Guardar Cambios' }}
          </PrimaryButton>
          <!-- BOTÓN PARA CERRAR CASO -->
          <DangerButton v-if="!isClosed" @click="openCloseModal">Cerrar Caso</DangerButton>
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
              <p class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Este caso está cerrado.</p>
              <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                <p class="font-semibold">Nota de cierre:</p>
                <p class="whitespace-pre-wrap">{{ proceso.nota_cierre }}</p>
              </div>
            </div>
          </div>
        </div>

        <form @submit.prevent="submit" class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
          <!-- Columna Principal -->
          <div class="lg:col-span-2 space-y-8">
            <fieldset :disabled="isClosed" :class="{ 'opacity-60': isClosed }">
              <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2 mb-4">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                  <span>Información del Proceso</span>
                </h3>
                <div class="border-t border-gray-200 dark:border-gray-700 pt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div class="md:col-span-2">
                    <InputLabel for="asunto" value="Asunto" />
                    <Textarea id="asunto" v-model="form.asunto" rows="2" class="mt-1 block w-full" />
                    <InputError :message="form.errors.asunto" class="mt-2" />
                  </div>
                  <div class="md:col-span-2">
                      <InputLabel for="juzgado" value="Juzgado / Entidad" />
                      <AsyncSelect id="juzgado" v-model="form.juzgado_id" :endpoint="route('juzgados.search')" :min-chars="3" placeholder="Buscar juzgado..." />
                      <InputError :message="form.errors.juzgado_id" class="mt-2" />
                  </div>
                  <div>
                      <InputLabel for="tipo_proceso" value="Tipo de Proceso" />
                      <AsyncSelect id="tipo_proceso" v-model="form.tipo_proceso_id" :endpoint="route('tipos-proceso.search')" placeholder="Buscar tipo..." />
                      <InputError :message="form.errors.tipo_proceso_id" class="mt-2" />
                  </div>
                  <div>
                      <InputLabel for="naturaleza" value="Naturaleza (opcional)" />
                      <TextInput id="naturaleza" v-model="form.naturaleza" class="mt-1 block w-full" />
                      <InputError :message="form.errors.naturaleza" class="mt-2" />
                  </div>
                  <div class="md:col-span-2">
                      <InputLabel for="ultima_actuacion" value="Última Actuación" />
                      <Textarea id="ultima_actuacion" v-model="form.ultima_actuacion" rows="4" class="mt-1 block w-full" />
                      <InputError :message="form.errors.ultima_actuacion" class="mt-2" />
                  </div>
                  <div class="md:col-span-2">
                      <InputLabel for="observaciones" value="Observaciones (opcional)" />
                      <Textarea id="observaciones" v-model="form.observaciones" rows="4" class="mt-1 block w-full" />
                      <InputError :message="form.errors.observaciones" class="mt-2" />
                  </div>
                </div>
              </div>
            </fieldset>
          </div>

          <!-- Columna Lateral -->
          <div class="lg:col-span-1">
            <fieldset :disabled="isClosed" :class="{ 'opacity-60': isClosed }">
              <div class="sticky top-8 space-y-6">
                <!-- Seguimiento y Fechas -->
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                   <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2 mb-4">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0h18" /></svg>
                      <span>Seguimiento y Fechas</span>
                  </h3>
                  <div class="border-t border-gray-200 dark:border-gray-700 pt-6 space-y-6">
                      <div>
                          <InputLabel for="radicado" value="Radicado" />
                          <TextInput id="radicado" v-model="form.radicado" class="mt-1 block w-full" />
                          <InputError :message="form.errors.radicado" class="mt-2" />
                      </div>
                      <div>
                          <InputLabel for="fecha_radicado" value="Fecha de Radicado" />
                          <TextInput id="fecha_radicado" v-model="form.fecha_radicado" type="date" class="mt-1 block w-full" />
                          <InputError :message="form.errors.fecha_radicado" class="mt-2" />
                      </div>
                       <div>
                          <InputLabel for="fecha_revision" value="Fecha de Revisión" />
                          <TextInput id="fecha_revision" v-model="form.fecha_revision" type="date" class="mt-1 block w-full" />
                          <InputError :message="form.errors.fecha_revision" class="mt-2" />
                      </div>
                      <div>
                          <InputLabel for="fecha_proxima_revision" value="Fecha Próxima Revisión" />
                          <TextInput id="fecha_proxima_revision" v-model="form.fecha_proxima_revision" type="date" class="mt-1 block w-full" />
                          <InputError :message="form.errors.fecha_proxima_revision" class="mt-2" />
                      </div>
                  </div>
                </div>

                <!-- Partes y Responsables -->
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                   <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2 mb-4">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197" /></svg>
                      <span>Partes y Responsables</span>
                  </h3>
                  <div class="border-t border-gray-200 dark:border-gray-700 pt-6 space-y-6">
                      <div>
                          <InputLabel for="demandante" value="Demandante / Denunciante" />
                          <AsyncSelect id="demandante" v-model="form.demandante_id" :endpoint="route('personas.search')" placeholder="Buscar persona..." />
                          <InputError :message="form.errors.demandante_id" class="mt-2" />
                      </div>
                      <div>
                          <InputLabel for="demandado" value="Demandado / Denunciado" />
                          <AsyncSelect id="demandado" v-model="form.demandado_id" :endpoint="route('personas.search')" placeholder="Buscar persona..." />
                          <InputError :message="form.errors.demandado_id" class="mt-2" />
                      </div>
                      <div>
                          <InputLabel for="abogado" value="Abogado / Gestor" />
                          <AsyncSelect id="abogado" v-model="form.abogado_id" :endpoint="route('users.search')" placeholder="Buscar abogado..." />
                          <InputError :message="form.errors.abogado_id" class="mt-2" />
                      </div>
                      <div>
                          <InputLabel for="responsable" value="Responsable de Revisión" />
                          <AsyncSelect id="responsable" v-model="form.responsable_revision_id" :endpoint="route('users.search')" placeholder="Buscar responsable..." />
                          <InputError :message="form.errors.responsable_revision_id" class="mt-2" />
                      </div>
                  </div>
                </div>

                <!-- Enlaces y Contacto -->
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                   <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 flex items-center gap-2 mb-4">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" /></svg>
                      <span>Enlaces y Contacto</span>
                  </h3>
                  <div class="border-t border-gray-200 dark:border-gray-700 pt-6 space-y-6">
                      <div>
                        <InputLabel for="link_expediente" value="Link de expediente digital" />
                        <TextInput id="link_expediente" v-model="form.link_expediente" type="url" class="mt-1 block w-full" placeholder="https://…" />
                        <InputError :message="form.errors.link_expediente" class="mt-2" />
                      </div>
                      <div>
                        <InputLabel for="ubicacion_drive" value="Ubicación en Drive" />
                        <TextInput id="ubicacion_drive" v-model="form.ubicacion_drive" type="url" class="mt-1 block w-full" placeholder="https://drive.google.com/…" />
                        <InputError :message="form.errors.ubicacion_drive" class="mt-2" />
                      </div>
                      <div>
                        <InputLabel for="correo_radicacion" value="Correo de radicación" />
                        <TextInput id="correo_radicacion" v-model="form.correo_radicacion" type="email" class="mt-1 block w-full" />
                        <InputError :message="form.errors.correo_radicacion" class="mt-2" />
                      </div>
                      <div>
                        <InputLabel for="correos_juzgado" value="Correos del juzgado" />
                        <TextInput id="correos_juzgado" v-model="form.correos_juzgado" class="mt-1 block w-full" />
                        <InputError :message="form.errors.correos_juzgado" class="mt-2" />
                      </div>
                  </div>
                </div>
              </div>
            </fieldset>
          </div>
        </form>
      </div>
    </div>

    <!-- MODAL DE CIERRE -->
    <Modal :show="showCloseModal" @close="showCloseModal = false">
      <div class="p-6">
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
          Cerrar Caso
        </h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
          Por favor, proporciona una nota de cierre para este caso. Esta acción no se puede deshacer.
        </p>
        <div class="mt-6">
          <InputLabel for="nota_cierre" value="Nota de Cierre" class="sr-only" />
          <Textarea
            id="nota_cierre"
            v-model="closeForm.nota_cierre"
            class="w-full"
            rows="4"
            placeholder="Explica por qué se cierra el caso (ej. 'Terminación del proceso por pago total de la obligación', 'Sentencia absolutoria', etc.)"
          />
          <InputError :message="closeForm.errors.nota_cierre" class="mt-2" />
        </div>
        <div class="mt-6 flex justify-end gap-3">
          <SecondaryButton @click="showCloseModal = false">Cancelar</SecondaryButton>
          <DangerButton
            @click="closeTheCase"
            :disabled="closeForm.processing"
            :class="{ 'opacity-25': closeForm.processing }"
          >
            {{ closeForm.processing ? 'Cerrando...' : 'Confirmar Cierre' }}
          </DangerButton>
        </div>
      </div>
    </Modal>

  </AuthenticatedLayout>
</template>

