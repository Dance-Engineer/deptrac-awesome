<?php

declare(strict_types=1);

namespace DanceEngineer\DeptracAwesome;

use phpDocumentor\GraphViz\AttributeNotFound;
use phpDocumentor\GraphViz\Edge;
use phpDocumentor\GraphViz\Exception;
use phpDocumentor\GraphViz\Graph;
use phpDocumentor\GraphViz\Node;
use Qossmic\Deptrac\Configuration\ConfigurationGraphViz;
use Qossmic\Deptrac\Console\Output;
use Qossmic\Deptrac\OutputFormatter\OutputFormatterInput;
use Qossmic\Deptrac\OutputFormatter\OutputFormatterInterface;
use Qossmic\Deptrac\RulesetEngine\Context;
use Qossmic\Deptrac\RulesetEngine\CoveredRule;
use Qossmic\Deptrac\RulesetEngine\Rule;
use Qossmic\Deptrac\RulesetEngine\Uncovered;
use Qossmic\Deptrac\RulesetEngine\Violation;
use RuntimeException;
use function sys_get_temp_dir;
use function tempnam;

abstract class GraphVizOutputFormatter implements OutputFormatterInterface
{
    protected const VIOLATION_EDGE_COLOR = 'red';

    public static function getConfigName(): string
    {
        return 'graphviz';
    }

    public function finish(
        Context $context,
        Output $output,
        OutputFormatterInput $outputFormatterInput
    ): void {
        $layerViolations = $this->calculateViolations($context->violations());
        $layersDependOnLayers = $this->calculateLayerDependencies($context->rules());

        /** @var array{hidden_layers?: string[], groups?: array<string, string[]>, pointToGroups?: bool} $outputConfig */
        $outputConfig = $outputFormatterInput->getConfig();
        $outputConfig = ConfigurationGraphViz::fromArray($outputConfig);

        $graph = Graph::create('');
        if ($outputConfig->getPointToGroups()) {
            $graph->setAttribute('compound', 'true');
        }
        $nodes = $this->createNodes($outputConfig, $layersDependOnLayers);
        $this->addNodesToGraph($graph, $nodes, $outputConfig);
        $this->connectEdges($graph, $nodes, $outputConfig, $layersDependOnLayers, $layerViolations);
        $this->output($graph, $output, $outputFormatterInput);
    }

    /**
     * @param Violation[] $violations
     *
     * @return array<string, array<string, int>>
     */
    private function calculateViolations(array $violations): array
    {
        $layerViolations = [];
        foreach ($violations as $violation) {
            if (!isset($layerViolations[$violation->getDependantLayerName()])) {
                $layerViolations[$violation->getDependantLayerName()] = [];
            }

            if (!isset($layerViolations[$violation->getDependantLayerName()][$violation->getDependeeLayerName()])) {
                $layerViolations[$violation->getDependantLayerName()][$violation->getDependeeLayerName()] = 1;
            } else {
                ++$layerViolations[$violation->getDependantLayerName()][$violation->getDependeeLayerName()];
            }
        }

        return $layerViolations;
    }

    /**
     * @param Rule[] $rules
     *
     * @return array<string, array<string, int>>
     */
    private function calculateLayerDependencies(array $rules): array
    {
        $layersDependOnLayers = [];

        foreach ($rules as $rule) {
            if ($rule instanceof CoveredRule) {
                $layerA = $rule->getDependantLayerName();
                $layerB = $rule->getDependeeLayerName();

                if (!isset($layersDependOnLayers[$layerA])) {
                    $layersDependOnLayers[$layerA] = [];
                }

                if (!isset($layersDependOnLayers[$layerA][$layerB])) {
                    $layersDependOnLayers[$layerA][$layerB] = 1;
                    continue;
                }

                ++$layersDependOnLayers[$layerA][$layerB];
            } elseif ($rule instanceof Uncovered) {
                $layer = $rule->getLayer();
                if (!isset($layersDependOnLayers[$layer])) {
                    $layersDependOnLayers[$layer] = [];
                }
            }
        }

        return $layersDependOnLayers;
    }

    /**
     * @param array<string, array<string, int>> $layersDependOnLayers
     *
     * @return Node[]
     */
    private function createNodes(ConfigurationGraphViz $outputConfig, array $layersDependOnLayers): array
    {
        $hiddenLayers = $outputConfig->getHiddenLayers();
        $nodes = [];
        foreach ($layersDependOnLayers as $layer => $layersDependOn) {
            if (in_array($layer, $hiddenLayers, true)) {
                continue;
            }
            if (!isset($nodes[$layer])) {
                $nodes[$layer] = new Node($layer);
            }

            foreach ($layersDependOn as $layerDependOn => $_) {
                if (in_array($layerDependOn, $hiddenLayers, true)) {
                    continue;
                }
                if (!isset($nodes[$layerDependOn])) {
                    $nodes[$layerDependOn] = new Node($layerDependOn);
                }
            }
        }

        return $nodes;
    }

