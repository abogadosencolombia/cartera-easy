from hashlib import sha256
from importlib.util import module_from_spec, spec_from_file_location
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile

from lxml import etree


ROOT = Path("/code/DEMANDAS")
OUT = ROOT / "ENTREGABLES_1912000835_V2"
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
    p("INFORME INTEGRAL DE CONTROL — EXPEDIENTE 1912000835", donor=7, bold=True, size=16),
    p("José Efraín Hincapié Villa y Ángela Yisela Restrepo Varón · versión 2 · 18 de julio de 2026", bold=True, color=NAVY),
    p("DECISIÓN: ROJO — NO APTO PARA FIRMA NI RADICACIÓN", bold=True, color=RED, size=13),
    p("Los Word quedan estructurados para precierre. No pueden presentarse hasta cerrar saldo, aceleración, llenado, custodia, personería, competencia, notificaciones, consultas y cautela. Ningún texto rojo puede sobrevivir a la versión judicial."),
    h("1. CONCLUSIÓN EJECUTIVA"),
    p("El pagaré No. 1912000835 identifica a JOSÉ EFRAÍN HINCAPIÉ VILLA, C.C. 1.061.655.910, como deudor, y a ÁNGELA YISELA RESTREPO VARÓN, C.C. 1.033.789.770, como codeudora. Ambos firmaron y estamparon huella en el pagaré y en la carta separada; el clausulado establece obligación solidaria e indivisible."),
    p("El capital a corte solo concilia mecánicamente si el movimiento CO 1000434 produjo extinción: $10.000.000 menos $2.203.337 equivale a $7.796.663. El CO de 31 de marzo de 2024 aplica $640.479 a capital y $41.389 a mora bajo la descripción ‘GESTIÓN DE COBRO MARZO 2024’, no como recibo. El mismo consecutivo y descripción aparecen ese día, por monto distinto, en otra obligación auditada; ello es compatible con un asiento por lote, no prueba pago ni acuerdo. Sin el efecto del CO, el capital diagnóstico sería $8.437.142. Debe aportarse el documento fuente; ninguna de las dos cifras es todavía pretensión definitiva."),
    p("El extracto informa interés $3.260.240 y mora $1.995.266, pero no permite reproducirlos. El interés programado total es $8.302.208 y los créditos del extracto suman $3.560.791; la resta sería $4.741.417, esto es $1.481.177 más que lo informado. Aun limitando el interés programado hasta 21 de junio de 2026, la diferencia diagnóstica es $1.420.007. En mora, $1.861.547 de cargos menos $59.490 de créditos da $1.802.057, no $1.995.266: faltan $193.209 en el detalle."),
    p("El plan pactó 48 cuotas y termina el 21 de septiembre de 2026; el vencimiento facial 6 de julio de 2026 anticipa 77 días el horizonte ordinario y deja tres cuotas naturales, con capital programado $1.082.718 e interés futuro $61.170. Para cobrar todo antes del vencimiento ordinario deben probarse incumplimiento y aceleración; además, debe definirse cuál regla de vencimiento gobierna y resolverse la contradicción entre la instrucción incorporada —inicio de mora— y la carta separada —día de llenado—. El corte del extracto no prueba esos eventos."),
    p("Las mismas dos personas aparecen también en la obligación 1912000833, con roles invertidos. Cada expediente debe conservar saldo, pagos, acuerdos y anexos separados; antes de decidir demandas independientes o acumulación, debe descartarse un acuerdo global, aplicaciones cruzadas, duplicidad de rubros y riesgo de decisiones incompatibles."),
    h("2. SELECCIÓN Y ANÁLISIS FORENSE DEL TÍTULO"),
    p("Título seleccionado: PAGARÉ 1912000835.pdf, cuatro páginas, SHA-256 2892c8efb882aa96d57d8a55844cf2c8538e724649b11ea9db8244c7c12d758a. Archivo descartado como título: 1912000835.pdf, SHA-256 53c40220cbef610a638e1a24d47d8f7976da437542dc7be3a8e77baf8d30a60d. Se conserva el segundo únicamente para trazabilidad."),
    p("El actual fue procesado por iLovePDF el 16 de julio de 2026 y combina página 1 A4 con páginas 2 a 4 carta. Sustituyó solo la página 1 por una nueva digitalización diligenciada; las páginas 2, 3 y 4 son idénticas byte por byte a las anteriores. En la página 1 previa ya constaban 21-09-22 y 1912000835; estaban en blanco agencia, capital, interés, tasa, vencimiento, nombres y cédulas, y no figuraba ‘48 cuotas mensuales’. Ello es compatible con llenado posterior autorizado, pero no prueba fecha, persona, fuente ni identidad del original."),
    p("El actual inserta capital $10.000.000, interés $3.260.240, tasa 39,29 %, 48 cuotas y vencimiento 6-07-26. Que el capital permanezca en el monto inicial mientras el interés coincide exactamente con el saldo del extracto es compatible con un llenado híbrido. La agencia sigue vacía. Debe exhibirse original y reconstruirse campo por campo."),
    h("3. DATO–SOPORTE"),
    p("[[TABLA_DATOS]]"),
    h("4. RECONCILIACIÓN CONTABLE"),
    p("[[TABLA_CONTABLE]]"),
    p("El total informado, $13.052.169, sí suma los tres rubros. Los RC y CB aparentan recaudos por $5.091.350, con $1.562.858 aplicado a capital; la NA 1017844 aplica $45.946 a interés y $4.454 a mora, y el CO 1000434 aplica $681.868. El último RC visible es de 18 de octubre de 2023 por $887.000; no hay créditos después del CO. Debe distinguirse último crédito contable de último pago efectivo probado."),
    h("5. EXIGIBILIDAD, LLENADO Y PRESCRIPCIÓN"),
    p("El plan prevé la cuota 48 para el 21 de septiembre de 2026. Al corte facial faltaban las cuotas 46, 47 y 48. La cláusula aceleratoria puede hacer exigible anticipadamente el saldo, pero se requiere identificar primera cuota impagada, evento concreto, declaración de aceleración y efectos de cualquier prórroga, reestructuración o acuerdo."),
    p("El extracto registra mora desde el 30 de noviembre de 2022 y, después del último RC de 18 de octubre de 2023, causaciones mensuales continuas, incluso posteriores al CO. Por ello, el 6 de julio de 2026 no aparece como inicio de mora con la evidencia disponible. Solo una cura, prórroga o reestructuración probada, junto con la distinción entre mora de cuotas y mora del saldo acelerado, podría explicar esa fecha."),
    p("Debe aplicarse el artículo 69 de la Ley 45 de 1990: si CREARCOOP exigió anticipadamente el total, no puede darse por restituido el plazo sin verificar la excepción legal relativa a cobrar mora únicamente sobre cuotas vencidas. La base histórica de mora y cualquier acuerdo deben mostrar si hubo aceleración, posterior restitución y tratamiento de cada cuota."),
    p("La fecha facial llevaría en principio la acción cambiaria directa al 6 de julio de 2029. Si gobernara el cronograma individual, al corte las nueve cuotas de octubre de 2022 a junio de 2023 ya superaban tres años; al 21 de septiembre de 2026 serían doce. Es un riesgo alternativo, no una conclusión: pagos, aceleración y acuerdos deben analizarse por cuota y firmante. Un asiento interno no prueba reconocimiento interruptivo. Debe determinarse primero si los obligados son signatarios en el mismo grado y luego armonizar los artículos 632 y 792 del Código de Comercio con el 2540 del Código Civil."),
    h("6. SEMÁFORO DE GATES"),
    p("[[TABLA_GATES]]"),
    h("7. ANÁLISIS COMO CONTRAPARTE"),
    p("La defensa alegará llenado contrario a instrucciones, vencimiento anticipado sin aceleración probada, pago parcial, cobro de interés futuro, mora no reproducible, CO no consentido, prescripción de cuotas, falta de entrega neta, ausencia de original, personería, pacto arbitral, notificación defectuosa y cautela excesiva. Destacará que el capital facial quedó original mientras el interés se actualizó al extracto."),
    p("También explotará la anomalía de las solicitudes: la marcada como solicitante de José contiene el nombre de Ángela en el encabezado, y la marcada como codeudora contiene el nombre de Efraín, aunque el resto de datos, firmas, cédulas y liquidación permiten distinguirlos. Debe inventariarse sin convertir formularios históricos en prueba actual de domicilio o activos."),
    h("8. ANÁLISIS COMO JUEZ"),
    p("El juez puede reconocer fuerza facial al pagaré, pero limitar o negar conceptos cuya claridad y exigibilidad no estén demostradas, conforme a los artículos 422 y 430 del CGP. El artículo 622 del Código de Comercio permite llenar espacios únicamente según instrucciones. El artículo 624 regula exhibición y pago parcial; la acción por la parte no pagada se sustenta en el artículo 782 y exige demostrarla, no escoger un saldo incierto. También debe explicarse la ausencia de anotaciones físicas de abonos."),
    p("La demanda definitiva necesita una sola cifra certificada; memoria de remuneratorios que excluya futuro; mora histórica conciliada; y mora futura solo sobre capital desde el día siguiente a exigibilidad válida, a la tasa legal de cada periodo. Debe decidirse de forma expresa si se acumulan o separan las obligaciones 1912000833 y 1912000835, sin mezclar pruebas ni movimientos."),
    h("9. CAUTELA"),
    p("La licencia histórica individualiza el vehículo FLJ238: Nissan, línea T5U41, modelo 1998, camión de estacas, servicio público, blanco, diésel, 4.200 cc, registrado en Floridablanca y expedido el 27 de abril de 2021 a nombre de José. Es la única cautela con soporte individual. Debe obtenerse certificado RUNT/tránsito actual, gravámenes, estado, identificación técnica y avalúo."),
    p("La declaración de inmueble de Ángela no tiene matrícula y contradice manzana 24/manzana 29. Tampoco hay prueba actual de cuentas, empleador o créditos frente a terceros. Si RUNT no confirma titularidad o el camión resulta desproporcionado, debe retirarse o reformularse la cautela."),
    p("FLJ238 también es el bien propuesto en el expediente 1912000833. Antes de presentar medidas separadas debe verificarse si existe solicitud, inscripción o proceso previo y escoger una coordinación procesal lícita —acumulación, concurrencia o remanentes según el estado real— que evite duplicidad, límites incompatibles o doble valoración del mismo activo."),
    h("10. ANEXOS E ÍNDICE DE CIERRE"),
    p("[[TABLA_ANEXOS]]"),
]

