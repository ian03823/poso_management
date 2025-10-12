<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class PosoMonthlySheet implements FromArray, ShouldAutoSize, WithEvents
{
    protected string $ym;        // 'YYYY-MM'
    /** @var \Illuminate\Support\Collection<int, array> */
    protected Collection $rows;  // normalized ticket rows
    protected bool $isEmpty = false;
    public function __construct(string $ym, Collection $rows)
    {
        $this->ym   = $ym;
        $this->rows = $rows->sortBy('date')->values();
        $this->isEmpty = $this->rows->isEmpty();
    }

    public function array(): array
    {
        $monthTitle = strtoupper(Carbon::parse($this->ym.'-01')->format('F'));

        // Title + month band + header
        $rows   = [];
        $rows[] = ['PROVINCE OF PANGASINAN'];
        $rows[] = ['CITY OF SAN CARLOS'];
        $rows[] = ['PUBLIC ORDER AND SAFETY OFFICE'];
        $rows[] = [''];                     // spacer
        $rows[] = [$monthTitle];            // green band
        $rows[] = [                         // tan header row
            'DAY',
            "DATE OF\nAPPREHENSION\n(MMDDYYYY)",
            "DRIVER'S NAME",
            'ADDRESS',
            'TCT#',
            'VIOLATIONS',
            'OR#',
            'AMOUNT',
            'STATUS',
        ];
        if ($this->isEmpty) {
            // One merged row with "No records" (we’ll merge/style in AfterSheet)
            $rows[] = ['NO RECORDS FOR THIS MONTH'];
            return $rows;
        }

        // Data rows (one per ticket)
        foreach ($this->rows as $t) {
            $dt = Carbon::parse($t['date']);
            $rows[] = [
                $dt->day,
                $dt->format('mdY'),
                (string)($t['driver'] ?? ''),
                (string)($t['address'] ?? ''),
                (string)($t['tct'] ?? ''),
                (string)($t['violations'] ?? ''),
                (string)($t['or_number'] ?? ''),
                (float)($t['amount'] ?? 0),
                strtoupper((string)($t['status'] ?? '')),
            ];
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $ws = $event->sheet->getDelegate();
                
                // Merge titles + center
                $ws->mergeCells('A1:I1');
                $ws->mergeCells('A2:I2');
                $ws->mergeCells('A3:I3');
                $ws->getStyle('A1:A3')->applyFromArray([
                    'font'      => ['bold' => true],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                // Green month band
                $ws->mergeCells('A5:I5');
                $ws->getStyle('A5')->applyFromArray([
                    'font'      => ['bold'=>true, 'size'=>12],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_LEFT],
                    'fill'      => ['fillType'=>Fill::FILL_SOLID, 'startColor'=>['rgb'=>'CFE3BE']],
                    'borders'   => ['outline'=>['borderStyle'=>Border::BORDER_MEDIUM]],
                ]);

                // Tan table header (row 6)
                $ws->getRowDimension(6)->setRowHeight(38);
                $ws->getStyle('A6:I6')->applyFromArray([
                    'font'      => ['bold'=>true],
                    'alignment' => [
                        'horizontal'=>Alignment::HORIZONTAL_CENTER,
                        'vertical'  =>Alignment::VERTICAL_CENTER,
                        'wrapText'  =>true,
                    ],
                    'fill'      => ['fillType'=>Fill::FILL_SOLID, 'startColor'=>['rgb'=>'EAD69A']],
                    'borders'   => ['allBorders'=>['borderStyle'=>Border::BORDER_MEDIUM]],
                ]);

                // Grid + number alignment for data area
                $firstData = 7;
                $lastData  = max($firstData, $ws->getHighestRow());
                $ws->getStyle("A{$firstData}:I{$lastData}")
                   ->applyFromArray(['borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN]]]);

                // Amount currency format
                $ws->getStyle("H{$firstData}:H{$lastData}")
                   ->getNumberFormat()->setFormatCode('₱#,##0.00');

                // Column widths tuned to your layout
                $ws->getColumnDimension('A')->setWidth(5);   // Day
                $ws->getColumnDimension('B')->setWidth(16);  // Date
                $ws->getColumnDimension('C')->setWidth(28);  // Driver
                $ws->getColumnDimension('D')->setWidth(28);  // Address
                $ws->getColumnDimension('E')->setWidth(12);  // TCT
                $ws->getColumnDimension('F')->setWidth(28);  // Violations
                $ws->getColumnDimension('G')->setWidth(12);  // OR
                $ws->getColumnDimension('H')->setWidth(12);  // Amount
                $ws->getColumnDimension('I')->setWidth(12);  // Status

                $ws->setTitle(strtoupper(\Carbon\Carbon::parse($this->ym.'-01')->format('F')));
                $firstData = 7;
                $lastRow   = $ws->getHighestRow();

                if ($this->isEmpty) {
                    // Merge A7:I7 and center the message
                    $ws->mergeCells("A{$firstData}:I{$firstData}");
                    $ws->getStyle("A{$firstData}")->applyFromArray([
                        'font' => ['italic'=>true, 'color'=>['rgb'=>'777777']],
                        'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
                        'borders' => ['outline'=>['borderStyle'=>Border::BORDER_THIN]],
                    ]);
                } else {
                    // Grid for real data + currency format
                    $ws->getStyle("A{$firstData}:I{$lastRow}")
                    ->applyFromArray(['borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN]]]);
                    $ws->getStyle("H{$firstData}:H{$lastRow}")
                    ->getNumberFormat()->setFormatCode('₱#,##0.00');
                }
            },
        ];
    }
}
