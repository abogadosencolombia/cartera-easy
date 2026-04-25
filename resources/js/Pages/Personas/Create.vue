<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import SelectInput from '@/Components/SelectInput.vue';
import DatePicker from '@/Components/DatePicker.vue';
import Textarea from '@/Components/Textarea.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { 
    PlusIcon, XMarkIcon, ExclamationTriangleIcon, UserIcon, IdentificationIcon,
    PhoneIcon, EnvelopeIcon, MapPinIcon, GlobeAltIcon, BuildingOfficeIcon,
    BriefcaseIcon, ShieldCheckIcon, CheckCircleIcon, ArrowPathIcon, ArrowLeftIcon
} from '@heroicons/vue/24/outline';
import { reactive, computed } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
  allCooperativas: { type: Array, default: () => [] },
  allAbogados: { type: Array, default: () => [] },
});

const form = useForm('CreatePersona', {
  nombre_completo: '',
  tipo_documento: 'CC',
  numero_documento: '',
  dv: '',
  fecha_expedicion: '',
  fecha_nacimiento: '',
  telefono_fijo: '',
  celular_1: '',
  celular_2: '',
  correo_1: '',
  correo_2: '',
  empresa: '',
  cargo: '',
  observaciones: '',
  social_links: [],
  addresses: [],
  es_demandado: false,
  cooperativas_ids: [],
  abogados_ids: [],
});

