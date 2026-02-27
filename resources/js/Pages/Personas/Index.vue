<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, watch, computed } from 'vue';
import { 
  MagnifyingGlassIcon, 
  PlusIcon, 
  ArrowDownTrayIcon, 
  PencilSquareIcon, 
  TrashIcon, 
  EyeIcon, 
  ChatBubbleLeftEllipsisIcon,
  EnvelopeIcon,
  BuildingOfficeIcon,
  ArrowPathIcon,
  XMarkIcon,
  FunnelIcon,
  BarsArrowUpIcon, // Iconos para ordenamiento
  BarsArrowDownIcon
} from '@heroicons/vue/24/outline';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';

// --- PROPS ---
const props = defineProps({
  personas: Object,      
  filters: Object,       
  cooperativas: Array,   
  abogados: Array,       
});

// --- ESTADO DE LOS FILTROS (REACTIVO) ---
const params = reactive({
  search: props.filters.search || '',
  status: props.filters.status || 'active',
  cooperativa_id: props.filters.cooperativa_id || '',
  abogado_id: props.filters.abogado_id || '',
  sort: props.filters.sort || 'created_at',
  direction: props.filters.direction || 'desc',
});

// --- COMPUTED: OPCIÓN DE ORDENAMIENTO COMBINADA ---
const sortOption = computed({
  get() {
    return `${params.sort}|${params.direction}`;
  },
  set(value) {
    const [field, dir] = value.split('|');
    params.sort = field;
    params.direction = dir;
  }
});

// --- WATCHER INTELLIGENTE ---
watch(params, debounce(() => {
  router.get(route('personas.index'), params, { 
    preserveState: true, 
    preserveScroll: true, 
    replace: true 
  });
}, 300));

// --- FUNCIONES DE LIMPIEZA ---
const clearSearch = () => {
  params.search = '';
};

const clearAllFilters = () => {
  params.search = '';
  params.cooperativa_id = '';
  params.abogado_id = '';
  params.status = 'active';
  params.sort = 'created_at';
  params.direction = 'desc';
};

// --- AYUDAS VISUALES ---
const getInitials = (name) => {
  if (!name) return '';
  const parts = name.split(' ');
  if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
  return (parts[0][0] + parts[1][0]).toUpperCase();
};

const getRandomColor = (id) => {
  const colors = ['bg-blue-100 text-blue-700', 'bg-green-100 text-green-700', 'bg-yellow-100 text-yellow-700', 'bg-purple-100 text-purple-700', 'bg-pink-100 text-pink-700'];
  return colors[id % colors.length];
};

const exportExcel = () => {
  const queryParams = new URLSearchParams({
    search: params.search,
    status: params.status,
    cooperativa_id: params.cooperativa_id,
    abogado_id: params.abogado_id
  }).toString();
  
  window.location.href = `${route('personas.export.excel')}?${queryParams}`;
};

// --- ACCIONES CRUD ---
const deletePersona = (id) => {
  Swal.fire({
    title: '¿Estás seguro?',
    text: "La persona será movida a la lista de suspendidos.",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#ef4444',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Sí, suspender',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      router.delete(route('personas.destroy', id), {
        onSuccess: () => Swal.fire('¡Suspendido!', 'La persona ha sido suspendida.', 'success')
      });
    }
  });
};

const restorePersona = (id) => {
  Swal.fire({
    title: '¿Reactivar persona?',
    text: "La persona volverá a la lista de activos.",
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#10b981',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Sí, reactivar',
    cancelButtonText: 'Cancelar'
  }).then((result) => {
    if (result.isConfirmed) {
      router.post(route('personas.restore', id), {}, {
        onSuccess: () => Swal.fire('¡Reactivado!', 'La persona está activa nuevamente.', 'success')
      });
    }
  });
};
</script>

