<?php

namespace App\Exports\Concerns;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

trait PreservesExcelIdentifiers
{
    /**
     * Excel only preserves 15 significant digits in numeric cells. Judicial
     * radicados, document numbers and credit references are identifiers, not
     * quantities, so they must be written as text to avoid silent corruption.
     */
    public function bindValue(Cell $cell, $value): bool
    {
        if (is_string($value) && preg_match('/^\d{7,}$/', trim($value))) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return (new DefaultValueBinder())->bindValue($cell, $value);
    }
}
