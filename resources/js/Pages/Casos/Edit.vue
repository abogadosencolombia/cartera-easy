<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import TextInput from '@/Components/TextInput.vue';
import DatePicker from '@/Components/DatePicker.vue';
import Textarea from '@/Components/Textarea.vue';
import Checkbox from '@/Components/Checkbox.vue';
import Modal from '@/Components/Modal.vue';
import Dropdown from '@/Components/Dropdown.vue';
import AsyncSelect from '@/Components/AsyncSelect.vue';
import { Head, Link, useForm, usePage, useRemember, router } from '@inertiajs/vue3';
import { ref, computed, watch, onMounted } from 'vue';
import axios from 'axios';
import { 
    TrashIcon, InformationCircleIcon, ScaleIcon, UsersIcon, LockClosedIcon, 
    PlusIcon, ChevronUpIcon, ChevronDownIcon, ArchiveBoxXMarkIcon, ArrowPathIcon 
} from '@heroicons/vue/24/outline';

const props = defineProps({
    caso: { type: Object, required: true },
    estructuraProcesal: { type: Array, default: () => [] },
    etapas_procesales: { type: Array, default: () => [] },
});

const activeTab = useRemember('info-principal', 'casoEditTab:' + props.caso.id);
const user = usePage().props.auth.user;

// --- LÓGICA DE CIERRE/REAPERTURA ---
const showCloseModal = ref(false);
const closeForm = useForm({ nota_cierre: '' });
const confirmClose = () => { closeForm.nota_cierre = ''; showCloseModal.value = true; };
const submitClose = () => {
    closeForm.patch(route('casos.close', props.caso.id), { onSuccess: () => showCloseModal.value = false, preserveScroll: true });
};
const submitReopen = () => {
    if (confirm('¿Reabrir este caso?')) router.patch(route('casos.reopen', props.caso.id), {}, { preserveScroll: true });
};

const isFormDisabled = computed(() => (user.tipo_usuario !== 'admin' && props.caso.bloqueado));

const formatDateForInput = (d) => d ? new Date(d).toISOString().split('T')[0] : null;
const safeJsonParse = (s) => { if (!s) return []; try { const p = JSON.parse(s); return Array.isArray(p) ? p : []; } catch (e) { return []; } };

const form = useForm({
    _method: 'PATCH',
    cooperativa_id: props.caso.cooperativa ? { id: props.caso.cooperativa.id, nombre: props.caso.cooperativa.nombre } : null,
    user_id: props.caso.users?.length > 0 
        ? props.caso.users.map(u => ({ id: u.id, name: u.name })) 
        : (props.caso.user ? [{ id: props.caso.user.id, name: props.caso.user.name }] : []),
    deudor_id: props.caso.deudor_id,
    deudor: {
        id: props.caso.deudor_id,
        selected: props.caso.deudor ? { id: props.caso.deudor.id, nombre_completo: props.caso.deudor.nombre_completo, numero_documento: props.caso.deudor.numero_documento } : null,
        is_new: false,
        nombre_completo: '', tipo_documento: 'CC', numero_documento: '', cooperativas_ids: [], abogados_ids: []
    },
    codeudores: props.caso.codeudores?.map(c => ({
        id: c.id, nombre_completo: c.nombre_completo || '', tipo_documento: c.tipo_documento || 'CC', numero_documento: c.numero_documento || '',
        celular: c.celular || '', correo: c.correo || '', addresses: safeJsonParse(c.addresses), social_links: safeJsonParse(c.social_links), showDetails: true
    })) || [],
    juzgado_id: props.caso.juzgado ? { id: props.caso.juzgado.id, nombre: props.caso.juzgado.nombre } : null,
    referencia_credito: props.caso.referencia_credito,
    especialidad_id: props.caso.especialidad_id,
    tipo_proceso: props.caso.tipo_proceso,
    subtipo_proceso: props.caso.subtipo_proceso,
    subproceso: props.caso.subproceso,
    etapa_procesal: props.caso.etapa_procesal,
    radicado: props.caso.radicado ?? '',
    tipo_garantia_asociada: props.caso.tipo_garantia_asociada,
    origen_documental: props.caso.origen_documental,
    medio_contacto: props.caso.medio_contacto,
    fecha_apertura: formatDateForInput(props.caso.fecha_apertura),
    fecha_vencimiento: formatDateForInput(props.caso.fecha_vencimiento),
    fecha_inicio_credito: formatDateForInput(props.caso.fecha_inicio_credito),
    monto_total: props.caso.monto_total,
    monto_deuda_actual: props.caso.monto_deuda_actual,
    monto_total_pagado: props.caso.monto_total_pagado,
    tasa_interes_corriente: props.caso.tasa_interes_corriente,
    bloqueado: !!props.caso.bloqueado,
    motivo_bloqueo: props.caso.motivo_bloqueo ?? '',
    notas_legales: props.caso.notas_legales,
    link_drive: props.caso.link_drive || '',
});

