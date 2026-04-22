<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import { computed, watch, ref } from 'vue';
import { 
    BriefcaseIcon, ShieldCheckIcon, BuildingOffice2Icon, UserIcon, ArrowLeftIcon, 
    DocumentTextIcon, MapPinIcon, LinkIcon, BuildingOfficeIcon, AcademicCapIcon,
    ArrowUpOnSquareIcon, PaperClipIcon, TrashIcon, DocumentPlusIcon, 
    ExclamationTriangleIcon, EnvelopeIcon, PhoneIcon, CheckCircleIcon,
    LockClosedIcon, LockOpenIcon, ClockIcon
} from '@heroicons/vue/24/outline';

// --- PROPS ---
const props = defineProps({
    user: Object,
});

// --- MENSAJES FLASH ---
const page = usePage();
const flash = ref({ success: '', error: '' });

watch(() => page.props.flash, (newFlash) => {
    flash.value.success = newFlash.success;
    flash.value.error = newFlash.error;
    if (newFlash.success || newFlash.error) {
        setTimeout(() => {
            flash.value.success = '';
            flash.value.error = '';
        }, 4000);
    }
}, { immediate: true });

// --- FORMULARIOS ---
const cardForm = useForm({ tarjeta_profesional: null });
const documentForm = useForm({ name: '', file: null });

function submitProfessionalCard() {
    cardForm.post(route('admin.users.upload.card', props.user.id), {
        preserveScroll: true,
        onSuccess: () => cardForm.reset(),
    });
}

function submitGeneralDocument() {
    documentForm.post(route('admin.users.documents.store', props.user.id), {
        preserveScroll: true,
        onSuccess: () => documentForm.reset(),
    });
}

function deleteDocument(documentId) {
    if (confirm('¿Deseas eliminar este documento permanentemente?')) {
        router.delete(route('admin.users.documents.destroy', documentId), {
            preserveScroll: true,
        });
    }
}

// --- HELPERS VISUALES ---
const roleDisplay = {
    admin: { icon: ShieldCheckIcon, color: 'text-rose-500', bg: 'bg-rose-50 dark:bg-rose-900/20', label: 'Administrador' },
    abogado: { icon: BriefcaseIcon, color: 'text-indigo-500', bg: 'bg-indigo-50 dark:bg-indigo-900/20', label: 'Abogado' },
    gestor: { icon: BuildingOffice2Icon, color: 'text-emerald-500', bg: 'bg-emerald-50 dark:bg-emerald-900/20', label: 'Gestor' },
    cliente: { icon: UserIcon, color: 'text-slate-400', bg: 'bg-slate-50 dark:bg-slate-900/20', label: 'Cliente' },
};

const getInitials = (name) => {
    if (!name) return '??';
    const names = name.split(' ');
    if (names.length > 1) return `${names[0][0]}${names[1][0]}`.toUpperCase();
    return `${names[0][0]}`.toUpperCase();
};

const formatList = (items) => {
    if (Array.isArray(items) && items.length > 0) {
        return items.map(item => item.nombre).join(', ');
    }
    return 'Ninguna asignada';
};

const professionalCardUrl = computed(() => {
    return props.user.tarjeta_profesional_url ? `/storage/${props.user.tarjeta_profesional_url}` : null;
});

const getDocumentUrl = (path) => path ? `/storage/${path}` : null;
</script>

