<script setup>
import { ref, watch, onMounted } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import axios from 'axios';
import Swal from 'sweetalert2';
import { 
    XMarkIcon, 
    ClipboardDocumentCheckIcon,
    MagnifyingGlassIcon,
    LinkIcon,
    CheckCircleIcon,
    TrashIcon,
    ClockIcon,
    ExclamationCircleIcon,
    ChevronRightIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    show: Boolean
});

const emit = defineEmits(['close']);

// Configuración de Toast
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.onmouseenter = Swal.stopTimer;
        toast.onmouseleave = Swal.resumeTimer;
    }
});

// Estado
const loading = ref(false);
const notas = ref([]);
const form = ref({
    descripcion: '',
    despacho: '',
    termino: '',
    relacionable_type: '',
    relacionable_id: null
});

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

// Guardar Nota
const saveNota = async () => {
    if (!form.value.descripcion || !form.value.despacho || !form.value.termino) {
        Toast.fire({ icon: 'warning', title: 'CAMPOS OBLIGATORIOS VACÍOS' });
        return;
    }
    
    loading.value = true;
    try {
        await axios.post(route('api.gestion.store'), form.value);
        form.value = { descripcion: '', despacho: '', termino: '', relacionable_type: '', relacionable_id: null };
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
    const result = await Swal.fire({
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

// Búsqueda de Despacho
watch(searchDespacho, async (val) => {
    if (val.length < 3) {
        resultadosDespacho.value = [];
        return;
    }
    try {
        const response = await axios.get(route('api.gestion.search-despacho'), { params: { q: val } });
        resultadosDespacho.value = response.data;
        showDespachoResults.value = true;
    } catch (e) {}
});

// Búsqueda de Vinculación
watch([searchVinculacion, () => form.value.relacionable_type], async ([q, type]) => {
    if (q.length < 2 || !type) {
        resultadosVinculacion.value = [];
        return;
    }
    try {
        const response = await axios.get(route('api.gestion.search-vinculacion'), { params: { q, type } });
        resultadosVinculacion.value = response.data;
        showVinculacionResults.value = true;
    } catch (e) {}
});

const selectDespacho = (nombre) => {
    form.value.despacho = nombre;
    searchDespacho.value = nombre;
    showDespachoResults.value = false;
};

const selectVinculacion = (item) => {
    form.value.relacionable_id = item.id;
    const label = item.radicado || item.referencia_credito || item.referencia;
    searchVinculacion.value = label;
    showVinculacionResults.value = false;
};

const getSemaforoClass = (semaforo) => {
    switch(semaforo) {
        case 'success': return 'bg-green-500 shadow-green-200';
        case 'danger-blink': return 'bg-red-600 animate-pulse shadow-red-200';
        case 'danger': return 'bg-red-600 shadow-red-200';
        case 'warning': return 'bg-yellow-400 shadow-yellow-100';
        default: return 'bg-blue-500 shadow-blue-100';
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
        <div class="absolute inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity" @click="$emit('close')"></div>
        
        <div class="absolute inset-y-0 right-0 w-full max-w-xl bg-white shadow-2xl flex flex-col transform transition-transform duration-300">
            <!-- Header -->
            <div class="p-6 bg-indigo-600 text-white flex justify-between items-center shadow-lg">
                <div>
                    <h2 class="text-xl font-black uppercase tracking-tighter flex items-center italic">
                        <ClipboardDocumentCheckIcon class="w-6 h-6 mr-2 text-white" />
                        Hoja de Ruta Diaria
                    </h2>
                    <p class="text-[10px] font-bold text-white/80 uppercase tracking-[0.2em] mt-1">Control personal de términos y gestiones</p>
                </div>
                <button @click="$emit('close')" class="p-2 hover:bg-white/20 rounded-full transition group">
                    <XMarkIcon class="w-6 h-6 group-hover:rotate-90 transition-transform duration-200" />
                </button>
            </div>

            <!-- Content -->
            <div class="flex-1 overflow-y-auto p-6 space-y-8 bg-gray-50/50">
                
                <!-- Formulario -->
                <div class="bg-white p-6 rounded-xl border border-indigo-100 shadow-sm">
                    <h3 class="text-[10px] font-black text-blue-500 uppercase mb-6 tracking-[0.3em] flex items-center">
                        <ChevronRightIcon class="w-3 h-3 mr-1" />
                        Nueva Entrada de Gestión
                    </h3>
                    <div class="space-y-5">
                        <div>
                            <label class="block text-[9px] font-black text-gray-400 uppercase mb-1 ml-1">Descripción de la Tarea</label>
                            <input v-model="form.descripcion" type="text" placeholder="¿QUÉ GESTIÓN REALIZARÁ HOY?" 
                                   class="w-full text-xs font-black uppercase rounded-lg border-gray-200 focus:border-indigo-600 focus:ring-indigo-600 transition-all">
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div class="relative">
                                <label class="block text-[9px] font-black text-gray-400 uppercase mb-1 ml-1">Despacho / Entidad</label>
                                <input v-model="searchDespacho" type="text" placeholder="BUSCAR..." 
                                       class="w-full text-xs font-black uppercase rounded-lg border-gray-200 focus:border-blue-500 focus:ring-blue-500 transition-all shadow-inner text-blue-600">
                                <div v-if="showDespachoResults && resultadosDespacho.length" class="absolute z-10 w-full mt-1 bg-white border-2 border-indigo-600 shadow-2xl rounded-lg max-h-48 overflow-y-auto">
                                    <button v-for="j in resultadosDespacho" :key="j.id" @click="selectDespacho(j.nombre)" 
                                            class="w-full text-left px-4 py-3 text-[10px] font-black uppercase hover:bg-indigo-600 hover:text-white border-b last:border-0 transition">
                                        {{ j.nombre }}
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[9px] font-black text-gray-400 uppercase mb-1 ml-1">Término / Plazo</label>
                                <input v-model="form.termino" type="text" placeholder="EJ: 24H / INMEDIATO" 
                                       class="w-full text-xs font-black uppercase rounded-lg border-gray-200 focus:border-indigo-600 focus:ring-indigo-600 transition-all">
                            </div>
                        </div>

                        <div class="pt-4 border-t border-gray-100">
                            <label class="block text-[9px] font-black text-gray-500 uppercase mb-3 ml-1 tracking-widest opacity-60 italic">Vincular a Expediente (Opcional)</label>
                            <div class="grid grid-cols-2 gap-4">
                                <select v-model="form.relacionable_type" class="text-[10px] font-black uppercase rounded-lg border-gray-200 bg-gray-50 focus:ring-blue-500">
                                    <option value="">REGISTRO LIBRE</option>
                                    <option value="App\Models\ProcesoRadicado">RADICADO / PROCESO</option>
                                    <option value="App\Models\Caso">CASO COOPERATIVA</option>
                                    <option value="App\Models\Contrato">CONTRATO HONORARIOS</option>
                                </select>
                                <div v-if="form.relacionable_type" class="relative">
                                    <input v-model="searchVinculacion" type="text" placeholder="FILTRAR..." 
                                           class="w-full text-[10px] font-black uppercase rounded-lg border-blue-200 focus:border-blue-500 focus:ring-blue-500 shadow-sm text-blue-600">
                                    <div v-if="showVinculacionResults && resultadosVinculacion.length" class="absolute z-10 w-full mt-1 bg-white border-2 border-blue-500 shadow-2xl rounded-lg max-h-48 overflow-y-auto">
                                        <button v-for="v in resultadosVinculacion" :key="v.id" @click="selectVinculacion(v)" 
                                                class="w-full text-left px-4 py-3 hover:bg-blue-500 hover:text-white border-b last:border-0 transition">
                                            <div class="text-[10px] font-black uppercase">{{ v.radicado || v.referencia_credito || v.referencia }}</div>
                                            <div class="text-[8px] font-bold opacity-75 uppercase truncate">{{ v.asunto || (v.cliente ? v.cliente.nombre_completo : '') }}</div>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button @click="saveNota" :disabled="loading" 
                                class="w-full bg-blue-500 hover:bg-indigo-600 text-white font-black py-4 rounded-xl text-[11px] uppercase tracking-[0.3em] transition-all shadow-lg hover:shadow-blue-200 disabled:opacity-50">
                            {{ loading ? 'PROCESANDO...' : 'REGISTRAR EN HOJA DE RUTA' }}
                        </button>
                    </div>
                </div>

                <!-- Listado -->
                <div class="space-y-4 pb-10">
                    <div class="flex items-center justify-between px-2">
                        <h4 class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">Pendientes y Actuaciones</h4>
                        <span class="text-[9px] font-bold text-blue-500 bg-blue-50 px-2 py-0.5 rounded border border-blue-100">{{ notas.length }} REGISTROS</span>
                    </div>

                    <div v-for="nota in notas" :key="nota.id" 
                         :class="['p-5 rounded-2xl border-2 transition relative group', nota.is_completed ? 'bg-white border-gray-100 grayscale' : 'bg-white border-white shadow-md hover:shadow-lg hover:border-blue-100']">
                        
                        <div class="flex justify-between items-start gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center space-x-3 mb-2">
                                    <div :class="['h-3 w-3 rounded-full border-2 border-white shadow-sm shrink-0', getSemaforoClass(nota.semaforo)]"></div>
                                    <h5 :class="['text-[11px] font-black uppercase truncate tracking-tight', nota.is_completed ? 'line-through text-gray-400' : 'text-indigo-900']">
                                        {{ nota.descripcion }}
                                    </h5>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-2 mb-3">
                                    <div class="flex items-center text-[9px] font-bold text-gray-500 uppercase">
                                        <ClockIcon class="w-3.5 h-3.5 mr-1.5 text-blue-500 opacity-70" /> 
                                        {{ nota.tiempo_restante }}
                                    </div>
                                    <div class="flex items-center text-[9px] font-bold text-gray-500 uppercase truncate">
                                        <span class="opacity-50 mr-1 italic text-indigo-600">@</span> {{ nota.despacho }}
                                    </div>
                                </div>

                                <button v-if="nota.relacionable_type" @click="goToLink(nota)" 
                                        class="inline-flex items-center px-3 py-1.5 bg-blue-50 hover:bg-blue-500 text-blue-600 hover:text-white rounded-lg text-[9px] font-black uppercase transition-all tracking-tighter">
                                    <LinkIcon class="w-3 h-3 mr-1.5" />
                                    VÍNCULO: {{ nota.relacionable?.radicado || nota.relacionable?.referencia_credito || nota.relacionable?.referencia }}
                                </button>
                            </div>

                            <div class="flex flex-col space-y-2 shrink-0">
                                <button v-if="!nota.is_completed" @click="markAsDone(nota.id)" 
                                        class="p-2.5 bg-green-50 text-green-600 hover:bg-green-600 hover:text-white rounded-xl transition-all shadow-sm border border-green-100">
                                    <CheckCircleIcon class="w-5 h-5" />
                                </button>
                                <button v-if="nota.is_completed" @click="deleteNota(nota.id)" 
                                        class="p-2.5 bg-red-50 text-red-600 hover:bg-red-600 hover:text-white rounded-xl transition-all shadow-sm border border-red-100">
                                    <TrashIcon class="w-5 h-5" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="notas.length === 0" class="text-center py-20 bg-white rounded-3xl border border-dashed border-blue-100">
                        <ExclamationCircleIcon class="w-12 h-12 mx-auto mb-3 text-blue-100" />
                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.3em]">Hoja de ruta vacía</p>
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
