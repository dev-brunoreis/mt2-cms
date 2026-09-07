<?php

declare(strict_types=1);

namespace Mt2Cms\Theme;

class LayoutMerger
{
    /**
     * Deep-merge layout trees by node id (not by array index).
     *
     * @param array<string, mixed> $base
     * @param array<string, mixed> $overlay
     * @return array<string, mixed>
     */
    public static function mergeById(array $base, array $overlay): array
    {
        $result = $base;

        foreach ($overlay as $key => $value) {
            if ($key === 'slots' && is_array($value)) {
                $result['slots'] = self::mergeSlots(
                    is_array($result['slots'] ?? null) ? $result['slots'] : [],
                    $value,
                );
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * @param array<string, list<array<string, mixed>>> $baseSlots
     * @param array<string, list<array<string, mixed>>> $overlaySlots
     * @return array<string, list<array<string, mixed>>>
     */
    private static function mergeSlots(array $baseSlots, array $overlaySlots): array
    {
        $result = $baseSlots;

        foreach ($overlaySlots as $slotName => $nodes) {
            if (!is_array($nodes)) {
                continue;
            }

            if (!isset($result[$slotName]) || !is_array($result[$slotName])) {
                $result[$slotName] = $nodes;
                continue;
            }

            $indexed = [];

            foreach ($result[$slotName] as $node) {
                if (!is_array($node)) {
                    continue;
                }

                $id = (string) ($node['id'] ?? '');
                $indexed[$id !== '' ? $id : spl_object_hash((object) $node)] = $node;
            }

            $order = [];

            foreach ($nodes as $node) {
                if (!is_array($node)) {
                    continue;
                }

                $id = (string) ($node['id'] ?? '');
                $key = $id !== '' ? $id : uniqid('node_', true);

                if ($id !== '' && isset($indexed[$id])) {
                    $indexed[$id] = self::mergeById($indexed[$id], $node);
                    $order[] = $id;
                    unset($indexed[$id]);
                } else {
                    $indexed[$key] = $node;
                    $order[] = $key;
                }
            }

            // Keep remaining base nodes that were not overridden, then overlay order
            $merged = array_values($indexed);

            if ($order !== []) {
                $ordered = [];
                $remaining = $indexed;

                foreach ($order as $key) {
                    if (isset($remaining[$key])) {
                        $ordered[] = $remaining[$key];
                        unset($remaining[$key]);
                    }
                }

                foreach ($remaining as $node) {
                    $ordered[] = $node;
                }

                $merged = $ordered;
            }

            $result[$slotName] = $merged;
        }

        return $result;
    }
}
