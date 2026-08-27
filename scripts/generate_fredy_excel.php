<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

const NAVY = '1F4E78';
const BLUE = '5B9BD5';
const LIGHT_BLUE = 'D9EAF7';
const RED = 'C00000';
const LIGHT_RED = 'FCE4D6';
const ORANGE = 'F4B183';
const LIGHT_ORANGE = 'FCE4D6';
const YELLOW = 'FFE699';
const LIGHT_YELLOW = 'FFF2CC';
const GREEN = '548235';
const LIGHT_GREEN = 'E2F0D9';
const GREY = 'E7E6E6';
const LIGHT_GREY = 'F2F2F2';
const WHITE = 'FFFFFF';
const DARK = '1F1F1F';

$fredyUserId = 58;
$auditDate = '8 de agosto de 2026';
$outputPath = __DIR__ . '/../Reporte_Auditoria_Pagares_Fredy_Bonfante_2026-08-08.xlsx';

$cases = DB::table('casos as c')
    ->leftJoin('personas as p', 'p.id', '=', 'c.deudor_id')
    ->leftJoin('cooperativas as coop', 'coop.id', '=', 'c.cooperativa_id')
    ->whereNull('c.deleted_at')
    ->where(function ($query) use ($fredyUserId): void {
        $query->where('c.user_id', $fredyUserId)
            ->orWhereExists(function ($subQuery) use ($fredyUserId): void {
                $subQuery->selectRaw('1')
                    ->from('caso_user as cu')
                    ->whereColumn('cu.caso_id', 'c.id')
                    ->where('cu.user_id', $fredyUserId);
            });
    })
    ->select([
        'c.id',
        'c.referencia_credito',
        'c.radicado',
        'c.estado',
        'c.estado_proceso',
        'c.link_drive',
        'p.nombre_completo as deudor',
        'p.numero_documento as cedula',
        'coop.nombre as cooperativa',
    ])
    ->orderBy('c.id')
    ->get();

if ($cases->count() !== 125) {
    throw new RuntimeException("Se esperaban 125 casos de Fredy y se encontraron {$cases->count()}.");
}

$confirmedIds = [
    381, 382, 383, 384, 385, 389, 390, 391, 392, 393,
    394, 395, 396, 397, 398, 399, 400, 401, 402, 403,
    404, 405, 407, 408, 409, 410, 411, 412, 413, 414,
    415, 416, 417, 419, 420, 421, 422, 423, 510,
];

$emptyFolderIds = [
    438, 440, 442, 460, 465, 475, 476, 480, 483, 487, 489, 493, 495, 504,
];

$noDriveIds = [519];

$confirmedLookup = array_fill_keys($confirmedIds, true);
$emptyFolderLookup = array_fill_keys($emptyFolderIds, true);
$noDriveLookup = array_fill_keys($noDriveIds, true);

$confirmedCases = [];
$requestCases = [];

foreach ($cases as $case) {
    if (isset($confirmedLookup[$case->id])) {
        $case->audit_result = 'PAGARÉ CONFIRMADO';
        $case->evidence = $case->id === 510
            ? 'Pagaré No. 242000214, cláusulas, firmas/huellas y carta de instrucciones.'
            : 'Pagaré explícito CREARCOOP verificado visualmente: encabezado, número, cláusulas y firmantes.';
        $confirmedCases[] = $case;
        continue;
    }

    if (isset($noDriveLookup[$case->id])) {
        $case->priority_order = 1;
        $case->priority = 'URGENTE';
        $case->finding = 'SIN ENLACE DRIVE';
        $case->action = 'Crear o enlazar el expediente y solicitar/cargar el pagaré.';
    } elseif (isset($emptyFolderLookup[$case->id])) {
        $case->priority_order = 2;
        $case->priority = 'ALTA';
        $case->finding = 'CARPETA DRIVE VACÍA';
        $case->action = 'Solicitar el pagaré y cargar los documentos del expediente.';
    } else {
        $case->priority_order = 3;
        $case->priority = 'REQUERIDA';
        $case->finding = 'ARCHIVOS REVISADOS, SIN PAGARÉ';
        $case->action = 'Solicitar y cargar el pagaré explícito del crédito.';
    }

    $requestCases[] = $case;
}