DATOS_ROWS = [
    ("Deudor", "José Efraín Hincapié Villa — C.C. 1.061.655.910", "Pagaré pp. 1–3; liquidación; CC p. 5", "Coincidente"),
    ("Codeudora", "Ángela Yisela Restrepo Varón — C.C. 1.033.789.770", "Pagaré pp. 1–3; CC p. 11", "Coincidente"),
    ("Título/obligación", "Pagaré 1912000835 / obligación 10-1912000835", "Pagaré y extracto", "Coincidente"),
    ("Solicitud/aprobación/desembolso", "14/9/2022 · 19/9/2022 · 21/9/2022", "Liquidación p. 1", "Evidenciado"),
    ("Solicitud vs. aprobación", "$25.000.000/36 meses vs. $10.000.000/48 meses", "Solicitudes pp. 3 y 9; liquidación", "Cambio documentado"),
    ("Neto registrado", "$9.076.000; deducciones $924.000", "Liquidación p. 1", "Entrega autónoma pendiente"),
    ("Plan", "48 × $381.296; 21/10/2022–21/9/2026", "Liquidación pp. 1–2", "Última futura al corte"),
    ("Tasa", "33,60 % N.A.M.V. / 39,29 % E.A., fija", "Liquidación", "Pagaré solo dice 39,29 %"),
    ("Vencimiento facial", "6 de julio de 2026", "Pagaré p. 1", "77 días antes del plan"),
    ("Capital facial", "$10.000.000", "Pagaré p. 1", "Contradice saldo"),
    ("Capital extracto", "$7.796.663 al 6/7/2026", "Extracto p. 3", "CO pendiente"),
    ("Interés", "$3.260.240", "Pagaré y extracto", "Coincide; no reproduce"),
    ("Mora", "$1.995.266", "Extracto p. 3", "No reproduce"),
    ("Último RC", "18/10/2023 por $887.000", "Extracto p. 1", "No confundir con CO"),
    ("Dirección José", "Cr. 2 No. 3-45 casa 14, Cota, Cundinamarca", "Liquidación p. 1", "Histórica 2022"),
    ("Direcciones Ángela", "Lote 15, manzana 24; inmueble declarado manzana 29", "Solicitud p. 9", "Contradictorias/históricas"),
    ("Solicitudes", "Encabezados José/Ángela cruzados respecto del resto del formulario", "CC pp. 3 y 9", "Anomalía inventariada"),
    ("Vehículo José", "FLJ238, Nissan T5U41, modelo 1998, camión estacas", "Licencia p. 6", "Titularidad 2021; actualizar"),
    ("Terceros", "Víctor Muñoz, Alejandro Cogua/Reciclajes y Ángela como certificadora", "CC pp. 7–10", "No obligados/pagadores actuales"),
]

