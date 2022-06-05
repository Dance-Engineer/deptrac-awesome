<?php

declare(strict_types=1);

namespace DanceEngineer\DeptracAwesome\OutputFormatters\Mermaid;

enum GraphDirection: string
{
    case TOP_BOTTOM = 'TB';
    case BOTTOM_TOP = 'BT';
    case LEFT_RIGHT = 'LR';
    case RIGHT_LEFT = 'RL';
}
