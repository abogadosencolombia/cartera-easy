<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    nombre: '',
    NIT: '',
    tipo_vigilancia: 'Supersolidaria',
    fecha_constitucion: '',
    numero_matricula_mercantil: '',
    tipo_persona: 'Jurídica',
    representante_legal_nombre: '',
    representante_legal_cedula: '',
    contacto_nombre: '',
    contacto_telefono: '',
    contacto_correo: '',
    correo_notificaciones_judiciales: '',
    usa_libranza: false,
    requiere_carta_instrucciones: false,
    tipo_garantia_frecuente: 'codeudor',
    tasa_maxima_moratoria: '',
    ciudad_principal_operacion: '',
});

const submit = () => {
    form.post(route('cooperativas.store'));
};
</script>

<template>
    <Head title="Registrar Nueva Cooperativa" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Registrar Nueva Cooperativa
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 md:p-8 text-gray-900 dark:text-gray-100">
                        <p class="text-gray-500 dark:text-gray-400 mb-6">Por favor, complete los siguientes datos para registrar una nueva entidad en el sistema.</p>
                        
                        <form @submit.prevent="submit" class="space-y-8">
                            
                            <section>
                                <h3 class="text-lg font-medium border-b pb-2 mb-6">Información General y Legal</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <InputLabel for="nombre" value="Nombre de la Cooperativa" />
                                        <TextInput v-model="form.nombre" id="nombre" type="text" class="mt-1 block w-full" required />
                                        <InputError :message="form.errors.nombre" class="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel for="NIT" value="NIT" />
                                        <TextInput v-model="form.NIT" id="NIT" type="text" class="mt-1 block w-full" required />
                                        <InputError :message="form.errors.NIT" class="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel for="fecha_constitucion" value="Fecha de Constitución" />
                                        <TextInput v-model="form.fecha_constitucion" id="fecha_constitucion" type="date" class="mt-1 block w-full" required />
                                        <InputError :message="form.errors.fecha_constitucion" class="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel for="numero_matricula_mercantil" value="Matrícula Mercantil (Opcional)" />
                                        <TextInput v-model="form.numero_matricula_mercantil" id="numero_matricula_mercantil" type="text" class="mt-1 block w-full" />
                                        <InputError :message="form.errors.numero_matricula_mercantil" class="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel for="tipo_vigilancia" value="Entidad de Vigilancia" />
                                        <select v-model="form.tipo_vigilancia" id="tipo_vigilancia" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                                            <option>Supersolidaria</option>
                                            <option>SFC</option>
                                            <option>Otro</option>
                                        </select>
                                        <InputError :message="form.errors.tipo_vigilancia" class="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel for="tipo_persona" value="Tipo de Persona" />
                                        <select v-model="form.tipo_persona" id="tipo_persona" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                                            <option>Jurídica</option>
                                            <option>Natural</option>
                                        </select>
                                        <InputError :message="form.errors.tipo_persona" class="mt-2" />
                                    </div>
                                </div>
                            </section>

                            <section>
                                <h3 class="text-lg font-medium border-b pb-2 mb-6">Representante Legal y Contacto</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <InputLabel for="representante_legal_nombre" value="Nombre del Representante Legal" />
                                        <TextInput v-model="form.representante_legal_nombre" id="representante_legal_nombre" type="text" class="mt-1 block w-full" required />
                                        <InputError :message="form.errors.representante_legal_nombre" class="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel for="representante_legal_cedula" value="Cédula del Representante" />
                                        <TextInput v-model="form.representante_legal_cedula" id="representante_legal_cedula" type="text" class="mt-1 block w-full" required />
                                        <InputError :message="form.errors.representante_legal_cedula" class="mt-2" />
                                    </div>
                                     <div>
                                        <InputLabel for="contacto_nombre" value="Nombre del Contacto Principal" />
                                        <TextInput v-model="form.contacto_nombre" id="contacto_nombre" type="text" class="mt-1 block w-full" required />
                                        <InputError :message="form.errors.contacto_nombre" class="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel for="contacto_telefono" value="Teléfono de Contacto" />
                                        <TextInput v-model="form.contacto_telefono" id="contacto_telefono" type="text" class="mt-1 block w-full" required />
                                        <InputError :message="form.errors.contacto_telefono" class="mt-2" />
                                    </div>
                                     <div class="md:col-span-2">
                                        <InputLabel for="contacto_correo" value="Email de Contacto" />
                                        <TextInput v-model="form.contacto_correo" id="contacto_correo" type="email" class="mt-1 block w-full" required />
                                        <InputError :message="form.errors.contacto_correo" class="mt-2" />
                                    </div>
                                    <div class="md:col-span-2">
                                        <InputLabel for="correo_notificaciones_judiciales" value="Email para Notificaciones Judiciales" />
                                        <TextInput v-model="form.correo_notificaciones_judiciales" id="correo_notificaciones_judiciales" type="email" class="mt-1 block w-full" required />
                                        <InputError :message="form.errors.correo_notificaciones_judiciales" class="mt-2" />
                                    </div>
                                </div>
                            </section>

                            <section>
                                <h3 class="text-lg font-medium border-b pb-2 mb-6">Políticas de Cobranza y Operación</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <InputLabel for="tipo_garantia_frecuente" value="Tipo de Garantía Frecuente" />
                                        <select v-model="form.tipo_garantia_frecuente" id="tipo_garantia_frecuente" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 rounded-md shadow-sm">
                                            <option>codeudor</option>
                                            <option>hipotecaria</option>
                                            <option>prendaria</option>
                                            <option>sin garantía</option>
                                        </select>
                                        <InputError :message="form.errors.tipo_garantia_frecuente" class="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel for="tasa_maxima_moratoria" value="Tasa de Usura (%)" />
                                        <TextInput v-model="form.tasa_maxima_moratoria" id="tasa_maxima_moratoria" type="text" class="mt-1 block w-full" required />
                                        <InputError :message="form.errors.tasa_maxima_moratoria" class="mt-2" />
                                    </div>
                                    <div>
                                        <InputLabel for="ciudad_principal_operacion" value="Ciudad Principal de Operación" />
                                        <TextInput v-model="form.ciudad_principal_operacion" id="ciudad_principal_operacion" type="text" class="mt-1 block w-full" />
                                        <InputError :message="form.errors.ciudad_principal_operacion" class="mt-2" />
                                    </div>
                                    <div class="md:col-span-2 grid grid-cols-2 gap-4 items-center pt-4">
                                        <label class="flex items-center">
                                            <Checkbox v-model:checked="form.usa_libranza" name="usa_libranza" />
                                            <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">¿Usa Libranza?</span>
                                        </label>
                                        <label class="flex items-center">
                                            <Checkbox v-model:checked="form.requiere_carta_instrucciones" name="requiere_carta_instrucciones" />
                                            <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">¿Requiere Carta de Instrucciones?</span>
                                        </label>
                                    </div>
                                </div>
                            </section>

                            <div class="flex items-center justify-end mt-8 border-t pt-6">
                                <Link :href="route('cooperativas.index')" class="text-sm text-gray-600 hover:underline mr-4">Cancelar</Link>
                                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                                    Guardar Cooperativa
                                </PrimaryButton>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
