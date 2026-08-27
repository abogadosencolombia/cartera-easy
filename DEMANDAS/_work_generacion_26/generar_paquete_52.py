from __future__ import annotations

import csv
import re
import shutil
import unicodedata
from decimal import Decimal, ROUND_CEILING
from hashlib import sha256
from importlib.util import module_from_spec, spec_from_file_location
from pathlib import Path
from zipfile import ZIP_DEFLATED, ZipFile


ROOT = Path("/code/DEMANDAS")
OUT = ROOT / "PLANTILLAS_26_CASOS_PARA_COMPLETAR_2026-07-21"
CONTROL = OUT / "00_CONTROL_MAESTRO"
WORK = ROOT / "_work_generacion_26"
DEMAND_TEMPLATE = ROOT / "MODELO.docx"
CAUTION_TEMPLATE = ROOT / "MODELO MEDIDAS.docx"
BASE_SCRIPT = ROOT / "_work_1911000986" / "generar_entregables_1911000986.py"

spec = spec_from_file_location("base_docx", BASE_SCRIPT)
base = module_from_spec(spec)
assert spec.loader is not None
spec.loader.exec_module(base)

RED = "C00000"
NAVY = "1F4E78"
AMBER = "BF9000"
GREEN = "548235"
EXTRACT_CUT = "6 de julio de 2026"
EXTRACT_CUT_SHORT = "06/07/2026"
NEXT_DAY = "7 de julio de 2026"
LEGAL_NAME = "COOPERATIVA DE AHORRO Y CRÉDITO CREAR LTDA. — CREARCOOP"

MONTHLY_INSTALLMENT_CASES = {
    "1911000986", "1912000810", "1912000833", "1912000835", "1912000875", "1912000942",
    "1913000633", "1913000784", "1913000857", "1913000881", "1913000904", "1913000955",
    "1914000078", "1915000810", "1915000914", "1915000921", "2016000364", "2016000382",
    "2016000402", "2211000086", "2211000173", "2211000308", "2315000024", "2315000031",
    "2315000116",
}


