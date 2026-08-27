from hashlib import sha256
from importlib.util import module_from_spec, spec_from_file_location
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile

from lxml import etree


ROOT = Path("/code/DEMANDAS")
OUT = ROOT / "ENTREGABLES_1912000810_V2"
HELPER = ROOT / "_work_1911000986" / "generar_paquete_v2_1911000986.py"
DEMAND_TEMPLATE = ROOT / "MODELO.docx"
CAUTION_TEMPLATE = ROOT / "MODELO MEDIDAS.docx"

spec = spec_from_file_location("helper1911v2", HELPER)
helper = module_from_spec(spec)
spec.loader.exec_module(helper)

NS = helper.NS
qn = helper.qn
make = helper.make
p = helper.p
h = helper.h

RED = "C00000"
AMBER = "BF9000"
NAVY = "1F4E78"


INFORME = [
    p("INFORME INTEGRAL DE CONTROL — EXPEDIENTE 1912000810", donor=7, bold=True, size=16),
    p("Nelson David Parada Rincón y Camilo Andrey Parada Rincón · versión 2 · 18 de julio de 2026", bold=True, color=NAVY),
    p("DECISIÓN: ROJO — NO APTO PARA FIRMA NI RADICACIÓN", bold=True, color=RED, size=13),
    p("La demanda y la cautelar quedan estructuradas en Word para precierre. No deben liberarse hasta acreditar el saldo, la exigibilidad anticipada, el diligenciamiento del título, la personería, la jurisdicción y una cautela actual. Los marcadores rojos son controles deliberados: no pueden completarse por inferencia."),
    h("1. CONCLUSIÓN EJECUTIVA"),
    p("El pagaré No. 1912000810 identifica a NELSON DAVID PARADA RINCÓN, C.C. 1.033.755.601, como deudor, y a CAMILO ANDREY PARADA RINCÓN, C.C. 1.018.459.638, como codeudor. Ambos firmaron y estamparon huella en el pagaré y en la carta separada de instrucciones. La obligación se pactó solidaria e indivisiblemente."),
    p("El extracto al 6 de julio de 2026 permite conciliar mecánicamente solo el capital: $12.000.000 de cargo menos $8.881.008 de créditos o aplicaciones contables equivale a $3.118.992. Esa cifra tampoco puede aceptarse todavía como obligación definitiva, porque $6.465.396 del componente de capital proviene de dos movimientos CO cuya causa no está documentada. Si el compromiso de pago no fue extintivo, el cálculo diagnóstico sería $8.761.986; si tampoco lo fue la gestión de cobro, sería $9.584.388. Esos escenarios forenses no son pretensiones alternativas. No es responsable reclamar $12.000.000 ni escoger uno de los saldos sin sus soportes. El interés informado, $3.587.258, y la mora informada, $911.676, tampoco se reproducen. Una vez explicados los movimientos, la ruta conservadora será reclamar el capital insoluto certificado y la mora futura desde una exigibilidad probada; los accesorios históricos se excluirán mientras no haya memoria verificable."),
    p("El plan ordinario termina el 22 de agosto de 2026, pero el pagaré fue completado con vencimiento el 6 de julio de 2026. A la fecha de este informe el vencimiento ordinario aún no ha ocurrido. Para demandar ahora debe acreditarse el incumplimiento, el ejercicio de la aceleración y el diligenciamiento conforme a instrucciones; la sola coincidencia entre vencimiento facial y corte del extracto no resuelve esas preguntas."),
    p("La razón social impresa en el título es COOPERATIVA DE AHORRO Y CRÉDITO CREAR LTDA. Un certificado común de 5 de febrero de 2026, conservado fuera de este expediente individual, informa la razón COOPERATIVA DE AHORRO Y CRÉDITO CREAR, sigla CREARCOOP, NIT 890.981.459-4, y una reforma inscrita el 17 de noviembre de 2021. Ese antecedente no sustituye el certificado vigente que debe obtenerse y anexarse para probar continuidad nominal, representación y canales al presentar."),
    h("2. SELECCIÓN Y ANÁLISIS FORENSE DEL TÍTULO"),
    p("Título seleccionado: PAGARÉ 1912000810.pdf, cuatro páginas, SHA-256 d7d2ee3fc633481de3485bbb17fa9c63876a4572f1b4724246348d821f248522. Archivo descartado como título: 1912000810.pdf, SHA-256 d40ed82dc573fcbb6774fbafc6cc115a2b53f245d34c0d206fe6da8b61de4d3d. El segundo se conserva únicamente para auditoría de trazabilidad."),
    p("La versión sin prefijo ya contenía el número 1912000810 y las páginas firmadas, pero tenía en blanco fecha, capital, interés, tasa, vencimiento, cuotas, nombres e identificaciones de la página 1. En el archivo seleccionado, la página 1 es un nuevo escaneo diligenciado; las imágenes incrustadas de las páginas 2, 3 y 4 son idénticas byte por byte a las de la versión anterior. Esto es compatible con llenado posterior de un título firmado en blanco, pero no prueba la fecha del llenado ni identifica pericialmente el papel original."),
    p("Metadato: el archivo seleccionado fue procesado por iLovePDF el 16 de julio de 2026; el anterior fue creado el 30 de mayo de 2024. Los metadatos documentan archivos digitales, no la fecha en que fueron escritos los campos físicos. Deben preservarse el original, la custodia y los registros internos de llenado."),
    h("3. DATO–SOPORTE"),
    p("[[TABLA_DATOS]]"),
    h("4. RECONCILIACIÓN CONTABLE"),
    p("[[TABLA_CONTABLE]]"),
    p("El saldo informado de $7.617.926 resulta de sumar capital $3.118.992, interés $3.587.258 y mora $911.676. Esa suma aritmética sí es correcta, pero dos de sus tres componentes no son verificables con el detalle impreso. El plan registra $6.802.185 de interés programado total y el extracto muestra $3.086.131 de créditos a interés; su diferencia sería $3.716.054, no $3.587.258: falta explicar otro ajuste por $128.796 y, ante todo, separar interés causado de interés futuro. En mora, $1.488.841 de cargos menos $633.036 de abonos da $855.805, no $911.676: existe una diferencia inexplicada de $55.871."),
    p("Los doce recaudos identificados como RC suman $5.529.800: $2.415.612 a capital, $3.062.602 a interés y $51.586 a mora. No aparece ningún RC posterior al 18 de diciembre de 2023. Dos movimientos de gran magnitud se presentan como abonos contables, no como pagos de caja documentados: CO 1000434 de 31 de marzo de 2024, ‘GESTIÓN DE COBRO MARZO 2024’, por $870.900; y CO 1000183 de 31 de enero de 2026, ‘GENERACIÓN COMPROMISO DE PAGO’, por $6.169.946. Deben aportarse los documentos fuente, determinar si fueron extintivos y establecer si el compromiso fue aceptado por Nelson y por Camilo."),
    p("El patrón refuerza la necesidad del soporte: $9.584.388 coincide con el capital programado después de la cuota 14; el componente de capital de CO 1000434, $822.402, conduce exactamente a $8.761.986, saldo programado después de la cuota 18 y equivalente a cuatro componentes de capital del plan. Esto es compatible con un ajuste o reclasificación, no constituye por sí mismo prueba de pago. De igual modo, los intereses programados de las primeras catorce cuotas suman $3.061.496, muy cerca de los $3.062.602 abonados por RC, pero el saldo de interés posterior sigue requiriendo el ajuste fuente."),
    h("5. LLENADO, VENCIMIENTO Y EXIGIBILIDAD"),
    p("La instrucción incorporada exige que CAPITAL sea lo adeudado según libros, documentos, registros o sistemas; sin embargo, se insertó $12.000.000 pese a que el extracto reporta $3.118.992 de capital al corte. En INTERÉS se insertó exactamente $3.587.258, igual al saldo informado en el extracto. Esta asimetría será un argumento central de la defensa."),
    p("Hay dos reglas incompatibles para FECHA DE VENCIMIENTO: la instrucción incorporada ordena usar la fecha de inicio de mora, mientras la carta separada ordena usar el día de llenado. Debe establecerse qué ocurrió el 6 de julio de 2026 y por qué satisface la regla aplicable. El campo AGENCIA quedó vacío pese a una instrucción expresa; aunque no parece un requisito esencial autónomo del pagaré, es una irregularidad objetiva de diligenciamiento."),
    p("La mención ‘48 cuotas mensuales’ no fija monto ni vencimiento individual de cada cuota. El cronograma independiente sí muestra primera cuota el 22 de septiembre de 2022 y última el 22 de agosto de 2026. La demanda debe escoger una sola teoría: aceleración anticipada probada el 6 de julio de 2026, o espera al vencimiento ordinario y actualización posterior. No se pueden mezclar ambas."),
    h("6. SEMÁFORO DE GATES"),
    p("[[TABLA_GATES]]"),
    h("7. ANÁLISIS COMO CONTRAPARTE"),
    p("Las excepciones previsibles son llenado contrario a instrucciones, pago o modificación por el compromiso, cobro de lo no debido, falta de exigibilidad anticipada, indebida liquidación de intereses, ausencia de original/custodia, falta de personería, pacto arbitral, prescripción, notificación defectuosa y exceso cautelar. La defensa resaltará que el capital facial quedó por el monto original mientras el interés fue actualizado al centavo del extracto."),
    p("También pedirá exhibir CO 1000434 y CO 1000183. Si alguno contiene condonación, reestructuración, plazo, novación, reviviscencia o condiciones de incumplimiento, la omisión en los hechos puede afectar la buena fe procesal y la claridad del título complejo. La cláusula de no novación no permite adivinar el contenido del acuerdo concreto. Si el compromiso solo fue aceptado por Nelson, debe analizarse separadamente su alcance frente a Camilo, incluida cualquier consecuencia sobre plazo, exigibilidad o prescripción."),
    h("8. ANÁLISIS COMO JUEZ"),
    p("En el examen inicial de los artículos 422 y 430 del CGP, el juez puede considerar facialmente claro el pagaré, pero limitar o negar rubros cuya exigibilidad o liquidación no resulte clara del conjunto aportado. El artículo 622 del Código de Comercio permite llenar espacios conforme a instrucciones; una divergencia no causa automáticamente inexistencia, pero abre el debate sobre la obligación efectivamente incorporada. La acción por la parte insoluta puede estructurarse con los artículos 624 y 782 del Código de Comercio si el saldo y la exigibilidad quedan probados."),
    p("La formulación judicial más defensible, una vez cerrados los soportes, es: capital insoluto certificado; intereses remuneratorios históricos solo si existe memoria reproducible y no incluyen interés futuro; mora histórica solo si se concilia; mora futura únicamente sobre el capital insoluto y desde el día siguiente a una exigibilidad válida, sin exceder la tasa máxima legal de cada periodo. Omitir accesorios causados tampoco es una decisión automática: debe aprobarse expresamente después de valorar disposición del derecho, eventual fragmentación de pretensiones y riesgo de un litigio posterior. Hasta entonces, la salida conservadora es no radicar."),
    h("9. CAUTELA"),
    p("El expediente contiene un certificado histórico de 19 de agosto de 2021 sobre la matrícula de persona natural No. 03415338 y el establecimiento APETITOZOS, matrícula No. 03415339, asociado a Nelson. No prueba titularidad ni actividad en julio de 2026. La cautelar queda como módulo condicionado a certificado actual de Cámara de Comercio/RUES. No se encontró activo verificable actual de Camilo; no se deben pedir embargos genéricos sobre sus bancos, vehículos, inmuebles o negocio."),
    p("Si el certificado actual confirma que Nelson conserva la titularidad y la matrícula está activa, puede solicitarse embargo del establecimiento sujeto a registro, oficio a la Cámara y posterior secuestro cuando corresponda, con límite numérico aprobado según el artículo 599 del CGP. Si no lo confirma, el módulo debe retirarse y no puede usarse para justificar la excepción de envío previo."),
    h("10. DECISIÓN DE LIBERACIÓN"),
    p("Solo podrá cambiarse el estado a APTO PARA REVISIÓN Y FIRMA HUMANA cuando todos los gates rojos estén cerrados, se haya actualizado el saldo el día de presentación, se eliminen todos los campos rojos y corchetes, y una segunda persona confronte cada dato con el anexo exacto. No debe prometerse ausencia absoluta de riesgo; sí debe quedar documentado qué riesgo residual fue aceptado y por quién."),
    h("11. ANEXOS E ÍNDICE DE CIERRE"),
    p("[[TABLA_ANEXOS]]"),
]


