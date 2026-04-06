<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { 
    BuildingOfficeIcon, 
    IdentificationIcon, 
    UserCircleIcon, 
    EnvelopeIcon, 
    PhoneIcon, 
    CalendarDaysIcon,
    BanknotesIcon,
    ShieldCheckIcon,
    CheckCircleIcon,
    ArrowPathIcon,
    ArrowLeftIcon,
    MapPinIcon,
    XMarkIcon
} from '@heroicons/vue/24/outline';
import Swal from 'sweetalert2';

const props = defineProps({
    cooperativa: Object,
});

const form = useForm({
    _method: 'PATCH',
    nombre: props.cooperativa.nombre,
    NIT: props.cooperativa.NIT,
    tipo_vigilancia: props.cooperativa.tipo_vigilancia,
    fecha_constitucion: props.cooperativa.fecha_constitucion ? props.cooperativa.fecha_constitucion.slice(0, 10) : '',
    numero_matricula_mercantil: props.cooperativa.numero_matricula_mercantil,
    tipo_persona: props.cooperativa.tipo_persona,
    representante_legal_nombre: props.cooperativa.representante_legal_nombre,
    representante_legal_cedula: props.cooperativa.representante_legal_cedula,
    contacto_nombre: props.cooperativa.contacto_nombre,
    contacto_telefono: props.cooperativa.contacto_telefono,
    contacto_correo: props.cooperativa.contacto_correo,
    correo_notificaciones_judiciales: props.cooperativa.correo_notificaciones_judiciales,
    usa_libranza: props.cooperativa.usa_libranza,
    requiere_carta_instrucciones: props.cooperativa.requiere_carta_instrucciones,
    tipo_garantia_frecuente: props.cooperativa.tipo_garantia_frecuente,
    tasa_maxima_moratoria: props.cooperativa.tasa_maxima_moratoria,
    ciudad_principal_operacion: props.cooperativa.ciudad_principal_operacion,
    estado_activo: props.cooperativa.estado_activo,
});

