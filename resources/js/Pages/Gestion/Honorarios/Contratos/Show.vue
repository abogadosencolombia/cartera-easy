<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import { computed, ref, onMounted, watch } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import Modal from '@/Components/Modal.vue'
import InputError from '@/Components/InputError.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import SecondaryButton from '@/Components/SecondaryButton.vue'
import InputLabel from '@/Components/InputLabel.vue'
import SelectInput from '@/Components/SelectInput.vue'
import TextInput from '@/Components/TextInput.vue'
import Textarea from '@/Components/Textarea.vue'
import { onClickOutside } from '@vueuse/core'
import {
    ArrowLeftIcon,
    ArrowPathIcon,
    ArrowTopRightOnSquareIcon,
    ArrowUpTrayIcon,
    BanknotesIcon,
    CalendarDaysIcon,
    CheckCircleIcon,
    ClockIcon,
    CreditCardIcon,
    DocumentTextIcon,
    EllipsisVerticalIcon,
    ExclamationTriangleIcon,
    EyeIcon,
    InformationCircleIcon,
    PencilSquareIcon,
    PlusIcon,
    ScaleIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline'
import AppAlert from '@/Utils/appAlert'

// --- PROPS ---
const props = defineProps({
    contrato: { type: Object, default: () => ({}) },
    contratoOrigen: { type: Object, default: null },
    cliente:  { type: Object, default: () => ({}) },
    cuotas:   { type: Object, default: () => ({ data: [] }) },
    pagos:    { type: Object, default: () => ({ data: [] }) },
    cargos:   { type: Object, default: () => ({ data: [] }) },
    actuaciones: { type: Array, default: () => [] },
    total_cargos_valor: { type: Number, default: 0 },
    total_pagos_valor:  { type: Number, default: 0 },
    clientes:   { type: Array,  default: () => [] },
    modalidades:{ type: Array,  default: () => [] },
})

// --- FORMULARIO PARA ACCIONES GENERALES ---
const accionesForm = useForm({})

// Opciones de Frecuencia (Igual que en Create.vue)
const frecuencias = [
    { value: 'DIARIO', label: 'Diario' },
    { value: 'SEMANAL', label: 'Semanal' },
    { value: 'QUINCENAL', label: 'Quincenal (15 días)' },
    { value: 'MENSUAL', label: 'Mensual' },
    { value: 'AL_FINALIZAR', label: 'Al finalizar' },
];

// --- HELPERS ---
const fmtMoney = (n) =>
    new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        maximumFractionDigits: 0
    }).format(Number(n || 0))

// --- fmtDate Corregido ---
const fmtDate = (d) => {
    if (!d) return 'N/A';
    const dateStr = String(d).replace(' ', 'T');
    const dateObj = new Date(dateStr);
    
    if (isNaN(dateObj.getTime())) {
        const dateOnlyMatch = String(d).match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (dateOnlyMatch) {
            const [, year, month, day] = dateOnlyMatch;
            const dateOnlyObj = new Date(Date.UTC(year, month - 1, day));
             if (!isNaN(dateOnlyObj.getTime())) {
                 return dateOnlyObj.toLocaleDateString('es-CO', { year: 'numeric', month: 'long', day: 'numeric', timeZone: 'UTC' });
             }
        }
        return 'Fecha Inválida';
    }
    return dateObj.toLocaleDateString('es-CO', { year: 'numeric', month: 'long', day: 'numeric', timeZone: 'UTC' });
};

const fmtDateTime = (d) =>
    d ? new Date(String(d).replace(' ', 'T')).toLocaleString('es-CO', { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true, timeZone: 'UTC' }) : 'N/A';

const today    = new Date().toISOString().slice(0, 10)

const parseDate = (d) => {
    if (!d) return null
    const dt = new Date(String(d).replace(' ', 'T'))
    return isNaN(dt.getTime()) ? null : dt
}

const diffDays = (from, to) => {
    const a = parseDate(from)
    const b = parseDate(to || new Date().toISOString().slice(0,10))
    if (!a || !b) return null
    const aUTC = Date.UTC(a.getUTCFullYear(), a.getUTCMonth(), a.getUTCDate());
    const bUTC = Date.UTC(b.getUTCFullYear(), b.getUTCMonth(), b.getUTCDate());
    return Math.floor((bUTC - aUTC) / (1000 * 60 * 60 * 24));
}

// =========================
// === LÓGICA / CÁLCULOS ===
// =========================
const nombreCliente = computed(() => props.cliente?.nombre_completo ?? `ID ${props.contrato?.cliente_id}`)

const valorTotalContrato = computed(() => {
    const c = props.contrato || {}
    const modalidad = String(c.modalidad || '')
    if (modalidad === 'LITIS') {
        return Number(c.litis_valor_ganado || 0) 
    }
     if (modalidad === 'CUOTA_MIXTA') {
         return (Number(c.monto_total || 0) + Number(c.litis_valor_ganado || 0))
    }
    return Number(c.monto_total || 0)
})

const netoContrato = computed(() => {
    const anticipo = Number(props.contrato?.anticipo) || 0
    const c = props.contrato || {}
    const modalidad = String(c.modalidad || '')

    if (modalidad === 'LITIS') {
        return 0;
    }
     if (modalidad === 'CUOTA_MIXTA') {
         return Math.max(0, (Number(c.monto_total || 0) - anticipo))
    }
    return Math.max(0, (Number(c.monto_total || 0) - anticipo))
})


const totalCargosValor = computed(() => Math.abs(Number(props.total_cargos_valor) || 0))
const totalPagosValor  = computed(() => Math.abs(Number(props.total_pagos_valor)  || 0))

const totalInteresesMora = computed(() => {
    const moraCuotas = (props.cuotas?.data || []).reduce((sum, c) => sum + Number(c.intereses_mora_acumulados || 0), 0)
    const moraCargos = (props.cargos?.data || [])
        .filter(c => String(c.tipo || '') !== 'INTERES_MORA')
        .reduce((sum, c) => sum + Number(c.intereses_mora_acumulados || 0), 0)
    return moraCuotas + moraCargos
})

const valorTotalCargosConMora = computed(() => totalCargosValor.value + totalInteresesMora.value)

const saldo = computed(() => {
     return Math.max(0, netoContrato.value + valorTotalCargosConMora.value - totalPagosValor.value)
})


const totalCargosPendientes = computed(() => {
    if (!props.cargos?.data) return 0
    return props.cargos.data
        .filter(c => c.estado !== 'PAGADO')
        .reduce((a, c) => {
            const base = Math.abs(Number(c.monto) || 0)
            const mora = String(c.tipo || '') === 'INTERES_MORA' ? 0 : Math.abs(Number(c.intereses_mora_acumulados) || 0)
            return a + base + mora
        }, 0)
})

// =============================
// === URLs para comprobantes ===
// =============================
const comprobanteUrl = (pago) =>
    pago?.comprobante ? route('honorarios.contratos.pagos.verComprobante', { pago_id: pago.id }) : null

const cargoComprobanteCreacionUrl = (cargo) =>
    cargo?.comprobante ? route('honorarios.contratos.cargos.verComprobante', { cargo_id: cargo.id }) : null

const cargoComprobantePagoUrl = (cargo) =>
    cargo?.pago_id_del_cargo && cargo?.comprobante_pago_cargo ? route('honorarios.contratos.pagos.verComprobante', { pago_id: cargo.pago_id_del_cargo }) : null

// ===================================
// === Utils de relación MORAS (UX) ===
// ===================================
const getCuotaById = (id) => (props.cuotas?.data || []).find(x => x.id === id)
const getCargoById = (id) => (props.cargos?.data || []).find(x => x.id === id)

const moraRef = (c) => {
    if (String(c?.tipo || '') !== 'INTERES_MORA') return null
    const cuotaId =
        c?.cuota_id ?? c?.cuotaId ?? c?.rel_cuota_id ?? c?.origen_cuota_id ?? c?.sobre_cuota_id ?? c?.cuota_referencia_id
    const cargoId =
        c?.cargo_id ?? c?.cargoId ?? c?.rel_cargo_id ?? c?.origen_cargo_id ?? c?.sobre_cargo_id ?? c?.cargo_referencia_id
    if (cuotaId) return { tipo: 'CUOTA', cuota: getCuotaById(cuotaId) }
    if (cargoId) return { tipo: 'CARGO', cargo: getCargoById(cargoId) }
    return null
}

const diasVencidoMora = (c) => {
    const ref = moraRef(c)
    const hoy = today
    let base = null
    if (ref?.tipo === 'CUOTA' && ref.cuota?.fecha_vencimiento) base = ref.cuota.fecha_vencimiento
    else if (ref?.tipo === 'CARGO' && ref.cargo?.fecha_aplicado) base = ref.cargo.fecha_aplicado
    else base = c?.fecha_aplicado
    const d = diffDays(base, hoy)
    return d && d > 0 ? d : 0
}

const textoRefMora = (c) => {
    const ref = moraRef(c)
    const dias = diasVencidoMora(c)
    if (ref?.tipo === 'CUOTA') {
        const n = ref.cuota?.numero ?? ref.cuota?.id
        return `Interés de mora sobre cuota #${n}${dias ? ` · ${dias} día${dias===1?'':'s'} vencida` : ''}`
    }
    if (ref?.tipo === 'CARGO') {
        return `Interés de mora sobre cargo aplicado el ${fmtDate(ref.cargo?.fecha_aplicado)}${dias ? ` · ${dias} día${dias===1?'':'s'} vencido` : ''}`
    }
    return `Interés de mora${dias ? ` · ${dias} día${dias===1?'':'s'} vencido` : ''}`
}

// ===================
// === CUOTAS/CARGOS Helpers ===
const cuotaPagado = (c) => (props.pagos?.data || []).filter(p => p.cuota_id === c.id).reduce((a, p) => a + Number(p.valor || 0), 0)
const cuotaTotalAPagar = (c) => (Number(c?.valor || 0) + Number(c?.intereses_mora_acumulados || 0))
const cuotaRestoConMora = (c) => Math.max(0, cuotaTotalAPagar(c) - cuotaPagado(c))
const cargoTotalAPagar = (c) => Number(c?.monto || 0) + (String(c?.tipo || '') === 'INTERES_MORA' ? 0 : Number(c?.intereses_mora_acumulados || 0))

const conceptoPago = (pago) => {
    if (pago?.cuota_id) {
        const q = (props.cuotas?.data || []).find(x => x.id === pago.cuota_id)
        return q ? `Cuota #${q.numero}` : 'Cuota'
    }
    return pago?.cargo_id ? 'Cargo adicional' : 'Abono General'
}

// ===================
// === MODAL CUOTA ===
const pagoModalAbierto    = ref(false)
const cuotaSeleccionada = ref(null)
const pagoCuotaForm = useForm({
    cuota_id: null,
    valor: '',
    fecha: today,
    metodo: 'TRANSFERENCIA',
    nota: '',
    comprobante: null
})

const abrirPagoCuotaModal = (cuota) => {
    cuotaSeleccionada.value = cuota
    pagoCuotaForm.reset()
    pagoCuotaForm.cuota_id = cuota.id
    pagoCuotaForm.valor    = cuotaRestoConMora(cuota) 
    pagoCuotaForm.fecha    = today
    pagoModalAbierto.value = true
}
const cerrarPagoModal = () => { pagoModalAbierto.value = false }
const registrarPagoCuota = () => {
    pagoCuotaForm.post(route('honorarios.contratos.pagar', props.contrato.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: cerrarPagoModal,
    })
}

