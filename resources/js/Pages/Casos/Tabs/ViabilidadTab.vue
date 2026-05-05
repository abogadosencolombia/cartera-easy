<script setup>
import { ref, watch, computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { 
    ShieldCheckIcon, 
    ExclamationTriangleIcon, 
    CheckCircleIcon, 
    XCircleIcon,
    InformationCircleIcon,
    ScaleIcon,
    BanknotesIcon,
    DocumentMagnifyingGlassIcon,
    EnvelopeIcon,
    BriefcaseIcon,
    ArrowPathIcon,
    LockClosedIcon,
    PlusIcon,
    TrashIcon
} from '@heroicons/vue/24/outline';
import Swal from 'sweetalert2';

const props = defineProps({
    entity: Object, // Puede ser un Caso o un ProcesoRadicado
    type: { type: String, default: 'caso' } // 'caso' o 'proceso'
});

const defaultViabilidad = {
    exigibilidad: 'pendiente',
    titulo_ejecutivo: 'pendiente',
    clausula_aceleratoria: 'pendiente',
    carta_instrucciones: 'pendiente',
    desembolso: 'pendiente',
    liquidacion: 'pendiente',
    control_usura: 'pendiente',
    prescripcion: 'pendiente',
    notificacion_digital: 'pendiente',
    habeas_data_ley2300: 'pendiente',
    insolvencia: 'pendiente',
    estrategia_cautelares: '',
    matriz_riesgos: [],
    analisis_tecnico: ''
};

const viabilidad = ref(props.entity.viabilidad_juridica || { ...defaultViabilidad });
const estado = ref(props.entity.viabilidad_estado || 'pendiente');

const ejecutarAutoAuditoria = () => {
    const e = props.entity;
    
    // 1. Título Ejecutivo (Pagaré)
    if (e.referencia_credito) {
        viabilidad.value.titulo_ejecutivo = 'completo';
    }

    // 2. Notificación Digital
    const deudor = e.deudor || (e.demandados && e.demandados[0]);
    if (deudor && deudor.correo_1 && deudor.celular_1) {
        viabilidad.value.notificacion_digital = 'ok';
    } else if (e.medio_contacto) {
        viabilidad.value.notificacion_digital = 'ok';
    }

    // 3. Prescripción (Lógica 2026)
    if (e.fecha_vencimiento) {
        const vencimiento = new Date(e.fecha_vencimiento);
        const hoy = new Date();
        const diffYears = (hoy - vencimiento) / (1000 * 60 * 60 * 24 * 365);
        
        if (diffYears >= 3) {
            viabilidad.value.prescripcion = 'fail';
            estado.value = 'rojo'; // Bloqueo automático por prescripción
        } else if (diffYears >= 2.5) {
            viabilidad.value.prescripcion = 'riesgo';
            if (estado.value !== 'rojo') estado.value = 'amarillo';
        } else {
            viabilidad.value.prescripcion = 'ok';
        }
    }

    // 4. Control de Usura
    if (e.tasa_interes_corriente > 3) {
        viabilidad.value.control_usura = 'ajuste';
    } else if (e.tasa_interes_corriente > 0) {
        viabilidad.value.control_usura = 'conforme';
    }

    Swal.fire({
        title: 'Auto-Auditoría Completa',
        text: 'El sistema ha analizado las fechas, montos y contactos para sugerir un dictamen inicial.',
        icon: 'info',
        timer: 3000,
        toast: true,
        position: 'top-end'
    });
};

const form = useForm({
    viabilidad_juridica: viabilidad.value,
    viabilidad_estado: estado.value
});

const saveViabilidad = () => {
    const routeName = props.type === 'caso' ? 'casos.viabilidad.update' : 'procesos.viabilidad.update';
    
    form.viabilidad_juridica = viabilidad.value;
    form.viabilidad_estado = estado.value;

    form.patch(route(routeName, props.entity.id), {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({
                title: '¡Actualizado!',
                text: 'La ficha de viabilidad jurídica se ha guardado correctamente.',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        }
    });
};

const addRiesgo = () => {
    if (!viabilidad.value.matriz_riesgos) viabilidad.value.matriz_riesgos = [];
    viabilidad.value.matriz_riesgos.push({
        riesgo: '',
        nivel: 'bajo',
        ataque: '',
        vision_juez: '',
        neutralizacion: ''
    });
};

const removeRiesgo = (index) => {
    viabilidad.value.matriz_riesgos.splice(index, 1);
};

const statusOptions = [
    { value: 'pendiente', label: 'PENDIENTE', color: 'bg-gray-100 text-gray-500', icon: InformationCircleIcon },
    { value: 'verde', label: 'VERDE - RADICAR', color: 'bg-green-100 text-green-700', icon: CheckCircleIcon },
    { value: 'amarillo', label: 'AMARILLO - SUBSANAR', color: 'bg-amber-100 text-amber-700', icon: ExclamationTriangleIcon },
    { value: 'rojo', label: 'ROJO - NO RADICAR', color: 'bg-red-100 text-red-700', icon: XCircleIcon },
];

const checkOptions = [
    { value: 'pendiente', label: 'Pendiente de Revisión', color: 'text-gray-400' },
    { value: 'ok', label: 'Conforme / Sí', color: 'text-green-600' },
    { value: 'riesgo', label: 'Riesgo / Débil', color: 'text-amber-600' },
    { value: 'fail', label: 'No Cumple / No', color: 'text-red-600' },
    { value: 'na', label: 'No Aplica', color: 'text-gray-500' },
];

const exigibilidadOptions = [
    { value: 'pendiente', label: 'Pendiente' },
    { value: 'si', label: 'Sí - Exigible' },
    { value: 'no', label: 'No - Pendiente Condición' },
    { value: 'condicionada', label: 'Condicionada' },
];

const tituloOptions = [
    { value: 'pendiente', label: 'Pendiente' },
    { value: 'completo', label: 'Completo / Presta Mérito' },
    { value: 'imperfecto', label: 'Imperfecto' },
    { value: 'sin_merito', label: 'Sin Mérito Ejecutivo' },
];

const usuraOptions = [
    { value: 'pendiente', label: 'Pendiente' },
    { value: 'conforme', label: 'Conforme' },
    { value: 'ajuste', label: 'Requiere Ajuste' },
    { value: 'usura', label: 'USURA DETECTADA' },
];
</script>

<template>
    <div class="space-y-8 pb-20">
        <!-- SEMÁFORO DE DECISIÓN -->
        <div class="bg-white dark:bg-gray-800 rounded-3xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
            <div class="p-8 flex flex-col md:flex-row items-center justify-between gap-6 bg-gradient-to-r from-gray-50 to-white dark:from-gray-800/50 dark:to-gray-800">
                <div class="flex items-center gap-4">
                    <div class="p-4 rounded-2xl bg-indigo-600 text-white shadow-lg shadow-indigo-200 dark:shadow-none">
                        <ShieldCheckIcon class="w-8 h-8" />
                    </div>
                    <div>
                        <h2 class="text-2xl font-black text-gray-900 dark:text-white uppercase tracking-tight">Dictamen de Viabilidad Jurídica</h2>
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-widest mt-1">PROTOCOLO DIRECTOR JURÍDICO - COLOMBIA 2026</p>
                        <button @click="ejecutarAutoAuditoria" class="mt-4 flex items-center gap-2 px-4 py-2 bg-indigo-50 text-indigo-600 rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-indigo-600 hover:text-white transition-all">
                            <ArrowPathIcon class="w-4 h-4" /> Diagnóstico Automático
                        </button>
                    </div>
                </div>

                <div class="flex flex-wrap justify-center gap-3">
                    <button 
                        v-for="opt in statusOptions" 
                        :key="opt.value"
                        @click="estado = opt.value"
                        :class="[
                            estado === opt.value ? 'ring-4 ring-indigo-500/20 border-indigo-500 shadow-md' : 'opacity-40 hover:opacity-100 border-transparent',
                            opt.color
                        ]"
                        class="flex items-center gap-2 px-6 py-3 rounded-2xl border-2 transition-all font-black text-[11px] uppercase tracking-widest"
                    >
                        <component :is="opt.icon" class="w-5 h-5" />
                        {{ opt.label }}
                    </button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- COLUMNA IZQUIERDA: CHECKLIST TÉCNICO -->
            <div class="lg:col-span-2 space-y-8">
                <!-- EXAMEN TÉCNICO-PROCESAL -->
                <section class="bg-white dark:bg-gray-800 p-8 rounded-3xl shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="flex items-center gap-3 mb-8 border-b border-gray-50 dark:border-gray-700 pb-4">
                        <ScaleIcon class="w-6 h-6 text-indigo-500" />
                        <h3 class="text-sm font-black text-gray-900 dark:text-white uppercase tracking-[0.2em]">Examen Técnico-Procesal</h3>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-6">
                        <!-- Items del Protocolo -->
                        <div class="space-y-4">
                            <label class="block">
                                <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 block">Exigibilidad de la Obligación</span>
                                <select v-model="viabilidad.exigibilidad" class="w-full bg-gray-50 dark:bg-gray-900 border-none rounded-xl text-xs font-bold focus:ring-2 focus:ring-indigo-500">
                                    <option v-for="o in exigibilidadOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                                </select>
                            </label>

                            <label class="block">
                                <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 block">Título Ejecutivo / Mérito</span>
                                <select v-model="viabilidad.titulo_ejecutivo" class="w-full bg-gray-50 dark:bg-gray-900 border-none rounded-xl text-xs font-bold focus:ring-2 focus:ring-indigo-500">
                                    <option v-for="o in tituloOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                                </select>
                            </label>

                            <label class="block">
                                <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 block">Cláusula Aceleratoria</span>
                                <select v-model="viabilidad.clausula_aceleratoria" class="w-full bg-gray-50 dark:bg-gray-900 border-none rounded-xl text-xs font-bold focus:ring-2 focus:ring-indigo-500">
                                    <option v-for="o in checkOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                                </select>
                            </label>

                            <label class="block">
                                <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 block">Carta de Instrucciones</span>
                                <select v-model="viabilidad.carta_instrucciones" class="w-full bg-gray-50 dark:bg-gray-900 border-none rounded-xl text-xs font-bold focus:ring-2 focus:ring-indigo-500">
                                    <option v-for="o in checkOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                                </select>
                            </label>

                            <label class="block">
                                <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 block">Prueba de Desembolso</span>
                                <select v-model="viabilidad.desembolso" class="w-full bg-gray-50 dark:bg-gray-900 border-none rounded-xl text-xs font-bold focus:ring-2 focus:ring-indigo-500">
                                    <option v-for="o in checkOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                                </select>
                            </label>
                        </div>

                        <div class="space-y-4">
                            <label class="block">
                                <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 block">Liquidación Auditada</span>
                                <select v-model="viabilidad.liquidacion" class="w-full bg-gray-50 dark:bg-gray-900 border-none rounded-xl text-xs font-bold focus:ring-2 focus:ring-indigo-500">
                                    <option v-for="o in checkOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                                </select>
                            </label>

                            <label class="block">
                                <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 block">Control de Usura</span>
                                <select v-model="viabilidad.control_usura" class="w-full bg-gray-50 dark:bg-gray-900 border-none rounded-xl text-xs font-bold focus:ring-2 focus:ring-indigo-500">
                                    <option v-for="o in usuraOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                                </select>
                            </label>

                            <label class="block">
                                <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 block">Estado de Prescripción</span>
                                <select v-model="viabilidad.prescripcion" class="w-full bg-gray-50 dark:bg-gray-900 border-none rounded-xl text-xs font-bold focus:ring-2 focus:ring-indigo-500">
                                    <option v-for="o in checkOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                                </select>
                            </label>

                            <label class="block">
                                <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 block">Notificación Digital (Trazabilidad)</span>
                                <select v-model="viabilidad.notificacion_digital" class="w-full bg-gray-50 dark:bg-gray-900 border-none rounded-xl text-xs font-bold focus:ring-2 focus:ring-indigo-500">
                                    <option v-for="o in checkOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                                </select>
                            </label>

                            <label class="block">
                                <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 block">Insolvencia (Consulta Previa)</span>
                                <select v-model="viabilidad.insolvencia" class="w-full bg-gray-50 dark:bg-gray-900 border-none rounded-xl text-xs font-bold focus:ring-2 focus:ring-indigo-500">
                                    <option v-for="o in checkOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                                </select>
                            </label>
                        </div>
                    </div>
                </section>

                <!-- MATRIZ DE RIESGOS -->
                <section class="bg-white dark:bg-gray-800 p-8 rounded-3xl shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="flex items-center justify-between mb-8 border-b border-gray-50 dark:border-gray-700 pb-4">
                        <div class="flex items-center gap-3">
                            <ExclamationTriangleIcon class="w-6 h-6 text-amber-500" />
                            <h3 class="text-sm font-black text-gray-900 dark:text-white uppercase tracking-[0.2em]">Matriz de Riesgos</h3>
                        </div>
                        <button @click="addRiesgo" class="p-2 bg-indigo-50 text-indigo-600 rounded-xl hover:bg-indigo-600 hover:text-white transition-all">
                            <PlusIcon class="w-5 h-5" />
                        </button>
                    </div>

                    <div v-if="!viabilidad.matriz_riesgos || viabilidad.matriz_riesgos.length === 0" class="text-center py-12 bg-gray-50 dark:bg-gray-900/50 rounded-2xl border-2 border-dashed border-gray-200 dark:border-gray-700">
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-widest">No se han identificado riesgos específicos</p>
                    </div>

                    <div v-else class="space-y-4">
                        <div v-for="(item, idx) in viabilidad.matriz_riesgos" :key="idx" class="p-6 bg-gray-50 dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 relative group">
                            <button @click="removeRiesgo(idx)" class="absolute top-4 right-4 p-2 text-gray-400 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-opacity">
                                <TrashIcon class="w-4 h-4" />
                            </button>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="md:col-span-2 flex items-center gap-4">
                                    <input v-model="item.riesgo" placeholder="Título del riesgo..." class="flex-grow bg-white dark:bg-gray-800 border-none rounded-lg text-xs font-black uppercase tracking-tight" />
                                    <select v-model="item.nivel" class="bg-white dark:bg-gray-800 border-none rounded-lg text-[10px] font-black uppercase">
                                        <option value="bajo">Bajo</option>
                                        <option value="medio">Medio</option>
                                        <option value="alto">Alto</option>
                                        <option value="critico">Crítico</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-[9px] font-black text-gray-400 uppercase mb-1 block">Ataque de la contraparte</label>
                                    <textarea v-model="item.ataque" rows="2" class="w-full bg-white dark:bg-gray-800 border-none rounded-lg text-xs" placeholder="¿Cómo lo atacarán?"></textarea>
                                </div>
                                <div>
                                    <label class="text-[9px] font-black text-gray-400 uppercase mb-1 block">Visión del Juez</label>
                                    <textarea v-model="item.vision_juez" rows="2" class="w-full bg-white dark:bg-gray-800 border-none rounded-lg text-xs" placeholder="¿Cómo lo verá el juez?"></textarea>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="text-[9px] font-black text-indigo-400 uppercase mb-1 block">Neutralización / Acción defensiva</label>
                                    <textarea v-model="item.neutralizacion" rows="2" class="w-full bg-white dark:bg-gray-800 border-none rounded-lg text-xs font-bold" placeholder="Acción para mitigar..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- COLUMNA DERECHA: ESTRATEGIA Y ACCIÓN -->
            <div class="space-y-8">
                <!-- ESTRATEGIA DE CAUTELARES -->
                <section class="bg-white dark:bg-gray-800 p-8 rounded-3xl shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="flex items-center gap-3 mb-6 border-b border-gray-50 dark:border-gray-700 pb-4">
                        <BanknotesIcon class="w-6 h-6 text-green-500" />
                        <h3 class="text-sm font-black text-gray-900 dark:text-white uppercase tracking-[0.2em]">Estrategia Cautelar</h3>
                    </div>
                    <textarea 
                        v-model="viabilidad.estrategia_cautelares" 
                        rows="6" 
                        class="w-full bg-gray-50 dark:bg-gray-900 border-none rounded-2xl text-xs font-medium focus:ring-2 focus:ring-indigo-500"
                        placeholder="Defina la proporcionalidad y necesidad de las medidas (embargo, secuestro, inscripción)..."
                    ></textarea>
                </section>

                <!-- ANÁLISIS TÉCNICO FINAL -->
                <section class="bg-white dark:bg-gray-800 p-8 rounded-3xl shadow-sm border border-gray-100 dark:border-gray-700">
                    <div class="flex items-center gap-3 mb-6 border-b border-gray-50 dark:border-gray-700 pb-4">
                        <DocumentMagnifyingGlassIcon class="w-6 h-6 text-indigo-500" />
                        <h3 class="text-sm font-black text-gray-900 dark:text-white uppercase tracking-[0.2em]">Análisis del Director</h3>
                    </div>
                    <textarea 
                        v-model="viabilidad.analisis_tecnico" 
                        rows="8" 
                        class="w-full bg-gray-50 dark:bg-gray-900 border-none rounded-2xl text-xs font-bold leading-relaxed focus:ring-2 focus:ring-indigo-500"
                        placeholder="Conclusión definitiva del caso..."
                    ></textarea>
                </section>

                <!-- BOTÓN GUARDAR -->
                <div class="sticky bottom-8">
                    <button 
                        @click="saveViabilidad"
                        :disabled="form.processing"
                        class="w-full py-5 bg-gray-900 dark:bg-white text-white dark:text-gray-900 rounded-2xl font-black text-[11px] uppercase tracking-[0.2em] transition-all hover:bg-black dark:hover:bg-gray-100 shadow-2xl flex items-center justify-center gap-3 active:scale-[0.98]"
                    >
                        <ArrowPathIcon v-if="form.processing" class="w-5 h-5 animate-spin" />
                        <LockClosedIcon v-else class="w-5 h-5" />
                        Guardar Ficha Jurídica
                    </button>
                    <p class="text-[9px] text-center text-gray-400 font-bold uppercase tracking-widest mt-4 italic">
                        El litigio se gana con título limpio y prueba completa.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
