<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import DatePicker from '@/Components/DatePicker.vue';
import Textarea from '@/Components/Textarea.vue';
import AsyncSelect from '@/Components/AsyncSelect.vue';
import Dropdown from '@/Components/Dropdown.vue';
import { PlusIcon, TrashIcon, ChevronDownIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
  etapas: { type: Array, required: true },
});

// --- State for the Wizard ---
const step = ref(1);
const totalSteps = 3;
const transitionName = ref('slide-next');

const steps = [
  { id: 1, name: 'Información Principal', fields: ['tipo_proceso_id', 'etapa_procesal_id', 'juzgado_id'] },
  { id: 2, name: 'Partes y Contacto', fields: ['demandantes', 'demandados', 'abogado_id'] },
  { id: 3, name: 'Seguimiento y Enlaces', fields: ['fecha_proxima_revision'] },
];

const currentStep = computed(() => steps.find(s => s.id === step.value));

// --- Form Data ---
const form = useForm({
  abogado_id: null,
  responsable_revision_id: null,
  juzgado_id: null,
  tipo_proceso_id: null,
  etapa_procesal_id: '', 

  demandantes: [{ 
    id: null, selected: null, is_new: false, nombre_completo: '', tipo_documento: 'CC', numero_documento: '', cooperativas_ids: [], abogados_ids: []
  }],
  demandados: [{ 
    id: null, selected: null, is_new: false, nombre_completo: '', tipo_documento: 'CC', numero_documento: '', sin_info: false, cooperativas_ids: [], abogados_ids: []
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

// --- Helpers para Listas Dinámicas ---
const addDemandante = () => form.demandantes.push({ 
    id: null, selected: null, is_new: false, nombre_completo: '', tipo_documento: 'CC', numero_documento: '', sin_info: false, cooperativas_ids: [], abogados_ids: []
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
    abogado_id: data.abogado_id?.id ?? null,
    responsable_revision_id: data.responsable_revision_id?.id ?? null,
    juzgado_id: data.juzgado_id?.id ?? null,
    tipo_proceso_id: data.tipo_proceso_id?.id ?? null,
    
    demandantes: data.demandantes.map(d => {
        if (d.is_new) return {
            nombre_completo: d.nombre_completo,
            tipo_documento: d.tipo_documento,
            numero_documento: d.numero_documento,
            cooperativas_ids: Array.isArray(d.cooperativas_ids) ? d.cooperativas_ids.map(c => c.id) : [],
            abogados_ids: Array.isArray(d.abogados_ids) ? d.abogados_ids.map(a => a.id) : [],
            is_new: true
        };
        return { id: d.selected?.id };
    }).filter(d => d.id || d.is_new),
    
    demandados: data.demandados.map(d => {
        if (d.is_new) return {
            nombre_completo: d.nombre_completo,
            tipo_documento: d.tipo_documento,
            numero_documento: d.numero_documento,
            sin_info: d.sin_info,
            cooperativas_ids: Array.isArray(d.cooperativas_ids) ? d.cooperativas_ids.map(c => c.id) : [],
            abogados_ids: Array.isArray(d.abogados_ids) ? d.abogados_ids.map(a => a.id) : [],
            is_new: true
        };
        return { id: d.selected?.id };
    }).filter(d => d.id || d.is_new),
  })).post(route('procesos.store'), {
    preserveScroll: true,
    onError: (errors) => {
      const errorKeys = Object.keys(errors);
      if (errorKeys.length > 0) {
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
        <div>
          <h2 class="font-semibold text-xl text-blue-500 dark:text-gray-200 leading-tight">Registrar Nuevo Radicado</h2>
          <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Paso {{ step }}: {{ currentStep.name }}</p>
        </div>
        <div class="flex items-center gap-3">
          <Link :href="route('procesos.index')"><SecondaryButton>Cancelar</SecondaryButton></Link>
        </div>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <!-- Stepper Navigation -->
        <nav aria-label="Progress">
          <ol role="list" class="flex items-center">
            <li v-for="(s, index) in steps" :key="s.id" class="relative" :class="{'flex-1': index < steps.length - 1}">
              <div v-if="s.id < step" class="absolute inset-0 flex items-center" aria-hidden="true"><div class="h-0.5 w-full bg-blue-500"></div></div>
              <a @click="goToStep(s.id)" class="relative flex h-8 w-8 items-center justify-center rounded-full transition-all duration-300" :class="[s.id < step ? 'bg-blue-500 hover:bg-blue-700 cursor-pointer' : '', s.id === step ? 'border-2 border-blue-500 bg-white dark:bg-gray-800 scale-110' : '', s.id > step ? 'border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700' : '']">
                <span v-if="s.id < step" class="text-white"><svg class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.052-.143z" clip-rule="evenodd" /></svg></span>
                <span v-else class="text-blue-500 dark:text-blue-400">{{ s.id }}</span>
              </a>
              <div class="absolute top-10 w-max text-center -translate-x-1/2 transition-opacity duration-300">
                  <p class="text-xs font-medium" :class="step >= s.id ? 'text-gray-800 dark:text-gray-200' : 'text-gray-500 dark:text-gray-400'">{{ s.name }}</p>
              </div>
            </li>
          </ol>
        </nav>

        <form @submit.prevent="submit" class="mt-16 bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg border border-gray-200 dark:border-gray-700">
          <div class="relative overflow-visible p-6 min-h-[550px]">
              <transition :name="transitionName" mode="out-in">
                <!-- Step 1 -->
                <div v-if="step === 1" key="step1" class="space-y-6">
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2"><InputLabel value="Asunto" /><Textarea v-model="form.asunto" rows="2" class="mt-1 block w-full" /><InputError :message="form.errors.asunto" /></div>
                    <div><InputLabel value="Radicado" /><TextInput v-model="form.radicado" class="mt-1 block w-full" /><InputError :message="form.errors.radicado" /></div>
                    <div><InputLabel value="Fecha de Radicado" /><DatePicker v-model="form.fecha_radicado" class="mt-1 block w-full" /><InputError :message="form.errors.fecha_radicado" /></div>
                    <div class="md:col-span-2"><InputLabel value="Juzgado / Entidad" /><AsyncSelect v-model="form.juzgado_id" :endpoint="route('juzgados.search')" placeholder="Buscar juzgado..." label-key="nombre" /><InputError :message="form.errors.juzgado_id" /></div>
                    <div><InputLabel value="Tipo de Proceso" /><AsyncSelect v-model="form.tipo_proceso_id" :endpoint="route('tipos-proceso.search')" placeholder="Buscar tipo..." label-key="nombre" /><InputError :message="form.errors.tipo_proceso_id" /></div>
                    <div>
                        <InputLabel value="Etapa Inicial" />
                        <Dropdown align="left" width="full">
                            <template #trigger><button type="button" class="mt-1 flex w-full justify-between items-center border rounded-md p-2 text-sm dark:bg-gray-900"><span>{{ form.etapa_procesal_id ? etapas.find(e => e.id === form.etapa_procesal_id)?.nombre : '(Automático: Primera Etapa)' }}</span><ChevronDownIcon class="h-4 w-4 text-gray-400" /></button></template>
                            <template #content><div class="py-1 bg-white dark:bg-gray-800 max-h-60 overflow-y-auto"><button type="button" v-for="e in etapas" :key="e.id" @click="form.etapa_procesal_id = e.id" class="block w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">{{ e.nombre }}</button></div></template>
                        </Dropdown>
                    </div>
                    <div><InputLabel value="Naturaleza (opcional)" /><TextInput v-model="form.naturaleza" class="mt-1 block w-full" /></div>
                  </div>
                </div>
                
                <!-- Step 2 -->
                <div v-else-if="step === 2" key="step2" class="space-y-6 text-gray-900 dark:text-gray-100">
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <div class="flex justify-between items-center"><InputLabel value="Demandantes / Denunciantes" class="!text-indigo-700 dark:!text-indigo-400 font-bold" /><button type="button" @click="addDemandante" class="text-xs bg-indigo-50 text-indigo-600 px-2 py-1 rounded-md font-semibold flex items-center transition"><PlusIcon class="w-3 h-3 mr-1"/> Agregar otro</button></div>
                        <div v-for="(item, index) in form.demandantes" :key="index" class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50/50 dark:bg-gray-900/20 space-y-3 relative group">
                            <div class="flex justify-between items-center mb-1"><span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Demandante #{{ index + 1 }}</span><div class="flex items-center gap-3"><button type="button" @click="item.is_new = !item.is_new" class="text-[10px] font-bold uppercase" :class="item.is_new ? 'text-blue-600' : 'text-green-600'">{{ item.is_new ? '← Buscar' : '+ Nuevo' }}</button><button v-if="form.demandantes.length > 1" type="button" @click="removeDemandante(index)" class="text-red-400 hover:text-red-600 transition"><TrashIcon class="w-4 h-4"/></button></div></div>
                            <div v-if="!item.is_new"><AsyncSelect v-model="item.selected" :endpoint="route('personas.search')" placeholder="Buscar persona..." label-key="nombre_completo" /></div>
                            <div v-else class="space-y-3 animate-in fade-in slide-in-from-top-1"><TextInput v-model="item.nombre_completo" placeholder="Nombre Completo *" class="text-sm w-full" /><div class="grid grid-cols-3 gap-2"><select v-model="item.tipo_documento" class="text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 shadow-sm"><option>CC</option><option>NIT</option></select><TextInput v-model="item.numero_documento" placeholder="Número *" class="text-sm col-span-2" /></div><div class="grid grid-cols-1 gap-2"><AsyncSelect v-model="item.cooperativas_ids" :endpoint="route('cooperativas.search')" placeholder="Asignar empresas..." multiple label-key="nombre" /><AsyncSelect v-model="item.abogados_ids" :endpoint="route('users.search')" placeholder="Asignar abogados..." multiple label-key="name" /></div></div>
                        </div>
                    </div>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center"><InputLabel value="Demandados / Denunciados" class="!text-red-700 dark:!text-red-400 font-bold" /><button type="button" @click="addDemandado" class="text-xs bg-red-50 text-red-600 px-2 py-1 rounded-md font-semibold flex items-center transition"><PlusIcon class="w-3 h-3 mr-1"/> Agregar otro</button></div>
                        <div v-for="(item, index) in form.demandados" :key="index" class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50/50 dark:bg-gray-900/20 space-y-3 relative group">
                            <div class="flex justify-between items-center mb-1"><span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Demandado #{{ index + 1 }}</span><div class="flex items-center gap-3"><button type="button" @click="item.is_new = !item.is_new" class="text-[10px] font-bold uppercase" :class="item.is_new ? 'text-blue-600' : 'text-green-600'">{{ item.is_new ? '← Buscar' : '+ Nuevo' }}</button><button v-if="form.demandados.length > 1" type="button" @click="removeDemandado(index)" class="text-red-400 hover:text-red-600 transition"><TrashIcon class="w-4 h-4"/></button></div></div>
                            <div v-if="!item.is_new"><AsyncSelect v-model="item.selected" :endpoint="route('personas.search')" placeholder="Buscar persona..." label-key="nombre_completo" /></div>
                            <div v-else class="space-y-3 animate-in fade-in slide-in-from-top-1"><label class="flex items-center gap-2 cursor-pointer mb-2"><input type="checkbox" v-model="item.sin_info" class="rounded border-gray-300 text-indigo-600 shadow-sm" /><span class="text-[10px] font-bold text-amber-600 uppercase tracking-tight">Sin información completa</span></label><TextInput v-model="item.nombre_completo" placeholder="Nombre Completo *" class="text-sm w-full" /><div v-if="!item.sin_info" class="grid grid-cols-3 gap-2"><select v-model="item.tipo_documento" class="text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 shadow-sm"><option>CC</option><option>NIT</option></select><TextInput v-model="item.numero_documento" placeholder="Número *" class="text-sm col-span-2" /></div><div class="grid grid-cols-1 gap-2"><AsyncSelect v-model="item.cooperativas_ids" :endpoint="route('cooperativas.search')" placeholder="Asignar empresas..." multiple label-key="nombre" /><AsyncSelect v-model="item.abogados_ids" :endpoint="route('users.search')" placeholder="Asignar abogados..." multiple label-key="name" /></div></div>
                        </div>
                    </div>
                    <div><InputLabel value="Abogado / Gestor Principal" /><AsyncSelect v-model="form.abogado_id" :endpoint="route('users.search')" placeholder="Seleccionar abogado..." label-key="name" /><InputError :message="form.errors.abogado_id" /></div>
                    <div><InputLabel value="Responsable de Revisión" /><AsyncSelect v-model="form.responsable_revision_id" :endpoint="route('users.search')" placeholder="Seleccionar responsable..." label-key="name" /><InputError :message="form.errors.responsable_revision_id" /></div>
                    <div><InputLabel value="Correo de radicación" /><TextInput v-model="form.correo_radicacion" type="email" class="mt-1 block w-full" /><InputError :message="form.errors.correo_radicacion" /></div>
                    <div><InputLabel value="Correo de la Entidad o Juzgado" /><TextInput v-model="form.correos_juzgado" class="mt-1 block w-full" /><InputError :message="form.errors.correos_juzgado" /></div>
                  </div>
                </div>

                <!-- Step 3 -->
                <div v-else-if="step === 3" key="step3" class="space-y-6">
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div><InputLabel value="Fecha de Revisión" /><DatePicker v-model="form.fecha_revision" class="mt-1 block w-full" /><InputError :message="form.errors.fecha_revision" /></div>
                    <div><InputLabel value="Fecha Próxima Revisión" /><DatePicker v-model="form.fecha_proxima_revision" class="mt-1 block w-full" /><InputError :message="form.errors.fecha_proxima_revision" /></div>
                    <div class="md:col-span-2"><InputLabel value="Observaciones (opcional)" /><Textarea v-model="form.observaciones" rows="3" class="mt-1 block w-full" /><InputError :message="form.errors.observaciones" /></div>
                    <div><InputLabel value="Link de expediente digital" /><TextInput v-model="form.link_expediente" type="url" class="mt-1 block w-full" placeholder="https://…" /><InputError :message="form.errors.link_expediente" /></div>
                    <div><InputLabel value="Ubicación en Drive" /><TextInput v-model="form.ubicacion_drive" type="url" class="mt-1 block w-full" placeholder="https://drive.google.com/…" /><InputError :message="form.errors.ubicacion_drive" /></div>
                  </div>
                </div>
              </transition>
          </div>
          <div class="flex items-center justify-between p-6 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-200 dark:border-gray-700 sm:rounded-b-lg">
            <SecondaryButton @click="prevStep" v-if="step > 1" type="button">Anterior</SecondaryButton>
            <div class="flex items-center gap-3">
              <span class="text-xs text-gray-500">Paso {{ step }} de {{ totalSteps }}</span>
              
              <PrimaryButton 
                  @click="nextStep" 
                  v-if="step < totalSteps" 
                  type="button" 
                  class="!bg-indigo-600 hover:!bg-indigo-700"
              >
                  Siguiente
              </PrimaryButton>

              <PrimaryButton 
                  type="submit" 
                  v-if="step === totalSteps" 
                  :disabled="form.processing"
              >
                  {{ form.processing ? 'Guardando...' : 'Guardar Radicado' }}
              </PrimaryButton>
          </div>
          </div>
        </form>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style>
.slide-next-enter-active, .slide-next-leave-active, .slide-prev-enter-active, .slide-prev-leave-active { transition: all 0.3s ease-in-out; width: 100%; }
.slide-next-leave-active, .slide-prev-leave-active { position: absolute; }
.slide-next-enter-from { opacity: 0; transform: translateX(20px); }
.slide-next-leave-to { opacity: 0; transform: translateX(-20px); }
.slide-prev-enter-from { opacity: 0; transform: translateX(-20px); }
.slide-prev-leave-to { opacity: 0; transform: translateX(20px); }
</style>
