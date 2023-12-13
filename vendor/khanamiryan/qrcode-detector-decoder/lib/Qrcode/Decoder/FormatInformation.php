<?php

/*
 * Copyright 2007 ZXing authors
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace WP2FA_Vendor\Zxing\Qrcode\Decoder;

/**
 * <p>Encapsulates a QR Code's format information, including the data mask used and
 * error correction level.</p>
 *
 * @author Sean Owen
 * @see    DataMask
 * @see    ErrorCorrectionLevel
 */
final class FormatInformation
{
    public static $FORMAT_INFO_MASK_QR;
    /**
     * See ISO 18004:2006, Annex C, Table C.1
     */
    public static $FORMAT_INFO_DECODE_LOOKUP;
    /**
     * Offset i holds the number of 1 bits in the binary representation of i
     * @var int[]|null
     */
    private static ?array $BITS_SET_IN_HALF_BYTE = null;
    private readonly \WP2FA_Vendor\Zxing\Qrcode\Decoder\ErrorCorrectionLevel $errorCorrectionLevel;
    private readonly int $dataMask;
    private function __construct($formatInfo)
    {
        // Bits 3,4
        $this->errorCorrectionLevel = ErrorCorrectionLevel::forBits($formatInfo >> 3 & 0x3);
        // Bottom 3 bits
        $this->dataMask = $formatInfo & 0x7;
        //(byte)
    }
    public static function Init() : void
    {
        self::$FORMAT_INFO_MASK_QR = 0x5412;
        self::$BITS_SET_IN_HALF_BYTE = [0, 1, 1, 2, 1, 2, 2, 3, 1, 2, 2, 3, 2, 3, 3, 4];
        self::$FORMAT_INFO_DECODE_LOOKUP = [[0x5412, 0x0], [0x5125, 0x1], [0x5e7c, 0x2], [0x5b4b, 0x3], [0x45f9, 0x4], [0x40ce, 0x5], [0x4f97, 0x6], [0x4aa0, 0x7], [0x77c4, 0x8], [0x72f3, 0x9], [0x7daa, 0xa], [0x789d, 0xb], [0x662f, 0xc], [0x6318, 0xd], [0x6c41, 0xe], [0x6976, 0xf], [0x1689, 0x10], [0x13be, 0x11], [0x1ce7, 0x12], [0x19d0, 0x13], [0x762, 0x14], [0x255, 0x15], [0xd0c, 0x16], [0x83b, 0x17], [0x355f, 0x18], [0x3068, 0x19], [0x3f31, 0x1a], [0x3a06, 0x1b], [0x24b4, 0x1c], [0x2183, 0x1d], [0x2eda, 0x1e], [0x2bed, 0x1f]];
    }
    /**
     * @param $maskedFormatInfo1 ; format info indicator, with mask still applied
     * @param $maskedFormatInfo2 ; second copy of same info; both are checked at the same time
     *                          to establish best match
     *
     * @return information about the format it specifies, or {@code null}
     *  if doesn't seem to match any known pattern
     */
    public static function decodeFormatInformation($maskedFormatInfo1, $maskedFormatInfo2)
    {
        $formatInfo = self::doDecodeFormatInformation($maskedFormatInfo1, $maskedFormatInfo2);
        if ($formatInfo != null) {
            return $formatInfo;
        }
        // Should return null, but, some QR codes apparently
        // do not mask this info. Try again by actually masking the pattern
        // first
        return self::doDecodeFormatInformation($maskedFormatInfo1 ^ self::$FORMAT_INFO_MASK_QR, $maskedFormatInfo2 ^ self::$FORMAT_INFO_MASK_QR);
    }
    private static function doDecodeFormatInformation($maskedFormatInfo1, $maskedFormatInfo2)
    {
        // Find the int in FORMAT_INFO_DECODE_LOOKUP with fewest bits differing
        $bestDifference = \PHP_INT_MAX;
        $bestFormatInfo = 0;
        foreach (self::$FORMAT_INFO_DECODE_LOOKUP as $decodeInfo) {
            $targetInfo = $decodeInfo[0];
            if ($targetInfo == $maskedFormatInfo1 || $targetInfo == $maskedFormatInfo2) {
                // Found an exact match
                return new FormatInformation($decodeInfo[1]);
            }
            $bitsDifference = self::numBitsDiffering($maskedFormatInfo1, $targetInfo);
            if ($bitsDifference < $bestDifference) {
                $bestFormatInfo = $decodeInfo[1];
                $bestDifference = $bitsDifference;
            }
            if ($maskedFormatInfo1 != $maskedFormatInfo2) {
                // also try the other option
                $bitsDifference = self::numBitsDiffering($maskedFormatInfo2, $targetInfo);
                if ($bitsDifference < $bestDifference) {
                    $bestFormatInfo = $decodeInfo[1];
                    $bestDifference = $bitsDifference;
                }
            }
        }
        // Hamming distance of the 32 masked codes is 7, by construction, so <= 3 bits
        // differing means we found a match
        if ($bestDifference <= 3) {
            return new FormatInformation($bestFormatInfo);
        }
        return null;
    }
    public static function numBitsDiffering($a, $b)
    {
        $a ^= $b;
        // a now has a 1 bit exactly where its bit differs with b's
        // Count bits set quickly with a series of lookups:
        return self::$BITS_SET_IN_HALF_BYTE[$a & 0xf] + self::$BITS_SET_IN_HALF_BYTE[(int) (uRShift($a, 4) & 0xf)] + self::$BITS_SET_IN_HALF_BYTE[uRShift($a, 8) & 0xf] + self::$BITS_SET_IN_HALF_BYTE[uRShift($a, 12) & 0xf] + self::$BITS_SET_IN_HALF_BYTE[uRShift($a, 16) & 0xf] + self::$BITS_SET_IN_HALF_BYTE[uRShift($a, 20) & 0xf] + self::$BITS_SET_IN_HALF_BYTE[uRShift($a, 24) & 0xf] + self::$BITS_SET_IN_HALF_BYTE[uRShift($a, 28) & 0xf];
    }
    public function getErrorCorrectionLevel()
    {
        return $this->errorCorrectionLevel;
    }
    public function getDataMask()
    {
        return $this->dataMask;
    }
    //@Override
    public function hashCode()
    {
        return $this->errorCorrectionLevel->ordinal() << 3 | (int) $this->dataMask;
    }
    //@Override
    public function equals($o)
    {
        if (!$o instanceof FormatInformation) {
            return \false;
        }
        $other = $o;
        return $this->errorCorrectionLevel == $other->errorCorrectionLevel && $this->dataMask == $other->dataMask;
    }
}
FormatInformation::Init();
