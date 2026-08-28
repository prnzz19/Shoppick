<?php
namespace App\Services\Moderation;
class ConfigurableImageModerationService implements ImageModerationService { public function __construct(private LocalImageModerationService $local){} public function scan(string $path):array { $provider=config('services.image_moderation.provider','local'); if($provider==='local'||blank($provider)) return $this->local->scan($path); throw new \RuntimeException('Configured image moderation provider is unavailable.'); } }