DATOS_ROWS = [
    ("Deudor", "Nelson David Parada Rincón — C.C. 1.033.755.601", "Pagaré pp. 1–3; extracto; CC/solicitud", "Coincidente"),
    ("Codeudor", "Camilo Andrey Parada Rincón — C.C. 1.018.459.638", "Pagaré pp. 1–3; CC p. 13", "Coincidente"),
    ("Título", "Pagaré No. 1912000810", "PAGARÉ 1912000810.pdf", "Evidenciado"),
    ("Obligación", "10-1912000810", "Extracto y liquidación", "Coincidente"),
    ("Otorgamiento", "19 de agosto de 2022", "Pagaré p. 1; liquidación p. 1", "Coincidente"),
    ("Crédito", "$12.000.000; microcrédito empresarial", "Liquidación y extracto", "Coincidente"),
    ("Neto registrado", "$11.232.471 como ‘neto a desembolsar’ después de descuentos", "Liquidación p. 1", "Evidenciado / recibo pendiente"),
    ("Plan", "48 cuotas de $391.222; final 22/8/2026", "Liquidación pp. 1–2", "Evidenciado"),
    ("Tasa", "24 % N.A.M.V. / 26,82 % E.A.", "Liquidación p. 1; pagaré p. 1", "Coincidente"),
    ("Vencimiento facial", "6 de julio de 2026", "Pagaré p. 1", "Evidenciado / explicar"),
    ("Capital facial", "$12.000.000", "Pagaré p. 1", "Contradice saldo"),
    ("Capital a corte", "$3.118.992 al 6/7/2026", "Extracto p. 3", "Conciliado"),
    ("Interés informado", "$3.587.258", "Pagaré p. 1 y extracto p. 3", "No reproducible"),
    ("Mora informada", "$911.676", "Extracto p. 3", "No reproducible"),
    ("Dirección Nelson", "Carrera 19 D No. 61 A-24 Sur, Bogotá: contacto/negocio; residencia manuscrita distinta y parcial", "Liquidación/solicitud/CCB 2021", "Histórico / divergente"),
    ("Dirección Camilo", "TV 112 C No. 60D-27, Torre 3 Apto. 1603, Villa Gladys, Bogotá [verificar grafía]", "Solicitud histórica p. 11", "Histórico / manuscrito"),
    ("Activo Nelson", "APETITOZOS, matrícula 03415339", "CCB expedido 19/8/2021", "Histórico / actualizar"),
]

