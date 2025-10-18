<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import Textarea from '@/Components/Textarea.vue';
import AsyncSelect from '@/Components/AsyncSelect.vue';

// --- State for the Wizard ---
const step = ref(1);
const totalSteps = 3;
const transitionName = ref('slide-next');

const steps = [
  { id: 1, name: 'Información Principal', fields: ['asunto', 'radicado', 'fecha_radicado', 'juzgado_id', 'tipo_proceso_id'] },
  { id: 2, name: 'Partes y Contacto', fields: ['demandante_id', 'demandado_id', 'abogado_id', 'responsable_revision_id', 'correo_radicacion'] },
  { id: 3, name: 'Seguimiento y Enlaces', fields: [] },
];

const currentStep = computed(() => steps.find(s => s.id === step.value));

// --- Form Data ---
const form = useForm({
  abogado_id: null,
  responsable_revision_id: null,
  juzgado_id: null,
  tipo_proceso_id: null,
  demandante_id: null,
  demandado_id: null,
  radicado: '',
  fecha_radicado: new Date().toISOString().slice(0, 10),
  naturaleza: '',
  asunto: '',
  correo_radicacion: '',
  fecha_revision: '',
  fecha_proxima_revision: '',
  ultima_actuacion: '',
  link_expediente: '',
  ubicacion_drive: '',
  correos_juzgado: '',
  observaciones: '',
});

// --- Wizard Navigation ---
const nextStep = () => {
  if (step.value < totalSteps) {
    transitionName.value = 'slide-next';
    step.value++;
  }
};

const prevStep = () => {
  if (step.value > 1) {
    transitionName.value = 'slide-prev';
    step.value--;
  }
};

const goToStep = (targetStep) => {
  if (targetStep < step.value) {
    transitionName.value = 'slide-prev';
    step.value = targetStep;
  }
};

