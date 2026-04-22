<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, useForm, useRemember, router } from '@inertiajs/vue3';
import { debounce } from 'lodash';
import { formatRadicado, addDaysToDate, addMonthsToDate, toUpperCase, calculateDV } from '@/Utils/formatters';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import DatePicker from '@/Components/DatePicker.vue';
import Textarea from '@/Components/Textarea.vue';
import SelectInput from '@/Components/SelectInput.vue';
import AsyncSelect from '@/Components/AsyncSelect.vue';
import Dropdown from '@/Components/Dropdown.vue';
import { 
    PlusIcon, 
    TrashIcon, 
    ChevronDownIcon, 
    ScaleIcon, 
    UserGroupIcon, 
    MapPinIcon, 
    ArrowRightIcon, 
    ArrowLeftIcon,
    CheckCircleIcon,
    ArrowPathIcon,
    DocumentTextIcon,
    UsersIcon,
    BriefcaseIcon,
    BuildingLibraryIcon,
    ClockIcon,
    XMarkIcon
} from '@heroicons/vue/24/outline';
import Swal from 'sweetalert2';

const props = defineProps({
  etapas: { type: Array, required: true },
});

const searchEtapa = ref('');
const filteredEtapas = computed(() => {
    return props.etapas.filter(e => 
        e.nombre.toLowerCase().includes(searchEtapa.value.toLowerCase())
    );
});

// --- State for the Wizard ---
const step = useRemember(1, 'CreateRadicadoStep');
const totalSteps = 3;
const transitionName = useRemember('slide-next', 'CreateRadicadoTransition');

const steps = [
  { id: 1, name: 'Información Técnica', icon: ScaleIcon, fields: ['radicado', 'asunto', 'juzgado_id', 'tipo_proceso_id'] },
  { id: 2, name: 'Partes Procesales', icon: UserGroupIcon, fields: ['demandantes', 'demandados', 'abogado_id'] },
  { id: 3, name: 'Seguimiento y Control', icon: ClockIcon, fields: ['fecha_proxima_revision'] },
];

const currentStep = computed(() => steps.find(s => s.id === step.value));

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

