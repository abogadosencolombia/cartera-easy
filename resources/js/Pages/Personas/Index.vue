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
  UserGroupIcon, IdentificationIcon, InboxIcon, PhoneIcon, EnvelopeIcon,
  UserIcon, ShieldCheckIcon
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

const isDirty = computed(() => {
    return params.search !== '' || params.cooperativa_id !== '' || params.abogado_id !== '' || params.tipo_rol !== '' || params.status !== 'active';
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
    title: '¿Suspender registro?',
    text: "La persona será movida a la papelera y no aparecerá en búsquedas comunes.",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#ef4444',
    confirmButtonText: 'Sí, suspender'
  }).then((res) => { if (res.isConfirmed) router.delete(route('personas.destroy', id)); });
};

const restorePersona = (id) => {
  router.post(route('personas.restore', id), {}, {
      onSuccess: () => Swal.fire('Reactivada', 'La persona vuelve a estar activa.', 'success')
  });
};

const getRandomColor = (id) => {
  const colors = ['bg-indigo-100 text-indigo-700', 'bg-emerald-100 text-emerald-700', 'bg-violet-100 text-violet-700', 'bg-amber-100 text-amber-700', 'bg-rose-100 text-red-700'];
  return colors[id % colors.length];
};

const checkIncompleta = (p) => {
    const faltaContacto = !p.celular_1 && !p.correo_1;
    const faltaFechas = !p.fecha_nacimiento || !p.fecha_expedicion;
    const faltaAsignacion = !p.cooperativas?.length || !p.abogados?.length;
    return faltaContacto || faltaFechas || faltaAsignacion;
};

const copyToClipboard = (text) => {
    if (!text) return;
    navigator.clipboard.writeText(text);
};
</script>

