<?php

declare (strict_types=1);
/*
 * (c) Jeroen van den Enden <info@endroid.nl>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace WP2FA_Vendor\Endroid\QrCode;

use WP2FA_Vendor\BaconQrCode\Common\ErrorCorrectionLevel as BaconErrorCorrectionLevel;
use WP2FA_Vendor\MyCLabs\Enum\Enum;
/**
 * @method static ErrorCorrectionLevel LOW()
 * @method static ErrorCorrectionLevel MEDIUM()
 * @method static ErrorCorrectionLevel QUARTILE()
 * @method static ErrorCorrectionLevel HIGH()
 *
 * @extends Enum<string>
 * @psalm-immutable
 */
class ErrorCorrectionLevel extends Enum
{
    const LOW = 'low';
    const MEDIUM = 'medium';
    const QUARTILE = 'quartile';
    const HIGH = 'high';
    /**
     * @psalm-suppress ImpureMethodCall
     */
    public function toBaconErrorCorrectionLevel() : BaconErrorCorrectionLevel
    {
        $name = \strtoupper(\substr($this->getValue(), 0, 1));
        return BaconErrorCorrectionLevel::valueOf($name);
    }
}
