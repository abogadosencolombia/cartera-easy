const normalizeText = (value) => String(value ?? '').trim();

const comparableText = (value) => normalizeText(value)
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase();

const hasText = (value) => normalizeText(value) !== '';

const hasUsableName = (persona) => {
    const name = comparableText(persona?.nombre_completo);

    return hasText(persona?.nombre_completo)
        && !name.includes('por identificar')
        && !name.includes('sin nombre');
};

const hasUsableDocument = (persona) => {
    const document = comparableText(persona?.numero_documento);

    return hasText(persona?.numero_documento)
        && !document.startsWith('temp-')
        && !document.includes('por identificar')
        && document !== 'sin documento';
};

const hasPhone = (persona) => [
    persona?.celular_1,
    persona?.celular_2,
    persona?.telefono_fijo,
    persona?.telefono,
    persona?.celular,
].some(hasText);

const hasEmail = (persona) => [
    persona?.correo_1,
    persona?.correo_2,
    persona?.correo,
    persona?.email,
].some(hasText);

const parseAddresses = (addresses) => {
    if (!addresses) return [];
    if (Array.isArray(addresses)) return addresses;
    if (typeof addresses === 'object') return [addresses];

    try {
        const parsed = JSON.parse(addresses);
        return Array.isArray(parsed) ? parsed : [parsed];
    } catch {
        return [];
    }
};

const hasAddress = (persona) => {
    if (hasText(persona?.direccion) || hasText(persona?.ciudad)) return true;

    return parseAddresses(persona?.addresses).some((address) => {
        if (typeof address === 'string') return hasText(address);

        return [
            address?.address,
            address?.direccion,
            address?.city,
            address?.ciudad,
        ].some(hasText);
    });
};

export const getPersonaMissingFields = (persona) => {
    if (!persona) return [];

    const missing = [];

    if (!hasUsableName(persona)) missing.push({ key: 'nombre_completo', label: 'Nombre' });
    if (!hasUsableDocument(persona)) missing.push({ key: 'numero_documento', label: 'Documento' });
    if (Object.prototype.hasOwnProperty.call(persona, 'dv') && comparableText(persona?.tipo_documento) === 'nit' && !hasText(persona?.dv)) missing.push({ key: 'dv', label: 'DV' });
    if (!hasPhone(persona)) missing.push({ key: 'telefono', label: 'Telefono' });
    if (!hasEmail(persona)) missing.push({ key: 'correo', label: 'Correo' });
    if (!hasAddress(persona)) missing.push({ key: 'direccion', label: 'Direccion' });

    return missing;
};

export const hasIncompletePersonaInfo = (persona) => getPersonaMissingFields(persona).length > 0;

export const personaCompletenessTitle = (persona) => {
    const missing = getPersonaMissingFields(persona);

    if (!missing.length) return 'Informacion completa';

    const name = normalizeText(persona?.nombre_completo);
    const prefix = name ? `Completar datos de ${name}` : 'Completar datos';

    return `${prefix}: ${missing.map((field) => field.label).join(', ')}`;
};
