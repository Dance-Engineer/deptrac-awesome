<?php

declare(strict_types=1);

namespace DanceEngineer\DeptracAwesome\OutputFormatters\Mermaid;

abstract class Utils
{
    public static function escape(string $text): string
    {
        $text = \trim($text);
        $text = \htmlentities($text);
        $text = \str_replace(['&', '#lt;', '#gt;'], ['#', '<', '>'], $text);

        return "\"{$text}\"";
    }
}