// ==================
// === MODAL CARGO ===
const pagoCargoModalAbierto = ref(false)
const cargoSeleccionado     = ref(null)
const pagoCargoForm = useForm({
    cargo_id: null,
    valor: '',
    fecha: today,
    metodo: 'TRANSFERENCIA',
    nota: '',
    comprobante: null
})

const abrirPagoCargoModal = (cargo) => {
    cargoSeleccionado.value = cargo
    pagoCargoForm.reset()
    pagoCargoForm.cargo_id = cargo.id
    pagoCargoForm.valor    = cargoTotalAPagar(cargo) 
    pagoCargoForm.fecha    = today
    pagoCargoModalAbierto.value = true
}
const cerrarPagoCargoModal = () => { pagoCargoModalAbierto.value = false }
const registrarPagoCargo = () => {
    pagoCargoForm.post(route('honorarios.contratos.cargos.pagar', { contrato_id: props.contrato.id }), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: cerrarPagoCargoModal
    })
}

// ==================
// === MODAL GASTO ===
const gastoModalAbierto = ref(false)
const gastoForm = useForm({ monto: '', descripcion: '', fecha: today, comprobante: null, fecha_inicio_intereses: '' })
const abrirGastoModal  = () => { gastoForm.reset(); gastoForm.fecha = today; gastoModalAbierto.value = true }
const cerrarGastoModal = () => { gastoModalAbierto.value = false }
const registrarGasto   = () => {
    gastoForm.post(route('honorarios.contratos.cargos.store', props.contrato.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: cerrarGastoModal
    })
}

// ========================
// === MODAL CIERRE MAN ===
const cierreModalAbierto = ref(false)
const cierreForm = useForm({ monto: '', descripcion: '', fecha_inicio_intereses: '' })
const abrirCierreModal  = () => { cierreForm.reset(); cierreModalAbierto.value = true }
const cerrarCierreModal = () => { cierreModalAbierto.value = false }
const confirmarCierre   = () => {
    cierreForm.post(route('honorarios.contratos.cerrar', props.contrato.id), {
        preserveScroll: true,
        onSuccess: cerrarCierreModal
    })
}

// ===================================
// === MODAL: RESOLVER LITIS ===
const resolverLitisModalAbierto = ref(false)
const resolverLitisForm = useForm({ monto_base_litis: '', fecha_inicio_intereses: '' })

const abrirResolverLitisModal  = () => { resolverLitisForm.reset(); resolverLitisModalAbierto.value = true }
const cerrarResolverLitisModal = () => { resolverLitisModalAbierto.value = false }
const confirmarResolucionLitis = () => {
    resolverLitisForm.post(route('honorarios.contratos.resolverLitis', props.contrato.id), {
        preserveScroll: true,
        onSuccess: cerrarResolverLitisModal
    })
}

// ===================
// === DOCUMENTO MANAGEMENT ===
// ===================
const subirDocumentoModalAbierto = ref(false)
const documentoMenuAbierto = ref(false)
const documentoForm = useForm({
    documento: null
})

const abrirSubirDocumentoModal = () => {
    documentoForm.reset()
    subirDocumentoModalAbierto.value = true
}

const cerrarSubirDocumentoModal = () => {
    subirDocumentoModalAbierto.value = false
}

const subirDocumento = () => {
    documentoForm.post(route('honorarios.contratos.documento.subir', props.contrato.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            cerrarSubirDocumentoModal()
            documentoMenuAbierto.value = false 
        }
    })
}

const toggleDocumentoMenu = () => {
    documentoMenuAbierto.value = !documentoMenuAbierto.value
}

// --- REEMPLAZADO alert/confirm ---
const confirmandoEliminarDoc = ref(false)
const cerrarConfirmarEliminarDoc = () => { confirmandoEliminarDoc.value = false }
const abrirConfirmarEliminarDoc = () => {
    documentoMenuAbierto.value = false
    confirmandoEliminarDoc.value = true
}
const eliminarDocumento = () => {
    accionesForm.delete(route('honorarios.contratos.documento.eliminar', props.contrato.id), {
        preserveScroll: true,
        onSuccess: () => {
            cerrarConfirmarEliminarDoc()
        }
    })
}
// --- FIN REEMPLAZO ---

// Cerrar menú cuando se hace clic fuera
const documentoMenuRef = ref(null)
onClickOutside(documentoMenuRef, () => documentoMenuAbierto.value = false)
const handleCerrarContratoClick = () => {
    const c = props.contrato
    // Validar si es Litis/Mixta Y AÚN NO se ha resuelto
    if (['LITIS', 'CUOTA_MIXTA'].includes(c?.modalidad) && !Number(c?.monto_base_litis) && !Number(c?.litis_valor_ganado)) {
        abrirResolverLitisModal()
    } else {
        abrirCierreModal()
    }
}

// --- LÓGICA PARA MODAL DE ELIMINACIÓN ---
const confirmandoEliminacion = ref(false)
const deleteForm = useForm({})
const abrirModalConfirmarEliminacion = () => { confirmandoEliminacion.value = true }
const cerrarModalConfirmarEliminacion = () => { confirmandoEliminacion.value = false }
const eliminarContrato = () => {
    deleteForm.delete(route('honorarios.contratos.destroy', props.contrato.id), {
        onError: () => {
            cerrarModalConfirmarEliminacion()
        }
    })
}

// --- LÓGICA PARA FORMULARIO DE ACTUACIONES ---
const actuacionForm = useForm({
    nota: '',
    fecha_actuacion: today // <-- Añadido campo de fecha
})

const guardarActuacion = () => {
    actuacionForm.post(route('honorarios.contratos.actuaciones.store', props.contrato.id), {
        preserveScroll: true,
        onSuccess: () => {
            actuacionForm.reset() // Limpiar formulario
            actuacionForm.fecha_actuacion = today // Resetear fecha a hoy
        },
        onError: () => {
             AppAlert.fire('No se pudo guardar', 'Revisa la actuación e inténtalo de nuevo.', 'error')
        }
    })
}

// --- Lógica para Editar/Eliminar Actuación ---
const editActuacionModalAbierto = ref(false)
const actuacionEnEdicion = ref(null)
const confirmandoEliminarActuacion = ref(false)
const actuacionAEliminar = ref(null)

const editActuacionForm = useForm({
    nota: '',
    fecha_actuacion: '',
})

const abrirModalEditar = (actuacion) => {
    actuacionEnEdicion.value = actuacion
    editActuacionForm.nota = actuacion.nota
    editActuacionForm.fecha_actuacion = actuacion.fecha_actuacion ? String(actuacion.fecha_actuacion).split('T')[0] : ''
    editActuacionModalAbierto.value = true
}

const cerrarModalEditar = () => {
    editActuacionModalAbierto.value = false
    actuacionEnEdicion.value = null
    editActuacionForm.reset()
}

const actualizarActuacion = () => {
    if (!actuacionEnEdicion.value) return;

    editActuacionForm.put(route('honorarios.contratos.actuaciones.update', actuacionEnEdicion.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            cerrarModalEditar()
        },
        onError: () => {
             AppAlert.fire('No se pudo actualizar', 'Revisa la actuación e inténtalo de nuevo.', 'error')
        }
    })
}

// --- REEMPLAZO DE confirm() ---
const abrirConfirmarEliminarActuacion = (actuacion) => {
    actuacionAEliminar.value = actuacion
    confirmandoEliminarActuacion.value = true
}
const cerrarConfirmarEliminarActuacion = () => {
    actuacionAEliminar.value = null
    confirmandoEliminarActuacion.value = false
}
const eliminarActuacionConfirmado = () => {
    if (!actuacionAEliminar.value) return;
    
    router.delete(route('honorarios.contratos.actuaciones.destroy', actuacionAEliminar.value.id), {
        preserveScroll: true,
        onSuccess: () => {
             cerrarConfirmarEliminarActuacion()
        },
        onError: () => {
            AppAlert.fire('No se pudo eliminar', 'La actuación no fue eliminada.', 'error')
            cerrarConfirmarEliminarActuacion()
        }
    })
}
// --- FIN REEMPLAZO ---


// --- UI / TABS ---
const contractStatusClasses = {
    'ACTIVO':           'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300',
    'PAGOS_PENDIENTES': 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300',
    'PAGO_PARCIAL':     'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300',
    'CERRADO':          'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    'EN_MORA':          'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300',
    'REESTRUCTURADO':   'bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-300',
}

// --- Lógica de Pestañas (Tabs) ---
const pestanaActiva = ref('cuotas') 
const cambiarPestana = (nuevaPestana) => {
    pestanaActiva.value = nuevaPestana;
    const url = new URL(window.location);
    url.searchParams.set('tab', nuevaPestana);
    window.history.replaceState({}, '', url);
}

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    const tabParam = params.get('tab');
    const validTabs = ['cuotas', 'cargos', 'pagos', 'actuaciones'];
    
    if (tabParam && validTabs.includes(tabParam)) {
        pestanaActiva.value = tabParam;
    } else {
        if (props.actuaciones.length > 0 && props.cuotas.data.length === 0 && props.cargos.data.length === 0) {
            pestanaActiva.value = 'actuaciones';
        } else {
            pestanaActiva.value = 'cuotas';
        }
    }
})

// --- Lógica de Paginación ---
const withTab = (url) => { if (!url) return null; try { const u = new URL(url, window.location.origin); u.searchParams.set('tab', pestanaActiva.value); return u.toString() } catch { return url } }
const cuotasLinks = computed(() => (props.cuotas?.links || []).map(l => ({ ...l, url: withTab(l.url) })))
const cargosLinks = computed(() => (props.cargos?.links || []).map(l => ({ ...l, url: withTab(l.url) })))
const pagosLinks  = computed(() => (props.pagos?.links  || []).map(l => ({ ...l, url: withTab(l.url) })))

const navegarPagina = (url) => {
    if (!url) return
    const nuevaUrl = new URL(url, window.location.origin)
    
    router.get(nuevaUrl.toString(), {}, {
        preserveScroll: true,
        preserveState: true, 
        only: ['cuotas', 'pagos', 'cargos'] 
    })
}

// --- MODAL REESTRUCTURACIÓN ---
const reestructurarModalAbierto = ref(false)
const crearContratoForm = useForm({ 
    cliente_id: null, 
    modalidad: 'CUOTAS', 
    frecuencia_pago: 'MENSUAL', // NUEVO CAMPO
    inicio: today, 
    monto_total: '', 
    cuotas: 12, 
    anticipo: '', 
    porcentaje_litis: '', 
    nota: '', 
    contrato_origen_id: null 
})

// --- CORRECCIÓN DE BUG: ---
const clienteSearch = ref(''); 
const selectedClientName = ref(''); 
const isClientListOpen = ref(false); 
const clientDropdown = ref(null)

