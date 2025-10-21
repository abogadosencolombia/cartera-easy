<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm, usePage } from '@inertiajs/vue3'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import InputError from '@/Components/InputError.vue'
import { computed } from 'vue'

const page = usePage()
const flash = computed(() => page.props.flash ?? {})

const form = useForm({
  file: null,
})

const submit = () => {
  form.post(route('procesos.import.store'), {
    forceFormData: true,       // **MULTIPART**
    onSuccess: () => form.reset('file'),
  })
}
</script>

<template>
  <Head title="Importar Procesos" />

  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
        Importar Procesos (Excel)
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-6 text-gray-900 dark:text-gray-100">
            <div v-if="flash.success" class="mb-4 rounded bg-green-100 px-4 py-3 text-green-800">
              {{ flash.success }}
            </div>

            <form @submit.prevent="submit" enctype="multipart/form-data" class="space-y-6">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                  Archivo (.xlsx, .xls, .csv)
                </label>
                <input
                  type="file"
                  accept=".xlsx,.xls,.csv"
                  class="mt-1 block w-full text-sm text-gray-700 dark:text-gray-200
                         file:mr-4 file:py-2 file:px-4
                         file:rounded-md file:border-0
                         file:text-sm file:font-semibold
                         file:bg-gray-100 file:text-gray-700
                         hover:file:bg-gray-200"
                  @change="form.file = $event.target.files[0]"
                />
                <InputError :message="form.errors.file" class="mt-2" />
              </div>

              <div class="flex justify-end">
                <PrimaryButton :disabled="form.processing">
                  {{ form.processing ? 'Importando…' : 'Importar' }}
                </PrimaryButton>
              </div>
            </form>

            <p class="mt-6 text-xs text-gray-500">
              * Si ves un error 419, asegúrate de tener la sesión activa y el token CSRF cargado.
            </p>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