CONT_ROWS = [
    ("Capital", "$12.000.000", "$8.881.008 créditos/aplicaciones", "$3.118.992", "$3.118.992", "Mecánico; efecto CO pendiente"),
    ("Interés", "Base de cargos no visible", "$3.086.131", "No calculable", "$3.587.258", "No"),
    ("Mora", "$1.488.841", "$633.036", "$855.805", "$911.676", "No: +$55.871"),
    ("Total", "—", "—", "—", "$7.617.926", "Solo suma rubros"),
]

GATES_ROWS = [
    ("G0", "Norma vigente", "AMARILLO", "Revalidar fuentes oficiales, competencia, tasas y cuantía al presentar."),
    ("G1", "Identidad/legitimación", "AMARILLO", "Identidades coinciden; falta certificado vigente y continuidad nominal actual."),
    ("G2", "Personería", "ROJO", "Poder común no individualiza ambos demandados; definir apoderada/RNA y confirmar membrete/contactos del modelo."),
    ("G3", "Jurisdicción", "ROJO", "Contrato y anexos incompletos; descartar pacto arbitral aplicable."),
    ("G4", "Competencia", "AMARILLO", "Bogotá histórica y mínima cuantía; actualizar domicilios y cuantía."),
    ("G5", "Título/original", "ROJO", "Falta original, custodia, no circulación y trazabilidad del PDF compuesto."),
    ("G6", "Llenado", "ROJO", "Capital facial, instrucciones incompatibles y agencia vacía no explicados."),
    ("G7", "Exigibilidad", "ROJO", "Final ordinario 22/8/2026; falta evento y ejercicio de aceleración al 6/7/2026."),
    ("G8", "Prescripción", "AMARILLO", "Facialmente 6/7/2029; depende de validar vencimiento y art. 94 CGP."),
    ("G9", "Saldo/intereses", "ROJO", "Capital concilia; interés y mora no; corte no actualizado."),
    ("G10", "Compromiso/pagos", "ROJO", "Faltan CO 1000434 y CO 1000183 y ausencia de pagos posteriores."),
    ("G11", "Insolvencia/procesos", "ROJO", "No hay consultas oficiales recientes de ninguno de los obligados."),
    ("G12", "Notificación/cautela", "ROJO", "Canales y CCB son históricos; no existe activo actual verificado."),
]

ANEXOS_ROWS = [
    ("A1", "Pagaré + instrucciones", "PAGARÉ 1912000810.pdf", "4", "Disponible / original pendiente"),
    ("A2", "Liquidación, solicitudes, CC y soportes históricos", "CC 1033755601 PAGARE 1912000810.pdf", "13", "Disponible / no contiene recibo"),
    ("A3", "Extracto histórico al 6/7/2026", "EXTRACTO 1033755601.PDF", "3", "Disponible / no certifica saldo"),
    ("A4", "CO 1000434 gestión de cobro", "[NOMBRE EXACTO]", "[●]", "Pendiente"),
    ("A5", "CO 1000183 compromiso de pago", "[NOMBRE EXACTO]", "[●]", "Pendiente"),
    ("A6", "Certificación contable + memoria", "[NOMBRE EXACTO]", "[●]", "Pendiente"),
    ("A7", "Aceleración, llenado y custodia", "[NOMBRE EXACTO]", "[●]", "Pendiente"),
    ("A8", "Certificado vigente CREARCOOP", "[NOMBRE EXACTO]", "[●]", "Pendiente"),
    ("A9", "Poder especial y RNA", "[NOMBRE EXACTO]", "[●]", "Pendiente"),
    ("A10", "Contrato/anexos y control arbitral", "[NOMBRE EXACTO]", "[●]", "Pendiente"),
    ("A11", "Consultas insolvencia/procesos", "[NOMBRE EXACTO]", "[●]", "Pendiente"),
    ("A12", "Datos actuales de notificación", "[NOMBRE EXACTO]", "[●]", "Pendiente"),
    ("A13", "CCB/RUES actual de Nelson/APETITOZOS", "[NOMBRE EXACTO]", "[●]", "Pendiente"),
]