usort($requestCases, static function (object $left, object $right): int {
    return [$left->priority_order, $left->id] <=> [$right->priority_order, $right->id];
});

if (count($confirmedCases) !== 39 || count($requestCases) !== 86) {
    throw new RuntimeException('Los totales de auditoría no coinciden con 39 confirmados y 86 por solicitar.');
}

$spreadsheet = new Spreadsheet();
$spreadsheet->getProperties()
    ->setCreator('Casos Cooperativas')
    ->setLastModifiedBy('Casos Cooperativas')
    ->setTitle('Auditoría de pagarés - Fredy Andrés Bonfante González')
    ->setSubject('Casos CREARCOOP con pagaré confirmado o pendiente')
    ->setDescription('Auditoría documental en solo lectura. Fecha de corte: 8 de agosto de 2026.')
    ->setKeywords('pagaré, CREARCOOP, Fredy Bonfante, auditoría documental');

$summary = $spreadsheet->getActiveSheet();
$summary->setTitle('Resumen');

function styleTitle(Worksheet $sheet, string $range): void
{
    $sheet->getStyle($range)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => WHITE], 'size' => 18],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => NAVY]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
    ]);
}

function styleHeader(Worksheet $sheet, string $range, string $color = NAVY): void
{
    $sheet->getStyle($range)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => WHITE]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $color]],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
            'wrapText' => true,
        ],
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => WHITE]],
        ],
    ]);
}

function styleBody(Worksheet $sheet, string $range): void
{
    $sheet->getStyle($range)->applyFromArray([
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        'borders' => [
            'allBorders' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['rgb' => 'BFBFBF']],
        ],
    ]);
}

function setText(Worksheet $sheet, string $coordinate, mixed $value): void
{
    $sheet->setCellValueExplicit($coordinate, (string) ($value ?? ''), DataType::TYPE_STRING);
}

function setDriveLink(Worksheet $sheet, string $coordinate, ?string $url): void
{
    if ($url === null || trim($url) === '') {
        $sheet->setCellValue($coordinate, 'SIN ENLACE');
        $sheet->getStyle($coordinate)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => RED]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => LIGHT_RED]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        return;
    }

    $sheet->setCellValue($coordinate, 'Abrir expediente');
    $sheet->getCell($coordinate)->getHyperlink()->setUrl($url);
    $sheet->getStyle($coordinate)->getFont()
        ->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('0563C1'))
        ->setUnderline(true);
    $sheet->getStyle($coordinate)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
}

function alternateRows(Worksheet $sheet, int $firstRow, int $lastRow, string $lastColumn): void
{
    for ($row = $firstRow; $row <= $lastRow; $row++) {
        if ($row % 2 === 0) {
            $sheet->getStyle("A{$row}:{$lastColumn}{$row}")
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setRGB(LIGHT_GREY);
        }
    }
}

function configurePrint(Worksheet $sheet, string $repeatRows = '1:1'): void
{
    $sheet->getPageSetup()
        ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
        ->setPaperSize(PageSetup::PAPERSIZE_A4)
        ->setFitToWidth(1)
        ->setFitToHeight(0);
    $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(
        (int) explode(':', $repeatRows)[0],
        (int) explode(':', $repeatRows)[1]
    );
    $sheet->getPageMargins()->setTop(0.4)->setBottom(0.4)->setLeft(0.35)->setRight(0.35);
    $sheet->getHeaderFooter()->setOddFooter('&LFecha de corte: 08/08/2026&C&P / &N&RConfidencial');
}

// Resumen ejecutivo.
$summary->mergeCells('A1:H2');
$summary->setCellValue('A1', 'AUDITORÍA DE PAGARÉS — FREDY ANDRÉS BONFANTE GONZÁLEZ');
styleTitle($summary, 'A1:H2');
$summary->getRowDimension(1)->setRowHeight(28);
$summary->getRowDimension(2)->setRowHeight(28);

