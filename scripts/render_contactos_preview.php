<?php

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\IOFactory;

require dirname(__DIR__).'/vendor/autoload.php';

$input = $argv[1] ?? dirname(__DIR__).'/storage/app/private/CONTACTOS_CREARCOOP_SANDRA_Y_SOLO_PAGARES.xlsx';
$outputDirectory = $argv[2] ?? sys_get_temp_dir().'/contactos_excel_preview';
$font = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';

if (! is_file($input) || ! is_file($font)) {
    fwrite(STDERR, "No se encontró el Excel o la fuente requerida para la vista previa.\n");
    exit(1);
}

if (! is_dir($outputDirectory) && ! mkdir($outputDirectory, 0700, true) && ! is_dir($outputDirectory)) {
    fwrite(STDERR, "No fue posible crear el directorio de vista previa.\n");
    exit(1);
}

/** @return array{0:int,1:int,2:int} */
function rgb(string $hex): array
{
    $hex = str_pad(substr($hex, -6), 6, '0', STR_PAD_LEFT);

    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}

/** @return list<string> */
function wrappedLines(string $text, string $font, int $fontSize, int $maxWidth, int $maxLines): array
{
    $paragraphs = preg_split('/\R/u', trim($text)) ?: [''];
    $lines = [];

    foreach ($paragraphs as $paragraph) {
        $words = preg_split('/\s+/u', trim($paragraph)) ?: [''];
        $current = '';
        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;
            $box = imagettfbbox($fontSize, 0, $font, $candidate);
            $width = $box === false ? 0 : abs($box[2] - $box[0]);
            if ($current !== '' && $width > $maxWidth) {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
            if (count($lines) >= $maxLines) {
                break 2;
            }
        }
        if ($current !== '' && count($lines) < $maxLines) {
            $lines[] = $current;
        }
        if (count($lines) >= $maxLines) {
            break;
        }
    }

    if ($lines === []) {
        return [''];
    }

    if (count($lines) === $maxLines && mb_strlen(end($lines), 'UTF-8') > 3) {
        $lines[$maxLines - 1] = mb_substr($lines[$maxLines - 1], 0, -3, 'UTF-8').'…';
    }

    return $lines;
}

$spreadsheet = IOFactory::load($input);
$columnWidths = [12, 21, 14, 32, 19, 23, 34, 27, 31, 21, 29, 32, 38, 40];
$pixelWidths = array_map(static fn (int $width): int => (int) round($width * 5.5), $columnWidths);
$canvasWidth = array_sum($pixelWidths) + 2;
$headerHeight = 72;
$rowHeight = 80;
$fontSize = 10;

foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
    $firstCodebtor = null;
    for ($row = 2; $row <= $sheet->getHighestDataRow(); $row++) {
        if (mb_strtoupper(trim((string) $sheet->getCell("C{$row}")->getValue()), 'UTF-8') === 'CODEUDOR') {
            $firstCodebtor = $row;
            break;
        }
    }

    $rows = range(2, min(7, $sheet->getHighestDataRow()));
    if ($firstCodebtor !== null && ! in_array($firstCodebtor, $rows, true)) {
        $rows[] = $firstCodebtor;
    }

    $canvasHeight = $headerHeight + (count($rows) * $rowHeight) + 2;
    $image = imagecreatetruecolor($canvasWidth, $canvasHeight);
    if ($image === false) {
        fwrite(STDERR, "No fue posible crear la vista previa.\n");
        exit(1);
    }

    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 31, 31, 31);
    $border = imagecolorallocate($image, 190, 200, 210);
    $stripe = imagecolorallocate($image, 245, 248, 251);
    imagefill($image, 0, 0, $white);

    $y = 0;
    foreach (array_merge([1], $rows) as $displayIndex => $sourceRow) {
        $height = $displayIndex === 0 ? $headerHeight : $rowHeight;
        $x = 0;
        foreach (range(1, 14) as $column) {
            $cell = $sheet->getCell([$column, $sourceRow]);
            $value = (string) $cell->getFormattedValue();
            $fillHex = $cell->getStyle()->getFill()->getStartColor()->getRGB();
            if ($displayIndex === 0) {
                $fillHex = '1F4E78';
            } elseif ($fillHex === '000000' || $fillHex === 'FFFFFF') {
                $fillHex = $displayIndex % 2 === 0 ? 'F5F8FB' : 'FFFFFF';
            }
            [$red, $green, $blue] = rgb($fillHex);
            $fill = imagecolorallocate($image, $red, $green, $blue);
            imagefilledrectangle($image, $x, $y, $x + $pixelWidths[$column - 1], $y + $height, $fill);
            imagerectangle($image, $x, $y, $x + $pixelWidths[$column - 1], $y + $height, $border);

            $textColor = $displayIndex === 0 ? $white : $black;
            $lines = wrappedLines($value, $font, $fontSize, $pixelWidths[$column - 1] - 10, $displayIndex === 0 ? 3 : 4);
            $textY = $y + 18;
            foreach ($lines as $line) {
                imagettftext($image, $fontSize, 0, $x + 5, $textY, $textColor, $font, $line);
                $textY += 16;
            }
            $x += $pixelWidths[$column - 1];
        }
        $y += $height;
    }

    $file = $outputDirectory.'/'.$sheet->getTitle().'.png';
    imagepng($image, $file, 6);
    imagedestroy($image);
    chmod($file, 0600);
    fwrite(STDOUT, $file.PHP_EOL);
}

$spreadsheet->disconnectWorksheets();
