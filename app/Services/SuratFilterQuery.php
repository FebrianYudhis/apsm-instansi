<?php

namespace App\Services;

use App\Models\Incoming;
use App\Models\Outcoming;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SuratFilterQuery
{
    public function validateIncoming(Request $request): array
    {
        $validated = $request->validate([
            'sumber_surat' => [
                'nullable',
                Rule::in(['semua', 'srikandi', 'non_srikandi']),
            ],
            'tanggal_dari' => ['nullable', 'date'],
            'tanggal_sampai' => [
                'nullable',
                'date',
                'after_or_equal:tanggal_dari',
            ],
        ]);

        return [
            'sumber_surat' => $validated['sumber_surat'] ?? 'semua',
            'tanggal_dari' => $validated['tanggal_dari'] ?? null,
            'tanggal_sampai' => $validated['tanggal_sampai'] ?? null,
        ];
    }

    public function validateOutgoing(Request $request): array
    {
        $validated = $request->validate([
            'jalur_pengiriman' => [
                'nullable',
                Rule::in(['semua', 'srikandi', 'non_srikandi']),
            ],
            'tanggal_dari' => ['nullable', 'date'],
            'tanggal_sampai' => [
                'nullable',
                'date',
                'after_or_equal:tanggal_dari',
            ],
        ]);

        return [
            'jalur_pengiriman' => $validated['jalur_pengiriman'] ?? 'semua',
            'tanggal_dari' => $validated['tanggal_dari'] ?? null,
            'tanggal_sampai' => $validated['tanggal_sampai'] ?? null,
        ];
    }

    public function incoming(int $year, array $filters): Builder
    {
        $query = Incoming::query()->where('tahun', $year);

        return $this->applyFilters(
            $query,
            $filters['sumber_surat'],
            $filters['tanggal_dari'],
            $filters['tanggal_sampai']
        );
    }

    public function outgoing(int $year, array $filters): Builder
    {
        $query = Outcoming::query()->where('tahun', $year);

        return $this->applyFilters(
            $query,
            $filters['jalur_pengiriman'],
            $filters['tanggal_dari'],
            $filters['tanggal_sampai']
        );
    }

    private function applyFilters(
        Builder $query,
        string $srikandiFilter,
        ?string $tanggalDari,
        ?string $tanggalSampai
    ): Builder {
        if ($srikandiFilter === 'srikandi') {
            $query->where('is_srikandi', true);
        } elseif ($srikandiFilter === 'non_srikandi') {
            $query->where('is_srikandi', false);
        }

        if ($tanggalDari) {
            $query->whereDate('tanggal_surat', '>=', $tanggalDari);
        }

        if ($tanggalSampai) {
            $query->whereDate('tanggal_surat', '<=', $tanggalSampai);
        }

        return $query;
    }
}
