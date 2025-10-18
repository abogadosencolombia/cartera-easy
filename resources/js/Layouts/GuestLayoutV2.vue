<script setup>
import { Link } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
</script>

<template>
    <div class="min-h-screen bg-gray-100 dark:bg-gray-900 text-gray-800 dark:text-gray-200">
        <nav class="bg-white dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-700/50 backdrop-blur-sm fixed w-full z-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <Link :href="route('welcome')">
                            <ApplicationLogo class="block h-9 w-auto fill-current text-gray-800 dark:text-gray-200" />
                        </Link>
                         <span class="ml-3 font-semibold text-xl">Cobro Cartera</span>
                    </div>

                    <div class="hidden sm:flex sm:items-center sm:ml-6">
                         <Link
                            v-if="$page.props.canLogin"
                            :href="route('login')"
                            class="rounded-md px-4 py-2 text-gray-700 dark:text-gray-300 ring-1 ring-transparent transition hover:text-black/70 dark:hover:text-white/80 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                        >
                            Iniciar Sesión
                        </Link>

                        <Link
                            v-if="$page.props.canRegister"
                            :href="route('register')"
                            class="ml-4 rounded-md px-4 py-2 text-white bg-indigo-600 ring-1 ring-transparent transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                        >
                            Registrarse
                        </Link>
                    </div>
                </div>
            </div>
        </nav>

        <main class="pt-16">
            <slot />
        </main>

        <footer class="py-8 text-center text-sm text-gray-500 dark:text-gray-400">
            <div class="flex justify-center items-center space-x-4">
                <span>Desarrollado por <a href="https://centipy.com" target="_blank" class="underline hover:text-indigo-500">Centipy.com</a></span>
            </div>
            <p class="mt-4">© {{ new Date().getFullYear() }} Cooperativas. Todos los derechos reservados.</p>
        </footer>
    </div>
</template>
