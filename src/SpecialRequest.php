<?php

namespace Nextbyte\Courier;

/**
 * An extra a courier offers with a vehicle, e.g. Lalamove's door-to-door delivery, sent with the
 * quotation and booking by its key. Lalamove groups some under a heading ("Door-to-Door Service"),
 * from which one at most can be chosen.
 */
class SpecialRequest
{
    /**
     * @param string      $key          The courier's code, e.g. "DOOR_TO_DOOR"
     * @param string      $description  The courier's own text, e.g. "Door-to-Door Service · by Driver + 1 Helper"
     * @param string|null $group        The heading it is listed under, e.g. "Door-to-Door Service"
     * @param int         $maxSelection How many of its group can be chosen together
     */
    public function __construct(
        public readonly string $key,
        public readonly string $description = '',
        public readonly ?string $group = null,
        public readonly int $maxSelection = 1,
    ) {
    }

    /**
     * Build a special request from a Lalamove City Info `specialRequests` entry.
     *
     * Lalamove's descriptions come with a "+" turned into spaces ("by Driver   1 Helper"); it is put back.
     */
    public static function fromLalamove(array $request): self
    {
        $clean = fn (string $text) => trim(preg_replace('/ {2,}/', ' + ', $text));
        $group = trim((string) ($request['parent_type'] ?? ''));

        return new self(
            (string) $request['name'],
            $clean((string) ($request['description'] ?? '')),
            $group !== '' ? $clean($group) : null,
            max(1, (int) ($request['max_selection'] ?? 1)),
        );
    }

    public static function fromArray(array $attributes): self
    {
        return new self(
            (string) $attributes['key'],
            (string) ($attributes['description'] ?? ''),
            $attributes['group'] ?? null,
            (int) ($attributes['maxSelection'] ?? 1),
        );
    }

    /**
     * The description without its group's heading: "by Driver + 1 Helper" for "Door-to-Door Service · by Driver + 1 Helper".
     */
    public function option(): string
    {
        if ($this->group !== null && str_starts_with($this->description, $this->group . ' · ')) {
            return substr($this->description, strlen($this->group . ' · '));
        }

        return $this->description !== '' ? $this->description : $this->key;
    }

    /**
     * @return array{key: string, description: string, group: string|null, maxSelection: int}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'description' => $this->description,
            'group' => $this->group,
            'maxSelection' => $this->maxSelection,
        ];
    }
}
