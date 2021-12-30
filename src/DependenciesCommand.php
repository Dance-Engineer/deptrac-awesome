<?php

declare(strict_types = 1);

namespace DanceEngineer\DeptracAwesome;

use Exception;
use Qossmic\Deptrac\AstRunner\AstRunner;
use Qossmic\Deptrac\Configuration\Loader;
use Qossmic\Deptrac\Console\Command\DefaultDepFileTrait;
use Qossmic\Deptrac\Console\Symfony\Style;
use Qossmic\Deptrac\Console\Symfony\SymfonyOutput;
use Qossmic\Deptrac\Dependency\Resolver;
use Qossmic\Deptrac\Dependency\Result;
use Qossmic\Deptrac\FileResolver;
use Qossmic\Deptrac\RulesetEngine\Violation;
use Qossmic\Deptrac\TokenLayerResolverFactory;
use Qossmic\Deptrac\TokenLayerResolverInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class DependenciesCommand extends Command
{
    use DefaultDepFileTrait;

    public static $defaultName = 'dependencies';

    public static $defaultDescription = 'List layer dependencies';

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

        $this->addArgument('layer', InputArgument::REQUIRED, 'Layer to debug');
        $this->addOption('depfile', null, InputOption::VALUE_OPTIONAL, 'Path to the depfile');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $outputStyle = new Style(new SymfonyStyle($input, $output));
        try {
            $configuration = $this->configurationLoader->load(
                self::getConfigFile($input, new SymfonyOutput($output, $outputStyle))
            );
            $astMap        = $this->astRunner->createAstMapByFiles(
                $this->fileResolver->resolve($configuration),
                $configuration->getAnalyser()
            );
            $result        = $this->getDependencies(
                $this->resolver->resolve($astMap, $configuration->getAnalyser()),
                $this->tokenLayerResolverFactory->create($configuration, $astMap),
                (string)$input->getArgument('layer')
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
            $dependency->getDependant()
                ->toString(),
            $dependency->getDependee()
                ->toString(),
            $rule->getDependeeLayerName()
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
        Result $dependencyResult,
        TokenLayerResolverInterface $tokenLayerResolver,
        string $layer
    ): array {
        $result = [];
        foreach ($dependencyResult->getDependenciesAndInheritDependencies() as $dependency) {
            $dependantLayerNames = $tokenLayerResolver->getLayersByTokenName($dependency->getDependant());
            if (in_array($layer, $dependantLayerNames, true)) {
                $dependeeLayerNames = $tokenLayerResolver->getLayersByTokenName($dependency->getDependee());
                foreach ($dependeeLayerNames as $dependeeLayerName) {
                    if ($layer === $dependeeLayerName) {
                        continue;
                    }
                    $result[$dependeeLayerName][] = new Violation($dependency, $layer, $dependeeLayerName);
                }
            }
        }
        return $result;
    }
}
