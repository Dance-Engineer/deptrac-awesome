<?php

declare(strict_types = 1);

namespace DanceEngineer\DeptracAwesome;

use Qossmic\Deptrac\Result\CoveredRule;
use Qossmic\Deptrac\Result\Rule;
use Qossmic\Deptrac\Result\Uncovered;
use Qossmic\Deptrac\Result\Violation;

class GraphUtils
{

    /**
     * @param Violation[] $violations
     *
     * @return array<string, array<string, int>>
     */
    public static function calculateViolations(array $violations): array
    {
        $layerViolations = [];
        foreach ($violations as $violation) {
            if (! array_key_exists($violation->getDependerLayer(), $layerViolations)) {
                $layerViolations[$violation->getDependerLayer()] = [];
            }

            if (! array_key_exists($violation->getDependentLayer(), $layerViolations[$violation->getDependerLayer()])) {
                $layerViolations[$violation->getDependerLayer()][$violation->getDependentLayer()] = 1;
            } else {
                ++$layerViolations[$violation->getDependerLayer()][$violation->getDependentLayer()];
            }
        }

        return $layerViolations;
    }

    /**
     * @param Rule[] $rules
     *
     * @return array<string, array<string, int>>
     */
    public static function calculateLayerDependencies(array $rules): array
    {
        $layersDependOnLayers = [];

        foreach ($rules as $rule) {
            if ($rule instanceof CoveredRule) {
                $layerA = $rule->getDependerLayer();
                $layerB = $rule->getDependentLayer();

                if (! array_key_exists($layerA, $layersDependOnLayers)) {
                    $layersDependOnLayers[$layerA] = [];
                }

                if (! array_key_exists($layerB, $layersDependOnLayers[$layerA])) {
                    $layersDependOnLayers[$layerA][$layerB] = 1;
                    continue;
                }

                ++$layersDependOnLayers[$layerA][$layerB];
            } elseif ($rule instanceof Uncovered) {
                $layer = $rule->getLayer();
                if (! array_key_exists($layer, $layersDependOnLayers)) {
                    $layersDependOnLayers[$layer] = [];
                }
            }
        }

        return $layersDependOnLayers;
    }

}