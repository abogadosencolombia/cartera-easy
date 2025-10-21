<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SelectInput from '@/Components/SelectInput.vue';
import Textarea from '@/Components/Textarea.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { ArrowLeftIcon, TicketIcon, PaperClipIcon, CheckBadgeIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    incidente: Object,
    usuarios: Array,
});

const page = usePage();

// --- LÓGICA PARA CREAR TICKETS ---
const creatingTicket = ref(false);
const ticketForm = useForm({
    asignado_a: '',
    comentarios: '',
});
const openCreateTicketModal = () => { creatingTicket.value = true; };
const closeCreateTicketModal = () => {
    creatingTicket.value = false;
    ticketForm.reset();
};
const createTicket = () => {
    ticketForm.post(route('admin.incidentes-juridicos.tickets.store', props.incidente.id), {
        preserveScroll: true,
        onSuccess: () => closeCreateTicketModal(),
    });
};

// --- LÓGICA PARA SUBIR ARCHIVOS (LA QUE FALTABA) ---
const archivoForm = useForm({
    archivo: null,
});
const submitArchivo = () => {
    archivoForm.post(route('admin.incidentes-juridicos.archivos.store', props.incidente.id), {
        onSuccess: () => {
            archivoForm.reset();
            const fileInput = document.getElementById('archivo_input');
            if (fileInput) fileInput.value = '';
        },
    });
};

// --- LÓGICA PARA REGISTRAR DECISIONES ---
const registeringDecision = ref(false);
const activeTicket = ref(null);
const decisionForm = useForm({
    resultado: 'sin_falta',
    resumen_decision: '',
    medida_administrativa: '',
    requiere_capacitacion: false,
});
const openDecisionModal = (ticket) => {
    activeTicket.value = ticket;
    registeringDecision.value = true;
};
const closeDecisionModal = () => {
    registeringDecision.value = false;
    activeTicket.value = null;
    decisionForm.reset();
};
const registerDecision = () => {
    decisionForm.post(route('admin.tickets-disciplinarios.decision.store', activeTicket.value.id), {
        preserveScroll: true,
        onSuccess: () => closeDecisionModal(),
    });
};

// --- FUNCIONES DE FORMATO ---
const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const options = { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
    return new Date(dateString).toLocaleDateString('es-CO', options);
};
const estadoClass = (estado) => {
    const classes = {
        pendiente: 'bg-yellow-100 text-yellow-800',
        en_revision: 'bg-blue-100 text-blue-800',
        resuelto: 'bg-green-100 text-green-800',
        archivado: 'bg-gray-100 text-gray-800',
    };
    return classes[estado] || 'bg-gray-200';
};
const etapaTicketClass = (etapa) => {
    const classes = {
        nuevo: 'text-blue-600',
        en_revision: 'text-yellow-600',
        resolucion: 'text-purple-600',
        cerrado: 'text-green-600',
    };
    return classes[etapa] || 'text-gray-600';
};
</script>

