<?php

declare (strict_types=1);
/*
 * (c) Jeroen van den Enden <info@endroid.nl>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace WP2FA_Vendor\Endroid\QrCode\Factory;

use WP2FA_Vendor\Endroid\QrCode\QrCodeInterface;
interface QrCodeFactoryInterface
{
    /** @param array<string, mixed> $options */
    public function create(string $text = '', array $options = []) : QrCodeInterface;
}
