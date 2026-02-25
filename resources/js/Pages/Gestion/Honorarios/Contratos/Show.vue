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
import TextInput from '@/Components/TextInput.vue'
import Textarea from '@/Components/Textarea.vue'
import { onClickOutside } from '@vueuse/core'

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
        onError: (errors) => {
             console.error("Error al guardar actuación:", errors);
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
        onError: (errors) => {
             console.error("Error al actualizar actuación:", errors);
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
    confirmandoEliminacion.value = false
}
const eliminarActuacionConfirmado = () => {
    if (!actuacionAEliminar.value) return;
    
    router.delete(route('honorarios.contratos.actuaciones.destroy', actuacionAEliminar.value.id), {
        preserveScroll: true,
        onSuccess: () => {
             cerrarConfirmarEliminarActuacion()
        },
        onError: (errors) => {
            console.error("Error al eliminar actuación:", errors);
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
    pestanaActiva.value = nuevaPestana
    const url = new URL(window.location)
    url.searchParams.set('tab', nuevaPestana)
    window.history.replaceState({}, '', url)
}
onMounted(() => {
    const tabFromUrl = new URLSearchParams(window.location.search).get('tab')
    if (tabFromUrl && ['cuotas', 'cargos', 'pagos', 'actuaciones'].includes(tabFromUrl)) {
         pestanaActiva.value = tabFromUrl;
    } else {
         if (props.actuaciones.length > 0 && props.cuotas.data.length === 0 && props.cargos.data.length === 0) {
             pestanaActiva.value = 'actuaciones';
         } else {
             pestanaActiva.value = 'cuotas';
         }
         cambiarPestana(pestanaActiva.value); 
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

</script>

<template>
    <Head :title="`Honorarios · Contrato #${props.contrato?.id}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Detalle del Contrato</h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Contrato <span class="font-mono">#{{ props.contrato?.id }}</span><span class="mx-2">|</span>
                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold" :class="contractStatusClasses[props.contrato?.estado] || contractStatusClasses['CERRADO']">
                            {{ props.contrato?.estado?.replace('_', ' ') }}
                        </span>
                    </p>
                </div>

                <div class="flex items-center gap-2 flex-wrap justify-start md:justify-end">
                    <Link :href="route('honorarios.contratos.index')" class="text-sm px-3 py-1.5 rounded-md bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                        Volver
                    </Link>
                    <template v-if="['ACTIVO','PAGOS_PENDIENTES', 'EN_MORA', 'PAGO_PARCIAL'].includes(props.contrato?.estado)">
                        <button @click="abrirGastoModal" class="text-sm px-3 py-1.5 rounded-md bg-blue-50 dark:bg-blue-900/40 text-blue-700 dark:text-blue-200 border border-blue-200 dark:border-blue-800 hover:bg-blue-100 dark:hover:bg-blue-900">
                            Añadir Gasto
                        </button>
                    </template>
                    <template v-if="props.contrato?.estado === 'ACTIVO'">
                        <button @click="handleCerrarContratoClick" class="text-sm px-3 py-1.5 rounded-md bg-slate-700 text-white hover:bg-slate-800">
                            {{ ['LITIS','CUOTA_MIXTA'].includes(props.contrato?.modalidad) && !Number(props.contrato?.monto_base_litis) && !Number(props.contrato?.litis_valor_ganado) ? 'Registrar Resultado y Cerrar' : 'Cerrar Contrato' }}
                        </button>
                    </template>
                    <template v-if="['PAGOS_PENDIENTES', 'PAGO_PARCIAL'].includes(props.contrato?.estado)">
                        <button v-if="saldo <= 0" @click="accionesForm.post(route('honorarios.contratos.saldar', props.contrato.id), { preserveScroll: true })" class="text-sm px-3 py-1.5 rounded-md bg-emerald-600 text-white hover:bg-emerald-700">
                            Saldar y Cerrar
                        </button>
                        <button v-if="props.contrato?.estado !== 'ACTIVO'" @click="accionesForm.post(route('honorarios.contratos.activar', props.contrato.id), { preserveScroll: true })" class="text-sm px-3 py-1.5 rounded-md bg-green-600 text-white hover:bg-green-700">
                            Activar
                        </button>
                    </template>
                    <button v-if="props.contrato?.estado === 'CERRADO'"
                            @click="accionesForm.post(route('honorarios.contratos.reabrir', props.contrato.id), { preserveScroll: true })"
                            class="text-sm px-3 py-1.5 rounded-md bg-amber-600 text-white hover:bg-amber-700">
                        Reabrir
                    </button>
                    <button @click="abrirReestructurarModal"
                            class="text-sm px-3 py-1.5 rounded-md bg-indigo-50 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-200 border border-indigo-200 dark:border-indigo-800 hover:bg-indigo-100 dark:hover:bg-indigo-900">
                        Reestructurar
                    </button>

                    <!-- BOTÓN DE ELIMINAR PROTEGIDO -->
                    <button v-if="$page.props.auth.user.tipo_usuario === 'admin'" @click="abrirModalConfirmarEliminacion"
                            class="text-sm px-3 py-1.5 rounded-md bg-red-600 text-white hover:bg-red-700 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                        Eliminar
                    </button>

                    <div class="relative">
                        <template v-if="!props.contrato.documento_contrato">
                            <button @click="abrirSubirDocumentoModal" class="text-sm px-3 py-1.5 rounded-md bg-green-50 dark:bg-green-900/40 text-green-700 dark:text-green-200 border border-green-200 dark:border-green-800 hover:bg-green-100 dark:hover:bg-green-900 flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" /></svg>
                                Subir Documento
                            </button>
                        </template>
                        <template v-else>
                            <div class="flex items-center gap-2">
                                <a :href="route('honorarios.contratos.documento.ver', props.contrato.id)" target="_blank" rel="noopener" class="text-sm px-3 py-1.5 rounded-md bg-blue-50 dark:bg-blue-900/40 text-blue-700 dark:text-blue-200 border border-blue-200 dark:border-blue-800 hover:bg-blue-100 dark:hover:bg-blue-900 flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                    Ver Documento
                                </a>
                                <div class="relative" ref="documentoMenuRef">
                                    <button @click="toggleDocumentoMenu" class="text-sm px-2 py-1.5 rounded-md bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-600">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" /></svg>
                                    </button>
                                    <div v-if="documentoMenuAbierto" class="absolute right-0 top-full mt-1 w-48 bg-white dark:bg-gray-800 rounded-md shadow-lg border border-gray-200 dark:border-gray-700 z-10">
                                        <button @click="abrirSubirDocumentoModal()" class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                                            Reemplazar Documento
                                        </button>
                                        <button @click="abrirConfirmarEliminarDoc()" class="w-full text-left px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 flex items-center gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            Eliminar Documento
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                    <a :href="route('honorarios.contratos.pdf.liquidacion', props.contrato.id)"
                       target="_blank" rel="noopener"
                       class="text-sm px-3 py-1.5 rounded-md bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600">
                        PDF Liquidación
                    </a>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <div v-if="props.contratoOrigen"
                     class="bg-amber-50 dark:bg-amber-900/20 border-l-4 border-amber-400 p-4 rounded-md shadow-sm">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-amber-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">
                                Este contrato es una reestructuración del
                                <Link :href="route('honorarios.contratos.show', props.contratoOrigen.id)" class="underline hover:text-amber-600">
                                    Contrato #{{ props.contratoOrigen.id }}
                                </Link>.
                            </p>
                            <div class="mt-2 text-sm text-amber-700 dark:text-amber-400">
                                <p>
                                    El contrato original tenía un estado de <strong>{{ props.contratoOrigen.estado }}</strong>
                                    con un monto de <strong>{{ fmtMoney(props.contratoOrigen.monto_total) }}</strong>
                                    en modalidad <strong>{{ props.contratoOrigen.modalidad.replace('_', ' ') }}</strong>.
                                </p>
                                <p v-if="props.contrato.nota" class="mt-1 italic">
                                    Nota de reestructuración: "{{ props.contrato.nota }}"
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-5">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Neto (Cuotas)</div>
                        <div class="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ fmtMoney(netoContrato) }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-5">
                        <div class="text-sm text-gray-500 dark:text-gray-400">(+) Cargos Adicionales</div>
                        <div class="mt-1 text-2xl font-bold text-blue-600 dark:text-blue-400">{{ fmtMoney(valorTotalCargosConMora) }}</div>
                        <div v-if="totalInteresesMora > 0" class="text-xs text-red-600 dark:text-red-400 font-semibold mt-1">
                            (Incluye {{ fmtMoney(totalInteresesMora) }} en mora)
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-5">
                        <div class="text-sm text-gray-500 dark:text-gray-400">(-) Total Pagado</div>
                        <div class="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ fmtMoney(totalPagosValor) }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-5">
                        <div class="text-sm text-gray-500 dark:text-gray-400">(=) Saldo Pendiente</div>
                        <div class="mt-1 text-2xl font-bold text-red-600 dark:text-red-400">{{ fmtMoney(saldo) }}</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="lg:col-span-2 space-y-6">
                        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                            <div class_="" :class="{'p-4 sm:p-6': props.contrato.modalidad !== 'LITIS'}">
                                <!-- Ocultar Pestañas si es LITIS puro y no tiene cargos/pagos/actuaciones -->
                                <template v-if="props.contrato.modalidad === 'LITIS' && props.cuotas.data.length === 0 && props.cargos.data.length === 0 && props.pagos.data.length === 0 && props.actuaciones.length === 0">
                                    <div class="text-center py-12 text-gray-500 dark:text-gray-400">
                                        <p>Este es un contrato de Litis sin cuotas fijas.</p>
                                        <p class="mt-1">Registre el resultado del caso para generar el cargo por honorarios.</p>
                                    </div>
                                </template>
                                <template v-else>
                                    <div class="border-b border-gray-200 dark:border-gray-700 px-4 sm:px-6">
                                        <nav class="-mb-px flex space-x-4 sm:space-x-6 overflow-x-auto" aria-label="Tabs">
                                            <button v-if="props.contrato.modalidad !== 'LITIS' || props.cuotas.data.length > 0" @click="cambiarPestana('cuotas')" :class="[pestanaActiva === 'cuotas' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300', 'whitespace-nowrap pb-3 px-1 border-b-2 font-medium text-sm']">Plan de Pagos</button>
                                            <button @click="cambiarPestana('cargos')" :class="[pestanaActiva === 'cargos' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300', 'whitespace-nowrap pb-3 px-1 border-b-2 font-medium text-sm']">Cargos Adicionales</button>
                                            <button @click="cambiarPestana('pagos')" :class="[pestanaActiva === 'pagos' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300', 'whitespace-nowrap pb-3 px-1 border-b-2 font-medium text-sm']">Historial de Pagos</button>
                                            <button @click="cambiarPestana('actuaciones')" :class="[pestanaActiva === 'actuaciones' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300', 'whitespace-nowrap pb-3 px-1 border-b-2 font-medium text-sm']">Actuaciones</button>
                                        </nav>
                                    </div>

                                    <div class="mt-6 px-4 sm:px-6 pb-6">
                                        <div v-if="pestanaActiva === 'cuotas'" class="space-y-4">
                                            <div v-if="!(props.cuotas?.data || []).length" class="text-center py-12 text-gray-500 dark:text-gray-400">
                                                <p>No hay cuotas definidas para este contrato.</p>
                                            </div>
                                            <div v-for="q in props.cuotas.data" :key="q.id"
                                                 class="border rounded-lg p-4 transition-all"
                                                 :class="q.estado === 'PAGADA' ? 'bg-emerald-50/50 dark:bg-emerald-900/10 border-emerald-200 dark:border-emerald-800' : (q.estado === 'PAGO_PARCIAL' ? 'bg-blue-50/50 dark:bg-blue-900/10 border-blue-200 dark:border-blue-800' : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700')">
                                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                                    <div class="flex-1">
                                                        <p class="font-bold text-lg text-gray-800 dark:text-gray-100">
                                                            Cuota #{{ q.numero }}
                                                            <span class="ml-2 px-2.5 py-0.5 rounded-full text-xs font-semibold"
                                                                  :class="q.estado === 'PAGADA' ? 'bg-emerald-100 text-emerald-800' : (q.estado === 'PAGO_PARCIAL' ? 'bg-blue-100 text-blue-800' : 'bg-amber-100 text-amber-800')">
                                                                {{ (q.estado === 'PAGADA' || q.fecha_pago) ? 'PAGADA' : (q.estado || 'PENDIENTE').replace('_', ' ') }}
                                                            </span>
                                                        </p>
                                                        <p class="text-sm text-gray-500">Vence: {{ fmtDate(q.fecha_vencimiento) }}</p>
                                                    </div>
                                                    <div class="w-full sm:w-auto grid grid-cols-2 sm:flex sm:items-center gap-4 text-right">
                                                        <div class="sm:pr-4 sm:border-r dark:border-gray-600">
                                                            <p class="text-xs text-gray-500">Valor</p>
                                                            <p class="font-mono">{{ fmtMoney(q.valor) }}</p>
                                                        </div>
                                                        <div class="sm:pr-4 sm:border-r dark:border-gray-600">
                                                            <p class="text-xs text-red-500">Mora</p>
                                                            <p class="font-mono text-red-600">{{ fmtMoney(q.intereses_mora_acumulados) }}</p>
                                                        </div>
                                                        <div class="sm:pr-4 sm:border-r dark:border-gray-600">
                                                            <p class="text-xs text-gray-500">Pagado</p>
                                                            <p class="font-mono text-emerald-600">{{ fmtMoney(cuotaPagado(q)) }}</p>
                                                        </div>
                                                        <div class="col-span-2 sm:col-span-1">
                                                            <p class="text-xs text-gray-500">Pendiente</p>
                                                            <p class="font-bold text-lg font-mono text-indigo-600 dark:text-indigo-400">{{ fmtMoney(cuotaRestoConMora(q)) }}</p>
                                                        </div>
                                                    </div>
                                                    <div class="flex-shrink-0">
                                                        <button
                                                            v-if="props.contrato.estado !== 'CERRADO' && q.estado !== 'PAGADA' && !q.fecha_pago"
                                                            @click="abrirPagoCuotaModal(q)"
                                                            class="w-full sm:w-auto px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">
                                                            Pagar
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            <Pagination class="mt-6" :links="cuotasLinks" @navigate="navegarPagina"/>
                                        </div>

                                        <div v-if="pestanaActiva === 'cargos'">
                                            <div v-if="!(props.cargos?.data || []).length" class="text-center py-8 text-gray-500 dark:text-gray-400">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" /></svg>
                                                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">Sin cargos adicionales</h3>
                                                <p class="mt-1 text-sm text-gray-500">Puedes añadir gastos desde el botón "Añadir Gasto".</p>
                                            </div>
                                            <div v-else class="space-y-4">
                                                <div v-for="c in props.cargos.data" :key="c.id"
                                                     :class="[
                                                      'border rounded-lg transition-all',
                                                      c.estado === 'PAGADO'
                                                          ? 'bg-emerald-50/50 dark:bg-emerald-900/10 border-emerald-200 dark:border-emerald-900'
                                                          : (String(c.tipo || '') === 'INTERES_MORA'
                                                              ? 'bg-red-50/50 dark:bg-red-900/10 border-red-200 dark:border-red-900'
                                                              : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700')
                                                    ]">
                                                    <div class="flex items-start sm:items-center gap-4 p-4 flex-col sm:flex-row">
                                                        <div class="flex-shrink-0">
                                                            <span v-if="String(c.tipo || '') === 'INTERES_MORA'" class="inline-flex justify-center items-center w-8 h-8 rounded-full bg-red-100 dark:bg-red-900/50 text-red-700 dark:text-red-300">
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd" /></svg>
                                                            </span>
                                                            <span v-else-if="String(c.tipo || '') === 'LITIS'" class="inline-flex justify-center items-center w-8 h-8 rounded-full bg-purple-100 dark:bg-purple-900/50 text-purple-700 dark:text-purple-300">
                                                               <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" /></svg>
                                                            </span>
                                                            <span v-else class="inline-flex justify-center items-center w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300">
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M4 4a2 2 0 00-2 2v1h16V6a2 2 0 00-2-2H4z" /><path fill-rule="evenodd" d="M18 9H2v5a2 2 0 002 2h12a2 2 0 002-2V9zM4 13a1 1 0 011-1h1a1 1 0 110 2H5a1 1 0 01-1-1zm5-1a1 1 0 100 2h1a1 1 0 100-2H9z" clip-rule="evenodd" /></svg>
                                                            </span>
                                                        </div>
                                                        <div class="flex-1 space-y-1 w-full">
                                                            <div class="flex items-center gap-3 text-sm flex-wrap">
                                                                <span class="font-bold text-gray-800 dark:text-gray-100">
                                                                    <template v-if="String(c.tipo || '') !== 'INTERES_MORA' && Number(c.intereses_mora_acumulados || 0) > 0">
                                                                        {{ fmtMoney(cargoTotalAPagar(c)) }}
                                                                    </template>
                                                                    <template v-else>
                                                                        {{ fmtMoney(c.monto) }}
                                                                    </template>
                                                                </span>
                                                                <span class="px-2 py-0.5 text-xs rounded-full font-semibold"
                                                                      :class="String(c.tipo || '') === 'INTERES_MORA'
                                                                          ? 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300'
                                                                          : (String(c.tipo || '') === 'LITIS' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-300' : 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300')">
                                                                    {{ String(c.tipo || '').replace('_', ' ') || 'CARGO' }}
                                                                </span>
                                                                <span class="text-gray-500">Aplicado el {{ fmtDate(c.fecha_aplicado) }}</span>
                                                            </div>
                                                            <p v-if="String(c.tipo || '') === 'INTERES_MORA'" class="text-xs sm:text-sm text-red-700 dark:text-red-300 font-medium">
                                                                {{ textoRefMora(c) }}
                                                            </p>
                                                            <p v-if="String(c.tipo || '') !== 'INTERES_MORA' && Number(c.intereses_mora_acumulados || 0) > 0" class="text-xs sm:text-sm text-red-700 dark:text-red-300 font-medium">
                                                                (Valor original: {{ fmtMoney(c.monto) }} + {{ fmtMoney(c.intereses_mora_acumulados) }} de mora)
                                                            </p>
                                                            <p class="text-sm text-gray-600 dark:text-gray-400" v-text="c.descripcion"></p>
                                                            <a v-if="c.comprobante" :href="cargoComprobanteCreacionUrl(c)" target="_blank" class="text-xs text-indigo-600 hover:underline font-semibold">Ver comprobante de creación</a>
                                                        </div>
                                                        <div class="flex items-center gap-4 self-end sm:self-center">
                                                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold" :class="c.estado === 'PAGADO' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">
                                                                {{ c.estado }}
                                                            </span>
                                                            <button v-if="c.estado !== 'PAGADO' && props.contrato.estado !== 'CERRADO'"
                                                                    @click="abrirPagoCargoModal(c)"
                                                                    class="px-3 py-1.5 rounded-md bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700">
                                                                Pagar
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <div v-if="c.estado === 'PAGADO'" class="bg-emerald-50 dark:bg-gray-700/50 border-t border-emerald-200 dark:border-emerald-800 px-4 py-3 text-sm">
                                                        <p class="font-semibold text-emerald-800 dark:text-emerald-300">Pagado el {{ fmtDate(c.fecha_pago_cargo) }} vía {{ c.metodo_pago_cargo }}.</p>
                                                        <p v-if="c.nota_pago_cargo" class="text-gray-600 dark:text-gray-400 mt-1 italic">"{{ c.nota_pago_cargo }}"</p>
                                                        <a v-if="c.comprobante_pago_cargo" :href="cargoComprobantePagoUrl(c)" target="_blank" class="mt-1 inline-block text-xs text-indigo-600 hover:underline font-semibold">Ver comprobante de pago</a>
                                                    </div>
                                                </div>
                                                <div class="mt-6 pt-4 border-t-2 border-dashed dark:border-gray-700 text-right">
                                                    <p class="text-sm text-gray-500">Total Cargos: <span class="font-bold text-gray-800 dark:text-gray-200">{{ fmtMoney(valorTotalCargosConMora) }}</span></p>
                                                    <p v-if="totalInteresesMora > 0" class="text-xs text-red-600 dark:text-red-400">(Incluye {{ fmtMoney(totalInteresesMora) }} en mora)</p>
                                                    <p class="text-sm text-amber-600 mt-1">Total Pendiente: <span class="font-bold">{{ fmtMoney(totalCargosPendientes) }}</span></p>
                                                </div>
                                                <Pagination class="mt-6" :links="cargosLinks" @navigate="navegarPagina"/>
                                            </div>
                                        </div>

                                        <div v-if="pestanaActiva === 'pagos'">
                                            <div v-if="!(props.pagos?.data || []).length" class="text-center py-8 text-gray-500 dark:text-gray-400">
                                                No se han registrado pagos.
                                            </div>
                                            <div v-else class="space-y-4">
                                                <!-- Ordenar por fecha descendente -->
                                                <div v-for="p in [...(props.pagos.data || [])].sort((a,b)=> new Date(b.fecha) - new Date(a.fecha))" :key="p.id"
                                                     class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                                                        <div class="flex items-center gap-3">
                                                            <div class="flex-shrink-0 inline-flex justify-center items-center w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-700">
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-600 dark:text-gray-300" viewBox="0 0 20 20" fill="currentColor"><path d="M2 5a2 2 0 012-2h2a2 2 0 012 2v1h2V5a2 2 0 012-2h2a2 2 0 012 2v1h1a1 1 0 110 2h-1v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8H2a1 1 0 110-2h1V5zm3 3v8h10V8H5z"/></svg>
                                                            </div>
                                                            <div>
                                                                <p class="text-sm text-gray-500">Fecha</p>
                                                                <p class="font-medium">{{ fmtDate(p.fecha) }}</p>
                                                            </div>
                                                        </div>
                                                        <div class="text-center">
                                                            <span class="px-2 py-1 rounded-md text-xs font-semibold"
                                                                  :class="p.cargo_id ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'">
                                                                {{ conceptoPago(p) }}
                                                            </span>
                                                        </div>
                                                        <div class="text-right">
                                                            <p class="text-sm text-gray-500">Valor</p>
                                                            <p class="font-mono font-semibold text-gray-800 dark:text-gray-200">{{ fmtMoney(p.valor) }}</p>
                                                        </div>
                                                    </div>
                                                    <div class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
                                                        <div>
                                                            <p class="text-gray-500">Método</p>
                                                            <p class="font-medium">{{ p.metodo ? p.metodo.replace('_',' ') : '-' }}</p>
                                                        </div>
                                                        <div class="md:col-span-2">
                                                            <p class="text-gray-500">Nota</p>
                                                            <p class="text-gray-700 dark:text-gray-300 break-words">{{ p.nota || '—' }}</p>
                                                        </div>
                                                    </div>
                                                    <div class="mt-3">
                                                        <a v-if="comprobanteUrl(p)" :href="comprobanteUrl(p)" target="_blank" class="text-xs text-indigo-600 hover:underline font-semibold">Ver comprobante</a>
                                                        <span v-else class="text-xs text-gray-400">Sin comprobante</span>
                                                    </div>
                                                </div>
                                                <Pagination class="mt-6" :links="pagosLinks" @navigate="navegarPagina"/>
                                            </div>
                                        </div>

                                        <div v-if="pestanaActiva === 'actuaciones'">
                                            <form @submit.prevent="guardarActuacion" class="mb-6 pb-6 border-b dark:border-gray-700">
                                                <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Nueva Actuación Manual</h4>
                                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                                                    <div class="md:col-span-3">
                                                        <InputLabel for="actuacion_nota" value="Descripción de la Actuación" />
                                                        <Textarea
                                                            id="actuacion_nota"
                                                            v-model="actuacionForm.nota"
                                                            rows="3"
                                                            class="mt-1 block w-full"
                                                            required
                                                        />
                                                        <InputError class="mt-2" :message="actuacionForm.errors.nota" />
                                                    </div>
                                                    <div>
                                                        <InputLabel for="fecha_actuacion" value="Fecha de Actuación" />
                                                        <TextInput
                                                            id="fecha_actuacion"
                                                            type="date"
                                                            v-model="actuacionForm.fecha_actuacion"
                                                            class="mt-1 block w-full"
                                                            required
                                                        />
                                                        <InputError class="mt-2" :message="actuacionForm.errors.fecha_actuacion" />
                                                    </div>
                                                </div>
                                                <div class="flex justify-end">
                                                    <PrimaryButton :disabled="actuacionForm.processing">
                                                        {{ actuacionForm.processing ? 'Registrando...' : 'Registrar Actuación' }}
                                                    </PrimaryButton>
                                                </div>
                                            </form>

                                            <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Historial de Actuaciones</h4>
                                            <div v-if="!props.actuaciones || props.actuaciones.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
                                                No hay actuaciones registradas para este contrato.
                                            </div>
                                            <div v-else class="space-y-4">
                                                <div v-for="actuacion in props.actuaciones" :key="actuacion.id" class="p-4 border rounded-lg bg-gray-50 dark:bg-gray-800/50 dark:border-gray-700">
                                                    <div class="flex items-start justify-between">
                                                        <p class="text-sm text-gray-800 dark:text-gray-200 whitespace-pre-wrap flex-1">{{ actuacion.nota }}</p>
                                                         <div v-if="$page.props.auth.user" class="flex-shrink-0 flex items-center gap-2 ml-4">
                                                            <button @click="abrirModalEditar(actuacion)" type="button" class="text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 p-1 rounded-md" title="Editar">
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                                            </button>
                                                            <button @click="abrirConfirmarEliminarActuacion(actuacion)" type="button" class="text-gray-400 hover:text-red-600 dark:hover:text-red-400 p-1 rounded-md" title="Eliminar">
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                                        Registrado por: {{ actuacion.user?.name ?? 'Usuario' }}
                                                        <span v-if="actuacion.fecha_actuacion"> | Fecha Actuación: <strong>{{ fmtDate(actuacion.fecha_actuacion) }}</strong></span>
                                                        <span v-else> | Registrado el: {{ fmtDate(actuacion.created_at) }}</span>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                    <div class="lg:col-span-1 space-y-6">
                        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Información del Contrato</h3>
                                <dl class="space-y-4 text-sm">
                                    <div>
                                        <dt class="text-gray-500 dark:text-gray-400">Cliente</dt>
                                        <dd class="font-medium text-gray-800 dark:text-gray-200">
                                            <Link :href="route('personas.show', props.contrato.cliente_id)" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                                {{ nombreCliente }}
                                            </Link>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-gray-500 dark:text-gray-400">Fecha de Inicio</dt>
                                        <dd class="font-medium text-gray-800 dark:text-gray-200">{{ fmtDate(props.contrato.inicio) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-gray-500 dark:text-gray-400">Modalidad</dt>
                                        <dd class="font-medium text-gray-800 dark:text-gray-200">{{ (props.contrato.modalidad || '').replace('_',' ') }}</dd>
                                    </div>
                                    <!-- NUEVO CAMPO DE FRECUENCIA -->
                                    <div v-if="props.contrato.modalidad !== 'LITIS'">
                                        <dt class="text-gray-500 dark:text-gray-400">Frecuencia de Pago</dt>
                                        <dd class="font-medium text-gray-800 dark:text-gray-200 capitalize">
                                            {{ (props.contrato.frecuencia_pago || 'Mensual').toLowerCase() }}
                                        </dd>
                                    </div>
                                    
                                    <!-- Sección de Litis -->
                                    <div v-if="['LITIS','CUOTA_MIXTA'].includes(props.contrato.modalidad)" class="pt-4 border-t dark:border-gray-700">
                                        <dt class="text-gray-500 dark:text-gray-400">Porcentaje de Éxito:</dt>
                                        <dd class="font-bold text-purple-600 dark:text-purple-400">{{ props.contrato.porcentaje_litis }}%</dd>
                                    </div>
                                    <div v-if="Number(props.contrato.monto_base_litis) > 0">
                                        <dt class="text-gray-500 dark:text-gray-400">Monto Base del Caso:</dt>
                                        <dd class="font-medium text-gray-800 dark:text-gray-200">{{ fmtMoney(props.contrato.monto_base_litis) }}</dd>
                                    </div>
                                    <div v-if="Number(props.contrato.litis_valor_ganado) > 0">
                                        <dt class="text-gray-500 dark:text-gray-400">Valor Litis (Ganado):</dt>
                                        <dd class="font-medium text-gray-800 dark:text-gray-200">{{ fmtMoney(props.contrato.litis_valor_ganado) }}</dd>
                                    </div>

                                    <!-- Sección de Cuotas (si no es Litis puro) -->
                                    <div v-if="props.contrato.modalidad !== 'LITIS'" class="pt-4 border-t dark:border-gray-700">
                                        <dt class="text-gray-500 dark:text-gray-400">
                                            {{ props.contrato.modalidad === 'CUOTA_MIXTA' ? 'Valor Parte Fija:' : 'Valor del Contrato:' }}
                                        </dt>
                                        <dd class="font-medium text-gray-800 dark:text-gray-200">{{ fmtMoney(props.contrato.monto_total) }}</dd>
                                    </div>
                                    <div v-if="props.contrato.modalidad !== 'LITIS' && Number(props.contrato.anticipo) > 0">
                                        <dt class="text-gray-500 dark:text-gray-400">Anticipo:</dt>
                                        <dd class="font-medium text-gray-800 dark:text-gray-200">{{ fmtMoney(props.contrato.anticipo) }}</dd>
                                    </div>

                                    <!-- Asociaciones -->
                                    <div class="pt-4 border-t dark:border-gray-700">
                                        <dt class="text-gray-500 dark:text-gray-400">Estado:</dt>
                                        <dd>
                                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold"
                                                  :class="contractStatusClasses[props.contrato.estado] || contractStatusClasses['CERRADO']">
                                                {{ props.contrato.estado?.replace('_', ' ') }}
                                            </span>
                                        </dd>
                                    </div>
                                    <div v-if="props.contrato.proceso" class="pt-4 border-t dark:border-gray-700">
                                        <dt class="text-gray-500 dark:text-gray-400">Proceso Asociado:</dt>
                                        <dd>
                                            <Link 
                                                :href="route('procesos.show', props.contrato.proceso.id)" 
                                                class="font-medium text-blue-600 dark:text-blue-400 hover:underline"
                                            >
                                                Radicado #{{ props.contrato.proceso.radicado }}
                                            </Link>
                                        </dd>
                                    </div>
                                    
                                    <div v-if="props.contrato.caso" class="pt-4 border-t dark:border-gray-700">
                                        <dt class="text-gray-500 dark:text-gray-400">Caso Asociado:</dt>
                                        <dd>
                                            <Link 
                                                :href="route('casos.show', props.contrato.caso.id)" 
                                                class="font-medium text-green-600 dark:text-green-400 hover:underline flex items-center gap-1"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                                                Caso #{{ props.contrato.caso.id }}
                                            </Link>
                                        </dd>
                                    </div>
                                    
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modales de Pago -->
        <Modal :show="pagoModalAbierto" @close="cerrarPagoModal">
           <form @submit.prevent="registrarPagoCuota">
                <div class="p-6 border-b dark:border-gray-700">
                    <h4 class="font-semibold text-lg text-gray-800 dark:text-gray-100">Abonar a la cuota #{{ cuotaSeleccionada?.numero }}</h4>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <InputLabel for="pago_cuota_valor" value="Valor del Pago" />
                        <TextInput id="pago_cuota_valor" v-model.number="pagoCuotaForm.valor" type="number" step="0.01" min="0" required class="mt-1 block w-full"/>
                        <p class="mt-1 text-xs text-gray-500">Pendiente (con mora): {{ fmtMoney(cuotaRestoConMora(cuotaSeleccionada)) }}.</p>
                        <InputError :message="pagoCuotaForm.errors.valor" class="mt-2" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="pago_cuota_fecha" value="Fecha" />
                            <TextInput id="pago_cuota_fecha" v-model="pagoCuotaForm.fecha" type="date" required class="mt-1 block w-full"/>
                            <InputError :message="pagoCuotaForm.errors.fecha" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="pago_cuota_metodo" value="Método" />
                            <select id="pago_cuota_metodo" v-model="pagoCuotaForm.metodo" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm dark:bg-gray-900 dark:border-gray-700">
                                <option>TRANSFERENCIA</option>
                                <option>EFECTIVO</option>
                                <option>TARJETA</option>
                                <option>OTRO</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <InputLabel for="pago_cuota_nota" value="Nota (Opcional)" />
                        <TextInput id="pago_cuota_nota" v-model="pagoCuotaForm.nota" type="text" class="mt-1 block w-full"/>
                    </div>
                    <div>
                        <InputLabel for="pago_cuota_comp" value="Comprobante (Opcional)" />
                        <input id="pago_cuota_comp" type="file" @input="pagoCuotaForm.comprobante = $event.target.files[0]" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-blue-900/50 dark:file:text-blue-300 dark:hover:file:bg-blue-900"/>
                        <InputError :message="pagoCuotaForm.errors.comprobante" class="mt-2" />
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50 flex items-center justify-end gap-3 rounded-b-lg">
                    <SecondaryButton type="button" @click="cerrarPagoModal">Cancelar</SecondaryButton>
                    <PrimaryButton type="submit" :disabled="pagoCuotaForm.processing">
                        {{ pagoCuotaForm.processing ? 'Guardando...' : 'Guardar Pago' }}
                    </PrimaryButton>
                </div>
           </form>
        </Modal>

        <Modal :show="pagoCargoModalAbierto" @close="cerrarPagoCargoModal">
           <form @submit.prevent="registrarPagoCargo">
                <div class="p-6 border-b dark:border-gray-700">
                    <h4 class="font-semibold text-lg text-gray-800 dark:text-gray-100">Pagar Cargo Adicional</h4>
                </div>
                <div class="p-6 space-y-4">
                    <div class="p-4 rounded-md bg-gray-50 dark:bg-gray-900/50 text-sm">
                        <p class="font-medium text-gray-700 dark:text-gray-300">{{ cargoSeleccionado?.descripcion }}</p>
                        <p class="text-gray-500">
                            <template v-if="cargoSeleccionado && String(cargoSeleccionado.tipo || '') !== 'INTERES_MORA' && Number(cargoSeleccionado.intereses_mora_acumulados || 0) > 0">
                                {{ fmtMoney(cargoTotalAPagar(cargoSeleccionado)) }} <span class="text-xs">(Original: {{ fmtMoney(cargoSeleccionado?.monto) }} + Mora: {{ fmtMoney(cargoSeleccionado?.intereses_mora_acumulados) }})</span>
                            </template>
                            <template v-else>
                                {{ fmtMoney(cargoSeleccionado?.monto) }}
                            </template>
                        </p>
                    </div>
                    <div>
                        <InputLabel for="pago_cargo_valor" value="Valor del Pago" />
                        <TextInput id="pago_cargo_valor" v-model.number="pagoCargoForm.valor" type="number" step="0.01" min="0" required class="mt-1 block w-full"/>
                        <p class="mt-1 text-xs text-gray-500">
                            Debe ser igual o mayor a
                            <strong>
                                <template v-if="cargoSeleccionado && String(cargoSeleccionado.tipo || '') !== 'INTERES_MORA' && Number(cargoSeleccionado.intereses_mora_acumulados || 0) > 0">
                                    {{ fmtMoney(cargoTotalAPagar(cargoSeleccionado)) }}
                                </template>
                                <template v-else>
                                    {{ fmtMoney(cargoSeleccionado?.monto) }}
                                </template>
                            </strong>.
                        </p>
                        <InputError :message="pagoCargoForm.errors.valor" class="mt-2" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="pago_cargo_fecha" value="Fecha" />
                            <TextInput id="pago_cargo_fecha" v-model="pagoCargoForm.fecha" type="date" required class="mt-1 block w-full"/>
                            <InputError :message="pagoCargoForm.errors.fecha" class="mt-2" />
                        </div>
                        <div>
                            <InputLabel for="pago_cargo_metodo" value="Método" />
                            <select id="pago_cargo_metodo" v-model="pagoCargoForm.metodo" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm dark:bg-gray-900 dark:border-gray-700">
                                <option>TRANSFERENCIA</option>
                                <option>EFECTIVO</option>
                                <option>TARJETA</option>
                                <option>OTRO</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <InputLabel for="pago_cargo_nota" value="Nota (Opcional)" />
                        <TextInput id="pago_cargo_nota" v-model="pagoCargoForm.nota" type="text" class="mt-1 block w-full"/>
                    </div>
                    <div>
                        <InputLabel for="pago_cargo_comp" value="Comprobante (Requerido)" />
                        <input id="pago_cargo_comp" type="file" @input="pagoCargoForm.comprobante = $event.target.files[0]" required class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-blue-900/50 dark:file:text-blue-300 dark:hover:file:bg-blue-900"/>
                        <InputError :message="pagoCargoForm.errors.comprobante" class="mt-2" />
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50 flex items-center justify-end gap-3 rounded-b-lg">
                    <SecondaryButton type="button" @click="cerrarPagoCargoModal">Cancelar</SecondaryButton>
                    <PrimaryButton type="submit" :disabled="pagoCargoForm.processing">
                        {{ pagoCargoForm.processing ? 'Guardando...' : 'Guardar Pago' }}
                    </PrimaryButton>
                </div>
           </form>
        </Modal>

        <!-- Modales de Acción -->
        <Modal :show="gastoModalAbierto" @close="cerrarGastoModal">
           <form @submit.prevent="registrarGasto">
                <div class="p-6 border-b dark:border-gray-700">
                    <h4 class="font-semibold text-lg text-gray-800 dark:text-gray-100">Añadir Gasto Pagado</h4>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <InputLabel for="gasto_monto" value="Monto del Gasto" />
                        <TextInput id="gasto_monto" v-model.number="gastoForm.monto" type="number" step="0.01" min="0" required class="mt-1 block w-full"/>
                        <InputError :message="gastoForm.errors.monto" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="gasto_fecha" value="Fecha de Aplicación" />
                        <TextInput id="gasto_fecha" v-model="gastoForm.fecha" type="date" required class="mt-1 block w-full"/>
                        <InputError :message="gastoForm.errors.fecha" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="gasto_desc" value="Descripción" />
                        <TextInput id="gasto_desc" v-model="gastoForm.descripcion" type="text" required class="mt-1 block w-full"/>
                        <InputError :message="gastoForm.errors.descripcion" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="gasto_comp" value="Comprobante (Opcional)" />
                        <input id="gasto_comp" type="file" @input="gastoForm.comprobante = $event.target.files[0]" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-blue-900/50 dark:file:text-blue-300 dark:hover:file:bg-blue-900"/>
                        <InputError :message="gastoForm.errors.comprobante" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="gasto_fecha_interes" value="Fecha de Inicio de Intereses (Opcional)" />
                        <TextInput id="gasto_fecha_interes" v-model="gastoForm.fecha_inicio_intereses" type="date" class="mt-1 block w-full"/>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Si no se especifica la fecha de cobro de intereses, no se cobrarán intereses.</p>
                        <InputError :message="gastoForm.errors.fecha_inicio_intereses" class="mt-2" />
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50 flex items-center justify-end gap-3 rounded-b-lg">
                    <SecondaryButton type="button" @click="cerrarGastoModal">Cancelar</SecondaryButton>
                    <PrimaryButton type="submit" :disabled="gastoForm.processing">
                        {{ gastoForm.processing ? 'Guardando...' : 'Guardar Gasto' }}
                    </PrimaryButton>
                </div>
           </form>
        </Modal>

        <Modal :show="cierreModalAbierto" @close="cerrarCierreModal">
           <form @submit.prevent="confirmarCierre">
                <div class="p-6 border-b dark:border-gray-700">
                    <h4 class="font-semibold text-lg text-gray-800 dark:text-gray-100">Cerrar Contrato Manualmente</h4>
                </div>
                <div class="p-6 space-y-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Opcionalmente, añade un cargo final por gestión o cláusula penal. El contrato pasará a "Pagos Pendientes".
                    </p>
                    <div>
                        <InputLabel for="cierre_monto" value="Monto del Cargo (Opcional)" />
                        <TextInput id="cierre_monto" v-model.number="cierreForm.monto" type="number" step="0.01" min="0" placeholder="Ej: 50000" class="mt-1 block w-full"/>
                        <InputError :message="cierreForm.errors.monto" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="cierre_desc" value="Descripción del Cargo" />
                        <TextInput id="cierre_desc" v-model="cierreForm.descripcion" type="text" placeholder="Ej: Cláusula penal por terminación" class="mt-1 block w-full"/>
                        <InputError :message="cierreForm.errors.descripcion" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="cierre_fecha_interes" value="Fecha de Inicio de Intereses (Opcional)" />
                        <TextInput id="cierre_fecha_interes" v-model="cierreForm.fecha_inicio_intereses" type="date" class="mt-1 block w-full"/>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Si no se especifica la fecha de cobro de intereses, no se cobrarán intereses.</p>
                        <InputError :message="cierreForm.errors.fecha_inicio_intereses" class="mt-2" />
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50 flex items-center justify-end gap-3 rounded-b-lg">
                    <SecondaryButton type="button" @click="cerrarCierreModal">Cancelar</SecondaryButton>
                    <button type="submit" :disabled="cierreForm.processing" class="px-4 py-2 rounded-md bg-slate-700 text-white text-sm font-semibold hover:bg-slate-800 disabled:opacity-50">
                        {{ cierreForm.processing ? 'Cerrando...' : 'Confirmar Cierre' }}
                    </button>
                </div>
           </form>
        </Modal>

        <Modal :show="resolverLitisModalAbierto" @close="cerrarResolverLitisModal">
           <form @submit.prevent="confirmarResolucionLitis">
                <div class="p-6 border-b dark:border-gray-700">
                    <h4 class="font-semibold text-lg text-gray-800 dark:text-gray-100">Registrar Resultado del Caso (Litis)</h4>
                </div>
                <div class="p-6 space-y-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Ingresa el monto final sobre el cual se calcularán los honorarios del {{ props.contrato.porcentaje_litis }}%. Esto generará un cargo final en el contrato.
                    </p>
                    <div>
                        <InputLabel for="litis_monto" value="Monto Base Final" />
                        <TextInput id="litis_monto" v-model.number="resolverLitisForm.monto_base_litis" type="number" step="0.01" min="0" required placeholder="Ej: 15000000" class="mt-1 block w-full"/>
                        <InputError :message="resolverLitisForm.errors.monto_base_litis" class="mt-2" />
                    </div>
                    <div>
                        <InputLabel for="litis_fecha_interes" value="Fecha de Inicio de Intereses (Opcional)" />
                        <TextInput id="litis_fecha_interes" v-model="resolverLitisForm.fecha_inicio_intereses" type="date"
                        class="mt-1 block w-full"/>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Si no se especifica la fecha de cobro de intereses, no se cobrarán intereses.</p>
                        <InputError :message="resolverLitisForm.errors.fecha_inicio_intereses" class="mt-2" />
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50 flex items-center justify-end gap-3 rounded-b-lg">
                    <SecondaryButton type="button" @click="cerrarResolverLitisModal">Cancelar</SecondaryButton>
                    <button type="submit" :disabled="resolverLitisForm.processing" class="px-4 py-2 rounded-md bg-purple-600 text-white text-sm font-semibold hover:bg-purple-700 disabled:opacity-50">
                        {{ resolverLitisForm.processing ? 'Confirmando...' : 'Confirmar y Generar Cargo' }}
                    </button>
                </div>
           </form>
        </Modal>

        <Modal :show="reestructurarModalAbierto" @close="cerrarReestructurarModal">
           <form @submit.prevent="guardarNuevoContrato">
                <div class="p-6 border-b dark:border-gray-700">
                    <h4 class="font-semibold text-lg text-gray-800 dark:text-gray-100">
                        Reestructurar Contrato #{{ props.contrato.id }}
                    </h4>
                </div>
                <div class="p-6 space-y-6">
                    <div>
                         <InputLabel for="cliente_search_reestructurar" value="Cliente" />
                         <TextInput
                                id="cliente_search_reestructurar"
                                type="text"
                                v-model="clienteSearch"
                                disabled
                                class="mt-1 block w-full disabled:bg-gray-100 dark:disabled:bg-gray-800/50"
                        />
                         <InputError class="mt-2" :message="crearContratoForm.errors.cliente_id" />
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <InputLabel for="modalidad_reestructurar" value="Modalidad del Contrato" />
                            <select
                                v-model="crearContratoForm.modalidad"
                                id="modalidad_reestructurar"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm dark:bg-gray-900 dark:border-gray-700"
                            >
                                <option v-for="mod in props.modalidades" :key="mod" :value="mod">{{ mod.replace('_',' ') }}</option>
                            </select>
                            <InputError class="mt-2" :message="crearContratoForm.errors.modalidad" />
                        </div>
                        <div>
                            <InputLabel for="inicio_reestructurar" value="Fecha de Inicio" />
                            <TextInput id="inicio_reestructurar" type="date" v-model="crearContratoForm.inicio" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="crearContratoForm.errors.inicio" />
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <template v-if="['CUOTAS','PAGO_UNICO','CUOTA_MIXTA'].includes(crearContratoForm.modalidad)">
                            <div>
                                <InputLabel for="monto_total_reestructurar" value="Monto Total (Parte Fija)" />
                                <TextInput id="monto_total_reestructurar" type="number" step="0.01" v-model="crearContratoForm.monto_total" class="mt-1 block w-full" placeholder="Ej: 5000000" />
                                <InputError class="mt-2" :message="crearContratoForm.errors.monto_total" />
                            </div>
                            <div v-if="crearContratoForm.modalidad !== 'PAGO_UNICO'">
                                <InputLabel for="cuotas_reestructurar" value="Número de Cuotas" />
                                <TextInput id="cuotas_reestructurar" type="number" v-model="crearContratoForm.cuotas" class="mt-1 block w-full" placeholder="Ej: 12" />
                                <InputError class="mt-2" :message="crearContratoForm.errors.cuotas" />
                            </div>
                            <!-- NUEVO: SELECTOR DE FRECUENCIA EN REESTRUCTURAR -->
                            <div v-if="crearContratoForm.modalidad !== 'PAGO_UNICO'">
                                <InputLabel for="frecuencia_reestructurar" value="Frecuencia de Pago" />
                                <select v-model="crearContratoForm.frecuencia_pago" id="frecuencia_reestructurar" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm dark:bg-gray-900 dark:border-gray-700">
                                    <option v-for="freq in frecuencias" :key="freq.value" :value="freq.value">{{ freq.label }}</option>
                                </select>
                            </div>
                            <div>
                                <InputLabel for="anticipo_reestructurar" value="Anticipo (Opcional)" />
                                <TextInput id="anticipo_reestructurar" type="number" step="0.01" v-model="crearContratoForm.anticipo" class="mt-1 block w-full" placeholder="Ej: 1000000" />
                                <InputError class="mt-2" :message="crearContratoForm.errors.anticipo" />
                            </div>
                        </template>
                        <template v-if="['LITIS','CUOTA_MIXTA'].includes(crearContratoForm.modalidad)">
                            <div>
                                <InputLabel for="porcentaje_litis_reestructurar" value="Porcentaje de Éxito (%)" />
                                <TextInput id="porcentaje_litis_reestructurar" type="number" step="0.01" v-model="crearContratoForm.porcentaje_litis" class="mt-1 block w-full" placeholder="Ej: 20" />
                                <InputError class="mt-2" :message="crearContratoForm.errors.porcentaje_litis" />
                            </div>
                        </template>
                    </div>
                    <div>
                        <InputLabel for="nota_reestructurar" value="Nota (Opcional)" />
                        <Textarea id="nota_reestructurar" v-model="crearContratoForm.nota" rows="3" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="crearContratoForm.errors.nota" />
                    </div>
                    <input type="hidden" v-model="crearContratoForm.contrato_origen_id">
                </div>
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50 flex items-center justify-end gap-3 rounded-b-lg">
                    <SecondaryButton type="button" @click="cerrarReestructurarModal">Cancelar</SecondaryButton>
                    <PrimaryButton type="submit" :disabled="crearContratoForm.processing">
                        {{ crearContratoForm.processing ? 'Guardando...' : 'Guardar Contrato' }}
                    </PrimaryButton>
                </div>
           </form>
        </Modal>

        <Modal :show="subirDocumentoModalAbierto" @close="cerrarSubirDocumentoModal">
           <form @submit.prevent="subirDocumento">
                <div class="p-6 border-b dark:border-gray-700">
                    <h4 class="font-semibold text-lg text-gray-800 dark:text-gray-100">
                        {{ props.contrato.documento_contrato ? 'Reemplazar Documento del Contrato' : 'Subir Documento del Contrato' }}
                    </h4>
                </div>
                <div class="p-6 space-y-4">
                    <div v-if="props.contrato.documento_contrato" class="p-4 rounded-md bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
                        <div class="flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.314 16.5c-.77.833.192 2.5 1.732 2.5z" />
                            </svg>
                            <span class="text-sm text-amber-800 dark:text-amber-300 font-medium">
                                Este contrato ya tiene un documento. Al subir uno nuevo, el anterior será reemplazado.
                            </span>
                        </div>
                    </div>

                    <div>
                        <InputLabel for="doc_input_file" value="Documento del Contrato (PDF únicamente)" />
                        <input type="file"
                               id="doc_input_file"
                               accept=".pdf"
                               @input="documentoForm.documento = $event.target.files[0]"
                               required
                               class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-green-50 file:text-green-700 hover:file:bg-green-100 dark:file:bg-green-900/50 dark:file:text-green-300 dark:hover:file:bg-green-900"/>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Archivo máximo: 10MB. Solo se permiten archivos PDF.
                        </p>
                        <InputError :message="documentoForm.errors.documento" class="mt-2" />
                    </div>

                    <div v-if="documentoForm.documento" class="p-3 bg-gray-50 dark:bg-gray-900/50 rounded-md">
                        <div class="flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ documentoForm.documento.name }}</p>
                                <p class="text-xs text-gray-500">{{ (documentoForm.documento.size / 1024 / 1024).toFixed(2) }} MB</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50 flex items-center justify-end gap-3 rounded-b-lg">
                    <SecondaryButton type="button" @click="cerrarSubirDocumentoModal">Cancelar</SecondaryButton>
                    <PrimaryButton type="submit" :disabled="documentoForm.processing || !documentoForm.documento">
                        {{ documentoForm.processing ? 'Subiendo...' : (props.contrato.documento_contrato ? 'Reemplazar Documento' : 'Subir Documento') }}
                    </PrimaryButton>
                </div>
           </form>
        </Modal>

        <Modal :show="confirmandoEliminacion" @close="cerrarModalConfirmarEliminacion">
            <form @submit.prevent="eliminarContrato" class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    ¿Estás seguro de que deseas eliminar este contrato?
                </h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Esta acción es <strong>permanente e irreversible</strong>. Se eliminará el contrato, junto con todas sus cuotas, cargos, pagos y actuaciones asociadas.
                </p>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Si el contrato solo tiene errores, considera usar la opción de <strong>"Reestructurar"</strong>.
                </p>
                <p class="mt-4 font-bold text-gray-800 dark:text-gray-200">
                    Contrato #{{ props.contrato.id }} - {{ nombreCliente }}
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="cerrarModalConfirmarEliminacion"> Cancelar </SecondaryButton>
                    <button type="submit"
                            :class="['px-4 py-2 rounded-md text-sm font-semibold text-white', deleteForm.processing ? 'bg-red-400' : 'bg-red-600 hover:bg-red-700']"
                            :disabled="deleteForm.processing">
                        {{ deleteForm.processing ? 'Eliminando...' : 'Sí, Eliminar Permanentemente' }}
                    </button>
                </div>
            </form>
        </Modal>

        <Modal :show="editActuacionModalAbierto" @close="cerrarModalEditar">
            <form @submit.prevent="actualizarActuacion" class="p-6">
                 <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                     Editar Actuación Manual
                 </h2>

                 <div class="mt-6 space-y-4">
                      <div>
                           <InputLabel for="edit_actuacion_nota" value="Descripción de la Actuación" />
                           <Textarea
                                id="edit_actuacion_nota"
                                v-model="editActuacionForm.nota"
                                rows="4"
                                class="mt-1 block w-full"
                                required
                           />
                           <InputError class="mt-2" :message="editActuacionForm.errors.nota" />
                      </div>
                      <div>
                           <InputLabel for="edit_fecha_actuacion" value="Fecha de Actuación" />
                           <TextInput
                                id="edit_fecha_actuacion"
                                type="date"
                                v-model="editActuacionForm.fecha_actuacion"
                                class="mt-1 block w-full"
                                required
                           />
                           <InputError class="mt-2" :message="editActuacionForm.errors.fecha_actuacion" />
                      </div>
                 </div>

                 <div class="mt-6 flex justify-end gap-3">
                      <SecondaryButton type="button" @click="cerrarModalEditar"> Cancelar </SecondaryButton>
                      <PrimaryButton type="submit" :disabled="editActuacionForm.processing">
                           {{ editActuacionForm.processing ? 'Guardando...' : 'Guardar Cambios' }}
                      </PrimaryButton>
                 </div>
            </form>
        </Modal>
        
        <!-- Modal Confirmar Eliminar Actuación -->
        <Modal :show="confirmandoEliminarActuacion" @close="cerrarConfirmarEliminarActuacion">
            <form @submit.prevent="eliminarActuacionConfirmado" class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    ¿Eliminar Actuación?
                </h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    ¿Estás seguro de que quieres eliminar esta actuación? Esta acción no se puede deshacer.
                </p>
                 <p class="mt-2 text-sm text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 p-3 rounded-md italic truncate">
                    "{{ actuacionAEliminar?.nota }}"
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="cerrarConfirmarEliminarActuacion"> Cancelar </SecondaryButton>
                    <button type="submit" class="px-4 py-2 rounded-md text-sm font-semibold text-white bg-red-600 hover:bg-red-700">
                        Eliminar
                    </button>
                </div>
            </form>
        </Modal>
        
        <!-- Modal Confirmar Eliminar Documento -->
        <Modal :show="confirmandoEliminarDoc" @close="cerrarConfirmarEliminarDoc">
            <form @submit.prevent="eliminarDocumento" class="p-6">
                <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    ¿Eliminar Documento?
                </h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    ¿Estás seguro de que quieres eliminar el documento PDF asociado a este contrato? Esta acción no se puede deshacer.
                </p>
                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="cerrarConfirmarEliminarDoc"> Cancelar </SecondaryButton>
                    <button type="submit" 
                        :class="['px-4 py-2 rounded-md text-sm font-semibold text-white', accionesForm.processing ? 'bg-red-400' : 'bg-red-600 hover:bg-red-700']"
                        :disabled="accionesForm.processing">
                        {{ accionesForm.processing ? 'Eliminando...' : 'Sí, Eliminar Documento' }}
                    </button>
                </div>
            </form>
        </Modal>

    </AuthenticatedLayout>
</template>