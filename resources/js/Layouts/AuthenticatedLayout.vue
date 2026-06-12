<script setup>
/**
 * AuthenticatedLayout.vue
 * Layout principal corregido para mostrar notificaciones.
 */

import { ref, computed, onMounted, onBeforeUnmount } from "vue";
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
import AlertasUrgentesOverlay from "@/Components/AlertasUrgentesOverlay.vue";

import {
    ChartBarIcon,
    FolderIcon,
    BellIcon,
    UsersIcon,
    ArrowTrendingUpIcon,
    Cog6ToothIcon,
    GlobeAltIcon,
    ClipboardDocumentCheckIcon,
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
        label: "Analítica",
        href: route("analytics.index"),
        active: "analytics.index",
        icon: ArrowTrendingUpIcon,
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
        active: ["casos.*", "procesos.*"],
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
            "requisitos.*",
            "admin.tasas.*",
            "admin.auditoria.*",
            "admin.jornadas.*",
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
                label: "Gestores del Sistema",
                href: route("admin.gestores.index"),
                active: "admin.gestores.*",
                roles: ["admin"],
            },
            {
                label: "Incidentes Jurídicos",
                href: route("admin.incidentes-juridicos.index"),
                active: "admin.incidentes-juridicos.*",
                roles: ["admin"],
            },
            {
                label: "Tareas del Sistema",
                href: route("admin.tareas.index"),
                active: "admin.tareas.*",
                roles: ["admin"],
            },
            { type: "divider", roles: ["admin"] },
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
            {
                label: "Jornadas de Usuario",
                href: route("admin.jornadas.index"),
                active: "admin.jornadas.*",
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
const idleTimeoutMs = computed(
    () => Number(page.props.session?.idleTimeoutMinutes ?? 60) * 60 * 1000,
);

const activityEvents = [
    "click",
    "keydown",
    "mousemove",
    "pointermove",
    "wheel",
    "scroll",
    "touchstart",
    "input",
    "focus",
];
let idleLogoutTimer = null;
let workSessionHeartbeatTimer = null;
let lastTrackedAt = Date.now();
let lastActivityAt = Date.now();
let pendingActiveMs = 0;
let pendingIdleMs = 0;
let hasPendingActivity = false;
const workSessionIdleThresholdMs = 5 * 60 * 1000;
const workSessionHeartbeatMs = 60 * 1000;

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

const clearIdleLogoutTimer = () => {
    if (idleLogoutTimer) {
        window.clearTimeout(idleLogoutTimer);
        idleLogoutTimer = null;
    }
};

const clearWorkSessionHeartbeatTimer = () => {
    if (workSessionHeartbeatTimer) {
        window.clearInterval(workSessionHeartbeatTimer);
        workSessionHeartbeatTimer = null;
    }
};

const accumulateWorkSessionDelta = () => {
    const now = Date.now();

    if (now <= lastTrackedAt) return;

    const idleStartsAt = lastActivityAt + workSessionIdleThresholdMs;

    if (now <= idleStartsAt) {
        pendingActiveMs += now - lastTrackedAt;
    } else if (lastTrackedAt >= idleStartsAt) {
        pendingIdleMs += now - lastTrackedAt;
    } else {
        pendingActiveMs += idleStartsAt - lastTrackedAt;
        pendingIdleMs += now - idleStartsAt;
    }

    lastTrackedAt = now;
};

const takePendingWorkSessionSeconds = () => {
    const activeSeconds = Math.floor(pendingActiveMs / 1000);
    const idleSeconds = Math.floor(pendingIdleMs / 1000);
    const activityDetected = hasPendingActivity;

    pendingActiveMs -= activeSeconds * 1000;
    pendingIdleMs -= idleSeconds * 1000;
    hasPendingActivity = false;

    return { activeSeconds, idleSeconds, activityDetected };
};

const restorePendingWorkSessionSeconds = (activeSeconds, idleSeconds, activityDetected = false) => {
    pendingActiveMs += activeSeconds * 1000;
    pendingIdleMs += idleSeconds * 1000;
    hasPendingActivity = hasPendingActivity || activityDetected;
};

const csrfToken = () =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ||
    "";

const hasHeartbeatRoute = () => {
    try {
        return typeof route === "function" &&
            (typeof route().has !== "function" || route().has("jornadas.heartbeat"));
    } catch (error) {
        return false;
    }
};

const sendWorkSessionHeartbeat = async ({ beacon = false } = {}) => {
    if (!hasHeartbeatRoute()) return;

    accumulateWorkSessionDelta();

    const { activeSeconds, idleSeconds, activityDetected } = takePendingWorkSessionSeconds();

    if (activeSeconds === 0 && idleSeconds === 0 && !activityDetected) return;

    if (beacon && navigator.sendBeacon) {
        const payload = new FormData();
        payload.append("_token", csrfToken());
        payload.append("active_seconds", String(activeSeconds));
        payload.append("idle_seconds", String(idleSeconds));
        payload.append("activity_detected", activityDetected ? "1" : "0");

        const queued = navigator.sendBeacon(route("jornadas.heartbeat"), payload);

        if (!queued) {
            restorePendingWorkSessionSeconds(activeSeconds, idleSeconds, activityDetected);
        }

        return;
    }

    if (!window.axios) {
        restorePendingWorkSessionSeconds(activeSeconds, idleSeconds, activityDetected);
        return;
    }

    try {
        await window.axios.post(route("jornadas.heartbeat"), {
            active_seconds: activeSeconds,
            idle_seconds: idleSeconds,
            activity_detected: activityDetected,
        });
    } catch (error) {
        restorePendingWorkSessionSeconds(activeSeconds, idleSeconds, activityDetected);
    }
};

const markUserActivity = () => {
    const now = Date.now();
    const wasIdle = now - lastActivityAt >= workSessionIdleThresholdMs;

    accumulateWorkSessionDelta();
    lastActivityAt = now;
    hasPendingActivity = true;
    resetIdleLogoutTimer();

    if (wasIdle) {
        sendWorkSessionHeartbeat();
    }
};

const closeSession = async (reason = "manual") => {
    await sendWorkSessionHeartbeat();

    router.post(
        route("logout"),
        { reason },
        {
            preserveScroll: true,
            onError: () => {
                window.location.href = route("login");
            },
        },
    );
};

const finishTurnAndLogout = () => {
    closeSession("finish_turn");
};

const logoutForInactivity = () => {
    closeSession("inactivity");
};

const resetIdleLogoutTimer = () => {
    clearIdleLogoutTimer();
    idleLogoutTimer = window.setTimeout(
        logoutForInactivity,
        idleTimeoutMs.value,
    );
};

const openGestionDiariaPanel = () => {
    showGestionPanel.value = true;
};

const flushWorkSessionWithBeacon = () => {
    sendWorkSessionHeartbeat({ beacon: true });
};

// 6. CICLO DE VIDA ------------------------------------------------------------
onMounted(() => {
    initPush().catch(() => {});
    window.addEventListener("open-gestion-diaria", openGestionDiariaPanel);
    window.addEventListener("pagehide", flushWorkSessionWithBeacon);
    activityEvents.forEach((eventName) => {
        window.addEventListener(eventName, markUserActivity, {
            passive: true,
        });
    });
    resetIdleLogoutTimer();
    workSessionHeartbeatTimer = window.setInterval(
        () => sendWorkSessionHeartbeat(),
        workSessionHeartbeatMs,
    );
});

onBeforeUnmount(() => {
    clearIdleLogoutTimer();
    clearWorkSessionHeartbeatTimer();
    flushWorkSessionWithBeacon();
    window.removeEventListener("open-gestion-diaria", openGestionDiariaPanel);
    window.removeEventListener("pagehide", flushWorkSessionWithBeacon);
    activityEvents.forEach((eventName) => {
        window.removeEventListener(eventName, markUserActivity);
    });
});
</script>

<template>
    <a
        href="#main-content"
        class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:bg-white focus:text-black focus:px-3 focus:py-2 focus:rounded"
    >
        Saltar al contenido
    </a>

    <div class="min-h-screen min-w-0 bg-gray-100 dark:bg-gray-900">
        <!-- NAVBAR PRINCIPAL - Añadimos z-[100] para que los dropdowns floten sobre todo -->
        <nav
            class="bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700 relative z-[100]"
        >
            <div
                class="mx-auto max-w-screen-2xl px-3 sm:px-4 lg:px-6 xl:px-8 overflow-visible"
            >
                <div class="flex h-16 items-center justify-between gap-3 overflow-visible">
                    <div class="flex min-w-0 flex-1 items-center overflow-visible">
                        <div class="shrink-0 flex items-center">
                            <Link :href="route('dashboard')">
                                <ApplicationLogo class="block h-9 w-auto" />
                            </Link>
                        </div>

                        <!-- Menú de Escritorio -->
                        <div
                            class="hidden min-w-0 flex-1 items-center gap-1 xl:ms-6 xl:flex xl:gap-2"
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
                                    class="inline-flex h-16 shrink-0 items-center border-b-2 border-transparent px-2 text-xs font-semibold leading-5 text-gray-500 transition hover:text-gray-700 hover:border-gray-300 focus:outline-none xl:text-sm"
                                >
                                    <div class="relative flex items-center">
                                        <component
                                            :is="item.icon"
                                            class="mr-1 h-4 w-4 xl:h-5 xl:w-5"
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
                                    class="hidden shrink-0 xl:flex xl:items-center"
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
                                                class="inline-flex h-16 shrink-0 items-center border-b-2 px-2 text-xs font-semibold leading-5 transition focus:outline-none xl:text-sm"
                                                :class="
                                                    isRouteActive(item.active)
                                                        ? 'border-indigo-400 dark:border-indigo-600 text-gray-900 dark:text-gray-100'
                                                        : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'
                                                "
                                            >
                                                <component
                                                    :is="item.icon"
                                                    class="mr-1 h-4 w-4 xl:h-5 xl:w-5"
                                                />
                                                {{ item.label }}
                                                <svg
                                                    class="ms-1 h-4 w-4"
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
                    <div class="hidden shrink-0 items-center xl:ms-3 xl:flex">
                        <Link
                            :href="route('notificaciones.index')"
                            class="relative mr-1 p-2 text-gray-400 transition duration-150 ease-in-out hover:text-gray-500 focus:outline-none dark:hover:text-gray-300"
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
                                        class="inline-flex max-w-[11rem] items-center rounded-md bg-white px-3 py-2 text-sm leading-4 text-gray-500 hover:text-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:text-gray-300 xl:max-w-[14rem]"
                                    >
                                        <span class="truncate">{{
                                            currentUser.name
                                        }}</span>
                                        <svg
                                            class="ms-2 h-4 w-4 shrink-0"
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
                                    <button
                                        type="button"
                                        @click="finishTurnAndLogout"
                                        class="block w-full px-4 py-2 text-start text-sm font-semibold leading-5 text-emerald-700 transition duration-150 ease-in-out hover:bg-emerald-50 focus:bg-emerald-50 focus:outline-none dark:text-emerald-300 dark:hover:bg-emerald-950/30"
                                    >
                                        Finalizar turno
                                    </button>
                                    <button
                                        type="button"
                                        @click="closeSession()"
                                        class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100 focus:bg-gray-100 focus:outline-none dark:text-gray-200 dark:hover:bg-gray-700"
                                    >
                                        Cerrar sesión
                                    </button>
                                </template>
                            </Dropdown>
                        </div>
                    </div>

                    <!-- Botón de Hamburguesa (Móvil) -->
                    <div class="-me-2 flex items-center gap-2 xl:hidden">
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
                class="border-t border-gray-100 bg-white dark:border-gray-700 dark:bg-gray-800 xl:hidden"
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
                        <template v-else-if="item.type === 'dropdown'">
                            <div class="px-4 pb-1 pt-4">
                                <span
                                    class="text-xs font-black uppercase tracking-widest text-gray-400 dark:text-gray-500"
                                    >{{ item.label }}</span
                                >
                            </div>
                            <template
                                v-for="(subItem, index) in item.items"
                                :key="`${item.label}-${index}`"
                            >
                                <div
                                    v-if="subItem.type === 'divider'"
                                    class="mx-4 my-1 border-t border-gray-100 dark:border-gray-700"
                                ></div>
                                <a
                                    v-else-if="subItem.type === 'external'"
                                    :href="subItem.href"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="block w-full border-l-4 border-transparent py-2 ps-10 pe-4 text-start text-base font-medium text-gray-600 transition duration-150 ease-in-out hover:border-gray-300 hover:bg-gray-50 hover:text-gray-800 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                                >
                                    <span class="block truncate">{{
                                        subItem.label
                                    }}</span>
                                    <span
                                        v-if="subItem.recommendation"
                                        class="mt-0.5 block truncate text-xs font-semibold text-gray-400 dark:text-gray-500"
                                    >
                                        {{ subItem.recommendation }}
                                    </span>
                                </a>
                                <ResponsiveNavLink
                                    v-else
                                    :href="subItem.href"
                                    :active="isRouteActive(subItem.active)"
                                >
                                    <span class="ms-7 block truncate">{{
                                        subItem.label
                                    }}</span>
                                </ResponsiveNavLink>
                            </template>
                        </template>
                    </template>
                </div>

                <div
                    class="pt-4 pb-1 border-t border-gray-200 dark:border-gray-600"
                >
                    <div class="px-4">
                        <div
                            class="truncate text-base font-medium text-gray-800 dark:text-gray-200"
                        >
                            {{ currentUser.name }}
                        </div>
                        <div class="truncate text-sm font-medium text-gray-500">
                            {{ currentUser.email }}
                        </div>
                    </div>

                    <div class="mt-3 space-y-1">
                        <ResponsiveNavLink :href="route('profile.edit')">
                            Perfil
                        </ResponsiveNavLink>
                        <button
                            type="button"
                            @click="finishTurnAndLogout"
                            class="block w-full border-l-4 border-transparent py-2 ps-3 pe-4 text-start text-base font-semibold text-emerald-700 transition duration-150 ease-in-out hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-800 focus:outline-none focus:ring-1 focus:ring-emerald-500 dark:text-emerald-300 dark:hover:border-emerald-700 dark:hover:bg-emerald-950/30"
                        >
                            Finalizar turno
                        </button>
                        <button
                            type="button"
                            @click="closeSession()"
                            class="block w-full border-l-4 border-transparent py-2 ps-3 pe-4 text-start text-base font-medium text-gray-600 transition duration-150 ease-in-out hover:border-gray-300 hover:bg-gray-50 hover:text-gray-800 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                        >
                            Cerrar sesión
                        </button>
                    </div>
                </div>
            </div>
        </nav>

        <header class="bg-white dark:bg-gray-800 shadow" v-if="$slots.header">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                <slot name="header" />
            </div>
        </header>

        <main id="main-content" class="min-w-0">
            <slot />
        </main>

        <GestionDiariaPanel
            :show="showGestionPanel"
            @close="showGestionPanel = false"
        />
        <ToastList />
        <WelcomeTour />
        <!-- Overlay de Alertas Urgentes al Iniciar Sesión -->
        <AlertasUrgentesOverlay />
    </div>
</template>
