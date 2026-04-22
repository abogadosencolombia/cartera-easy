<script setup>
import { Link } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { 
    BanknotesIcon, 
    CheckBadgeIcon, 
    ExclamationTriangleIcon,
    ArrowTopRightOnSquareIcon,
    CalculatorIcon,
    ShieldCheckIcon,
    WalletIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    caso: Object,
    resumen_financiero: { type: Object, default: () => ({ monto_total: 0, total_pagado: 0, saldo_pendiente: 0 }) },
    contrato_id: Number,
    formatCurrency: Function,
});
</script>

<template>
    <div class="space-y-8 animate-in fade-in duration-500">

        <!-- KPIs COMPACTOS -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white dark:bg-gray-800 p-6 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm relative overflow-hidden group">
                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Total Obligación</p>
                <p class="text-2xl font-black text-gray-900 dark:text-white">{{ formatCurrency(resumen_financiero?.monto_total || 0) }}</p>
                <CalculatorIcon class="absolute -right-2 -bottom-2 w-16 h-16 text-gray-100 dark:text-gray-700/50 -rotate-12" />
            </div>

            <div class="bg-emerald-50/50 dark:bg-emerald-900/10 p-6 rounded-xl border border-emerald-100 dark:border-emerald-900/30 shadow-sm">
                <p class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-widest mb-2">Recaudo Total</p>
                <p class="text-2xl font-black text-emerald-700 dark:text-emerald-300">{{ formatCurrency(resumen_financiero?.total_pagado || 0) }}</p>
                <div class="mt-3 h-1.5 bg-emerald-200 dark:bg-emerald-900 rounded-full overflow-hidden">
                    <div class="h-full bg-emerald-500" :style="{ width: (resumen_financiero?.monto_total > 0 ? (resumen_financiero.total_pagado / resumen_financiero.monto_total * 100) : 0) + '%' }"></div>
                </div>
            </div>

            <div class="bg-rose-50/50 dark:bg-rose-900/10 p-6 rounded-xl border border-rose-100 dark:border-rose-900/30 shadow-sm">
                <p class="text-[10px] font-bold text-rose-600 dark:text-rose-400 uppercase tracking-widest mb-2">Saldo Pendiente</p>
                <p class="text-2xl font-black text-rose-700 dark:text-rose-300">{{ formatCurrency(resumen_financiero?.saldo_pendiente || 0) }}</p>
                <p class="mt-2 text-[9px] font-bold text-rose-400 uppercase">En gestión de cobro</p>
            </div>
        </div>

        <!-- GESTIÓN DE CONTRATO -->
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-sm">
            <div class="flex flex-col md:flex-row">
                <div class="md:w-1/3 bg-indigo-600 p-8 text-white relative overflow-hidden">
                    <WalletIcon class="absolute -right-4 -bottom-4 w-32 h-32 opacity-10" />
                    <h3 class="text-lg font-bold uppercase tracking-tight mb-2">Contrato Digital</h3>
                    <p class="text-xs text-indigo-100 leading-relaxed">Centralice honorarios, cuotas y abonos. Garantice la transparencia financiera del proceso.</p>
                    <div class="mt-6 inline-flex items-center gap-2 px-3 py-1 bg-white/10 rounded-lg text-[10px] font-bold uppercase">
                        <ShieldCheckIcon class="w-4 h-4" /> {{ contrato_id ? 'VINCULADO' : 'PENDIENTE' }}
                    </div>
                </div>

                <div class="md:w-2/3 p-8 flex flex-col items-center justify-center text-center">
                    <div v-if="contrato_id" class="space-y-4">
                        <h4 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-widest">Estado de Cuenta Activo</h4>
                        <p class="text-xs text-gray-500 max-w-xs mx-auto">Consulte el desglose de pagos y honorarios profesionales asociados a este expediente.</p>
                        <Link :href="route('honorarios.contratos.show', contrato_id)" class="inline-flex items-center px-6 py-2.5 bg-indigo-600 text-white rounded-xl font-bold text-[10px] uppercase tracking-widest hover:bg-indigo-700 transition-all shadow-lg shadow-indigo-100 dark:shadow-none">
                            Ver Detalles del Contrato
                        </Link>
                    </div>
                    <div v-else class="space-y-4">
                        <ExclamationTriangleIcon class="w-10 h-10 text-amber-500 mx-auto" />
                        <h4 class="text-sm font-bold text-gray-900 dark:text-white uppercase tracking-widest">Sin Contrato Vinculado</h4>
                        <p class="text-xs text-gray-500 max-w-xs mx-auto">Debe generar un contrato para habilitar el registro de abonos y seguimiento de honorarios.</p>
                        <Link :href="route('honorarios.contratos.create', { caso_id: caso?.id, monto: resumen_financiero?.saldo_pendiente || 0 })" class="inline-flex items-center px-6 py-2.5 bg-gray-900 dark:bg-white text-white dark:text-gray-900 rounded-xl font-bold text-[10px] uppercase tracking-widest hover:bg-black transition-all">
                            Vincular Contrato Ahora
                        </Link>
                    </div>
                </div>
            </div>
        </div>

    </div>
</template>
