<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Textarea from '@/Components/Textarea.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { reactive } from 'vue';

const form = useForm({
  nombre_completo: '',
  tipo_documento: 'CC',
  numero_documento: '',
  fecha_expedicion: '', // <-- AÑADIDO
  telefono_fijo: '',
  celular_1: '',
  celular_2: '',
  correo_1: '',
  correo_2: '',
  // 'ciudad' -> ELIMINADO (Ahora va dentro de 'addresses')
  empresa: '',
  cargo: '',
  observaciones: '',
  social_links: [],
  addresses: [], // <-- CAMBIADO de 'direcciones' a 'addresses'
});

// Lógica para redes sociales
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

// Lógica para direcciones (CORREGIDA)
const addAddressRow = () => {
  form.addresses.push(reactive({ label: 'Casa', address: '', city: '' }));
};
const removeAddressRow = (idx) => {
  form.addresses.splice(idx, 1);
};

const submit = () => form.post(route('personas.store'));
</script>

<template>
  <Head title="Registrar Persona" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
          Registrar Nueva Persona
        </h2>
        <Link :href="route('personas.index')" class="text-sm text-gray-600 hover:underline">&larr; Volver</Link>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 md:p-8">
            <form @submit.prevent="submit" class="space-y-8">
              
              <!-- SECCIÓN 1: DATOS BÁSICOS -->
              <section>
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Datos de la Persona</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div class="md:col-span-2">
                    <InputLabel for="nombre_completo" value="Nombre Completo" />
                    <TextInput v-model="form.nombre_completo" id="nombre_completo" type="text" class="mt-1 block w-full" required autofocus />
                    <InputError :message="form.errors.nombre_completo" class="mt-2" />
                  </div>
                  <div>
                    <InputLabel for="tipo_documento" value="Tipo de Documento" />
                    <select v-model="form.tipo_documento" id="tipo_documento" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 shadow-sm">
                      <option>CC</option>
                      <option>CE</option>
                      <option>NIT</option>
                      <option>Pasaporte</option>
                    </select>
                  </div>
                  <div>
                    <InputLabel for="numero_documento" value="Número de Documento" />
                    <TextInput v-model="form.numero_documento" id="numero_documento" type="text" class="mt-1 block w-full" required />
                    <InputError :message="form.errors.numero_documento" class="mt-2" />
                  </div>
                  <div>
                    <InputLabel for="fecha_expedicion" value="Fecha de Expedición (Opcional)" />
                    <TextInput v-model="form.fecha_expedicion" id="fecha_expedicion" type="date" class="mt-1 block w-full" />
                    <InputError :message="form.errors.fecha_expedicion" class="mt-2" />
                  </div>
                </div>
              </section>

              <!-- SECCIÓN 2: CONTACTO -->
              <section>
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Información de Contacto</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div>
                    <!-- CORREGIDO -->
                    <InputLabel for="celular_1" value="Celular Principal (Opcional)" />
                    <TextInput v-model="form.celular_1" id="celular_1" type="text" class="mt-1 block w-full" />
                    <InputError :message="form.errors.celular_1" class="mt-2" />
                  </div>
                  <div>
                    <InputLabel for="celular_2" value="Celular Secundario (Opcional)" />
                    <TextInput v-model="form.celular_2" id="celular_2" type="text" class="mt-1 block w-full" />
                  </div>
                  <div>
                    <!-- CORREGIDO -->
                    <InputLabel for="correo_1" value="Correo Principal (Opcional)" />
                    <TextInput v-model="form.correo_1" id="correo_1" type="email" class="mt-1 block w-full" />
                    <InputError :message="form.errors.correo_1" class="mt-2" />
                  </div>
                  <div>
                    <InputLabel for="correo_2" value="Correo Secundario (Opcional)" />
                    <TextInput v-model="form.correo_2" id="correo_2" type="email" class="mt-1 block w-full" />
                  </div>
                   <div>
                    <InputLabel for="telefono_fijo" value="Teléfono Fijo (Opcional)" />
                    <TextInput v-model="form.telefono_fijo" id="telefono_fijo" type="text" class="mt-1 block w-full" />
                    <InputError :message="form.errors.telefono_fijo" class="mt-2" />
                  </div>
                </div>
              </section>

              <!-- SECCIÓN 3: DIRECCIONES (CORREGIDA) -->
              <section>
                <div class="flex items-center justify-between mb-2">
                  <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Direcciones</h3>
                  <button type="button" @click="addAddressRow" class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-3 py-1.5 text-white text-sm hover:bg-indigo-700">
                    <PlusIcon class="h-4 w-4" /> Agregar dirección
                  </button>
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">Añade las direcciones que necesites (Casa, Oficina, Trabajo, etc.).</p>
                <div v-if="!form.addresses.length" class="rounded-md border border-dashed border-gray-300 dark:border-gray-600 p-4 text-center text-sm text-gray-500 dark:text-gray-400">
                  No hay direcciones todavía.
                </div>
                <div v-for="(addr, idx) in form.addresses" :key="idx" class="mt-3 grid grid-cols-1 gap-3 rounded-md border border-gray-200 p-3 dark:border-gray-700 sm:grid-cols-12">
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
                    <button type="button" @click="removeAddressRow(idx)" class="inline-flex h-10 w-full items-center justify-center rounded-md border border-gray-300 px-2 py-2 text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                      <XMarkIcon class="h-5 w-5" />
                    </button>
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
              
              <!-- SECCIÓN 4: ADICIONAL -->
              <section>
                 <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Información Adicional</h3>
                 <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                      <InputLabel for="empresa" value="Empresa (Opcional)" />
                      <TextInput v-model="form.empresa" id="empresa" type="text" class="mt-1 block w-full" />
                    </div>
                    <div>
                      <InputLabel for="cargo" value="Cargo (Opcional)" />
                      <TextInput v-model="form.cargo" id="cargo" type="text" class="mt-1 block w-full" />
                    </div>
                    <div class="md:col-span-2">
                      <InputLabel for="observaciones" value="Observaciones (Opcional)" />
                      <Textarea v-model="form.observaciones" id="observaciones" class="mt-1 block w-full" rows="3" />
                    </div>
                 </div>
              </section>

              <!-- SECCIÓN 5: REDES SOCIALES -->
              <section>
                <div class="flex items-center justify-between mb-2">
                  <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Redes Sociales</h3>
                  <button type="button" @click="addLinkRow" class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-3 py-1.5 text-white text-sm hover:bg-indigo-700">
                    <PlusIcon class="h-4 w-4" /> Agregar enlace
                  </button>
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">Añade tantos enlaces como necesites (Facebook, Instagram, etc.).</p>
                <div v-if="!form.social_links.length" class="rounded-md border border-dashed border-gray-300 dark:border-gray-600 p-4 text-center text-sm text-gray-500 dark:text-gray-400">
                  No hay enlaces todavía.
                </div>
                <div v-for="(l, idx) in form.social_links" :key="idx" class="grid grid-cols-1 sm:grid-cols-12 gap-3 rounded-md border border-gray-200 dark:border-gray-700 p-3 mt-3">
                  <div class="sm:col-span-4">
                    <InputLabel :for="`label-${idx}`" value="Etiqueta / Red" />
                    <TextInput v-model.trim="l.label" :id="`label-${idx}`" type="text" class="mt-1 block w-full" list="social-presets" placeholder="Instagram, Sitio Web..." />
                  </div>
                  <div class="sm:col-span-7">
                    <InputLabel :for="`url-${idx}`" value="URL" />
                    <TextInput v-model.trim="l.url" :id="`url-${idx}`" type="url" class="mt-1 block w-full" placeholder="https://ejemplo.com/usuario" @blur="normalizeUrl(l)" />
                  </div>
                  <div class="sm:col-span-1 flex items-end">
                    <button type="button" @click="removeLinkRow(idx)" class="inline-flex w-full items-center justify-center rounded-md border border-gray-300 px-2 py-2 text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">
                      <XMarkIcon class="h-5 w-5" />
                    </button>
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

              <div class="flex justify-end">
                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
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
