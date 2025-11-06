<script setup>
import { ref, onMounted, computed, onUnmounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

// --- Lógica del Estado de la Página (Sin cambios) ---

// Props de Laravel
defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    laravelVersion: String,
    phpVersion: String,
});

// Lógica para el banner de cookies
const showCookieBanner = ref(false);

// --- INICIO: FUNCIÓN DE COOKIES ---
const acceptCookies = () => {
    localStorage.setItem('cookies_accepted', 'true');
    showCookieBanner.value = false;
};
// --- FIN: FUNCIÓN DE COOKIES ---

// Lógica para el Modal de Privacidad
const showPrivacyModal = ref(false);
const openPrivacyModal = () => (showPrivacyModal.value = true);
const closePrivacyModal = () => (showPrivacyModal.value = false);

// Lógica para Pestañas de Características (Features)
const activeFeatureTab = ref('centralice');
const selectFeatureTab = (tab) => (activeFeatureTab.value = tab);

// Lógica del Formulario de Contacto
const contactForm = ref({ name: '', email: '', message: '' });
const contactFormSubmitted = ref(false);
const submitContactForm = () => {
    // --- INICIO: LÓGICA DE WHATSAPP (Preservada) ---
    const numero = '573152819233'; // Número sin '+'
    const nombre = contactForm.value.name;
    const email = contactForm.value.email;
    const mensaje = contactForm.value.message;

    // Formatear el mensaje
    const textoMensaje = `¡Hola! 👋\n\nSoy ${nombre} (${email}) y estoy interesado en una demo de Cobro Cartera.\n\nMi mensaje es:\n"${mensaje}"`;
    
    // Codificar para URL
    const urlTexto = encodeURIComponent(textoMensaje);
    
    // Crear y abrir la URL de WhatsApp
    const urlWhatsApp = `https://wa.me/${numero}?text=${urlTexto}`;
    window.open(urlWhatsApp, '_blank');
    // --- FIN: LÓGICA DE WHATSAPP ---

    // Simulación de éxito
    console.log('Redirigiendo a WhatsApp con el mensaje:', textoMensaje);
    contactFormSubmitted.value = true;
    contactForm.value = { name: '', email: '', message: '' };
    
    setTimeout(() => {
        contactFormSubmitted.value = false;
    }, 3000);
};

// --- MEJORA: Contenido de Características (Icono es clave) ---
// Se eliminó la propiedad 'image'
const featuresContent = {
    centralice: {
        title: 'Todo en un solo lugar',
        description: 'Acceda a expedientes, documentos, clientes y contactos al instante. Convierta el desorden de archivos en un activo digital organizado y seguro.',
        icon: 'M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z',
    },
    organice: {
        title: 'Flexibilidad Total',
        description: 'Nuestra plataforma se adapta a su especialidad. Gestione con precisión desde complejos cobros de cartera de cooperativas hasta delicados asuntos personales.',
        icon: 'M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5',
    },
    colabore: {
        title: 'Colaboración Transparente',
        description: 'Defina permisos claros. Administradores configuran, abogados ejecutan y gestores actualizan en tiempo real. Elimine los cuellos de botella.',
        icon: 'M18 18.72a9.094 9.094 0 00-3.071 1.99a9.094 9.094 0 00-3.071-1.99m-9.585 5.176l.345-.17a1.125 1.125 0 011.086 0l.345.17a1.125 1.125 0 001.086 0l.345-.17a1.125 1.125 0 011.086 0l.345.17a1.125 1.125 0 001.086 0l.345-.17a1.125 1.125 0 011.086 0l.345.17a1.125 1.125 0 001.086 0l.345-.17a1.125 1.125 0 011.086 0l.345.17a1.125 1.125 0 001.086 0l.345-.17a1.125 1.125 0 011.086 0l1.173 1.173c.121.12.27.218.428.304l.309.176A1.125 1.125 0 0021 21.178V6.983a1.125 1.125 0 00-.624-1.03l-1.173-1.173a1.125 1.125 0 00-.428-.304l-.309-.176A1.125 1.125 0 0017.25 4.102V3.75A1.125 1.125 0 0016.125 2.625H5.625A1.125 1.125 0 004.5 3.75v.352c0 .248.09.487.26.66l.309.176c.158.086.307.184.428.304l1.173 1.173A1.125 1.125 0 006.75 8.017v5.966c0 .248-.09.487-.26.66l-.309.176a1.125 1.125 0 00-.428.304l-1.173 1.173A1.125 1.125 0 003 17.653v3.524c0 .351.098.683.278.966l.345.17z',
    },
};

