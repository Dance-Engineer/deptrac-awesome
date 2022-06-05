<?php

declare(strict_types=1);

namespace DanceEngineer\DeptracAwesome\Commands;

use Exception;
use Qossmic\Deptrac\Analyser\AstMapExtractor;
use Qossmic\Deptrac\Console\Symfony\Style;
use Qossmic\Deptrac\Dependency\DependencyResolver;
use Qossmic\Deptrac\Dependency\TokenResolver;
use Qossmic\Deptrac\Layer\LayerResolver;
use Qossmic\Deptrac\Result\Uncovered;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Stopwatch\Stopwatch;

final class DependenciesCommand extends Command
{
    public static $defaultName = 'dependencies';

    public static $defaultDescription = 'List layer dependencies';

    private AstMapExtractor $astMapExtractor;

    private DependencyResolver $dependencyResolver;

    private LayerResolver $layerResolver;

    private TokenResolver $tokenResolver;

    public function __construct(
        AstMapExtractor $astMapExtractor,
        DependencyResolver $dependencyResolver,
        LayerResolver $layerResolver,
        TokenResolver $tokenResolver,
    ) {
        parent::__construct();
        $this->astMapExtractor = $astMapExtractor;
        $this->dependencyResolver = $dependencyResolver;
        $this->layerResolver = $layerResolver;
        $this->tokenResolver = $tokenResolver;
    }

    protected function configure(): void
    {
        parent::configure();

        /** @throws void */
        $this->addArgument('layer', InputArgument::REQUIRED, 'Layer to debug');
        /** @throws void */
        $this->addArgument(
            'targetLayer',
            InputArgument::OPTIONAL,
            'Target layer to filter dependencies to only one layer'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $stopwatch = new Stopwatch();
        $stopwatch->start('command');

        $outputStyle = new Style(new SymfonyStyle($input, $output));
        $status = self::SUCCESS;
        try {
            /** @var ?string $target */
            $target = $input->getArgument('targetLayer');

            $dependencies = $this->getDependencies((string) $input->getArgument('layer'), $target);

            foreach ($dependencies as $layer => $violations) {
                $outputStyle->table(
                    [$layer],
                    array_map(fn (Uncovered $violation): array => $this->formatRow($violation), $violations)
                );
            }
        } catch (Exception $exception) {
            $outputStyle->error($exception->getMessage());
            $status = self::FAILURE;
        }

        $output->writeln(
            sprintf(
                'The command finished in %1.2f seconds',
                (float) $stopwatch->stop('command')
                    ->getDuration() / 1000.0
            )
        );

        return $status;
    }

    /**
     * @return array<string>
     */
    private function formatRow(Uncovered $rule): array
    {
        $dependency = $rule->getDependency();

        $message = sprintf(
            '<info>%s</info> depends on <info>%s</info> (%s)',
            $dependency->getDepender()
                ->toString(),
            $dependency->getDependent()
                ->toString(),
            $rule->getLayer()
        );

        $fileOccurrence = $dependency->getFileOccurrence();
        $message .= sprintf("\n%s:%d", $fileOccurrence->getFilepath(), $fileOccurrence->getLine());

        return [$message];
    }

    /**
     * @return array<string, array<Uncovered>>
     */
    private function getDependencies(string $layer, ?string $targetLayer): array
    {
        $result = [];
        $astMap = $this->astMapExtractor->extract();
        $dependencies = $this->dependencyResolver->resolve($astMap);
        foreach ($dependencies->getDependenciesAndInheritDependencies() as $dependency) {
            $dependerLayerNames = $this->layerResolver->getLayersForReference(
                $this->tokenResolver->resolve($dependency->getDepender(), $astMap),
                $astMap
            );
            if (in_array($layer, $dependerLayerNames, true)) {
                $dependentLayerNames = $this->layerResolver->getLayersForReference(
                    $this->tokenResolver->resolve($dependency->getDependent(), $astMap),
                    $astMap
                );
                foreach ($dependentLayerNames as $dependentLayerName) {
                    if ($layer === $dependentLayerName || ($targetLayer !== null && $targetLayer !== $dependentLayerName)) {
                        continue;
                    }
                    $result[$dependentLayerName][] = new Uncovered($dependency, $dependentLayerName);
                }
            }
        }
        return $result;
    }
}
