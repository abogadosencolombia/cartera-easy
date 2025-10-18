<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import TextInput from '@/Components/TextInput.vue';
import Pagination from '@/Components/Pagination.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import Textarea from '@/Components/Textarea.vue';
import { debounce } from 'lodash';
import { useProcesos } from '@/composables/useProcesos';

const props = defineProps({
  procesos: { type: Object, required: true },
  filtros: { type: Object, default: () => ({}) },
});

const { getRevisionStatus } = useProcesos();

// --- LÓGICA DE FILTROS (SIN CAMBIOS) ---
const search = ref(props.filtros.search);
const estado = ref(props.filtros.estado || 'TODOS');

const applyFilters = debounce(() => {
    const params = {
        search: search.value,
        estado: estado.value === 'TODOS' ? '' : estado.value,
    };
    
    router.get(route('procesos.index'), params, {
        preserveState: true,
        replace: true,
    });
}, 300);

watch(search, applyFilters);
watch(estado, applyFilters);


// --- LÓGICA PARA MODALES ---

// Lógica para Cerrar Caso
const procesoToManage = ref(null);
const showCloseModal = ref(false);
const closeForm = useForm({ nota_cierre: '' });

const openCloseModal = (proceso) => {
    procesoToManage.value = proceso;
    closeForm.reset();
    showCloseModal.value = true;
};

const submitCloseCase = () => {
    if (!procesoToManage.value) return;
    closeForm.patch(route('procesos.close', procesoToManage.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showCloseModal.value = false;
            procesoToManage.value = null;
        }
    });
};

// Lógica para Reabrir Caso
const showReopenModal = ref(false);
const reopenForm = useForm({});

const openReopenModal = (proceso) => {
    procesoToManage.value = proceso;
    showReopenModal.value = true;
};

const submitReopenCase = () => {
    if (!procesoToManage.value) return;
    reopenForm.patch(route('procesos.reopen', procesoToManage.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showReopenModal.value = false;
            procesoToManage.value = null;
        }
    });
};
</script>

