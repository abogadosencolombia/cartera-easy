from copy import deepcopy
from hashlib import sha256
from importlib.util import module_from_spec, spec_from_file_location
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile

from lxml import etree


ROOT = Path("/code/DEMANDAS")
OUT = ROOT / "ENTREGABLES_1911000986_V2"
BASE_SCRIPT = ROOT / "_work_1911000986" / "generar_entregables_1911000986.py"
DEMAND_TEMPLATE = ROOT / "MODELO.docx"
CAUTION_TEMPLATE = ROOT / "MODELO MEDIDAS.docx"

spec = spec_from_file_location("base1911", BASE_SCRIPT)
base = module_from_spec(spec)
spec.loader.exec_module(base)

NS_W = base.NS_W
NS = base.NS
W = f"{{{NS_W}}}"
RED = "C00000"
AMBER = "BF9000"
GREEN = "548235"
NAVY = "1F4E78"


def p(text, donor=38, **kwargs):
    return base.p(donor, text, **kwargs)


def h(text, *, donor=37, page_break_before=False):
    return base.heading(text, donor=donor, page_break_before=page_break_before)


def qn(tag):
    return f"{W}{tag}"


def _set_cell_text(cell, value, *, bold=False, color=None, size=8):
    paragraph = etree.SubElement(cell, qn("p"))
    ppr = etree.SubElement(paragraph, qn("pPr"))
    spacing = etree.SubElement(ppr, qn("spacing"))
    spacing.set(qn("after"), "40")
    spacing.set(qn("line"), "220")
    spacing.set(qn("lineRule"), "auto")
    run = etree.SubElement(paragraph, qn("r"))
    rpr = etree.SubElement(run, qn("rPr"))
    fonts = etree.SubElement(rpr, qn("rFonts"))
    fonts.set(qn("ascii"), "Arial")
    fonts.set(qn("hAnsi"), "Arial")
    fonts.set(qn("cs"), "Arial")
    if bold:
        etree.SubElement(rpr, qn("b"))
        etree.SubElement(rpr, qn("bCs"))
    if color:
        c = etree.SubElement(rpr, qn("color"))
        c.set(qn("val"), color)
    for tag in ("sz", "szCs"):
        node = etree.SubElement(rpr, qn(tag))
        node.set(qn("val"), str(int(size * 2)))
    text = etree.SubElement(run, qn("t"))
    text.set("{http://www.w3.org/XML/1998/namespace}space", "preserve")
    text.text = str(value)


def make_table(headers, rows, widths=None):
    widths = widths or [2200] * len(headers)
    table = etree.Element(qn("tbl"))
    props = etree.SubElement(table, qn("tblPr"))
    table_width = etree.SubElement(props, qn("tblW"))
    table_width.set(qn("w"), str(sum(widths)))
    table_width.set(qn("type"), "dxa")
    layout = etree.SubElement(props, qn("tblLayout"))
    layout.set(qn("type"), "fixed")
    borders = etree.SubElement(props, qn("tblBorders"))
    for edge in ("top", "left", "bottom", "right", "insideH", "insideV"):
        border = etree.SubElement(borders, qn(edge))
        border.set(qn("val"), "single")
        border.set(qn("sz"), "4")
        border.set(qn("space"), "0")
        border.set(qn("color"), "A6A6A6")

    grid = etree.SubElement(table, qn("tblGrid"))
    for width in widths:
        col = etree.SubElement(grid, qn("gridCol"))
        col.set(qn("w"), str(width))

    for row_index, values in enumerate([headers] + list(rows)):
        tr = etree.SubElement(table, qn("tr"))
        if row_index == 0:
            trpr = etree.SubElement(tr, qn("trPr"))
            etree.SubElement(trpr, qn("tblHeader"))
        for col_index, value in enumerate(values):
            tc = etree.SubElement(tr, qn("tc"))
            tcpr = etree.SubElement(tc, qn("tcPr"))
            tcw = etree.SubElement(tcpr, qn("tcW"))
            tcw.set(qn("w"), str(widths[col_index]))
            tcw.set(qn("type"), "dxa")
            margins = etree.SubElement(tcpr, qn("tcMar"))
            for side in ("top", "left", "bottom", "right"):
                margin = etree.SubElement(margins, qn(side))
                margin.set(qn("w"), "70")
                margin.set(qn("type"), "dxa")
            if row_index == 0:
                shading = etree.SubElement(tcpr, qn("shd"))
                shading.set(qn("fill"), NAVY)
            _set_cell_text(
                tc,
                value,
                bold=row_index == 0,
                color="FFFFFF" if row_index == 0 else None,
                size=7.5 if len(headers) >= 5 else 8,
            )
    return table


def inject_tables(path, table_map):
    with ZipFile(path, "r") as zin:
        members = {info.filename: (info, zin.read(info.filename)) for info in zin.infolist()}
    info, data = members["word/document.xml"]
    root = etree.fromstring(data)
    for paragraph in list(root.xpath("//w:p", namespaces=NS)):
        value = "".join(paragraph.xpath(".//w:t/text()", namespaces=NS)).strip()
        if value in table_map:
            paragraph.getparent().replace(paragraph, make_table(**table_map[value]))
    members["word/document.xml"] = (
        info,
        etree.tostring(root, xml_declaration=True, encoding="UTF-8", standalone=True),
    )
    tmp = path.with_suffix(".tmp.docx")
    with ZipFile(tmp, "w", compression=ZIP_DEFLATED) as zout:
        for name, (member_info, member_data) in members.items():
            zout.writestr(member_info, member_data)
    tmp.replace(path)


def make(path, template, specs, title, tables=None):
    base.make_docx(template, path, specs, title)
    if tables:
        inject_tables(path, tables)


INFORME = [
    p("INFORME DE VIABILIDAD — EXPEDIENTE 1911000986", donor=7, bold=True, size=16),
    p("Control interno reservado · versión 2 · 18 de julio de 2026", bold=True, color=NAVY),
    p("DECISIÓN: ROJO — NO APTO PARA FIRMA NI RADICACIÓN", bold=True, color=RED, size=13),
    p("La acción no se descarta. Existe una ruta jurídica seria para reclamar únicamente el saldo real, pero hoy no puede afirmarse de manera responsable la exigibilidad ni el importe de todos los accesorios. Deben cerrarse primero los gates G2, G3, G5, G6, G7, G9 y G10."),
    h("1. CONCLUSIÓN EJECUTIVA"),
    p("El pagaré No. 1911000986 contiene firma de la deudora, promesa de pago, beneficiario, monto facial y vencimiento. El extracto identifica la misma obligación y permite conciliar exactamente el capital: $12.000.000 de cargo menos $8.254.065 de abonos equivale a $3.745.935 de saldo al 6 de julio de 2026. Esto permite estructurar una futura acción ejecutiva por la parte no pagada, conforme a los artículos 624 y 782 del Código de Comercio y a la cláusula quinta del pagaré, siempre que CREARCOOP pruebe qué acordó en el compromiso de enero de 2026, cómo ejerció la aceleración y cuál es el saldo actual."),
    p("Legitimación nominal: el formato del pagaré aún dice “COOPERATIVA DE AHORRO Y CRÉDITO CREAR LTDA.”. El certificado de Cámara informa que la misma entidad cambió desde esa denominación a “COOPERATIVA DE AHORRO Y CRÉDITO CREAR”, sigla CREARCOOP, por reforma inscrita el 17 de noviembre de 2021. La continuidad debe probarse con un certificado actualizado al presentar; no basta el certificado de 5 de febrero de 2026."),
    p("El problema determinante no es que el pagaré muestre $12.000.000 mientras se pretenda menos. El problema es que las instrucciones exigían llenar el capital con lo adeudado al momento del diligenciamiento, y el expediente no explica por qué se escribió el capital original. La estrategia no debe ocultar esa diferencia: debe limitar la pretensión al saldo real, documentar su reconciliación y estar preparada para una adecuación judicial del importe."),
    p("El movimiento CO 1000183 es el principal bloqueo. El extracto lo denomina “GENERACIÓN COMPROMISO DE PAGO” y registra abonos contables por $4.970.391 a capital y $408.509 a mora. Sin el documento fuente no puede saberse si hubo acuerdo de pago, condonación, reestructuración, traslado contable, sustitución, novación o condiciones de reviviscencia. Ninguna de esas alternativas puede escogerse por inferencia. El capital sí concilia; los intereses no: la diferencia visible entre cargos y abonos de mora es $9.881, no el saldo informado de $67.662."),
    h("2. RUTA DE SALVAMENTO"),
    p("Ruta A — aceleración: acreditar que, tras un incumplimiento determinado, CREARCOOP ejerció la facultad de la cláusula decimocuarta el 6 de julio de 2026 y diligenció el pagaré ese mismo día. Esta ruta concilia el vencimiento escrito con la carta separada, que ordena usar el día del llenado. Requiere soporte contemporáneo o certificación ex post veraz y trazable; no se puede antedatar."),
    p("Ruta B — compromiso: si CO 1000183 modificó el plazo o fijó nuevas cuotas, narrar exclusivamente lo que el acuerdo firmado disponga, demostrar su incumplimiento y establecer si el título original conservó exigibilidad. La cláusula décima contiene una estipulación de no novación para reestructuraciones, pero no reemplaza el examen del acuerdo concreto ni de sus efectos."),
    p("Ruta C — abstención: si no se acredita A o B, no presentar demanda. La fecha facial por sí sola no explica por qué la obligación, cuyo plan ordinario terminaba el 7 de octubre de 2026, fue anticipada al 6 de julio de 2026."),
    h("3. SEMÁFORO DE GATES"),
    p("[[TABLA_GATES]]"),
    p("Control de cuantía: el SMLMV transitorio de 2026 es $1.750.905 y cuarenta (40) SMLMV equivalen a $70.036.200. El saldo conocido de $5.484.532 es de mínima cuantía, sin perjuicio de actualizar el importe al día de presentación."),
    h("4. APRECIACIÓN COMO CONTRAPARTE"),
    p("La defensa más fuerte sería llenar el título en contra de las instrucciones: capital facial original frente a saldo insoluto y dos reglas distintas para el vencimiento. Seguirían pago/acuerdo, cobro de lo no debido, liquidación opaca, ausencia de ejercicio probado de la aceleración, personería, pacto arbitral, prescripción, notificación y exceso cautelar."),
    p("El documento CO 1000183 permitiría además alegar que la cooperativa aplicó contablemente una reducción sin explicar su causa y que los dos pagos de $832.750 fueron realizados bajo un acuerdo posterior. Si el acreedor no aporta el acuerdo, la contraparte puede pedir exhibición y usar esa omisión en su teoría probatoria. También puede resaltar la asimetría del llenado: el interés escrito coincide exactamente con el extracto al corte, mientras el capital quedó por el monto original."),
    h("5. APRECIACIÓN COMO JUEZ"),
    p("En el umbral del artículo 430 del CGP, un juez podría librar mandamiento por el saldo menor si el conjunto documental permite establecerlo, o negar/limitar conceptos si no son claros y exigibles. La divergencia de instrucciones no produce inexorablemente nulidad del título, pero sí obliga a establecer lo efectivamente pactado. Mientras no exista certificación suficiente, la ruta conservadora es reclamar el capital insoluto actualizado, la mora futura desde una aceleración probada y las costas, sin incluir los $1.670.935 ni los $67.662 históricos."),
    p("La cautelar FOPEP no puede activarse con desprendibles de 2022. Debe acreditarse condición actual de pensionada, pagador, mesada neta, descuentos y afectación residual. El 50 % es un máximo legal para créditos cooperativos, no el porcentaje automático del caso."),
    h("6. ALERTA DE HOMOLOGACIÓN"),
    p("El protocolo identifica como inmutables FORMATO_DEMANDA_EJECUTIVA_CREARCOOP_HOMOLOGADO_v2.0.docx y FORMATO_MEDIDAS_CAUTELARES_CREARCOOP_HOMOLOGADO_v2.0.docx. Esos nombres no aparecen en Drive ni en la carpeta local. Este paquete conserva membrete, tamaño, márgenes, encabezado y pie de MODELO.docx y MODELO MEDIDAS.docx, pero no puede certificarse como plantilla canónica v2.0."),
    p("Además, la regla azul del modelo que permite omitir la aceleración cuando el vencimiento del pagaré coincide con el corte contable es insuficiente aquí: el plan ordinario termina después. Cambio mínimo propuesto para homologación: exigir módulo de aceleración siempre que el vencimiento diligenciado sea anterior al vencimiento ordinario, aunque coincida con el corte."),
]

