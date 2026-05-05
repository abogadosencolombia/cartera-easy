<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import DatePicker from '@/Components/DatePicker.vue';
import Textarea from '@/Components/Textarea.vue';
import SelectInput from '@/Components/SelectInput.vue';
import AsyncSelect from '@/Components/AsyncSelect.vue';
import Modal from '@/Components/Modal.vue';
import Dropdown from '@/Components/Dropdown.vue';
import { formatRadicado, addDaysToDate, addMonthsToDate, toUpperCase, calculateDV } from '@/Utils/formatters';
import { PlusIcon, TrashIcon, ChevronDownIcon, CalendarDaysIcon, HandThumbUpIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
  proceso: { type: Object, required: true },
  etapas: { type: Array, required: true },
});

const searchEtapa = ref('');
const filteredEtapas = computed(() => {
    return props.etapas.filter(e => 
        e.nombre.toLowerCase().includes(searchEtapa.value.toLowerCase())
    );
});

const handleRadicadoInput = (e) => {
    const formatted = formatRadicado(e.target.value);
    form.radicado = formatted;
    e.target.value = formatted;
};

const addDays = (field, days) => {
    form[field] = addDaysToDate(form[field], days);
};

const addMonths = (field, months) => {
    form[field] = addMonthsToDate(form[field], months);
};

// --- LÓGICA DE COMPROMISO DE REVISIÓN (OBLIGATORIO) ---
const showCommitmentModal = ref(false);
const globalRevisionDate = ref(props.proceso.fecha_proxima_revision || '');
let isCommitted = false;

const openCommitmentModal = () => {
    const today = new Date().toISOString().split('T')[0];
    if (!globalRevisionDate.value || globalRevisionDate.value < today) {
        globalRevisionDate.value = today;
    }
    showCommitmentModal.value = true;
};

const executeCommittedAction = () => {
    if (!globalRevisionDate.value) return;
    showCommitmentModal.value = false;
    isCommitted = true;
    form.fecha_proxima_revision = globalRevisionDate.value;
    submit(); 
};
// -----------------------------------------------------

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
const mapToSelectOption = (obj, labelKey = 'nombre_completo') => obj ? { id: obj.id, [labelKey]: obj[labelKey] } : null;

const loadPersonasArray = (personas) => {
    if (!personas || personas.length === 0) return [{ 
        id: null, selected: null, is_new: false, nombre_completo: '', tipo_documento: 'CC', numero_documento: '', sin_info: false, cooperativas_ids: [], abogados_ids: []
    }];
    return personas.map(p => {
        const isIncomplete = p.nombre_completo === 'DEMANDADO POR IDENTIFICAR' || p.nombre_completo === 'DEMANDANTE POR IDENTIFICAR' || p.nombre_completo === 'PERSONA INDETERMINADA';
        return { 
            id: p.id, 
            selected: mapToSelectOption(p), 
            is_new: isIncomplete, 
            nombre_completo: p.nombre_completo, 
            tipo_documento: p.tipo_documento, 
            numero_documento: p.numero_documento,
            dv: p.dv || '',
            sin_info: isIncomplete,
            cooperativas_ids: p.cooperativas ? p.cooperativas.map(c => ({ id: c.id, nombre: c.nombre })) : [],
            abogados_ids: p.abogados ? p.abogados.map(a => ({ id: a.id, name: a.name })) : []
        };
    });
};

