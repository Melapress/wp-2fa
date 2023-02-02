<?php

declare (strict_types=1);
namespace WP2FA_Vendor;

use WP2FA_Vendor\Rector\Config\RectorConfig;
use WP2FA_Vendor\Rector\Nette\Set\NetteSetList;
use WP2FA_Vendor\Rector\Set\ValueObject\SetList;
use WP2FA_Vendor\Rector\Core\Configuration\Option;
use WP2FA_Vendor\Rector\Symfony\Set\SymfonySetList;
use WP2FA_Vendor\Rector\Doctrine\Set\DoctrineSetList;
use WP2FA_Vendor\Rector\Set\ValueObject\LevelSetList;
use WP2FA_Vendor\Rector\Symfony\Set\SensiolabsSetList;
use WP2FA_Vendor\Rector\TypeDeclaration\Rector\Property\PropertyTypeDeclarationRector;
use WP2FA_Vendor\Rector\TypeDeclaration\Rector\Property\TypedPropertyFromAssignsRector;
use WP2FA_Vendor\Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromReturnNewRector;
use WP2FA_Vendor\Rector\TypeDeclaration\Rector\ClassMethod\AddReturnTypeDeclarationRector;
use WP2FA_Vendor\Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use WP2FA_Vendor\Rector\TypeDeclaration\Rector\ClassMethod\ParamTypeByMethodCallTypeRector;
use WP2FA_Vendor\Rector\TypeDeclaration\Rector\ClassMethod\ParamTypeByParentCallTypeRector;
use WP2FA_Vendor\Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;
use WP2FA_Vendor\Rector\TypeDeclaration\Rector\ClassMethod\ArrayShapeFromConstantArrayReturnRector;
return static function (RectorConfig $rectorConfig) : void {
    $rectorConfig->paths([__DIR__ . '/lib']);
    $parameters = $rectorConfig->parameters();
    $parameters->set(Option::SYMFONY_CONTAINER_XML_PATH_PARAMETER, __DIR__ . '/var/cache/dev/App_KernelDevDebugContainer.xml');
    $rectorConfig->sets([DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES, SymfonySetList::ANNOTATIONS_TO_ATTRIBUTES, NetteSetList::ANNOTATIONS_TO_ATTRIBUTES, SensiolabsSetList::FRAMEWORK_EXTRA_61, SymfonySetList::SYMFONY_60, LevelSetList::UP_TO_PHP_81]);
    // register a single rule
    $rectorConfig->rule(InlineConstructorDefaultToPropertyRector::class);
    $rectorConfig->rule(AddReturnTypeDeclarationRector::class);
    $rectorConfig->rules([
        AddVoidReturnTypeWhereNoReturnRector::class,
        ArrayShapeFromConstantArrayReturnRector::class,
        ParamTypeByMethodCallTypeRector::class,
        ParamTypeByParentCallTypeRector::class,
        PropertyTypeDeclarationRector::class,
        ReturnTypeFromReturnNewRector::class,
        // ReturnTypeFromStrictBoolReturnExprRector::class,
        // ReturnTypeFromStrictNativeFuncCallRector::class,
        // ReturnTypeFromStrictNewArrayRector::class,
        TypedPropertyFromAssignsRector::class,
    ]);
    // define sets of rules
    //    $rectorConfig->sets([
    //        LevelSetList::UP_TO_PHP_80
    //    ]);
};