GATES_ROWS = [
    ("G0", "Vigencia oficial", "AMARILLO", "Normas decisivas verificadas; repetir control el día de la radicación."),
    ("G1", "Identidad y legitimación", "AMARILLO", "Nombre, cédula, pagaré y obligación coinciden; faltan certificado vigente, custodia y no circulación."),
    ("G2", "Personería y firmante", "ROJO", "El poder común no individualiza el asunto; modelo y designación señalan profesionales distintas."),
    ("G3", "Jurisdicción/arbitraje", "ROJO", "No se aportó el contrato completo ni certificación sobre pacto arbitral ejecutivo."),
    ("G4", "Competencia y cuantía", "AMARILLO", "Mínima cuantía y Bogotá están sustentadas de forma documental; actualizar al presentar."),
    ("G5", "Título ejecutivo", "ROJO", "Falta establecer el efecto de CO 1000183 y la exigibilidad del saldo."),
    ("G6", "Requisitos y llenado", "ROJO", "Capital y vencimiento deben reconciliarse con ambas instrucciones."),
    ("G7", "Mora/aceleración", "ROJO", "No existe soporte del evento, ejercicio y fecha de aceleración."),
    ("G8", "Prescripción", "AMARILLO", "Facialmente vence 06/07/2029; depende de validar el vencimiento y controlar art. 94 CGP."),
    ("G9", "Capital/intereses", "ROJO", "Saldo sin actualización; interés de plazo no separa causado de futuro; CO 1000183 no explicado."),
    ("G10", "Insolvencia/procesos", "ROJO", "No hay consultas oficiales recientes documentadas."),
    ("G11", "Digital/anexos/cautela", "ROJO", "Canales de 2022 y FOPEP histórico; cautela no individualizada en forma actual."),
]


MATRIZ = [
    p("MATRIZ DATO–SOPORTE — EXPEDIENTE 1911000986", donor=7, bold=True, size=16),
    p("Control interno reservado · versión 2 · 18 de julio de 2026", bold=True, color=NAVY),
    p("Regla de uso: solo los datos EVIDENCIADOS o COINCIDENTES pueden pasar al cuerpo judicial. Los datos CONTRADICTORIOS, NO VERIFICADOS o INCOMPLETOS bloquean la liberación.", color=RED, bold=True),
    p("[[TABLA_MATRIZ]]"),
    h("NOTAS DE LECTURA"),
    p("1. Las fechas del cronograma se leen en formato mes/día/año: la cuota 48 figura 10/07/2026, es decir, 7 de octubre de 2026. El pagaré y el extracto usan 6 de julio de 2026 como corte/vencimiento."),
    p("2. El archivo 1911000986.pdf sin el prefijo “PAGARÉ” se conserva para trazabilidad, pero no es el título seleccionado ni debe incluirse en el índice judicial. Es visualmente altamente consistente con el instrumento antes de llenarse, pero los metadatos de los PDF no prueban cuándo se escribieron físicamente los campos ni sustituyen original, custodia o peritaje."),
    p("3. El acta de diligenciamiento, la certificación contable y la certificación de custodia no son solemnidades universales del pagaré. En este caso son soportes probatorios internos necesarios para resolver contradicciones concretas."),
]

MATRIZ_ROWS = [
    ("Identidad", "María Amelia Cabiedes Plata — C.C. 41.526.686", "Pagaré p. 1; extracto pp. 1–3; CC/solicitud pp. 1, 3 y 5", "COINCIDENTE"),
    ("Acreedora", "Pagaré usa razón histórica con LTDA.; certificado registra razón vigente sin LTDA., misma sigla CREARCOOP", "Pagaré p. 1; certificado común pp. 1–2", "COINCIDENTE / ACTUALIZAR"),
    ("Calidad", "Deudora principal única; no aparece codeudor", "Pagaré pp. 1–2; solicitud p. 3", "EVIDENCIADO"),
    ("Título", "Pagaré No. 1911000986", "PAGARÉ 1911000986.pdf, p. 1", "EVIDENCIADO"),
    ("Obligación", "10-1911000986", "Extracto pp. 1–3; liquidación p. 1", "COINCIDENTE"),
    ("Otorgamiento", "7 de octubre de 2022", "Pagaré p. 1; liquidación p. 1", "COINCIDENTE"),
    ("Modalidad", "Microcrédito empresarial", "Extracto pp. 1–3; liquidación p. 1", "COINCIDENTE"),
    ("Monto inicial", "$12.000.000", "Pagaré p. 1; liquidación p. 1; extracto p. 1", "COINCIDENTE"),
    ("Tasa", "26,82 % E.A.; 24,00 % N.A.M.V.", "Pagaré p. 1; liquidación p. 1", "COINCIDENTE"),
    ("Plan", "48 cuotas de $391.222; final ordinario 7/10/2026", "Liquidación pp. 1–2", "EVIDENCIADO"),
    ("Neto", "$7.725.069; refinancia obligación 1911000712 y descuenta otros conceptos", "Liquidación p. 1", "EVIDENCIADO"),
    ("Vencimiento facial", "6 de julio de 2026", "Pagaré p. 1", "EVIDENCIADO"),
    ("Aceleración", "Cláusula 14 permite vencimiento anticipado; ejercicio y fecha no documentados", "Pagaré p. 1; faltan soporte y compromiso", "NO VERIFICADO"),
    ("Instrucción capital", "Campo debía reflejar lo adeudado según libros/registros", "Pagaré p. 2, cláusula 15; carta p. 3", "EVIDENCIADO"),
    ("Instrucción fecha", "Cláusula 15: inicio de mora; carta separada: día de llenado", "Pagaré pp. 2–3", "CONTRADICTORIO"),
    ("Campo agencia", "La instrucción ordena llenarlo, pero permanece vacío; no es requisito esencial por sí solo", "Pagaré pp. 1–2", "INCUMPLIMIENTO FORMAL"),
    ("Capital facial", "$12.000.000", "Pagaré p. 1", "EVIDENCIADO"),
    ("Capital a corte", "$3.745.935 al 6/7/2026", "Extracto p. 3", "EVIDENCIADO"),
    ("Interés de plazo", "$1.670.935 al 6/7/2026, sin memoria reproducible", "Pagaré p. 1; extracto p. 3", "INCOMPLETO"),
    ("Mora", "$67.662 al 6/7/2026, sin memoria reproducible", "Extracto p. 3", "INCOMPLETO"),
    ("Compromiso", "CO 1000183 del 31/1/2026 por $5.378.900", "Extracto p. 2", "INCOMPLETO"),
    ("Pagos posteriores", "$832.750 el 28/4/2026 y $832.750 el 25/5/2026", "Extracto p. 3", "EVIDENCIADO"),
    ("Saldo total", "$5.484.532 al 6/7/2026; no actualizado a presentación", "Extracto p. 3", "INCOMPLETO"),
    ("Domicilio", "Carrera 8G No. 159B-25, Bogotá D.C., dato de 2022", "Liquidación p. 1; solicitud p. 3", "EVIDENCIADO/ANTIGUO"),
    ("Correo", "Grafía manuscrita no suficientemente inequívoca", "Solicitud p. 3", "ILEGIBLE"),
    ("Pensión", "FOPEP, julio/agosto de 2022; neto histórico $1.261.086,24", "CC/soportes pp. 6–7", "EVIDENCIADO/ANTIGUO"),
    ("Activo inmueble", "Declaración de vivienda propia sin matrícula ni titularidad actual", "Solicitud p. 3", "NO VERIFICADO"),
    ("Personería", "Poder común a sociedad; designación de Nubia; modelo nombra Anggie", "Soportes comunes y modelos", "CONTRADICTORIO"),
]


