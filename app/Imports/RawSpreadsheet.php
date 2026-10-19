<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

/**
 * Read a sheet as plain numeric rows so the registration importer can work
 * with caller-selected column indexes instead of relying on fixed headings.
 */
class RawSpreadsheet implements ToArray
{
    public function array(array $array): void
    {
        // The reader collects the return value through Excel::toArray(); this
        // importer intentionally has no side effects.
    }
}
