<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import DatePicker from '@/Components/DatePicker.vue';
import Textarea from '@/Components/Textarea.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { PlusIcon, XMarkIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import { reactive } from 'vue';

const props = defineProps({
  allCooperativas: { type: Array, default: () => [] },
  allAbogados: { type: Array, default: () => [] },
});

const form = useForm('CreatePersona', {
  nombre_completo: '',
  tipo_documento: 'CC',
  numero_documento: '',
  dv: '',
  fecha_expedicion: '',
  fecha_nacimiento: '',
  telefono_fijo: '',
  celular_1: '',
  celular_2: '',
  correo_1: '',
  correo_2: '',
  empresa: '',
  cargo: '',
  observaciones: '',
  social_links: [],
  addresses: [],
  cooperativas_ids: [],
  abogados_ids: [],
});

const addLinkRow = () => {
  form.social_links.push(reactive({ label: '', url: '' }));
};
const removeLinkRow = (idx) => {
  form.social_links.splice(idx, 1);
};
const normalizeUrl = (l) => {
  if (!l.url) return;
  if (!/^https?:\/\//i.test(l.url)) l.url = `https://${l.url}`;
};

const addAddressRow = () => {
  form.addresses.push(reactive({ label: 'Casa', address: '', city: '' }));
};
const removeAddressRow = (idx) => {
  form.addresses.splice(idx, 1);
};

// --- LÓGICA DE SELECCIONAR TODOS ---
const toggleCooperativas = () => {
    if (form.cooperativas_ids.length === props.allCooperativas.length) {
        form.cooperativas_ids = [];
    } else {
        form.cooperativas_ids = props.allCooperativas.map(c => c.id);
    }
};

const toggleAbogados = () => {
    if (form.abogados_ids.length === props.allAbogados.length) {
        form.abogados_ids = [];
    } else {
        form.abogados_ids = props.allAbogados.map(a => a.id);
    }
};

const submit = () => {
    form.post(route('personas.store'), {
        preserveScroll: true, 
        onSuccess: () => {
            form.reset();
        },
        onError: () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    });
};
</script>

<template>
  <Head title="Registrar Persona" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-blue-500 dark:text-gray-200 leading-tight">
          Registrar Nueva Persona
        </h2>
        <Link :href="route('personas.index')" class="text-sm text-gray-600 hover:underline">&larr; Volver</Link>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 md:p-8">
            
            <div v-if="form.hasErrors" class="mb-8 rounded-md bg-red-50 p-4 border border-red-200 dark:bg-red-900/20 dark:border-red-800">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <ExclamationTriangleIcon class="h-5 w-5 text-red-400" aria-hidden="true" />
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800 dark:text-red-200">
                            No se pudo guardar la persona.
                        </h3>
                        <div class="mt-2 text-sm text-red-700 dark:text-red-300">
                            <ul role="list" class="list-disc pl-5 space-y-1">
                                <li v-for="(error, key) in form.errors" :key="key">{{ error }}</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-8">
              
              <section>
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4">Datos de la Persona</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  
                  <div class="md:col-span-2">
                    <InputLabel for="nombre_completo">
                        Nombre Completo <span class="text-red-500">*</span>
                    </InputLabel>
                    <TextInput v-model="form.nombre_completo" id="nombre_completo" type="text" class="mt-1 block w-full" required autofocus :class="{'border-red-500': form.errors.nombre_completo}" />
                    <InputError :message="form.errors.nombre_completo" class="mt-2" />
                  </div>
                  
                  <div>
                    <InputLabel for="tipo_documento">
                        Tipo de Documento <span class="text-red-500">*</span>
                    </InputLabel>
                    <select v-model="form.tipo_documento" id="tipo_documento" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:text-white">
                      <option>CC</option>
                      <option>CE</option>
                      <option>NIT</option>
                      <option>Pasaporte</option>
                      <option>TI</option>
                    </select>
                    <InputError :message="form.errors.tipo_documento" class="mt-2" />
                  </div>
                  
                  <div>
                    <InputLabel for="numero_documento">
                        Número de Documento <span class="text-red-500">*</span>
                    </InputLabel>
                    <div :class="form.tipo_documento === 'NIT' ? 'grid grid-cols-4 gap-2' : ''">
                        <div :class="form.tipo_documento === 'NIT' ? 'col-span-3' : ''">
                            <TextInput v-model="form.numero_documento" id="numero_documento" type="text" class="mt-1 block w-full" required :class="{'border-red-500': form.errors.numero_documento}" />
                        </div>
                        <div v-if="form.tipo_documento === 'NIT'">
                            <TextInput v-model="form.dv" id="dv" type="text" class="mt-1 block w-full text-center" maxlength="1" placeholder="DV" :class="{'border-red-500': form.errors.dv}" />
                        </div>
                    </div>
                    <InputError :message="form.errors.numero_documento" class="mt-2" />
                    <InputError :message="form.errors.dv" class="mt-2" />
                  </div>
                  
                  <div>
                    <InputLabel for="fecha_expedicion" value="Fecha de Expedición (Opcional)" />
                    <DatePicker v-model="form.fecha_expedicion" id="fecha_expedicion" class="mt-1 block w-full" :class="{'border-red-500': form.errors.fecha_expedicion}" />
                    <InputError :message="form.errors.fecha_expedicion" class="mt-2" />
                  </div>
                  <div>
                    <InputLabel for="fecha_nacimiento" value="Fecha de Nacimiento (Opcional)" />
                    <DatePicker v-model="form.fecha_nacimiento" id="fecha_nacimiento" class="mt-1 block w-full" :class="{'border-red-500': form.errors.fecha_nacimiento}" />
                    <InputError :message="form.errors.fecha_nacimiento" class="mt-2" />
                  </div>

                </div>
              </section>

              <section>
                <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4">Información de Contacto</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div>
                    <InputLabel for="celular_1" value="Celular Principal (Opcional)" />
                    <TextInput v-model="form.celular_1" id="celular_1" type="text" class="mt-1 block w-full" :class="{'border-red-500': form.errors.celular_1}" />
                    <InputError :message="form.errors.celular_1" class="mt-2" />
                  </div>
                  <div>
                    <InputLabel for="celular_2" value="Celular Secundario (Opcional)" />
                    <TextInput v-model="form.celular_2" id="celular_2" type="text" class="mt-1 block w-full" :class="{'border-red-500': form.errors.celular_2}" />
                    <InputError :message="form.errors.celular_2" class="mt-2" />
                  </div>
                  <div>
                    <InputLabel for="correo_1" value="Correo Principal (Opcional)" />
                    <TextInput v-model="form.correo_1" id="correo_1" type="email" class="mt-1 block w-full" :class="{'border-red-500': form.errors.correo_1}" />
                    <InputError :message="form.errors.correo_1" class="mt-2" />
                  </div>
                  <div>
                    <InputLabel for="correo_2" value="Correo Secundario (Opcional)" />
                    <TextInput v-model="form.correo_2" id="correo_2" type="email" class="mt-1 block w-full" :class="{'border-red-500': form.errors.correo_2}" />
                    <InputError :message="form.errors.correo_2" class="mt-2" />
                  </div>
                    <div>
                    <InputLabel for="telefono_fijo" value="Teléfono Fijo (Opcional)" />
                    <TextInput v-model="form.telefono_fijo" id="telefono_fijo" type="text" class="mt-1 block w-full" :class="{'border-red-500': form.errors.telefono_fijo}" />
                    <InputError :message="form.errors.telefono_fijo" class="mt-2" />
                  </div>
                </div>
              </section>

              <section>
                <div class="flex items-center justify-between mb-2">
                  <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Direcciones</h3>
                  <button type="button" @click="addAddressRow" class="inline-flex items-center gap-2 rounded-md bg-blue-500 px-3 py-1.5 text-white text-sm hover:bg-blue-600 transition shadow-sm">
                    <PlusIcon class="h-4 w-4" /> Agregar dirección
                  </button>
                </div>
                <InputError :message="form.errors.addresses" class="mb-2" />

                <div v-if="!form.addresses.length" class="rounded-md border border-dashed border-gray-300 dark:border-gray-600 p-4 text-center text-sm text-gray-500 dark:text-gray-400">
                  No hay direcciones todavía.
                </div>
                <div v-for="(addr, idx) in form.addresses" :key="idx" class="mt-3 grid grid-cols-1 gap-3 rounded-md border border-gray-200 p-3 dark:border-gray-700 sm:grid-cols-12 bg-gray-50 dark:bg-gray-700/30">
                  <div class="sm:col-span-3">
                    <InputLabel :for="`addr-label-${idx}`" value="Etiqueta" />
                    <TextInput v-model.trim="addr.label" :id="`addr-label-${idx}`" type="text" class="mt-1 block w-full" placeholder="Casa, Trabajo..." list="address-presets" />
                  </div>
                  <div class="sm:col-span-5">
                    <InputLabel :for="`addr-address-${idx}`" value="Dirección" />
                    <TextInput v-model.trim="addr.address" :id="`addr-address-${idx}`" type="text" class="mt-1 block w-full" placeholder="Calle 123 #45-67" />
                  </div>
                  <div class="sm:col-span-3">
                    <InputLabel :for="`addr-city-${idx}`" value="Ciudad" />
                    <TextInput v-model.trim="addr.city" :id="`addr-city-${idx}`" type="text" class="mt-1 block w-full" placeholder="Medellín" />
                  </div>
                  <div class="flex items-end sm:col-span-1">
                    <button type="button" @click="removeAddressRow(idx)" class="inline-flex h-10 w-full items-center justify-center rounded-md border border-red-200 bg-white text-red-600 hover:bg-red-50 dark:border-gray-600 dark:bg-gray-800 dark:text-red-400 dark:hover:bg-gray-700 transition">
                      <XMarkIcon class="h-5 w-5" />
                    </button>
                  </div>
                  <div class="sm:col-span-12">
                      <InputError :message="form.errors[`addresses.${idx}.label`]" />
                      <InputError :message="form.errors[`addresses.${idx}.address`]" />
                      <InputError :message="form.errors[`addresses.${idx}.city`]" />
                  </div>
                </div>
                <datalist id="address-presets">
                  <option value="Casa" />
                  <option value="Trabajo" />
                  <option value="Oficina" />
                  <option value="Apartamento" />
                  <option value="Finca" />
                </datalist>
              </section>
              
              <section>
                  <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4">Información Adicional</h3>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                      <InputLabel for="empresa" value="Empresa (Opcional)" />
                      <TextInput v-model="form.empresa" id="empresa" type="text" class="mt-1 block w-full" />
                      <InputError :message="form.errors.empresa" class="mt-2" />
                    </div>
                    <div>
                      <InputLabel for="cargo" value="Cargo (Opcional)" />
                      <TextInput v-model="form.cargo" id="cargo" type="text" class="mt-1 block w-full" />
                      <InputError :message="form.errors.cargo" class="mt-2" />
                    </div>
                    <div class="md:col-span-2">
                      <InputLabel for="observaciones" value="Observaciones (Opcional)" />
                      <Textarea v-model="form.observaciones" id="observaciones" class="mt-1 block w-full" rows="3" />
                      <InputError :message="form.errors.observaciones" class="mt-2" />
                    </div>
                   </div>
              </section>

              <section>
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Asignar Cooperativas</h3>
                    <button type="button" @click="toggleCooperativas" class="text-sm text-indigo-600 hover:text-indigo-800 hover:underline dark:text-indigo-400">
                        {{ form.cooperativas_ids.length === props.allCooperativas.length ? 'Deseleccionar todas' : 'Seleccionar todas' }}
                    </button>
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Selecciona las cooperativas a las que pertenece esta persona.</p>
                
                <div v-if="!props.allCooperativas.length" class="rounded-md border border-dashed border-gray-300 dark:border-gray-600 p-4 text-center text-sm text-gray-500 dark:text-gray-400">
                  No hay cooperativas para asignar.
                </div>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-3">
                  <label v-for="coop in props.allCooperativas" :key="coop.id" class="flex items-center space-x-2 p-2 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
                    <input
                      type="checkbox"
                      :value="coop.id"
                      v-model="form.cooperativas_ids"
                      class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:bg-gray-900 dark:focus:ring-indigo-600"
                    />
                    <span class="text-gray-700 dark:text-gray-300">{{ coop.nombre }}</span>
                  </label>
                </div>
                <InputError :message="form.errors.cooperativas_ids" class="mt-2" />
              </section>

              <section>
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Asignar Abogados / Gestores</h3>
                    <button type="button" @click="toggleAbogados" class="text-sm text-indigo-600 hover:text-indigo-800 hover:underline dark:text-indigo-400">
                        {{ form.abogados_ids.length === props.allAbogados.length ? 'Deseleccionar todos' : 'Seleccionar todos' }}
                    </button>
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Selecciona los abogados o gestores que manejarán esta persona.</p>
                
                <div v-if="!props.allAbogados.length" class="rounded-md border border-dashed border-gray-300 dark:border-gray-600 p-4 text-center text-sm text-gray-500 dark:text-gray-400">
                  No hay abogados o gestores para asignar.
                </div>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-3">
                  <label v-for="abogado in props.allAbogados" :key="abogado.id" class="flex items-center space-x-2 p-2 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer">
                    <input
                      type="checkbox"
                      :value="abogado.id"
                      v-model="form.abogados_ids"
                      class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:bg-gray-900 dark:focus:ring-indigo-600"
                    />
                    <span class="text-gray-700 dark:text-gray-300">{{ abogado.name }}</span>
                  </label>
                </div>
                <InputError :message="form.errors.abogados_ids" class="mt-2" />
              </section>

              <section>
                <div class="mb-2 flex items-center justify-between">
                  <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Redes Sociales</h3>
                  <button type="button" @click="addLinkRow" class="inline-flex items-center gap-2 rounded-md bg-blue-500 px-3 py-1.5 text-sm text-white hover:bg-blue-600 transition shadow-sm">
                    <PlusIcon class="h-4 w-4" /> Agregar enlace
                  </button>
                </div>
                
                <InputError :message="form.errors.social_links" class="mb-2" />

                <div v-if="!form.social_links.length" class="rounded-md border border-dashed border-gray-300 dark:border-gray-600 p-4 text-center text-sm text-gray-500 dark:text-gray-400">
                  No hay enlaces todavía.
                </div>
                <div v-for="(l, idx) in form.social_links" :key="idx" class="grid grid-cols-1 sm:grid-cols-12 gap-3 rounded-md border border-gray-200 dark:border-gray-700 p-3 mt-3 bg-gray-50 dark:bg-gray-700/30">
                  <div class="sm:col-span-4">
                    <InputLabel :for="`label-${idx}`" value="Etiqueta / Red" />
                    <TextInput v-model.trim="l.label" :id="`label-${idx}`" type="text" class="mt-1 block w-full" list="social-presets" placeholder="Instagram, Sitio Web..." />
                  </div>
                  <div class="sm:col-span-7">
                    <InputLabel :for="`url-${idx}`" value="URL" />
                    <TextInput v-model.trim="l.url" :id="`url-${idx}`" type="url" class="mt-1 block w-full" placeholder="https://ejemplo.com/usuario" @blur="normalizeUrl(l)" />
                  </div>
                  <div class="sm:col-span-1 flex items-end">
                    <button type="button" @click="removeLinkRow(idx)" class="inline-flex w-full items-center justify-center rounded-md border border-red-200 bg-white text-red-600 hover:bg-red-50 dark:border-gray-600 dark:bg-gray-800 dark:text-red-400 dark:hover:bg-gray-700 transition">
                      <XMarkIcon class="h-5 w-5" />
                    </button>
                  </div>
                    <div class="sm:col-span-12">
                      <InputError :message="form.errors[`social_links.${idx}.label`]" />
                      <InputError :message="form.errors[`social_links.${idx}.url`]" />
                  </div>
                </div>
                <datalist id="social-presets">
                  <option value="WhatsApp" />
                  <option value="Facebook" />
                  <option value="Instagram" />
                  <option value="X / Twitter" />
                  <option value="LinkedIn" />
                  <option value="TikTok" />
                  <option value="YouTube" />
                  <option value="GitHub" />
                  <option value="Sitio Web" />
                </datalist>
              </section>

              <div class="flex justify-end pt-6">
                <PrimaryButton 
                    :class="{ 'opacity-25': form.processing }" 
                    :disabled="form.processing" 
                    class="w-full sm:w-auto flex justify-center !bg-indigo-600 hover:!bg-indigo-700"
                >
                    <span v-if="form.processing" class="mr-2 animate-spin">⏳</span>
                    Guardar Persona
                </PrimaryButton>
            </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>