const form = useForm(`EditRadicado:${props.proceso.id}`, {
  _method: 'PATCH',
  abogado_id: mapToSelectOption(props.proceso.abogado, 'name'),
  responsable_revision_id: mapToSelectOption(props.proceso.responsable_revision, 'name'),
  juzgado_id: mapToSelectOption(props.proceso.juzgado, 'nombre'),
  tipo_proceso_id: mapToSelectOption(props.proceso.tipo_proceso, 'nombre'),
  etapa_procesal_id: props.proceso.etapa_procesal_id || '', 
  a_favor_de: props.proceso.a_favor_de || 'DEMANDANTE',

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

// --- AUTO-FORMATO ---
watch(() => form.demandantes, (newVal) => {
    newVal.forEach(d => {
        if (d.is_new && d.tipo_documento === 'NIT' && d.numero_documento) {
            d.dv = calculateDV(d.numero_documento).toString();
        }
    });
}, { deep: true });

watch(() => form.demandados, (newVal) => {
    newVal.forEach(d => {
        if (d.is_new && d.tipo_documento === 'NIT' && d.numero_documento) {
            d.dv = calculateDV(d.numero_documento).toString();
        }
    });
}, { deep: true });

const addDemandante = () => form.demandantes.push({ 
    id: null, selected: null, is_new: false, nombre_completo: '', tipo_documento: 'CC', numero_documento: '', sin_info: false, cooperativas_ids: [], abogados_ids: []
});
const removeDemandante = (index) => { if (form.demandantes.length > 1) form.demandantes.splice(index, 1); };
const addDemandado = () => form.demandados.push({ 
    id: null, selected: null, is_new: false, nombre_completo: '', tipo_documento: 'CC', numero_documento: '', sin_info: false, cooperativas_ids: [], abogados_ids: []
});
const removeDemandado = (index) => { if (form.demandados.length > 1) form.demandados.splice(index, 1); };

const submit = () => {
  // OBLIGATORIEDAD: Si no han pasado por el modal de compromiso, se abre antes de enviar.
  if (!isCommitted) {
      openCommitmentModal();
      return;
  }

  form.transform(data => ({
    ...data,
    abogado_id: data.abogado_id?.id ?? null,
    responsable_revision_id: data.responsable_revision_id?.id ?? null,
    juzgado_id: data.juzgado_id?.id ?? null,
    tipo_proceso_id: data.tipo_proceso_id?.id ?? null,
    
    demandantes: data.demandantes.map(d => {
        if (d.is_new) return {
            id: d.id,
            nombre_completo: d.nombre_completo,
            tipo_documento: d.tipo_documento,
            numero_documento: d.numero_documento,
            dv: d.dv,
            sin_info: d.sin_info,
            cooperativas_ids: Array.isArray(d.cooperativas_ids) ? d.cooperativas_ids.map(c => c.id || c) : [],
            abogados_ids: Array.isArray(d.abogados_ids) ? d.abogados_ids.map(a => a.id || a) : [],
            is_new: true
        };
        return { id: d.selected?.id };
    }).filter(d => d.id || d.is_new),
    
    demandados: data.demandados.map(d => {
        if (d.is_new) return {
            id: d.id,
            nombre_completo: d.nombre_completo,
            tipo_documento: d.tipo_documento,
            numero_documento: d.numero_documento,
            dv: d.dv,
            sin_info: d.sin_info,
            cooperativas_ids: Array.isArray(d.cooperativas_ids) ? d.cooperativas_ids.map(c => c.id || c) : [],
            abogados_ids: Array.isArray(d.abogados_ids) ? d.abogados_ids.map(a => a.id || a) : [],
            is_new: true
        };
        return { id: d.selected?.id };
    }).filter(d => d.id || d.is_new),
  })).post(route('procesos.update', props.proceso.id), { preserveScroll: true });
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
          <Link :href="route('procesos.show', props.proceso.id)"><SecondaryButton>Cancelar</SecondaryButton></Link>
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

        <form @submit.prevent="submit" class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start text-gray-900 dark:text-gray-100">
          <div class="lg:col-span-2 space-y-8">
            <fieldset :disabled="isClosed" :class="{ 'opacity-60': isClosed }">
              <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 border border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-bold mb-4">Información del Proceso</h3>
                
                <!-- A Favor De Selection -->
                <div class="mb-8 p-4 bg-indigo-50 dark:bg-indigo-900/10 rounded-2xl border border-indigo-100 dark:border-indigo-800">
                    <InputLabel value="Representamos a Favor de:" class="text-xs font-bold text-indigo-700 dark:text-indigo-300 uppercase mb-3 ml-1" />
                    <div class="flex bg-white dark:bg-gray-900 p-1 rounded-xl border border-indigo-100 dark:border-indigo-800 max-w-md">
                        <button 
                            type="button" 
                            @click="form.a_favor_de = 'DEMANDANTE'"
                            :class="form.a_favor_de === 'DEMANDANTE' ? 'bg-indigo-600 text-white shadow-md' : 'text-gray-500 hover:text-indigo-600'"
                            class="flex-1 py-2 text-[10px] font-black uppercase rounded-lg transition-all flex items-center justify-center gap-2"
                        >
                            <HandThumbUpIcon v-if="form.a_favor_de === 'DEMANDANTE'" class="w-3 h-3" /> Demandante
                        </button>
                        <button 
                            type="button" 
                            @click="form.a_favor_de = 'DEMANDADO'"
                            :class="form.a_favor_de === 'DEMANDADO' ? 'bg-red-600 text-white shadow-md' : 'text-gray-500 hover:text-red-600'"
                            class="flex-1 py-2 text-[10px] font-black uppercase rounded-lg transition-all flex items-center justify-center gap-2"
                        >
                            <HandThumbUpIcon v-if="form.a_favor_de === 'DEMANDADO'" class="w-3 h-3" /> Demandado
                        </button>
                    </div>
                    <InputError :message="form.errors.a_favor_de" class="mt-2" />
                </div>

                <div class="border-t border-gray-200 dark:border-gray-700 pt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                  
                  <div class="md:col-span-2 space-y-4">
                        <div class="flex justify-between items-center">
                            <InputLabel value="Demandantes / Denunciantes" class="!text-indigo-700 dark:!text-indigo-400 font-bold" />
                            <button type="button" @click="addDemandante" class="text-xs bg-indigo-50 text-indigo-600 hover:bg-indigo-100 px-2 py-1 rounded-md font-semibold flex items-center transition">
                                <PlusIcon class="w-3 h-3 mr-1"/> Agregar otro
                            </button>
                        </div>
                        <InputError :message="form.errors.demandantes" />
                        <div v-for="(item, index) in form.demandantes" :key="index" class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50/50 dark:bg-gray-900/20 space-y-3 relative group">
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Demandante #{{ index + 1 }}</span>
                                <div class="flex items-center gap-3">
                                    <button type="button" @click="item.is_new = !item.is_new" class="text-[10px] font-bold uppercase" :class="item.is_new ? 'text-blue-600' : 'text-green-600'">
                                        {{ item.is_new ? '← Buscar' : '+ Nuevo' }}
                                    </button>
                                    <button v-if="form.demandantes.length > 1" type="button" @click="removeDemandante(index)" class="text-red-400 hover:text-red-600 transition">
                                        <TrashIcon class="w-4 h-4"/>
                                    </button>
                                </div>
                            </div>
                            <div v-if="!item.is_new">
                                <AsyncSelect v-model="item.selected" :endpoint="route('personas.search')" placeholder="Buscar persona..." label-key="nombre_completo" />
                            </div>
                            <div v-else class="space-y-3 animate-in fade-in slide-in-from-top-1">
                                <label class="flex items-center gap-2 cursor-pointer mb-2">
                                    <input type="checkbox" v-model="item.sin_info" class="rounded border-gray-300 text-indigo-600 shadow-sm" />
                                    <span class="text-[10px] font-bold text-amber-600 uppercase">Sin info completa (Demandante por identificar)</span>
                                </label>
                                <TextInput v-model="item.nombre_completo" @blur="item.nombre_completo = toUpperCase(item.nombre_completo)" placeholder="Nombre Completo *" class="text-sm w-full" />
                                <InputError :message="form.errors[`demandantes.${index}.nombre_completo`]" />
                                <div v-if="!item.sin_info" class="grid grid-cols-3 gap-2 items-end">
                                    <SelectInput v-model="item.tipo_documento" class="text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 shadow-sm h-[42px]">
                                        <option>CC</option>
                                        <option>NIT</option>
                                    </SelectInput>
                                    <div :class="item.tipo_documento === 'NIT' ? 'col-span-2 grid grid-cols-4 gap-2' : 'col-span-2'">
                                        <div :class="item.tipo_documento === 'NIT' ? 'col-span-3' : ''">
                                            <TextInput v-model="item.numero_documento" placeholder="Número *" class="text-sm w-full" />
                                        </div>
                                        <div v-if="item.tipo_documento === 'NIT'">
                                            <TextInput v-model="item.dv" maxlength="1" placeholder="DV" class="text-sm w-full text-center px-0 h-[42px]" />
                                        </div>
                                    </div>
                                </div>
                                <InputError :message="form.errors[`demandantes.${index}.numero_documento`]" />
                                <div class="space-y-2">
                                    <InputLabel value="Asignar empresas * (Mín. 1)" class="text-[10px] font-black uppercase text-gray-400" />
                                    <AsyncSelect v-model="item.cooperativas_ids" :endpoint="route('cooperativas.search')" placeholder="Seleccione..." multiple label-key="nombre" />
                                    <InputError :message="form.errors[`demandantes.${index}.cooperativas_ids`]" />
                                    <AsyncSelect v-model="item.abogados_ids" :endpoint="route('users.search')" placeholder="Asignar abogados..." multiple label-key="name" />
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
                        <InputError :message="form.errors.demandados" />
                        <div v-for="(item, index) in form.demandados" :key="index" class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50/50 dark:bg-gray-900/20 space-y-3 relative group">
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Demandado #{{ index + 1 }}</span>
                                <div class="flex items-center gap-3">
                                    <button type="button" @click="item.is_new = !item.is_new" class="text-[10px] font-bold uppercase" :class="item.is_new ? 'text-blue-600' : 'text-green-600'">
                                        {{ item.is_new ? '← Buscar' : '+ Nuevo' }}
                                    </button>
                                    <button v-if="form.demandados.length > 1" type="button" @click="removeDemandado(index)" class="text-red-400 hover:text-red-600 transition">
                                        <TrashIcon class="w-4 h-4"/>
                                    </button>
                                </div>
                            </div>
                            <div v-if="!item.is_new">
                                <AsyncSelect v-model="item.selected" :endpoint="route('personas.search')" placeholder="Buscar persona..." label-key="nombre_completo" />
                            </div>
                            <div v-else class="space-y-3 animate-in fade-in slide-in-from-top-1">
                                <label class="flex items-center gap-2 cursor-pointer mb-2">
                                    <input type="checkbox" v-model="item.sin_info" class="rounded border-gray-300 text-indigo-600 shadow-sm" />
                                    <span class="text-[10px] font-bold text-amber-600 uppercase">Sin info completa</span>
                                </label>
                                <TextInput v-model="item.nombre_completo" @blur="item.nombre_completo = toUpperCase(item.nombre_completo)" placeholder="Nombre Completo *" class="text-sm w-full" />
                                <InputError :message="form.errors[`demandados.${index}.nombre_completo`]" />
                                <div v-if="!item.sin_info" class="grid grid-cols-3 gap-2 items-end">
                                    <SelectInput v-model="item.tipo_documento" class="text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 shadow-sm h-[42px]">
                                        <option>CC</option>
                                        <option>NIT</option>
                                    </SelectInput>
                                    <div :class="item.tipo_documento === 'NIT' ? 'col-span-2 grid grid-cols-4 gap-2' : 'col-span-2'">
                                        <div :class="item.tipo_documento === 'NIT' ? 'col-span-3' : ''">
                                            <TextInput v-model="item.numero_documento" placeholder="Número *" class="text-sm w-full" />
                                        </div>
                                        <div v-if="item.tipo_documento === 'NIT'">
                                            <TextInput v-model="item.dv" maxlength="1" placeholder="DV" class="text-sm w-full text-center px-0 h-[42px]" />
                                        </div>
                                    </div>
                                </div>
                                <InputError :message="form.errors[`demandados.${index}.numero_documento`]" />
                                <div class="space-y-2">
                                    <InputLabel value="Asignar empresas * (Mín. 1)" class="text-[10px] font-black uppercase text-gray-400" />
                                    <AsyncSelect v-model="item.cooperativas_ids" :endpoint="route('cooperativas.search')" placeholder="Seleccione..." multiple label-key="nombre" />
                                    <InputError :message="form.errors[`demandados.${index}.cooperativas_ids`]" />
                                    <AsyncSelect v-model="item.abogados_ids" :endpoint="route('users.search')" placeholder="Asignar abogados..." multiple label-key="name" />
                                </div>
                            </div>
                        </div>
                  </div>

                  <div class="md:col-span-2">
                      <InputLabel value="Asunto" />
                      <Textarea v-model="form.asunto" rows="2" class="mt-1 block w-full" />
                      <InputError :message="form.errors.asunto" />
                  </div>
                  <div class="md:col-span-2">
                      <InputLabel value="Juzgado / Entidad" />
                      <AsyncSelect v-model="form.juzgado_id" :endpoint="route('juzgados.search')" placeholder="Buscar juzgado..." label-key="nombre" />
                      <InputError :message="form.errors.juzgado_id" />
                  </div>
                  
                  <div>
                      <InputLabel value="Tipo de Proceso" />
                      <AsyncSelect v-model="form.tipo_proceso_id" :endpoint="route('tipos-proceso.search')" placeholder="Buscar tipo..." label-key="nombre" />
                      <InputError :message="form.errors.tipo_proceso_id" />
                  </div>
                  
                  <div>
                      <InputLabel value="Etapa Procesal" />
                      <Dropdown align="left" width="full">
                          <template #trigger><button type="button" class="mt-1 flex w-full justify-between items-center border border-gray-300 dark:border-gray-700 rounded-md p-2 text-sm dark:bg-gray-900"><span>{{ form.etapa_procesal_id ? etapas.find(e => e.id === form.etapa_procesal_id)?.nombre : 'Seleccione etapa...' }}</span><ChevronDownIcon class="h-4 w-4 text-gray-400" /></button></template>
                          <template #content>
                              <div class="p-2 border-b border-gray-100 dark:border-gray-700 sticky top-0 bg-white dark:bg-gray-800 z-10">
                                  <TextInput v-model="searchEtapa" placeholder="Buscar etapa..." class="w-full text-xs" @click.stop />
                              </div>
                              <div class="py-1 bg-white dark:bg-gray-800 max-h-60 overflow-y-auto">
                                  <button type="button" v-for="e in filteredEtapas" :key="e.id" @click="form.etapa_procesal_id = e.id" class="block w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">
                                      {{ e.nombre }}
                                  </button>
                                  <div v-if="filteredEtapas.length === 0" class="p-4 text-xs text-gray-500 text-center">No hay resultados</div>
                              </div>
                          </template>
                      </Dropdown>
                      <InputError :message="form.errors.etapa_procesal_id" />
                  </div>

                  <div><InputLabel value="Naturaleza" /><TextInput v-model="form.naturaleza" class="mt-1 block w-full" /><InputError :message="form.errors.naturaleza" /></div>
                  <div class="md:col-span-2"><InputLabel value="Observaciones" /><Textarea v-model="form.observaciones" rows="4" class="mt-1 block w-full" /><InputError :message="form.errors.observaciones" /></div>
                </div>
              </div>
            </fieldset>
          </div>

          <div class="lg:col-span-1">
            <fieldset :disabled="isClosed" :class="{ 'opacity-60': isClosed }">
              <div class="sticky top-8 space-y-6">
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 border border-gray-200 dark:border-gray-700">
                  <h3 class="text-lg font-bold mb-4">Seguimiento y Fechas</h3>
                  <div class="border-t border-gray-200 dark:border-gray-700 pt-6 space-y-6">
                      <div>
                          <div class="flex justify-between items-center mb-1">
                              <InputLabel value="Radicado" />
                              <label class="flex items-center gap-1.5 cursor-pointer group">
                                  <input type="checkbox" v-model="form.es_spoa_nunc" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 w-3 h-3" />
                                  <span class="text-[9px] font-black uppercase text-gray-400 group-hover:text-indigo-600 transition-colors">¿Es SPOA/NUNC?</span>
                              </label>
                          </div>
                          <TextInput 
                              v-model="form.radicado" 
                              @input="handleRadicadoInput"
                              class="mt-1 block w-full font-mono" 
                              :placeholder="form.es_spoa_nunc ? '21 dígitos (Sistema Penal)' : '14 a 23 dígitos (Sin caracteres especiales)'"
                          />
                          <InputError :message="form.errors.radicado" />
                      </div>
                      <div><InputLabel value="Fecha de Radicado" /><DatePicker v-model="form.fecha_radicado" class="mt-1 block w-full" /><InputError :message="form.errors.fecha_radicado" /></div>
                      <div><InputLabel value="Abogado / Gestor" /><AsyncSelect v-model="form.abogado_id" :endpoint="route('users.search')" placeholder="Asignar gestor..." label-key="name" /></div>
                      <div><InputLabel value="Responsable de Revisión" /><AsyncSelect v-model="form.responsable_revision_id" :endpoint="route('users.search')" placeholder="Asignar responsable..." label-key="name" /></div>
                      <div><InputLabel value="Correo Radicación" /><TextInput v-model="form.correo_radicacion" type="email" class="mt-1 block w-full" /><InputError :message="form.errors.correo_radicacion" /></div>
                      <div><InputLabel value="Correo(s) del Juzgado" /><TextInput v-model="form.correos_juzgado" class="mt-1 block w-full" placeholder="correo1@juzgado.com, correo2@..." /><InputError :message="form.errors.correos_juzgado" /></div>
                      <div><InputLabel value="Link Expediente" /><TextInput v-model="form.link_expediente" type="url" class="mt-1 block w-full" /><InputError :message="form.errors.link_expediente" /></div>
                      <div><InputLabel value="Ubicación Drive" /><TextInput v-model="form.ubicacion_drive" type="url" class="mt-1 block w-full" /><InputError :message="form.errors.ubicacion_drive" /></div>
                  </div>
                </div>
              </div>
            </fieldset>
          </div>
        </form>
      </div>
    </div>
    
    <Modal :show="showCloseModal" @close="showCloseModal = false" centered>
       <div class="p-6 text-gray-900 dark:text-gray-100">
         <h2 class="text-lg font-medium">Cerrar Radicado</h2>
         <div class="mt-6"><InputLabel value="Nota de Cierre" /><Textarea v-model="closeForm.nota_cierre" class="w-full mt-1" rows="4" placeholder="Indica el motivo del cierre..." /></div>
         <div class="mt-6 flex justify-end gap-3"><SecondaryButton @click="showCloseModal = false">Cancelar</SecondaryButton><DangerButton @click="closeTheCase">Confirmar</DangerButton></div>
       </div>
    </Modal>

    <!-- MODAL DE COMPROMISO DE REVISIÓN (OBLIGATORIO) -->
    <Modal :show="showCommitmentModal" @close="showCommitmentModal = false" maxWidth="sm" centered>
        <div class="p-6">
            <div class="flex items-center gap-3 mb-4 text-indigo-600 dark:text-indigo-400">
                <CalendarDaysIcon class="h-8 w-8" />
                <h2 class="text-xl font-black uppercase tracking-tight">Próxima Revisión</h2>
            </div>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-6 font-medium">
                Para guardar los cambios, es <strong>obligatorio</strong> que definas la fecha de la próxima revisión.
            </p>
            
            <div class="space-y-4">
                <div class="p-4 bg-indigo-50 dark:bg-indigo-900/20 rounded-xl border-2 border-indigo-100 dark:border-indigo-800">
                    <InputLabel value="¿Cuándo revisarás esto de nuevo?" class="text-xs font-bold text-indigo-700 dark:text-indigo-300 uppercase mb-2" />
                    <DatePicker 
                        v-model="globalRevisionDate" 
                        class="mt-1 block w-full" 
                        required 
                    />
                    <div class="mt-2 flex gap-1">
                        <button type="button" @click="globalRevisionDate = addDaysToDate(globalRevisionDate, 3)" class="text-[9px] bg-white dark:bg-gray-700 px-1.5 py-0.5 rounded font-bold hover:bg-indigo-100 text-gray-600">+3d</button>
                        <button type="button" @click="globalRevisionDate = addDaysToDate(globalRevisionDate, 5)" class="text-[9px] bg-white dark:bg-gray-700 px-1.5 py-0.5 rounded font-bold hover:bg-indigo-100 text-gray-600">+5d</button>
                        <button type="button" @click="globalRevisionDate = addDaysToDate(globalRevisionDate, 10)" class="text-[9px] bg-white dark:bg-gray-700 px-1.5 py-0.5 rounded font-bold hover:bg-indigo-100 text-gray-600">+10d</button>
                        <button type="button" @click="globalRevisionDate = addMonthsToDate(globalRevisionDate, 1)" class="text-[9px] bg-white dark:bg-gray-700 px-1.5 py-0.5 rounded font-bold hover:bg-indigo-100 text-gray-600">+1m</button>
                    </div>
                </div>
                
                <div class="flex flex-col gap-2 pt-4">
                    <PrimaryButton 
                        @click="executeCommittedAction" 
                        class="w-full justify-center !py-4 !text-sm !bg-indigo-600 hover:!bg-indigo-700 shadow-lg transition-all"
                        :disabled="!globalRevisionDate"
                    >
                        Confirmar y Guardar Cambios
                    </PrimaryButton>
                    <SecondaryButton @click="showCommitmentModal = false" class="w-full justify-center">
                        Seguir editando
                    </SecondaryButton>
                </div>
            </div>
        </div>
    </Modal>
  </AuthenticatedLayout>
</template>
