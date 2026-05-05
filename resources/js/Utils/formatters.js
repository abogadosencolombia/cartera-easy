export const formatRadicado = (val) => {
    let value = String(val || '').replace(/\D/g, '');
    if (value.length > 23) value = value.slice(0, 23);
    return value;
};

export const addDaysToDate = (dateStr, days) => {
    const current = dateStr ? new Date(dateStr) : new Date();
    current.setDate(current.getDate() + days);
    return current.toISOString().split('T')[0];
};

export const addMonthsToDate = (dateStr, months) => {
    const current = dateStr ? new Date(dateStr) : new Date();
    current.setMonth(current.getMonth() + months);
    return current.toISOString().split('T')[0];
};

/**
 * Convierte un texto a Mayúsculas.
 */
export const toUpperCase = (val) => {
    return String(val || '').toUpperCase();
};

/**
 * Calcula el Dígito de Verificación (DV) para un NIT en Colombia.
 */
export const calculateDV = (nit) => {
    if (!nit || isNaN(nit)) return "";
    
    let vpri = [0, 3, 7, 13, 17, 19, 23, 29, 37, 41, 43, 47, 53, 59, 67, 71];
    let x = 0;
    let y = 0;
    let z = nit.length;
    
    for (let i = 0; i < z; i++) {
        y = nit.substr(i, 1);
        x += y * vpri[z - i];
    }
    
    y = x % 11;
    
    if (y > 1) {
        return 11 - y;
    } else {
        return y;
    }
};