$summary->mergeCells('A3:H3');
$summary->setCellValue('A3', "Casos Cooperativas · CREARCOOP · Fecha de corte: {$auditDate}");
$summary->getStyle('A3:H3')->applyFromArray([
    'font' => ['italic' => true, 'color' => ['rgb' => DARK]],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => LIGHT_BLUE]],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
]);
$summary->getRowDimension(3)->setRowHeight(24);

$summary->setCellValue('A5', 'INDICADOR');
$summary->setCellValue('B5', 'CANTIDAD');
styleHeader($summary, 'A5:B5');

$metrics = [
    ['Casos vigentes asignados', 125, LIGHT_BLUE, NAVY],
    ['Pagaré explícito confirmado', 39, LIGHT_GREEN, GREEN],
    ['Requieren solicitar pagaré', 86, LIGHT_RED, RED],
    ['Con archivos, pero sin pagaré', 71, LIGHT_YELLOW, DARK],
    ['Carpetas Drive vacías', 14, LIGHT_ORANGE, DARK],
    ['Sin enlace Drive', 1, LIGHT_RED, RED],
    ['Drive inaccesible', 0, LIGHT_GREEN, GREEN],
];

$metricRow = 6;
foreach ($metrics as [$label, $count, $fillColor, $fontColor]) {
    $summary->setCellValue("A{$metricRow}", $label);
    $summary->setCellValue("B{$metricRow}", $count);
    $summary->getStyle("A{$metricRow}:B{$metricRow}")->applyFromArray([
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $fillColor]],
        'font' => ['color' => ['rgb' => $fontColor], 'bold' => $label === 'Requieren solicitar pagaré'],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BFBFBF']]],
    ]);
    $summary->getStyle("B{$metricRow}")->applyFromArray([
        'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => $fontColor]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);
    $metricRow++;
}

$summary->mergeCells('D5:H5');
$summary->setCellValue('D5', 'CRITERIO DE VALIDACIÓN');
styleHeader($summary, 'D5:H5');
$summary->mergeCells('D6:H9');
$summary->setCellValue('D6', "Se aceptó únicamente un pagaré explícito: formato de título valor con encabezado “Pagaré”, número, cláusulas obligacionales y firmantes. No se aceptaron solicitudes de vinculación, liquidaciones, estudios de crédito, cartas de instrucciones aisladas, nombres de archivo ni simples menciones a la palabra pagaré.");
$summary->getStyle('D6:H9')->applyFromArray([
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => LIGHT_YELLOW]],
    'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => ORANGE]]],
]);

$summary->mergeCells('D10:H10');
$summary->setCellValue('D10', 'ACCIÓN RECOMENDADA');
styleHeader($summary, 'D10:H10', RED);
$summary->mergeCells('D11:H12');
$summary->setCellValue('D11', 'Gestionar los 86 casos de la hoja “Solicitar pagaré”. El caso sin Drive debe recibir además un enlace de expediente; las carpetas vacías requieren cargar el expediente y el pagaré.');
$summary->getStyle('D11:H12')->applyFromArray([
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => LIGHT_RED]],
    'font' => ['bold' => true, 'color' => ['rgb' => RED]],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => RED]]],
]);

$summary->mergeCells('A15:H15');
$summary->setCellValue('A15', 'HOJAS DEL INFORME');
styleHeader($summary, 'A15:H15');

$summaryLinks = [
    16 => ['Solicitar pagaré', '86 casos que requieren gestión, ordenados por prioridad.', RED, LIGHT_RED],
    17 => ['Pagaré confirmado', '39 casos con título valor verificado.', GREEN, LIGHT_GREEN],
    18 => ['Alertas documentales', 'Cruces, nombres engañosos y controles especiales.', '9C6500', LIGHT_YELLOW],
];

