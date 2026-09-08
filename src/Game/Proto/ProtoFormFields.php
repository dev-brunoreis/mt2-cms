<?php

declare(strict_types=1);

namespace Mt2Cms\Game\Proto;

use Mt2Cms\I18n\Translator;

class ProtoFormFields
{
    public function __construct(private Translator $translator)
    {
    }

    /**
     * @param list<array{id: string, fields: list<string>}> $tabs
     * @param array<string, string> $record
     * @return list<array{id: string, label: string, active: bool, fields: list<array<string, mixed>>}>
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
            $decorated[] = [
                'id' => $tab['id'],
                'label' => $this->translator->has($tabKey) ? $this->translator->get($tabKey) : $tab['id'],
                'active' => $index === 0,
                'fields' => $fields,
            ];
        }

        return $decorated;
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
        $widget = ProtoEnums::widgetForField($kind, $fieldKey);
        $labelKey = $prefix . '.fields.' . $fieldKey;

        if (str_starts_with($fieldKey, 'value') && $kind === ProtoEnums::KIND_ITEM) {
            $valueLabels = ProtoEnums::valueLabelsFor($itemType, $subtype);
            $dynamicKey = $valueLabels[$fieldKey] ?? 'value_0';
            $labelKey = 'admin.proto.values.' . $dynamicKey;
        }

        $config = [
            'key' => $fieldKey,
            'label' => $this->translator->has($labelKey) ? $this->translator->get($labelKey) : $fieldKey,
            'widget' => $widget,
            'value' => $current,
        ];

        if ($widget === 'select' || $widget === 'subtype') {
            $options = $widget === 'subtype'
                ? ProtoEnums::subtypesForItemType($itemType)
                : ProtoEnums::optionsForField($kind, $fieldKey);
            $options = ProtoEnums::ensureOption($options, $current);
            $config['options'] = array_map(fn (string $token): array => [
                'value' => $token,
                'label' => $this->tokenLabel($token),
            ], $options);

            if ($current === '' && in_array($fieldKey, ['size'], true)) {
                array_unshift($config['options'], ['value' => '', 'label' => '—']);
            }
        }

        if ($widget === 'bitmask') {
            $selected = ProtoEnums::parseBitmask($current, $fieldKey);
            $config['selected'] = $selected;
            $config['flags'] = array_map(fn (string $token): array => [
                'value' => $token,
                'label' => $this->tokenLabel($token),
                'checked' => in_array($token, $selected, true),
            ], ProtoEnums::bitmaskTokens($fieldKey));
        }

        if ($fieldKey === 'wear' && $kind === ProtoEnums::KIND_ITEM) {
            $hintKey = ProtoEnums::equipSlotHint($itemType, $subtype, $current);

            if ($hintKey !== null) {
                $fullKey = 'admin.proto.equip.' . $hintKey;
                $config['hint'] = $this->translator->has($fullKey)
                    ? $this->translator->get($fullKey)
                    : null;
            }
        }

        return $config;
    }

    private function tokenLabel(string $token): string
    {
        $key = ProtoEnums::tokenI18nKey($token);

        return $this->translator->has($key) ? $this->translator->get($key) : $token;
    }

    /**
     * @return array<string, list<string>>
     */
    public function subtypesJsonMap(): array
    {
        return ProtoEnums::itemSubtypesByType();
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function valueLabelsJsonMap(): array
    {
        $map = [];

        foreach (ProtoEnums::itemTypes() as $type) {
            $map[$type] = [];

            foreach (ProtoEnums::valueLabelsFor($type) as $field => $labelKey) {
                $fullKey = 'admin.proto.values.' . $labelKey;
                $map[$type][$field] = $this->translator->has($fullKey)
                    ? $this->translator->get($fullKey)
                    : $labelKey;
            }

            foreach (ProtoEnums::subtypesForItemType($type) as $subtype) {
                $subtypeKey = $type . ':' . $subtype;
                $map[$subtypeKey] = [];

                foreach (ProtoEnums::valueLabelsFor($type, $subtype) as $field => $labelKey) {
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
