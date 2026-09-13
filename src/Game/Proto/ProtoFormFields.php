<?php

declare(strict_types=1);

namespace Mt2Cms\Game\Proto;

use Mt2Cms\I18n\Translator;
use Mt2Cms\Support\SelectOptions;

class ProtoFormFields
{
    public function __construct(
        private Translator $translator,
        private ProtoEnums $enums,
    ) {
    }

    /**
     * @param list<array{id: string, fields: list<string>}> $tabs
     * @param array<string, string> $record
     * @return list<array{id: string, label: string, active: bool, fields: list<array<string, mixed>>, rows: list<array<string, mixed>>}>
     */
    public function decorateTabs(string $kind, string $prefix, array $tabs, array $record): array
    {
        $itemType = $record['type'] ?? '';
        $subtype = $record['subtype'] ?? '';
        $decorated = [];

        foreach ($tabs as $index => $tab) {
            $fields = [];

            foreach ($tab['fields'] as $fieldKey) {
                $fields[] = $this->fieldConfig($kind, $prefix, $fieldKey, $record, $itemType, $subtype);
            }

            $tabKey = $prefix . '.tab_' . $tab['id'];
            $tabHelpKey = $prefix . '.tab_' . $tab['id'] . '_help';
            $entry = [
                'id' => $tab['id'],
                'label' => $this->translator->has($tabKey) ? $this->translator->get($tabKey) : $tab['id'],
                'active' => $index === 0,
                'fields' => $fields,
                'rows' => $this->groupFieldRows($prefix, $fields),
            ];

            if ($this->translator->has($tabHelpKey)) {
                $entry['help'] = $this->translator->get($tabHelpKey);
            }

            $decorated[] = $entry;
        }

        return $decorated;
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @return list<array<string, mixed>>
     */
    private function groupFieldRows(string $prefix, array $fields): array
    {
        $rows = [];
        $count = count($fields);

        for ($i = 0; $i < $count; $i++) {
            $field = $fields[$i];
            $next = $fields[$i + 1] ?? null;
            $role = $this->typeValuePairRole((string) ($field['key'] ?? ''), (string) ($next['key'] ?? ''));

            if ($role !== null && is_array($next)) {
                $typeField = $field;
                $valueField = $next;
                unset($typeField['hint'], $valueField['hint']);

                $typeLabelKey = $prefix . ($role === 'apply' ? '.list_bonus' : '.list_requirement');
                $valueLabelKey = $prefix . '.pair_value';

                $rows[] = [
                    'kind' => 'pair',
                    'role' => $role,
                    'title' => (string) ($field['label'] ?? $field['key']),
                    'hint' => (string) ($field['hint'] ?? ''),
                    'typeLabel' => $this->translator->has($typeLabelKey)
                        ? $this->translator->get($typeLabelKey)
                        : ($role === 'apply' ? 'Bonus' : 'Requirement'),
                    'valueLabel' => $this->translator->has($valueLabelKey)
                        ? $this->translator->get($valueLabelKey)
                        : 'Value',
                    'type' => $typeField,
                    'value' => $valueField,
                ];
                $i++;
                continue;
            }

            $rows[] = [
                'kind' => 'field',
                'field' => $field,
            ];
        }

        return $rows;
    }

    private function typeValuePairRole(string $typeKey, string $valueKey): ?string
    {
        if (!preg_match('/^(apply|limit)_type(\d+)$/', $typeKey, $matches)) {
            return null;
        }

        if ($valueKey !== $matches[1] . '_value' . $matches[2]) {
            return null;
        }

        return $matches[1];
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldConfig(
        string $kind,
        string $prefix,
        string $fieldKey,
        array $record,
        string $itemType,
        string $subtype,
    ): array {
        $current = trim((string) ($record[$fieldKey] ?? ''));
        $widget = $this->enums->widgetForField($kind, $fieldKey);
        $labelKey = $prefix . '.fields.' . $fieldKey;

        if (str_starts_with($fieldKey, 'value') && $kind === ProtoEnums::KIND_ITEM) {
            $valueLabels = $this->enums->valueLabelsFor($itemType, $subtype);
            $dynamicKey = $valueLabels[$fieldKey] ?? 'value_0';
            $labelKey = 'admin.proto.values.' . $dynamicKey;
        }

        $config = [
            'key' => $fieldKey,
            'label' => $this->translator->has($labelKey) ? $this->translator->get($labelKey) : $fieldKey,
            'widget' => $widget,
            'value' => $current,
        ];

        $helpKey = $prefix . '.help.' . $fieldKey;

        if ($this->translator->has($helpKey)) {
            $config['hint'] = $this->translator->get($helpKey);
        }

        if ($widget === 'select' || $widget === 'subtype') {
            $options = $widget === 'subtype'
                ? $this->enums->subtypesForItemType($itemType)
                : $this->enums->optionsForField($kind, $fieldKey);
            $options = $this->enums->ensureOption($options, $current);
            $config['options'] = $this->labeledOptions($options);

            if ($current === '' && in_array($fieldKey, ['size'], true)) {
                array_unshift($config['options'], ['value' => '', 'label' => '—']);
            }
        }

        if ($widget === 'bitmask') {
            $selected = $this->enums->parseBitmask($current, $fieldKey, $kind);
            $config['selected'] = $selected;
            $config['flags'] = $this->labeledFlags(
                $this->enums->bitmaskTokens($fieldKey, $kind),
                $selected,
            );
        }

        if ($fieldKey === 'wear' && $kind === ProtoEnums::KIND_ITEM) {
            $hintKey = $this->enums->equipSlotHint($itemType, $subtype, $current);

            if ($hintKey !== null) {
                $fullKey = 'admin.proto.equip.' . $hintKey;
                if ($this->translator->has($fullKey)) {
                    $config['hint'] = $this->translator->get($fullKey);
                }
            }
        }

        return $config;
    }

    private function tokenLabel(string $token): string
    {
        $key = $this->enums->tokenI18nKey($token);

        return $this->translator->has($key) ? $this->translator->get($key) : $token;
    }

    /**
     * @param list<string> $tokens
     * @return list<array{value: string, label: string}>
     */
    private function labeledOptions(array $tokens): array
    {
        return SelectOptions::sortBy(array_map(fn (string $token): array => [
            'value' => $token,
            'label' => $this->tokenLabel($token),
        ], $tokens));
    }

    /**
     * @param list<string> $tokens
     * @param list<string> $selected
     * @return list<array{value: string, label: string, checked: bool}>
     */
    private function labeledFlags(array $tokens, array $selected): array
    {
        return SelectOptions::sortBy(array_map(fn (string $token): array => [
            'value' => $token,
            'label' => $this->tokenLabel($token),
            'checked' => in_array($token, $selected, true),
        ], $tokens));
    }

    /**
     * @return array<string, list<array{value: string, label: string}>>
     */
    public function subtypesJsonMap(): array
    {
        $map = [];

        foreach ($this->enums->itemTypes() as $type) {
            $map[$type] = $this->labeledOptions($this->enums->subtypesForItemType($type));
        }

        return $map;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function valueLabelsJsonMap(): array
    {
        $map = [];

        foreach ($this->enums->itemTypes() as $type) {
            $map[$type] = [];

            foreach ($this->enums->valueLabelsFor($type) as $field => $labelKey) {
                $fullKey = 'admin.proto.values.' . $labelKey;
                $map[$type][$field] = $this->translator->has($fullKey)
                    ? $this->translator->get($fullKey)
                    : $labelKey;
            }

            foreach ($this->enums->subtypesForItemType($type) as $subtype) {
                $subtypeKey = $type . ':' . $subtype;
                $map[$subtypeKey] = [];

                foreach ($this->enums->valueLabelsFor($type, $subtype) as $field => $labelKey) {
                    $fullKey = 'admin.proto.values.' . $labelKey;
                    $map[$subtypeKey][$field] = $this->translator->has($fullKey)
                        ? $this->translator->get($fullKey)
                        : $labelKey;
                }
            }
        }

        return $map;
    }
}
