<?php

namespace App\Services\Moderation;

use RuntimeException;

/**
 * Development provider. It verifies that the upload is a decodable image and
 * otherwise returns the project's transparent local-safe result. It does not
 * invent a confidence score or claim to perform AI content recognition.
 */
class LocalImageModerationService implements ImageModerationService
{
    public function scan(string $path): array
    {
        if (! is_file($path) || @getimagesize($path) === false) {
            throw new RuntimeException('Image file is unavailable or invalid.');
        }

        return ['status' => 'safe', 'category' => 'safe', 'confidence' => null, 'risk_level' => 'low', 'reference' => null];
    }
}
