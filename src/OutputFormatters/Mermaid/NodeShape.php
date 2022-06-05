<?php

declare(strict_types=1);

namespace DanceEngineer\DeptracAwesome\OutputFormatters\Mermaid;

enum NodeShape: string
{
    case SQUARE = '[%s]';
    case ROUND = '(%s)';
    case CIRCLE = '((%s))';
    case ASYMMETRIC_SHAPE = '>%s]';
    case RHOMBUS = '{%s}';
    case HEXAGON = '{{%s}}';
    case PARALLELOGRAM = '[/%s/]';
    case PARALLELOGRAM_ALT = '[\%s\]';
    case TRAPEZOID = '[/%s\]';
    case TRAPEZOID_ALT = '[\%s/]';
    case DATABASE = '[(%s)]';
    case SUBROUTINE = '[[%s]]';
    case STADIUM = '([%s])';
}
