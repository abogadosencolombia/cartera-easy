from hashlib import sha256
from importlib.util import module_from_spec, spec_from_file_location
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile

from lxml import etree


ROOT = Path("/code/DEMANDAS")
OUT = ROOT / "ENTREGABLES_1912000833_V2"
HELPER = ROOT / "_work_1911000986" / "generar_paquete_v2_1911000986.py"
DEMAND_TEMPLATE = ROOT / "MODELO.docx"
CAUTION_TEMPLATE = ROOT / "MODELO MEDIDAS.docx"

spec = spec_from_file_location("helper1911v2", HELPER)
helper = module_from_spec(spec)
spec.loader.exec_module(helper)

NS = helper.NS
make = helper.make
p = helper.p
h = helper.h

RED = "C00000"
NAVY = "1F4E78"


INFORME = [
    p("INFORME INTEGRAL DE CONTROL — EXPEDIENTE 1912000833", donor=7, bold=True, size=16),
    p("Ángela Yisela Restrepo Varón y José Efraín Hincapié Villa · versión 2 · 18 de julio de 2026", bold=True, color=NAVY),
    p("DECISIÓN: ROJO — NO APTO PARA FIRMA NI RADICACIÓN", bold=True, color=RED, size=13),
    p("El paquete queda estructurado para precierre, no para presentación. Deben cerrarse saldo, movimientos internos, exigibilidad, llenado, custodia, personería, jurisdicción, notificaciones y cautela. Cada control rojo impide liberar el Word."),
    h("1. CONCLUSIÓN EJECUTIVA"),
    p("El pagaré No. 1912000833 identifica a ÁNGELA YISELA RESTREPO VARÓN, C.C. 1.033.789.770, como deudora, y a JOSÉ EFRAÍN HINCAPIÉ VILLA, C.C. 1.061.655.910, como codeudor. Ambos firmaron y estamparon huella en el pagaré y en la carta separada; el texto contiene obligación solidaria e indivisible."),
    p("El extracto al 6 de julio de 2026 permite conciliar mecánicamente el capital: $6.000.000 menos $2.117.348 de créditos o aplicaciones contables equivale a $3.882.652. Esa cifra no es definitiva hasta explicar el movimiento CO 1000142 de 31 de enero de 2024 por $72.891. Si ese CO no fue extintivo, el cálculo diagnóstico sería $3.955.543; no es una pretensión alternativa. No debe reclamarse el capital facial de $6.000.000 ni escogerse saldo sin soporte."),
    p("El interés de plazo informado, $852.162, coincide exactamente entre pagaré y extracto, pero no se reproduce: el plan registra $3.600.588 de interés programado y el extracto muestra $2.399.491 de créditos, cuya diferencia es $1.201.097. Deben explicarse $348.935 y separarse interés causado, futuro, condonado o reliquidado. La mora tampoco concilia: $1.346.743 de cargos menos $21.824 de créditos da $1.324.919, no $1.421.444; faltan $96.525 en el detalle."),
    p("La fecha de vencimiento insertada, 6 de julio de 2026, es posterior al vencimiento ordinario del plan, 20 de septiembre de 2025. Ello no prueba cuándo se diligenció materialmente el título ni descarta una mora o aceleración anterior. Deben acreditarse la primera mora relevante, cualquier prórroga o compromiso y la fecha real de llenado."),
    p("La razón impresa en el título es COOPERATIVA DE AHORRO Y CRÉDITO CREAR LTDA. Un certificado común de 5 de febrero de 2026, ajeno al expediente individual, informa la razón COOPERATIVA DE AHORRO Y CRÉDITO CREAR, sigla CREARCOOP, NIT 890.981.459-4. Debe obtenerse y anexarse certificado vigente que pruebe continuidad, representación y canales al presentar."),
    h("2. SELECCIÓN Y ANÁLISIS FORENSE DEL TÍTULO"),
    p("Título seleccionado: PAGARÉ 1912000833.pdf, tres páginas, SHA-256 d1404c1ee17417294da5a5ef6c844bc10ca2482cfe59050f4b1ebda9b1abc1b8. Archivo descartado como título: 1912000833.pdf, SHA-256 0bf108a2fba057141c6821cdcdade9265bd90024fee1e2b93b1565f5f7da99d0. El segundo se conserva solo para auditoría de trazabilidad."),
    p("El archivo seleccionado fue procesado por iLovePDF el 16 de julio de 2026 y mezcla página 1 A4 con páginas 2 y 3 tamaño carta; el anterior fue creado el 30 de mayo de 2024 y tiene tres páginas carta. La página 1 actual es una nueva digitalización diligenciada, mientras las imágenes de las páginas 2 y 3 son idénticas byte por byte a las anteriores. En la página 1 previa ya constaban 20-09-22 y 1912000833; estaban en blanco agencia, capital, interés, tasa, vencimiento, nombres e identificaciones, y no figuraba ‘36 cuotas mensuales’. La composición es compatible con llenado posterior autorizado, pero no prueba fecha, persona, fuente ni identidad física del original."),
    h("3. DATO–SOPORTE"),
    p("[[TABLA_DATOS]]"),
    h("4. RECONCILIACIÓN CONTABLE"),
    p("[[TABLA_CONTABLE]]"),
    p("El saldo resumen es $6.156.258: capital $3.882.652, interés $852.162 y mora $1.421.444. La suma de los tres rubros es correcta; no lo son, con la información visible, las memorias de interés y mora. El extracto tiene además errores sintácticos de estructura PDF al ser leído, aunque las tres páginas se renderizan y los datos son visibles; conviene obtener exportación nativa o certificada."),
    p("Los RC y CB suman $4.415.272: $2.044.457 a capital, $2.351.678 a interés y $19.137 a mora. No todos prueban recaudo externo: CB 100030121 por $239.040 dice ‘cancelación bono aut cartera’; CB 100002605 por $146.412 dice ‘CARTERA’; y CB 1200000010 por $519.820 dice ‘ABONO A CRÉDITO, COMPROMISO Y CTA HONORARIOS SOLICITA CARTERA’. La NA 1000378 por $50.500 aplica $47.813 a interés y $2.687 a mora; tampoco es pago demostrado. El último crédito visible es el CB de 6 de febrero de 2024; no hay créditos posteriores. El CO 1000142 de 31 de enero aplica $72.891 a capital. Se requieren documentos fuente, clasificación, efecto, autoría, aceptación y oponibilidad, además de determinar separadamente el último pago efectivo probado."),
    h("5. LLENADO, VENCIMIENTO Y PRESCRIPCIÓN"),
    p("El pagaré fue completado por $6.000.000 de capital, $852.162 de interés, tasa 39,29 %, treinta y seis cuotas y vencimiento 6 de julio de 2026. La instrucción incorporada ordenaba insertar como CAPITAL lo adeudado según registros y como vencimiento el inicio de mora; la carta separada ordenaba usar el día de llenado. El contraste entre capital facial y saldo contable, unido a la coincidencia del interés con el extracto, es consistente con un llenado híbrido, pero no lo prueba por sí solo. También se añadieron ‘36 cuotas mensuales’, nombres y cédulas en campos sin regla específica de llenado, aunque coinciden con las páginas firmadas; deben explicarse dentro de la misma trazabilidad."),
    p("El plan muestra cuota 36 el 20 de septiembre de 2025. Si hubo compromiso o prórroga, debe aportarse y calificarse. La fecha facial llevaría en principio la acción cambiaria directa al 6 de julio de 2029, pero las cuotas y la eventual regla de inicio de mora pueden generar defensas de prescripción anteriores. El movimiento interno no prueba por sí solo reconocimiento interruptivo. Si se acredita un acto atribuible a uno de los firmantes, su eficacia frente al otro deberá analizarse conforme a los artículos 632 y 792 del Código de Comercio y 2540 del Código Civil, incluida la regla de los signatarios solidarios en un mismo grado."),
    h("6. SEMÁFORO DE GATES"),
    p("[[TABLA_GATES]]"),
    h("7. ANÁLISIS COMO CONTRAPARTE"),
    p("La defensa alegará llenado contrario a instrucciones, pago o acuerdo, cobro de lo no debido, prescripción de cuotas, intereses futuros incorporados, mora no reproducible, ausencia de original/custodia, personería, pacto arbitral, notificación y cautela excesiva. Pedirá exhibir el compromiso de enero de 2024 y destacará que el capital facial quedó original mientras el interés se actualizó exactamente al corte."),
    p("También puede controvertir cualquier medida sobre el vehículo con la antigüedad del dato, la propiedad actual, gravámenes, afectaciones, identificación del automotor y proporcionalidad. Una declaración de 2022 no sustituye el certificado RUNT o de tránsito al día."),
    h("8. ANÁLISIS COMO JUEZ"),
    p("Un juez puede reconocer fuerza facial al título, pero limitar o negar conceptos no claros, expresos y exigibles conforme a los artículos 422 y 430 del CGP. El artículo 622 del Código de Comercio admite llenar espacios solo con arreglo a instrucciones. La acción por la parte insoluta requiere conciliar saldo y explicar el instrumento complejo; los artículos 624 y 782 permiten reclamar la parte no pagada cuando quede demostrada."),
    p("La futura formulación debe usar una sola cifra de capital certificada; incluir remuneratorios históricos solo con memoria que excluya interés futuro; conciliar mora histórica; y liquidar mora futura únicamente sobre capital desde el día siguiente a exigibilidad válida, a la tasa legalmente aplicable en cada periodo. Hasta cerrar esos puntos, no radicar."),
    h("9. CAUTELA"),
    p("La solicitud del codeudor declara un vehículo Nissan, placa FLJ238 y valor histórico $33.300.000. El número ‘1998’ aparece en el campo ‘Moneda’, no en ‘Modelo’, y no debe afirmarse como año/modelo sin RUNT. La certificación comercial histórica confirma uso de la placa, no titularidad. La cautelar queda condicionada a consulta/certificación actual de RUNT y autoridad de tránsito que confirme propietario e individualice el automotor."),
    p("La deudora declaró vivienda propia y un valor aproximado, pero no aportó matrícula inmobiliaria ni prueba de titularidad; no sustenta embargo. Si RUNT no confirma el vehículo, debe retirarse la cautelar y cumplirse la ruta de envío que corresponda. No se pedirán bancos, inmuebles u otros vehículos indeterminados."),
    h("10. ANEXOS E ÍNDICE DE CIERRE"),
    p("[[TABLA_ANEXOS]]"),
]

