<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SelectInput from '@/Components/SelectInput.vue';
import { onClickOutside } from '@vueuse/core';
import {
    ArrowLeftIcon,
    ArrowPathIcon,
    BanknotesIcon,
    CalendarDaysIcon,
    CheckCircleIcon,
    DocumentTextIcon,
    ExclamationTriangleIcon,
    InformationCircleIcon,
    PlusIcon,
    ScaleIcon,
    TrashIcon,
    UserIcon,
} from '@heroicons/vue/24/outline';
import { useFormDraft } from '@/composables/useFormDraft';
import AppAlert from '@/Utils/appAlert';

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
    frecuencia_pago: 'MENSUAL',
    cuotas: 12,
    inicio: todayYmd(),
    nota: '',
    porcentaje_litis: null,
    contrato_origen_id: null,
    proceso_id: null,
    caso_id: null,
    manual_cuotas: [],
    pagos_iniciales: [],
});

const frecuencias = [
    { value: 'DIARIO', label: 'Diario' },
    { value: 'SEMANAL', label: 'Semanal' },
    { value: 'QUINCENAL', label: 'Quincenal' },
    { value: 'MENSUAL', label: 'Mensual' },
    { value: 'AL_FINALIZAR', label: 'Al finalizar' },
];

const metodosPago = ['TRANSFERENCIA', 'EFECTIVO', 'TARJETA', 'OTRO'];

const modalidadMeta = {
    CUOTAS: { label: 'Cuotas', description: 'Valor fijo distribuido en varias fechas.' },
    PAGO_UNICO: { label: 'Pago unico', description: 'Un solo vencimiento por el saldo.' },
    LITIS: { label: 'Litis', description: 'Cobro por porcentaje al cierre.' },
    CUOTA_MIXTA: { label: 'Cuota mixta', description: 'Cuotas mas porcentaje de exito.' },
};

const modalidadOptions = computed(() => {
    const source = props.modalidades?.length ? props.modalidades : ['CUOTAS', 'PAGO_UNICO', 'LITIS', 'CUOTA_MIXTA'];
    return source.map((value) => ({ value, ...(modalidadMeta[value] || { label: value, description: 'Modalidad disponible.' }) }));
});

const manualCuotas = ref([]);
const pagosIniciales = ref([]);
const isManualMode = ref(false);
const skipNextAutoGenerate = ref(false);
const clienteSearch = ref('');
const selectedClientName = ref('');
const isClientListOpen = ref(false);
const clientDropdown = ref(null);

const fmtMoney = (n) => new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    maximumFractionDigits: 0,
}).format(Number(n || 0));