DEMANDA = [
    p("PRECIERRE — NO RADICAR NI FIRMAR HASTA REEMPLAZAR TODOS LOS CAMPOS ROJOS", donor=7, bold=True, color=RED, size=13),
    p("Bogotá D.C., [FECHA REAL DE PRESENTACIÓN]", donor=0, color=RED),
    p("SEÑOR JUEZ DE PEQUEÑAS CAUSAS Y COMPETENCIA MÚLTIPLE DE BOGOTÁ D.C. (REPARTO)", donor=1, bold=True),
    p("E. S. D.", donor=2),
    p("REFERENCIA: DEMANDA EJECUTIVA DE MÍNIMA CUANTÍA — PAGARÉ No. 1912000810 — OBLIGACIÓN No. 10-1912000810.", donor=7),
    p("DEMANDANTE: COOPERATIVA DE AHORRO Y CRÉDITO CREAR, sigla CREARCOOP, NIT 890.981.459-4.", donor=8),
    p("DEMANDADOS: NELSON DAVID PARADA RINCÓN, C.C. No. 1.033.755.601, y CAMILO ANDREY PARADA RINCÓN, C.C. No. 1.018.459.638.", donor=9),
    p("[NOMBRE DE LA APODERADA DEFINIDA], mayor de edad, identificada como aparece al pie de mi firma, abogada en ejercicio y apoderada judicial de CREARCOOP conforme al poder especial adjunto, presento demanda ejecutiva de mínima cuantía contra NELSON DAVID PARADA RINCÓN, deudor, y CAMILO ANDREY PARADA RINCÓN, codeudor solidario, para que se libre mandamiento de pago por el saldo insoluto respaldado por el pagaré No. 1912000810 y por los registros de pagos, créditos o aplicaciones contables que se aportan.", donor=15, color=RED),
    h("I. HECHOS", donor=16),
    p("PRIMERO. NELSON DAVID PARADA RINCÓN, C.C. No. 1.033.755.601, como deudor, y CAMILO ANDREY PARADA RINCÓN, C.C. No. 1.018.459.638, como codeudor, suscribieron el pagaré No. 1912000810, correspondiente a la obligación No. 10-1912000810, y se obligaron solidaria e indivisiblemente a la orden de la entidad que el título denomina COOPERATIVA DE AHORRO Y CRÉDITO CREAR LTDA., sigla CREARCOOP. [ANTES DE RADICAR, ADJUNTAR CERTIFICADO VIGENTE Y SUSTITUIR ESTE CONTROL POR EL HECHO EXACTO QUE ACREDITE CONTINUIDAD CON COOPERATIVA DE AHORRO Y CRÉDITO CREAR, CREARCOOP, NIT 890.981.459-4].", donor=17, color=RED),
    p("SEGUNDO. La liquidación del crédito registra aprobación por DOCE MILLONES DE PESOS M/CTE ($12.000.000), modalidad de microcrédito empresarial, fecha de desembolso 19 de agosto de 2022 y valor ‘neto a desembolsar’ de $11.232.471, después de $23.529 de interés anticipado, $180.000 de Fondo de Solidaridad, $540.000 de Ley Mipyme y $24.000 de estudio de crédito. [ADJUNTAR COMPROBANTE AUTÓNOMO DE ENTREGA O CERTIFICACIÓN TRAZABLE ANTES DE AFIRMAR EL DESEMBOLSO EFECTIVO].", donor=18, color=RED),
    p("TERCERO. El plan de pagos previó cuarenta y ocho (48) cuotas mensuales de $391.222, con primera fecha el 22 de septiembre de 2022 y vencimiento ordinario final el 22 de agosto de 2026, a una tasa de 24 % N.A.M.V., equivalente documentalmente a 26,82 % E.A.", donor=19),
    p("CUARTO. Los demandados suscribieron el pagaré con espacios destinados a ser completados y autorizaron su diligenciamiento conforme a las instrucciones incorporadas y a la carta separada que ambos firmaron.", donor=20),
    p("QUINTO. [INSERTAR EL CONTENIDO PROBADO DE CO 1000434 DE 31/3/2024 Y CO 1000183 DE 31/1/2026: documento fuente, causa, alcance, efecto contable y jurídico, cuotas o condiciones, incumplimiento y subsistencia del pagaré. NO CALIFICAR SIN APORTARLOS].", donor=21, color=RED),
    p("SEXTO. [DESCRIBIR UN ÚNICO EVENTO PROBADO DE VENCIMIENTO ANTICIPADO, LA CLÁUSULA APLICADA, LA FECHA Y FORMA EN QUE CREARCOOP EJERCIÓ LA ACELERACIÓN, Y CÓMO EL 6/7/2026 CUMPLE LA INSTRUCCIÓN DE INICIO DE MORA Y/O DÍA DE LLENADO. SI NO EXISTE SOPORTE, NO PRESENTAR ANTES DEL VENCIMIENTO ORDINARIO Y REHACER LA LIQUIDACIÓN].", donor=22, color=RED),
    p("SÉPTIMO. [USAR SOLO SI LOS SOPORTES CONFIRMAN QUE LOS DOS MOVIMIENTOS CO REDUJERON DEFINITIVAMENTE LA DEUDA] Al corte histórico del 6 de julio de 2026, los registros de CREARCOOP muestran $8.881.008 de créditos o aplicaciones contables a capital. Descontados del capital inicial de $12.000.000, arrojan $3.118.992 de capital insoluto. La cooperativa limita su acción a la parte no pagada y no reclama como capital el monto original consignado en el encabezamiento. [SI LOS MOVIMIENTOS NO FUERON EXTINTIVOS, SUSTITUIR TODO EL HECHO POR LA RECONCILIACIÓN PROBADA; NO ESCOGER CIFRA POR CONVENIENCIA].", donor=26, color=RED),
    p("OCTAVO. [CERTIFICAR EL DÍA DE PRESENTACIÓN] A [FECHA/HORA DE CORTE], el capital insoluto es $[CAPITAL ACTUAL CERTIFICADO]. No se recibieron pagos ni se aplicaron ajustes posteriores a [FECHA], salvo [DETALLE]. La memoria adjunta reproduce cada movimiento y explica los comprobantes CO 1000434 y CO 1000183.", donor=27, color=RED),
    p("NOVENO. [RUTA QUE REQUIERE APROBACIÓN EXPRESA DE CREARCOOP Y DEL EQUIPO JURÍDICO] CREARCOOP no incluye en esta demanda los $3.587.258 de interés ni los $911.676 de mora informados en el extracto histórico, porque no se reproducen con el detalle disponible. Antes de usar esta ruta debe documentarse la decisión sobre disposición del derecho y fragmentación de pretensiones. [ELIMINAR ESTE HECHO SI UNA MEMORIA REPRODUCIBLE PERMITE PRETENDERLOS; EN ESE CASO SUSTITUIRLO POR BASE, TASA, PERIODOS, PAGOS Y SUMA EXACTOS].", donor=28, color=RED),
    p("DÉCIMO. [SOLO CON CERTIFICACIÓN] CREARCOOP conserva la tenencia legítima y custodia del original físico del pagaré; no lo ha endosado ni circulado y lo exhibirá al despacho cuando sea requerido.", donor=29, color=RED),
    h("II. PRETENSIONES", donor=30),
    p("Solicito librar mandamiento de pago a favor de CREARCOOP y contra NELSON DAVID PARADA RINCÓN y CAMILO ANDREY PARADA RINCÓN, solidariamente, por:", donor=31),
    p("PRIMERA. [CAPITAL ACTUAL EN LETRAS] PESOS M/CTE ($[CAPITAL ACTUAL CERTIFICADO]), por capital insoluto de la obligación No. 10-1912000810. El corte histórico arroja mecánicamente $3.118.992 si los dos movimientos CO fueron extintivos; la cifra definitiva debe establecerse con sus soportes y actualizarse el día de presentación.", donor=32, color=RED),
    p("SEGUNDA. Los intereses moratorios que se causen exclusivamente sobre el capital insoluto certificado, desde [DÍA SIGUIENTE A LA EXIGIBILIDAD VALIDADA] hasta el pago, a la tasa moratoria legalmente aplicable en cada periodo, sin exceder la máxima autorizada, sin capitalización ni superposición con intereses remuneratorios.", donor=33, color=RED),
    p("[MÓDULO OPCIONAL: SOLO SI EXISTE MEMORIA REPRODUCIBLE] TERCERA. [SUMA EN LETRAS] PESOS ($[●]) por intereses [REMUNERATORIOS/MORATORIOS] efectivamente causados entre [FECHAS], sobre [BASE], a [TASA], menos pagos de [DETALLE]. SI SE USA, COSTAS PASA A CUARTA; SI NO, ELIMINAR COMPLETAMENTE ESTE MÓDULO.", donor=34, color=RED),
    p("TERCERA [O CUARTA SI SE ACTIVA EL MÓDULO ANTERIOR]. Por las costas y agencias en derecho que se causen.", donor=35, color=RED),
    h("III. FUNDAMENTOS DE DERECHO"),
    p("Fundamento la demanda en los artículos 17, 25, 26, 28, 82, 84, 422, 430 y 431 del Código General del Proceso; 619, 621, 622, 624, 709, 782, 789 y 884 del Código de Comercio; el artículo 69 de la Ley 45 de 1990; la Ley 2213 de 2022 y las normas concordantes.", donor=38),
    h("IV. COMPETENCIA, TRÁMITE Y CUANTÍA", donor=40),
    p("Es competente el Juez de Pequeñas Causas y Competencia Múltiple de Bogotá D.C. por [DOMICILIO ACTUAL PROBADO DE AL MENOS UN DEMANDADO O REGLA TERRITORIAL APLICABLE], la naturaleza ejecutiva y la mínima cuantía, conforme a los artículos 17, 25, 26 y 28 del CGP. Los soportes históricos ubican a ambos en Bogotá, pero no prueban su domicilio actual. [CONFIRMAR Y SUSTITUIR ESTE CONTROL POR EL HECHO TERRITORIAL EXACTO].", donor=41, color=RED),
    p("La cuantía se estima en [TOTAL ACTUAL EN LETRAS] PESOS M/CTE ($[TOTAL ACTUAL]), sin incluir intereses futuros ni costas, suma inferior a cuarenta (40) SMLMV vigentes al presentar.", donor=42, color=RED),
    h("V. PRUEBAS Y ANEXOS", donor=43),
    p("Solicito tener como pruebas los documentos efectivamente aportados y relacionados en el índice definitivo:", donor=44),
    p("1. Pagaré No. 1912000810 y sus dos cuerpos de instrucciones, íntegros.", donor=45),
    p("2. Liquidación del crédito, plan de pagos, solicitudes e identificaciones; y comprobante o certificación trazable del desembolso efectivo.", donor=45),
    p("3. Documentos fuente CO 1000434 y CO 1000183 y prueba de su cumplimiento o incumplimiento.", donor=45),
    p("4. Extracto histórico, certificación contable y memoria de liquidación actualizada.", donor=45),
    p("5. Soporte de aceleración y certificación de diligenciamiento, custodia, no circulación y disponibilidad del original.", donor=45),
    p("6. Certificado vigente de CREARCOOP, poder especial para ambos demandados y soportes de personería.", donor=45),
    p("7. [OTROS ANEXOS REALMENTE APORTADOS; ELIMINAR SI NO EXISTEN].", donor=46, color=RED),
    h("VI. MEDIDA CAUTELAR Y ENVÍO PREVIO", donor=56),
    p("[USAR SOLO SI SE APORTA CCB/RUES ACTUAL Y SE RADICA LA CAUTELAR REAL DEL ESCRITO SEPARADO] La demanda se presenta con solicitud de medida cautelar previa. En consecuencia, se aplica la excepción de remisión simultánea prevista en el artículo 6 de la Ley 2213 de 2022, sin perjuicio de la notificación personal del mandamiento. [SI NO HAY CAUTELA REAL, ELIMINAR Y CUMPLIR LA RUTA DE ENVÍO QUE CORRESPONDA].", donor=57, color=RED),
    h("VII. NOTIFICACIONES", donor=59),
    p("Demandante: CREARCOOP, Calle 113 No. 64D-119, Medellín, y correo gerencia@crearcoop.com, [USAR ÚNICAMENTE SI EL CERTIFICADO VIGENTE CONFIRMA AMBOS DATOS].", donor=61, color=RED),
    p("Apoderada judicial: [NOMBRE, DIRECCIÓN Y CORREO COINCIDENTE CON EL RNA].", donor=64, color=RED),
    p("Nelson David Parada Rincón: Carrera 19 D No. 61 A-24 Sur, Bogotá D.C. [DATO HISTÓRICO DE CONTACTO/NEGOCIO; LA SOLICITUD TRAE UNA RESIDENCIA MANUSCRITA DISTINTA Y PARCIALMENTE ILEGIBLE. OBTENER Y USAR CANAL ACTUAL].", donor=71, color=RED),
    p("Camilo Andrey Parada Rincón: TV 112 C No. 60D-27, Torre 3 Apto. 1603, barrio Villa Gladys, Bogotá D.C. [GRAFÍA MANUSCRITA E HISTÓRICA; VERIFICAR ANTES DE USAR].", donor=72, color=RED),
    p("Canales electrónicos: [INFORMAR SOLO LOS QUE TENGAN EVIDENCIA ACTUAL DE TITULARIDAD/USO Y EXPONER BAJO JURAMENTO CÓMO SE OBTUVIERON, SEGÚN ART. 8 LEY 2213. SI SE DESCONOCEN, DECIRLO EXPRESAMENTE].", donor=80, color=RED),
    p("Atentamente,", donor=89),
    p("[NOMBRE DE LA APODERADA]", donor=95, bold=True, color=RED),
    p("C.C. No. [●] — T.P. No. [●] del C. S. de la J. — correo RNA: [●]", donor=96, color=RED),
]


