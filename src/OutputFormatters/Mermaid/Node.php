<?php

declare(strict_types=1);

namespace DanceEngineer\DeptracAwesome\OutputFormatters\Mermaid;

final class Node implements \Stringable
{
    private string $identifier;

    private string $title;

    private NodeShape $shape;

    public function __construct(string $title, NodeShape $shape = NodeShape::ROUND)
    {
        $this->identifier = self::idFromTitle($title);
        $this->title = $title;
        $this->shape = $shape;
    }

    /**
     * @return string
     * @psalm-suppress RedundantCastGivenDocblockType
     */
    public function __toString(): string
    {
        if ($this->title !== '' && $this->title !== '0') {
            return $this->identifier . \sprintf($this->shape->value, Utils::escape($this->title)) . ';';
        }

        return "{$this->identifier};";
    }

    public static function idFromTitle(string $identifier): string
    {
        return md5($identifier);
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getId(): string
    {
        return $this->identifier;
    }

    public function setShape(NodeShape $shape): void
    {
        $this->shape = $shape;
    }

    public function getShape(): NodeShape
    {
        return $this->shape;
    }
}
