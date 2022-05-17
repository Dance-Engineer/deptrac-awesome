<?php

declare(strict_types = 1);

use PhpCsFixer\Fixer\ArrayNotation\ArraySyntaxFixer;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symplify\EasyCodingStandard\ValueObject\Option;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

return static function (\Symplify\EasyCodingStandard\Config\ECSConfig $containerConfigurator): void {
    $services = $containerConfigurator->services();
    $services->set(ArraySyntaxFixer::class)
        ->call(
            'configure',
            [
                [
                    'syntax' => 'short',
                ],
            ]
        );

    $parameters = $containerConfigurator->parameters();
    $parameters->set(
        Option::PATHS,
        [
            __DIR__.'/src',
        ]
    );

    $parameters->set(
        Option::SKIP,
        [
            //Curly braces around switch statements
            PhpCsFixer\Fixer\ControlStructure\NoUnneededCurlyBracesFixer::class,
            //Prefer `+= 1` over `++`
            PhpCsFixer\Fixer\Operator\StandardizeIncrementFixer::class,
            'Unused variable $_.',
            //Deletes Closure(type):type definitions
            PhpCsFixer\Fixer\Phpdoc\NoSuperfluousPhpdocTagsFixer::class,
            Symplify\CodingStandard\Fixer\Commenting\RemoveCommentedCodeFixer::class,
            //This one is deleting @throws annotations for some reason
            \PhpCsFixer\Fixer\Phpdoc\GeneralPhpdocAnnotationRemoveFixer::class
        ]
    );

    $parameters->set(Option::PARALLEL, true);

    $containerConfigurator->import(SetList::PSR_12);
    $containerConfigurator->import(SetList::SYMPLIFY);
    $containerConfigurator->import(SetList::COMMON);
    $containerConfigurator->import(SetList::CLEAN_CODE);
    $containerConfigurator->import(SetList::ARRAY);
};
