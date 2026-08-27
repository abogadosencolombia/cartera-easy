from copy import deepcopy
from hashlib import sha256
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile

from lxml import etree


ROOT = Path("/code/DEMANDAS")
OUT = ROOT / "ENTREGABLES_1911000986"
DEMAND_TEMPLATE = ROOT / "MODELO.docx"
CAUTION_TEMPLATE = ROOT / "MODELO MEDIDAS.docx"

NS_W = "http://schemas.openxmlformats.org/wordprocessingml/2006/main"
NS_R = "http://schemas.openxmlformats.org/officeDocument/2006/relationships"
NS_REL = "http://schemas.openxmlformats.org/package/2006/relationships"
NS_XML = "http://www.w3.org/XML/1998/namespace"
NS_DC = "http://purl.org/dc/elements/1.1/"
NS_CP = "http://schemas.openxmlformats.org/package/2006/metadata/core-properties"
NS = {"w": NS_W, "r": NS_R}


def qn(namespace, tag):
    return f"{{{namespace}}}{tag}"


def first_run_properties(paragraph):
    rpr = paragraph.find(f".//{qn(NS_W, 'rPr')}")
    return deepcopy(rpr) if rpr is not None else etree.Element(qn(NS_W, "rPr"))


def set_on_off(parent, tag, enabled):
    existing = parent.find(qn(NS_W, tag))
    if enabled:
        if existing is None:
            existing = etree.SubElement(parent, qn(NS_W, tag))
        existing.set(qn(NS_W, "val"), "1")
    elif existing is not None:
        parent.remove(existing)


def set_color(rpr, color):
    existing = rpr.find(qn(NS_W, "color"))
    if color:
        if existing is None:
            existing = etree.SubElement(rpr, qn(NS_W, "color"))
        existing.set(qn(NS_W, "val"), color)
    elif existing is not None:
        rpr.remove(existing)


def set_size(rpr, points):
    if points is None:
        return
    value = str(int(points * 2))
    for tag in ("sz", "szCs"):
        node = rpr.find(qn(NS_W, tag))
        if node is None:
            node = etree.SubElement(rpr, qn(NS_W, tag))
        node.set(qn(NS_W, "val"), value)


def rewrite_paragraph(paragraph, text, *, color=None, bold=None, italic=None,
                      size=None, page_break_before=False, keep_next=False):
    ppr = paragraph.find(qn(NS_W, "pPr"))
    ppr = deepcopy(ppr) if ppr is not None else etree.Element(qn(NS_W, "pPr"))
    # Todos los ordinales y viñetas se escriben expresamente en el texto. Las
    # plantillas traen numeración automática en varios párrafos donantes, lo
    # que duplicaría encabezados o agregaría viñetas ajenas.
    num_pr = ppr.find(qn(NS_W, "numPr"))
    if num_pr is not None:
        ppr.remove(num_pr)
    rpr = first_run_properties(paragraph)

    for child in list(paragraph):
        paragraph.remove(child)
    paragraph.append(ppr)

    for bad in list(rpr.findall(qn(NS_W, "highlight"))):
        rpr.remove(bad)
    set_color(rpr, color)
    if bold is not None:
        set_on_off(rpr, "b", bold)
        set_on_off(rpr, "bCs", bold)
    if italic is not None:
        set_on_off(rpr, "i", italic)
        set_on_off(rpr, "iCs", italic)
    set_size(rpr, size)

    if page_break_before:
        node = ppr.find(qn(NS_W, "pageBreakBefore"))
        if node is None:
            node = etree.SubElement(ppr, qn(NS_W, "pageBreakBefore"))
        node.set(qn(NS_W, "val"), "1")
    if keep_next:
        node = ppr.find(qn(NS_W, "keepNext"))
        if node is None:
            node = etree.SubElement(ppr, qn(NS_W, "keepNext"))
        node.set(qn(NS_W, "val"), "1")

    run = etree.SubElement(paragraph, qn(NS_W, "r"))
    run.append(rpr)
    text_node = etree.SubElement(run, qn(NS_W, "t"))
    text_node.set(qn(NS_XML, "space"), "preserve")
    text_node.text = text
    return paragraph


def strip_guide_formatting(root):
    for color in root.findall(f".//{qn(NS_W, 'color')}"):
        if color.get(qn(NS_W, "val"), "").upper() in {
            "00B050", "00B0F0", "1155CC", "FFFFFF"
        }:
            parent = color.getparent()
            parent.remove(color)
    for highlight in list(root.findall(f".//{qn(NS_W, 'highlight')}")):
        highlight.getparent().remove(highlight)


def remove_document_hyperlinks(rels_bytes):
    root = etree.fromstring(rels_bytes)
    for rel in list(root):
        rel_type = rel.get("Type", "")
        target = rel.get("Target", "")
        if (
            rel_type.endswith("/hyperlink")
            or rel_type.endswith("/customXml")
            or "customXml/" in target
        ):
            root.remove(rel)
    return etree.tostring(root, xml_declaration=True, encoding="UTF-8", standalone=True)


