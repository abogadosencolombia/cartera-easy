<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Bar, Pie } from 'vue-chartjs';
import { Chart as ChartJS, Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, ArcElement } from 'chart.js';

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale, ArcElement);

const props = defineProps({
    kpis: Object,
    chartData: Object,
});

// --- Datos para el gráfico de barras (Incidentes por Estado) ---
const incidentesChartData = {
    labels: Object.keys(props.chartData.incidentesPorEstado).map(s => s.charAt(0).toUpperCase() + s.slice(1).replace('_', ' ')),
    datasets: [{
        label: 'Total de Incidentes',
        backgroundColor: ['#FBBF24', '#60A5FA', '#34D399', '#9CA3AF'],
        data: Object.values(props.chartData.incidentesPorEstado),
    }],
};

// --- Datos para el gráfico de pastel (Decisiones por Resultado) ---
const decisionesChartData = {
    labels: Object.keys(props.chartData.decisionesPorResultado).map(s => s.charAt(0).toUpperCase() + s.slice(1).replace('_', ' ')),
    datasets: [{
        backgroundColor: ['#10B981', '#F59E0B', '#EF4444', '#6B7280'],
        data: Object.values(props.chartData.decisionesPorResultado),
    }],
};

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
};
</script>

<template>
    <Head title="Panel de Indicadores Jurídicos" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                📊 Panel de Indicadores de Gestión Jurídica
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                
                <!-- Tarjetas de KPIs -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <div class="bg-white p-6 rounded-lg shadow-md">
                        <h3 class="text-sm font-medium text-gray-500">Incidentes Activos</h3>
                        <p class="mt-1 text-3xl font-semibold text-gray-900">{{ kpis.activos }}</p>
                    </div>
                    <div class="bg-white p-6 rounded-lg shadow-md">
                        <h3 class="text-sm font-medium text-gray-500">Actualmente en Revisión</h3>
                        <p class="mt-1 text-3xl font-semibold text-gray-900">{{ kpis.en_revision }}</p>
                    </div>
                    <div class="bg-white p-6 rounded-lg shadow-md">
                        <h3 class="text-sm font-medium text-gray-500">Sanciones Emitidas</h3>
                        <p class="mt-1 text-3xl font-semibold text-gray-900">{{ kpis.sanciones }}</p>
                    </div>
                    <div class="bg-white p-6 rounded-lg shadow-md">
                        <h3 class="text-sm font-medium text-gray-500">Tiempo Promedio Resolución</h3>
                        <p class="mt-1 text-3xl font-semibold text-gray-900">{{ kpis.tiempo_promedio }} <span class="text-lg font-normal">días</span></p>
                    </div>
                </div>

                <!-- Gráficos -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div class="bg-white p-6 rounded-lg shadow-md">
                        <h3 class="font-semibold text-lg text-gray-900 mb-4">Incidentes por Estado</h3>
                        <div class="h-64">
                            <Bar :data="incidentesChartData" :options="chartOptions" />
                        </div>
                    </div>
                    <div class="bg-white p-6 rounded-lg shadow-md">
                        <h3 class="font-semibold text-lg text-gray-900 mb-4">Resultados de Decisiones</h3>
                        <div class="h-64">
                            <Pie :data="decisionesChartData" :options="chartOptions" />
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