const fmtDate = (value) => {
    if (!value) return 'Sin fecha';
    const date = new Date(`${String(value).slice(0, 10)}T00:00:00`);
    if (Number.isNaN(date.getTime())) return 'Fecha invalida';

    return date.toLocaleDateString('es-CO', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' });
};

const roundCurrency = (value) => Math.round(Number(value || 0) * 100) / 100;

const filteredClients = computed(() => {
    if (!Array.isArray(props.clientes)) return [];
    const searchTerm = clienteSearch.value.trim().toLowerCase();

    if (!searchTerm) return props.clientes.slice(0, 12);

    return props.clientes.filter((client) => {
        const name = String(client.nombre || client.nombre_completo || '').toLowerCase();
        return name.includes(searchTerm);
    }).slice(0, 12);
});

const lockedClient = computed(() => Boolean(props.plantilla || props.clienteSeleccionado));

const selectClient = (client) => {
    const clientDisplayName = client?.nombre || client?.nombre_completo;
    if (!client?.id || !clientDisplayName) return;

    form.cliente_id = client.id;
    selectedClientName.value = clientDisplayName;
    clienteSearch.value = clientDisplayName;
    isClientListOpen.value = false;
};

watch(clienteSearch, (newVal) => {
    if (newVal !== selectedClientName.value && !(props.clienteSeleccionado && newVal === props.clienteSeleccionado.nombre_completo)) {
        form.cliente_id = null;
    }
});

onClickOutside(clientDropdown, () => { isClientListOpen.value = false; });

const pageTitle = computed(() => {
    if (props.plantilla) return `Reestructurar contrato #${props.plantilla.id}`;
    if (props.datosCaso) return `Contrato para caso #${props.datosCaso.id}`;
    if (props.proceso) return `Contrato para radicado #${props.proceso.radicado || props.proceso.id}`;
    return 'Nuevo contrato de honorarios';
});

const sourceLabel = computed(() => {
    if (props.plantilla) return 'Reestructuracion';
    if (props.datosCaso) return `Caso #${props.datosCaso.id}`;
    if (props.proceso) return `Radicado ${props.proceso.radicado || props.proceso.id}`;
    return 'Contrato independiente';
});

const requiresFixedAmount = computed(() => ['CUOTAS', 'PAGO_UNICO', 'CUOTA_MIXTA'].includes(form.modalidad));
const requiresSchedule = computed(() => form.modalidad !== 'LITIS');
const requiresLitis = computed(() => ['LITIS', 'CUOTA_MIXTA'].includes(form.modalidad));

const totalContrato = computed(() => Number(form.monto_total || 0));
const anticipoValue = computed(() => Number(form.anticipo || 0));
const netoFinanciar = computed(() => Math.max(0, totalContrato.value - anticipoValue.value));
const sumaCuotas = computed(() => manualCuotas.value.reduce((acc, row) => acc + Number(row.valor || 0), 0));
const primeraCuota = computed(() => manualCuotas.value[0]?.fecha || null);
const ultimaCuota = computed(() => manualCuotas.value[manualCuotas.value.length - 1]?.fecha || null);

const addMonthsNoOverflow = (dateObj, monthsToAdd) => {
    const day = dateObj.getDate();
    const tmp = new Date(dateObj);
    tmp.setMonth(dateObj.getMonth() + monthsToAdd);
    const lastDayOfMonth = new Date(tmp.getFullYear(), tmp.getMonth() + 1, 0).getDate();
    tmp.setDate(Math.min(day, lastDayOfMonth));
    return tmp;
};

const calcularFechaVencimiento = (fechaInicioYmd, indiceCuota, frecuencia) => {
    if (!fechaInicioYmd) return '';
    const fechaBase = new Date(fechaInicioYmd.replace(/-/g, '/'));
    let nuevaFecha = new Date(fechaBase);

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
        case 'AL_FINALIZAR':
        default:
            nuevaFecha = addMonthsNoOverflow(fechaBase, indiceCuota);
            break;
    }

    const yyyy = nuevaFecha.getFullYear();
    const mm = String(nuevaFecha.getMonth() + 1).padStart(2, '0');
    const dd = String(nuevaFecha.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
};

const crearPagoInicial = () => ({
    estado: 'PENDIENTE',
    valor: null,
    fecha: '',
    metodo: 'TRANSFERENCIA',
    nota: '',
    comprobante: null,
});

const pagoInicialActivo = (pago) => ['PAGO_PARCIAL', 'PAGADA'].includes(pago?.estado);

const syncPagosIniciales = () => {
    while (pagosIniciales.value.length < manualCuotas.value.length) {
        pagosIniciales.value.push(crearPagoInicial());
    }

    if (pagosIniciales.value.length > manualCuotas.value.length) {
        pagosIniciales.value.splice(manualCuotas.value.length);
    }

    pagosIniciales.value.forEach((pago, idx) => {
        const cuota = manualCuotas.value[idx];
        if (!pago.estado) pago.estado = 'PENDIENTE';
        if (!pago.metodo) pago.metodo = 'TRANSFERENCIA';
        if (pagoInicialActivo(pago) && !pago.fecha) pago.fecha = cuota?.fecha || form.inicio || todayYmd();
        if (pago.estado === 'PAGADA') pago.valor = roundCurrency(cuota?.valor || 0);
    });
};

const cambiarEstadoPagoInicial = (idx, estado) => {
    syncPagosIniciales();
    const pago = pagosIniciales.value[idx];
    const cuota = manualCuotas.value[idx];
    if (!pago || !cuota) return;

    pago.estado = estado;
    pago.fecha = pago.fecha || cuota.fecha || form.inicio || todayYmd();
    pago.metodo = pago.metodo || 'TRANSFERENCIA';

    if (estado === 'PAGADA') {
        pago.valor = roundCurrency(cuota.valor);
        return;
    }

    if (estado === 'PAGO_PARCIAL') {
        const cuotaValor = roundCurrency(cuota.valor);
        const valorActual = roundCurrency(pago.valor);
        pago.valor = valorActual > 0 && valorActual < cuotaValor ? valorActual : null;
        return;
    }

    pago.valor = null;
    pago.nota = '';
    pago.comprobante = null;
};

const marcarTodasPagadas = () => {
    syncPagosIniciales();
    manualCuotas.value.forEach((cuota, idx) => {
        const pago = pagosIniciales.value[idx];
        pago.estado = 'PAGADA';
        pago.valor = roundCurrency(cuota.valor);
        pago.fecha = pago.fecha || cuota.fecha || form.inicio || todayYmd();
        pago.metodo = pago.metodo || 'TRANSFERENCIA';
    });
};

const limpiarPagosIniciales = () => {
    pagosIniciales.value = manualCuotas.value.map(() => crearPagoInicial());
};

const generarCuotasAutomaticas = () => {
    const total = totalContrato.value;
    const anticipo = anticipoValue.value;
    const neto = Math.max(0, total - anticipo);
    const numCuotas = Math.max(1, parseInt(form.cuotas || 1, 10));

    if (!form.inicio || neto <= 0 || !form.monto_total || form.modalidad === 'LITIS') {
        manualCuotas.value = [];
        syncPagosIniciales();
        return;
    }

    if (form.modalidad === 'PAGO_UNICO') {
        manualCuotas.value = [{ numero: 1, fecha: form.inicio, valor: neto }];
        form.cuotas = 1;
        syncPagosIniciales();
        return;
    }

    const netoCents = Math.round(neto * 100);
    const base = Math.floor(netoCents / numCuotas);
    const resto = netoCents - base * numCuotas;

    manualCuotas.value = Array.from({ length: numCuotas }, (_, i) => {
        const numero = i + 1;
        const cents = base + (numero <= resto ? 1 : 0);
        const fecha = calcularFechaVencimiento(form.inicio, i, form.frecuencia_pago);

        return { numero, fecha, valor: cents / 100 };
    });

    isManualMode.value = false;
    syncPagosIniciales();
};

watch(
    [
        () => form.monto_total,
        () => form.anticipo,
        () => form.cuotas,
        () => form.inicio,
        () => form.modalidad,
        () => form.frecuencia_pago,
    ],
    () => {
        if (skipNextAutoGenerate.value) {
            skipNextAutoGenerate.value = false;
            return;
        }

        if (form.modalidad === 'LITIS') {
            form.cuotas = 1;
            manualCuotas.value = [];
            return;
        }

        if (!form.monto_total) {
            if (props.datosCaso) form.monto_total = props.datosCaso.monto_total;
            else if (props.plantilla) form.monto_total = props.plantilla.monto_total;
        }

        if (form.modalidad === 'PAGO_UNICO') form.cuotas = 1;
        generarCuotasAutomaticas();
    },
);

const validationStatus = computed(() => {
    if (form.modalidad === 'LITIS') {
        return { valid: true, diff: 0, message: 'Sin cronograma fijo', color: 'green' };
    }

    const diff = netoFinanciar.value - sumaCuotas.value;
    const isExact = Math.abs(diff) < 0.01;

    if (isExact) return { valid: true, diff: 0, message: 'Cuotas cuadradas', color: 'green' };
    if (diff > 0) return { valid: false, diff, message: `Faltan ${fmtMoney(diff)}`, color: 'amber' };
    return { valid: false, diff, message: `Sobran ${fmtMoney(Math.abs(diff))}`, color: 'red' };
});

const validationClass = computed(() => {
    if (validationStatus.value.valid) return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-300';
    if (validationStatus.value.diff > 0) return 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200';
    return 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-300';
});

const pagosInicialesActivos = computed(() => pagosIniciales.value.filter((pago) => pagoInicialActivo(pago)));
const totalPagosIniciales = computed(() => pagosInicialesActivos.value.reduce((acc, pago) => acc + Number(pago.valor || 0), 0));
const saldoInicial = computed(() => Math.max(0, netoFinanciar.value - totalPagosIniciales.value));

const pagosInicialesErrors = computed(() => {
    if (!requiresSchedule.value) return [];

    const errors = [];
    pagosIniciales.value.forEach((pago, idx) => {
        if (!pagoInicialActivo(pago)) return;

        const cuotaValor = roundCurrency(manualCuotas.value[idx]?.valor || 0);
        const valorPago = roundCurrency(pago.valor);
        const cuotaLabel = `Cuota #${idx + 1}`;

        if (valorPago <= 0) errors.push(`${cuotaLabel}: registra el valor pagado.`);
        if (valorPago - cuotaValor > 0.01) errors.push(`${cuotaLabel}: el pago no puede superar el valor de la cuota.`);
        if (!pago.fecha) errors.push(`${cuotaLabel}: registra la fecha del pago.`);
        if (!pago.metodo) errors.push(`${cuotaLabel}: selecciona el metodo de pago.`);
    });

    return errors;
});

const pagosInicialesValidos = computed(() => pagosInicialesErrors.value.length === 0);

const canSubmit = computed(() => {
    if (form.processing || !form.cliente_id || !form.inicio || !form.modalidad) return false;
    if (requiresFixedAmount.value && totalContrato.value <= 0) return false;
    if (requiresLitis.value && Number(form.porcentaje_litis || 0) <= 0) return false;
    if (requiresSchedule.value && (!validationStatus.value.valid || manualCuotas.value.length === 0)) return false;
    if (!pagosInicialesValidos.value) return false;
    return true;
});

const addManualRow = () => {
    isManualMode.value = true;
    let nextDate = form.inicio;

    if (manualCuotas.value.length > 0) {
        const lastDate = manualCuotas.value[manualCuotas.value.length - 1].fecha;
        nextDate = calcularFechaVencimiento(lastDate, 1, form.frecuencia_pago);
    }

    const remaining = validationStatus.value.diff > 0 ? validationStatus.value.diff : 0;

    manualCuotas.value.push({
        numero: manualCuotas.value.length + 1,
        fecha: nextDate,
        valor: parseFloat(remaining.toFixed(2)),
    });
    skipNextAutoGenerate.value = true;
    form.cuotas = Math.max(1, manualCuotas.value.length);
    syncPagosIniciales();
};

const removeManualRow = (index) => {
    isManualMode.value = true;
    manualCuotas.value.splice(index, 1);
    manualCuotas.value.forEach((row, i) => { row.numero = i + 1; });
    skipNextAutoGenerate.value = true;
    form.cuotas = Math.max(1, manualCuotas.value.length);
    syncPagosIniciales();
};

const resetToAuto = async () => {
    const result = await AppAlert.fire({
        title: 'Recalcular cronograma',
        text: 'Se reemplazaran las fechas y valores editados manualmente.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Recalcular',
        cancelButtonText: 'Cancelar',
    });

    if (result.isConfirmed) generarCuotasAutomaticas();
};

watch(manualCuotas, syncPagosIniciales, { deep: true });

const buildPagosInicialesPayload = () => pagosIniciales.value
    .map((pago, idx) => ({
        cuota_numero: idx + 1,
        estado: pago.estado || 'PENDIENTE',
        valor: roundCurrency(pago.valor),
        fecha: pago.fecha,
        metodo: pago.metodo,
        nota: pago.nota,
        comprobante: pago.comprobante,
    }));

const submit = () => {
    if (!canSubmit.value) {
        const message = pagosInicialesErrors.value[0] || 'Falta informacion obligatoria o el cronograma no cuadra.';
        AppAlert.fire('Revisa el contrato', message, 'warning');
        return;
    }

    form.manual_cuotas = manualCuotas.value;
    form.pagos_iniciales = buildPagosInicialesPayload();
    form.post(route('honorarios.contratos.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: clearDraft,
        onError: () => AppAlert.fire('No se pudo guardar', 'Revisa los campos marcados por el sistema.', 'error'),
    });
};

onMounted(() => {
    if (props.plantilla) {
        const clienteOriginal = props.clientes.find((client) => client.id === props.plantilla.cliente_id);
        form.defaults({
            cliente_id: props.plantilla.cliente_id,
            monto_total: props.plantilla.monto_total,
            anticipo: props.plantilla.anticipo,
            modalidad: props.plantilla.modalidad,
            frecuencia_pago: props.plantilla.frecuencia_pago || 'MENSUAL',
            cuotas: 12,
            inicio: todayYmd(),
            nota: `Reestructuracion del contrato #${props.plantilla.id}.`,
            porcentaje_litis: props.plantilla.porcentaje_litis,
            contrato_origen_id: props.plantilla.id,
            proceso_id: props.plantilla.proceso_id,
            caso_id: props.plantilla.caso_id,
        });
        form.reset();
        if (clienteOriginal) selectClient(clienteOriginal);
        setTimeout(generarCuotasAutomaticas, 100);
        return;
    }

    if (props.datosCaso) {
        form.defaults({
            cliente_id: props.clienteSeleccionado?.id || null,
            monto_total: props.datosCaso.monto_total,
            anticipo: 0,
            modalidad: 'CUOTAS',
            frecuencia_pago: 'MENSUAL',
            cuotas: 12,
            inicio: todayYmd(),
            nota: `Contrato generado desde el caso de cobro #${props.datosCaso.id}.`,
            caso_id: props.datosCaso.id,
        });
        form.reset();
        if (props.clienteSeleccionado) selectClient(props.clienteSeleccionado);
        setTimeout(generarCuotasAutomaticas, 100);
        return;
    }

    if (props.clienteSeleccionado && props.proceso) {
        const radicadoNum = props.proceso.radicado || props.proceso.id;
        form.defaults({
            cliente_id: props.clienteSeleccionado.id,
            proceso_id: props.proceso.id,
            nota: `Contrato generado desde el radicado #${radicadoNum}.`,
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

const contractDraftKey = props.plantilla
    ? `draft:create:honorarios.contratos:plantilla:${props.plantilla.id}`
    : props.datosCaso
        ? `draft:create:honorarios.contratos:caso:${props.datosCaso.id}`
        : props.proceso
            ? `draft:create:honorarios.contratos:proceso:${props.proceso.id}`
            : 'draft:create:honorarios.contratos:nuevo';

const { clearDraft } = useFormDraft(form, contractDraftKey, {
    extra: () => ({
        manualCuotas: manualCuotas.value,
        pagosIniciales: pagosIniciales.value.map(({ comprobante, ...pago }) => ({ ...pago, comprobante: null })),
        isManualMode: isManualMode.value,
        clienteSearch: clienteSearch.value,
        selectedClientName: selectedClientName.value,
    }),
    restoreExtra: (draft) => {
        if (Array.isArray(draft.manualCuotas)) manualCuotas.value = draft.manualCuotas;
        if (Array.isArray(draft.pagosIniciales)) pagosIniciales.value = draft.pagosIniciales;
        if (typeof draft.isManualMode === 'boolean') isManualMode.value = draft.isManualMode;
        if (typeof draft.clienteSearch === 'string') clienteSearch.value = draft.clienteSearch;
        if (typeof draft.selectedClientName === 'string') selectedClientName.value = draft.selectedClientName;
        syncPagosIniciales();
    },
    restoreExtraAfterTick: true,
});
</script>

<template>
    <Head :title="`${pageTitle} · Honorarios`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex min-w-0 items-center gap-3">
                    <Link
                        :href="route('honorarios.contratos.index')"
                        class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 shadow-sm transition hover:text-indigo-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                    >
                        <ArrowLeftIcon class="h-5 w-5" />
                    </Link>
                    <div class="min-w-0">
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Gestion de honorarios</p>
                        <h2 class="mt-1 text-2xl font-black tracking-tight text-gray-950 dark:text-white">{{ pageTitle }}</h2>
                        <p class="mt-1 text-sm font-medium text-gray-500 dark:text-gray-400">{{ sourceLabel }}</p>
                    </div>
                </div>
                <button
                    type="button"
                    @click="submit"
                    :disabled="!canSubmit"
                    class="inline-flex items-center justify-center gap-2 rounded-lg border px-4 py-2.5 text-xs font-black uppercase tracking-widest shadow-sm transition"
                    :class="canSubmit
                        ? 'border-indigo-600 bg-indigo-600 text-white hover:bg-indigo-700'
                        : 'cursor-not-allowed border-gray-300 bg-gray-300 text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400'"
                >
                    <CheckCircleIcon v-if="canSubmit" class="h-4 w-4" />
                    <ExclamationTriangleIcon v-else class="h-4 w-4" />
                    Guardar contrato
                </button>
            </div>
        </template>

        <div class="min-h-screen bg-gray-50/70 py-6 dark:bg-gray-950/40">
            <div class="mx-auto max-w-[1600px] px-4 sm:px-6 lg:px-8">
                <div v-if="Object.keys(form.errors).length > 0" class="mb-5 rounded-lg border border-rose-200 bg-rose-50 p-4 text-rose-800 shadow-sm dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-200">
                    <div class="flex items-start gap-3">
                        <ExclamationTriangleIcon class="mt-0.5 h-5 w-5 shrink-0" />
                        <div>
                            <h3 class="text-sm font-black uppercase tracking-widest">Errores de validacion</h3>
                            <ul class="mt-2 space-y-1 text-sm font-semibold">
                                <li v-for="(error, field) in form.errors" :key="field">{{ error }}</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
                    <main class="space-y-5">
                        <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                            <div class="border-b border-gray-200 p-5 dark:border-gray-700">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300">
                                        <UserIcon class="h-5 w-5" />
                                    </span>
                                    <div>
                                        <h3 class="text-base font-black text-gray-950 dark:text-white">Datos base</h3>
                                        <p class="mt-1 text-xs font-semibold text-gray-500 dark:text-gray-400">Cliente, fecha de inicio y anticipo.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-5 p-5 lg:grid-cols-3">
                                <div class="relative lg:col-span-3" ref="clientDropdown">
                                    <label class="block text-xs font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">Cliente *</label>
                                    <input
                                        v-model="clienteSearch"
                                        type="text"
                                        @focus="isClientListOpen = true"
                                        :disabled="lockedClient"
                                        placeholder="Buscar cliente"
                                        class="mt-2 w-full rounded-lg border-gray-300 px-3 py-2.5 text-sm font-semibold shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100 dark:disabled:bg-gray-800/60"
                                    />
                                    <p v-if="form.errors.cliente_id" class="mt-1 text-xs font-bold text-rose-600 dark:text-rose-300">{{ form.errors.cliente_id }}</p>

                                    <transition enter-active-class="transition ease-out duration-100" enter-from-class="opacity-0 translate-y-1" enter-to-class="opacity-100 translate-y-0" leave-active-class="transition ease-in duration-75" leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 translate-y-1">
                                        <div v-if="isClientListOpen && !lockedClient" class="absolute z-20 mt-2 max-h-64 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-950">
                                            <button
                                                v-for="client in filteredClients"
                                                :key="client.id"
                                                type="button"
                                                @click="selectClient(client)"
                                                class="block w-full px-4 py-3 text-left text-sm font-semibold text-gray-700 transition hover:bg-indigo-50 hover:text-indigo-700 dark:text-gray-300 dark:hover:bg-indigo-950/40 dark:hover:text-indigo-300"
                                            >
                                                {{ client.nombre || client.nombre_completo }}
                                            </button>
                                            <div v-if="filteredClients.length === 0" class="px-4 py-3 text-sm font-semibold text-gray-500 dark:text-gray-400">Sin coincidencias</div>
                                        </div>
                                    </transition>
                                </div>

                                <div>
                                    <label class="block text-xs font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">Fecha de inicio *</label>
                                    <input v-model="form.inicio" type="date" class="mt-2 w-full rounded-lg border-gray-300 px-3 py-2.5 text-sm font-semibold shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" />
                                    <p v-if="form.errors.inicio" class="mt-1 text-xs font-bold text-rose-600 dark:text-rose-300">{{ form.errors.inicio }}</p>
                                </div>

                                <div>
                                    <label class="block text-xs font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">Anticipo</label>
                                    <div class="relative mt-2">
                                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-xs font-black text-gray-400">COP</span>
                                        <input v-model.number="form.anticipo" type="number" min="0" class="w-full rounded-lg border-gray-300 py-2.5 pl-12 pr-3 text-sm font-semibold shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" />
                                    </div>
                                    <p v-if="form.errors.anticipo" class="mt-1 text-xs font-bold text-rose-600 dark:text-rose-300">{{ form.errors.anticipo }}</p>
                                </div>

                                <div class="rounded-lg border border-indigo-200 bg-indigo-50 p-3 dark:border-indigo-900/60 dark:bg-indigo-950/30">
                                    <p class="text-[10px] font-black uppercase tracking-widest text-indigo-700 dark:text-indigo-300">Origen</p>
                                    <p class="mt-1 text-sm font-black text-indigo-900 dark:text-indigo-100">{{ sourceLabel }}</p>
                                </div>
                            </div>
                        </section>

                        <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                            <div class="border-b border-gray-200 p-5 dark:border-gray-700">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                                        <BanknotesIcon class="h-5 w-5" />
                                    </span>
                                    <div>
                                        <h3 class="text-base font-black text-gray-950 dark:text-white">Acuerdo economico</h3>
                                        <p class="mt-1 text-xs font-semibold text-gray-500 dark:text-gray-400">Modalidad, monto, frecuencia y porcentaje.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-5 p-5">
                                <div>
                                    <label class="block text-xs font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">Modalidad *</label>
                                    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                        <label
                                            v-for="mod in modalidadOptions"
                                            :key="mod.value"
                                            class="cursor-pointer rounded-lg border p-4 transition"
                                            :class="form.modalidad === mod.value
                                                ? 'border-indigo-500 bg-indigo-50 ring-2 ring-indigo-500/20 dark:border-indigo-700 dark:bg-indigo-950/30'
                                                : 'border-gray-200 bg-white hover:border-indigo-200 dark:border-gray-700 dark:bg-gray-950 dark:hover:border-indigo-900'"
                                        >
                                            <input v-model="form.modalidad" type="radio" :value="mod.value" class="sr-only" />
                                            <span class="block text-sm font-black text-gray-950 dark:text-white">{{ mod.label }}</span>
                                            <span class="mt-1 block text-xs font-semibold leading-5 text-gray-500 dark:text-gray-400">{{ mod.description }}</span>
                                        </label>
                                    </div>
                                    <p v-if="form.errors.modalidad" class="mt-1 text-xs font-bold text-rose-600 dark:text-rose-300">{{ form.errors.modalidad }}</p>
                                </div>

                                <div class="grid grid-cols-1 gap-5 lg:grid-cols-4">
                                    <div v-if="requiresFixedAmount" class="lg:col-span-2">
                                        <label class="block text-xs font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">
                                            {{ form.modalidad === 'CUOTA_MIXTA' ? 'Monto fijo a cuotas *' : 'Monto total *' }}
                                        </label>
                                        <div class="relative mt-2">
                                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-xs font-black text-gray-400">COP</span>
                                            <input
                                                v-model.number="form.monto_total"
                                                type="number"
                                                min="0"
                                                :disabled="!!props.datosCaso"
                                                class="w-full rounded-lg border-gray-300 py-2.5 pl-12 pr-3 text-sm font-semibold shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100 dark:disabled:bg-gray-800/60"
                                            />
                                        </div>
                                        <p v-if="form.errors.monto_total" class="mt-1 text-xs font-bold text-rose-600 dark:text-rose-300">{{ form.errors.monto_total }}</p>
                                    </div>

                                    <div v-if="form.modalidad === 'CUOTAS' || form.modalidad === 'CUOTA_MIXTA'">
                                        <label class="block text-xs font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">Cuotas base *</label>
                                        <input v-model.number="form.cuotas" type="number" min="1" max="120" step="1" class="mt-2 w-full rounded-lg border-gray-300 px-3 py-2.5 text-sm font-semibold shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" />
                                        <p v-if="form.errors.cuotas" class="mt-1 text-xs font-bold text-rose-600 dark:text-rose-300">{{ form.errors.cuotas }}</p>
                                    </div>

                                    <div v-if="form.modalidad === 'CUOTAS' || form.modalidad === 'CUOTA_MIXTA'">
                                        <label class="block text-xs font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">Frecuencia *</label>
                                        <SelectInput v-model="form.frecuencia_pago" class="mt-2 w-full rounded-lg text-sm font-semibold">
                                            <option v-for="freq in frecuencias" :key="freq.value" :value="freq.value">{{ freq.label }}</option>
                                        </SelectInput>
                                        <p v-if="form.errors.frecuencia_pago" class="mt-1 text-xs font-bold text-rose-600 dark:text-rose-300">{{ form.errors.frecuencia_pago }}</p>
                                    </div>

                                    <div v-if="requiresLitis">
                                        <label class="block text-xs font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">Porcentaje exito *</label>
                                        <div class="relative mt-2">
                                            <input v-model.number="form.porcentaje_litis" type="number" min="0" max="100" class="w-full rounded-lg border-gray-300 px-3 py-2.5 pr-9 text-sm font-semibold shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" />
                                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs font-black text-gray-400">%</span>
                                        </div>
                                        <p v-if="form.errors.porcentaje_litis" class="mt-1 text-xs font-bold text-rose-600 dark:text-rose-300">{{ form.errors.porcentaje_litis }}</p>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">Nota</label>
                                    <textarea v-model="form.nota" rows="3" class="mt-2 w-full rounded-lg border-gray-300 px-3 py-2.5 text-sm font-semibold shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"></textarea>
                                </div>
                            </div>
                        </section>

                        <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                            <div class="flex flex-col gap-4 border-b border-gray-200 p-5 dark:border-gray-700 lg:flex-row lg:items-center lg:justify-between">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-sky-50 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300">
                                        <CalendarDaysIcon class="h-5 w-5" />
                                    </span>
                                    <div>
                                        <h3 class="text-base font-black text-gray-950 dark:text-white">Cronograma de pagos</h3>
                                        <p class="mt-1 text-xs font-semibold text-gray-500 dark:text-gray-400">{{ requiresSchedule ? `${manualCuotas.length} cuota(s) programada(s)` : 'Sin cuotas iniciales' }}</p>
                                    </div>
                                </div>
                                <button
                                    v-if="requiresSchedule"
                                    type="button"
                                    @click="resetToAuto"
                                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-[10px] font-black uppercase tracking-widest text-indigo-700 transition hover:bg-indigo-100 dark:border-indigo-900/60 dark:bg-indigo-950/30 dark:text-indigo-300"
                                >
                                    <ArrowPathIcon class="h-4 w-4" />
                                    Recalcular
                                </button>
                            </div>

                            <template v-if="requiresSchedule">
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-gray-800/70">
                                            <tr>
                                                <th class="w-16 px-4 py-3 text-left text-[10px] font-black uppercase tracking-widest text-gray-400">#</th>
                                                <th class="px-4 py-3 text-left text-[10px] font-black uppercase tracking-widest text-gray-400">Vencimiento</th>
                                                <th class="px-4 py-3 text-right text-[10px] font-black uppercase tracking-widest text-gray-400">Valor</th>
                                                <th class="px-4 py-3 text-left text-[10px] font-black uppercase tracking-widest text-gray-400">Estado inicial</th>
                                                <th class="w-16 px-4 py-3 text-center text-[10px] font-black uppercase tracking-widest text-gray-400">Quitar</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                            <template v-for="(fila, idx) in manualCuotas" :key="`cuota-${idx}`">
                                                <tr class="align-top">
                                                    <td class="px-4 py-3 text-sm font-black text-gray-500 dark:text-gray-400">{{ idx + 1 }}</td>
                                                    <td class="px-4 py-3">
                                                        <input v-model="fila.fecha" type="date" class="w-full rounded-lg border-gray-300 px-3 py-2 text-sm font-semibold focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" />
                                                        <p v-if="form.errors[`manual_cuotas.${idx}.fecha`]" class="mt-1 text-xs font-bold text-rose-600 dark:text-rose-300">{{ form.errors[`manual_cuotas.${idx}.fecha`] }}</p>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <div class="relative ml-auto max-w-xs">
                                                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-xs font-black text-gray-400">$</span>
                                                            <input v-model.number="fila.valor" type="number" min="0" class="w-full rounded-lg border-gray-300 py-2 pl-8 pr-3 text-right text-sm font-black focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" />
                                                        </div>
                                                        <p v-if="form.errors[`manual_cuotas.${idx}.valor`]" class="mt-1 text-right text-xs font-bold text-rose-600 dark:text-rose-300">{{ form.errors[`manual_cuotas.${idx}.valor`] }}</p>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <select
                                                            v-if="pagosIniciales[idx]"
                                                            v-model="pagosIniciales[idx].estado"
                                                            @change="cambiarEstadoPagoInicial(idx, $event.target.value)"
                                                            class="w-full rounded-lg border-gray-300 px-3 py-2 text-sm font-semibold shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"
                                                        >
                                                            <option value="PENDIENTE">Pendiente</option>
                                                            <option value="PAGO_PARCIAL">Abono parcial</option>
                                                            <option value="PAGADA">Pagada</option>
                                                        </select>
                                                        <p v-if="pagosIniciales[idx] && pagoInicialActivo(pagosIniciales[idx])" class="mt-1 text-xs font-bold text-emerald-700 dark:text-emerald-300">
                                                            Registrado: {{ fmtMoney(pagosIniciales[idx].valor) }}
                                                        </p>
                                                    </td>
                                                    <td class="px-4 py-3 text-center">
                                                        <button type="button" @click="removeManualRow(idx)" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-400 transition hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/30">
                                                            <TrashIcon class="h-4 w-4" />
                                                        </button>
                                                    </td>
                                                </tr>
                                                <tr v-if="pagosIniciales[idx] && pagoInicialActivo(pagosIniciales[idx])" class="bg-emerald-50/40 dark:bg-emerald-950/10">
                                                    <td colspan="5" class="px-4 pb-4 pt-0">
                                                        <div class="rounded-lg border border-emerald-200 bg-white p-4 dark:border-emerald-900/60 dark:bg-gray-950/60">
                                                            <div class="grid grid-cols-1 gap-3 lg:grid-cols-[12rem_11rem_11rem_minmax(0,1fr)_16rem]">
                                                                <div>
                                                                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400">Valor pagado</label>
                                                                    <div class="relative mt-1">
                                                                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-xs font-black text-gray-400">$</span>
                                                                        <input
                                                                            v-model.number="pagosIniciales[idx].valor"
                                                                            type="number"
                                                                            min="0.01"
                                                                            step="0.01"
                                                                            :max="fila.valor"
                                                                            :disabled="pagosIniciales[idx].estado === 'PAGADA'"
                                                                            class="w-full rounded-lg border-gray-300 py-2 pl-8 pr-3 text-right text-sm font-black focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100 dark:disabled:bg-gray-800/60"
                                                                        />
                                                                    </div>
                                                                    <p v-if="form.errors[`pagos_iniciales.${idx}.valor`]" class="mt-1 text-xs font-bold text-rose-600 dark:text-rose-300">{{ form.errors[`pagos_iniciales.${idx}.valor`] }}</p>
                                                                </div>
                                                                <div>
                                                                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400">Fecha</label>
                                                                    <input v-model="pagosIniciales[idx].fecha" type="date" class="mt-1 w-full rounded-lg border-gray-300 px-3 py-2 text-sm font-semibold focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" />
                                                                </div>
                                                                <div>
                                                                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400">Metodo</label>
                                                                    <select v-model="pagosIniciales[idx].metodo" class="mt-1 w-full rounded-lg border-gray-300 px-3 py-2 text-sm font-semibold focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                                                                        <option v-for="metodo in metodosPago" :key="metodo" :value="metodo">{{ metodo }}</option>
                                                                    </select>
                                                                </div>
                                                                <div>
                                                                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400">Nota o referencia</label>
                                                                    <input v-model="pagosIniciales[idx].nota" type="text" class="mt-1 w-full rounded-lg border-gray-300 px-3 py-2 text-sm font-semibold focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" />
                                                                </div>
                                                                <div>
                                                                    <label class="block text-[10px] font-black uppercase tracking-widest text-gray-400">Comprobante</label>
                                                                    <input
                                                                        type="file"
                                                                        accept=".pdf,.jpg,.jpeg,.png,.webp"
                                                                        @input="pagosIniciales[idx].comprobante = $event.target.files[0] || null"
                                                                        class="mt-1 block w-full rounded-lg border border-dashed border-gray-300 bg-gray-50 p-2 text-xs text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-xs file:font-black file:text-indigo-700 hover:file:bg-indigo-100 dark:border-gray-700 dark:bg-gray-950/40 dark:text-gray-300 dark:file:bg-indigo-950/40 dark:file:text-indigo-300"
                                                                    />
                                                                    <p v-if="form.errors[`pagos_iniciales.${idx}.comprobante`]" class="mt-1 text-xs font-bold text-rose-600 dark:text-rose-300">{{ form.errors[`pagos_iniciales.${idx}.comprobante`] }}</p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </template>
                                            <tr>
                                                <td colspan="5" class="px-4 py-4 text-center">
                                                    <button type="button" @click="addManualRow" class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-black uppercase tracking-widest text-gray-700 transition hover:border-indigo-200 hover:text-indigo-700 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-300 dark:hover:border-indigo-800 dark:hover:text-indigo-300">
                                                        <PlusIcon class="h-4 w-4" />
                                                        Agregar cuota
                                                    </button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="border-t border-gray-200 p-4 dark:border-gray-700">
                                    <div class="flex flex-col gap-3 rounded-lg border p-4" :class="validationClass">
                                        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                                            <div class="flex items-start gap-3">
                                                <CheckCircleIcon v-if="validationStatus.valid" class="mt-0.5 h-5 w-5 shrink-0" />
                                                <ExclamationTriangleIcon v-else class="mt-0.5 h-5 w-5 shrink-0" />
                                                <div>
                                                    <p class="text-sm font-black">{{ validationStatus.message }}</p>
                                                    <p class="mt-1 text-xs font-semibold opacity-80">Neto a financiar: {{ fmtMoney(netoFinanciar) }}</p>
                                                </div>
                                            </div>
                                            <div class="grid grid-cols-2 gap-4 text-right text-sm lg:min-w-80">
                                                <div>
                                                    <p class="text-[10px] font-black uppercase tracking-widest opacity-70">Suma cuotas</p>
                                                    <p class="mt-1 font-black">{{ fmtMoney(sumaCuotas) }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-[10px] font-black uppercase tracking-widest opacity-70">Diferencia</p>
                                                    <p class="mt-1 font-black">{{ fmtMoney(Math.abs(validationStatus.diff || 0)) }}</p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 gap-3 border-t border-current/10 pt-3 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
                                            <div class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-3">
                                                <div>
                                                    <p class="text-[10px] font-black uppercase tracking-widest opacity-70">Pagos iniciales</p>
                                                    <p class="mt-1 font-black">{{ fmtMoney(totalPagosIniciales) }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-[10px] font-black uppercase tracking-widest opacity-70">Saldo inicial</p>
                                                    <p class="mt-1 font-black">{{ fmtMoney(saldoInicial) }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-[10px] font-black uppercase tracking-widest opacity-70">Cuotas con pago</p>
                                                    <p class="mt-1 font-black">{{ pagosInicialesActivos.length }}</p>
                                                </div>
                                            </div>
                                            <div class="flex flex-wrap gap-2 lg:justify-end">
                                                <button type="button" @click="marcarTodasPagadas" class="inline-flex items-center justify-center gap-2 rounded-lg border border-current/20 bg-white/50 px-3 py-2 text-[10px] font-black uppercase tracking-widest transition hover:bg-white dark:bg-gray-950/30">
                                                    <CheckCircleIcon class="h-4 w-4" />
                                                    Marcar todas pagadas
                                                </button>
                                                <button type="button" @click="limpiarPagosIniciales" class="inline-flex items-center justify-center gap-2 rounded-lg border border-current/20 bg-white/50 px-3 py-2 text-[10px] font-black uppercase tracking-widest transition hover:bg-white dark:bg-gray-950/30">
                                                    <TrashIcon class="h-4 w-4" />
                                                    Limpiar pagos
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-if="pagosInicialesErrors.length" class="mt-3 rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm font-semibold text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-200">
                                        {{ pagosInicialesErrors[0] }}
                                    </div>
                                </div>
                            </template>

                            <div v-else class="p-8 text-center">
                                <ScaleIcon class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600" />
                                <h4 class="mt-3 text-base font-black text-gray-950 dark:text-white">Modalidad litis</h4>
                                <p class="mt-1 text-sm font-semibold text-gray-500 dark:text-gray-400">El cargo se genera cuando se registre el resultado del proceso.</p>
                            </div>
                        </section>
                    </main>

                    <aside class="space-y-5 xl:sticky xl:top-6 xl:self-start">
                        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                            <div class="flex items-center gap-3">
                                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-gray-50 text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                    <DocumentTextIcon class="h-5 w-5" />
                                </span>
                                <div>
                                    <h3 class="text-sm font-black uppercase tracking-widest text-gray-950 dark:text-white">Resumen</h3>
                                    <p class="mt-1 text-xs font-semibold text-gray-500 dark:text-gray-400">Antes de guardar</p>
                                </div>
                            </div>

                            <dl class="mt-5 space-y-3">
                                <div class="flex items-center justify-between gap-4 border-b border-gray-100 pb-3 dark:border-gray-800">
                                    <dt class="text-xs font-black uppercase tracking-widest text-gray-400">Cliente</dt>
                                    <dd class="max-w-48 truncate text-right text-sm font-black text-gray-950 dark:text-white">{{ selectedClientName || 'Sin seleccionar' }}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-4 border-b border-gray-100 pb-3 dark:border-gray-800">
                                    <dt class="text-xs font-black uppercase tracking-widest text-gray-400">Modalidad</dt>
                                    <dd class="text-sm font-black text-gray-950 dark:text-white">{{ modalidadMeta[form.modalidad]?.label || form.modalidad }}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-4 border-b border-gray-100 pb-3 dark:border-gray-800">
                                    <dt class="text-xs font-black uppercase tracking-widest text-gray-400">Total</dt>
                                    <dd class="text-sm font-black text-gray-950 dark:text-white">{{ requiresFixedAmount ? fmtMoney(totalContrato) : 'Por resultado' }}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-4 border-b border-gray-100 pb-3 dark:border-gray-800">
                                    <dt class="text-xs font-black uppercase tracking-widest text-gray-400">Anticipo</dt>
                                    <dd class="text-sm font-black text-gray-950 dark:text-white">{{ fmtMoney(anticipoValue) }}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-4 border-b border-gray-100 pb-3 dark:border-gray-800">
                                    <dt class="text-xs font-black uppercase tracking-widest text-gray-400">A financiar</dt>
                                    <dd class="text-sm font-black text-gray-950 dark:text-white">{{ requiresSchedule ? fmtMoney(netoFinanciar) : 'No aplica' }}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-4 border-b border-gray-100 pb-3 dark:border-gray-800">
                                    <dt class="text-xs font-black uppercase tracking-widest text-gray-400">Cuotas</dt>
                                    <dd class="text-sm font-black text-gray-950 dark:text-white">{{ requiresSchedule ? manualCuotas.length : 0 }}</dd>
                                </div>

                                <div class="flex items-center justify-between gap-4 border-b border-gray-100 pb-3 dark:border-gray-800">
                                    <dt class="text-xs font-black uppercase tracking-widest text-gray-400">Pagos iniciales</dt>
                                    <dd class="text-sm font-black text-emerald-700 dark:text-emerald-300">{{ requiresSchedule ? fmtMoney(totalPagosIniciales) : 'No aplica' }}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-4 border-b border-gray-100 pb-3 dark:border-gray-800">
                                    <dt class="text-xs font-black uppercase tracking-widest text-gray-400">Saldo inicial</dt>
                                    <dd class="text-sm font-black text-gray-950 dark:text-white">{{ requiresSchedule ? fmtMoney(saldoInicial) : 'No aplica' }}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <dt class="text-xs font-black uppercase tracking-widest text-gray-400">Rango</dt>
                                    <dd class="text-right text-sm font-black text-gray-950 dark:text-white">{{ requiresSchedule && primeraCuota ? `${fmtDate(primeraCuota)} - ${fmtDate(ultimaCuota)}` : 'Sin cronograma' }}</dd>
                                </div>
                            </dl>

                            <div class="mt-5 rounded-lg border p-3" :class="validationClass">
                                <div class="flex items-start gap-2">
                                    <InformationCircleIcon class="mt-0.5 h-4 w-4 shrink-0" />
                                    <p class="text-xs font-bold leading-5">{{ requiresSchedule ? validationStatus.message : 'Contrato listo para porcentaje de exito.' }}</p>
                                </div>
                            </div>

                            <button
                                type="button"
                                @click="submit"
                                :disabled="!canSubmit"
                                class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-lg border px-4 py-3 text-xs font-black uppercase tracking-widest shadow-sm transition"
                                :class="canSubmit
                                    ? 'border-indigo-600 bg-indigo-600 text-white hover:bg-indigo-700'
                                    : 'cursor-not-allowed border-gray-300 bg-gray-300 text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400'"
                            >
                                <ArrowPathIcon v-if="form.processing" class="h-4 w-4 animate-spin" />
                                <CheckCircleIcon v-else class="h-4 w-4" />
                                {{ form.processing ? 'Guardando...' : 'Guardar contrato' }}
                            </button>
                        </section>
                    </aside>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