CONT_ROWS = [
    ("Capital", "$10.000.000", "$2.203.337", "$7.796.663", "$7.796.663", "Solo si CO extingue"),
    ("Interés", "$8.302.208 programado", "$3.560.791", "$4.741.417", "$3.260.240", "No: −$1.481.177"),
    ("Mora", "$1.861.547", "$59.490", "$1.802.057", "$1.995.266", "No: +$193.209"),
    ("Total", "—", "—", "—", "$13.052.169", "Suma rubros"),
]

GATES_ROWS = [
    ("G0", "Norma vigente", "AMARILLO", "Revalidar fuentes oficiales, tasa, competencia y cuantía al presentar."),
    ("G1", "Identidad/legitimación", "AMARILLO", "Partes coinciden; falta certificado vigente y continuidad de acreedora."),
    ("G2", "Personería", "ROJO", "Poder para ambos, representante, apoderada/RNA y firma pendientes."),
    ("G3", "Jurisdicción", "ROJO", "Contrato y anexos incompletos; control arbitral pendiente."),
    ("G4", "Competencia", "ROJO", "Domicilios de 2022 y formularios territorialmente inconsistentes."),
    ("G5", "Título/original", "ROJO", "Original, custodia, no circulación y trazabilidad de llenado pendientes."),
    ("G6", "Llenado", "ROJO", "Capital original, interés al corte, agencia vacía y dos reglas de vencimiento."),
    ("G7", "Exigibilidad", "ROJO", "Antes de 21/9/2026: aceleración válida; después: vencimiento ordinario y defensa de llenado."),
    ("G8", "Prescripción", "ROJO", "Facial 6/7/2029; controlar cuotas, acuerdos y actos por firmante."),
    ("G9", "Saldo/intereses", "ROJO", "Capital depende del CO; interés y mora no concilian."),
    ("G10", "Movimientos/acuerdos", "ROJO", "CO/NA, último pago efectivo y posibles acuerdos sin soporte."),
    ("G11", "Procesos/estrategia", "ROJO", "Consultas actuales y decisión 0833/0835 separadas o acumuladas."),
    ("G12", "Notificación/cautela", "ROJO", "Canales 2022; FLJ238 sin RUNT ni avalúo actuales."),
]

ANEXOS_ROWS = [
    ("A1", "Pagaré + instrucciones", "PAGARÉ 1912000835.pdf", "4", "Disponible / original pendiente"),
    ("A2", "Liquidación, solicitudes, CC y soportes", "CC 1061655910 PAGARE 1912000835.pdf", "11", "Disponible / depurar"),
    ("A3", "Extracto al 6/7/2026", "EXTRACTO 1061655910.PDF", "3", "Disponible / certificar"),
    ("A4", "CO/NA + memoria y soportes fuente", "[NOMBRE EXACTO]", "[●]", "Pendiente"),
    ("A5", "Aceleración, llenado y custodia", "[NOMBRE EXACTO]", "[●]", "Pendiente"),
    ("A6", "Personería, contrato, consultas, notificación y RUNT", "[NOMBRES EXACTOS]", "[●]", "Pendiente"),
]