CAUTELAR = [
    p("MÓDULO CONDICIONAL — NO RADICAR SIN CCB/RUES ACTUAL, TITULARIDAD Y LÍMITE NUMÉRICO", donor=7, bold=True, color=RED, size=13),
    p("Bogotá D.C., [FECHA REAL DE PRESENTACIÓN]", donor=0, color=RED),
    p("SEÑOR JUEZ DE PEQUEÑAS CAUSAS Y COMPETENCIA MÚLTIPLE DE BOGOTÁ D.C. (REPARTO)", donor=1, bold=True),
    p("E. S. D.", donor=2),
    p("REFERENCIA: SOLICITUD DE MEDIDA CAUTELAR PREVIA — PROCESO EJECUTIVO DE MÍNIMA CUANTÍA.", donor=7),
    p("DEMANDANTE: COOPERATIVA DE AHORRO Y CRÉDITO CREAR, sigla CREARCOOP, NIT 890.981.459-4.", donor=8),
    p("DEMANDADOS: NELSON DAVID PARADA RINCÓN, C.C. 1.033.755.601, y CAMILO ANDREY PARADA RINCÓN, C.C. 1.018.459.638.", donor=10),
    p("PAGARÉ: No. 1912000810 — OBLIGACIÓN: No. 10-1912000810.", donor=11),
    p("[NOMBRE DE LA APODERADA], obrando como apoderada judicial de CREARCOOP, solicito decretar la siguiente medida previa únicamente contra el bien de NELSON DAVID PARADA RINCÓN, siempre que su titularidad actual quede acreditada con el certificado que se anexará:", donor=14, color=RED),
    h("I. MEDIDA SOLICITADA", donor=17),
    p("Embargo de establecimiento de comercio sujeto a registro", donor=19, bold=True),
    p("PRIMERO. Decretar el embargo del establecimiento de comercio APETITOZOS, matrícula mercantil No. 03415339, cuya titularidad actual en cabeza de NELSON DAVID PARADA RINCÓN, C.C. No. 1.033.755.601, consta en el certificado expedido por la Cámara de Comercio de Bogotá el [FECHA RECIENTE], con código de verificación [●].", donor=24, color=RED),
    p("SEGUNDO. Ordenar la inscripción de la medida en el registro mercantil y librar oficio a la CÁMARA DE COMERCIO DE BOGOTÁ por el canal oficial vigente, con identificación completa del proceso, las partes, la matrícula y el límite de la medida.", donor=25),
    p("TERCERO. Una vez inscrito el embargo, disponer el secuestro conforme al artículo 601 del Código General del Proceso y aplicar, para la administración del establecimiento, las reglas del numeral 8 del artículo 595, con las determinaciones necesarias para preservar su valor y continuidad sin apartarse de las órdenes del despacho.", donor=26),
    p("CUARTO. Limitar la medida a [LÍMITE CAUTELAR EN LETRAS] PESOS M/CTE ($[LÍMITE NUMÉRICO]), aprobado conforme al artículo 599 del CGP, incluidas sus excepciones cuando resulte aplicable la relativa a un solo bien, y calculado sobre el crédito efectivamente reclamado, intereses y costas prudencialmente estimados.", donor=27, color=RED),
    p("QUINTO. Tramitar la solicitud en cuaderno separado y preservar la reserva necesaria hasta su práctica.", donor=45),
    h("II. SOPORTE FÁCTICO", donor=17),
    p("[SOLO DESPUÉS DE OBTENERLO] El certificado expedido el [FECHA] acredita que NELSON DAVID PARADA RINCÓN figura como titular actual de APETITOZOS, matrícula No. 03415339, con estado [ACTIVO/VIGENTE] y dirección [●]. El certificado histórico de 19 de agosto de 2021 se aporta solo como antecedente y no sustituye el certificado actual.", donor=17, color=RED),
    p("La medida se individualiza sobre un bien sujeto a registro, es idónea para asegurar el resultado del proceso y se limita numéricamente para evitar afectación excesiva. No se solicitan en este escrito embargos genéricos sobre cuentas, inmuebles, vehículos o negocios no verificados.", donor=17),
    p("No se formula medida contra bienes de CAMILO ANDREY PARADA RINCÓN porque el expediente revisado no contiene un activo actual individualizado. Esta precisión no modifica su condición de obligado solidario ni impide solicitar posteriormente una medida fundada en información obtenida lícitamente.", donor=17),
    h("III. FUNDAMENTOS", donor=19),
    p("La solicitud se funda en los artículos 593, 595 numeral 8, 599 y 601 del Código General del Proceso y en las normas del registro mercantil aplicables al establecimiento de comercio.", donor=17),
    h("IV. ANEXOS ESPECÍFICOS", donor=19),
    p("1. Certificado actual y verificable de la Cámara de Comercio/RUES sobre titularidad, matrícula y estado del establecimiento.", donor=42),
    p("2. Liquidación actualizada y memoria del límite cautelar.", donor=42),
    p("3. Poder y documentos de personería aportados con la demanda.", donor=42),
    p("4. [OTRO SOPORTE REALMENTE APORTADO; ELIMINAR SI NO EXISTE].", donor=42, color=RED),
    p("Solo si se satisfacen esas condiciones y esta solicitud se presenta efectivamente, podrá invocarse la excepción de envío simultáneo del artículo 6 de la Ley 2213 de 2022.", donor=39, color=RED),
    p("Atentamente,", donor=47),
    p("[NOMBRE DE LA APODERADA]", donor=52, bold=True, color=RED),
    p("C.C. No. [●] — T.P. No. [●] del C. S. de la J. — correo RNA: [●]", donor=53, color=RED),
]


