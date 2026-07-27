<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class SafeSpreadsheetValueBinder extends DefaultValueBinder
{
    public function bindValue(Cell $cell, $value): bool
    {
        if (
            is_string($value)
            && preg_match('/^[\p{C}\p{Z}\s]*[=+\-@]/u', $value) === 1
        ) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }
}