def make_docx(template_path, output_path, specs, title):
    with ZipFile(template_path, "r") as zin:
        members = {info.filename: (info, zin.read(info.filename)) for info in zin.infolist()}

    # Eliminar metadatos y almacenes auxiliares heredados de las plantillas.
    for name in list(members):
        if name.startswith("customXml/") or name == "docProps/custom.xml":
            del members[name]

    content_types_name = "[Content_Types].xml"
    if content_types_name in members:
        ct_info, ct_bytes = members[content_types_name]
        ct_root = etree.fromstring(ct_bytes)
        for node in list(ct_root):
            part_name = node.get("PartName", "")
            if part_name.startswith("/customXml/") or part_name == "/docProps/custom.xml":
                ct_root.remove(node)
        members[content_types_name] = (
            ct_info,
            etree.tostring(ct_root, xml_declaration=True, encoding="UTF-8", standalone=True),
        )

    package_rels_name = "_rels/.rels"
    if package_rels_name in members:
        rel_info, rel_bytes = members[package_rels_name]
        rel_root = etree.fromstring(rel_bytes)
        for node in list(rel_root):
            if node.get("Target", "") == "docProps/custom.xml" or node.get("Type", "").endswith("/custom-properties"):
                rel_root.remove(node)
        members[package_rels_name] = (
            rel_info,
            etree.tostring(rel_root, xml_declaration=True, encoding="UTF-8", standalone=True),
        )

    doc_info, doc_bytes = members["word/document.xml"]
    root = etree.fromstring(doc_bytes)
    body = root.find(f".//{qn(NS_W, 'body')}")
    source_paragraphs = body.findall(qn(NS_W, "p"))
    sect = body.find(qn(NS_W, "sectPr"))
    sect_copy = deepcopy(sect) if sect is not None else None

    for child in list(body):
        body.remove(child)
    for spec in specs:
        donor = deepcopy(source_paragraphs[spec["donor"]])
        rewrite_paragraph(
            donor,
            spec["text"],
            color=spec.get("color"),
            bold=spec.get("bold"),
            italic=spec.get("italic"),
            size=spec.get("size"),
            page_break_before=spec.get("page_break_before", False),
            keep_next=spec.get("keep_next", False),
        )
        body.append(donor)
    if sect_copy is not None:
        body.append(sect_copy)

    strip_guide_formatting(root)
    members["word/document.xml"] = (
        doc_info,
        etree.tostring(root, xml_declaration=True, encoding="UTF-8", standalone=True),
    )

    rel_name = "word/_rels/document.xml.rels"
    if rel_name in members:
        rel_info, rel_bytes = members[rel_name]
        members[rel_name] = (rel_info, remove_document_hyperlinks(rel_bytes))

    core_name = "docProps/core.xml"
    if core_name in members:
        core_info, core_bytes = members[core_name]
        core_root = etree.fromstring(core_bytes)
        title_nodes = core_root.xpath(
            "//*[local-name()='title']"
        )
        if title_nodes:
            title_nodes[0].text = title
        else:
            title_node = etree.Element(qn(NS_DC, "title"))
            title_node.text = title
            core_root.insert(0, title_node)
        subject_nodes = core_root.xpath("//*[local-name()='subject']")
        subject_text = "Estado=NO RADICAR" if "no radicar" in title.lower() else "Control jurídico"
        if subject_nodes:
            subject_nodes[0].text = subject_text
        else:
            subject_node = etree.Element(qn(NS_DC, "subject"))
            subject_node.text = subject_text
            core_root.insert(1, subject_node)
        metadata = {
            "creator": (NS_DC, "Equipo jurídico — control interno"),
            "lastModifiedBy": (NS_CP, "Equipo jurídico — control interno"),
            "revision": (NS_CP, "1"),
            "created": ("http://purl.org/dc/terms/", "2026-07-21T00:00:00Z"),
            "modified": ("http://purl.org/dc/terms/", "2026-07-21T00:00:00Z"),
        }
        for local_name, (namespace, value) in metadata.items():
            nodes = core_root.xpath(f"//*[local-name()='{local_name}']")
            if nodes:
                nodes[0].text = value
            else:
                node = etree.SubElement(core_root, qn(namespace, local_name))
                node.text = value
        members[core_name] = (
            core_info,
            etree.tostring(
                core_root,
                xml_declaration=True,
                encoding="UTF-8",
                standalone=True,
            ),
        )

    app_name = "docProps/app.xml"
    if app_name in members:
        app_info, app_bytes = members[app_name]
        app_root = etree.fromstring(app_bytes)
        inherited_stats = {"TotalTime", "Pages", "Words", "Characters", "Lines", "Paragraphs", "CharactersWithSpaces"}
        for node in list(app_root):
            if etree.QName(node).localname in inherited_stats:
                app_root.remove(node)
        members[app_name] = (
            app_info,
            etree.tostring(app_root, xml_declaration=True, encoding="UTF-8", standalone=True),
        )

    with ZipFile(output_path, "w", compression=ZIP_DEFLATED) as zout:
        for name, (info, data) in members.items():
            zout.writestr(info, data)


def p(donor, text, **kwargs):
    return {"donor": donor, "text": text, **kwargs}


def heading(text, *, donor=37, page_break_before=False):
    return p(
        donor,
        text,
        bold=True,
        keep_next=True,
        page_break_before=page_break_before,
    )