foreach ($summaryLinks as $row => [$title, $description, $fontColor, $fillColor]) {
    $summary->setCellValue("A{$row}", $title);
    $summary->getCell("A{$row}")->getHyperlink()->setUrl("sheet://'{$title}'!A1");
    $summary->getStyle("A{$row}")->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color($fontColor))->setUnderline(true);
    $summary->mergeCells("B{$row}:H{$row}");
    $summary->setCellValue("B{$row}", $description);
    $summary->getStyle("A{$row}:H{$row}")->applyFromArray([
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $fillColor]],
        'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BFBFBF']]],
    ]);
    $summary->getRowDimension($row)->setRowHeight(25);
}

$summary->mergeCells('A21:H22');
$summary->setCellValue('A21', 'Informe generado a partir de la asignación exacta de Fredy en Casos Cooperativas. Auditoría realizada en modo solo lectura; no se modificaron expedientes ni registros.');
$summary->getStyle('A21:H22')->applyFromArray([
    'font' => ['italic' => true, 'color' => ['rgb' => '666666']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
]);

$summary->getColumnDimension('A')->setWidth(34);
$summary->getColumnDimension('B')->setWidth(14);
foreach (range('C', 'H') as $column) {
    $summary->getColumnDimension($column)->setWidth(18);
}
$summary->setShowGridlines(false);
$summary->freezePane('A5');
configurePrint($summary, '1:3');
$summary->getPageSetup()->setPrintArea('A1:H22');

// Hoja de gestión.
$requestSheet = $spreadsheet->createSheet();
$requestSheet->setTitle('Solicitar pagaré');
$requestHeaders = [
    '#', 'Prioridad', 'Caso ID', 'Referencia crédito', 'Radicado', 'Deudor', 'Cédula/NIT',
    'Estado del proceso', 'Hallazgo', 'Acción requerida', 'Expediente Drive',
];
$requestSheet->fromArray($requestHeaders, null, 'A1');
styleHeader($requestSheet, 'A1:K1', RED);
$requestSheet->getRowDimension(1)->setRowHeight(32);

$row = 2;
foreach ($requestCases as $index => $case) {
    $requestSheet->setCellValue("A{$row}", $index + 1);
    $requestSheet->setCellValue("B{$row}", $case->priority);
    $requestSheet->setCellValue("C{$row}", $case->id);
    setText($requestSheet, "D{$row}", $case->referencia_credito);
    setText($requestSheet, "E{$row}", $case->radicado);
    setText($requestSheet, "F{$row}", $case->deudor);
    setText($requestSheet, "G{$row}", $case->cedula);
    setText($requestSheet, "H{$row}", $case->estado_proceso);
    $requestSheet->setCellValue("I{$row}", $case->finding);
    $requestSheet->setCellValue("J{$row}", $case->action);
    setDriveLink($requestSheet, "K{$row}", $case->link_drive);

    $priorityFill = match ($case->priority) {
        'URGENTE' => LIGHT_RED,
        'ALTA' => LIGHT_ORANGE,
        default => LIGHT_YELLOW,
    };
    $priorityFont = $case->priority === 'URGENTE' ? RED : DARK;
    $requestSheet->getStyle("B{$row}")->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => $priorityFont]],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $priorityFill]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);
    $requestSheet->getStyle("I{$row}")->getFont()->setBold(true);
    $requestSheet->getRowDimension($row)->setRowHeight(34);
    $row++;
}