CASES = [
    dict(n="1911000986", cc="41526686", primary="MARÍA AMELIA CABIEDES PLATA", product="MICROCRÉDITO EMPRESARIAL", initial=12000000, ledger_date="7 de octubre de 2022", capital=3745935, interest=1670935, mora=67662, cos=[], city="Bogotá D.C.", face_date="7 de octubre de 2022", maturity="6 de julio de 2026", installments=48, face_capital=12000000, face_interest=1670935, rate="26,82 % E.A. [VALIDAR MODALIDAD FACIAL]", asset_hint="FOPEP y dos inmuebles sin matrícula aparecen únicamente en soportes históricos de 2022. Confirmar condición actual de pensionada, pagador, mesada neta y descuentos; para inmuebles exigir folio, ORIP, titularidad y gravámenes actuales.", audit_alert="El vencimiento facial antecede en 93 días al final del plan (7 de octubre de 2026); existe un movimiento «CO» que aplica capital y mora sin acuerdo fuente, y la mora reconstruida no coincide con el resumen. No fijar exigibilidad, saldo ni cautela con esos soportes históricos."),
    dict(n="1912000810", cc="1033755601", primary="NELSON DAVID PARADA RINCÓN", product="MICROCRÉDITO EMPRESARIAL", initial=12000000, ledger_date="19 de agosto de 2022", capital=3118992, interest=3587258, mora=911676, cos=[("CAMILO ANDREY PARADA RINCÓN", "1018459638")], city="Bogotá D.C.", face_date="19 de agosto de 2022", maturity="6 de julio de 2026", installments=48, face_capital=12000000, face_interest=3587258, rate="26,82 % E.A. [VALIDAR MODALIDAD FACIAL]", asset_hint="Establecimiento APETITOZOS, matrícula 03415339, aparece en certificado CCB histórico de 19 de agosto de 2021 a nombre de Nelson David Parada Rincón. Obtener RUES/CCB vigente, titularidad, derechos, gravámenes y terceros antes de una cautela.", audit_alert="El vencimiento facial antecede en 47 días al final del plan; existen movimientos «CO» sin acuerdos fuente y las reconstrucciones de interés y mora difieren del resumen. Validar además grafía, domicilio y notificación actual de cada firmante."),
    dict(n="1912000833", cc="1033789770", primary="ÁNGELA YISELA RESTREPO VARÓN", product="MICROEMPRESARIAL NUEVOS INFORMALES", initial=6000000, ledger_date="20 de septiembre de 2022", capital=3882652, interest=852162, mora=1421444, cos=[("JOSÉ EFRAÍN HINCAPIÉ VILLA", "1061655910")], city="Bogotá D.C.", face_date="20 de septiembre de 2022", maturity="6 de julio de 2026", installments=36, face_capital=6000000, face_interest=852162, rate="39,29 % E.A. [VALIDAR MODALIDAD FACIAL]", asset_hint="Vehículo Nissan placa FLJ238 declarado históricamente por el codeudor, sin certificado actual; exigir RUNT/tránsito, titularidad, identificación técnica, servicio, gravámenes y avalúo. La vivienda atribuida a Ángela carece de matrícula.", audit_alert="El vencimiento facial es aproximadamente 289 días posterior al plan; las reconstrucciones de interés y mora difieren del resumen y existe un movimiento «CO» sin acuerdo fuente. Es expediente espejo del pagaré 1912000835 con roles invertidos: definir acumulación o estrategia coordinada y evitar doble afectación cautelar."),
    dict(n="1912000835", cc="1061655910", primary="JOSÉ EFRAÍN HINCAPIÉ VILLA", product="MICROEMPRESARIAL NUEVOS INFORMALES", initial=10000000, ledger_date="21 de septiembre de 2022", capital=7796663, interest=3260240, mora=1995266, cos=[("ÁNGELA YISELA RESTREPO VARÓN", "1033789770")], city="Cota", face_date="21 de septiembre de 2022", maturity="6 de julio de 2026", installments=48, face_capital=10000000, face_interest=3260240, rate="39,29 % E.A. / 33,60 % N.A.M.V. [VALIDAR MODALIDAD FACIAL]", asset_hint="Vehículo Nissan, placa FLJ238, modelo 1998, declarado históricamente a nombre de José Efraín Hincapié Villa; coordinar con el pagaré 1912000833 y exigir certificado RUNT/tránsito actual, propiedad, identificación, servicio, gravámenes y avalúo.", audit_alert="El vencimiento facial del 6 de julio de 2026 antecede en 77 días al final del plan (21 de septiembre de 2026); el capital/interés diligenciados y las instrucciones requieren trazabilidad; la mora reconstruida no coincide con el resumen y existe un compromiso interno de 31 de marzo de 2024 sin acuerdo fuente. Definir además si procede acumulación o demandas separadas frente al pagaré 1912000833."),
    dict(n="1912000875", cc="51797042", primary="BLANCA CECILIA BARRERA MARTÍNEZ", product="MICROEMPRESARIAL NUEVOS INFORMALES", initial=12000000, ledger_date="1 de diciembre de 2022", capital=1742489, interest=4569637, mora=255538, cos=[("ANA JUDIT CAÑAS CORREA", "1032433279")], city="Bogotá D.C.", face_date="1 de diciembre de 2022", maturity="6 de julio de 2026", installments=48, face_capital=12000000, face_interest=4569637, rate="42,57608868 % E.A. / 36 % N.A.M.V. [VALIDAR MODALIDAD FACIAL]", asset_hint="Los documentos históricos no individualizan bienes registrables de las obligadas. No formular cautela patrimonial sin una fuente oficial actual.", audit_alert="El vencimiento facial antecede en 148 días al final del plan (1 de diciembre de 2026); existen múltiples movimientos «CO» sin acuerdos fuente y la mora reconstruida difiere del resumen. La dirección de la codeudora presenta inconsistencias internas; actualizar identidad, domicilio, exigibilidad y memoria antes de demandar."),
    dict(n="1912000942", cc="1024492842", primary="FERNANDO MAURICIO MACHETE RODRÍGUEZ", product="CRÉDITO PRODUCTIVO POPULAR URBANO", initial=6000000, ledger_date="28 de abril de 2023", capital=4696029, interest=1349505, mora=1987442, cos=[("DEICY VIVIANA CABEZAS PAREDES", "1024522232")], city="Bogotá D.C.", face_date="28 de abril de 2023", maturity="6 de julio de 2026", installments=24, face_capital=6000000, face_interest=1349505, rate="46,78 % E.A. / 39 % N.A.M.V. [VALIDAR MODALIDAD Y LÍMITE POR PERIODO]", asset_hint="Vehículo SsangYong, modelo 2015, placa leída provisionalmente como ZZP022, autodeclarado por Fernando Mauricio Machete Rodríguez con prenda histórica; verificar placa, RUNT/tránsito, titularidad, identificación, gravámenes y avalúo actuales.", audit_alert="El plan finalizaba el 1 de mayo de 2025, 431 días antes del vencimiento facial; la mora reconstruida no coincide con el resumen y existe un ajuste «NA» sin soporte. No fijar exigibilidad ni cautela sobre el vehículo con documentos históricos."),
    dict(n="1913000633", cc="3187760", primary="GENARO ALONSO RODRÍGUEZ RODRÍGUEZ", product="MICROCRÉDITO EMPRESARIAL", initial=40000000, ledger_date="4 de abril de 2022", capital=12616467, interest=4143619, mora=194178, cos=[("JULIET JISELL RODRÍGUEZ ACERO", "1078347591"), ("JEIMY ANDREA RODRÍGUEZ ACERO", "1078347854")], city="Suesca", face_date="4 de abril de 2022", maturity="6 de julio de 2026", installments=60, face_capital=40000000, face_interest=4143619, rate="26,82 % E.A. / 24 % N.A.M.V. [VALIDAR MODALIDAD FACIAL]", asset_hint="Pistas históricas: inmueble rural sin folio y Nissan D21 modelo 1998 placa CCG386 atribuidos a Genaro; antigua cuenta Bancolombia terminada en 9588 y contratos públicos ya finalizados de Jeimy. Ninguna pista prueba un activo actual: exigir ORIP/RUNT/entidad/contrato vigente.", audit_alert="Es una refinanciación que aplicó parte del desembolso a la obligación 1913000435; debe reconciliarse el neto entregado, la cancelación previa y todos los movimientos. El vencimiento facial antecede aproximadamente 273 días al plan y la mora reconstruida difiere del resumen; hay varios «CO» sin acuerdos fuente."),
    dict(n="1913000784", cc="1071329791", primary="VÍCTOR GARZÓN FORERO", product="MICROEMPRESARIAL NUEVOS FORMALES", initial=6600000, ledger_date="12 de septiembre de 2022", capital=2829624, interest=343296, mora=628675, cos=[("ALBA MERCEDES CALDERÓN", "20744270")], city="Sesquilé", face_date="12 de septiembre de 2022", maturity="6 de julio de 2026", installments=36, face_capital=6600000, face_interest=343296, rate="34,49 % E.A. / 30 % N.A.M.V. [VALIDAR MODALIDAD FACIAL]", asset_hint="Pistas históricas: establecimiento/taller A y D, matrícula 03227807, y cafetería de la codeudora; hay valores patrimoniales contradictorios. Obtener RUES, titularidad y derechos actuales antes de solicitar embargo de establecimiento, crédito o remanente.", audit_alert="El vencimiento facial es 295 días posterior al final del plan; la mora reconstruida no coincide con el resumen y existe un movimiento «CO» sin acuerdo fuente. Resolver la fecha única de exigibilidad y actualizar la información patrimonial."),
    dict(n="1913000857", cc="23702420", primary="SANDRA EDITH MONTENEGRO SALGADO", product="PRÉSTAMO DE CONSUMO ORDINARIO", initial=4000000, ledger_date="28 de febrero de 2023", capital=3293903, interest=917245, mora=850868, cos=[], city="Chía", face_date="28 de febrero de 2023", maturity="6 de julio de 2026", installments=36, face_capital=4000000, face_interest=917245, rate="42,57608868 % E.A. / 36 % N.A.M.V. [VALIDAR MODALIDAD FACIAL]", asset_hint="Empleador histórico: Inmobiliaria La Aldaba Ltda., según certificación de febrero de 2023. Confirmar vínculo, pagador, NIT, canal, salario neto, descuentos, preferencias y límites actuales; no basta esa certificación histórica.", audit_alert="El vencimiento facial es 112 días posterior al final del plan; la mora reconstruida difiere del resumen y los movimientos mencionan un «ACUERDO DE PAGO» que no obra como fuente completa. La solicitud inicial y la aprobación también difieren en monto/plazo."),
    dict(n="1913000881", cc="20471487", primary="MARÍA DEL ROSARIO BERRÍO LÓPEZ DE RUBIANO", product="CRÉDITO PRODUCTIVO POPULAR URBANO", initial=5400000, ledger_date="17 de abril de 2023", capital=4202654, interest=531425, mora=1921853, cos=[], city="Chía", face_date="17 de abril de 2023", maturity="6 de julio de 2026", installments=24, face_capital=5400000, face_interest=531425, rate="16,76517763 % E.A. [VALIDAR MODALIDAD FACIAL]", asset_hint="Consulta SNR histórica de 12 de abril de 2023: matrícula 50N-20397749, sin dirección. No acredita titularidad actual; exigir certificado de tradición vigente, ORIP, identificación y gravámenes.", audit_alert="La agencia facial está en blanco. Las instrucciones sobre la fecha de vencimiento no son consistentes entre páginas; no fijar mora o llenado sin trazabilidad. Actualizar titularidad del inmueble y saldo."),
    dict(n="1913000904", cc="11325586", primary="ÁNGEL GABRIEL BENAVIDES RODRÍGUEZ", product="CRÉDITO PRODUCTIVO POPULAR URBANO", initial=4650000, ledger_date="26 de mayo de 2023", capital=4144299, interest=2298711, mora=1076631, cos=[("OCTAVIO BENAVIDES CÁRDENAS", "3086189")], city="Gachancipá", face_date="26 de mayo de 2023", maturity="6 de julio de 2026", installments=36, face_capital=4650000, face_interest=2298711, rate="46,78467782 % E.A. [VALIDAR MODALIDAD Y LÍMITE POR PERIODO]", asset_hint=None, audit_alert="El título identifica agencia Chía, mientras el soporte sugiere Gachancipá; la competencia depende del domicilio actual o lugar de cumplimiento probado. Hay codeudor y debe analizarse exigibilidad, prescripción, insolvencia y notificación por separado."),
    dict(n="1913000955", cc="1078347854", primary="JEIMY ANDREA RODRÍGUEZ ACERO", product="PRÉSTAMO DE CONSUMO ORDINARIO", initial=17200000, ledger_date="18 de septiembre de 2023", capital=14273680, interest=7360413, mora=902697, cos=[("GENARO ALONSO RODRÍGUEZ RODRÍGUEZ", "3187760")], city="Suesca", face_date="18 de septiembre de 2023", maturity="6 de julio de 2026", installments=55, face_capital=17200000, face_interest=7360413, rate="23,87205316 % E.A. [VALIDAR MODALIDAD FACIAL]", asset_hint=None, audit_alert="El plan contractual finaliza el 1 de mayo de 2028, muy después del vencimiento facial del 6 de julio de 2026. Explicar con documentos la aceleración, el diligenciamiento y la fecha única de mora; el codeudor también figura en el pagaré 1913000633 y exige coordinación procesal."),
    dict(n="1914000078", cc="1020405945", primary="JUAN DAVID CORREA GIRALDO", product="MICROCRÉDITO EMPRESARIAL", initial=7000000, ledger_date="30 de abril de 2019", capital=6617781, interest=3150182, mora=9505440, cos=[("MARÍA YANITH ÁNGEL BUITRAGO", "1076654187")], city="Ubaté", face_date="30 de abril de 2019", maturity="6 de julio de 2026", installments=36, face_capital=7000000, face_interest=3150182, rate="34,49 % E.A. [VALIDAR MODALIDAD FACIAL]", asset_hint=None, audit_alert="La agencia facial está en blanco y el crédito recogió o refinanció la obligación 1814000134. La cuantía de mora es superior al capital: exige trazabilidad completa del crédito anterior, aplicación de pagos, periodos, tasa máxima y control de prescripción."),
    dict(n="1914000814", cc="1068953260", primary="JOSÉ LIBARDO RODRÍGUEZ MONTAÑO", product="MICROEMPRESARIAL NUEVOS INFORMALES", initial=10000000, ledger_date="2 de febrero de 2023", capital=8655822, interest=2682935, mora=3803568, cos=[("EVANGELISTA RODRÍGUEZ RINCÓN", "2983713")], city="Carmen de Carupa", face_date="1 de febrero de 2023", maturity="6 de julio de 2026", installments=10, face_capital=10000000, face_interest=2682935, rate="51,10686573 % E.A. [VALIDAR MODALIDAD, PERIODICIDAD Y LÍMITE POR PERIODO]", asset_hint=None, audit_alert="El título fue fechado un día antes del desembolso registrado y la agencia facial está en blanco. El plan indica diez cuotas trimestrales: verificar periodicidad, tasa, entrega y fecha única de exigibilidad para ambos obligados."),
    dict(n="1915000810", cc="1032506521", primary="JUAN DAVID PEÑA CARABALLO", product="PRÉSTAMO DE CONSUMO ORDINARIO", initial=11000000, ledger_date="19 de febrero de 2022", capital=997733, interest=20469, mora=18426, cos=[], city="Bogotá D.C.", face_date="19 de febrero de 2022", maturity="6 de julio de 2026", installments=40, face_capital=11000000, face_interest=20469, rate="23,87 % E.A. [VALIDAR MODALIDAD FACIAL]", asset_hint=None, audit_alert="La agencia facial está en blanco. El interés facial de $20.469 coincide con el saldo de interés del extracto, pero es muy inferior al interés total del plan; probar cuándo y cómo se llenó y evitar presentarlo como interés total originalmente pactado."),
    dict(n="1915000914", cc="80157227", primary="EDYSON URREGO CRUZ", product="PRÉSTAMO DE CONSUMO ORDINARIO", initial=8000000, ledger_date="31 de agosto de 2022", capital=5727835, interest=1621249, mora=1775110, cos=[("MARÍA VICTORIA MARIÑO BEDOYA", "52972794")], city="Bogotá D.C.", face_date="31 de agosto de 2022", maturity="6 de julio de 2026", installments=42, face_capital=8000000, face_interest=1621249, rate="26,82 % E.A. [VALIDAR MODALIDAD FACIAL]", asset_hint=None, audit_alert="La agencia facial está en blanco. La codeudora debe incluirse y analizarse individualmente; cerrar trazabilidad del llenado, exigibilidad, pagos, mora, insolvencia y canales antes de presentar."),
    dict(n="1915000921", cc="1019091195", primary="MARÍA FERNANDA RODRÍGUEZ PINZÓN", product="MICROCRÉDITO EMPRESARIAL", initial=7000000, ledger_date="12 de septiembre de 2022", capital=5023587, interest=1260935, mora=1984881, cos=[], city="Bogotá D.C.", face_date="12 de septiembre de 2022 [TRAZO TENUE — CONFRONTAR ORIGINAL]", maturity="5 de julio de 2026", installments=36, face_capital=7000000, face_interest=1260935, rate="26,82 % E.A. [VALIDAR MODALIDAD FACIAL]", asset_hint=None, audit_alert="El vencimiento facial es el 5 de julio de 2026, un día antes del corte del extracto. Existe un movimiento «CO» de 31 de agosto de 2023 denominado generación de compromisos; obtener su soporte y efecto antes de fijar exigibilidad y saldo."),
    dict(n="2016000364", cc="1127382354", primary="JOSÉ IGNACIO ORTIZ TOVAR", product="PRÉSTAMO DE CONSUMO ORDINARIO", initial=17700000, ledger_date="12 de enero de 2023", capital=1068924, interest=0, mora=378521, cos=[], city="Villavicencio", face_date="12 de enero de 2023", maturity="6 de julio de 2026", installments=12, face_capital=17700000, face_interest=0, rate="16,76517763 % E.A. [VALIDAR MODALIDAD FACIAL]", asset_hint="El soporte histórico refiere una libranza. Confirmar pagador, vigencia del vínculo, autorización, descuentos, saldo y régimen aplicable antes de formular embargo de salario u honorarios.", audit_alert="La agencia facial está en blanco; el plan histórico indicaba última cuota el 15 de enero de 2024, muy anterior al vencimiento facial. Reconciliar libranza, pagos, llenado y fecha única de exigibilidad."),
    dict(n="2016000382", cc="1121877588", primary="SONIA STEPHANE BEDOYA LIÉVANO", product="MICROEMPRESARIAL NUEVOS FORMALES", initial=4600000, ledger_date="7 de marzo de 2023", capital=3918552, interest=1110213, mora=1485449, cos=[("SANDRA PATRICIA ÁLVAREZ [COMPLETAR SEGUNDO APELLIDO]", "1090387419")], city=None, face_date="7 de marzo de 2023", maturity="6 de julio de 2026", installments=36, face_capital=3918552, face_interest=1110213, rate="51,10686573 % E.A. [VALIDAR MODALIDAD Y LÍMITE POR PERIODO]", asset_hint=None, audit_alert="El nombre completo de la codeudora no quedó legible en las fuentes revisadas. El capital facial coincide con el saldo histórico y no con el primer cargo de $4.600.000; probar desembolso, llenado, tasa, pagos y exigibilidad antes de usar el título."),
    dict(n="2016000402", cc="1121937363", primary="BERONICA ANDREA GONZALEZ CORDOBA", product="PRÉSTAMO DE CONSUMO ORDINARIO", initial=8600000, ledger_date="20 de abril de 2023", capital=7527339, interest=2344098, mora=2154598, cos=[("MILEYNA SERRANO AGUIRRE", "1022408499")], city=None, face_date="20 de abril de 2023", maturity="6 de julio de 2026", installments=36, face_capital=8600000, face_interest=2344098, rate="26,82417946 % E.A. [VALIDAR MODALIDAD FACIAL]", asset_hint=None, audit_alert="El interés facial coincide con el saldo histórico del extracto; demostrar fecha y fuente de diligenciamiento, aplicación de pagos y fecha única de exigibilidad para ambas firmantes."),
    dict(n="2211000086", cc="1015394579", primary="MARTHA LILIANA ALFONSO WILCHES", product="MICROCRÉDITO FIDELIDAD", initial=3300000, ledger_date="7 de marzo de 2023", capital=2772505, interest=550599, mora=944860, cos=[], city=None, face_date="7 de marzo de 2023", maturity="6 de julio de 2026", installments=36, face_capital=2772505, face_interest=550599, rate="16,76517763 % E.A. [VALIDAR MODALIDAD FACIAL]", asset_hint=None, audit_alert="El capital facial coincide con el saldo histórico y no con el primer cargo de $3.300.000. Probar desembolso, fecha y fuente del llenado, pagos, tasa y exigibilidad; no tratar los campos faciales como valores originales sin esa trazabilidad."),
    dict(n="2211000173", cc="1071163544", primary="DIEGO ALEXANDER ROZO FERNANDEZ", product="PRÉSTAMO DE CONSUMO ORDINARIO", initial=8000000, ledger_date="10 de julio de 2023", capital=5798688, interest=1251632, mora=753520, cos=[("KAREN EDITH JIMENEZ BEJARANO", "1071171007")], city=None, face_date="10 de julio de 2023", maturity="6 de julio de 2026", installments=48, face_capital=8000000, face_interest=1251632, rate="42,57608868 % E.A. [VALIDAR MODALIDAD Y LÍMITE POR PERIODO]", asset_hint=None, audit_alert="El interés facial coincide con el saldo histórico. Cerrar trazabilidad de llenado, pagos, exigibilidad y tasa; analizar a deudor y codeudora por separado en prescripción, insolvencia y notificación."),
    dict(n="2211000308", cc="4252020", primary="ORLANDO PEREZ BAEZ", product="CRÉDITO PRODUCTIVO URBANO", initial=10000000, ledger_date="5 de diciembre de 2023", capital=10000000, interest=1645434, mora=4138116, cos=[], city=None, face_date="5 de diciembre de 2023", maturity="6 de julio de 2026", installments=18, face_capital=10000000, face_interest=1645434, rate="23,14393449 % E.A. [VALIDAR MODALIDAD FACIAL]", asset_hint=None, audit_alert="La mora histórica supera el 40 % del capital. Exigir memoria por día y periodo, pagos, tasa máxima, fecha única de exigibilidad y trazabilidad del llenado antes de pedir mandamiento."),
    dict(n="2315000024", cc="1000601898", primary="DANIEL RICARDO CALDERÓN CORREA", product="CRÉDITO PRODUCTIVO POPULAR URBANO", initial=6000000, ledger_date="17 de mayo de 2023", capital=4717256, interest=1490169, mora=1958089, cos=[("DIANA LORENA TORRES TORRES", "52778684")], city=None, face_date="17 de mayo de 2023", maturity="6 de julio de 2026", installments=24, face_capital=4717256, face_interest=1490169, rate="46,78467782 % E.A. [VALIDAR MODALIDAD Y LÍMITE POR PERIODO]", asset_hint="La solicitud histórica refiere actividad en OPTIMUS TENIS y a la codeudora como propietaria de locales; actualizar vínculo, pagador, titularidad y activos antes de pedir medida.", audit_alert="El capital facial coincide con el saldo histórico, no con el cargo inicial de $6.000.000, y la tasa exige control por cada periodo. Probar desembolso, llenado, pagos, exigibilidad y coordinar con el pagaré 2315000116, donde se repiten firmantes."),
    dict(n="2315000031", cc="23496918", primary="NUBIA ELSA CELIS GARCÍA", product="CRÉDITO PRODUCTIVO URBANO", initial=8000000, ledger_date="26 de mayo de 2023", capital=6798440, interest=2226101, mora=2818010, cos=[], city=None, face_date="26 de mayo de 2023", maturity="6 de julio de 2026", installments=24, face_capital=6798440, face_interest=2226101, rate="46,78467782 % E.A. [VALIDAR MODALIDAD Y LÍMITE POR PERIODO]", asset_hint="Vehículo de placa PBJ249 aparece únicamente como pista histórica; exigir RUNT/tránsito actual, propiedad, identificación técnica, gravámenes y avalúo.", audit_alert="El capital facial coincide con el saldo histórico y no con el cargo inicial de $8.000.000. Demostrar llenado, pagos, exigibilidad, memoria de interés y límite de tasa; la placa PBJ249 no habilita una cautela sin RUNT actual."),
    dict(n="2315000116", cc="52778684", primary="DIANA LORENA TORRES TORRES", product="CRÉDITO PRODUCTIVO URBANO", initial=10000000, ledger_date="23 de octubre de 2023", capital=6022667, interest=7934754, mora=287040, cos=[("HOLMES SÁNCHEZ QUIROGA", "79977583"), ("DANIEL RICARDO CALDERÓN CORREA", "1000601898")], city=None, face_date="23 de octubre de 2023", maturity="6 de julio de 2026", installments=48, face_capital=6022667, face_interest=7934754, rate="46,78 % E.A. [VALIDAR MODALIDAD, LÍMITE Y MEMORIA]", asset_hint="Motocicleta/vehículo de placa CJA93F y producto Bancolombia aparecen solo en soportes históricos. Actualizar propiedad, organismo, gravámenes, cuenta/producto exacto, titularidad y fecha de la fuente.", audit_alert="El capital facial coincide con el saldo histórico y no con el cargo inicial de $10.000.000; el interés facial de $7.934.754 y la mora no quedaron reproducidos. Existen dos compromisos internos de 2026 por $4.458.601 sin soporte fuente. No pedir esos rubros ni cautelas sobre CJA93F/Bancolombia hasta cerrar memoria, pagos, llenado y titularidad; coordinar con 2315000024."),
]

