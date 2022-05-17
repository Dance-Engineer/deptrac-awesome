<?php

declare(strict_types=1);

namespace DanceEngineer\DeptracAwesome;

use function base64_encode;
use function file_get_contents;
use LogicException;
use phpDocumentor\GraphViz\Exception;
use phpDocumentor\GraphViz\Graph;
use Qossmic\Deptrac\Configuration\OutputFormatterInput;
use Qossmic\Deptrac\Console\Output;
use RuntimeException;

final class GraphVizOutputHtmlFormatter extends GraphVizOutputFormatter
{
    public static function getName(): string
    {
        return 'graphviz-awesome-html';
    }

    protected function output(Graph $graph, Output $output, OutputFormatterInput $outputFormatterInput): void
    {
        $dumpHtmlPath = $outputFormatterInput->getOutputPath();
        if ($dumpHtmlPath === null) {
            return;
        }
        try {
            $filename = $this->getTempImage($graph);
        } catch (Exception|RuntimeException $exception) {
            throw new LogicException('Unable to generate HTML file: ' . $exception->getMessage());
        }
        $imageData = file_get_contents($filename);
        if ($imageData === false) {
            unlink($filename);
            throw new LogicException('Unable to generate HTML file: Unable to create temp file for output.');
        }
        file_put_contents($dumpHtmlPath, '<img src="data:image/png;base64,' . base64_encode($imageData) . '" />');
        $output->writeLineFormatted('<info>HTML dumped to ' . realpath($dumpHtmlPath) . '</info>');
        unlink($filename);
    }
}
