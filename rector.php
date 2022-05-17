<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\Core\Configuration\Option;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Php80\Rector\FunctionLike\UnionTypesRector;
use Rector\Set\ValueObject\SetList;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (\Rector\Config\RectorConfig $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();

    $parameters->set(Option::PATHS, [
        __DIR__ . '/src',
    ]);

    $parameters->set(Option::SKIP, [
        //Breaking Doctrine annotations for Entities 
        ClassPropertyAssignToConstructorPromotionRector::class,
        //Broken for some reason
        FlipTypeControlToUseExclusiveTypeRector::class,
        //Breaks return types for PHPStan
        UnionTypesRector::class,
    ]);
    $parameters->set(Option::PHPSTAN_FOR_RECTOR_PATH, getcwd() . '../phpstan.neon.dist');

    $containerConfigurator->import(SetList::PHP_80);
    $containerConfigurator->import(SetList::PHP_81);
    $containerConfigurator->import(SetList::CODE_QUALITY);
    $containerConfigurator->import(SetList::TYPE_DECLARATION_STRICT);

};
