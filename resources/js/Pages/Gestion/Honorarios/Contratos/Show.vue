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
  total_cargos_valor: { type: Number, default: 0 },
  total_pagos_valor:  { type: Number, default: 0 },
  clientes:   { type: Array,  default: () => [] },
  modalidades:{ type: Array,  default: () => [] },
})

// --- FORMULARIO PARA ACCIONES GENERALES ---
const accionesForm = useForm({})

// --- HELPERS ---
const fmtMoney = (n) =>
  new Intl.NumberFormat('es-CO', {
    style: 'currency',
    currency: 'COP',
    maximumFractionDigits: 0
  }).format(Number(n || 0))

const fmtDate  = (d) =>
  d ? new Date(String(d).replace(/-/g, '/')).toLocaleDateString('es-CO', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A'

const today    = new Date().toISOString().slice(0, 10)

const parseDate = (d) => {
  if (!d) return null
  const dt = new Date(String(d).replace(/-/g, '/'))
  return isNaN(dt.getTime()) ? null : dt
}
const diffDays = (from, to) => {
  const a = parseDate(from)
  const b = parseDate(to || new Date().toISOString().slice(0,10))
  if (!a || !b) return null
  const a0 = new Date(a.getFullYear(), a.getMonth(), a.getDate())
  const b0 = new Date(b.getFullYear(), b.getMonth(), b.getDate())
  return Math.floor((b0 - a0) / 86400000)
}

// =========================
// === LÓGICA / CÁLCULOS ===
// =========================
const nombreCliente = computed(() => props.cliente?.nombre ?? `ID ${props.cliente?.id}`)

// ====================================================================================
// INICIO DE LA CORRECCIÓN CLAVE: Simplificar el cálculo del valor total del contrato
// ====================================================================================
const valorTotalContrato = computed(() => {
  const c = props.contrato || {}
  const modalidad = String(c.modalidad || '')

  // Para LITIS puro, el valor base del contrato es $0. Los honorarios son un CARGO, no parte del valor inicial.
  if (modalidad === 'LITIS') {
    return 0
  }

  // Para CUOTA_MIXTA, el valor del contrato es ÚNICAMENTE la parte fija. El componente Litis es un CARGO.
  // Para el resto de modalidades (CUOTAS, PAGO_UNICO), es simplemente el monto total.
  return Number(c.monto_total || 0)
})
// ====================================================================================
// FIN DE LA CORRECCIÓN
// ====================================================================================


const netoContrato = computed(() => {
  const anticipo = Number(props.contrato?.anticipo) || 0
  return Math.max(0, valorTotalContrato.value - anticipo)
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

const saldo = computed(() => Math.max(0, netoContrato.value + valorTotalCargosConMora.value - totalPagosValor.value))

const totalCargosPendientes = computed(() => {
  if (!props.cargos?.data) return 0
  return props.cargos.data
    .filter(c => c.estado === 'PENDIENTE')
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
  cargo?.comprobante_pago_cargo ? `/storage/${cargo.comprobante_pago_cargo}` : null

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
const cuotaTotalAPagar = (c) => Number(c?.valor || 0) + Number(c?.intereses_mora_acumulados || 0)
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
const pagoModalAbierto   = ref(false)
const cuotaSeleccionada  = ref(null)
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
  pagoCargoForm.post(route('honorarios.contratos.cargos.pagar', props.contrato.id), {
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
    }
  })
}

const toggleDocumentoMenu = () => {
  documentoMenuAbierto.value = !documentoMenuAbierto.value
}

const eliminarDocumento = () => {
  if (confirm('¿Estás seguro de que quieres eliminar el documento? Esta acción no se puede deshacer.')) {
    accionesForm.delete(route('honorarios.contratos.documento.eliminar', props.contrato.id), {
      preserveScroll: true
    })
  }
}

// Cerrar menú cuando se hace clic fuera
const documentoMenuRef = ref(null)
onClickOutside(documentoMenuRef, () => documentoMenuAbierto.value = false)
const handleCerrarContratoClick = () => {
  const c = props.contrato
  if (['LITIS', 'CUOTA_MIXTA'].includes(c?.modalidad) && !Number(c?.monto_base_litis) && !Number(c?.litis_valor_ganado)) {
    abrirResolverLitisModal()
  } else {
    abrirCierreModal()
  }
}

// ===================
// === UI / TABS   ===
const contractStatusClasses = {
  'ACTIVO':           'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300',
  'PAGOS_PENDIENTES': 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300',
  'CERRADO':          'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
}

const pestanaActiva = ref('cargos')
const cambiarPestana = (nuevaPestana) => {
  pestanaActiva.value = nuevaPestana
  const url = new URL(window.location)
  url.searchParams.set('tab', nuevaPestana)
  window.history.replaceState({}, '', url)
}
onMounted(() => {
  const tabFromUrl = new URLSearchParams(window.location.search).get('tab')
  if (tabFromUrl && ['cuotas', 'cargos', 'pagos'].includes(tabFromUrl)) pestanaActiva.value = tabFromUrl
})

const withTab = (url) => {
  if (!url) return null
  try {
    const u = new URL(url, window.location.origin)
    u.searchParams.set('tab', pestanaActiva.value)
    return u.toString()
  } catch {
    return url
  }
}
const cuotasLinks = computed(() => (props.cuotas?.links || []).map(l => ({ ...l, url: withTab(l.url) })))
const cargosLinks = computed(() => (props.cargos?.links || []).map(l => ({ ...l, url: withTab(l.url) })))
const pagosLinks  = computed(() => (props.pagos?.links  || []).map(l => ({ ...l, url: withTab(l.url) })))

const navegarPagina = (url) => {
  if (!url) return
  const nuevaUrl = new URL(url, window.location.origin)
  nuevaUrl.searchParams.set('tab', pestanaActiva.value)
  router.get(nuevaUrl.toString(), {}, {
    preserveScroll: true,
    preserveState: true,
    only: ['cuotas', 'pagos', 'cargos', 'total_cargos_valor', 'total_pagos_valor']
  })
}

// ======================================================================
// === MODAL DE REESTRUCTURACIÓN ===
// ======================================================================
const reestructurarModalAbierto = ref(false)
const crearContratoForm = useForm({
  cliente_id: null,
  modalidad: 'CUOTAS',
  inicio: new Date().toISOString().slice(0, 10),
  monto_total: '',
  cuotas: 12,
  anticipo: '',
  porcentaje_litis: '',
  nota: '',
  contrato_origen_id: null,
})

const clienteSearch = ref('')
const selectedClientName = ref('')
const isClientListOpen = ref(false)
const clientDropdown = ref(null)

const filteredClients = computed(() => {
    if (!clienteSearch.value) return props.clientes.slice(0, 10)
    return props.clientes.filter(c => 
        c.nombre.toLowerCase().includes(clienteSearch.value.toLowerCase())
    ).slice(0, 10)
})

const selectClient = (client) => {
    crearContratoForm.cliente_id = client.id
    selectedClientName.value = client.nombre
    clienteSearch.value = client.nombre
    isClientListOpen.value = false
}

watch(clienteSearch, (newVal) => {
    if (newVal !== selectedClientName.value) {
        crearContratoForm.cliente_id = null
    }
})

onClickOutside(clientDropdown, () => isClientListOpen.value = false)

const abrirReestructurarModal = () => {
  crearContratoForm.defaults({
    cliente_id: props.contrato?.cliente_id ?? null,
    modalidad: props.contrato?.modalidad ?? 'CUOTAS',
    inicio: new Date().toISOString().slice(0, 10),
    monto_total: props.contrato?.monto_total ?? '',
    cuotas: 12,
    anticipo: props.contrato?.anticipo ?? '',
    porcentaje_litis: props.contrato?.porcentaje_litis ?? '',
    nota: `Reestructuración del contrato #${props.contrato?.id ?? ''}.`,
    contrato_origen_id: props.contrato?.id ?? null,
  })
  crearContratoForm.reset()

  const clienteOriginal = props.clientes.find(c => c.id === props.contrato.cliente_id)
  if (clienteOriginal) {
      clienteSearch.value = clienteOriginal.nombre
      selectedClientName.value = clienteOriginal.nombre
  } else {
      clienteSearch.value = ''
      selectedClientName.value = ''
  }

  reestructurarModalAbierto.value = true
}

// ======================================================================
// === INICIO DE LA CORRECCIÓN: Watcher para la modalidad
// ======================================================================
watch(() => crearContratoForm.modalidad, (newModalidad) => {
  if (newModalidad === 'PAGO_UNICO') {
    // Si es pago único, las cuotas son 1 por definición.
    crearContratoForm.cuotas = 1;
  } else if (newModalidad === 'CUOTAS' || newModalidad === 'CUOTA_MIXTA') {
    // Opcional: restaurar un valor por defecto al cambiar a una modalidad de cuotas
    crearContratoForm.cuotas = 12;
  }
});
// ======================================================================
// === FIN DE LA CORRECCIÓN
// ======================================================================

const cerrarReestructurarModal = () => { reestructurarModalAbierto.value = false }
const guardarNuevoContrato = () => {
  crearContratoForm.post(route('honorarios.contratos.store'), {
    preserveScroll: true,
    onSuccess: cerrarReestructurarModal,
  })
}
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

        <div class="flex items-center gap-2 flex-wrap">
          <Link :href="route('honorarios.contratos.index')" class="text-sm px-3 py-1.5 rounded-md bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            Volver
          </Link>
          <template v-if="['ACTIVO','PAGOS_PENDIENTES'].includes(props.contrato?.estado)">
            <button @click="abrirGastoModal" class="text-sm px-3 py-1.5 rounded-md bg-blue-50 dark:bg-blue-900/40 text-blue-700 dark:text-blue-200 border border-blue-200 dark:border-blue-800 hover:bg-blue-100 dark:hover:bg-blue-900">
              Añadir Gasto
            </button>
          </template>

          <template v-if="props.contrato?.estado === 'ACTIVO'">
            <button @click="handleCerrarContratoClick" class="text-sm px-3 py-1.5 rounded-md bg-slate-700 text-white hover:bg-slate-800">
              {{ ['LITIS','CUOTA_MIXTA'].includes(props.contrato?.modalidad) && !Number(props.contrato?.monto_base_litis) && !Number(props.contrato?.litis_valor_ganado) ? 'Registrar Resultado y Cerrar' : 'Cerrar Contrato' }}
            </button>
          </template>

          <template v-if="props.contrato?.estado === 'PAGOS_PENDIENTES'">
            <button v-if="saldo <= 0" @click="accionesForm.post(route('honorarios.contratos.saldar', props.contrato.id), { preserveScroll: true })" class="text-sm px-3 py-1.5 rounded-md bg-emerald-600 text-white hover:bg-emerald-700">
              Saldar y Cerrar
            </button>
            <button @click="accionesForm.post(route('honorarios.contratos.reabrir', props.contrato.id), { preserveScroll: true })" class="text-sm px-3 py-1.5 rounded-md bg-amber-600 text-white hover:bg-amber-700">
              Reabrir
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

          <div class="relative">
            <template v-if="!props.contrato.documento_contrato">
              <button @click="abrirSubirDocumentoModal"
                      class="text-sm px-3 py-1.5 rounded-md bg-green-50 dark:bg-green-900/40 text-green-700 dark:text-green-200 border border-green-200 dark:border-green-800 hover:bg-green-100 dark:hover:bg-green-900 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                </svg>
                Subir Documento
              </button>
            </template>

            <template v-else>
              <div class="flex items-center gap-2">
                <a :href="route('honorarios.contratos.documento.ver', props.contrato.id)"
                   target="_blank" rel="noopener"
                   class="text-sm px-3 py-1.5 rounded-md bg-blue-50 dark:bg-blue-900/40 text-blue-700 dark:text-blue-200 border border-blue-200 dark:border-blue-800 hover:bg-blue-100 dark:hover:bg-blue-900 flex items-center gap-2">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                  Ver Documento
                </a>
                <div class="relative" ref="documentoMenuRef">
                  <button @click="toggleDocumentoMenu"
                          class="text-sm px-2 py-1.5 rounded-md bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-600">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                    </svg>
                  </button>
                  <div v-if="documentoMenuAbierto" class="absolute right-0 top-full mt-1 w-48 bg-white dark:bg-gray-800 rounded-md shadow-lg border border-gray-200 dark:border-gray-700 z-10">
                    <button @click="abrirSubirDocumentoModal(); documentoMenuAbierto = false"
                            class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-2">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                      </svg>
                      Reemplazar Documento
                    </button>
                    <button @click="eliminarDocumento(); documentoMenuAbierto = false"
                            class="w-full text-left px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 flex items-center gap-2">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                      </svg>
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
            <div class="text-sm text-gray-500 dark:text-gray-400">Neto Contrato</div>
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
              <div class="p-4 sm:p-6">
                <div class="border-b border-gray-200 dark:border-gray-700">
                  <nav class="-mb-px flex space-x-4 sm:space-x-6 overflow-x-auto" aria-label="Tabs">
                    <button @click="cambiarPestana('cuotas')" :class="[pestanaActiva === 'cuotas' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700', 'whitespace-nowrap pb-3 px-1 border-b-2 font-medium text-sm']">Plan de Pagos</button>
                    <button @click="cambiarPestana('cargos')" :class="[pestanaActiva === 'cargos' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700', 'whitespace-nowrap pb-3 px-1 border-b-2 font-medium text-sm']">Cargos Adicionales</button>
                    <button @click="cambiarPestana('pagos')" :class="[pestanaActiva === 'pagos' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700', 'whitespace-nowrap pb-3 px-1 border-b-2 font-medium text-sm']">Historial de Pagos</button>
                  </nav>
                </div>

                <div class="mt-6">
                  <div v-if="pestanaActiva === 'cuotas'" class="space-y-4">
                    <div v-if="!(props.cuotas?.data || []).length" class="text-center py-12 text-gray-500 dark:text-gray-400">
                      <p>No hay cuotas definidas para este contrato.</p>
                    </div>
                    <div v-for="q in props.cuotas.data" :key="q.id"
                         class="border rounded-lg p-4 transition-all"
                         :class="q.estado === 'PAGADA' ? 'bg-emerald-50/50 dark:bg-emerald-900/10 border-emerald-200 dark:border-emerald-800' : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700'">
                      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="flex-1">
                          <p class="font-bold text-lg text-gray-800 dark:text-gray-100">
                            Cuota #{{ q.numero }}
                            <span class="ml-2 px-2.5 py-0.5 rounded-full text-xs font-semibold"
                                  :class="q.estado === 'PAGADA' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">
                              {{ (q.estado === 'PAGADA' || q.fecha_pago) ? 'PAGADA' : (q.estado || 'PENDIENTE') }}
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
                    <Pagination class="mt-6" :links="cuotasLinks" />
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
                                      : 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300'">
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
                            <button v-if="c.estado === 'PENDIENTE' && props.contrato.estado !== 'CERRADO'"
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
                      <Pagination class="mt-6" :links="cargosLinks" />
                    </div>
                  </div>

                  <div v-if="pestanaActiva === 'pagos'">
                    <div v-if="!(props.pagos?.data || []).length" class="text-center py-8 text-gray-500 dark:text-gray-400">
                      No se han registrado pagos.
                    </div>
                    <div v-else class="space-y-4">
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
                                  :class="p.cargo_id ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'">
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
                      <Pagination class="mt-6" :links="pagosLinks" />
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="space-y-6">
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg">
              <div class="p-6 border-b dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Información del Contrato</h3>
              </div>
              <div class="p-6 space-y-4 text-sm">
                <div class="flex justify-between">
                  <span class="text-gray-500 dark:text-gray-400">Cliente:</span>
                  <span class="font-medium text-gray-800 dark:text-gray-200">{{ nombreCliente }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-500 dark:text-gray-400">Fecha de Inicio:</span>
                  <span class="font-medium text-gray-800 dark:text-gray-200">{{ fmtDate(props.contrato.inicio) }}</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-gray-500 dark:text-gray-400">Modalidad:</span>
                  <span class="font-medium text-gray-800 dark:text-gray-200">{{ (props.contrato.modalidad || '').replace('_',' ') }}</span>
                </div>
                <div v-if="['LITIS','CUOTA_MIXTA'].includes(props.contrato.modalidad)" class="flex justify-between">
                  <span class="text-gray-500 dark:text-gray-400">Porcentaje de Éxito:</span>
                  <span class="font-bold text-purple-600 dark:text-purple-400">{{ props.contrato.porcentaje_litis }}%</span>
                </div>
                <div v-if="Number(props.contrato.monto_base_litis) > 0" class="flex justify-between">
                  <span class="text-gray-500 dark:text-gray-400">Monto Base del Caso:</span>
                  <span class="font-medium text-gray-800 dark:text-gray-200">{{ fmtMoney(props.contrato.monto_base_litis) }}</span>
                </div>
                <div v-if="Number(props.contrato.litis_valor_ganado) > 0" class="flex justify-between">
                  <span class="text-gray-500 dark:text-gray-400">Valor Litis (Ganado):</span>
                  <span class="font-medium text-gray-800 dark:text-gray-200">{{ fmtMoney(props.contrato.litis_valor_ganado) }}</span>
                </div>
                <div v-if="props.contrato.modalidad === 'CUOTA_MIXTA'" class="flex justify-between border-t pt-4 mt-4 dark:border-gray-700">
                  <span class="text-gray-500 dark:text-gray-400">Parte Fija (Cuotas):</span>
                  <span class="font-medium text-gray-800 dark:text-gray-200">{{ fmtMoney(props.contrato.monto_total) }}</span>
                </div>
                <div class="flex justify-between items-center">
                  <span class="text-gray-500 dark:text-gray-400">Estado:</span>
                  <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold"
                        :class="contractStatusClasses[props.contrato.estado] || contractStatusClasses['CERRADO']">
                    {{ props.contrato.estado?.replace('_', ' ') }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <Modal :show="pagoModalAbierto" @close="cerrarPagoModal">
      <form @submit.prevent="registrarPagoCuota">
        <div class="p-6 border-b dark:border-gray-700">
          <h4 class="font-semibold text-lg text-gray-800 dark:text-gray-100">Abonar a la cuota #{{ cuotaSeleccionada?.numero }}</h4>
        </div>
        <div class="p-6 space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Valor del Pago</label>
            <input v-model.number="pagoCuotaForm.valor" type="number" step="0.01" min="0" required class="w-full rounded-md border-gray-300 dark:bg-gray-900 dark:border-gray-700 shadow-sm"/>
            <p class="mt-1 text-xs text-gray-500">Debe ser igual o mayor a {{ fmtMoney(cuotaRestoConMora(cuotaSeleccionada)) }}.</p>
            <InputError :message="pagoCuotaForm.errors.valor" class="mt-2" />
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Fecha</label>
              <input v-model="pagoCuotaForm.fecha" type="date" required class="w-full rounded-md border-gray-300 dark:bg-gray-900 dark:border-gray-700 shadow-sm"/>
              <InputError :message="pagoCuotaForm.errors.fecha" class="mt-2" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Método</label>
              <select v-model="pagoCuotaForm.metodo" class="w-full rounded-md border-gray-300 dark:bg-gray-900 dark:border-gray-700 shadow-sm">
                <option>TRANSFERENCIA</option>
                <option>EFECTIVO</option>
                <option>TARJETA</option>
                <option>OTRO</option>
              </select>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Nota (Opcional)</label>
            <input v-model="pagoCuotaForm.nota" type="text" class="w-full rounded-md border-gray-300 dark:bg-gray-900 dark:border-gray-700 shadow-sm"/>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Comprobante (Opcional)</label>
            <input type="file" @input="pagoCuotaForm.comprobante = $event.target.files[0]" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-blue-900/50 dark:file:text-blue-300 dark:hover:file:bg-blue-900"/>
            <InputError :message="pagoCuotaForm.errors.comprobante" class="mt-2" />
          </div>
        </div>
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50 flex items-center justify-end gap-3 rounded-b-xl">
          <button type="button" @click="cerrarPagoModal" class="px-4 py-2 rounded-md bg-white dark:bg-gray-700 border dark:border-gray-600 text-sm font-semibold hover:bg-gray-50 dark:hover:bg-gray-600">Cancelar</button>
          <button type="submit" :disabled="pagoCuotaForm.processing" class="px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 disabled:opacity-50">Guardar Pago</button>
        </div>
      </form>
    </Modal>

    <Modal :show="pagoCargoModalAbierto" @close="cerrarPagoCargoModal">
      <form @submit.prevent="registrarPagoCargo">
        <div class="p-6 border-b dark:border-gray-700">
          <h4 class="font-semibold text-lg text-gray-800 dark:text-gray-100">Abonar a Cargo Adicional</h4>
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
            <label class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Valor del Pago</label>
            <input v-model.number="pagoCargoForm.valor" type="number" step="0.01" min="0" required class="w-full rounded-md border-gray-300 dark:bg-gray-900 dark:border-gray-700 shadow-sm"/>
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
              <label class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Fecha</label>
              <input v-model="pagoCargoForm.fecha" type="date" required class="w-full rounded-md border-gray-300 dark:bg-gray-900 dark:border-gray-700 shadow-sm"/>
              <InputError :message="pagoCargoForm.errors.fecha" class="mt-2" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Método</label>
              <select v-model="pagoCargoForm.metodo" class="w-full rounded-md border-gray-300 dark:bg-gray-900 dark:border-gray-700 shadow-sm">
                <option>TRANSFERENCIA</option>
                <option>EFECTIVO</option>
                <option>TARJETA</option>
                <option>OTRO</option>
              </select>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Nota (Opcional)</label>
            <input v-model="pagoCargoForm.nota" type="text" class="w-full rounded-md border-gray-300 dark:bg-gray-900 dark:border-gray-700 shadow-sm"/>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Comprobante (Requerido)</label>
            <input type="file" @input="pagoCargoForm.comprobante = $event.target.files[0]" required class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-blue-900/50 dark:file:text-blue-300 dark:hover:file:bg-blue-900"/>
            <InputError :message="pagoCargoForm.errors.comprobante" class="mt-2" />
          </div>
        </div>
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50 flex items-center justify-end gap-3 rounded-b-xl">
          <button type="button" @click="cerrarPagoCargoModal" class="px-4 py-2 rounded-md bg-white dark:bg-gray-700 border dark:border-gray-600 text-sm font-semibold hover:bg-gray-50 dark:hover:bg-gray-600">Cancelar</button>
          <button type="submit" :disabled="pagoCargoForm.processing" class="px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 disabled:opacity-50">Guardar Pago</button>
        </div>
      </form>
    </Modal>

    <Modal :show="gastoModalAbierto" @close="cerrarGastoModal">
      <form @submit.prevent="registrarGasto">
        <div class="p-6 border-b dark:border-gray-700">
          <h4 class="font-semibold text-lg text-gray-800 dark:text-gray-100">Añadir Gasto Pagado</h4>
        </div>
        <div class="p-6 space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Monto del Gasto</label>
            <input v-model.number="gastoForm.monto" type="number" step="0.01" min="0" required class="w-full rounded-md border-gray-300 dark:bg-gray-900 dark:border-gray-700 shadow-sm"/>
            <InputError :message="gastoForm.errors.monto" class="mt-2" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Fecha de Aplicación</label>
            <input v-model="gastoForm.fecha" type="date" required class="w-full rounded-md border-gray-300 dark:bg-gray-900 dark:border-gray-700 shadow-sm"/>
            <InputError :message="gastoForm.errors.fecha" class="mt-2" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Descripción</label>
            <input v-model="gastoForm.descripcion" type="text" required class="w-full rounded-md border-gray-300 dark:bg-gray-900 dark:border-gray-700 shadow-sm"/>
            <InputError :message="gastoForm.errors.descripcion" class="mt-2" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Comprobante (Opcional)</label>
            <input type="file" @input="gastoForm.comprobante = $event.target.files[0]" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-blue-900/50 dark:file:text-blue-300 dark:hover:file:bg-blue-900"/>
            <InputError :message="gastoForm.errors.comprobante" class="mt-2" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Fecha de Inicio de Intereses (Opcional)</label>
            <input v-model="gastoForm.fecha_inicio_intereses" type="date" class="w-full rounded-md border-gray-300 dark:bg-gray-900 dark:border-gray-700 shadow-sm"/>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Si no se especifica la fecha de cobro de intereses, no se cobrarán intereses.</p>
            <InputError :message="gastoForm.errors.fecha_inicio_intereses" class="mt-2" />
          </div>
        </div>
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50 flex items-center justify-end gap-3 rounded-b-xl">
          <button type="button" @click="cerrarGastoModal" class="px-4 py-2 rounded-md bg-white dark:bg-gray-700 border dark:border-gray-600 text-sm font-semibold hover:bg-gray-50 dark:hover:bg-gray-600">Cancelar</button>
          <button type="submit" :disabled="gastoForm.processing" class="px-4 py-2 rounded-md bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700 disabled:opacity-50">Guardar Gasto</button>
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
            <label class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Monto del Cargo (Opcional)</label>
            <input v-model.number="cierreForm.monto" type="number" step="0.01" min="0" placeholder="Ej: 50000" class="w-full rounded-md border-gray-300 dark:bg-gray-900 dark:border-gray-700 shadow-sm"/>
            <InputError :message="cierreForm.errors.monto" class="mt-2" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Descripción del Cargo</label>
            <input v-model="cierreForm.descripcion" type="text" placeholder="Ej: Cláusula penal por terminación" class="w-full rounded-md border-gray-300 dark:bg-gray-900 dark:border-gray-700 shadow-sm"/>
            <InputError :message="cierreForm.errors.descripcion" class="mt-2" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Fecha de Inicio de Intereses (Opcional)</label>
            <input v-model="cierreForm.fecha_inicio_intereses" type="date" class="w-full rounded-md border-gray-300 dark:bg-gray-900 dark:border-gray-700 shadow-sm"/>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Si no se especifica la fecha de cobro de intereses, no se cobrarán intereses.</p>
            <InputError :message="cierreForm.errors.fecha_inicio_intereses" class="mt-2" />
          </div>
        </div>
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50 flex items-center justify-end gap-3 rounded-b-xl">
          <button type="button" @click="cerrarCierreModal" class="px-4 py-2 rounded-md bg-white dark:bg-gray-700 border dark:border-gray-600 text-sm font-semibold hover:bg-gray-50 dark:hover:bg-gray-600">Cancelar</button>
          <button type="submit" :disabled="cierreForm.processing" class="px-4 py-2 rounded-md bg-slate-700 text-white text-sm font-semibold hover:bg-slate-800 disabled:opacity-50">Confirmar Cierre</button>
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
            <label class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Monto Base Final</label>
            <input v-model.number="resolverLitisForm.monto_base_litis" type="number" step="0.01" min="0" required placeholder="Ej: 15000000" class="w-full rounded-md border-gray-300 dark:bg-gray-900 dark:border-gray-700 shadow-sm"/>
            <InputError :message="resolverLitisForm.errors.monto_base_litis" class="mt-2" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Fecha de Inicio de Intereses (Opcional)</label>
            <input v-model="resolverLitisForm.fecha_inicio_intereses" type="date" 
            class="w-full rounded-md border-gray-300 dark:bg-gray-900 dark:border-gray-700 shadow-sm"/>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Si no se especifica la fecha de cobro de intereses, no se cobrarán intereses.</p>
            <InputError :message="resolverLitisForm.errors.fecha_inicio_intereses" class="mt-2" />
          </div>
        </div>
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50 flex items-center justify-end gap-3 rounded-b-xl">
          <button type="button" @click="cerrarResolverLitisModal" class="px-4 py-2 rounded-md bg-white dark:bg-gray-700 border dark:border-gray-600 text-sm font-semibold hover:bg-gray-50 dark:hover:bg-gray-600">Cancelar</button>
          <button type="submit" :disabled="resolverLitisForm.processing" class="px-4 py-2 rounded-md bg-purple-600 text-white text-sm font-semibold hover:bg-purple-700 disabled:opacity-50">Confirmar y Generar Cargo</button>
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

        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50 flex items-center justify-end gap-3 rounded-b-xl">
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
            <label class="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">
              Documento del Contrato (PDF únicamente)
            </label>
            <input type="file" 
                   accept=".pdf"
                   @input="documentoForm.documento = $event.target.files[0]" 
                   required 
                   class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-green-50 file:text-green-700 hover:file:bg-green-100 dark:file:bg-green-900/50 dark:file:text-green-300 dark:hover:file:bg-green-900"/>
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
        <div class="px-6 py-4 bg-gray-50 dark:bg-gray-900/50 flex items-center justify-end gap-3 rounded-b-xl">
          <SecondaryButton type="button" @click="cerrarSubirDocumentoModal">Cancelar</SecondaryButton>
          <PrimaryButton type="submit" :disabled="documentoForm.processing || !documentoForm.documento">
            {{ documentoForm.processing ? 'Subiendo...' : (props.contrato.documento_contrato ? 'Reemplazar Documento' : 'Subir Documento') }}
          </PrimaryButton>
        </div>
      </form>
    </Modal>
  </AuthenticatedLayout>
</template>