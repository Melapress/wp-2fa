<?php

namespace WP2FA_Vendor\MyCLabs\Enum\PHPUnit;

use WP2FA_Vendor\MyCLabs\Enum\Enum;
use WP2FA_Vendor\SebastianBergmann\Comparator\ComparisonFailure;
/**
 * Use this Comparator to get nice output when using PHPUnit assertEquals() with Enums.
 *
 * Add this to your PHPUnit bootstrap PHP file:
 *
 * \SebastianBergmann\Comparator\Factory::getInstance()->register(new \MyCLabs\Enum\PHPUnit\Comparator());
 */
final class Comparator extends \WP2FA_Vendor\SebastianBergmann\Comparator\Comparator
{
    public function accepts($expected, $actual)
    {
        return $expected instanceof Enum && ($actual instanceof Enum || $actual === null);
    }
    /**
     * @param Enum $expected
     * @param Enum|null $actual
     *
     * @return void
     */
    public function assertEquals($expected, $actual, $delta = 0.0, $canonicalize = \false, $ignoreCase = \false)
    {
        if ($expected->equals($actual)) {
            return;
        }
        throw new ComparisonFailure($expected, $actual, $this->formatEnum($expected), $this->formatEnum($actual), \false, 'Failed asserting that two Enums are equal.');
    }
    private function formatEnum(Enum $enum = null)
    {
        if ($enum === null) {
            return "null";
        }
        return \get_class($enum) . "::{$enum->getKey()}()";
    }
}