FALTANTES = [
    p("FALTANTES BLOQUEANTES Y ORDEN DE CIERRE", donor=7, bold=True, size=16),
    p("Expediente 1911000986 · control interno reservado", bold=True, color=NAVY),
    p("No basta con obtener documentos: cada uno debe resolver la pregunta indicada. Un soporte genérico no cierra el gate.", bold=True, color=RED),
    p("[[TABLA_FALTANTES]]"),
    h("CRITERIO DE PARADA"),
    p("Si el compromiso CO 1000183 no aparece, si contradice la permanencia del título o si no existe una explicación contable reproducible, detener la radicación. Si el soporte de aceleración no permite fijar una sola fecha de exigibilidad, detener la radicación. Si no hay cautela actual individualizada, conservar el escrito cautelar como módulo no activado y definir correctamente la regla de envío previo."),
]

FALTANTES_ROWS = [
    ("1", "Compromiso CO 1000183 completo, anexos y comunicaciones", "Define modificación, condonación, cuotas, reviviscencia, incumplimiento y título subsistente.", "Cartera/Jurídica", "ROJO"),
    ("2", "Certificación contable + archivo de cálculo", "Reconcilia desembolso, abonos, CO 1000183, capital, remuneratorios causados, mora y pagos posteriores.", "Contabilidad", "ROJO"),
    ("3", "Trazabilidad de diligenciamiento y custodia", "Fecha/hora, persona, fuente de cada campo, original, ubicación y no circulación. Si es ex post, declararlo.", "Custodia/Jurídica", "ROJO"),
    ("4", "Soporte de aceleración", "Evento exacto, cláusula, modo de ejercicio, fecha y efecto; conciliar 6/7/2026 con 7/10/2026.", "Cartera/Jurídica", "ROJO"),
    ("5", "Certificado CREARCOOP vigente", "Representación, domicilio, correo judicial/mercantil y facultades del otorgante.", "Secretaría", "ROJO"),
    ("6", "Poder especial individualizado", "María Amelia, C.C., pagaré, obligación, acción y cautelar; correo RNA verificado.", "Representante/Abogada", "ROJO"),
    ("7", "Contrato y anexos completos", "Descartar pacto arbitral ejecutivo, garantía mobiliaria, libranza y reglas adicionales.", "Archivo/Jurídica", "ROJO"),
    ("8", "Cierre obligación 1911000712", "Acredita aplicación de refinanciación y ausencia de cobro paralelo.", "Contabilidad/Cartera", "ROJO"),
    ("9", "Consultas oficiales", "Insolvencia, liquidación patrimonial, fallecimiento/sucesión y procesos paralelos, con fecha y resultado.", "Jurídica", "ROJO"),
    ("10", "Datos actuales de notificación", "Dirección y celular; correo solo con evidencia de titularidad/uso y juramento art. 8 Ley 2213.", "Cartera", "ROJO"),
    ("11", "Soporte FOPEP reciente", "Pagador legal, calidad actual, mesada neta, salud, deducciones, órdenes y canal oficial.", "Cartera/Jurídica", "ROJO"),
    ("12", "Control de pago el día de presentar", "Actualiza saldo, pretensiones, cuantía y límite cautelar; evita cobro excesivo.", "Contabilidad", "ROJO"),
]


ALERTAS = [
    p("ALERTAS ESTRATÉGICAS Y TEORÍA DEL CASO", donor=7, bold=True, size=16),
    p("Expediente 1911000986 · uso exclusivo del equipo jurídico", bold=True, color=NAVY),
    h("1. TEORÍA AFIRMATIVA PROPUESTA"),
    p("Una vez cerrados los soportes, la teoría debe ser simple: María Amelia suscribió el pagaré; recibió el crédito; efectuó pagos; incurrió en el incumplimiento documentado; CREARCOOP ejerció válidamente la aceleración o se produjo el vencimiento pactado en el compromiso; el saldo certificado permanece insoluto; la cooperativa reclama solo ese saldo y los intereses legalmente causados."),
    p("No se debe pedir $12.000.000. Se debe reclamar únicamente el capital insoluto certificado. La pretensión menor evita cobrar capital ya satisfecho, pero no sustituye la explicación probatoria del llenado."),
    h("2. MAPA DE ATAQUE Y RESPUESTA"),
    p("[[TABLA_ALERTAS]]"),
    h("3. JURISPRUDENCIA DE SALVAMENTO — FICHA COMPLETA"),
    p("Órgano y sala: Corte Constitucional, Sala Cuarta de Revisión. Providencia: Sentencia T-968 de 2011, 16 de diciembre de 2011, expediente T-3.128.732. Magistrado ponente: Gabriel Eduardo Mendoza Martelo. Fuente oficial: https://www.corteconstitucional.gov.co/relatoria/2011/T-968-11.htm"),
    p("Hechos relevantes: en un ejecutivo con letras diligenciadas sin instrucciones escritas sobre vencimiento, el tribunal declaró una excepción y levantó cautelas. La Corte Suprema, en tutela, consideró omitido su precedente civil; la Corte Constitucional confirmó con precisiones."),
    p("Problema y decisión: si la ausencia o inobservancia de instrucciones elimina necesariamente el mérito ejecutivo. La Corte respondió negativamente y sostuvo que la discrepancia no necesariamente invalida el instrumento, sino que puede imponer adecuarlo a lo realmente acordado."),
    p("Ratio/regla útil: el título debe llenarse conforme a instrucciones; aun así, la sanción no es automáticamente la nulidad o ineficacia total. El juez debe establecer el acuerdo real y adecuar importe o exigibilidad cuando el material probatorio lo permita."),
    p("Similitud: aquí existen espacios llenados, debate sobre monto y fecha y una acción directa del beneficiario. Diferencias/límites: en T-968 se discutían letras, instrucciones no escritas y un proceso ya sentenciado; aquí hay pagaré, dos textos escritos de instrucciones, pagos y un acuerdo posterior no aportado. La tutela no autoriza al acreedor a suplir pruebas ni garantiza mandamiento."),
    p("Proposición soportada: una irregularidad de llenado no obliga por sí sola a abandonar toda ejecución; es viable reclamar el saldo real si se prueba el acuerdo y se permite la adecuación. Esta sentencia se usa en la memoria de estrategia, no como sustituto de la prueba."),
    p("Contrapeso: Corte Constitucional, Sala Séptima de Revisión, Sentencia T-673 de 2010, 31 de agosto de 2010, expediente T-2.644.977, M.P. Jorge Ignacio Pretelt Chaljub, fuente oficial https://www.corteconstitucional.gov.co/relatoria/2010/T-673-10.htm. Allí se censuró adelantar ejecución cuando la prueba mostraba llenado sin observar el artículo 622. Enseña que la ruta de salvamento depende de probar las instrucciones y su aplicación, no de ignorarlas."),
    h("3.1. FICHAS COMPLEMENTARIAS"),
    p("Aceleración — Corte Constitucional, Sala Plena, Sentencia C-332 de 2001, 29 de marzo de 2001, expediente D-3083, M.P. Manuel José Cepeda Espinosa, fuente oficial https://www.corteconstitucional.gov.co/relatoria/2001/C-332-01.htm. Problema: exigibilidad anticipada de créditos por cuotas. Regla decisiva: la mora de una cuota no hace exigible automáticamente todo el saldo; se requieren pacto, incumplimiento y decisión del acreedor de hacer efectiva la aceleración. Similitud: el plan de este crédito termina después del vencimiento facial. Límite: control abstracto, no decide cuándo CREARCOOP ejerció la cláusula. Proposición: la demanda debe indicar y probar una fecha exacta de aceleración."),
    p("Título complejo — Corte Constitucional, Sala Octava de Revisión, Sentencia T-207 de 2021, 1 de julio de 2021, expediente T-7.861.448, M.P. José Fernando Reyes Cuartas, fuente oficial https://www.corteconstitucional.gov.co/relatoria/2021/T-207-21.htm. Problema: rigor del control ejecutivo y documentos conexos. Regla: la obligación debe ser clara, expresa y exigible; varios documentos pueden formar unidad jurídica coherente, pero la ejecución no puede crear una obligación que requiera declaración. Similitud: pagaré, instrucciones, extracto y compromiso deben leerse conjuntamente. Límite: hechos y título distintos. Proposición: CO 1000183 debe integrarse y conciliarse antes del mandamiento."),
    p("Acuerdo de pago — Corte Suprema de Justicia, Sala de Casación Civil, Sentencia SC1365-2022, 6 de junio de 2022, rad. 11001-31-03-009-2013-00173-01, M.P. Luis Alonso Rico Puerta, fuente oficial https://cortesuprema.gov.co/corte/wp-content/uploads/2022/07/SC1365-2022-2013-00173-01.pdf. Problema: efectos de un documento denominado acuerdo de pagos. Regla: manda su contenido; puede configurar transacción si resuelve controversia mediante concesiones recíprocas. Similitud: existe un registro de compromiso y pagos posteriores. Límite: el compromiso de este caso no está aportado y no puede presumirse transacción. Proposición: obtenerlo y calificar sus cláusulas antes de demandar."),
    p("Embargo pensional — Corte Constitucional, Sala Primera de Revisión, Sentencia T-678 de 2017, 16 de noviembre de 2017, expediente T-6.301.544, M.P. Carlos Bernal Pulido, fuente oficial https://www.corteconstitucional.gov.co/relatoria/2017/T-678-17.htm. Problema: embargo cooperativo del 50 % y mínimo vital. Regla: 50 % es techo, no orden automática; debe fijarse una proporción motivada según circunstancias. Similitud: el acreedor es cooperativa y la pista es pensión. Límite: soportes actuales ausentes. Proposición: no activar FOPEP sin mesada neta, remanente y porcentaje concreto."),
    h("4. INTERESES"),
    p("No tratar automáticamente los $1.670.935 como remuneratorios causados. El cronograma aún proyectaba intereses posteriores al 6 de julio de 2026; además, el extracto no revela base, días ni tasa por período. La certificación debe separar remuneratorios efectivamente devengados hasta la aceleración de cualquier interés futuro no causado."),
    p("La mora futura se pide exclusivamente sobre el capital insoluto, desde el día siguiente a la exigibilidad validada, a la tasa pactada sin exceder la máxima legal. La mora previa que figure en el sistema debe liquidarse por las bases vencidas de cada período y sin superponer remuneratorios y moratorios sobre la misma base y lapso."),
    p("Para este microcrédito originado antes del 31 de marzo de 2023 debe documentarse el análisis transitorio del Decreto 455 de 2023. Ese análisis no autoriza congelar indefinidamente el 58,80 % certificado en 2022: deben usarse la categoría y el máximo oficial aplicables a cada período, verificarlos al presentar y no insertar un porcentaje fijo en la pretensión."),
    h("5. PERSONERÍA"),
    p("Ruta preferida: poder especial directo de la representante legal vigente de CREARCOOP a Nubia Aide Gallego Álvarez, o a la profesional que finalmente figure, con C.C., T.P. y correo RNA verificados. Esta ruta elimina la ambigüedad entre sociedad apoderada, designación interna y firmante del modelo."),
    p("Ruta institucional alternativa: poder especial a ABOGADOS EN COLOMBIA S.A.S. que individualice el asunto y actuación por profesional conforme al artículo 75 CGP, o sustitución formal válida. No mezclar las dos rutas ni mantener a Anggie en el modelo si el soporte designa a Nubia."),
    h("6. BIBLIOTECA OFICIAL VERIFICADA AL 18/7/2026"),
    p("Código General del Proceso: https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=48425"),
    p("Código de Comercio, artículos 622 y 624: https://www.secretariasenado.gov.co/senado/basedoc/codigo_comercio_pr019.html — artículo 782: https://www.secretariasenado.gov.co/senado/basedoc/codigo_comercio_pr024.html"),
    p("Ley 2213 de 2022: https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=187626 — Ley 2540 de 2025: https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=263541"),
    p("Ley 100 de 1993, artículo 134: https://www1.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=5248 — Decreto 1833 de 2016: https://www1.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=85319"),
    p("Decreto 455 de 2023: https://www.suin-juriscol.gov.co/viewDocument.asp?ruta=Decretos%2F30046370 — certificaciones SFC: https://www.superfinanciera.gov.co/publicaciones/10112914/"),
    p("SMLMV 2026, Decreto transitorio 0159 de 2026: https://dapre.presidencia.gov.co/normativa/normativa/DECRETO%20No.%200159%20DEL%2019%20DE%20FEBRERO%20DE%202026.pdf"),
    p("FOPEP — instrucciones oficiales de embargo: https://www.fopep.gov.co/embargos/"),
]