DATOS_ROWS = [
    ("Deudora", "Ángela Yisela Restrepo Varón — C.C. 1.033.789.770", "Pagaré pp. 1–3; extracto; CC p. 5", "Coincidente"),
    ("Codeudor", "José Efraín Hincapié Villa — C.C. 1.061.655.910", "Pagaré pp. 1–3; CC p. 8", "Coincidente"),
    ("Título", "Pagaré No. 1912000833", "PAGARÉ 1912000833.pdf", "Evidenciado"),
    ("Obligación", "10-1912000833", "Extracto y liquidación", "Coincidente"),
    ("Otorgamiento", "20 de septiembre de 2022", "Pagaré p. 1; liquidación", "Coincidente"),
    ("Aprobación", "$6.000.000; microempresarial nuevos informales", "Liquidación p. 1", "Evidenciado"),
    ("Neto registrado", "$5.478.000 como ‘neto a desembolsar’", "Liquidación p. 1", "Recibo autónomo pendiente"),
    ("Plan", "36 cuotas de $266.683; final 20/9/2025", "Liquidación pp. 1–2", "Evidenciado"),
    ("Tasa", "33,60 % N.A.M.V. / 39,29 % E.A., fija", "Liquidación y pagaré", "Coincidente"),
    ("Vencimiento facial", "6 de julio de 2026", "Pagaré p. 1", "Posterior al plan"),
    ("Capital facial", "$6.000.000", "Pagaré p. 1", "Contradice saldo"),
    ("Capital a corte", "$3.882.652 al 6/7/2026", "Extracto p. 3", "Mecánico; CO pendiente"),
    ("Interés informado", "$852.162", "Pagaré y extracto", "No reproducible"),
    ("Mora informada", "$1.421.444", "Extracto p. 3", "No reproducible"),
    ("Nombre Ángela", "Prevalece Yisela; certificación p. 9 escribe Yissela", "CC p. 5 vs. certificación p. 9", "Usar cédula"),
    ("Direcciones Ángela", "Cliente: ‘MZ 24 SUR No 15 LO’; residencia Lote 15 manzana 29; negocio Dg 78 D Bis No. 26-08 Sur", "Liquidación p. 1; solicitud p. 3", "Tres datos 2022 / territorio invertido"),
    ("Dirección José", "Carrera 2 No. 3-45, casa 14, Pueblo Viejo [histórica/manuscrita]", "Solicitud p. 6", "Municipio/departamento dudosos"),
    ("Actividad José", "Cr 2 No. 3-45, Pueblo Viejo; campos sugieren Cota/Cundinamarca sin certeza", "Solicitud p. 6", "Pista 2022 / no corregir por inferencia"),
    ("CC José en soportes", "Prevalece 1.061.655.910; solicitud dice 1.061.655.940 y carta comercial 1.061.651.910", "CC pp. 6, 8 y 10", "Contradictorio / usar cédula"),
    ("Fecha solicitud José", "12/9/2020 en p. 6; continuación/entrevista 12/9/2022", "CC pp. 6–7", "Contradictorio"),
    ("Vehículo José", "Nissan, placa FLJ238, $33.300.000 declarado; ‘1998’ en campo Moneda", "Solicitud p. 7; carta p. 10", "Histórico / modelo incierto"),
    ("Terceros", "Víctor Muñoz: compañero, no obligado; Alejandro Cogua/Reciclajes: certificador histórico", "CC pp. 4 y 9–10", "No prueba pagador ni activo actual"),
]