SUBSANACION = [
    p("FORMATOS Y ORDEN DE SUBSANACIÓN — EXPEDIENTE 1912000810", donor=7, bold=True, size=16),
    p("Uso interno · completar solo con hechos y soportes reales · prohibido antedatar", bold=True, color=RED),
    h("1. ORDEN DE CIERRE"),
    p("[[TABLA_FALTANTES]]"),
    h("2. PODER ESPECIAL DIRECTO — RUTA PREFERENTE"),
    p("[REPRESENTANTE LEGAL VIGENTE, C.C. Y CALIDAD SEGÚN CERTIFICADO ACTUAL], actuando en representación de la COOPERATIVA DE AHORRO Y CRÉDITO CREAR — CREARCOOP, NIT 890.981.459-4, confiere poder especial a [ABOGADA, C.C., T.P. Y CORREO RNA] para promover y llevar hasta su terminación proceso ejecutivo de mínima cuantía contra NELSON DAVID PARADA RINCÓN, C.C. 1.033.755.601, y CAMILO ANDREY PARADA RINCÓN, C.C. 1.018.459.638, con fundamento en el pagaré No. 1912000810 y la obligación No. 10-1912000810, incluidas medidas cautelares."),
    p("Enumerar solo facultades especiales realmente autorizadas. Conservar mensaje de datos original, encabezados, adjuntos, fecha/hora y aceptación. El correo de la abogada debe coincidir con el Registro Nacional de Abogados. Si se usa una sociedad de abogados, verificar y documentar la ruta del artículo 75 del CGP; no mezclar rutas."),
    h("3. CERTIFICACIÓN CONTABLE Y ARCHIVO REPRODUCIBLE"),
    p("Yo, [NOMBRE, CALIDAD E IDENTIFICACIÓN], con acceso autorizado a [SISTEMA/FUENTES], certifico que revisé la obligación No. 10-1912000810 y que el archivo adjunto reproduce todos los movimientos desde el 19 de agosto de 2022 hasta [FECHA/HORA DE CORTE]. El saldo a ese corte es: capital $[●], remuneratorios causados $[●], mora $[●], otros $[●], total $[●]. Desde ese corte [NO HUBO/HUBO] pagos o ajustes: [DETALLE]."),
    p("La certificación debe distinguir pagos RC, notas NC, movimientos CO y cualquier otro tipo de comprobante, sin llamar pago a una aplicación interna. Debe explicar documentalmente: (a) $12.000.000 − $8.881.008 = $3.118.992 al 6/7/2026; (b) por qué $6.802.185 de interés programado menos $3.086.131 de créditos visibles arroja $3.716.054, cifra superior en $128.796 al saldo informado de $3.587.258, qué parte estaba efectivamente causada y cuál era futura, condonada o reliquidada; (c) la diferencia de $55.871 en mora; (d) CO 1000434 por $870.900; (e) CO 1000183 por $6.169.946; (f) la inexistencia o detalle de pagos posteriores al 18/12/2023; y (g) bases, fechas, tasas, días, fórmula y aplicación de cada movimiento."),
    h("4. CONSTANCIA DE ACELERACIÓN, DILIGENCIAMIENTO Y CUSTODIA"),
    p("Si la constancia se elabora ahora, titularla ‘reconstrucción ex post’ e identificar persona, conocimiento y registros consultados. No afirmar hechos no documentados. Debe responder quién, cuándo, dónde y con qué fuente completó cada campo; cuál fue el evento concreto de vencimiento anticipado; cómo y cuándo CREARCOOP ejerció la cláusula; por qué se usó 6/7/2026; y cómo se resolvió la contradicción entre inicio de mora y día de llenado."),
    p("[[TABLA_LLENADO]]"),
    p("Agregar ubicación física del original, responsables y transferencias de custodia, controles de acceso, hash del escaneo, no endoso/no circulación y disponibilidad para exhibición. Explicar por qué CAPITAL quedó en $12.000.000 mientras los registros al corte muestran $3.118.992 y por qué AGENCIA quedó vacía."),
    h("5. DOCUMENTOS CO 1000434 Y CO 1000183"),
    p("Aportar documentos completos, anexos, comunicaciones, autorizaciones y asientos antes/después. Jurídica debe definir si cada movimiento fue pago, condonación, acuerdo, reestructuración, traslado, castigo, novación o ajuste; qué plazo creó; si hubo condición de reviviscencia; cómo se incumplió; por qué el pagaré conservó exigibilidad; y quién lo aceptó. Analizar por separado sus efectos frente a Nelson y Camilo. Contabilidad certifica registros; jurídica califica efectos."),
    h("6. CERTIFICADO DE CREARCOOP, CONTRATO Y JURISDICCIÓN"),
    p("Obtener certificado vigente que pruebe razón social actual, continuidad de la denominación histórica, representante, domicilio, correos y facultades. Revisar contrato, reglamento, anexos, solicitudes, pagaré, instrucciones, compromiso y mensajes para identificar o descartar pacto arbitral aplicable al ejecutivo. Dejar inventario fechado de lo revisado."),
    h("7. NOTIFICACIONES Y CONSULTAS"),
    p("Actualizar por fuentes lícitas la dirección física de ambos obligados. Un correo solo se usa con evidencia de titularidad/uso y con la explicación juramentada del artículo 8 de la Ley 2213; las grafías manuscritas históricas no bastan. En el día de radicación documentar consultas de insolvencia, negociación de deudas, liquidación patrimonial, fallecimiento/sucesión y procesos paralelos de ambos demandados."),
    h("8. CAUTELA"),
    p("Solicitar certificado reciente de matrícula mercantil de Nelson y APETITOZOS. Confirmar titular, estado, dirección, gravámenes y canal oficial de la Cámara. Calcular límite con saldo del día. Si la matrícula no está activa o Nelson no es titular, retirar íntegramente la cautelar y no usar su excepción de envío. No convertir la referencia manuscrita de la solicitud de Camilo a un negocio en una cautela sin registro actual que identifique inequívocamente establecimiento, matrícula y titular."),
    h("9. ACTA DE LIBERACIÓN"),
    p("La persona que libera certificará: ‘Confronté demanda, cautelar y cada anexo; el saldo corresponde al día de presentación; no quedan campos rojos, corchetes, alternativas ni datos de otro expediente; el título descartado no fue anexado como título; las fuentes jurídicas decisivas fueron revalidadas; y el paquete queda APTO PARA REVISIÓN Y FIRMA HUMANA’."),
    p("[[TABLA_CONTROL]]"),
]

