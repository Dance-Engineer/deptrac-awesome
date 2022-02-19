<?php

declare(strict_types = 1);

namespace DanceEngineer\DeptracAwesome;

use Qossmic\Deptrac\AstRunner\AstRunner;
use Qossmic\Deptrac\Configuration\Configuration;
use Qossmic\Deptrac\Configuration\ConfigurationLayer;
use Qossmic\Deptrac\Configuration\Loader;
use Qossmic\Deptrac\Console\Command\DefaultDepFileTrait;
use Qossmic\Deptrac\Console\Symfony\Style;
use Qossmic\Deptrac\Console\Symfony\SymfonyOutput;
use Qossmic\Deptrac\Dependency\Resolver;
use Qossmic\Deptrac\FileResolver;
use Qossmic\Deptrac\TokenLayerResolverFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class UnusedDependenciesCommand extends Command
{
    use DefaultDepFileTrait;

    public static $defaultName = 'unused';

    public static $defaultDescription = 'List unused layer dependencies';

    private AstRunner $astRunner;

    private FileResolver $fileResolver;

    private Loader $configurationLoader;

    private Resolver $resolver;

    private TokenLayerResolverFactory $tokenLayerResolverFactory;

    public function __construct(
        AstRunner $astRunner,
        FileResolver $fileResolver,
        Resolver $resolver,
        TokenLayerResolverFactory $tokenLayerResolverFactory,
        Loader $configurationLoader,
    ) {
        $this->astRunner                 = $astRunner;
        $this->fileResolver              = $fileResolver;
        $this->resolver                  = $resolver;
        $this->tokenLayerResolverFactory = $tokenLayerResolverFactory;
        $this->configurationLoader       = $configurationLoader;
        parent::__construct();
    }

    protected function configure(): void
    {
        parent::configure();
        $this->addOption(
            'depfile',
            null,
            InputOption::VALUE_REQUIRED,
            '!deprecated: use --config-file instead - Path to the depfile',
            getcwd().DIRECTORY_SEPARATOR.'depfile.yaml'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $outputStyle = new Style(new SymfonyStyle($input, $output));
        try {
            $configuration = $this->configurationLoader->load(
                self::getConfigFile($input, new SymfonyOutput($output, $outputStyle))
            );
            $layerNames    = $this->getLayerNames($configuration);

            $astMap             = $this->astRunner->createAstMapByFiles(
                $this->fileResolver->resolve($configuration),
                $configuration->getAnalyser()
            );
            $dependencyResult   = $this->resolver->resolve($astMap, $configuration->getAnalyser());
            $tokenLayerResolver = $this->tokenLayerResolverFactory->create($configuration, $astMap);
            foreach ($dependencyResult->getDependenciesAndInheritDependencies() as $dependency) {
                $dependantLayerNames = $tokenLayerResolver->getLayersByTokenName($dependency->getDependant());
                foreach ($dependantLayerNames as $dependantLayerName) {
                    $dependeeLayerNames = $tokenLayerResolver->getLayersByTokenName($dependency->getDependee());
                    foreach ($dependeeLayerNames as $dependeeLayerName) {
                        if (array_key_exists($dependantLayerName, $layerNames)
                            && array_key_exists(
                                $dependeeLayerName,
                                $layerNames[$dependantLayerName]
                            )
                        ) {
                            $layerNames[$dependantLayerName][$dependeeLayerName] += 1;
                        }
                    }
                }
            }

            $outputStyle->table(['Unused'], $this->prepareOutputTable($layerNames));
            return self::SUCCESS;
        } catch (\Exception $exception) {
            $outputStyle->error($exception->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * @return array<string, array<string, 0>>
     */
    private function getLayerNames(Configuration $configuration): array
    {
        $layerNames = [];
        foreach (
            array_map(static fn(ConfigurationLayer $config): string => $config->getName(), $configuration->getLayers())
            as $sourceLayerName
        ) {
            foreach (
                $configuration->getRuleset()
                    ->getAllowedDependencies($sourceLayerName) as $destinationLayerName
            ) {
                $layerNames[$sourceLayerName][$destinationLayerName] = 0;
            }
        }
        return $layerNames;
    }

    /**
     * @param  array<string, array<string, int>>  $layerNames
     * @return array<array{string}}>
     */
    private function prepareOutputTable(array $layerNames): array
    {
        $rows = [];
        foreach ($layerNames as $dependantLayerName => $dependeeLayers) {
            foreach ($dependeeLayers as $dependeeLayerName => $numberOfDependencies) {
                if ($numberOfDependencies === 0) {
                    $rows[] = ["<info>$dependantLayerName</info> is not dependant on <info>$dependeeLayerName</info>"];
                }
            }
        }
        return $rows;
    }

}