ALERT_ROWS = [
    ("Llenado contrario", "Capital facial $12m y dos reglas de vencimiento", "Reconciliación, compromiso, trazabilidad y pretensión limitada al saldo", "Alta"),
    ("Legitimación nominal", "Título usa antigua razón social con LTDA.", "Certificado vigente con reforma, misma entidad, sigla y NIT", "Media"),
    ("Pago/acuerdo", "CO 1000183 + dos recaudos de $832.750", "Aportar acuerdo, aplicación y saldo posterior; no ocultar pagos", "Crítica"),
    ("Inexigibilidad", "Plan final 7/10/2026 vs pagaré 6/7/2026", "Probar aceleración o vencimiento modificado; una sola fecha", "Crítica"),
    ("Intereses indebidos", "$1.670.935 no reproducible", "Separar causado/futuro, bases, períodos, tasas y pagos", "Crítica"),
    ("Doble cobro", "Refinanciación 1911000712", "Certificar cierre y no proceso/cobro paralelo", "Alta"),
    ("Personería", "Poder genérico y firmantes incompatibles", "Poder especial individualizado, RNA y correo coincidente", "Alta"),
    ("Arbitraje", "Contrato incompleto bajo Ley 2540/2025", "Contrato/anexos + certificación de búsqueda", "Alta"),
    ("Prescripción", "Vencimiento facial 6/7/2026", "Radicar antes 6/7/2029 y controlar notificación art. 94", "Media"),
    ("Cautela excesiva", "FOPEP solo 2022 y descuentos altos", "Prueba actual, porcentaje concreto, mínimo vital y límite numérico", "Alta"),
    ("Notificación", "Datos de 2022 y correo ambiguo", "Actualizar; si no se conoce email, decirlo sin inventar", "Alta"),
]