const activeFeature = computed(() => featuresContent[activeFeatureTab.value]);

// --- Lógica de Animación en Scroll (Sin cambios) ---
const animatedSections = ref([]);

const observer = new IntersectionObserver(
    (entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-section-in');
                observer.unobserve(entry.target);
            }
        });
    },
    {
        threshold: 0.1,
    }
);

onMounted(() => {
    // Comprobar cookies
    if (localStorage.getItem('cookies_accepted') !== 'true') {
        showCookieBanner.value = true;
    }
    
    // Observar secciones animadas
    document.querySelectorAll('.animate-section').forEach((section) => {
        animatedSections.value.push(section);
        observer.observe(section);
    });
});

onUnmounted(() => {
    // Limpiar observador
    animatedSections.value.forEach((section) => {
        if (section) {
            observer.unobserve(section);
        }
    });
});

</script>

<template>
    <Head title="Bienvenido a Cobro Cartera" />

    <!-- CONTENEDOR PRINCIPAL -->
    <div class="flex flex-col min-h-screen font-sans antialiased text-slate-900 dark:text-slate-100 bg-white dark:bg-slate-950 overflow-x-hidden">

        <!-- Header (Navegación Corregida) -->
        <header class="sticky top-0 z-40 w-full bg-white/80 dark:bg-slate-950/80 backdrop-blur-sm border-b border-slate-200 dark:border-slate-800">
            <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center h-16">
                    <!-- Logo -->
                    <Link href="/" class="flex items-center gap-2 text-slate-800 dark:text-white">
                        <svg class="h-8 w-auto text-[#D4AF37]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25-2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9M3 12V9m18 3a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m15-3V6a2.25 2.25 0 00-2.25-2.25H9.375a3 3 0 11-6 0H3.75A2.25 2.25 0 001.5 6v3m18-3h-2.25m-15 0H3.75" />
                        </svg>
                        <span class="font-bold text-xl tracking-tight">Cobro Cartera</span>
                    </Link>
                    
                    <!-- Navegación y Autenticación (Corregida) -->
                    <div class="flex items-center gap-4">
                        <nav class="hidden md:flex items-center gap-6 text-sm font-medium text-slate-600 dark:text-slate-300">
                            <a href="#features" class="hover:text-[#D4AF37] transition-colors">Solución</a>
                            <!-- SE ELIMINÓ 'CLIENTES' -->
                            <a href="#contact" class="hover:text-[#D4AF37] transition-colors">Contacto</a>
                        </nav>

                        <div v-if="canLogin" class="flex items-center gap-3">
                            <Link
                                v-if="$page.props.auth.user"
                                :href="route('dashboard')"
                                class="px-4 py-2 text-sm font-semibold text-white bg-gradient-to-r from-[#0052CC] to-blue-700 rounded-lg shadow-md hover:shadow-lg transition-all"
                            >
                                Dashboard
                            </Link>

                            <template v-else>
                                <Link
                                    :href="route('login')"
                                    class="px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:text-[#D4AF37] dark:hover:text-[#D4AF37] transition-colors"
                                >
                                    Iniciar Sesión
                                </Link>

                                <Link
                                    v-if="canRegister"
                                    :href="route('register')"
                                    class="hidden sm:inline-flex px-4 py-2 text-sm font-semibold text-white bg-gradient-to-r from-[#0052CC] to-blue-700 rounded-lg shadow-md hover:shadow-lg transition-all"
                                >
                                    Crear Cuenta
                                </Link>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-grow">

            <!-- Hero Section (Sin Cambios) -->
            <section class="relative py-24 md:py-36 lg:py-48 bg-slate-950 text-white overflow-hidden">
                <!-- Fondo Dinámico (CSS-puro) -->
                <div class="absolute inset-0 -z-10 h-full w-full bg-slate-950">
                    <div class="absolute bottom-0 left-0 right-0 top-0 bg-[radial-gradient(ellipse_80%_80%_at_50%_-20%,rgba(120,119,198,0.3),rgba(255,255,255,0))]"></div>
                    <div class="absolute inset-0 bg-[linear-gradient(to_right,#4f4f4f2e_1px,transparent_1px),linear-gradient(to_bottom,#4f4f4f2e_1px,transparent_1px)] bg-[size:100px_100px] opacity-10"></div>
                </div>
                
                <div class="relative z-10 container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 text-center animate-fade-in-up">
                    <h1 class="text-4xl md:text-5xl lg:text-6xl font-extrabold tracking-tight [text-wrap:balance]">
                        Transforme su Práctica Legal.
                        <span class="text-[#D4AF37] block">Bienvenido a la Gestión Definitiva.</span>
                    </h1>
                    <p class="mt-6 max-w-2xl mx-auto text-lg md:text-xl text-blue-200 [text-wrap:balance]">
                        La plataforma para centralizar casos, automatizar cartera y unificar la comunicación entre abogados, gestores y clientes.
                    </p>
                    <div class="mt-10 flex flex-col sm:flex-row justify-center items-center gap-4">
                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="w-full sm:w-auto px-8 py-3 text-lg font-semibold text-white bg-gradient-to-r from-[#0052CC] to-blue-700 rounded-lg shadow-lg hover:shadow-xl hover:shadow-blue-500/50 transition-all duration-300 transform hover:-translate-y-0.5"
                        >
                            Comenzar Ahora
                        </Link>
                        <a
                            href="#features"
                            class="w-full sm:w-auto px-8 py-3 text-lg font-semibold text-slate-900 dark:text-white bg-white dark:bg-slate-800 rounded-lg shadow-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-all duration-300"
                        >
                            Vea la Solución
                        </a>
                    </div>
                </div>
            </section>

            <!-- SECCIÓN Social Proof (Logos) ELIMINADA -->

            <!-- Features Section (GRAN TRANSFORMACIÓN VISUAL) -->
            <section id="features" class="py-20 md:py-28 bg-white dark:bg-slate-950 overflow-hidden">
                <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    
                    <!-- Cabecera de la sección -->
                    <div class="text-center mb-16 animate-section">
                        <h2 class="text-3xl md:text-4xl font-bold text-slate-800 dark:text-white [text-wrap:balance]">
                            Más que un software. Es su nuevo flujo de trabajo.
                        </h2>
                        <p class="mt-4 text-lg text-slate-600 dark:text-slate-400 max-w-2xl mx-auto [text-wrap:balance]">
                            Diseñado para firmas, cooperativas y abogados que valoran la eficiencia y la claridad.
                        </p>
                    </div>
                    
                    <!-- Layout dinámico de Features -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-16 items-center animate-section">
                        
                        <!-- Columna de Pestañas (Vertical) (Sin Cambios) -->
                        <div class="flex flex-col gap-4">
                            <button
                                v-for="(feature, key) in featuresContent"
                                :key="key"
                                @click="selectFeatureTab(key)"
                                :class="[
                                    'p-6 rounded-xl text-left transition-all duration-300 group',
                                    activeFeatureTab === key 
                                        ? 'bg-blue-50 dark:bg-slate-800/50 shadow-lg' 
                                        : 'hover:bg-slate-50 dark:hover:bg-slate-800/30'
                                ]"
                            >
                                <div class="flex items-center gap-4">
                                    <div :class="[
                                        'flex-shrink-0 w-12 h-12 rounded-lg flex items-center justify-center transition-colors',
                                        activeFeatureTab === key ? 'bg-blue-600 text-white' : 'bg-slate-200 dark:bg-slate-800 text-blue-600 dark:text-[#D4AF37] group-hover:bg-blue-100 dark:group-hover:bg-slate-700'
                                    ]">
                                        <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" :d="feature.icon" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-semibold text-slate-800 dark:text-white">{{ feature.title }}</h3>
                                        <p :class="[
                                            'text-slate-600 dark:text-slate-400 transition-all',
                                            activeFeatureTab === key ? 'mt-2' : 'h-0 opacity-0'
                                        ]">
                                            {{ feature.description }}
                                        </p>
                                    </div>
                                </div>
                            </button>
                        </div>
                        
                        <!-- Columna de Visualización (REEMPLAZADA) -->
                        <div class="relative min-h-[400px] lg:min-h-[500px]">
                            <!-- Mockup de App/Browser -->
                            <div class="relative w-full h-full aspect-[4/3] bg-slate-100 dark:bg-slate-900 rounded-xl shadow-2xl overflow-hidden border border-slate-200 dark:border-slate-800 p-2">
                                <!-- Barra de Navegador Falsa -->
                                <div class="h-8 bg-slate-50 dark:bg-slate-800 rounded-t-lg flex items-center px-4 gap-1.5">
                                    <div class="w-3 h-3 rounded-full bg-red-400"></div>
                                    <div class="w-3 h-3 rounded-full bg-yellow-400"></div>
                                    <div class="w-3 h-3 rounded-full bg-green-400"></div>
                                </div>
                                
                                <!-- Contenido Visual que Cambia (Icono Animado) -->
                                <transition
                                    enter-active-class="transition-all duration-500 ease-out"
                                    enter-from-class="opacity-0 scale-90"
                                    enter-to-class="opacity-100 scale-100"
                                    leave-active-class="transition-all duration-300 ease-in absolute inset-2 top-10"
                                    leave-from-class="opacity-100 scale-100"
                                    leave-to-class="opacity-0 scale-95"
                                    mode="out-in"
                                >
                                    <!-- REEMPLAZO: De <img> a Icono Animado -->
                                    <div 
                                        :key="activeFeatureTab" 
                                        class="w-full h-full flex items-center justify-center p-8 lg:p-16 text-[#D4AF37] bg-slate-50 dark:bg-slate-800"
                                    >
                                        <svg class="w-full h-full max-w-[250px] max-h-[250px] animate-pulse-slow" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" :d="activeFeature.icon" />
                                        </svg>
                                    </div>
                                </transition>
                            </div>
                        </div>

                    </div>
                </div>
            </section>

            <!-- SECCIÓN Testimonios ELIMINADA -->
            
            <!-- Contact Section (Sin Cambios) -->
            <section id="contact" class="py-20 md:py-28 bg-white dark:bg-slate-950">
                <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-16 items-center animate-section">
                        <!-- Columna de Información -->
                        <div>
                            <h2 class="text-3xl md:text-4xl font-bold text-slate-800 dark:text-white [text-wrap:balance]">Vea la plataforma en acción.</h2>
                            <p class="mt-4 text-lg text-slate-600 dark:text-slate-400 max-w-lg">
                                Solicite una demo personalizada y descubra por qué las firmas más eficientes nos eligen. Sin compromiso.
                            </p>
                            <div class="mt-8 space-y-4 text-slate-700 dark:text-slate-300">
                                <p class="flex items-center gap-3">
                                    <svg class="w-5 h-5 text-[#D4AF37]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" /></svg>
                                    <span>abogadosencolombiasas@gmail.com</span>
                                </p>
                                <p class="flex items-center gap-3">
                                    <svg class="w-5 h-5 text-[#D4AF37]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" /></svg>
                                    <span>Calle 44A # 68A - 106, Medellín, Colombia</span>
                                </p>
                            </div>
                        </div>

                        <!-- Columna de Formulario (Sin Cambios) -->
                        <div class="bg-slate-50 dark:bg-slate-900/50 p-8 md:p-10 rounded-xl shadow-2xl border border-slate-200 dark:border-slate-800">
                            <form v-if="!contactFormSubmitted" @submit.prevent="submitContactForm" class="space-y-8">
                                <!-- Estilo de Input Minimalista -->
                                <div class="relative">
                                    <input v-model="contactForm.name" type="text" name="name" id="name" required class="peer w-full bg-transparent border-0 border-b-2 border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white placeholder-transparent focus:border-[#D4AF37] focus:outline-none focus:ring-0" placeholder="Nombre Completo">
                                    <label for="name" class="absolute left-0 -top-3.5 text-slate-500 dark:text-slate-400 text-sm transition-all peer-placeholder-shown:text-base peer-placeholder-shown:text-slate-400 peer-placeholder-shown:top-2 peer-focus:-top-3.5 peer-focus:text-sm peer-focus:text-[#D4AF37]">
                                        Nombre Completo
                                    </label>
                                </div>
                                <div class="relative">
                                    <input v-model="contactForm.email" type="email" name="email" id="email" required class="peer w-full bg-transparent border-0 border-b-2 border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white placeholder-transparent focus:border-[#D4AF37] focus:outline-none focus:ring-0" placeholder="Correo Electrónico">
                                    <label for="email" class="absolute left-0 -top-3.5 text-slate-500 dark:text-slate-400 text-sm transition-all peer-placeholder-shown:text-base peer-placeholder-shown:text-slate-400 peer-placeholder-shown:top-2 peer-focus:-top-3.5 peer-focus:text-sm peer-focus:text-[#D4AF37]">
                                        Correo Electrónico
                                    </label>
                                </div>
                                <div class="relative">
                                    <textarea v-model="contactForm.message" name="message" id="message" rows="4" required class="peer w-full bg-transparent border-0 border-b-2 border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white placeholder-transparent focus:border-[#D4AF37] focus:outline-none focus:ring-0" placeholder="Mensaje"></textarea>
                                    <label for="message" class="absolute left-0 -top-3.5 text-slate-500 dark:text-slate-400 text-sm transition-all peer-placeholder-shown:text-base peer-placeholder-shown:text-slate-400 peer-placeholder-shown:top-2 peer-focus:-top-3.5 peer-focus:text-sm peer-focus:text-[#D4AF37]">
                                        Mensaje
                                    </label>
                                </div>
                                <div>
                                    <button type="submit" class="w-full px-8 py-3 text-lg font-semibold text-white bg-gradient-to-r from-[#0052CC] to-blue-700 rounded-lg shadow-lg hover:shadow-xl hover:shadow-blue-500/50 transition-all duration-300 transform hover:-translate-y-0.5">
                                        Solicitar Demo
                                    </button>
                                </div>
                            </form>
                            <!-- Mensaje de Éxito (Sin Cambios) -->
                            <div v-else class="text-center py-10">
                                <svg class="w-16 h-16 text-green-500 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <h3 class="text-xl font-semibold text-slate-800 dark:text-white">¡Mensaje Enviado!</h3>
                                <p class="text-slate-600 dark:text-slate-400 mt-2">Gracias por su interés. Nos pondremos en contacto con usted en breve.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

        </main>

        <!-- Footer (Sin Cambios) -->
        <footer class="bg-[#0A101F] text-blue-200 py-12">
            <div class="container mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 text-center">
                <p class="text-sm">
                    &copy; {{ new Date().getFullYear() }} Cobro Cartera. Todos los derechos reservados.
                </p>
                <p class="text-sm mt-2">
                    Desarrollado por <a href="https://centipy.com" target="_blank" class="font-semibold underline hover:text-[#D4AF37] transition-colors">Centipy.com</a>
                </p>
                <div class="mt-4 flex justify-center gap-6 text-sm">
                    <a @click.prevent="openPrivacyModal" href="#" class="hover:text-white transition-colors">Términos de Servicio</a>
                    <a @click.prevent="openPrivacyModal" href="#" class="hover:text-white transition-colors">Política de Privacidad</a>
                </div>
            </div>
        </footer>

        <!-- ========== BANNER DE COOKIES (Sin Cambios) ========== -->
        <transition
            enter-active-class="transition-all duration-500 ease-out"
            enter-from-class="transform translate-y-full opacity-0"
            enter-to-class="transform translate-y-0 opacity-100"
            leave-active-class="transition-all duration-300 ease-in"
            leave-from-class="transform translate-y-0 opacity-100"
            leave-to-class="transform translate-y-full opacity-0"
        >
            <div
                v-if="showCookieBanner"
                class="fixed bottom-0 left-0 right-0 z-50 p-4"
                role="dialog"
                aria-live="polite"
                aria-label="Banner de cookies"
            >
                <div class="max-w-3xl mx-auto bg-slate-800/95 dark:bg-slate-900/95 backdrop-blur-md text-white rounded-lg shadow-2xl p-5 md:flex md:items-center md:justify-between md:gap-6">
                    <div>
                        <h3 class="font-semibold text-lg">Este sitio web utiliza cookies</h3>
                        <p class="text-sm text-slate-300 mt-1">
                            Usamos cookies para analizar el tráfico y mejorar tu experiencia. Al continuar, aceptas nuestro uso de cookies.
                            <a @click.prevent="openPrivacyModal" href="#" class="underline hover:text-[#D4AF37]">Leer más aquí</a>.
                        </p>
                    </div>
                    <button
                        @click="acceptCookies"
                        type="button"
                        class="mt-4 md:mt-0 w-full md:w-auto flex-shrink-0 px-6 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-[#0052CC] to-blue-700 rounded-lg shadow-md hover:shadow-lg transition-all"
                        aria-label="Aceptar cookies"
                    >
                        Entendido, Aceptar
                    </button>
                </div>
            </div>
        </transition>

        <!-- ========== MODAL DE PRIVACIDAD (CORREGIDO) ========== -->
        <transition
            enter-active-class="transition-opacity duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <!-- 
                LA CORRECCIÓN ESTÁ AQUÍ:
                Cambié "vD-if" por "v-if"
            -->
            <div v-if="showPrivacyModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
                <!-- Overlay -->
                <div @click="closePrivacyModal" class="absolute inset-0 bg-black/70 backdrop-blur-sm"></div>
                
                <!-- Contenido del Modal -->
                <div class="relative z-10 w-full max-w-3xl bg-white dark:bg-slate-900 rounded-lg shadow-2xl max-h-[85vh] flex flex-col">
                    <header class="flex items-center justify-between p-6 border-b border-slate-200 dark:border-slate-700">
                        <h2 class="text-xl font-semibold text-slate-800 dark:text-white">Política de Privacidad y Términos</h2>
                        <button @click="closePrivacyModal" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors">
                            <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </header>
                    
                    <div class="p-8 overflow-y-auto space-y-6 text-slate-600 dark:text-slate-400">
                        <h3 class="text-lg font-semibold text-slate-800 dark:text-white">1. Términos y Condiciones</h3>
                        <p>Al acceder y utilizar la plataforma <strong>Cobro Cartera</strong>, usted acepta estar sujeto a los siguientes términos y condiciones. El uso de esta plataforma está restringido a usuarios autorizados (abogados y personal de cooperativas) para la gestión de cartera legal.</p>
                        <p>Usted es responsable de mantener la confidencialidad de su cuenta y contraseña. <strong>Abogados en Colombia S.A.S.</strong> no se hace responsable de ningún uso no autorizado de su cuenta.</p>

                        <h3 class="text-lg font-semibold text-slate-800 dark:text-white">2. Política de Privacidad y Tratamiento de Datos</h3>
                        <p>De conformidad con la Ley 1581 de 2012, <strong>Abogados en Colombia S.A.S.</strong> (NIT [901.866.706-6]) actúa como Responsable del Tratamiento de sus datos personales (nombre, correo, IP) para fines de autenticación, seguridad y prestación del servicio.</p>
                        <p>Los datos sensibles y la información contenida en los expedientes legales son tratados con la máxima confidencialidad, amparados por el secreto profesional, y <strong>Abogados en Colombia S.A.S.</strong> actúa como Encargado del Tratamiento, siguiendo las instrucciones de la cooperativa o firma legal.</p>
                        
                        <h3 class="text-lg font-semibold text-slate-800 dark:text-white">3. Uso de Cookies</h3>
                        <p>Utilizamos cookies estrictamente necesarias para el funcionamiento de la plataforma (ej. mantener su sesión activa). También utilizamos cookies analíticas (ej. Google Analytics) para entender cómo se usa nuestro sitio y mejorar la experiencia. Al hacer clic en "Aceptar" en el banner, usted consiente el uso de estas cookies analíticas.</p>
                        <p>Puede retirar su consentimiento en cualquier momento borrando las cookies de su navegador.</p>
                    </div>

                    <footer class="p-6 border-t border-slate-200 dark:border-slate-700 text-right">
                        <button
                            @click="closePrivacyModal"
                            class="px-6 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-[#0052CC] to-blue-700 rounded-lg shadow-md hover:shadow-lg transition-all"
                        >
                            Cerrar
                        </button>
                    </footer>
                </div>
            </div>
        </transition>

    </div>
</template>

<style>
/* --- ESTILO PARA SCROLL SUAVE --- */
html {
    scroll-behavior: smooth;
}

/* --- ESTILOS DE ANIMACIÓN DE ENTRADA --- */
@keyframes fade-in-up {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in-up {
    animation: fade-in-up 1s ease-out forwards;
}

/* --- NUEVA ANIMACIÓN PARA ICONOS --- */
@keyframes pulse-slow {
    0%, 100% { opacity: 0.7; transform: scale(0.98); }
    50% { opacity: 1; transform: scale(1.02); }
}
.animate-pulse-slow {
    animation: pulse-slow 4s ease-in-out infinite;
}

/* --- ESTILOS DE ANIMACIÓN EN SCROLL --- */
.animate-section {
    opacity: 0;
    transform: translateY(30px);
    transition: opacity 0.8s ease-out, transform 0.8s ease-out;
}
.animate-section-in {
    opacity: 1;
    transform: translateY(0);
}

/* --- ESTILO PARA FORMULARIO MINIMALISTA --- */
.peer:focus ~ label, .peer:not(:placeholder-shown) ~ label {
    @apply -top-3.5 text-sm;
}

.peer:focus ~ label {
    @apply text-[#D4AF37];
}
</style>