<template>
    <Head :title="'Detalle Incidente: ' + incidente.asunto" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center">
                <Link :href="route('admin.incidentes-juridicos.index')" class="text-gray-500 hover:text-gray-700">
                    <ArrowLeftIcon class="h-6 w-6"/>
                </Link>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight ml-4">
                    Detalle del Incidente
                </h2>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <h3 class="text-lg font-bold text-gray-900">{{ incidente.asunto }}</h3>
                         <div class="mt-2">
                             <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full" :class="estadoClass(incidente.estado)">
                                {{ incidente.estado ? incidente.estado.replace('_', ' ') : 'Sin Estado' }}
                            </span>
                        </div>
                        <p class="mt-4 text-sm text-gray-600">{{ incidente.descripcion }}</p>

                        <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                            <div>
                                <div class="font-bold text-gray-500">Responsable</div>
                                <div>{{ incidente.responsable ? incidente.responsable.name : 'N/A' }}</div>
                            </div>
                            <div>
                                <div class="font-bold text-gray-500">Origen del Reporte</div>
                                <div class="capitalize">{{ incidente.origen }}</div>
                            </div>
                            <div>
                                <div class="font-bold text-gray-500">Fecha de Registro</div>
                                <div>{{ formatDate(incidente.fecha_registro) }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-bold text-gray-900 flex items-center">
                                <TicketIcon class="h-6 w-6 mr-2"/>
                                Tickets Disciplinarios
                            </h3>
                            <PrimaryButton @click="openCreateTicketModal">Crear Nuevo Ticket</PrimaryButton>
                        </div>

                        <div class="mt-4 text-sm text-gray-500" v-if="incidente.tickets.length === 0">
                            No hay tickets disciplinarios para este incidente.
                        </div>

                        <div v-else class="mt-4 space-y-4">
                            <div v-for="ticket in incidente.tickets" :key="ticket.id" class="border border-gray-200 rounded-lg p-4">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-bold text-gray-800">
                                            Ticket #{{ ticket.id }} -
                                            <span class="font-bold capitalize" :class="etapaTicketClass(ticket.etapa)">{{ ticket.etapa.replace('_', ' ') }}</span>
                                        </p>
                                        <p class="text-sm text-gray-500 mt-1">
                                            Asignado a: <span class="font-semibold">{{ ticket.asignado ? ticket.asignado.name : 'N/A' }}</span>
                                        </p>
                                    </div>
                                    <p class="text-xs text-gray-400">{{ formatDate(ticket.created_at) }}</p>
                                </div>
                                <p class="mt-3 text-sm bg-gray-50 p-3 rounded-md">{{ ticket.comentarios }}</p>

                                <div v-if="ticket.decision" class="mt-4 border-t pt-3">
                                    <h4 class="font-bold text-sm text-gray-700 flex items-center"><CheckBadgeIcon class="h-5 w-5 mr-1 text-green-600"/>Decisión Final</h4>
                                    <p class="text-sm text-gray-600 mt-2">{{ ticket.decision.resumen_decision }}</p>
                                    <p class="text-xs text-gray-500 mt-1">Resultado: <span class="font-semibold">{{ ticket.decision.resultado.replace('_', ' ') }}</span></p>
                                </div>

                                <div v-else class="mt-4 text-right">
                                    <SecondaryButton @click="openDecisionModal(ticket)">Registrar Decisión</SecondaryButton>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <h3 class="text-lg font-bold text-gray-900 flex items-center">
                            <PaperClipIcon class="h-6 w-6 mr-2"/>
                            Archivos de Evidencia
                        </h3>

                        <form @submit.prevent="submitArchivo" class="mt-4 border-t pt-4">
                            <InputLabel for="archivo_input" value="Adjuntar nuevo archivo" />
                            <div class="mt-1 flex items-center">
                                <input id="archivo_input" type="file" @input="archivoForm.archivo = $event.target.files[0]" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"/>
                                <PrimaryButton class="ms-4" :class="{ 'opacity-25': archivoForm.processing }" :disabled="archivoForm.processing">
                                    Subir
                                </PrimaryButton>
                            </div>
                            <InputError :message="archivoForm.errors.archivo" class="mt-2" />
                            
                            <div v-if="archivoForm.progress" class="mt-2">
                                <div class="w-full bg-gray-200 rounded-full h-2.5">
                                    <div class="bg-indigo-600 h-2.5 rounded-full" :style="{ width: archivoForm.progress.percentage + '%' }"></div>
                                </div>
                            </div>
                        </form>

                        <div class="mt-6">
                            <h4 class="font-bold text-gray-700">Archivos Adjuntos:</h4>
                            <div class="mt-2 text-sm text-gray-500" v-if="incidente.archivos.length === 0">
                                No hay archivos adjuntos para este incidente.
                            </div>
                            <ul v-else class="mt-2 space-y-2">
                                <li v-for="archivo in incidente.archivos" :key="archivo.id" class="flex justify-between items-center p-2 bg-gray-50 rounded-md">
                                    <span class="truncate">{{ archivo.nombre_original }}</span>
                                    <a :href="route('admin.archivos-incidente.descargar', archivo.id)" class="font-semibold text-indigo-600 hover:text-indigo-800">
                                        Descargar
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <Modal :show="creatingTicket" @close="closeCreateTicketModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    Crear Nuevo Ticket Disciplinario
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    Asigne un responsable para la revisión del incidente y añada comentarios iniciales.
                </p>

                <form @submit.prevent="createTicket" class="mt-6 space-y-4">
                    <div>
                        <InputLabel for="asignado_a" value="Asignar Revisión A" />
                        <SelectInput id="asignado_a" class="mt-1 block w-full" v-model="ticketForm.asignado_a" required>
                            <option value="" disabled>-- Seleccione un usuario --</option>
                            <option v-for="usuario in usuarios" :key="usuario.id" :value="usuario.id">
                                {{ usuario.name }}
                            </option>
                        </SelectInput>
                        <InputError :message="ticketForm.errors.asignado_a" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="comentarios" value="Comentarios Iniciales" />
                        <Textarea id="comentarios" v-model="ticketForm.comentarios" class="mt-1 block w-full" rows="4" required />
                        <InputError :message="ticketForm.errors.comentarios" class="mt-2" />
                    </div>

                    <div class="flex justify-end">
                        <SecondaryButton @click="closeCreateTicketModal"> Cancelar </SecondaryButton>
                        <PrimaryButton class="ms-3" :class="{ 'opacity-25': ticketForm.processing }" :disabled="ticketForm.processing">
                            Crear Ticket
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="registeringDecision" @close="closeDecisionModal">
            <div class="p-6">
                <h2 class="text-lg font-medium text-gray-900">
                    Registrar Decisión Final para Ticket #{{ activeTicket?.id }}
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    Este es el veredicto final del comité. Esta acción cerrará el ticket.
                </p>

                <form @submit.prevent="registerDecision" class="mt-6 space-y-4">
                    <div>
                        <InputLabel for="resultado" value="Resultado del Veredicto" />
                        <SelectInput id="resultado" class="mt-1 block w-full" v-model="decisionForm.resultado" required>
                            <option value="sin_falta">Sin Falta</option>
                            <option value="falta_leve">Falta Leve</option>
                            <option value="falta_grave">Falta Grave</option>
                            <option value="sancionado">Sancionado</option>
                        </SelectInput>
                        <InputError :message="decisionForm.errors.resultado" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="resumen_decision" value="Resumen y Justificación de la Decisión" />
                        <Textarea id="resumen_decision" v-model="decisionForm.resumen_decision" class="mt-1 block w-full" rows="4" required />
                        <InputError :message="decisionForm.errors.resumen_decision" class="mt-2" />
                    </div>

                    <div>
                        <InputLabel for="medida_administrativa" value="Medida Administrativa o Pedagógica (Opcional)" />
                        <Textarea id="medida_administrativa" v-model="decisionForm.medida_administrativa" class="mt-1 block w-full" rows="2" />
                        <InputError :message="decisionForm.errors.medida_administrativa" class="mt-2" />
                    </div>

                    <div class="flex items-center">
                        <Checkbox id="requiere_capacitacion" v-model="decisionForm.requiere_capacitacion" :checked="decisionForm.requiere_capacitacion" />
                        <label for="requiere_capacitacion" class="ms-2 text-sm text-gray-600">¿Requiere capacitación obligatoria?</label>
                    </div>

                    <div class="flex justify-end">
                        <SecondaryButton @click="closeDecisionModal"> Cancelar </SecondaryButton>
                        <PrimaryButton class="ms-3" :class="{ 'opacity-25': decisionForm.processing }" :disabled="decisionForm.processing">
                            Confirmar Decisión y Cerrar Ticket
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

    </AuthenticatedLayout>
</template>