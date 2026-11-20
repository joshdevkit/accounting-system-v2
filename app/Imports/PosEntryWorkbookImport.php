<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PosEntryWorkbookImport implements Import, WithMultipleSheets
{
    public function __construct(
        protected string $sheet,
        protected PosEntryImport $import,
    ) {}

    public function sheets(): array
    {
        return [$this->sheet => $this->import];
    }
}
