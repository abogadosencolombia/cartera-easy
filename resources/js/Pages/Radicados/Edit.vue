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
import DatePicker from '@/Components/DatePicker.vue';
import Textarea from '@/Components/Textarea.vue';
import AsyncSelect from '@/Components/AsyncSelect.vue';
import Modal from '@/Components/Modal.vue';
import Dropdown from '@/Components/Dropdown.vue';
import { PlusIcon, TrashIcon, ChevronDownIcon } from '@heroicons/vue/24/outline';

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
    if (!personas || personas.length === 0) return [{ 
        id: null, selected: null, is_new: false, nombre_completo: '', tipo_documento: 'CC', numero_documento: '', sin_info: false 
    }];
    return personas.map(p => {
        const isIncomplete = p.nombre_completo === 'DEMANDADO POR IDENTIFICAR';
        return { 
            id: p.id, 
            selected: mapToSelectOption(p), 
            is_new: isIncomplete, // Si es incompleto, activamos modo registro rápido para corregir
            nombre_completo: p.nombre_completo, 
            tipo_documento: p.tipo_documento, 
            numero_documento: p.numero_documento,
            sin_info: isIncomplete
        };
    });
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

const addDemandante = () => form.demandantes.push({ 
    id: null, selected: null, is_new: false, nombre_completo: '', tipo_documento: 'CC', numero_documento: '', sin_info: false 
});
const removeDemandante = (index) => { if (form.demandantes.length > 1) form.demandantes.splice(index, 1); };
const addDemandado = () => form.demandados.push({ 
    id: null, selected: null, is_new: false, nombre_completo: '', tipo_documento: 'CC', numero_documento: '', sin_info: false 
});
const removeDemandado = (index) => { if (form.demandados.length > 1) form.demandados.splice(index, 1); };