DEMANDA = [
    p("PRECIERRE — NO RADICAR NI FIRMAR HASTA REEMPLAZAR TODOS LOS CAMPOS ROJOS", donor=7, bold=True, color=RED, size=13),
    p("Bogotá D.C., [FECHA REAL DE PRESENTACIÓN]", donor=0, color=RED),
    p("SEÑOR JUEZ DE PEQUEÑAS CAUSAS Y COMPETENCIA MÚLTIPLE DE BOGOTÁ D.C. (REPARTO)", donor=1, bold=True),
    p("E. S. D.", donor=2),
    p("REFERENCIA: DEMANDA EJECUTIVA DE MÍNIMA CUANTÍA — PAGARÉ No. 1911000986 — OBLIGACIÓN No. 10-1911000986.", donor=7),
    p("DEMANDANTE: COOPERATIVA DE AHORRO Y CRÉDITO CREAR, sigla CREARCOOP, NIT 890.981.459-4.", donor=8),
    p("DEMANDADA: MARÍA AMELIA CABIEDES PLATA, C.C. No. 41.526.686.", donor=9),
    p("[NOMBRE DE LA APODERADA DEFINIDA], mayor de edad, identificada como aparece al pie de mi firma, abogada en ejercicio, obrando como apoderada judicial de CREARCOOP conforme al poder especial adjunto, presento demanda ejecutiva de mínima cuantía contra MARÍA AMELIA CABIEDES PLATA para que se libre mandamiento de pago por las obligaciones incorporadas en el pagaré No. 1911000986.", donor=15, color=RED),
    h("I. HECHOS", donor=16),
    p("PRIMERO. MARÍA AMELIA CABIEDES PLATA suscribió en calidad de deudora principal el pagaré No. 1911000986, correspondiente a la obligación No. 10-1911000986, a la orden de la entidad identificada en el título con su denominación histórica COOPERATIVA DE AHORRO Y CRÉDITO CREAR LTDA., sigla CREARCOOP. Conforme al certificado vigente adjunto, es la misma persona jurídica hoy denominada COOPERATIVA DE AHORRO Y CRÉDITO CREAR, sigla CREARCOOP, NIT 890.981.459-4.", donor=17),
    p("SEGUNDO. CREARCOOP otorgó el crédito por DOCE MILLONES DE PESOS M/CTE ($12.000.000), desembolsado el 7 de octubre de 2022 bajo la modalidad de microcrédito empresarial, con un plan de cuarenta y ocho (48) cuotas mensuales y tasa fija de 26,82 % E.A.", donor=18),
    p("TERCERO. La demandada suscribió el pagaré con espacios destinados a ser completados y autorizó su diligenciamiento en los eventos y con arreglo a las instrucciones incorporadas en el título y en la carta suscrita para ese efecto.", donor=19),
    p("CUARTO. [INSERTAR HECHO EXACTO, PROBADO Y NO CONCLUSIVO SOBRE EL COMPROMISO CO 1000183: fecha, contenido, cuotas, efecto sobre el título y pago(s) posterior(es)].", donor=20, color=RED),
    p("QUINTO. [ELEGIR UNA SOLA RUTA PROBADA: (A) describir incumplimiento, cláusula decimocuarta, ejercicio de aceleración y diligenciamiento el 6 de julio de 2026; o (B) describir vencimiento e incumplimiento conforme al compromiso. ELIMINAR ESTA INSTRUCCIÓN].", donor=21, color=RED),
    p("SEXTO. Al 6 de julio de 2026, los registros electrónicos de CREARCOOP reconocen abonos a capital por $8.254.065. Descontados del capital original de $12.000.000, arrojan un saldo insoluto de capital de $3.745.935. La cooperativa limita su acción a la parte no pagada y no reclama como capital la suma original.", donor=22),
    p("SÉPTIMO. [CERTIFICAR EL MISMO DÍA DE LA PRESENTACIÓN] A [CORTE CONTABLE ACTUALIZADO], el capital insoluto es $[CAPITAL CERTIFICADO]; los intereses remuneratorios efectivamente causados y no pagados son $[REMUNERATORIOS CAUSADOS CERTIFICADOS]; y la mora causada es $[MORA CAUSADA CERTIFICADA]. Desde ese corte no se han recibido pagos adicionales.", donor=26, color=RED),
    p("OCTAVO. [SOLO CON CERTIFICACIÓN] CREARCOOP conserva la tenencia legítima y custodia del original del pagaré, que no ha sido endosado ni circulado y será exhibido al despacho cuando sea requerido.", donor=27, color=RED),
    h("II. PRETENSIONES", donor=30),
    p("Solicito librar mandamiento de pago a favor de CREARCOOP y contra MARÍA AMELIA CABIEDES PLATA por:", donor=31),
    p("PRIMERA. [CAPITAL EN LETRAS] PESOS M/CTE ($[CAPITAL CERTIFICADO]), por capital insoluto de la obligación No. 10-1911000986.", donor=32, color=RED),
    p("SEGUNDA. [REMUNERATORIOS EN LETRAS] PESOS M/CTE ($[REMUNERATORIOS CAUSADOS CERTIFICADOS]), por intereses remuneratorios efectivamente causados y no pagados desde [FECHA] hasta [FECHA], liquidados sobre [BASE] a [TASA/MODALIDAD], sin incluir intereses futuros no devengados.", donor=33, color=RED),
    p("TERCERA. (i) [MORA CAUSADA EN LETRAS] PESOS M/CTE ($[MORA CAUSADA CERTIFICADA]), por intereses moratorios causados hasta [FECHA DE CORTE]; y (ii) los intereses moratorios que se causen exclusivamente sobre el capital insoluto de $[CAPITAL CERTIFICADO], desde [DÍA SIGUIENTE A LA EXIGIBILIDAD VALIDADA] hasta el pago, a la tasa pactada sin exceder la máxima legal aplicable y sin capitalización ni superposición.", donor=34, color=RED),
    p("CUARTA. Por las costas y agencias en derecho que se causen en el proceso.", donor=35),
    h("III. FUNDAMENTOS DE DERECHO"),
    p("Fundamento la demanda en los artículos 17, 25, 26, 28, 82, 84, 422, 430 y 431 del Código General del Proceso; 619, 621, 622, 624, 709, 782, 789 y 884 del Código de Comercio; el artículo 69 de la Ley 45 de 1990; la Ley 2213 de 2022 y las disposiciones concordantes.", donor=38),
    h("IV. COMPETENCIA, TRÁMITE Y CUANTÍA", donor=40),
    p("Es competente el Juez de Pequeñas Causas y Competencia Múltiple de Bogotá D.C. por el domicilio de la demandada, la naturaleza ejecutiva del asunto y su mínima cuantía, conforme a los artículos 17, 25, 26 y 28 del Código General del Proceso. [CONFIRMAR AUSENCIA DE PACTO ARBITRAL EJECUTIVO ANTES DE LIBERAR].", donor=41, color=RED),
    p("La cuantía se estima en [TOTAL CAUSADO ACTUALIZADO EN LETRAS] PESOS M/CTE ($[TOTAL CAUSADO ACTUALIZADO]), sin incluir intereses futuros ni costas, suma inferior a cuarenta (40) SMLMV vigentes al presentar la demanda.", donor=42, color=RED),
    h("V. PRUEBAS Y ANEXOS", donor=43),
    p("Solicito tener como pruebas los documentos relacionados en el índice de anexos definitivo:", donor=44),
    p("1. Pagaré No. 1911000986 y carta de instrucciones, íntegros.", donor=45),
    p("2. Liquidación del crédito, solicitud y soportes del desembolso.", donor=45),
    p("3. Compromiso de pago CO 1000183, sus anexos y prueba de incumplimiento.", donor=45),
    p("4. Extracto histórico, certificación contable y memoria de liquidación actualizada.", donor=45),
    p("5. Certificación de diligenciamiento, custodia, no circulación y disponibilidad del original.", donor=45),
    p("6. Certificado vigente de existencia y representación legal de CREARCOOP, poder especial y soportes de personería.", donor=45),
    p("7. [OTROS ANEXOS EFECTIVAMENTE APORTADOS; ELIMINAR SI NO EXISTEN].", donor=46, color=RED),
    h("VI. MEDIDAS CAUTELARES", donor=56),
    p("La demanda se presenta con solicitud de medidas cautelares previas en escrito separado. Por ello no se agotó conciliación prejudicial y no se remitió simultáneamente copia de la demanda y sus anexos a la demandada, sin perjuicio de la posterior notificación personal del mandamiento de pago.", donor=57),
    h("VII. NOTIFICACIONES", donor=59),
    p("Demandante: CREARCOOP, en [DIRECCIÓN VIGENTE DEL CERTIFICADO], correo para notificaciones judiciales [CORREO VIGENTE DEL CERTIFICADO].", donor=61, color=RED),
    p("Apoderada judicial: [NOMBRE], en [DIRECCIÓN], correo [CORREO COINCIDENTE CON RNA].", donor=64, color=RED),
    p("Demandada: MARÍA AMELIA CABIEDES PLATA, en Carrera 8G No. 159B-25, Bogotá D.C. [USAR SOLO SI SE VERIFICA SU VIGENCIA].", donor=71, color=RED),
    p("Canal electrónico de la demandada: [SI SE OBTIENE, INDICARLO Y AGREGAR JURAMENTO/FUENTE DEL ART. 8 LEY 2213; SI NO SE CONOCE, AFIRMARLO DE FORMA EXPRESA Y ELIMINAR ESTE MARCADOR].", donor=80, color=RED),
    p("Atentamente,", donor=89),
    p("[NOMBRE DE LA APODERADA]", donor=95, bold=True, color=RED),
    p("C.C. No. [●] — T.P. No. [●] del C. S. de la J. — correo RNA: [●]", donor=96, color=RED),
]


CAUTELAR = [
    p("MÓDULO CONDICIONAL — NO RADICAR HASTA ACREDITAR FOPEP Y FIJAR LOS TRES CAMPOS ROJOS", donor=7, bold=True, color=RED, size=13),
    p("Bogotá D.C., [FECHA REAL DE PRESENTACIÓN]", donor=0, color=RED),
    p("SEÑOR JUEZ DE PEQUEÑAS CAUSAS Y COMPETENCIA MÚLTIPLE DE BOGOTÁ D.C. (REPARTO)", donor=1, bold=True),
    p("E. S. D.", donor=2),
    p("REFERENCIA: SOLICITUD DE MEDIDA CAUTELAR PREVIA DENTRO DEL PROCESO EJECUTIVO DE MÍNIMA CUANTÍA.", donor=7),
    p("DEMANDANTE: COOPERATIVA DE AHORRO Y CRÉDITO CREAR, sigla CREARCOOP, NIT 890.981.459-4.", donor=8),
    p("DEMANDADA: MARÍA AMELIA CABIEDES PLATA, C.C. No. 41.526.686.", donor=10),
    p("PAGARÉ: No. 1911000986 — OBLIGACIÓN: No. 10-1911000986.", donor=11),
    p("[NOMBRE DE LA APODERADA DEFINIDA], obrando como apoderada judicial de CREARCOOP, solicito decretar la siguiente medida cautelar previa:", donor=14, color=RED),
    h("I. MEDIDA SOLICITADA", donor=17),
    p("Embargo y retención de mesada pensional", donor=19, bold=True),
    p("PRIMERO. Decretar el embargo y retención del [PORCENTAJE CONCRETO APROBADO] % de la mesada pensional ordinaria neta que [DENOMINACIÓN LEGAL Y NIT DEL PAGADOR FOPEP ACTUAL] paga a MARÍA AMELIA CABIEDES PLATA, después del aporte obligatorio a salud y de las órdenes con prelación legal, sin exceder el límite del artículo 134 de la Ley 100 de 1993 y preservando su mínimo vital.", donor=24, color=RED),
    p("SEGUNDO. Limitar la medida a [LÍMITE CAUTELAR ACTUALIZADO EN LETRAS] PESOS M/CTE ($[LÍMITE NUMÉRICO]), calculado y aprobado conforme al artículo 599 del Código General del Proceso. La retención deberá cesar al alcanzarse ese monto o cuando el despacho lo ordene.", donor=25, color=RED),
    p("TERCERO. Oficiar al pagador individualizado para que practique la retención mensual, respete descuentos de salud, alimentos y embargos anteriores, consigne las sumas en la cuenta de depósitos judiciales del despacho e informe el primer cumplimiento y las novedades que impidan o modifiquen la retención.", donor=26),
    p("CUARTO. Tramitar la solicitud en cuaderno separado y mantener la reserva necesaria para preservar su efectividad hasta su práctica.", donor=45),
    h("II. SOPORTE FÁCTICO", donor=17),
    p("La demandada tiene la calidad actual de pensionada y recibe su mesada de [PAGADOR LEGAL], como consta en [CERTIFICACIÓN O DESPRENDIBLE OFICIAL RECIENTE, FECHA Y CÓDIGO DE VERIFICACIÓN]. Su mesada bruta es $[●], el aporte de salud es $[●], las deducciones y órdenes vigentes ascienden a $[●] y la mesada neta es $[●].", donor=17, color=RED),
    p("Aplicada la retención solicitada, la demandada conservará $[VALOR RESIDUAL] mensuales. Conforme a la ficha de proporcionalidad anexa, ese remanente [EXPLICACIÓN PROBADA DEL MÍNIMO VITAL, INGRESOS DEL HOGAR Y CARGAS].", donor=17, color=RED),
    p("El crédito ejecutado es a favor de una cooperativa. La medida se solicita dentro del máximo especial previsto para créditos cooperativos, con porcentaje individualizado y límite global, y resulta idónea para asegurar el pago sin extenderse a mesadas adicionales ni a bienes indeterminados.", donor=17),
    h("III. FUNDAMENTOS", donor=19),
    p("La solicitud se funda en los artículos 593, 594 y 599 del Código General del Proceso y en el numeral 5 del artículo 134 de la Ley 100 de 1993, junto con las normas reglamentarias aplicables al pagador acreditado.", donor=17),
    h("IV. ANEXOS ESPECÍFICOS", donor=19),
    p("1. Certificación o desprendible oficial reciente y verificable del pagador.", donor=42),
    p("2. Ficha de proporcionalidad, mínimo vital y cálculo del porcentaje.", donor=42),
    p("3. Liquidación actualizada y cálculo del límite cautelar.", donor=42),
    p("4. Poder y documentos de personería aportados con la demanda.", donor=42),
    p("La presente solicitud cautelar previa sustenta la aplicación de la excepción de envío simultáneo prevista en el artículo 6 de la Ley 2213 de 2022, sin perjuicio de la notificación posterior del mandamiento.", donor=39),
    p("Atentamente,", donor=47),
    p("[NOMBRE DE LA APODERADA]", donor=52, bold=True, color=RED),
    p("C.C. No. [●] — T.P. No. [●] del C. S. de la J. — correo RNA: [●]", donor=53, color=RED),
]