FALTANTES_ROWS = [
    ("1", "CO 1000434 y CO 1000183 completos", "Naturaleza, efecto, plazo, incumplimiento y subsistencia del título", "Cartera/Jurídica", "ROJO"),
    ("2", "Certificación contable + cálculo", "Capital actual, interés, mora, pagos y diferencias", "Contabilidad", "ROJO"),
    ("3", "Aceleración y llenado", "Evento, ejercicio, fecha 6/7/2026 e instrucciones", "Cartera/Jurídica", "ROJO"),
    ("4", "Original y custodia", "Ubicación, no circulación, trazabilidad y exhibición", "Custodia", "ROJO"),
    ("5", "Certificado CREARCOOP vigente", "Continuidad, representación, domicilio y correo", "Secretaría", "ROJO"),
    ("6", "Poder especial para ambos", "Pagaré, obligación, apoderada, RNA y facultades", "Representante", "ROJO"),
    ("7", "Contrato, anexos y desembolso", "Pacto arbitral, reglas adicionales y entrega efectiva del crédito", "Archivo/Jurídica", "ROJO"),
    ("8", "Consultas oficiales", "Insolvencia, sucesión y procesos de ambos", "Jurídica", "ROJO"),
    ("9", "Notificación actual", "Direcciones; emails solo con prueba y juramento", "Cartera", "ROJO"),
    ("10", "CCB/RUES actual", "Titularidad y estado de APETITOZOS", "Jurídica", "ROJO"),
    ("11", "Último pago", "Saldo, cuantía, pretensiones y límite el día de presentar", "Contabilidad", "ROJO"),
]

