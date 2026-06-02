<?php

namespace Joomla\Database;

interface DatabaseInterface
{
    public function getQuery(bool $new = false): object;
    public function setQuery(object $query, int $offset = 0, int $limit = 0): static;
    public function loadResult(): mixed;
    public function loadAssocList(?string $key = null): array;
    public function loadObjectList(?string $key = null): array;
    public function quote(mixed $text, bool $escape = true): string;
    public function quoteName(mixed $name, mixed $as = null): mixed;
    public function execute(): bool;
    public function insertObject(string $table, object &$object, ?string $key = null): bool;
    public function updateObject(string $table, object &$object, mixed $key, bool $nulls = false): bool;
}
