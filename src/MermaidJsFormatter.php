<?php

declare(strict_types = 1);

namespace DanceEngineer\DeptracAwesome;

use JBZoo\MermaidPHP\Graph;
use JBZoo\MermaidPHP\Link;
use JBZoo\MermaidPHP\Node;
use LogicException;
use Qossmic\Deptrac\Configuration\OutputFormatterInput;
use Qossmic\Deptrac\Console\Output;
use Qossmic\Deptrac\OutputFormatter\OutputFormatterInterface;
use Qossmic\Deptrac\Result\LegacyResult;

class MermaidJsFormatter implements OutputFormatterInterface
{
    protected const VIOLATION_EDGE_STYLE = 'color:red';

    public static function getName(): string
    {
        return 'mermaid-js';
    }

    public function finish(LegacyResult $result, Output $output, OutputFormatterInput $outputFormatterInput): void
    {
        $layerViolations = GraphUtils::calculateViolations($result->violations());
        $layersDependOnLayers = GraphUtils::calculateLayerDependencies($result->rules());

        $graph = new Graph();
        $nodes = $this->createNodes($layersDependOnLayers);
        $this->addNodesToGraph($graph, $nodes);
        $this->connectEdges($graph, $nodes, $layersDependOnLayers, $layerViolations);
        $this->output($graph, $output, $outputFormatterInput);
    }

    /**
     * @return array<Node>
     */
    private function createNodes(array $layersDependOnLayers): array
    {
        $nodes = [];
        foreach ($layersDependOnLayers as $layer => $layersDependOn) {
            if (!array_key_exists($layer, $nodes)) {
                $nodes[$layer] = new Node($layer);
            }

            foreach ($layersDependOn as $layerDependOn => $_) {
                if (!array_key_exists($layerDependOn, $nodes)) {
                    $nodes[$layerDependOn] = new Node($layerDependOn);
                }
            }
        }
        return $nodes;
    }


    /**
     * @param array<Node> $nodes
     */
    private function addNodesToGraph(Graph $graph, array $nodes): void
    {
        foreach ($nodes as $node) {
            $graph->addNode($node);
        }
    }

    /**
     * @param array<Node> $nodes
     * @param array<string, array<string, int>> $layersDependOnLayers
     * @param array<string, array<string, int>> $layerViolations
     */
    private function connectEdges(Graph $graph, array $nodes, array $layersDependOnLayers, array $layerViolations): void
    {
        /** @var array<string, Link> $edges */
        $edges = [];
        foreach ($layersDependOnLayers as $layer => $layersDependOn) {
            foreach ($layersDependOn as $layerDependOn => $layerDependOnCount) {
                $edge = new Link($nodes[$layer], $nodes[$layerDependOn]);
                $label = 0;
                if (array_key_exists($layer,$layerViolations) && array_key_exists($layerDependOn, $layerViolations[$layer])) {
                    $label += $layerViolations[$layer][$layerDependOn];
                    $edge->setType(Link::BIDIRECTIONAL_ARROW);
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
            
            $edgeStyle = $edge->getStyle();
            if (array_key_exists($otherKey, $edges) && $edgeStyle !== self::VIOLATION_EDGE_STYLE) {
                $otherEdge = $edges[$otherKey];
                $otherEdgeStyle = $otherEdge->getStyle();
                if ($otherEdgeStyle !== self::VIOLATION_EDGE_STYLE) {
                    $edge->setText((string) ((int)($otherEdge->getText()) + (int) $edge->getText()));
                    $edge->setType(Link::BIDIRECTIONAL_ARROW);
                    $edge->setStyle('color:blue');
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