<script setup>
import { computed, ref, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import AppAlert from '@/Utils/appAlert';
import SelectInput from '@/Components/SelectInput.vue';
import { 
    XMarkIcon, 
    ClipboardDocumentCheckIcon,
    MagnifyingGlassIcon,
    LinkIcon,
    CheckCircleIcon,
    TrashIcon,
    ClockIcon,
    ExclamationCircleIcon,
    ChevronRightIcon,
    PaperClipIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    show: Boolean
});

const emit = defineEmits(['close']);

const Toast = AppAlert.mixin({
    toast: true,
    timer: 3000,
});

// Estado
const loading = ref(false);
const notas = ref([]);
const toDatetimeLocal = (date) => {
    const pad = (value) => String(value).padStart(2, '0');
    return [
        date.getFullYear(),
        pad(date.getMonth() + 1),
        pad(date.getDate()),
    ].join('-') + `T${pad(date.getHours())}:${pad(date.getMinutes())}`;
};
const defaultReminder = () => {
    const date = new Date();
    date.setHours(date.getHours() + 8, 0, 0, 0);
    return toDatetimeLocal(date);
};
const form = ref({
    descripcion: '',
    despacho: '',
    termino: '',
    expires_at: defaultReminder(),
    relacionable_type: '',
    relacionable_id: null,
    archivos: []
});

const fileInput = ref(null);
const selectedFiles = ref([]);

const pendientes = computed(() => notas.value.filter((nota) => !nota.is_completed));
const completadas = computed(() => notas.value.filter((nota) => nota.is_completed));
const fileLimitReached = computed(() => selectedFiles.value.length >= 3);
const minReminder = computed(() => toDatetimeLocal(new Date()));
const quickReminders = [
    { label: '1 h', hours: 1 },
    { label: '4 h', hours: 4 },
    { label: 'Hoy 6 p.m.', endOfDay: true },
    { label: 'Mañana', hours: 24 },
];

// Búsquedas
const searchDespacho = ref('');
const searchVinculacion = ref('');
const resultadosDespacho = ref([]);
const resultadosVinculacion = ref([]);
const showDespachoResults = ref(false);
const showVinculacionResults = ref(false);

// Cargar Notas
const fetchNotas = async () => {
    try {
        const response = await axios.get(route('api.gestion.index'));
        notas.value = response.data;
    } catch (error) {
        console.error('Error al cargar notas', error);
    }
};

const handleFileUpload = (e) => {
    const files = Array.from(e.target.files);
    if (selectedFiles.value.length + files.length > 3) {
        Toast.fire({ icon: 'warning', title: 'MÁXIMO 3 ARCHIVOS' });
        return;
    }
    selectedFiles.value = [...selectedFiles.value, ...files];
};

const removeFile = (index) => {
    selectedFiles.value.splice(index, 1);
};

const setQuickReminder = (option) => {
    const date = new Date();
    if (option.endOfDay) {
        date.setHours(18, 0, 0, 0);
        if (date <= new Date()) date.setDate(date.getDate() + 1);
    } else {
        date.setHours(date.getHours() + option.hours, 0, 0, 0);
    }

    form.value.expires_at = toDatetimeLocal(date);
    form.value.termino = option.label;
};

const clearRelacion = () => {
    form.value.relacionable_type = '';
    form.value.relacionable_id = null;
    searchVinculacion.value = '';
    resultadosVinculacion.value = [];
    showVinculacionResults.value = false;
};

// Guardar Nota
const saveNota = async () => {
    if (!form.value.descripcion || !form.value.despacho || !form.value.expires_at) {
        Toast.fire({ icon: 'warning', title: 'CAMPOS OBLIGATORIOS VACÍOS' });
        return;
    }
    
    loading.value = true;
    try {
        const formData = new FormData();
        formData.append('descripcion', form.value.descripcion);
        formData.append('despacho', form.value.despacho);
        formData.append('termino', form.value.termino || form.value.expires_at);
        formData.append('expires_at', form.value.expires_at);
        formData.append('relacionable_type', form.value.relacionable_type || '');
        if (form.value.relacionable_id) formData.append('relacionable_id', form.value.relacionable_id);
        
        selectedFiles.value.forEach((file, index) => {
            formData.append(`archivos[${index}]`, file);
        });

        await axios.post(route('api.gestion.store'), formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });

        form.value = { descripcion: '', despacho: '', termino: '', expires_at: defaultReminder(), relacionable_type: '', relacionable_id: null, archivos: [] };
        selectedFiles.value = [];
        searchDespacho.value = '';
        searchVinculacion.value = '';
        await fetchNotas();
        Toast.fire({ icon: 'success', title: 'GESTIÓN REGISTRADA' });
    } catch (error) {
        Toast.fire({ icon: 'error', title: 'ERROR AL GUARDAR' });
    } finally {
        loading.value = false;
    }
};

