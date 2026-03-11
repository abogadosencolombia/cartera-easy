<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Dropdown from '@/Components/Dropdown.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, watch, computed } from 'vue';
import { 
  MagnifyingGlassIcon, PlusIcon, ArrowDownTrayIcon, PencilSquareIcon, 
  TrashIcon, EyeIcon, BuildingOfficeIcon, ArrowPathIcon, XMarkIcon, 
  FunnelIcon, BarsArrowUpIcon, BarsArrowDownIcon, ChevronDownIcon,
  UserGroupIcon, IdentificationIcon
} from '@heroicons/vue/24/outline';
import { debounce } from 'lodash';
import Swal from 'sweetalert2';

const props = defineProps({
  personas: Object,      
  filters: Object,       
  cooperativas: Array,   
  abogados: Array,       
});

const params = reactive({
  search: props.filters.search || '',
  status: props.filters.status || 'active',
  cooperativa_id: props.filters.cooperativa_id || '',
  abogado_id: props.filters.abogado_id || '',
  tipo_rol: props.filters.tipo_rol || '', // 'deudor', 'demandado'
  sort: props.filters.sort || 'created_at',
  direction: props.filters.direction || 'desc',
});

const sortOption = computed({
  get: () => `${params.sort}|${params.direction}`,
  set: (val) => { const [field, dir] = val.split('|'); params.sort = field; params.direction = dir; }
});

watch(params, debounce(() => {
  router.get(route('personas.index'), params, { preserveState: true, preserveScroll: true, replace: true });
}, 300));

const clearAllFilters = () => {
  Object.assign(params, { search: '', cooperativa_id: '', abogado_id: '', tipo_rol: '', status: 'active', sort: 'created_at', direction: 'desc' });
};

const exportExcel = () => {
  window.location.href = route('personas.export.excel', params);
};

const deletePersona = (id) => {
  Swal.fire({
    title: '¿Suspender persona?', text: "Se moverá a la papelera.", icon: 'warning',
    showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Sí, suspender'
  }).then((res) => { if (res.isConfirmed) router.delete(route('personas.destroy', id)); });
};

const restorePersona = (id) => {
  router.post(route('personas.restore', id));
};

const getRandomColor = (id) => {
  const colors = ['bg-blue-100 text-blue-700', 'bg-green-100 text-green-700', 'bg-purple-100 text-purple-700', 'bg-amber-100 text-amber-700'];
  return colors[id % colors.length];
};

const checkIncompleta = (p) => {
    const faltaContacto = !p.celular_1 && !p.correo_1;
    const faltaFechas = !p.fecha_nacimiento || !p.fecha_expedicion;
    const faltaAsignacion = !p.cooperativas?.length || !p.abogados?.length;
    return faltaContacto || faltaFechas || faltaAsignacion;
};
</script>

