<?php

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Table;
use PhpOffice\PhpSpreadsheet\Worksheet\Table\TableStyle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require dirname(__DIR__).'/vendor/autoload.php';

$storagePath = dirname(__DIR__).'/storage/app/private';
$sourcePath = $argv[1] ?? $storagePath.'/.contactos_lotes_source.json';
$outputPath = $argv[2] ?? $storagePath.'/contactos_deudores_codeudores_lotes_1_y_2.xlsx';
$summaryPath = $argv[3] ?? $storagePath.'/contactos_deudores_codeudores_lotes_1_y_2.summary.json';

$payload = json_decode((string) file_get_contents($sourcePath), true, 512, JSON_THROW_ON_ERROR);

/** @return list<string> */
function splitLines(mixed $value): array
{
    $parts = preg_split('/\R+/u', trim((string) $value)) ?: [];

    return array_values(array_filter(array_map(
        static fn (string $part): string => trim(preg_replace('/^\s*\d+\s*[.)-]\s*/u', '', $part) ?? $part),
        $parts,
    ), static fn (string $part): bool => $part !== ''));
}

function clean(mixed $value): string
{
    $value = trim((string) $value);

    return preg_replace('/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}]/u', '', $value) ?? '';
}

function normalizedIdentity(mixed $value): string
{
    $value = mb_strtoupper(clean($value), 'UTF-8');
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

    return preg_replace('/[^A-Z0-9]/', '', $ascii === false ? $value : $ascii) ?? '';
}

function roleOf(mixed $value): string
{
    $role = normalizedIdentity($value);

    if (str_contains($role, 'CODEUDOR')) {
        return 'CODEUDOR';
    }

    if (str_contains($role, 'DEUDOR')) {
        return 'DEUDOR';
    }

    return $role === '' ? 'POR REVISAR' : clean($value);
}

function contactStatus(array $person): string
{
    $missing = [];

    foreach ([
        'name' => 'NOMBRE',
        'document' => 'DOCUMENTO',
        'phones' => 'TELÉFONO',
        'emails' => 'CORREO',
    ] as $field => $label) {
        if (clean($person[$field] ?? '') === '') {
            $missing[] = $label;
        }
    }

    return $missing === [] ? 'COMPLETO' : 'FALTA '.implode(' + ', $missing);
}

/** @param array<string, mixed> $person */
function canonicalRow(array $person): array
{
    $row = [
        'source_row' => (string) ($person['source_row'] ?? ''),
        'promissory' => clean($person['promissory'] ?? ''),
        'role' => clean($person['role'] ?? ''),
        'name' => clean($person['name'] ?? ''),
        'document' => clean($person['document'] ?? ''),
        'phones' => clean($person['phones'] ?? ''),
        'emails' => clean($person['emails'] ?? ''),
        'contact_status' => '',
        'related_debtor_name' => clean($person['related_debtor_name'] ?? ''),
        'related_debtor_document' => clean($person['related_debtor_document'] ?? ''),
        'review_status' => clean($person['review_status'] ?? ''),
        'contact_source' => clean($person['contact_source'] ?? ''),
        'drive' => clean($person['drive'] ?? ''),
        'source_documents' => clean($person['source_documents'] ?? ''),
    ];

    $row['contact_status'] = contactStatus($row);

    return $row;
}

