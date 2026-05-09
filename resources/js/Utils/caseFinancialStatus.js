const financialFields = [
    {
        key: 'monto_total',
        label: 'Monto de Crédito (Capital)',
        shortLabel: 'Capital',
        zeroCountsAsMissing: true,
    },
    {
        key: 'monto_deuda_actual',
        label: 'Deuda Actual',
        shortLabel: 'Deuda actual',
        zeroCountsAsMissing: true,
    },
    {
        key: 'monto_total_pagado',
        label: 'Total Pagado',
        shortLabel: 'Total pagado',
        zeroCountsAsMissing: false,
        zeroLabel: 'Sin pagos registrados',
    },
];

const normalizeMoney = (value) => {
    if (value === null || value === undefined || value === '') return null;
    const number = Number(String(value).replace(/[^0-9.-]+/g, ''));
    return Number.isFinite(number) ? number : null;
};

const isMissingMoney = (value, zeroCountsAsMissing = false) => {
    const normalized = normalizeMoney(value);
    if (normalized === null) return true;
    return zeroCountsAsMissing && normalized <= 0;
};

export const getCaseFinancialStatus = (caso = {}) => {
    const montoTotal = normalizeMoney(caso?.monto_total);
    const totalPagado = normalizeMoney(caso?.monto_total_pagado);
    const isFullyPaid = montoTotal !== null && montoTotal > 0 && totalPagado !== null && totalPagado >= montoTotal;

    const fields = financialFields.map((field) => {
        const value = caso?.[field.key];
        const numericValue = normalizeMoney(value);
        const zeroCountsAsMissing = field.key === 'monto_deuda_actual'
            ? !isFullyPaid
            : field.zeroCountsAsMissing;
        const missing = isMissingMoney(value, zeroCountsAsMissing);

        return {
            ...field,
            value,
            numericValue,
            missing,
            displayLabel: !missing && numericValue === 0 && field.zeroLabel
                ? field.zeroLabel
                : null,
        };
    });

    const missingFields = fields.filter((field) => field.missing);

    return {
        fields,
        missingFields,
        missingLabels: missingFields.map((field) => field.label),
        hasMissing: missingFields.length > 0,
        completedCount: fields.length - missingFields.length,
        totalCount: fields.length,
        label: missingFields.length > 0
            ? `${missingFields.length} dato${missingFields.length === 1 ? '' : 's'} financiero${missingFields.length === 1 ? '' : 's'} pendiente${missingFields.length === 1 ? '' : 's'}`
            : 'Información financiera completa',
        detail: missingFields.length > 0
            ? `Falta registrar: ${missingFields.map((field) => field.shortLabel).join(', ')}.`
            : 'Capital, deuda actual y pagos registrados.',
    };
};