CONT_ROWS = [
    ("Capital", "$6.000.000", "$2.117.348 créditos/aplicaciones", "$3.882.652", "$3.882.652", "Mecánico"),
    ("Interés", "$3.251.653 base implícita; $3.600.588 programado", "$2.399.491", "$852.162 con base implícita", "$852.162", "Base no impresa; diferencia diagnóstica $348.935"),
    ("Mora", "$1.346.743", "$21.824", "$1.324.919", "$1.421.444", "No: +$96.525"),
    ("Total", "—", "—", "—", "$6.156.258", "Solo suma rubros"),
]

GATES_ROWS = [
    ("G0", "Norma vigente", "AMARILLO", "Revalidar fuentes oficiales, tasas, competencia y cuantía al presentar."),
    ("G1", "Identidad/legitimación", "AMARILLO", "Coinciden partes y título; falta certificado vigente de acreedora."),
    ("G2", "Personería", "ROJO", "Poder especial para ambos, apoderada/RNA y membrete/contactos pendientes."),
    ("G3", "Jurisdicción", "ROJO", "Contrato y anexos incompletos; control arbitral interno pendiente."),
    ("G4", "Competencia", "ROJO", "Direcciones históricas y campos territoriales inconsistentes; actualizar domicilio."),
    ("G5", "Título/original", "ROJO", "Faltan original, custodia, no circulación y trazabilidad del llenado."),
    ("G6", "Llenado", "ROJO", "Capital facial, interés coincidente con extracto, agencia vacía e instrucciones incompatibles."),
    ("G7", "Exigibilidad", "ROJO", "Plan terminó 20/9/2025; falta acuerdo/prórroga y razón del 6/7/2026."),
    ("G8", "Prescripción", "ROJO", "Facial 6/7/2029; cuotas pueden tener términos anteriores; analizar compromiso."),
    ("G9", "Saldo/intereses", "ROJO", "Capital mecánico; interés y mora no concilian; corte no actualizado."),
    ("G10", "Compromiso/movimientos", "ROJO", "Faltan CO, NA, CB ambiguos, último crédito y último pago efectivo probado."),
    ("G11", "Insolvencia/procesos", "ROJO", "No hay consultas oficiales recientes de ambos obligados."),
    ("G12", "Notificación/cautela", "ROJO", "Canales 2022; vehículo sin confirmación actual RUNT."),
]

ANEXOS_ROWS = [
    ("A1", "Pagaré + instrucciones", "PAGARÉ 1912000833.pdf", "3", "Disponible / original pendiente"),
    ("A2", "Liquidación, solicitudes, CC y soportes históricos", "CC 1033789770 PAGARE 1912000833.pdf", "10", "Disponible / depurar"),
    ("A3", "Extracto histórico al 6/7/2026", "EXTRACTO 1033789770.PDF", "3", "Disponible / estructura PDF defectuosa"),
    ("A4", "CO/NA/CB + certificación y memoria", "[NOMBRE EXACTO]", "[●]", "Pendiente"),
    ("A5", "Llenado, vencimiento y custodia", "[NOMBRE EXACTO]", "[●]", "Pendiente"),
    ("A6", "Personería, contrato, consultas, notificación y RUNT/avalúo", "[NOMBRES EXACTOS]", "[●]", "Pendiente"),
]


