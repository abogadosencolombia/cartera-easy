<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import Textarea from '@/Components/Textarea.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    proceso: Object,
    abogadosYGestores: Array,
    personas: Array,
    juzgados: Array,
    subtipos_proceso: Array,
    etapas_procesales: Array,
});

// El formulario se inicializa con los datos del proceso que estamos editando
const form = useForm({
    _method: 'PUT', // Importante para que Laravel sepa que es una actualización
    user_id: props.proceso.user_id,
    radicado_externo: props.proceso.radicado_externo,
    juzgado_id: props.proceso.juzgado_id,
    naturaleza_proceso: props.proceso.naturaleza_proceso,
    asunto: props.proceso.asunto,
    demandante_id: props.proceso.demandante_id,
    demandado_id: props.proceso.demandado_id,
    link_expediente_digital: props.proceso.link_expediente_digital,
    correo_juzgado: props.proceso.correo_juzgado,
    ubicacion_drive: props.proceso.ubicacion_drive,
    subtipo_proceso: props.proceso.subtipo_proceso,
    etapa_procesal: props.proceso.etapa_procesal,
    tipo_proceso: props.proceso.tipo_proceso,
});

const submit = () => {
    form.post(route('procesos.update', props.proceso.id));
};
</script>

<template>
    <Head :title="'Editar Proceso Judicial #' + proceso.radicado_externo" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Editando Proceso Judicial
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 md:p-8">
                        <form @submit.prevent="submit" class="space-y-6">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <InputLabel for="user_id" value="Abogado / Gestor Responsable *" />
                                    <select v-model="form.user_id" id="user_id" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                                        <option v-for="user in abogadosYGestores" :key="user.id" :value="user.id">{{ user.name }}</option>
                                    </select>
                                    <InputError class="mt-2" :message="form.errors.user_id" />
                                </div>
                                <div>
                                    <InputLabel for="radicado_externo" value="Radicado Externo" />
                                    <TextInput id="radicado_externo" type="text" class="mt-1 block w-full" v-model="form.radicado_externo" />
                                    <InputError class="mt-2" :message="form.errors.radicado_externo" />
                                </div>
                                <div>
                                    <InputLabel for="juzgado_id" value="Juzgado" />
                                    <select v-model="form.juzgado_id" id="juzgado_id" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                                        <option :value="null">-- Sin especificar --</option>
                                        <option v-for="juzgado in juzgados" :key="juzgado.id" :value="juzgado.id">{{ juzgado.nombre }}</option>
                                    </select>
                                    <InputError class="mt-2" :message="form.errors.juzgado_id" />
                                </div>
                                <div>
                                    <InputLabel for="naturaleza_proceso" value="Naturaleza del Proceso" />
                                    <TextInput id="naturaleza_proceso" type="text" class="mt-1 block w-full" v-model="form.naturaleza_proceso" />
                                    <InputError class="mt-2" :message="form.errors.naturaleza_proceso" />
                                </div>
                                <div>
                                    <InputLabel for="subtipo_proceso" value="Subtipo de Proceso" />
                                    <select v-model="form.subtipo_proceso" id="subtipo_proceso" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                                        <option value="">-- Sin especificar --</option>
                                        <option v-for="subtipo in subtipos_proceso" :key="subtipo" :value="subtipo">{{ subtipo }}</option>
                                    </select>
                                    <InputError class="mt-2" :message="form.errors.subtipo_proceso" />
                                </div>
                                <div>
                                    <InputLabel for="etapa_procesal" value="Etapa Procesal" />
                                    <select v-model="form.etapa_procesal" id="etapa_procesal" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                                        <option value="">-- Sin especificar --</option>
                                        <option v-for="etapa in etapas_procesales" :key="etapa" :value="etapa">{{ etapa }}</option>
                                    </select>
                                    <InputError class="mt-2" :message="form.errors.etapa_procesal" />
                                </div>
                                <div>
                                    <InputLabel for="demandante_id" value="Demandante *" />
                                    <select v-model="form.demandante_id" id="demandante_id" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                                        <option v-for="persona in personas" :key="persona.id" :value="persona.id">{{ persona.nombre_completo }} ({{ persona.numero_documento }})</option>
                                    </select>
                                    <InputError class="mt-2" :message="form.errors.demandante_id" />
                                </div>
                                <div>
                                    <InputLabel for="demandado_id" value="Demandado *" />
                                    <select v-model="form.demandado_id" id="demandado_id" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                                        <option v-for="persona in personas" :key="persona.id" :value="persona.id">{{ persona.nombre_completo }} ({{ persona.numero_documento }})</option>
                                    </select>
                                    <InputError class="mt-2" :message="form.errors.demandado_id" />
                                </div>
                            </div>
                            
                            <div>
                                <InputLabel for="asunto" value="Asunto" />
                                <Textarea id="asunto" class="mt-1 block w-full" v-model="form.asunto" rows="4" />
                                <InputError class="mt-2" :message="form.errors.asunto" />
                            </div>
                             <div>
                                <InputLabel for="link_expediente_digital" value="Link Expediente Digital" />
                                <TextInput id="link_expediente_digital" type="url" class="mt-1 block w-full" v-model="form.link_expediente_digital" placeholder="https://" />
                                <InputError class="mt-2" :message="form.errors.link_expediente_digital" />
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                               <div>
                                    <InputLabel for="correo_juzgado" value="Email del Juzgado" />
                                    <TextInput id="correo_juzgado" type="email" class="mt-1 block w-full" v-model="form.correo_juzgado" />
                                    <InputError class="mt-2" :message="form.errors.correo_juzgado" />
                                </div>
                                <div>
                                    <InputLabel for="ubicacion_drive" value="Ubicación en Drive" />
                                    <TextInput id="ubicacion_drive" type="text" class="mt-1 block w-full" v-model="form.ubicacion_drive" />
                                    <InputError class="mt-2" :message="form.errors.ubicacion_drive" />
                                </div>
                            </div>

                            <div class="flex items-center justify-end mt-6">
                                <Link :href="route('procesos.show', proceso.id)" class="text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">Cancelar</Link>
                                <PrimaryButton class="ms-4" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                    Actualizar Proceso
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>