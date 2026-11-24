<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Modules\Fis\Models\AircraftRegistration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class FisReportSettingsController extends Controller
{
    private const DEFAULT_HEADER_TITLE = "Flight\nInformation\nSheet";

    public function show(): JsonResponse
    {
        $settings = DB::table('fis_report_settings')->where('id', 1)->first();

        return response()->json([
            'header_title' => $settings->header_title ?? self::DEFAULT_HEADER_TITLE,
            'has_custom_logo' => filled($settings->logo_path ?? null),
        ]);
    }

    public function logo(): StreamedResponse
    {
        $path = DB::table('fis_report_settings')->where('id', 1)->value('logo_path');
        abort_if(! $path || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response(
            $path,
            headers: ['Cache-Control' => 'private, no-store'],
        );
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'header_title' => ['required', 'string', 'max:150'],
            'logo' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/svg+xml,image/webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ], [
            'logo.mimetypes' => 'Logo harus berupa file JPG, PNG, SVG, atau WebP yang valid.',
        ]);

        $settings = DB::table('fis_report_settings')->where('id', 1)->first();
        $oldLogoPath = $settings->logo_path ?? null;
        $logoPath = ($validated['remove_logo'] ?? false) ? null : $oldLogoPath;
        $newLogoPath = null;

        if ($request->hasFile('logo')) {
            $logo = $request->file('logo');
            if ($logo->getMimeType() === 'image/svg+xml') {
                $this->validateSvg($logo, 'logo');
            }

            $newLogoPath = $this->storeImage($logo, 'fis/report', 'logo');
            $logoPath = $newLogoPath;
        }

        $now = now();

        try {
            DB::transaction(fn () => DB::table('fis_report_settings')->updateOrInsert(
                ['id' => 1],
                [
                    'header_title' => $validated['header_title'],
                    'logo_path' => $logoPath,
                    'created_at' => $settings->created_at ?? $now,
                    'updated_at' => $now,
                ],
            ));
        } catch (Throwable $exception) {
            if ($newLogoPath !== null && ! Storage::disk('local')->delete($newLogoPath)) {
                report(new RuntimeException('Logo report FIS gagal dibersihkan setelah penyimpanan gagal.'));
            }

            throw $exception;
        }

        if ($oldLogoPath !== null && $oldLogoPath !== $logoPath && ! Storage::disk('local')->delete($oldLogoPath)) {
            throw new RuntimeException('Logo report FIS sebelumnya gagal dihapus.');
        }

        return response()->json([
            'header_title' => $validated['header_title'],
            'has_custom_logo' => $logoPath !== null,
        ]);
    }

    public function aircraft(): JsonResponse
    {
        return response()->json(
            AircraftRegistration::query()
                ->orderBy('registration')
                ->get(['id', 'registration', 'aircraft_type', 'seat_capacity', 'report_image_path'])
                ->map(fn (AircraftRegistration $aircraft): array => [
                    'id' => $aircraft->id,
                    'registration' => $aircraft->registration,
                    'aircraft_type' => $aircraft->aircraft_type,
                    'seat_capacity' => $aircraft->seat_capacity,
                    'has_report_image' => $aircraft->report_image_path !== null,
                ]),
        );
    }

    public function updateAircraftImage(Request $request, AircraftRegistration $aircraft): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/svg+xml,image/webp', 'max:5120'],
        ], [
            'image.mimetypes' => 'Gambar pesawat harus berupa file JPG, PNG, SVG, atau WebP yang valid.',
        ]);
        $image = $validated['image'];
        if ($image->getMimeType() === 'image/svg+xml') {
            $this->validateSvg($image, 'image');
        }

        $newImagePath = $this->storeImage($image, "fis/aircraft/{$aircraft->id}", 'image');

        $oldImagePath = $aircraft->report_image_path;

        try {
            DB::transaction(function () use ($aircraft, $newImagePath): void {
                if (! $aircraft->forceFill(['report_image_path' => $newImagePath])->save()) {
                    throw new RuntimeException('Gambar report pesawat gagal diperbarui.');
                }
            });
        } catch (Throwable $exception) {
            if (! Storage::disk('local')->delete($newImagePath)) {
                report(new RuntimeException('Gambar pesawat gagal dibersihkan setelah penyimpanan gagal.'));
            }

            throw $exception;
        }

        if ($oldImagePath !== null && ! Storage::disk('local')->delete($oldImagePath)) {
            throw new RuntimeException('Gambar pesawat sebelumnya gagal dihapus.');
        }

        return response()->json([
            'id' => $aircraft->id,
            'has_report_image' => true,
        ]);
    }

    public function removeAircraftImage(AircraftRegistration $aircraft): JsonResponse
    {
        $oldImagePath = $aircraft->report_image_path;
        if ($oldImagePath === null) {
            return response()->json([
                'id' => $aircraft->id,
                'has_report_image' => false,
            ]);
        }

        if (! $aircraft->forceFill(['report_image_path' => null])->save()) {
            throw new RuntimeException('Gambar report pesawat gagal dihapus.');
        }

        if (! Storage::disk('local')->delete($oldImagePath)) {
            throw new RuntimeException('File gambar pesawat gagal dihapus.');
        }

        return response()->json([
            'id' => $aircraft->id,
            'has_report_image' => false,
        ]);
    }

    private function validateSvg(UploadedFile $file, string $field): void
    {
        $contents = file_get_contents($file->getPathname());
        $document = new \DOMDocument;
        $previousErrorHandling = libxml_use_internal_errors(true);

        try {
            $isValidSvg = $contents !== false
                && trim($contents) !== ''
                && $document->loadXML($contents, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrorHandling);
        }

        $svg = $document->documentElement;
        if (
            ! $isValidSvg
            || $document->doctype !== null
            || $svg?->localName !== 'svg'
            || $svg->namespaceURI !== 'http://www.w3.org/2000/svg'
        ) {
            throw ValidationException::withMessages([
                $field => 'File SVG tidak valid.',
            ]);
        }

        $blockedElements = [
            'animate',
            'animatemotion',
            'animatetransform',
            'audio',
            'foreignobject',
            'iframe',
            'image',
            'script',
            'set',
            'style',
            'video',
        ];

        foreach ($document->childNodes as $node) {
            if ($node->nodeType === XML_PI_NODE) {
                throw ValidationException::withMessages([
                    $field => 'SVG tidak boleh memuat referensi eksternal.',
                ]);
            }
        }

        foreach ($document->getElementsByTagName('*') as $element) {
            if (in_array(strtolower($element->localName), $blockedElements, true)) {
                throw ValidationException::withMessages([
                    $field => 'SVG berisi elemen yang tidak didukung.',
                ]);
            }

            foreach ($element->attributes as $attribute) {
                $name = strtolower($attribute->localName ?: $attribute->name);
                $value = trim($attribute->value);

                if (
                    str_starts_with($name, 'on')
                    || ($name === 'href' && ! str_starts_with($value, '#'))
                    || preg_match('/url\s*\(\s*([\'"]?)(?!#)/i', $value)
                    || preg_match('/@import|expression\s*\(/i', $value)
                ) {
                    throw ValidationException::withMessages([
                        $field => 'SVG tidak boleh memuat script atau referensi eksternal.',
                    ]);
                }
            }
        }
    }

    private function storeImage(UploadedFile $file, string $directory, string $field): string
    {
        if ($file->getMimeType() !== 'image/webp') {
            $path = $file->store($directory, 'local');
            if ($path === false) {
                throw new RuntimeException('Gambar report FIS gagal disimpan.');
            }

            return $path;
        }

        $imageInfo = getimagesize($file->getPathname());
        if (
            $imageInfo === false
            || $imageInfo[2] !== IMAGETYPE_WEBP
            || $imageInfo[0] > 4096
            || $imageInfo[1] > 4096
            || $imageInfo[0] * $imageInfo[1] > 16000000
            || ! function_exists('imagecreatefromwebp')
        ) {
            throw ValidationException::withMessages([
                $field => 'Dimensi atau encoding WebP tidak didukung.',
            ]);
        }

        $image = imagecreatefromwebp($file->getPathname());
        if ($image === false) {
            throw ValidationException::withMessages([
                $field => 'File WebP tidak valid.',
            ]);
        }

        imagesavealpha($image, true);
        ob_start();
        $encoded = imagepng($image);
        $contents = ob_get_clean();
        imagedestroy($image);

        if (! $encoded || $contents === false) {
            throw new RuntimeException('Gambar WebP gagal dikonversi ke PNG.');
        }

        $path = $directory.'/'.Str::uuid().'.png';
        if (! Storage::disk('local')->put($path, $contents)) {
            throw new RuntimeException('Gambar PNG hasil konversi gagal disimpan.');
        }

        return $path;
    }
}
