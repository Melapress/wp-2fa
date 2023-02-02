<?php

declare (strict_types=1);
namespace WP2FA_Vendor\BaconQrCode\Renderer;

use WP2FA_Vendor\BaconQrCode\Encoder\QrCode;
interface RendererInterface
{
    public function render(QrCode $qrCode) : string;
}