// --- Form Submission with Smart Error Handling ---
const submit = () => {
  form.transform(data => ({
    ...data,
    abogado_id: data.abogado_id?.id ?? null,
    responsable_revision_id: data.responsable_revision_id?.id ?? null,
    juzgado_id: data.juzgado_id?.id ?? null,
    tipo_proceso_id: data.tipo_proceso_id?.id ?? null,
    demandante_id: data.demandante_id?.id ?? null,
    demandado_id: data.demandado_id?.id ?? null,
  })).post(route('procesos.store'), {
    preserveScroll: true,
    onError: (errors) => {
      const errorKeys = Object.keys(errors);
      if (errorKeys.length > 0) {
        // Find the first step that contains a field with an error
        for (const s of steps) {
          if (s.fields.some(field => errorKeys.includes(field))) {
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
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
              Registrar Nuevo Radicado
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
              Paso {{ step }}: {{ currentStep.description }}
            </p>
        </div>
        <div class="flex items-center gap-3">
          <Link :href="route('procesos.index')">
            <SecondaryButton>Cancelar</SecondaryButton>
          </Link>
        </div>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <!-- Stepper Navigation -->
        <nav aria-label="Progress">
          <ol role="list" class="flex items-center">
            <li v-for="(s, index) in steps" :key="s.id" class="relative" :class="{'flex-1': index < steps.length - 1}">
              <div v-if="s.id < step" class="absolute inset-0 flex items-center" aria-hidden="true">
                <div class="h-0.5 w-full bg-indigo-600"></div>
              </div>
              <a @click="goToStep(s.id)" class="relative flex h-8 w-8 items-center justify-center rounded-full transition-all duration-300" :class="[s.id < step ? 'bg-indigo-600 hover:bg-indigo-800 cursor-pointer' : '', s.id === step ? 'border-2 border-indigo-600 bg-white dark:bg-gray-800 scale-110' : '', s.id > step ? 'border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700' : '']">
                <span v-if="s.id < step" class="text-white">
                    <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.052-.143z" clip-rule="evenodd" /></svg>
                </span>
                <span v-else class="text-indigo-600 dark:text-indigo-400">{{ s.id }}</span>
              </a>
              <div class="absolute top-10 w-max text-center -translate-x-1/2 transition-opacity duration-300">
                  <p class="text-xs font-medium" :class="step >= s.id ? 'text-gray-800 dark:text-gray-200' : 'text-gray-500 dark:text-gray-400'">{{ s.name }}</p>
              </div>
            </li>
          </ol>
        </nav>

        <form @submit.prevent="submit" class="mt-16 bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
          <div class="relative overflow-hidden p-6 min-h-[490px]">
              <transition :name="transitionName" mode="out-in">
                  <!-- Step 1: Información Principal -->
                  <div v-if="step === 1" key="step1" class="space-y-6">
                      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                          <InputLabel for="asunto" value="Asunto" />
                          <Textarea id="asunto" v-model="form.asunto" rows="2" class="mt-1 block w-full" />
                          <InputError :message="form.errors.asunto" class="mt-2" />
                        </div>
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
                      </div>
                  </div>
                  <!-- Step 2: Partes y Contacto -->
                  <div v-else-if="step === 2" key="step2" class="space-y-6">
                      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
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
                        <div>
                            <InputLabel for="correo_radicacion" value="Correo de radicación" />
                            <TextInput id="correo_radicacion" v-model="form.correo_radicacion" type="email" class="mt-1 block w-full" />
                            <InputError :message="form.errors.correo_radicacion" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="correos_juzgado" value="Correos del juzgado (opcional)" />
                            <TextInput id="correos_juzgado" v-model="form.correos_juzgado" class="mt-1 block w-full" />
                            <InputError :message="form.errors.correos_juzgado" class="mt-2" />
                        </div>
                      </div>
                  </div>
                  <!-- Step 3: Seguimiento y Enlaces -->
                  <div v-else-if="step === 3" key="step3" class="space-y-6">
                      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
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
                        <div class="md:col-span-2">
                          <InputLabel for="ultima_actuacion" value="Última Actuación" />
                          <Textarea id="ultima_actuacion" v-model="form.ultima_actuacion" rows="3" class="mt-1 block w-full" />
                          <InputError :message="form.errors.ultima_actuacion" class="mt-2" />
                        </div>
                        <div class="md:col-span-2">
                          <InputLabel for="observaciones" value="Observaciones (opcional)" />
                          <Textarea id="observaciones" v-model="form.observaciones" rows="3" class="mt-1 block w-full" />
                          <InputError :message="form.errors.observaciones" class="mt-2" />
                        </div>
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
                      </div>
                  </div>
              </transition>
          </div>
          
          <!-- Navigation Buttons -->
          <div class="flex items-center justify-between p-6 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-200 dark:border-gray-700 sm:rounded-b-lg">
            <div>
              <SecondaryButton @click="prevStep" v-if="step > 1" type="button">
                Anterior
              </SecondaryButton>
            </div>
            <div class="flex items-center gap-3">
              <span class="text-xs text-gray-500">Paso {{ step }} de {{ totalSteps }}</span>
              <PrimaryButton @click="nextStep" v-if="step < totalSteps" type="button">
                Siguiente
              </PrimaryButton>
              <PrimaryButton type="submit" v-if="step === totalSteps" :disabled="form.processing" :class="{ 'opacity-25': form.processing }">
                {{ form.processing ? 'Guardando…' : 'Guardar Radicado' }}
              </PrimaryButton>
            </div>
          </div>
        </form>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style>
.slide-next-enter-active,
.slide-next-leave-active,
.slide-prev-enter-active,
.slide-prev-leave-active {
  transition: all 0.3s ease-in-out;
  width: 100%;
}
.slide-next-leave-active,
.slide-prev-leave-active {
    position: absolute;
}
.slide-next-enter-from {
  opacity: 0;
  transform: translateX(20px);
}
.slide-next-leave-to {
  opacity: 0;
  transform: translateX(-20px);
}
.slide-prev-enter-from {
  opacity: 0;
  transform: translateX(-20px);
}
.slide-prev-leave-to {
  opacity: 0;
  transform: translateX(20px);
}
</style>