DEMANDA = [
    p("PRECIERRE — NO RADICAR NI FIRMAR HASTA REEMPLAZAR TODOS LOS CAMPOS ROJOS", donor=7, bold=True, color=RED, size=13),
    p("[CIUDAD REAL DE PRESENTACIÓN], [FECHA REAL DE PRESENTACIÓN]", donor=0, color=RED),
    p("SEÑOR JUEZ DE PEQUEÑAS CAUSAS Y COMPETENCIA MÚLTIPLE DE [CIUDAD/JURISDICCIÓN PROBADA] (REPARTO)", donor=1, bold=True, color=RED),
    p("E. S. D.", donor=2),
    p("REFERENCIA: DEMANDA EJECUTIVA DE MÍNIMA CUANTÍA — PAGARÉ No. 1912000833 — OBLIGACIÓN No. 10-1912000833.", donor=7),
    p("DEMANDANTE: COOPERATIVA DE AHORRO Y CRÉDITO CREAR, sigla CREARCOOP, NIT 890.981.459-4.", donor=8),
    p("DEMANDADOS: ÁNGELA YISELA RESTREPO VARÓN, C.C. No. 1.033.789.770, y JOSÉ EFRAÍN HINCAPIÉ VILLA, C.C. No. 1.061.655.910.", donor=9),
    p("[NOMBRE DE LA APODERADA], abogada en ejercicio y apoderada judicial de CREARCOOP conforme al poder especial adjunto, presento demanda ejecutiva de mínima cuantía contra ÁNGELA YISELA RESTREPO VARÓN, deudora, y JOSÉ EFRAÍN HINCAPIÉ VILLA, codeudor solidario, para obtener el pago del saldo insoluto respaldado por el pagaré No. 1912000833 y por los registros de pagos, créditos o aplicaciones contables aportados.", donor=15, color=RED),
    h("I. HECHOS", donor=16),
    p("PRIMERO. ÁNGELA YISELA RESTREPO VARÓN, C.C. 1.033.789.770, como deudora, y JOSÉ EFRAÍN HINCAPIÉ VILLA, C.C. 1.061.655.910, como codeudor, suscribieron el pagaré No. 1912000833, obligación No. 10-1912000833, y se obligaron solidaria e indivisiblemente a la orden de la entidad denominada en el título COOPERATIVA DE AHORRO Y CRÉDITO CREAR LTDA., sigla CREARCOOP. [ADJUNTAR CERTIFICADO VIGENTE Y SUSTITUIR ESTE CONTROL POR EL HECHO EXACTO DE CONTINUIDAD CON LA RAZÓN ACTUAL].", donor=17, color=RED),
    p("SEGUNDO. La liquidación registra aprobación por SEIS MILLONES DE PESOS M/CTE ($6.000.000), modalidad microempresarial nuevos informales, fecha de desembolso 20 de septiembre de 2022 y valor ‘neto a desembolsar’ $5.478.000. Registra créditos por Ley Mipyme $270.000, estudio $22.000, Fondo de Solidaridad $90.000 y afiliación/aportes $120.000; además, bajo ‘Documentos soporte’ figura un crédito de $20.000 denominado Bono Futuro. [APORTAR COMPROBANTE AUTÓNOMO DE ENTREGA O CERTIFICACIÓN TRAZABLE].", donor=18, color=RED),
    p("TERCERO. El plan previó treinta y seis (36) cuotas mensuales de $266.683, primera el 20 de octubre de 2022 y última el 20 de septiembre de 2025, a tasa fija de 33,60 % N.A.M.V., equivalente documentalmente a 39,29 % E.A.", donor=19),
    p("CUARTO. Ambos demandados suscribieron el pagaré con espacios destinados a ser completados y autorizaron su diligenciamiento conforme a instrucciones incorporadas y carta separada.", donor=20),
    p("QUINTO. [INSERTAR CONTENIDO PROBADO DE CO 1000142 Y DEL COMPROMISO: fecha, autoría, aceptación, oponibilidad frente a cada firmante, cuotas, prórroga, efectos, incumplimiento, pagos y subsistencia del pagaré].", donor=21, color=RED),
    p("SEXTO. [IDENTIFICAR Y PROBAR: (i) la primera cuota impagada y el inicio de mora; (ii) cualquier compromiso, prórroga o reestructuración y su efecto bajo la cláusula DÉCIMA; y (iii) la fecha material de llenado. DETERMINAR SI 6/7/2026 SATISFACE AMBAS INSTRUCCIONES; SI NO, EXPONER LA BASE JURÍDICA Y PROBATORIA PARA RESOLVER SU CONTRADICCIÓN. SI NO PUEDE SUSTENTARSE, NO RADICAR CON EL TÍTULO ASÍ DILIGENCIADO].", donor=22, color=RED),
    p("SÉPTIMO. [USAR SOLO SI CO 1000142 Y LOS DEMÁS MOVIMIENTOS REDUJERON DEFINITIVAMENTE LA DEUDA] Al 6 de julio de 2026, los registros muestran $2.117.348 de créditos o aplicaciones a capital; descontados de $6.000.000 arrojan $3.882.652. CREARCOOP limita la acción a la parte no pagada. [SI NO FUERON EXTINTIVOS, REHACER TODO EL HECHO CON SOPORTE].", donor=26, color=RED),
    p("OCTAVO. [CERTIFICAR EL DÍA DE PRESENTACIÓN] A [FECHA/HORA], el capital insoluto es $[CAPITAL CERTIFICADO], los remuneratorios causados son $[●], la mora causada es $[●] y desde [FECHA] [NO HUBO/HUBO] pagos o ajustes [DETALLE].", donor=27, color=RED),
    p("NOVENO. [DECIDIR CON APROBACIÓN EXPRESA] Los $852.162 de interés y $1.421.444 de mora del extracto histórico [SE EXCLUYEN POR NO SER REPRODUCIBLES / SE RECLAMAN CON LA MEMORIA ANEXA]. Si se excluyen, documentar riesgo de fragmentación o disposición; si se reclaman, insertar base, tasa, periodos, pagos y sumas exactas.", donor=28, color=RED),
    p("DÉCIMO. [SOLO CON CERTIFICACIÓN] CREARCOOP conserva el original físico, no lo ha endosado ni circulado y lo exhibirá cuando el despacho lo requiera.", donor=29, color=RED),
    h("II. PRETENSIONES", donor=30),
    p("Solicito librar mandamiento a favor de CREARCOOP y contra ambos demandados, solidariamente, por:", donor=31),
    p("PRIMERA. [CAPITAL EN LETRAS] PESOS M/CTE ($[CAPITAL CERTIFICADO]), por capital insoluto. El corte histórico arroja mecánicamente $3.882.652, sujeto a soporte del CO y actualización.", donor=32, color=RED),
    p("SEGUNDA. Intereses moratorios exclusivamente sobre el capital insoluto certificado, desde [DÍA SIGUIENTE A EXIGIBILIDAD VALIDADA] hasta el pago, a la tasa moratoria legalmente aplicable en cada periodo, sin exceder la máxima autorizada, sin capitalización ni superposición.", donor=33, color=RED),
    p("[MÓDULO OPCIONAL] TERCERA. [SUMA EN LETRAS] PESOS ($[●]) por intereses [REMUNERATORIOS/MORATORIOS] causados entre [FECHAS], sobre [BASE], a [TASA], menos [PAGOS]. SI SE USA, COSTAS PASA A CUARTA; SI NO, ELIMINAR.", donor=34, color=RED),
    p("TERCERA [O CUARTA]. Por costas y agencias en derecho.", donor=35, color=RED),
    h("III. FUNDAMENTOS DE DERECHO"),
    p("Artículos 17, 25, 26, 28, 82, 84, 422, 430 y 431 del CGP; 619, 621, 622, 624, 632, 709, 710, 782, 785, 789, 792 y 884 del Código de Comercio; 2540 del Código Civil; artículo 69 de la Ley 45 de 1990; Ley 2213 de 2022 y normas concordantes.", donor=38),
    h("IV. COMPETENCIA, TRÁMITE Y CUANTÍA", donor=40),
    p("Es competente [JUEZ Y CIUDAD] por [DOMICILIO ACTUAL PROBADO O REGLA TERRITORIAL], la naturaleza ejecutiva y la mínima cuantía, conforme a los artículos 17, 25, 26 y 28 del CGP. Los soportes de 2022 no prueban domicilio actual y contienen campos municipales inconsistentes. [SUSTITUIR POR HECHO TERRITORIAL EXACTO].", donor=41, color=RED),
    p("La cuantía es [TOTAL ACTUAL EN LETRAS] ($[TOTAL ACTUAL]), sin intereses futuros ni costas, inferior a cuarenta (40) SMLMV vigentes al presentar.", donor=42, color=RED),
    h("V. PRUEBAS Y ANEXOS", donor=43),
    p("1. Pagaré No. 1912000833 e instrucciones íntegras.", donor=45),
    p("2. Liquidación, plan, solicitudes e identificaciones; comprobante/certificación de desembolso.", donor=45),
    p("3. CO 1000142, compromiso, NA 1000378 y soportes de los CB 100030121, 100002605 y 1200000010.", donor=45),
    p("4. Extracto, certificación contable y memoria reproducible actualizada.", donor=45),
    p("5. Trazabilidad de llenado, vencimiento, custodia, no circulación y original.", donor=45),
    p("6. Certificado vigente CREARCOOP, poder especial para ambos y personería.", donor=45),
    p("7. [OTROS ANEXOS REALMENTE APORTADOS; ELIMINAR SI NO EXISTEN].", donor=46, color=RED),
    h("VI. MEDIDA CAUTELAR Y ENVÍO PREVIO", donor=56),
    p("[USAR SOLO SI RUNT/TRÁNSITO CONFIRMA EL VEHÍCULO Y SE PRESENTA LA CAUTELAR REAL] La demanda se acompaña de solicitud cautelar previa; se aplica la excepción de remisión simultánea del artículo 6 de la Ley 2213. [SI NO HAY CAUTELA REAL, ELIMINAR Y CUMPLIR LA RUTA DE ENVÍO CORRESPONDIENTE].", donor=57, color=RED),
    h("VII. NOTIFICACIONES", donor=59),
    p("Demandante: CREARCOOP, [DIRECCIÓN Y CORREO DEL CERTIFICADO VIGENTE].", donor=61, color=RED),
    p("Apoderada: [NOMBRE, DIRECCIÓN Y CORREO RNA].", donor=64, color=RED),
    p("Demandados: [DIRECCIÓN ACTUAL PROBADA DE ÁNGELA] y [DIRECCIÓN ACTUAL PROBADA DE JOSÉ]. Canales electrónicos: [SOLO LOS PROBADOS Y JURAMENTO ART. 8 LEY 2213; SI SE DESCONOCEN, DECLARARLO].", donor=71, color=RED),
    p("Atentamente,", donor=89),
    p("[NOMBRE DE LA APODERADA]", donor=95, bold=True, color=RED),
    p("C.C. [●] — T.P. [●] — correo RNA [●]", donor=96, color=RED),
]


