<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import axios from 'axios';
import {
    CloudArrowUpIcon,
    ArrowLeftIcon,
    TableCellsIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
    XMarkIcon,
    ArrowDownTrayIcon,
    ArrowPathIcon,
    UserIcon,
    ShieldCheckIcon,
    ClipboardDocumentCheckIcon,
    DocumentMagnifyingGlassIcon,
    NoSymbolIcon,
    ScaleIcon,
} from '@heroicons/vue/24/outline';
import AppAlert from '@/Utils/appAlert';

const props = defineProps({
    juzgados: Array,
    tiposProceso: Array,
    abogados: Array,
});

const page = usePage();
const file = ref(null);
const fileInput = ref(null);
const results = ref(null);
const processing = ref(false);
const confirmed = ref(false);
const safeMode = ref(true);
const selectedAbogadoId = ref(page.props.auth.user.id);

const handleFileChange = (event) => {
    file.value = event.target.files[0];
    confirmed.value = false;
    if (file.value) uploadFile();
};

const resetImport = () => {
    file.value = null;
    results.value = null;
    confirmed.value = false;
    if (fileInput.value) fileInput.value.value = '';
};

const uploadFile = () => {
    if (!file.value) return;

    const formData = new FormData();
    formData.append('file', file.value);

    processing.value = true;
    axios.post(route('procesos.import.validate'), formData)
        .then((response) => {
            results.value = {
                ...response.data,
                rows: (response.data.rows || []).map(row => ({
                    ...row,
                    selected: row.selected ?? (row.status !== 'error' && row.action !== 'no_change'),
                })),
            };
        })
        .catch(() => {
            AppAlert.fire('Error', 'El archivo no tiene el formato correcto o está corrupto.', 'error');
        })
        .finally(() => { processing.value = false; });
};

const rows = computed(() => results.value?.rows || []);
const selectedRows = computed(() => rows.value.filter(row => row.selected && row.status !== 'error'));
const hasErrors = computed(() => rows.value.some(row => row.status === 'error'));
const canImport = computed(() => confirmed.value && selectedRows.value.length > 0 && !processing.value && selectedAbogadoId.value);

const summary = computed(() => ({
    total: rows.value.length,
    selected: selectedRows.value.length,
    create: rows.value.filter(row => row.action === 'create').length,
    update: rows.value.filter(row => row.action === 'update').length,
    unchanged: rows.value.filter(row => row.action === 'no_change').length,
    errors: rows.value.filter(row => row.status === 'error').length,
}));

const selectActionableRows = () => {
    rows.value.forEach((row) => {
        row.selected = row.status !== 'error' && row.action !== 'no_change';
    });
};

const clearSelection = () => {
    rows.value.forEach((row) => { row.selected = false; });
};

const confirmImport = () => {
    if (!confirmed.value) {
        AppAlert.fire('Atención', 'Marca la confirmación final para procesar la carga.', 'warning');
        return;
    }

    if (selectedRows.value.length === 0) {
        AppAlert.fire('Sin filas seleccionadas', 'Selecciona al menos una fila válida para importar.', 'warning');
        return;
    }

    processing.value = true;
    router.post(route('procesos.import.store'), {
        data: rows.value,
        abogado_id: selectedAbogadoId.value,
        safe_mode: safeMode.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            AppAlert.fire('Importación completada', 'Las filas seleccionadas fueron procesadas.', 'success');
        },
        onError: () => {
            AppAlert.fire('Error', 'Ocurrió un fallo al guardar los datos.', 'error');
        },
        onFinish: () => { processing.value = false; },
    });
};

const actionLabel = (row) => ({
    create: 'Crear',
    update: 'Actualizar',
    no_change: 'Sin cambios',
    error: 'Bloqueada',
}[row.action] || 'Revisar');

const actionClass = (row) => ({
    create: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-300',
    update: 'border-indigo-200 bg-indigo-50 text-indigo-700 dark:border-indigo-900/60 dark:bg-indigo-950/30 dark:text-indigo-300',
    no_change: 'border-gray-200 bg-gray-50 text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300',
    error: 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-300',
}[row.action] || 'border-amber-200 bg-amber-50 text-amber-700');

const statusIcon = (row) => {
    if (row.status === 'error') return XMarkIcon;
    if (row.status === 'warning') return ExclamationTriangleIcon;
    if (row.action === 'no_change') return NoSymbolIcon;
    return CheckCircleIcon;
};

