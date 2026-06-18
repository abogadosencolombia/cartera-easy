<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { 
    BuildingOffice2Icon, 
    ArrowLeftIcon,
    MapPinIcon,
    EnvelopeIcon,
    PhoneIcon,
    GlobeAltIcon,
    IdentificationIcon,
    PencilIcon
} from '@heroicons/vue/24/outline';

const props = defineProps(['juzgado']);

const form = useForm({
    nombre: props.juzgado.nombre,
    email: props.juzgado.email,
    telefono: props.juzgado.telefono,
    municipio: props.juzgado.municipio,
    departamento: props.juzgado.departamento,
    distrito: props.juzgado.distrito,
});

const submit = () => {
    form.put(route('juzgados.update', props.juzgado.id));
};
</script>

<template>
    <Head title="Editar Juzgado" />

    <AuthenticatedLayout>
        <template #header>
            <div class="sticky top-0 z-10 backdrop-blur-xl bg-white/70 dark:bg-gray-800/70 p-6 rounded-[2rem] shadow-xl shadow-gray-200/50 dark:shadow-none border border-white/20 dark:border-gray-700/50 mb-8 transition-all duration-500">
                <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                    <div class="flex items-center gap-5">
                        <div class="p-4 bg-gradient-to-br from-blue-500 to-cyan-600 rounded-2xl shadow-lg shadow-blue-200 dark:shadow-none transform transition-transform hover:scale-105 duration-300">
                            <PencilIcon class="h-8 w-8 text-white" />
                        </div>
                        <div>
                            <div class="flex items-center gap-3">
                                <h2 class="font-black text-3xl text-gray-900 dark:text-white leading-tight tracking-tight">
                                    Editar Despacho
                                </h2>
                                <span class="hidden sm:inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300 uppercase tracking-wider">
                                    Edición
                                </span>
                            </div>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 font-medium italic">Actualizando información de: <span class="font-bold text-gray-700 dark:text-gray-200">{{ juzgado.nombre }}</span></p>
                        </div>
                    </div>
                    <Link :href="route('juzgados.index')" class="group">
                        <SecondaryButton class="!rounded-full !py-3 !px-6 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-all duration-300">
                            <div class="flex items-center gap-2">
                                <ArrowLeftIcon class="h-4 w-4 transform group-hover:-translate-x-1 transition-transform duration-300" />
                                <span class="font-bold">Volver al Directorio</span>
                            </div>
                        </SecondaryButton>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-8">
                    
                    <!-- Sección 1: Información General -->
                    <div class="bg-white dark:bg-gray-800 shadow-xl shadow-gray-200/40 dark:shadow-none rounded-[2.5rem] border border-gray-100 dark:border-gray-700 overflow-hidden group transition-all duration-500 hover:-translate-y-1 hover:shadow-2xl hover:border-blue-200 dark:hover:border-blue-800 relative">
                        <div class="absolute top-0 left-0 w-1.5 h-full bg-blue-500"></div>
                        <div class="p-6 border-b border-gray-50 dark:border-gray-700 bg-gray-50/30 dark:bg-gray-700/30 flex items-center gap-2">
                            <IdentificationIcon class="h-6 w-6 text-blue-500 group-hover:scale-125 group-hover:rotate-6 transition-all duration-300 ml-2" />
                            <h3 class="font-black text-gray-900 dark:text-white tracking-tight">Identificación del Despacho</h3>
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
                                        class="mt-1 block w-full pl-10 !rounded-xl !border-gray-200 transition-all duration-200 hover:border-blue-300 dark:hover:border-blue-600 hover:shadow-sm focus:!ring-blue-500 transition-all duration-200 hover:border-blue-300 dark:hover:border-blue-600 hover:shadow-sm" 
                                        v-model="form.nombre" 
                                        required 
                                        autofocus 
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
                                        class="mt-1 block w-full pl-10 !rounded-xl !border-gray-200 transition-all duration-200 hover:border-blue-300 dark:hover:border-blue-600 hover:shadow-sm focus:!ring-blue-500 transition-all duration-200 hover:border-blue-300 dark:hover:border-blue-600 hover:shadow-sm" 
                                        v-model="form.distrito" 
                                    />
                                </div>
                                <InputError class="mt-2" :message="form.errors.distrito" />
                            </div>
                        </div>
                    </div>

                    <!-- Sección 2: Ubicación y Contacto -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <!-- Ubicación -->
                        <div class="bg-white dark:bg-gray-800 shadow-xl shadow-gray-200/40 dark:shadow-none rounded-[2.5rem] border border-gray-100 dark:border-gray-700 overflow-hidden group transition-all duration-500 hover:-translate-y-1 hover:shadow-2xl hover:border-red-100 dark:hover:border-red-900/30 relative">
                            <div class="absolute top-0 left-0 w-1.5 h-full bg-red-500"></div>
                            <div class="p-6 border-b border-gray-50 dark:border-gray-700 bg-gray-50/30 dark:bg-gray-700/30 flex items-center gap-2">
                                <MapPinIcon class="h-6 w-6 text-red-500 group-hover:scale-125 group-hover:rotate-6 transition-all duration-300 ml-2" />
                                <h3 class="font-black text-gray-900 dark:text-white tracking-tight">Ubicación</h3>
                            </div>
                            <div class="p-8 space-y-6">
                                <div>
                                    <InputLabel for="municipio" value="Municipio / Ciudad" class="!text-xs !font-bold !uppercase !tracking-wider !text-gray-500 mb-1" />
                                    <TextInput id="municipio" type="text" class="mt-1 block w-full !rounded-xl !border-gray-200 transition-all duration-200 hover:border-blue-300 dark:hover:border-blue-600 hover:shadow-sm" v-model="form.municipio" />
                                    <InputError class="mt-2" :message="form.errors.municipio" />
                                </div>
                                <div>
                                    <InputLabel for="departamento" value="Departamento" class="!text-xs !font-bold !uppercase !tracking-wider !text-gray-500 mb-1" />
                                    <TextInput id="departamento" type="text" class="mt-1 block w-full !rounded-xl !border-gray-200 transition-all duration-200 hover:border-blue-300 dark:hover:border-blue-600 hover:shadow-sm" v-model="form.departamento" />
                                    <InputError class="mt-2" :message="form.errors.departamento" />
                                </div>
                            </div>
                        </div>

                        <!-- Contacto -->
                        <div class="bg-white dark:bg-gray-800 shadow-xl shadow-gray-200/40 dark:shadow-none rounded-[2.5rem] border border-gray-100 dark:border-gray-700 overflow-hidden group transition-all duration-500 hover:-translate-y-1 hover:shadow-2xl hover:border-cyan-100 dark:hover:border-cyan-900/30 relative">
                            <div class="absolute top-0 left-0 w-1.5 h-full bg-cyan-500"></div>
                            <div class="p-6 border-b border-gray-50 dark:border-gray-700 bg-gray-50/30 dark:bg-gray-700/30 flex items-center gap-2">
                                <EnvelopeIcon class="h-6 w-6 text-cyan-500 group-hover:scale-125 group-hover:rotate-6 transition-all duration-300 ml-2" />
                                <h3 class="font-black text-gray-900 dark:text-white tracking-tight">Contacto</h3>
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
                                            class="mt-1 block w-full pl-10 !rounded-xl !border-gray-200 transition-all duration-200 hover:border-blue-300 dark:hover:border-blue-600 hover:shadow-sm" 
                                            v-model="form.email" 
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
                                            class="mt-1 block w-full pl-10 !rounded-xl !border-gray-200 transition-all duration-200 hover:border-blue-300 dark:hover:border-blue-600 hover:shadow-sm" 
                                            v-model="form.telefono" 
                                        />
                                    </div>
                                    <InputError class="mt-2" :message="form.errors.telefono" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botones de Acción -->
                    <div class="flex items-center justify-end gap-5 bg-white/50 dark:bg-gray-800/50 backdrop-blur-sm p-8 rounded-[2.5rem] shadow-xl shadow-gray-200/30 dark:shadow-none border border-gray-100 dark:border-gray-700">
                        <Link :href="route('juzgados.index')">
                            <SecondaryButton type="button" class="!px-8 !py-3 !rounded-full hover:!bg-gray-100 dark:hover:!bg-gray-700 transition-all duration-300">
                                Cancelar
                            </SecondaryButton>
                        </Link>
                        <PrimaryButton 
                            class="!px-12 !py-4 !rounded-full !bg-gradient-to-r !from-blue-600 !to-cyan-600 hover:!from-blue-700 hover:!to-cyan-700 !text-sm font-bold flex items-center gap-3 transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl hover:shadow-blue-500/40 active:scale-95 shadow-lg shadow-blue-500/20" 
                            :disabled="form.processing"
                        >
                            <span v-if="form.processing">Procesando...</span>
                            <template v-else>
                                <PencilIcon class="h-5 w-5" />
                                Actualizar Despacho
                            </template>
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