const addCodeudor = () => { form.codeudores.push({ id: null, nombre_completo: '', tipo_documento: 'CC', numero_documento: '', showDetails: true }); activeTab.value = 'codeudores'; };
const removeCodeudor = (idx) => form.codeudores.splice(idx, 1);

// --- CASCADA ---
const formatLabel = (text) => text?.replace(/_/g, ' ').toLowerCase().replace(/\b\w/g, c => c.toUpperCase()) || '';
const especialidades = computed(() => props.estructuraProcesal);
const tiposDisponibles = ref([]);
const subtiposDisponibles = ref([]);
const subprocesosDisponibles = ref([]);

watch(() => form.especialidad_id, (id) => {
    const esp = especialidades.value.find(e => e.id === id);
    tiposDisponibles.value = esp ? esp.tipos_proceso : [];
}, { immediate: true });

watch(() => form.tipo_proceso, (val) => {
    const t = tiposDisponibles.value.find(x => x.nombre === val);
    subtiposDisponibles.value = t ? t.subtipos : [];
}, { immediate: true });

watch(() => form.subtipo_proceso, (val) => {
    const s = subtiposDisponibles.value.find(x => x.nombre === val);
    subprocesosDisponibles.value = s ? s.subprocesos : [];
}, { immediate: true });

const submit = () => {
    form.transform(data => ({
        ...data,
        cooperativa_id: data.cooperativa_id?.id ?? null,
        user_id: Array.isArray(data.user_id) ? data.user_id.map(u => u.id) : [],
        juzgado_id: data.juzgado_id?.id ?? null,
        deudor: data.deudor.is_new ? { ...data.deudor, cooperativas_ids: data.deudor.cooperativas_ids.map(c => c.id), abogados_ids: data.deudor.abogados_ids.map(a => a.id) } : { id: data.deudor.selected?.id },
        deudor_id: data.deudor.is_new ? null : data.deudor.selected?.id,
    })).patch(route('casos.update', props.caso.id));
};
</script>

