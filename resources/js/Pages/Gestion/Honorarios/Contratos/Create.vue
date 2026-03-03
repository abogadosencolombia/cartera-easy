<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch, onMounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { onClickOutside } from '@vueuse/core';
import { PlusIcon, TrashIcon, ArrowPathIcon, CheckCircleIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    clientes: { type: Array, default: () => [] },
    modalidades: { type: Array, default: () => [] },
    plantilla: { type: Object, default: null },
    proceso: { type: Object, default: null },
    clienteSeleccionado: { type: Object, default: null },
    datosCaso: { type: Object, default: null },
});

const todayYmd = () => new Date().toISOString().slice(0, 10);

const form = useForm({
    cliente_id: null,
    monto_total: null,
    anticipo: 0,
    modalidad: 'CUOTAS',
    frecuencia_pago: 'MENSUAL', // NUEVO CAMPO
    cuotas: 12,
    inicio: todayYmd(),
    nota: '',
    porcentaje_litis: null,
    contrato_origen_id: null,
    proceso_id: null,
    caso_id: null,
    // Enviamos las cuotas manuales al backend
    manual_cuotas: [], 
});

// Opciones de Frecuencia
const frecuencias = [
    { value: 'DIARIO', label: 'Diario' },
    { value: 'SEMANAL', label: 'Semanal' },
    { value: 'QUINCENAL', label: 'Quincenal (15 días)' },
    { value: 'MENSUAL', label: 'Mensual' },
    // ✅ CAMBIO REALIZADO: Nueva opción agregada
    { value: 'AL_FINALIZAR', label: 'Al Finalizar el Proceso' },
];

// --- ESTADO PARA CUOTAS MANUALES ---
const manualCuotas = ref([]);
const isManualMode = ref(false); // Si true, el usuario ha editado manualmente y dejamos de auto-calcular

// --- BÚSQUEDA DE CLIENTES (Mismo código anterior) ---
const clienteSearch = ref('');
const selectedClientName = ref('');
const isClientListOpen = ref(false);
const clientDropdown = ref(null);

const filteredClients = computed(() => {
    if (!props.clientes || !Array.isArray(props.clientes)) return [];
    if (!clienteSearch.value) return props.clientes.slice(0, 10);
    const searchTerm = clienteSearch.value.toLowerCase();
    return props.clientes.filter(c =>
        c.nombre && c.nombre.toLowerCase().includes(searchTerm)
    ).slice(0, 10);
});

const selectClient = (client) => {
    if (client && client.id) {
        const clientDisplayName = client.nombre || client.nombre_completo;
        if(clientDisplayName) {
            form.cliente_id = client.id;
            selectedClientName.value = clientDisplayName;
            clienteSearch.value = clientDisplayName;
            isClientListOpen.value = false;
        }
    }
};

watch(clienteSearch, (newVal) => {
    if (newVal !== selectedClientName.value && !(props.clienteSeleccionado && newVal === props.clienteSeleccionado.nombre_completo)) {
        form.cliente_id = null;
    }
});
onClickOutside(clientDropdown, () => isClientListOpen.value = false);

// --- LÓGICA DE NEGOCIO ---
const fmtMoney = (n) => new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(Number(n || 0));

const pageTitle = computed(() => {
    if (props.plantilla) return `Reestructurar Contrato #${props.plantilla.id}`;
    if (props.datosCaso) return `Contrato para Caso #${props.datosCaso.id}`;
    if (props.proceso) {
        const radicadoNum = props.proceso.radicado || props.proceso.id;
        return `Contrato para Radicado #${radicadoNum}`;
    }
    return 'Crear Nuevo Contrato';
});

// --- GENERADOR DE CUOTAS "INTELIGENTE" ACTUALIZADO ---

// Función auxiliar para sumar meses sin desbordamiento (ej: 31 Ene + 1 mes = 28 Feb)
const addMonthsNoOverflow = (dateObj, monthsToAdd) => {
    const day = dateObj.getDate();
    const tmp = new Date(dateObj);
    tmp.setMonth(dateObj.getMonth() + monthsToAdd);
    const lastDayOfMonth = new Date(tmp.getFullYear(), tmp.getMonth() + 1, 0).getDate();
    tmp.setDate(Math.min(day, lastDayOfMonth));
    return tmp;
};

