<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import {
    PencilSquareIcon,
    ArrowLeftIcon,
    MapPinIcon,
    LinkIcon,
    ArrowTopRightOnSquareIcon,
    PaperClipIcon,
    TrashIcon,
    ArrowDownTrayIcon,
    DocumentPlusIcon,
    DocumentIcon,
    EyeIcon,
    ExclamationTriangleIcon,
    PhoneIcon,
    EnvelopeIcon,
    BriefcaseIcon,
    IdentificationIcon,
    GlobeAltIcon,
    FolderIcon,
    ScaleIcon,
    CheckCircleIcon,
    CloudArrowUpIcon,
    ShieldCheckIcon,
    BuildingOfficeIcon,
    CalendarDaysIcon,
} from '@heroicons/vue/24/outline';
import { ref, computed } from 'vue';
import AppAlert from '@/Utils/appAlert';

const props = defineProps({
  persona: { type: Object, required: true },
});

const isViewable = (mimeType) => {
    if (!mimeType) return false;
    return ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg', 'image/webp'].includes(mimeType);
};

const hasData = (arr) => Array.isArray(arr) && arr.length > 0;

const missingInfo = computed(() => {
    const p = props.persona;
    const missing = [];
    if (!p.celular_1 && !p.correo_1) missing.push('Contacto principal');
    if (!p.fecha_nacimiento) missing.push('Fecha de nacimiento');
    if (!p.fecha_expedicion) missing.push('Fecha de expedicion');
    if (!p.sin_empresa_o_cooperativa && !hasData(p.cooperativas)) missing.push('Empresa o cooperativa');
    if (!hasData(p.abogados)) missing.push('Responsable asignado');
    return missing;
});

const initials = computed(() => props.persona.nombre_completo?.trim()?.[0] || '?');
const roleLabel = computed(() => props.persona.es_demandado ? 'Demandado' : 'Cliente / Deudor');
const roleClass = computed(() => props.persona.es_demandado
    ? 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-300'
    : 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-300'
);
const estadoCarteraLabel = computed(() => props.persona.estado_cartera || 'NO APLICA');
const estadoCarteraClass = computed(() => {
    if (estadoCarteraLabel.value === 'ACTIVO') return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-300';
    if (estadoCarteraLabel.value === 'CASTIGADO') return 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-300';
    return 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-200';
});
const profileStatus = computed(() => missingInfo.value.length ? 'Datos pendientes' : 'Perfil completo');
const profileStatusClass = computed(() => missingInfo.value.length
    ? 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-300'
    : 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-300'
);

const documentNumber = computed(() => {
    if (!props.persona.numero_documento) return 'Sin identificacion';
    const suffix = props.persona.dv ? `-${props.persona.dv}` : '';
    return `${props.persona.numero_documento}${suffix}`;
});

const summaryCards = computed(() => [
    {
        label: 'Casos de cobro',
        value: props.persona.casos_count || 0,
        detail: 'Expedientes financieros',
        icon: FolderIcon,
        class: 'border-indigo-200 bg-indigo-50 text-indigo-700 dark:border-indigo-900/60 dark:bg-indigo-950/30 dark:text-indigo-300',
    },
    {
        label: 'Radicados',
        value: props.persona.procesos_count || 0,
        detail: 'Procesos judiciales',
        icon: ScaleIcon,
        class: 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/60 dark:bg-sky-950/30 dark:text-sky-300',
    },
    {
        label: 'Documentos',
        value: props.persona.documentos?.length || 0,
        detail: 'Soportes cargados',
        icon: PaperClipIcon,
        class: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-300',
    },
    {
        label: 'Calidad de datos',
        value: missingInfo.value.length,
        detail: missingInfo.value.length ? 'Pendientes' : 'Sin faltantes',
        icon: ExclamationTriangleIcon,
        class: missingInfo.value.length
            ? 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-300'
            : 'border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-200',
    },
]);

const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return isNaN(date.getTime()) ? dateString : date.toLocaleDateString('es-CO', { year: 'numeric', month: 'long', day: 'numeric' });
};

const formatBytes = (bytes, decimals = 2) => {
    if (!+bytes) return '0 Bytes';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return `${parseFloat((bytes / Math.pow(k, i)).toFixed(dm))} ${sizes[i]}`;
};

const formUpload = useForm({ documento: null });
const fileInput = ref(null);