DEMAND_SPECS = [
    p(7, "BORRADOR DE CONTROL — NO RADICAR, NO FIRMAR Y NO NOTIFICAR", color="C00000", bold=True, size=14),
    p(38, "Estado del protocolo civilista v2.0: ROJO. Este Word no acredita exigibilidad, personería ni una liquidación reproducible. Solo permite revisar la futura estructura de la demanda.", color="C00000", bold=True),
    p(38, "ALERTA DE HOMOLOGACIÓN: los archivos fuente se denominan MODELO.docx y MODELO MEDIDAS.docx; no aparecieron las plantillas canónicas FORMATO_DEMANDA_EJECUTIVA_CREARCOOP_HOMOLOGADO_v2.0.docx y FORMATO_MEDIDAS_CAUTELARES_CREARCOOP_HOMOLOGADO_v2.0.docx exigidas por el protocolo.", color="C00000"),
    p(0, "Bogotá D.C., 17 de julio de 2026"),
    p(1, "SEÑOR JUEZ DE PEQUEÑAS CAUSAS Y COMPETENCIA MÚLTIPLE DE BOGOTÁ D.C. (REPARTO)", bold=True),
    p(2, "E. S. D."),
    p(7, "REFERENCIA: BORRADOR DE DEMANDA EJECUTIVA DE MÍNIMA CUANTÍA — PAGARÉ No. 1911000986 — OBLIGACIÓN No. 10-1911000986."),
    p(8, "DEMANDANTE: COOPERATIVA DE AHORRO Y CRÉDITO CREAR, sigla CREARCOOP, NIT 890.981.459-4, con domicilio principal en Medellín, Antioquia."),
    p(9, "REPRESENTANTE LEGAL DOCUMENTAL: CARMEN JACINTA RAMÍREZ ARISTIZÁBAL, C.C. No. 21.659.419. Debe aportarse certificado de existencia y representación legal vigente al momento de radicar."),
    p(10, "APODERADA JUDICIAL: [POR DEFINIR DESPUÉS DE SUBSANAR PERSONERÍA].", color="C00000", bold=True),
    p(12, "DEMANDADA: MARÍA AMELIA CABIEDES PLATA, C.C. No. 41.526.686, en calidad de deudora principal única. El pagaré revisado no contiene codeudor."),
    p(15, "[COMPARECENCIA PENDIENTE]. No debe completarse ni firmarse este apartado hasta contar con poder especial individualizado para MARÍA AMELIA CABIEDES PLATA y el pagaré No. 1911000986, o poder general otorgado por escritura pública, y hasta definir la profesional que comparecerá con correo coincidente con el Registro Nacional de Abogados.", color="C00000"),
    heading("I. HECHOS", donor=16),
    p(17, "PRIMERO. MARÍA AMELIA CABIEDES PLATA, identificada con C.C. No. 41.526.686, suscribió a favor de la COOPERATIVA DE AHORRO Y CRÉDITO CREAR, sigla CREARCOOP, el pagaré No. 1911000986, correspondiente a la obligación No. 10-1911000986."),
    p(18, "SEGUNDO. El crédito fue aprobado por valor inicial de DOCE MILLONES DE PESOS M/CTE ($12.000.000), bajo la modalidad documental de microcrédito empresarial, y fue desembolsado el 7 de octubre de 2022."),
    p(19, "TERCERO. El plan de pagos aportado prevé cuarenta y ocho (48) cuotas mensuales de $391.222, con primera cuota el 7 de noviembre de 2022 y vencimiento ordinario final el 7 de octubre de 2026, a una tasa contractual de 26,82 % E.A. (24,00 % N.A.M.V. según la liquidación)."),
    p(20, "CUARTO. El archivo seleccionado como título, “PAGARÉ 1911000986.pdf”, muestra diligenciados, entre otros, capital por $12.000.000, intereses por $1.670.935, tasa de 26,82 % E.A. y vencimiento el 6 de julio de 2026. Estos datos deben reconciliarse con las instrucciones de llenado y con el saldo contable antes de invocar mérito ejecutivo."),
    p(21, "QUINTO. El extracto de movimientos del crédito, con corte al 6 de julio de 2026, informa provisionalmente capital insoluto por $3.745.935, intereses remuneratorios o de plazo por $1.670.935 e intereses moratorios por $67.662, para un total de $5.484.532."),
    p(22, "SEXTO. El mismo extracto registra el 31 de enero de 2026 el movimiento “GENERACIÓN COMPROMISO DE PAGO”, identificado como CO 1000183, por $5.378.900, y registra recaudos posteriores el 28 de abril y el 25 de mayo de 2026. No se aportó el compromiso ni certificación contable que explique su naturaleza, aplicación y efecto sobre plazo, mora, exigibilidad y saldo."),
    p(26, "SÉPTIMO. El título fue suscrito con espacios en blanco. La cláusula decimoquinta del pagaré y la carta de instrucciones contienen reglas que deben confrontarse respecto de la fecha de vencimiento; además, la instrucción relativa al capital debe conciliarse con el capital inicial de $12.000.000 y el capital insoluto de $3.745.935."),
    p(27, "OCTAVO. [HECHO NO AFIRMABLE TODAVÍA]. Solo después de aportar el compromiso de pago, una liquidación reproducible, el acta de llenado y la cadena de custodia podrá determinarse si la obligación incorporada en el pagaré es clara, expresa y actualmente exigible en los términos del artículo 422 del Código General del Proceso y del artículo 622 del Código de Comercio.", color="C00000"),
    heading("II. PRETENSIONES PROPUESTAS — CONDICIONADAS A SUBSANACIÓN", donor=30),
    p(31, "Únicamente si se superan todos los bloqueos señalados en la auditoría, se proyecta solicitar mandamiento ejecutivo de pago a favor de CREARCOOP y contra MARÍA AMELIA CABIEDES PLATA por los conceptos que resulten confirmados en la liquidación actualizada y reproducible."),
    p(32, "PRIMERA. Por TRES MILLONES SETECIENTOS CUARENTA Y CINCO MIL NOVECIENTOS TREINTA Y CINCO PESOS M/CTE ($3.745.935), como capital insoluto provisional de la obligación No. 10-1911000986, sujeto a certificación contable y a la explicación del compromiso de pago."),
    p(33, "SEGUNDA. Por UN MILLÓN SEISCIENTOS SETENTA MIL NOVECIENTOS TREINTA Y CINCO PESOS M/CTE ($1.670.935), como intereses remuneratorios o de plazo provisionalmente informados al 6 de julio de 2026, sujeto a liquidación que identifique capital base, tasa, período y aplicación de cada pago."),
    p(34, "TERCERA. Por SESENTA Y SIETE MIL SEISCIENTOS SESENTA Y DOS PESOS M/CTE ($67.662), como intereses moratorios provisionalmente informados al 6 de julio de 2026, sujeto a liquidación que excluya superposición y cobros sobre intereses."),
    p(35, "CUARTA. Por los intereses moratorios futuros que, una vez determinada jurídicamente la exigibilidad, se causen exclusivamente sobre el capital insoluto confirmado, desde la fecha correcta y hasta el pago, a la tasa pactada sin exceder el máximo legal aplicable. Para este microcrédito originado y desembolsado antes del 31 de marzo de 2023, debe verificarse la regla transitoria del Decreto 455 de 2023 y el límite de 58,80 % E.A."),
    p(36, "No se pretende mora futura sobre intereses remuneratorios ni moratorios. Antes de radicar debe actualizarse la liquidación a la fecha de presentación y evitarse cualquier duplicidad entre los tres conceptos causados y la mora futura."),
    heading("III. FUNDAMENTOS DE DERECHO"),
    p(38, "Se invocarían, una vez subsanado el expediente, los artículos 17, 25, 26, 28, 82, 84, 90, 94, 422, 424, 430 y 431 del Código General del Proceso; los artículos 619, 621, 622, 624, 709, 789, 884 y 886 del Código de Comercio; la Ley 2213 de 2022; la Ley 2220 de 2022; el Decreto 455 de 2023; y las demás normas concordantes."),
    p(39, "La jurisdicción ordinaria solo será procedente después de verificar el contrato completo y certificar que no existe pacto arbitral ejecutivo o cláusula compromisoria aplicable conforme a la Ley 2540 de 2025, vigente desde el 27 de febrero de 2026."),
    heading("IV. COMPETENCIA, TRÁMITE Y CUANTÍA", donor=40),
    p(41, "Bajo la hipótesis de que no exista pacto arbitral aplicable, el asunto corresponde a un proceso ejecutivo de mínima cuantía. Por el domicilio documental de la demandada en Bogotá D.C. y por la cuantía, el escrito se dirige al Juez de Pequeñas Causas y Competencia Múltiple de Bogotá D.C. (Reparto). La dirección Carrera 8G No. 159B-25 corresponde documentalmente al sector San Cristóbal Norte, localidad de Usaquén, dato que debe actualizarse antes de radicar."),
    p(42, "La cuantía provisional asciende a CINCO MILLONES CUATROCIENTOS OCHENTA Y CUATRO MIL QUINIENTOS TREINTA Y DOS PESOS M/CTE ($5.484.532), suma inferior a cuarenta (40) SMLMV de 2026 ($70.036.200). No incluye intereses futuros ni costas."),
    heading("V. PRUEBAS Y ANEXOS — INVENTARIO DEL BORRADOR", donor=43),
    p(44, "Documentos individualizados y revisados para elaborar este control:"),
    p(45, "1. “PAGARÉ 1911000986.pdf”, tres (3) páginas: versión seleccionada conforme a la instrucción del cliente; contiene pagaré y carta de instrucciones."),
    p(45, "2. “EXTRACTO 41526686.PDF”, tres (3) páginas: extracto de movimientos de la obligación al 6 de julio de 2026."),
    p(45, "3. “CC 41526686 PAGARE 1911000986.pdf”, siete (7) páginas: liquidación/plan de pagos, solicitud de crédito, identificación y desprendibles pensionales históricos."),
    p(45, "4. El archivo “1911000986.pdf” se preserva únicamente para comparación y auditoría; no es el título seleccionado ni debe anexarse como pagaré ejecutivo."),
    p(46, "ANEXOS OBLIGATORIOS PENDIENTES: compromiso de pago de 31 de enero de 2026; certificación contable del movimiento CO 1000183; liquidación actualizada y reproducible; acta de llenado y cadena de custodia; certificado corporativo vigente; poder válido e individualizado; verificación de pacto arbitral; constancia de cierre de la obligación refinanciada No. 1911000712; consultas de insolvencia y procesos paralelos; y evidencia actual de domicilio y canales.", color="C00000"),
    heading("VI. MEDIDA CAUTELAR", donor=56),
    p(57, "No existe con los soportes actuales una medida cautelar individualizada apta para radicación. El borrador separado contiene únicamente un módulo condicional respecto de una posible mesada pagada por FOPEP, que no podrá activarse sin certificación vigente del pagador, mesada neta, descuentos, embargos previos y análisis de mínimo vital.", color="C00000"),
    p(58, "Mientras la solicitud cautelar no sea jurídicamente procedente, no puede invocarse la excepción del artículo 6 de la Ley 2213 de 2022 para omitir el envío previo. La estrategia de presentación deberá definirse con el paquete final."),
    heading("VII. NOTIFICACIONES", donor=59),
    p(61, "Demandante: COOPERATIVA DE AHORRO Y CRÉDITO CREAR, sigla CREARCOOP. NIT 890.981.459-4. Dirección documental: Calle 113 No. 64D-119, Medellín, Antioquia. Correo judicial documental: gerencia@crearcoop.com. Teléfono: (604) 4613030. Debe confirmarse todo con certificado vigente."),
    p(64, "Apoderada judicial: [POR DEFINIR TRAS SUBSANAR G2].", color="C00000"),
    p(71, "Demandada: MARÍA AMELIA CABIEDES PLATA, C.C. No. 41.526.686."),
    p(72, "Dirección física documental de 2022: Carrera 8G No. 159B-25, barrio San Cristóbal Norte, localidad de Usaquén, Bogotá D.C. Debe verificarse su vigencia."),
    p(74, "Celular documental de 2022: 301 438 1414. Debe verificarse su vigencia."),
    p(80, "Dirección electrónica: [NO DETERMINADA CON CERTEZA; LA GRAFÍA MANUSCRITA ES AMBIGUA]. No usar para notificación electrónica hasta contar con verificación y evidencia de uso.", color="C00000"),
    p(82, "No se formula todavía el juramento exigido por el artículo 8 de la Ley 2213 de 2022 sobre obtención y uso del canal electrónico de la demandada."),
    heading("VIII. PERSONERÍA Y FIRMA", donor=83),
    p(86, "BLOQUEADO. El modelo identifica a ANGGIE GISSETH COUTIN MATURANA, mientras los soportes comunes revisados designan a NUBIA AIDE GALLEGO ÁLVAREZ. Ninguna firma debe insertarse hasta homologar el modelo y aportar poder válido para este asunto, certificado RNA vigente y correo coincidente.", color="C00000"),
    p(89, "Atentamente,"),
    p(95, "[NOMBRE DE LA APODERADA QUE ACREDITE PERSONERÍA]", color="C00000", bold=True),
    p(96, "[C.C., T.P., CORREO RNA Y CALIDAD — PENDIENTES]", color="C00000"),
]