DEMANDA = [
    p("PRECIERRE — NO RADICAR NI FIRMAR HASTA REEMPLAZAR TODOS LOS CAMPOS ROJOS", donor=7, bold=True, color=RED, size=13),
    p("[CIUDAD REAL DE PRESENTACIÓN], [FECHA REAL DE PRESENTACIÓN]", donor=0, color=RED),
    p("SEÑOR JUEZ DE PEQUEÑAS CAUSAS Y COMPETENCIA MÚLTIPLE DE [CIUDAD/JURISDICCIÓN PROBADA] (REPARTO)", donor=1, bold=True, color=RED),
    p("E. S. D.", donor=2),
    p("REFERENCIA: DEMANDA EJECUTIVA DE MÍNIMA CUANTÍA — PAGARÉ No. 1912000835 — OBLIGACIÓN No. 10-1912000835.", donor=7),
    p("DEMANDANTE: COOPERATIVA DE AHORRO Y CRÉDITO CREAR, sigla CREARCOOP, NIT 890.981.459-4.", donor=8),
    p("DEMANDADOS: JOSÉ EFRAÍN HINCAPIÉ VILLA, C.C. 1.061.655.910, y ÁNGELA YISELA RESTREPO VARÓN, C.C. 1.033.789.770.", donor=9),
    p("[APODERADA], actuando por CREARCOOP conforme al poder especial adjunto, presenta demanda ejecutiva contra JOSÉ EFRAÍN HINCAPIÉ VILLA, deudor, y ÁNGELA YISELA RESTREPO VARÓN, codeudora solidaria, por el saldo insoluto del pagaré No. 1912000835.", donor=15, color=RED),
    h("I. HECHOS", donor=16),
    p("PRIMERO. José Efraín Hincapié Villa y Ángela Yisela Restrepo Varón suscribieron el pagaré No. 1912000835, obligación No. 10-1912000835, a la orden de la entidad denominada en el título COOPERATIVA DE AHORRO Y CRÉDITO CREAR LTDA., sigla CREARCOOP, y se obligaron solidaria e indivisiblemente. [ADJUNTAR CERTIFICADO VIGENTE Y PRECISAR CONTINUIDAD CON LA RAZÓN ACTUAL].", donor=17, color=RED),
    p("SEGUNDO. La liquidación registra solicitud el 14 de septiembre de 2022, aprobación el 19 y desembolso el 21 del mismo mes por $10.000.000, modalidad microempresarial nuevos informales. Registra neto a desembolsar $9.076.000, después de conceptos por $924.000. [APORTAR COMPROBANTE AUTÓNOMO Y JUSTIFICACIÓN DE CADA DESCUENTO].", donor=18, color=RED),
    p("TERCERO. El plan previó 48 cuotas mensuales de $381.296, primera el 21 de octubre de 2022 y última el 21 de septiembre de 2026, a tasa fija de 33,60 % N.A.M.V., documentalmente equivalente a 39,29 % E.A.", donor=19),
    p("CUARTO. Ambos demandados firmaron el pagaré con espacios y autorizaron su diligenciamiento conforme a las instrucciones incorporadas y separadas.", donor=20),
    p("QUINTO. [RUTA A — PRESENTACIÓN ANTES DEL 21/9/2026: IDENTIFICAR PRIMERA CUOTA IMPAGADA, ACELERACIÓN, FECHA Y FORMA PROBADA DE EJERCICIO —DILIGENCIAMIENTO, DEMANDA, COMUNICACIÓN SI EXISTIÓ U OTRO ACTO IDÓNEO— Y EFECTOS. RUTA B — PRESENTACIÓN DESDE EL 21/9/2026: ACREDITAR VENCIMIENTO ORDINARIO TOTAL, SIN PRETENDER QUE EL PASO DEL TIEMPO CURÓ EL LLENADO]. Aportar acuerdos, prórrogas o reestructuraciones y aplicar el artículo 69 de la Ley 45 de 1990 sobre aceleración, restitución del plazo y base de mora.", donor=21, color=RED),
    p("SEXTO. [PROBAR FECHA MATERIAL DE LLENADO; DEFINIR CUÁL REGLA DE VENCIMIENTO GOBIERNA Y RESOLVER LA CONTRADICCIÓN ENTRE ‘INICIO DE MORA’ Y ‘DÍA DE LLENADO’. EXPLICAR LA BASE DEL 6/7/2026. SI NO SE SUSTENTA, NO RADICAR].", donor=22, color=RED),
    p("SÉPTIMO. [USAR SOLO SI CO 1000434 EXTINGUIÓ DEUDA] Al 6 de julio de 2026, $10.000.000 menos $2.203.337 de créditos o aplicaciones arroja $7.796.663. [SI EL CO NO FUE EXTINTIVO, REHACER EL HECHO CON EL DOCUMENTO FUENTE; NO USAR $8.437.142 COMO PRETENSIÓN AUTOMÁTICA].", donor=26, color=RED),
    p("OCTAVO. [CERTIFICAR EL DÍA DE PRESENTACIÓN] A [FECHA/HORA], el capital insoluto es $[CAPITAL CERTIFICADO], los remuneratorios causados son $[●], la mora causada es $[●] y desde [FECHA] [NO HUBO/HUBO] pagos o ajustes [DETALLE].", donor=27, color=RED),
    p("NOVENO. [DECISIÓN EXPRESA] Los $3.260.240 de interés y $1.995.266 de mora del extracto [SE EXCLUYEN POR NO SER REPRODUCIBLES / SE RECLAMAN CON MEMORIA COMPLETA]. Si se excluyen, documentar disposición y fragmentación; si se reclaman, insertar base, tasa, periodos, pagos y sumas exactas, excluyendo todo remuneratorio no causado desde la fecha real de aceleración o exigibilidad. Los $61.170 de las cuotas 46–48 son solo el mínimo futuro programado al corte facial.", donor=28, color=RED),
    p("DÉCIMO. [DECISIÓN DE ESTRATEGIA] La obligación 1912000833 vincula a las mismas personas con roles invertidos. [EXPLICAR POR QUÉ SE DEMANDA SEPARADAMENTE O ACUMULAR PRETENSIONES, Y ACREDITAR AUSENCIA DE APLICACIONES CRUZADAS O ACUERDO GLOBAL].", donor=29, color=RED),
    p("UNDÉCIMO. [SOLO CON CERTIFICACIÓN] CREARCOOP conserva el original, no lo ha endosado ni circulado y lo exhibirá cuando sea requerido.", donor=29, color=RED),
    h("II. PRETENSIONES", donor=30),
    p("Solicito librar mandamiento a favor de CREARCOOP y contra ambos demandados, solidariamente, por:", donor=31),
    p("PRIMERA. [CAPITAL EN LETRAS] PESOS M/CTE ($[CAPITAL CERTIFICADO]), por capital insoluto. El corte histórico arroja $7.796.663 solo si el CO está probado como extintivo.", donor=32, color=RED),
    p("SEGUNDA. Intereses moratorios exclusivamente sobre el capital certificado, desde [DÍA SIGUIENTE A EXIGIBILIDAD VALIDADA] hasta el pago, a la tasa legalmente aplicable en cada periodo, sin exceder la máxima autorizada, sin capitalización ni superposición.", donor=33, color=RED),
    p("[MÓDULO OPCIONAL] TERCERA. [SUMA EN LETRAS] PESOS ($[●]) por intereses [REMUNERATORIOS/MORATORIOS] causados entre [FECHAS], sobre [BASE], a [TASA], menos [PAGOS]. SI SE USA, COSTAS PASA A CUARTA; SI NO, ELIMINAR.", donor=34, color=RED),
    p("TERCERA [O CUARTA]. Costas y agencias en derecho.", donor=35, color=RED),
    h("III. FUNDAMENTOS DE DERECHO"),
    p("Artículos 17, 25, 26, 28, 82, 84, 88, 94, 422, 430 y 431 del CGP; 619, 621, 622, 624, 632, 709, 710, 782, 784, 785, 789, 792 y 884 del Código de Comercio; 2540 del Código Civil; artículos 68 y 69 de la Ley 45 de 1990; Ley 2213 de 2022 y normas concordantes.", donor=38),
    h("IV. COMPETENCIA, TRÁMITE Y CUANTÍA", donor=40),
    p("Es competente [JUEZ Y CIUDAD] por [DOMICILIO ACTUAL PROBADO O REGLA TERRITORIAL]. Los formularios de 2022 no prueban domicilio actual y presentan campos territoriales invertidos. [SUSTITUIR POR HECHO EXACTO].", donor=41, color=RED),
    p("La cuantía es [TOTAL ACTUAL EN LETRAS] ($[TOTAL ACTUAL]), sin intereses futuros ni costas, inferior a cuarenta (40) SMLMV vigentes al presentar.", donor=42, color=RED),
    h("V. PRUEBAS Y ANEXOS", donor=43),
    p("1. Pagaré No. 1912000835 e instrucciones íntegras.", donor=45),
    p("2. Liquidación, plan, solicitudes, identificaciones y comprobante/certificación de desembolso.", donor=45),
    p("3. CO 1000434, NA 1017844 y soportes fuente de pagos/aplicaciones.", donor=45),
    p("4. Extracto, certificación contable y memoria reproducible actualizada.", donor=45),
    p("5. Aceleración, trazabilidad de llenado, custodia, no circulación y original.", donor=45),
    p("6. Certificado vigente, poder especial, contrato, consultas y anexos realmente aportados.", donor=46, color=RED),
    h("VI. MEDIDA CAUTELAR Y ENVÍO PREVIO", donor=56),
    p("[USAR SOLO SI RUNT/TRÁNSITO CONFIRMA FLJ238 Y SE PRESENTA LA CAUTELA] Se acompaña cautelar previa y se invoca la excepción del artículo 6 de la Ley 2213. [SI NO HAY CAUTELA REAL, ELIMINAR Y CUMPLIR LA REMISIÓN QUE CORRESPONDA].", donor=57, color=RED),
    h("VII. NOTIFICACIONES", donor=59),
    p("Demandante: CREARCOOP, [DIRECCIÓN Y CORREO DEL CERTIFICADO VIGENTE].", donor=61, color=RED),
    p("Apoderada: [NOMBRE, DIRECCIÓN Y CORREO RNA].", donor=64, color=RED),
    p("Demandados: [DIRECCIONES ACTUALES PROBADAS]. Canales electrónicos: [SOLO LOS PROBADOS Y JURAMENTO ART. 8 LEY 2213; SI SE DESCONOCEN, DECLARARLO].", donor=71, color=RED),
    p("Atentamente,", donor=89),
    p("[NOMBRE DE LA APODERADA]", donor=95, bold=True, color=RED),
    p("C.C. [●] — T.P. [●] — correo RNA [●]", donor=96, color=RED),
]


