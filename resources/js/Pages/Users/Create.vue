<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import Multiselect from 'vue-multiselect';
import { 
    TrashIcon, PlusIcon, UserIcon, LockClosedIcon, 
    IdentificationIcon, MapPinIcon, ArrowLeftIcon, 
    ShieldCheckIcon, BriefcaseIcon, BuildingOffice2Icon 
} from '@heroicons/vue/24/outline';
import { useFormDraft } from '@/composables/useFormDraft';

// --- PROPS ---
defineProps({
    allCooperativas: Array,
    allEspecialidades: Array,
    personas: Array,
});

// --- FORMULARIO ---
const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    tipo_usuario: 'cliente',
    cooperativas: [],
    especialidades: [],
    persona_id: null,
    addresses: [],
});

const { clearDraft } = useFormDraft(form, 'draft:create:users');

// --- LÓGICA DE DIRECCIONES ---
function addAddress() {
    form.addresses.push({ address: '', city: '', details: '' });
}
function removeAddress(index) {
    form.addresses.splice(index, 1);
}

// --- SUBMIT ---
const submit = () => {
    form.post(route('admin.users.store'), {
        onSuccess: clearDraft,
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<style src="vue-multiselect/dist/vue-multiselect.css"></style>
<style>
.multiselect__tags {
    border-radius: 0.75rem !important;
    border-color: #e5e7eb !important;
    padding-top: 8px !important;
}
.dark .multiselect__tags {
    background-color: #111827 !important;
    border-color: #374151 !important;
}
.multiselect__option--highlight {
    background: #4f46e5 !important;
}
.multiselect__tag {
    background: #4f46e5 !important;
    border-radius: 6px !important;
}
</style>

<template>
    <Head title="Registrar Usuario" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('admin.users.index')" class="p-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 text-gray-400 hover:text-indigo-600 transition-all">
                    <ArrowLeftIcon class="h-5 w-5" />
                </Link>
                <div>
                    <h2 class="font-black text-2xl text-gray-900 dark:text-white leading-tight">
                        Registrar Nuevo Integrante
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Configure las credenciales y permisos de acceso.</p>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-8">
                    
                    <!-- SECCIÓN 1: IDENTIDAD Y ACCESO -->
                    <div class="bg-white dark:bg-gray-800 shadow-xl shadow-slate-200/50 dark:shadow-none rounded-3xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                        <div class="p-6 bg-gray-50/50 dark:bg-gray-700/30 border-b border-gray-100 dark:border-gray-700 flex items-center">
                            <div class="p-2 bg-white dark:bg-gray-800 rounded-lg shadow-sm mr-3">
                                <IdentificationIcon class="h-5 w-5 text-indigo-600" />
                            </div>
                            <h3 class="font-bold text-gray-900 dark:text-white uppercase tracking-widest text-xs">Identidad y Seguridad</h3>
                        </div>
                        
                        <div class="p-8 space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <InputLabel for="name" value="Nombre Completo" class="font-bold text-xs uppercase text-gray-400 mb-1" />
                                    <TextInput id="name" type="text" class="mt-1 block w-full bg-gray-50/50 focus:bg-white transition-all" v-model="form.name" required placeholder="Ej: Juan Pérez" />
                                    <InputError class="mt-2" :message="form.errors.name" />
                                </div>
                                <div>
                                    <InputLabel for="email" value="Correo Electrónico" class="font-bold text-xs uppercase text-gray-400 mb-1" />
                                    <TextInput id="email" type="email" class="mt-1 block w-full bg-gray-50/50 focus:bg-white transition-all" v-model="form.email" required placeholder="correo@ejemplo.com" />
                                    <InputError class="mt-2" :message="form.errors.email" />
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <InputLabel for="password" value="Contraseña de Acceso" class="font-bold text-xs uppercase text-gray-400 mb-1" />
                                    <TextInput id="password" type="password" class="mt-1 block w-full bg-gray-50/50 focus:bg-white transition-all" v-model="form.password" required />
                                    <InputError class="mt-2" :message="form.errors.password" />
                                </div>
                                <div>
                                    <InputLabel for="password_confirmation" value="Confirmar Contraseña" class="font-bold text-xs uppercase text-gray-400 mb-1" />
                                    <TextInput id="password_confirmation" type="password" class="mt-1 block w-full bg-gray-50/50 focus:bg-white transition-all" v-model="form.password_confirmation" required />
                                    <InputError class="mt-2" :message="form.errors.password_confirmation" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN 2: ROL Y PERMISOS -->
                    <div class="bg-white dark:bg-gray-800 shadow-xl shadow-slate-200/50 dark:shadow-none rounded-3xl border border-gray-100 dark:border-gray-700 overflow-visible relative z-30">
                        <div class="p-6 bg-gray-50/50 dark:bg-gray-700/30 border-b border-gray-100 dark:border-gray-700 flex items-center rounded-t-3xl">
                            <div class="p-2 bg-white dark:bg-gray-800 rounded-lg shadow-sm mr-3">
                                <ShieldCheckIcon class="h-5 w-5 text-indigo-600" />
                            </div>
                            <h3 class="font-bold text-gray-900 dark:text-white uppercase tracking-widest text-xs">Rol y Asignaciones</h3>
                        </div>

                        <div class="p-8 space-y-8 overflow-visible">
                            <div>
                                <InputLabel for="tipo_usuario" value="Tipo de Perfil" class="font-bold text-xs uppercase text-gray-400 mb-2" />
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                    <button v-for="role in ['admin', 'abogado', 'gestor', 'cliente']" :key="role" type="button" @click="form.tipo_usuario = role" 
                                        :class="form.tipo_usuario === role ? 'border-indigo-600 bg-indigo-50 text-indigo-700 ring-2 ring-indigo-500/20' : 'border-gray-200 bg-white text-gray-500 hover:border-indigo-300'"
                                        class="flex flex-col items-center p-4 border-2 rounded-2xl transition-all group">
                                        <component :is="role === 'admin' ? ShieldCheckIcon : (role === 'abogado' ? BriefcaseIcon : (role === 'gestor' ? BuildingOffice2Icon : UserIcon))" 
                                            class="h-6 w-6 mb-2 transition-transform group-hover:scale-110" />
                                        <span class="text-xs font-black uppercase tracking-tighter">{{ role }}</span>
                                    </button>
                                </div>
                                <InputError class="mt-2" :message="form.errors.tipo_usuario" />
                            </div>

                            <div v-if="form.tipo_usuario === 'abogado' || form.tipo_usuario === 'gestor'" class="animate-in fade-in slide-in-from-top-2 relative z-40">
                                <InputLabel value="Especialidades Jurídicas" class="font-bold text-xs uppercase text-gray-400 mb-2" />
                                <Multiselect
                                    v-model="form.especialidades"
                                    :options="allEspecialidades.map(e => e.id)"
                                    :custom-label="opt => allEspecialidades.find(e => e.id === opt)?.nombre"
                                    :multiple="true"
                                    placeholder="Buscar especialidades..."
                                    class="relative z-50"
                                />
                                <InputError :message="form.errors.especialidades" class="mt-2" />
                            </div>

                            <div v-if="form.tipo_usuario !== 'admin'" class="animate-in fade-in slide-in-from-top-2 relative z-30">
                                <InputLabel value="Cooperativas Asignadas" class="font-bold text-xs uppercase text-gray-400 mb-2" />
                                <Multiselect
                                    v-model="form.cooperativas"
                                    :options="allCooperativas.map(c => c.id)"
                                    :custom-label="opt => allCooperativas.find(c => c.id === opt)?.nombre"
                                    :multiple="true"
                                    placeholder="Vincular cooperativas..."
                                    class="relative z-40"
                                />
                                <InputError :message="form.errors.cooperativas" class="mt-2" />
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN 3: CONTACTO -->
                    <div class="bg-white dark:bg-gray-800 shadow-xl shadow-slate-200/50 dark:shadow-none rounded-3xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                        <div class="p-6 bg-gray-50/50 dark:bg-gray-700/30 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
                            <div class="flex items-center">
                                <div class="p-2 bg-white dark:bg-gray-800 rounded-lg shadow-sm mr-3">
                                    <MapPinIcon class="h-5 w-5 text-indigo-600" />
                                </div>
                                <h3 class="font-bold text-gray-900 dark:text-white uppercase tracking-widest text-xs">Información de Contacto</h3>
                            </div>
                            <button type="button" @click="addAddress" class="text-[10px] font-black uppercase tracking-widest bg-indigo-600 text-white px-3 py-1.5 rounded-lg hover:bg-indigo-700 transition-colors shadow-lg shadow-indigo-200 dark:shadow-none">
                                + Añadir Dirección
                            </button>
                        </div>

                        <div class="p-8 space-y-6">
                            <div v-for="(address, index) in form.addresses" :key="index" class="p-6 bg-gray-50 dark:bg-gray-900/40 rounded-2xl border-2 border-dashed border-gray-200 dark:border-gray-700 relative animate-in zoom-in-95">
                                <button type="button" @click="removeAddress(index)" class="absolute -top-3 -right-3 bg-rose-500 text-white p-1.5 rounded-full shadow-lg hover:bg-rose-600 transition-transform hover:scale-110">
                                    <TrashIcon class="h-4 w-4" />
                                </button>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <InputLabel :for="'address_' + index" value="Dirección Física" class="text-[10px] font-black text-gray-400" />
                                        <TextInput :id="'address_' + index" type="text" class="mt-1 block w-full bg-white" v-model="address.address" placeholder="Calle, Carrera, #..." />
                                    </div>
                                    <div>
                                        <InputLabel :for="'city_' + index" value="Ciudad" class="text-[10px] font-black text-gray-400" />
                                        <TextInput :id="'city_' + index" type="text" class="mt-1 block w-full bg-white" v-model="address.city" placeholder="Ej: Bogotá" />
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <InputLabel :for="'details_' + index" value="Indicaciones Adicionales" class="text-[10px] font-black text-gray-400" />
                                    <TextInput :id="'details_' + index" type="text" class="mt-1 block w-full bg-white" v-model="address.details" placeholder="Torre, Apto, Oficina..." />
                                </div>
                            </div>

                            <div v-if="form.addresses.length === 0" class="py-12 border-2 border-dashed border-gray-100 dark:border-gray-700 rounded-3xl text-center">
                                <div class="p-3 bg-gray-50 dark:bg-gray-900/50 rounded-full w-fit mx-auto mb-3 text-gray-300">
                                    <MapPinIcon class="h-8 w-8" />
                                </div>
                                <p class="text-sm text-gray-400 font-medium italic px-8">No se han registrado direcciones de contacto para este usuario.</p>
                            </div>
                        </div>
                    </div>

                    <!-- BOTÓN FINAL -->
                    <div class="flex items-center justify-between p-4 bg-indigo-900 rounded-3xl shadow-2xl shadow-indigo-200 dark:shadow-none">
                        <p class="text-indigo-100 text-xs font-bold pl-4">Verifique que todos los datos sean correctos antes de guardar.</p>
                        <PrimaryButton class="!bg-white !text-indigo-900 !rounded-2xl !px-8 !py-4 !text-sm !font-black !shadow-none hover:!bg-indigo-50 transition-colors" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                            GUARDAR USUARIO
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
