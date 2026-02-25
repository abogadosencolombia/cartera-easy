<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
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
import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
  proceso: { type: Object, required: true },
  etapas: { type: Array, required: true }, // <--- Recibimos etapas
});

const showCloseModal = ref(false);
const closeForm = useForm({ nota_cierre: '' });

const openCloseModal = () => { closeForm.reset(); showCloseModal.value = true; };
const closeTheCase = () => {
  closeForm.patch(route('procesos.close', props.proceso.id), {
    preserveScroll: true,
    onSuccess: () => { showCloseModal.value = false; closeForm.reset(); },
  });
};

const formatDateForInput = (dateString) => dateString ? dateString.substring(0, 10) : '';
const mapToSelectOption = (obj, labelKey = 'nombre_completo') => obj ? { id: obj.id, label: obj[labelKey] } : null;

const loadPersonasArray = (personas) => {
    if (!personas || personas.length === 0) return [{ id: null, selected: null }];
    return personas.map(p => ({ id: p.id, selected: mapToSelectOption(p) }));
};

const form = useForm({
  _method: 'PATCH',
  abogado_id: mapToSelectOption(props.proceso.abogado, 'name'),
  responsable_revision_id: mapToSelectOption(props.proceso.responsable_revision, 'name'),
  juzgado_id: mapToSelectOption(props.proceso.juzgado, 'nombre'),
  tipo_proceso_id: mapToSelectOption(props.proceso.tipo_proceso, 'nombre'),
  etapa_procesal_id: props.proceso.etapa_procesal_id || '', // <--- Campo Nuevo

  demandantes: loadPersonasArray(props.proceso.demandantes),
  demandados: loadPersonasArray(props.proceso.demandados),

  radicado: props.proceso.radicado ?? '',
  fecha_radicado: formatDateForInput(props.proceso.fecha_radicado),
  fecha_revision: formatDateForInput(props.proceso.fecha_revision),
  fecha_proxima_revision: formatDateForInput(props.proceso.fecha_proxima_revision),
  naturaleza: props.proceso.naturaleza ?? '',
  asunto: props.proceso.asunto ?? '',
  correo_radicacion: props.proceso.correo_radicacion ?? '',
  link_expediente: props.proceso.link_expediente ?? '',
  ubicacion_drive: props.proceso.ubicacion_drive ?? '',
  correos_juzgado: props.proceso.correos_juzgado ?? '',
  observaciones: props.proceso.observaciones ?? '',
});

const addDemandante = () => form.demandantes.push({ id: null, selected: null });
const removeDemandante = (index) => { if (form.demandantes.length > 1) form.demandantes.splice(index, 1); };
const addDemandado = () => form.demandados.push({ id: null, selected: null });
const removeDemandado = (index) => { if (form.demandados.length > 1) form.demandados.splice(index, 1); };