CAUTELAR = [
    p("MÓDULO CONDICIONAL — NO RADICAR SIN RUNT/TRÁNSITO ACTUAL, PROPIEDAD Y AVALÚO", donor=7, bold=True, color=RED, size=13),
    p("[CIUDAD], [FECHA REAL]", donor=0, color=RED),
    p("SEÑOR JUEZ DE [DESPACHO Y CIUDAD PROBADOS] (REPARTO)", donor=1, bold=True, color=RED),
    p("E. S. D.", donor=2),
    p("REFERENCIA: MEDIDA CAUTELAR PREVIA — EJECUTIVO DE MÍNIMA CUANTÍA.", donor=7),
    p("DEMANDANTE: COOPERATIVA DE AHORRO Y CRÉDITO CREAR — CREARCOOP, NIT 890.981.459-4.", donor=8),
    p("DEMANDADOS: JOSÉ EFRAÍN HINCAPIÉ VILLA, C.C. 1.061.655.910, y ÁNGELA YISELA RESTREPO VARÓN, C.C. 1.033.789.770.", donor=10),
    p("PAGARÉ No. 1912000835 — OBLIGACIÓN No. 10-1912000835.", donor=11),
    p("[APODERADA], por CREARCOOP, solicita, sujeta a certificado actual que acredite titularidad de José, la siguiente medida sobre el vehículo FLJ238:", donor=14, color=RED),
    h("I. MEDIDA SOLICITADA", donor=17),
    p("Embargo y posterior secuestro de vehículo automotor", donor=19, bold=True),
    p("PRIMERO. Decretar el embargo del vehículo placa FLJ238, marca Nissan, línea T5U41, modelo 1998, clase camión, carrocería estacas, servicio público, color blanco, combustible diésel, cilindraje 4.200 cc, [MOTOR, CHASIS, VIN Y DEMÁS DATOS EXACTOS DEL CERTIFICADO ACTUAL], cuya propiedad vigente de JOSÉ EFRAÍN HINCAPIÉ VILLA consta en [CERTIFICADO RUNT/TRÁNSITO DE FECHA Y CÓDIGO].", donor=24, color=RED),
    p("SEGUNDO. Comunicar a [ORGANISMO DE TRÁNSITO ACTUAL] por canal oficial y ordenar la inscripción, con proceso, partes, placa y límite.", donor=25, color=RED),
    p("TERCERO. Inscrito el embargo, disponer el secuestro conforme al artículo 601 del CGP, individualizando ubicación, autoridad comisionada, secuestre y custodia.", donor=26),
    p("CUARTO. Como criterio voluntario de proporcionalidad, limitar la medida a [LÍMITE EN LETRAS] ($[LÍMITE]), calculado sobre lo efectivamente reclamado, intereses y costas prudenciales, sin perjuicio de la excepción del artículo 599 del CGP cuando se persigue un solo bien.", donor=27, color=RED),
    p("QUINTO. Tramitar en cuaderno separado y preservar reserva hasta la práctica.", donor=45),
    h("II. SOPORTE Y PROPORCIONALIDAD", donor=17),
    p("La licencia expedida el 27 de abril de 2021 por Tránsito y Transporte de Floridablanca se aporta solo como antecedente histórico. [INSERTAR CERTIFICADO ACTUAL: propietario, organismo, datos, gravámenes, limitaciones y estado].", donor=17, color=RED),
    p("El embargo es registral y el secuestro procede después de su inscripción. El valor comercial actual es $[AVALÚO/FUENTE], frente a pretensión $[●] y límite $[●]. [EXPLICAR PROPORCIONALIDAD, GRAVÁMENES Y SI ES ÚNICO BIEN].", donor=17, color=RED),
    p("[COORDINACIÓN OBLIGATORIA] FLJ238 también fue identificado en el expediente 1912000833. Verificar solicitudes e inscripciones previas y definir acumulación, concurrencia o remanentes según el estado procesal, sin medidas paralelas incompatibles ni doble valoración del mismo bien.", donor=17, color=RED),
    p("No se pide el inmueble declarado por Ángela, por ausencia de matrícula y contradicción manzana 24/29; tampoco cuentas, salario, negocio o créditos frente a terceros indeterminados.", donor=17),
    h("III. FUNDAMENTOS", donor=19),
    p("Artículos 593, 599 y 601 del Código General del Proceso y normas de registro automotor aplicables.", donor=17),
    h("IV. ANEXOS ESPECÍFICOS", donor=19),
    p("1. Certificado actual RUNT/tránsito y consulta de propiedad/gravámenes.", donor=42),
    p("2. Identificación técnica y avalúo actual del vehículo.", donor=42),
    p("3. Liquidación actualizada y cálculo del límite.", donor=42),
    p("4. Poder y personería aportados con la demanda.", donor=42),
    p("Solo satisfechas estas condiciones podrá invocarse la excepción del artículo 6 de la Ley 2213.", donor=39, color=RED),
    p("Atentamente,", donor=47),
    p("[NOMBRE DE LA APODERADA]", donor=52, bold=True, color=RED),
    p("C.C. [●] — T.P. [●] — correo RNA [●]", donor=53, color=RED),
]


