<?php

namespace App\Jobs;

use App\Models\Filelist;
use App\Services\DocumentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use Throwable;

class ProcessAlihMediaWatermarkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const WATERMARK_OPACITY = 0.15;

    private const WATERMARK_MAX_WIDTH = 120;

    private const WATERMARK_WIDTH_RATIO = 0.44;

    public $timeout = 900;

    public $tries = 1;

    private $filelistId;

    public function __construct(int $filelistId)
    {
        $this->filelistId = $filelistId;
    }

    public function handle(DocumentService $documents)
    {
        $berkas = Filelist::with(['incomings', 'outcomings'])->find($this->filelistId);
        if (! $berkas) {
            Log::warning('Alih media gagal: berkas tidak ditemukan', ['filelist_id' => $this->filelistId]);

            return;
        }

        $berkas->alih_media_status_id = Filelist::ALIH_MEDIA_PROCESSING;
        $berkas->save();

        $items = collect()
            ->merge($berkas->incomings->map(function ($item) {
                return [
                    'jenis' => 'masuk',
                    'data' => $item,
                    'tanggal_item' => $item->tanggal_surat,
                    'nomor_naskah' => $item->nomor_surat,
                ];
            }))
            ->merge($berkas->outcomings->map(function ($item) {
                return [
                    'jenis' => 'keluar',
                    'data' => $item,
                    'tanggal_item' => $item->tanggal_surat,
                    'nomor_naskah' => $item->nomor_surat,
                ];
            }))
            ->sortBy([
                ['tanggal_item', 'asc'],
                ['nomor_naskah', 'asc'],
            ])
            ->values();

        $failedCount = 0;

        foreach ($items as $index => $item) {
            $surat = $item['data'];
            $jenis = $item['jenis'];

            if ($surat->hasExistingWatermarkedFile()) {
                continue;
            }

            $sourceUrl = $documents->path(
                $jenis,
                $surat,
                DocumentService::VARIANT_ORIGINAL
            );
            if ($sourceUrl === null) {
                $failedCount++;
                Log::warning('Alih media dilewati: path PDF tidak aman', [
                    'filelist_id' => $berkas->id,
                    'jenis' => $jenis,
                    'surat_id' => $surat->id,
                    'path' => $surat->url,
                ]);

                continue;
            }

            $documentDisk = Storage::disk(config('documents.disk'));
            $sourcePath = $documentDisk->path($sourceUrl);
            if (! is_file($sourcePath)) {
                $failedCount++;
                Log::warning('Alih media dilewati: file PDF tidak ditemukan', [
                    'filelist_id' => $berkas->id,
                    'jenis' => $jenis,
                    'surat_id' => $surat->id,
                    'path' => $surat->url,
                ]);

                continue;
            }

            $targetUrl = 'dokumen/alih-media/'.$this->makeBerkasFolderName($berkas).'/'.$this->makeWatermarkedFileName($index + 1, $surat);
            $targetPath = $documentDisk->path($targetUrl);

            try {
                $this->watermarkPdf($sourcePath, $targetPath);
                $surat->url_watermarked = $targetUrl;
                $surat->save();
            } catch (Throwable $th) {
                $failedCount++;
                Log::error('Alih media gagal memproses PDF', [
                    'filelist_id' => $berkas->id,
                    'jenis' => $jenis,
                    'surat_id' => $surat->id,
                    'message' => $th->getMessage(),
                ]);
            }
        }

        $berkas->refresh();
        $berkas->load(['incomings', 'outcomings']);

        $totalIsi = $berkas->incomings->count() + $berkas->outcomings->count();
        $totalWatermarked = $berkas->incomings->filter(function ($surat) {
            return $surat->hasExistingWatermarkedFile();
        })->count()
            + $berkas->outcomings->filter(function ($surat) {
                return $surat->hasExistingWatermarkedFile();
            })->count();

        $berkas->alih_media_status_id = ($totalIsi > 0 && $totalIsi === $totalWatermarked && $failedCount === 0)
            ? Filelist::ALIH_MEDIA_DONE
            : Filelist::ALIH_MEDIA_FAILED;
        $berkas->save();
    }

    public function failed(Throwable $exception): void
    {
        Filelist::where('id', $this->filelistId)->update([
            'alih_media_status_id' => Filelist::ALIH_MEDIA_FAILED,
        ]);

        Log::error('Alih media gagal total', [
            'filelist_id' => $this->filelistId,
            'message' => $exception->getMessage(),
        ]);
    }

    private function makeBerkasFolderName(Filelist $berkas): string
    {
        return $berkas->id.'_'.$this->cleanPathSegment($berkas->nama_berkas ?: 'berkas');
    }

    private function makeWatermarkedFileName(int $number, $surat): string
    {
        $perihal = $this->takeWords((string) ($surat->perihal ?? 'surat'), 15);
        $fileName = $number.'_'.$this->cleanPathSegment($perihal ?: 'surat');

        return $fileName.'.pdf';
    }

    private function takeWords(string $text, int $limit): string
    {
        $words = preg_split('/\s+/', trim($text));
        $words = array_filter($words, function ($word) {
            return $word !== '';
        });

        return implode(' ', array_slice($words, 0, $limit));
    }

    private function cleanPathSegment(string $text): string
    {
        $text = strip_tags($text);
        $text = preg_replace('/[\\\\\/:*?"<>|]+/', ' ', $text);
        $text = preg_replace('/[^A-Za-z0-9 ._-]+/', ' ', $text);
        $text = preg_replace('/\s+/', '_', trim($text));
        $text = trim($text, '._-');

        if ($text === '') {
            return 'file';
        }

        return substr($text, 0, 120);
    }

    private function watermarkPdf(string $sourcePath, string $targetPath): void
    {
        $watermarkPath = $this->makeWatermarkImage();
        [$watermarkWidthPx, $watermarkHeightPx] = getimagesize($watermarkPath);

        $pdf = new Fpdi;
        $pageCount = $pdf->setSourceFile($sourcePath);

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($templateId);
            $orientation = $size['width'] > $size['height'] ? 'L' : 'P';

            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);

            $imageWidth = min($size['width'] * self::WATERMARK_WIDTH_RATIO, self::WATERMARK_MAX_WIDTH);
            $imageHeight = $imageWidth * ($watermarkHeightPx / $watermarkWidthPx);
            $x = ($size['width'] - $imageWidth) / 2;
            $y = ($size['height'] - $imageHeight) / 2;

            $imageType = strtoupper(pathinfo($watermarkPath, PATHINFO_EXTENSION));
            $pdf->Image($watermarkPath, $x, $y, $imageWidth, $imageHeight, $imageType);
        }

        $targetDirectory = dirname($targetPath);
        if (! is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0775, true);
        }

        $pdf->Output($targetPath, 'F');
    }

    private function makeWatermarkImage(): string
    {
        if (! extension_loaded('gd')) {
            throw new \RuntimeException('Ekstensi GD belum aktif');
        }

        $sourcePath = public_path('gambar/logo-watermark.png');
        if (! is_file($sourcePath)) {
            throw new \RuntimeException('Gambar watermark public/gambar/logo-watermark.png tidak ditemukan');
        }

        $info = getimagesize($sourcePath);
        if (! $info) {
            throw new \RuntimeException('Gambar watermark tidak valid');
        }

        if ($info[2] === IMAGETYPE_PNG) {
            $source = imagecreatefrompng($sourcePath);
        } elseif ($info[2] === IMAGETYPE_JPEG) {
            $source = imagecreatefromjpeg($sourcePath);
        } else {
            throw new \RuntimeException('Format gambar watermark harus PNG atau JPG');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $watermark = imagecreatetruecolor($width, $height);
        imagealphablending($watermark, false);
        imagesavealpha($watermark, true);

        $transparent = imagecolorallocatealpha($watermark, 255, 255, 255, 127);
        imagefilledrectangle($watermark, 0, 0, $width, $height, $transparent);

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $rgba = imagecolorat($source, $x, $y);
                $colors = imagecolorsforindex($source, $rgba);
                $sourceOpacity = (127 - $colors['alpha']) / 127;
                $alpha = 127 - (int) round(127 * self::WATERMARK_OPACITY * $sourceOpacity);
                $color = imagecolorallocatealpha($watermark, $colors['red'], $colors['green'], $colors['blue'], $alpha);
                imagesetpixel($watermark, $x, $y, $color);
            }
        }

        $targetDirectory = storage_path('app/tmp');
        if (! is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0775, true);
        }

        $targetPath = $targetDirectory.'/alih-media-watermark.png';
        imagepng($watermark, $targetPath, 6);
        imagedestroy($source);
        imagedestroy($watermark);

        return $targetPath;
    }
}