<template>
  <Head title="Gestión de Radicados" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex flex-col md:flex-row items-center justify-between gap-4">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
          Gestión de Radicados
        </h2>
        <div class="flex items-center gap-2">
          <Link :href="route('procesos.create')">
            <PrimaryButton>
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
              Registrar Radicado
            </PrimaryButton>
          </Link>
        </div>
      </div>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <!-- Panel de Control y Búsqueda -->
        <div class="mb-6 p-4 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
           <div class="flex flex-col md:flex-row items-center gap-4">
               <div class="relative flex-grow w-full md:w-auto">
                 <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                   <svg class="w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                     <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                   </svg>
                 </div>
                 <TextInput
                   v-model="search"
                   type="text"
                   class="block w-full pl-10"
                   placeholder="Buscar por radicado, asunto, etc..."
                 />
               </div>
               
               <div class="flex-shrink-0 w-full md:w-auto">
                   <label for="estado-filtro" class="sr-only">Filtrar por estado</label>
                   <select
                       id="estado-filtro"
                       v-model="estado"
                       class="block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                   >
                       <option value="TODOS">Todos los Estados</option>
                       <option value="ACTIVO">Solo Activos</option>
                       <option value="CERRADO">Solo Cerrados</option>
                   </select>
               </div>
           </div>
        </div>

        <!-- Listado de Radicados con Transiciones -->
        <transition
          mode="out-in"
          enter-active-class="transition-all duration-300 ease-out"
          enter-from-class="opacity-0 transform -translate-y-4"
          enter-to-class="opacity-100 transform translate-y-0"
          leave-active-class="transition-all duration-200 ease-in"
          leave-from-class="opacity-100 transform translate-y-0"
          leave-to-class="opacity-0 transform -translate-y-4"
        >
          <div v-if="procesos.data.length > 0" class="relative">
            <transition-group
                tag="div"
                class="space-y-4"
                enter-active-class="transition-all duration-500 ease-out"
                enter-from-class="opacity-0 transform translate-x-8"
                enter-to-class="opacity-100 transform translate-x-0"
                leave-active-class="transition-all duration-300 ease-in absolute w-full"
                leave-from-class="opacity-100 transform scale-100"
                leave-to-class="opacity-0 transform scale-95"
            >
              <div v-for="proceso in procesos.data" :key="proceso.id"
                   class="bg-white dark:bg-gray-800 overflow-hidden shadow-md sm:rounded-lg p-5 transition duration-300 ease-in-out hover:shadow-xl dark:hover:ring-1 dark:hover:ring-gray-700"
                   :class="{ 'opacity-60 grayscale-[50%]': proceso.estado === 'CERRADO' }"
              >
                
                <div class="flex flex-col md:flex-row justify-between md:items-start gap-6">
                  <!-- === SECCIÓN PRINCIPAL DE INFORMACIÓN (IZQUIERDA) === -->
                  <div class="flex-grow min-w-0">
                    <!-- Radicado, Estado y Asunto -->
                    <div class="mb-4">
                      <div class="flex items-center gap-3 mb-1 flex-wrap">
                        <Link :href="route('procesos.show', proceso.id)" class="group min-w-0">
                          <h3 class="text-lg font-bold text-indigo-600 dark:text-indigo-400 group-hover:underline truncate" :title="proceso.radicado">
                            {{ proceso.radicado }}
                          </h3>
                        </Link>
                        <span
                          class="inline-flex flex-shrink-0 items-center rounded-md px-2 py-1 text-xs font-bold"
                          :class="{
                            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300': proceso.estado === 'ACTIVO',
                            'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300': proceso.estado === 'CERRADO',
                          }"
                        >
                          {{ proceso.estado }}
                        </span>
                      </div>
                      <p class="text-sm text-gray-600 dark:text-gray-400 truncate" :title="proceso.asunto">
                        {{ proceso.asunto || 'Sin asunto' }}
                      </p>
                    </div>
                    
                    <!-- Divisor -->
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                      <!-- Detalles del Proceso (Partes, Juzgado, etc.) -->
                      <dl class="space-y-3 text-sm">
                        <!-- Partes -->
                        <div class="flex items-start gap-3">
                          <dt class="flex-shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M15 21v-1a6 6 0 00-5.197-5.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-3.197 1.053m-2.454 4.092A3.986 3.986 0 013 18v-1a6 6 0 0112 0v1z" /></svg>
                          </dt>
                          <dd class="text-gray-800 dark:text-gray-200 min-w-0">
                            <span class="font-medium truncate">{{ proceso.demandante?.nombre_completo || 'N/A' }}</span>
                            <span class="text-gray-400 mx-1">vs.</span>
                            <span class="font-medium truncate">{{ proceso.demandado?.nombre_completo || 'N/A' }}</span>
                          </dd>
                        </div>
                        <!-- Juzgado -->
                        <div class="flex items-start gap-3">
                          <dt class="flex-shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a1 1 0 110 2h-3a1 1 0 01-1-1v-2a1 1 0 00-1-1H9a1 1 0 00-1 1v2a1 1 0 01-1 1H4a1 1 0 110-2V4zm3 1h2v2H7V5zm2 4H7v2h2V9zm2-4h2v2h-2V5zm2 4h-2v2h2V9z" clip-rule="evenodd" /></svg>
                          </dt>
                          <dd class="text-gray-700 dark:text-gray-300 min-w-0 break-words">
                            {{ proceso.juzgado?.nombre || 'Juzgado no asignado' }}
                          </dd>
                        </div>
                        <!-- Tipo Proceso -->
                        <div class="flex items-start gap-3">
                          <dt class="flex-shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 9.776c.112-.017.227-.026.344-.026h15.812c.117 0 .232.009.344.026m-16.5 0a2.25 2.25 0 00-1.883 2.542l.857 6a2.25 2.25 0 002.227 1.932H19.05a2.25 2.25 0 002.227-1.932l.857-6a2.25 2.25 0 00-1.883-2.542m-16.5 0A2.25 2.25 0 015.625 7.5h12.75c1.135 0 2.099.774 2.231 1.879M15 9.75a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                          </dt>
                          <dd class="text-gray-700 dark:text-gray-300">
                            {{ proceso.tipo_proceso?.nombre || 'Tipo no definido' }}
                          </dd>
                        </div>
                      </dl>
                    </div>
                  </div>

                  <!-- === SECCIÓN DE ESTADO Y ACCIONES (DERECHA) === -->
                  <div class="flex-shrink-0 flex flex-col items-start md:items-end justify-between gap-4 w-full md:w-auto md:min-w-[200px]">
                    <div class="flex flex-col items-start md:items-end w-full">
                      <span class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Próxima Revisión</span>
                      <span
                        class="inline-flex items-center rounded-md px-2 py-1 text-xs font-semibold"
                        :class="getRevisionStatus(proceso.fecha_proxima_revision).classes"
                      >
                        {{ getRevisionStatus(proceso.fecha_proxima_revision).text }}
                      </span>
                    </div>
                    <div class="flex items-center gap-3 self-stretch md:self-end mt-auto flex-wrap justify-end">
                      <DangerButton @click="openCloseModal(proceso)" v-if="proceso.estado === 'ACTIVO'" small>Cerrar</DangerButton>
                      <PrimaryButton @click="openReopenModal(proceso)" v-if="proceso.estado === 'CERRADO'" small class="!bg-blue-600">Reabrir</PrimaryButton>
                      
                      <!-- INICIO DE LA MODIFICACIÓN -->
                      <Link :href="route('procesos.edit', proceso.id)">
                        <SecondaryButton small>Editar</SecondaryButton>
                      </Link>
                      <Link :href="route('procesos.show', proceso.id)">
                        <PrimaryButton small>Ver Detalles</PrimaryButton>
                      </Link>
                      <!-- FIN DE LA MODIFICACIÓN -->
                    </div>
                  </div>
                </div>
              </div>
            </transition-group>
          </div>

          <!-- Estado Vacío -->
          <div v-else class="text-center bg-white dark:bg-gray-800 rounded-lg shadow-sm p-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
              <path vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">No se encontraron radicados</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Intenta ajustar los filtros de búsqueda o registra un nuevo radicado.</p>
            <div class="mt-6">
             <Link :href="route('procesos.create')">
              <PrimaryButton>
                + Registrar Nuevo Radicado
              </PrimaryButton>
             </Link>
            </div>
          </div>
        </transition>

        <!-- Paginación -->
        <Pagination v-if="procesos.links.length > 3" class="mt-6" :links="procesos.links" />

      </div>
    </div>
    
    <!-- MODALES DE ACCIÓN -->
    <Modal :show="showCloseModal" @close="showCloseModal = false">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Cerrar Radicado</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Estás a punto de cerrar el radicado <span class="font-bold">{{ procesoToManage?.radicado }}</span>. Por favor, añade una nota de cierre.
            </p>
            <div class="mt-6">
                <InputLabel for="nota_cierre" value="Nota de Cierre *" required />
                <Textarea v-model="closeForm.nota_cierre" id="nota_cierre" class="mt-1 block w-full" rows="4" />
                <InputError :message="closeForm.errors.nota_cierre" class="mt-2" />
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton @click="showCloseModal = false">Cancelar</SecondaryButton>
                <DangerButton @click="submitCloseCase" :disabled="closeForm.processing" :class="{ 'opacity-25': closeForm.processing }">
                    {{ closeForm.processing ? 'Cerrando...' : 'Confirmar Cierre' }}
                </DangerButton>
            </div>
        </div>
    </Modal>

    <Modal :show="showReopenModal" @close="showReopenModal = false">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Reabrir Radicado</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                ¿Estás seguro de que quieres reabrir el radicado <span class="font-bold">{{ procesoToManage?.radicado }}</span>? Su estado volverá a "ACTIVO".
            </p>
            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton @click="showReopenModal = false">Cancelar</SecondaryButton>
                <PrimaryButton @click="submitReopenCase" :disabled="reopenForm.processing" :class="{ 'opacity-25': reopenForm.processing }" class="!bg-blue-600 hover:!bg-blue-700 focus:!bg-blue-700 active:!bg-blue-800 focus:!ring-blue-500">
                    {{ reopenForm.processing ? 'Reabriendo...' : 'Sí, Reabrir' }}
                </PrimaryButton>
            </div>
        </div>
    </Modal>
    
  </AuthenticatedLayout>
</template>

