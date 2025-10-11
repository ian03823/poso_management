<?php

namespace App\Exports;

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\Settings;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FormalStatsDocxExport
{
    protected int $paid;
    protected int $unpaid;
    protected int $total;
    protected array $hotspots;   // array of ['area' => string, 'c' => int]
    protected string $cover;
    protected string $generatedOn;
    protected array $filters;    // array of strings
    protected ?string $logoPath;

    public function __construct(array $data)
    {
        $this->paid        = (int)($data['paid'] ?? 0);
        $this->unpaid      = (int)($data['unpaid'] ?? 0);
        $this->total       = (int)($data['total'] ?? ($this->paid + $this->unpaid));
        $this->hotspots    = array_values($data['hotspots'] ?? []); // [['area'=>'X','c'=>5],...]
        $this->cover       = (string)($data['cover'] ?? '');
        $this->generatedOn = (string)($data['generated_on'] ?? '');
        $this->filters     = $data['filters'] ?? [];
        $this->logoPath    = $data['logo_path'] ?? null;
    }

    public function download(string $filename = null): BinaryFileResponse
    {
        while (ob_get_level() > 0) { ob_end_clean(); }

        $tmpDir = storage_path('app/tmp');
        File::ensureDirectoryExists($tmpDir);
        Settings::setTempDir($tmpDir);
        Settings::setZipClass(Settings::PCLZIP);

        $phpWord = new PhpWord();

        // Styles
        $phpWord->addFontStyle('Title', ['name'=>'Calibri','size'=>14,'bold'=>true]);
        $phpWord->addFontStyle('Sub',   ['name'=>'Calibri','size'=>11]);
        $phpWord->addFontStyle('H1',    ['name'=>'Calibri','size'=>13,'bold'=>true]);
        $phpWord->addFontStyle('H2',    ['name'=>'Calibri','size'=>12,'bold'=>true]);
        $phpWord->addFontStyle('Body',  ['name'=>'Calibri','size'=>11]);
        $phpWord->addParagraphStyle('Tight', ['spaceAfter'=>0]);
        $phpWord->addParagraphStyle('After', ['spaceAfter'=>200]);

        $section = $phpWord->addSection([
            'pageSizeW' => Converter::cmToTwip(21.0),
            'pageSizeH' => Converter::cmToTwip(29.7),
            'marginTop'    => Converter::cmToTwip(2.0),
            'marginBottom' => Converter::cmToTwip(2.0),
            'marginLeft'   => Converter::cmToTwip(2.0),
            'marginRight'  => Converter::cmToTwip(2.0),
        ]);

        // Header (text only)
        $header = $section->addHeader();
        $header->addText('Public Order and Safety Office (POSO) San Carlos City, Pangasinan.', 'Title', ['alignment'=>Jc::CENTER]);
        $header->addText($this->generatedOn, 'Sub', ['alignment'=>Jc::CENTER]);

        // --- Background logo (absolute, behind text — safer than watermark) ---
        if ($this->logoPath && is_file($this->logoPath)) {
            $section->addImage($this->logoPath, [
                'width'         => Converter::cmToPixel(12),
                'positioning'   => 'absolute',
                'posHorizontal' => \PhpOffice\PhpWord\Style\Image::POSITION_HORIZONTAL_CENTER,
                'posVertical'   => \PhpOffice\PhpWord\Style\Image::POSITION_VERTICAL_CENTER,
                'wrappingStyle' => 'behind',
            ]);
        }

        // Title
        $section->addText('Analytics Summary', 'H1', 'After');

        // Filters
        $section->addText('Filters Applied:', 'H2', 'Tight');
        foreach ($this->filters as $f) {
            $section->addText('• '.$f, 'Body', 'Tight');
        }
        $section->addTextBreak(1);

        // Coverage line (same as Excel)
        if ($this->cover !== '') {
            $section->addText("Coverage: {$this->cover}", 'Body', 'After');
        }

        // Totals
        $section->addText('Totals', 'H2', 'After');
        $section->addText("Total Paid Tickets: {$this->paid}", 'Body', 'Tight');
        $section->addText("Total Unpaid Tickets: {$this->unpaid}", 'Body', 'After');

        // Hotspots + recommendations
        $section->addText('Top Hotspots & Smart Recommendations:', 'H2', 'After');
        $used = [];
        foreach ($this->hotspots as $h) {
            // tolerant to either object or array
            $place = is_array($h) ? ($h['area'] ?? 'Unknown') : ($h->area ?? 'Unknown');
            $c     = is_array($h) ? (int)($h['c'] ?? 0)       : (int)($h->c ?? 0);

            $section->addText(
                "Hotspot: {$place} — {$c} ticket(s). ".$this->suggest($place, $c, $used),
                'Body',
                'After'
            );
        }

        // Save & stream
        $filename = $filename ?: ('POSO_Analytics_'.now()->format('Ymd_His').'.docx');
        $fullpath = $tmpDir.DIRECTORY_SEPARATOR.$filename;

        IOFactory::createWriter($phpWord, 'Word2007')->save($fullpath);

        return response()->download($fullpath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'X-Content-Type-Options' => 'nosniff',
        ])->deleteFileAfterSend(true);
    }

    private function suggest(string $place, int $count, array &$used): string
    {
        $low = [
            "Increase visibility with periodic patrols during peak hours.",
            "Coordinate with barangay officials for community reminders.",
            "Place temporary warning signage to nudge driver compliance.",
            "Conduct short awareness talks with nearby establishments.",
        ];
        $mid = [
            "Schedule randomized checkpoints in alternating time blocks.",
            "Deploy a mobile team for moving violations along approach roads.",
            "Use targeted SMS or social posts about active enforcement in the area.",
            "Coordinate with traffic aides to optimize lane management.",
        ];
        $high = [
            "Implement sustained checkpoint operations for a week, then reassess.",
            "Propose a permanent signage/road marking refresh and camera coverage.",
            "Assign a fixed post during rush hours and evaluate monthly impact.",
            "Coordinate multi-agency operation (POSO/PNP/LTO) for deterrence.",
        ];
        $pool = $count >= 10 ? array_merge($high, $mid, $low)
              : ($count >= 4  ? array_merge($mid, $low) : $low);

        foreach ($pool as $s) {
            if (!in_array($s, $used, true)) { $used[] = $s; return $s; }
        }
        $fallback = "Increase patrols and checkpoint presence near {$place}, then review after 2 weeks.";
        $used[] = $fallback;
        return $fallback;
    }
}