CAUTION_SPECS = [
    p(7, "BORRADOR CONDICIONAL DE MEDIDA CAUTELAR — NO RADICAR, NO FIRMAR Y NO OFICIAR", color="C00000", bold=True, size=14),
    p(17, "Estado del protocolo civilista v2.0: ROJO. Los desprendibles de FOPEP son de 2022 y no prueban pagador ni mesada vigente en julio de 2026. Este módulo solo puede activarse después de la subsanación indicada.", color="C00000", bold=True),
    p(0, "Bogotá D.C., 17 de julio de 2026"),
    p(1, "SEÑOR JUEZ DE PEQUEÑAS CAUSAS Y COMPETENCIA MÚLTIPLE DE BOGOTÁ D.C. (REPARTO)", bold=True),
    p(2, "E. S. D."),
    p(7, "REFERENCIA: BORRADOR CONDICIONAL DE SOLICITUD DE MEDIDA CAUTELAR DENTRO DEL PROCESO EJECUTIVO DE MÍNIMA CUANTÍA."),
    p(8, "DEMANDANTE: COOPERATIVA DE AHORRO Y CRÉDITO CREAR, sigla CREARCOOP, NIT 890.981.459-4."),
    p(9, "APODERADA JUDICIAL: [POR DEFINIR DESPUÉS DE SUBSANAR PERSONERÍA].", color="C00000"),
    p(10, "DEMANDADA: MARÍA AMELIA CABIEDES PLATA, C.C. No. 41.526.686, deudora principal única."),
    p(11, "TÍTULO EJECUTIVO PROPUESTO: Pagaré No. 1911000986."),
    p(12, "OBLIGACIÓN: No. 10-1911000986."),
    p(14, "[COMPARECENCIA PENDIENTE]. No debe completarse ni firmarse hasta contar con poder válido e individualizado, profesional definida y correo coincidente con el Registro Nacional de Abogados.", color="C00000"),
    heading("I. ANTECEDENTES Y PRESUPUESTOS", donor=17),
    p(15, "La futura demanda se proyecta contra MARÍA AMELIA CABIEDES PLATA con fundamento en el pagaré No. 1911000986 y la obligación No. 10-1911000986, siempre que se subsanen previamente los defectos de exigibilidad, llenado, personería, liquidación y jurisdicción identificados en la auditoría.", bold=False),
    p(16, "El extracto de movimientos al 6 de julio de 2026 informa provisionalmente capital de $3.745.935, intereses remuneratorios de $1.670.935 e intereses moratorios de $67.662, para un total de $5.484.532. Estos valores requieren liquidación actualizada y reproducible."),
    p(17, "Los desprendibles incorporados al archivo de identificación sugieren históricamente pagos por FOPEP en julio y agosto de 2022. No permiten afirmar que la demandada sea actualmente pensionada, que FOPEP continúe como pagador, cuál sea la mesada neta ni qué descuentos o embargos existan."),
    heading("II. MEDIDA ÚNICA CONDICIONADA", donor=19, page_break_before=True),
    p(24, "Embargo y retención de mesada pensional — ACTIVAR SOLO CON PRUEBA VIGENTE", color="C00000", bold=True, keep_next=True),
    p(25, "Una vez aportada certificación vigente que individualice a FOPEP como pagador y a MARÍA AMELIA CABIEDES PLATA como pensionada, se proyecta solicitar el embargo y retención del porcentaje de la mesada pensional neta que el despacho determine proporcional, sin exceder el cincuenta por ciento (50 %), por tratarse de un crédito a favor de una cooperativa, con preservación del mínimo vital y respeto de descuentos de salud, órdenes alimentarias y embargos anteriores."),
    p(26, "El oficio deberá dirigirse exclusivamente al pagador actual acreditado. Antes de practicar la retención deberá solicitársele certificar mesada bruta y neta, descuentos, mesadas adicionales, órdenes preferentes y embargos vigentes. No se pedirá retención sobre mesadas adicionales ni un 50 % automático."),
    p(45, "La medida deberá limitarse al monto que el despacho fije conforme al artículo 599 del Código General del Proceso, sobre una liquidación actualizada. No se utilizará como regla general el incremento automático del 50 % previsto específicamente para depósitos bancarios en el numeral 10 del artículo 593."),
    heading("III. FUNDAMENTOS", donor=17),
    p(17, "La solicitud condicionada se sustenta en los artículos 593, 594 y 599 del Código General del Proceso; el numeral 5 del artículo 134 de la Ley 100 de 1993; los artículos 2.2.8.5.1 a 2.2.8.5.3 del Decreto 1833 de 2016; y los criterios de proporcionalidad y mínimo vital desarrollados, entre otras, en las sentencias T-418 de 2016 y T-678 de 2017 de la Corte Constitucional."),
    heading("IV. MEDIDAS EXPRESAMENTE EXCLUIDAS", donor=19),
    p(28, "No se solicitan búsquedas o embargos genéricos frente a todos los bancos, fondos pensionales, SNR, RUNT, cámaras de comercio, SIC, SECOP, empleadores indeterminados ni otros terceros. No existe soporte actual que individualice cuentas, productos, inmuebles, vehículos, contratos, derechos de propiedad industrial o empleador."),
    heading("V. CONDICIONES PARA ACTIVAR ESTE MÓDULO", donor=19),
    p(42, "1. Certificación o desprendible reciente que confirme la pensión, el pagador exacto y la mesada neta."),
    p(42, "2. Información de descuentos de salud, libranzas, embargos y órdenes alimentarias vigentes."),
    p(42, "3. Análisis documentado del mínimo vital y proporcionalidad del porcentaje pedido."),
    p(42, "4. Certificado vigente de CREARCOOP y prueba de titularidad actual del crédito."),
    p(42, "5. Poder válido e individualizado y profesional compareciente acreditada."),
    p(42, "6. Compromiso de pago de 31 de enero de 2026, liquidación actualizada y superación de los gates G3, G5, G6, G7, G9 y G10."),
    p(39, "Hasta cumplir todas estas condiciones, la medida no debe presentarse, oficiarse ni usarse para justificar la omisión del traslado previo prevista en el artículo 6 de la Ley 2213 de 2022.", color="C00000", bold=True),
    p(47, "Atentamente,"),
    p(52, "[NOMBRE DE LA APODERADA QUE ACREDITE PERSONERÍA]", color="C00000", bold=True),
    p(53, "[C.C., T.P. Y CORREO RNA — PENDIENTES]", color="C00000"),
    p(54, "[CALIDAD Y SOPORTE DE ACTUACIÓN — PENDIENTES]", color="C00000"),
]