<template>
  <Head title="Directorio de Personas" />
  <AuthenticatedLayout>
    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h2 class="font-bold text-2xl text-blue-500 dark:text-gray-200">Directorio de Personas</h2>
            <p class="text-sm text-gray-500 mt-1">Gestión centralizada de deudores, codeudores y demandados.</p>
          </div>
          <div class="flex items-center gap-3">
              <SecondaryButton 
                  @click="exportExcel" 
                  class="!bg-blue-500 hover:!bg-blue-600 !text-white border-none"
              >
                  <ArrowDownTrayIcon class="w-4 h-4 mr-2" /> 
                  Exportar
              </SecondaryButton>

              <Link :href="route('personas.create')">
                  <PrimaryButton class="!bg-indigo-600 hover:!bg-indigo-700">
                      <PlusIcon class="w-4 h-4 mr-2" /> 
                      Nueva Persona
                  </PrimaryButton>
              </Link>
          </div>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow-xl rounded-2xl border border-gray-200 dark:border-gray-700 overflow-hidden">
          
          <!-- FILTROS -->
          <div class="p-6 bg-gray-50/50 dark:bg-gray-900/20 border-b dark:border-gray-700 space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
              <nav class="flex p-1 bg-white dark:bg-gray-900 rounded-xl border dark:border-gray-700 shadow-sm">
                <button @click="params.status = 'active'" :class="[params.status === 'active' ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300' : 'text-gray-500']" class="px-4 py-2 text-sm font-bold rounded-lg transition-all">Activos</button>
                <button @click="params.status = 'suspended'" :class="[params.status === 'suspended' ? 'bg-red-50 text-red-700 dark:bg-red-900/40 dark:text-red-300' : 'text-gray-500']" class="px-4 py-2 text-sm font-bold rounded-lg transition-all">Papelera</button>
              </nav>
              <button v-if="params.search || params.tipo_rol || params.cooperativa_id" @click="clearAllFilters" class="text-xs text-red-600 font-bold uppercase hover:underline">Limpiar Filtros</button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-5 gap-4">
              <div class="relative lg:col-span-2">
                <MagnifyingGlassIcon class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" />
                <input v-model="params.search" type="text" placeholder="Buscar por nombre o documento..." class="pl-10 w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-sm shadow-sm" />
              </div>

              <Dropdown align="left" width="full">
                <template #trigger>
                  <button type="button" class="w-full flex justify-between items-center bg-white dark:bg-gray-900 border dark:border-gray-600 p-2.5 rounded-xl text-sm shadow-sm">
                    <span class="truncate">{{ params.tipo_rol ? (params.tipo_rol === 'demandado' ? 'Solo Demandados' : 'Solo Deudores') : 'Todos los Roles' }}</span>
                    <ChevronDownIcon class="h-4 w-4" />
                  </button>
                </template>
                <template #content>
                  <button @click="params.tipo_rol = ''" class="block w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">Todos los Roles</button>
                  <button @click="params.tipo_rol = 'deudor'" class="block w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">Deudores / Clientes</button>
                  <button @click="params.tipo_rol = 'demandado'" class="block w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">Demandados</button>
                </template>
              </Dropdown>

              <Dropdown align="left" width="full">
                <template #trigger>
                  <button type="button" class="w-full flex justify-between items-center bg-white dark:bg-gray-900 border dark:border-gray-600 p-2.5 rounded-xl text-sm shadow-sm">
                    <span class="truncate">{{ params.cooperativa_id ? cooperativas.find(c => c.id === params.cooperativa_id)?.nombre : 'Cooperativa...' }}</span>
                    <ChevronDownIcon class="h-4 w-4" />
                  </button>
                </template>
                <template #content>
                  <div class="max-h-60 overflow-y-auto"><button @click="params.cooperativa_id = ''" class="block w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">Todas</button>
                  <button v-for="c in cooperativas" :key="c.id" @click="params.cooperativa_id = c.id" class="block w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">{{ c.nombre }}</button></div>
                </template>
              </Dropdown>

              <Dropdown align="right" width="full">
                <template #trigger>
                  <button type="button" class="w-full flex justify-between items-center bg-white dark:bg-gray-900 border dark:border-gray-600 p-2.5 rounded-xl text-sm shadow-sm">
                    <span>{{ sortOption === 'created_at|desc' ? 'Más Recientes' : 'A - Z' }}</span>
                    <BarsArrowDownIcon class="h-4 w-4" />
                  </button>
                </template>
                <template #content>
                  <button @click="sortOption = 'created_at|desc'" class="block w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">Más Recientes</button>
                  <button @click="sortOption = 'nombre_completo|asc'" class="block w-full text-left px-4 py-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700">Nombre (A-Z)</button>
                </template>
              </Dropdown>
            </div>
          </div>

          <!-- TABLA -->
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead class="bg-gray-50/50 dark:bg-gray-900/40">
                <tr class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">
                  <th class="px-6 py-4 text-left">Persona / Rol</th>
                  <th class="px-6 py-4 text-left">Identificación</th>
                  <th class="px-6 py-4 text-left">Asignaciones</th>
                  <th class="px-6 py-4 text-right">Acciones</th>
                </tr>
              </thead>
              <tbody class="divide-y dark:divide-gray-700">
                <tr v-for="p in personas.data" :key="p.id" class="hover:bg-indigo-50/30 dark:hover:bg-indigo-900/10 transition-all group">
                  <td class="px-6 py-4">
                    <div class="flex items-center gap-4">
                      <div :class="`h-10 w-10 rounded-xl flex items-center justify-center font-bold ${getRandomColor(p.id)} shadow-sm`">{{ p.nombre_completo[0] }}</div>
                      <div>
                        <div class="flex items-center gap-2">
                            <Link :href="route('personas.show', p.id)" class="font-bold text-gray-900 dark:text-gray-100 hover:text-indigo-600">{{ p.nombre_completo }}</Link>
                            <!-- Indicador de Información Incompleta -->
                            <span v-if="checkIncompleta(p)" class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-black bg-amber-100 text-amber-700 border border-amber-200 shadow-sm" title="Faltan datos de contacto, fechas o asignaciones">
                                FALTA INFO
                            </span>
                        </div>
                        <div class="flex gap-2 mt-1">
                          <span v-if="p.es_demandado" class="text-[9px] font-black bg-red-100 text-red-700 px-1.5 py-0.5 rounded border border-red-200 uppercase">Demandado</span>
                          <span v-else class="text-[9px] font-black bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded border border-emerald-200 uppercase">Deudor / Cliente</span>
                        </div>
                      </div>
                    </div>
                  </td>
                  <td class="px-6 py-4">
                    <div class="text-xs font-bold text-gray-500 uppercase">{{ p.tipo_documento }}</div>
                    <div class="text-sm font-mono text-gray-700 dark:text-gray-300">
                        {{ p.numero_documento }}<template v-if="p.tipo_documento === 'NIT' && p.dv">-{{ p.dv }}</template>
                    </div>
                  </td>
                  <td class="px-6 py-4">
                    <div class="flex flex-wrap gap-1 max-w-[200px]">
                      <span v-for="c in p.cooperativas" :key="c.id" class="text-[10px] bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded-full truncate">{{ c.nombre }}</span>
                      <span v-if="!p.cooperativas?.length" class="text-xs italic text-gray-400">Sin empresa</span>
                    </div>
                  </td>
                  <td class="px-6 py-4 text-right">
                    <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                      <Link :href="route('personas.show', p.id)" class="p-2 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 rounded-lg hover:scale-110 transition"><EyeIcon class="w-4 h-4"/></Link>
                      <Link :href="route('personas.edit', p.id)" class="p-2 bg-blue-50 dark:bg-blue-900/30 text-blue-600 rounded-lg hover:scale-110 transition"><PencilSquareIcon class="w-4 h-4"/></Link>
                      <button v-if="p.deleted_at" @click="restorePersona(p.id)" class="p-2 bg-green-50 text-green-600 rounded-lg"><ArrowPathIcon class="w-4 h-4"/></button>
                      <button v-else @click="deletePersona(p.id)" class="p-2 bg-red-50 text-red-600 rounded-lg"><TrashIcon class="w-4 h-4"/></button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="personas.links?.length > 3" class="p-6 border-t dark:border-gray-700 flex justify-center">
            <div class="flex gap-1">
              <Link v-for="(link, i) in personas.links" :key="i" :href="link.url || '#'" v-html="link.label" :class="[link.active ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-gray-900 text-gray-500 border dark:border-gray-700 hover:bg-gray-50']" class="px-3 py-1.5 text-xs font-bold rounded-lg" />
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
