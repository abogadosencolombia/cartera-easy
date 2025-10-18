<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { onMounted } from 'vue';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
});

// Lógica para animaciones al hacer scroll
onMounted(() => {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-fade-in-up');
            }
        });
    }, {
        threshold: 0.1
    });

    document.querySelectorAll('.scroll-animate').forEach(el => {
        observer.observe(el);
    });
});

const features = [
    {
        name: 'Gestión de Casos Centralizada',
        description: 'Supervise cada etapa de sus procesos judiciales con una claridad y control sin precedentes.',
        icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-7 0H5m2 0v-5h3v5m4 0v-3h3v3',
    },
    {
        name: 'Cumplimiento Legal Automatizado',
        description: 'Nuestro motor de validación minimiza el riesgo, asegurando que cada caso cumpla con los requisitos normativos.',
        icon: 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    },
    {
        name: 'Inteligencia de Negocios',
        description: 'Convierta datos en decisiones. Nuestros reportes estratégicos le ofrecen una visión 360° de su cartera.',
        icon: 'M10.5 6a7.5 7.5 0 100 15 7.5 7.5 0 000-15zM21 21l-5.197-5.197',
    },
    {
        name: 'Seguridad de Nivel Bancario',
        description: 'Protegemos su información con los más altos estándares de encriptación y protocolos de seguridad.',
        icon: 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z',
    },
];
</script>

