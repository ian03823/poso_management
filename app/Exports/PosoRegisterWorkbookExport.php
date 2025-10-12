<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PosoRegisterWorkbookExport implements WithMultipleSheets
{
     /** @var array<string, mixed> */
    protected array $byMonth;

    public function __construct(array $byMonth)
    {
        $this->byMonth = $byMonth;
    }

    public function sheets(): array
    {
        $sheets = [];
        ksort($this->byMonth); // ascending months
        foreach ($this->byMonth as $ym => $rows) {
            $sheets[] = new PosoMonthlySheet($ym, $rows);
        }
        return $sheets;
    }
}
