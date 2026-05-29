<?php

namespace App\Services;

use App\Core\Models\Customer;
use App\Core\Models\Frame;
use App\Core\Models\FrameRender;
use App\Helpers\FileHelper;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Exceptions\ImageException;

class FrameRenderService
{
    protected FileHelper $fileHelper;
    protected ImageManager $imageManager;

    public function __construct(FileHelper $fileHelper)
    {
        $this->fileHelper = $fileHelper;
        $this->imageManager = new ImageManager(new Driver());
    }

    /**
     * Render a frame on an uploaded image.
     *
     * @param Customer|null $customer
     * @param Frame $frame
     * @param UploadedFile $image
     * @param array $options
     * @return array
     * @throws \Exception
     */
    public function render(?Customer $customer, Frame $frame, UploadedFile $image, array $options = []): array
    {
        // Validate frame
        $this->validateFrame($frame);

        // Parse options
        $outputSize = $options['output_size'] ?? '1080x1080';
        $format = $options['format'] ?? 'jpg';
        
        // Parse output size
        [$width, $height] = $this->parseOutputSize($outputSize);

        // Upload original image
        $originalPath = $this->fileHelper->uploadImage(
            $image,
            'frames/originals',
            'public',
            10240 // 10MB max
        );

        if (!$originalPath) {
            throw new \Exception('Failed to upload image');
        }

        // Process image
        $fullOriginalPath = Storage::disk('public')->path($originalPath);
        $processedImage = $this->processImage($fullOriginalPath, ['width' => $width, 'height' => $height]);

        // Apply overlay
        $overlayPath = Storage::disk('public')->path($frame->overlay_path);
        if (!Storage::disk('public')->exists($frame->overlay_path)) {
            throw new \Exception('Overlay file not found');
        }

        $renderedImage = $this->applyOverlay($processedImage, $overlayPath, $width, $height);

        // Save rendered image
        $renderedPath = $this->saveRenderedImage($renderedImage, $format);

        // Create frame render record
        $frameRender = FrameRender::create([
            'customer_id' => $customer?->id,
            'frame_id' => $frame->id,
            'original_image_path' => $originalPath,
            'rendered_image_path' => $renderedPath,
            'width' => $width,
            'height' => $height,
            'format' => $format,
        ]);

        // Get URLs
        $fileHelper = new FileHelper();
        $renderedUrl = $fileHelper->getUrl($renderedPath, 'public');
        $originalUrl = $fileHelper->getUrl($originalPath, 'public');

        return [
            'rendered_path' => $renderedPath,
            'original_path' => $originalPath,
            'rendered_url' => $renderedUrl,
            'original_url' => $originalUrl,
            'width' => $width,
            'height' => $height,
            'format' => $format,
            'frame_render_id' => $frameRender->id,
        ];
    }

    /**
     * Validate frame is active and valid.
     *
     * @param Frame $frame
     * @return void
     * @throws \Exception
     */
    protected function validateFrame(Frame $frame): void
    {
        if (!$frame->is_active) {
            throw new \Exception('Frame is not active');
        }

        if (!$frame->isCurrentlyValid()) {
            throw new \Exception('Frame is not currently available');
        }

        if (!$frame->overlay_path || !Storage::disk('public')->exists($frame->overlay_path)) {
            throw new \Exception('Frame overlay file not found');
        }
    }

    /**
     * Parse output size string (e.g., "1080x1080").
     *
     * @param string|null $outputSize
     * @return array [width, height]
     */
    protected function parseOutputSize(?string $outputSize): array
    {
        if (!$outputSize || !preg_match('/^(\d+)x(\d+)$/', $outputSize, $matches)) {
            return [1080, 1080]; // Default
        }

        return [(int) $matches[1], (int) $matches[2]];
    }

    /**
     * Process uploaded image (resize/crop).
     *
     * @param string $imagePath
     * @param array $options
     * @return \Intervention\Image\Image
     * @throws ImageException
     */
    protected function processImage(string $imagePath, array $options): \Intervention\Image\Image
    {
        $width = $options['width'] ?? 1080;
        $height = $options['height'] ?? 1080;

        $image = $this->imageManager->read($imagePath);

        // Resize and crop to fit (maintain aspect ratio, center crop). Intervention
        // Image v3 honors EXIF orientation on read, so no manual orient step is needed
        // (the v2 orientate() method no longer exists and would fatal).
        $image->cover($width, $height);

        return $image;
    }

    /**
     * Apply overlay on top of base image.
     *
     * @param \Intervention\Image\Image $baseImage
     * @param string $overlayPath
     * @param int $width
     * @param int $height
     * @return \Intervention\Image\Image
     * @throws ImageException
     */
    protected function applyOverlay(
        \Intervention\Image\Image $baseImage,
        string $overlayPath,
        int $width,
        int $height
    ): \Intervention\Image\Image {
        // Load overlay
        $overlay = $this->imageManager->read($overlayPath);

        // Resize overlay to match output dimensions if needed
        if ($overlay->width() !== $width || $overlay->height() !== $height) {
            $overlay->cover($width, $height);
        }

        // Place overlay on top of base image (preserve transparency)
        $baseImage->place($overlay, 'top-left', 0, 0);

        return $baseImage;
    }

    /**
     * Save rendered image to storage.
     *
     * @param \Intervention\Image\Image $image
     * @param string $format
     * @return string Path relative to storage/app/public
     */
    protected function saveRenderedImage(\Intervention\Image\Image $image, string $format): string
    {
        $directory = 'frames/renders';
        $filename = \Illuminate\Support\Str::uuid() . '.' . $format;
        $path = $directory . '/' . $filename;

        // Encode image
        if ($format === 'png') {
            $encoded = $image->encode(new \Intervention\Image\Encoders\PngEncoder());
        } else {
            $encoded = $image->encode(new \Intervention\Image\Encoders\JpegEncoder(quality: 90));
        }

        // Save to storage
        Storage::disk('public')->put($path, $encoded);

        return $path;
    }
}

