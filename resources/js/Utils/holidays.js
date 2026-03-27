/**
 * Utility to calculate Colombian holidays.
 * Includes fixed dates, Ley Emiliani (moved to Monday), and religious holidays (relative to Easter).
 */

export const getEaster = (year) => {
    const a = year % 19;
    const b = Math.floor(year / 100);
    const c = year % 100;
    const d = Math.floor(b / 4);
    const e = b % 4;
    const f = Math.floor((b + 8) / 25);
    const g = Math.floor((b - f + 1) / 3);
    const h = (19 * a + b - d - g + 15) % 30;
    const i = Math.floor(c / 4);
    const k = c % 4;
    const l = (32 + 2 * e + 2 * i - h - k) % 7;
    const m = Math.floor((a + 11 * h + 22 * l) / 451);
    const month = Math.floor((h + l - 7 * m + 114) / 31);
    const day = ((h + l - 7 * m + 114) % 31) + 1;
    return new Date(year, month - 1, day);
};

export const getNextMonday = (date) => {
    const result = new Date(date);
    const day = result.getDay();
    if (day === 1) return result; // Already Monday
    const diff = (day === 0) ? 1 : (8 - day);
    result.setDate(result.getDate() + diff);
    return result;
};

export const getColombianHolidays = (year) => {
    const holidays = [];

    // --- Fixed Date Holidays ---
    holidays.push({ date: new Date(year, 0, 1), name: 'Año Nuevo' });
    holidays.push({ date: new Date(year, 4, 1), name: 'Día del Trabajo' });
    holidays.push({ date: new Date(year, 6, 20), name: 'Independencia de Colombia' });
    holidays.push({ date: new Date(year, 7, 7), name: 'Batalla de Boyacá' });
    holidays.push({ date: new Date(year, 11, 8), name: 'Inmaculada Concepción' });
    holidays.push({ date: new Date(year, 11, 25), name: 'Navidad' });

    // --- Ley Emiliani Holidays (Moved to next Monday) ---
    holidays.push({ date: getNextMonday(new Date(year, 0, 6)), name: 'Reyes Magos' });
    holidays.push({ date: getNextMonday(new Date(year, 2, 19)), name: 'San José' });
    holidays.push({ date: getNextMonday(new Date(year, 5, 29)), name: 'San Pedro y San Pablo' });
    holidays.push({ date: getNextMonday(new Date(year, 7, 15)), name: 'Asunción de la Virgen' });
    holidays.push({ date: getNextMonday(new Date(year, 9, 12)), name: 'Día de la Raza' });
    holidays.push({ date: getNextMonday(new Date(year, 10, 1)), name: 'Todos los Santos' });
    holidays.push({ date: getNextMonday(new Date(year, 10, 11)), name: 'Independencia de Cartagena' });

    // --- Religious Holidays (Relative to Easter) ---
    const easter = getEaster(year);
    
    // Jueves Santo (Easter - 3 days)
    const holyThursday = new Date(easter);
    holyThursday.setDate(easter.getDate() - 3);
    holidays.push({ date: holyThursday, name: 'Jueves Santo' });

    // Viernes Santo (Easter - 2 days)
    const holyFriday = new Date(easter);
    holyFriday.setDate(easter.getDate() - 2);
    holidays.push({ date: holyFriday, name: 'Viernes Santo' });

    // Ascensión del Señor (Easter + 43 days, moved to next Monday -> + 43 is always Sunday, so +44)
    const ascension = new Date(easter);
    ascension.setDate(easter.getDate() + 43);
    holidays.push({ date: getNextMonday(ascension), name: 'Ascensión del Señor' });

    // Corpus Christi (Easter + 64 days, moved to next Monday -> + 64 is Sunday, so +65)
    const corpusChristi = new Date(easter);
    corpusChristi.setDate(easter.getDate() + 64);
    holidays.push({ date: getNextMonday(corpusChristi), name: 'Corpus Christi' });

    // Sagrado Corazón (Easter + 71 days, moved to next Monday -> + 71 is Sunday, so +72)
    const sacredHeart = new Date(easter);
    sacredHeart.setDate(easter.getDate() + 71);
    holidays.push({ date: getNextMonday(sacredHeart), name: 'Sagrado Corazón' });

    return holidays;
};

export const isHoliday = (date) => {
    if (!date) return null;
    const year = date.getFullYear();
    const holidays = getColombianHolidays(year);
    const found = holidays.find(h => 
        h.date.getDate() === date.getDate() && 
        h.date.getMonth() === date.getMonth() && 
        h.date.getFullYear() === date.getFullYear()
    );
    return found ? found.name : null;
};