// Nueva función centralizada para calcular fechas según frecuencia
const calcularFechaVencimiento = (fechaInicioYmd, indiceCuota, frecuencia) => {
    if (!fechaInicioYmd) return '';
    // Crear fecha base asegurando zona horaria correcta
    const fechaBase = new Date(fechaInicioYmd.replace(/-/g, '/')); 
    let nuevaFecha = new Date(fechaBase);

    // El índice 0 es la primera cuota (fecha inicial), índice 1 es la segunda, etc.
    if (indiceCuota === 0) return fechaInicioYmd;

    switch (frecuencia) {
        case 'DIARIO':
            nuevaFecha.setDate(fechaBase.getDate() + indiceCuota);
            break;
        case 'SEMANAL':
            nuevaFecha.setDate(fechaBase.getDate() + (7 * indiceCuota));
            break;
        case 'QUINCENAL':
            nuevaFecha.setDate(fechaBase.getDate() + (15 * indiceCuota));
            break;
        case 'MENSUAL':
        case 'AL_FINALIZAR': // Tratamos igual que mensual por defecto para cálculo, el usuario puede editar
        default:
            nuevaFecha = addMonthsNoOverflow(fechaBase, indiceCuota);
            break;
    }

    // Formatear a YYYY-MM-DD
    const yyyy = nuevaFecha.getFullYear();
    const mm = String(nuevaFecha.getMonth() + 1).padStart(2, '0');
    const dd = String(nuevaFecha.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
};

const generarCuotasAutomaticas = () => {
    // Si estamos en modo manual (el usuario editó algo), NO sobrescribimos automáticamente
    // a menos que cambien parámetros drásticos como modalidad.
    
    const total = Number(form.monto_total || 0);
    const anticipo = Number(form.anticipo || 0);
    const neto = Math.max(0, total - anticipo);
    const numCuotas = Math.max(1, parseInt(form.cuotas || 1, 10));

    if (!form.inicio || neto <= 0 || !form.monto_total || form.modalidad === 'LITIS') {
        manualCuotas.value = [];
        return;
    }

    if (form.modalidad === 'PAGO_UNICO') {
        manualCuotas.value = [{ numero: 1, fecha: form.inicio, valor: neto }];
        return;
    }

    // Algoritmo de distribución equitativa (manejo de centavos/pesos)
    const netoCents = Math.round(neto * 100); // Trabajamos en base 100 para precisión
    const base = Math.floor(netoCents / numCuotas);
    const resto = netoCents - base * numCuotas;

    manualCuotas.value = Array.from({ length: numCuotas }, (_, i) => {
        const numero = i + 1;
        const cents = base + (numero <= resto ? 1 : 0);
        const valor = cents / 100; // Volvemos a decimales
        
        // USAMOS LA NUEVA LÓGICA DE FRECUENCIA
        const fecha = calcularFechaVencimiento(form.inicio, i, form.frecuencia_pago);
        
        return { numero, fecha, valor };
    });
    
    isManualMode.value = false; // Reset manual flag
};

// --- OBSERVADORES ---
// Agregamos form.frecuencia_pago a los watchers para regenerar si cambia
watch(
    [
        () => form.monto_total, 
        () => form.anticipo, 
        () => form.cuotas, 
        () => form.inicio, 
        () => form.modalidad,
        () => form.frecuencia_pago // <-- Nuevo watcher
    ],
    () => {
        if (form.modalidad === 'LITIS') {
            // No borramos monto_total para no perderlo si el usuario se arrepiente,
            // especialmente útil cuando viene de un caso y el campo está bloqueado.
            form.cuotas = 1;
            manualCuotas.value = [];
        } else {
            // Si el monto quedó vacío y tenemos una fuente (caso o plantilla), lo restauramos
            if (!form.monto_total) {
                if (props.datosCaso) form.monto_total = props.datosCaso.monto_total;
                else if (props.plantilla) form.monto_total = props.plantilla.monto_total;
            }

            if (form.modalidad === 'PAGO_UNICO') form.cuotas = 1;
            // Regeneramos siempre para asegurar coherencia matemática
            generarCuotasAutomaticas(); 
        }
    }
);

// --- VALIDACIÓN MATEMÁTICA EN TIEMPO REAL ---
const validationStatus = computed(() => {
    if (form.modalidad === 'LITIS') return { valid: true, diff: 0, message: 'Modalidad Litis no requiere cronograma fijo.' };

    const total = Number(form.monto_total || 0);
    const anticipo = Number(form.anticipo || 0);
    const objetivo = Math.max(0, total - anticipo);
    
    const sumaActual = manualCuotas.value.reduce((acc, row) => acc + Number(row.valor || 0), 0);
    
    // Usamos pequeña tolerancia para punto flotante
    const diff = objetivo - sumaActual;
    const isExact = Math.abs(diff) < 0.01;

    if (isExact) {
        return { valid: true, diff: 0, message: '¡Perfecto! La suma coincide.', color: 'green' };
    } else if (diff > 0) {
        return { valid: false, diff, message: `Faltan distribuir ${fmtMoney(diff)}`, color: 'amber' };
    } else {
        return { valid: false, diff, message: `Te has pasado por ${fmtMoney(Math.abs(diff))}`, color: 'red' };
    }
});

// --- ACCIONES MANUALES ---
const addManualRow = () => {
    isManualMode.value = true;
    
    // Obtener última fecha para calcular la siguiente según frecuencia
    let nextDate = form.inicio;
    if (manualCuotas.value.length > 0) {
        const lastDate = manualCuotas.value[manualCuotas.value.length - 1].fecha;
        // Calculamos la siguiente fecha como si fuera la cuota #1 relativa a la anterior
        nextDate = calcularFechaVencimiento(lastDate, 1, form.frecuencia_pago);
    }
    
    // Sugerencia inteligente: Agrega lo que falta para completar el monto, o 0 si ya se pasó
    const remaining = validationStatus.value.diff > 0 ? validationStatus.value.diff : 0;

    manualCuotas.value.push({
        numero: manualCuotas.value.length + 1,
        fecha: nextDate,
        valor: parseFloat(remaining.toFixed(2)) // Sugerimos el remanente
    });
    // Actualizamos el contador visual de cuotas del form
    form.cuotas = manualCuotas.value.length;
};

const removeManualRow = (index) => {
    isManualMode.value = true;
    manualCuotas.value.splice(index, 1);
    // Renumerar
    manualCuotas.value.forEach((row, i) => row.numero = i + 1);
    form.cuotas = manualCuotas.value.length;
};

const resetToAuto = () => {
    if(confirm('¿Estás seguro? Se borrarán tus ediciones manuales y se distribuirá el monto equitativamente.')){
        generarCuotasAutomaticas();
    }
};

// --- ENVÍO ---
const submit = () => {
    // Inyectamos las cuotas manuales al form antes de enviar
    form.manual_cuotas = manualCuotas.value;
    form.post(route('honorarios.contratos.store'), {
        preserveScroll: true,
        onError: (errors) => {
            console.error("Errores al guardar contrato:", errors);
        }
    });
};

onMounted(() => {
    // Inicialización de datos (igual que antes)
    if (props.plantilla) {
        const clienteOriginal = props.clientes.find(c => c.id === props.plantilla.cliente_id);
        form.defaults({
            cliente_id: props.plantilla.cliente_id,
            monto_total: props.plantilla.monto_total,
            anticipo: props.plantilla.anticipo,
            modalidad: props.plantilla.modalidad,
            frecuencia_pago: props.plantilla.frecuencia_pago || 'MENSUAL', // Cargar frecuencia si existe en plantilla
            cuotas: 12,
            inicio: todayYmd(),
            nota: `Reestructuración del contrato #${props.plantilla.id}.`,
            porcentaje_litis: props.plantilla.porcentaje_litis,
            contrato_origen_id: props.plantilla.id,
            proceso_id: props.plantilla.proceso_id,
            caso_id: props.plantilla.caso_id,
        });
        form.reset();
        if (clienteOriginal) selectClient(clienteOriginal);
        setTimeout(generarCuotasAutomaticas, 100);

    } else if (props.datosCaso) {
        form.defaults({
            cliente_id: props.clienteSeleccionado?.id || null,
            monto_total: props.datosCaso.monto_total,
            anticipo: 0,
            modalidad: 'CUOTAS',
            frecuencia_pago: 'MENSUAL',
            cuotas: 12,
            inicio: todayYmd(),
            nota: `Contrato generado desde el Caso de cobro #${props.datosCaso.id}.`,
            caso_id: props.datosCaso.id,
        });
        form.reset();
        if (props.clienteSeleccionado) selectClient(props.clienteSeleccionado);
        setTimeout(generarCuotasAutomaticas, 100);

    } else if (props.clienteSeleccionado && props.proceso) {
        const radicadoNum = props.proceso.radicado || props.proceso.id;
        form.defaults({
            cliente_id: props.clienteSeleccionado.id,
            proceso_id: props.proceso.id,
            nota: `Contrato generado desde el Radicado #${radicadoNum}.`,
            monto_total: null,
            anticipo: 0,
            modalidad: 'CUOTAS',
            frecuencia_pago: 'MENSUAL',
            cuotas: 12,
            inicio: todayYmd(),
        });
        form.reset();
        selectClient(props.clienteSeleccionado);
    }
});
</script>

<template>
    <Head :title="`${pageTitle} · Honorarios`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">
                    {{ pageTitle }}
                </h2>
                <div class="flex items-center gap-4">
                    <Link :href="route('honorarios.contratos.index')" class="text-sm px-3 py-2 rounded-md bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 flex items-center gap-2">
                        Volver
                    </Link>
                    <!-- BOTÓN GUARDAR INTELIGENTE -->
                    <button @click="submit" 
                            :disabled="form.processing || (form.modalidad !== 'LITIS' && !validationStatus.valid)"
                            :class="[
                                'inline-flex items-center gap-2 px-4 py-2 rounded-md text-sm font-semibold text-white transition-colors',
                                (form.modalidad !== 'LITIS' && !validationStatus.valid) 
                                    ? 'bg-gray-400 cursor-not-allowed opacity-70' 
                                    : 'bg-indigo-600 hover:bg-indigo-700'
                            ]">
                        <CheckCircleIcon v-if="validationStatus.valid" class="h-5 w-5" />
                        <ExclamationTriangleIcon v-else class="h-5 w-5" />
                        {{ (form.modalidad !== 'LITIS' && !validationStatus.valid) ? 'Corrige los montos' : 'Guardar Contrato' }}
                    </button>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <!-- ALERTA DE ERRORES GENERALES -->
                <div v-if="Object.keys(form.errors).length > 0" class="bg-red-50 dark:bg-red-900/20 border-l-4 border-red-400 p-4 rounded-md">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <ExclamationTriangleIcon class="h-5 w-5 text-red-400" aria-hidden="true" />
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-bold text-red-800 dark:text-red-200">Se encontraron errores de validación</h3>
                            <div class="mt-2 text-sm text-red-700 dark:text-red-300">
                                <ul role="list" class="list-disc space-y-1 pl-5">
                                    <li v-for="(error, field) in form.errors" :key="field">{{ error }}</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 1: Información Principal -->
                <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                    <div class="p-6 border-b dark:border-gray-700 flex items-center gap-3">
                        <div class="bg-indigo-100 dark:bg-indigo-900/50 p-2 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-indigo-600 dark:text-indigo-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg></div>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Información Principal</h3>
                    </div>
                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2 relative" ref="clientDropdown">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Cliente *</label>
                            <input type="text" v-model="clienteSearch" @focus="isClientListOpen = true"
                                   :disabled="!!props.plantilla || !!props.clienteSeleccionado"
                                   placeholder="Escribe para buscar un cliente..."
                                   class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-gray-100 dark:disabled:bg-gray-800/50" />
                            <p v-if="form.errors.cliente_id" class="mt-1 text-sm text-red-600">{{ form.errors.cliente_id }}</p>
                            
                            <!-- Dropdown Cliente -->
                            <transition enter-active-class="transition ease-out duration-100" enter-from-class="transform opacity-0 scale-95" enter-to-class="transform opacity-100 scale-100" leave-active-class="transition ease-in duration-75" leave-from-class="transform opacity-100 scale-100" leave-to-class="transform opacity-0 scale-95">
                                <div v-if="isClientListOpen && !props.plantilla && !props.clienteSeleccionado" class="absolute z-10 mt-1 w-full bg-white dark:bg-gray-900 shadow-lg rounded-md border dark:border-gray-700 max-h-60 overflow-y-auto">
                                    <ul class="py-1">
                                        <li v-if="filteredClients.length === 0" class="px-4 py-2 text-sm text-gray-500">No hay coincidencias</li>
                                        <li v-for="client in filteredClients" :key="client.id" @click="selectClient(client)"
                                            class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-indigo-500 hover:text-white dark:hover:bg-indigo-600 cursor-pointer">
                                            {{ client.nombre }}
                                        </li>
                                    </ul>
                                </div>
                            </transition>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Fecha de Inicio *</label>
                            <input type="date" v-model="form.inicio" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" />
                            <p v-if="form.errors.inicio" class="mt-1 text-sm text-red-600 font-bold">{{ form.errors.inicio }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Anticipo (Opcional)</label>
                            <div class="relative mt-1">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-500">COP</div>
                                <input type="number" v-model.number="form.anticipo" placeholder="0" class="pl-12 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" />
                            </div>
                            <p v-if="form.errors.anticipo" class="mt-1 text-sm text-red-600 font-bold">{{ form.errors.anticipo }}</p>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Detalles del Pago -->
                <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                    <div class="p-6 border-b dark:border-gray-700 flex items-center gap-3">
                         <div class="bg-emerald-100 dark:bg-emerald-900/50 p-2 rounded-lg"><svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-emerald-600 dark:text-emerald-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" /></svg></div>
                        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Detalles del Pago</h3>
                    </div>
                    <div class="p-6 space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Modalidad de Pago *</label>
                            <fieldset class="mt-2">
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                    <label v-for="mod in modalidades" :key="mod" :class="['relative flex items-center justify-center p-3 rounded-lg border cursor-pointer transition-all', form.modalidad === mod ? 'bg-indigo-50 dark:bg-indigo-900/30 border-indigo-500 dark:border-indigo-600 ring-2 ring-indigo-500' : 'bg-white dark:bg-gray-900/50 border-gray-300 dark:border-gray-600 hover:border-gray-400 dark:hover:border-gray-500']">
                                        <input type="radio" v-model="form.modalidad" :value="mod" class="hidden" />
                                        <span class="text-sm font-medium text-gray-900 dark:text-gray-200">{{ mod.replace('_', ' ') }}</span>
                                    </label>
                                </div>
                            </fieldset>
                            <p v-if="form.errors.modalidad" class="mt-1 text-sm text-red-600 font-bold">{{ form.errors.modalidad }}</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t dark:border-gray-700">
                            <div v-if="form.modalidad === 'CUOTAS' || form.modalidad === 'PAGO_UNICO' || form.modalidad === 'CUOTA_MIXTA'">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ form.modalidad === 'CUOTA_MIXTA' ? 'Monto Fijo a Cuotas *' : 'Monto Total *' }}
                                </label>
                                <div class="relative mt-1">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-500">COP</div>
                                    <input type="number" v-model.number="form.monto_total" 
                                           :disabled="!!props.datosCaso" 
                                           placeholder="5000000" 
                                           class="pl-12 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-gray-100 dark:disabled:bg-gray-800/50" />
                                </div>
                                <p v-if="form.errors.monto_total" class="mt-1 text-sm text-red-600 font-bold">{{ form.errors.monto_total }}</p>
                            </div>

                            <div v-if="form.modalidad === 'CUOTAS' || form.modalidad === 'CUOTA_MIXTA'">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Número de Cuotas Base *</label>
                                <input type="number" v-model.number="form.cuotas" min="1" max="120" step="1" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" />
                                <p v-if="form.errors.cuotas" class="mt-1 text-sm text-red-600 font-bold">{{ form.errors.cuotas }}</p>
                                <p class="text-xs text-gray-500 mt-1">Si editas la tabla inferior manualmente, este número se actualizará.</p>
                            </div>

                            <!-- NUEVO: SELECTOR DE FRECUENCIA -->
                            <div v-if="form.modalidad === 'CUOTAS' || form.modalidad === 'CUOTA_MIXTA'">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Frecuencia de Pago *</label>
                                <select v-model="form.frecuencia_pago" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                    <option v-for="freq in frecuencias" :key="freq.value" :value="freq.value">{{ freq.label }}</option>
                                </select>
                                <p v-if="form.errors.frecuencia_pago" class="mt-1 text-sm text-red-600 font-bold">{{ form.errors.frecuencia_pago }}</p>
                            </div>

                            <div v-if="form.modalidad === 'LITIS' || form.modalidad === 'CUOTA_MIXTA'">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Porcentaje de Éxito *</label>
                                <div class="relative mt-1">
                                    <input type="number" v-model.number="form.porcentaje_litis" placeholder="30" class="pr-8 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" />
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-gray-500">%</div>
                                </div>
                                <p v-if="form.errors.porcentaje_litis" class="mt-1 text-sm text-red-600 font-bold">{{ form.errors.porcentaje_litis }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nota (Opcional)</label>
                            <textarea v-model="form.nota" rows="3" class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 shadow-sm focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Cronograma de Pagos EDITABLE -->
                <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg overflow-hidden">
                    <div class="p-6 border-b dark:border-gray-700 flex justify-between items-center">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Cronograma de Pagos</h3>
                            <p class="text-sm text-gray-500 mt-1">
                                <span v-if="form.modalidad !== 'LITIS'">
                                    Modifica las fechas o valores si es necesario. <strong class="text-indigo-600">La suma debe ser exacta.</strong>
                                </span>
                                <span v-else>No se genera cronograma inicial para la modalidad LITIS.</span>
                            </p>
                        </div>
                        <div v-if="form.modalidad !== 'LITIS'">
                            <button type="button" @click="resetToAuto" class="text-xs flex items-center gap-1 text-indigo-600 hover:text-indigo-800 border border-indigo-200 px-2 py-1 rounded bg-indigo-50">
                                <ArrowPathIcon class="w-3 h-3" />
                                Recalcular Automático
                            </button>
                        </div>
                    </div>

                    <template v-if="form.modalidad !== 'LITIS'">
                        
                        <!-- TABLA DE CUOTAS EDITABLE -->
                        <div class="overflow-x-auto max-h-[500px] overflow-y-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700/50 sticky top-0 z-10">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-12">#</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fecha Vencimiento</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Valor Cuota</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase w-10">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-for="(fila, idx) in manualCuotas" :key="idx">
                                        <td class="px-4 py-2 text-sm font-mono text-gray-500">{{ idx + 1 }}</td>
                                        <td class="px-4 py-2">
                                            <input type="date" v-model="fila.fecha" 
                                                   class="text-sm rounded border-gray-300 dark:border-gray-600 dark:bg-gray-900 py-1 px-2 w-full focus:ring-1 focus:ring-indigo-500" />
                                            <p v-if="form.errors[`manual_cuotas.${idx}.fecha`]" class="text-[10px] text-red-600 mt-1">{{ form.errors[`manual_cuotas.${idx}.fecha`] }}</p>
                                        </td>
                                        <td class="px-4 py-2">
                                            <div class="relative">
                                                <div class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none text-gray-400 text-xs">$</div>
                                                <input type="number" v-model.number="fila.valor"
                                                       class="text-sm text-right font-mono rounded border-gray-300 dark:border-gray-600 dark:bg-gray-900 py-1 px-2 w-full focus:ring-1 focus:ring-indigo-500" />
                                            </div>
                                            <p v-if="form.errors[`manual_cuotas.${idx}.valor`]" class="text-[10px] text-red-600 mt-1 text-right">{{ form.errors[`manual_cuotas.${idx}.valor`] }}</p>
                                        </td>
                                        <td class="px-4 py-2 text-center">
                                            <button type="button" @click="removeManualRow(idx)" class="text-gray-400 hover:text-red-500 transition">
                                                <TrashIcon class="w-5 h-5" />
                                            </button>
                                        </td>
                                    </tr>
                                    <!-- Botón agregar fila -->
                                    <tr>
                                        <td colspan="4" class="px-4 py-3 text-center border-t dark:border-gray-700">
                                            <button type="button" @click="addManualRow" class="inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800 font-medium">
                                                <PlusIcon class="w-4 h-4" />
                                                Agregar Cuota Manual
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        
                        <!-- BARRA DE VALIDACIÓN (A PRUEBA DE TONTOS) -->
                        <div class="p-4 border-t dark:border-gray-700 flex flex-col sm:flex-row justify-between items-center gap-4"
                             :class="{
                                 'bg-green-50 dark:bg-green-900/20': validationStatus.valid,
                                 'bg-red-50 dark:bg-red-900/20': !validationStatus.valid && validationStatus.diff < 0,
                                 'bg-amber-50 dark:bg-amber-900/20': !validationStatus.valid && validationStatus.diff > 0
                             }">
                            
                            <div class="text-sm font-medium flex items-center gap-2">
                                <span v-if="validationStatus.valid" class="text-green-700 dark:text-green-400 flex items-center gap-2">
                                    <CheckCircleIcon class="w-5 h-5" />
                                    {{ validationStatus.message }}
                                </span>
                                <span v-else-if="validationStatus.diff > 0" class="text-amber-700 dark:text-amber-400 flex items-center gap-2">
                                    <ExclamationTriangleIcon class="w-5 h-5" />
                                    {{ validationStatus.message }}
                                </span>
                                <span v-else class="text-red-700 dark:text-red-400 flex items-center gap-2">
                                    <ExclamationTriangleIcon class="w-5 h-5" />
                                    {{ validationStatus.message }}
                                </span>
                            </div>

                            <div class="flex items-center gap-6 text-sm">
                                <div>
                                    <span class="block text-gray-500 text-xs">Total Asignado</span>
                                    <span class="font-bold font-mono text-lg text-gray-800 dark:text-white">
                                        {{ fmtMoney(Math.max(0, (form.monto_total || 0) - (form.anticipo || 0))) }}
                                    </span>
                                </div>
                                <div class="text-right">
                                    <span class="block text-gray-500 text-xs">Suma Cuotas</span>
                                    <span class="font-bold font-mono text-lg" :class="validationStatus.color === 'green' ? 'text-green-600' : 'text-red-600'">
                                        {{ fmtMoney(manualCuotas.reduce((acc, r) => acc + Number(r.valor), 0)) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                    </template>
                    <div v-else class="p-10 text-center text-gray-500">
                         <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                         <h4 class="mt-2 font-semibold">Modalidad Litis</h4>
                         <p class="text-sm">El cobro se generará como un cargo único al finalizar el proceso, basado en el porcentaje de éxito.</p>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-4 sticky bottom-0 z-20 border-t dark:border-gray-700">
                    <button @click="submit" 
                            :disabled="form.processing || (form.modalidad !== 'LITIS' && !validationStatus.valid)"
                            :class="[
                                'w-full inline-flex justify-center items-center gap-2 px-4 py-3 rounded-md text-base font-semibold text-white transition-colors',
                                (form.modalidad !== 'LITIS' && !validationStatus.valid) 
                                    ? 'bg-gray-400 cursor-not-allowed' 
                                    : 'bg-indigo-600 hover:bg-indigo-700'
                            ]">
                        {{ (form.modalidad !== 'LITIS' && !validationStatus.valid) ? 'Corrige los montos para guardar' : 'Guardar Contrato' }}
                    </button>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>