onClickOutside(clientDropdown, () => isClientListOpen.value = false)
const abrirReestructurarModal = () => {
    crearContratoForm.defaults({ 
        cliente_id: props.contrato?.cliente_id ?? null, 
        modalidad: props.contrato?.modalidad ?? 'CUOTAS', 
        frecuencia_pago: props.contrato?.frecuencia_pago ?? 'MENSUAL', // Cargar frecuencia actual
        inicio: today, 
        monto_total: props.contrato?.monto_total ?? '', 
        cuotas: 12, 
        anticipo: props.contrato?.anticipo ?? '', 
        porcentaje_litis: props.contrato?.porcentaje_litis ?? '', 
        nota: `Reestructuración del contrato #${props.contrato?.id ?? ''}. Saldo anterior (aprox): ${fmtMoney(saldo.value)}`, 
        contrato_origen_id: props.contrato?.id ?? null 
    })
    crearContratoForm.reset()
    
    const clienteOriginal = props.clientes.find(c => c.id === props.contrato.cliente_id);
    if (clienteOriginal) {
        clienteSearch.value = clienteOriginal.nombre; 
        selectedClientName.value = clienteOriginal.nombre;
    } else {
        clienteSearch.value = props.cliente?.nombre_completo ?? '';
        selectedClientName.value = props.cliente?.nombre_completo ?? '';
    }
    reestructurarModalAbierto.value = true
}
watch(() => crearContratoForm.modalidad, (newModalidad) => { if (newModalidad === 'PAGO_UNICO') { crearContratoForm.cuotas = 1; } else if (newModalidad === 'CUOTAS' || newModalidad === 'CUOTA_MIXTA') { crearContratoForm.cuotas = 12; } });
const cerrarReestructurarModal = () => { reestructurarModalAbierto.value = false }
const guardarNuevoContrato = () => { crearContratoForm.post(route('honorarios.contratos.store'), { preserveScroll: true, onSuccess: cerrarReestructurarModal }) }

const estadosActivos = ['ACTIVO', 'PAGOS_PENDIENTES', 'EN_MORA', 'PAGO_PARCIAL']
const contratoAbierto = computed(() => estadosActivos.includes(props.contrato?.estado))
const puedeRegistrarGasto = computed(() => estadosActivos.includes(props.contrato?.estado))
const esLitisSinResolver = computed(() => ['LITIS', 'CUOTA_MIXTA'].includes(props.contrato?.modalidad) && !Number(props.contrato?.monto_base_litis) && !Number(props.contrato?.litis_valor_ganado))

const estadoLabel = (estado) => ({
    ACTIVO: 'Activo',
    PAGOS_PENDIENTES: 'Pagos pendientes',
    PAGO_PARCIAL: 'Pago parcial',
    CERRADO: 'Cerrado',
    EN_MORA: 'En mora',
    REESTRUCTURADO: 'Reestructurado',
}[String(estado || '').toUpperCase()] || 'Sin estado')

const estadoPillClass = (estado) => {
    const value = String(estado || '').toUpperCase()
    if (value === 'EN_MORA') return 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-300'
    if (value === 'CERRADO') return 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300'
    if (value === 'PAGOS_PENDIENTES') return 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200'
    if (value === 'PAGO_PARCIAL') return 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/60 dark:bg-sky-950/30 dark:text-sky-300'
    if (value === 'REESTRUCTURADO') return 'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-900/60 dark:bg-violet-950/30 dark:text-violet-300'
    return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-300'
}

const modalidadLabel = (modalidad) => ({
    CUOTAS: 'Cuotas',
    PAGO_UNICO: 'Pago unico',
    LITIS: 'Litis',
    CUOTA_MIXTA: 'Cuota mixta',
}[String(modalidad || '').toUpperCase()] || 'Sin modalidad')

const frecuenciaLabel = (frecuencia) => ({
    DIARIO: 'Diario',
    SEMANAL: 'Semanal',
    QUINCENAL: 'Quincenal',
    MENSUAL: 'Mensual',
    AL_FINALIZAR: 'Al finalizar',
}[String(frecuencia || '').toUpperCase()] || 'Mensual')

const cuotasAbiertas = computed(() => (props.cuotas?.data || []).filter((cuota) => cuota.estado !== 'PAGADA' && !cuota.fecha_pago))
const cuotasVencidas = computed(() => cuotasAbiertas.value.filter((cuota) => (diffDays(cuota.fecha_vencimiento, today) || 0) > 0))
const cargosPendientes = computed(() => (props.cargos?.data || []).filter((cargo) => cargo.estado !== 'PAGADO'))
const pagosCount = computed(() => props.pagos?.data?.length || 0)
const actuacionesCount = computed(() => props.actuaciones?.length || 0)

const baseDeCobro = computed(() => Math.max(0, netoContrato.value + valorTotalCargosConMora.value))
const porcentajePagado = computed(() => {
    if (baseDeCobro.value <= 0) return totalPagosValor.value > 0 ? 100 : 0
    return Math.max(0, Math.min(100, (totalPagosValor.value / baseDeCobro.value) * 100))
})

const proximaCuota = computed(() => {
    return [...cuotasAbiertas.value]
        .filter((cuota) => cuota.fecha_vencimiento)
        .sort((a, b) => new Date(a.fecha_vencimiento) - new Date(b.fecha_vencimiento))[0] || null
})

const estadoCartera = computed(() => {
    if (props.contrato?.estado === 'CERRADO') return 'Contrato cerrado'
    if (cuotasVencidas.value.length > 0 || props.contrato?.estado === 'EN_MORA') return `${cuotasVencidas.value.length || 1} alerta(s) de mora`
    if (saldo.value <= 0 && baseDeCobro.value > 0) return 'Sin saldo pendiente'
    if (proximaCuota.value) return `Proxima cuota: ${fmtDate(proximaCuota.value.fecha_vencimiento)}`
    return 'Sin vencimientos abiertos'
})

const progresoClass = computed(() => {
    if (props.contrato?.estado === 'EN_MORA' || cuotasVencidas.value.length > 0) return 'bg-rose-500'
    if (saldo.value <= 0 && baseDeCobro.value > 0) return 'bg-emerald-500'
    return 'bg-indigo-500'
})

const tabItems = computed(() => [
    { id: 'cuotas', label: 'Cuotas', count: props.cuotas?.data?.length || 0, icon: CalendarDaysIcon, show: props.contrato?.modalidad !== 'LITIS' || (props.cuotas?.data?.length || 0) > 0 },
    { id: 'cargos', label: 'Cargos', count: props.cargos?.data?.length || 0, icon: BanknotesIcon, show: true },
    { id: 'pagos', label: 'Pagos', count: pagosCount.value, icon: CreditCardIcon, show: true },
    { id: 'actuaciones', label: 'Actuaciones', count: actuacionesCount.value, icon: DocumentTextIcon, show: true },
].filter((tab) => tab.show))

watch(tabItems, (tabs) => {
    if (!tabs.some((tab) => tab.id === pestanaActiva.value)) {
        pestanaActiva.value = tabs[0]?.id || 'cargos'
    }
}, { immediate: true })

const cargoIcon = (cargo) => {
    const tipo = String(cargo?.tipo || '')
    if (tipo === 'INTERES_MORA') return ClockIcon
    if (tipo === 'LITIS') return ScaleIcon
    return BanknotesIcon
}

</script>