/** @return list<array<string, string>> */
function firstBatchRows(array $sourceRows): array
{
    $output = [];

    foreach ($sourceRows as $source) {
        $people = [[
            'source_row' => $source['row'],
            'promissory' => $source['promissory'],
            'role' => roleOf($source['listed_role']),
            'name' => $source['name'],
            'document' => $source['document'],
            'phones' => $source['listed_phones'],
            'emails' => $source['listed_emails'],
            'review_status' => $source['review_status'],
            'contact_source' => 'Hoja completada / ficha y documentos vinculados',
            'drive' => $source['drive'],
            'source_documents' => $source['source_docs'],
        ]];

        $names = splitLines($source['related_names']);
        $roles = splitLines($source['related_roles']);
        $documents = splitLines($source['related_documents']);
        $phones = splitLines($source['related_phones']);
        $emails = splitLines($source['related_emails']);
        $relatedCount = max(count($names), count($roles), count($documents), count($phones), count($emails));

        for ($index = 0; $index < $relatedCount; $index++) {
            $people[] = [
                'source_row' => $source['row'],
                'promissory' => $source['promissory'],
                'role' => roleOf($roles[$index] ?? ''),
                'name' => $names[$index] ?? '',
                'document' => $documents[$index] ?? '',
                'phones' => $phones[$index] ?? '',
                'emails' => $emails[$index] ?? '',
                'review_status' => $source['review_status'],
                'contact_source' => 'Hoja completada / ficha y documentos vinculados',
                'drive' => $source['drive'],
                'source_documents' => $source['source_docs'],
            ];
        }

        $debtor = null;
        foreach ($people as $person) {
            if (($person['role'] ?? '') === 'DEUDOR') {
                $debtor = $person;
                break;
            }
        }

        usort($people, static fn (array $left, array $right): int =>
            (($left['role'] ?? '') === 'DEUDOR' ? 0 : 1) <=> (($right['role'] ?? '') === 'DEUDOR' ? 0 : 1)
        );

        foreach ($people as $person) {
            if (($person['role'] ?? '') === 'CODEUDOR' && $debtor !== null) {
                $person['related_debtor_name'] = $debtor['name'] ?? '';
                $person['related_debtor_document'] = $debtor['document'] ?? '';
            }

            $output[] = canonicalRow($person);
        }
    }

    return $output;
}

/** @return list<array<string, string>> */
function secondBatchRows(array $sourceRows): array
{
    $output = [];

    foreach ($sourceRows as $source) {
        $base = [
            'source_row' => $source['row'],
            'promissory' => $source['promissory'],
            'drive' => $source['drive'],
            'source_documents' => $source['source_docs'],
        ];

        $output[] = canonicalRow($base + [
            'role' => 'DEUDOR',
            'name' => $source['name'],
            'document' => $source['document'],
            'phones' => $source['debtor_phones'],
            'emails' => $source['debtor_emails'],
            'review_status' => $source['review_status'],
            'contact_source' => 'Columnas verificadas de la hoja completada',
        ]);

        $verifiedPresent = clean($source['verified_codebtor_names']) !== ''
            || clean($source['verified_codebtor_documents']) !== '';
        $rawPresent = clean($source['raw_codebtor_name']) !== ''
            || clean($source['raw_codebtor_document']) !== '';
        $sameIdentity = $verifiedPresent && $rawPresent && (
            (normalizedIdentity($source['verified_codebtor_documents']) !== ''
                && normalizedIdentity($source['verified_codebtor_documents']) === normalizedIdentity($source['raw_codebtor_document']))
            || (normalizedIdentity($source['verified_codebtor_names']) !== ''
                && normalizedIdentity($source['verified_codebtor_names']) === normalizedIdentity($source['raw_codebtor_name']))
        );

        if ($verifiedPresent) {
            $output[] = canonicalRow($base + [
                'role' => 'CODEUDOR',
                'name' => $source['verified_codebtor_names'],
                'document' => $source['verified_codebtor_documents'],
                'phones' => clean($source['verified_codebtor_phones']) !== ''
                    ? $source['verified_codebtor_phones']
                    : ($sameIdentity ? $source['raw_codebtor_phone'] : ''),
                'emails' => clean($source['verified_codebtor_emails']) !== ''
                    ? $source['verified_codebtor_emails']
                    : ($sameIdentity ? $source['raw_codebtor_email'] : ''),
                'related_debtor_name' => $source['name'],
                'related_debtor_document' => $source['document'],
                'review_status' => $source['review_status'] ?: 'VERIFICADO',
                'contact_source' => $sameIdentity
                    ? 'Columnas verificadas + dato original coincidente'
                    : 'Columnas verificadas de la hoja completada',
            ]);
        }

        if ($rawPresent && ! $sameIdentity) {
            $output[] = canonicalRow($base + [
                'role' => 'CODEUDOR',
                'name' => $source['raw_codebtor_name'],
                'document' => $source['raw_codebtor_document'],
                'phones' => $source['raw_codebtor_phone'],
                'emails' => $source['raw_codebtor_email'],
                'related_debtor_name' => $source['name'],
                'related_debtor_document' => $source['document'],
                'review_status' => $verifiedPresent
                    ? 'REVISIÓN: el dato original no coincide con el verificado'
                    : 'REVISIÓN: solo consta en la fuente original',
                'contact_source' => 'Columnas originales del segundo lote',
            ]);
        }
    }

    return $output;
}

