<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { useFormDraft } from '@/composables/useFormDraft';
import { 
    BuildingOffice2Icon, 
    ArrowLeftIcon,
    MapPinIcon,
    EnvelopeIcon,
    PhoneIcon,
    GlobeAltIcon,
    IdentificationIcon
} from '@heroicons/vue/24/outline';

const form = useForm({
    nombre: '',
    email: '',
    telefono: '',
    municipio: '',
    departamento: '',
    distrito: '',
});

const { clearDraft } = useFormDraft(form, 'draft:create:juzgados');

const submit = () => {
    form.post(route('juzgados.store'), {
        onSuccess: clearDraft,
    });
};
</script>

<template>
    <Head title="Crear Juzgado" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700">
                <div class="flex items-center gap-4">
                    <div class="p-3 bg-indigo-50 dark:bg-indigo-900/30 rounded-xl">
                        <BuildingOffice2Icon class="h-8 w-8 text-indigo-600 dark:text-indigo-400" />
                    </div>
                    <div>
                        <h2 class="font-black text-2xl text-gray-900 dark:text-white leading-tight">
                            Registrar Nuevo Despacho
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Complete los datos para añadir una nueva entidad judicial</p>
                    </div>
                </div>
                <Link :href="route('juzgados.index')">
                    <SecondaryButton class="flex items-center gap-2">
                        <ArrowLeftIcon class="h-4 w-4" />
                        <span class="hidden md:inline">Volver al Directorio</span>
                    </SecondaryButton>
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-8">
                    
                    <!-- Sección 1: Información General -->
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-2xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                        <div class="p-6 border-b border-gray-50 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/30 flex items-center gap-2">
                            <IdentificationIcon class="h-5 w-5 text-indigo-500" />
                            <h3 class="font-bold text-gray-900 dark:text-white">Identificación del Despacho</h3>
                        </div>
                        <div class="p-8 space-y-6">
                            <div>
                                <InputLabel for="nombre" value="Nombre Oficial del Despacho" class="!text-xs !font-bold !uppercase !tracking-wider !text-gray-500 mb-1" />
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <BuildingOffice2Icon class="h-5 w-5 text-gray-400" />
                                    </div>
                                    <TextInput 
                                        id="nombre" 
                                        type="text" 
                                        class="mt-1 block w-full pl-10 !rounded-xl !border-gray-200 focus:!ring-indigo-500" 
                                        v-model="form.nombre" 
                                        required 
                                        autofocus 
                                        placeholder="Ej: Juzgado 01 Civil Municipal de Bogotá" 
                                    />
                                </div>
                                <InputError class="mt-2" :message="form.errors.nombre" />
                            </div>

                            <div>
                                <InputLabel for="distrito" value="Distrito Judicial" class="!text-xs !font-bold !uppercase !tracking-wider !text-gray-500 mb-1" />
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <GlobeAltIcon class="h-5 w-5 text-gray-400" />
                                    </div>
                                    <TextInput 
                                        id="distrito" 
                                        type="text" 
                                        class="mt-1 block w-full pl-10 !rounded-xl !border-gray-200 focus:!ring-indigo-500" 
                                        v-model="form.distrito" 
                                        placeholder="Ej: Distrito Judicial de Bogotá"
                                    />
                                </div>
                                <InputError class="mt-2" :message="form.errors.distrito" />
                            </div>
                        </div>
                    </div>

                    <!-- Sección 2: Ubicación y Contacto -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <!-- Ubicación -->
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-2xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                            <div class="p-6 border-b border-gray-50 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/30 flex items-center gap-2">
                                <MapPinIcon class="h-5 w-5 text-red-500" />
                                <h3 class="font-bold text-gray-900 dark:text-white">Ubicación Geográfica</h3>
                            </div>
                            <div class="p-8 space-y-6">
                                <div>
                                    <InputLabel for="municipio" value="Municipio / Ciudad" class="!text-xs !font-bold !uppercase !tracking-wider !text-gray-500 mb-1" />
                                    <TextInput id="municipio" type="text" class="mt-1 block w-full !rounded-xl !border-gray-200" v-model="form.municipio" />
                                    <InputError class="mt-2" :message="form.errors.municipio" />
                                </div>
                                <div>
                                    <InputLabel for="departamento" value="Departamento" class="!text-xs !font-bold !uppercase !tracking-wider !text-gray-500 mb-1" />
                                    <TextInput id="departamento" type="text" class="mt-1 block w-full !rounded-xl !border-gray-200" v-model="form.departamento" />
                                    <InputError class="mt-2" :message="form.errors.departamento" />
                                </div>
                            </div>
                        </div>

                        <!-- Contacto -->
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-2xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                            <div class="p-6 border-b border-gray-50 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-700/30 flex items-center gap-2">
                                <EnvelopeIcon class="h-5 w-5 text-blue-500" />
                                <h3 class="font-bold text-gray-900 dark:text-white">Medios de Contacto</h3>
                            </div>
                            <div class="p-8 space-y-6">
                                <div>
                                    <InputLabel for="email" value="Correo Electrónico" class="!text-xs !font-bold !uppercase !tracking-wider !text-gray-500 mb-1" />
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <EnvelopeIcon class="h-4 w-4 text-gray-400" />
                                        </div>
                                        <TextInput 
                                            id="email" 
                                            type="email" 
                                            class="mt-1 block w-full pl-10 !rounded-xl !border-gray-200" 
                                            v-model="form.email" 
                                            placeholder="correo@cendoj.ramajudicial.gov.co" 
                                        />
                                    </div>
                                    <InputError class="mt-2" :message="form.errors.email" />
                                </div>
                                <div>
                                    <InputLabel for="telefono" value="Teléfono / Extensión" class="!text-xs !font-bold !uppercase !tracking-wider !text-gray-500 mb-1" />
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <PhoneIcon class="h-4 w-4 text-gray-400" />
                                        </div>
                                        <TextInput 
                                            id="telefono" 
                                            type="text" 
                                            class="mt-1 block w-full pl-10 !rounded-xl !border-gray-200" 
                                            v-model="form.telefono" 
                                        />
                                    </div>
                                    <InputError class="mt-2" :message="form.errors.telefono" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botones de Acción -->
                    <div class="flex items-center justify-end gap-4 bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-700">
                        <Link :href="route('juzgados.index')">
                            <SecondaryButton type="button" class="!px-8 !py-3">
                                Cancelar
                            </SecondaryButton>
                        </Link>
                        <PrimaryButton 
                            class="!px-10 !py-3 !bg-indigo-600 hover:!bg-indigo-700 !text-sm flex items-center gap-2" 
                            :disabled="form.processing"
                        >
                            <span v-if="form.processing">Procesando...</span>
                            <template v-else>
                                <BuildingOffice2Icon class="h-4 w-4" />
                                Guardar Despacho
                            </template>
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
