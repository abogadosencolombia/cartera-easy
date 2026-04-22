<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Multiselect from 'vue-multiselect';
import { 
    TrashIcon, PlusIcon, UserIcon, LockClosedIcon, 
    IdentificationIcon, MapPinIcon, ArrowLeftIcon, 
    ShieldCheckIcon, BriefcaseIcon, BuildingOffice2Icon,
    BellIcon, KeyIcon, PencilSquareIcon
} from '@heroicons/vue/24/outline';

// --- PROPS ---
const props = defineProps({
    user: Object,
    allCooperativas: Array,
    allEspecialidades: Array,
    personas: Array,
});

// --- LÓGICA DE EDICIÓN ---
const authUser = usePage().props.auth.user;
const isEditingSelf = computed(() => props.user.id === authUser.id);

// --- FORMULARIO ---
const form = useForm({
    _method: 'patch',
    name: props.user.name,
    email: props.user.email,
    tipo_usuario: props.user.tipo_usuario,
    estado_activo: props.user.estado_activo,
    password: '',
    password_confirmation: '',
    cooperativas: props.user.cooperativas.map(c => c.id),
    especialidades: props.user.especialidades.map(e => e.id),
    persona_id: props.user.persona_id,
    preferencias_notificacion: props.user.preferencias_notificacion || { 'email': true, 'in-app': true },
    addresses: props.user.addresses || [],
});

// --- LÓGICA DE DIRECCIONES ---
function addAddress() {
    form.addresses.push({ address: '', city: '', details: '' });
}
function removeAddress(index) {
    form.addresses.splice(index, 1);
}

// --- SUBMIT ---
const submit = () => {
    form.post(route('admin.users.update', props.user.id), {
        preserveScroll: true,
    });
};