INDICE = [
    p("ÍNDICE DE ANEXOS — BORRADOR DE CIERRE", donor=7, bold=True, size=16),
    p("Expediente 1911000986 · no usar para radicación hasta completar, foliar y verificar", bold=True, color=RED),
    p("[[TABLA_INDICE]]"),
    h("REGLAS DE CIERRE"),
    p("1. El nombre del archivo, el número de páginas y el orden del mensaje de radicación deben coincidir exactamente con este índice."),
    p("2. No incluir 1911000986.pdf como título. La versión seleccionada es PAGARÉ 1911000986.pdf. El archivo descartado se conserva fuera del paquete judicial para auditoría."),
    p("3. Convertir a PDF legible, orientado, sin contraseña; revisar cada página y conservar hashes del paquete final."),
    p("4. No anunciar un anexo que no esté efectivamente adjunto. Los ítems pendientes deben eliminarse del índice judicial hasta su obtención."),
]

INDICE_ROWS = [
    ("A1", "Pagaré No. 1911000986 + carta de instrucciones", "PAGARÉ 1911000986.pdf", "3", "Disponible / correcto"),
    ("A2", "Liquidación, solicitud, identificación y soportes históricos", "CC 41526686 PAGARE 1911000986.pdf", "7", "Disponible / depurar datos"),
    ("A3", "Extracto histórico al 6/7/2026", "EXTRACTO 41526686.PDF", "3", "Disponible / corte vencido"),
    ("A4", "Compromiso CO 1000183 y anexos", "[NOMBRE EXACTO]", "[●]", "PENDIENTE"),
    ("A5", "Certificación contable y memoria reproducible", "[NOMBRE EXACTO]", "[●]", "PENDIENTE"),
    ("A6", "Trazabilidad de diligenciamiento y custodia", "[NOMBRE EXACTO]", "[●]", "PENDIENTE"),
    ("A7", "Certificado CREARCOOP vigente", "[NOMBRE EXACTO]", "[●]", "PENDIENTE"),
    ("A8", "Poder especial, mensaje de datos y aceptación", "[NOMBRE EXACTO]", "[●]", "PENDIENTE"),
    ("A9", "RNA y soporte de actuación de la profesional", "[NOMBRE EXACTO]", "[●]", "PENDIENTE"),
    ("A10", "Contrato completo y certificación de pacto arbitral", "[NOMBRE EXACTO]", "[●]", "PENDIENTE"),
    ("A11", "Cierre/no cobro obligación 1911000712", "[NOMBRE EXACTO]", "[●]", "PENDIENTE"),
    ("A12", "Consultas insolvencia, sucesión y procesos", "[NOMBRE EXACTO]", "[●]", "PENDIENTE"),
    ("A13", "Prueba actual de dirección/canales", "[NOMBRE EXACTO]", "[●]", "PENDIENTE"),
    ("A14", "Soporte FOPEP actual + ficha proporcionalidad", "[NOMBRE EXACTO]", "[●]", "PENDIENTE"),
]


LIQUIDACION = [
    p("MEMORIA DE LIQUIDACIÓN Y RECONCILIACIÓN", donor=7, bold=True, size=16),
    p("Expediente 1911000986 · plantilla de trabajo; no es certificación contable", bold=True, color=RED),
    h("1. DATOS DE PARTIDA"),
    p("Monto inicial: $12.000.000. Desembolso: 7/10/2022. Tasa contractual: 26,82 % E.A. / 24,00 % N.A.M.V. Plan: 48 cuotas de $391.222. Interés total proyectado del cronograma: $6.778.656. Vencimiento ordinario final: 7/10/2026."),
    p("Neto a desembolsar: $7.725.069, después de Fondo de Solidaridad $180.000, estudio de crédito $12.000, Ley Mipyme $373.100 y refinanciación de la obligación 1911000712 por capital $3.699.531, mora $1.337 e interés $8.963."),
    h("2. CORTE DISPONIBLE"),
    p("Extracto al 6/7/2026: capital $3.745.935; interés $1.670.935; mora $67.662; total $5.484.532. El capital es reproducible exactamente ($12.000.000 − $8.254.065). El interés y la mora no lo son con esta impresión: en mora, $1.395.694 de cargos menos $1.385.813 de abonos da $9.881, cifra inferior en $57.781 al saldo informado."),
    p("[[TABLA_MOVIMIENTOS]]"),
    h("3. PREGUNTAS CONTABLES OBLIGATORIAS"),
    p("a) ¿Qué cuenta o concepto debitó y acreditó CO 1000183? ¿El abono de $4.970.391 redujo definitivamente capital, lo trasladó a otra cuenta o quedó condicionado?"),
    p("b) ¿Por qué los pagos de abril y mayo de 2026 no se aplicaron a capital? Identificar regla contractual y saldo antes/después."),
    p("c) ¿Qué parte de $1.670.935 corresponde a remuneratorios ya causados al 6/7/2026 y qué parte, si alguna, corresponde a intereses futuros del cronograma?"),
    p("d) ¿Cómo se obtienen los $67.662 de mora si la resta de cargos $1.395.694 y abonos $1.385.813 arroja $9.881? Explicar la diferencia de $57.781 e informar base diaria, fechas, modalidad y redondeos."),
    p("e) ¿Hubo pagos, descuentos, condonaciones, seguros, subrogaciones o ajustes después del 6/7/2026?"),
    h("4. ESTRUCTURA DEL ARCHIVO REPRODUCIBLE"),
    p("[[TABLA_LIQUIDACION]]"),
    h("5. REGLAS DE CÁLCULO"),
    p("Aplicar cada pago en su fecha y según la regla contractual demostrada. Mantener columnas separadas de capital, remuneratorios, mora, seguros y otros. No superponer remuneratorios y moratorios sobre la misma base y período. No capitalizar intereses salvo presupuesto legal probado. Indicar fórmula de conversión de tasa y días usados."),
    p("La pretensión de remuneratorios solo puede incluir lo efectivamente causado hasta la exigibilidad. La mora futura solo puede calcularse sobre el capital insoluto certificado, desde el día siguiente a la fecha de exigibilidad validada y sujeta al máximo legal aplicable."),
    p("El contador debe certificar hechos y registros contables; el equipo jurídico debe calificar los efectos de novación, aceleración y exigibilidad. Ninguno debe sustituir el análisis del otro."),
]

MOV_ROWS = [
    ("7/10/2022", "NA 11000216", "Creación crédito", "$12.000.000 cargo capital", "Extracto p. 1"),
    ("31/1/2026", "CO 1000183", "Generación compromiso de pago", "$4.970.391 abono capital + $408.509 abono mora", "Extracto p. 2"),
    ("28/4/2026", "CB 126013904", "Recaudo Efecty", "$150.772 interés + $681.978 mora", "Extracto p. 3"),
    ("25/5/2026", "CB 126016683", "Recaudo Efecty", "$797.008 interés + $35.742 mora", "Extracto p. 3"),
    ("6/7/2026", "Corte", "Saldo de sistema", "$3.745.935 capital + $1.670.935 interés + $67.662 mora", "Extracto p. 3"),
]

