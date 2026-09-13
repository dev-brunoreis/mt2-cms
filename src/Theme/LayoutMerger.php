<?php

declare(strict_types=1);

namespace Mt2Cms\Theme;

class LayoutMerger
{
    /**
     * Deep-merge layout trees by node id (not by array index).
     * Overlay nodes with `"remove": true` drop the matching base id.
     *
     * @param array<string, mixed> $base
     * @param array<string, mixed> $overlay
     * @return array<string, mixed>
     */
    public static function mergeById(array $base, array $overlay): array
    {
        $result = $base;

        foreach ($overlay as $key => $value) {
            if ($key === 'extends') {
                continue;
            }

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
                $result[$slotName] = self::filterRemoved($nodes);
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
            $removed = [];

            foreach ($nodes as $node) {
                if (!is_array($node)) {
                    continue;
                }

                $id = (string) ($node['id'] ?? '');
                $key = $id !== '' ? $id : uniqid('node_', true);

                if (!empty($node['remove'])) {
                    if ($id !== '') {
                        $removed[$id] = true;
                        unset($indexed[$id]);
                    }

                    continue;
                }

                if ($id !== '' && isset($indexed[$id])) {
                    $indexed[$id] = self::mergeById($indexed[$id], $node);
                    $order[] = $id;
                } else {
                    $indexed[$key] = $node;
                    $order[] = $key;
                }
            }

            $ordered = [];
            $remaining = $indexed;

            foreach ($order as $key) {
                if (isset($remaining[$key]) && empty($removed[$key])) {
                    $ordered[] = self::stripRemoveFlag($remaining[$key]);
                    unset($remaining[$key]);
                }
            }

            foreach ($remaining as $key => $node) {
                if (isset($removed[$key])) {
                    continue;
                }

                $ordered[] = self::stripRemoveFlag($node);
            }

            $result[$slotName] = $ordered;
        }

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @return list<array<string, mixed>>
     */
    private static function filterRemoved(array $nodes): array
    {
        $out = [];

        foreach ($nodes as $node) {
            if (!is_array($node) || !empty($node['remove'])) {
                continue;
            }

            $out[] = self::stripRemoveFlag($node);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    private static function stripRemoveFlag(array $node): array
    {
        unset($node['remove']);

        return $node;
    }
}
