<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import { ref } from 'vue';

const form = useForm({
    file: null,
});

const inputRef = ref(null);

const handleFileChange = (e) => {
    form.file = e.target.files[0];
};

const submit = () => {
    if (!form.file) {
        alert('Por favor selecciona un archivo primero.');
        return;
    }

    form.post(route('juzgados.import'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            alert('¡Importación completada con éxito!');
            form.reset();
            if(inputRef.value) inputRef.value.value = '';
        },
        onError: (errors) => {
            console.error(errors);
            alert('Ocurrió un error. Revisa que el archivo sea Excel (.xlsx) y no tenga filas vacías al inicio.');
        }
    });
};
</script>

<template>
    <Head title="Importar Juzgados" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-blue-500 leading-tight">
                Importar Juzgados
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        
                        <div class="mb-6 p-4 bg-yellow-50 border-l-4 border-yellow-400 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-200">
                            <h3 class="font-bold">⚠️ ANTES DE SUBIR:</h3>
                            <ul class="list-disc ml-5 text-sm mt-1">
                                <li>Abre tu Excel.</li>
                                <li>Borra las filas de arriba que dicen "CORTESIA GMH...", etc.</li>
                                <li><strong>La Fila 1 debe tener los títulos:</strong> NOMBRE, MUNICIPIO, etc.</li>
                            </ul>
                        </div>

                        <form @submit.prevent="submit" class="space-y-6">
                            
                            <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-6 text-center hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                <input 
                                    ref="inputRef"
                                    type="file" 
                                    @change="handleFileChange"
                                    accept=".xlsx, .xls"
                                    class="hidden"
                                    id="file-upload"
                                />
                                <label for="file-upload" class="cursor-pointer flex flex-col items-center">
                                    <span class="text-4xl mb-2">📂</span>
                                    <span class="text-sm font-medium text-indigo-600 dark:text-indigo-400">
                                        {{ form.file ? form.file.name : 'Clic aquí para seleccionar tu Excel limpio' }}
                                    </span>
                                </label>
                                <InputError :message="form.errors.file" class="mt-2" />
                            </div>

                            <!-- Barra de Progreso -->
                            <div v-if="form.progress" class="w-full bg-gray-200 rounded-full h-4 dark:bg-gray-700 mt-4 overflow-hidden">
                                <div class="bg-indigo-600 h-4 rounded-full transition-all duration-300 flex items-center justify-center text-xs text-white font-bold" 
                                     :style="{ width: form.progress.percentage + '%' }">
                                     {{ form.progress.percentage }}%
                                </div>
                            </div>

                            <div class="flex items-center justify-end gap-4 border-t pt-4 border-gray-100 dark:border-gray-700">
                                <Link :href="route('juzgados.index')" class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md text-sm font-medium hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                                    Cancelar
                                </Link>
                                
                                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                    <span v-if="form.processing">Procesando (Espere unos segundos)...</span>
                                    <span v-else>Iniciar Importación</span>
                                </PrimaryButton>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>