<?php

declare(strict_types = 1);

namespace DanceEngineer\DeptracAwesome;

use Qossmic\Deptrac\Analyser\AstMapExtractor;
use Qossmic\Deptrac\Console\Symfony\Style;
use Qossmic\Deptrac\Dependency\DependencyResolver;
use Qossmic\Deptrac\Dependency\TokenResolver;
use Qossmic\Deptrac\Layer\LayerProvider;
use Qossmic\Deptrac\Layer\LayerResolver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Stopwatch\Stopwatch;
use Throwable;

use function array_map;

final class UnusedDependenciesCommand extends Command
{
    public static $defaultName = 'unused';

    public static $defaultDescription = 'List unused layer dependencies';

    private AstMapExtractor $astMapExtractor;

    private DependencyResolver $dependencyResolver;

    private LayerResolver $layerResolver;

    private LayerProvider $layerProvider;

    private array $layers;

    private TokenResolver $tokenResolver;

    public function __construct(
        AstMapExtractor $astMapExtractor,
        DependencyResolver $dependencyResolver,
        LayerResolver $layerResolver,
        LayerProvider $layerProvider,
        TokenResolver $tokenResolver,
        array $layers
    ) {
        parent::__construct();
        $this->astMapExtractor    = $astMapExtractor;
        $this->dependencyResolver = $dependencyResolver;
        $this->layerResolver      = $layerResolver;
        $this->layerProvider      = $layerProvider;
        $this->tokenResolver      = $tokenResolver;
        $this->layers             = $layers;
    }

    protected function configure(): void
    {
        parent::configure();

        $this->addOption(
            'limit',
            'l',
            InputOption::VALUE_OPTIONAL,
            'How many times can it be used to be considered unused',
            0
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('command');

        $outputStyle = new Style(new SymfonyStyle($input, $output));
        $status      = self::SUCCESS;
        try {
            $layerNames = $this->layerResolution($stopwatch, $output);

            $unusedLayerNames = $this->findUnusedDependencies($layerNames, $stopwatch, $output);

            $outputTable = $this->prepareOutputTable($unusedLayerNames, (int)$input->getOption('limit'), $stopwatch, $output);

            $outputStyle->table(['Unused'],$outputTable);
        } catch (Throwable $exception) {
            $outputStyle->error($exception->getMessage());
            $status = self::FAILURE;
        }

        $output->writeln(
            sprintf(
                'The command finished in %1.2f seconds',
                $stopwatch->stop('command')
                    ->getDuration() / 1000.0
            )
        );

        return $status;
    }

    /**
     * @return array<string, array<string, 0>>
     */
    private function layerResolution(Stopwatch $stopwatch, OutputInterface $output): array
    {
        if ($output->isDebug()) {
            $stopwatch->start('layerResolution');
        }

        $layerNames = [];
        foreach (array_map(static fn(array $layerDef): string => $layerDef['name'], $this->layers) as $sourceLayerName)
        {
            foreach (
                $this->layerProvider->getAllowedLayers($sourceLayerName) as $destinationLayerName
            ) {
                $layerNames[$sourceLayerName][$destinationLayerName] = 0;
            }
        }

        if ($output->isDebug()) {
            $output->writeln(
                sprintf(
                    '"layerResolution" finished in %1.2f seconds',
                    $stopwatch->stop('layerResolution')
                        ->getDuration() / 1000.0
                )
            );
        }

        return $layerNames;
    }

    /**
     * @param  array<string, array<string, 0>> $layerNames
     * @return array<string, array<string, int>>
     */
    private function findUnusedDependencies(array $layerNames, Stopwatch $stopwatch, OutputInterface $output): array
    {
        if ($output->isDebug()) {
            $stopwatch->start('findUnusedDependencies');
        }

        $astMap           = $this->astMapExtractor->extract();
        $dependencyResult = $this->dependencyResolver->resolve($astMap);
        foreach ($dependencyResult->getDependenciesAndInheritDependencies() as $dependency) {
            $dependerLayerNames = $this->layerResolver->getLayersForReference(
                $this->tokenResolver->resolve($dependency->getDepender(), $astMap),
                $astMap
            );
            foreach ($dependerLayerNames as $dependerLayerName) {
                $dependentLayerNames = $this->layerResolver->getLayersForReference(
                    $this->tokenResolver->resolve($dependency->getDependent(), $astMap),
                    $astMap
                );
                foreach ($dependentLayerNames as $dependentLayerName) {
                    if (array_key_exists($dependerLayerName, $layerNames)
                        && array_key_exists($dependentLayerName, $layerNames[$dependerLayerName])
                    ) {
                        $layerNames[$dependerLayerName][$dependentLayerName] += 1;
                    }
                }
            }
        }

        if ($output->isDebug()) {
            $output->writeln(
                sprintf(
                    '"findUnusedDependencies" finished in %1.2f seconds',
                    $stopwatch->stop('findUnusedDependencies')
                        ->getDuration() / 1000.0
                )
            );
        }

        return $layerNames;
    }

    /**
     * @param  array<string, array<string, int>>  $layerNames
     * @return array<array{string}}>
     */
    private function prepareOutputTable(array $layerNames, int $limit, Stopwatch $stopwatch, OutputInterface $output): array
    {
        if ($output->isDebug()) {
            $stopwatch->start('prepareOutputTable');
        }

        $rows = [];
        foreach ($layerNames as $dependerLayerName => $dependentLayerNames) {
            foreach ($dependentLayerNames as $dependentLayerName => $numberOfDependencies) {
                if ($numberOfDependencies <= $limit) {
                    if ($numberOfDependencies === 0) {
                        $rows[] = [
                            "<info>$dependerLayerName</info> layer is not dependant on <info>$dependentLayerName</info>",
                        ];
                    } else {
                        $rows[] = [
                            "<info>$dependerLayerName</info> layer is dependent <info>$dependentLayerName</info> layer $numberOfDependencies times",
                        ];
                    }
                }
            }
        }

        if ($output->isDebug()) {
            $output->writeln(
                sprintf(
                    '"prepareOutputTable" finished in %1.2f seconds',
                    $stopwatch->stop('prepareOutputTable')
                        ->getDuration() / 1000.0
                )
            );
        }

        return $rows;
    }
}