const messageClass = (row) => {
    if (row.status === 'error') return 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-300';
    if (row.status === 'warning') return 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200';
    return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-300';
};
</script>

<template>
    <Head title="Carga asistida de radicados" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex min-w-0 items-center gap-3">
                    <Link :href="route('procesos.index')" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 shadow-sm transition hover:text-indigo-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                        <ArrowLeftIcon class="h-5 w-5" />
                    </Link>
                    <div class="min-w-0">
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Casos Abogados en Colombia</p>
                        <h2 class="mt-1 text-2xl font-black tracking-tight text-gray-950 dark:text-white">Carga asistida</h2>
                        <p class="mt-1 text-sm font-medium text-gray-500 dark:text-gray-400">Revisa radicados, partes y despacho antes de guardar cambios.</p>
                    </div>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <a :href="route('procesos.exportar')" class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-xs font-black uppercase tracking-widest text-gray-600 shadow-sm transition hover:border-emerald-200 hover:text-emerald-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                        <ArrowDownTrayIcon class="h-4 w-4" /> Exportar base
                    </a>
                    <a :href="route('procesos.import.template')" class="inline-flex items-center justify-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2.5 text-xs font-black uppercase tracking-widest text-indigo-700 shadow-sm transition hover:bg-indigo-100 dark:border-indigo-900/60 dark:bg-indigo-950/30 dark:text-indigo-300">
                        <TableCellsIcon class="h-4 w-4" /> Plantilla avanzada
                    </a>
                </div>
            </div>
        </template>

        <div class="min-h-screen bg-gray-50/70 py-6 dark:bg-gray-950/40">
            <div class="mx-auto max-w-[1600px] space-y-5 px-4 sm:px-6 lg:px-8">
                <section class="grid grid-cols-1 gap-3 lg:grid-cols-3">
                    <article class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-200">
                        <div class="flex items-start gap-3">
                            <ShieldCheckIcon class="mt-0.5 h-5 w-5 shrink-0" />
                            <div>
                                <p class="text-xs font-black uppercase tracking-widest">Modo protegido activo</p>
                                <p class="mt-1 text-sm font-semibold">Las celdas vacías no borran datos existentes cuando el expediente ya existe.</p>
                            </div>
                        </div>
                    </article>
                    <article class="rounded-lg border border-indigo-200 bg-indigo-50 p-4 text-indigo-800 dark:border-indigo-900/60 dark:bg-indigo-950/30 dark:text-indigo-200">
                        <div class="flex items-start gap-3">
                            <DocumentMagnifyingGlassIcon class="mt-0.5 h-5 w-5 shrink-0" />
                            <div>
                                <p class="text-xs font-black uppercase tracking-widest">Primero valida</p>
                                <p class="mt-1 text-sm font-semibold">Subir el archivo solo analiza filas; nada se guarda hasta confirmar.</p>
                            </div>
                        </div>
                    </article>
                    <article class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200">
                        <div class="flex items-start gap-3">
                            <ClipboardDocumentCheckIcon class="mt-0.5 h-5 w-5 shrink-0" />
                            <div>
                                <p class="text-xs font-black uppercase tracking-widest">Abogado de respaldo</p>
                                <p class="mt-1 text-sm font-semibold">Si el Excel no trae responsable válido, se usará el abogado seleccionado.</p>
                            </div>
                        </div>
                    </article>
                </section>

                <section v-if="!results" class="grid grid-cols-1 gap-5 lg:grid-cols-[minmax(0,1fr)_24rem]">
                    <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:p-8">
                        <div class="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
                            <div class="max-w-2xl">
                                <p class="text-[10px] font-black uppercase tracking-widest text-indigo-600 dark:text-indigo-300">Paso 1</p>
                                <h3 class="mt-1 text-xl font-black text-gray-950 dark:text-white">Carga el archivo para revisarlo</h3>
                                <p class="mt-2 text-sm font-medium leading-6 text-gray-600 dark:text-gray-300">Usa la plantilla avanzada o un Excel exportado desde el sistema. La revisión previa detecta nuevos expedientes, actualizaciones, filas sin cambios y alertas.</p>
                            </div>
                            <label class="flex items-center gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 dark:border-emerald-900/60 dark:bg-emerald-950/30">
                                <input v-model="safeMode" type="checkbox" class="rounded border-emerald-300 text-emerald-600 focus:ring-emerald-500" />
                                <span class="text-xs font-black uppercase tracking-widest text-emerald-700 dark:text-emerald-300">Proteger datos existentes</span>
                            </label>
                        </div>

                        <button type="button" @click="fileInput?.click()" class="mt-7 flex min-h-[18rem] w-full cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-indigo-200 bg-indigo-50/50 p-8 text-center transition hover:border-indigo-400 hover:bg-indigo-50 dark:border-indigo-900/60 dark:bg-indigo-950/20 dark:hover:bg-indigo-950/30">
                            <input ref="fileInput" type="file" @change="handleFileChange" class="hidden" accept=".xlsx,.xls" />
                            <ArrowPathIcon v-if="processing" class="h-12 w-12 animate-spin text-indigo-600" />
                            <CloudArrowUpIcon v-else class="h-12 w-12 text-indigo-500" />
                            <span class="mt-4 text-sm font-black uppercase tracking-widest text-indigo-700 dark:text-indigo-300">{{ processing ? 'Analizando archivo...' : 'Seleccionar Excel' }}</span>
                            <span class="mt-2 max-w-md text-xs font-semibold text-gray-500 dark:text-gray-400">Formatos permitidos: .xlsx y .xls</span>
                        </button>
                    </div>

                    <aside class="space-y-4 rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <div>
                            <label class="mb-2 flex items-center gap-2 text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">
                                <UserIcon class="h-4 w-4" /> Abogado de respaldo
                            </label>
                            <SelectInput v-model="selectedAbogadoId" class="w-full text-sm font-bold">
                                <option v-for="abogado in abogados" :key="abogado.id" :value="abogado.id">{{ abogado.name }}</option>
                            </SelectInput>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800/60">
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Crear</p>
                            <p class="mt-1 text-xs font-semibold text-gray-600 dark:text-gray-300">No existe un expediente equivalente.</p>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800/60">
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Actualizar</p>
                            <p class="mt-1 text-xs font-semibold text-gray-600 dark:text-gray-300">El sistema encontró diferencias frente a la base.</p>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800/60">
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Sin cambios</p>
                            <p class="mt-1 text-xs font-semibold text-gray-600 dark:text-gray-300">Quedan fuera por defecto.</p>
                        </div>
                    </aside>
                </section>

                <section v-else class="space-y-5">
                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:p-5">
                        <div class="grid grid-cols-2 gap-3 lg:grid-cols-6">
                            <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800/60"><p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Filas</p><p class="mt-1 text-2xl font-black text-gray-950 dark:text-white">{{ summary.total }}</p></div>
                            <div class="rounded-lg bg-indigo-50 p-3 dark:bg-indigo-950/30"><p class="text-[10px] font-black uppercase tracking-widest text-indigo-700 dark:text-indigo-300">Seleccionadas</p><p class="mt-1 text-2xl font-black text-indigo-700 dark:text-indigo-300">{{ summary.selected }}</p></div>
                            <div class="rounded-lg bg-emerald-50 p-3 dark:bg-emerald-950/30"><p class="text-[10px] font-black uppercase tracking-widest text-emerald-700 dark:text-emerald-300">Crear</p><p class="mt-1 text-2xl font-black text-emerald-700 dark:text-emerald-300">{{ summary.create }}</p></div>
                            <div class="rounded-lg bg-amber-50 p-3 dark:bg-amber-950/30"><p class="text-[10px] font-black uppercase tracking-widest text-amber-700 dark:text-amber-300">Actualizar</p><p class="mt-1 text-2xl font-black text-amber-700 dark:text-amber-300">{{ summary.update }}</p></div>
                            <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800/60"><p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Sin cambios</p><p class="mt-1 text-2xl font-black text-gray-700 dark:text-gray-200">{{ summary.unchanged }}</p></div>
                            <div class="rounded-lg bg-rose-50 p-3 dark:bg-rose-950/30"><p class="text-[10px] font-black uppercase tracking-widest text-rose-700 dark:text-rose-300">Errores</p><p class="mt-1 text-2xl font-black text-rose-700 dark:text-rose-300">{{ summary.errors }}</p></div>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:p-5">
                        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button" @click="selectActionableRows" class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-[10px] font-black uppercase tracking-widest text-indigo-700 hover:bg-indigo-100 dark:border-indigo-900/60 dark:bg-indigo-950/30 dark:text-indigo-300">Seleccionar válidas</button>
                                <button type="button" @click="clearSelection" class="rounded-lg border border-gray-200 px-3 py-2 text-[10px] font-black uppercase tracking-widest text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">Quitar selección</button>
                                <button type="button" @click="resetImport" class="rounded-lg border border-gray-200 px-3 py-2 text-[10px] font-black uppercase tracking-widest text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">Cambiar archivo</button>
                            </div>
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                                <div class="min-w-56 rounded-lg border border-indigo-100 bg-indigo-50/60 p-3 dark:border-indigo-900/60 dark:bg-indigo-950/30">
                                    <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-indigo-700 dark:text-indigo-300">Abogado de respaldo</label>
                                    <SelectInput v-model="selectedAbogadoId" class="w-full text-xs font-bold">
                                        <option v-for="abogado in abogados" :key="abogado.id" :value="abogado.id">{{ abogado.name }}</option>
                                    </SelectInput>
                                </div>
                                <label class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-900/60 dark:bg-amber-950/30">
                                    <input v-model="confirmed" type="checkbox" class="mt-0.5 rounded border-amber-300 text-amber-600 focus:ring-amber-500" />
                                    <span class="text-xs font-bold text-amber-800 dark:text-amber-200">Revisé el resumen y autorizo procesar solo las filas seleccionadas.</span>
                                </label>
                                <PrimaryButton @click="confirmImport" :disabled="!canImport" class="justify-center !rounded-lg !px-6 !py-3 !text-xs" :class="{ 'opacity-50': !canImport }">
                                    <ArrowPathIcon v-if="processing" class="mr-2 h-4 w-4 animate-spin" />
                                    Procesar {{ selectedRows.length }} fila(s)
                                </PrimaryButton>
                            </div>
                        </div>
                        <p v-if="hasErrors" class="mt-3 text-xs font-bold text-rose-600 dark:text-rose-300">Las filas bloqueadas no se guardarán. Corrige el Excel y vuelve a cargarlo si necesitas incluirlas.</p>
                    </div>

                    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-800/70">
                                    <tr>
                                        <th class="w-12 px-4 py-3 text-left text-[10px] font-black uppercase tracking-widest text-gray-400">Incluir</th>
                                        <th class="px-4 py-3 text-left text-[10px] font-black uppercase tracking-widest text-gray-400">Acción</th>
                                        <th class="px-4 py-3 text-left text-[10px] font-black uppercase tracking-widest text-gray-400">Radicado / asunto</th>
                                        <th class="px-4 py-3 text-left text-[10px] font-black uppercase tracking-widest text-gray-400">Despacho y partes</th>
                                        <th class="px-4 py-3 text-left text-[10px] font-black uppercase tracking-widest text-gray-400">Revisión</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    <tr v-for="(row, index) in rows" :key="index" class="align-top" :class="row.selected ? 'bg-indigo-50/30 dark:bg-indigo-950/10' : ''">
                                        <td class="px-4 py-4">
                                            <input v-model="row.selected" :disabled="row.status === 'error'" type="checkbox" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-30" />
                                        </td>
                                        <td class="px-4 py-4">
                                            <span class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-[10px] font-black uppercase tracking-widest" :class="actionClass(row)">
                                                <component :is="statusIcon(row)" class="h-3.5 w-3.5" />
                                                {{ actionLabel(row) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-4">
                                            <p class="font-mono text-xs font-black text-gray-950 dark:text-white">{{ row.radicado || 'SIN RADICADO' }}</p>
                                            <p class="mt-1 max-w-md text-xs font-semibold leading-5 text-gray-600 dark:text-gray-300">{{ row.asunto || 'Sin asunto' }}</p>
                                            <p class="mt-1 text-[10px] font-black uppercase tracking-widest text-indigo-600 dark:text-indigo-300">{{ row.tipo_proceso_nombre || 'Tipo no indicado' }}</p>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="max-w-md space-y-1.5">
                                                <p class="text-xs font-black text-gray-800 dark:text-gray-200">{{ row.juzgado_nombre || 'Sin despacho' }}</p>
                                                <p class="truncate text-[10px] font-semibold text-blue-700 dark:text-blue-300"><span class="font-black">DTE:</span> {{ row.demandantes_nombres || 'Sin registrar' }}</p>
                                                <p class="truncate text-[10px] font-semibold text-rose-700 dark:text-rose-300"><span class="font-black">DDO:</span> {{ row.demandados_nombres || 'Sin registrar' }}</p>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="flex max-w-xl flex-wrap gap-1.5">
                                                <span v-for="message in row.messages" :key="message" class="rounded-lg border px-2.5 py-1 text-[10px] font-bold leading-5" :class="messageClass(row)">
                                                    {{ message }}
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
