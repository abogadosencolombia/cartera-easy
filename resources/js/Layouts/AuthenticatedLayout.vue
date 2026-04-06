<script setup>
/**
 * AuthenticatedLayout.vue
 * Layout principal corregido para mostrar notificaciones.
 */

import { ref, computed, onMounted } from "vue";
import { Link, usePage, router } from "@inertiajs/vue3";
import { initPush } from "@/push";

import ApplicationLogo from "@/Components/ApplicationLogo.vue";
import Dropdown from "@/Components/Dropdown.vue";
import DropdownLink from "@/Components/DropdownLink.vue";
import NavLink from "@/Components/NavLink.vue";
import ResponsiveNavLink from "@/Components/ResponsiveNavLink.vue";
import ToastList from "@/Components/ToastList.vue";
import GestionDiariaPanel from "@/Components/GestionDiariaPanel.vue";
import WelcomeTour from "@/Components/WelcomeTour.vue";

import {
    ChartBarIcon,
    FolderIcon,
    BellIcon,
    UsersIcon,
    ScaleIcon,
    Cog6ToothIcon,
    DocumentTextIcon,
    ListBulletIcon,
    GlobeAltIcon,
    BanknotesIcon,
    ClipboardDocumentCheckIcon,
    ShieldCheckIcon,
    ExclamationTriangleIcon,
    BuildingOffice2Icon,
} from "@heroicons/vue/24/outline";

