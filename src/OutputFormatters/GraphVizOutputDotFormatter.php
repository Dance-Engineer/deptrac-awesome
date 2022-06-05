<?php

declare(strict_types=1);

namespace DanceEngineer\DeptracAwesome\OutputFormatters;

use phpDocumentor\GraphViz\Graph;
use Qossmic\Deptrac\Configuration\OutputFormatterInput;
use Qossmic\Deptrac\Console\Output;

final class GraphVizOutputDotFormatter extends GraphVizOutputFormatter
{
    public static function getName(): string
    {
        return 'graphviz-awesome-dot';
    }

    protected function output(Graph $graph, Output $output, OutputFormatterInput $outputFormatterInput): void
    {
        $dumpDotPath = $outputFormatterInput->getOutputPath();
        if ($dumpDotPath !== null) {
            file_put_contents($dumpDotPath, (string) $graph);
            $output->writeLineFormatted('<info>Script dumped to ' . realpath($dumpDotPath) . '</info>');
        }
    }
}