/** @param list<array<string, string>> $rows */
function populateSheet(Worksheet $sheet, array $rows, string $tableName): void
{
    $headers = [
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

    foreach ($headers as $columnIndex => $header) {
        $sheet->setCellValueExplicit([$columnIndex + 1, 1], $header, DataType::TYPE_STRING);
    }

    foreach ($rows as $rowIndex => $row) {
        $values = array_values($row);
        foreach ($values as $columnIndex => $value) {
            $sheet->setCellValueExplicit(
                [$columnIndex + 1, $rowIndex + 2],
                (string) $value,
                DataType::TYPE_STRING,
            );
        }
    }

    $lastRow = count($rows) + 1;
    $lastColumn = 'N';
    $sheet->freezePane('A2');
    $sheet->setAutoFilter("A1:{$lastColumn}{$lastRow}");
    $sheet->getRowDimension(1)->setRowHeight(32);
    $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
    ]);
    $sheet->getStyle("A2:{$lastColumn}{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
    $sheet->getStyle("A2:E{$lastRow}")->getNumberFormat()->setFormatCode('@');
    $sheet->getStyle("F2:G{$lastRow}")->getNumberFormat()->setFormatCode('@');
    $sheet->getStyle("J2:J{$lastRow}")->getNumberFormat()->setFormatCode('@');

    $widths = [12, 22, 14, 34, 20, 24, 36, 28, 34, 22, 28, 34, 42, 42];
    foreach ($widths as $index => $width) {
        $sheet->getColumnDimensionByColumn($index + 1)->setWidth($width);
    }

    if ($lastRow > 1) {
        $table = new Table("A1:{$lastColumn}{$lastRow}", $tableName);
        $table->setStyle((new TableStyle())->setTheme(TableStyle::TABLE_STYLE_MEDIUM2)->setShowRowStripes(true));
        $sheet->addTable($table);
    }
}

/** @param list<array<string, string>> $rows */
function sheetSummary(array $rows, int $sourceRowCount): array
{
    $byRole = [];
    $missing = ['name' => 0, 'document' => 0, 'phones' => 0, 'emails' => 0, 'promissory' => 0];
    $sourceRowsSeen = [];

    foreach ($rows as $row) {
        $byRole[$row['role']] = ($byRole[$row['role']] ?? 0) + 1;
        $sourceRowsSeen[$row['source_row']] = true;
        foreach (array_keys($missing) as $field) {
            if (clean($row[$field] ?? '') === '') {
                $missing[$field]++;
            }
        }
    }

    ksort($byRole);

    return [
        'source_rows_expected' => $sourceRowCount,
        'source_rows_represented' => count($sourceRowsSeen),
        'person_rows' => count($rows),
        'by_role' => $byRole,
        'missing_fields' => $missing,
    ];
}

$firstRows = firstBatchRows($payload['first']);
$secondRows = secondBatchRows($payload['second']);

$spreadsheet = new Spreadsheet();
$spreadsheet->getProperties()
    ->setCreator('Abogados en Colombia S.A.S.')
    ->setTitle('Contactos deudores y codeudores - dos lotes')
    ->setSubject('Datos de contacto con trazabilidad y sin valores monetarios')
    ->setDescription('Dos hojas separadas para el lote Sandra Duque y el segundo lote.');

$firstSheet = $spreadsheet->getActiveSheet();
$firstSheet->setTitle('1_Sandra_Duque');
$secondSheet = new Worksheet($spreadsheet, '2_Segundo_Lote');
$spreadsheet->addSheet($secondSheet);

populateSheet($firstSheet, $firstRows, 'ContactosSandraDuque');
populateSheet($secondSheet, $secondRows, 'ContactosSegundoLote');
$spreadsheet->setActiveSheetIndex(0);

$writer = new Xlsx($spreadsheet);
$writer->setPreCalculateFormulas(false);
$writer->save($outputPath);
$spreadsheet->disconnectWorksheets();

$summary = [
    'generated_at_utc' => gmdate(DATE_ATOM),
    'output' => basename($outputPath),
    'sha256' => hash_file('sha256', $outputPath),
    'contains_monetary_values' => false,
    'sheets' => [
        '1_Sandra_Duque' => sheetSummary($firstRows, count($payload['first'])),
        '2_Segundo_Lote' => sheetSummary($secondRows, count($payload['second'])),
    ],
];

file_put_contents($summaryPath, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL);

fwrite(STDOUT, json_encode($summary, JSON_UNESCAPED_UNICODE).PHP_EOL);