// 2. DEFINICIÓN DEL MENÚ ------------------------------------------------------
const NAV_ITEMS = [
    {
        type: "link",
        label: "Dashboard",
        href: route("dashboard"),
        active: "dashboard",
        icon: ChartBarIcon,
        roles: ["admin", "gestor", "abogado"],
    },
    {
        type: "link",
        label: "Gestión Diaria",
        href: "#",
        active: "gestion-diaria",
        icon: ClipboardDocumentCheckIcon,
        roles: ["admin", "gestor", "abogado"],
    },

    // --- GRUPO: CASOS ---
    {
        type: "dropdown",
        label: "Casos",
        icon: FolderIcon,
        active: ["casos.*", "procesos.*", "revision.*", "reportes.*"],
        roles: ["admin", "gestor", "abogado"],
        items: [
            {
                label: "Casos Cooperativas",
                href: route("casos.index"),
                active: "casos.*",
                roles: ["admin", "gestor", "abogado"],
            },
            {
                label: "Casos Abogados en Colombia",
                href: route("procesos.index"),
                active: "procesos.*",
                roles: ["admin", "gestor", "abogado"],
            },
            { type: "divider", roles: ["admin", "gestor", "abogado"] },
            {
                label: "Revisión Diaria",
                href: route("revision.index"),
                active: "revision.*",
                roles: ["admin", "gestor", "abogado"],
            },
            {
                label: "Reportes",
                href: route("reportes.index"),
                active: "reportes.*",
                roles: ["admin", "gestor", "abogado"],
            },
        ],
    },

    // --- GRUPO: DIRECTORIO ---
    {
        type: "dropdown",
        label: "Directorio",
        icon: UsersIcon,
        active: ["cooperativas.*", "personas.*", "admin.users.*", "juzgados.*"],
        roles: ["admin", "gestor", "abogado"],
        items: [
            {
                label: "Cooperativas",
                href: route("cooperativas.index"),
                active: "cooperativas.*",
                roles: ["admin", "gestor", "abogado"],
            },
            {
                label: "Personas",
                href: route("personas.index"),
                active: "personas.*",
                roles: ["admin", "gestor", "abogado"],
            },
            { type: "divider", roles: ["admin"] },
            {
                label: "Directorio de Juzgados",
                href: route("juzgados.index"),
                active: "juzgados.*",
                roles: ["admin"],
            },
            {
                label: "Usuarios del Sistema",
                href: route("admin.users.index"),
                active: "admin.users.*",
                roles: ["admin"],
            },
        ],
    },

    // --- GRUPO: ADMINISTRACIÓN ---
    {
        type: "dropdown",
        label: "Administración",
        icon: Cog6ToothIcon,
        active: [
            "gestion.honorarios.*",
            "honorarios.contratos.*",
            "admin.gestores.*",
            "admin.tareas.*",
            "plantillas.*",
            "requisitos.*",
            "admin.tasas.*",
            "admin.auditoria.*",
            "admin.reglas-alerta.*",
        ],
        roles: ["admin", "gestor", "abogado"],
        items: [
            {
                label: "Contratos",
                href: route("gestion.honorarios.index"),
                active: "gestion.honorarios.*",
                roles: ["admin", "gestor", "abogado"],
            },
            { type: "divider", roles: ["admin"] },
            {
                label: "Abogados/Gestores",
                href: route("admin.gestores.index"),
                active: "admin.gestores.*",
                roles: ["admin"],
            },
            {
                label: "Gestión de Tareas",
                href: route("admin.tareas.index"),
                active: "admin.tareas.*",
                roles: ["admin"],
            },
            { type: "divider", roles: ["admin"] },
            {
                label: "Plantillas de Documentos",
                href: route("plantillas.index"),
                active: "plantillas.*",
                roles: ["admin"],
            },
            {
                label: "Requisitos de Documentos",
                href: route("requisitos.index"),
                active: "requisitos.*",
                roles: ["admin"],
            },
            {
                label: "Tasas de Interés",
                href: route("admin.tasas.index"),
                active: "admin.tasas.*",
                roles: ["admin"],
            },
            { type: "divider", roles: ["admin"] },
            {
                label: "Reglas de Alerta",
                href: route("admin.reglas-alerta.index"),
                active: "admin.reglas-alerta.*",
                roles: ["admin"],
            },
            {
                label: "Auditoría Global",
                href: route("admin.auditoria.index"),
                active: "admin.auditoria.index",
                roles: ["admin"],
            },
        ],
    },

    // --- GRUPO: HERRAMIENTAS ---
    {
        type: "dropdown",
        label: "Herramientas",
        icon: GlobeAltIcon,
        active: [],
        roles: ["admin", "gestor", "abogado"],
        items: [
            {
                type: "external",
                label: "Consulta de procesos",
                href: "https://consultaprocesos.ramajudicial.gov.co/procesos/Index",
                roles: ["admin", "gestor", "abogado"],
            },
            {
                type: "external",
                label: "Procesos rama judicial",
                href: "https://procesos.ramajudicial.gov.co/procesoscs/ConsultaJusticias21.aspx?EntryId=1ND%2fT1QaEBFgDjwxVoCZ45pYS4g%3d",
                roles: ["admin", "gestor", "abogado"],
            },
            {
                type: "external",
                label: "MonoLegal",
                href: "https://nuevoexpedientedigital.monolegal.co/#/admin/default",
                roles: ["admin", "gestor", "abogado"],
            },
            {
                type: "external",
                label: "Publicaciones Procesales",
                href: "https://publicacionesprocesales.ramajudicial.gov.co/",
                roles: ["admin", "gestor", "abogado"],
            },
            {
                type: "external",
                label: "Tyba",
                href: "https://procesojudicial.ramajudicial.gov.co/Justicia21/Administracion/Ciudadanos/frmConsulta",
                roles: ["admin", "gestor", "abogado"],
            },
            {
                type: "external",
                label: "Asistente ChatGPT",
                href: "https://chatgpt.com/g/g-68360119b4d48191898d44cb97865146-abogado-civil-director-juridico-experto?model=gpt-5-pro",
                roles: ["admin", "gestor", "abogado"],
            },
            {
                type: "external",
                label: "Asistente Gemini",
                href: "https://gemini.google.com/",
                roles: ["admin", "gestor", "abogado"],
            },
            {
                type: "external",
                label: "Asistente Claude",
                href: "https://claude.ai/",
                recommendation: "Se recomienda la app de escritorio",
                roles: ["admin", "gestor", "abogado"],
            },
        ],
    },
];

// 3. ESTADO Y DATOS -----------------------------------------------------------
const page = usePage();
const showingNavigationDropdown = ref(false);
const showGestionPanel = ref(false);
const currentUser = computed(
    () => page.props.auth?.user ?? { name: "Usuario", email: "" },
);
const userRole = computed(
    () =>
        page.props.auth?.user?.tipo_usuario ??
        page.props.auth?.user?.role ??
        "guest",
);

// LÓGICA CORREGIDA: Acceso seguro a las props de Inertia para el contador
const unreadCount = computed(() =>
    Number(page.props.auth?.unreadNotifications ?? 0),
);

