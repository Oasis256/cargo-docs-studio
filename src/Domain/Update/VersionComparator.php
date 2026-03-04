<?php

namespace CargoDocsStudio\Domain\Update;

class VersionComparator
{
    public function normalizeTagVersion(string $tag): string
    {
        $value = trim($tag);
        if (str_starts_with(strtolower($value), 'v')) {
            $value = substr($value, 1);
        }

        return preg_replace('/[^0-9A-Za-z.\-\+]/', '', $value) ?: '';
    }

    public function isNewer(string $latest, string $current): bool
    {
        $latestNorm = $this->normalizeTagVersion($latest);
        $currentNorm = $this->normalizeTagVersion($current);
        if ($latestNorm === '' || $currentNorm === '') {
            return false;
        }

        return version_compare($latestNorm, $currentNorm, '>');
    }
}

