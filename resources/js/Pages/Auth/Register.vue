<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import Checkbox from '@/Components/Checkbox.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

// Variables de política
const showTermsModal = ref(false);
const policyVersion = 'v1.0';
const policyUpdatedAt = '04/11/2025';

const termsAccepted = ref(false);
const privacyAccepted = ref(false);
const marketingOptIn = ref(false);

const canAccept = computed(() => termsAccepted.value && privacyAccepted.value);

// Función de registro real
const registerUser = () => {
    const consent = buildConsentRecord();
    // Aquí puedes añadir consent al form si tu backend lo necesita
    // form.consent = consent;
    
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};

// Función submit que abre el modal
const submit = () => {
    termsAccepted.value = false;
    privacyAccepted.value = false;
    marketingOptIn.value = false;
    showTermsModal.value = true;
};

// Función para aceptar y registrar
const acceptAndRegister = () => {
    if (canAccept.value) {
        showTermsModal.value = false;
        registerUser();
    }
};

// Función para rechazar y redirigir
const rejectAndRedirect = () => {
    showTermsModal.value = false;
    router.get('/'); 
};

// Función para cerrar el modal
const closeModal = () => {
    showTermsModal.value = false;
};

// Funciones adicionales
function printPolicy() { 
    window.print(); 
}