CAUTELAR = [
    p("MÓDULO CONDICIONAL — NO RADICAR SIN RUNT/TRÁNSITO ACTUAL, PROPIEDAD E INDIVIDUALIZACIÓN", donor=7, bold=True, color=RED, size=13),
    p("[CIUDAD], [FECHA REAL]", donor=0, color=RED),
    p("SEÑOR JUEZ DE [DESPACHO Y CIUDAD PROBADOS] (REPARTO)", donor=1, bold=True, color=RED),
    p("E. S. D.", donor=2),
    p("REFERENCIA: MEDIDA CAUTELAR PREVIA — EJECUTIVO DE MÍNIMA CUANTÍA.", donor=7),
    p("DEMANDANTE: COOPERATIVA DE AHORRO Y CRÉDITO CREAR — CREARCOOP, NIT 890.981.459-4.", donor=8),
    p("DEMANDADOS: ÁNGELA YISELA RESTREPO VARÓN, C.C. 1.033.789.770, y JOSÉ EFRAÍN HINCAPIÉ VILLA, C.C. 1.061.655.910.", donor=10),
    p("PAGARÉ No. 1912000833 — OBLIGACIÓN No. 10-1912000833.", donor=11),
    p("[APODERADA], obrando por CREARCOOP, solicita, sujeta al certificado actual que acredite la titularidad de JOSÉ EFRAÍN HINCAPIÉ VILLA, la siguiente medida respecto del vehículo de placa FLJ238:", donor=14, color=RED),
    h("I. MEDIDA SOLICITADA", donor=17),
    p("Embargo y posterior secuestro de vehículo automotor", donor=19, bold=True),
    p("PRIMERO. Decretar el embargo del vehículo de placa FLJ238, marca Nissan, [MODELO/AÑO, CLASE, LÍNEA, COLOR, MOTOR, CHASIS, VIN Y DEMÁS DATOS EXACTOS DEL CERTIFICADO], cuya propiedad actual en cabeza de JOSÉ EFRAÍN HINCAPIÉ VILLA, C.C. 1.061.655.910, consta en [CERTIFICADO RUNT/TRÁNSITO DE FECHA Y CÓDIGO]. No trasladar ‘1998’ como modelo: en la solicitud histórica está escrito en el campo Moneda.", donor=24, color=RED),
    p("SEGUNDO. Comunicar la medida a [ORGANISMO DE TRÁNSITO COMPETENTE] por su canal oficial y ordenar la inscripción, con identificación completa del proceso, partes, placa y límite.", donor=25, color=RED),
    p("TERCERO. Una vez inscrito el embargo, disponer el secuestro conforme al artículo 601 del CGP, individualizando lugar, autoridad comisionada, secuestre y condiciones de custodia según la información actual del automotor.", donor=26),
    p("CUARTO. Solicito que, como criterio voluntario de proporcionalidad, la medida se limite a [LÍMITE EN LETRAS] ($[LÍMITE]), calculado sobre lo efectivamente reclamado, intereses y costas prudenciales, sin perjuicio de la excepción prevista por el artículo 599 del CGP cuando se persigue un solo bien.", donor=27, color=RED),
    p("QUINTO. Tramitar en cuaderno separado y preservar reserva hasta la práctica.", donor=45),
    h("II. SOPORTE Y PROPORCIONALIDAD", donor=17),
    p("[SOLO DESPUÉS DE OBTENERLO] El certificado de [FECHA] acredita que José figura como propietario actual, que el vehículo está registrado en [ORGANISMO], que sus datos son [●] y que presenta [GRAVÁMENES/LIMITACIONES]. La solicitud de 2022 se aporta solo como antecedente y no sustituye la consulta actual.", donor=17, color=RED),
    p("El embargo solicitado es registral; el secuestro solo procederá después de inscrito aquel, conforme al artículo 601 del CGP. El valor comercial actual es $[AVALÚO/FUENTE], frente a una pretensión de $[●] y límite voluntario $[●]. [EXPLICAR PROPORCIONALIDAD, GRAVÁMENES Y SI ES ÚNICO BIEN].", donor=17, color=RED),
    p("No se solicita embargo de la supuesta vivienda de Ángela porque no existe matrícula ni titularidad actual acreditada; tampoco se piden cuentas, inmuebles u otros bienes indeterminados.", donor=17),
    h("III. FUNDAMENTOS", donor=19),
    p("Artículos 593, 599 y 601 del Código General del Proceso y normas de registro automotor aplicables.", donor=17),
    h("IV. ANEXOS ESPECÍFICOS", donor=19),
    p("1. Certificado actual RUNT/tránsito y consulta de propiedad/gravámenes.", donor=42),
    p("2. Identificación técnica y avalúo actual del vehículo.", donor=42),
    p("3. Liquidación actualizada y cálculo del límite.", donor=42),
    p("4. Poder y personería aportados con la demanda.", donor=42),
    p("Solo si se satisfacen estas condiciones y se presenta efectivamente la medida podrá invocarse la excepción del artículo 6 de la Ley 2213.", donor=39, color=RED),
    p("Atentamente,", donor=47),
    p("[NOMBRE DE LA APODERADA]", donor=52, bold=True, color=RED),
    p("C.C. [●] — T.P. [●] — correo RNA [●]", donor=53, color=RED),
]


