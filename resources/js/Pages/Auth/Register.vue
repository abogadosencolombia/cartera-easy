<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
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
</template>

<style>
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

/* Estilos para el fondo de partículas (requiere particles.js) */
#particles-js {
    position: absolute;
    width: 100%;
    height: 100%;
    background-color: #0A192F;
}

/* Estilos para los inputs */
input[type="email"], input[type="password"], input[type="text"] {
    background-color: #F8FAFC;
    border: 1px solid #E2E8F0;
    transition: all 0.3s ease;
}
input[type="email"]:focus, input[type="password"]:focus, input[type="text"]:focus {
    border-color: #D4AF37;
    box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.2);
    background-color: white;
}

.dark input[type="email"], .dark input[type="password"], .dark input[type="text"] {
    background-color: #1E293B;
    border-color: #334155;
    color: #E2E8F0;
}
.dark input[type="email"]:focus, .dark input[type="password"]:focus, .dark input[type="text"]:focus {
    border-color: #D4AF37;
    background-color: #0F172A;
}
</style>