const addLinkRow = () => { form.social_links.push(reactive({ label: '', url: '' })); };
const removeLinkRow = (idx) => { form.social_links.splice(idx, 1); };
const normalizeUrl = (l) => { if (l.url && !/^https?:\/\//i.test(l.url)) l.url = `https://${l.url}`; };

const addAddressRow = () => { form.addresses.push(reactive({ label: 'Casa', address: '', city: '' })); };
const removeAddressRow = (idx) => { form.addresses.splice(idx, 1); };

const toggleCooperativas = () => {
    form.cooperativas_ids = form.cooperativas_ids.length === props.allCooperativas.length ? [] : props.allCooperativas.map(c => c.id);
};

const toggleAbogados = () => {
    form.abogados_ids = form.abogados_ids.length === props.allAbogados.length ? [] : props.allAbogados.map(a => a.id);
};

const submit = () => {
    form.post(route('personas.store'), {
        preserveScroll: true, 
        onSuccess: () => {
            Swal.fire({ title: '¡Registrada!', text: 'La persona ha sido creada correctamente.', icon: 'success', timer: 2000, showConfirmButton: false });
            form.reset();
        },
        onError: (errors) => {
            const firstErr = Object.values(errors)[0];
            Swal.fire({ title: 'Atención', text: firstErr || 'Revise los campos obligatorios.', icon: 'warning', confirmButtonColor: '#4f46e5' });
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    });
};
</script>

<template>
  <Head title="Registrar Persona" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex flex-col md:flex-row items-center justify-between gap-6">
        <div class="flex items-center gap-4">
            <Link :href="route('personas.index')" class="p-2.5 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-gray-400 hover:text-indigo-600 transition-all shadow-sm">
                <ArrowLeftIcon class="w-6 h-6" />
            </Link>
            <div>
                <h2 class="font-black text-2xl text-gray-900 dark:text-white leading-tight">Registrar Persona</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Cree un nuevo perfil en el directorio centralizado.</p>
            </div>
        </div>
        <div class="flex items-center gap-3 w-full md:w-auto">
            <PrimaryButton @click="submit" class="w-full md:w-auto !bg-indigo-600 hover:!bg-indigo-700 !px-10 !py-3 !text-sm !font-black !rounded-xl !shadow-xl !shadow-indigo-100 dark:!shadow-none flex items-center justify-center gap-2" :disabled="form.processing">
                <CheckCircleIcon v-if="!form.processing" class="w-5 h-5" />
                <ArrowPathIcon v-else class="w-5 h-5 animate-spin" />
                Finalizar Registro
            </PrimaryButton>
        </div>
      </div>
    </template>

    <div class="py-12 bg-gray-50/50 dark:bg-gray-900/50 min-h-screen">
      <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-10">
        
        <form @submit.prevent="submit" class="space-y-10">
          
          <!-- SECCIÓN 1: IDENTIDAD CORE -->
          <div class="bg-white dark:bg-gray-800 rounded-[2.5rem] border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="px-8 py-5 bg-gray-50/50 dark:bg-gray-800/50 border-b border-gray-100 dark:border-gray-700 flex items-center gap-3">
                <IdentificationIcon class="w-5 h-5 text-indigo-500" />
                <h3 class="font-black text-gray-900 dark:text-white uppercase tracking-wider text-xs">Identidad y Documentación</h3>
            </div>
            <div class="p-8 md:p-10 grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="md:col-span-2 space-y-2">
                    <InputLabel value="Nombre Completo o Razón Social *" class="font-bold text-xs uppercase" />
                    <TextInput v-model="form.nombre_completo" type="text" class="w-full rounded-2xl border-gray-200 focus:ring-indigo-500 font-bold text-lg" placeholder="Ej: Juan Pérez o Empresa S.A.S" required />
                    <InputError :message="form.errors.nombre_completo" />
                </div>
                <div class="space-y-2">
                    <InputLabel value="Tipo de Documento *" class="font-bold text-xs uppercase" />
                    <SelectInput v-model="form.tipo_documento" class="w-full">
                        <option>CC</option><option>CE</option><option>NIT</option><option>Pasaporte</option><option>TI</option>
                    </SelectInput>
                </div>
                <div class="space-y-2">
                    <InputLabel value="Número de Identificación *" class="font-bold text-xs uppercase" />
                    <div :class="form.tipo_documento === 'NIT' ? 'flex gap-2' : ''">
                        <TextInput v-model="form.numero_documento" type="text" class="flex-1 rounded-xl border-gray-200 font-mono font-bold" required />
                        <TextInput v-if="form.tipo_documento === 'NIT'" v-model="form.dv" maxlength="1" placeholder="DV" class="w-12 text-center rounded-xl border-gray-200 font-bold" />
                    </div>
                    <InputError :message="form.errors.numero_documento" />
                </div>
                <div class="space-y-2">
                    <InputLabel value="Rol de la Persona *" class="font-bold text-xs uppercase" />
                    <SelectInput v-model="form.es_demandado" class="w-full">
                        <option :value="false">Deudor / Cliente</option>
                        <option :value="true">Demandado</option>
                    </SelectInput>
                    <InputError :message="form.errors.es_demandado" />
                </div>
                <div class="space-y-2">
                    <InputLabel value="Fecha de Expedición" class="font-bold text-xs uppercase" />
                    <DatePicker v-model="form.fecha_expedicion" class="w-full" />
                </div>
                <div class="space-y-2">
                    <InputLabel value="Fecha de Nacimiento" class="font-bold text-xs uppercase" />
                    <DatePicker v-model="form.fecha_nacimiento" class="w-full" />
                </div>
            </div>
          </div>

          <!-- SECCIÓN 2: CONECTIVIDAD -->
          <div class="bg-white dark:bg-gray-800 rounded-[2.5rem] border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="px-8 py-5 border-b border-gray-50 dark:border-gray-700 flex items-center gap-3">
                <EnvelopeIcon class="w-5 h-5 text-indigo-500" />
                <h3 class="font-black text-gray-900 dark:text-white uppercase tracking-wider text-xs">Canales de Contacto</h3>
            </div>
            <div class="p-8 md:p-10 grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="space-y-2">
                    <InputLabel value="Celular Principal" class="font-bold text-xs uppercase" />
                    <div class="relative">
                        <PhoneIcon class="absolute left-3 top-3 w-4 h-4 text-gray-400" />
                        <TextInput v-model="form.celular_1" class="pl-10 w-full rounded-xl border-gray-200" placeholder="300..." />
                    </div>
                </div>
                <div class="space-y-2">
                    <InputLabel value="Correo Principal" class="font-bold text-xs uppercase" />
                    <div class="relative">
                        <EnvelopeIcon class="absolute left-3 top-3 w-4 h-4 text-gray-400" />
                        <TextInput v-model="form.correo_1" type="email" class="pl-10 w-full rounded-xl border-gray-200" placeholder="ejemplo@correo.com" />
                    </div>
                </div>
                <div class="space-y-2">
                    <InputLabel value="Celular Alternativo" class="font-bold text-xs uppercase" />
                    <TextInput v-model="form.celular_2" class="w-full rounded-xl border-gray-200" />
                </div>
                <div class="space-y-2">
                    <InputLabel value="Correo Alternativo" class="font-bold text-xs uppercase" />
                    <TextInput v-model="form.correo_2" type="email" class="w-full rounded-xl border-gray-200" />
                </div>
            </div>
          </div>

          <!-- SECCIÓN 3: LOCALIZACIÓN -->
          <div class="bg-white dark:bg-gray-800 rounded-[2.5rem] border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="px-8 py-5 border-b border-gray-50 dark:border-gray-700 flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <MapPinIcon class="w-5 h-5 text-red-500" />
                    <h3 class="font-black text-gray-900 dark:text-white uppercase tracking-wider text-xs">Direcciones Físicas</h3>
                </div>
                <button type="button" @click="addAddressRow" class="inline-flex items-center px-4 py-2 bg-red-50 text-red-700 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-red-600 hover:text-white transition-all shadow-sm">
                    <PlusIcon class="w-4 h-4 mr-2" /> Añadir Ubicación
                </button>
            </div>
            <div class="p-8 md:p-10">
                <div v-if="!form.addresses.length" class="text-center py-10 opacity-30">
                    <MapPinIcon class="w-12 h-12 mx-auto text-gray-300 mb-2" />
                    <p class="text-xs font-black uppercase tracking-widest text-gray-400">No se han registrado direcciones</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div v-for="(addr, idx) in form.addresses" :key="idx" class="p-6 bg-gray-50 dark:bg-gray-900/50 rounded-3xl border border-gray-100 dark:border-gray-700 relative group animate-in zoom-in-95 duration-200">
                        <button type="button" @click="removeAddressRow(idx)" class="absolute -top-2 -right-2 p-1.5 bg-white dark:bg-gray-800 text-red-500 rounded-full shadow-md border dark:border-gray-700 opacity-0 group-hover:opacity-100 transition-all"><XMarkIcon class="w-4 h-4"/></button>
                        <div class="space-y-4">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-1">
                                    <InputLabel value="Etiqueta" class="text-[10px] font-black uppercase text-gray-400" />
                                    <TextInput v-model="addr.label" class="w-full text-xs font-bold rounded-lg" placeholder="Casa, Oficina..." />
                                </div>
                                <div class="space-y-1">
                                    <InputLabel value="Ciudad" class="text-[10px] font-black uppercase text-gray-400" />
                                    <TextInput v-model="addr.city" class="w-full text-xs font-bold rounded-lg" placeholder="Ciudad" />
                                </div>
                            </div>
                            <div class="space-y-1">
                                <InputLabel value="Dirección Completa" class="text-[10px] font-black uppercase text-gray-400" />
                                <TextInput v-model="addr.address" class="w-full text-xs font-medium rounded-lg" placeholder="Calle... Carrera..." />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
          </div>

          <!-- SECCIÓN 4: LABORAL Y NOTAS -->
          <div class="bg-white dark:bg-gray-800 rounded-[2.5rem] border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="px-8 py-5 border-b border-gray-50 dark:border-gray-700 flex items-center gap-3">
                <BriefcaseIcon class="w-5 h-5 text-indigo-500" />
                <h3 class="font-black text-gray-900 dark:text-white uppercase tracking-wider text-xs">Entorno Profesional</h3>
            </div>
            <div class="p-8 md:p-10 grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="space-y-2">
                    <InputLabel value="Empresa / Entidad Laboral" class="font-bold text-xs uppercase" />
                    <TextInput v-model="form.empresa" class="w-full rounded-xl border-gray-200" />
                </div>
                <div class="space-y-2">
                    <InputLabel value="Cargo Actual" class="font-bold text-xs uppercase" />
                    <TextInput v-model="form.cargo" class="w-full rounded-xl border-gray-200" />
                </div>
                <div class="md:col-span-2 space-y-2">
                    <InputLabel value="Observaciones del Perfil" class="font-bold text-xs uppercase" />
                    <Textarea v-model="form.observaciones" rows="3" class="w-full rounded-2xl border-gray-200" placeholder="Información relevante para cobranza o seguimiento..." />
                </div>
            </div>
          </div>

          <!-- SECCIÓN 5: ASIGNACIONES -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
              <!-- Cooperativas -->
              <div class="bg-white dark:bg-gray-800 rounded-[2.5rem] border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                <div class="px-8 py-5 border-b border-gray-50 dark:border-gray-700 flex justify-between items-center">
                    <div class="flex items-center gap-3">
                        <BuildingOfficeIcon class="w-5 h-5 text-emerald-500" />
                        <h3 class="font-black text-gray-900 dark:text-white uppercase tracking-wider text-[10px]">Vincular Empresas *</h3>
                    </div>
                    <button type="button" @click="toggleCooperativas" class="text-[9px] font-black uppercase text-indigo-600 hover:underline">Alternar Todos</button>
                </div>
                <div class="p-8 max-h-64 overflow-y-auto custom-scrollbar">
                    <InputError :message="form.errors.cooperativas_ids" class="mb-4" />
                    <div class="grid grid-cols-1 gap-2">
                        <label v-for="c in allCooperativas" :key="c.id" class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-900 rounded-xl cursor-pointer hover:bg-emerald-50 transition-all border border-transparent hover:border-emerald-100 group">
                            <input type="checkbox" :value="c.id" v-model="form.cooperativas_ids" class="rounded-lg border-gray-300 text-emerald-600 focus:ring-emerald-500" />
                            <span class="text-xs font-bold text-gray-700 dark:text-gray-300 group-hover:text-emerald-700 transition-colors">{{ c.nombre }}</span>
                        </label>
                    </div>
                </div>
              </div>

              <!-- Abogados -->
              <div class="bg-white dark:bg-gray-800 rounded-[2.5rem] border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
                <div class="px-8 py-5 border-b border-gray-50 dark:border-gray-700 flex justify-between items-center">
                    <div class="flex items-center gap-3">
                        <ShieldCheckIcon class="w-5 h-5 text-violet-500" />
                        <h3 class="font-black text-gray-900 dark:text-white uppercase tracking-wider text-[10px]">Asignar Responsables</h3>
                    </div>
                    <button type="button" @click="toggleAbogados" class="text-[9px] font-black uppercase text-indigo-600 hover:underline">Alternar Todos</button>
                </div>
                <div class="p-8 max-h-64 overflow-y-auto custom-scrollbar">
                    <div class="grid grid-cols-1 gap-2">
                        <label v-for="a in allAbogados" :key="a.id" class="flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-900 rounded-xl cursor-pointer hover:bg-violet-50 transition-all border border-transparent hover:border-violet-100 group">
                            <input type="checkbox" :value="a.id" v-model="form.abogados_ids" class="rounded-lg border-gray-300 text-violet-600 focus:ring-violet-500" />
                            <span class="text-xs font-bold text-gray-700 dark:text-gray-300 group-hover:text-violet-700 transition-colors">{{ a.name }}</span>
                        </label>
                    </div>
                </div>
              </div>
          </div>

          <!-- SECCIÓN 6: ECOSISTEMA DIGITAL -->
          <div class="bg-white dark:bg-gray-800 rounded-[2.5rem] border border-gray-100 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="px-8 py-5 border-b border-gray-50 dark:border-gray-700 flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <GlobeAltIcon class="w-5 h-5 text-blue-500" />
                    <h3 class="font-black text-gray-900 dark:text-white uppercase tracking-wider text-xs">Redes y Enlaces Digitales</h3>
                </div>
                <button type="button" @click="addLinkRow" class="inline-flex items-center px-4 py-2 bg-blue-50 text-blue-700 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-blue-600 hover:text-white transition-all shadow-sm">
                    <PlusIcon class="w-4 h-4 mr-2" /> Añadir Perfil
                </button>
            </div>
            <div class="p-8 md:p-10">
                <div v-if="!form.social_links.length" class="text-center py-10 opacity-30">
                    <GlobeAltIcon class="w-12 h-12 mx-auto text-gray-300 mb-2" />
                    <p class="text-xs font-black uppercase tracking-widest text-gray-400">Sin presencia digital registrada</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div v-for="(l, idx) in form.social_links" :key="idx" class="p-5 bg-gray-50 dark:bg-gray-900/50 rounded-3xl border border-gray-100 dark:border-gray-700 relative group animate-in zoom-in-95 duration-200 flex items-end gap-3">
                        <div class="flex-grow grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <InputLabel value="Red / Plataforma" class="text-[9px] font-black uppercase text-gray-400" />
                                <TextInput v-model="l.label" class="w-full text-xs font-bold rounded-lg" placeholder="LinkedIn, Facebook..." />
                            </div>
                            <div class="space-y-1">
                                <InputLabel value="URL del Perfil" class="text-[9px] font-black uppercase text-gray-400" />
                                <TextInput v-model="l.url" @blur="normalizeUrl(l)" class="w-full text-xs font-medium rounded-lg" placeholder="https://..." />
                            </div>
                        </div>
                        <button type="button" @click="removeLinkRow(idx)" class="p-2.5 bg-white text-red-500 rounded-xl shadow-sm border border-gray-100 hover:bg-red-50 transition-all"><TrashIcon class="w-4 h-4"/></button>
                    </div>
                </div>
            </div>
          </div>

          <!-- BOTONES DE ACCIÓN FINAL -->
          <div class="flex flex-col sm:flex-row items-center justify-between gap-4 bg-white dark:bg-gray-800 p-8 rounded-3xl shadow-xl border border-indigo-100 dark:border-indigo-900/30 mt-12">
            <Link :href="route('personas.index')" class="order-2 sm:order-1 flex items-center justify-center gap-2 px-8 py-3 text-sm font-bold text-gray-500 hover:text-red-600 transition-all">
                <XMarkIcon class="w-5 h-5" /> Cancelar y salir
            </Link>
            <PrimaryButton 
                class="order-1 sm:order-2 w-full sm:w-auto !bg-indigo-600 hover:!bg-indigo-700 !px-16 !py-4 !text-lg !font-black !rounded-2xl !shadow-2xl !shadow-indigo-200 dark:!shadow-none flex items-center justify-center gap-3 transition-all transform hover:scale-[1.02]" 
                :disabled="form.processing"
            >
                <CheckCircleIcon v-if="!form.processing" class="w-7 h-7" />
                <ArrowPathIcon v-else class="w-7 h-7 animate-spin" />
                Registrar Persona Ahora
            </PrimaryButton>
          </div>

        </form>
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background-color: rgba(79, 70, 229, 0.1); border-radius: 10px; }
.animate-in { animation-fill-mode: both; }
</style>