// 4. LÓGICA COMPUTADA ---------------------------------------------------------
const visibleMenu = computed(() => {
    const role = userRole.value;
    // Aseguramos que el ID sea un número para la comparación estricta
    const userId = Number(page.props.auth?.user?.id || 0);
    const isUser28 = userId === 28;

    return NAV_ITEMS.map((item) => {
        // Clonamos superficialmente para no mutar el array original
        const newItem = { ...item };

        // --- 1. Verificación del Padre (Grupo) ---
        const hasRole = newItem.roles.includes(role);

        // Verificar si este grupo contiene la sección de juzgados
        const containsJuzgados = Array.isArray(newItem.active)
            ? newItem.active.includes("juzgados.*")
            : newItem.active === "juzgados.*";

        // Permitir acceso si tiene el rol O si es el usuario 28 y el grupo contiene juzgados
        if (!hasRole && !(isUser28 && containsJuzgados)) {
            return null;
        }

        // --- 2. Verificación de Hijos (Dropdown) ---
        if (newItem.type === "dropdown" && Array.isArray(newItem.items)) {
            newItem.items = newItem.items.filter((sub) => {
                // Regla A: El usuario tiene el rol requerido para este sub-item
                const subHasRole = sub.roles ? sub.roles.includes(role) : true;

                // Regla B: Es el usuario 28 y es el item de juzgados
                const isJuzgadoItem = sub.active === "juzgados.*";

                if (isUser28 && isJuzgadoItem) {
                    return true;
                }

                return subHasRole;
            });

            // Si se quedó sin items hijos visibles, ocultamos el grupo completo
            if (newItem.items.length === 0) {
                return null;
            }
        }

        return newItem;
    }).filter(Boolean); // Eliminar los nulos del array final
});

// 5. HELPERS ------------------------------------------------------------------
const isRouteActive = (patterns) => {
    if (!Array.isArray(patterns)) patterns = [patterns];
    return patterns.some((p) => route().current(p));
};

// 6. CICLO DE VIDA ------------------------------------------------------------
onMounted(() => {
    initPush().catch(() => {});
});
</script>