LIQ_ROWS = [
    ("Fecha", "Comprobante y concepto", "Débito/cargo por rubro", "Pago/aplicación por rubro", "Saldo posterior por rubro"),
    ("[dd/mm/aaaa]", "[número + soporte]", "Capital [ ]; rem. [ ]; mora [ ]; otros [ ]", "Capital [ ]; rem. [ ]; mora [ ]; otros [ ]", "Capital [ ]; rem. [ ]; mora [ ]; total [ ]"),
    ("…", "Una fila por movimiento", "Sin agrupaciones opacas", "Aplicación exacta en su fecha", "Fórmula verificable"),
]


ACTA = [
    p("ACTA FINAL DE CONTROL Y LIBERACIÓN", donor=7, bold=True, size=16),
    p("Expediente 1911000986 · completar únicamente con evidencia", bold=True, color=RED),
    p("Resultado actual: NO LIBERADO", bold=True, color=RED, size=13),
    p("[[TABLA_ACTA]]"),
    h("CERTIFICACIÓN DE LIBERACIÓN"),
    p("Solo cuando todos los controles sean VERDE, la revisora humana podrá firmar: “Certifico que confronté demanda, cautelar y anexos; que no subsisten campos, alternativas, instrucciones, comentarios ni datos de otro expediente; que el saldo fue verificado el día de presentación; que la norma decisiva se consultó en fuente oficial; y que el paquete queda APTO PARA REVISIÓN Y FIRMA HUMANA”."),
    p("Revisó — abogado/a de demanda: [NOMBRE / FIRMA / FECHA / HORA]"),
    p("Contrarrevisión — abogado/a de defensa: [NOMBRE / FIRMA / FECHA / HORA]"),
    p("Control contable: [NOMBRE / CALIDAD / FIRMA / FECHA / HORA]"),
    p("Aprobó firmante: [NOMBRE / FIRMA / FECHA / HORA]"),
]

ACTA_ROWS = [
    ("Control", "Criterio de liberación", "Estado", "Evidencia/folio"),
    ("Identidad", "Coinciden nombre, cédula, calidad, pagaré y obligación", "[ ]", "[●]"),
    ("Personería", "Poder especial, certificado vigente, RNA, correo y firma coinciden", "[ ]", "[●]"),
    ("Arbitraje", "Contrato/anexos revisados; jurisdicción definida", "[ ]", "[●]"),
    ("Título", "Original, instrucciones, llenado, custodia y legitimación cerrados", "[ ]", "[●]"),
    ("Exigibilidad", "Una fecha, evento, ejercicio y efecto probados", "[ ]", "[●]"),
    ("CO 1000183", "Documento completo y efecto contable/jurídico resuelto", "[ ]", "[●]"),
    ("Liquidación", "Actualizada, reproducible, sin pagos omitidos ni interés futuro", "[ ]", "[●]"),
    ("Prescripción", "Fecha límite y control art. 94 registrados", "[ ]", "[●]"),
    ("Insolvencia", "Consultas oficiales recientes y sin bloqueo", "[ ]", "[●]"),
    ("Notificación", "Dirección/canal actual; juramento electrónico soportado si aplica", "[ ]", "[●]"),
    ("Cautela", "Pagador, porcentaje, residual y límite numérico aprobados", "[ ]", "[●]"),
    ("Anexos", "Índice = archivos = mensaje; PDFs legibles y con hashes", "[ ]", "[●]"),
    ("Higiene", "Cero rojo, corchetes, instrucciones, alternativas o pagaré descartado", "[ ]", "[●]"),
]


AGENDA = [
    p("AGENDA DE SEGUIMIENTO PROCESAL", donor=7, bold=True, size=16),
    p("Expediente 1911000986 · activar después de la decisión de radicar", bold=True, color=NAVY),
    p("[[TABLA_AGENDA]]"),
    h("HITOS INTERNOS INNEGOCIABLES"),
    p("Fecha facial de vencimiento: 6 de julio de 2026. Fecha facial de prescripción de la acción cambiaria directa: 6 de julio de 2029, sujeta a validación de la exigibilidad. No esperar al límite: fijar margen interno y controlar que la notificación cumpla el artículo 94 del CGP."),
    p("La radicación no cierra el control. Registrar diariamente auto de mandamiento, decreto/práctica cautelar, entrega de notificación, contestación/excepciones, liquidación, pagos, suspensión por insolvencia y cualquier término de desistimiento tácito."),
]

AGENDA_ROWS = [
    ("Hito", "Responsable", "Fecha objetivo", "Evidencia/alerta"),
    ("Obtener CO 1000183", "Cartera", "[●]", "Documento completo, anexos, comunicaciones"),
    ("Cerrar liquidación", "Contabilidad + jurídica", "[●]", "Archivo reproducible y certificado"),
    ("Definir personería", "Representante + firmante", "[●]", "Poder, RNA, correos y aceptación"),
    ("Consultas oficiales", "Jurídica", "Mismo día de radicar", "Insolvencia, sucesión, procesos, arbitraje"),
    ("Control último pago", "Contabilidad", "Mismo día de radicar", "Saldo y pretensiones actualizados"),
    ("Radicación", "Apoderada", "[●]", "Acuse, sello de tiempo, índice y adjuntos"),
    ("Mandamiento", "Apoderada", "Revisión diaria", "Admisión/inadmisión/negativa y término"),
    ("Práctica cautelar", "Apoderada", "Revisión diaria", "Oficio, entrega, respuesta y depósito"),
    ("Notificación", "Apoderada", "Sin demora", "Entrega/rebote, términos y art. 94 CGP"),
    ("Excepciones", "Equipo defensa", "Según traslado", "Matriz de respuesta y prueba"),
    ("Pagos posteriores", "Cartera", "Continuo", "Actualizar liquidación y comunicar al proceso"),
    ("Desistimiento tácito", "Apoderada", "Control mensual", "Art. 317 CGP y última actuación"),
]


SUBSANACION = [
    p("FORMATOS OPERATIVOS DE SUBSANACIÓN", donor=7, bold=True, size=16),
    p("Expediente 1911000986 · estos formatos deben completarse con hechos reales; prohibido antedatar o certificar lo desconocido", bold=True, color=RED),
    h("A. PODER ESPECIAL DIRECTO — RUTA RECOMENDADA"),
    p("Señor(a) Juez de Pequeñas Causas y Competencia Múltiple de Bogotá D.C. (Reparto)"),
    p("CARMEN JACINTA RAMÍREZ ARISTIZÁBAL, identificada con C.C. No. 21.659.419, actuando exclusivamente si para la fecha conserva la representación legal acreditada de la COOPERATIVA DE AHORRO Y CRÉDITO CREAR — CREARCOOP, NIT 890.981.459-4, confiero poder especial, amplio y suficiente a [NOMBRE DE LA ABOGADA], C.C. No. [●], T.P. No. [●], correo inscrito en el Registro Nacional de Abogados [●], para promover y llevar hasta su terminación proceso ejecutivo de mínima cuantía contra MARÍA AMELIA CABIEDES PLATA, C.C. No. 41.526.686, con fundamento en el pagaré No. 1911000986 y la obligación No. 10-1911000986, incluida la solicitud y práctica de medidas cautelares."),
    p("La apoderada queda facultada para las actuaciones generales del artículo 77 del CGP y, solo si la poderdante lo autoriza expresamente, para [ENUMERAR FACULTADES ESPECIALES REALES: recibir, transigir, conciliar, desistir, sustituir, reasumir]. No incorporar facultades no aprobadas."),
    p("Poderdante: [FIRMA O MENSAJE DE DATOS] — correo mercantil/judicial vigente según certificado [●] — fecha/hora [●]. Acepto: [ABOGADA] — correo RNA [●] — fecha/hora [●]. Conservar mensaje original, encabezados, adjuntos y acuse."),
    h("B. RUTA ALTERNATIVA — PODER A PERSONA JURÍDICA"),
    p("Si se decide conferir poder a ABOGADOS EN COLOMBIA S.A.S., individualizar el mismo asunto y verificar el artículo 75 CGP, el objeto social, el certificado vigente y la forma documentada en que actuará la profesional. Si la abogada no figura donde la ley exige o se usa sustitución, aportar el acto formal correspondiente. No usar simultáneamente la ruta A y B."),
    h("C. CERTIFICACIÓN CONTABLE"),
    p("Yo, [NOMBRE / CALIDAD / IDENTIFICACIÓN / T.P. SI APLICA], con acceso autorizado a [SISTEMA Y FUENTES], certifico bajo mi responsabilidad que revisé la obligación No. 10-1911000986 a nombre de MARÍA AMELIA CABIEDES PLATA, C.C. 41.526.686, y que el archivo de cálculo adjunto reproduce todos los movimientos desde el desembolso hasta [FECHA/HORA DE CORTE]."),
    p("La certificación debe: (1) explicar CO 1000183 y su soporte; (2) mostrar los pagos del 28/4 y 25/5/2026; (3) conciliar capital facial e insoluto; (4) separar remuneratorios causados de futuros; (5) liquidar mora por base, tasa y días; (6) informar pagos posteriores; (7) explicar la refinanciación y cierre de 1911000712; y (8) adjuntar exportación y fórmulas. Firma, fecha y datos verificables."),
    h("D. CERTIFICACIÓN EX POST DE TRAZABILIDAD Y CUSTODIA"),
    p("Usar este título si el acta no fue contemporánea. Identificar quién reconstruye la trazabilidad, cuáles registros consultó y qué hechos conoce personalmente. No afirmar fecha, hora, no circulación o ubicación sin conocimiento y soporte."),
    p("[[TABLA_DILIGENCIAMIENTO]]"),
    p("Agregar ubicación física del original, responsables y transferencias de custodia, controles de acceso, escaneo íntegro, hash y compromiso de exhibición. Explicar expresamente el capital $12.000.000 frente al saldo y el vencimiento 6/7/2026 frente al plan 7/10/2026 y al compromiso."),
    h("E. CERTIFICACIÓN JURÍDICA DE CONTRATO Y PACTO ARBITRAL"),
    p("[NOMBRE/CALIDAD] certifica que revisó solicitud, contrato, reglamento, anexos, pagaré, carta, compromiso y mensajes asociados a la obligación, e indica uno de dos resultados sustentados: (i) no existe pacto arbitral aplicable; o (ii) existe, transcribe su ubicación, alcance, centro y aceptación, caso en el cual no se radica ante juez sin definir la ruta. Adjuntar inventario de documentos revisados."),
    h("F. CIERRE DE OBLIGACIÓN REFINANCIADA"),
    p("Contabilidad y cartera deben certificar el destino de $3.699.531 de capital, $1.337 de mora y $8.963 de interés aplicados a la obligación 1911000712 el 7/10/2022; su estado actual; y que no existe cobro, proceso o cesión paralela incompatible. Adjuntar mayor, extracto y consulta jurídica."),
    h("G. FICHA FOPEP Y PROPORCIONALIDAD"),
    p("Aportar respuesta o desprendible oficial reciente verificable; no reemplazarlo por certificación de CREARCOOP. Registrar pagador legal/NIT/canal, calidad actual, mesada bruta, salud, descuentos, órdenes, neto, porcentaje propuesto, valor retenido, remanente, cargas familiares, otros ingresos conocidos, límite global y justificación de mínimo vital. Respetar finalidad y reserva de los datos."),
    p("Control operativo consultado el 18/7/2026: la página oficial de FOPEP identifica al administrador como Consorcio FOPEP 2022 y publica notificacionesjudiciales.consorcio@fopep.gov.co para oficios; exige radicado completo de 23 dígitos, porcentaje exacto, tope monetario y oficio con vigencia no superior a doce meses. Verificar nuevamente https://www.fopep.gov.co/embargos/ el día del oficio."),
    h("H. CONSTANCIA DE CONSULTAS Y ÚLTIMO PAGO"),
    p("En la fecha de radicación, dejar constancia firmada de las fuentes oficiales consultadas sobre insolvencia, liquidación patrimonial, fallecimiento/sucesión y procesos paralelos; resultado, fecha, hora, criterios y evidencia. Agregar consulta de pago del mismo día y aprobación de saldo, cuantía y límite cautelar."),
]

