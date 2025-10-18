<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const page = usePage();
const flash = computed(() => page.props.flash ?? {});

const form = useForm({
  archivo: null,
});

const onFile = (e) => {
  const f = e.target.files?.[0] ?? null;
  form.archivo = f;
};

const enviar = () => {
  if (!form.archivo) return;
  form.post(route('procesos.import'), {
    forceFormData: true,
    onSuccess: () => {
      form.reset('archivo');
      // limpiar input file
      const el = document.getElementById('archivo');
      if (el) el.value = '';
    },
  });
};
</script>

<template>
  <Head title="Procesos" />
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
        Procesos judiciales
      </h2>
    </template>

    <div class="py-10">
      <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <!-- Flash success -->
        <div
          v-if="flash.success"
          class="rounded-md bg-green-50 dark:bg-green-900/30 p-4 border border-green-200 dark:border-green-800"
        >
          <div class="text-sm text-green-700 dark:text-green-200">
            {{ flash.success }}
          </div>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg">
          <div class="px-6 py-5">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
              Importar Excel de procesos
            </h3>

            <div class="space-y-4">
              <div>
                <label for="archivo" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                  Selecciona el archivo (.xlsx, .xls o .csv)
                </label>
                <input
                  id="archivo"
                  type="file"
                  accept=".xlsx,.xls,.csv"
                  @change="onFile"
                  class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 shadow-sm"
                />
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                  Máx. 10MB. Debe contener todas las columnas requeridas.
                </p>
                <div v-if="form.errors.archivo" class="mt-2 text-sm text-red-600">
                  {{ form.errors.archivo }}
                </div>
              </div>

              <div class="flex items-center gap-3">
                <PrimaryButton
                  type="button"
                  :disabled="!form.archivo || form.processing"
                  @click="enviar"
                >
                  {{ form.processing ? 'Subiendo…' : 'Importar' }}
                </PrimaryButton>
                <SecondaryButton type="button" @click="() => { form.reset('archivo'); const el = document.getElementById('archivo'); if (el) el.value=''; }">
                  Limpiar
                </SecondaryButton>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </AuthenticatedLayout>
</template>