// Marcar como hecha
const markAsDone = async (id) => {
    try {
        await axios.patch(route('api.gestion.complete', id));
        await fetchNotas();
        Toast.fire({ icon: 'success', title: 'GESTIÓN FINALIZADA' });
    } catch (error) {
        Toast.fire({ icon: 'error', title: 'ERROR AL ACTUALIZAR' });
    }
};

// Eliminar
const deleteNota = async (id) => {
    const result = await AppAlert.fire({
        title: '¿ELIMINAR REGISTRO?',
        text: "ESTA ACCIÓN NO SE PUEDE DESHACER",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#4f46e5', // indigo-600
        cancelButtonColor: '#3b82f6', // blue-500
        confirmButtonText: 'SÍ, ELIMINAR',
        cancelButtonText: 'CANCELAR'
    });

    if (result.isConfirmed) {
        try {
            await axios.delete(route('api.gestion.destroy', id));
            await fetchNotas();
            Toast.fire({ icon: 'success', title: 'REGISTRO ELIMINADO' });
        } catch (error) {
            Toast.fire({ icon: 'error', title: 'ERROR AL ELIMINAR' });
        }
    }
};

const searchingDespacho = ref(false);
const searchingVinculacion = ref(false);

// Búsqueda de Despacho
let searchTimeout = null;
watch(searchDespacho, (val) => {
    if (val.length < 2) {
        resultadosDespacho.value = [];
        showDespachoResults.value = false;
        return;
    }
    
    // Evitar buscar si el valor es el que acabamos de seleccionar
    if (val === form.value.despacho) return;

    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(async () => {
        searchingDespacho.value = true;
        try {
            const response = await axios.get(route('api.gestion.search-despacho'), { params: { q: val } });
            resultadosDespacho.value = response.data;
            showDespachoResults.value = true;
        } catch (e) {
            console.error('Error en búsqueda de despacho', e);
        } finally {
            searchingDespacho.value = false;
        }
    }, 300);
});

// Búsqueda de Vinculación
let vinculacionTimeout = null;
watch([searchVinculacion, () => form.value.relacionable_type], ([q, type]) => {
    if (q.length < 2 || !type) {
        resultadosVinculacion.value = [];
        showVinculacionResults.value = false;
        return;
    }

    // Evitar buscar si ya está seleccionado
    if (searchVinculacion.value.includes(': ') && form.value.relacionable_id) return;

    clearTimeout(vinculacionTimeout);
    vinculacionTimeout = setTimeout(async () => {
        searchingVinculacion.value = true;
        try {
            const response = await axios.get(route('api.gestion.search-vinculacion'), { params: { q, type } });
            resultadosVinculacion.value = response.data;
            showVinculacionResults.value = true;
        } catch (e) {
            console.error('Error en búsqueda de vinculación', e);
        } finally {
            searchingVinculacion.value = false;
        }
    }, 300);
});

const selectDespacho = (nombre) => {
    form.value.despacho = nombre;
    searchDespacho.value = nombre;
    showDespachoResults.value = false;
};

const selectVinculacion = (item) => {
    form.value.relacionable_id = item.id;
    const typeLabel = form.value.relacionable_type.split('\\').pop().toUpperCase();
    const ref = item.radicado || item.referencia_credito || item.referencia || `#${item.id}`;
    searchVinculacion.value = `${typeLabel}: ${ref}`;
    showVinculacionResults.value = false;
};

const getSemaforoClass = (semaforo) => {
    switch(semaforo) {
        case 'success': return 'bg-emerald-500 shadow-emerald-200';
        case 'danger-blink': return 'bg-red-600 animate-pulse shadow-red-200';
        case 'danger': return 'bg-red-600 shadow-red-200';
        case 'warning': return 'bg-amber-400 shadow-amber-100';
        default: return 'bg-sky-500 shadow-sky-100';
    }
};

