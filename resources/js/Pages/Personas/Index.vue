<script setup>
/**
 * Personas/Index.vue
 * v2.3 - Versión final con paginación y filtros funcionando
 */
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SocialLinksDropdown from '@/Components/SocialLinksDropdown.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import { ref, watch, reactive, computed, onMounted, onUnmounted } from 'vue';
import throttle from 'lodash/throttle';

import {
  TrashIcon, PencilIcon, ChatBubbleLeftEllipsisIcon, EnvelopeIcon, LinkIcon, UserPlusIcon,
  MagnifyingGlassIcon, ArrowUpIcon, ArrowDownIcon, SparklesIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
  personas: Object,
  can: Object,
  filters: Object,
});

const page = usePage();
const loading = ref(false);

/* ------------------------- Barra de Carga y Transiciones ------------------------- */
onMounted(() => {
  router.on('start', () => loading.value = true);
  router.on('finish', () => loading.value = false);
});

onUnmounted(() => {
  router.on('start', () => {});
  router.on('finish', () => {});
});

/* ------------------------- Filtros y Ordenamiento ------------------------- */
const filtersForm = reactive({
  search: props.filters?.search ?? '',
  sort_by: props.filters?.sort_by ?? 'created_at',
  sort_direction: props.filters?.sort_direction ?? 'desc',
});

watch(filtersForm, throttle(() => {
  router.get(route('personas.index'), filtersForm, {
    preserveState: true,
    replace: true,
    preserveScroll: true,
  });
}, 300));

const resetFilters = () => {
  filtersForm.search = '';
  filtersForm.sort_by = 'created_at';
  filtersForm.sort_direction = 'desc';
};

const areFiltersActive = computed(() =>
    filtersForm.search !== '' ||
    filtersForm.sort_by !== 'created_at' ||
    filtersForm.sort_direction !== 'desc'
);

/* ------------------------- Modal de eliminación ------------------------- */
const confirmingDeletion = ref(false);
const itemToDelete = ref(null);
const deleteForm = useForm({});

const confirmDeletion = (item) => {
  itemToDelete.value = item;
  confirmingDeletion.value = true;
};

const deleteItem = () => {
  deleteForm.delete(route('personas.destroy', itemToDelete.value.id), {
    preserveScroll: true,
    onSuccess: () => closeModal(),
  });
};

const closeModal = () => {
  confirmingDeletion.value = false;
  itemToDelete.value = null;
};

/* ------------------------------ Utilidades ------------------------------ */
const formatWhatsAppLink = (phone) => {
  if (!phone) return '#';
  const cleaned = String(phone).replace(/\D/g, '');
  return `https://wa.me/57${cleaned}`;
};

const emailSubject = encodeURIComponent('Contacto desde el CRM');
const emailBody = encodeURIComponent('Cordial saludo,');
const gmailCompose = (email) =>
  `https://mail.google.com/mail/?view=cm&fs=1&to=${encodeURIComponent(email)}&su=${emailSubject}&body=${emailBody}`;
const mailtoUrl = (email) => `mailto:${email}?subject=${emailSubject}&body=${emailBody}`;

const sendEmail = (raw) => {
  if (!raw) return;
  const email = String(raw).trim().toLowerCase();
  const w = window.open(gmailCompose(email), '_blank', 'noopener,noreferrer');
  if (!w) window.location.href = mailtoUrl(email);
};
</script>

<style>
/* Animación para las filas de la tabla */
.list-move,
.list-enter-active,
.list-leave-active {
  transition: all 0.5s cubic-bezier(0.55, 0, 0.1, 1);
}
.list-enter-from,
.list-leave-to {
  opacity: 0;
  transform: scaleY(0.01) translate(30px, 0);
}
.list-leave-active {
  position: absolute;
}
/* Estilos para la barra de carga */
.loading-bar {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 2px;
  background-color: #4f46e5;
  transform-origin: 0% 50%;
  animation: loading-animation 1.5s infinite cubic-bezier(0.4, 0, 0.2, 1);
}
@keyframes loading-animation {
  0% { transform: translateX(-100%) scaleX(0.1); }
  50% { transform: translateX(0%) scaleX(0.8); }
  100% { transform: translateX(100%) scaleX(0.1); }
}
</style>

