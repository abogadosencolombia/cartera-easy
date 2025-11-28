<script setup>
import { Link } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    resumen_financiero: Object,
    contrato_id: Number,
    formatCurrency: Function,
});
</script>

<template>
    <div class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-gray-50 dark:bg-gray-900/50 p-4 rounded-lg text-center">
                <p class="text-sm text-gray-500">Monto Total (Contrato + Cargos)</p>
                <p class="text-2xl font-bold">{{ formatCurrency(resumen_financiero.monto_total) }}</p>
            </div>
            <div class="bg-green-50 dark:bg-green-900/50 p-4 rounded-lg text-center">
                <p class="text-sm text-green-700 dark:text-green-300">Total Pagado</p>
                <p class="text-2xl font-bold text-green-800 dark:text-green-200">
                    {{ formatCurrency(resumen_financiero.total_pagado) }}
                </p>
            </div>
            <div class="bg-yellow-50 dark:bg-yellow-900/50 p-4 rounded-lg text-center">
                <p class="text-sm text-yellow-700 dark:text-yellow-300">Saldo Pendiente</p>
                <p class="text-2xl font-bold text-yellow-800 dark:text-yellow-200">
                    {{ formatCurrency(resumen_financiero.saldo_pendiente) }}
                </p>
            </div>
        </div>

        <div class="bg-gray-50 dark:bg-gray-900/50 p-6 rounded-lg text-center">
            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">Gestión Financiera</h3>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                Toda la gestión de pagos, cuotas y cargos se maneja directamente en el contrato.
            </p>
            
            <Link
                v-if="contrato_id"
                :href="route('honorarios.contratos.show', contrato_id)"
                class="mt-4 inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
            >
                Ir al Contrato #{{ contrato_id }}
            </Link>
            
            <p v-else class="mt-4 text-sm text-gray-500">
                Aún no se ha generado un contrato para este caso.
            </p>
        </div>
    </div>
</template>