AUDIT_SECTIONS = [
    p(7, "AUDITORÍA PREVIA DEL EXPEDIENTE 1911000986", bold=True, size=16),
    p(38, "Resultado: ROJO — NO APTO PARA FIRMA NI RADICACIÓN", color="C00000", bold=True, size=13),
    p(38, "Fecha de corte de la auditoría: 17 de julio de 2026. Alcance: demanda ejecutiva y solicitud cautelar de MARÍA AMELIA CABIEDES PLATA, C.C. 41.526.686."),
    heading("1. DECISIÓN EJECUTIVA"),
    p(38, "Los defectos encontrados afectan el mérito ejecutivo y no son simples errores de forma. Con los soportes actuales solo pueden emitirse borradores de control marcados NO RADICAR. No es responsable convertirlos en documentos listos para firma hasta cerrar la lista de faltantes de esta auditoría."),
    p(38, "Bloqueos principales: (i) capital y vencimiento diligenciados sin trazabilidad compatible con las instrucciones; (ii) compromiso de pago del 31 de enero de 2026 no aportado; (iii) liquidación no reproducible; (iv) personería incompatible con el modelo; (v) contrato incompleto para descartar pacto arbitral; (vi) ausencia de consultas oficiales de insolvencia y procesos paralelos; y (vii) ausencia de activo cautelable actual individualizado."),
    heading("2. IDENTIFICACIÓN DOCUMENTAL"),
    p(38, "Demandada: MARÍA AMELIA CABIEDES PLATA — C.C. 41.526.686 — deudora principal única."),
    p(38, "Pagaré: 1911000986 — obligación: 10-1911000986 — no existe codeudor en el título revisado."),
    p(38, "Archivo seleccionado: PAGARÉ 1911000986.pdf — 1.570.422 bytes — SHA-256 e1343ac3410ac6bfda11c7c609c19179c1948205e7966037d045f8e5f6497a86."),
    p(38, "Extracto: EXTRACTO 41526686.PDF — 226.709 bytes — SHA-256 71546930e962d960630b0114711340c4da393d9d8b84c1767f335ac3507b4ba6."),
    p(38, "Solicitud/liquidación/identificación: CC 41526686 PAGARE 1911000986.pdf — 1.966.009 bytes — SHA-256 4cd853c2d4110441d0369cc7c9948fd0a847f3b01b9890df81ade7a7d0d79916."),
    p(38, "Versión descartada para la ejecución: 1911000986.pdf — 911.069 bytes — SHA-256 8ed71ad680394daac73f21b85fa064043c4687dd96822b2d38a7ac2521abc7b6. Se preserva solo para auditoría y comparación; no debe anexarse como título ejecutivo."),
    heading("3. CONTRADICCIONES MATERIALES"),
    p(38, "3.1. El archivo histórico muestra el pagaré con campos esenciales en blanco; el archivo seleccionado los muestra diligenciados. El artículo 622 del Código de Comercio exige correspondencia estricta con las instrucciones."),
    p(38, "3.2. El pagaré diligenciado expresa capital de $12.000.000, mientras el extracto al 6 de julio de 2026 informa capital insoluto de $3.745.935. Las instrucciones ordenan conciliar el campo con lo adeudado según libros; se requiere explicación y acta de llenado."),
    p(38, "3.3. El vencimiento diligenciado es 6 de julio de 2026, pero el cronograma ordinario termina el 7 de octubre de 2026 y el extracto evidencia mora anterior. La cláusula del pagaré y la carta de instrucciones contienen criterios distintos para llenar el vencimiento. Debe documentarse la aceleración y su fecha."),
    p(38, "3.4. El extracto registra “GENERACIÓN COMPROMISO DE PAGO” CO 1000183 el 31 de enero de 2026 por $5.378.900 y recaudos posteriores por $832.750 el 28 de abril y el 25 de mayo de 2026. Sin el compromiso no puede excluirse modificación del plazo, refinanciación, novación, reconocimiento de pagos o renuncia temporal a la aceleración."),
    p(38, "3.5. El extracto no permite reproducir por sí solo bases, períodos, tasas y aplicación de pagos de los $1.670.935 de interés de plazo y $67.662 de mora."),
    p(38, "3.6. Parte del desembolso refinanció la obligación 1911000712. Falta constancia de cierre y de no cobro paralelo."),
    heading("4. PERSONERÍA"),
    p(38, "El modelo identifica a ANGGIE GISSETH COUTIN MATURANA. Los soportes comunes revisados designan a NUBIA AIDE GALLEGO ÁLVAREZ y muestran su RNA, pero no acreditan poder especial individualizado para esta deudora y este pagaré."),
    p(38, "El poder de cartera fechado el 2 de mayo de 2025 no individualiza este asunto. Conforme al artículo 74 del CGP, un poder especial debe determinar claramente los asuntos; el poder general se otorga por escritura pública. Debe reemplazarse o acreditarse la ruta válida."),
    p(38, "El certificado de la sociedad apoderada no demuestra que Anggie esté inscrita como profesional de la sociedad ni existe sustitución acreditada a su favor. El modelo también contenía un hipervínculo residual a marlinpalacios0913@gmail.com; fue eliminado de estos borradores."),
    heading("5. CUANTÍA, COMPETENCIA Y JURISDICCIÓN"),
    p(38, "Saldo provisional: $5.484.532. SMLMV 2026: $1.750.905. Umbral de 40 SMLMV: $70.036.200. El asunto es de mínima cuantía y el encabezado correcto, si no existe pacto arbitral, es JUEZ DE PEQUEÑAS CAUSAS Y COMPETENCIA MÚLTIPLE DE BOGOTÁ D.C. (REPARTO)."),
    p(38, "La dirección Carrera 8G No. 159B-25 corresponde documentalmente a San Cristóbal Norte, localidad de Usaquén. No debe utilizarse “Bogotá, Cundinamarca”. El reparto, no el demandante, definirá el juzgado específico."),
    p(38, "La Ley 2540 de 2025 rige desde el 27 de febrero de 2026 y permite arbitraje ejecutivo cuando existe pacto aplicable. Falta contrato completo y certificación de búsqueda de documento anexo o cláusula compromisoria."),
    heading("6. INTERESES"),
    p(38, "Capital provisional: $3.745.935. Intereses remuneratorios provisionales: $1.670.935. Mora provisional: $67.662. Total al 6 de julio de 2026: $5.484.532."),
    p(38, "El crédito fue originado y desembolsado el 7 de octubre de 2022. El parágrafo transitorio del Decreto 455 de 2023 ordena aplicar hasta agotar el saldo la certificación de microcrédito de la Resolución SFC 1968 de 2022: IBC 39,20 % E.A. y máximo remuneratorio o moratorio 58,80 % E.A. La tasa contractual de 26,82 % E.A. es inferior."),
    p(38, "No debe solicitarse mora futura sobre intereses remuneratorios ni moratorios. Si se valida el vencimiento del 6 de julio de 2026, la mora futura sobre capital iniciaría el 7 de julio de 2026; esta fecha permanece bloqueada hasta explicar el compromiso y el llenado."),
    heading("7. MEDIDA CAUTELAR"),
    p(38, "La única pista individualizada es FOPEP, basada en desprendibles de julio y agosto de 2022. No prueba situación actual. No procede pedir a todos los fondos, bancos, SNR, RUNT, cámaras de comercio, SIC, SECOP ni empleadores indeterminados."),
    p(38, "Para un crédito de cooperativa, la mesada puede embargarse sin exceder el 50 %, pero ese porcentaje es un techo y no una orden automática. Deben preservarse mínimo vital, descuentos de salud, órdenes alimentarias y embargos anteriores. Se requiere certificación actual del pagador y de la mesada neta."),
    p(38, "El incremento del 50 % del numeral 10 del artículo 593 CGP corresponde a depósitos bancarios; no es un límite universal. El artículo 599 contiene la regla general de proporcionalidad y tope sobre bienes cautelados."),
    heading("8. NOTIFICACIÓN Y DATOS PERSONALES"),
    p(38, "La dirección, el celular y el correo provienen de documentos de 2022. El correo manuscrito no puede leerse con certeza suficiente. No debe jurarse ni usarse para notificación electrónica hasta obtener evidencia reciente de titularidad y uso, conforme al artículo 8 de la Ley 2213 de 2022."),
    heading("9. SEMÁFORO POR GATES"),
    p(38, "G0 Vigencia oficial: AMARILLO — normas y tasa transitoria verificadas; debe repetirse control inmediatamente antes de radicar."),
    p(38, "G1 Identidad y legitimación: AMARILLO — coinciden nombre, cédula, título y extracto; faltan certificado corporativo vigente, titularidad y custodia del original."),
    p(38, "G2 Personería y firmante: ROJO — modelo y soportes no coinciden; poder no individualizado."),
    p(38, "G3 Jurisdicción y arbitraje: ROJO — contrato incompleto y ausencia de certificación sobre pacto arbitral."),
    p(38, "G4 Competencia y cuantía: AMARILLO — mínima cuantía calculada; domicilio y reparto deben actualizarse al presentar."),
    p(38, "G5 Título claro, expreso y exigible: ROJO — llenado, vencimiento y compromiso impiden afirmarlo."),
    p(38, "G6 Requisitos y llenado: ROJO — capital y vencimiento no conciliados con instrucciones."),
    p(38, "G7 Mora, vencimiento y aceleración: ROJO — fecha de aceleración y efecto del compromiso no establecidos."),
    p(38, "G8 Prescripción: ROJO — el literal indica 6 de julio de 2029, pero la instrucción ligada al inicio de mora permite una defensa anterior."),
    p(38, "G9 Capital, pagos e intereses: ROJO — liquidación no reproducible ni actualizada."),
    p(38, "G10 Insolvencia y procesos paralelos: ROJO — no existen consultas oficiales documentadas."),
    p(38, "G11 Justicia digital, anexos y cautela: ROJO — canales antiguos, personería digital incompleta y ningún activo actual individualizado."),
    heading("10. DEFENSAS PREVISIBLES"),
    p(38, "La contraparte podría alegar: llenado contrario a instrucciones; inexigibilidad; aceleración no demostrada; pago parcial; acuerdo, reestructuración o novación; cobro de lo no debido; liquidación incorrecta; prescripción; personería irregular; falta de jurisdicción o competencia; nulidad de notificación; cautela excesiva; y doble cobro de la obligación refinanciada."),
    p(38, "Como juez, los defectos formales justificarían inadmisión y los defectos del título podrían llevar a negar el mandamiento o a estimar excepciones. Las cautelas genéricas deberían negarse."),
    heading("11. LISTA CERRADA DE SUBSANACIONES"),
    p(38, "1. Compromiso de pago completo del 31 de enero de 2026, cronograma, comunicaciones e incumplimiento."),
    p(38, "2. Certificación contable que explique el movimiento CO 1000183 y los pagos posteriores."),
    p(38, "3. Liquidación actualizada y reproducible a la fecha de radicación, con capital base, períodos, tasas y aplicación de cada pago."),
    p(38, "4. Acta de llenado y cadena de custodia: fecha, responsable, fuente de cada campo, original, no circulación y no endoso."),
    p(38, "5. Explicación jurídica y contable del capital $12.000.000 y vencimiento 6 de julio de 2026; si no se reconcilian, ratificación expresa o nuevo título válido."),
    p(38, "6. Certificado vigente de CREARCOOP y soporte de facultades de quien otorgó el poder."),
    p(38, "7. Poder especial individualizado para esta deudora y pagaré, o poder general por escritura pública."),
    p(38, "8. Definición de apoderada: soporte societario o sustitución, RNA vigente y correo coincidente."),
    p(38, "9. Contrato y condiciones completas, con certificación de búsqueda de pacto arbitral."),
    p(38, "10. Consultas oficiales recientes de insolvencia, reorganización, fallecimiento, sucesión y procesos paralelos."),
    p(38, "11. Constancia de cierre y no cobro separado de la obligación refinanciada No. 1911000712."),
    p(38, "12. Evidencia reciente de domicilio, localidad, celular y canal electrónico."),
    p(38, "13. Certificación reciente de FOPEP, pagador, clase de pensión, mesada neta, descuentos y embargos; o soporte actual de otro activo individualizado."),
    p(38, "14. Certificaciones oficiales de la tasa aplicada y control final del límite de intereses."),
    p(38, "15. Índice final de anexos, páginas, legibilidad, hashes y verificación de custodia."),
    heading("12. FUENTES OFICIALES VERIFICADAS"),
    p(38, "Código General del Proceso: https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=48425"),
    p(38, "Código de Comercio: https://www1.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=41102"),
    p(38, "Ley 2213 de 2022: https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=187626"),
    p(38, "Ley 2540 de 2025: https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=263541"),
    p(38, "Decreto 455 de 2023: https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=205884"),
    p(38, "Resolución SFC 1968 de 2022 y comunicado oficial: https://www.superfinanciera.gov.co/publicaciones/10112914/"),
    p(38, "Ley 100 de 1993, artículo 134: https://www1.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=5248"),
    p(38, "Decreto 1833 de 2016: https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=85319"),
    p(38, "Ley 2220 de 2022: https://www.funcionpublica.gov.co/eva/gestornormativo/norma.php?i=188766"),
    p(38, "SMLMV 2026, Decreto 159 de 2026: https://www.suin-juriscol.gov.co/viewDocument.asp?id=30056106"),
    heading("13. CONTROL DE PLANTILLA Y CALIDAD"),
    p(38, "No aparecieron en Drive ni en la carpeta local las dos plantillas canónicas nombradas por el protocolo. Los borradores conservaron papel legal, encabezados y pies de los modelos disponibles, pero sustituyeron el texto incompatible y eliminaron instrucciones de color, codeudor de muestra, datos del expediente de ejemplo, autorización del dependiente no soportado e hipervínculos residuales."),
    p(38, "Clasificación autorizada del producto: únicamente BORRADOR DE CONTROL — NO RADICAR. El expediente no puede declararse APTO PARA REVISIÓN Y FIRMA HUMANA hasta cerrar todos los gates rojos."),
]