// --- Form Data ---
const form = useForm('CreateRadicado', {
  abogado_id: null,
  responsable_revision_id: null,
  juzgado_id: null,
  tipo_proceso_id: null,
  etapa_procesal_id: '', 

  demandantes: [{ 
    id: null, selected: null, is_new: false, nombre_completo: '', tipo_documento: 'CC', numero_documento: '', dv: '', cooperativas_ids: [], abogados_ids: []
  }],
  demandados: [{ 
    id: null, selected: null, is_new: false, nombre_completo: '', tipo_documento: 'CC', numero_documento: '', dv: '', sin_info: false, cooperativas_ids: [], abogados_ids: []
  }],
  
  radicado: '',
  fecha_radicado: new Date().toISOString().slice(0, 10),
  naturaleza: '',
  asunto: '',
  correo_radicacion: '',
  fecha_revision: '',
  fecha_proxima_revision: '',
  link_expediente: '',
  ubicacion_drive: '',
  correos_juzgado: '',
  observaciones: '',
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

// Sincronizar campos principales con la URL
watch([() => form.radicado, () => form.asunto], debounce(() => {
    const url = new URL(window.location);
    if (form.radicado) url.searchParams.set('radicado', form.radicado);
    else url.searchParams.delete('radicado');
    
    if (form.asunto) url.searchParams.set('asunto', form.asunto);
    else url.searchParams.delete('asunto');
    
    window.history.replaceState({}, '', url);
}, 500));

// --- Helpers para Listas Dinámicas ---
const addDemandante = () => form.demandantes.push({ 
    id: null, selected: null, is_new: false, nombre_completo: '', tipo_documento: 'CC', numero_documento: '', cooperativas_ids: [], abogados_ids: []
});
const removeDemandante = (index) => { if (form.demandantes.length > 1) form.demandantes.splice(index, 1); };

const addDemandado = () => form.demandados.push({ 
    id: null, selected: null, is_new: false, nombre_completo: '', tipo_documento: 'CC', numero_documento: '', sin_info: false, cooperativas_ids: [], abogados_ids: []
});
const removeDemandado = (index) => { if (form.demandados.length > 1) form.demandados.splice(index, 1); };

// --- Wizard Navigation ---
const nextStep = () => { if (step.value < totalSteps) { transitionName.value = 'slide-next'; step.value++; } };
const prevStep = () => { if (step.value > 1) { transitionName.value = 'slide-prev'; step.value--; } };
const goToStep = (targetStep) => { if (targetStep < step.value) transitionName.value = 'slide-prev'; else transitionName.value = 'slide-next'; step.value = targetStep; };

// --- Form Submission ---
const submit = () => {
  form.transform(data => ({
    ...data,
    abogado_id: data.abogado_id?.id ?? data.abogado_id,
    responsable_revision_id: data.responsable_revision_id?.id ?? data.responsable_revision_id,
    juzgado_id: data.juzgado_id?.id ?? data.juzgado_id,
    tipo_proceso_id: data.tipo_proceso_id?.id ?? data.tipo_proceso_id,
    
    demandantes: data.demandantes.map(d => {
        if (d.is_new) return {
            nombre_completo: d.nombre_completo,
            tipo_documento: d.tipo_documento,
            numero_documento: d.numero_documento,
            dv: d.dv,
            cooperativas_ids: Array.isArray(d.cooperativas_ids) ? d.cooperativas_ids.map(c => c.id ?? c) : [],
            abogados_ids: Array.isArray(d.abogados_ids) ? d.abogados_ids.map(a => a.id ?? a) : [],
            is_new: true
        };
        return { id: d.selected?.id ?? d.id };
    }).filter(d => d.id || d.is_new),
    
    demandados: data.demandados.map(d => {
        if (d.is_new) return {
            nombre_completo: d.nombre_completo,
            tipo_documento: d.tipo_documento,
            numero_documento: d.numero_documento,
            dv: d.dv,
            sin_info: d.sin_info,
            cooperativas_ids: Array.isArray(d.cooperativas_ids) ? d.cooperativas_ids.map(c => c.id ?? c) : [],
            abogados_ids: Array.isArray(d.abogados_ids) ? d.abogados_ids.map(a => a.id ?? a) : [],
            is_new: true
        };
        return { id: d.selected?.id ?? d.id };
    }).filter(d => d.id || d.is_new),
  })).post(route('procesos.store'), {
    preserveScroll: true,
    onSuccess: () => {
      Swal.fire({
          title: '¡Éxito!',
          text: 'El radicado ha sido registrado correctamente.',
          icon: 'success',
          timer: 2000,
          showConfirmButton: false,
      });
      step.value = 1;
    },
    onError: (errors) => {
      const errorKeys = Object.keys(errors);
      if (errorKeys.length > 0) {
        Swal.fire({
            title: 'Hay errores en el formulario',
            text: 'Por favor revise los campos marcados en rojo en los diferentes pasos.',
            icon: 'warning',
            confirmButtonColor: '#4f46e5',
        });
        for (const s of steps) {
          if (s.fields.some(field => errorKeys.includes(field) || errorKeys.some(k => k.startsWith(field)))) {
            goToStep(s.id);
            break;
          }
        }
      }
    },
  });
};
</script>

<template>
  <Head title="Registrar Radicado" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex flex-col md:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="p-2 bg-indigo-50 dark:bg-indigo-900/30 rounded-lg">
                <DocumentTextIcon class="w-6 h-6 text-indigo-600" />
            </div>
            <div>
                <h2 class="font-black text-2xl text-gray-900 dark:text-white leading-tight">Registrar Nuevo Radicado</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Complete los 3 pasos para dar de alta el expediente judicial.</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
          <Link :href="route('procesos.index')" class="text-sm font-bold text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 mr-2">Cancelar</Link>
        </div>
      </div>
    </template>

    <div class="py-12 bg-gray-50/50 dark:bg-gray-900/50 min-h-screen">
      <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
        
        <!-- STEPPER PREMIUM -->
        <div class="mb-12 relative">
            <!-- Línea de fondo -->
            <div class="absolute top-1/2 left-0 w-full h-0.5 bg-gray-200 dark:bg-gray-700 -translate-y-1/2"></div>
            
            <div class="relative flex justify-between">
                <button 
                    v-for="(s, idx) in steps" 
                    :key="s.id" 
                    @click="goToStep(s.id)"
                    class="group flex flex-col items-center gap-2 outline-none focus:outline-none"
                >
                    <div 
                        class="relative z-10 w-12 h-12 rounded-2xl flex items-center justify-center border-4 transition-all duration-500"
                        :class="[
                            s.id < step ? 'bg-indigo-600 border-indigo-600 text-white shadow-lg shadow-indigo-100 dark:shadow-none scale-90' : '',
                            s.id === step ? 'bg-white dark:bg-gray-800 border-indigo-600 text-indigo-600 scale-110 shadow-xl' : '',
                            s.id > step ? 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 text-gray-300' : ''
                        ]"
                    >
                        <component :is="s.id < step ? CheckCircleIcon : s.icon" class="w-6 h-6" />
                    </div>
                    <div class="absolute -bottom-8 flex flex-col items-center">
                        <span 
                            class="text-[10px] font-black uppercase tracking-widest whitespace-nowrap transition-colors duration-300"
                            :class="step >= s.id ? 'text-indigo-600 dark:text-indigo-400' : 'text-gray-400'"
                        >
                            Paso {{ s.id }}
                        </span>
                        <span 
                            class="text-xs font-bold whitespace-nowrap transition-colors duration-300"
                            :class="step >= s.id ? 'text-gray-900 dark:text-white' : 'text-gray-400'"
                        >
                            {{ s.name }}
                        </span>
                    </div>
                </button>
            </div>
        </div>

        <!-- FORMULARIO CONTENEDOR -->
        <form @submit.prevent="submit" class="mt-16 bg-white dark:bg-gray-800 shadow-xl rounded-3xl border border-gray-100 dark:border-gray-700 overflow-visible">
          <div class="relative p-8 md:p-10 min-h-[600px]">
              <transition :name="transitionName" mode="out-in">
                <!-- STEP 1: INFORMACIÓN TÉCNICA -->
                <div v-if="step === 1" key="step1" class="space-y-8 animate-in fade-in duration-500">
                  <div class="flex items-center gap-3 border-b border-gray-50 dark:border-gray-700 pb-4">
                      <div class="p-2 bg-indigo-50 dark:bg-indigo-900/30 rounded-xl">
                          <BuildingLibraryIcon class="w-5 h-5 text-indigo-600" />
                      </div>
                      <h3 class="text-lg font-black text-gray-900 dark:text-white uppercase tracking-wider">Identificación del Proceso</h3>
                  </div>

                  <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="md:col-span-2 space-y-2">
                        <InputLabel value="Asunto / Objeto de la Demanda *" class="font-bold text-xs uppercase" />
                        <Textarea v-model="form.asunto" rows="2" class="w-full rounded-2xl border-gray-200 focus:ring-indigo-500" placeholder="Breve descripción del proceso..." required />
                        <InputError :message="form.errors.asunto" />
                    </div>
                    <div class="space-y-2">
                        <InputLabel value="Número de Radicado (23 dígitos)" class="font-bold text-xs uppercase" />
                        <div class="relative">
                            <ScaleIcon class="absolute left-3 top-3 w-4 h-4 text-gray-400" />
                            <TextInput 
                                v-model="form.radicado" 
                                @input="handleRadicadoInput"
                                class="pl-10 w-full rounded-xl border-gray-200 font-mono" 
                                placeholder="XXXXX-XX-XX-XXX-XXXX-XXXXX-XX" 
                            />
                        </div>
                        <InputError :message="form.errors.radicado" />
                    </div>
                    <div class="space-y-2">
                        <InputLabel value="Fecha de Radicación" class="font-bold text-xs uppercase" />
                        <DatePicker v-model="form.fecha_radicado" class="w-full" />
                        <InputError :message="form.errors.fecha_radicado" />
                    </div>
                    <div class="md:col-span-2 space-y-2">
                        <InputLabel value="Juzgado o Entidad *" class="font-bold text-xs uppercase" />
                        <AsyncSelect v-model="form.juzgado_id" :endpoint="route('juzgados.search')" placeholder="Escriba para buscar el despacho..." label-key="nombre" />
                        <InputError :message="form.errors.juzgado_id" />
                    </div>
                    <div class="space-y-2">
                        <InputLabel value="Tipo de Proceso *" class="font-bold text-xs uppercase" />
                        <AsyncSelect v-model="form.tipo_proceso_id" :endpoint="route('tipos-proceso.search')" placeholder="Ej: Ejecutivo, Verbal..." label-key="nombre" />
                        <InputError :message="form.errors.tipo_proceso_id" />
                    </div>
                    <div class="space-y-2">
                        <InputLabel value="Etapa Inicial" class="font-bold text-xs uppercase" />
                        <Dropdown align="left" width="full">
                            <template #trigger>
                                <button type="button" class="w-full flex justify-between items-center bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-3 text-sm transition-all hover:border-indigo-500">
                                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ form.etapa_procesal_id ? etapas.find(e => e.id === form.etapa_procesal_id)?.nombre : '(Automático: Primera Etapa)' }}</span>
                                    <ChevronDownIcon class="h-4 w-4 text-gray-400" />
                                </button>
                            </template>
                            <template #content>
                                <div class="p-3 border-b border-gray-100 dark:border-gray-700 sticky top-0 bg-white dark:bg-gray-800 z-10">
                                    <TextInput v-model="searchEtapa" placeholder="Filtrar etapas..." class="w-full text-xs rounded-lg" @click.stop />
                                </div>
                                <div class="py-1 bg-white dark:bg-gray-800 max-h-64 overflow-y-auto">
                                    <button type="button" v-for="e in filteredEtapas" :key="e.id" @click="form.etapa_procesal_id = e.id" class="block w-full text-left px-4 py-3 text-xs font-bold uppercase tracking-tight text-gray-600 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 hover:text-indigo-600 transition-colors border-b border-gray-50 dark:border-gray-700/50 last:border-0">
                                        {{ e.nombre }}
                                    </button>
                                    <div v-if="filteredEtapas.length === 0" class="p-6 text-xs text-gray-400 text-center italic">Sin coincidencias</div>
                                </div>
                            </template>
                        </Dropdown>
                    </div>
                  </div>
                </div>
                
                <!-- STEP 2: PARTES Y RESPONSABLES -->
                <div v-else-if="step === 2" key="step2" class="space-y-10 animate-in fade-in duration-500">
                  
                  <!-- Responsables -->
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-8 p-6 bg-indigo-50/30 dark:bg-indigo-900/10 rounded-3xl border border-indigo-100 dark:border-indigo-900/30">
                      <div class="space-y-2">
                          <InputLabel value="Abogado / Gestor Principal *" class="font-bold text-xs uppercase" />
                          <AsyncSelect v-model="form.abogado_id" :endpoint="route('users.search')" placeholder="Quién lleva el caso..." label-key="name" />
                          <InputError :message="form.errors.abogado_id" />
                      </div>
                      <div class="space-y-2">
                          <InputLabel value="Responsable de Revisión" class="font-bold text-xs uppercase" />
                          <AsyncSelect v-model="form.responsable_revision_id" :endpoint="route('users.search')" placeholder="Quién supervisa..." label-key="name" />
                          <InputError :message="form.errors.responsable_revision_id" />
                      </div>
                  </div>

                  <!-- Demandantes -->
                  <div class="space-y-6">
                    <div class="flex justify-between items-center border-b dark:border-gray-700 pb-3">
                        <div class="flex items-center gap-2">
                            <UsersIcon class="w-5 h-5 text-blue-600" />
                            <h3 class="font-black text-gray-900 dark:text-white uppercase tracking-wider text-xs">Demandantes / Accionantes</h3>
                        </div>
                        <button type="button" @click="addDemandante" class="inline-flex items-center px-3 py-1.5 bg-blue-50 text-blue-700 rounded-lg text-[10px] font-black uppercase tracking-widest hover:bg-blue-600 hover:text-white transition-all">
                            <PlusIcon class="w-3.5 h-3.5 mr-1" /> Añadir
                        </button>
                    </div>
                    <InputError :message="form.errors.demandantes" />
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div v-for="(item, index) in form.demandantes" :key="'dte-'+index" class="p-6 border border-gray-100 dark:border-gray-700 rounded-2xl bg-white dark:bg-gray-800 shadow-sm relative group animate-in zoom-in-95 duration-200">
                            <button v-if="form.demandantes.length > 1" type="button" @click="removeDemandante(index)" class="absolute -top-2 -right-2 p-1.5 bg-white dark:bg-gray-800 text-red-500 rounded-full shadow-md border dark:border-gray-700 opacity-0 group-hover:opacity-100 transition-all"><XMarkIcon class="w-4 h-4"/></button>
                            
                            <div class="flex justify-between items-center mb-4">
                                <span class="text-[9px] font-black text-gray-400 uppercase tracking-widest">Demandante #{{ index + 1 }}</span>
                                <button type="button" @click="item.is_new = !item.is_new" class="text-[10px] font-black uppercase text-indigo-600 hover:underline">
                                    {{ item.is_new ? '← Buscar' : '+ Nuevo' }}
                                </button>
                            </div>

                            <div v-if="!item.is_new">
                                <AsyncSelect v-model="item.selected" :endpoint="route('personas.search')" placeholder="Buscar por nombre o CC..." label-key="nombre_completo" />
                            </div>
                            <div v-else class="space-y-4 animate-in slide-in-from-top-2 duration-300">
                                <TextInput v-model="item.nombre_completo" @blur="item.nombre_completo = toUpperCase(item.nombre_completo)" placeholder="Nombre Completo *" class="text-sm w-full" />
                                <InputError :message="form.errors[`demandantes.${index}.nombre_completo`]" />
                                <div class="grid grid-cols-3 gap-2">
                                    <SelectInput v-model="item.tipo_documento" class="text-sm rounded-xl border-gray-200 bg-gray-50">
                                        <option>CC</option><option>NIT</option>
                                    </SelectInput>
                                    <div :class="item.tipo_documento === 'NIT' ? 'col-span-2 flex gap-1' : 'col-span-2'">
                                        <TextInput v-model="item.numero_documento" placeholder="Número *" class="text-sm flex-1" />
                                        <TextInput v-if="item.tipo_documento === 'NIT'" v-model="item.dv" maxlength="1" placeholder="DV" class="w-10 text-center" />
                                    </div>
                                </div>
                                <InputError :message="form.errors[`demandantes.${index}.numero_documento`]" />
                                <div class="space-y-2">
                                    <AsyncSelect v-model="item.cooperativas_ids" :endpoint="route('cooperativas.search')" placeholder="Vincular empresas * (Mín. 1)" multiple label-key="nombre" />
                                    <AsyncSelect v-model="item.abogados_ids" :endpoint="route('users.search')" placeholder="Vincular abogados..." multiple label-key="name" />
                                </div>
                            </div>
                        </div>
                    </div>
                  </div>

                  <!-- Demandados -->
                  <div class="space-y-6">
                    <div class="flex justify-between items-center border-b dark:border-gray-700 pb-3">
                        <div class="flex items-center gap-2">
                            <UsersIcon class="w-5 h-5 text-red-600" />
                            <h3 class="font-black text-gray-900 dark:text-white uppercase tracking-wider text-xs">Demandados / Accionados</h3>
                        </div>
                        <button type="button" @click="addDemandado" class="inline-flex items-center px-3 py-1.5 bg-red-50 text-red-700 rounded-lg text-[10px] font-black uppercase tracking-widest hover:bg-red-600 hover:text-white transition-all">
                            <PlusIcon class="w-3.5 h-3.5 mr-1" /> Añadir
                        </button>
                    </div>
                    <InputError :message="form.errors.demandados" />
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div v-for="(item, index) in form.demandados" :key="'ddo-'+index" class="p-6 border border-gray-100 dark:border-gray-700 rounded-2xl bg-white dark:bg-gray-800 shadow-sm relative group animate-in zoom-in-95 duration-200">
                            <button v-if="form.demandados.length > 1" type="button" @click="removeDemandado(index)" class="absolute -top-2 -right-2 p-1.5 bg-white dark:bg-gray-800 text-red-500 rounded-full shadow-md border dark:border-gray-700 opacity-0 group-hover:opacity-100 transition-all"><XMarkIcon class="w-4 h-4"/></button>
                            
                            <div class="flex justify-between items-center mb-4">
                                <span class="text-[9px] font-black text-gray-400 uppercase tracking-widest">Demandado #{{ index + 1 }}</span>
                                <button type="button" @click="item.is_new = !item.is_new" class="text-[10px] font-black uppercase text-indigo-600 hover:underline">
                                    {{ item.is_new ? '← Buscar' : '+ Nuevo' }}
                                </button>
                            </div>

                            <div v-if="!item.is_new">
                                <AsyncSelect v-model="item.selected" :endpoint="route('personas.search')" placeholder="Buscar por nombre o CC..." label-key="nombre_completo" />
                            </div>
                            <div v-else class="space-y-4 animate-in slide-in-from-top-2 duration-300">
                                <label class="flex items-center gap-2 cursor-pointer p-2 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-100 dark:border-amber-800">
                                    <input type="checkbox" v-model="item.sin_info" class="rounded border-amber-300 text-amber-600" />
                                    <span class="text-[9px] font-black text-amber-700 dark:text-amber-400 uppercase">Sin información (Demandado por identificar)</span>
                                </label>
                                <TextInput v-model="item.nombre_completo" @blur="item.nombre_completo = toUpperCase(item.nombre_completo)" placeholder="Nombre o Alias *" class="text-sm w-full" />
                                <InputError :message="form.errors[`demandados.${index}.nombre_completo`]" />
                                <div v-if="!item.sin_info" class="grid grid-cols-3 gap-2">
                                    <SelectInput v-model="item.tipo_documento" class="text-sm rounded-xl border-gray-200 bg-gray-50">
                                        <option>CC</option><option>NIT</option>
                                    </SelectInput>
                                    <div :class="item.tipo_documento === 'NIT' ? 'col-span-2 flex gap-1' : 'col-span-2'">
                                        <TextInput v-model="item.numero_documento" placeholder="Número *" class="text-sm flex-1" />
                                        <TextInput v-if="item.tipo_documento === 'NIT'" v-model="item.dv" maxlength="1" placeholder="DV" class="w-10 text-center" />
                                    </div>
                                </div>
                                <InputError :message="form.errors[`demandados.${index}.numero_documento`]" />
                                <div class="space-y-2">
                                    <AsyncSelect v-model="item.cooperativas_ids" :endpoint="route('cooperativas.search')" placeholder="Vincular empresas * (Mín. 1)" multiple label-key="nombre" />
                                    <AsyncSelect v-model="item.abogados_ids" :endpoint="route('users.search')" placeholder="Vincular abogados..." multiple label-key="name" />
                                </div>
                            </div>
                        </div>
                    </div>
                  </div>
                </div>

                <!-- STEP 3: SEGUIMIENTO Y EXTRAS -->
                <div v-else-if="step === 3" key="step3" class="space-y-8 animate-in fade-in duration-500">
                  <div class="flex items-center gap-3 border-b border-gray-50 dark:border-gray-700 pb-4">
                      <div class="p-2 bg-indigo-50 dark:bg-indigo-900/30 rounded-xl">
                          <ClockIcon class="w-5 h-5 text-indigo-600" />
                      </div>
                      <h3 class="text-lg font-black text-gray-900 dark:text-white uppercase tracking-wider">Control y Seguimiento</h3>
                  </div>

                  <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="p-6 bg-indigo-600 rounded-3xl text-white shadow-xl shadow-indigo-100 dark:shadow-none relative overflow-visible group">
                        <div class="absolute -right-6 -top-6 opacity-10 group-hover:scale-110 transition-transform">
                            <ClockIcon class="w-32 h-32" />
                        </div>
                        <InputLabel value="Fecha Próxima Revisión *" class="!text-white !font-black !text-[10px] !uppercase !tracking-widest" />
                        <DatePicker v-model="form.fecha_proxima_revision" class="mt-2 w-full !bg-white/10 !border-white/20 !text-white" required />
                        <div class="mt-2 flex gap-1">
                            <button type="button" @click="addDays('fecha_proxima_revision', 3)" class="text-[9px] bg-white/20 px-1.5 py-0.5 rounded font-bold hover:bg-white/40 text-white">+3d</button>
                            <button type="button" @click="addDays('fecha_proxima_revision', 5)" class="text-[9px] bg-white/20 px-1.5 py-0.5 rounded font-bold hover:bg-white/40 text-white">+5d</button>
                            <button type="button" @click="addDays('fecha_proxima_revision', 10)" class="text-[9px] bg-white/20 px-1.5 py-0.5 rounded font-bold hover:bg-white/40 text-white">+10d</button>
                            <button type="button" @click="addMonths('fecha_proxima_revision', 1)" class="text-[9px] bg-white/20 px-1.5 py-0.5 rounded font-bold hover:bg-white/40 text-white">+1m</button>
                        </div>
                        <p class="text-[10px] text-indigo-100 mt-3 font-bold uppercase tracking-tighter">Obligatorio: Define la fecha en que el sistema generará una alerta de revisión para este proceso.</p>
                        <InputError :message="form.errors.fecha_proxima_revision" class="!text-white font-bold" />
                    </div>

                    <div class="space-y-6">
                        <div class="space-y-2">
                            <InputLabel value="Correo de Radicación" class="font-bold text-xs uppercase" />
                            <TextInput v-model="form.correo_radicacion" type="email" class="w-full rounded-xl" placeholder="ejemplo@juzgado.gov.co" />
                        </div>
                        <div class="space-y-2">
                            <InputLabel value="Correos Adicionales de la Entidad" class="font-bold text-xs uppercase" />
                            <TextInput v-model="form.correos_juzgado" class="w-full rounded-xl" placeholder="Separados por coma..." />
                        </div>
                    </div>

                    <div class="md:col-span-2 space-y-2">
                        <InputLabel value="Observaciones Iniciales" class="font-bold text-xs uppercase" />
                        <Textarea v-model="form.observaciones" rows="3" class="w-full rounded-2xl border-gray-200" placeholder="Cualquier nota relevante para el inicio del caso..." />
                    </div>

                    <div class="space-y-2">
                        <InputLabel value="Link Expediente Digital" class="font-bold text-xs uppercase" />
                        <TextInput v-model="form.link_expediente" type="url" class="w-full rounded-xl" placeholder="https://expediente.justicia.gov.co/..." />
                    </div>
                    <div class="space-y-2">
                        <InputLabel value="Carpeta en Drive" class="font-bold text-xs uppercase" />
                        <TextInput v-model="form.ubicacion_drive" type="url" class="w-full rounded-xl" placeholder="https://drive.google.com/..." />
                    </div>
                  </div>
                </div>
              </transition>
          </div>

          <!-- FOOTER NAVEGACIÓN -->
          <div class="flex items-center justify-between p-8 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-100 dark:border-gray-700 rounded-b-3xl">
            <SecondaryButton v-if="step > 1" @click="prevStep" type="button" class="!rounded-xl !px-8 !py-3 !font-black uppercase tracking-widest flex items-center gap-2">
                <ArrowLeftIcon class="w-4 h-4" /> Anterior
            </SecondaryButton>
            <div v-else></div>

            <div class="flex items-center gap-4">
              <span class="hidden sm:block text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">Paso {{ step }} / {{ totalSteps }}</span>
              
              <PrimaryButton 
                  v-if="step < totalSteps" 
                  @click="nextStep" 
                  type="button" 
                  class="!bg-indigo-600 hover:!bg-indigo-700 !rounded-xl !px-10 !py-3 !font-black uppercase tracking-widest flex items-center gap-2 shadow-lg shadow-indigo-100 dark:shadow-none"
              >
                  Siguiente <ArrowRightIcon class="w-4 h-4" />
              </PrimaryButton>

              <PrimaryButton 
                  v-if="step === totalSteps" 
                  type="submit" 
                  class="!bg-green-600 hover:!bg-green-700 !rounded-xl !px-12 !py-4 !text-lg !font-black uppercase tracking-widest flex items-center gap-3 transition-all transform hover:scale-[1.02] shadow-xl shadow-green-100 dark:shadow-none"
                  :disabled="form.processing"
              >
                  <CheckCircleIcon v-if="!form.processing" class="w-6 h-6" />
                  <ArrowPathIcon v-else class="w-6 h-6 animate-spin" />
                  Finalizar Registro
              </PrimaryButton>
            </div>
          </div>
        </form>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
.slide-next-enter-active, .slide-next-leave-active, .slide-prev-enter-active, .slide-prev-leave-active { transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); width: 100%; }
.slide-next-leave-active, .slide-prev-leave-active { position: absolute; }
.slide-next-enter-from { opacity: 0; transform: translateX(40px); }
.slide-next-leave-to { opacity: 0; transform: translateX(-40px); }
.slide-prev-enter-from { opacity: 0; transform: translateX(-40px); }
.slide-prev-leave-to { opacity: 0; transform: translateX(40px); }

.animate-in {
    animation-fill-mode: both;
}
</style>

<style>
.slide-next-enter-active, .slide-next-leave-active, .slide-prev-enter-active, .slide-prev-leave-active { transition: all 0.3s ease-in-out; width: 100%; }
.slide-next-leave-active, .slide-prev-leave-active { position: absolute; }
.slide-next-enter-from { opacity: 0; transform: translateX(20px); }
.slide-next-leave-to { opacity: 0; transform: translateX(-20px); }
.slide-prev-enter-from { opacity: 0; transform: translateX(-20px); }
.slide-prev-leave-to { opacity: 0; transform: translateX(20px); }
</style>
