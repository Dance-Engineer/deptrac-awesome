<?php

declare(strict_types=1);

namespace DanceEngineer\DeptracAwesome\OutputFormatters\Mermaid;

use Stringable;

final class Graph implements Stringable
{
    private const RENDER_SHIFT = 4;

    private const SUBGRAPH_SHIFT = 4;

    /**
     * @var array<Graph>
     */
    private array $subGraphs = [];

    /**
     * @var array<string, Node>
     */
    private array $nodes = [];

    /**
     * @var array<Edge>
     */
    private array $links = [];

    private GraphDirection $graphDirection;

    private string $title;

    public function __construct(string $title, GraphDirection $graphDirection = GraphDirection::TOP_BOTTOM)
    {
        $this->title = $title;
        $this->graphDirection = $graphDirection;
    }

    public function __toString(): string
    {
        return $this->render();
    }

    public function addNode(Node $node): void
    {
        $this->nodes[$node->getId()] = $node;
    }

    public function addLink(Edge $link): void
    {
        $this->links[] = $link;
    }

    public function addSubGraph(self $subGraph): void
    {
        $this->subGraphs[] = $subGraph;
    }

    public function getNode(string $nodeTitle): ?Node
    {
        return $this->nodes[Node::idFromTitle($nodeTitle)] ?? null;
    }

    /**
     * @return array<string, Node>
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }

    private function render(bool $isMainGraph = true, int $shift = 0): string
    {
        $spaces = \str_repeat(' ', $shift);
        $spacesSub = \str_repeat(' ', $shift + self::RENDER_SHIFT);

        if ($isMainGraph) {
            $result = ["graph {$this->graphDirection->value};"];
        } else {
            $result = ["{$spaces}subgraph " . Utils::escape($this->title)];
        }

        if ($this->nodes !== []) {
            $tmp = [];
            foreach ($this->nodes as $node) {
                $tmp[] = $spacesSub . (string) $node;
            }
            \sort($tmp);
            $result = \array_merge($result, $tmp);
            if ($isMainGraph) {
                $result[] = '';
            }
        }

        if ($this->links !== []) {
            $tmp = [];
            $i = 0;
            foreach ($this->links as $idx => $link) {
                $tmp[] = $spacesSub . (string) $link;
                $this->links[$idx]->setIndex($i);
                $i++;
            }
            \sort($tmp);
            $result = \array_merge($result, $tmp);
            if ($isMainGraph) {
                $result[] = '';
            }
        }

        foreach ($this->subGraphs as $subGraph) {
            $result[] = $subGraph->render(false, $shift + self::SUBGRAPH_SHIFT);
        }

        if ($this->links !== []) {
            foreach ($this->links as $link) {
                $style = $link->getOutputStyle();
                if ($style !== null) {
                    $result[] = $spaces . $style . ';';
                }
            }
        }

        if (! $isMainGraph) {
            $result[] = "{$spaces}end";
        }

        return \implode(\PHP_EOL, $result);
    }
}
