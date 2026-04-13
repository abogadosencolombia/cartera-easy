<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputError from '@/Components/InputError.vue';
import { ref } from 'vue';
import Swal from 'sweetalert2';
import { 
    ArrowUpTrayIcon, 
    ArrowLeftIcon, 
    DocumentIcon,
    ExclamationTriangleIcon,
    CheckCircleIcon,
    CloudArrowUpIcon,
    InformationCircleIcon
} from '@heroicons/vue/24/outline';

const form = useForm({
    file: null,
});

const inputRef = ref(null);
const isDragging = ref(false);

const handleFileChange = (e) => {
    form.file = e.target.files[0];
};

const handleDrop = (e) => {
    isDragging.value = false;
    const files = e.dataTransfer.files;
    if (files.length) {
        form.file = files[0];
    }
};

const submit = () => {
    if (!form.file) {
        Swal.fire({
            title: 'Archivo Requerido',
            text: 'Por favor selecciona un archivo Excel (.xlsx) para continuar.',
            icon: 'info',
            confirmButtonColor: '#4f46e5'
        });
        return;
    }

    form.post(route('juzgados.import'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({
                title: '¡Importación Exitosa!',
                text: 'El directorio de juzgados ha sido actualizado correctamente.',
                icon: 'success',
                confirmButtonColor: '#4f46e5'
            });
            form.reset();
            if(inputRef.value) inputRef.value.value = '';
        },
        onError: (errors) => {
            console.error(errors);
            Swal.fire({
                title: 'Error en Importación',
                text: 'No pudimos procesar el archivo. Revisa que el formato sea correcto y no tenga filas vacías al inicio.',
                icon: 'error',
                confirmButtonColor: '#ef4444'
            });
        }
    });
};
</script>

