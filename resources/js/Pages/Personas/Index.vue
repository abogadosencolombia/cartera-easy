<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SelectInput from '@/Components/SelectInput.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { reactive, watch, computed } from 'vue';
import {
  MagnifyingGlassIcon,
  PlusIcon,
  ArrowDownTrayIcon,
  PencilSquareIcon,
  TrashIcon,
  EyeIcon,
  ArrowPathIcon,
  XMarkIcon,
  UserGroupIcon,
  IdentificationIcon,
  InboxIcon,
  PhoneIcon,
  EnvelopeIcon,
  ShieldCheckIcon,
  DocumentDuplicateIcon,
  BuildingOfficeIcon,
  ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline';
import { debounce } from 'lodash';
import AppAlert from '@/Utils/appAlert';

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
  tipo_rol: props.filters.tipo_rol || '',
  sort: props.filters.sort || 'updated_at',
  direction: props.filters.direction || 'desc',
});

const SIN_EMPRESA_FILTER = 'sin_empresa_o_cooperativa';
const personasData = computed(() => props.personas?.data || []);

const isSpecialNoCompanyCooperativa = (cooperativa) => {
    const nombre = cooperativa?.nombre || '';
    return Number(cooperativa?.id) === 9
        || /^(sin|si)\s+empresa|empresa\s+o\s+cooperativa/i.test(nombre);
};
const cooperativasFiltro = computed(() => (props.cooperativas || []).filter(c => !isSpecialNoCompanyCooperativa(c)));

const sortOption = computed({
  get: () => `${params.sort}|${params.direction}`,
  set: (val) => {
    const [field, dir] = val.split('|');
    params.sort = field;
    params.direction = dir;
  }
});

const isDirty = computed(() => {
    return params.search !== ''
        || params.cooperativa_id !== ''
        || params.abogado_id !== ''
        || params.tipo_rol !== ''
        || params.status !== 'active'
        || params.sort !== 'updated_at'
        || params.direction !== 'desc';
});

const incompleteCount = computed(() => personasData.value.filter(checkIncompleta).length);
const suspendedMode = computed(() => params.status === 'suspended');
const activeFilterCount = computed(() => [params.search, params.cooperativa_id, params.abogado_id, params.tipo_rol, params.status !== 'active' ? params.status : '', sortOption.value !== 'updated_at|desc' ? sortOption.value : ''].filter(Boolean).length);

const summaryCards = computed(() => [
    {
        label: 'Resultados',
        value: props.personas?.total || 0,
        detail: suspendedMode.value ? 'Registros en papelera' : 'Registros activos',
        icon: UserGroupIcon,
        class: 'border-indigo-200 bg-indigo-50 text-indigo-700 dark:border-indigo-900/60 dark:bg-indigo-950/30 dark:text-indigo-300',
    },
    {
        label: 'En pantalla',
        value: personasData.value.length,
        detail: 'Pagina actual',
        icon: IdentificationIcon,
        class: 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/60 dark:bg-sky-950/30 dark:text-sky-300',
    },
    {
        label: 'Datos pendientes',
        value: incompleteCount.value,
        detail: 'Requieren revision',
        icon: ExclamationTriangleIcon,
        class: incompleteCount.value
            ? 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-300'
            : 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-300',
    },
    {
        label: 'Filtros',
        value: activeFilterCount.value,
        detail: activeFilterCount.value ? 'Aplicados' : 'Vista base',
        icon: ShieldCheckIcon,
        class: 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-200',
    },
]);

watch(params, debounce(() => {
  router.get(route('personas.index'), { ...params }, { preserveState: true, preserveScroll: true, replace: true });
}, 300));

const clearAllFilters = () => {
  Object.assign(params, {
    search: '',
    cooperativa_id: '',
    abogado_id: '',
    tipo_rol: '',
    status: 'active',
    sort: 'updated_at',
    direction: 'desc'
  });
};

const exportExcel = () => {
  window.location.href = route('personas.export.excel', params);
};