$requestLastRow = $row - 1;
alternateRows($requestSheet, 2, $requestLastRow, 'K');
styleBody($requestSheet, "A2:K{$requestLastRow}");
$requestSheet->setAutoFilter("A1:K{$requestLastRow}");
$requestSheet->freezePane('A2');
$requestSheet->getSheetView()->setZoomScale(85);
$requestSheet->getColumnDimension('A')->setWidth(6);
$requestSheet->getColumnDimension('B')->setWidth(13);
$requestSheet->getColumnDimension('C')->setWidth(10);
$requestSheet->getColumnDimension('D')->setWidth(19);
$requestSheet->getColumnDimension('E')->setWidth(27);
$requestSheet->getColumnDimension('F')->setWidth(37);
$requestSheet->getColumnDimension('G')->setWidth(17);
$requestSheet->getColumnDimension('H')->setWidth(18);
$requestSheet->getColumnDimension('I')->setWidth(31);
$requestSheet->getColumnDimension('J')->setWidth(43);
$requestSheet->getColumnDimension('K')->setWidth(21);
$requestSheet->getStyle("A2:E{$requestLastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$requestSheet->getStyle("G2:I{$requestLastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$requestSheet->getStyle("A2:A{$requestLastRow}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER);
$requestSheet->setShowGridlines(false);
configurePrint($requestSheet);
$requestSheet->getPageSetup()->setPrintArea("A1:K{$requestLastRow}");

// Hoja de confirmados.
$confirmedSheet = $spreadsheet->createSheet();
$confirmedSheet->setTitle('Pagaré confirmado');
$confirmedHeaders = [
    '#', 'Caso ID', 'Referencia crédito', 'Radicado', 'Deudor', 'Cédula/NIT',
    'Estado del proceso', 'Evidencia verificada', 'Expediente Drive',
];
$confirmedSheet->fromArray($confirmedHeaders, null, 'A1');
styleHeader($confirmedSheet, 'A1:I1', GREEN);
$confirmedSheet->getRowDimension(1)->setRowHeight(32);

$row = 2;
foreach ($confirmedCases as $index => $case) {
    $confirmedSheet->setCellValue("A{$row}", $index + 1);
    $confirmedSheet->setCellValue("B{$row}", $case->id);
    setText($confirmedSheet, "C{$row}", $case->referencia_credito);
    setText($confirmedSheet, "D{$row}", $case->radicado);
    setText($confirmedSheet, "E{$row}", $case->deudor);
    setText($confirmedSheet, "F{$row}", $case->cedula);
    setText($confirmedSheet, "G{$row}", $case->estado_proceso);
    $confirmedSheet->setCellValue("H{$row}", $case->evidence);
    setDriveLink($confirmedSheet, "I{$row}", $case->link_drive);
    $confirmedSheet->getStyle("H{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(LIGHT_GREEN);
    $confirmedSheet->getRowDimension($row)->setRowHeight(34);
    $row++;
}

$confirmedLastRow = $row - 1;
alternateRows($confirmedSheet, 2, $confirmedLastRow, 'I');
styleBody($confirmedSheet, "A2:I{$confirmedLastRow}");
$confirmedSheet->setAutoFilter("A1:I{$confirmedLastRow}");
$confirmedSheet->freezePane('A2');
$confirmedSheet->getSheetView()->setZoomScale(85);
$confirmedSheet->getColumnDimension('A')->setWidth(6);
$confirmedSheet->getColumnDimension('B')->setWidth(10);
$confirmedSheet->getColumnDimension('C')->setWidth(19);
$confirmedSheet->getColumnDimension('D')->setWidth(27);
$confirmedSheet->getColumnDimension('E')->setWidth(39);
$confirmedSheet->getColumnDimension('F')->setWidth(17);
$confirmedSheet->getColumnDimension('G')->setWidth(18);
$confirmedSheet->getColumnDimension('H')->setWidth(64);
$confirmedSheet->getColumnDimension('I')->setWidth(21);
$confirmedSheet->getStyle("A2:D{$confirmedLastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$confirmedSheet->getStyle("F2:G{$confirmedLastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$confirmedSheet->setShowGridlines(false);
configurePrint($confirmedSheet);
$confirmedSheet->getPageSetup()->setPrintArea("A1:I{$confirmedLastRow}");

// Alertas y notas de control.
$alertsSheet = $spreadsheet->createSheet();
$alertsSheet->setTitle('Alertas documentales');
$alertsSheet->mergeCells('A1:D2');
$alertsSheet->setCellValue('A1', 'ALERTAS DOCUMENTALES Y CONTROLES ESPECIALES');
styleTitle($alertsSheet, 'A1:D2');
$alertsSheet->fromArray(['Tipo', 'Casos / referencia', 'Hallazgo', 'Recomendación'], null, 'A4');
styleHeader($alertsSheet, 'A4:D4', '9C6500');

$alerts = [
    [
        'Nombre engañoso',
        '430, 439, 444, 445, 451, 453, 455, 458, 459, 466, 467, 469 y 470',
        'El nombre del PDF contiene “PAGARE”, pero su contenido no incluye ningún pagaré explícito.',
        'Solicitar el título valor; no aceptar el nombre del archivo como prueba.',
    ],
    [
        'Mención insuficiente',
        '482, 490, 494 y 505',
        '“Pagaré” aparece únicamente como un campo dentro de una liquidación de crédito.',
        'Solicitar el documento pagaré completo.',
    ],
    [
        'Cruce documental',
        'Caso 477 · ref. 1915000164',
        'El paquete está nombrado con la referencia 1915000354, correspondiente al caso 476, y tampoco contiene pagaré.',
        'Corregir el cruce documental y solicitar el pagaré correcto.',
    ],
    [
        'Confirmación especial',
        'Casos 381 y 382',
        'El PDF combinado era engañoso; otro archivo nombrado solo con la referencia sí contenía el pagaré completo.',
        'No solicitar nuevamente. Conservar claramente identificado el archivo válido.',
    ],
    [
        'Confirmación especial',
        'Caso 510 · ref. 242000214',
        'Pagaré completo verificado: título, cláusulas, firmas/huellas y carta de instrucciones.',
        'No requiere solicitud de pagaré.',
    ],
    [
        'Fuera del universo',
        'Judith Esther Luque Pérez',
        'Aparece en una carpeta histórica rotulada como Fredy, pero la aplicación asigna el caso a Marlin Palacios. Sí tiene pagaré.',
        'No incluir en la gestión de faltantes de Fredy.',
    ],
    [
        'Alcance',
        '125 casos',
        'Se revisaron todos los registros vigentes/no borrados asignados a Fredy; todos pertenecen a CREARCOOP.',
        'Usar la hoja “Solicitar pagaré” como listado operativo.',
    ],
];

$row = 5;
foreach ($alerts as $alert) {
    $alertsSheet->fromArray($alert, null, "A{$row}");
    $alertsSheet->getRowDimension($row)->setRowHeight(65);
    $row++;
}

$alertsLastRow = $row - 1;
alternateRows($alertsSheet, 5, $alertsLastRow, 'D');
styleBody($alertsSheet, "A5:D{$alertsLastRow}");
$alertsSheet->getStyle("A5:A{$alertsLastRow}")->getFont()->setBold(true);
$alertsSheet->getStyle("A5:A{$alertsLastRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(LIGHT_YELLOW);
$alertsSheet->freezePane('A5');
$alertsSheet->setAutoFilter("A4:D{$alertsLastRow}");
$alertsSheet->getColumnDimension('A')->setWidth(23);
$alertsSheet->getColumnDimension('B')->setWidth(35);
$alertsSheet->getColumnDimension('C')->setWidth(66);
$alertsSheet->getColumnDimension('D')->setWidth(52);
$alertsSheet->setShowGridlines(false);
configurePrint($alertsSheet, '1:4');
$alertsSheet->getPageSetup()->setPrintArea("A1:D{$alertsLastRow}");

foreach ($spreadsheet->getAllSheets() as $sheet) {
    $sheet->getStyle($sheet->calculateWorksheetDimension())->getFont()->setName('Aptos')->setSize(10);
    $sheet->getStyle($sheet->calculateWorksheetDimension())->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
}

// Reaplicar tamaños de títulos después del estilo global.
$summary->getStyle('A1:H2')->getFont()->setSize(18)->setBold(true)->getColor()->setRGB(WHITE);
$alertsSheet->getStyle('A1:D2')->getFont()->setSize(18)->setBold(true)->getColor()->setRGB(WHITE);

$spreadsheet->setActiveSheetIndexByName('Resumen');

$writer = new Xlsx($spreadsheet);
$writer->setPreCalculateFormulas(true);
$writer->save($outputPath);

echo $outputPath . PHP_EOL;
