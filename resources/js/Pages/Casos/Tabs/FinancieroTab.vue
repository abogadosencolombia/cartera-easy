<script setup>
import { Link } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { 
    BanknotesIcon, 
    ArrowPathIcon, 
    CheckBadgeIcon, 
    ExclamationTriangleIcon,
    ArrowTopRightOnSquareIcon,
    CalculatorIcon,
    ShieldCheckIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    caso: Object,
    resumen_financiero: {
        type: Object,
        default: () => ({
            monto_total: 0,
            total_pagado: 0,
            saldo_pendiente: 0
        })
    },
    contrato_id: Number,
    formatCurrency: Function,
});
</script>

<template>
    <div class="space-y-10 animate-in fade-in duration-500">

        <!-- KPIs FINANCIEROS PRINCIPALES -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white dark:bg-gray-800 p-8 rounded-3xl border border-gray-100 dark:border-gray-700 shadow-sm relative overflow-hidden group">
                <div class="absolute -right-4 -top-4 opacity-5 group-hover:scale-110 transition-transform">
                    <CalculatorIcon class="w-24 h-24 text-gray-600" />
                </div>
                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">Total Obligación</p>
                <p class="text-2xl font-black text-gray-900 dark:text-white">{{ formatCurrency(resumen_financiero?.monto_total || 0) }}</p>
                <p class="mt-2 text-[10px] text-gray-500 font-medium italic">Capital + Cargos acumulados</p>
            </div>

            <div class="bg-green-50 dark:bg-green-900/10 p-8 rounded-3xl border border-green-100 dark:border-green-900/30 shadow-sm relative overflow-hidden group">
                <div class="absolute -right-4 -top-4 opacity-10 group-hover:scale-110 transition-transform">
                    <CheckBadgeIcon class="w-24 h-24 text-green-600" />
                </div>
                <p class="text-[10px] font-black text-green-600 dark:text-green-400 uppercase tracking-widest mb-2">Total Recaudado</p>
                <p class="text-2xl font-black text-green-700 dark:text-green-300">{{ formatCurrency(resumen_financiero?.total_pagado || 0) }}</p>
                <div class="mt-2 flex items-center gap-1.5">
                    <div class="flex-1 h-1.5 bg-green-200 dark:bg-green-900 rounded-full overflow-hidden">
                        <div 
                            class="h-full bg-green-500" 
                            :style="{ width: (resumen_financiero?.monto_total > 0 ? (resumen_financiero.total_pagado / resumen_financiero.monto_total * 100) : 0) + '%' }"
                        ></div>
                    </div>
                    <span class="text-[10px] font-black text-green-600">
                        {{ resumen_financiero?.monto_total > 0 ? Math.round(resumen_financiero.total_pagado / resumen_financiero.monto_total * 100) : 0 }}%
                    </span>
                </div>
            </div>

            <div class="bg-red-50 dark:bg-red-900/10 p-8 rounded-3xl border border-red-100 dark:border-red-900/30 shadow-sm relative overflow-hidden group">
                <div class="absolute -right-4 -top-4 opacity-10 group-hover:scale-110 transition-transform">
                    <ExclamationTriangleIcon class="w-24 h-24 text-red-600" />
                </div>
                <p class="text-[10px] font-black text-red-600 dark:text-red-400 uppercase tracking-widest mb-2">Saldo en Riesgo</p>
                <p class="text-2xl font-black text-red-700 dark:text-red-300">{{ formatCurrency(resumen_financiero?.saldo_pendiente || 0) }}</p>
                <p class="mt-2 text-[10px] text-red-500 font-bold uppercase tracking-tighter">Pendiente por cobro judicial</p>
            </div>
        </div>

        <!-- SECCIÓN DE GESTIÓN -->
        <div class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-100 dark:border-gray-700 overflow-hidden shadow-xl">
            <div class="flex flex-col lg:flex-row">
                <!-- Ilustración/Info Izquierda -->
                <div class="lg:w-1/3 bg-indigo-600 p-10 text-white flex flex-col justify-between relative overflow-hidden">
                    <div class="absolute -bottom-10 -left-10 opacity-10">
                        <BanknotesIcon class="w-64 h-64" />
                    </div>
                    <div class="relative z-10">
                        <h3 class="text-2xl font-black mb-4 leading-tight">Control de Honorarios y Pagos</h3>
                        <p class="text-indigo-100 text-sm leading-relaxed font-medium">
                            La gestión detallada de cuotas, intereses de mora y honorarios profesionales se centraliza en el módulo de contratos para garantizar la transparencia financiera.
                        </p>
                    </div>
                    <div class="relative z-10 mt-8">
                        <div class="flex items-center gap-3 p-3 bg-white/10 rounded-2xl border border-white/20 backdrop-blur-sm">
                            <div class="p-2 bg-white rounded-xl">
                                <ShieldCheckIcon class="w-5 h-5 text-indigo-600" />
                            </div>
                            <div>
                                <p class="text-[10px] font-black uppercase opacity-70">Estado Contable</p>
                                <p class="text-xs font-bold">{{ contrato_id ? 'VINCULADO A CONTRATO' : 'SIN CONTRATO ACTIVO' }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Acción Derecha -->
                <div class="lg:w-2/3 p-10 flex flex-col items-center justify-center text-center">
                    <div v-if="contrato_id" class="space-y-6 max-w-md">
                        <div class="p-4 bg-indigo-50 dark:bg-indigo-900/20 rounded-full inline-block">
                            <ArrowTopRightOnSquareIcon class="w-10 h-10 text-indigo-600" />
                        </div>
                        <h4 class="text-xl font-black text-gray-900 dark:text-white">Acceso al Detalle del Contrato</h4>
                        <p class="text-sm text-gray-500 font-medium">
                            Visualice el plan de pagos, los abonos registrados por el cliente y el histórico de cobros realizados por el despacho.
                        </p>
                        <Link
                            :href="route('honorarios.contratos.show', contrato_id)"
                            class="inline-flex items-center justify-center w-full px-8 py-4 bg-indigo-600 hover:bg-indigo-700 text-white rounded-2xl font-black text-sm uppercase tracking-widest transition-all shadow-xl shadow-indigo-100 dark:shadow-none"
                        >
                            Ver Estado de Cuenta #{{ contrato_id }}
                        </Link>
                    </div>

                    <div v-else class="space-y-6 max-w-md">
                        <div class="p-4 bg-amber-50 dark:bg-amber-900/20 rounded-full inline-block">
                            <ExclamationTriangleIcon class="w-10 h-10 text-amber-600" />
                        </div>
                        <h4 class="text-xl font-black text-gray-900 dark:text-white">Contrato Pendiente</h4>
                        <p class="text-sm text-gray-500 font-medium">
                            No se ha detectado un contrato de honorarios vinculado a este proceso. Debe generarlo para iniciar el seguimiento financiero.
                        </p>
                        <Link
                            :href="route('honorarios.contratos.create', { caso_id: caso?.id, monto: resumen_financiero?.saldo_pendiente || 0 })"
                            class="inline-flex items-center justify-center w-full px-8 py-4 bg-gray-900 dark:bg-white text-white dark:text-gray-900 rounded-2xl font-black text-sm uppercase tracking-widest transition-all shadow-xl"
                        >
                            Generar Contrato Ahora
                        </Link>
                    </div>
                </div>
            </div>
        </div>

    </div>
</template>