<template>
    <Head :title="'Perfil: ' + user.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('admin.users.index')" class="p-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 text-gray-400 hover:text-indigo-600 transition-all">
                    <ArrowLeftIcon class="h-5 w-5" />
                </Link>
                <div>
                    <h2 class="font-black text-2xl text-gray-900 dark:text-white leading-tight">
                        Perfil del Usuario
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Detalles de cuenta, permisos y documentos.</p>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
                
                <!-- ALERTAS -->
                <div v-if="flash.success" class="bg-emerald-50 border-l-4 border-emerald-400 p-4 rounded-r-xl shadow-sm flex items-center animate-in fade-in slide-in-from-top-4">
                    <CheckCircleIcon class="h-5 w-5 text-emerald-500 mr-3" />
                    <span class="text-emerald-800 text-sm font-bold">{{ flash.success }}</span>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                    
                    <!-- COLUMNA IZQUIERDA: TARJETA DE PERFIL -->
                    <div class="lg:col-span-4 space-y-6">
                        <div class="bg-white dark:bg-gray-800 shadow-xl shadow-slate-200/50 dark:shadow-none rounded-[2rem] border border-gray-100 dark:border-gray-700 overflow-hidden">
                            <div class="h-24 bg-gradient-to-r from-indigo-500 to-purple-600"></div>
                            <div class="px-6 pb-8 text-center -mt-12">
                                <div class="relative inline-block">
                                    <div :class="[roleDisplay[user.tipo_usuario].bg, user.estado_activo ? 'ring-emerald-400' : 'ring-rose-400']" class="h-28 w-24 mx-auto rounded-[2rem] flex items-center justify-center text-3xl font-black shadow-2xl ring-4 border-4 border-white dark:border-gray-800">
                                        <span :class="roleDisplay[user.tipo_usuario].color">{{ getInitials(user.name) }}</span>
                                    </div>
                                    <div v-if="user.estado_activo" class="absolute bottom-1 right-1 h-6 w-6 bg-emerald-500 border-4 border-white dark:border-gray-800 rounded-full shadow-lg" title="Usuario Activo"></div>
                                    <div v-else class="absolute bottom-1 right-1 h-6 w-6 bg-rose-500 border-4 border-white dark:border-gray-800 rounded-full shadow-lg" title="Usuario Suspendido"></div>
                                </div>
                                
                                <h3 class="mt-4 text-xl font-black text-gray-900 dark:text-white">{{ user.name }}</h3>
                                <div class="flex items-center justify-center text-gray-500 dark:text-gray-400 text-sm mt-1">
                                    <EnvelopeIcon class="h-4 w-4 mr-1.5" />
                                    {{ user.email }}
                                </div>

                                <div class="mt-6 flex flex-wrap justify-center gap-2">
                                    <span :class="[roleDisplay[user.tipo_usuario].bg, roleDisplay[user.tipo_usuario].color]" class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest">
                                        <component :is="roleDisplay[user.tipo_usuario].icon" class="h-3 w-3 mr-1.5" />
                                        {{ roleDisplay[user.tipo_usuario].label }}
                                    </span>
                                </div>

                                <div class="mt-8 pt-8 border-t border-gray-100 dark:border-gray-700 space-y-3">
                                    <Link :href="route('admin.users.edit', user.id)" class="w-full inline-flex justify-center items-center px-6 py-3 bg-indigo-600 text-white rounded-2xl font-bold text-sm hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-200 dark:shadow-none">
                                        <PencilSquareIcon class="h-4 w-4 mr-2" />
                                        Editar Perfil
                                    </Link>
                                    
                                    <template v-if="user.id !== usePage().props.auth.user.id">
                                        <Link :href="route('admin.users.toggle-status', user.id)" method="patch" as="button" 
                                            :class="user.estado_activo ? 'text-rose-600 hover:bg-rose-50 border-rose-100' : 'text-emerald-600 hover:bg-emerald-50 border-emerald-100'"
                                            class="w-full inline-flex justify-center items-center px-6 py-3 border-2 rounded-2xl font-bold text-sm transition-all">
                                            <component :is="user.estado_activo ? LockClosedIcon : LockOpenIcon" class="h-4 w-4 mr-2" />
                                            {{ user.estado_activo ? 'Suspender Cuenta' : 'Activar Cuenta' }}
                                        </Link>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <!-- PANEL DE DIRECCIONES (COMO CARDS) -->
                        <div class="bg-white dark:bg-gray-800 p-6 rounded-[2rem] border border-gray-100 dark:border-gray-700 shadow-sm">
                            <h4 class="text-xs font-black text-gray-400 uppercase tracking-[0.2em] mb-4 flex items-center">
                                <MapPinIcon class="h-4 w-4 mr-2" />
                                Ubicaciones
                            </h4>
                            <div v-if="user.addresses?.length" class="space-y-3">
                                <div v-for="(addr, idx) in user.addresses" :key="idx" class="p-4 bg-gray-50 dark:bg-gray-900/40 rounded-2xl border border-gray-100 dark:border-gray-700">
                                    <p class="text-sm font-bold text-gray-800 dark:text-gray-200">{{ addr.address }}</p>
                                    <p class="text-xs text-gray-500 mt-0.5">{{ addr.city }} <span v-if="addr.details">· {{ addr.details }}</span></p>
                                </div>
                            </div>
                            <div v-else class="py-6 text-center border-2 border-dashed border-gray-100 dark:border-gray-700 rounded-2xl text-gray-400 text-xs italic">
                                Sin direcciones registradas
                            </div>
                        </div>
                    </div>

                    <!-- COLUMNA DERECHA: INFORMACIÓN Y DOCUMENTOS -->
                    <div class="lg:col-span-8 space-y-8">
                        
                        <!-- SECCIÓN: DETALLES JURÍDICOS -->
                        <div class="bg-white dark:bg-gray-800 p-8 rounded-[2rem] border border-gray-100 dark:border-gray-700 shadow-sm">
                            <div class="flex items-center mb-8">
                                <div class="p-3 bg-indigo-50 dark:bg-indigo-900/30 rounded-2xl mr-4 text-indigo-600">
                                    <BuildingOfficeIcon class="h-6 w-6" />
                                </div>
                                <h3 class="text-xl font-black text-gray-900 dark:text-white uppercase tracking-tighter">Asignaciones del Perfil</h3>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                <div class="p-6 bg-slate-50 dark:bg-gray-900/40 rounded-3xl border border-gray-100 dark:border-gray-700">
                                    <label class="block text-[10px] font-black text-indigo-500 uppercase tracking-widest mb-2">Especialidades</label>
                                    <p class="text-sm font-bold text-gray-700 dark:text-gray-200 leading-relaxed">{{ formatList(user.especialidades) }}</p>
                                </div>
                                <div class="p-6 bg-slate-50 dark:bg-gray-900/40 rounded-3xl border border-gray-100 dark:border-gray-700">
                                    <label class="block text-[10px] font-black text-indigo-500 uppercase tracking-widest mb-2">Cooperativas</label>
                                    <p class="text-sm font-bold text-gray-700 dark:text-gray-200 leading-relaxed">{{ formatList(user.cooperativas) }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- SECCIÓN: GESTOR DE ARCHIVOS -->
                        <div class="bg-white dark:bg-gray-800 p-8 rounded-[2rem] border border-gray-100 dark:border-gray-700 shadow-xl shadow-slate-200/50 dark:shadow-none">
                            <div class="flex justify-between items-center mb-8">
                                <div class="flex items-center">
                                    <div class="p-3 bg-amber-50 dark:bg-amber-900/30 rounded-2xl mr-4 text-amber-600">
                                        <PaperClipIcon class="h-6 w-6" />
                                    </div>
                                    <h3 class="text-xl font-black text-gray-900 dark:text-white uppercase tracking-tighter">Bóveda de Documentos</h3>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                <!-- CARGA DE TARJETA PROFESIONAL -->
                                <div class="bg-indigo-50/30 dark:bg-indigo-900/10 p-6 rounded-3xl border border-indigo-100 dark:border-indigo-900/30 relative overflow-hidden group">
                                    <h4 class="text-xs font-black text-indigo-900 dark:text-indigo-300 uppercase mb-4 flex items-center">
                                        <AcademicCapIcon class="h-4 w-4 mr-2" />
                                        Tarjeta Profesional
                                    </h4>
                                    
                                    <div v-if="professionalCardUrl" class="mb-6 flex items-center p-3 bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-indigo-100 dark:border-indigo-900/50">
                                        <div class="p-2 bg-indigo-50 rounded-lg mr-3">
                                            <DocumentTextIcon class="h-6 w-6 text-indigo-600" />
                                        </div>
                                        <div class="flex-1 truncate">
                                            <p class="text-[10px] font-black text-gray-400 uppercase leading-none mb-1">Cargado</p>
                                            <a :href="professionalCardUrl" target="_blank" class="text-xs font-black text-indigo-600 hover:underline">VER DOCUMENTO</a>
                                        </div>
                                    </div>

                                    <form @submit.prevent="submitProfessionalCard" class="space-y-3">
                                        <div class="relative">
                                            <input type="file" @input="cardForm.tarjeta_profesional = $event.target.files[0]" class="block w-full text-[10px] text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-black file:bg-indigo-600 file:text-white hover:file:bg-indigo-700 cursor-pointer"/>
                                        </div>
                                        <button type="submit" :disabled="cardForm.processing || !cardForm.tarjeta_profesional" class="w-full inline-flex justify-center items-center px-4 py-2.5 bg-white dark:bg-gray-800 border-2 border-indigo-600 text-indigo-600 rounded-xl text-[10px] font-black uppercase hover:bg-indigo-600 hover:text-white transition-all shadow-sm disabled:opacity-30">
                                            <ArrowUpOnSquareIcon class="h-4 w-4 mr-2"/>
                                            {{ professionalCardUrl ? 'REEMPLAZAR ARCHIVO' : 'SUBIR POR PRIMERA VEZ' }}
                                        </button>
                                    </form>
                                </div>

                                <!-- CARGA DE OTROS DOCUMENTOS -->
                                <div class="bg-gray-50 dark:bg-gray-900/40 p-6 rounded-3xl border border-gray-100 dark:border-gray-700">
                                    <h4 class="text-xs font-black text-gray-900 dark:text-white uppercase mb-4 flex items-center">
                                        <DocumentPlusIcon class="h-4 w-4 mr-2 text-gray-400" />
                                        Nuevo Adjunto
                                    </h4>
                                    
                                    <form @submit.prevent="submitGeneralDocument" class="space-y-4">
                                        <input v-model="documentForm.name" type="text" class="block w-full text-xs font-bold border-gray-200 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-800 focus:ring-indigo-500" placeholder="Nombre: ej. Cédula, RUT..." required>
                                        <input type="file" @input="documentForm.file = $event.target.files[0]" class="block w-full text-[10px] text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-black file:bg-gray-800 file:text-white hover:file:bg-black cursor-pointer" required>
                                        <button type="submit" :disabled="documentForm.processing || !documentForm.file" class="w-full inline-flex justify-center items-center px-4 py-2.5 bg-gray-900 text-white rounded-xl text-[10px] font-black uppercase hover:bg-black transition-all shadow-lg">
                                            GUARDAR EN LA NUBE
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <!-- LISTADO DE DOCUMENTOS -->
                            <div class="mt-10 pt-10 border-t border-gray-100 dark:border-gray-700">
                                <h4 class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] mb-6 flex items-center">
                                    <ClockIcon class="h-4 w-4 mr-2" />
                                    Archivos Recientes
                                </h4>
                                
                                <div v-if="user.documents?.length" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div v-for="doc in user.documents" :key="doc.id" class="flex items-center justify-between p-4 bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-2xl hover:shadow-md transition-all group">
                                        <div class="flex items-center truncate flex-1">
                                            <div class="p-2 bg-slate-50 dark:bg-gray-900 rounded-lg mr-3 group-hover:bg-indigo-50 transition-colors">
                                                <PaperClipIcon class="h-5 w-5 text-gray-400 group-hover:text-indigo-600" />
                                            </div>
                                            <div class="truncate">
                                                <a :href="getDocumentUrl(doc.path)" target="_blank" class="text-xs font-bold text-gray-700 dark:text-gray-200 hover:text-indigo-600 transition-colors block truncate" :title="doc.name">
                                                    {{ doc.name }}
                                                </a>
                                                <span class="text-[9px] font-bold text-gray-400 uppercase">DOCUMENTO PDF/DOCX</span>
                                            </div>
                                        </div>
                                        <button @click="deleteDocument(doc.id)" class="ml-4 p-2 text-gray-300 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-all opacity-0 group-hover:opacity-100">
                                            <TrashIcon class="h-5 w-5"/>
                                        </button>
                                    </div>
                                </div>
                                <div v-else class="py-16 text-center border-2 border-dashed border-gray-100 dark:border-gray-700 rounded-[2rem]">
                                    <div class="p-4 bg-gray-50 dark:bg-gray-900/50 rounded-full w-fit mx-auto mb-4 text-gray-300">
                                        <DocumentTextIcon class="h-10 w-10" />
                                    </div>
                                    <p class="text-sm text-gray-400 font-bold italic">No se han encontrado documentos adicionales en el repositorio.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>