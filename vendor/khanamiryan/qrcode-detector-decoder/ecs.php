<?php

namespace WP2FA_Vendor;

use WP2FA_Vendor\Rector\Set\ValueObject\LevelSetList;
use WP2FA_Vendor\PhpCsFixer\Fixer\Operator\ConcatSpaceFixer;
use WP2FA_Vendor\Symplify\EasyCodingStandard\Config\ECSConfig;
use WP2FA_Vendor\Symplify\EasyCodingStandard\ValueObject\Option;
use WP2FA_Vendor\PhpCsFixer\Fixer\ArrayNotation\ArraySyntaxFixer;
use WP2FA_Vendor\Symplify\EasyCodingStandard\ValueObject\Set\SetList;
use WP2FA_Vendor\Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
return static function (ECSConfig $configurator) : void {
    // alternative to CLI arguments, easier to maintain and extend
    $configurator->paths([__DIR__ . '/lib', __DIR__ . '/tests']);
    // choose
    $configurator->sets([SetList::CLEAN_CODE, SetList::PSR_12]);
    $configurator->ruleWithConfiguration(ConcatSpaceFixer::class, ['spacing' => 'one']);
    // indent and tabs/spaces
    // [default: spaces]. BUT: tabs are superiour due to accessibility reasons
    $configurator->indentation('tab');
};