PRODUCT_PREFIX_FIX = {
    "1912000942", "1913000881", "1913000904", "2211000308", "2315000024", "2315000031",
    "2315000116",
}

# Correcciones puntuales preservadas como datos de control, sin inferir competencia.
for _case in CASES:
    if _case["n"] == "2016000382":
        _case["cos"] = [("SANDRA PATRICIA ÁLVAREZ M. [COMPLETAR — APELLIDO COMPLETO]", "1090387419")]
    elif _case["n"] in {"2211000173", "2211000308"}:
        _case["city"] = "Suesca (agencia facial; no acredita fuero territorial)"


ONES = ["CERO", "UNO", "DOS", "TRES", "CUATRO", "CINCO", "SEIS", "SIETE", "OCHO", "NUEVE"]
TEENS = {10: "DIEZ", 11: "ONCE", 12: "DOCE", 13: "TRECE", 14: "CATORCE", 15: "QUINCE", 16: "DIECISÉIS", 17: "DIECISIETE", 18: "DIECIOCHO", 19: "DIECINUEVE"}
TENS = {20: "VEINTE", 30: "TREINTA", 40: "CUARENTA", 50: "CINCUENTA", 60: "SESENTA", 70: "SETENTA", 80: "OCHENTA", 90: "NOVENTA"}
HUNDREDS = {100: "CIEN", 200: "DOSCIENTOS", 300: "TRESCIENTOS", 400: "CUATROCIENTOS", 500: "QUINIENTOS", 600: "SEISCIENTOS", 700: "SETECIENTOS", 800: "OCHOCIENTOS", 900: "NOVECIENTOS"}