const submit = () => {
    form.post(route('cooperativas.update', props.cooperativa.id), {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({
                title: '¡Actualizado!',
                text: 'Los datos de la entidad han sido actualizados.',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
        },
        onError: (errors) => {
            const firstErr = Object.values(errors)[0];
            Swal.fire({
                title: 'Atención',
                text: firstErr || 'Revise los errores en el formulario.',
                icon: 'warning',
                confirmButtonColor: '#4f46e5'
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
    <Head :title="'Editar ' + cooperativa.nombre" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                <div class="flex items-center gap-4">
                    <Link :href="route('cooperativas.show', cooperativa.id)" class="p-2.5 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-gray-400 hover:text-indigo-600 transition-all shadow-sm">
                        <ArrowLeftIcon class="w-6 h-6" />
                    </Link>
                    <div class="flex items-center gap-4">
                        <div :class="`w-14 h-14 rounded-2xl flex items-center justify-center text-white text-xl font-black shadow-lg ${getRandomColor(cooperativa.id)}`">
                            {{ cooperativa.nombre[0] }}
                        </div>
                        <div>
                            <h2 class="font-black text-2xl text-gray-900 dark:text-white leading-tight">
                                Editar: {{ cooperativa.nombre }}
                            </h2>
                            <p class="text-sm text-gray-500 dark:text-gray-400">NIT: {{ cooperativa.NIT }}</p>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-3 w-full md:w-auto">
                    <PrimaryButton @click="submit" class="w-full md:w-auto !bg-indigo-600 hover:!bg-indigo-700 !px-10 !py-3 !text-sm !font-black !rounded-xl !shadow-xl !shadow-indigo-200 dark:!shadow-none flex items-center justify-center gap-2" :disabled="form.processing">
                        <CheckCircleIcon v-if="!form.processing" class="w-5 h-5" />
                        <ArrowPathIcon v-else class="w-5 h-5 animate-spin" />
                        Guardar Cambios
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <div class="py-12 bg-gray-50/50 dark:bg-gray-900/50 min-h-screen">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-10">
                <form @submit.prevent="submit" class="space-y-10">
                    
                    <!-- SECCIÓN 1: IDENTIDAD LEGAL -->
                    <div class="bg-white dark:bg-gray-800 rounded-[2.5rem] border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                        <div class="px-8 py-5 bg-gray-50/50 dark:bg-gray-800/50 border-b border-gray-100 dark:border-gray-700 flex items-center gap-3">
                            <BuildingOfficeIcon class="w-5 h-5 text-indigo-500" />
                            <h3 class="font-black text-gray-900 dark:text-white uppercase tracking-wider text-xs">Identidad y Personería Jurídica</h3>
                        </div>
                        <div class="p-8 md:p-10 grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="md:col-span-2 space-y-2">
                                <InputLabel value="Razón Social Completa *" class="font-bold text-xs uppercase" />
                                <TextInput v-model="form.nombre" type="text" class="w-full rounded-2xl border-gray-200 font-bold text-lg" required />
                                <InputError :message="form.errors.nombre" />
                            </div>
                            <div class="space-y-2">
                                <InputLabel value="NIT *" class="font-bold text-xs uppercase" />
                                <div class="relative">
                                    <IdentificationIcon class="absolute left-3 top-3 w-4 h-4 text-gray-400" />
                                    <TextInput v-model="form.NIT" class="pl-10 w-full rounded-xl border-gray-200 font-mono font-bold" required />
                                </div>
                                <InputError :message="form.errors.NIT" />
                            </div>
                            <div class="space-y-2">
                                <InputLabel value="Fecha de Constitución" class="font-bold text-xs uppercase" />
                                <TextInput v-model="form.fecha_constitucion" type="date" class="w-full rounded-xl" />
                            </div>
                            <div class="space-y-2">
                                <InputLabel value="Matrícula Mercantil" class="font-bold text-xs uppercase" />
                                <TextInput v-model="form.numero_matricula_mercantil" class="w-full rounded-xl" />
                            </div>
                            <div class="space-y-2">
                                <InputLabel value="Entidad de Vigilancia" class="font-bold text-xs uppercase" />
                                <select v-model="form.tipo_vigilancia" class="w-full rounded-xl border-gray-200 bg-gray-50 font-bold text-sm">
                                    <option>Supersolidaria</option><option>SFC</option><option>Otro</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN 2: REPRESENTACIÓN Y CONTACTO -->
                    <div class="bg-white dark:bg-gray-800 rounded-[2.5rem] border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                        <div class="px-8 py-5 border-b border-gray-100 dark:border-gray-700 flex items-center gap-3">
                            <UserCircleIcon class="w-5 h-5 text-indigo-500" />
                            <h3 class="font-black text-gray-900 dark:text-white uppercase tracking-wider text-xs">Representación y Contacto Directo</h3>
                        </div>
                        <div class="p-8 md:p-10 grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="space-y-2">
                                <InputLabel value="Nombre del Representante Legal" class="font-bold text-xs uppercase" />
                                <TextInput v-model="form.representante_legal_nombre" class="w-full rounded-xl" />
                            </div>
                            <div class="space-y-2">
                                <InputLabel value="Cédula del Representante" class="font-bold text-xs uppercase" />
                                <TextInput v-model="form.representante_legal_cedula" class="w-full rounded-xl" />
                            </div>
                            <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-3 gap-6 pt-4 border-t border-gray-50 dark:border-gray-700">
                                <div class="space-y-2">
                                    <InputLabel value="Enlace Administrativo" class="font-bold text-xs uppercase" />
                                    <TextInput v-model="form.contacto_nombre" class="w-full rounded-xl" />
                                </div>
                                <div class="space-y-2">
                                    <InputLabel value="Teléfono" class="font-bold text-xs uppercase" />
                                    <TextInput v-model="form.contacto_telefono" class="w-full rounded-xl" />
                                </div>
                                <div class="space-y-2">
                                    <InputLabel value="Email" class="font-bold text-xs uppercase" />
                                    <TextInput v-model="form.contacto_correo" type="email" class="w-full rounded-xl" />
                                </div>
                            </div>
                            <div class="md:col-span-2 space-y-2">
                                <InputLabel value="Buzón Judicial Oficial" class="font-bold text-xs uppercase" />
                                <div class="relative">
                                    <EnvelopeIcon class="absolute left-3 top-3 w-4 h-4 text-indigo-400" />
                                    <TextInput v-model="form.correo_notificaciones_judiciales" type="email" class="pl-10 w-full rounded-xl border-indigo-100 bg-indigo-50/10" required />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SECCIÓN 3: POLÍTICAS OPERATIVAS -->
                    <div class="bg-white dark:bg-gray-800 rounded-[2.5rem] border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                        <div class="px-8 py-5 bg-gray-50/50 dark:bg-gray-800/50 border-b border-gray-100 dark:border-gray-700 flex items-center gap-3">
                            <ShieldCheckIcon class="w-5 h-5 text-indigo-500" />
                            <h3 class="font-black text-gray-900 dark:text-white uppercase tracking-wider text-xs">Políticas de Cobranza y Garantías</h3>
                        </div>
                        <div class="p-8 md:p-10 grid grid-cols-1 md:grid-cols-2 gap-8">
                            <div class="space-y-2">
                                <InputLabel value="Tasa Máxima Moratoria (%) *" class="font-bold text-xs uppercase" />
                                <div class="relative">
                                    <BanknotesIcon class="absolute left-3 top-3 w-4 h-4 text-gray-400" />
                                    <TextInput v-model="form.tasa_maxima_moratoria" type="number" step="0.01" class="pl-10 w-full rounded-xl border-gray-200 font-bold" required />
                                </div>
                            </div>
                            <div class="space-y-2">
                                <InputLabel value="Ciudad de Operación" class="font-bold text-xs uppercase" />
                                <TextInput v-model="form.ciudad_principal_operacion" class="w-full rounded-xl" />
                            </div>
                            <div class="md:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-6 pt-6">
                                <label class="flex items-center cursor-pointer group">
                                    <Checkbox v-model:checked="form.usa_libranza" class="w-5 h-5 rounded-lg border-gray-300 text-indigo-600" />
                                    <span class="ms-3 text-xs font-black uppercase text-gray-500 group-hover:text-indigo-600 transition-colors">Usa Libranza</span>
                                </label>
                                <label class="flex items-center cursor-pointer group">
                                    <Checkbox v-model:checked="form.requiere_carta_instrucciones" class="w-5 h-5 rounded-lg border-gray-300 text-indigo-600" />
                                    <span class="ms-3 text-xs font-black uppercase text-gray-500 group-hover:text-indigo-600 transition-colors">Requiere Carta</span>
                                </label>
                                <label class="flex items-center cursor-pointer group">
                                    <Checkbox v-model:checked="form.estado_activo" class="w-5 h-5 rounded-lg border-gray-300 text-emerald-600" />
                                    <span class="ms-3 text-xs font-black uppercase text-gray-500 group-hover:text-emerald-600 transition-colors">Entidad Activa</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- BOTONES DE ACCIÓN FINAL -->
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-4 bg-white dark:bg-gray-800 p-8 rounded-3xl shadow-xl border border-indigo-100 dark:border-indigo-900/30 mt-12">
                        <Link :href="route('cooperativas.show', cooperativa.id)" class="order-2 sm:order-1 flex items-center justify-center gap-2 px-8 py-3 text-sm font-bold text-gray-500 hover:text-red-600 transition-all">
                            <XMarkIcon class="w-5 h-5" /> Descartar cambios
                        </Link>
                        <PrimaryButton 
                            class="order-1 sm:order-2 w-full sm:w-auto !bg-indigo-600 hover:!bg-indigo-700 !px-16 !py-4 !text-lg !font-black !rounded-2xl !shadow-2xl !shadow-indigo-200 dark:!shadow-none flex items-center justify-center gap-3 transition-all transform hover:scale-[1.02]" 
                            :disabled="form.processing"
                        >
                            <CheckCircleIcon v-if="!form.processing" class="w-7 h-7" />
                            <ArrowPathIcon v-else class="w-7 h-7 animate-spin" />
                            Actualizar Entidad Ahora
                        </PrimaryButton>
                    </div>

                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.animate-in { animation-fill-mode: both; animation-duration: 0.4s; }
</style>
