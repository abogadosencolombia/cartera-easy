<?php

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require dirname(__DIR__).'/vendor/autoload.php';

$input = $argv[1] ?? dirname(__DIR__).'/storage/app/private/contactos_deudores_codeudores_lotes_1_y_2.xlsx';
$output = $argv[2] ?? dirname(__DIR__).'/storage/app/private/CONTACTOS_CREARCOOP_SANDRA_Y_SOLO_PAGARES.xlsx';

if (! is_file($input)) {
    fwrite(STDERR, "No se encontró el Excel consolidado de entrada.\n");
    exit(1);
}

$spreadsheet = IOFactory::load($input);

if ($spreadsheet->getSheetCount() !== 2) {
    fwrite(STDERR, "El Excel de entrada debe contener exactamente dos hojas.\n");
    exit(1);
}

$expectedHeaders = [
    'Fila fuente',
    'Pagaré/Obligación',
    'Rol',
    'Nombre',
    'Documento',
    'Teléfono(s)',
    'Correo(s)',
    'Estado de contacto',
    'Deudor relacionado',
    'Documento deudor relacionado',
    'Estado de verificación',
    'Fuente del contacto',
    'Carpeta Drive',
    'Documento(s) fuente',
];

$sheetNames = ['1_Sandra_Duque', '2_Solo_Pagares'];
$tabColors = ['4472C4', '70AD47'];

function isMissingContactValue(mixed $value): bool
{
    $value = trim((string) $value);

    return $value === '' || preg_match(
        '/^(?:[-—–.]+|N\/?A|NO(?: TIENE| REGISTRA)?|SIN (?:DATO|INFORMACI[ÓO]N)|NO APLICA)$/iu',
        $value,
    ) === 1;
}

function contactStatusForRow(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $row): string
{
    $missing = [];
    foreach (['D' => 'NOMBRE', 'E' => 'DOCUMENTO', 'F' => 'TELÉFONO', 'G' => 'CORREO'] as $column => $label) {
        if (isMissingContactValue($sheet->getCell("{$column}{$row}")->getValue())) {
            $missing[] = $label;
        }
    }

    return $missing === [] ? 'COMPLETO' : 'FALTA '.implode(' + ', $missing);
}

foreach ($spreadsheet->getWorksheetIterator() as $index => $sheet) {
    $actualHeaders = [];
    foreach (range(1, count($expectedHeaders)) as $column) {
        $actualHeaders[] = (string) $sheet->getCell([$column, 1])->getValue();
    }

    if ($actualHeaders !== $expectedHeaders) {
        fwrite(STDERR, "La estructura del Excel de entrada no coincide con la esperada.\n");
        exit(1);
    }

    $sheet->setTitle($sheetNames[$index]);
    $sheet->getTabColor()->setRGB($tabColors[$index]);
    $sheet->freezePane('A2');
    $sheet->getPageSetup()
        ->setOrientation('landscape')
        ->setFitToWidth(1)
        ->setFitToHeight(0);
    $sheet->getPageMargins()
        ->setTop(0.4)
        ->setRight(0.3)
        ->setBottom(0.4)
        ->setLeft(0.3)
        ->setHeader(0.2)
        ->setFooter(0.2);
    $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 1);
    $sheet->getHeaderFooter()
        ->setOddHeader('&B'.$sheetNames[$index])
        ->setOddFooter('Página &P de &N');

    $lastRow = $sheet->getHighestDataRow();
    $lastColumn = 'N';
    $sheet->setAutoFilter("A1:{$lastColumn}{$lastRow}");
    $sheet->getRowDimension(1)->setRowHeight(34);
    $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
            'wrapText' => true,
        ],
        'borders' => [
            'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D9EAF7']],
        ],
    ]);
    $sheet->getStyle("A2:{$lastColumn}{$lastRow}")
        ->getAlignment()
        ->setVertical(Alignment::VERTICAL_TOP)
        ->setWrapText(true);
    $sheet->getStyle("A2:N{$lastRow}")->getFont()->setSize(10);
    $sheet->getStyle("A2:G{$lastRow}")->getNumberFormat()->setFormatCode('@');
    $sheet->getStyle("J2:J{$lastRow}")->getNumberFormat()->setFormatCode('@');

    $widths = [12, 21, 14, 32, 19, 23, 34, 27, 31, 21, 29, 32, 38, 40];
    foreach ($widths as $columnIndex => $width) {
        $sheet->getColumnDimensionByColumn($columnIndex + 1)->setWidth($width);
    }

    for ($row = 2; $row <= $lastRow; $row++) {
        $role = mb_strtoupper(trim((string) $sheet->getCell("C{$row}")->getValue()), 'UTF-8');
        $sheet->setCellValueExplicit("H{$row}", contactStatusForRow($sheet, $row), DataType::TYPE_STRING);
        $status = mb_strtoupper(trim((string) $sheet->getCell("H{$row}")->getValue()), 'UTF-8');
        $driveUrl = trim((string) $sheet->getCell("M{$row}")->getValue());

        $roleColor = $role === 'DEUDOR' ? 'D9EAF7' : ($role === 'CODEUDOR' ? 'FCE4D6' : 'E7E6E6');
        $sheet->getStyle("C{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $roleColor]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $statusColor = $status === 'COMPLETO' ? 'E2F0D9' : (str_contains($status, 'DOCUMENTO') ? 'F4CCCC' : 'FFF2CC');
        $sheet->getStyle("H{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB($statusColor);

        if (preg_match('~^https://(?:drive|docs)\.google\.com/~i', $driveUrl) === 1) {
            $sheet->getCell("M{$row}")->getHyperlink()->setUrl($driveUrl);
            $sheet->getStyle("M{$row}")->getFont()->getColor()->setRGB('0563C1');
            $sheet->getStyle("M{$row}")->getFont()->setUnderline(true);
        }

        foreach (['B', 'E', 'F', 'G', 'J'] as $column) {
            $value = (string) $sheet->getCell("{$column}{$row}")->getValue();
            $sheet->setCellValueExplicit("{$column}{$row}", $value, DataType::TYPE_STRING);
        }
    }
}

$spreadsheet->getProperties()
    ->setCreator('Abogados en Colombia S.A.S.')
    ->setLastModifiedBy('Abogados en Colombia S.A.S.')
    ->setTitle('Contactos CREARCOOP: Sandra Duque y Solo pagarés')
    ->setSubject('Deudores y codeudores organizados por grupo')
    ->setDescription('Dos hojas separadas, con pagaré, identidad, contacto, verificación y fuentes. Sin valores monetarios.');
$spreadsheet->setActiveSheetIndex(0);

$writer = new Xlsx($spreadsheet);
$writer->setPreCalculateFormulas(false);
$writer->save($output);
$spreadsheet->disconnectWorksheets();

chmod($output, 0600);

fwrite(STDOUT, json_encode([
    'output' => $output,
    'sha256' => hash_file('sha256', $output),
], JSON_UNESCAPED_SLASHES).PHP_EOL);
