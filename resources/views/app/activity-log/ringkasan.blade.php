@extends('layouts.main')

@push('css')
    <style>
        .stat-card {
            border: 1px solid var(--apsm-border);
            border-radius: 12px;
            background: #ffffff;
            padding: 20px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            box-shadow: 0 4px 14px rgba(17, 37, 62, 0.05);
            display: flex;
            align-items: center;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(17, 37, 62, 0.1);
        }

        .stat-icon-wrapper {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin-right: 16px;
            flex-shrink: 0;
        }

        .stat-icon-primary {
            background: rgba(23, 105, 170, 0.12);
            color: #1769aa;
        }

        .stat-icon-success {
            background: rgba(33, 134, 91, 0.12);
            color: #21865b;
        }

        .stat-icon-info {
            background: rgba(24, 123, 143, 0.12);
            color: #187b8f;
        }

        .stat-icon-danger {
            background: rgba(194, 65, 59, 0.12);
            color: #c2413b;
        }

        .stat-icon-dark {
            background: rgba(34, 49, 63, 0.1);
            color: #22313f;
        }

        .stat-value {
            font-size: 26px;
            font-weight: 800;
            line-height: 1.1;
            color: var(--apsm-text);
            margin-bottom: 2px;
        }

        .stat-label {
            font-size: 12px;
            font-weight: 700;
            color: var(--apsm-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-subtext {
            font-size: 11px;
            color: var(--apsm-muted);
            margin-top: 2px;
        }

        .badge-count {
            min-width: 28px;
            padding: 4px 8px;
            font-weight: 700;
            font-size: 12px;
            border-radius: 6px;
        }
    </style>
@endpush

@section('konten')
    <div class="mt-4">
        <!-- Header & Breadcrumb -->
        <div class="card mb-4">
            <div class="card-header bg-white py-3">
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between">
                    <div>
                        <h4 class="mb-1 font-weight-bold text-dark">Ringkasan Aktivitas Sistem</h4>
                        <p class="text-muted mb-0 small">
                            Statistik aktivitas autentikasi login, penambahan data, perubahan, dan penghapusan data.
                        </p>
                    </div>
                    <div class="mt-3 mt-md-0 d-flex flex-wrap align-items-center">
                        <a href="{{ route('activity-log') }}" class="btn btn-sm btn-outline-primary mr-2">
                            <i class="fa fa-list-alt mr-1"></i> Buka Log Terperinci
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-body bg-light py-3">
                <!-- Filter Form -->
                <form action="{{ route('activity-log.ringkasan') }}" method="GET" class="form-row align-items-end">
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-2 mb-md-0">
                        <label for="filterBulan" class="font-weight-bold small text-muted mb-1">Pilih Bulan</label>
                        <select name="bulan" id="filterBulan" class="form-control form-control-sm custom-select">
                            <option value="semua" {{ (string) $selectedMonth === 'semua' ? 'selected' : '' }}>
                                Semua Bulan (1 Tahun)
                            </option>
                            @foreach ($daftarBulan as $noBulan => $namaBulan)
                                <option value="{{ $noBulan }}" {{ (int) $selectedMonth === $noBulan ? 'selected' : '' }}>
                                    {{ $namaBulan }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-lg-2 col-md-3 col-sm-6 mb-2 mb-md-0">
                        <label for="filterTahun" class="font-weight-bold small text-muted mb-1">Pilih Tahun</label>
                        <select name="tahun" id="filterTahun" class="form-control form-control-sm custom-select">
                            @foreach ($daftarTahun as $tahun)
                                <option value="{{ $tahun }}" {{ $selectedYear === $tahun ? 'selected' : '' }}>
                                    Tahun {{ $tahun }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-lg-4 col-md-5 col-sm-6 mb-2 mb-md-0">
                        <label for="filterUser" class="font-weight-bold small text-muted mb-1">Pelaku (Pengguna)</label>
                        <select name="user_id" id="filterUser" class="form-control form-control-sm custom-select">
                            <option value="">Semua Pelaku & Pengguna</option>
                            <option value="sistem" {{ $selectedUserId === 'sistem' ? 'selected' : '' }}>
                                Sistem (Aksi Latar Belakang)
                            </option>
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" {{ (string) $selectedUserId === (string) $user->id ? 'selected' : '' }}>
                                    {{ $user->name }} ({{ $user->username }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-lg-3 col-md-12 col-sm-6 mt-2 mt-lg-0 d-flex">
                        <button type="submit" class="btn btn-sm btn-primary flex-fill mr-1">
                            <i class="fa fa-filter mr-1"></i> Terapkan Filter
                        </button>
                        <a href="{{ route('activity-log.ringkasan') }}" class="btn btn-sm btn-outline-secondary" title="Reset Filter">
                            <i class="fa fa-undo"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        @php
            $filterParams = array_filter([
                'tahun' => $selectedYear,
                'bulan' => $selectedMonth !== 'semua' ? $selectedMonth : null,
                'user_id' => ! empty($selectedUserId) ? $selectedUserId : null,
            ]);
        @endphp

        <!-- Metric Cards -->
        <div class="row mb-4">
            <!-- Created -->
            <div class="col-xl-3 col-md-6 mb-3 mb-xl-0">
                <a href="{{ route('activity-log', array_merge($filterParams, ['event' => 'created'])) }}" class="text-decoration-none">
                    <div class="stat-card">
                        <div class="stat-icon-wrapper stat-icon-success">
                            <i class="fa fa-plus-circle"></i>
                        </div>
                        <div>
                            <div class="stat-value text-success">{{ number_format($totalCreated, 0, ',', '.') }}</div>
                            <div class="stat-label">Data Ditambahkan</div>
                            <div class="stat-subtext">Pencatatan data baru &rarr;</div>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Updated -->
            <div class="col-xl-3 col-md-6 mb-3 mb-xl-0">
                <a href="{{ route('activity-log', array_merge($filterParams, ['event' => 'updated'])) }}" class="text-decoration-none">
                    <div class="stat-card">
                        <div class="stat-icon-wrapper stat-icon-info">
                            <i class="fa fa-edit"></i>
                        </div>
                        <div>
                            <div class="stat-value text-info">{{ number_format($totalUpdated, 0, ',', '.') }}</div>
                            <div class="stat-label">Data Diubah</div>
                            <div class="stat-subtext">Pembaruan & edit rekaman &rarr;</div>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Deleted -->
            <div class="col-xl-3 col-md-6 mb-3 mb-xl-0">
                <a href="{{ route('activity-log', array_merge($filterParams, ['event' => 'deleted'])) }}" class="text-decoration-none">
                    <div class="stat-card">
                        <div class="stat-icon-wrapper stat-icon-danger">
                            <i class="fa fa-trash-alt"></i>
                        </div>
                        <div>
                            <div class="stat-value text-danger">{{ number_format($totalDeleted, 0, ',', '.') }}</div>
                            <div class="stat-label">Data Dihapus</div>
                            <div class="stat-subtext">Penghapusan rekaman &rarr;</div>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Total -->
            <div class="col-xl-3 col-md-6 mb-3 mb-xl-0">
                <a href="{{ route('activity-log', $filterParams) }}" class="text-decoration-none">
                    <div class="stat-card">
                        <div class="stat-icon-wrapper stat-icon-primary">
                            <i class="fa fa-database"></i>
                        </div>
                        <div>
                            <div class="stat-value text-primary">{{ number_format($totalAktivitas, 0, ',', '.') }}</div>
                            <div class="stat-label">Total Aksi Data</div>
                            <div class="stat-subtext">Buka semua log periode ini &rarr;</div>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <!-- Detail Breakdown Tables -->
        <div class="row">
            <!-- Breakdown per Kategori Modul -->
            <div class="{{ empty($selectedUserId) ? 'col-lg-6' : 'col-lg-12' }} mb-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                        <h6 class="mb-0 font-weight-bold text-dark">
                            <i class="fa fa-database mr-2 text-primary"></i> Rincian Per Kategori Data
                        </h6>
                        <span class="badge badge-light border text-muted">
                            Periode: {{ $selectedMonth === 'semua' ? 'Semua Bulan' : ($daftarBulan[$selectedMonth] ?? $selectedMonth) }} {{ $selectedYear }}
                        </span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Kategori / Modul</th>
                                        <th class="text-center">Ditambahkan</th>
                                        <th class="text-center">Diubah</th>
                                        <th class="text-center">Dihapus</th>
                                        <th class="text-center">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $sumCreated = 0;
                                        $sumUpdated = 0;
                                        $sumDeleted = 0;
                                        $sumTotal = 0;
                                    @endphp
                                    @foreach ($categories as $cat)
                                        @php
                                            $sumCreated += $cat['created'];
                                            $sumUpdated += $cat['updated'];
                                            $sumDeleted += $cat['deleted'];
                                            $sumTotal += $cat['total'];
                                        @endphp
                                        <tr>
                                            <td class="font-weight-bold">
                                                <a href="{{ route('activity-log', array_merge($filterParams, ['subject_type' => $cat['key']])) }}" class="text-dark text-decoration-none" title="Lihat semua log {{ $cat['label'] }}">
                                                    <i class="fa {{ $cat['icon'] }} mr-2 text-muted" style="width: 16px;"></i>
                                                    {{ $cat['label'] }}
                                                </a>
                                            </td>
                                            <td class="text-center">
                                                @if ($cat['created'] > 0)
                                                    <a href="{{ route('activity-log', array_merge($filterParams, ['subject_type' => $cat['key'], 'event' => 'created'])) }}" class="badge badge-count badge-success text-decoration-none" title="Lihat log {{ $cat['label'] }} dibuat">
                                                        {{ number_format($cat['created'], 0, ',', '.') }}
                                                    </a>
                                                @else
                                                    <span class="badge badge-count badge-light text-muted">0</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if ($cat['updated'] > 0)
                                                    <a href="{{ route('activity-log', array_merge($filterParams, ['subject_type' => $cat['key'], 'event' => 'updated'])) }}" class="badge badge-count badge-info text-decoration-none" title="Lihat log {{ $cat['label'] }} diubah">
                                                        {{ number_format($cat['updated'], 0, ',', '.') }}
                                                    </a>
                                                @else
                                                    <span class="badge badge-count badge-light text-muted">0</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if ($cat['deleted'] > 0)
                                                    <a href="{{ route('activity-log', array_merge($filterParams, ['subject_type' => $cat['key'], 'event' => 'deleted'])) }}" class="badge badge-count badge-danger text-decoration-none" title="Lihat log {{ $cat['label'] }} dihapus">
                                                        {{ number_format($cat['deleted'], 0, ',', '.') }}
                                                    </a>
                                                @else
                                                    <span class="badge badge-count badge-light text-muted">0</span>
                                                @endif
                                            </td>
                                            <td class="text-center font-weight-bold">
                                                @if ($cat['total'] > 0)
                                                    <a href="{{ route('activity-log', array_merge($filterParams, ['subject_type' => $cat['key']])) }}" class="text-primary text-decoration-none" title="Lihat seluruh log {{ $cat['label'] }}">
                                                        {{ number_format($cat['total'], 0, ',', '.') }}
                                                    </a>
                                                @else
                                                    <span class="text-muted">0</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-light font-weight-bold">
                                    <tr>
                                        <td>
                                            <a href="{{ route('activity-log', $filterParams) }}" class="text-dark text-decoration-none" title="Buka seluruh log data periode ini">
                                                TOTAL AKSI DATA
                                            </a>
                                        </td>
                                        <td class="text-center">
                                            <a href="{{ route('activity-log', array_merge($filterParams, ['event' => 'created'])) }}" class="text-success text-decoration-none" title="Buka log data ditambahkan">
                                                {{ number_format($sumCreated, 0, ',', '.') }}
                                            </a>
                                        </td>
                                        <td class="text-center">
                                            <a href="{{ route('activity-log', array_merge($filterParams, ['event' => 'updated'])) }}" class="text-info text-decoration-none" title="Buka log data diubah">
                                                {{ number_format($sumUpdated, 0, ',', '.') }}
                                            </a>
                                        </td>
                                        <td class="text-center">
                                            <a href="{{ route('activity-log', array_merge($filterParams, ['event' => 'deleted'])) }}" class="text-danger text-decoration-none" title="Buka log data dihapus">
                                                {{ number_format($sumDeleted, 0, ',', '.') }}
                                            </a>
                                        </td>
                                        <td class="text-center">
                                            <a href="{{ route('activity-log', $filterParams) }}" class="text-primary text-decoration-none" title="Buka seluruh log data periode ini">
                                                {{ number_format($sumTotal, 0, ',', '.') }}
                                            </a>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            @if (empty($selectedUserId))
                <!-- Breakdown per Pelaku / User -->
                <div class="col-lg-6 mb-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-header bg-white py-3 d-flex align-items-center justify-content-between">
                            <h6 class="mb-0 font-weight-bold text-dark">
                                <i class="fa fa-users mr-2 text-info"></i> Aktivitas Per Pengguna (Pelaku)
                            </h6>
                            <span class="badge badge-light border text-muted">
                                {{ $userBreakdown->count() }} Pelaku Terdata
                            </span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Pengguna / Pelaku</th>
                                            <th class="text-center">Tambah</th>
                                            <th class="text-center">Ubah</th>
                                            <th class="text-center">Hapus</th>
                                            <th class="text-center">Total Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $userSumCreated = 0;
                                            $userSumUpdated = 0;
                                            $userSumDeleted = 0;
                                            $userSumTotal = 0;
                                        @endphp
                                        @forelse ($userBreakdown as $userItem)
                                            @php
                                                $userSumCreated += $userItem['created'];
                                                $userSumUpdated += $userItem['updated'];
                                                $userSumDeleted += $userItem['deleted'];
                                                $userSumTotal += $userItem['total'];
                                            @endphp
                                            <tr>
                                                <td>
                                                    <a href="{{ route('activity-log', array_merge($filterParams, ['user_id' => $userItem['id']])) }}" class="text-dark font-weight-bold text-decoration-none" title="Lihat semua log pengguna ini">
                                                        {{ $userItem['name'] }}
                                                    </a>
                                                    <div><small class="text-muted">{{ $userItem['username'] }}</small></div>
                                                </td>
                                                <td class="text-center font-weight-bold">
                                                    @if ($userItem['created'] > 0)
                                                        <a href="{{ route('activity-log', array_merge($filterParams, ['user_id' => $userItem['id'], 'event' => 'created'])) }}" class="text-success text-decoration-none" title="Lihat log tambah data pengguna ini">
                                                            {{ number_format($userItem['created'], 0, ',', '.') }}
                                                        </a>
                                                    @else
                                                        <span class="text-muted">0</span>
                                                    @endif
                                                </td>
                                                <td class="text-center font-weight-bold">
                                                    @if ($userItem['updated'] > 0)
                                                        <a href="{{ route('activity-log', array_merge($filterParams, ['user_id' => $userItem['id'], 'event' => 'updated'])) }}" class="text-info text-decoration-none" title="Lihat log ubah data pengguna ini">
                                                            {{ number_format($userItem['updated'], 0, ',', '.') }}
                                                        </a>
                                                    @else
                                                        <span class="text-muted">0</span>
                                                    @endif
                                                </td>
                                                <td class="text-center font-weight-bold">
                                                    @if ($userItem['deleted'] > 0)
                                                        <a href="{{ route('activity-log', array_merge($filterParams, ['user_id' => $userItem['id'], 'event' => 'deleted'])) }}" class="text-danger text-decoration-none" title="Lihat log hapus data pengguna ini">
                                                            {{ number_format($userItem['deleted'], 0, ',', '.') }}
                                                        </a>
                                                    @else
                                                        <span class="text-muted">0</span>
                                                    @endif
                                                </td>
                                                <td class="text-center font-weight-bold">
                                                    @if ($userItem['total'] > 0)
                                                        <a href="{{ route('activity-log', array_merge($filterParams, ['user_id' => $userItem['id']])) }}" class="text-primary text-decoration-none" title="Lihat total aksi pengguna ini">
                                                            {{ number_format($userItem['total'], 0, ',', '.') }}
                                                        </a>
                                                    @else
                                                        <span class="text-muted">0</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-4">
                                                    <i class="fa fa-info-circle mr-1"></i> Tidak ada aktivitas manipulasi data pada periode ini.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                    @if ($userBreakdown->isNotEmpty())
                                        <tfoot class="bg-light font-weight-bold">
                                            <tr>
                                                <td>TOTAL KONTRIBUSI</td>
                                                <td class="text-center">
                                                    <a href="{{ route('activity-log', array_merge($filterParams, ['event' => 'created'])) }}" class="text-success text-decoration-none">
                                                        {{ number_format($userSumCreated, 0, ',', '.') }}
                                                    </a>
                                                </td>
                                                <td class="text-center">
                                                    <a href="{{ route('activity-log', array_merge($filterParams, ['event' => 'updated'])) }}" class="text-info text-decoration-none">
                                                        {{ number_format($userSumUpdated, 0, ',', '.') }}
                                                    </a>
                                                </td>
                                                <td class="text-center">
                                                    <a href="{{ route('activity-log', array_merge($filterParams, ['event' => 'deleted'])) }}" class="text-danger text-decoration-none">
                                                        {{ number_format($userSumDeleted, 0, ',', '.') }}
                                                    </a>
                                                </td>
                                                <td class="text-center">
                                                    <a href="{{ route('activity-log', $filterParams) }}" class="text-primary text-decoration-none">
                                                        {{ number_format($userSumTotal, 0, ',', '.') }}
                                                    </a>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    @endif
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
