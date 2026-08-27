from __future__ import annotations

import csv
import re
import shutil
import subprocess
import unicodedata
from hashlib import sha256
from importlib.util import module_from_spec, spec_from_file_location
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile


ROOT = Path("/code/DEMANDAS")
MAIN_GENERATOR = ROOT / "_work_generacion_26" / "generar_paquete_52.py"
OUT = ROOT / "FICHAS_CIERRE_26_CASOS_PARA_COMPLETAR_2026-07-21"
CONTROL = OUT / "00_CONTROL_MAESTRO"
ZIP_PATH = ROOT / "PAQUETE_26_FICHAS_CIERRE_PARA_COMPLETAR_2026-07-21.zip"
TEMPLATE = ROOT / "MODELO.docx"
EXTRACT_DIR = Path("/code/wetransfers_20260715/WETRANSFERS/wetransfer_extractos-sandra-duque-activos_2026-07-06_1943")
SUPPORT_DIR = Path("/code/wetransfers_20260715/WETRANSFERS")
MAIN_OUT = ROOT / "PLANTILLAS_26_CASOS_PARA_COMPLETAR_2026-07-21"

spec = spec_from_file_location("main_package", MAIN_GENERATOR)
g = module_from_spec(spec)
assert spec.loader is not None
spec.loader.exec_module(g)

RED = "C00000"
AMBER = "BF9000"
NAVY = "1F4E78"
GREEN = "548235"
CUT = "6 de julio de 2026"

DETAILED_AUDITS = {
    "1911000986": (
        "Auditoría exhaustiva disponible en ENTREGABLES_1911000986_V2; "
        "incluye informe, matriz dato–soporte, faltantes, alertas y memoria de liquidación autónoma."
    ),
    "1912000810": (
        "Auditoría integral disponible en ENTREGABLES_1912000810_V2; "
        "incluye reconciliación contable interna, pero no una liquidación autónoma certificada."
    ),
    "1912000833": (
        "Auditoría integral disponible en ENTREGABLES_1912000833_V2; "
        "incluye reconciliación contable interna, pero no una liquidación autónoma certificada."
    ),
    "1912000835": (
        "Auditoría integral disponible en ENTREGABLES_1912000835_V2; "
        "incluye reconciliación contable interna, pero no una liquidación autónoma certificada."
    ),
}


def p(text: str, donor: int = 38, **kwargs):
    return g.p(text, donor=donor, **kwargs)


def h(text: str, *, donor: int = 37, page_break_before: bool = False):
    return g.h(text, donor=donor, page_break_before=page_break_before)


def slug(value: str) -> str:
    value = unicodedata.normalize("NFD", value)
    value = "".join(ch for ch in value if unicodedata.category(ch) != "Mn")
    return re.sub(r"[^A-Za-z0-9]+", "_", value).strip("_").upper()[:58]


def source_paths(case: dict) -> tuple[Path, Path, Path]:
    pagare = ROOT / f"PAGARÉ {case['n']}.pdf"
    extract = EXTRACT_DIR / f"EXTRACTO {case['cc']}.PDF"
    support = SUPPORT_DIR / f"CC {case['cc']} PAGARE {case['n']}.pdf"
    return pagare, extract, support


def paired_paths(case: dict, order: int) -> tuple[Path, Path]:
    folder = MAIN_OUT / f"{order:02d}_{case['n']}_{slug(case['primary'])}"
    demand = folder / f"01_DEMANDA_{case['n']}_PARA_COMPLETAR_NO_RADICAR.docx"
    caution = folder / f"02_CAUTELAR_{case['n']}_PARA_COMPLETAR_NO_RADICAR.docx"
    return demand, caution


def pdf_pages(path: Path) -> int:
    result = subprocess.run(["pdfinfo", str(path)], check=True, capture_output=True, text=True)
    match = re.search(r"^Pages:\s+(\d+)\s*$", result.stdout, re.MULTILINE)
    if not match:
        raise RuntimeError(f"No se obtuvo número de páginas: {path}")
    return int(match.group(1))


def sha(path: Path) -> str:
    digest = sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def short_sha(path: Path) -> str:
    return sha(path)[:16] + "…"


