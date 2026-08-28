<?php
namespace App\Services\Moderation;
class LocalImageModerationService implements ImageModerationService { public function scan(string $path):array { if(!is_file($path)) throw new \RuntimeException('Image file is unavailable.'); return ['status'=>'clean','category'=>null,'confidence'=>null,'risk_level'=>'low','reference'=>null]; } }