<template>
    <a
        href="#main-content"
        class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:bg-white focus:text-black focus:px-3 focus:py-2 focus:rounded"
    >
        Saltar al contenido
    </a>

    <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
        <!-- NAVBAR PRINCIPAL -->
        <nav
            class="bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700"
        >
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <div class="shrink-0 flex items-center">
                            <Link :href="route('dashboard')">
                                <ApplicationLogo class="block h-9 w-auto" />
                            </Link>
                        </div>

                        <!-- Menú de Escritorio -->
                        <div
                            class="hidden space-x-4 sm:-my-px sm:ms-10 sm:flex"
                        >
                            <template
                                v-for="item in visibleMenu"
                                :key="item.label"
                            >
                                <!-- Opción Especial: Gestión Diaria abre el Panel Lateral -->
                                <button
                                    v-if="item.label === 'Gestión Diaria'"
                                    type="button"
                                    @click="showGestionPanel = true"
                                    class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 transition focus:outline-none"
                                >
                                    <div class="relative flex items-center">
                                        <component
                                            :is="item.icon"
                                            class="h-5 w-5 mr-1"
                                        />
                                        <span>{{ item.label }}</span>
                                        <span
                                            v-if="
                                                Number(
                                                    $page.props.auth
                                                        ?.pendingGestionDiaria ??
                                                        0,
                                                ) > 0
                                            "
                                            class="absolute -top-1 -right-2.5 inline-flex h-4 w-4 items-center justify-center rounded-full bg-red-600 text-[10px] font-black text-white border border-white"
                                        >
                                            {{
                                                $page.props.auth
                                                    .pendingGestionDiaria
                                            }}
                                        </span>
                                    </div>
                                </button>

                                <!-- Opción 2: Links comunes -->
                                <NavLink
                                    v-else-if="item.type === 'link'"
                                    :href="item.href"
                                    :active="isRouteActive(item.active)"
                                >
                                    <component
                                        :is="item.icon"
                                        class="h-5 w-5 mr-1 inline-block"
                                    />
                                    {{ item.label }}
                                </NavLink>

                                <!-- Opción 3: Notificaciones -->
                                <NavLink
                                    v-else-if="item.type === 'notification'"
                                    :href="item.href"
                                    :active="isRouteActive(item.active)"
                                >
                                    <div class="relative flex items-center">
                                        <component
                                            :is="item.icon"
                                            class="h-5 w-5 mr-1"
                                        />
                                        <span>{{ item.label }}</span>
                                        <span
                                            v-if="unreadCount > 0"
                                            class="absolute -top-1 -right-2.5 inline-flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-xs font-bold text-white"
                                        >
                                            {{ unreadCount }}
                                        </span>
                                    </div>
                                </NavLink>

                                <!-- Opción 4: Dropdowns -->
                                <div
                                    v-else-if="item.type === 'dropdown'"
                                    class="hidden sm:flex sm:items-center sm:ms-1"
                                >
                                    <Dropdown
                                        align="left"
                                        :width="
                                            item.label === 'Configuración'
                                                ? '64'
                                                : '56'
                                        "
                                    >
                                        <template #trigger>
                                            <button
                                                class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 focus:outline-none transition"
                                                :class="
                                                    isRouteActive(item.active)
                                                        ? 'border-indigo-400 dark:border-indigo-600 text-gray-900 dark:text-gray-100'
                                                        : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'
                                                "
                                            >
                                                <component
                                                    :is="item.icon"
                                                    class="h-5 w-5 mr-1"
                                                />
                                                {{ item.label }}
                                                <svg
                                                    class="ms-2 -me-0.5 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    fill="currentColor"
                                                    viewBox="0 0 20 20"
                                                >
                                                    <path
                                                        fill-rule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clip-rule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </template>

                                        <template #content>
                                            <template
                                                v-for="(
                                                    subItem, index
                                                ) in item.items"
                                                :key="index"
                                            >
                                                <div
                                                    v-if="
                                                        subItem.type ===
                                                        'divider'
                                                    "
                                                    class="border-t border-gray-200 dark:border-gray-600 my-1"
                                                ></div>
                                                <a
                                                    v-else-if="
                                                        subItem.type ===
                                                        'external'
                                                    "
                                                    :href="subItem.href"
                                                    target="_blank"
                                                    class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800"
                                                >
                                                    {{ subItem.label }}
                                                    <div
                                                        v-if="
                                                            subItem.recommendation
                                                        "
                                                        class="text-xs text-gray-500 dark:text-gray-400 mt-1 ps-6"
                                                    >
                                                        {{
                                                            subItem.recommendation
                                                        }}
                                                    </div>
                                                </a>
                                                <DropdownLink
                                                    v-else
                                                    :href="subItem.href"
                                                    :active="
                                                        isRouteActive(
                                                            subItem.active,
                                                        )
                                                    "
                                                >
                                                    {{ subItem.label }}
                                                </DropdownLink>
                                            </template>
                                        </template>
                                    </Dropdown>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Menú de Usuario (Escritorio) -->
                    <div class="hidden sm:flex sm:items-center sm:ms-6">
                        <Link
                            :href="route('notificaciones.index')"
                            class="relative p-2 mr-2 text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 focus:outline-none transition duration-150 ease-in-out"
                            title="Notificaciones"
                        >
                            <BellIcon class="h-6 w-6" />
                            <span
                                v-if="unreadCount > 0"
                                class="absolute top-1.5 right-1.5 flex h-3 w-3"
                            >
                                <span
                                    class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"
                                ></span>
                                <span
                                    class="relative inline-flex rounded-full h-3 w-3 bg-red-600"
                                ></span>
                            </span>
                            <span
                                v-if="unreadCount > 0"
                                class="absolute -top-1 -right-1 inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 py-0.5 text-[11px] font-black leading-none text-white transform translate-x-1/4 -translate-y-1/4 bg-red-600 rounded-full border-2 border-white dark:border-gray-800 shadow-sm"
                            >
                                {{ unreadCount > 99 ? "99+" : unreadCount }}
                            </span>
                        </Link>

                        <div class="ms-3 relative">
                            <Dropdown align="right" width="48">
                                <template #trigger>
                                    <button
                                        type="button"
                                        class="inline-flex items-center px-3 py-2 text-sm leading-4 rounded-md text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300"
                                    >
                                        {{ currentUser.name }}
                                        <svg
                                            class="ms-2 -me-0.5 h-4 w-4"
                                            xmlns="http://www.w3.org/2000/svg"
                                            fill="currentColor"
                                            viewBox="0 0 20 20"
                                        >
                                            <path
                                                fill-rule="evenodd"
                                                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                clip-rule="evenodd"
                                            />
                                        </svg>
                                    </button>
                                </template>
                                <template #content>
                                    <DropdownLink :href="route('profile.edit')"
                                        >Perfil</DropdownLink
                                    >
                                    <DropdownLink
                                        :href="route('logout')"
                                        method="post"
                                        as="button"
                                        >Cerrar Sesión</DropdownLink
                                    >
                                </template>
                            </Dropdown>
                        </div>
                    </div>

                    <!-- Botón de Hamburguesa (Móvil) -->
                    <div class="-me-2 flex items-center sm:hidden gap-2">
                        <!-- Campana en Móvil -->
                        <Link
                            :href="route('notificaciones.index')"
                            class="relative p-2 text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 focus:outline-none transition duration-150 ease-in-out"
                            title="Notificaciones"
                        >
                            <BellIcon class="h-6 w-6" />
                            <span
                                v-if="unreadCount > 0"
                                class="absolute top-1.5 right-1.5 flex h-3 w-3"
                            >
                                <span
                                    class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"
                                ></span>
                                <span
                                    class="relative inline-flex rounded-full h-3 w-3 bg-red-600"
                                ></span>
                            </span>
                            <span
                                v-if="unreadCount > 0"
                                class="absolute -top-0 -right-0 inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-bold leading-none text-white bg-red-600 rounded-full border border-white dark:border-gray-800"
                            >
                                {{ unreadCount > 99 ? "99+" : unreadCount }}
                            </span>
                        </Link>

                        <button
                            @click="
                                showingNavigationDropdown =
                                    !showingNavigationDropdown
                            "
                            class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 dark:text-gray-500 hover:text-gray-500 dark:hover:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-900 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-900 focus:text-gray-500 dark:focus:text-gray-400 transition duration-150 ease-in-out"
                        >
                            <svg
                                class="h-6 w-6"
                                stroke="currentColor"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    :class="{
                                        hidden: showingNavigationDropdown,
                                        'inline-flex':
                                            !showingNavigationDropdown,
                                    }"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M4 6h16M4 12h16M4 18h16"
                                />
                                <path
                                    :class="{
                                        hidden: !showingNavigationDropdown,
                                        'inline-flex':
                                            showingNavigationDropdown,
                                    }"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Menú Desplegable (Móvil) -->
            <div
                :class="{
                    block: showingNavigationDropdown,
                    hidden: !showingNavigationDropdown,
                }"
                class="sm:hidden"
            >
                <div class="pt-2 pb-3 space-y-1">
                    <template v-for="item in visibleMenu" :key="item.label">
                        <!-- Móvil: Gestión Diaria -->
                        <button
                            v-if="item.label === 'Gestión Diaria'"
                            @click="
                                showGestionPanel = true;
                                showingNavigationDropdown = false;
                            "
                            class="flex w-full items-center pl-3 pr-4 py-2 border-l-4 border-transparent text-left text-base font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-50 hover:border-gray-300 transition duration-150 ease-in-out"
                        >
                            <component
                                :is="item.icon"
                                class="h-5 w-5 mr-2 inline-block"
                            />
                            {{ item.label }}
                        </button>

                        <ResponsiveNavLink
                            v-else-if="
                                item.type === 'link' ||
                                item.type === 'notification'
                            "
                            :href="item.href"
                            :active="isRouteActive(item.active)"
                        >
                            <component
                                :is="item.icon"
                                class="h-5 w-5 mr-2 inline-block"
                            />
                            {{ item.label }}
                        </ResponsiveNavLink>
                        <template v-if="item.type === 'dropdown'">
                            <div class="px-4 pt-2 pb-1">
                                <span
                                    class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase"
                                    >{{ item.label }}</span
                                >
                            </div>
                            <ResponsiveNavLink
                                v-for="subItem in item.items"
                                :key="subItem.label"
                                :href="subItem.href"
                                :active="isRouteActive(subItem.active)"
                            >
                                <span class="ms-7">{{ subItem.label }}</span>
                            </ResponsiveNavLink>
                        </template>
                    </template>
                </div>

                <div
                    class="pt-4 pb-1 border-t border-gray-200 dark:border-gray-600"
                >
                    <div class="px-4">
                        <div
                            class="font-medium text-base text-gray-800 dark:text-gray-200"
                        >
                            {{ currentUser.name }}
                        </div>
                        <div class="font-medium text-sm text-gray-500">
                            {{ currentUser.email }}
                        </div>
                    </div>

                    <div class="mt-3 space-y-1">
                        <ResponsiveNavLink :href="route('profile.edit')">
                            Perfil
                        </ResponsiveNavLink>
                        <ResponsiveNavLink
                            :href="route('logout')"
                            method="post"
                            as="button"
                        >
                            Cerrar Sesión
                        </ResponsiveNavLink>
                    </div>
                </div>
            </div>
        </nav>

        <header class="bg-white dark:bg-gray-800 shadow" v-if="$slots.header">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                <slot name="header" />
            </div>
        </header>

        <main id="main-content">
            <slot />
        </main>

        <GestionDiariaPanel
            :show="showGestionPanel"
            @close="showGestionPanel = false"
        />
        <ToastList />
        <WelcomeTour />
    </div>
</template>
