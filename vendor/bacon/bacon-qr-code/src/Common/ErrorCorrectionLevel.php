<?php

declare (strict_types=1);
namespace WP2FA_Vendor\BaconQrCode\Common;

use WP2FA_Vendor\BaconQrCode\Exception\OutOfBoundsException;
use WP2FA_Vendor\DASPRiD\Enum\AbstractEnum;
/**
 * Enum representing the four error correction levels.
 *
 * @method static self L() ~7% correction
 * @method static self M() ~15% correction
 * @method static self Q() ~25% correction
 * @method static self H() ~30% correction
 */
final class ErrorCorrectionLevel extends AbstractEnum
{
    protected const L = [0x1];
    protected const M = [0x0];
    protected const Q = [0x3];
    protected const H = [0x2];
    /**
     * @var int
     */
    private $bits;
    protected function __construct(int $bits)
    {
        $this->bits = $bits;
    }
    /**
     * @throws OutOfBoundsException if number of bits is invalid
     */
    public static function forBits(int $bits) : self
    {
        switch ($bits) {
            case 0:
                return self::M();
            case 1:
                return self::L();
            case 2:
                return self::H();
            case 3:
                return self::Q();
        }
        throw new OutOfBoundsException('Invalid number of bits');
    }
    /**
     * Returns the two bits used to encode this error correction level.
     */
    public function getBits() : int
    {
        return $this->bits;
    }
}