def signer_text(case: dict) -> str:
    items = [f"{case['primary']} — C.C. {g.fmt_id(case['cc'])} — primer firmante de control; calidad individual pendiente de cierre"]
    for name, ident in case["cos"]:
        items.append(f"{name} — C.C. {g.fmt_id(ident)} — firmante adicional; calidad individual pendiente de cierre")
    return " | ".join(items)


def neutralize_unverified_roles(text: str) -> str:
    replacements = (
        ("deudor y codeudora", "cada firmante —calidad individual por verificar—"),
        ("ambos obligados", "ambos firmantes —calidad individual por verificar—"),
        ("ambas obligadas", "ambas firmantes —calidad individual por verificar—"),
        ("las obligadas", "las firmantes —calidad individual por verificar—"),
        ("la codeudora", "la firmante adicional señalada históricamente como codeudora —calidad por verificar—"),
        ("La codeudora", "La firmante adicional señalada históricamente como codeudora —calidad por verificar—"),
        ("el codeudor", "el firmante adicional señalado históricamente como codeudor —calidad por verificar—"),
        ("El codeudor", "El firmante adicional señalado históricamente como codeudor —calidad por verificar—"),
        ("Hay codeudor", "Existe un firmante adicional señalado históricamente como codeudor —calidad por verificar—"),
    )
    for old, new in replacements:
        text = text.replace(old, new)
    return text


def periodicity(case: dict) -> str:
    if case["n"] == "1914000814":
        return "trimestrales"
    if case["n"] in g.MONTHLY_INSTALLMENT_CASES:
        return "mensuales"
    return "[COMPLETAR — PERIODICIDAD]"


def face_block(case: dict) -> str:
    installments = f"{case['installments']} cuotas {periodicity(case)}" if case.get("installments") else "[COMPLETAR — CUOTAS Y PERIODICIDAD]"
    cap = g.money(case["face_capital"]) if case.get("face_capital") is not None else "[COMPLETAR — CAPITAL FACIAL]"
    interest = g.money(case["face_interest"]) if case.get("face_interest") is not None else "[COMPLETAR — INTERÉS FACIAL]"
    date = case.get("face_date") or "[COMPLETAR — FECHA FACIAL]"
    maturity = case.get("maturity") or "[COMPLETAR — VENCIMIENTO FACIAL]"
    return f"Otorgamiento: {date}; {installments}; capital facial: {cap}; interés facial: {interest}; {g.facial_rate_text(case)}; vencimiento facial: {maturity}."


def gate_specs(case: dict) -> list[dict]:
    names = "; ".join([case["primary"]] + [name for name, _ in case["cos"]])
    return [
        p("G0 — AMARILLO — Vigencia normativa: contrastada para este paquete; repetir consulta oficial el día de presentación, incluida reglamentación de arbitraje, insolvencia, cuantía, tasas y canales."),
        p("G1 — AMARILLO/ROJO — Identidad y legitimación: nombres y cédulas fueron transcritos; [COMPLETAR — calidad individual de cada firma, estado civil cuando importe, tenedor actual y legitimación].", color=RED),
        p("G2 — ROJO — Personería: [COMPLETAR — certificado vigente, representante, poder, mensaje de datos, correo mercantil, sociedad apoderada si interviene, acto de representación/sustitución y correo RNA].", color=RED),
        p("G3 — ROJO — Jurisdicción y arbitraje: [COMPLETAR — revisar contrato, solicitud, anexos y pacto arbitral ejecutivo conforme a la Ley 2540 de 2025].", color=RED),
        p(f"G4 — ROJO — Competencia y cuantía: pista histórica de ciudad/agencia: {case.get('city') or 'no verificada'}. [COMPLETAR — domicilio actual, lugar de cumplimiento, despacho y SMLMV vigente].", color=RED),
        p("G5 — ROJO — Título ejecutivo: [COMPLETAR — claridad, expresividad, exigibilidad, liquidabilidad, tenedor y coincidencia con obligación subyacente].", color=RED),
        p("G6 — ROJO — Pagaré y llenado: [COMPLETAR — original, todas las páginas/reversos, fecha/persona/fuente de llenado, instrucciones y explicación de diferencias].", color=RED),
        p("G7 — ROJO — Incumplimiento: [COMPLETAR — una sola fecha y causa de exigibilidad, cláusula, evento, aceleración, comunicación y efecto por cada firmante].", color=RED),
        p("G8 — ROJO — Prescripción: [COMPLETAR — fecha jurídicamente válida de vencimiento, término individual, interrupciones, margen del artículo 94 CGP y control del artículo 317].", color=RED),
        p(f"G8.1 — REGISTRO INDIVIDUAL POR FIRMANTE — {names}: [COMPLETAR PARA CADA PERSONA — vencimiento jurídicamente adoptado; fecha límite de la acción cambiaria; interrupciones/suspensiones con soporte; fecha interna máxima de presentación; fecha de notificación y control del artículo 94 CGP; revisor y fecha].", color=RED),
        p("G9 — ROJO — Capital e intereses: [COMPLETAR — desembolso, refinanciación, todos los pagos y movimientos, memoria por periodo, tasa oficial, ausencia de anatocismo y saldo del día].", color=RED),
        p("G10 — ROJO — Insolvencia/sucesión/procesos: [COMPLETAR — consulta actual e individual de cada firmante, radicado, autoridad, fecha y efecto].", color=RED),
        p("G11 — ROJO — Justicia digital, anexos y cautela: [COMPLETAR — canales y fuente, índice coincidente, PDFs finales, activo actual individualizado, límite y oficio oficial].", color=RED),
    ]


