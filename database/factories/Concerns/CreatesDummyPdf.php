<?php

namespace Database\Factories\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use UnexpectedValueException;

trait CreatesDummyPdf
{
    protected function dummyPdfPath(string $directory, string $prefix): string
    {
        return trim($directory, '/').'/'.$prefix.'-'.Str::uuid().'.pdf';
    }

    protected function createDummyPdf(Model $model, string $directory): void
    {
        $directory = trim($directory, '/');
        $path = str_replace('\\', '/', (string) $model->getAttribute('url'));

        if (strpos($path, $directory.'/') !== 0
            || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'pdf') {
            throw new UnexpectedValueException(
                'Factory PDF hanya boleh menulis berkas .pdf di direktori '.$directory.'.'
            );
        }

        $disk = Storage::disk(config('documents.disk'));

        if (! $disk->exists($path) && ! $disk->put($path, $this->blankPdfContent())) {
            throw new RuntimeException('Factory gagal membuat PDF dummy: '.$path);
        }
    }

    private function blankPdfContent(): string
    {
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << >> /Contents 4 0 R >>',
            "<< /Length 0 >>\nstream\n\nendstream",
        ];

        $content = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($content);
            $number = $index + 1;
            $content .= $number." 0 obj\n".$object."\nendobj\n";
        }

        $xrefOffset = strlen($content);
        $content .= "xref\n0 ".(count($objects) + 1)."\n";
        $content .= "0000000000 65535 f \n";

        for ($index = 1; $index <= count($objects); $index++) {
            $content .= sprintf('%010d 00000 n ', $offsets[$index])."\n";
        }

        return $content
            ."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\n"
            ."startxref\n".$xrefOffset."\n%%EOF\n";
    }
}
