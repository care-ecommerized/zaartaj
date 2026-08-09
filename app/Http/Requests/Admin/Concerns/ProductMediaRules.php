<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Catalog\MediaLimits;

/**
 * The upload rules for product media, shared by the create form and the
 * out-of-band upload endpoints on the edit screen so both accept exactly the
 * same files and report the same limits.
 */
trait ProductMediaRules
{
    /**
     * @return list<string>
     */
    protected function imageFileRules(): array
    {
        return ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:'.$this->imageMaxKb()];
    }

    /**
     * @return list<string>
     */
    protected function videoFileRules(): array
    {
        return ['file', 'mimetypes:video/mp4,video/webm,video/quicktime', 'max:'.$this->videoMaxKb()];
    }

    /**
     * @return array<string, string>
     */
    protected function mediaMessages(): array
    {
        return [
            'images.max' => 'You can upload up to :max images at a time.',
            'images.*.image' => 'Each file must be a JPG, PNG, WebP or GIF image.',
            'images.*.max' => 'An image may not be larger than '.$this->humanMb($this->imageMaxKb()).'.',
            'image.max' => 'An image may not be larger than '.$this->humanMb($this->imageMaxKb()).'.',
            'video.mimetypes' => 'The video must be an MP4, WebM or MOV file.',
            'video.max' => 'A video may not be larger than '.$this->humanMb($this->videoMaxKb()).'.',
        ];
    }

    protected function imageMaxKb(): int
    {
        return MediaLimits::imageMaxKb();
    }

    protected function videoMaxKb(): int
    {
        return MediaLimits::videoMaxKb();
    }

    protected function imageBatchMax(): int
    {
        return MediaLimits::imageBatchMax();
    }

    private function humanMb(int $kilobytes): string
    {
        return round($kilobytes / 1024).' MB';
    }
}