SUBSANACION = [
    p("FORMATOS Y ORDEN DE SUBSANACIÓN — EXPEDIENTE 1912000835", donor=7, bold=True, size=16),
    p("Uso interno · completar con evidencia real · prohibido antedatar", bold=True, color=RED),
    h("1. ORDEN DE CIERRE"),
    p("[[TABLA_FALTANTES]]"),
    h("2. PODER ESPECIAL"),
    p("[REPRESENTANTE VIGENTE, C.C. Y CALIDAD], por CREARCOOP, confiere poder especial a [ABOGADA, C.C., T.P. Y CORREO RNA] para ejecutivo contra JOSÉ EFRAÍN HINCAPIÉ VILLA, C.C. 1.061.655.910, y ÁNGELA YISELA RESTREPO VARÓN, C.C. 1.033.789.770, por pagaré 1912000835 y obligación 10-1912000835, incluidas cautelas. Enumerar solo facultades autorizadas y conservar mensaje de datos/aceptación."),
    h("3. CERTIFICACIÓN CONTABLE"),
    p("[NOMBRE/CALIDAD] certifica, con archivo reproducible, movimientos desde 21/9/2022 hasta [FECHA/HORA]. Distinguir RC, CB, NA, CM, CO y toda nota; no llamar pago a aplicación interna."),
    p("Explicar $10.000.000 − $2.203.337; CO 1000434 ($640.479 capital + $41.389 mora); NA 1017844; último RC 18/10/2023; total implícito de interés $6.821.031 frente a $8.302.208 programados y $8.241.038 causados programáticamente hasta 21/6/2026; créditos $3.560.791; saldo $3.260.240; mora $1.861.547 − $59.490 = $1.802.057 versus $1.995.266 y diferencia $193.209. Separar causado/no causado desde la fecha real de aceleración; incluir pagos posteriores, bases, tasas, días y aplicaciones."),
    h("4. ACELERACIÓN, VENCIMIENTO Y PRESCRIPCIÓN"),
    p("Ruta A, si se presenta antes del 21/9/2026: aportar primera mora, declaración y soporte de aceleración, comunicaciones y efectos. Ruta B, desde esa fecha: probar vencimiento ordinario de todo el plan. En ambas, aportar acuerdos, prórrogas, reestructuraciones y nuevo plan; explicar 21/9/2026 frente a 6/7/2026, excluir todo interés no causado desde la aceleración y resolver efectos frente a ambos firmantes."),
    p("Aplicar el artículo 69 de la Ley 45 de 1990: determinar si se exigió anticipadamente el total, si después se restituyó plazo y si la mora se cobró solo sobre cuotas vencidas. Controlar prescripción por cuotas y acción cambiaria; armonizar los artículos 632 y 792 del Código de Comercio con el 2540 del Código Civil. Esperar el vencimiento ordinario no cura por sí solo un llenado contrario."),
    h("5. TRAZABILIDAD DE LLENADO Y CUSTODIA"),
    p("Identificar quién, cuándo, dónde y con qué fuente completó cada campo; original, transferencias de custodia, no circulación, hash y exhibición. Resolver capital original, interés al corte, agencia vacía, 48 cuotas y dos reglas de vencimiento."),
    p("[[TABLA_LLENADO]]"),
    h("6. PERSONERÍA, CONTRATO, DESEMBOLSO Y ESTRATEGIA"),
    p("Obtener certificado vigente, poder para ambos, RNA, contrato/reglamento/anexos, control arbitral y comprobante de entrega neta $9.076.000. Clasificar bajo el artículo 68 de la Ley 45 de 1990 cuáles descuentos/cargos se reputan intereses y controlar costo efectivo/usura. Decidir demanda independiente o acumulación con 1912000833; impedir mezcla o doble aplicación de pagos y acuerdos."),
    h("7. DOMICILIO, NOTIFICACIÓN Y CONSULTAS"),
    p("Actualizar domicilios; no corregir campos invertidos por inferencia. Email solo con evidencia actual y juramento del artículo 8 de la Ley 2213. Consultar insolvencia, negociación de deudas, liquidación patrimonial, fallecimiento/sucesión y procesos paralelos de ambos al presentar. Calendarizar el artículo 94 del CGP: la interrupción solo se conserva si el mandamiento se notifica al demandado dentro del año contado desde el día siguiente a la notificación de esa providencia al demandante."),
    h("8. RUNT Y CAUTELA"),
    p("Obtener certificado actual FLJ238: propietario con C.C. correcta, organismo, datos técnicos, gravámenes, limitaciones y estado; avalúo y ubicación lícita. Verificar si el mismo bien ya fue pedido o afectado por 1912000833 y coordinar acumulación, concurrencia o remanentes. Si José no es propietario o el bien no es individualizable/proporcional, retirar o ajustar. El inmueble de Ángela carece de folio y dirección coherente."),
    h("9. ACTA DE LIBERACIÓN"),
    p("La revisora certificará confrontación integral; saldo del día; cero campos, alternativas o datos ajenos; título correcto; fuentes revalidadas; y estado APTO PARA REVISIÓN Y FIRMA HUMANA."),
    p("[[TABLA_CONTROL]]"),
]

