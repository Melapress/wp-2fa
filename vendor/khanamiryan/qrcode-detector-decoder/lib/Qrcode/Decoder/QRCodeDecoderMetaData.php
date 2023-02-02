<?php

namespace WP2FA_Vendor\Zxing\Qrcode\Decoder;

class QRCodeDecoderMetaData
{
    /**
     * QRCodeDecoderMetaData constructor.
     * @param bool $mirrored
     */
    public function __construct(private $mirrored)
    {
    }
    public function isMirrored()
    {
        return $this->mirrored;
    }
}