<template>
    <Head :title="`Honorarios · Contrato #${props.contrato?.id}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div class="flex min-w-0 items-center gap-3">
                    <Link
                        :href="route('honorarios.contratos.index')"
                        class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 shadow-sm transition hover:text-indigo-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                    >
                        <ArrowLeftIcon class="h-5 w-5" />
                    </Link>
                    <div class="min-w-0">
                        <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Contrato de honorarios</p>
                        <div class="mt-1 flex flex-wrap items-center gap-2">
                            <h2 class="text-2xl font-black tracking-tight text-gray-950 dark:text-white">Contrato #{{ props.contrato?.id }}</h2>
                            <span class="inline-flex items-center rounded-lg border px-2.5 py-1 text-[10px] font-black uppercase tracking-widest" :class="estadoPillClass(props.contrato?.estado)">
                                {{ estadoLabel(props.contrato?.estado) }}
                            </span>
                        </div>
                        <p class="mt-1 truncate text-sm font-medium text-gray-500 dark:text-gray-400">{{ nombreCliente }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        v-if="puedeRegistrarGasto"
                        type="button"
                        @click="abrirGastoModal"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs font-black uppercase tracking-widest text-sky-700 shadow-sm transition hover:bg-sky-100 dark:border-sky-900/60 dark:bg-sky-950/30 dark:text-sky-300"
                    >
                        <PlusIcon class="h-4 w-4" />
                        Gasto
                    </button>
                    <button
                        v-if="props.contrato?.estado === 'ACTIVO'"
                        type="button"
                        @click="handleCerrarContratoClick"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-800 bg-gray-800 px-3 py-2 text-xs font-black uppercase tracking-widest text-white shadow-sm transition hover:bg-gray-900 dark:border-gray-200 dark:bg-gray-100 dark:text-gray-950 dark:hover:bg-white"
                    >
                        <CheckCircleIcon class="h-4 w-4" />
                        {{ esLitisSinResolver ? 'Registrar resultado' : 'Cerrar' }}
                    </button>
                    <button
                        v-if="['PAGOS_PENDIENTES', 'PAGO_PARCIAL'].includes(props.contrato?.estado) && saldo <= 0"
                        type="button"
                        @click="accionesForm.post(route('honorarios.contratos.saldar', props.contrato.id), { preserveScroll: true })"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-emerald-600 bg-emerald-600 px-3 py-2 text-xs font-black uppercase tracking-widest text-white shadow-sm transition hover:bg-emerald-700"
                    >
                        <CheckCircleIcon class="h-4 w-4" />
                        Saldar
                    </button>
                    <button
                        v-if="props.contrato?.estado === 'CERRADO'"
                        type="button"
                        @click="accionesForm.post(route('honorarios.contratos.reabrir', props.contrato.id), { preserveScroll: true })"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-indigo-600 bg-indigo-600 px-3 py-2 text-xs font-black uppercase tracking-widest text-white shadow-sm transition hover:bg-indigo-700"
                    >
                        <ArrowPathIcon class="h-4 w-4" />
                        Reabrir
                    </button>
                    <button
                        type="button"
                        @click="abrirReestructurarModal"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-black uppercase tracking-widest text-gray-700 shadow-sm transition hover:border-indigo-200 hover:text-indigo-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                    >
                        <ArrowPathIcon class="h-4 w-4" />
                        Reestructurar
                    </button>
                    <a
                        :href="route('honorarios.contratos.pdf.liquidacion', props.contrato.id)"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-black uppercase tracking-widest text-gray-700 shadow-sm transition hover:border-indigo-200 hover:text-indigo-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                    >
                        <DocumentTextIcon class="h-4 w-4" />
                        Liquidacion
                    </a>
                </div>
            </div>
        </template>

        <div class="min-h-screen bg-gray-50/70 py-6 dark:bg-gray-950/40">
            <div class="mx-auto max-w-[1600px] space-y-5 px-4 sm:px-6 lg:px-8">
                <section v-if="props.contratoOrigen" class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-900 shadow-sm dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200">
                    <div class="flex items-start gap-3">
                        <InformationCircleIcon class="mt-0.5 h-5 w-5 shrink-0" />
                        <div>
                            <p class="text-sm font-black">Contrato reestructurado</p>
                            <p class="mt-1 text-sm font-semibold">
                                Viene del
                                <Link :href="route('honorarios.contratos.show', props.contratoOrigen.id)" class="underline hover:text-amber-700 dark:hover:text-amber-100">
                                    contrato #{{ props.contratoOrigen.id }}
                                </Link>, con estado {{ props.contratoOrigen.estado }} y monto {{ fmtMoney(props.contratoOrigen.monto_total) }}.
                            </p>
                            <p v-if="props.contrato.nota" class="mt-1 text-sm italic">{{ props.contrato.nota }}</p>
                        </div>
                    </div>
                </section>

                <section class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <article class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Neto cuotas</p>
                                <p class="mt-2 text-2xl font-black text-gray-950 dark:text-white">{{ fmtMoney(netoContrato) }}</p>
                                <p class="mt-1 text-xs font-semibold text-gray-500 dark:text-gray-400">Base fija despues de anticipo</p>
                            </div>
                            <BanknotesIcon class="h-7 w-7 text-emerald-600 dark:text-emerald-300" />
                        </div>
                    </article>
                    <article class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Cargos y mora</p>
                                <p class="mt-2 text-2xl font-black text-gray-950 dark:text-white">{{ fmtMoney(valorTotalCargosConMora) }}</p>
                                <p class="mt-1 text-xs font-semibold text-gray-500 dark:text-gray-400">Mora: {{ fmtMoney(totalInteresesMora) }}</p>
                            </div>
                            <ScaleIcon class="h-7 w-7 text-sky-600 dark:text-sky-300" />
                        </div>
                    </article>
                    <article class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Pagado</p>
                                <p class="mt-2 text-2xl font-black text-emerald-700 dark:text-emerald-300">{{ fmtMoney(totalPagosValor) }}</p>
                                <p class="mt-1 text-xs font-semibold text-gray-500 dark:text-gray-400">{{ Math.round(porcentajePagado) }}% del total cobrado</p>
                            </div>
                            <CreditCardIcon class="h-7 w-7 text-indigo-600 dark:text-indigo-300" />
                        </div>
                    </article>
                    <article class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Saldo pendiente</p>
                                <p class="mt-2 text-2xl font-black" :class="saldo > 0 ? 'text-rose-700 dark:text-rose-300' : 'text-emerald-700 dark:text-emerald-300'">{{ fmtMoney(saldo) }}</p>
                                <p class="mt-1 text-xs font-semibold text-gray-500 dark:text-gray-400">{{ estadoCartera }}</p>
                            </div>
                            <ExclamationTriangleIcon v-if="saldo > 0" class="h-7 w-7 text-rose-600 dark:text-rose-300" />
                            <CheckCircleIcon v-else class="h-7 w-7 text-emerald-600 dark:text-emerald-300" />
                        </div>
                    </article>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-3 text-xs font-black uppercase tracking-widest text-gray-400">
                                <span>Avance de recaudo</span>
                                <span>{{ Math.round(porcentajePagado) }}%</span>
                            </div>
                            <div class="mt-2 h-3 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800">
                                <div class="h-full rounded-full transition-all" :class="progresoClass" :style="{ width: `${porcentajePagado}%` }"></div>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-2 text-center text-xs font-bold text-gray-500 dark:text-gray-400 lg:min-w-[28rem]">
                            <div class="rounded-lg bg-gray-50 p-2 dark:bg-gray-800/60">
                                <p class="text-[10px] uppercase tracking-widest">Cuotas abiertas</p>
                                <p class="mt-1 text-base font-black text-gray-950 dark:text-white">{{ cuotasAbiertas.length }}</p>
                            </div>
                            <div class="rounded-lg bg-rose-50 p-2 text-rose-700 dark:bg-rose-950/30 dark:text-rose-300">
                                <p class="text-[10px] uppercase tracking-widest">Vencidas</p>
                                <p class="mt-1 text-base font-black">{{ cuotasVencidas.length }}</p>
                            </div>
                            <div class="rounded-lg bg-amber-50 p-2 text-amber-700 dark:bg-amber-950/30 dark:text-amber-300">
                                <p class="text-[10px] uppercase tracking-widest">Cargos</p>
                                <p class="mt-1 text-base font-black">{{ cargosPendientes.length }}</p>
                            </div>
                        </div>
                    </div>
                </section>

                <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1fr)_24rem]">
                    <main class="space-y-5">
                        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                            <div class="border-b border-gray-200 px-4 pt-4 dark:border-gray-700 sm:px-5">
                                <nav class="flex gap-2 overflow-x-auto" aria-label="Secciones del contrato">
                                    <button
                                        v-for="tab in tabItems"
                                        :key="tab.id"
                                        type="button"
                                        @click="cambiarPestana(tab.id)"
                                        class="inline-flex items-center gap-2 whitespace-nowrap rounded-t-lg border-b-2 px-3 py-3 text-xs font-black uppercase tracking-widest transition"
                                        :class="pestanaActiva === tab.id
                                            ? 'border-indigo-600 text-indigo-700 dark:border-indigo-400 dark:text-indigo-300'
                                            : 'border-transparent text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200'"
                                    >
                                        <component :is="tab.icon" class="h-4 w-4" />
                                        {{ tab.label }}
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ tab.count }}</span>
                                    </button>
                                </nav>
                            </div>

                            <div class="p-4 sm:p-5">
                                <div v-if="pestanaActiva === 'cuotas'" class="space-y-3">
                                    <div v-if="!(props.cuotas?.data || []).length" class="rounded-lg border border-dashed border-gray-300 py-12 text-center dark:border-gray-700">
                                        <CalendarDaysIcon class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" />
                                        <p class="mt-3 text-sm font-black text-gray-950 dark:text-white">No hay cuotas definidas</p>
                                        <p class="mt-1 text-sm font-medium text-gray-500 dark:text-gray-400">Este contrato no tiene plan de pagos fijo en esta vista.</p>
                                    </div>

                                    <article
                                        v-for="q in props.cuotas.data"
                                        :key="q.id"
                                        class="rounded-lg border p-4 transition"
                                        :class="q.estado === 'PAGADA'
                                            ? 'border-emerald-200 bg-emerald-50/50 dark:border-emerald-900/60 dark:bg-emerald-950/20'
                                            : q.estado === 'PAGO_PARCIAL'
                                                ? 'border-sky-200 bg-sky-50/50 dark:border-sky-900/60 dark:bg-sky-950/20'
                                                : 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-950/40'"
                                    >
                                        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(22rem,0.9fr)_auto] lg:items-center">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <p class="text-base font-black text-gray-950 dark:text-white">Cuota #{{ q.numero }}</p>
                                                    <span class="rounded-lg border px-2 py-1 text-[10px] font-black uppercase tracking-widest" :class="q.estado === 'PAGADA' || q.fecha_pago ? estadoPillClass('ACTIVO') : q.estado === 'PAGO_PARCIAL' ? estadoPillClass('PAGO_PARCIAL') : estadoPillClass('PAGOS_PENDIENTES')">
                                                        {{ (q.estado === 'PAGADA' || q.fecha_pago) ? 'Pagada' : (q.estado || 'Pendiente').replace('_', ' ') }}
                                                    </span>
                                                </div>
                                                <p class="mt-2 text-sm font-semibold text-gray-500 dark:text-gray-400">Vence: {{ fmtDate(q.fecha_vencimiento) }}</p>
                                            </div>

                                            <div class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                                                <div>
                                                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Valor</p>
                                                    <p class="mt-1 font-black text-gray-950 dark:text-white">{{ fmtMoney(q.valor) }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Mora</p>
                                                    <p class="mt-1 font-black text-rose-700 dark:text-rose-300">{{ fmtMoney(q.intereses_mora_acumulados) }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Pagado</p>
                                                    <p class="mt-1 font-black text-emerald-700 dark:text-emerald-300">{{ fmtMoney(cuotaPagado(q)) }}</p>
                                                </div>
                                                <div>
                                                    <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Pendiente</p>
                                                    <p class="mt-1 font-black text-indigo-700 dark:text-indigo-300">{{ fmtMoney(cuotaRestoConMora(q)) }}</p>
                                                </div>
                                            </div>

                                            <button
                                                v-if="props.contrato.estado !== 'CERRADO' && q.estado !== 'PAGADA' && !q.fecha_pago"
                                                type="button"
                                                @click="abrirPagoCuotaModal(q)"
                                                class="inline-flex items-center justify-center gap-2 rounded-lg border border-indigo-600 bg-indigo-600 px-4 py-2 text-xs font-black uppercase tracking-widest text-white shadow-sm transition hover:bg-indigo-700"
                                            >
                                                <CreditCardIcon class="h-4 w-4" />
                                                Pagar
                                            </button>
                                        </div>
                                    </article>
                                    <Pagination v-if="cuotasLinks.length > 3" class="mt-5" :links="cuotasLinks" @navigate="navegarPagina" />
                                </div>

                                <div v-if="pestanaActiva === 'cargos'" class="space-y-3">
                                    <div v-if="!(props.cargos?.data || []).length" class="rounded-lg border border-dashed border-gray-300 py-12 text-center dark:border-gray-700">
                                        <BanknotesIcon class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" />
                                        <p class="mt-3 text-sm font-black text-gray-950 dark:text-white">Sin cargos adicionales</p>
                                        <p class="mt-1 text-sm font-medium text-gray-500 dark:text-gray-400">Puedes registrar gastos desde la accion superior.</p>
                                    </div>

                                    <article
                                        v-for="c in props.cargos.data"
                                        :key="c.id"
                                        class="overflow-hidden rounded-lg border"
                                        :class="c.estado === 'PAGADO'
                                            ? 'border-emerald-200 bg-emerald-50/50 dark:border-emerald-900/60 dark:bg-emerald-950/20'
                                            : String(c.tipo || '') === 'INTERES_MORA'
                                                ? 'border-rose-200 bg-rose-50/50 dark:border-rose-900/60 dark:bg-rose-950/20'
                                                : 'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-950/40'"
                                    >
                                        <div class="grid grid-cols-1 gap-4 p-4 lg:grid-cols-[auto_minmax(0,1fr)_auto] lg:items-center">
                                            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-white text-gray-700 shadow-sm dark:bg-gray-900 dark:text-gray-300">
                                                <component :is="cargoIcon(c)" class="h-5 w-5" />
                                            </span>
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <p class="text-base font-black text-gray-950 dark:text-white">
                                                        <template v-if="String(c.tipo || '') !== 'INTERES_MORA' && Number(c.intereses_mora_acumulados || 0) > 0">
                                                            {{ fmtMoney(cargoTotalAPagar(c)) }}
                                                        </template>
                                                        <template v-else>{{ fmtMoney(c.monto) }}</template>
                                                    </p>
                                                    <span class="rounded-lg border px-2 py-1 text-[10px] font-black uppercase tracking-widest" :class="String(c.tipo || '') === 'INTERES_MORA' ? estadoPillClass('EN_MORA') : String(c.tipo || '') === 'LITIS' ? 'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-900/60 dark:bg-violet-950/30 dark:text-violet-300' : estadoPillClass('PAGO_PARCIAL')">
                                                        {{ String(c.tipo || 'Cargo').replace('_', ' ') }}
                                                    </span>
                                                    <span class="text-xs font-bold text-gray-500 dark:text-gray-400">{{ c.estado }}</span>
                                                </div>
                                                <p class="mt-1 text-sm font-semibold text-gray-600 dark:text-gray-300">{{ c.descripcion || 'Sin descripcion' }}</p>
                                                <p class="mt-1 text-xs font-semibold text-gray-500 dark:text-gray-400">Aplicado: {{ fmtDate(c.fecha_aplicado) }}</p>
                                                <p v-if="String(c.tipo || '') === 'INTERES_MORA'" class="mt-1 text-xs font-bold text-rose-700 dark:text-rose-300">{{ textoRefMora(c) }}</p>
                                                <p v-if="String(c.tipo || '') !== 'INTERES_MORA' && Number(c.intereses_mora_acumulados || 0) > 0" class="mt-1 text-xs font-bold text-rose-700 dark:text-rose-300">
                                                    Original {{ fmtMoney(c.monto) }} + mora {{ fmtMoney(c.intereses_mora_acumulados) }}
                                                </p>
                                                <a v-if="c.comprobante" :href="cargoComprobanteCreacionUrl(c)" target="_blank" class="mt-2 inline-flex text-xs font-black text-indigo-700 hover:underline dark:text-indigo-300">Ver comprobante de creacion</a>
                                            </div>
                                            <button
                                                v-if="c.estado !== 'PAGADO' && props.contrato.estado !== 'CERRADO'"
                                                type="button"
                                                @click="abrirPagoCargoModal(c)"
                                                class="inline-flex items-center justify-center gap-2 rounded-lg border border-indigo-600 bg-indigo-600 px-4 py-2 text-xs font-black uppercase tracking-widest text-white shadow-sm transition hover:bg-indigo-700"
                                            >
                                                <CreditCardIcon class="h-4 w-4" />
                                                Pagar
                                            </button>
                                        </div>
                                        <div v-if="c.estado === 'PAGADO'" class="border-t border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-200">
                                            Pagado el {{ fmtDate(c.fecha_pago_cargo) }} via {{ c.metodo_pago_cargo || 'metodo no registrado' }}.
                                            <a v-if="c.comprobante_pago_cargo" :href="cargoComprobantePagoUrl(c)" target="_blank" class="ml-2 font-black underline">Ver comprobante</a>
                                        </div>
                                    </article>
                                    <Pagination v-if="cargosLinks.length > 3" class="mt-5" :links="cargosLinks" @navigate="navegarPagina" />
                                </div>

                                <div v-if="pestanaActiva === 'pagos'" class="space-y-3">
                                    <div v-if="!(props.pagos?.data || []).length" class="rounded-lg border border-dashed border-gray-300 py-12 text-center dark:border-gray-700">
                                        <CreditCardIcon class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" />
                                        <p class="mt-3 text-sm font-black text-gray-950 dark:text-white">No hay pagos registrados</p>
                                        <p class="mt-1 text-sm font-medium text-gray-500 dark:text-gray-400">Los pagos apareceran aqui cuando se registren.</p>
                                    </div>

                                    <article v-for="p in [...(props.pagos.data || [])].sort((a,b)=> new Date(b.fecha) - new Date(a.fecha))" :key="p.id" class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-950/40">
                                        <div class="grid grid-cols-1 gap-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-center">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <p class="text-base font-black text-gray-950 dark:text-white">{{ fmtMoney(p.valor) }}</p>
                                                    <span class="rounded-lg border border-gray-200 bg-gray-50 px-2 py-1 text-[10px] font-black uppercase tracking-widest text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                                        {{ conceptoPago(p) }}
                                                    </span>
                                                </div>
                                                <p class="mt-1 text-sm font-semibold text-gray-500 dark:text-gray-400">{{ fmtDate(p.fecha) }} · {{ p.metodo ? p.metodo.replace('_',' ') : 'Sin metodo' }}</p>
                                                <p class="mt-1 break-words text-sm text-gray-600 dark:text-gray-300">{{ p.nota || 'Sin nota' }}</p>
                                            </div>
                                            <a v-if="comprobanteUrl(p)" :href="comprobanteUrl(p)" target="_blank" class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-black uppercase tracking-widest text-gray-700 transition hover:border-indigo-200 hover:text-indigo-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                                <EyeIcon class="h-4 w-4" />
                                                Comprobante
                                            </a>
                                        </div>
                                    </article>
                                    <Pagination v-if="pagosLinks.length > 3" class="mt-5" :links="pagosLinks" @navigate="navegarPagina" />
                                </div>

                                <div v-if="pestanaActiva === 'actuaciones'" class="space-y-5">
                                    <form @submit.prevent="guardarActuacion" class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-950/40">
                                        <div class="grid grid-cols-1 gap-4 md:grid-cols-[minmax(0,1fr)_12rem]">
                                            <div>
                                                <InputLabel for="actuacion_nota" value="Nueva actuacion" />
                                                <Textarea id="actuacion_nota" v-model="actuacionForm.nota" rows="3" class="mt-1 block w-full" required />
                                                <InputError class="mt-2" :message="actuacionForm.errors.nota" />
                                            </div>
                                            <div>
                                                <InputLabel for="fecha_actuacion" value="Fecha" />
                                                <TextInput id="fecha_actuacion" type="date" v-model="actuacionForm.fecha_actuacion" class="mt-1 block w-full" required />
                                                <InputError class="mt-2" :message="actuacionForm.errors.fecha_actuacion" />
                                                <PrimaryButton class="mt-4 w-full justify-center" :disabled="actuacionForm.processing">
                                                    {{ actuacionForm.processing ? 'Registrando...' : 'Registrar' }}
                                                </PrimaryButton>
                                            </div>
                                        </div>
                                    </form>

                                    <div v-if="!props.actuaciones || props.actuaciones.length === 0" class="rounded-lg border border-dashed border-gray-300 py-12 text-center dark:border-gray-700">
                                        <DocumentTextIcon class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" />
                                        <p class="mt-3 text-sm font-black text-gray-950 dark:text-white">Sin actuaciones</p>
                                    </div>
                                    <article v-for="actuacion in props.actuaciones" :key="actuacion.id" class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-950/40">
                                        <div class="flex items-start justify-between gap-4">
                                            <div class="min-w-0 flex-1">
                                                <p class="whitespace-pre-wrap text-sm font-semibold leading-6 text-gray-800 dark:text-gray-200">{{ actuacion.nota }}</p>
                                                <p class="mt-2 text-xs font-bold text-gray-500 dark:text-gray-400">
                                                    {{ actuacion.user?.name ?? 'Usuario' }} · {{ fmtDate(actuacion.fecha_actuacion || actuacion.created_at) }}
                                                </p>
                                            </div>
                                            <div v-if="$page.props.auth.user" class="flex shrink-0 items-center gap-1">
                                                <button type="button" @click="abrirModalEditar(actuacion)" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-400 transition hover:bg-indigo-50 hover:text-indigo-700 dark:hover:bg-indigo-950/30 dark:hover:text-indigo-300" title="Editar">
                                                    <PencilSquareIcon class="h-4 w-4" />
                                                </button>
                                                <button type="button" @click="abrirConfirmarEliminarActuacion(actuacion)" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-400 transition hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-950/30 dark:hover:text-rose-300" title="Eliminar">
                                                    <TrashIcon class="h-4 w-4" />
                                                </button>
                                            </div>
                                        </div>
                                    </article>
                                </div>
                            </div>
                        </section>
                    </main>

                    <aside class="space-y-5 xl:sticky xl:top-6 xl:self-start">
                        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                            <h3 class="text-sm font-black uppercase tracking-widest text-gray-950 dark:text-white">Informacion</h3>
                            <dl class="mt-4 space-y-3">
                                <div class="flex items-center justify-between gap-4 border-b border-gray-100 pb-3 dark:border-gray-800">
                                    <dt class="text-xs font-black uppercase tracking-widest text-gray-400">Cliente</dt>
                                    <dd class="max-w-48 truncate text-right text-sm font-black text-gray-950 dark:text-white">
                                        <Link :href="route('personas.show', props.contrato.cliente_id)" class="hover:text-indigo-700 dark:hover:text-indigo-300">{{ nombreCliente }}</Link>
                                    </dd>
                                </div>
                                <div class="flex items-center justify-between gap-4 border-b border-gray-100 pb-3 dark:border-gray-800">
                                    <dt class="text-xs font-black uppercase tracking-widest text-gray-400">Inicio</dt>
                                    <dd class="text-sm font-black text-gray-950 dark:text-white">{{ fmtDate(props.contrato.inicio) }}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-4 border-b border-gray-100 pb-3 dark:border-gray-800">
                                    <dt class="text-xs font-black uppercase tracking-widest text-gray-400">Modalidad</dt>
                                    <dd class="text-sm font-black text-gray-950 dark:text-white">{{ modalidadLabel(props.contrato.modalidad) }}</dd>
                                </div>
                                <div v-if="props.contrato.modalidad !== 'LITIS'" class="flex items-center justify-between gap-4 border-b border-gray-100 pb-3 dark:border-gray-800">
                                    <dt class="text-xs font-black uppercase tracking-widest text-gray-400">Frecuencia</dt>
                                    <dd class="text-sm font-black text-gray-950 dark:text-white">{{ frecuenciaLabel(props.contrato.frecuencia_pago) }}</dd>
                                </div>
                                <div v-if="['LITIS','CUOTA_MIXTA'].includes(props.contrato.modalidad)" class="flex items-center justify-between gap-4 border-b border-gray-100 pb-3 dark:border-gray-800">
                                    <dt class="text-xs font-black uppercase tracking-widest text-gray-400">Exito</dt>
                                    <dd class="text-sm font-black text-violet-700 dark:text-violet-300">{{ props.contrato.porcentaje_litis }}%</dd>
                                </div>
                                <div v-if="Number(props.contrato.anticipo) > 0" class="flex items-center justify-between gap-4 border-b border-gray-100 pb-3 dark:border-gray-800">
                                    <dt class="text-xs font-black uppercase tracking-widest text-gray-400">Anticipo</dt>
                                    <dd class="text-sm font-black text-gray-950 dark:text-white">{{ fmtMoney(props.contrato.anticipo) }}</dd>
                                </div>
                            </dl>
                            <div class="mt-4 space-y-2">
                                <Link v-if="props.contrato.proceso" :href="route('procesos.show', props.contrato.proceso.id)" class="inline-flex w-full items-center justify-between gap-2 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-sm font-bold text-sky-700 transition hover:bg-sky-100 dark:border-sky-900/60 dark:bg-sky-950/30 dark:text-sky-300">
                                    Radicado #{{ props.contrato.proceso.radicado }}
                                    <ArrowTopRightOnSquareIcon class="h-4 w-4" />
                                </Link>
                                <Link v-if="props.contrato.caso" :href="route('casos.show', props.contrato.caso.id)" class="inline-flex w-full items-center justify-between gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-bold text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-300">
                                    Caso #{{ props.contrato.caso.id }}
                                    <ArrowTopRightOnSquareIcon class="h-4 w-4" />
                                </Link>
                            </div>
                        </section>

                        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                            <h3 class="text-sm font-black uppercase tracking-widest text-gray-950 dark:text-white">Documento</h3>
                            <div class="mt-4 space-y-2">
                                <a :href="route('honorarios.contratos.pdf.contrato', props.contrato.id)" target="_blank" rel="noopener" class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-black uppercase tracking-widest text-gray-700 transition hover:border-indigo-200 hover:text-indigo-700 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-300">
                                    <DocumentTextIcon class="h-4 w-4" />
                                    PDF contrato
                                </a>
                                <a :href="route('honorarios.contratos.pdf.liquidacion', props.contrato.id)" target="_blank" rel="noopener" class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs font-black uppercase tracking-widest text-indigo-700 transition hover:bg-indigo-100 dark:border-indigo-900/60 dark:bg-indigo-950/30 dark:text-indigo-300">
                                    <CreditCardIcon class="h-4 w-4" />
                                    Estado de cuenta
                                </a>
                                <button v-if="!props.contrato.documento_contrato" type="button" @click="abrirSubirDocumentoModal" class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-black uppercase tracking-widest text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-300">
                                    <ArrowUpTrayIcon class="h-4 w-4" />
                                    Subir documento
                                </button>
                                <template v-else>
                                    <a :href="route('honorarios.contratos.documento.ver', props.contrato.id)" target="_blank" rel="noopener" class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-black uppercase tracking-widest text-emerald-700 transition hover:bg-emerald-100 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-300">
                                        <EyeIcon class="h-4 w-4" />
                                        Ver documento cargado
                                    </a>
                                    <div class="relative" ref="documentoMenuRef">
                                        <button type="button" @click="toggleDocumentoMenu" class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-black uppercase tracking-widest text-gray-700 transition hover:border-indigo-200 hover:text-indigo-700 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-300">
                                            <EllipsisVerticalIcon class="h-4 w-4" />
                                            Gestionar documento
                                        </button>
                                        <div v-if="documentoMenuAbierto" class="absolute right-0 z-20 mt-2 w-full overflow-hidden rounded-lg border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-950">
                                            <button type="button" @click="abrirSubirDocumentoModal()" class="block w-full px-4 py-3 text-left text-sm font-bold text-gray-700 transition hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800">Reemplazar documento</button>
                                            <button type="button" @click="abrirConfirmarEliminarDoc()" class="block w-full px-4 py-3 text-left text-sm font-bold text-rose-700 transition hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-950/30">Eliminar documento</button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </section>

                        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                            <h3 class="text-sm font-black uppercase tracking-widest text-gray-950 dark:text-white">Acciones</h3>
                            <div class="mt-4 space-y-2">
                                <button v-if="['PAGOS_PENDIENTES', 'PAGO_PARCIAL'].includes(props.contrato?.estado) && props.contrato?.estado !== 'ACTIVO'" type="button" @click="accionesForm.post(route('honorarios.contratos.activar', props.contrato.id), { preserveScroll: true })" class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs font-black uppercase tracking-widest text-indigo-700 transition hover:bg-indigo-100 dark:border-indigo-900/60 dark:bg-indigo-950/30 dark:text-indigo-300">
                                    Activar contrato
                                </button>
                                <button v-if="$page.props.auth.user.tipo_usuario === 'admin'" type="button" @click="abrirModalConfirmarEliminacion" class="inline-flex w-full items-center justify-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-black uppercase tracking-widest text-rose-700 transition hover:bg-rose-100 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-300">
                                    <TrashIcon class="h-4 w-4" />
                                    Eliminar contrato
                                </button>
                            </div>
                        </section>
                    </aside>
                </div>
            </div>
        </div>

        <!-- Modales de Pago -->
        <Modal :show="pagoModalAbierto" @close="cerrarPagoModal" max-width="2xl">
            <form @submit.prevent="registrarPagoCuota" class="overflow-hidden">
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-5 dark:border-gray-700 dark:bg-gray-900/60">
                    <div class="flex items-start gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-indigo-100 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300">
                            <CreditCardIcon class="h-6 w-6" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Registro de pago</p>
                            <h4 class="mt-1 text-lg font-black text-gray-950 dark:text-white">Abonar a cuota #{{ cuotaSeleccionada?.numero }}</h4>
                            <p class="mt-1 text-sm font-semibold text-gray-500 dark:text-gray-400">Pendiente con mora: {{ fmtMoney(cuotaRestoConMora(cuotaSeleccionada)) }}</p>
                        </div>
                    </div>
                </div>

                <div class="space-y-5 px-6 py-6">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-950/40">
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Valor cuota</p>
                            <p class="mt-1 text-base font-black text-gray-950 dark:text-white">{{ fmtMoney(cuotaSeleccionada?.valor) }}</p>
                        </div>
                        <div class="rounded-lg border border-rose-200 bg-rose-50 p-3 dark:border-rose-900/60 dark:bg-rose-950/20">
                            <p class="text-[10px] font-black uppercase tracking-widest text-rose-500">Mora</p>
                            <p class="mt-1 text-base font-black text-rose-700 dark:text-rose-300">{{ fmtMoney(cuotaSeleccionada?.intereses_mora_acumulados) }}</p>
                        </div>
                        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-900/60 dark:bg-emerald-950/20">
                            <p class="text-[10px] font-black uppercase tracking-widest text-emerald-600">Pagado</p>
                            <p class="mt-1 text-base font-black text-emerald-700 dark:text-emerald-300">{{ fmtMoney(cuotaPagado(cuotaSeleccionada)) }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <InputLabel for="pago_cuota_valor" value="Valor del pago" />
                            <TextInput id="pago_cuota_valor" v-model.number="pagoCuotaForm.valor" type="number" step="0.01" min="0" required class="mt-1 block w-full" />
                            <InputError :message="pagoCuotaForm.errors.valor" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="pago_cuota_fecha" value="Fecha" />
                            <TextInput id="pago_cuota_fecha" v-model="pagoCuotaForm.fecha" type="date" required class="mt-1 block w-full" />
                            <InputError :message="pagoCuotaForm.errors.fecha" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="pago_cuota_metodo" value="Metodo" />
                            <SelectInput id="pago_cuota_metodo" v-model="pagoCuotaForm.metodo" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm dark:bg-gray-900 dark:border-gray-700">
                                <option>TRANSFERENCIA</option>
                                <option>EFECTIVO</option>
                                <option>TARJETA</option>
                                <option>OTRO</option>
                            </SelectInput>
                        </div>
                        <div class="md:col-span-2">
                            <InputLabel for="pago_cuota_nota" value="Nota opcional" />
                            <TextInput id="pago_cuota_nota" v-model="pagoCuotaForm.nota" type="text" class="mt-1 block w-full" placeholder="Referencia, banco, observacion o detalle interno" />
                        </div>
                        <div class="md:col-span-2">
                            <InputLabel for="pago_cuota_comp" value="Comprobante opcional" />
                            <input id="pago_cuota_comp" type="file" @input="pagoCuotaForm.comprobante = $event.target.files[0]" class="mt-1 block w-full rounded-lg border border-dashed border-gray-300 bg-gray-50 p-3 text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-black file:text-indigo-700 hover:file:bg-indigo-100 dark:border-gray-700 dark:bg-gray-950/40 dark:text-gray-300 dark:file:bg-indigo-950/40 dark:file:text-indigo-300" />
                            <InputError :message="pagoCuotaForm.errors.comprobante" class="mt-2" />
                        </div>
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-2 border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900/60 sm:flex-row sm:justify-end">
                    <SecondaryButton type="button" @click="cerrarPagoModal">Cancelar</SecondaryButton>
                    <PrimaryButton type="submit" :disabled="pagoCuotaForm.processing" class="justify-center">
                        {{ pagoCuotaForm.processing ? 'Guardando...' : 'Guardar pago' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <Modal :show="pagoCargoModalAbierto" @close="cerrarPagoCargoModal" max-width="2xl">
            <form @submit.prevent="registrarPagoCargo" class="overflow-hidden">
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-5 dark:border-gray-700 dark:bg-gray-900/60">
                    <div class="flex items-start gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">
                            <BanknotesIcon class="h-6 w-6" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Pago de cargo</p>
                            <h4 class="mt-1 text-lg font-black text-gray-950 dark:text-white">{{ cargoSeleccionado?.descripcion || 'Cargo adicional' }}</h4>
                            <p class="mt-1 text-sm font-semibold text-gray-500 dark:text-gray-400">
                                Valor requerido:
                                <strong class="text-gray-950 dark:text-white">
                                    <template v-if="cargoSeleccionado && String(cargoSeleccionado.tipo || '') !== 'INTERES_MORA' && Number(cargoSeleccionado.intereses_mora_acumulados || 0) > 0">
                                        {{ fmtMoney(cargoTotalAPagar(cargoSeleccionado)) }}
                                    </template>
                                    <template v-else>{{ fmtMoney(cargoSeleccionado?.monto) }}</template>
                                </strong>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="space-y-5 px-6 py-6">
                    <div v-if="cargoSeleccionado && String(cargoSeleccionado.tipo || '') !== 'INTERES_MORA' && Number(cargoSeleccionado.intereses_mora_acumulados || 0) > 0" class="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm font-semibold text-rose-800 dark:border-rose-900/60 dark:bg-rose-950/20 dark:text-rose-200">
                        Este cargo incluye {{ fmtMoney(cargoSeleccionado?.monto) }} de valor original y {{ fmtMoney(cargoSeleccionado?.intereses_mora_acumulados) }} de mora acumulada.
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <InputLabel for="pago_cargo_valor" value="Valor del pago" />
                            <TextInput id="pago_cargo_valor" v-model.number="pagoCargoForm.valor" type="number" step="0.01" min="0" required class="mt-1 block w-full" />
                            <InputError :message="pagoCargoForm.errors.valor" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="pago_cargo_fecha" value="Fecha" />
                            <TextInput id="pago_cargo_fecha" v-model="pagoCargoForm.fecha" type="date" required class="mt-1 block w-full" />
                            <InputError :message="pagoCargoForm.errors.fecha" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="pago_cargo_metodo" value="Metodo" />
                            <SelectInput id="pago_cargo_metodo" v-model="pagoCargoForm.metodo" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm dark:bg-gray-900 dark:border-gray-700">
                                <option>TRANSFERENCIA</option>
                                <option>EFECTIVO</option>
                                <option>TARJETA</option>
                                <option>OTRO</option>
                            </SelectInput>
                        </div>
                        <div class="md:col-span-2">
                            <InputLabel for="pago_cargo_nota" value="Nota opcional" />
                            <TextInput id="pago_cargo_nota" v-model="pagoCargoForm.nota" type="text" class="mt-1 block w-full" placeholder="Referencia o detalle interno del pago" />
                        </div>
                        <div class="md:col-span-2">
                            <InputLabel for="pago_cargo_comp" value="Comprobante requerido" />
                            <input id="pago_cargo_comp" type="file" @input="pagoCargoForm.comprobante = $event.target.files[0]" required class="mt-1 block w-full rounded-lg border border-dashed border-gray-300 bg-gray-50 p-3 text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-black file:text-indigo-700 hover:file:bg-indigo-100 dark:border-gray-700 dark:bg-gray-950/40 dark:text-gray-300 dark:file:bg-indigo-950/40 dark:file:text-indigo-300" />
                            <InputError :message="pagoCargoForm.errors.comprobante" class="mt-2" />
                        </div>
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-2 border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900/60 sm:flex-row sm:justify-end">
                    <SecondaryButton type="button" @click="cerrarPagoCargoModal">Cancelar</SecondaryButton>
                    <PrimaryButton type="submit" :disabled="pagoCargoForm.processing" class="justify-center">
                        {{ pagoCargoForm.processing ? 'Guardando...' : 'Guardar pago' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Modales de Acción -->
        <Modal :show="gastoModalAbierto" @close="cerrarGastoModal" max-width="2xl">
            <form @submit.prevent="registrarGasto" class="overflow-hidden">
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-5 dark:border-gray-700 dark:bg-gray-900/60">
                    <div class="flex items-start gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-sky-100 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300">
                            <PlusIcon class="h-6 w-6" />
                        </span>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Cargo recuperable</p>
                            <h4 class="mt-1 text-lg font-black text-gray-950 dark:text-white">Registrar gasto pagado</h4>
                            <p class="mt-1 text-sm font-semibold text-gray-500 dark:text-gray-400">Quedara como cargo pendiente para cobrar al cliente.</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 px-6 py-6 md:grid-cols-2">
                    <div>
                        <InputLabel for="gasto_monto" value="Monto del gasto" />
                        <TextInput id="gasto_monto" v-model.number="gastoForm.monto" type="number" step="0.01" min="0" required class="mt-1 block w-full" />
                        <InputError :message="gastoForm.errors.monto" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="gasto_fecha" value="Fecha de aplicacion" />
                        <TextInput id="gasto_fecha" v-model="gastoForm.fecha" type="date" required class="mt-1 block w-full" />
                        <InputError :message="gastoForm.errors.fecha" class="mt-2" />
                    </div>
                    <div class="md:col-span-2">
                        <InputLabel for="gasto_desc" value="Descripcion" />
                        <TextInput id="gasto_desc" v-model="gastoForm.descripcion" type="text" required class="mt-1 block w-full" placeholder="Ej: notificacion, desplazamiento, certificado, autenticacion" />
                        <InputError :message="gastoForm.errors.descripcion" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="gasto_fecha_interes" value="Inicio de intereses opcional" />
                        <TextInput id="gasto_fecha_interes" v-model="gastoForm.fecha_inicio_intereses" type="date" class="mt-1 block w-full" />
                        <p class="mt-1 text-xs font-semibold text-gray-500 dark:text-gray-400">Si queda vacio, este gasto no genera intereses.</p>
                        <InputError :message="gastoForm.errors.fecha_inicio_intereses" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="gasto_comp" value="Comprobante opcional" />
                        <input id="gasto_comp" type="file" @input="gastoForm.comprobante = $event.target.files[0]" class="mt-1 block w-full rounded-lg border border-dashed border-gray-300 bg-gray-50 p-3 text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-sky-50 file:px-4 file:py-2 file:text-sm file:font-black file:text-sky-700 hover:file:bg-sky-100 dark:border-gray-700 dark:bg-gray-950/40 dark:text-gray-300 dark:file:bg-sky-950/40 dark:file:text-sky-300" />
                        <InputError :message="gastoForm.errors.comprobante" class="mt-2" />
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-2 border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900/60 sm:flex-row sm:justify-end">
                    <SecondaryButton type="button" @click="cerrarGastoModal">Cancelar</SecondaryButton>
                    <PrimaryButton type="submit" :disabled="gastoForm.processing" class="justify-center">
                        {{ gastoForm.processing ? 'Guardando...' : 'Guardar gasto' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <Modal :show="cierreModalAbierto" @close="cerrarCierreModal" max-width="2xl">
            <form @submit.prevent="confirmarCierre" class="overflow-hidden">
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-5 dark:border-gray-700 dark:bg-gray-900/60">
                    <div class="flex items-start gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-100">
                            <CheckCircleIcon class="h-6 w-6" />
                        </span>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Cierre manual</p>
                            <h4 class="mt-1 text-lg font-black text-gray-950 dark:text-white">Cerrar contrato</h4>
                            <p class="mt-1 text-sm font-semibold text-gray-500 dark:text-gray-400">El contrato pasara a pagos pendientes. Puedes agregar un cargo final si aplica.</p>
                        </div>
                    </div>
                </div>

                <div class="space-y-4 px-6 py-6">
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/20 dark:text-amber-200">
                        Revisa el saldo antes de cerrar: saldo actual {{ fmtMoney(saldo) }}. El cierre no borra cuotas, cargos ni pagos.
                    </div>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <InputLabel for="cierre_monto" value="Cargo final opcional" />
                            <TextInput id="cierre_monto" v-model.number="cierreForm.monto" type="number" step="0.01" min="0" placeholder="Ej: 50000" class="mt-1 block w-full" />
                            <InputError :message="cierreForm.errors.monto" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="cierre_fecha_interes" value="Inicio de intereses opcional" />
                            <TextInput id="cierre_fecha_interes" v-model="cierreForm.fecha_inicio_intereses" type="date" class="mt-1 block w-full" />
                            <InputError :message="cierreForm.errors.fecha_inicio_intereses" class="mt-2" />
                        </div>
                        <div class="md:col-span-2">
                            <InputLabel for="cierre_desc" value="Descripcion del cargo" />
                            <TextInput id="cierre_desc" v-model="cierreForm.descripcion" type="text" placeholder="Ej: clausula penal por terminacion" class="mt-1 block w-full" />
                            <InputError :message="cierreForm.errors.descripcion" class="mt-2" />
                        </div>
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-2 border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900/60 sm:flex-row sm:justify-end">
                    <SecondaryButton type="button" @click="cerrarCierreModal">Cancelar</SecondaryButton>
                    <button type="submit" :disabled="cierreForm.processing" class="inline-flex items-center justify-center rounded-lg bg-gray-900 px-4 py-2 text-xs font-black uppercase tracking-widest text-white transition hover:bg-black disabled:opacity-50 dark:bg-gray-100 dark:text-gray-950 dark:hover:bg-white">
                        {{ cierreForm.processing ? 'Cerrando...' : 'Cerrar contrato' }}
                    </button>
                </div>
            </form>
        </Modal>

        <Modal :show="resolverLitisModalAbierto" @close="cerrarResolverLitisModal" max-width="2xl">
            <form @submit.prevent="confirmarResolucionLitis" class="overflow-hidden">
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-5 dark:border-gray-700 dark:bg-gray-900/60">
                    <div class="flex items-start gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-violet-100 text-violet-700 dark:bg-violet-950/50 dark:text-violet-300">
                            <ScaleIcon class="h-6 w-6" />
                        </span>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Resultado litis</p>
                            <h4 class="mt-1 text-lg font-black text-gray-950 dark:text-white">Registrar base de honorarios</h4>
                            <p class="mt-1 text-sm font-semibold text-gray-500 dark:text-gray-400">Se calculara el {{ props.contrato.porcentaje_litis }}% y se generara el cargo final.</p>
                        </div>
                    </div>
                </div>

                <div class="space-y-4 px-6 py-6">
                    <div class="rounded-lg border border-violet-200 bg-violet-50 p-4 text-sm font-semibold text-violet-900 dark:border-violet-900/60 dark:bg-violet-950/20 dark:text-violet-200">
                        Usa el valor real obtenido o reconocido en el proceso. Esta accion deja trazabilidad en el contrato.
                    </div>
                    <div>
                        <InputLabel for="litis_monto" value="Monto base final" />
                        <TextInput id="litis_monto" v-model.number="resolverLitisForm.monto_base_litis" type="number" step="0.01" min="0" required placeholder="Ej: 15000000" class="mt-1 block w-full" />
                        <InputError :message="resolverLitisForm.errors.monto_base_litis" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="litis_fecha_interes" value="Inicio de intereses opcional" />
                        <TextInput id="litis_fecha_interes" v-model="resolverLitisForm.fecha_inicio_intereses" type="date" class="mt-1 block w-full" />
                        <p class="mt-1 text-xs font-semibold text-gray-500 dark:text-gray-400">Si queda vacio, el cargo litis no genera intereses.</p>
                        <InputError :message="resolverLitisForm.errors.fecha_inicio_intereses" class="mt-2" />
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-2 border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900/60 sm:flex-row sm:justify-end">
                    <SecondaryButton type="button" @click="cerrarResolverLitisModal">Cancelar</SecondaryButton>
                    <button type="submit" :disabled="resolverLitisForm.processing" class="inline-flex items-center justify-center rounded-lg bg-violet-600 px-4 py-2 text-xs font-black uppercase tracking-widest text-white transition hover:bg-violet-700 disabled:opacity-50">
                        {{ resolverLitisForm.processing ? 'Confirmando...' : 'Generar cargo litis' }}
                    </button>
                </div>
            </form>
        </Modal>

        <Modal :show="reestructurarModalAbierto" @close="cerrarReestructurarModal" max-width="4xl">
            <form @submit.prevent="guardarNuevoContrato" class="overflow-hidden">
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-5 dark:border-gray-700 dark:bg-gray-900/60">
                    <div class="flex items-start gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-indigo-100 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300">
                            <ArrowPathIcon class="h-6 w-6" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Nuevo acuerdo</p>
                            <h4 class="mt-1 text-lg font-black text-gray-950 dark:text-white">Reestructurar contrato #{{ props.contrato.id }}</h4>
                            <p class="mt-1 text-sm font-semibold text-gray-500 dark:text-gray-400">Crea un contrato nuevo ligado al actual sin perder el historial.</p>
                        </div>
                    </div>
                </div>

                <div class="space-y-6 px-6 py-6">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                        <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-950/40">
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Cliente</p>
                            <p class="mt-1 truncate text-sm font-black text-gray-950 dark:text-white">{{ clienteSearch }}</p>
                        </div>
                        <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-950/40">
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Estado actual</p>
                            <p class="mt-1 text-sm font-black text-gray-950 dark:text-white">{{ estadoLabel(props.contrato.estado) }}</p>
                        </div>
                        <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-950/40">
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Saldo</p>
                            <p class="mt-1 text-sm font-black" :class="saldo > 0 ? 'text-rose-700 dark:text-rose-300' : 'text-emerald-700 dark:text-emerald-300'">{{ fmtMoney(saldo) }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <InputLabel for="cliente_search_reestructurar" value="Cliente" />
                            <TextInput id="cliente_search_reestructurar" type="text" v-model="clienteSearch" disabled class="mt-1 block w-full disabled:bg-gray-100 dark:disabled:bg-gray-800/50" />
                            <InputError class="mt-2" :message="crearContratoForm.errors.cliente_id" />
                        </div>
                        <div>
                            <InputLabel for="inicio_reestructurar" value="Fecha de inicio" />
                            <TextInput id="inicio_reestructurar" type="date" v-model="crearContratoForm.inicio" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="crearContratoForm.errors.inicio" />
                        </div>
                        <div>
                            <InputLabel for="modalidad_reestructurar" value="Modalidad" />
                            <SelectInput v-model="crearContratoForm.modalidad" id="modalidad_reestructurar" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm dark:bg-gray-900 dark:border-gray-700">
                                <option v-for="mod in props.modalidades" :key="mod" :value="mod">{{ modalidadLabel(mod) }}</option>
                            </SelectInput>
                            <InputError class="mt-2" :message="crearContratoForm.errors.modalidad" />
                        </div>
                        <template v-if="['CUOTAS','PAGO_UNICO','CUOTA_MIXTA'].includes(crearContratoForm.modalidad)">
                            <div>
                                <InputLabel for="monto_total_reestructurar" value="Monto total fijo" />
                                <TextInput id="monto_total_reestructurar" type="number" step="0.01" v-model="crearContratoForm.monto_total" class="mt-1 block w-full" placeholder="Ej: 5000000" />
                                <InputError class="mt-2" :message="crearContratoForm.errors.monto_total" />
                            </div>
                            <div v-if="crearContratoForm.modalidad !== 'PAGO_UNICO'">
                                <InputLabel for="cuotas_reestructurar" value="Numero de cuotas" />
                                <TextInput id="cuotas_reestructurar" type="number" v-model="crearContratoForm.cuotas" class="mt-1 block w-full" placeholder="Ej: 12" />
                                <InputError class="mt-2" :message="crearContratoForm.errors.cuotas" />
                            </div>
                            <div v-if="crearContratoForm.modalidad !== 'PAGO_UNICO'">
                                <InputLabel for="frecuencia_reestructurar" value="Frecuencia de pago" />
                                <SelectInput v-model="crearContratoForm.frecuencia_pago" id="frecuencia_reestructurar" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm dark:bg-gray-900 dark:border-gray-700">
                                    <option v-for="freq in frecuencias" :key="freq.value" :value="freq.value">{{ freq.label }}</option>
                                </SelectInput>
                            </div>
                            <div>
                                <InputLabel for="anticipo_reestructurar" value="Anticipo opcional" />
                                <TextInput id="anticipo_reestructurar" type="number" step="0.01" v-model="crearContratoForm.anticipo" class="mt-1 block w-full" placeholder="Ej: 1000000" />
                                <InputError class="mt-2" :message="crearContratoForm.errors.anticipo" />
                            </div>
                        </template>
                        <template v-if="['LITIS','CUOTA_MIXTA'].includes(crearContratoForm.modalidad)">
                            <div>
                                <InputLabel for="porcentaje_litis_reestructurar" value="Porcentaje de exito" />
                                <TextInput id="porcentaje_litis_reestructurar" type="number" step="0.01" v-model="crearContratoForm.porcentaje_litis" class="mt-1 block w-full" placeholder="Ej: 20" />
                                <InputError class="mt-2" :message="crearContratoForm.errors.porcentaje_litis" />
                            </div>
                        </template>
                        <div class="md:col-span-2">
                            <InputLabel for="nota_reestructurar" value="Nota opcional" />
                            <Textarea id="nota_reestructurar" v-model="crearContratoForm.nota" rows="3" class="mt-1 block w-full" placeholder="Motivo del nuevo acuerdo, condiciones o autorizaciones" />
                            <InputError class="mt-2" :message="crearContratoForm.errors.nota" />
                        </div>
                    </div>
                    <input type="hidden" v-model="crearContratoForm.contrato_origen_id">
                </div>

                <div class="flex flex-col-reverse gap-2 border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900/60 sm:flex-row sm:justify-end">
                    <SecondaryButton type="button" @click="cerrarReestructurarModal">Cancelar</SecondaryButton>
                    <PrimaryButton type="submit" :disabled="crearContratoForm.processing" class="justify-center">
                        {{ crearContratoForm.processing ? 'Guardando...' : 'Crear nuevo contrato' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <Modal :show="subirDocumentoModalAbierto" @close="cerrarSubirDocumentoModal" max-width="xl">
            <form @submit.prevent="subirDocumento" class="overflow-hidden">
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-5 dark:border-gray-700 dark:bg-gray-900/60">
                    <div class="flex items-start gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">
                            <ArrowUpTrayIcon class="h-6 w-6" />
                        </span>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Documento PDF</p>
                            <h4 class="mt-1 text-lg font-black text-gray-950 dark:text-white">
                                {{ props.contrato.documento_contrato ? 'Reemplazar documento del contrato' : 'Subir documento del contrato' }}
                            </h4>
                        </div>
                    </div>
                </div>

                <div class="space-y-4 px-6 py-6">
                    <div v-if="props.contrato.documento_contrato" class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/20 dark:text-amber-200">
                        <div class="flex items-start gap-2">
                            <ExclamationTriangleIcon class="mt-0.5 h-5 w-5 shrink-0" />
                            <span>Este contrato ya tiene un documento. El nuevo PDF reemplazara el archivo actual.</span>
                        </div>
                    </div>

                    <div>
                        <InputLabel for="doc_input_file" value="Archivo PDF" />
                        <input type="file" id="doc_input_file" accept=".pdf" @input="documentoForm.documento = $event.target.files[0]" required class="mt-1 block w-full rounded-lg border border-dashed border-gray-300 bg-gray-50 p-4 text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-4 file:py-2 file:text-sm file:font-black file:text-emerald-700 hover:file:bg-emerald-100 dark:border-gray-700 dark:bg-gray-950/40 dark:text-gray-300 dark:file:bg-emerald-950/40 dark:file:text-emerald-300" />
                        <p class="mt-1 text-xs font-semibold text-gray-500 dark:text-gray-400">Maximo 10MB. Solo PDF.</p>
                        <InputError :message="documentoForm.errors.documento" class="mt-2" />
                    </div>

                    <div v-if="documentoForm.documento" class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-950/40">
                        <div class="flex items-center gap-3">
                            <DocumentTextIcon class="h-6 w-6 shrink-0 text-rose-600 dark:text-rose-300" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-black text-gray-800 dark:text-gray-100">{{ documentoForm.documento.name }}</p>
                                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ (documentoForm.documento.size / 1024 / 1024).toFixed(2) }} MB</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-2 border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900/60 sm:flex-row sm:justify-end">
                    <SecondaryButton type="button" @click="cerrarSubirDocumentoModal">Cancelar</SecondaryButton>
                    <PrimaryButton type="submit" :disabled="documentoForm.processing || !documentoForm.documento" class="justify-center">
                        {{ documentoForm.processing ? 'Subiendo...' : (props.contrato.documento_contrato ? 'Reemplazar documento' : 'Subir documento') }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <Modal :show="confirmandoEliminacion" @close="cerrarModalConfirmarEliminacion" max-width="lg">
            <form @submit.prevent="eliminarContrato" class="overflow-hidden">
                <div class="border-b border-rose-200 bg-rose-50 px-6 py-5 dark:border-rose-900/60 dark:bg-rose-950/20">
                    <div class="flex items-start gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-rose-100 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300">
                            <TrashIcon class="h-6 w-6" />
                        </span>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-rose-500">Accion irreversible</p>
                            <h4 class="mt-1 text-lg font-black text-rose-950 dark:text-rose-100">Eliminar contrato #{{ props.contrato.id }}</h4>
                            <p class="mt-1 text-sm font-semibold text-rose-800 dark:text-rose-200">{{ nombreCliente }}</p>
                        </div>
                    </div>
                </div>

                <div class="space-y-3 px-6 py-6 text-sm font-semibold text-gray-600 dark:text-gray-300">
                    <p>Se eliminara el contrato junto con sus cuotas, cargos, pagos, actuaciones y documentos asociados.</p>
                    <p>Si el contrato solo tiene condiciones mal pactadas, la opcion menos riesgosa es reestructurarlo.</p>
                </div>

                <div class="flex flex-col-reverse gap-2 border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900/60 sm:flex-row sm:justify-end">
                    <SecondaryButton type="button" @click="cerrarModalConfirmarEliminacion">Cancelar</SecondaryButton>
                    <button type="submit" :class="['inline-flex items-center justify-center rounded-lg px-4 py-2 text-xs font-black uppercase tracking-widest text-white transition', deleteForm.processing ? 'bg-rose-400' : 'bg-rose-600 hover:bg-rose-700']" :disabled="deleteForm.processing">
                        {{ deleteForm.processing ? 'Eliminando...' : 'Eliminar permanentemente' }}
                    </button>
                </div>
            </form>
        </Modal>

        <Modal :show="editActuacionModalAbierto" @close="cerrarModalEditar" max-width="xl">
            <form @submit.prevent="actualizarActuacion" class="overflow-hidden">
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-5 dark:border-gray-700 dark:bg-gray-900/60">
                    <div class="flex items-start gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-indigo-100 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300">
                            <PencilSquareIcon class="h-6 w-6" />
                        </span>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-400">Actuacion manual</p>
                            <h4 class="mt-1 text-lg font-black text-gray-950 dark:text-white">Editar actuacion</h4>
                        </div>
                    </div>
                </div>

                <div class="space-y-4 px-6 py-6">
                    <div>
                        <InputLabel for="edit_actuacion_nota" value="Descripcion" />
                        <Textarea id="edit_actuacion_nota" v-model="editActuacionForm.nota" rows="5" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="editActuacionForm.errors.nota" />
                    </div>
                    <div>
                        <InputLabel for="edit_fecha_actuacion" value="Fecha de actuacion" />
                        <TextInput id="edit_fecha_actuacion" type="date" v-model="editActuacionForm.fecha_actuacion" class="mt-1 block w-full" required />
                        <InputError class="mt-2" :message="editActuacionForm.errors.fecha_actuacion" />
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-2 border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900/60 sm:flex-row sm:justify-end">
                    <SecondaryButton type="button" @click="cerrarModalEditar">Cancelar</SecondaryButton>
                    <PrimaryButton type="submit" :disabled="editActuacionForm.processing" class="justify-center">
                        {{ editActuacionForm.processing ? 'Guardando...' : 'Guardar cambios' }}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>

        <Modal :show="confirmandoEliminarActuacion" @close="cerrarConfirmarEliminarActuacion" max-width="lg">
            <form @submit.prevent="eliminarActuacionConfirmado" class="overflow-hidden">
                <div class="border-b border-rose-200 bg-rose-50 px-6 py-5 dark:border-rose-900/60 dark:bg-rose-950/20">
                    <div class="flex items-start gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-rose-100 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300">
                            <TrashIcon class="h-6 w-6" />
                        </span>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-rose-500">Confirmacion</p>
                            <h4 class="mt-1 text-lg font-black text-rose-950 dark:text-rose-100">Eliminar actuacion</h4>
                        </div>
                    </div>
                </div>

                <div class="space-y-4 px-6 py-6">
                    <p class="text-sm font-semibold text-gray-600 dark:text-gray-300">Esta actuacion se eliminara del historial del contrato.</p>
                    <div class="max-h-36 overflow-y-auto rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm italic text-gray-700 dark:border-gray-700 dark:bg-gray-950/40 dark:text-gray-300">
                        {{ actuacionAEliminar?.nota }}
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-2 border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900/60 sm:flex-row sm:justify-end">
                    <SecondaryButton type="button" @click="cerrarConfirmarEliminarActuacion">Cancelar</SecondaryButton>
                    <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-rose-600 px-4 py-2 text-xs font-black uppercase tracking-widest text-white transition hover:bg-rose-700">
                        Eliminar actuacion
                    </button>
                </div>
            </form>
        </Modal>

        <Modal :show="confirmandoEliminarDoc" @close="cerrarConfirmarEliminarDoc" max-width="lg">
            <form @submit.prevent="eliminarDocumento" class="overflow-hidden">
                <div class="border-b border-rose-200 bg-rose-50 px-6 py-5 dark:border-rose-900/60 dark:bg-rose-950/20">
                    <div class="flex items-start gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-rose-100 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300">
                            <DocumentTextIcon class="h-6 w-6" />
                        </span>
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-rose-500">Documento del contrato</p>
                            <h4 class="mt-1 text-lg font-black text-rose-950 dark:text-rose-100">Eliminar PDF asociado</h4>
                        </div>
                    </div>
                </div>

                <div class="space-y-3 px-6 py-6 text-sm font-semibold text-gray-600 dark:text-gray-300">
                    <p>El documento PDF cargado se eliminara del contrato. El contrato y sus datos financieros no se modifican.</p>
                </div>

                <div class="flex flex-col-reverse gap-2 border-t border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900/60 sm:flex-row sm:justify-end">
                    <SecondaryButton type="button" @click="cerrarConfirmarEliminarDoc">Cancelar</SecondaryButton>
                    <button type="submit" :class="['inline-flex items-center justify-center rounded-lg px-4 py-2 text-xs font-black uppercase tracking-widest text-white transition', accionesForm.processing ? 'bg-rose-400' : 'bg-rose-600 hover:bg-rose-700']" :disabled="accionesForm.processing">
                        {{ accionesForm.processing ? 'Eliminando...' : 'Eliminar documento' }}
                    </button>
                </div>
            </form>
        </Modal>

    </AuthenticatedLayout>
</template>