<template>
  <Head title="Directorio de Personas" />
  <AuthenticatedLayout>
    <template #header>
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white dark:bg-gray-800 p-6 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
            <div>
                <h2 class="text-2xl font-black text-gray-900 dark:text-white flex items-center gap-2">
                    <UserGroupIcon class="w-8 h-8 text-indigo-500" />
                    Directorio de Personas
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Base de datos centralizada de Deudores, Clientes y Demandados.
                </p>
            </div>
            <div class="flex items-center gap-3 w-full md:w-auto">
                <button 
                    @click="exportExcel" 
                    class="flex-1 md:flex-none inline-flex items-center justify-center px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white rounded-lg font-bold text-sm transition-all shadow-sm shadow-green-200 dark:shadow-none"
                >
                    <ArrowDownTrayIcon class="w-5 h-5 mr-2" />
                    Exportar Excel
                </button>
                <Link
                    :href="route('personas.create')"
                    class="flex-1 md:flex-none inline-flex items-center justify-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-bold text-sm transition-all shadow-sm shadow-indigo-200 dark:shadow-none"
                >
                    + Nueva Persona
                </Link>
            </div>
        </div>
    </template>

    <div class="py-8 bg-gray-50/50 dark:bg-gray-900/50 min-h-screen">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
        
        <!-- BARRA DE FILTROS AVANZADA -->
        <div class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 space-y-4">
            <div class="flex flex-col xl:flex-row gap-4">
                <!-- Búsqueda Principal -->
                <div class="relative flex-grow">
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-1 ml-1">Búsqueda Maestra</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <MagnifyingGlassIcon class="h-4 w-4 text-gray-400" />
                        </div>
                        <input
                            v-model="params.search"
                            type="text"
                            class="block w-full pl-10 pr-3 py-2.5 border border-gray-200 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm focus:ring-indigo-500 focus:border-indigo-500 dark:text-white font-medium"
                            placeholder="Nombre, número de identificación, alias..."
                        />
                    </div>
                </div>

                <!-- Selectores de Filtro -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 xl:w-2/3">
                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-1 ml-1">Estado</label>
                        <select v-model="params.status" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-xs font-bold uppercase tracking-tight py-2.5">
                            <option value="active">Activos</option>
                            <option value="suspended">Papelera</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-1 ml-1">Rol</label>
                        <select v-model="params.tipo_rol" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-xs font-bold uppercase tracking-tight py-2.5">
                            <option value="">Todos los Roles</option>
                            <option value="deudor">Deudores / Clientes</option>
                            <option value="demandado">Demandados</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-1 ml-1">Empresa</label>
                        <select v-model="params.cooperativa_id" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-xs font-bold uppercase tracking-tight py-2.5">
                            <option value="">Todas</option>
                            <option v-for="c in cooperativas" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-1 ml-1">Orden</label>
                        <select v-model="sortOption" class="w-full rounded-xl border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 text-xs font-bold uppercase tracking-tight py-2.5">
                            <option value="created_at|desc">Más Recientes</option>
                            <option value="nombre_completo|asc">A - Z</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Botón Limpiar y Resultados -->
            <div class="flex justify-between items-center pt-2 border-t border-gray-50 dark:border-gray-700">
                <div class="text-xs text-gray-500 dark:text-gray-400 font-bold uppercase tracking-widest">
                    <span class="text-indigo-600 dark:text-indigo-400">{{ personas.total }}</span> registros encontrados
                </div>
                <button 
                    v-if="isDirty"
                    @click="clearAllFilters"
                    class="inline-flex items-center text-[10px] font-black text-red-500 hover:text-red-700 uppercase tracking-[0.2em] transition-colors"
                >
                    <XMarkIcon class="w-4 h-4 mr-1" />
                    Limpiar Filtros
                </button>
            </div>
        </div>

        <!-- TABLA DE PERSONAS -->
        <div class="bg-white dark:bg-gray-800 shadow-xl rounded-[2rem] border border-gray-100 dark:border-gray-700 overflow-hidden">
          <div class="overflow-x-auto custom-scrollbar-horizontal">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead class="bg-gray-50/50 dark:bg-gray-700/50">
                <tr class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">
                  <th class="px-8 py-5 text-left">Persona / Rol</th>
                  <th class="px-8 py-5 text-left">Identificación</th>
                  <th class="px-8 py-5 text-left">Asignaciones</th>
                  <th class="px-8 py-5 text-left">Contacto</th>
                  <th class="sticky right-0 bg-gray-50 dark:bg-gray-700 px-8 py-5 text-right z-10 shadow-[-4px_0_6px_-2px_rgba(0,0,0,0.05)]">Acciones</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 dark:divide-gray-700 bg-white dark:bg-gray-800">
                <tr v-if="personas.data.length === 0">
                    <td colspan="5" class="px-6 py-32 text-center">
                        <div class="flex flex-col items-center">
                            <div class="p-6 bg-gray-50 dark:bg-gray-700 rounded-full mb-4">
                                <InboxIcon class="h-12 w-12 text-gray-300" />
                            </div>
                            <h3 class="text-xl font-black text-gray-900 dark:text-white uppercase tracking-wider">Directorio Vacío</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2 max-w-xs mx-auto font-medium leading-relaxed">
                                No hay registros que coincidan con los filtros aplicados en este momento.
                            </p>
                            <button @click="clearAllFilters" class="mt-6 px-6 py-2 bg-indigo-50 text-indigo-600 rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-indigo-600 hover:text-white transition-all">Resetear Búsqueda</button>
                        </div>
                    </td>
                </tr>

                <tr v-else v-for="p in personas.data" :key="p.id" class="hover:bg-indigo-50/30 dark:hover:bg-indigo-900/10 transition-all group">
                  <td class="px-8 py-5">
                    <div class="flex items-center gap-5">
                      <div :class="`h-12 w-12 rounded-2xl flex items-center justify-center font-black text-lg transition-transform group-hover:scale-110 ${getRandomColor(p.id)} shadow-sm`">
                          {{ p.nombre_completo[0] }}
                      </div>
                      <div class="min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <Link :href="route('personas.show', p.id)" class="text-sm font-black text-gray-900 dark:text-gray-100 hover:text-indigo-600 transition-colors truncate block max-w-[200px]">
                                {{ p.nombre_completo }}
                            </Link>
                            <span v-if="checkIncompleta(p)" class="inline-flex items-center px-1.5 py-0.5 rounded-md text-[8px] font-black bg-amber-50 text-amber-600 border border-amber-100 uppercase tracking-tighter" title="Faltan datos clave">FALTA INFO</span>
                        </div>
                        <div class="flex gap-2">
                          <span v-if="p.es_demandado" class="text-[9px] font-black bg-red-50 text-red-600 px-2 py-0.5 rounded-lg border border-red-100 uppercase tracking-widest leading-none">Demandado</span>
                          <span v-else class="text-[9px] font-black bg-emerald-50 text-emerald-600 px-2 py-0.5 rounded-lg border border-emerald-100 uppercase tracking-widest leading-none">Deudor / Cliente</span>
                        </div>
                      </div>
                    </div>
                  </td>

                  <td class="px-8 py-5">
                    <div class="flex flex-col">
                        <span class="text-[9px] font-black text-gray-400 uppercase tracking-widest mb-0.5">{{ p.tipo_documento }}</span>
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs font-mono font-bold text-gray-700 dark:text-gray-300">
                                {{ p.numero_documento }}<template v-if="p.tipo_documento === 'NIT' && p.dv">-{{ p.dv }}</template>
                            </span>
                            <button @click="copyToClipboard(p.numero_documento)" class="opacity-0 group-hover:opacity-100 text-gray-300 hover:text-indigo-500 transition-all">
                                <DocumentDuplicateIcon class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>
                  </td>

                  <td class="px-8 py-5">
                    <div class="flex flex-wrap gap-1.5 max-w-[250px]">
                      <span v-for="c in p.cooperativas" :key="c.id" class="text-[9px] font-black bg-gray-50 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400 px-2 py-1 rounded-lg border border-gray-100 dark:border-gray-600 truncate max-w-[120px]" :title="c.nombre">
                          {{ c.nombre }}
                      </span>
                      <span v-if="!p.cooperativas?.length" class="text-[10px] font-bold text-gray-400 italic">Sin empresas</span>
                    </div>
                  </td>

                  <td class="px-8 py-5">
                      <div class="flex flex-col gap-1">
                          <div class="flex items-center gap-2 text-xs font-medium text-gray-600 dark:text-gray-400" v-if="p.celular_1">
                              <PhoneIcon class="w-3 h-3 text-gray-400" /> {{ p.celular_1 }}
                          </div>
                          <div class="flex items-center gap-2 text-xs font-medium text-gray-600 dark:text-gray-400 truncate max-w-[180px]" v-if="p.correo_1" :title="p.correo_1">
                              <EnvelopeIcon class="w-3 h-3 text-gray-400" /> {{ p.correo_1 }}
                          </div>
                          <span v-if="!p.celular_1 && !p.correo_1" class="text-[10px] text-gray-400 font-bold uppercase italic">Sin datos</span>
                      </div>
                  </td>

                  <td class="sticky right-0 bg-white dark:bg-gray-800 group-hover:bg-indigo-50/50 dark:group-hover:bg-gray-700 px-8 py-5 whitespace-nowrap text-right z-10 shadow-[-4px_0_6px_-2px_rgba(0,0,0,0.05)] transition-colors">
                    <div class="flex items-center justify-end gap-2">
                      <Link :href="route('personas.show', p.id)" class="p-2.5 text-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 rounded-xl hover:bg-indigo-600 hover:text-white transition-all shadow-sm" title="Ver Expediente Personal">
                          <EyeIcon class="w-5 h-5"/>
                      </Link>
                      
                      <Link :href="route('personas.edit', p.id)" class="p-2.5 text-blue-500 bg-blue-50 dark:bg-blue-900/30 rounded-xl hover:bg-blue-600 hover:text-white transition-all shadow-sm" title="Editar Información">
                          <PencilSquareIcon class="w-5 h-5"/>
                      </Link>
                      
                      <button v-if="p.deleted_at" @click="restorePersona(p.id)" class="p-2.5 text-green-600 bg-green-50 dark:bg-green-900/30 rounded-xl hover:bg-green-600 hover:text-white transition-all" title="Restaurar de Papelera">
                          <ArrowPathIcon class="w-5 h-5"/>
                      </button>
                      
                      <button v-else @click="deletePersona(p.id)" class="p-2.5 text-red-500 bg-red-50 dark:bg-red-900/30 rounded-xl hover:bg-red-600 hover:text-white transition-all" title="Mover a Papelera">
                          <TrashIcon class="w-5 h-5"/>
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- PAGINACIÓN -->
          <div v-if="personas.links?.length > 3" class="p-8 border-t border-gray-50 dark:border-gray-700 flex justify-center bg-gray-50/30 dark:bg-gray-900/10">
            <div class="flex items-center gap-1.5 p-1.5 bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700">
              <Link v-for="(link, i) in personas.links" :key="i" :href="link.url || '#'" v-html="link.label" 
                :class="[link.active ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-100 dark:shadow-none' : 'text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-700/50']" 
                class="px-4 py-2 text-[11px] font-black uppercase rounded-xl transition-all" />
            </div>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
.custom-scrollbar-horizontal::-webkit-scrollbar { height: 6px; }
.custom-scrollbar-horizontal::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar-horizontal::-webkit-scrollbar-thumb { background-color: rgba(79, 70, 229, 0.1); border-radius: 20px; }
.sticky { position: -webkit-sticky; position: sticky; }
</style>
