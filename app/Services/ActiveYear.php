<?php

namespace App\Services;

use InvalidArgumentException;

class ActiveYear
{
    public const SESSION_KEY = 'active_year';

    public function current(): int
    {
        $year = (int) session(self::SESSION_KEY);

        if (! $this->isSelectable($year)) {
            $year = (int) now()->year;
            $this->select($year);
        }

        return $year;
    }

    public function select(int $year): void
    {
        if (! $this->isSelectable($year)) {
            throw new InvalidArgumentException('Tahun aktif berada di luar rentang yang diizinkan.');
        }

        session([self::SESSION_KEY => $year]);
    }

    public function isSelectable(int $year): bool
    {
        $currentYear = (int) now()->year;
        $startYear = min((int) config('app.start_year', 2025), $currentYear);

        return $year >= $startYear && $year <= $currentYear;
    }
}