LLENADO_ROWS = [
    ("Agencia", "En blanco", "Agencia de solicitud/radicación", "[●]", "[No llenado]", "[persona/fecha]"),
    ("Otorgamiento", "En blanco en PDF previo", "Desembolso/obligación más antigua", "19/8/2022", "19-08-22", "[persona/fecha]"),
    ("Capital", "En blanco en PDF previo", "Adeudado según registros", "[saldo al llenar]", "$12.000.000", "[persona/fecha]"),
    ("Interés", "En blanco en PDF previo", "Adeudado según registros", "[memoria]", "$3.587.258", "[persona/fecha]"),
    ("Tasa", "En blanco en PDF previo", "Política/contrato/límite", "[soporte]", "26,82 %", "[persona/fecha]"),
    ("Vencimiento", "En blanco en PDF previo", "Inicio mora / día de llenado", "[evento]", "6/7/2026", "[persona/fecha]"),
]

CONTROL_ROWS = [
    ("Partes/personería", "Identidad, calidades, certificado, poder para ambos, RNA y firma", "[ ]", "[folio]"),
    ("Jurisdicción", "Domicilio, competencia, contrato/anexos y pacto arbitral revisados", "[ ]", "[folio]"),
    ("Título/exigibilidad", "Original, custodia, llenado, CO, evento y fecha únicos probados", "[ ]", "[folio]"),
    ("Liquidación", "Saldo actual, pagos y ajustes reproducibles; sin rubros futuros/dobles", "[ ]", "[folio]"),
    ("Insolvencia/notificación", "Consultas de ambos, canales actuales y evidencia Ley 2213", "[ ]", "[folio]"),
    ("Cautela/higiene", "CCB, titularidad, límite, anexos y cero campos o datos ajenos", "[ ]", "[folio]"),
]


FILES = [
    (
        "01_INFORME_MATRIZ_CONTROL_1912000810_V2.docx",
        DEMAND_TEMPLATE,
        INFORME,
        "Informe integral de control 1912000810 v2",
        {
            "[[TABLA_DATOS]]": {"headers": ("Elemento", "Dato", "Fuente", "Estado"), "rows": DATOS_ROWS, "widths": (1500, 3200, 2600, 1500)},
            "[[TABLA_CONTABLE]]": {"headers": ("Rubro", "Cargos/base", "Abonos", "Resta visible", "Saldo informado", "Concilia"), "rows": CONT_ROWS, "widths": (1200, 1700, 1500, 1700, 1600, 1200)},
            "[[TABLA_GATES]]": {"headers": ("Gate", "Control", "Estado", "Razón"), "rows": GATES_ROWS, "widths": (700, 2000, 1100, 5000)},
            "[[TABLA_ANEXOS]]": {"headers": ("No.", "Documento", "Archivo", "Págs.", "Estado"), "rows": ANEXOS_ROWS, "widths": (600, 2800, 2600, 700, 1800)},
        },
    ),
    ("02_DEMANDA_EJECUTIVA_PRECIERRE_1912000810_V2_NO_RADICAR.docx", DEMAND_TEMPLATE, DEMANDA, "Demanda ejecutiva precierre 1912000810 v2", None),
    ("03_MEDIDA_CAUTELAR_PRECIERRE_1912000810_V2_NO_RADICAR.docx", CAUTION_TEMPLATE, CAUTELAR, "Medida cautelar precierre 1912000810 v2", None),
    (
        "04_FORMATOS_SUBSANACION_1912000810_V2.docx",
        DEMAND_TEMPLATE,
        SUBSANACION,
        "Formatos de subsanación 1912000810 v2",
        {
            "[[TABLA_FALTANTES]]": {"headers": ("Orden", "Soporte", "Debe resolver", "Responsable", "Estado"), "rows": FALTANTES_ROWS, "widths": (650, 2300, 3300, 1500, 900)},
            "[[TABLA_LLENADO]]": {"headers": ("Campo", "Estado previo", "Instrucción", "Fuente", "Dato insertado", "Persona/fecha"), "rows": LLENADO_ROWS, "widths": (1150, 1400, 1750, 1400, 1400, 1700)},
            "[[TABLA_CONTROL]]": {"headers": ("Control", "Criterio", "Estado", "Evidencia"), "rows": CONTROL_ROWS, "widths": (1600, 4700, 1000, 1600)},
        },
    ),
]


def sanitize_metadata(path):
    with ZipFile(path, "r") as zin:
        members = {info.filename: (info, zin.read(info.filename)) for info in zin.infolist()}
    info, data = members["docProps/core.xml"]
    root = etree.fromstring(data)
    for node in root.xpath("//*[local-name()='creator' or local-name()='lastModifiedBy']"):
        node.text = "CREARCOOP — control jurídico"
    members["docProps/core.xml"] = (
        info,
        etree.tostring(root, xml_declaration=True, encoding="UTF-8", standalone=True),
    )
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
        required = ("1912000810", "NELSON DAVID PARADA RINCÓN", "CAMILO ANDREY PARADA RINCÓN")
        for value in required:
            if value.lower() not in text.lower():
                raise RuntimeError(f"Falta dato obligatorio {value}: {path.name}")
        for residual in ("MARÍA AMELIA", "1911000986", "41.526.686", "FOPEP"):
            if residual.lower() in text.lower():
                raise RuntimeError(f"Dato residual {residual}: {path.name}")
        if "[[TABLA_" in text:
            raise RuntimeError(f"Marcador de tabla no reemplazado: {path.name}")
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
    manifest_path = OUT / "MANIFIESTO_SHA256.txt"
    manifest_path.write_text(
        "\n".join(f"{digest}  {name}" for name, _size, _chars, digest in manifest) + "\n",
        encoding="utf-8",
    )
    for row in manifest:
        print("\t".join(map(str, row)))


if __name__ == "__main__":
    main()