<template>
  <Head title="Directorio de Personas" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
          Gestión de Personas
        </h2>
        <Link
          :href="route('personas.create')"
          class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-white shadow transition hover:bg-indigo-700 active:bg-indigo-800"
        >
          <UserPlusIcon class="h-5 w-5" />
          Registrar Persona
        </Link>
      </div>
    </template>

    <div class="py-10">
      <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
        <transition
          enter-active-class="transition ease-out duration-300"
          enter-from-class="transform opacity-0 scale-95"
          enter-to-class="transform opacity-100 scale-100"
          leave-active-class="transition ease-in duration-200"
          leave-from-class="transform opacity-100 scale-100"
          leave-to-class="transform opacity-0 scale-95"
        >
          <div
            v-if="page.props.flash.success"
            class="rounded-md border-l-4 border-green-500 bg-green-50 p-4 text-green-800 dark:bg-green-900/30 dark:text-green-100"
            role="alert"
          >
            <p class="font-semibold">¡Éxito!</p>
            <p class="text-sm">{{ page.props.flash.success }}</p>
          </div>
        </transition>

        <div class="relative overflow-hidden rounded-xl border border-gray-100 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
          <div v-if="loading" class="loading-bar"></div>

          <div class="border-b border-gray-100 p-4 dark:border-gray-700 sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
              <div class="relative flex-1">
                <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                <TextInput
                  v-model="filtersForm.search"
                  type="text"
                  placeholder="Buscar por nombre, ID, doc..."
                  class="block w-full pl-10"
                />
              </div>
              <div class="flex flex-shrink-0 items-center gap-2">
                <select
                  v-model="filtersForm.sort_by"
                  class="rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900"
                >
                  <option value="created_at">Fecha de Creación</option>
                  <option value="nombre_completo">Nombre</option>
                </select>
                <button
                  @click="filtersForm.sort_direction = filtersForm.sort_direction === 'asc' ? 'desc' : 'asc'"
                  class="rounded-md border border-gray-300 p-2 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700"
                  aria-label="Cambiar dirección de ordenamiento"
                >
                  <ArrowUpIcon v-if="filtersForm.sort_direction === 'asc'" class="h-5 w-5" />
                  <ArrowDownIcon v-else class="h-5 w-5" />
                </button>
                <button
                  v-if="areFiltersActive"
                  @click="resetFilters"
                  class="inline-flex items-center gap-2 rounded-md bg-gray-100 px-3 py-2 text-sm text-gray-700 transition hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                >
                  <SparklesIcon class="h-4 w-4" />
                  Limpiar
                </button>
              </div>
            </div>
          </div>

          <transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
            mode="out-in"
          >
            <div :key="(personas.current_page || 0) + filtersForm.search + filtersForm.sort_by + filtersForm.sort_direction" class="overflow-x-auto">
              <table class="min-w-full">
                <thead class="bg-gray-50/60 dark:bg-gray-700/40">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">Nombre</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">Documento</th>
                    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">Contacto</th>
                    <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300">Acciones</th>
                  </tr>
                </thead>
                <TransitionGroup tag="tbody" name="list" class="divide-y divide-gray-100 dark:divide-gray-700">
                  <tr v-for="persona in (personas.data || personas)" :key="persona.id">
                    <td class="whitespace-nowrap px-6 py-4">
                      <div class="flex items-center gap-3">
                        <div class="grid h-9 w-9 shrink-0 place-content-center rounded-full bg-indigo-100 font-semibold text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-200">
                          {{ (persona.nombre_completo || '?')[0] }}
                        </div>
                        <div class="min-w-0">
                          <div class="truncate font-medium text-gray-900 dark:text-white">{{ persona.nombre_completo }}</div>
                          <div class="truncate text-xs text-gray-500 dark:text-gray-400">ID #{{ persona.id }}</div>
                        </div>
                      </div>
                    </td>
                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                      {{ persona.tipo_documento }} - {{ persona.numero_documento }}
                    </td>
                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                      <div class="flex flex-col">
                        <span v-if="persona.celular_1" class="truncate">{{ persona.celular_1 }}</span>
                        <span v-if="persona.correo_1" class="truncate">{{ persona.correo_1 }}</span>
                        <span v-if="persona.direcciones && persona.direcciones.length" class="truncate text-xs text-gray-400 mt-1" :title="persona.direcciones[0].direccion">
                          {{ persona.direcciones[0].label }}: {{ persona.direcciones[0].direccion }}
                        </span>
                      </div>
                    </td>
                    <td class="whitespace-nowrap px-6 py-4">
                      <div class="flex items-center justify-end gap-1.5 sm:gap-2">
                        <a v-if="persona.celular_1" :href="formatWhatsAppLink(persona.celular_1)" target="_blank" rel="noopener" title="Enviar WhatsApp" class="rounded-full p-2 text-gray-600 transition hover:bg-emerald-50 hover:text-emerald-700 dark:text-gray-300 dark:hover:bg-emerald-900/30 dark:hover:text-emerald-200">
                          <ChatBubbleLeftEllipsisIcon class="h-5 w-5" />
                        </a>
                        <span v-else class="rounded-full p-2 text-gray-400" title="Sin celular">
                          <ChatBubbleLeftEllipsisIcon class="h-5 w-5" />
                        </span>
                        <button v-if="persona.correo_1" type="button" @click="sendEmail(persona.correo_1)" title="Enviar correo" class="rounded-full p-2 text-gray-600 transition hover:bg-sky-50 hover:text-sky-700 dark:text-gray-300 dark:hover:bg-sky-900/30 dark:hover:text-sky-200">
                          <EnvelopeIcon class="h-5 w-5" />
                        </button>
                        <span v-else class="rounded-full p-2 text-gray-400" title="Sin correo">
                          <EnvelopeIcon class="h-5 w-5" />
                        </span>
                        <div class="flex items-center">
                          <div v-if="persona.social_links && persona.social_links.length">
                            <SocialLinksDropdown :links="persona.social_links" />
                          </div>
                          <span v-else class="rounded-full p-2 text-gray-400" title="Sin enlaces sociales">
                            <LinkIcon class="h-5 w-5" />
                          </span>
                        </div>
                        <Link :href="route('personas.edit', persona.id)" title="Editar" class="rounded-full p-2 text-blue-600 transition hover:bg-blue-50 hover:text-blue-700 dark:text-blue-300 dark:hover:bg-blue-900/30 dark:hover:text-blue-200">
                          <PencilIcon class="h-5 w-5" />
                        </Link>
                        <button v-if="can.delete_personas" @click="confirmDeletion(persona)" title="Eliminar" class="rounded-full p-2 text-red-600 transition hover:bg-red-50 hover:text-red-700 dark:text-red-300 dark:hover:bg-red-900/30 dark:hover:text-red-200">
                          <TrashIcon class="h-5 w-5" />
                        </button>
                      </div>
                    </td>
                  </tr>
                  <tr v-if="!(personas.data || personas).length" key="empty-state">
                    <td colspan="4" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                      <div class="mx-auto mb-3 grid h-12 w-12 place-content-center rounded-full bg-gray-100 dark:bg-gray-700">
                        <MagnifyingGlassIcon class="h-6 w-6" />
                      </div>
                      <p class="font-medium">No se encontraron resultados</p>
                      <p class="mt-1 text-sm">Prueba con otro término de búsqueda o limpia los filtros.</p>
                    </td>
                  </tr>
                </TransitionGroup>
              </table>
            </div>
          </transition>
          
          <div v-if="personas.links && personas.links.length > 3" class="flex items-center justify-between border-t border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-800 sm:px-6">
            <p class="hidden text-sm text-gray-700 dark:text-gray-300 sm:block">
              Mostrando <span class="font-medium">{{ personas.from || 1 }}</span> a <span class="font-medium">{{ personas.to || (personas.data || personas).length }}</span> de <span class="font-medium">{{ personas.total || (personas.data || personas).length }}</span> resultados
            </p>
            <div class="flex items-center gap-2">
              <Link
                v-for="(link, k) in personas.links"
                :key="k"
                :href="link.url"
                v-html="link.label"
                class="inline-flex items-center justify-center rounded-md border px-3 py-1.5 text-sm font-medium transition"
                :class="{
                  'bg-indigo-600 text-white border-indigo-600': link.active,
                  'bg-white text-gray-700 border-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700': !link.active && link.url,
                  'bg-white text-gray-400 border-gray-200 cursor-not-allowed dark:bg-gray-800 dark:text-gray-500 dark:border-gray-700': !link.url
                }"
              />
            </div>
          </div>
        </div>
      </div>
    </div>

    <Modal :show="confirmingDeletion" @close="closeModal">
      <div class="p-6">
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">¿Eliminar esta persona?</h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400" v-if="itemToDelete">
          Se eliminará permanentemente <span class="font-semibold">{{ itemToDelete.nombre_completo }}</span>.
          Si está asociada a algún caso, se perderá esa relación. Esta acción no se puede deshacer.
        </p>
        <div class="mt-6 flex justify-end">
          <SecondaryButton @click="closeModal">Cancelar</SecondaryButton>
          <DangerButton
            class="ms-3"
            :class="{ 'opacity-25': deleteForm.processing }"
            :disabled="deleteForm.processing"
            @click="deleteItem"
          >
            Sí, eliminar
          </DangerButton>
        </div>
      </div>
    </Modal>
  </AuthenticatedLayout>
</template>