DIL_ROWS = [
    ("Campo", "Estado original", "Instrucción aplicable", "Evento/fuente", "Dato insertado", "Fecha/persona"),
    ("Capital", "[●]", "Cláusula 15 + carta", "[saldo/documento]", "$12.000.000", "[●]"),
    ("Interés", "[●]", "Cláusula 15 + carta", "[liquidación]", "$1.670.935", "[●]"),
    ("Vencimiento", "[●]", "Inicio mora / día llenado", "[aceleración/compromiso]", "6/7/2026", "[●]"),
    ("Tasa", "[●]", "Contrato/límite legal", "[soporte]", "26,82 % E.A.", "[●]"),
]


FILES = [
    ("01_INFORME_VIABILIDAD_1911000986_V2.docx", DEMAND_TEMPLATE, INFORME, "Informe de viabilidad 1911000986 v2", {"[[TABLA_GATES]]": {"headers": ("Gate", "Control", "Estado", "Razón"), "rows": GATES_ROWS, "widths": (800, 2100, 1100, 4700)}}),
    ("02_MATRIZ_DATO_SOPORTE_1911000986_V2.docx", DEMAND_TEMPLATE, MATRIZ, "Matriz dato soporte 1911000986 v2", {"[[TABLA_MATRIZ]]": {"headers": ("Elemento", "Dato", "Archivo/página", "Certeza"), "rows": MATRIZ_ROWS, "widths": (1400, 2900, 2900, 1500)}}),
    ("03_FALTANTES_BLOQUEANTES_1911000986_V2.docx", DEMAND_TEMPLATE, FALTANTES, "Faltantes bloqueantes 1911000986 v2", {"[[TABLA_FALTANTES]]": {"headers": ("Orden", "Soporte", "Debe resolver", "Responsable", "Estado"), "rows": FALTANTES_ROWS, "widths": (600, 2300, 3300, 1500, 900)}}),
    ("04_ALERTAS_ESTRATEGICAS_1911000986_V2.docx", DEMAND_TEMPLATE, ALERTAS, "Alertas estratégicas 1911000986 v2", {"[[TABLA_ALERTAS]]": {"headers": ("Defensa", "Base", "Respuesta probatoria", "Riesgo"), "rows": ALERT_ROWS, "widths": (1500, 2500, 3500, 1200)}}),
    ("05_DEMANDA_EJECUTIVA_PRECIERRE_1911000986_V2_NO_RADICAR.docx", DEMAND_TEMPLATE, DEMANDA, "Demanda ejecutiva precierre 1911000986 v2 no radicar", None),
    ("06_MEDIDA_CAUTELAR_FOPEP_PRECIERRE_1911000986_V2_NO_RADICAR.docx", CAUTION_TEMPLATE, CAUTELAR, "Medida cautelar FOPEP precierre 1911000986 v2 no radicar", None),
    ("07_INDICE_ANEXOS_1911000986_V2.docx", DEMAND_TEMPLATE, INDICE, "Índice de anexos 1911000986 v2", {"[[TABLA_INDICE]]": {"headers": ("No.", "Documento", "Archivo final", "Págs.", "Estado"), "rows": INDICE_ROWS, "widths": (600, 2700, 2600, 700, 1700)}}),
    ("08_MEMORIA_LIQUIDACION_1911000986_V2.docx", DEMAND_TEMPLATE, LIQUIDACION, "Memoria de liquidación 1911000986 v2", {"[[TABLA_MOVIMIENTOS]]": {"headers": ("Fecha", "Comprobante", "Movimiento", "Aplicación visible", "Fuente"), "rows": MOV_ROWS, "widths": (1100, 1500, 2100, 2800, 1200)}, "[[TABLA_LIQUIDACION]]": {"headers": ("Fecha", "Concepto", "Cargo", "Pago/aplicación", "Saldo"), "rows": LIQ_ROWS, "widths": (1100, 1900, 1900, 2200, 1700)}}),
    ("09_ACTA_FINAL_CONTROL_1911000986_V2.docx", DEMAND_TEMPLATE, ACTA, "Acta final de control 1911000986 v2", {"[[TABLA_ACTA]]": {"headers": ("Control", "Criterio de liberación", "Estado", "Evidencia/folio"), "rows": ACTA_ROWS, "widths": (1600, 4500, 1000, 1600)}}),
    ("10_AGENDA_SEGUIMIENTO_1911000986_V2.docx", DEMAND_TEMPLATE, AGENDA, "Agenda de seguimiento 1911000986 v2", {"[[TABLA_AGENDA]]": {"headers": ("Hito", "Responsable", "Fecha objetivo", "Evidencia/alerta"), "rows": AGENDA_ROWS, "widths": (2000, 1800, 1500, 3400)}}),
    ("11_FORMATOS_SUBSANACION_1911000986_V2.docx", DEMAND_TEMPLATE, SUBSANACION, "Formatos de subsanación 1911000986 v2", {"[[TABLA_DILIGENCIAMIENTO]]": {"headers": ("Campo", "Estado original", "Instrucción", "Evento/fuente", "Dato", "Fecha/persona"), "rows": DIL_ROWS, "widths": (900, 1300, 1700, 1800, 1400, 1600)}}),
]


def validate(path):
    with ZipFile(path, "r") as z:
        bad = z.testzip()
        if bad:
            raise RuntimeError(f"ZIP defectuoso: {path.name}: {bad}")
        root = etree.fromstring(z.read("word/document.xml"))
        text = "\n".join(root.xpath("//w:t/text()", namespaces=NS))
        for residual in ("JEFFERSON", "2312000044", "JHEISON", "marlinpalacios0913"):
            if residual.lower() in text.lower():
                raise RuntimeError(f"Dato residual {residual}: {path.name}")
        if "[[TABLA_" in text:
            raise RuntimeError(f"Marcador de tabla no reemplazado: {path.name}")
        colors = {node.get(qn("val"), "").upper() for node in root.findall(f".//{qn('color')}")}
        if {"00B050", "00B0F0"} & colors:
            raise RuntimeError(f"Persisten colores guía: {path.name}")
        return len(text), sha256(path.read_bytes()).hexdigest()


def main():
    OUT.mkdir(parents=True, exist_ok=True)
    manifest = []
    for name, template, specs, title, tables in FILES:
        path = OUT / name
        make(path, template, specs, title, tables)
        chars, digest = validate(path)
        manifest.append((name, path.stat().st_size, chars, digest))
    for row in manifest:
        print("\t".join(map(str, row)))


if __name__ == "__main__":
    main()