const getSemaforoLabel = (semaforo) => {
    switch(semaforo) {
        case 'success': return 'Al día';
        case 'danger-blink': return 'Vence ahora';
        case 'danger': return 'Urgente';
        case 'warning': return 'Próximo';
        default: return 'Programado';
    }
};

const goToLink = (nota) => {
    let url = '#';
    if (nota.relacionable_type === 'App\\Models\\Caso') url = route('casos.show', nota.relacionable_id);
    if (nota.relacionable_type === 'App\\Models\\ProcesoRadicado') url = route('procesos.show', nota.relacionable_id);
    if (nota.relacionable_type === 'App\\Models\\Contrato') url = route('honorarios.contratos.show', nota.relacionable_id);
    
    emit('close');
    router.visit(url);
};

// Bloqueo de Scroll Global
watch(() => props.show, (val) => {
    if (val) {
        document.body.style.overflow = 'hidden';
        fetchNotas(); // Recargar al abrir
    } else {
        document.body.style.overflow = '';
    }
}, { immediate: true });

onMounted(fetchNotas);
</script>

<template>
    <div v-if="show" class="fixed inset-0 z-[100] overflow-hidden">
        <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm transition-opacity" @click="$emit('close')"></div>
        
        <div class="absolute inset-y-0 right-0 flex w-full max-w-2xl transform flex-col bg-white shadow-2xl transition-transform duration-300 dark:bg-gray-950">
            <!-- Header -->
            <div class="border-b border-slate-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-950">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex min-w-0 gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-sky-50 text-sky-700 dark:bg-sky-950 dark:text-sky-300">
                            <ClipboardDocumentCheckIcon class="h-6 w-6" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-xs font-black uppercase tracking-widest text-sky-600 dark:text-sky-400">Gestión diaria</p>
                            <h2 class="mt-1 text-2xl font-black leading-tight text-slate-950 dark:text-white">Hoja de ruta</h2>
                            <p class="mt-1 text-sm font-semibold text-slate-500 dark:text-slate-400">Registre tareas, términos, vínculos y anexos sin salir del flujo.</p>
                        </div>
                    </div>
                    <button @click="$emit('close')" class="rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-gray-800 dark:hover:text-white">
                        <XMarkIcon class="h-6 w-6" />
                    </button>
                </div>

                <div class="mt-5 grid grid-cols-3 gap-3">
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-gray-800 dark:bg-gray-900">
                        <p class="text-[11px] font-black uppercase tracking-widest text-slate-400">Pendientes</p>
                        <p class="mt-1 text-2xl font-black text-slate-950 dark:text-white">{{ pendientes.length }}</p>
                    </div>
                    <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-900/60 dark:bg-emerald-950/30">
                        <p class="text-[11px] font-black uppercase tracking-widest text-emerald-700 dark:text-emerald-300">Hechas</p>
                        <p class="mt-1 text-2xl font-black text-emerald-700 dark:text-emerald-300">{{ completadas.length }}</p>
                    </div>
                    <div class="rounded-lg border border-sky-200 bg-sky-50 p-3 dark:border-sky-900/60 dark:bg-sky-950/30">
                        <p class="text-[11px] font-black uppercase tracking-widest text-sky-700 dark:text-sky-300">Total</p>
                        <p class="mt-1 text-2xl font-black text-sky-700 dark:text-sky-300">{{ notas.length }}</p>
                    </div>
                </div>
            </div>

            <!-- Content -->
            <div class="flex-1 space-y-6 overflow-y-auto bg-slate-50 p-4 dark:bg-gray-900 sm:p-6">
                
                <!-- Formulario -->
                <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-950">
                    <div class="mb-5 flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-black text-slate-950 dark:text-white">Nueva gestión</h3>
                            <p class="mt-1 text-sm font-semibold text-slate-500 dark:text-slate-400">Los campos marcados son necesarios para guardar.</p>
                        </div>
                        <ChevronRightIcon class="h-5 w-5 text-sky-600 dark:text-sky-400" />
                    </div>
                    <div class="space-y-5">
                        <div>
                            <label class="mb-1.5 block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Descripción <span class="text-red-500">*</span></label>
                            <input v-model="form.descripcion" type="text" placeholder="Ej: Radicar memorial de impulso procesal"
                                   class="w-full rounded-lg border-slate-200 text-sm font-semibold text-slate-900 transition focus:border-sky-500 focus:ring-sky-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        </div>
                        
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="relative">
                                <label class="mb-1.5 block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Despacho / entidad <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                    <input v-model="searchDespacho" type="text" placeholder="Buscar despacho o entidad"
                                           class="w-full rounded-lg border-slate-200 pl-9 text-sm font-semibold text-slate-900 transition focus:border-sky-500 focus:ring-sky-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                                    <div v-if="searchingDespacho" class="absolute right-3 top-1/2 -translate-y-1/2">
                                        <div class="h-4 w-4 animate-spin rounded-full border-2 border-sky-500 border-t-transparent"></div>
                                    </div>
                                </div>
                                <div v-if="showDespachoResults" class="absolute z-20 mt-2 max-h-52 w-full overflow-y-auto rounded-lg border border-sky-200 bg-white shadow-2xl dark:border-sky-900 dark:bg-gray-950">
                                    <template v-if="resultadosDespacho.length">
                                        <button v-for="j in resultadosDespacho" :key="j.id" @click="selectDespacho(j.nombre)" 
                                                class="w-full border-b border-slate-100 px-4 py-3 text-left text-xs font-black uppercase tracking-wide text-slate-700 transition last:border-0 hover:bg-sky-600 hover:text-white dark:border-gray-800 dark:text-slate-200">
                                            {{ j.nombre }}
                                        </button>
                                    </template>
                                    <div v-else-if="!searchingDespacho" class="px-4 py-3 text-xs font-bold text-slate-400">
                                        No se encontraron resultados
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Recordatorio <span class="text-red-500">*</span></label>
                                <input v-model="form.expires_at" type="datetime-local" :min="minReminder"
                                       class="w-full rounded-lg border-slate-200 text-sm font-semibold text-slate-900 transition focus:border-sky-500 focus:ring-sky-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <button
                                        v-for="option in quickReminders"
                                        :key="option.label"
                                        @click="setQuickReminder(option)"
                                        type="button"
                                        class="rounded-md border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-black uppercase tracking-wide text-slate-500 transition hover:border-sky-200 hover:bg-sky-50 hover:text-sky-700 dark:border-gray-700 dark:bg-gray-900 dark:text-slate-300 dark:hover:bg-sky-950"
                                    >
                                        {{ option.label }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-slate-100 pt-5 dark:border-gray-800">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <label class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Vincular expediente</label>
                                <button v-if="form.relacionable_type || searchVinculacion" @click="clearRelacion" type="button" class="text-xs font-black uppercase tracking-widest text-slate-400 transition hover:text-red-600">
                                    Limpiar
                                </button>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <SelectInput v-model="form.relacionable_type" class="rounded-lg border-slate-200 bg-white text-xs font-black uppercase tracking-wide focus:ring-sky-500 dark:border-gray-700 dark:bg-gray-900">
                                    <option value="">Registro libre</option>
                                    <option value="App\Models\ProcesoRadicado">Radicado / proceso</option>
                                    <option value="App\Models\Caso">Caso cooperativa</option>
                                    <option value="App\Models\Contrato">Contrato honorarios</option>
                                </SelectInput>
                                <div v-if="form.relacionable_type" class="relative">
                                    <div class="relative">
                                        <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                        <input v-model="searchVinculacion" type="text" placeholder="Buscar por nombre, doc o radicado"
                                               class="w-full rounded-lg border-sky-200 pl-9 text-sm font-semibold text-slate-900 transition focus:border-sky-500 focus:ring-sky-500 dark:border-sky-900 dark:bg-gray-900 dark:text-white">
                                        <div v-if="searchingVinculacion" class="absolute right-3 top-1/2 -translate-y-1/2">
                                            <div class="h-4 w-4 animate-spin rounded-full border-2 border-sky-500 border-t-transparent"></div>
                                        </div>
                                    </div>
                                    <div v-if="showVinculacionResults" class="absolute z-20 mt-2 max-h-60 w-full overflow-y-auto rounded-lg border border-sky-200 bg-white shadow-2xl dark:border-sky-900 dark:bg-gray-950">
                                        <template v-if="resultadosVinculacion.length">
                                            <button v-for="v in resultadosVinculacion" :key="v.id" @click="selectVinculacion(v)" 
                                                    class="w-full border-b border-slate-100 px-4 py-3 text-left text-slate-800 transition last:border-0 hover:bg-sky-600 hover:text-white dark:border-gray-800 dark:text-slate-200">
                                                <div class="text-xs font-black uppercase">{{ v.radicado || v.referencia_credito || v.referencia }}</div>
                                                <div class="truncate text-[11px] font-bold opacity-75">
                                                    {{ v.asunto || '' }}
                                                    <span v-if="v.deudor">· {{ v.deudor.nombre_completo }} ({{ v.deudor.numero_documento }})</span>
                                                    <span v-else-if="v.cliente">· {{ v.cliente.nombre_completo }} ({{ v.cliente.numero_documento }})</span>
                                                    <span v-else-if="v.demandados?.length">· DDO: {{ v.demandados[0].nombre_completo }}</span>
                                                </div>
                                            </button>
                                        </template>
                                        <div v-else-if="!searchingVinculacion" class="px-4 py-3 text-xs font-bold text-slate-400">
                                            No se encontraron resultados
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="flex min-h-10 items-center rounded-lg border border-dashed border-slate-200 px-3 text-xs font-bold text-slate-400 dark:border-gray-700">
                                    Sin expediente vinculado
                                </div>
                            </div>
                        </div>

                        <!-- Sección de Archivos -->
                        <div class="border-t border-slate-100 pt-5 dark:border-gray-800">
                            <label class="mb-2 block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">Anexos</label>
                            <div class="flex flex-wrap items-center gap-3">
                                <button @click="fileInput?.click()" type="button" :disabled="fileLimitReached"
                                        class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2 text-xs font-black uppercase tracking-widest text-slate-600 transition hover:border-sky-200 hover:bg-sky-50 hover:text-sky-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-slate-300 dark:hover:bg-sky-950">
                                    <PaperClipIcon class="h-4 w-4" />
                                    {{ selectedFiles.length > 0 ? 'Agregar más' : 'Adjuntar' }}
                                </button>
                                <input type="file" ref="fileInput" multiple @change="handleFileUpload" class="hidden" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,image/*">
                                <span class="text-xs font-bold text-slate-400">{{ selectedFiles.length }}/3 archivos PDF, imagen, Word o Excel</span>
                            </div>
                            
                            <div v-if="selectedFiles.length" class="mt-3 space-y-2">
                                <div v-for="(file, idx) in selectedFiles" :key="idx" class="flex items-center justify-between gap-3 rounded-lg border border-sky-100 bg-sky-50 p-2 dark:border-sky-900/60 dark:bg-sky-950/30">
                                    <div class="flex min-w-0 items-center gap-2">
                                        <PaperClipIcon class="h-4 w-4 shrink-0 text-sky-600 dark:text-sky-300" />
                                        <span class="truncate text-xs font-bold text-sky-900 dark:text-sky-100">{{ file.name }}</span>
                                    </div>
                                    <button @click="removeFile(idx)" class="rounded-md p-1 text-red-500 transition hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-950/40">
                                        <XMarkIcon class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <button @click="saveNota" :disabled="loading" 
                                class="inline-flex w-full items-center justify-center rounded-lg bg-sky-600 px-4 py-3 text-sm font-black uppercase tracking-widest text-white shadow-lg shadow-sky-100 transition hover:bg-sky-700 disabled:cursor-not-allowed disabled:opacity-50 dark:shadow-none">
                            <span v-if="loading" class="mr-2 h-4 w-4 animate-spin rounded-full border-2 border-white/70 border-t-transparent"></span>
                            {{ loading ? 'Procesando' : 'Registrar gestión' }}
                        </button>
                    </div>
                </div>

                <!-- Listado -->
                <div class="space-y-4 pb-8">
                    <div class="flex items-center justify-between gap-3 px-1">
                        <div>
                            <h4 class="text-sm font-black text-slate-950 dark:text-white">Pendientes y actuaciones</h4>
                            <p class="mt-1 text-xs font-semibold text-slate-500 dark:text-slate-400">Marque como hecha cuando la gestión quede registrada.</p>
                        </div>
                        <span class="rounded-lg border border-sky-100 bg-sky-50 px-3 py-1.5 text-xs font-black uppercase tracking-widest text-sky-700 dark:border-sky-900/60 dark:bg-sky-950/30 dark:text-sky-300">{{ notas.length }} registros</span>
                    </div>

                    <div v-for="nota in notas" :key="nota.id" 
                         :class="['rounded-lg border p-4 transition relative group', nota.is_completed ? 'bg-white/80 border-slate-200 opacity-75 dark:bg-gray-950/70 dark:border-gray-800' : 'bg-white border-slate-200 shadow-sm hover:border-sky-200 hover:shadow-md dark:bg-gray-950 dark:border-gray-800 dark:hover:border-sky-900']">
                        
                        <div class="flex justify-between items-start gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="mb-3 flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-black uppercase tracking-widest text-slate-600 dark:bg-gray-800 dark:text-slate-300">
                                        <span :class="['h-2.5 w-2.5 rounded-full shadow-sm', getSemaforoClass(nota.semaforo)]"></span>
                                        {{ getSemaforoLabel(nota.semaforo) }}
                                    </span>
                                    <span v-if="nota.is_completed" class="rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-black uppercase tracking-widest text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300">
                                        Finalizada
                                    </span>
                                </div>

                                <h5 :class="['break-words text-sm font-black leading-5', nota.is_completed ? 'line-through text-slate-400' : 'text-slate-950 dark:text-white']">
                                        {{ nota.descripcion }}
                                </h5>
                                
                                <div class="mt-3 grid gap-2 text-xs font-bold text-slate-500 dark:text-slate-400 sm:grid-cols-2">
                                    <div class="flex items-center">
                                        <ClockIcon class="mr-1.5 h-4 w-4 text-sky-600 opacity-80" />
                                        {{ nota.tiempo_restante }}
                                    </div>
                                    <div class="truncate">
                                        <span class="mr-1 font-black text-sky-600">@</span>{{ nota.despacho }}
                                    </div>
                                </div>

                                <button v-if="nota.relacionable_type" @click="goToLink(nota)" 
                                        class="mt-3 inline-flex max-w-full items-center rounded-lg bg-sky-50 px-3 py-1.5 text-xs font-black uppercase tracking-wide text-sky-700 transition hover:bg-sky-600 hover:text-white dark:bg-sky-950/30 dark:text-sky-300">
                                    <LinkIcon class="mr-1.5 h-3.5 w-3.5 shrink-0" />
                                    <span class="truncate">{{ nota.relacionable?.radicado || nota.relacionable?.referencia_credito || nota.relacionable?.referencia || 'Sin referencia' }}</span>
                                </button>

                                <!-- Anexos en la lista -->
                                <div v-if="nota.archivos?.length" class="mt-3 flex flex-wrap gap-2">
                                    <a v-for="file in nota.archivos" :key="file.id" 
                                       :href="route('api.gestion.download-archivo', file.id)" target="_blank"
                                       class="flex max-w-full items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px] font-bold text-slate-600 transition hover:border-sky-200 hover:bg-sky-50 hover:text-sky-700 dark:border-gray-800 dark:bg-gray-900 dark:text-slate-300">
                                        <PaperClipIcon class="h-3 w-3 shrink-0" />
                                        {{ file.nombre_original }}
                                    </a>
                                </div>
                            </div>

                            <div class="flex flex-col space-y-2 shrink-0">
                                <button v-if="!nota.is_completed" @click="markAsDone(nota.id)" 
                                        title="Marcar como hecha"
                                        class="rounded-lg border border-emerald-100 bg-emerald-50 p-2.5 text-emerald-600 shadow-sm transition hover:bg-emerald-600 hover:text-white dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-300">
                                    <CheckCircleIcon class="h-5 w-5" />
                                </button>
                                <button v-if="nota.is_completed" @click="deleteNota(nota.id)" 
                                        title="Eliminar"
                                        class="rounded-lg border border-red-100 bg-red-50 p-2.5 text-red-600 shadow-sm transition hover:bg-red-600 hover:text-white dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300">
                                    <TrashIcon class="h-5 w-5" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="notas.length === 0" class="rounded-lg border border-dashed border-sky-200 bg-white py-16 text-center dark:border-sky-900 dark:bg-gray-950">
                        <ExclamationCircleIcon class="mx-auto mb-3 h-12 w-12 text-sky-200 dark:text-sky-900" />
                        <p class="text-sm font-black uppercase tracking-widest text-slate-400">Hoja de ruta vacía</p>
                        <p class="mt-1 text-sm font-semibold text-slate-400">Agregue la primera gestión para empezar el seguimiento del día.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
<style scoped>
/* Scrollbar personalizado para el panel */
::-webkit-scrollbar { width: 4px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: #3b82f6; border-radius: 10px; }
::-webkit-scrollbar-thumb:hover { background: #4f46e5; }
</style>