<template>
    <Head :title="'Editar Caso #' + caso.id" />
    <AuthenticatedLayout>
        <template #header>
             <div class="flex items-center justify-between">
                 <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200">Editando Caso <span class="text-indigo-500">#{{ caso.id }}</span></h2>
                 <div class="flex gap-3">
                     <Link :href="route('casos.show', caso.id)"><SecondaryButton>Cancelar</SecondaryButton></Link>
                     <PrimaryButton @click="submit" :disabled="form.processing || isFormDisabled">Actualizar Caso</PrimaryButton>
                 </div>
             </div>
        </template>

        <div class="py-12">
            <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-8">
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                    <div class="border-b border-gray-200 dark:border-gray-700">
                        <nav class="-mb-px flex space-x-6 px-6 overflow-x-auto">
                            <button @click="activeTab = 'info-principal'" :class="[activeTab === 'info-principal' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500']" class="py-4 px-1 border-b-2 font-medium text-sm flex items-center"><InformationCircleIcon class="h-5 w-5 mr-2"/> Info Principal</button>
                            <button @click="activeTab = 'proceso-judicial'" :class="[activeTab === 'proceso-judicial' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500']" class="py-4 px-1 border-b-2 font-medium text-sm flex items-center"><ScaleIcon class="h-5 w-5 mr-2"/> Proceso Judicial</button>
                            <button @click="activeTab = 'codeudores'" :class="[activeTab === 'codeudores' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500']" class="py-4 px-1 border-b-2 font-medium text-sm flex items-center"><UsersIcon class="h-5 w-5 mr-2"/> Codeudores</button>
                            <button @click="activeTab = 'control-notas'" :class="[activeTab === 'control-notas' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500']" class="py-4 px-1 border-b-2 font-medium text-sm flex items-center"><LockClosedIcon class="h-5 w-5 mr-2"/> Control y Notas</button>
                        </nav>
                    </div>

                    <div class="p-8">
                        <fieldset :disabled="isFormDisabled">
                            <!-- TAB 1 -->
                            <section v-show="activeTab === 'info-principal'" class="space-y-8">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div><InputLabel value="Cooperativa / Empresa *" /><AsyncSelect v-model="form.cooperativa_id" :endpoint="route('cooperativas.search')" placeholder="Asignar a..." label-key="nombre" /></div>
                                    <div><InputLabel value="Abogado(s) a Cargo *" /><AsyncSelect v-model="form.user_id" :endpoint="route('users.search')" multiple placeholder="Asignar gestores..." label-key="name" /></div>
                                    <div class="md:col-span-2 space-y-4">
                                        <div class="flex justify-between items-center"><InputLabel value="Deudor Principal *" /><button type="button" @click="form.deudor.is_new = !form.deudor.is_new" class="text-[10px] font-bold text-indigo-600 uppercase">{{ form.deudor.is_new ? '← Buscar' : '+ Nuevo' }}</button></div>
                                        <div v-if="!form.deudor.is_new"><AsyncSelect v-model="form.deudor.selected" :endpoint="route('personas.search')" label-key="nombre_completo" /></div>
                                        <div v-else class="grid grid-cols-3 gap-4 p-4 border rounded-lg dark:border-gray-700">
                                            <TextInput v-model="form.deudor.nombre_completo" placeholder="Nombre completo" class="col-span-1" />
                                            <select v-model="form.deudor.tipo_documento" class="rounded-md border-gray-300 dark:bg-gray-900"><option>CC</option><option>NIT</option></select>
                                            <TextInput v-model="form.deudor.numero_documento" placeholder="Documento" />
                                            <div class="col-span-3 grid grid-cols-2 gap-4 mt-2">
                                                <AsyncSelect v-model="form.deudor.cooperativas_ids" :endpoint="route('cooperativas.search')" multiple label-key="nombre" placeholder="Cooperativas..." />
                                                <AsyncSelect v-model="form.deudor.abogados_ids" :endpoint="route('users.search')" multiple label-key="name" placeholder="Abogados..." />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-6 border-t dark:border-gray-700">
                                    <div><InputLabel value="Número Pagaré" /><TextInput v-model="form.referencia_credito" class="w-full" /></div>
                                    <div><InputLabel value="Monto Inicial" /><TextInput v-model="form.monto_total" type="number" class="w-full" /></div>
                                    <div><InputLabel value="Fecha Apertura" /><DatePicker v-model="form.fecha_apertura" class="w-full" /></div>
                                </div>
                            </section>

                            <!-- TAB 2 -->
                            <section v-show="activeTab === 'proceso-judicial'" class="space-y-8">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div><InputLabel value="Radicado" /><TextInput v-model="form.radicado" class="w-full" /></div>
                                    <div><InputLabel value="Juzgado" /><AsyncSelect v-model="form.juzgado_id" :endpoint="route('juzgados.search')" label-key="nombre" /></div>
                                    <div><InputLabel value="Especialidad" /><select v-model="form.especialidad_id" class="w-full rounded-md border-gray-300 dark:bg-gray-900"><option v-for="e in especialidades" :key="e.id" :value="e.id">{{ formatLabel(e.nombre) }}</option></select></div>
                                    <div><InputLabel value="Etapa" /><Dropdown align="left" width="full"><template #trigger><button type="button" class="w-full flex justify-between border rounded-md p-2 dark:bg-gray-900"><span>{{ formatLabel(form.etapa_procesal) || '--' }}</span><ChevronDownIcon class="h-4 w-4"/></button></template><template #content><button v-for="e in etapas_procesales" :key="e" @click="form.etapa_procesal = e" class="block w-full text-left px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700">{{ formatLabel(e) }}</button></template></Dropdown></div>
                                </div>
                            </section>

                            <!-- TAB 3 -->
                            <section v-show="activeTab === 'codeudores'" class="space-y-6">
                                <div class="flex justify-between items-center"><h3 class="font-bold">Lista de Codeudores</h3><PrimaryButton type="button" @click="addCodeudor">+ Añadir</PrimaryButton></div>
                                <div v-for="(c, i) in form.codeudores" :key="i" class="p-4 border rounded-lg dark:border-gray-700 relative">
                                    <div class="grid grid-cols-2 gap-4">
                                        <div><InputLabel value="Nombre" /><TextInput v-model="c.nombre_completo" class="w-full"/></div>
                                        <div><InputLabel value="Documento" /><TextInput v-model="c.numero_documento" class="w-full"/></div>
                                    </div>
                                    <button @click="removeCodeudor(i)" class="absolute top-2 right-2 text-red-500"><TrashIcon class="h-4 w-4"/></button>
                                </div>
                            </section>

                            <!-- TAB 4 -->
                            <section v-show="activeTab === 'control-notas'" class="space-y-8">
                                <div><InputLabel value="Notas Legales / Internas" /><Textarea v-model="form.notas_legales" rows="4" class="w-full" /></div>
                                <div v-if="user.tipo_usuario === 'admin'" class="p-4 border border-red-200 rounded-lg bg-red-50 dark:bg-red-900/20">
                                    <div class="flex items-center justify-between">
                                        <div><h4 class="font-bold text-red-800 dark:text-red-200">{{ caso.nota_cierre ? 'Reabrir' : 'Cerrar' }} Caso</h4></div>
                                        <PrimaryButton v-if="caso.nota_cierre" @click="submitReopen" class="bg-blue-600">Reabrir</PrimaryButton>
                                        <DangerButton v-else @click="confirmClose">Cerrar</DangerButton>
                                    </div>
                                </div>
                            </section>
                        </fieldset>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="showCloseModal" @close="showCloseModal = false">
            <div class="p-6">
                <h2 class="text-lg font-bold">Cierre del Caso</h2>
                <div class="mt-4"><InputLabel value="Nota Final" /><Textarea v-model="closeForm.nota_cierre" class="w-full" rows="4" /></div>
                <div class="mt-6 flex justify-end gap-3"><SecondaryButton @click="showCloseModal = false">Cerrar</SecondaryButton><DangerButton @click="submitClose">Confirmar Cierre</DangerButton></div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
