<?php

declare(strict_types=1);

namespace DanceEngineer\DeptracAwesome\OutputFormatters;

use LogicException;
use phpDocumentor\GraphViz\Exception;
use phpDocumentor\GraphViz\Graph;
use Qossmic\Deptrac\Configuration\OutputFormatterInput;

use Qossmic\Deptrac\Console\Output;

final class GraphVizOutputDisplayFormatter extends GraphVizOutputFormatter
{
    /**
     * @var positive-int
     */
    private const DELAY_OPEN = 2;

    public static function getName(): string
    {
        return 'graphviz-awesome-display';
    }

    protected function output(Graph $graph, Output $output, OutputFormatterInput $outputFormatterInput): void
    {
        try {
            $filename = $this->getTempImage($graph);
            static $next = 0;
            if ($next > microtime(true)) {
                sleep(self::DELAY_OPEN);
            }

            if ('Windows' === PHP_OS_FAMILY) {
                exec('start "" ' . escapeshellarg($filename) . ' >NUL');
            } elseif ('Darwin' === PHP_OS_FAMILY) {
                exec('open ' . escapeshellarg($filename) . ' > /dev/null 2>&1 &');
            } else {
                exec('xdg-open ' . escapeshellarg($filename) . ' > /dev/null 2>&1 &');
            }
            $next = microtime(true) + (float) self::DELAY_OPEN;
        } catch (Exception|\RuntimeException $exception) {
            throw new LogicException(
                'Unable to display output: ' . $exception->getMessage(),
                (int) $exception->getCode(),
                $exception
            );
        }
    }
}