SUBSANACION = [
    p("FORMATOS Y ORDEN DE SUBSANACIÓN — EXPEDIENTE 1912000833", donor=7, bold=True, size=16),
    p("Uso interno · completar con evidencia real · prohibido antedatar", bold=True, color=RED),
    h("1. ORDEN DE CIERRE"),
    p("[[TABLA_FALTANTES]]"),
    h("2. PODER ESPECIAL"),
    p("[REPRESENTANTE LEGAL VIGENTE, C.C. Y CALIDAD], en representación de CREARCOOP, confiere poder especial a [ABOGADA, C.C., T.P. Y CORREO RNA] para proceso ejecutivo contra ÁNGELA YISELA RESTREPO VARÓN, C.C. 1.033.789.770, y JOSÉ EFRAÍN HINCAPIÉ VILLA, C.C. 1.061.655.910, por el pagaré No. 1912000833 y obligación No. 10-1912000833, incluidas cautelas. Enumerar solo facultades autorizadas y conservar mensaje de datos/aceptación."),
    h("3. CERTIFICACIÓN CONTABLE"),
    p("[NOMBRE/CALIDAD] certifica, con archivo reproducible adjunto, todos los movimientos de la obligación desde 20/9/2022 hasta [FECHA/HORA]. Debe distinguir RC, CB, NA, CM, CO y cualquier nota; no llamar pago a aplicación interna."),
    p("Explicar: capital $6.000.000 − $2.117.348; CO 1000142; NA 1000378; CB 100030121, 100002605 y 1200000010; base implícita de interés $3.251.653 frente al programa $3.600.588, créditos $2.399.491, saldo $852.162 y comparación diagnóstica $348.935; mora $1.346.743 − $21.824 = $1.324.919 versus $1.421.444 y diferencia $96.525. Obtener detalle diario para establecer, sin presumir, si esta última corresponde total o parcialmente a junio–6 de julio de 2026; incluir pagos posteriores, bases, tasas, días y aplicaciones."),
    h("4. COMPROMISO, VENCIMIENTO Y PRESCRIPCIÓN"),
    p("Aportar CO 1000142, compromiso completo, comunicaciones, nuevo plan, incumplimiento y asientos. Determinar autoría, aceptación, alcance y oponibilidad frente a cada firmante; analizar la cláusula DÉCIMA, su calidad de signatarios solidarios en un mismo grado y los efectos interruptivos de los artículos 632 y 792 del Código de Comercio y 2540 del Código Civil."),
    p("El análisis debe identificar primera mora, modificaciones y fecha material de llenado; explicar plan final 20/9/2025 frente a vencimiento facial 6/7/2026; y controlar prescripción por cuotas y acción cambiaria sin presumir ni negar automáticamente efectos entre los firmantes."),
    h("5. TRAZABILIDAD DE LLENADO Y CUSTODIA"),
    p("Si se reconstruye ahora, denominarla reconstrucción ex post. Identificar quién, cuándo, dónde y con qué fuente completó cada campo; original y transferencias de custodia; no circulación; hash; y disponibilidad de exhibición. Resolver capital original, interés actualizado, agencia vacía y dos reglas de vencimiento."),
    p("[[TABLA_LLENADO]]"),
    h("6. PERSONERÍA, CONTRATO Y DESEMBOLSO"),
    p("Obtener certificado vigente CREARCOOP; poder para ambos; RNA; contrato, reglamento y anexos completos; control interno de pacto arbitral; y comprobante o certificación trazable de entrega efectiva de $5.478.000 netos."),
    h("7. DOMICILIO, NOTIFICACIÓN E INSOLVENCIA"),
    p("Actualizar direcciones y verificar mediante fuente actual el municipio y departamento correctos, sin completar los formularios por inferencia. Usar email solo con evidencia actual y juramento del artículo 8 de la Ley 2213. Consultar en fuentes oficiales insolvencia, negociación de deudas, liquidación patrimonial, fallecimiento/sucesión y procesos paralelos de ambos el día de presentar."),
    h("8. RUNT Y CAUTELA"),
    p("Obtener certificado actual de placa FLJ238: propietario con C.C. correcta 1.061.655.910, organismo, modelo/año, clase, línea, motor, chasis, VIN, gravámenes, limitaciones y estado. Obtener avalúo actual y ubicación lícita. Si José no es propietario o el bien no es individualizable, retirar la cautela. La declaración de vivienda de Ángela no autoriza embargo sin matrícula y titularidad."),
    h("9. ACTA DE LIBERACIÓN"),
    p("La revisora certificará que confrontó demanda, cautela y anexos; saldo del día; cero campos/alternativas/datos ajenos; título correcto; fuentes revalidadas; y estado APTO PARA REVISIÓN Y FIRMA HUMANA."),
    p("[[TABLA_CONTROL]]"),
]