const deletePersona = (id) => {
  const persona = personasData.value.find(p => p.id === id);
  const nombre = persona ? persona.nombre_completo : 'esta persona';

  AppAlert.fire({
    title: '¿Suspender registro?',
    html: `
      <div class="text-left space-y-3">
        <p class="text-sm text-gray-600 font-medium">Vas a mover a <b>${nombre}</b> a la papelera.</p>
        <div class="p-3 bg-amber-50 border border-amber-100 rounded-lg">
          <p class="text-[11px] text-amber-700 font-bold uppercase tracking-tight mb-1">Importante</p>
          <ul class="text-[10px] text-amber-600 space-y-1 list-disc pl-4 font-medium">
            <li>No se borraran sus datos.</li>
            <li>Seguira vinculado a sus casos y procesos actuales.</li>
            <li>Podras restaurarlo desde el filtro de papelera.</li>
          </ul>
        </div>
      </div>
    `,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#4f46e5',
    cancelButtonColor: '#6b7280',
    confirmButtonText: 'Si, suspender',
    cancelButtonText: 'Cancelar',
    customClass: {
        confirmButton: 'rounded-xl font-black text-[10px] uppercase tracking-widest px-6 py-3',
        cancelButton: 'rounded-xl font-black text-[10px] uppercase tracking-widest px-6 py-3'
    }
  }).then((res) => {
    if (res.isConfirmed) {
        router.delete(route('personas.destroy', id), {
            preserveScroll: true,
            onSuccess: () => {
                const flash = usePage().props.flash;
                if (!flash.error) {
                    AppAlert.fire({
                        title: 'Suspendido',
                        text: 'El registro se movio a la papelera correctamente.',
                        icon: 'success',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }
            }
        });
    }
  });
};

const restorePersona = (id) => {
  router.post(route('personas.restore', id), {}, {
      onSuccess: () => {
          const flash = usePage().props.flash;
          if (!flash.error) {
              AppAlert.fire('Reactivada', 'La persona vuelve a estar activa.', 'success');
          }
      }
  });
};

const getRandomColor = (id) => {
  const colors = ['bg-indigo-100 text-indigo-700', 'bg-emerald-100 text-emerald-700', 'bg-violet-100 text-violet-700', 'bg-amber-100 text-amber-700', 'bg-rose-100 text-rose-700'];
  return colors[id % colors.length];
};

function checkIncompleta(p) {
    const faltaContacto = !p.celular_1 && !p.correo_1;
    const faltaFechas = !p.fecha_nacimiento || !p.fecha_expedicion;
    const faltaAsignacion = (!p.sin_empresa_o_cooperativa && !p.cooperativas?.length) || !p.abogados?.length;
    return faltaContacto || faltaFechas || faltaAsignacion;
}

const roleLabel = (p) => p.es_demandado ? 'Demandado' : 'Deudor / Cliente';
const roleClass = (p) => p.es_demandado
    ? 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-300'
    : 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-300';

const documentLabel = (p) => {
    if (!p.numero_documento) return 'Sin identificacion';
    const suffix = p.tipo_documento === 'NIT' && p.dv ? `-${p.dv}` : '';
    return `${p.numero_documento}${suffix}`;
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
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Directorio</p>
                <div class="mt-1 flex items-center gap-3">
                    <div class="hidden rounded-lg border border-indigo-100 bg-indigo-50 p-2 text-indigo-600 dark:border-indigo-900/60 dark:bg-indigo-950/30 dark:text-indigo-300 sm:block">
                        <UserGroupIcon class="h-6 w-6" />
                    </div>
                    <div>
                        <h2 class="text-2xl font-black leading-tight text-gray-950 dark:text-white">Personas</h2>
                        <p class="mt-1 text-sm font-medium text-gray-600 dark:text-gray-300">Clientes, deudores, demandados y contactos vinculados a expedientes.</p>
                    </div>
                </div>
            </div>
            <div class="flex w-full flex-wrap items-center gap-2 lg:w-auto lg:justify-end">
                <button
                    @click="exportExcel"
                    class="inline-flex flex-1 items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-xs font-black uppercase tracking-wider text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-300 sm:flex-none"
                >
                    <ArrowDownTrayIcon class="mr-2 h-4 w-4" />
                    Exportar
                </button>
                <Link
                    :href="route('personas.create')"
                    class="inline-flex flex-1 items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-xs font-black uppercase tracking-wider text-white shadow-sm transition hover:bg-indigo-700 sm:flex-none"
                >
                    <PlusIcon class="mr-2 h-4 w-4" />
                    Nueva persona
                </Link>
            </div>
        </div>
    </template>

    <div class="min-h-screen bg-gray-50/60 py-6 dark:bg-gray-900/40">
      <div class="mx-auto max-w-[1600px] space-y-5 px-4 sm:px-6 lg:px-8">
        <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <article
                v-for="item in summaryCards"
                :key="item.label"
                class="rounded-lg border bg-white p-4 shadow-sm dark:bg-gray-800"
                :class="item.class"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest opacity-80">{{ item.label }}</p>
                        <p class="mt-1 text-2xl font-black">{{ item.value }}</p>
                        <p class="mt-1 text-xs font-semibold opacity-90">{{ item.detail }}</p>
                    </div>
                    <component :is="item.icon" class="h-5 w-5 opacity-80" />
                </div>
            </article>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="border-b border-gray-200 p-4 dark:border-gray-700">
                <div class="grid grid-cols-1 gap-3 xl:grid-cols-12">
                    <div class="xl:col-span-4">
                        <label class="mb-1 block text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Busqueda</label>
                        <div class="relative">
                            <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-3 h-4 w-4 text-gray-400" />
                            <input
                                v-model="params.search"
                                type="text"
                                class="block w-full rounded-lg border-gray-200 bg-gray-50 py-2.5 pl-10 pr-3 text-sm font-medium text-gray-900 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                                placeholder="Nombre, identificacion, correo o telefono"
                            />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:col-span-8 xl:grid-cols-5">
                        <div>
                            <label class="mb-1 block text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Estado</label>
                            <SelectInput v-model="params.status" class="w-full !rounded-lg !py-2.5">
                                <option value="active">Activos</option>
                                <option value="suspended">Papelera</option>
                            </SelectInput>
                        </div>
                        <div>
                            <label class="mb-1 block text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Rol</label>
                            <SelectInput v-model="params.tipo_rol" class="w-full !rounded-lg !py-2.5">
                                <option value="">Todos</option>
                                <option value="deudor">Deudores / Clientes</option>
                                <option value="demandado">Demandados</option>
                            </SelectInput>
                        </div>
                        <div>
                            <label class="mb-1 block text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Empresa</label>
                            <SelectInput v-model="params.cooperativa_id" class="w-full !rounded-lg !py-2.5">
                                <option value="">Todas</option>
                                <option :value="SIN_EMPRESA_FILTER">Sin empresa</option>
                                <option v-for="c in cooperativasFiltro" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                            </SelectInput>
                        </div>
                        <div>
                            <label class="mb-1 block text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Responsable</label>
                            <SelectInput v-model="params.abogado_id" class="w-full !rounded-lg !py-2.5">
                                <option value="">Todos</option>
                                <option v-for="a in abogados" :key="a.id" :value="a.id">{{ a.name }}</option>
                            </SelectInput>
                        </div>
                        <div>
                            <label class="mb-1 block text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Orden</label>
                            <SelectInput v-model="sortOption" class="w-full !rounded-lg !py-2.5">
                                <option value="updated_at|desc">Mas recientes</option>
                                <option value="nombre_completo|asc">A - Z</option>
                            </SelectInput>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">
                    <span class="text-indigo-600 dark:text-indigo-300">{{ personas.total }}</span> registros encontrados
                </p>
                <button
                    v-if="isDirty"
                    @click="clearAllFilters"
                    class="inline-flex items-center justify-center rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-[10px] font-black uppercase tracking-widest text-rose-700 transition hover:bg-rose-100 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-300"
                >
                    <XMarkIcon class="mr-1.5 h-4 w-4" />
                    Limpiar filtros
                </button>
            </div>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
          <div class="overflow-x-auto custom-scrollbar-horizontal">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
              <thead class="bg-gray-50 dark:bg-gray-900/50">
                <tr class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">
                  <th class="px-5 py-4 text-left">Persona</th>
                  <th class="px-5 py-4 text-left">Identificacion</th>
                  <th class="px-5 py-4 text-left">Contacto</th>
                  <th class="px-5 py-4 text-left">Asignaciones</th>
                  <th class="px-5 py-4 text-left">Estado de datos</th>
                  <th class="sticky right-0 z-10 bg-gray-50 px-5 py-4 text-right dark:bg-gray-900">Acciones</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-700 dark:bg-gray-800">
                <tr v-if="personasData.length === 0">
                    <td colspan="6" class="px-6 py-24 text-center">
                        <div class="mx-auto flex max-w-md flex-col items-center">
                            <div class="mb-4 rounded-full bg-gray-50 p-5 dark:bg-gray-900">
                                <InboxIcon class="h-10 w-10 text-gray-300" />
                            </div>
                            <h3 class="text-lg font-black uppercase tracking-wider text-gray-950 dark:text-white">Sin resultados</h3>
                            <p class="mt-2 text-sm font-medium leading-relaxed text-gray-500 dark:text-gray-400">No hay personas que coincidan con los filtros aplicados.</p>
                            <button v-if="isDirty" @click="clearAllFilters" class="mt-5 rounded-lg bg-indigo-600 px-4 py-2 text-xs font-black uppercase tracking-wider text-white transition hover:bg-indigo-700">Restablecer busqueda</button>
                        </div>
                    </td>
                </tr>

                <tr v-else v-for="p in personasData" :key="p.id" class="group transition hover:bg-gray-50 dark:hover:bg-gray-900/40">
                  <td class="px-5 py-4">
                    <div class="flex items-center gap-3">
                      <div :class="`flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-base font-black ${getRandomColor(p.id)}`">
                          {{ p.nombre_completo?.[0] || '?' }}
                      </div>
                      <div class="min-w-0">
                        <div class="flex max-w-sm flex-wrap items-center gap-2">
                            <Link :href="route('personas.show', p.id)" class="truncate text-sm font-black text-gray-950 transition hover:text-indigo-600 dark:text-white">
                                {{ p.nombre_completo }}
                            </Link>
                            <span class="rounded-md border px-2 py-0.5 text-[9px] font-black uppercase" :class="roleClass(p)">
                                {{ roleLabel(p) }}
                            </span>
                            <span v-if="p.deleted_at" class="rounded-md border border-gray-300 bg-gray-100 px-2 py-0.5 text-[9px] font-black uppercase text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">Papelera</span>
                        </div>
                        <p class="mt-1 truncate text-xs font-medium text-gray-500 dark:text-gray-400">{{ p.empresa || p.cargo || 'Sin perfil laboral registrado' }}</p>
                      </div>
                    </div>
                  </td>

                  <td class="px-5 py-4">
                    <div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">{{ p.tipo_documento || 'Documento' }}</p>
                        <div class="mt-1 flex items-center gap-2">
                            <span class="font-mono text-sm font-bold text-gray-800 dark:text-gray-200">{{ documentLabel(p) }}</span>
                            <button @click="copyToClipboard(p.numero_documento)" class="text-gray-300 opacity-0 transition hover:text-indigo-500 group-hover:opacity-100" title="Copiar identificacion">
                                <DocumentDuplicateIcon class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                  </td>

                  <td class="px-5 py-4">
                      <div class="space-y-1">
                          <div v-if="p.celular_1" class="flex items-center gap-2 text-xs font-semibold text-gray-700 dark:text-gray-300">
                              <PhoneIcon class="h-3.5 w-3.5 text-emerald-500" /> {{ p.celular_1 }}
                          </div>
                          <div v-if="p.correo_1" class="flex max-w-[220px] items-center gap-2 truncate text-xs font-semibold text-gray-700 dark:text-gray-300" :title="p.correo_1">
                              <EnvelopeIcon class="h-3.5 w-3.5 shrink-0 text-sky-500" /> <span class="truncate">{{ p.correo_1 }}</span>
                          </div>
                          <span v-if="!p.celular_1 && !p.correo_1" class="text-xs font-bold text-gray-400">Sin contacto principal</span>
                      </div>
                  </td>

                  <td class="px-5 py-4">
                    <div class="max-w-xs space-y-2">
                      <div class="flex flex-wrap gap-1.5">
                        <span v-for="c in (p.cooperativas || []).slice(0, 2)" :key="c.id" class="max-w-[150px] truncate rounded-md border border-gray-200 bg-gray-50 px-2 py-1 text-[10px] font-bold text-gray-600 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" :title="c.nombre">
                            {{ c.nombre }}
                        </span>
                        <span v-if="(p.cooperativas || []).length > 2" class="rounded-md border border-gray-200 bg-white px-2 py-1 text-[10px] font-black text-gray-500 dark:border-gray-700 dark:bg-gray-800">+{{ p.cooperativas.length - 2 }}</span>
                        <span v-if="p.sin_empresa_o_cooperativa && !p.cooperativas?.length" class="rounded-md border border-amber-200 bg-amber-50 px-2 py-1 text-[10px] font-black text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-300">Sin empresa</span>
                        <span v-else-if="!p.cooperativas?.length" class="text-xs font-semibold text-gray-400">Sin empresas</span>
                      </div>
                      <div class="flex items-center gap-1.5 text-xs font-semibold text-gray-500 dark:text-gray-400">
                        <ShieldCheckIcon class="h-3.5 w-3.5" />
                        <span>{{ p.abogados?.[0]?.name || 'Sin responsable' }}</span>
                        <span v-if="(p.abogados || []).length > 1">+{{ p.abogados.length - 1 }}</span>
                      </div>
                    </div>
                  </td>

                  <td class="px-5 py-4">
                    <span
                        class="inline-flex items-center rounded-md border px-2.5 py-1 text-[10px] font-black uppercase"
                        :class="checkIncompleta(p)
                            ? 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-300'
                            : 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-300'"
                    >
                        {{ checkIncompleta(p) ? 'Falta info' : 'Completo' }}
                    </span>
                  </td>

                  <td class="sticky right-0 z-10 bg-white px-5 py-4 text-right transition group-hover:bg-gray-50 dark:bg-gray-800 dark:group-hover:bg-gray-900">
                    <div class="flex items-center justify-end gap-2">
                      <Link :href="route('personas.show', p.id)" class="rounded-lg border border-indigo-200 bg-indigo-50 p-2 text-indigo-600 transition hover:bg-indigo-600 hover:text-white dark:border-indigo-900/60 dark:bg-indigo-950/30 dark:text-indigo-300" title="Ver ficha">
                          <EyeIcon class="h-4 w-4"/>
                      </Link>
                      <Link :href="route('personas.edit', p.id)" class="rounded-lg border border-sky-200 bg-sky-50 p-2 text-sky-600 transition hover:bg-sky-600 hover:text-white dark:border-sky-900/60 dark:bg-sky-950/30 dark:text-sky-300" title="Editar">
                          <PencilSquareIcon class="h-4 w-4"/>
                      </Link>
                      <button v-if="p.deleted_at" @click="restorePersona(p.id)" class="rounded-lg border border-emerald-200 bg-emerald-50 p-2 text-emerald-600 transition hover:bg-emerald-600 hover:text-white dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-300" title="Restaurar">
                          <ArrowPathIcon class="h-4 w-4"/>
                      </button>
                      <button v-else @click="deletePersona(p.id)" class="rounded-lg border border-rose-200 bg-rose-50 p-2 text-rose-600 transition hover:bg-rose-600 hover:text-white dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-300" title="Mover a papelera">
                          <TrashIcon class="h-4 w-4"/>
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="personas.links?.length > 3" class="border-t border-gray-200 bg-gray-50 px-4 py-4 dark:border-gray-700 dark:bg-gray-900/40">
            <div class="flex flex-wrap justify-center gap-1.5">
              <Link
                v-for="(link, i) in personas.links"
                :key="i"
                :href="link.url || '#'"
                v-html="link.label"
                :class="link.active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'"
                class="rounded-lg border border-gray-200 px-3 py-2 text-[11px] font-black uppercase transition dark:border-gray-700"
              />
            </div>
          </div>
        </section>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
.custom-scrollbar-horizontal::-webkit-scrollbar { height: 6px; }
.custom-scrollbar-horizontal::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar-horizontal::-webkit-scrollbar-thumb { background-color: rgba(99, 102, 241, 0.25); border-radius: 999px; }
.sticky { position: -webkit-sticky; position: sticky; }
</style>