def fiche_specs(case: dict, order: int) -> list[dict]:
    total = case["capital"] + case["interest"] + case["mora"]
    pagare, extract, support = source_paths(case)
    demand, caution = paired_paths(case, order)
    sources = [(pagare, pdf_pages(pagare)), (extract, pdf_pages(extract)), (support, pdf_pages(support))]
    alert = neutralize_unverified_roles(case.get("audit_alert") or "No existe memoria reproducible ni cierre de exigibilidad; completar todos los gates antes de promover el escrito.")
    asset = neutralize_unverified_roles(case.get("asset_hint") or "No se identificó un activo actual mediante fuente oficial; la cautela debe permanecer sin activar hasta individualizarlo.")
    specs = [
        p("FICHA DE CIERRE JURÍDICO — PARA COMPLETAR Y REVISAR — NO RADICAR", donor=7, bold=True, color=RED, size=14),
        p("CIRCULACIÓN RESTRINGIDA — CONTIENE DATOS PERSONALES", bold=True, color=RED),
        p("Documento interno complementario. No sustituye la demanda, la cautelar, la liquidación firmada ni la revisión de la abogada. Todo texto entre corchetes bloquea el cierre.", bold=True, color=RED),
        p(f"CASO {order:02d}/26 — PAGARÉ No. {case['n']} — OBLIGACIÓN No. 10-{case['n']}", donor=7, bold=True, color=NAVY),
        p("SEMÁFORO GENERAL: ROJO — NO APTO PARA FIRMA NI RADICACIÓN", bold=True, color=RED, size=13),
        h("0. CONTROL DE VERSIÓN Y TRAZABILIDAD"),
        p("Expediente interno: [COMPLETAR]. Versión de ficha: 1.0. Fecha de generación: 21 de julio de 2026. Corte documental y contable histórico: 6 de julio de 2026. Corte normativo y de consultas para presentar: [COMPLETAR — FECHA Y HORA].", color=RED),
        p("Custodio y ubicación del original: [COMPLETAR]. Elaboró: [COMPLETAR]. Control contable: [COMPLETAR]. Revisión jurídica: [COMPLETAR]. Abogada firmante: [COMPLETAR].", color=RED),
        p(f"Demanda vinculada: {demand.name} — SHA-256 {short_sha(demand)}. Cautelar vinculada: {caution.name} — SHA-256 {short_sha(caution)}."),
        p("Homologación: [COMPLETAR — identificar versión aprobada de demanda y cautelar y documentar cualquier modificación al texto fijo]. No se acreditó una versión canónica liberada para firma.", color=RED),
        p(DETAILED_AUDITS.get(case["n"], "No existe auditoría individual previa de profundidad equivalente; esta ficha y los soportes deben cerrarse íntegramente por el equipo jurídico."), color=AMBER),
        h("1. IDENTIFICACIÓN Y RESUMEN EJECUTIVO"),
        p(f"Acreedora documental: {g.LEGAL_NAME}, NIT 890.981.459-4. [CONFRONTAR CON CERTIFICADO VIGENTE].", color=RED),
        p(f"Firmantes: {signer_text(case)}.", color=RED),
        p(f"Producto literal del extracto: «{g.product_label(case)}». Primer cargo/apertura registrado: {case['ledger_date']} por {g.money_words(case['initial'])}. [NO CONFUNDIR CON DESEMBOLSO NETO SIN COMPROBANTE].", color=RED),
        p(f"Lectura facial de control: {face_block(case)} [CONFRONTAR ORIGINAL FÍSICO Y NO INFERIR MODALIDAD DE TASA].", color=RED),
        p(f"ALERTA CRÍTICA DE LLENADO: el interés facial ({g.money(case['face_interest'])}) coincide exactamente con el saldo histórico de interés del extracto ({g.money(case['interest'])}). La coincidencia ocurre en los 26 expedientes y no prueba por sí sola el valor originalmente pactado; exige trazabilidad documental de cuándo, por quién y con qué fuente se diligenció el pagaré.", bold=True, color=RED),
        p(f"Saldo histórico al {CUT}: capital {g.money_words(case['capital'])}; interés de plazo {g.money_words(case['interest'])}; mora {g.money_words(case['mora'])}; total {g.money_words(total)}. [ACTUALIZAR Y REPRODUCIR].", color=RED),
        h("2. DIAGNÓSTICO COMO JUEZ Y COMO DEFENSA"),
        p(f"Alerta específica: {alert}", bold=True, color=RED),
        p("Como juez: estos defectos impiden liberar el expediente y, según su naturaleza, pueden conducir a inadmisión, rechazo o remisión por falta de competencia, o negativa del mandamiento por insuficiencia sustancial del título."),
        p("Como defensa: revisar calidad de cada firmante, pago, acuerdos y movimientos internos, llenado contrario a instrucciones, prescripción, falta de legitimación, pacto arbitral, tasa excesiva, anatocismo, doble cobro, notificación y exceso cautelar."),
        p(f"Cautela/activo: {asset}", color=AMBER),
        h("3. MATRIZ DATO–SOPORTE MÍNIMA"),
        p(f"D01 — Pagaré y firmantes — {pagare.name}, pp. 1–2 y revisión integral de {sources[0][1]} páginas — EVIDENCIADO EN COPIA DIGITAL; original/custodia pendientes."),
        p(f"D02 — Datos faciales — {pagare.name}, p. 1 — EVIDENCIADOS SEGÚN LECTURA; modalidad de tasa y cualquier trazo tenue quedan por confirmar."),
        p(f"D03 — Capital, interés, mora y movimientos — {extract.name}, {sources[1][1]} páginas — EVIDENCIADOS COMO RESUMEN HISTÓRICO; NO REPRODUCIBLES SIN MEMORIA."),
        p(f"D04 — Identidad, solicitud, plan y pistas patrimoniales — {support.name}, {sources[2][1]} páginas — USO HISTÓRICO; revisar vigencia y página exacta."),
        p("D05 — Poder, certificado, arbitraje, insolvencia, domicilio/canales y activo actual — NO VERIFICADOS; bloquean cierre.", color=RED),
        p("[COMPLETAR — añadir para cada hecho final: código, proposición exacta, archivo, página, fecha, certeza, contradicción y decisión].", color=RED),
        h("4. GATES DEL PROTOCOLO"),
    ]
    specs.extend(gate_specs(case))
    specs.extend([
        h("5. INVENTARIO CONGELADO E ÍNDICE DE ANEXOS"),
        p(f"A01 — {pagare.name} — {sources[0][1]} páginas — SHA-256 {short_sha(pagare)} — EXISTE; título correcto seleccionado por prefijo «PAGARÉ» — fecha/versión: [COMPLETAR] — legibilidad: [COMPLETAR — INTEGRAL/PARCIAL Y OBSERVACIÓN; CONFRONTAR ORIGINAL].", color=RED),
        p(f"A02 — {extract.name} — {sources[1][1]} páginas — SHA-256 {short_sha(extract)} — EXISTE; corte {CUT} — versión/emisor: [COMPLETAR] — legibilidad: [COMPLETAR — INTEGRAL/PARCIAL Y OBSERVACIÓN].", color=RED),
        p(f"A03 — {support.name} — {sources[2][1]} páginas — SHA-256 {short_sha(support)} — EXISTE; paquete de apoyo histórico — fecha/versión: [COMPLETAR] — legibilidad: [COMPLETAR — INTEGRAL/PARCIAL Y OBSERVACIÓN].", color=RED),
        p("A04 — [COMPLETAR — certificado vigente de CREARCOOP y, si interviene, de la sociedad apoderada].", color=RED),
        p("A05 — [COMPLETAR — poder, mensaje de datos, correos, sustitución/designación y documentos de la abogada].", color=RED),
        p("A06 — [COMPLETAR — certificación contable, movimientos completos, pagos posteriores y memoria actualizada].", color=RED),
        p("A07 — [COMPLETAR — acta de llenado, custodia, no circulación, contrato completo y arbitraje].", color=RED),
        p("A08 — [COMPLETAR — domicilio/canales con fuente; insolvencia, sucesión y procesos paralelos].", color=RED),
        p("A09 — [COMPLETAR — fuente oficial actual del activo y documentos particulares de la cautela].", color=RED),
        p("[ELIMINAR ANEXOS INEXISTENTES; numerar archivo final, fecha, páginas, tamaño, hash y proposición que soporta].", color=RED),
        h("6. MEMORIA DE LIQUIDACIÓN — HOJA DE CIERRE"),
        p(f"Referencia histórica al {CUT}: capital {g.money(case['capital'])} + interés de plazo {g.money(case['interest'])} + mora {g.money(case['mora'])} = {g.money(total)}."),
        p("L01 — Capital original/cargo: [COMPLETAR — naturaleza, fecha, soporte, monto bruto y neto].", color=RED),
        p("L02 — Capital amortizado: [COMPLETAR — cada pago, fecha, comprobante y orden de imputación].", color=RED),
        p("L03 — Capital insoluto al presentar: [COMPLETAR — certificación firmada y conciliación].", color=RED),
        p("L04 — Interés de plazo: [COMPLETAR — capital base, fecha inicial/final, tasa, modalidad, conversión, días, pagos y subtotal por periodo].", color=RED),
        p("L05 — Mora histórica: [COMPLETAR — capital de cada cuota o saldo acelerado, exigibilidad, tasa oficial por periodo, días, pagos y subtotal, sin solape].", color=RED),
        p("L06 — Mora posterior al corte: [COMPLETAR — continuar únicamente desde el día siguiente al último periodo liquidado, sobre capital vencido validado].", color=RED),
        p("L07 — Otros rubros: [COMPLETAR — seguro/costo/ajuste con fuente, o eliminar]. No capitalizar intereses salvo supuesto legal excepcional expresamente acreditado y revisado, incluido el control del artículo 886 del Código de Comercio; no cobrar rubros no titulados.", color=RED),
        p("TOTAL PARA CUANTÍA AL PRESENTAR = capital validado + accesorios causados y exigibles computables. [COMPLETAR — total, SMLMV vigente y categoría].", bold=True, color=RED),
        h("7. MATRIZ CAUTELAR — ACTIVAR SOLO CON FUENTE ACTUAL"),
        p("MC-01 — Firmante afectado y calidad acreditada: [COMPLETAR] — bien/derecho exacto: [COMPLETAR] — identificador: [COMPLETAR] — tercero/oficina: [COMPLETAR] — fuente oficial y fecha: [COMPLETAR] — inembargabilidad/preferencia y límite especial según el tipo de bien —incluido el artículo 593.10 CGP cuando corresponda—: [COMPLETAR] — límite global del artículo 599 CGP: [COMPLETAR] — monto: [COMPLETAR] — utilidad/proporcionalidad: [COMPLETAR] — módulo: [ACTIVO/ELIMINADO].", color=RED),
        p("MC-02 — [REPETIR SOLO SI EXISTE OTRA MEDIDA INDIVIDUALIZADA Y SOPORTADA; DE LO CONTRARIO, ELIMINAR].", color=RED),
        p("No se incluyen búsquedas indiscriminadas, ‘todos los bienes’, ‘todos los bancos’, porcentajes automáticos ni medidas sin fuente actual. Si no existe una medida individualizada y soportada, G11 no se cierra y el escrito cautelar no se libera.", bold=True, color=RED),
        h("8. ACTA FINAL DE CONTROL"),
        p("☐ Cero corchetes, alternativas, instrucciones, comentarios, resaltados y datos ajenos."),
        p("☐ Razón social, personería, partes, firmas, título, obligación y anexos coinciden."),
        p("☐ Original, llenado, exigibilidad, prescripción, pagos, tasa y memoria cerrados."),
        p("☐ Jurisdicción/arbitraje, competencia, cuantía, insolvencia y procesos verificados."),
        p("☐ Canales, juramento digital, envío previo/excepción, cautela y oficios validados."),
        p("☐ Revisión como juez, defensa y consistencia; aprobación y firma de abogada responsable."),
        p("Resultado del cierre: [COMPLETAR — ROJO / AMARILLO / VERDE]. Responsable: [●]. Fecha/hora: [●]. Firma: [●].", color=RED),
        h("9. AGENDA DE SEGUIMIENTO"),
        p("PRIORIDAD 1 — Obtener personería y certificado vigente. Responsable: [●]. Fecha límite: [●]. Evidencia: [●].", color=RED),
        p("PRIORIDAD 2 — Cerrar original, llenado, contrato/arbitraje y exigibilidad. Responsable: [●]. Fecha: [●]. Evidencia: [●].", color=RED),
        p("PRIORIDAD 3 — Conciliar saldo, pagos, movimientos internos e intereses. Responsable: [●]. Fecha: [●]. Evidencia: [●].", color=RED),
        p("PRIORIDAD 4 — Consultar insolvencia/procesos, domicilio/canales y activos actuales. Responsable: [●]. Fecha: [●]. Evidencia: [●].", color=RED),
        p("PRIORIDAD 5 — Actualizar norma, despacho/canal, anexos, demanda y cautelar; ejecutar triple revisión y firma. Responsable: [●]. Fecha: [●].", color=RED),
        h("10. TRIPLE REVISIÓN, CERTIFICACIÓN Y FIRMAS"),
        p("R-01 — Perspectiva judicial: [COMPLETAR — causal concreta de inadmisión, negativa del mandamiento o limitación cautelar; respuesta, soporte, resultado y revisor].", color=RED),
        p("R-02 — Perspectiva de contradicción: [COMPLETAR — defensas sobre título, llenado, pago, prescripción, legitimación, competencia, arbitraje, intereses, insolvencia, notificación y cautela; respuesta, soporte, resultado y revisor].", color=RED),
        p("R-03 — Consistencia: [COMPLETAR — identidad entre poder, pagaré, memoria, demanda, cautelar, anexos y firma; resultado y revisor].", color=RED),
        p("Con base exclusivamente en los documentos relacionados en esta ficha, los responsables que suscriben deberán certificar que confrontaron demanda, cautelar, título, poder, memoria e índice; que cada dato y cifra crítica remite a archivo y página; que no subsisten contradicciones, campos, alternativas, instrucciones, comentarios, resaltados ni datos de otro expediente; que G0–G11 cumplen; que el saldo y la inexistencia de pago posterior se confirmaron en la fecha y hora indicadas; y que la norma decisiva se verificó en fuente oficial.", color=RED),
        p("Elaboró: [COMPLETAR]. Control contable: [COMPLETAR]. Revisión judicial crítica: [COMPLETAR]. Revisión desde la contradicción: [COMPLETAR]. Revisó y aprobó la abogada firmante: [COMPLETAR]. Fecha y hora: [COMPLETAR].", color=RED),
        p("MÁXIMO ESTADO PERMITIDO: VERDE — APTO PARA REVISIÓN Y FIRMA HUMANA. Esta clasificación no sustituye la revisión, decisión, firma ni responsabilidad profesional de la abogada.", bold=True, color=GREEN),
        p("AUTORIZACIÓN AUTOMÁTICA DE RADICACIÓN: NO. La radicación exige firma profesional y ejecución documentada de los controles finales inmediatamente previos a la presentación.", bold=True, color=RED),
        p("Nota: la marca NO RADICAR solo puede retirarse después de acta VERDE firmada por la abogada responsable.", bold=True, color=RED),
    ])
    return specs