FALTANTES_ROWS = [
    ("1", "CO, NA y CB ambiguos", "Naturaleza, aceptación, pagos, plazo, incumplimiento y título", "Cartera/Jurídica", "ROJO"),
    ("2", "Certificación + cálculo", "Capital, interés, mora, pagos y diferencias", "Contabilidad", "ROJO"),
    ("3", "Vencimiento/prescripción", "Plan 2025 versus facial 2026 y términos", "Jurídica", "ROJO"),
    ("4", "Original/llenado/custodia", "Campos, instrucciones, ubicación y no circulación", "Custodia", "ROJO"),
    ("5", "Certificado CREARCOOP", "Continuidad, representación y canales", "Secretaría", "ROJO"),
    ("6", "Poder especial para ambos", "Pagaré, obligación, apoderada y RNA", "Representante", "ROJO"),
    ("7", "Contrato y desembolso", "Reglas, control arbitral y entrega efectiva", "Archivo/Jurídica", "ROJO"),
    ("8", "Consultas oficiales", "Insolvencia, sucesión y procesos de ambos", "Jurídica", "ROJO"),
    ("9", "Notificación actual", "Domicilios y canales electrónicos probados", "Cartera", "ROJO"),
    ("10", "RUNT/avalúo FLJ238", "Propiedad, datos, gravámenes, valor y organismo", "Jurídica", "ROJO"),
    ("11", "Último crédito/pago", "Separar último crédito visible de último pago efectivo; actualizar saldo y límite", "Contabilidad", "ROJO"),
]

LLENADO_ROWS = [
    ("Agencia", "En blanco", "Agencia solicitud/radicación", "[●]", "[No llenado]", "[persona/fecha]"),
    ("Otorgamiento", "Ya decía 200922", "Desembolso/obligación antigua", "Liquidación 20/9/2022", "20-09-22", "Preexistente al nuevo escaneo"),
    ("Capital", "En blanco", "Adeudado según registros", "[saldo al llenar]", "$6.000.000", "[persona/fecha]"),
    ("Interés", "En blanco", "Adeudado según registros", "[memoria]", "$852.162", "[persona/fecha]"),
    ("Tasa", "En blanco", "Políticas CREARCOOP: tipo, periodicidad, liquidación, fija/variable, índice y margen; en defecto, máxima legal", "[soporte]", "39,29 %", "[persona/fecha]"),
    ("Vencimiento", "En blanco", "Inicio mora / día llenado", "[evento]", "6/7/2026", "[persona/fecha]"),
]