function downloadPolicy() {
    const blob = new Blob(["Términos y Condiciones - Versión " + policyVersion], { type: 'text/html' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `politicas_${policyVersion}.html`;
    a.click();
    URL.revokeObjectURL(url);
}

function buildConsentRecord() {
    return {
        version: policyVersion,
        termsAccepted: termsAccepted.value,
        privacyAccepted: privacyAccepted.value,
        marketingOptIn: marketingOptIn.value,
        acceptedAtISO: new Date().toISOString(),
        ipHint: null
    };
}
</script>

<template>
    <Head title="Crear Cuenta" />
    <div class="min-h-screen font-sans antialiased lg:grid lg:grid-cols-12">
        <!-- Columna Izquierda (Visual y Épica) -->
        <div class="relative hidden lg:col-span-5 lg:flex flex-col items-center justify-center bg-[#0A101F] text-white p-12 overflow-hidden">
            <!-- Fondo animado de partículas -->
            <div id="particles-js" class="absolute inset-0 z-0"></div>
            
            <div class="relative z-10 text-center animate-fade-in-down">
                <Link href="/" class="flex justify-center mb-8">
                    <svg class="h-16 w-auto text-[#D4AF37] drop-shadow-[0_0_15px_rgba(212,175,55,0.5)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25-2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9M3 12V9m18 3a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m15-3V6a2.25 2.25 0 00-2.25-2.25H9.375a3 3 0 11-6 0H3.75A2.25 2.25 0 001.5 6v3m18-3h-2.25m-15 0H3.75" />
                    </svg>
                </Link>
                <h2 class="text-4xl font-bold mb-4 tracking-wider [text-wrap:balance]">Únase a la Vanguardia de la Gestión Legal</h2>
                <p class="text-blue-200 max-w-sm mx-auto [text-wrap:balance]">Cree su cuenta y comience a transformar la eficiencia de su cooperativa hoy mismo.</p>
            </div>
            <div class="absolute bottom-6 text-center text-sm text-blue-300 z-10 animate-fade-in-up">
                <p>Desarrollado por <a href="https://centipy.com" target="_blank" class="font-semibold underline hover:text-purple-500 transition-colors duration-300">Centipy.com</a></p>
            </div>
        </div>

        <!-- Columna Derecha (Formulario Elegante) -->
        <div class="lg:col-span-7 flex items-center justify-center min-h-screen bg-white dark:bg-slate-900 p-6">
            <div class="w-full max-w-md animate-fade-in-up">
                <div class="mb-10 text-center lg:text-left">
                    <h1 class="text-3xl font-bold text-slate-800 dark:text-white">Formulario de Registro</h1>
                    <p class="text-slate-500 dark:text-slate-400 mt-2">Complete los siguientes campos para crear su cuenta.</p>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <div>
                        <InputLabel for="name" value="Nombre Completo" class="text-slate-700 dark:text-slate-300" />
                        <TextInput id="name" type="text" class="mt-1 block w-full" v-model="form.name" required autofocus autocomplete="name" />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div>
                        <InputLabel for="email" value="Correo Electrónico" class="text-slate-700 dark:text-slate-300" />
                        <TextInput id="email" type="email" class="mt-1 block w-full" v-model="form.email" required autocomplete="username" />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div>
                        <InputLabel for="password" value="Contraseña" class="text-slate-700 dark:text-slate-300" />
                        <TextInput id="password" type="password" class="mt-1 block w-full" v-model="form.password" required autocomplete="new-password" />
                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>

                    <div>
                        <InputLabel for="password_confirmation" value="Confirmar Contraseña" class="text-slate-700 dark:text-slate-300" />
                        <TextInput id="password_confirmation" type="password" class="mt-1 block w-full" v-model="form.password_confirmation" required autocomplete="new-password" />
                        <InputError class="mt-2" :message="form.errors.password_confirmation" />
                    </div>

                    <div>
                        <PrimaryButton class="w-full justify-center text-base py-3 bg-gradient-to-r from-[#0052CC] to-blue-700 text-white hover:shadow-lg hover:shadow-blue-500/50 transition-all duration-300 transform hover:-translate-y-0.5" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                            <span class="font-bold tracking-wider">CREAR MI CUENTA</span>
                        </PrimaryButton>
                    </div>
                </form>

                <p class="mt-8 text-center text-sm text-slate-500 dark:text-slate-400">
                    ¿Ya tiene una cuenta?
                    <Link :href="route('login')" class="font-medium text-[#0052CC] hover:text-[#0041A3] dark:hover:text-blue-400 transition-colors duration-300">
                        Inicie sesión aquí
                    </Link>
                </p>
            </div>
        </div>
    </div>

    <!-- Modal de Términos y Condiciones -->
    <Modal :show="showTermsModal" @close="closeModal" role="dialog" aria-modal="true" aria-labelledby="termsTitle" aria-describedby="termsDesc">
        <div class="p-6 max-h-[85vh] overflow-hidden flex flex-col">
            <header class="flex items-start justify-between gap-4">
                <div>
                    <h2 id="termsTitle" class="text-lg font-medium text-gray-900 dark:text-gray-100">
                        Términos, Condiciones y Política de Datos
                    </h2>
                    <p id="termsDesc" class="text-xs text-gray-500 dark:text-gray-400">
                        Versión {{ policyVersion }} · Actualizado {{ policyUpdatedAt }}
                    </p>
                </div>
                <div class="shrink-0 flex items-center gap-2">
                    <button type="button" @click="printPolicy" class="text-sm underline hover:text-blue-600">Imprimir</button>
                    <button type="button" @click="downloadPolicy" class="text-sm underline hover:text-blue-600">Descargar</button>
                </div>
            </header>

            <div class="mt-4 grow overflow-y-auto pr-1 space-y-3">
                <!-- 1. Términos y Condiciones -->
                <details class="border border-gray-300 dark:border-gray-700 rounded-lg">
                    <summary class="cursor-pointer p-4 font-semibold text-gray-800 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
                        1. Términos y Condiciones de Uso
                    </summary>
                    <div class="p-4 border-t border-gray-300 dark:border-gray-700 space-y-3 text-sm text-gray-700 dark:text-gray-300">
                        <p><strong>Proveedor:</strong> Abogados en Colombia S.A.S., NIT [901.866.706-6], domicilio [Calle 44A # 68A - 106], correo [abogadosencolombiasas@gmail.com].</p>
                        <p><strong>Cuenta y veracidad:</strong> el Usuario declara información veraz y custodiar sus credenciales. Prohibido uso ilícito o que vulnere derechos o la seguridad.</p>
                        <p><strong>Alcance:</strong> la plataforma facilita gestión y comunicación abogado–cliente. La representación legal exige mandato independiente. No hay garantía de resultado.</p>
                        <p><strong>Propiedad intelectual:</strong> software y contenidos reservados.</p>
                        <p><strong>Confidencialidad:</strong> comunicaciones y archivos amparados por el secreto profesional del abogado.</p>
                        <p><strong>Seguridad e incidentes:</strong> controles razonables y notificación a titulares/autoridad si procede.</p>
                        <p><strong>Responsabilidad:</strong> se ofrece "tal cual", sin perjuicio de derechos irrenunciables del consumidor cuando apliquen.</p>
                        <p><strong>Ley y jurisdicción:</strong> Colombia, jueces de [Medellín]. Aceptación electrónica conforme a Ley 527/1999.</p>
                    </div>
                </details>

                <!-- 2. Política de Tratamiento -->
                <details class="border border-gray-300 dark:border-gray-700 rounded-lg">
                    <summary class="cursor-pointer p-4 font-semibold text-gray-800 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
                        2. Política de Tratamiento de Datos Personales (Ley 1581/2012)
                    </summary>
                    <div class="p-4 border-t border-gray-300 dark:border-gray-700 space-y-3 text-sm text-gray-700 dark:text-gray-300">
                        <p><strong>Responsable:</strong> Abogados en Colombia S.A.S., NIT [901.866.706-6], [Calle 44A # 68A - 106], [Medellín]. Correo PQR: [abogadosencolombiasas@gmail.com]. Oficial de Protección de Datos: [Nombre/correo].</p>
                        <p><strong>Finalidades:</strong> autenticación; gestión de servicios legales y expedientes; facturación; comunicaciones operativas; cumplimiento legal. <em>Opcional:</em> comunicaciones comerciales con autorización independiente.</p>
                        <p><strong>Derechos y plazos:</strong> conocer, actualizar, rectificar, suprimir y revocar. Consultas: 10 días hábiles (+5). Reclamos: 15 días hábiles (+8) y etiqueta "reclamo en trámite" en 2 días.</p>
                        <p><strong>Datos sensibles y de menores:</strong> tratamiento excepcional; en NNA solo bajo interés superior y autorización del representante.</p>
                        <p><strong>Transferencias/encargados:</strong> uso de proveedores tecnológicos [listar]. Transferencias internacionales sujetas a niveles adecuados o garantías contractuales.</p>
                        <p><strong>Seguridad e incidentes:</strong> medidas técnicas y organizativas; notificación a SIC/titulares cuando corresponda.</p>
                        <p><strong>Conservación y RNBD:</strong> por vigencia del encargo y plazos legales. [Indicar estado de inscripción en RNBD].</p>
                        <p><strong>Vigencia:</strong> versión {{ policyVersion }}; fecha {{ policyUpdatedAt }}.</p>
                    </div>
                </details>

                <!-- 3. Cookies -->
                <details class="border border-gray-300 dark:border-gray-700 rounded-lg">
                    <summary class="cursor-pointer p-4 font-semibold text-gray-800 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
                        3. Cookies (resumen)
                    </summary>
                    <div class="p-4 border-t border-gray-300 dark:border-gray-700 space-y-3 text-sm text-gray-700 dark:text-gray-300">
                        <p>Usamos cookies necesarias y, si autorizas, analíticas. Configúralas en "Preferencias de cookies".</p>
                    </div>
                </details>
            </div>

            <!-- Consentimientos -->
            <div class="mt-6 space-y-3">
                <div class="flex items-start gap-2">
                    <Checkbox v-model:checked="termsAccepted" id="chk-terms" />
                    <InputLabel for="chk-terms" class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                        He leído y acepto los <strong>Términos y Condiciones</strong>.
                    </InputLabel>
                </div>

                <div class="flex items-start gap-2">
                    <Checkbox v-model:checked="privacyAccepted" id="chk-privacy" />
                    <InputLabel for="chk-privacy" class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                        Autorizo el <strong>Tratamiento de mis datos personales</strong> conforme a la Política.
                    </InputLabel>
                </div>

                <div class="flex items-start gap-2">
                    <Checkbox v-model:checked="marketingOptIn" id="chk-mkt" />
                    <InputLabel for="chk-mkt" class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                        (Opcional) Acepto comunicaciones informativas/comerciales.
                    </InputLabel>
                </div>
            </div>

            <footer class="mt-6 flex justify-end gap-4">
                <SecondaryButton @click="rejectAndRedirect">
                    Rechazar
                </SecondaryButton>

                <PrimaryButton
                    @click="acceptAndRegister"
                    :disabled="!canAccept || form.processing"
                    :class="{ 'opacity-25': !canAccept || form.processing }"
                >
                    Aceptar y Registrarse
                </PrimaryButton>
            </footer>
        </div>
    </Modal>
</template>

<style scoped>
/* Animaciones de entrada */
@keyframes fade-in-down {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}

.animate-fade-in-down {
    animation: fade-in-down 1s ease-out forwards;
}

@keyframes fade-in-up {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.animate-fade-in-up {
    animation: fade-in-up 1s ease-out forwards;
}

/* Estilos para el fondo de partículas */
#particles-js {
    position: absolute;
    width: 100%;
    height: 100%;
    background-color: #0A192F;
}

/* Estilos para los inputs */
input[type="email"],
input[type="password"],
input[type="text"] {
    background-color: #F8FAFC;
    border: 1px solid #E2E8F0;
    transition: all 0.3s ease;
}

input[type="email"]:focus,
input[type="password"]:focus,
input[type="text"]:focus {
    border-color: #D4AF37;
    box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.2);
    background-color: white;
}

.dark input[type="email"],
.dark input[type="password"],
.dark input[type="text"] {
    background-color: #1E293B;
    border-color: #334155;
    color: #E2E8F0;
}

.dark input[type="email"]:focus,
.dark input[type="password"]:focus,
.dark input[type="text"]:focus {
    border-color: #D4AF37;
    background-color: #0F172A;
}
</style>