const handleFileChange = (e) => {
    const file = e.target.files[0];
    if (!file) return;

    AppAlert.fire({
        title: '¿Subir documento?',
        text: `Se adjuntara "${file.name}" al expediente.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Si, subir ahora',
        confirmButtonColor: '#4f46e5'
    }).then((result) => {
        if (result.isConfirmed) {
            formUpload.documento = file;
            formUpload.post(route('personas.upload_document', props.persona.id), {
                preserveScroll: true,
                onSuccess: () => {
                    formUpload.reset();
                    if (fileInput.value) fileInput.value.value = null;
                    AppAlert.fire('Exito', 'Documento cargado correctamente.', 'success');
                },
            });
        } else if (fileInput.value) {
            fileInput.value.value = null;
        }
    });
};

const deleteDocument = (docId) => {
    AppAlert.fire({
        title: '¿Eliminar archivo?',
        text: 'Esta accion no se puede deshacer.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Si, eliminar'
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('personas.delete_document', docId), {
                onSuccess: () => AppAlert.fire('Eliminado', 'El archivo ha sido borrado.', 'success')
            });
        }
    });
};

const getRandomColor = (id) => {
  const colors = ['bg-indigo-600', 'bg-emerald-600', 'bg-violet-600', 'bg-amber-600', 'bg-rose-600'];
  return colors[id % colors.length];
};
</script>

<template>
  <Head :title="persona.nombre_completo" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div class="flex min-w-0 items-start gap-3">
            <Link :href="route('personas.index')" class="mt-1 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 shadow-sm transition hover:border-indigo-300 hover:text-indigo-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:text-indigo-300">
                <ArrowLeftIcon class="h-4 w-4" />
            </Link>
            <div class="flex min-w-0 items-start gap-4">
                <div :class="`flex h-14 w-14 shrink-0 items-center justify-center rounded-lg text-xl font-black text-white shadow-sm ${getRandomColor(persona.id)}`">
                    {{ initials }}
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Ficha personal</p>
                    <div class="mt-1 flex flex-wrap items-center gap-2">
                        <h2 class="truncate text-2xl font-black leading-tight text-gray-950 dark:text-white">{{ persona.nombre_completo }}</h2>
                        <span class="rounded-md border px-2.5 py-1 text-[10px] font-black uppercase" :class="roleClass">{{ roleLabel }}</span>
                        <span class="rounded-md border px-2.5 py-1 text-[10px] font-black uppercase" :class="estadoCarteraClass">{{ estadoCarteraLabel }}</span>
                        <span class="rounded-md border px-2.5 py-1 text-[10px] font-black uppercase" :class="profileStatusClass">{{ profileStatus }}</span>
                    </div>
                    <p class="mt-1 text-sm font-medium text-gray-600 dark:text-gray-300">{{ persona.tipo_documento || 'Documento' }} {{ documentNumber }}</p>
                </div>
            </div>
        </div>

        <Link :href="route('personas.edit', persona.id)" class="inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-xs font-black uppercase tracking-wider text-white shadow-sm transition hover:bg-indigo-700 sm:w-auto">
            <PencilSquareIcon class="mr-2 h-4 w-4" /> Editar perfil
        </Link>
      </div>
    </template>

    <div class="min-h-screen bg-gray-50/60 py-6 dark:bg-gray-900/40">
      <div class="mx-auto max-w-[1600px] space-y-5 px-4 sm:px-6 lg:px-8">
        <section class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <article
                v-for="item in summaryCards"
                :key="item.label"
                class="rounded-lg border p-4 shadow-sm"
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

        <div v-if="missingInfo.length > 0" class="rounded-lg border border-amber-200 bg-amber-50 p-4 shadow-sm dark:border-amber-900/60 dark:bg-amber-950/30">
            <div class="flex items-start gap-3">
                <ExclamationTriangleIcon class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-300" />
                <div>
                    <p class="text-xs font-black uppercase tracking-widest text-amber-900 dark:text-amber-100">Datos pendientes</p>
                    <p class="mt-1 text-sm font-medium text-amber-800 dark:text-amber-200">Completar estos datos mejora busqueda, notificacion y gestion juridica.</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <span v-for="item in missingInfo" :key="item" class="rounded-md border border-amber-200 bg-white/70 px-2.5 py-1 text-[10px] font-black uppercase text-amber-800 dark:border-amber-900/60 dark:bg-gray-900/30 dark:text-amber-200">{{ item }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-5 xl:grid-cols-12 xl:items-start">
            <main class="xl:col-span-8 space-y-5">
                <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-center gap-2 border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                        <IdentificationIcon class="h-5 w-5 text-indigo-500" />
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-indigo-600 dark:text-indigo-300">Perfil</p>
                            <h3 class="text-base font-black text-gray-950 dark:text-white">Identificacion, contacto y contexto laboral</h3>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-4 p-5 md:grid-cols-2">
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Documento</p>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <span class="rounded-md border border-indigo-200 bg-indigo-50 px-2 py-1 text-[10px] font-black uppercase text-indigo-700 dark:border-indigo-900/60 dark:bg-indigo-950/30 dark:text-indigo-300">{{ persona.tipo_documento || 'Documento' }}</span>
                                <p class="font-mono text-sm font-black text-gray-950 dark:text-white">{{ documentNumber }}</p>
                            </div>
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Fechas clave</p>
                            <div class="mt-2 space-y-1 text-sm font-semibold text-gray-800 dark:text-gray-200">
                                <p class="flex items-center gap-2"><CalendarDaysIcon class="h-4 w-4 text-gray-400" />Nacimiento: <span class="font-medium text-gray-600 dark:text-gray-300">{{ formatDate(persona.fecha_nacimiento) }}</span></p>
                                <p class="flex items-center gap-2"><IdentificationIcon class="h-4 w-4 text-gray-400" />Expedicion: <span class="font-medium text-gray-600 dark:text-gray-300">{{ formatDate(persona.fecha_expedicion) }}</span></p>
                            </div>
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Contacto principal</p>
                            <div class="mt-2 space-y-2">
                                <p v-if="persona.celular_1" class="flex items-center gap-2 text-sm font-bold text-gray-900 dark:text-gray-100"><PhoneIcon class="h-4 w-4 text-emerald-500" />{{ persona.celular_1 }}</p>
                                <p v-if="persona.correo_1" class="flex items-center gap-2 break-all text-sm font-bold text-gray-900 dark:text-gray-100"><EnvelopeIcon class="h-4 w-4 shrink-0 text-sky-500" />{{ persona.correo_1 }}</p>
                                <p v-if="!persona.celular_1 && !persona.correo_1" class="text-sm font-semibold text-gray-500 dark:text-gray-400">Sin contacto principal registrado.</p>
                            </div>
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Perfil laboral</p>
                            <div class="mt-2 flex items-start gap-3">
                                <BriefcaseIcon class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" />
                                <div>
                                    <p class="text-sm font-black text-gray-950 dark:text-white">{{ persona.empresa || 'Empresa no registrada' }}</p>
                                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ persona.cargo || 'Cargo no especificado' }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="md:col-span-2 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900/40">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Notas y observaciones</p>
                            <p class="mt-2 whitespace-pre-line text-sm font-medium leading-relaxed text-gray-700 dark:text-gray-300">{{ persona.observaciones || 'Sin anotaciones adicionales en el perfil.' }}</p>
                        </div>
                    </div>
                </section>

                <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex flex-col gap-3 border-b border-gray-200 px-5 py-4 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-2">
                            <CloudArrowUpIcon class="h-5 w-5 text-indigo-500" />
                            <div>
                                <p class="text-[10px] font-bold uppercase tracking-widest text-indigo-600 dark:text-indigo-300">Documentos</p>
                                <h3 class="text-base font-black text-gray-950 dark:text-white">Repositorio de archivos</h3>
                            </div>
                        </div>
                        <div>
                            <input type="file" ref="fileInput" class="hidden" @change="handleFileChange">
                            <button @click="fileInput.click()" class="inline-flex w-full items-center justify-center rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2.5 text-xs font-black uppercase tracking-wider text-indigo-700 transition hover:bg-indigo-100 dark:border-indigo-900/60 dark:bg-indigo-950/30 dark:text-indigo-300 sm:w-auto">
                                <DocumentPlusIcon class="mr-2 h-4 w-4" /> Adjuntar soporte
                            </button>
                        </div>
                    </div>

                    <div class="p-5">
                        <div v-if="!hasData(persona.documentos)" class="rounded-lg border border-dashed border-gray-300 p-10 text-center dark:border-gray-700">
                            <DocumentIcon class="mx-auto mb-3 h-10 w-10 text-gray-300" />
                            <p class="text-xs font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">Sin documentos vinculados</p>
                        </div>
                        <ul v-else class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <li v-for="doc in persona.documentos" :key="doc.id" class="rounded-lg border border-gray-200 bg-gray-50 p-4 transition hover:border-indigo-200 hover:bg-indigo-50/50 dark:border-gray-700 dark:bg-gray-900/40 dark:hover:border-indigo-900/60 dark:hover:bg-indigo-950/20">
                                <div class="flex items-start gap-3">
                                    <div class="rounded-lg bg-white p-2 text-gray-500 shadow-sm dark:bg-gray-800 dark:text-gray-300">
                                        <DocumentIcon class="h-6 w-6" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <h4 class="truncate text-sm font-black text-gray-950 dark:text-white" :title="doc.nombre_original">{{ doc.nombre_original }}</h4>
                                        <p class="mt-1 text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ formatBytes(doc.size) }} - {{ formatDate(doc.created_at) }}</p>
                                        <div class="mt-3 flex flex-wrap items-center gap-2">
                                            <a v-if="isViewable(doc.mime_type)" :href="route('personas.view_document', doc.id)" target="_blank" class="inline-flex items-center rounded-md border border-gray-200 bg-white px-2.5 py-1 text-[10px] font-black uppercase text-gray-600 transition hover:text-indigo-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                                <EyeIcon class="mr-1 h-3.5 w-3.5" /> Ver
                                            </a>
                                            <a :href="route('personas.download_document', doc.id)" class="inline-flex items-center rounded-md border border-indigo-200 bg-white px-2.5 py-1 text-[10px] font-black uppercase text-indigo-600 transition hover:bg-indigo-50 dark:border-indigo-900/60 dark:bg-gray-800 dark:text-indigo-300">
                                                <ArrowDownTrayIcon class="mr-1 h-3.5 w-3.5" /> Descargar
                                            </a>
                                            <button @click="deleteDocument(doc.id)" class="inline-flex items-center rounded-md border border-rose-200 bg-white px-2.5 py-1 text-[10px] font-black uppercase text-rose-600 transition hover:bg-rose-50 dark:border-rose-900/60 dark:bg-gray-800 dark:text-rose-300">
                                                <TrashIcon class="mr-1 h-3.5 w-3.5" /> Eliminar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </section>

                <section class="grid grid-cols-1 gap-5 2xl:grid-cols-2">
                    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="flex items-center justify-between gap-3 border-b border-gray-200 bg-indigo-50/60 px-5 py-4 dark:border-gray-700 dark:bg-indigo-950/20">
                            <h3 class="text-sm font-black text-gray-950 dark:text-white">Casos de cobro</h3>
                            <Link v-if="persona.casos_count > persona.casos?.length" :href="route('casos.index', { search: persona.numero_documento })" class="text-[10px] font-black uppercase text-indigo-600 hover:underline dark:text-indigo-300">Ver todos</Link>
                        </div>
                        <div v-if="!hasData(persona.casos)" class="p-10 text-center text-xs font-black uppercase tracking-widest text-gray-400">Sin registros de cobro</div>
                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    <tr v-for="caso in persona.casos" :key="caso.id" class="hover:bg-gray-50 dark:hover:bg-gray-900/40">
                                        <td class="px-5 py-4">
                                            <p class="text-sm font-black text-gray-950 dark:text-white">#{{ caso.id }}</p>
                                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ caso.radicado || 'Sin radicar' }}</p>
                                        </td>
                                        <td class="px-5 py-4"><span class="rounded-md border border-emerald-200 bg-emerald-50 px-2 py-1 text-[10px] font-black uppercase text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-300">{{ caso.estado }}</span></td>
                                        <td class="px-5 py-4 text-xs font-semibold text-gray-600 dark:text-gray-300">{{ caso.user?.name || 'Sin responsable' }}</td>
                                        <td class="px-5 py-4 text-right"><Link :href="route('casos.show', caso.id)" class="text-[10px] font-black uppercase text-indigo-600 hover:underline dark:text-indigo-300">Ver</Link></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="flex items-center justify-between gap-3 border-b border-gray-200 bg-sky-50/60 px-5 py-4 dark:border-gray-700 dark:bg-sky-950/20">
                            <h3 class="text-sm font-black text-gray-950 dark:text-white">Radicados judiciales</h3>
                            <Link v-if="persona.procesos_count > persona.procesos?.length" :href="route('procesos.index', { search: persona.numero_documento })" class="text-[10px] font-black uppercase text-sky-600 hover:underline dark:text-sky-300">Ver todos</Link>
                        </div>
                        <div v-if="!hasData(persona.procesos)" class="p-10 text-center text-xs font-black uppercase tracking-widest text-gray-400">Sin procesos registrados</div>
                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    <tr v-for="proc in persona.procesos" :key="proc.id" class="hover:bg-gray-50 dark:hover:bg-gray-900/40">
                                        <td class="px-5 py-4">
                                            <p class="max-w-[220px] truncate text-sm font-black text-gray-950 dark:text-white" :title="proc.radicado">{{ proc.radicado || 'Sin radicado' }}</p>
                                            <p class="max-w-[220px] truncate text-xs font-medium text-gray-500 dark:text-gray-400" :title="proc.asunto">{{ proc.asunto || 'Sin asunto' }}</p>
                                        </td>
                                        <td class="px-5 py-4 text-xs font-black uppercase text-gray-500 dark:text-gray-400">{{ proc.pivot?.tipo || 'Vinculado' }}</td>
                                        <td class="px-5 py-4"><span class="rounded-md border border-sky-200 bg-sky-50 px-2 py-1 text-[10px] font-black uppercase text-sky-700 dark:border-sky-900/60 dark:bg-sky-950/30 dark:text-sky-300">{{ proc.estado || 'Activo' }}</span></td>
                                        <td class="px-5 py-4 text-right"><Link :href="route('procesos.show', proc.id)" class="text-[10px] font-black uppercase text-indigo-600 hover:underline dark:text-indigo-300">Ver</Link></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </main>

            <aside class="xl:col-span-4 space-y-5">
                <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-center gap-2 border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                        <MapPinIcon class="h-5 w-5 text-rose-500" />
                        <h3 class="text-sm font-black text-gray-950 dark:text-white">Ubicaciones registradas</h3>
                    </div>
                    <div class="space-y-3 p-5">
                        <div v-if="hasData(persona.addresses)" v-for="(addr, idx) in persona.addresses" :key="idx" class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                            <p class="text-[10px] font-black uppercase tracking-widest text-gray-500 dark:text-gray-400">{{ addr.label || 'Direccion' }}</p>
                            <p class="mt-2 text-sm font-bold text-gray-900 dark:text-gray-100">{{ addr.address }}</p>
                            <p class="mt-1 text-xs font-semibold text-gray-500 dark:text-gray-400">{{ addr.city || 'Ciudad no registrada' }}</p>
                        </div>
                        <div v-else class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-xs font-black uppercase tracking-widest text-gray-400 dark:border-gray-700">Sin direcciones</div>
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-center gap-2 border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                        <GlobeAltIcon class="h-5 w-5 text-sky-500" />
                        <h3 class="text-sm font-black text-gray-950 dark:text-white">Enlaces digitales</h3>
                    </div>
                    <div class="space-y-3 p-5">
                        <a v-if="hasData(persona.social_links)" v-for="(link, idx) in persona.social_links" :key="idx" :href="link.url" target="_blank" rel="noopener noreferrer" class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-gray-50 p-4 transition hover:border-sky-200 hover:bg-sky-50 dark:border-gray-700 dark:bg-gray-900/40 dark:hover:border-sky-900/60 dark:hover:bg-sky-950/20">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-black text-gray-950 dark:text-white">{{ link.label || 'Enlace' }}</p>
                                <p class="truncate text-xs font-medium text-gray-500 dark:text-gray-400">{{ link.url }}</p>
                            </div>
                            <ArrowTopRightOnSquareIcon class="h-4 w-4 shrink-0 text-sky-500" />
                        </a>
                        <div v-else class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-xs font-black uppercase tracking-widest text-gray-400 dark:border-gray-700">Sin enlaces digitales</div>
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-center gap-2 border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                        <ShieldCheckIcon class="h-5 w-5 text-emerald-500" />
                        <h3 class="text-sm font-black text-gray-950 dark:text-white">Control y asignaciones</h3>
                    </div>
                    <div class="space-y-5 p-5">
                        <div>
                            <p class="mb-2 text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Empresas / Cooperativas</p>
                            <div v-if="hasData(persona.cooperativas)" class="flex flex-wrap gap-2">
                                <span v-for="c in persona.cooperativas" :key="c.id" class="rounded-md border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[10px] font-black uppercase text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/30 dark:text-emerald-300">{{ c.nombre }}</span>
                            </div>
                            <p v-else-if="persona.sin_empresa_o_cooperativa" class="inline-flex rounded-md border border-amber-200 bg-amber-50 px-2.5 py-1 text-[10px] font-black uppercase text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-300">Sin empresa o cooperativa</p>
                            <p v-else class="text-sm font-semibold text-gray-500 dark:text-gray-400">No hay empresas vinculadas.</p>
                        </div>
                        <div class="border-t border-gray-200 pt-5 dark:border-gray-700">
                            <p class="mb-2 text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Responsables</p>
                            <div v-if="hasData(persona.abogados)" class="flex flex-wrap gap-2">
                                <span v-for="a in persona.abogados" :key="a.id" class="rounded-md border border-violet-200 bg-violet-50 px-2.5 py-1 text-[10px] font-black uppercase text-violet-700 dark:border-violet-900/60 dark:bg-violet-950/30 dark:text-violet-300">{{ a.name }}</span>
                            </div>
                            <p v-else class="text-sm font-semibold text-gray-500 dark:text-gray-400">Sin responsables asignados.</p>
                        </div>
                    </div>
                </section>
            </aside>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