<template>
  <Head title="Directorio de Personas" />

  <AuthenticatedLayout>
    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        
        <!-- Encabezado -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h2 class="font-semibold text-2xl text-gray-800 dark:text-gray-200 leading-tight">
              Gestión de Personas
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Administra clientes, deudores y codeudores.</p>
          </div>
          <div class="flex items-center gap-3">
            <SecondaryButton @click="exportExcel" title="Descargar listado actual">
              <ArrowDownTrayIcon class="w-4 h-4 mr-2" /> Exportar
            </SecondaryButton>
            <Link :href="route('personas.create')">
              <PrimaryButton>
                <PlusIcon class="w-4 h-4 mr-2" /> Nueva Persona
              </PrimaryButton>
            </Link>
          </div>
        </div>

        <!-- Panel Principal -->
        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
          
          <!-- SECCIÓN DE FILTROS (REDISEÑADA) -->
          <div class="p-6 border-b border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/50 space-y-6">
            
            <!-- Fila 1: Pestañas y Reset -->
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
              
              <!-- Pestañas Activos/Suspendidos (Diseño Pill) -->
              <nav class="flex space-x-1 bg-white dark:bg-gray-900 p-1 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
                <button 
                  @click="params.status = 'active'"
                  :class="['flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200', 
                    params.status === 'active' 
                      ? 'bg-indigo-50 text-indigo-700 shadow-sm ring-1 ring-indigo-200 dark:bg-indigo-900/30 dark:text-indigo-300 dark:ring-indigo-800' 
                      : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800']"
                >
                  <!-- Punto indicador -->
                  <span class="relative flex h-2 w-2 mr-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75" v-if="params.status === 'active'"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2" :class="params.status === 'active' ? 'bg-indigo-500' : 'bg-gray-300'"></span>
                  </span>
                  Activos
                </button>
                <button 
                  @click="params.status = 'suspended'"
                  :class="['flex items-center px-4 py-2 text-sm font-medium rounded-lg transition-all duration-200', 
                    params.status === 'suspended' 
                      ? 'bg-red-50 text-red-700 shadow-sm ring-1 ring-red-200 dark:bg-red-900/30 dark:text-red-300 dark:ring-red-800' 
                      : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800']"
                >
                  <TrashIcon class="w-4 h-4 mr-2" :class="params.status === 'suspended' ? 'text-red-500' : 'text-gray-400'" />
                  Papelera
                </button>
              </nav>

              <!-- Botón Limpiar Todo (Animado) -->
              <transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 translate-y-1" enter-to-class="opacity-100 translate-y-0" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 translate-y-1">
                <button 
                  v-if="params.search || params.cooperativa_id || params.abogado_id || params.status === 'suspended' || params.sort !== 'created_at'"
                  @click="clearAllFilters"
                  class="group flex items-center px-3 py-2 text-sm text-gray-500 hover:text-red-600 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg hover:border-red-200 dark:hover:border-red-900 transition-all shadow-sm"
                >
                  <div class="bg-gray-100 dark:bg-gray-800 p-1 rounded-md mr-2 group-hover:bg-red-50 dark:group-hover:bg-red-900/30 transition-colors">
                    <XMarkIcon class="w-3.5 h-3.5" />
                  </div>
                  Limpiar filtros
                </button>
              </transition>
            </div>

            <!-- Fila 2: Grid de Filtros -->
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
              
              <!-- 1. Buscador -->
              <div class="md:col-span-4">
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1 ml-1">Búsqueda rápida</label>
                <div class="relative group">
                  <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <MagnifyingGlassIcon class="h-5 w-5 text-gray-400 group-focus-within:text-indigo-500 transition-colors" />
                  </div>
                  <input 
                    v-model="params.search"
                    type="text" 
                    placeholder="Nombre, cédula o ID..." 
                    class="pl-10 pr-10 block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all sm:text-sm shadow-sm hover:border-gray-400 dark:hover:border-gray-500"
                  >
                  <!-- Botón X limpia busqueda -->
                  <button 
                    v-if="params.search"
                    @click="clearSearch"
                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 cursor-pointer"
                  >
                    <div class="bg-gray-100 dark:bg-gray-700 rounded-full p-0.5 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                      <XMarkIcon class="h-3 w-3" />
                    </div>
                  </button>
                </div>
              </div>

              <!-- 2. Cooperativa -->
              <div class="md:col-span-3">
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1 ml-1">Filtrar por Cooperativa</label>
                <select 
                  v-model="params.cooperativa_id" 
                  class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 sm:text-sm shadow-sm hover:border-gray-400 dark:hover:border-gray-500 transition-all cursor-pointer"
                  :class="{'border-indigo-300 bg-indigo-50/30 text-indigo-900 dark:bg-indigo-900/10 dark:text-indigo-100': params.cooperativa_id}"
                >
                  <option value="">Todas las Cooperativas</option>
                  <option v-for="coop in cooperativas" :key="coop.id" :value="coop.id">{{ coop.nombre }}</option>
                </select>
              </div>

              <!-- 3. Abogado -->
              <div class="md:col-span-3">
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1 ml-1">Filtrar por Abogado</label>
                <select 
                  v-model="params.abogado_id" 
                  class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 sm:text-sm shadow-sm hover:border-gray-400 dark:hover:border-gray-500 transition-all cursor-pointer"
                  :class="{'border-indigo-300 bg-indigo-50/30 text-indigo-900 dark:bg-indigo-900/10 dark:text-indigo-100': params.abogado_id}"
                >
                  <option value="">Todos los Abogados</option>
                  <option v-for="abg in abogados" :key="abg.id" :value="abg.id">{{ abg.name }}</option>
                </select>
              </div>

              <!-- 4. Ordenamiento (Mejorado con iconos) -->
              <div class="md:col-span-2">
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1 ml-1">Orden</label>
                <div class="relative">
                  <select 
                    v-model="sortOption" 
                    class="block w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 sm:text-sm shadow-sm hover:border-gray-400 dark:hover:border-gray-500 transition-all cursor-pointer pl-9"
                  >
                    <option value="created_at|desc">Recientes</option>
                    <option value="created_at|asc">Antiguos</option>
                    <option value="nombre_completo|asc">A - Z</option>
                    <option value="nombre_completo|desc">Z - A</option>
                  </select>
                  <!-- Icono dinámico -->
                  <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                     <BarsArrowDownIcon v-if="params.sort === 'created_at' && params.direction === 'desc'" class="h-4 w-4 text-gray-400" />
                     <BarsArrowUpIcon v-else-if="params.sort === 'created_at' && params.direction === 'asc'" class="h-4 w-4 text-gray-400" />
                     <span v-else class="text-gray-400 text-xs font-bold">AZ</span>
                  </div>
                </div>
              </div>

            </div>
          </div>

          <!-- TABLA DE RESULTADOS -->
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead class="bg-gray-50 dark:bg-gray-700/50">
                <tr>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Persona</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Identificación</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Contacto</th>
                  <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Asignaciones</th>
                  <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Acciones</th>
                </tr>
              </thead>
              <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                
                <!-- Estado Vacío -->
                <tr v-if="personas.data.length === 0">
                  <td colspan="5" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                    <div class="flex flex-col items-center justify-center">
                        <div class="bg-gray-100 dark:bg-gray-700 p-4 rounded-full mb-3">
                          <FunnelIcon class="h-8 w-8 text-gray-400" />
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-200">No se encontraron resultados</h3>
                        <p class="max-w-sm mx-auto mt-1 text-sm">Intenta ajustar los filtros o busca por otro término.</p>
                        <button @click="clearAllFilters" class="mt-4 text-indigo-600 hover:text-indigo-800 font-medium text-sm hover:underline">
                          Restablecer todos los filtros
                        </button>
                    </div>
                  </td>
                </tr>

                <!-- Filas de Datos -->
                <tr v-for="persona in personas.data" :key="persona.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition duration-150 ease-in-out group">
                  
                  <!-- Columna Nombre -->
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                      <div class="flex-shrink-0 h-10 w-10">
                        <span :class="`h-10 w-10 rounded-full flex items-center justify-center text-sm font-bold ${getRandomColor(persona.id)} shadow-sm`">
                          {{ getInitials(persona.nombre_completo) }}
                        </span>
                      </div>
                      <div class="ml-4">
                        <div class="text-sm font-bold text-gray-900 dark:text-gray-100 group-hover:text-indigo-600 transition-colors flex items-center gap-2">
                          {{ persona.nombre_completo }}
                          <span v-if="persona.es_demandado" class="inline-flex items-center px-1.5 py-0.5 rounded-md text-[10px] font-bold bg-amber-100 text-amber-700 border border-amber-200 uppercase tracking-tighter">
                            Demandado
                          </span>
                        </div>
                        <div class="text-xs text-gray-500 flex items-center">
                          <span class="bg-gray-100 text-gray-600 px-1.5 rounded text-[10px] font-mono mr-1">ID: {{ persona.id }}</span>
                        </div>
                      </div>
                    </div>
                  </td>

                  <!-- Columna Documento -->
                  <td class="px-6 py-4 whitespace-nowrap">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-600">
                      {{ persona.tipo_documento }}
                    </span>
                    <div class="text-sm font-mono text-gray-700 dark:text-gray-300 mt-1 ml-1">
                      {{ persona.numero_documento }}
                    </div>
                  </td>

                  <!-- Columna Contacto -->
                  <td class="px-6 py-4">
                    <div class="flex flex-col space-y-1">
                      <div v-if="persona.celular_1 || persona.telefono_fijo" class="flex items-center text-sm text-gray-600 dark:text-gray-300">
                        <ChatBubbleLeftEllipsisIcon class="w-4 h-4 mr-1.5 text-gray-400" />
                        {{ persona.celular_1 || persona.telefono_fijo }}
                      </div>
                      <div v-if="persona.correo_1" class="flex items-center text-sm text-gray-600 dark:text-gray-300">
                        <EnvelopeIcon class="w-4 h-4 mr-1.5 text-gray-400" />
                        <span class="truncate max-w-[140px]" :title="persona.correo_1">{{ persona.correo_1 }}</span>
                      </div>
                    </div>
                  </td>

                  <!-- Columna Asignaciones -->
                  <td class="px-6 py-4 whitespace-nowrap">
                    <div v-if="persona.cooperativas && persona.cooperativas.length > 0" class="flex flex-col gap-1">
                      <span v-for="coop in persona.cooperativas" :key="coop.id" class="inline-flex items-center text-xs text-gray-600 dark:text-gray-300 bg-blue-50 dark:bg-blue-900/20 px-2 py-0.5 rounded border border-blue-100 dark:border-blue-800">
                        <BuildingOfficeIcon class="w-3 h-3 mr-1 text-blue-400" />
                        {{ coop.nombre }}
                      </span>
                    </div>
                    <span v-else class="text-xs text-gray-400 italic pl-2 border-l-2 border-gray-200">Sin asignaciones</span>
                  </td>

                  <!-- Columna Acciones -->
                  <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                    <div class="flex items-center justify-end gap-2">
                      
                      <!-- Acciones Rápidas -->
                      <a v-if="!persona.deleted_at && persona.correo_1" :href="`mailto:${persona.correo_1}`" class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-md transition" title="Enviar Correo">
                        <EnvelopeIcon class="w-5 h-5" />
                      </a>
                      
                      <div class="h-4 w-px bg-gray-300 mx-1"></div>

                      <!-- Ver Detalles -->
                      <Link :href="route('personas.show', persona.id)" class="p-1.5 text-indigo-500 hover:text-indigo-700 hover:bg-indigo-50 rounded-md transition" title="Ver Detalles">
                        <EyeIcon class="w-5 h-5" />
                      </Link>

                      <!-- Reactivar (Si está suspendido) -->
                      <template v-if="persona.deleted_at">
                        <button 
                          v-if="$page.props.auth.user.tipo_usuario === 'admin'"
                          @click="restorePersona(persona.id)" 
                          class="p-1.5 text-green-600 hover:text-green-800 hover:bg-green-50 rounded-md transition flex items-center" 
                          title="Reactivar">
                          <ArrowPathIcon class="w-5 h-5" />
                        </button>
                      </template>

                      <!-- Editar/Eliminar (Si está activo) -->
                      <template v-else>
                        <Link :href="route('personas.edit', persona.id)" class="p-1.5 text-blue-500 hover:text-blue-700 hover:bg-blue-50 rounded-md transition" title="Editar">
                          <PencilSquareIcon class="w-5 h-5" />
                        </Link>

                        <button 
                          v-if="$page.props.auth.user.tipo_usuario === 'admin'"
                          @click="deletePersona(persona.id)" 
                          class="p-1.5 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-md transition" 
                          title="Suspender">
                          <TrashIcon class="w-5 h-5" />
                        </button>
                      </template>

                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Paginación -->
          <div v-if="personas.links && personas.links.length > 3" class="flex justify-center mt-6 pb-6">
             <div class="flex flex-wrap gap-1">
                <Link
                    v-for="(link, k) in personas.links"
                    :key="k"
                    :href="link.url || '#'"
                    v-html="link.label"
                    class="px-3 py-1 text-sm border rounded-md transition-all duration-200"
                    :class="{
                        'bg-indigo-600 text-white border-indigo-600 shadow-sm': link.active,
                        'text-gray-600 border-gray-300 hover:bg-gray-50 hover:border-gray-400 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-700': !link.active,
                        'opacity-50 cursor-not-allowed hover:bg-transparent hover:border-gray-300': !link.url
                    }"
                />
             </div>
          </div>

        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>