CONTROL_ROWS = [
    ("Partes/personería", "Identidad, calidades, certificado, poder para ambos, RNA y firma", "[ ]", "[folio]"),
    ("Jurisdicción", "Domicilio, contrato/anexos y ruta territorial definidos", "[ ]", "[folio]"),
    ("Título/exigibilidad", "Original, custodia, llenado, compromiso y vencimiento probados", "[ ]", "[folio]"),
    ("Liquidación", "Saldo actual y reproducible, sin rubros futuros/dobles", "[ ]", "[folio]"),
    ("Prescripción/insolvencia", "Términos y consultas oficiales de ambos cerrados", "[ ]", "[folio]"),
    ("Cautela/higiene", "RUNT, límite, anexos y cero campos o datos ajenos", "[ ]", "[folio]"),
]


# Los módulos rojos son instrucciones internas que deben desaparecer antes de
# liberar la demanda. Se compactan para que el borrador no genere una página
# residual de firmas; el texto judicial definitivo heredará el formato normal.
for _spec in DEMANDA:
    if _spec.get("color") == RED and "size" not in _spec:
        _spec["size"] = 10


FILES = [
    ("01_INFORME_MATRIZ_CONTROL_1912000833_V2.docx", DEMAND_TEMPLATE, INFORME, "Informe integral 1912000833 v2", {
        "[[TABLA_DATOS]]": {"headers": ("Elemento", "Dato", "Fuente", "Estado"), "rows": DATOS_ROWS, "widths": (1500, 3200, 2600, 1500)},
        "[[TABLA_CONTABLE]]": {"headers": ("Rubro", "Cargos/base", "Créditos", "Resta", "Saldo", "Concilia"), "rows": CONT_ROWS, "widths": (1200, 1800, 1700, 1500, 1500, 1400)},
        "[[TABLA_GATES]]": {"headers": ("Gate", "Control", "Estado", "Razón"), "rows": GATES_ROWS, "widths": (700, 2000, 1100, 5000)},
        "[[TABLA_ANEXOS]]": {"headers": ("No.", "Documento", "Archivo", "Págs.", "Estado"), "rows": ANEXOS_ROWS, "widths": (600, 2800, 2600, 700, 1800)},
    }),
    ("02_DEMANDA_EJECUTIVA_PRECIERRE_1912000833_V2_NO_RADICAR.docx", DEMAND_TEMPLATE, DEMANDA, "Demanda ejecutiva precierre 1912000833 v2", None),
    ("03_MEDIDA_CAUTELAR_PRECIERRE_1912000833_V2_NO_RADICAR.docx", CAUTION_TEMPLATE, CAUTELAR, "Medida cautelar precierre 1912000833 v2", None),
    ("04_FORMATOS_SUBSANACION_1912000833_V2.docx", DEMAND_TEMPLATE, SUBSANACION, "Formatos subsanación 1912000833 v2", {
        "[[TABLA_FALTANTES]]": {"headers": ("Orden", "Soporte", "Debe resolver", "Responsable", "Estado"), "rows": FALTANTES_ROWS, "widths": (650, 2300, 3300, 1500, 900)},
        "[[TABLA_LLENADO]]": {"headers": ("Campo", "Estado previo", "Instrucción", "Fuente", "Dato", "Persona/fecha"), "rows": LLENADO_ROWS, "widths": (1150, 1400, 1750, 1400, 1400, 1700)},
        "[[TABLA_CONTROL]]": {"headers": ("Control", "Criterio", "Estado", "Evidencia"), "rows": CONTROL_ROWS, "widths": (1700, 4600, 1000, 1600)},
    }),
]


def sanitize_metadata(path):
    with ZipFile(path, "r") as zin:
        members = {info.filename: (info, zin.read(info.filename)) for info in zin.infolist()}
    info, data = members["docProps/core.xml"]
    root = etree.fromstring(data)
    for node in root.xpath("//*[local-name()='creator' or local-name()='lastModifiedBy']"):
        node.text = "CREARCOOP — control jurídico"
    members["docProps/core.xml"] = (info, etree.tostring(root, xml_declaration=True, encoding="UTF-8", standalone=True))
    tmp = path.with_suffix(".meta.tmp.docx")
    with ZipFile(tmp, "w", compression=ZIP_DEFLATED) as zout:
        for _name, (member_info, member_data) in members.items():
            zout.writestr(member_info, member_data)
    tmp.replace(path)


def validate(path):
    with ZipFile(path, "r") as z:
        bad = z.testzip()
        if bad:
            raise RuntimeError(f"ZIP defectuoso: {path.name}: {bad}")
        root = etree.fromstring(z.read("word/document.xml"))
        text = "\n".join(root.xpath("//w:t/text()", namespaces=NS))
        for value in ("1912000833", "ÁNGELA YISELA RESTREPO VARÓN", "JOSÉ EFRAÍN HINCAPIÉ VILLA"):
            if value.lower() not in text.lower():
                raise RuntimeError(f"Falta dato {value}: {path.name}")
        for residual in ("NELSON DAVID", "CAMILO ANDREY", "1912000810", "MARÍA AMELIA", "FOPEP", "APETITOZOS"):
            if residual.lower() in text.lower():
                raise RuntimeError(f"Residuo {residual}: {path.name}")
        if "[[TABLA_" in text:
            raise RuntimeError(f"Marcador no reemplazado: {path.name}")
        return len(text), sha256(path.read_bytes()).hexdigest()


def main():
    OUT.mkdir(parents=True, exist_ok=True)
    manifest = []
    for name, template, specs, title, tables in FILES:
        path = OUT / name
        make(path, template, specs, title, tables)
        sanitize_metadata(path)
        chars, digest = validate(path)
        manifest.append((name, path.stat().st_size, chars, digest))
    (OUT / "MANIFIESTO_SHA256.txt").write_text(
        "\n".join(f"{digest}  {name}" for name, _size, _chars, digest in manifest) + "\n",
        encoding="utf-8",
    )
    for row in manifest:
        print("\t".join(map(str, row)))


if __name__ == "__main__":
    main()