// --- ICONOS DE ROL ---
const roleIcons = {
    admin: ShieldCheckIcon,
    abogado: BriefcaseIcon,
    gestor: BuildingOffice2Icon,
    cliente: UserIcon
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
    <Head :title="'Editar: ' + user.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Link :href="route('admin.users.index')" class="p-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 text-gray-400 hover:text-indigo-600 transition-all">
                    <ArrowLeftIcon class="h-5 w-5" />
                </Link>
                <div>
                    <h2 class="font-black text-2xl text-gray-900 dark:text-white leading-tight">
                        Editar Usuario
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 font-medium">Actualizando el perfil de: <span class="text-indigo-600 font-bold">{{ user.name }}</span></p>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-8">
                    
                    <!-- SECCIÓN 1: IDENTIDAD -->
                    <div class="bg-white dark:bg-gray-800 shadow-xl shadow-slate-200/50 dark:shadow-none rounded-3xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                        <div class="p-6 bg-gray-50/50 dark:bg-gray-700/30 border-b border-gray-100 dark:border-gray-700 flex items-center rounded-t-3xl">
                            <div class="p-2 bg-white dark:bg-gray-800 rounded-lg shadow-sm mr-3 text-indigo-600">
                                <IdentificationIcon class="h-5 w-5" />
                            </div>
                            <h3 class="font-bold text-gray-900 dark:text-white uppercase tracking-widest text-xs">Identidad Básica</h3>
                        </div>
                        
                        <div class="p-8 space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <InputLabel for="name" value="Nombre Completo" class="text-[10px] font-black uppercase text-gray-400" />
                                    <TextInput id="name" type="text" class="mt-1 block w-full bg-gray-50 border-transparent focus:bg-white" v-model="form.name" required />
                                    <InputError class="mt-2" :message="form.errors.name" />
                                </div>
                                <div>
                                    <InputLabel for="email" value="Correo Electrónico" class="text-[10px] font-black uppercase text-gray-400" />
                                    <TextInput id="email" type="email" class="mt-1 block w-full bg-gray-50 border-transparent focus:bg-white" v-model="form.email" required />
                                    <InputError class="mt-2" :message="form.errors.email" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN 2: ROL Y PERMISOS (CON Z-INDEX FIX) -->
                    <div class="bg-white dark:bg-gray-800 shadow-xl shadow-slate-200/50 dark:shadow-none rounded-3xl border border-gray-100 dark:border-gray-700 overflow-visible relative z-30">
                        <div class="p-6 bg-gray-50/50 dark:bg-gray-700/30 border-b border-gray-100 dark:border-gray-700 flex items-center rounded-t-3xl">
                            <div class="p-2 bg-white dark:bg-gray-800 rounded-lg shadow-sm mr-3 text-indigo-600">
                                <ShieldCheckIcon class="h-5 w-5" />
                            </div>
                            <h3 class="font-bold text-gray-900 dark:text-white uppercase tracking-widest text-xs">Rol y Asignaciones</h3>
                        </div>

                        <div class="p-8 space-y-8 overflow-visible">
                            <div>
                                <InputLabel value="Tipo de Perfil" class="text-[10px] font-black uppercase text-gray-400 mb-3" />
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                    <button v-for="role in ['admin', 'abogado', 'gestor', 'cliente']" :key="role" type="button" 
                                        @click="!isEditingSelf ? form.tipo_usuario = role : null" 
                                        :disabled="isEditingSelf"
                                        :class="[
                                            form.tipo_usuario === role ? 'border-indigo-600 bg-indigo-50 text-indigo-700 ring-2 ring-indigo-500/20' : 'border-gray-200 bg-white text-gray-500 hover:border-indigo-300',
                                            isEditingSelf && form.tipo_usuario !== role ? 'opacity-40 grayscale cursor-not-allowed' : ''
                                        ]"
                                        class="flex flex-col items-center p-4 border-2 rounded-2xl transition-all group">
                                        <component :is="roleIcons[role]" class="h-6 w-6 mb-2" />
                                        <span class="text-[10px] font-black uppercase tracking-tighter">{{ role }}</span>
                                    </button>
                                </div>
                                <p v-if="isEditingSelf" class="text-[10px] text-amber-600 font-bold mt-2 italic">* No puedes cambiar tu propio rol para evitar bloqueos.</p>
                                <InputError class="mt-2" :message="form.errors.tipo_usuario" />
                            </div>

                            <div v-if="form.tipo_usuario === 'abogado' || form.tipo_usuario === 'gestor'" class="relative z-40">
                                <InputLabel value="Especialidades" class="text-[10px] font-black uppercase text-gray-400 mb-2" />
                                <Multiselect
                                    v-model="form.especialidades"
                                    :options="allEspecialidades.map(e => e.id)"
                                    :custom-label="opt => allEspecialidades.find(e => e.id === opt)?.nombre"
                                    :multiple="true"
                                    placeholder="Seleccionar..."
                                />
                                <InputError :message="form.errors.especialidades" class="mt-2" />
                            </div>

                            <div v-if="form.tipo_usuario !== 'admin'" class="relative z-30">
                                <InputLabel value="Cooperativas" class="text-[10px] font-black uppercase text-gray-400 mb-2" />
                                <Multiselect
                                    v-model="form.cooperativas"
                                    :options="allCooperativas.map(c => c.id)"
                                    :custom-label="opt => allCooperativas.find(c => c.id === opt)?.nombre"
                                    :multiple="true"
                                    placeholder="Seleccionar..."
                                />
                                <InputError :message="form.errors.cooperativas" class="mt-2" />
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN 3: CONTACTO -->
                    <div class="bg-white dark:bg-gray-800 shadow-xl shadow-slate-200/50 dark:shadow-none rounded-3xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                        <div class="p-6 bg-gray-50/50 dark:bg-gray-700/30 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center rounded-t-3xl">
                            <div class="flex items-center">
                                <div class="p-2 bg-white dark:bg-gray-800 rounded-lg shadow-sm mr-3 text-indigo-600">
                                    <MapPinIcon class="h-5 w-5" />
                                </div>
                                <h3 class="font-bold text-gray-900 dark:text-white uppercase tracking-widest text-xs">Ubicaciones de Contacto</h3>
                            </div>
                            <button type="button" @click="addAddress" class="text-[10px] font-black uppercase tracking-widest bg-indigo-600 text-white px-3 py-1.5 rounded-lg hover:bg-indigo-700 transition-all">
                                + Añadir Otra
                            </button>
                        </div>

                        <div class="p-8 space-y-6">
                            <div v-for="(address, index) in form.addresses" :key="index" class="p-6 bg-gray-50 dark:bg-gray-900/40 rounded-3xl border-2 border-dashed border-gray-200 dark:border-gray-700 relative animate-in zoom-in-95">
                                <button type="button" @click="removeAddress(index)" class="absolute -top-3 -right-3 bg-rose-500 text-white p-1.5 rounded-full shadow-lg hover:scale-110 transition-transform">
                                    <TrashIcon class="h-4 w-4" />
                                </button>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <InputLabel value="Dirección Física" class="text-[10px] font-black text-gray-400" />
                                        <TextInput type="text" class="mt-1 block w-full bg-white" v-model="address.address" />
                                    </div>
                                    <div>
                                        <InputLabel value="Ciudad" class="text-[10px] font-black text-gray-400" />
                                        <TextInput type="text" class="mt-1 block w-full bg-white" v-model="address.city" />
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <InputLabel value="Detalles" class="text-[10px] font-black text-gray-400" />
                                    <TextInput type="text" class="mt-1 block w-full bg-white" v-model="address.details" />
                                </div>
                            </div>
                            <div v-if="form.addresses.length === 0" class="py-10 text-center border-2 border-dashed border-gray-100 dark:border-gray-700 rounded-[2rem] text-gray-400 text-xs italic">
                                No hay direcciones registradas.
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN 4: PREFERENCIAS -->
                    <div class="bg-white dark:bg-gray-800 shadow-xl shadow-slate-200/50 dark:shadow-none rounded-3xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                        <div class="p-6 bg-gray-50/50 dark:bg-gray-700/30 border-b border-gray-100 dark:border-gray-700 flex items-center rounded-t-3xl">
                            <div class="p-2 bg-white dark:bg-gray-800 rounded-lg shadow-sm mr-3 text-indigo-600">
                                <BellIcon class="h-5 w-5" />
                            </div>
                            <h3 class="font-bold text-gray-900 dark:text-white uppercase tracking-widest text-xs">Notificaciones</h3>
                        </div>
                        <div class="p-8 grid grid-cols-1 md:grid-cols-2 gap-6">
                            <label class="flex items-center p-4 bg-gray-50 dark:bg-gray-900 rounded-2xl cursor-pointer hover:bg-indigo-50 transition-colors">
                                <Checkbox v-model:checked="form.preferencias_notificacion['in-app']" class="rounded-lg h-5 w-5" />
                                <span class="ml-3 text-sm font-bold text-gray-700 dark:text-gray-300">Alertas en la Aplicación</span>
                            </label>
                            <label class="flex items-center p-4 bg-gray-50 dark:bg-gray-900 rounded-2xl cursor-pointer hover:bg-indigo-50 transition-colors">
                                <Checkbox v-model:checked="form.preferencias_notificacion.email" class="rounded-lg h-5 w-5" />
                                <span class="ml-3 text-sm font-bold text-gray-700 dark:text-gray-300">Alertas por Correo</span>
                            </label>
                        </div>
                    </div>

                    <!-- SECCIÓN 5: SEGURIDAD (ZONA CRÍTICA) -->
                    <div class="bg-rose-50/50 dark:bg-rose-900/10 shadow-xl rounded-3xl border border-rose-100 dark:border-rose-900/30 overflow-hidden">
                        <div class="p-6 bg-rose-100/50 dark:bg-rose-900/30 border-b border-rose-200 dark:border-rose-900/50 flex items-center">
                            <div class="p-2 bg-white dark:bg-gray-800 rounded-lg shadow-sm mr-3 text-rose-600">
                                <KeyIcon class="h-5 w-5" />
                            </div>
                            <h3 class="font-bold text-rose-900 dark:text-rose-300 uppercase tracking-widest text-xs">Seguridad y Estado</h3>
                        </div>
                        <div class="p-8 space-y-8">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <InputLabel value="Cambiar Contraseña" class="text-[10px] font-black text-rose-400 uppercase" />
                                    <TextInput type="password" class="mt-1 block w-full border-rose-100 focus:border-rose-500 focus:ring-rose-500" v-model="form.password" placeholder="Dejar en blanco para no cambiar" />
                                    <InputError :message="form.errors.password" class="mt-2" />
                                </div>
                                <div>
                                    <InputLabel value="Confirmar Contraseña" class="text-[10px] font-black text-rose-400 uppercase" />
                                    <TextInput type="password" class="mt-1 block w-full border-rose-100 focus:border-rose-500 focus:ring-rose-500" v-model="form.password_confirmation" />
                                </div>
                            </div>
                            
                            <div class="pt-6 border-t border-rose-100 dark:border-rose-900/30">
                                <label class="flex items-center group">
                                    <Checkbox v-model:checked="form.estado_activo" :disabled="isEditingSelf" class="h-6 w-6 text-rose-600 rounded-lg" />
                                    <div class="ml-4">
                                        <span class="text-sm font-black text-rose-900 dark:text-rose-300 uppercase">Cuenta Activa</span>
                                        <p class="text-xs text-rose-500 dark:text-rose-400/60 leading-none mt-1">Desactive esta opción para suspender el acceso de inmediato.</p>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- ACCIÓN FINAL -->
                    <div class="flex items-center justify-between p-4 bg-indigo-900 rounded-3xl shadow-2xl">
                        <p class="text-indigo-100 text-[10px] font-black uppercase tracking-widest pl-4">Revisión Final: {{ form.name }}</p>
                        <PrimaryButton class="!bg-white !text-indigo-900 !rounded-2xl !px-10 !py-4 !text-sm !font-black hover:!bg-indigo-50 transition-all" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                            ACTUALIZAR PERFIL
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>