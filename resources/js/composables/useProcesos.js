// resources/js/composables/useProcesos.js
import { ref } from 'vue';

/**
 * Composable para la lógica compartida de la gestión de Procesos.
 */
export function useProcesos() {

  /**
   * Formatea un string de fecha de Laravel (YYYY-MM-DD HH:MM:SS)
   * al formato que necesita un input[type=date] (YYYY-MM-DD).
   * @param {String|null} dateString
   * @returns {String}
   */
  const formatDateForInput = (dateString) => {
    if (!dateString) return '';
    return dateString.substring(0, 10);
  };

  /**
   * Formatea una fecha para ser legible por humanos.
   * @param {String|null} v
   * @returns {String}
   */
  const formatDate = (v) => {
    if (!v) return '—';
    return new Date(v).toLocaleDateString('es-CO', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      timeZone: 'UTC',
    });
  };

  /**
   * Genera el estado y estilo del semáforo para la próxima revisión.
   * @param {String|null} dateString
   * @returns {{text: String, classes: String}}
   */
  const getRevisionStatus = (dateString) => {
    if (!dateString) {
      return { text: 'Sin fecha', classes: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 ring-1 ring-inset ring-gray-500/20' };
    }
    const revisionDate = new Date(dateString);
    if (isNaN(revisionDate.getTime())) {
      return { text: 'Fecha inválida', classes: 'bg-gray-100 text-gray-800' };
    }
    const revUTC = new Date(Date.UTC(revisionDate.getUTCFullYear(), revisionDate.getUTCMonth(), revisionDate.getUTCDate()));
    const today = new Date();
    const todayUTC = new Date(Date.UTC(today.getUTCFullYear(), today.getUTCMonth(), today.getUTCDate()));
    const diffDays = Math.ceil((revUTC.getTime() - todayUTC.getTime()) / 86400000); // 1000 * 60 * 60 * 24
    const shortDate = revisionDate.toLocaleDateString('es-CO', { month: 'short', day: 'numeric', timeZone: 'UTC' });

    if (diffDays <= 2) {
      const label = diffDays < 0 ? `Vencido (${shortDate})` : `Urgente (${shortDate})`;
      return { text: label, classes: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200 ring-1 ring-inset ring-red-600/20 animate-pulse' };
    }
    if (diffDays <= 4) {
      return { text: `Próximo (${shortDate})`, classes: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-200 ring-1 ring-inset ring-yellow-600/20' };
    }
    return { text: formatDate(dateString), classes: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200 ring-1 ring-inset ring-green-600/20' };
  };

  /**
   * Convierte un objeto de relación de Laravel al formato que AsyncSelect espera.
   * @param {Object|null} obj
   * @param {String} labelKey - La propiedad del objeto a usar como 'label' (ej. 'name', 'nombre').
   * @returns {{id: Number, label: String}|null}
   */
  const mapToSelectOption = (obj, labelKey) => {
    if (!obj) return null;
    return {
      id: obj.id,
      label: obj[labelKey],
    };
  };

  return {
    formatDateForInput,
    formatDate,
    getRevisionStatus,
    mapToSelectOption
  };
}