const submit = () => {
  const payload = {
    ...form.data(),
    abogado_id: form.abogado_id?.id ?? null,
    responsable_revision_id: form.responsable_revision_id?.id ?? null,
    juzgado_id: form.juzgado_id?.id ?? null,
    tipo_proceso_id: form.tipo_proceso_id?.id ?? null,
    
    // Enviar objetos completos
    demandantes: form.demandantes.map(d => {
        if (d.is_new) return d;
        return { id: d.selected?.id };
    }).filter(d => d.id || d.is_new),
    
    demandados: form.demandados.map(d => {
        if (d.is_new) return d;
        return { id: d.selected?.id };
    }).filter(d => d.id || d.is_new),
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
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4">Información del Proceso</h3>
                <div class="border-t border-gray-200 dark:border-gray-700 pt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                  
                  <div class="md:col-span-2 space-y-4">
                        <div class="flex justify-between items-center">
                            <InputLabel value="Demandantes / Denunciantes" class="!text-indigo-700 dark:!text-indigo-400 font-bold" />
                            <button type="button" @click="addDemandante" class="text-xs bg-indigo-50 text-indigo-600 hover:bg-indigo-100 px-2 py-1 rounded-md font-semibold flex items-center transition">
                                <PlusIcon class="w-3 h-3 mr-1"/> Agregar otro
                            </button>
                        </div>
                        <div v-for="(item, index) in form.demandantes" :key="index" class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50/50 dark:bg-gray-900/20 space-y-3 relative group">
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Demandante #{{ index + 1 }}</span>
                                <div class="flex items-center gap-3">
                                    <button type="button" @click="item.is_new = !item.is_new" class="text-[10px] font-bold uppercase" :class="item.is_new ? 'text-blue-600' : 'text-green-600'">
                                        {{ item.is_new ? '← Buscar en base de datos' : '+ Registrar como nuevo' }}
                                    </button>
                                    <button v-if="form.demandantes.length > 1" type="button" @click="removeDemandante(index)" class="text-red-400 hover:text-red-600 transition">
                                        <TrashIcon class="w-4 h-4"/>
                                    </button>
                                </div>
                            </div>

                            <div v-if="!item.is_new">
                                <AsyncSelect v-model="item.selected" :endpoint="route('personas.search')" placeholder="Buscar por nombre o documento..." />
                            </div>

                            <div v-else class="space-y-3 animate-in fade-in slide-in-from-top-1 duration-200">
                                <TextInput v-model="item.nombre_completo" placeholder="Nombre Completo *" class="text-sm w-full" />
                                <div class="grid grid-cols-3 gap-2">
                                    <select v-model="item.tipo_documento" class="text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                                        <option>CC</option><option>NIT</option><option>CE</option><option>TI</option><option>Pasaporte</option>
                                    </select>
                                    <TextInput v-model="item.numero_documento" placeholder="Número Documento *" class="text-sm col-span-2" />
                                </div>
                            </div>
                        </div>
                  </div>

                  <div class="md:col-span-2 space-y-4">
                        <div class="flex justify-between items-center">
                            <InputLabel value="Demandados / Denunciados" class="!text-red-700 dark:!text-red-400 font-bold" />
                            <button type="button" @click="addDemandado" class="text-xs bg-red-50 text-red-600 hover:bg-red-100 px-2 py-1 rounded-md font-semibold flex items-center transition">
                                <PlusIcon class="w-3 h-3 mr-1"/> Agregar otro
                            </button>
                        </div>
                        <div v-for="(item, index) in form.demandados" :key="index" class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50/50 dark:bg-gray-900/20 space-y-3 relative group">
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Demandado #{{ index + 1 }}</span>
                                <div class="flex items-center gap-3">
                                    <button type="button" @click="item.is_new = !item.is_new" class="text-[10px] font-bold uppercase" :class="item.is_new ? 'text-blue-600' : 'text-green-600'">
                                        {{ item.is_new ? '← Buscar en base de datos' : '+ Registrar como nuevo' }}
                                    </button>
                                    <button v-if="form.demandados.length > 1" type="button" @click="removeDemandado(index)" class="text-red-400 hover:text-red-600 transition">
                                        <TrashIcon class="w-4 h-4"/>
                                    </button>
                                </div>
                            </div>

                            <div v-if="!item.is_new">
                                <AsyncSelect v-model="item.selected" :endpoint="route('personas.search')" placeholder="Buscar por nombre o documento..." />
                            </div>

                            <div v-else class="space-y-3 animate-in fade-in slide-in-from-top-1 duration-200">
                                <label class="flex items-center gap-2 cursor-pointer mb-2">
                                    <input type="checkbox" v-model="item.sin_info" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 shadow-sm transition" />
                                    <span class="text-xs font-bold text-amber-600 dark:text-amber-400 uppercase tracking-tight">Sin información completa</span>
                                </label>

                                <TextInput v-model="item.nombre_completo" :placeholder="item.sin_info ? 'Nombre (Opcional)' : 'Nombre Completo *'" class="text-sm w-full" />
                                
                                <div v-if="!item.sin_info" class="grid grid-cols-3 gap-2">
                                    <select v-model="item.tipo_documento" class="text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                                        <option>CC</option><option>NIT</option><option>CE</option><option>TI</option><option>Pasaporte</option>
                                    </select>
                                    <TextInput v-model="item.numero_documento" placeholder="Número Documento *" class="text-sm col-span-2" />
                                </div>
                            </div>
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
                      <Dropdown align="left" width="full">
                          <template #trigger>
                              <button type="button" class="mt-1 flex w-full items-center justify-between gap-2 rounded-md border border-gray-300 dark:border-gray-700 dark:bg-gray-900 bg-white px-3 py-2 text-sm shadow-sm hover:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-all cursor-pointer dark:text-gray-300">
                                  <span>{{ form.etapa_procesal_id ? etapas.find(e => e.id === form.etapa_procesal_id)?.nombre : 'Seleccione etapa...' }}</span>
                                  <ChevronDownIcon class="h-4 w-4 text-gray-400" />
                              </button>
                          </template>
                          <template #content>
                              <div class="py-1 bg-white dark:bg-gray-800 max-h-60 overflow-y-auto">
                                  <button v-for="etapa in etapas" :key="etapa.id" @click="form.etapa_procesal_id = etapa.id" class="block w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700" :class="{ 'bg-indigo-50 dark:bg-indigo-900/20 text-indigo-700 dark:text-indigo-400 font-bold': form.etapa_procesal_id === etapa.id }">
                                      {{ etapa.nombre }}
                                  </button>
                              </div>
                          </template>
                      </Dropdown>
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
                  <h3 class="text-lg font-bold mb-4">Seguimiento y Fechas</h3>
                  <div class="border-t pt-6 space-y-6">
                      <div><InputLabel for="radicado" value="Radicado" /><TextInput id="radicado" v-model="form.radicado" class="mt-1 block w-full" /><InputError :message="form.errors.radicado" class="mt-2" /></div>
                      <div><InputLabel for="fecha_radicado" value="Fecha de Radicado" /><DatePicker id="fecha_radicado" v-model="form.fecha_radicado" class="mt-1 block w-full" /></div>
                      <div><InputLabel for="fecha_revision" value="Fecha de Revisión" /><DatePicker id="fecha_revision" v-model="form.fecha_revision" class="mt-1 block w-full" /></div>
                      <div><InputLabel for="fecha_proxima_revision" value="Fecha Próxima Revisión" /><DatePicker id="fecha_proxima_revision" v-model="form.fecha_proxima_revision" class="mt-1 block w-full" /></div>
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