FALTANTES_ROWS = [
    ("1", "CO/NA y pagos", "Naturaleza, aceptación, efecto y último pago efectivo", "Cartera/Contabilidad", "ROJO"),
    ("2", "Memoria certificada", "Capital, interés, mora, pagos y diferencias", "Contabilidad", "ROJO"),
    ("3", "Aceleración", "Primera mora, evento, comunicación y fecha", "Jurídica", "ROJO"),
    ("4", "Original/llenado", "Campos, instrucciones, custodia y no circulación", "Custodia", "ROJO"),
    ("5", "Certificado y poder", "Continuidad, representación, ambos obligados y RNA", "Secretaría", "ROJO"),
    ("6", "Contrato/desembolso", "Reglas, arbitraje y entrega $9.076.000", "Archivo/Jurídica", "ROJO"),
    ("7", "Estrategia 0833/0835", "Separación/acumulación y ausencia de cruces", "Dirección jurídica", "ROJO"),
    ("8", "Consultas oficiales", "Insolvencia, sucesión y procesos de ambos", "Jurídica", "ROJO"),
    ("9", "Notificación actual", "Domicilios y canales probados", "Cartera", "ROJO"),
    ("10", "RUNT/avalúo", "FLJ238: propiedad, datos, gravámenes, valor y organismo", "Jurídica", "ROJO"),
]