def readme_specs() -> list[dict]:
    return [
        p("GUÍA — 26 FICHAS DE CIERRE JURÍDICO", donor=7, bold=True, color=NAVY, size=16),
        p("CIRCULACIÓN RESTRINGIDA — CONTIENE DATOS PERSONALES", bold=True, color=RED),
        p("ESTADO DEL PAQUETE: CONTROL INTERNO — NO RADICAR", bold=True, color=RED, size=13),
        p("Este paquete complementa las 26 demandas y 26 cautelares. Cada ficha concentra las salidas 1–4 y 7–10 del protocolo: viabilidad, dato–soporte, faltantes, alertas, índice, memoria, acta y agenda."),
        h("REGLA DE USO"),
        p("Asignar una ficha y su pareja demanda/cautelar a un abogado. Completar únicamente con prueba legible y actual. Todo corchete es un bloqueo; una marca verde solo puede emitirla y firmarla la abogada responsable después de revisar el expediente completo."),
        h("FUENTES CONGELADAS"),
        p("Se seleccionó exclusivamente el PDF cuyo nombre inicia «PAGARÉ ». Cada ficha registra páginas y huella abreviada de ese título, del extracto y del soporte. La matriz de semáforo conserva las huellas completas de fuentes y productos; el manifiesto separado congela los 78 PDF fuente con nombre, ruta, tamaño, páginas y SHA-256."),
        h("ORDEN DE CIERRE"),
        p("1. Personería y certificado. 2. Original, llenado y custodia. 3. Contrato y arbitraje. 4. Exigibilidad y prescripción. 5. Pagos y memoria. 6. Insolvencia/procesos. 7. Competencia/canales. 8. Activo y cautela. 9. Anexos. 10. Triple revisión, firma y radicación."),
        h("PROHIBICIONES"),
        p("No trasladar alertas internas a la copia para firma; no borrar marcadores sin soporte; no convertir pistas patrimoniales históricas en hechos actuales; no pedir búsquedas generales; no radicar con saldo del 6 de julio de 2026 sin actualización."),
        h("AUDITORÍAS INDIVIDUALES PREEXISTENTES"),
        p("Los pagarés 1911000986, 1912000810, 1912000833 y 1912000835 cuentan con auditorías individuales en las carpetas ENTREGABLES correspondientes. Solo 1911000986 tiene una memoria de liquidación autónoma. Los otros 22 casos requieren cierre individual completo con esta ficha."),
        h("ESTADO MÁXIMO Y RESPONSABILIDAD"),
        p("El único estado verde admisible es «APTO PARA REVISIÓN Y FIRMA HUMANA». AUTORIZACIÓN AUTOMÁTICA DE RADICACIÓN: NO. La firma profesional y los controles finales inmediatamente previos a la presentación siguen siendo obligatorios.", bold=True, color=RED),
    ]


