<?php

declare(strict_types=1);

namespace DanceEngineer\DeptracAwesome\OutputFormatters;

use DanceEngineer\DeptracAwesome\OutputFormatters\Mermaid\Edge;
use DanceEngineer\DeptracAwesome\OutputFormatters\Mermaid\EdgeShape;
use DanceEngineer\DeptracAwesome\OutputFormatters\Mermaid\Graph;
use DanceEngineer\DeptracAwesome\OutputFormatters\Mermaid\Node;
use LogicException;
use Qossmic\Deptrac\Configuration\OutputFormatterInput;
use Qossmic\Deptrac\Console\Output;
use Qossmic\Deptrac\OutputFormatter\OutputFormatterInterface;
use Qossmic\Deptrac\Result\LegacyResult;

final class MermaidJsFormatter implements OutputFormatterInterface
{
    private const VIOLATION_EDGE_STYLE = 'color:red';

    private const BIDIRECTIONAL_EDGE_STYLE = 'color:blue';

    public static function getName(): string
    {
        return 'mermaid-js';
    }

    public function finish(LegacyResult $result, Output $output, OutputFormatterInput $outputFormatterInput): void
    {
        $layerViolations = GraphUtils::calculateViolations($result->violations());
        $layersDependOnLayers = GraphUtils::calculateLayerDependencies($result->rules());

        $graph = new Graph('Graph');
        $nodes = $this->createNodes($layersDependOnLayers);
        $this->addNodesToGraph($graph, $nodes);
        $this->connectEdges($graph, $nodes, $layersDependOnLayers, $layerViolations);
        $this->output($graph, $output, $outputFormatterInput);
    }

    /**
     * @param array<string, array<string, int>> $layersDependOnLayers
     *
     * @return array<Node>
     */
    private function createNodes(array $layersDependOnLayers): array
    {
        $nodes = [];
        foreach ($layersDependOnLayers as $layer => $layersDependOn) {
            if (! array_key_exists($layer, $nodes)) {
                $nodes[$layer] = new Node($layer);
            }

            foreach ($layersDependOn as $layerDependOn => $_) {
                if (! array_key_exists($layerDependOn, $nodes)) {
                    $nodes[$layerDependOn] = new Node($layerDependOn);
                }
            }
        }
        return $nodes;
    }

    /**
     * @param  array<Node>  $nodes
     */
    private function addNodesToGraph(Graph $graph, array $nodes): void
    {
        foreach ($nodes as $node) {
            $graph->addNode($node);
        }
    }

    /**
     * @param  array<Node>  $nodes
     * @param  array<string, array<string, int>>  $layersDependOnLayers
     * @param  array<string, array<string, int>>  $layerViolations
     */
    private function connectEdges(Graph $graph, array $nodes, array $layersDependOnLayers, array $layerViolations): void
    {
        /** @var array<string, Edge> $edges */
        $edges = [];
        foreach ($layersDependOnLayers as $layer => $layersDependOn) {
            foreach ($layersDependOn as $layerDependOn => $layerDependOnCount) {
                $edge = new Edge($nodes[$layer], $nodes[$layerDependOn]);
                $label = 0;
                if (array_key_exists($layer, $layerViolations)
                    && array_key_exists($layerDependOn, $layerViolations[$layer])
                ) {
                    $label += $layerViolations[$layer][$layerDependOn];
                    $edge->setType(EdgeShape::BIDIRECTIONAL_ARROW);
                    $edge->setStyle(self::VIOLATION_EDGE_STYLE);
                } else {
                    $label += $layerDependOnCount;
                }
                $edge->setText((string) $label);
                $edges[(string) $nodes[$layer] . '|' . (string) $nodes[$layerDependOn]] = $edge;
            }
        }
        foreach ($edges as $key => &$edge) {
            [$before, $after] = explode('|', $key, 2);
            $otherKey = $after . '|' . $before;

            if (array_key_exists($otherKey, $edges) && $edge->getStyle() !== self::VIOLATION_EDGE_STYLE) {
                $otherEdge = $edges[$otherKey];
                if ($otherEdge->getStyle() !== self::VIOLATION_EDGE_STYLE) {
                    $edge->setText((string) ((int) ($otherEdge->getText()) + (int) $edge->getText()));
                    $edge->setType(EdgeShape::BIDIRECTIONAL_ARROW);
                    $edge->setStyle(self::BIDIRECTIONAL_EDGE_STYLE);
                    unset($edges[$otherKey]);
                }
            }
            $graph->addLink($edge);
        }
    }

    private function output(Graph $graph, Output $output, OutputFormatterInput $outputFormatterInput): void
    {
        $outputPath = $outputFormatterInput->getOutputPath();
        if ($outputPath !== null) {
            file_put_contents($outputPath, (string) $graph);
            $output->writeLineFormatted('<info>Script dumped to ' . realpath($outputPath) . '</info>');
        } else {
            throw new LogicException("No '--output' defined for MermaidJs formatter");
        }
    }
}