def under_100(n: int) -> str:
    if n < 10:
        return ONES[n]
    if n < 20:
        return TEENS[n]
    if n < 30:
        special = {21: "VEINTIUNO", 22: "VEINTIDÓS", 23: "VEINTITRÉS", 24: "VEINTICUATRO", 25: "VEINTICINCO", 26: "VEINTISÉIS", 27: "VEINTISIETE", 28: "VEINTIOCHO", 29: "VEINTINUEVE"}
        return special.get(n, "VEINTE")
    ten = (n // 10) * 10
    unit = n % 10
    return TENS[ten] if unit == 0 else f"{TENS[ten]} Y {ONES[unit]}"


def under_1000(n: int) -> str:
    if n < 100:
        return under_100(n)
    hundred = (n // 100) * 100
    rest = n % 100
    prefix = "CIENTO" if hundred == 100 and rest else HUNDREDS[hundred]
    return prefix if not rest else f"{prefix} {under_100(rest)}"


def apocopate(text: str) -> str:
    if text.endswith("VEINTIUNO"):
        return text[:-9] + "VEINTIÚN"
    if text.endswith(" Y UNO"):
        return text[:-6] + " Y UN"
    if text.endswith(" UNO"):
        return text[:-4] + " UN"
    if text == "UNO":
        return "UN"
    return text


def number_words(n: int) -> str:
    n = int(n)
    if n < 1000:
        return under_1000(n)
    if n < 1_000_000:
        thousands, rest = divmod(n, 1000)
        prefix = "MIL" if thousands == 1 else f"{apocopate(number_words(thousands))} MIL"
        return prefix if not rest else f"{prefix} {number_words(rest)}"
    if n < 1_000_000_000:
        millions, rest = divmod(n, 1_000_000)
        prefix = "UN MILLÓN" if millions == 1 else f"{apocopate(number_words(millions))} MILLONES"
        return prefix if not rest else f"{prefix} {number_words(rest)}"
    raise ValueError(n)


def money(n: int) -> str:
    return f"${int(n):,}".replace(",", ".")


def money_words(n: int) -> str:
    n = int(n)
    words = apocopate(number_words(n))
    if n == 1:
        return f"UN PESO M/CTE ({money(n)})"
    connector = " DE" if n >= 1_000_000 and n % 1_000_000 == 0 else ""
    return f"{words}{connector} PESOS M/CTE ({money(n)})"


def normalize_slug(value: str) -> str:
    value = unicodedata.normalize("NFD", value)
    value = "".join(ch for ch in value if unicodedata.category(ch) != "Mn")
    value = re.sub(r"[^A-Za-z0-9]+", "_", value).strip("_")
    return value.upper()[:58]


def p(text: str, donor: int = 38, **kwargs):
    return base.p(donor, text, **kwargs)


def h(text: str, *, donor: int = 37, page_break_before: bool = False):
    return base.heading(text, donor=donor, page_break_before=page_break_before)


def parties(case: dict, include_roles: bool = True) -> str:
    role_marker = ", firmante [COMPLETAR — CALIDAD INDIVIDUAL ACREDITADA Y ALCANCE DE LA OBLIGACIÓN]" if include_roles else ""
    items = [f"{case['primary']}, C.C. No. {fmt_id(case['cc'])}{role_marker}"]
    for name, ident in case["cos"]:
        items.append(f"{name}, C.C. No. {fmt_id(ident)}{role_marker}")
    return "; y ".join(items)


def fmt_id(value: str) -> str:
    if value.startswith("["):
        return value
    digits = re.sub(r"\D", "", value)
    return f"{int(digits):,}".replace(",", ".") if digits else value


def product_label(case: dict) -> str:
    label = case["product"]
    return f"MICRO - {label}" if case["n"] in PRODUCT_PREFIX_FIX and not label.startswith("MICRO") else label


def facial_rate_text(case: dict) -> str:
    if not case.get("rate"):
        return "[COMPLETAR — TASA FACIAL LITERAL Y MODALIDAD]"
    literal_rate = re.match(r"\s*([0-9]+(?:,[0-9]+)?)\s*%", case["rate"])
    if not literal_rate:
        return "[COMPLETAR — TASA FACIAL LITERAL Y MODALIDAD]"
    return f"tasa facial {literal_rate.group(1)} % [COMPLETAR — MODALIDAD FACIAL; NO PRESUMIR E.A. O N.A.M.V.]"


def face_summary(case: dict) -> str:
    fields = []
    fields.append(case.get("face_date") or "[COMPLETAR — FECHA FACIAL DE OTORGAMIENTO]")
    if case.get("installments"):
        if case["n"] in MONTHLY_INSTALLMENT_CASES:
            fields.append(f"{case['installments']} cuotas mensuales")
        elif case["n"] == "1914000814":
            fields.append(f"{case['installments']} cuotas trimestrales")
        else:
            fields.append(f"{case['installments']} cuotas [COMPLETAR — PERIODICIDAD]")
    else:
        fields.append("[COMPLETAR — NÚMERO Y PERIODICIDAD DE CUOTAS]")
    fields.append(f"capital facial {money(case['face_capital'])}" if case.get("face_capital") is not None else "[COMPLETAR — CAPITAL FACIAL]")
    fields.append(f"interés de plazo facial {money(case['face_interest'])}" if case.get("face_interest") is not None else "[COMPLETAR — INTERÉS DE PLAZO FACIAL]")
    fields.append(facial_rate_text(case))
    fields.append(f"vencimiento {case['maturity']}" if case.get("maturity") else "[COMPLETAR — FECHA FACIAL DE VENCIMIENTO]")
    return ", ".join(fields)


def demand_specs(case: dict) -> list[dict]:
    total = case["capital"] + case["interest"] + case["mora"]
    city_hint = case.get("city")
    city = (
        f"[COMPLETAR — DESPACHO; PISTA HISTÓRICA: {city_hint}, VALIDAR FUERO ACTUAL]"
        if city_hint
        else "[COMPLETAR — CIUDAD Y DESPACHO SEGÚN DOMICILIO/LUGAR DE CUMPLIMIENTO ACREDITADO]"
    )
    signed_verb = "suscribió" if not case["cos"] else "suscribieron"
    audit_alert = case.get("audit_alert") or (
        "No se cerró una memoria que reproduzca capital, interés y mora ni la trazabilidad del diligenciamiento. "
        "Resolver exigibilidad, pagos, tasa, prescripción, insolvencia, competencia y canales con fuentes actuales."
    )
    codebtor_fact = ""
    if case["cos"]:
        codebtor_fact = " La calidad, solidaridad y alcance de cada firma deben confrontarse con todas las páginas del título y el negocio subyacente."
    else:
        codebtor_fact = " [CONFIRMAR EN TODAS LAS PÁGINAS QUE NO EXISTE CODEUDOR, AVALISTA U OTRO OBLIGADO]."
    return [
        p("PARA COMPLETAR Y REVISAR — NO RADICAR, NO FIRMAR Y NO NOTIFICAR", donor=7, bold=True, color=RED, size=14),
        p("Este documento contiene marcadores entre corchetes y cifras históricas con corte al 6 de julio de 2026. Solo podrá promoverse a versión para firma después de reemplazar todos los marcadores, actualizar el saldo y cerrar los controles de título, poder, exigibilidad, prescripción, insolvencia, notificación y cautela.", bold=True, color=RED),
        p("[COMPLETAR — CIUDAD], [COMPLETAR — FECHA REAL DE PRESENTACIÓN]", donor=0, color=RED),
        p(f"SEÑOR JUEZ CIVIL COMPETENTE DE {city} (REPARTO)", donor=1, bold=True, color=RED if "[" in city else None),
        p("E. S. D.", donor=2),
        p(f"REFERENCIA: DEMANDA EJECUTIVA — PAGARÉ No. {case['n']} — OBLIGACIÓN No. 10-{case['n']}.", donor=7),
        p(f"DEMANDANTE: {LEGAL_NAME}, NIT 890.981.459-4. [CONFRONTAR RAZÓN SOCIAL CON CERTIFICADO VIGENTE].", color=RED),
        p(f"DEMANDADOS: {parties(case)}.", color=RED),
        p("APODERADA: [COMPLETAR — NOMBRE, C.C., T.P., CORREO RNA Y CADENA DE PERSONERÍA VÁLIDA].", bold=True, color=RED),
        p("[DECISIÓN DEL ABOGADO — CONSERVAR UNA SOLA RUTA ACREDITADA: (A) poder directo de CREARCOOP a la abogada; o (B) poder de CREARCOOP a ABOGADOS EN COLOMBIA S.A.S. y acto válido de representación, sustitución o designación de la abogada. Aportar mensaje de datos, correos, certificados y facultades de la ruta elegida].", color=RED),
        p(f"[COMPLETAR — NOMBRE Y RUTA DE PERSONERÍA], actuando judicialmente por {LEGAL_NAME}, presenta demanda ejecutiva contra {parties(case, include_roles=False)} para obtener el pago de la parte insoluta que resulte clara, expresa y exigible del pagaré No. {case['n']}.", color=RED),
        h("I. HECHOS"),
        p(f"PRIMERO. {parties(case)} {signed_verb} a la orden de CREARCOOP el pagaré No. {case['n']}, asociado en el extracto a la obligación No. 10-{case['n']}.{codebtor_fact}", color=RED),
        p(f"SEGUNDO. El extracto registra como primer asiento de apertura o cargo del crédito, el {case['ledger_date']}, un capital de {money_words(case['initial'])}, bajo la modalidad literal «{product_label(case)}». Ese asiento no reemplaza el comprobante de entrega: [COMPLETAR — FECHA, MONTO NETO Y SOPORTE DEL DESEMBOLSO O DE LA REFINANCIACIÓN].", color=RED),
        p(f"TERCERO. La primera página del pagaré presenta, según la lectura disponible, los siguientes datos: {face_summary(case)}. [CONFRONTAR CON ORIGINAL FÍSICO, PLAN DE PAGOS Y CARTA DE INSTRUCCIONES; CORREGIR CUALQUIER LECTURA].", color=RED),
        p(f"CUARTO. Las páginas entregadas contienen instrucciones para el diligenciamiento de espacios en blanco. [COMPLETAR — FECHA MATERIAL DE LLENADO, PERSONA QUE LO REALIZÓ, FUENTE DE CADA CAMPO Y EXPLICACIÓN DE CUALQUIER DIFERENCIA ENTRE INSTRUCCIONES INCORPORADAS Y CARTA SEPARADA].", color=RED),
        p(f"QUINTO. El extracto con corte al {EXTRACT_CUT} informa capital por {money_words(case['capital'])}, intereses remuneratorios o de plazo por {money_words(case['interest'])} e intereses moratorios por {money_words(case['mora'])}, para un total histórico de {money_words(total)}."),
        p("SEXTO. [COMPLETAR — IDENTIFICAR UNA SOLA FECHA Y CAUSA DE EXIGIBILIDAD: vencimiento ordinario, aceleración o incumplimiento de acuerdo; aportar cláusula, comunicación, ejercicio, entrega y efecto frente a cada obligado]. Si no puede acreditarse, eliminar toda afirmación de exigibilidad y no radicar.", color=RED),
        p("SÉPTIMO. [COMPLETAR — RELACIONAR TODOS LOS PAGOS, compromisos, acuerdos, reestructuraciones, novaciones, condonaciones, seguros, subrogaciones, movimientos internos y sus efectos]. La cifra histórica del extracto no debe usarse sin reconciliación y actualización.", color=RED),
        p("OCTAVO. [COMPLETAR — CERTIFICACIÓN VERAZ DE EXISTENCIA, TENENCIA, UBICACIÓN, CUSTODIO, NO CIRCULACIÓN Y DISPONIBILIDAD DE EXHIBICIÓN DEL ORIGINAL].", color=RED),
        p("NOVENO. [COMPLETAR — DOMICILIO ACTUAL, dirección física, canal digital y fuente de obtención de cada dato de cada demandado; consultas de fallecimiento, sucesión, insolvencia y procesos paralelos].", color=RED),
        p(f"ALERTA ESPECÍFICA DEL CASO — {audit_alert} [RESOLVER Y DOCUMENTAR; NO TRASLADAR ESTA ALERTA A LA VERSIÓN PARA FIRMA].", bold=True, color=RED),
        p(f"TRAZABILIDAD INTERNA — Título y firmantes: «PAGARÉ {case['n']}.pdf», especialmente pp. 1–2. Saldos y movimientos: «EXTRACTO {case['cc']}.PDF», revisión integral. Identidad, solicitud, contrato y pistas patrimoniales: «CC {case['cc']} PAGARE {case['n']}.pdf». [COMPLETAR EN EL ÍNDICE LA PÁGINA EXACTA DE CADA PROPOSICIÓN Y ELIMINAR ESTA NOTA EN LA COPIA PARA FIRMA].", color=AMBER),
        h("II. PRETENSIONES — CIFRAS PROVISIONALES SUJETAS A CIERRE"),
        p("La siguiente formulación solo podrá conservarse si la certificación contable actual, el pagaré y la exigibilidad validada coinciden:"),
        p(f"PRIMERA. Por {money_words(case['capital'])}, correspondiente al capital insoluto histórico informado al {EXTRACT_CUT}, [REEMPLAZAR POR CAPITAL CERTIFICADO AL DÍA DE PRESENTACIÓN].", color=RED),
        p(f"SEGUNDA. Por {money_words(case['interest'])}, correspondiente a intereses remuneratorios o de plazo informados al {EXTRACT_CUT}, únicamente si una memoria anexa demuestra base, tasa, periodos, causación, pagos y ausencia de interés futuro o duplicado. [SI NO SE REPRODUCE, ELIMINAR ESTA PRETENSIÓN].", color=RED),
        p(f"TERCERA. Por intereses moratorios, sin superposición, anatocismo ni mora sobre intereses, mediante los subliterales que resulten acreditados: (a) [CONSERVAR SOLO SI LA MEMORIA LO REPRODUCE] los causados hasta [COMPLETAR — CORTE ACTUAL] sobre el capital de cada cuota vencida o sobre el saldo válidamente acelerado, con base, fecha inicial, pagos, días y tasa por periodo; el resumen histórico informa {money_words(case['mora'])} al {EXTRACT_CUT}, solo como control; y (b) [CONSERVAR SOLO DESDE EL CORTE ANTERIOR, SIN SOLAPAR] los que se causen después sobre el capital entonces vencido o válidamente acelerado hasta el pago, a la tasa pactada sin exceder la máxima legal. [ELIMINAR TODO SUBLITERAL O PERIODO NO REPRODUCIBLE].", color=RED),
        p("CUARTA. Por costas y agencias en derecho."),
        p("No se solicita mora sobre intereses ni doble cobro de periodos o bases. El abogado revisor deberá eliminar cualquier pretensión que no esté respaldada por una memoria reproducible."),
        h("III. FUNDAMENTOS DE DERECHO"),
        p("Artículos 17, 25, 26, 28, 74, 75, 82, 84, 90, 94, 422, 424, 430, 431 y concordantes del Código General del Proceso; artículos 619, 621, 622, 624, 709, 710, 782, 789 y 884 del Código de Comercio; Ley 2213 de 2022; Ley 2220 de 2022; Ley 2445 de 2025; Ley 2540 de 2025 y normas aplicables al crédito y a la tasa en cada periodo."),
        p("[COMPLETAR — VERIFICAR EN EL CONTRATO Y ANEXOS SI EXISTE PACTO ARBITRAL EJECUTIVO; definir jurisdicción ordinaria o arbitral antes de conservar este apartado].", color=RED),
        h("IV. COMPETENCIA, TRÁMITE Y CUANTÍA"),
        p(f"[COMPLETAR — JUEZ, CIUDAD, FACTOR TERRITORIAL Y SOPORTE]. La competencia deberá fundarse en el domicilio actual probado de uno de los demandados o en el lugar de cumplimiento jurídicamente aplicable; no en una cláusula de domicilio judicial. El trámite será ejecutivo y la categoría de cuantía deberá actualizarse con el SMLMV vigente al presentar.", color=RED),
        p(f"La cuantía histórica asciende a {money_words(total)}, correspondiente a capital, intereses de plazo y mora informados al {EXTRACT_CUT}, sin incluir intereses futuros ni costas. [ACTUALIZAR Y RECLASIFICAR CUANTÍA].", color=RED),
        h("V. PRUEBAS Y ANEXOS"),
        p(f"1. Copia digital del pagaré No. {case['n']} y de las instrucciones incorporadas o separadas que realmente integren el título. [COMPLETAR — CERTIFICAR EXISTENCIA, TENENCIA, UBICACIÓN, CUSTODIA, NO CIRCULACIÓN Y DISPONIBILIDAD DE EXHIBICIÓN DEL ORIGINAL].", color=RED),
        p(f"2. Extracto de la obligación No. 10-{case['n']} y liquidación/memoria actualizada y reproducible."),
        p("3. Solicitud, contrato, reglamento, desembolso, plan y modificaciones completas."),
        p("4. Pagos, acuerdos, compromisos, reestructuraciones, comunicaciones de aceleración y comprobantes de entrega."),
        p("5. Identificaciones y documentos de domicilio/canales de cada obligado."),
        p("6. Certificado vigente de CREARCOOP y documentos completos de la ruta de personería seleccionada: poder(es), mensaje(s) de datos, correos, certificado de la sociedad apoderada cuando intervenga, sustitución/designación y documentos de la abogada.", color=RED),
        p("7. Certificaciones y consultas de original/custodia, arbitraje, insolvencia, sucesión y procesos paralelos."),
        p("[ELIMINAR TODO ANEXO QUE NO EXISTA; AGREGAR NOMBRE EXACTO, FECHA, NÚMERO DE PÁGINAS Y ORDEN DIGITAL].", color=RED),
        h("VI. MEDIDAS CAUTELARES, CONCILIACIÓN Y ENVÍO"),
        p("La demanda se proyecta con solicitud cautelar previa en escrito separado. Solo conservar esta fórmula si el escrito cautelar final contiene una medida individualizada y soportada: «La demanda se presenta con solicitud de medidas cautelares previas en escrito separado. Por ello no se agotó conciliación prejudicial y no se remitió simultáneamente copia de la demanda y sus anexos al demandado, sin perjuicio de la posterior notificación personal del mandamiento de pago». [SI NO HAY CAUTELA REAL, REVISAR Y CAMBIAR LA RUTA].", color=RED),
        h("VII. NOTIFICACIONES"),
        p(f"Demandante: {LEGAL_NAME}, NIT 890.981.459-4. [COMPLETAR — RAZÓN SOCIAL, DIRECCIÓN Y CORREO JUDICIAL SEGÚN CERTIFICADO VIGENTE].", color=RED),
        p("Apoderada: [COMPLETAR — NOMBRE, C.C., T.P., DIRECCIÓN Y CORREO COINCIDENTE CON RNA].", color=RED),
        p(f"Demandados: {parties(case, include_roles=False)}. [COMPLETAR POR CADA PERSONA — domicilio, dirección física, teléfono, correo, fuente y fecha de obtención; formular juramento del artículo 8 de la Ley 2213 solo con soporte].", color=RED),
        h("VIII. CONTROL HUMANO OBLIGATORIO"),
        p("Cierre obligatorio: cero corchetes; saldo y pagos al día; todos los gates del protocolo acreditados; revisión final como juez, defensa y consistencia."),
        p("Atentamente,"),
        p("[COMPLETAR — NOMBRE DE LA ABOGADA CON PERSONERÍA]", bold=True, color=RED),
        p("[COMPLETAR — C.C., T.P., CORREO RNA Y CALIDAD]", color=RED),
    ]


def caution_specs(case: dict) -> list[dict]:
    total = case["capital"] + case["interest"] + case["mora"]
    general_reference = total * 2
    deposit_reference = int(
        (Decimal(total) * Decimal("1.5")).quantize(Decimal("1"), rounding=ROUND_CEILING)
    )
    hint = case.get("asset_hint") or "No se encontró en la auditoría una fuente actual de activo individualizado. [COMPLETAR — IDENTIFICAR ACTIVO ACTUAL Y APORTAR FUENTE]."
    return [
        p("PARA COMPLETAR Y REVISAR — NO RADICAR, NO FIRMAR Y NO OFICIAR", donor=7, bold=True, color=RED, size=14),
        p("Este escrito contiene módulos alternativos. El abogado debe conservar únicamente la medida sustentada por prueba actual, eliminar todas las demás y reemplazar cada marcador entre corchetes.", donor=17, bold=True, color=RED),
        p("[COMPLETAR — CIUDAD], [COMPLETAR — FECHA REAL DE PRESENTACIÓN]", donor=0, color=RED),
        p("SEÑOR JUEZ CIVIL COMPETENTE DE [COMPLETAR — CIUDAD Y DESPACHO] (REPARTO)", donor=1, bold=True, color=RED),
        p("E. S. D.", donor=2),
        p(f"REFERENCIA: SOLICITUD DE MEDIDA CAUTELAR PREVIA — PAGARÉ No. {case['n']} — OBLIGACIÓN No. 10-{case['n']}.", donor=7),
        p(f"DEMANDANTE: {LEGAL_NAME}, NIT 890.981.459-4. [CONFRONTAR RAZÓN SOCIAL CON CERTIFICADO VIGENTE].", donor=8, color=RED),
        p(f"DEMANDADOS: {parties(case)}.", donor=10, color=RED),
        p("APODERADA: [COMPLETAR — NOMBRE, C.C., T.P., CORREO RNA Y CADENA DE PERSONERÍA VÁLIDA].", donor=12, bold=True, color=RED),
        p("[DECISIÓN DEL ABOGADO — CONSERVAR UNA SOLA RUTA ACREDITADA: (A) poder directo de CREARCOOP a la abogada; o (B) poder de CREARCOOP a ABOGADOS EN COLOMBIA S.A.S. y representación, sustitución o designación válida de la abogada].", donor=14, color=RED),
        p(f"[COMPLETAR — APODERADA Y RUTA DE PERSONERÍA], actuando por {LEGAL_NAME}, dentro del proceso ejecutivo fundado en el pagaré No. {case['n']}, solicita únicamente la medida o medidas que permanezcan después de la depuración de este escrito.", donor=14, color=RED),
        h("I. BASE PROVISIONAL Y PROPORCIONALIDAD", donor=17),
        p(f"El extracto histórico al {EXTRACT_CUT} informa capital de {money(case['capital'])}, intereses de plazo de {money(case['interest'])} y mora de {money(case['mora'])}, para un total de {money(total)}. Como controles de trabajo —sin costas estimadas—, el doble equivale a {money(general_reference)} para revisar el límite general del artículo 599 del CGP, mientras el 150 % equivale a {money(deposit_reference)} —calculado con Decimal y redondeado al peso superior cuando arroja fracción— como referencia incompleta para el límite especial de depósitos del artículo 593.10. Deben actualizarse crédito, intereses, costas, valor del bien y límite aplicable; ninguna referencia se solicita automáticamente.", donor=17),
        p(f"Pista documental: {hint}", donor=17, color=AMBER),
        p(f"Trazabilidad interna: «PAGARÉ {case['n']}.pdf»; «EXTRACTO {case['cc']}.PDF»; «CC {case['cc']} PAGARE {case['n']}.pdf». [INDIZAR PÁGINA EXACTA, FECHA Y VIGENCIA DEL ACTIVO; ELIMINAR ESTA NOTA EN LA COPIA PARA FIRMA].", donor=17, color=AMBER),
        p("[COMPLETAR — EXPLICAR necesidad, idoneidad, proporcionalidad, titular demandado, fuente actual, gravámenes, embargos preferentes, inembargabilidad y límite solicitado].", donor=17, color=RED),
        h("II. MÓDULOS DE MEDIDA — CONSERVAR SOLO LOS SOPORTADOS", donor=17),
        p("MÓDULO A — SALARIO U HONORARIOS", donor=19, bold=True, color=RED),
        p("[CONSERVAR SOLO CON PRUEBA ACTUAL] Decretar, según la naturaleza acreditada del ingreso, el embargo y retención de la porción legalmente embargable de los salarios o emolumentos que [DEMANDADO Y C.C.] percibe de [EMPLEADOR/PAGADOR EXACTO, NIT, DIRECCIÓN Y CORREO OFICIAL], conforme a [CERTIFICADO O FUENTE DE FECHA]. Si se trata de honorarios, justificar su régimen y no aplicar automáticamente el porcentaje salarial. Indicar porcentaje o suma concreta, mínimo vital, preferencias y límite global. [ELIMINAR SI NO HAY SOPORTE].", donor=24, color=RED),
        p("MÓDULO B — MESADA PENSIONAL", donor=19, bold=True, color=RED),
        p("[CONSERVAR SOLO CON PRUEBA ACTUAL] Decretar el embargo dentro del límite legal de la mesada neta de [DEMANDADO Y C.C.], pagada por [ENTIDAD EXACTA Y CANAL], acreditada mediante [DOCUMENTO, FECHA, VALOR NETO Y DEDUCCIONES]. Motivar porcentaje concreto, mínimo vital y preferencias. No oficiar indiscriminadamente a todos los fondos. [ELIMINAR SI NO HAY SOPORTE].", donor=24, color=RED),
        p("MÓDULO C — PRODUCTO FINANCIERO INDIVIDUALIZADO", donor=19, bold=True, color=RED),
        p("[CONSERVAR SOLO CON FUENTE ACTUAL] Decretar el embargo y retención de los dineros de titularidad de [DEMANDADO Y C.C.] en [ENTIDAD EXACTA], producto [TIPO Y ÚLTIMOS CUATRO DÍGITOS], conocido por [FUENTE Y FECHA], hasta [LÍMITE NUMÉRICO]. Respetar la inembargabilidad aplicable y excluir depósitos de terceros. No sustituir por una lista general de bancos. [ELIMINAR SI NO HAY SOPORTE].", donor=24, color=RED),
        p("MÓDULO D — INMUEBLE", donor=19, bold=True, color=RED),
        p("[CONSERVAR SOLO CON CERTIFICADO ACTUAL] Decretar el embargo del derecho de dominio de [DEMANDADO Y C.C.] sobre el inmueble matrícula [NÚMERO], ORIP [OFICINA], dirección [DIRECCIÓN], porcentaje [●], conforme al certificado de tradición de [FECHA/CÓDIGO]. Comunicar por el canal oficial de la ORIP; solicitar secuestro únicamente después de inscrito el embargo. [ELIMINAR SI NO HAY SOPORTE].", donor=24, color=RED),
        p("MÓDULO E — VEHÍCULO", donor=19, bold=True, color=RED),
        p("[CONSERVAR SOLO CON RUNT/TRÁNSITO ACTUAL] Decretar el embargo del vehículo placa [PLACA], clase [●], marca [●], línea [●], modelo [●], VIN/chasis/motor [●], registrado a nombre de [DEMANDADO Y C.C.] en [ORGANISMO], según certificado de [FECHA/CÓDIGO]. Comunicar a la autoridad competente y solicitar secuestro/aprehensión solo después de inscrito el embargo y definida la ruta legal. [ELIMINAR SI NO HAY SOPORTE].", donor=24, color=RED),
        p("MÓDULO F — CRÉDITO, DERECHO ECONÓMICO O REMANENTE", donor=19, bold=True, color=RED),
        p("[CONSERVAR SOLO CON INDIVIDUALIZACIÓN] Decretar el embargo del crédito o derecho que [TERCERO EXACTO, NIT Y CANAL] adeuda a [DEMANDADO Y C.C.] por [CONTRATO/FACTURA/CAUSA, FECHA, MONTO Y VENCIMIENTO], o del remanente dentro del proceso [JUZGADO, RADICADO, PARTES Y ESTADO], hasta [LÍMITE]. [ELIMINAR SI NO HAY SOPORTE].", donor=24, color=RED),
        h("III. SOLICITUDES FINALES", donor=17),
        p("PRIMERA. Decretar exclusivamente la medida o medidas individualizadas que hayan quedado en la sección II después de eliminar los módulos no soportados.", donor=39),
        p(f"SEGUNDA. Fijar como límite [COMPLETAR — LÍMITE ACTUAL MOTIVADO SEGÚN EL ACTIVO]. Controles internos históricos sin costas: {money(general_reference)} para el techo general de revisión y {money(deposit_reference)} para la referencia especial de depósitos; aplicar la regla pertinente y el valor probado del bien, sin convertir estos máximos en una solicitud automática.", donor=39, color=RED),
        p("TERCERA. Librar únicamente los oficios dirigidos a la entidad, autoridad o tercero plenamente identificado, por su canal oficial verificado.", donor=39),
        p("CUARTA. Tramitar la solicitud por separado y, si se decreta, cumplir inmediatamente la medida antes de notificar a la parte contraria, conforme al artículo 298 del Código General del Proceso, sin pedir una reserva distinta de la prevista por la ley.", donor=39),
        h("IV. FUNDAMENTOS", donor=17),
        p("Artículos 298, 593, 594, 599, 600 y 601 del Código General del Proceso; Ley 2213 de 2022 y normas específicas del activo finalmente seleccionado. [AGREGAR SOLO LA NORMA ESPECIAL PERTINENTE Y VERIFICADA].", donor=17, color=RED),
        h("V. ANEXOS ESPECÍFICOS", donor=17),
        p("1. Liquidación actual y cálculo del límite."),
        p("2. Certificado o fuente actual de titularidad e individualización del activo."),
        p("3. Avalúo, gravámenes, preferencias e inembargabilidad, cuando correspondan."),
        p("4. Poder y personería aportados con la demanda."),
        p("[ENUMERAR NOMBRES EXACTOS, FECHA Y PÁGINAS; ELIMINAR ANEXOS INEXISTENTES].", color=RED),
        h("VI. CONTROL HUMANO OBLIGATORIO", donor=17),
        p("Buscar y reemplazar todos los corchetes; conservar una sola opción por activo probado; actualizar titularidad, saldo, límite y canales el día de presentar; verificar medidas previas e insolvencia; eliminar toda búsqueda indiscriminada y revisar proporcionalidad como juez y contraparte."),
        p("Atentamente,"),
        p("[COMPLETAR — NOMBRE DE LA ABOGADA CON PERSONERÍA]", bold=True, color=RED),
        p("[COMPLETAR — C.C., T.P., CORREO RNA Y CALIDAD]", color=RED),
    ]


def readme_specs() -> list[dict]:
    return [
        p("GUÍA DE USO — 26 DEMANDAS Y 26 CAUTELARES PARA COMPLETAR", donor=7, bold=True, size=16, color=NAVY),
        p("Estado jurídico del paquete: DOCUMENTOS DE TRABAJO — NO RADICAR", bold=True, color=RED, size=13),
        p("La usuaria autorizó dejar espacios identificados para que varios abogados completen la información faltante. Por eso este paquete no constituye una entrega final ni apta para firma. Conserva el membrete y la geometría de MODELO.docx y MODELO MEDIDAS.docx; las plantillas canónicas v2.0 exigidas por el protocolo no fueron localizadas."),
        h("1. CONTENIDO"),
        p("Se generaron 52 Word: una demanda y una solicitud cautelar por cada uno de los 26 pagarés correctos. Cada carpeta identifica el pagaré y el primer firmante de control asociado al extracto, sin cerrar por ello su calidad jurídica individual. La matriz CSV contiene cifras, fuentes y conteo de marcadores."),
        p("Regla de selección aplicada: se tomó exclusivamente el PDF cuyo nombre inicia exactamente con «PAGARÉ » seguido del número. El PDF homónimo que solo lleva el número o no inicia con «PAGARÉ» quedó excluido de la lectura facial."),
        h("2. SIGNIFICADO DE LOS MARCADORES"),
        p("[COMPLETAR — ...]: dato o soporte que no puede afirmarse con las fuentes actuales. Debe sustituirse por información evidenciada, nunca por inferencia."),
        p("[DECISIÓN DEL ABOGADO — ...] / [CONSERVAR SOLO CON ...] / [ELIMINAR SI ...]: rutas o módulos alternativos. El abogado debe dejar una sola opción respaldada y borrar por completo las demás."),
        p("Texto rojo: bloqueo de cierre. Texto ámbar: pista histórica que requiere actualización; no prueba el hecho actual."),
        h("3. REGLA DE CIERRE"),
        p("Ningún archivo puede perder la marca NO RADICAR hasta: (i) cero corchetes; (ii) poder y correo RNA; (iii) original, custodia y llenado; (iv) contrato y arbitraje; (v) una sola exigibilidad; (vi) saldo y pagos del día; (vii) prescripción; (viii) insolvencia/sucesión/procesos; (ix) competencia y canales; (x) cautela individualizada; y (xi) revisión y firma humana."),
        h("4. CIFRAS"),
        p(f"Todas las cifras incorporadas provienen del resumen de extractos con corte al {EXTRACT_CUT}. Son históricas y provisionales. Los rubros de interés y mora no se vuelven reproducibles por aparecer en el resumen; necesitan memoria de cálculo, movimientos y control de tasa. Deben confirmarse pagos justo antes de presentar."),
        h("5. MEDIDAS CAUTELARES"),
        p("Cada cautelar contiene seis módulos posibles. No deben presentarse juntos por defecto. Conservar solo empleador/pagador, banco/producto, matrícula, vehículo, crédito o remanente que esté identificado mediante fuente actual; borrar el resto. Se prohíbe convertirlos en solicitudes contra todos los bancos, fondos, inmuebles o vehículos."),
        h("6. FUENTES RECTORAS"),
        p("Protocolo local: 00_LEER_PRIMERO_PROTOCOLO_CIVILISTA_CREARCOOP_2026.txt. Fuentes oficiales: Secretaría del Senado — Código General del Proceso y Código de Comercio; Ley 2213 de 2022; Ley 2445 de 2025; Ley 2540 de 2025; Superintendencia Financiera para tasas por periodo; y registros oficiales aplicables a cada activo."),
        h("7. CONTROL AUTOMÁTICO"),
        p("El manifiesto SHA-256 permite verificar que los archivos no cambiaron desde la generación. La matriz lista el número de marcadores por documento. Una cifra de marcadores igual a cero no reemplaza la revisión jurídica ni autoriza por sí sola la radicación."),
        h("8. HALLAZGOS TRANSVERSALES DE CIERRE"),
        p("En numerosos casos el capital facial reproduce el capital inicial o el saldo del extracto, y el interés facial coincide con el saldo de interés al corte. También hay vencimientos faciales que no coinciden con el final del plan, movimientos «CO» sin acuerdo fuente y reconstrucciones de interés/mora que no cuadran con el resumen. Estas coincidencias o brechas no prueban por sí solas el llenado, la exigibilidad ni el saldo; la alerta específica de cada demanda debe resolverse con memoria y soporte."),
        p("Los domicilios, empleadores, pagadores, establecimientos, cuentas, vehículos e inmuebles encontrados son pistas históricas. Ninguno debe tratarse como vigente sin consulta oficial actual. Los expedientes con obligados repetidos deben coordinarse para evitar pretensiones, pagos o cautelas duplicadas."),
    ]


def make_docx(template: Path, output: Path, specs: list[dict], title: str) -> None:
    base.make_docx(template, output, specs, title)


def xml_text(docx_path: Path) -> str:
    with ZipFile(docx_path) as zf:
        data = zf.read("word/document.xml").decode("utf-8", "ignore")
    return re.sub(r"<[^>]+>", " ", data)


def file_sha(path: Path) -> str:
    h = sha256()
    with path.open("rb") as fh:
        for chunk in iter(lambda: fh.read(1024 * 1024), b""):
            h.update(chunk)
    return h.hexdigest()


def generate() -> None:
    if OUT.exists():
        shutil.rmtree(OUT)
    CONTROL.mkdir(parents=True)
    make_docx(DEMAND_TEMPLATE, CONTROL / "00_LEAME_PRIMERO_NO_RADICAR.docx", readme_specs(), "Guía paquete 26 casos — no radicar")

    matrix_rows = []
    produced = []
    for index, case in enumerate(CASES, 1):
        folder = OUT / f"{index:02d}_{case['n']}_{normalize_slug(case['primary'])}"
        folder.mkdir()
        demand = folder / f"01_DEMANDA_{case['n']}_PARA_COMPLETAR_NO_RADICAR.docx"
        caution = folder / f"02_CAUTELAR_{case['n']}_PARA_COMPLETAR_NO_RADICAR.docx"
        make_docx(DEMAND_TEMPLATE, demand, demand_specs(case), f"Demanda {case['n']} — para completar — no radicar")
        make_docx(CAUTION_TEMPLATE, caution, caution_specs(case), f"Cautelar {case['n']} — para completar — no radicar")
        produced.extend([demand, caution])
        demand_text = xml_text(demand)
        caution_text = xml_text(caution)
        total = case["capital"] + case["interest"] + case["mora"]
        matrix_rows.append({
            "orden": index,
            "pagare": case["n"],
            "primer_firmante_control": case["primary"],
            "cc_primer_firmante": case["cc"],
            "otros_firmantes": " | ".join(f"{n} — {i}" for n, i in case["cos"]) or "CONFIRMAR AUSENCIA",
            "producto_extracto": product_label(case),
            "corte_extracto": EXTRACT_CUT_SHORT,
            "ciudad_pista_historica": case.get("city") or "NO VERIFICADA",
            "otorgamiento_facial": case.get("face_date") or "COMPLETAR",
            "vencimiento_facial": case.get("maturity") or "COMPLETAR",
            "cuotas_faciales_plan": case.get("installments") if case.get("installments") is not None else "COMPLETAR",
            "periodicidad": "MENSUAL" if case["n"] in MONTHLY_INSTALLMENT_CASES else ("TRIMESTRAL" if case["n"] == "1914000814" else "COMPLETAR"),
            "capital_facial": case.get("face_capital") if case.get("face_capital") is not None else "COMPLETAR",
            "interes_facial": case.get("face_interest") if case.get("face_interest") is not None else "COMPLETAR",
            "tasa_facial": facial_rate_text(case),
            "capital_historico": case["capital"],
            "interes_historico": case["interest"],
            "mora_historica": case["mora"],
            "total_historico": total,
            "alerta_especifica": case.get("audit_alert") or "CERRAR MEMORIA, LLENADO, EXIGIBILIDAD Y GATES",
            "marcadores_demanda": demand_text.count("["),
            "marcadores_cautelar": caution_text.count("["),
            "pagare_fuente": str(ROOT / f"PAGARÉ {case['n']}.pdf"),
            "extracto_fuente": f"/code/wetransfers_20260715/WETRANSFERS/wetransfer_extractos-sandra-duque-activos_2026-07-06_1943/EXTRACTO {case['cc']}.PDF",
            "soporte_fuente": f"/code/wetransfers_20260715/WETRANSFERS/CC {case['cc']} PAGARE {case['n']}.pdf",
            "estado": "PARA COMPLETAR — NO RADICAR",
        })

    matrix = CONTROL / "01_MATRIZ_CONTROL_26_CASOS.csv"
    with matrix.open("w", newline="", encoding="utf-8-sig") as fh:
        writer = csv.DictWriter(fh, fieldnames=list(matrix_rows[0]))
        writer.writeheader()
        writer.writerows(matrix_rows)

    manifest = CONTROL / "02_MANIFIESTO_SHA256.txt"
    all_files = sorted([CONTROL / "00_LEAME_PRIMERO_NO_RADICAR.docx", matrix] + produced)
    manifest.write_text("\n".join(f"{file_sha(path)}  {path.relative_to(OUT)}" for path in all_files) + "\n", encoding="utf-8")

    zip_path = ROOT / "PAQUETE_52_WORD_PARA_COMPLETAR_2026-07-21.zip"
    if zip_path.exists():
        zip_path.unlink()
    with ZipFile(zip_path, "w", compression=ZIP_DEFLATED) as zf:
        for path in sorted(OUT.rglob("*")):
            if path.is_file():
                zf.write(path, Path(OUT.name) / path.relative_to(OUT))

    print(f"OUT={OUT}")
    print(f"DOCX={len(produced) + 1}")
    print(f"CASES={len(CASES)}")
    print(f"ZIP={zip_path}")


if __name__ == "__main__":
    generate()
