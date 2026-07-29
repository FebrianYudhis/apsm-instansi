<?php

namespace App\Services;

use App\Models\Digital;
use App\Models\Incoming;
use App\Models\Outcoming;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Throwable;

class DocumentService
{
    public const TYPE_INCOMING = 'masuk';

    public const TYPE_OUTGOING = 'keluar';

    public const TYPE_DIGITAL = 'digital';

    public const VARIANT_DISPLAY = 'tampil';

    public const VARIANT_ORIGINAL = 'asli';

    public const VARIANT_WATERMARK = 'watermark';

    private const DOWNLOAD_SUBJECT_WORD_LIMIT = 6;

    private const MODELS = [
        self::TYPE_INCOMING => Incoming::class,
        self::TYPE_OUTGOING => Outcoming::class,
        self::TYPE_DIGITAL => Digital::class,
    ];

    private const ORIGINAL_ROOTS = [
        self::TYPE_INCOMING => 'dokumen/masuk',
        self::TYPE_OUTGOING => 'dokumen/keluar',
        self::TYPE_DIGITAL => 'dokumen/digital',
    ];

    public function find(string $type, int $id): ?Model
    {
        $model = self::MODELS[$type] ?? null;

        return $model ? $model::find($id) : null;
    }

    public function storeOriginal(string $type, UploadedFile $file): string
    {
        $root = self::ORIGINAL_ROOTS[$type] ?? null;
        if ($root === null) {
            throw new InvalidArgumentException('Jenis dokumen tidak dikenal.');
        }

        $diskName = config('documents.disk');
        $path = null;
        $stored = false;

        try {
            $path = $file->store($root, $diskName);
            $path = $this->normalizePath($path);
            $stored = $path !== null
                && $this->isExpectedPath($type, self::VARIANT_ORIGINAL, $path)
                && Storage::disk($diskName)->exists($path);
        } catch (Throwable $exception) {
            report($exception);
        }

        if (! $stored) {
            if (
                $path !== null
                && $this->isExpectedPath($type, self::VARIANT_ORIGINAL, $path)
            ) {
                try {
                    Storage::disk($diskName)->delete($path);
                } catch (Throwable $exception) {
                    report($exception);
                }
            }

            throw ValidationException::withMessages([
                'berkas' => 'PDF gagal disimpan. Periksa kapasitas dan izin storage, lalu coba kembali.',
            ]);
        }

        return $path;
    }

    public function isGuestPublic(string $type, Model $document): bool
    {
        if ($type === self::TYPE_DIGITAL) {
            return true;
        }

        return method_exists($document, 'isPubliclyAccessible')
            && $document->isPubliclyAccessible();
    }

    public function path(string $type, Model $document, string $variant = self::VARIANT_DISPLAY): ?string
    {
        if ($variant === self::VARIANT_WATERMARK) {
            $path = $document->url_watermarked ?? null;
        } elseif ($variant === self::VARIANT_ORIGINAL) {
            $path = $document->url ?? null;
        } else {
            $path = ($document->url_watermarked ?? null) ?: ($document->url ?? null);
            $variant = ! empty($document->url_watermarked)
                ? self::VARIANT_WATERMARK
                : self::VARIANT_ORIGINAL;
        }

        $path = $this->normalizePath($path);
        if ($path === null || ! $this->isExpectedPath($type, $variant, $path)) {
            return null;
        }

        return $path;
    }

    public function exists(string $type, Model $document, string $variant = self::VARIANT_DISPLAY): bool
    {
        $path = $this->path($type, $document, $variant);

        return $path !== null
            && Storage::disk(config('documents.disk'))->exists($path);
    }

    public function delete(string $type, $path, string $variant = self::VARIANT_ORIGINAL): bool
    {
        $path = $this->normalizePath($path);
        if ($path === null || ! $this->isExpectedPath($type, $variant, $path)) {
            return false;
        }

        return Storage::disk(config('documents.disk'))->delete($path);
    }

    public function response(
        string $type,
        Model $document,
        string $variant = self::VARIANT_DISPLAY
    ): BinaryFileResponse {
        $path = $this->path($type, $document, $variant);
        abort_if($path === null, 404);

        $disk = Storage::disk(config('documents.disk'));
        abort_unless($disk->exists($path), 404);

        $response = response()->file($disk->path($path), [
            'Content-Type' => 'application/pdf',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $this->downloadFileName($document, $variant)
        );

        $response->setPrivate();
        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('no-store');
        $response->headers->addCacheControlDirective('no-cache');

        return $response;
    }

    public function descriptiveFileName(
        Model $document,
        string $variant = self::VARIANT_ORIGINAL
    ): string {
        return $this->fileNamePrefix($document, $variant)
            .$this->subjectFileName($document).'.pdf';
    }

    private function downloadFileName(Model $document, string $variant): string
    {
        return $this->fileNamePrefix($document, $variant)
            .$this->subjectFileName($document)
            .'-'.now()->format('dmYHis').'.pdf';
    }

    private function fileNamePrefix(Model $document, string $variant): string
    {
        $usesWatermark = $variant === self::VARIANT_WATERMARK
            || ($variant === self::VARIANT_DISPLAY && ! empty($document->url_watermarked));

        return $usesWatermark ? 'wm-' : '';
    }

    private function subjectFileName(Model $document): string
    {
        $words = preg_split(
            '/\s+/u',
            trim((string) ($document->perihal ?? '')),
            -1,
            PREG_SPLIT_NO_EMPTY
        ) ?: [];
        $subject = Str::slug(
            implode(' ', array_slice($words, 0, self::DOWNLOAD_SUBJECT_WORD_LIMIT)),
            '-'
        );

        return $subject ?: 'dokumen';
    }

    public function adminUrl(string $type, Model $document, string $variant = self::VARIANT_DISPLAY): string
    {
        return route('document.admin', [
            'jenis' => $type,
            'id' => $document->getKey(),
            'versi' => $variant,
            'nama' => $this->descriptiveFileName($document, $variant),
        ]);
    }

    public function publicUrl(string $type, Model $document): string
    {
        return route('document.public', [
            'jenis' => $type,
            'id' => $document->getKey(),
            'nama' => $this->descriptiveFileName($document, self::VARIANT_DISPLAY),
        ]);
    }

    public function grantSessionKey(string $type, int $id): string
    {
        return "document_grants.{$type}.{$id}";
    }

    public function normalizePath($path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $path = ltrim(str_replace('\\', '/', trim($path)), '/');
        if ($path === ''
            || strpos($path, "\0") !== false
            || preg_match('#(^|/)\.\.(/|$)#', $path)
            || preg_match('#^[A-Za-z][A-Za-z0-9+.-]*:#', $path)) {
            return null;
        }

        return preg_replace('#/+#', '/', $path);
    }

    private function isExpectedPath(string $type, string $variant, string $path): bool
    {
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'pdf') {
            return false;
        }

        $root = $variant === self::VARIANT_WATERMARK
            ? 'dokumen/alih-media'
            : (self::ORIGINAL_ROOTS[$type] ?? null);

        return $root !== null
            && ($path === $root || strpos($path, $root.'/') === 0);
    }
}
