<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

class EmailListAnalyzer
{
    public function countEmails(UploadedFile $file): int
    {
        $extension = strtolower($file->getClientOriginalExtension() ?? '');

        if (in_array($extension, ['xls', 'xlsx'], true)) {
            return $this->countFromSpreadsheet($file->getRealPath());
        }

        $contents = file_get_contents($file->getRealPath()) ?: '';

        return $this->countFromText($contents);
    }

    private function countFromSpreadsheet(string $path): int
    {
        $spreadsheet = IOFactory::load($path);
        $text = '';

        foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
            foreach ($worksheet->toArray() as $row) {
                foreach ($row as $cell) {
                    if (is_string($cell) || is_numeric($cell)) {
                        $text .= ' '.$cell;
                    }
                }
            }
        }

        return $this->countFromText($text);
    }

    private function countFromText(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        preg_match_all('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $text, $matches);

        return count($matches[0] ?? []);
    }
}