<template>
    <Head title="Bienvenido a Cobro Cartera" />
    <div class="bg-[#0A101F] text-gray-300 font-sans antialiased">
        <div class="absolute inset-0 z-0 overflow-hidden">
            <div class="absolute top-0 left-0 w-96 h-96 bg-blue-900 rounded-full mix-blend-screen filter blur-3xl opacity-20 animate-blob"></div>
            <div class="absolute top-0 right-0 w-96 h-96 bg-yellow-900 rounded-full mix-blend-screen filter blur-3xl opacity-20 animate-blob animation-delay-2000"></div>
            <div class="absolute bottom-20 left-20 w-96 h-96 bg-blue-800 rounded-full mix-blend-screen filter blur-3xl opacity-20 animate-blob animation-delay-4000"></div>
        </div>

        <div class="relative z-10">
            <header class="absolute inset-x-0 top-0 z-50">
                <nav class="flex items-center justify-between p-6 lg:px-8" aria-label="Global">
                    <div class="flex lg:flex-1">
                        <Link href="/" class="-m-1.5 p-1.5 flex items-center space-x-3">
                            <svg class="h-8 w-auto text-[#D4AF37]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9M3 12V9m18 3a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m15-3V6a2.25 2.25 0 00-2.25-2.25H9.375a3 3 0 11-6 0H3.75A2.25 2.25 0 001.5 6v3m18-3h-2.25m-15 0H3.75" />
                            </svg>
                            <span class="text-2xl font-bold text-white tracking-wider">Cobro Cartera</span>
                        </Link>
                    </div>
                    <div class="lg:flex lg:flex-1 lg:justify-end">
                        <Link v-if="$page.props.auth.user" :href="route('dashboard')" class="text-sm font-semibold leading-6 text-gray-100 hover:text-white transition-colors duration-300">Dashboard <span aria-hidden="true">&rarr;</span></Link>
                        <Link v-else :href="route('login')" class="text-sm font-semibold leading-6 text-gray-100 hover:text-white transition-colors duration-300">Iniciar Sesión <span aria-hidden="true">&rarr;</span></Link>
                    </div>
                </nav>
            </header>

            <main class="relative isolate min-h-screen flex items-center">
                <div class="mx-auto max-w-2xl text-center px-6">
                    <h1 class="text-4xl font-bold tracking-tight text-white sm:text-7xl [text-wrap:balance] animate-fade-in-down">
                        La Vanguardia en Gestión Jurídica Cooperativa
                    </h1>
                    <p class="mt-6 text-lg leading-8 text-gray-300 [text-wrap:balance] animate-fade-in-down" style="animation-delay: 200ms;">
                        Transforme su cartera de cobranza con una plataforma que une poder, inteligencia y una elegancia sin concesiones.
                    </p>
                    <div class="mt-10 flex items-center justify-center gap-x-6 animate-fade-in-down" style="animation-delay: 400ms;">
                        <Link :href="route('register')" class="rounded-md bg-[#D4AF37] px-5 py-3 text-sm font-semibold text-black shadow-lg hover:bg-yellow-400 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-yellow-500 transition-transform duration-300 hover:scale-105">
                            Solicitar Acceso
                        </Link>
                        <a href="#features" class="text-sm font-semibold leading-6 text-white group transition-colors duration-300">
                            Descubrir la Plataforma <span class="inline-block transition-transform group-hover:translate-x-1" aria-hidden="true">→</span>
                        </a>
                    </div>
                </div>
            </main>
        </div>

        <section id="features" class="bg-white/5 py-24 sm:py-32 scroll-animate">
            <div class="mx-auto max-w-7xl px-6 lg:px-8">
                <div class="mx-auto max-w-2xl lg:text-center">
                    <h2 class="text-base font-semibold leading-7 text-[#D4AF37]">Inteligencia Operativa</h2>
                    <p class="mt-2 text-3xl font-bold tracking-tight text-white sm:text-4xl">Diseñado para la Máxima Eficiencia</p>
                    <p class="mt-6 text-lg leading-8 text-gray-300">Cada función está creada para maximizar el rendimiento de su equipo y la rentabilidad de su cartera.</p>
                </div>
                <div class="mx-auto mt-16 max-w-2xl sm:mt-20 lg:mt-24 lg:max-w-none">
                    <dl class="grid grid-cols-1 gap-x-8 gap-y-10 md:grid-cols-2 lg:grid-cols-4 lg:gap-y-16">
                        <div v-for="(feature, index) in features" :key="feature.name" class="flex flex-col p-8 bg-white/5 rounded-2xl border border-white/10 hover:border-yellow-400/50 transition-all duration-300 transform hover:-translate-y-2 scroll-animate" :style="{ 'animation-delay': `${index * 150}ms` }">
                            <dt class="flex items-center gap-x-3 text-base font-semibold leading-7 text-white">
                                <div class="flex h-10 w-10 flex-none items-center justify-center rounded-lg bg-[#0052CC]">
                                    <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" :d="feature.icon" />
                                    </svg>
                                </div>
                                {{ feature.name }}
                            </dt>
                            <dd class="mt-4 flex flex-auto flex-col text-base leading-7 text-gray-400">
                                <p class="flex-auto">{{ feature.description }}</p>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </section>

        <footer class="py-16 text-center text-sm text-gray-400 border-t border-white/10">
            <div class="flex justify-center items-center space-x-4">
                <span>Desarrollado por <a href="https://centipy.com" target="_blank" class="font-semibold underline hover:text-[#7F5AF0] transition-colors duration-300">Centipy.com</a></span>
            </div>
            <p class="mt-4">© {{ new Date().getFullYear() }} Cobro Cartera. Todos los derechos reservados.</p>
        </footer>
    </div>
</template>

<style>
/* Animación de fondo con degradado suave */
@keyframes blob {
	0% {
		transform: translate(0px, 0px) scale(1);
	}
	33% {
		transform: translate(30px, -50px) scale(1.1);
	}
	66% {
		transform: translate(-20px, 20px) scale(0.9);
	}
	100% {
		transform: translate(0px, 0px) scale(1);
	}
}

.animate-blob {
	animation: blob 7s infinite;
}

.animation-delay-2000 {
	animation-delay: 2s;
}

.animation-delay-4000 {
	animation-delay: 4s;
}

/* Animaciones de entrada de texto */
@keyframes fade-in-down {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.animate-fade-in-down {
    animation: fade-in-down 0.8s ease-out forwards;
}

/* Animaciones de entrada al hacer scroll */
@keyframes fade-in-up {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.scroll-animate {
    opacity: 0;
}
.animate-fade-in-up {
    animation: fade-in-up 0.8s ease-out forwards;
}
</style>
