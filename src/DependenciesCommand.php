<?php

declare(strict_types = 1);

namespace DanceEngineer\DeptracAwesome;

use Exception;
use Qossmic\Deptrac\Analyser\AstMapExtractor;
use Qossmic\Deptrac\Analyser\LayerForTokenAnalyser;
use Qossmic\Deptrac\Console\Symfony\Style;
use Qossmic\Deptrac\Console\Symfony\SymfonyOutput;
use Qossmic\Deptrac\Dependency\DependencyResolver;
use Qossmic\Deptrac\Result\Violation;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class DependenciesCommand extends Command
{
    public static $defaultName = 'dependencies';

    public static $defaultDescription = 'List layer dependencies';

    private AstMapExtractor $astMapExtractor;

    private DependencyResolver $dependencyResolver;

    private LayerForTokenAnalyser $layerForTokenAnalyser;

    public function __construct(
        AstMapExtractor $astMapExtractor,
        DependencyResolver $dependencyResolver,
        LayerForTokenAnalyser $layerForTokenAnalyser
    ) {
        parent::__construct();
        $this->astMapExtractor = $astMapExtractor;
        $this->dependencyResolver = $dependencyResolver;
        $this->layerForTokenAnalyser = $layerForTokenAnalyser;
    }

    protected function configure(): void
    {
        parent::configure();

        $this->addArgument('layer', InputArgument::REQUIRED, 'Layer to debug');
        $this->addArgument('targetLayer', InputArgument::OPTIONAL, 'Target layer to filter dependencies to only one layer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $outputStyle = new Style(new SymfonyStyle($input, $output));
        try {
            $target = $input->getArgument('targetLayer');
            $result        = $this->getDependencies(
                (string)$input->getArgument('layer'),
                $target === null ? null : (string)$target
            );

            foreach ($result as $item) {
                $outputStyle->table(
                    ['Dependencies'],
                    array_map(fn(Violation $violation): array => $this->violationRow($violation), $item)
                );
            }
            return self::SUCCESS;
        } catch (Exception $exception) {
            $outputStyle->error($exception->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * @return array<string>
     */
    private function violationRow(Violation $rule): array
    {
        $dependency = $rule->getDependency();

        $message = sprintf(
            '<info>%s</info> depends on <info>%s</info> (%s)',
            $dependency->getDepender()
                ->toString(),
            $dependency->getDependent()
                ->toString(),
            $rule->getDependentLayer()
        );

        $fileOccurrence = $rule->getDependency()
            ->getFileOccurrence();
        $message        .= sprintf("\n%s:%d", $fileOccurrence->getFilepath(), $fileOccurrence->getLine());

        return [$message];
    }

    /**
     * @return array<string, array<Violation>>
     */
    private function getDependencies(
        string $layer,
        ?string $targetLayer
    ): array {
        $result = [];
        $astMap = $this->astMapExtractor->extract();
        $dependencies = $this->dependencyResolver->resolve($astMap);

        foreach ($dependencies->getDependenciesAndInheritDependencies() as $dependency) {
            $dependerLayerNames = $this->layerForTokenAnalyser->findLayerForToken($dependency->getDepender()->toString(), 'class-like');
            if (in_array($layer, $dependerLayerNames, true)) {
                $dependentLayerNames = $this->layerForTokenAnalyser->findLayerForToken($dependency->getDependent()->toString(), 'class-like');
                foreach ($dependentLayerNames as $dependentLayerName) {
                    if ($layer === $dependentLayerName || ($targetLayer !== null && $targetLayer !== $dependentLayerName)) {
                        continue;
                    }
                    $result[$dependentLayerName][] = new Violation($dependency, $layer, $dependentLayerName);
                }
            }
        }
        return $result;
    }
}
