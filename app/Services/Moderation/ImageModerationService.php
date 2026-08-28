<?php
namespace App\Services\Moderation;
interface ImageModerationService { public function scan(string $absolutePath): array; }