def xml_text(docx_path: Path) -> str:
    with ZipFile(docx_path) as archive:
        data = archive.read("word/document.xml").decode("utf-8", "ignore")
    return re.sub(r"<[^>]+>", " ", data)


def generate() -> None:
    if OUT.exists():
        shutil.rmtree(OUT)
    CONTROL.mkdir(parents=True)

    readme = CONTROL / "00_LEAME_PRIMERO_FICHAS_CIERRE_NO_RADICAR.docx"
    g.make_docx(TEMPLATE, readme, readme_specs(), "Guía fichas de cierre — no radicar")
    produced: list[Path] = []
    rows: list[dict] = []
    source_rows: list[dict] = []

    for order, case in enumerate(g.CASES, 1):
        pagare, extract, support = source_paths(case)
        demand, caution = paired_paths(case, order)
        for source in (pagare, extract, support, demand, caution):
            if not source.is_file() or source.stat().st_size == 0:
                raise FileNotFoundError(source)
        for source_type, source in (("PAGARÉ CORRECTO", pagare), ("EXTRACTO", extract), ("SOPORTE", support)):
            source_rows.append({
                "orden": order,
                "pagare": case["n"],
                "tipo": source_type,
                "nombre_exacto": source.name,
                "ruta_fuente": str(source),
                "tamano_bytes": source.stat().st_size,
                "paginas": pdf_pages(source),
                "sha256": sha(source),
                "estado": "CONGELADA COMO FUENTE DE CONTROL — NO ACREDITA VIGENCIA POR SÍ SOLA",
            })
        folder = OUT / f"{order:02d}_{case['n']}_{slug(case['primary'])}"
        folder.mkdir()
        output = folder / f"00_FICHA_CIERRE_{case['n']}_PARA_COMPLETAR_NO_RADICAR.docx"
        g.make_docx(TEMPLATE, output, fiche_specs(case, order), f"Ficha de cierre {case['n']} — no radicar")
        produced.append(output)
        total = case["capital"] + case["interest"] + case["mora"]
        text = xml_text(output)
        rows.append({
            "orden": order,
            "pagare": case["n"],
            "primer_firmante_control": case["primary"],
            "cc": case["cc"],
            "otros_firmantes": " | ".join(f"{name} — {ident}" for name, ident in case["cos"]) or "CONFIRMAR AUSENCIA",
            "semaforo": "ROJO",
            "capital_historico": case["capital"],
            "interes_historico": case["interest"],
            "mora_historica": case["mora"],
            "total_historico": total,
            "pagare_paginas": pdf_pages(pagare),
            "extracto_paginas": pdf_pages(extract),
            "soporte_paginas": pdf_pages(support),
            "pagare_sha256": sha(pagare),
            "extracto_sha256": sha(extract),
            "soporte_sha256": sha(support),
            "demanda_sha256": sha(demand),
            "cautelar_sha256": sha(caution),
            "auditoria_individual_preexistente": "SÍ" if case["n"] in DETAILED_AUDITS else "NO",
            "marcadores": text.count("["),
            "estado": "PARA COMPLETAR — NO RADICAR",
        })

    matrix = CONTROL / "01_MATRIZ_SEMAFORO_FUENTES_26_CASOS.csv"
    with matrix.open("w", newline="", encoding="utf-8-sig") as handle:
        writer = csv.DictWriter(handle, fieldnames=list(rows[0]))
        writer.writeheader()
        writer.writerows(rows)

    if len(source_rows) != 78:
        raise RuntimeError(f"Se esperaban 78 fuentes y se obtuvieron {len(source_rows)}")
    source_manifest = CONTROL / "03_MANIFIESTO_FUENTES_78_PDF.csv"
    with source_manifest.open("w", newline="", encoding="utf-8-sig") as handle:
        writer = csv.DictWriter(handle, fieldnames=list(source_rows[0]))
        writer.writeheader()
        writer.writerows(source_rows)

    manifest = CONTROL / "02_MANIFIESTO_SHA256.txt"
    all_files = sorted([readme, matrix, source_manifest] + produced)
    manifest.write_text("\n".join(f"{sha(path)}  {path.relative_to(OUT)}" for path in all_files) + "\n", encoding="utf-8")

    if ZIP_PATH.exists():
        ZIP_PATH.unlink()
    with ZipFile(ZIP_PATH, "w", compression=ZIP_DEFLATED) as archive:
        for path in sorted(OUT.rglob("*")):
            if path.is_file():
                archive.write(path, Path(OUT.name) / path.relative_to(OUT))

    print(f"OUT={OUT}")
    print(f"FICHAS={len(produced)}")
    print(f"DOCX={len(produced) + 1}")
    print(f"ZIP={ZIP_PATH}")


if __name__ == "__main__":
    generate()
