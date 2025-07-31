<?php

declare (strict_types=1);
namespace WP2FA_Vendor\BaconQrCode\Common;

use WP2FA_Vendor\DASPRiD\Enum\AbstractEnum;
/**
 * Enum representing various modes in which data can be encoded to bits.
 *
 * @method static self TERMINATOR()
 * @method static self NUMERIC()
 * @method static self ALPHANUMERIC()
 * @method static self STRUCTURED_APPEND()
 * @method static self BYTE()
 * @method static self ECI()
 * @method static self KANJI()
 * @method static self FNC1_FIRST_POSITION()
 * @method static self FNC1_SECOND_POSITION()
 * @method static self HANZI()
 */
final class Mode extends AbstractEnum
{
    protected const TERMINATOR = [[0, 0, 0], 0x0];
    protected const NUMERIC = [[10, 12, 14], 0x1];
    protected const ALPHANUMERIC = [[9, 11, 13], 0x2];
    protected const STRUCTURED_APPEND = [[0, 0, 0], 0x3];
    protected const BYTE = [[8, 16, 16], 0x4];
    protected const ECI = [[0, 0, 0], 0x7];
    protected const KANJI = [[8, 10, 12], 0x8];
    protected const FNC1_FIRST_POSITION = [[0, 0, 0], 0x5];
    protected const FNC1_SECOND_POSITION = [[0, 0, 0], 0x9];
    protected const HANZI = [[8, 10, 12], 0xd];
    /**
     * @var int[]
     */
    private $characterCountBitsForVersions;
    /**
     * @var int
     */
    private $bits;
    /**
     * @param int[] $characterCountBitsForVersions
     */
    protected function __construct(array $characterCountBitsForVersions, int $bits)
    {
        $this->characterCountBitsForVersions = $characterCountBitsForVersions;
        $this->bits = $bits;
    }
    /**
     * Returns the number of bits used in a specific QR code version.
     */
    public function getCharacterCountBits(Version $version) : int
    {
        $number = $version->getVersionNumber();
        if ($number <= 9) {
            $offset = 0;
        } elseif ($number <= 26) {
            $offset = 1;
        } else {
            $offset = 2;
        }
        return $this->characterCountBitsForVersions[$offset];
    }
    /**
     * Returns the four bits used to encode this mode.
     */
    public function getBits() : int
    {
        return $this->bits;
    }
}
