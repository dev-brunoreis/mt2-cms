<?php

declare(strict_types=1);

namespace Mt2Cms\Game\Proto;

use Mt2Cms\Game\GameProfile;

class ProtoSchemas
{
    public const KIND_ITEM = GameProfile::KIND_ITEM;
    public const KIND_MOB = GameProfile::KIND_MOB;

    public function __construct(private GameProfile $profile)
    {
    }

    /**
     * @return list<string>
     */
    public function columns(string $kind): array
    {
        return $this->profile->columns($kind);
    }

    /**
     * @return array<string, string>
     */
    public function defaults(string $kind): array
    {
        return $this->profile->defaults($kind);
    }

    /**
     * @return list<string>
     */
    public function listColumns(string $kind): array
    {
        return $this->profile->listColumns($kind);
    }

    /**
     * @return list<array{id: string, fields: list<string>}>
     */
    public function formTabs(string $kind): array
    {
        return $this->profile->formTabs($kind);
    }
}