LLENADO_ROWS = [
    ("Agencia", "En blanco", "Oficina de solicitud/radicación", "[●]", "[No llenado]", "[persona/fecha]"),
    ("Otorgamiento", "Ya decía 210922", "Desembolso/causación antigua", "Liquidación 21/9/2022", "21-09-22", "Preexistente"),
    ("Capital", "En blanco", "Adeudado según registros", "[saldo al llenar]", "$10.000.000", "[persona/fecha]"),
    ("Interés", "En blanco", "Adeudado según registros", "[memoria]", "$3.260.240", "[persona/fecha]"),
    ("Tasa", "En blanco", "Política CREARCOOP; en defecto máxima legal", "[soporte]", "39,29 %", "[persona/fecha]"),
    ("Vencimiento", "En blanco", "Inicio mora / día llenado", "[evento]", "6/7/2026", "[persona/fecha]"),
    ("48 cuotas/nombres", "Ausentes p.1", "Sin regla específica", "Plan/páginas firmadas", "Añadidos", "[persona/fecha]"),
]

CONTROL_ROWS = [
    ("Partes/personería", "Identidad, certificado, poder para ambos, RNA y firma", "[ ]", "[folio]"),
    ("Jurisdicción", "Domicilio, contrato y ruta territorial definidos", "[ ]", "[folio]"),
    ("Título/exigibilidad", "Original, llenado, aceleración y vencimiento probados", "[ ]", "[folio]"),
    ("Liquidación", "Saldo actual y reproducible, sin futuro/doble cobro", "[ ]", "[folio]"),
    ("Prescripción/estrategia", "Términos, consultas y relación 0833/0835 cerrados", "[ ]", "[folio]"),
    ("Cautela/higiene", "RUNT, avalúo, límite, anexos y cero residuos", "[ ]", "[folio]"),
]

for _spec in DEMANDA:
    if _spec.get("color") == RED and "size" not in _spec:
        _spec["size"] = 10

FILES = [
    ("01_INFORME_MATRIZ_CONTROL_1912000835_V2.docx", DEMAND_TEMPLATE, INFORME, "Informe integral 1912000835 v2", {
        "[[TABLA_DATOS]]": {"headers": ("Elemento", "Dato", "Fuente", "Estado"), "rows": DATOS_ROWS, "widths": (1500, 3200, 2600, 1500)},
        "[[TABLA_CONTABLE]]": {"headers": ("Rubro", "Cargos/base", "Créditos", "Resta", "Saldo", "Concilia"), "rows": CONT_ROWS, "widths": (1200, 1800, 1700, 1500, 1500, 1400)},
        "[[TABLA_GATES]]": {"headers": ("Gate", "Control", "Estado", "Razón"), "rows": GATES_ROWS, "widths": (700, 2000, 1100, 5000)},
        "[[TABLA_ANEXOS]]": {"headers": ("No.", "Documento", "Archivo", "Págs.", "Estado"), "rows": ANEXOS_ROWS, "widths": (600, 2800, 2600, 700, 1800)},
    }),
    ("02_DEMANDA_EJECUTIVA_PRECIERRE_1912000835_V2_NO_RADICAR.docx", DEMAND_TEMPLATE, DEMANDA, "Demanda ejecutiva precierre 1912000835 v2", None),
    ("03_MEDIDA_CAUTELAR_PRECIERRE_1912000835_V2_NO_RADICAR.docx", CAUTION_TEMPLATE, CAUTELAR, "Medida cautelar precierre 1912000835 v2", None),
    ("04_FORMATOS_SUBSANACION_1912000835_V2.docx", DEMAND_TEMPLATE, SUBSANACION, "Formatos subsanación 1912000835 v2", {
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
        for value in ("1912000835", "JOSÉ EFRAÍN HINCAPIÉ VILLA", "ÁNGELA YISELA RESTREPO VARÓN"):
            if value.lower() not in text.lower():
                raise RuntimeError(f"Falta dato {value}: {path.name}")
        for residual in ("NELSON DAVID", "CAMILO ANDREY", "MARÍA AMELIA", "FOPEP", "APETITOZOS", "1912000810"):
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