def validate_docx(path, forbidden):
    with ZipFile(path, "r") as z:
        bad_zip = z.testzip()
        if bad_zip:
            raise RuntimeError(f"Miembro ZIP defectuoso en {path.name}: {bad_zip}")
        xml = z.read("word/document.xml")
        root = etree.fromstring(xml)
        text = "\n".join(root.xpath("//w:t/text()", namespaces=NS))
        for term in forbidden:
            if term.lower() in text.lower():
                raise RuntimeError(f"Término residual {term!r} en {path.name}")
        colors = {
            node.get(qn(NS_W, "val"), "").upper()
            for node in root.findall(f".//{qn(NS_W, 'color')}")
        }
        if {"00B050", "00B0F0"} & colors:
            raise RuntimeError(f"Persisten colores guía en {path.name}")
        return text, sha256(path.read_bytes()).hexdigest()


def main():
    OUT.mkdir(parents=True, exist_ok=True)
    demand_path = OUT / "BORRADOR_CONTROL_DEMANDA_1911000986_NO_RADICAR.docx"
    caution_path = OUT / "BORRADOR_CONTROL_MEDIDA_CAUTELAR_1911000986_NO_RADICAR.docx"
    audit_path = OUT / "AUDITORIA_PREVIA_ROJA_1911000986.docx"

    make_docx(
        DEMAND_TEMPLATE,
        demand_path,
        DEMAND_SPECS,
        "Borrador de control demanda 1911000986 — NO RADICAR",
    )
    make_docx(
        CAUTION_TEMPLATE,
        caution_path,
        CAUTION_SPECS,
        "Borrador condicional de medida cautelar 1911000986 — NO RADICAR",
    )
    make_docx(
        DEMAND_TEMPLATE,
        audit_path,
        AUDIT_SECTIONS,
        "Auditoría previa roja — expediente 1911000986",
    )

    forbidden = [
        "JEFFERSON",
        "SUAREZ",
        "2312000044",
        "JHEISON",
        "LO QUE ESTÉ DE COLOR",
        "LO QUE ESTÁ EN COLOR",
    ]
    for path in (demand_path, caution_path, audit_path):
        path_forbidden = forbidden + ([] if path == audit_path else ["marlinpalacios0913"])
        text, digest = validate_docx(path, path_forbidden)
        print(f"{path.name}\t{path.stat().st_size}\t{digest}\t{len(text)} caracteres")


if __name__ == "__main__":
    main()