    /**
     * @param Node[]                            $nodes
     * @param array<string, array<string, int>> $layersDependOnLayers
     * @param array<string, array<string, int>> $layerViolations
     */
    private function connectEdges(
        Graph $graph,
        array $nodes,
        ConfigurationGraphViz $outputConfig,
        array $layersDependOnLayers,
        array $layerViolations
    ): void {
        $hiddenLayers = $outputConfig->getHiddenLayers();

        /** @var array<Edge> $edges */
        $edges = [];
        foreach ($layersDependOnLayers as $layer => $layersDependOn) {
            if (in_array($layer, $hiddenLayers, true)) {
                continue;
            }
            foreach ($layersDependOn as $layerDependOn => $layerDependOnCount) {
                if (in_array($layerDependOn, $hiddenLayers, true)) {
                    continue;
                }
                $edge = new Edge($nodes[$layer], $nodes[$layerDependOn]);
                if ($outputConfig->getPointToGroups() && $graph->hasGraph($this->getSubgraphName($layerDependOn))) {
                    $edge->setAttribute('lhead', $this->getSubgraphName($layerDependOn));
                }
                $label = 0;
                if (isset($layerViolations[$layer][$layerDependOn])) {
                    $label += $layerViolations[$layer][$layerDependOn];
                    $edge->setAttribute('color', self::VIOLATION_EDGE_COLOR);
                } else {
                    $label += $layerDependOnCount;
                }
                $edge->setAttribute('label', (string) $label);
                $edges[$nodes[$layer] . '|' . $nodes[$layerDependOn]] = $edge;
            }
        }
        foreach ($edges as $key => &$edge) {
            [$before, $after] = explode('|', $key, 2);
            $otherKey = $after.'|'.$before;
            try {
                $edgeColor = $edge->getAttribute('color')->getValue();
            } catch (AttributeNotFound $_) {
                $edgeColor = null;
            }
            if (array_key_exists($otherKey, $edges) && $edgeColor !== self::VIOLATION_EDGE_COLOR) {
                $otherEdge = $edges[$otherKey];
                try {
                    $otherEdgeColor = $otherEdge->getAttribute('color')->getValue();
                } catch (AttributeNotFound $_) {
                    $otherEdgeColor = null;
                }
                if ($otherEdgeColor !== self::VIOLATION_EDGE_COLOR) {
                    try {
                        $label = $otherEdge->getAttribute('label')->getValue();
                        $edge->setAttribute('label',(string)((int)$label + (int)$edge->getAttribute('label')->getValue()));
                        $edge->setAttribute('dir', 'both');
                        $edge->setAttribute('color', 'blue');
                        unset($edges[$otherKey]);
                    } catch (AttributeNotFound $_) {
                    }
                }
            }
            $graph->link($edge);
        }
    }

    /**
     * @param Node[] $nodes
     */
    private function addNodesToGraph(Graph $graph, array $nodes, ConfigurationGraphViz $outputConfig): void
    {
        foreach ($outputConfig->getGroupsLayerMap() as $groupName => $groupLayerNames) {
            $subgraph = Graph::create($this->getSubgraphName($groupName))
                ->setAttribute('label', $groupName);
            $graph->addGraph($subgraph);

            foreach ($groupLayerNames as $groupLayerName) {
                if (array_key_exists($groupLayerName, $nodes)) {
                    $subgraph->setNode($nodes[$groupLayerName]);
                    $nodes[$groupLayerName]->setAttribute('group', $groupName);
                    unset($nodes[$groupLayerName]);
                }
            }
        }

        foreach ($nodes as $node) {
            $graph->setNode($node);
        }
    }

    /**
     * @throws Exception
     */
    protected function getTempImage(Graph $graph): string
    {
        $filename = tempnam(sys_get_temp_dir(), 'deptrac');
        if (false === $filename) {
            throw new RuntimeException('Unable to create temp file for output.');
        }
        $filename .= '.png';
        $graph->export('png', $filename);

        return $filename;
    }

    private function getSubgraphName(string $groupName): string
    {
        return 'cluster_'.$groupName;
    }

    abstract protected function output(Graph $graph, Output $output, OutputFormatterInput $outputFormatterInput): void;
}
