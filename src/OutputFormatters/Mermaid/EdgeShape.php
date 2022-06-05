<?php

declare(strict_types=1);

namespace DanceEngineer\DeptracAwesome\OutputFormatters\Mermaid;

enum EdgeShape: string
{
    case ARROW = '-->|%s|';
    case BIDIRECTIONAL_ARROW = '<-- %s -->';
}
