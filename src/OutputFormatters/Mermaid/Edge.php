<?php

declare(strict_types=1);

namespace DanceEngineer\DeptracAwesome\OutputFormatters\Mermaid;

final class Edge implements \Stringable
{
    private Node $sourceNode;

    private Node $targetNode;

    private EdgeShape $type;

    private ?string $style;

    private ?int $index = null;

    private string $text;

    public function __construct(
        Node $sourceNode,
        Node $targetNode,
        string $text = '',
        EdgeShape $type = EdgeShape::ARROW,
        ?string $style = null
    ) {
        $this->sourceNode = $sourceNode;
        $this->targetNode = $targetNode;
        $this->text = $text;
        $this->type = $type;
        $this->style = $style;
    }

    public function __toString(): string
    {
        $line = sprintf($this->type->value, Utils::escape($this->text));
        return "{$this->sourceNode->getId()}{$line}{$this->targetNode->getId()};";
    }

    public function setText(string $text): void
    {
        $this->text = $text;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setStyle(string $style): void
    {
        $this->style = $style;
    }

    public function setType(EdgeShape $type): void
    {
        $this->type = $type;
    }

    /**
     * The link is assigned an index at render time, which is used in getStyle()
     *
     * @internal
     */
    public function setIndex(int $index): self
    {
        $this->index = $index;
        return $this;
    }

    public function getStyle(): ?string
    {
        return $this->style;
    }

    /**
     * @return string|null
     */
    public function getOutputStyle(): ?string
    {
        if ($this->index === null || $this->style === null) {
            return null;
        }
        return "linkStyle {$this->index} {$this->style}";
    }
}
