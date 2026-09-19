<?php

namespace App\Services\Moderation;

use RuntimeException;

class ConfigurableImageModerationService implements ImageModerationService
{
    public function __construct(private LocalImageModerationService $local) {}

    public function scan(string $path): array
    {
        $provider = config('services.image_moderation.provider');
        if ($provider === 'local' && config('services.image_moderation.local_enabled')) {
            return $this->local->scan($path);
        }

        throw new RuntimeException('Configured image moderation provider is unavailable.');
    }
}
