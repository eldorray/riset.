<?php

declare(strict_types=1);

namespace App\References;

/** Filter pencarian yang dipetakan tiap penyedia sesuai kemampuannya. */
final readonly class SearchFilters
{
    public function __construct(
        public ?int $yearFrom = null,
        public ?int $yearTo = null,
        public string $type = 'any', // any|article|book
        public bool $openAccess = false,
        public string $scope = 'all', // all|national|international — negara penerbit jurnal (Indonesia/lainnya)
    ) {}

    /** Filter nasional/internasional hanya bermakna untuk artikel jurnal. */
    public function scoped(): bool
    {
        return $this->scope !== 'all';
    }
}