<template>
    <Head title="Importar Juzgados" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-indigo-50 dark:bg-indigo-900/30 rounded-xl">
                        <ArrowUpTrayIcon class="h-8 w-8 text-indigo-600 dark:text-indigo-400" />
                    </div>
                    <div>
                        <h2 class="font-black text-2xl text-gray-900 dark:text-white leading-tight">
                            Importar Directorio masivo
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Carga de despachos mediante archivos de Microsoft Excel</p>
                    </div>
                </div>
                <Link :href="route('juzgados.index')">
                    <SecondaryButton class="flex items-center gap-2">
                        <ArrowLeftIcon class="h-4 w-4" />
                        <span class="hidden md:inline">Volver</span>
                    </SecondaryButton>
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-8">
                
                <!-- Guía de Formato -->
                <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-100 dark:border-amber-800 rounded-2xl p-6 flex gap-4">
                    <div class="flex-shrink-0">
                        <ExclamationTriangleIcon class="h-6 w-6 text-amber-600" />
                    </div>
                    <div>
                        <h3 class="font-bold text-amber-800 dark:text-amber-400">Instrucciones críticas para el archivo Excel:</h3>
                        <div class="mt-2 grid grid-cols-1 md:grid-cols-2 gap-4">
                            <ul class="text-sm text-amber-700 dark:text-amber-500 space-y-1 list-disc ml-4">
                                <li>La <b>Fila 1</b> debe contener exactamente los encabezados.</li>
                                <li>No dejar filas en blanco al principio del documento.</li>
                                <li>Columnas requeridas: <b>NOMBRE, MUNICIPIO, EMAIL.</b></li>
                            </ul>
                            <div class="bg-white/50 dark:bg-black/20 p-3 rounded-lg border border-amber-200 dark:border-amber-700/50">
                                <p class="text-[10px] font-black uppercase tracking-tighter text-amber-800/50 mb-1">Estructura Sugerida:</p>
                                <div class="grid grid-cols-3 gap-1 text-[9px] font-mono font-bold">
                                    <span class="border p-1 bg-white">NOMBRE</span>
                                    <span class="border p-1 bg-white">MUNICIPIO</span>
                                    <span class="border p-1 bg-white">EMAIL</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Formulario de Carga -->
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-3xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                    <div class="p-10">
                        <form @submit.prevent="submit" class="space-y-8">
                            
                            <div 
                                @dragover.prevent="isDragging = true"
                                @dragleave.prevent="isDragging = false"
                                @drop.prevent="handleDrop"
                                class="relative border-2 border-dashed rounded-3xl p-12 text-center transition-all duration-300 group"
                                :class="[
                                    isDragging 
                                        ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20 scale-[1.01]' 
                                        : 'border-gray-300 dark:border-gray-600 hover:border-indigo-400 hover:bg-gray-50/50 dark:hover:bg-gray-700/30'
                                ]"
                            >
                                <input 
                                    ref="inputRef"
                                    type="file" 
                                    @change="handleFileChange"
                                    accept=".xlsx, .xls"
                                    class="hidden"
                                    id="file-upload"
                                />
                                
                                <label for="file-upload" class="cursor-pointer flex flex-col items-center">
                                    <div class="w-20 h-20 bg-indigo-50 dark:bg-indigo-900/30 rounded-full flex items-center justify-center mb-4 group-hover:scale-110 transition-transform duration-500">
                                        <CloudArrowUpIcon class="h-10 w-10 text-indigo-600 dark:text-indigo-400" />
                                    </div>
                                    
                                    <template v-if="!form.file">
                                        <h4 class="text-xl font-bold text-gray-900 dark:text-white mb-2">Selecciona tu archivo de Excel</h4>
                                        <p class="text-sm text-gray-500 dark:text-gray-400 max-w-xs mx-auto">Arrastra y suelta tu archivo aquí o haz clic para buscar en tu equipo</p>
                                        <div class="mt-6 inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-xl text-sm font-bold shadow-lg shadow-indigo-200 dark:shadow-none hover:bg-indigo-700 transition-colors">
                                            Buscar Archivo
                                        </div>
                                    </template>
                                    
                                    <template v-else>
                                        <div class="flex items-center gap-3 bg-white dark:bg-gray-700 p-4 rounded-2xl shadow-sm border border-indigo-100 dark:border-indigo-900/50">
                                            <div class="p-2 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg">
                                                <DocumentIcon class="h-6 w-6 text-emerald-600 dark:text-emerald-400" />
                                            </div>
                                            <div class="text-left">
                                                <p class="text-sm font-bold text-gray-900 dark:text-white truncate max-w-[200px]">{{ form.file.name }}</p>
                                                <p class="text-[10px] text-gray-500 uppercase font-black tracking-widest">Listo para importar</p>
                                            </div>
                                            <button @click.stop.prevent="form.file = null" class="ml-4 p-1 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-lg transition-colors">
                                                <XMarkIcon class="h-5 w-5 text-gray-400" />
                                            </button>
                                        </div>
                                    </template>
                                </label>
                                
                                <InputError :message="form.errors.file" class="mt-4" />
                            </div>

                            <!-- Barra de Progreso -->
                            <div v-if="form.progress" class="space-y-2">
                                <div class="flex justify-between text-xs font-bold text-indigo-600 uppercase tracking-widest">
                                    <span>Subiendo archivo...</span>
                                    <span>{{ form.progress.percentage }}%</span>
                                </div>
                                <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-3 overflow-hidden border border-gray-200 dark:border-gray-600">
                                    <div class="bg-indigo-600 h-full transition-all duration-300" 
                                         :style="{ width: form.progress.percentage + '%' }">
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between gap-4 pt-6">
                                <div class="flex items-center gap-2 text-xs text-gray-400 font-medium">
                                    <InformationCircleIcon class="h-4 w-4" />
                                    Formatos soportados: .xlsx, .xls
                                </div>
                                <PrimaryButton 
                                    class="!px-10 !py-4 !bg-indigo-600 hover:!bg-indigo-700 shadow-xl shadow-indigo-100 dark:shadow-none !rounded-2xl flex items-center gap-2" 
                                    :class="{ 'opacity-50': form.processing }" 
                                    :disabled="form.processing"
                                >
                                    <template v-if="form.processing">
                                        <ArrowPathIcon class="h-5 w-5 animate-spin" />
                                        <span>Procesando Directorio...</span>
                                    </template>
                                    <template v-else>
                                        <CheckCircleIcon class="h-5 w-5" />
                                        <span>Iniciar Importación</span>
                                    </template>
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                    
                    <div class="bg-gray-50 dark:bg-gray-700/30 p-6 border-t border-gray-100 dark:border-gray-700 text-center">
                        <p class="text-xs text-gray-500 dark:text-gray-400 italic">
                            Esta herramienta actualizará la base de datos de despachos judiciales. Si el nombre del despacho ya existe, se omitirá o actualizará según la configuración del sistema.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script>
import { ArrowPathIcon, XMarkIcon } from '@heroicons/vue/24/outline';
export default {
    components: { ArrowPathIcon, XMarkIcon }
}
</script>