const submit = () => {
  const payload = {
    ...form.data(),
    abogado_id: form.abogado_id?.id ?? null,
    responsable_revision_id: form.responsable_revision_id?.id ?? null,
    juzgado_id: form.juzgado_id?.id ?? null,
    tipo_proceso_id: form.tipo_proceso_id?.id ?? null,
    demandantes: form.demandantes.map(d => d.selected?.id).filter(id => id),
    demandados: form.demandados.map(d => d.selected?.id).filter(id => id),
  };
  
  router.post(route('procesos.update', props.proceso.id), payload, { preserveScroll: true });
};

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
            <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-bold" :class="!isClosed ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300'">
              {{ proceso.estado }}
            </span>
          </div>
          <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Actualiza la información y el estado del proceso.</p>
        </div>
        <div class="flex items-center gap-3">
          <Link :href="route('procesos.show', proceso.id)"><SecondaryButton>Cancelar</SecondaryButton></Link>
          <PrimaryButton @click="submit" :disabled="form.processing || isClosed" :class="{ 'opacity-25': form.processing || isClosed }">
            {{ form.processing ? 'Guardando…' : 'Guardar Cambios' }}
          </PrimaryButton>
          <DangerButton v-if="!isClosed" @click="openCloseModal">Cerrar Caso</DangerButton>
        </div>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div v-if="isClosed" class="bg-yellow-50 dark:bg-gray-800 border-l-4 border-yellow-400 p-4 mb-8 rounded-r-lg">
          <div class="flex"><div class="ml-3"><p class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Este caso está cerrado.</p></div></div>
        </div>

        <form @submit.prevent="submit" class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
          <div class="lg:col-span-2 space-y-8">
            <fieldset :disabled="isClosed" :class="{ 'opacity-60': isClosed }">
              <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Información del Proceso</h3>
                <div class="border-t border-gray-200 dark:border-gray-700 pt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                  
                  <div class="md:col-span-2 space-y-3">
                        <div class="flex justify-between items-center">
                            <InputLabel value="Demandantes / Denunciantes" />
                            <button type="button" @click="addDemandante" class="text-xs text-indigo-600 hover:text-indigo-800 font-semibold flex items-center"><PlusIcon class="w-3 h-3 mr-1"/> Agregar</button>
                        </div>
                        <div v-for="(item, index) in form.demandantes" :key="index" class="flex gap-2">
                            <div class="flex-grow"><AsyncSelect v-model="item.selected" :endpoint="route('personas.search')" placeholder="Buscar persona..." /></div>
                            <button v-if="form.demandantes.length > 1" type="button" @click="removeDemandante(index)" class="text-red-500 hover:text-red-700"><TrashIcon class="w-5 h-5"/></button>
                        </div>
                  </div>

                  <div class="md:col-span-2 space-y-3">
                        <div class="flex justify-between items-center">
                            <InputLabel value="Demandados / Denunciados" />
                            <button type="button" @click="addDemandado" class="text-xs text-indigo-600 hover:text-indigo-800 font-semibold flex items-center"><PlusIcon class="w-3 h-3 mr-1"/> Agregar</button>
                        </div>
                        <div v-for="(item, index) in form.demandados" :key="index" class="flex gap-2">
                            <div class="flex-grow"><AsyncSelect v-model="item.selected" :endpoint="route('personas.search')" placeholder="Buscar persona..." /></div>
                            <button v-if="form.demandados.length > 1" type="button" @click="removeDemandado(index)" class="text-red-500 hover:text-red-700"><TrashIcon class="w-5 h-5"/></button>
                        </div>
                  </div>

                  <div class="md:col-span-2"><InputLabel for="asunto" value="Asunto" /><Textarea id="asunto" v-model="form.asunto" rows="2" class="mt-1 block w-full" /></div>
                  <div class="md:col-span-2"><InputLabel for="juzgado" value="Juzgado / Entidad" /><AsyncSelect id="juzgado" v-model="form.juzgado_id" :endpoint="route('juzgados.search')" :min-chars="3" placeholder="Buscar juzgado..." /><InputError :message="form.errors.juzgado_id" class="mt-2" /></div>
                  
                  <div>
                      <InputLabel for="tipo_proceso" value="Tipo de Proceso" />
                      <AsyncSelect id="tipo_proceso" v-model="form.tipo_proceso_id" :endpoint="route('tipos-proceso.search')" placeholder="Buscar tipo..." />
                      <InputError :message="form.errors.tipo_proceso_id" class="mt-2" />
                  </div>

                  <!-- CAMPO ETAPA -->
                  <div>
                      <InputLabel for="etapa" value="Etapa Procesal" />
                      <select id="etapa" v-model="form.etapa_procesal_id" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                          <option value="" disabled>Seleccione etapa...</option>
                          <option v-for="etapa in etapas" :key="etapa.id" :value="etapa.id">
                              {{ etapa.nombre }}
                          </option>
                      </select>
                      <InputError :message="form.errors.etapa_procesal_id" class="mt-2" />
                  </div>

                  <div><InputLabel for="naturaleza" value="Naturaleza (opcional)" /><TextInput id="naturaleza" v-model="form.naturaleza" class="mt-1 block w-full" /></div>
                  <div class="md:col-span-2"><InputLabel for="observaciones" value="Observaciones (opcional)" /><Textarea id="observaciones" v-model="form.observaciones" rows="4" class="mt-1 block w-full" /></div>
                </div>
              </div>
            </fieldset>
          </div>

          <!-- Columna Lateral -->
          <div class="lg:col-span-1">
            <fieldset :disabled="isClosed" :class="{ 'opacity-60': isClosed }">
              <div class="sticky top-8 space-y-6">
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                  <h3 class="text-lg font-medium mb-4">Seguimiento y Fechas</h3>
                  <div class="border-t pt-6 space-y-6">
                      <div><InputLabel for="radicado" value="Radicado" /><TextInput id="radicado" v-model="form.radicado" class="mt-1 block w-full" /><InputError :message="form.errors.radicado" class="mt-2" /></div>
                      <div><InputLabel for="fecha_radicado" value="Fecha de Radicado" /><TextInput id="fecha_radicado" v-model="form.fecha_radicado" type="date" class="mt-1 block w-full" /></div>
                      <div><InputLabel for="fecha_revision" value="Fecha de Revisión" /><TextInput id="fecha_revision" v-model="form.fecha_revision" type="date" class="mt-1 block w-full" /></div>
                      <div><InputLabel for="fecha_proxima_revision" value="Fecha Próxima Revisión" /><TextInput id="fecha_proxima_revision" v-model="form.fecha_proxima_revision" type="date" class="mt-1 block w-full" /></div>
                      <div><InputLabel for="abogado" value="Abogado / Gestor" /><AsyncSelect id="abogado" v-model="form.abogado_id" :endpoint="route('users.search')" /></div>
                      <div><InputLabel for="responsable" value="Responsable de Revisión" /><AsyncSelect id="responsable" v-model="form.responsable_revision_id" :endpoint="route('users.search')" /></div>
                      <div><InputLabel for="correo_radicacion" value="Correo de radicación" /><TextInput id="correo_radicacion" v-model="form.correo_radicacion" class="mt-1 block w-full" /></div>
                      <div><InputLabel for="correos_juzgado" value="Correos Juzgado" /><TextInput id="correos_juzgado" v-model="form.correos_juzgado" class="mt-1 block w-full" /></div>
                      <div><InputLabel for="link_expediente" value="Link Expediente" /><TextInput id="link_expediente" v-model="form.link_expediente" class="mt-1 block w-full" /></div>
                      <div><InputLabel for="ubicacion_drive" value="Ubicación Drive" /><TextInput id="ubicacion_drive" v-model="form.ubicacion_drive" class="mt-1 block w-full" /></div>
                  </div>
                </div>
              </div>
            </fieldset>
          </div>
        </form>
      </div>
    </div>
    
    <Modal :show="showCloseModal" @close="showCloseModal = false">
       <div class="p-6">
         <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Cerrar Caso</h2>
         <p class="mt-1 text-sm text-gray-600">Nota de cierre.</p>
         <div class="mt-6"><Textarea v-model="closeForm.nota_cierre" class="w-full" rows="4" /></div>
         <div class="mt-6 flex justify-end gap-3"><SecondaryButton @click="showCloseModal = false">Cancelar</SecondaryButton><DangerButton @click="closeTheCase">Confirmar</DangerButton></div>
       </div>
    </Modal>
  </AuthenticatedLayout>
</template>