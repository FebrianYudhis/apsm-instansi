<?php

namespace App\Http\Controllers;

use App\Models\Classification;
use App\Models\Digital;
use App\Models\Filelist;
use App\Models\Incoming;
use App\Models\Outcoming;
use App\Models\User;
use App\Services\ActiveYear;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Yajra\DataTables\DataTables;

class ActivityLogController extends Controller
{
    public function __construct(
        private ActiveYear $activeYear
    ) {}

    public function ringkasan(Request $request)
    {
        $selectedYear = (int) $request->input('tahun', $this->activeYear->current());
        $rawMonth = $request->input('bulan', (string) Carbon::now()->month);
        $selectedMonth = ($rawMonth === 'semua' || ! is_numeric($rawMonth)) ? 'semua' : (int) $rawMonth;
        $selectedUserId = $request->input('user_id');

        $query = Activity::query()
            ->whereYear('created_at', $selectedYear);

        if ($selectedMonth !== 'semua') {
            $query->whereMonth('created_at', $selectedMonth);
        }

        if (! empty($selectedUserId)) {
            if ($selectedUserId === 'sistem') {
                $query->whereNull('causer_id');
            } else {
                $query->where('causer_id', (int) $selectedUserId);
            }
        }

        $domainSubjectTypes = [
            Incoming::class,
            Outcoming::class,
            Digital::class,
            Filelist::class,
            Classification::class,
        ];

        $totalCreated = (clone $query)
            ->where('event', 'created')
            ->whereIn('subject_type', $domainSubjectTypes)
            ->count();
        $totalUpdated = (clone $query)
            ->where('event', 'updated')
            ->whereIn('subject_type', $domainSubjectTypes)
            ->count();
        $totalDeleted = (clone $query)
            ->where('event', 'deleted')
            ->whereIn('subject_type', $domainSubjectTypes)
            ->count();
        $totalAktivitas = $totalCreated + $totalUpdated + $totalDeleted;

        $totalLogin = (clone $query)
            ->where('log_name', 'auth')
            ->where('description', 'User successfully logged in')
            ->count();
        $totalExported = (clone $query)
            ->where(function ($q) {
                $q->where('event', 'exported')
                    ->orWhere('log_name', 'export');
            })
            ->count();

        $eventStatsBySubject = (clone $query)
            ->whereIn('subject_type', $domainSubjectTypes)
            ->selectRaw('subject_type, event, count(*) as total')
            ->groupBy('subject_type', 'event')
            ->get();

        $categories = [
            [
                'key' => 'incoming',
                'label' => 'Surat Masuk',
                'icon' => 'fa-inbox',
                'badge' => 'badge-info',
                'created' => $eventStatsBySubject->where('subject_type', Incoming::class)->where('event', 'created')->sum('total'),
                'updated' => $eventStatsBySubject->where('subject_type', Incoming::class)->where('event', 'updated')->sum('total'),
                'deleted' => $eventStatsBySubject->where('subject_type', Incoming::class)->where('event', 'deleted')->sum('total'),
            ],
            [
                'key' => 'outcoming',
                'label' => 'Surat Keluar',
                'icon' => 'fa-paper-plane',
                'badge' => 'badge-primary',
                'created' => $eventStatsBySubject->where('subject_type', Outcoming::class)->where('event', 'created')->sum('total'),
                'updated' => $eventStatsBySubject->where('subject_type', Outcoming::class)->where('event', 'updated')->sum('total'),
                'deleted' => $eventStatsBySubject->where('subject_type', Outcoming::class)->where('event', 'deleted')->sum('total'),
            ],
            [
                'key' => 'digital',
                'label' => 'Surat Digital',
                'icon' => 'fa-file-alt',
                'badge' => 'badge-success',
                'created' => $eventStatsBySubject->where('subject_type', Digital::class)->where('event', 'created')->sum('total'),
                'updated' => $eventStatsBySubject->where('subject_type', Digital::class)->where('event', 'updated')->sum('total'),
                'deleted' => $eventStatsBySubject->where('subject_type', Digital::class)->where('event', 'deleted')->sum('total'),
            ],
            [
                'key' => 'filelist',
                'label' => 'Berkas',
                'icon' => 'fa-folder',
                'badge' => 'badge-warning',
                'created' => $eventStatsBySubject->where('subject_type', Filelist::class)->where('event', 'created')->sum('total'),
                'updated' => $eventStatsBySubject->where('subject_type', Filelist::class)->where('event', 'updated')->sum('total'),
                'deleted' => $eventStatsBySubject->where('subject_type', Filelist::class)->where('event', 'deleted')->sum('total'),
            ],
            [
                'key' => 'classification',
                'label' => 'Klasifikasi',
                'icon' => 'fa-tags',
                'badge' => 'badge-secondary',
                'created' => $eventStatsBySubject->where('subject_type', Classification::class)->where('event', 'created')->sum('total'),
                'updated' => $eventStatsBySubject->where('subject_type', Classification::class)->where('event', 'updated')->sum('total'),
                'deleted' => $eventStatsBySubject->where('subject_type', Classification::class)->where('event', 'deleted')->sum('total'),
            ],
        ];

        foreach ($categories as &$category) {
            $category['total'] = $category['created'] + $category['updated'] + $category['deleted'];
        }
        unset($category);

        $userStats = (clone $query)
            ->whereIn('subject_type', $domainSubjectTypes)
            ->whereIn('event', ['created', 'updated', 'deleted'])
            ->selectRaw('causer_id, event, count(*) as total')
            ->groupBy('causer_id', 'event')
            ->get();

        $users = User::orderBy('name', 'asc')->get();

        $userBreakdown = $users->map(function ($user) use ($userStats) {
            $userRows = $userStats->where('causer_id', $user->id);
            $createdCount = $userRows->where('event', 'created')->sum('total');
            $updatedCount = $userRows->where('event', 'updated')->sum('total');
            $deletedCount = $userRows->where('event', 'deleted')->sum('total');
            $total = $createdCount + $updatedCount + $deletedCount;

            return [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'created' => $createdCount,
                'updated' => $updatedCount,
                'deleted' => $deletedCount,
                'total' => $total,
            ];
        })->filter(function ($item) use ($selectedUserId) {
            if (! empty($selectedUserId) && $selectedUserId !== 'sistem') {
                return $item['id'] === (int) $selectedUserId;
            }

            return true;
        })->sortByDesc('total')->values();

        $systemRows = $userStats->whereNull('causer_id');
        $systemCreated = $systemRows->where('event', 'created')->sum('total');
        $systemUpdated = $systemRows->where('event', 'updated')->sum('total');
        $systemDeleted = $systemRows->where('event', 'deleted')->sum('total');
        $systemTotal = $systemCreated + $systemUpdated + $systemDeleted;

        if ($systemTotal > 0 && (empty($selectedUserId) || $selectedUserId === 'sistem')) {
            $userBreakdown->push([
                'id' => 'sistem',
                'name' => 'Sistem (Latar Belakang)',
                'username' => 'system',
                'created' => $systemCreated,
                'updated' => $systemUpdated,
                'deleted' => $systemDeleted,
                'total' => $systemTotal,
            ]);
        }

        $daftarBulan = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        $startYear = min((int) config('app.start_year', 2025), Carbon::now()->year);
        $currentYear = Carbon::now()->year;
        $daftarTahun = range($startYear, $currentYear);
        if (! in_array($selectedYear, $daftarTahun, true)) {
            $daftarTahun[] = $selectedYear;
            sort($daftarTahun);
        }

        $data = [
            'judul' => 'Ringkasan Aktivitas',
            'users' => $users,
            'selectedYear' => $selectedYear,
            'selectedMonth' => $selectedMonth,
            'selectedUserId' => $selectedUserId,
            'daftarBulan' => $daftarBulan,
            'daftarTahun' => $daftarTahun,
            'totalAktivitas' => $totalAktivitas,
            'totalLogin' => $totalLogin,
            'totalCreated' => $totalCreated,
            'totalUpdated' => $totalUpdated,
            'totalDeleted' => $totalDeleted,
            'totalExported' => $totalExported,
            'categories' => $categories,
            'userBreakdown' => $userBreakdown,
        ];

        return view('app.activity-log.ringkasan', $data);
    }

    public function index(Request $request)
    {
        if ($request->ajax() || $request->wantsJson()) {
            $query = Activity::with(['causer', 'subject']);

            if ($request->filled('user_id')) {
                if ($request->user_id === 'sistem') {
                    $query->whereNull('causer_id');
                } else {
                    $query->where('causer_id', (int) $request->user_id);
                }
            }

            if ($request->filled('subject_type')) {
                $subjectMap = [
                    'incoming' => Incoming::class,
                    'outcoming' => Outcoming::class,
                    'digital' => Digital::class,
                    'filelist' => Filelist::class,
                    'classification' => Classification::class,
                    Incoming::class => Incoming::class,
                    Outcoming::class => Outcoming::class,
                    Digital::class => Digital::class,
                    Filelist::class => Filelist::class,
                    Classification::class => Classification::class,
                ];

                if (isset($subjectMap[$request->subject_type])) {
                    $query->where('subject_type', $subjectMap[$request->subject_type]);
                }
            }

            if ($request->filled('event')) {
                $query->where('event', $request->event);
            }

            if ($request->filled('tahun')) {
                $query->whereYear('created_at', (int) $request->tahun);
            }

            if ($request->filled('bulan') && $request->bulan !== 'semua') {
                $query->whereMonth('created_at', (int) $request->bulan);
            }

            return DataTables::of($query)
                ->addColumn('waktu', function ($data) {
                    return Carbon::parse($data->created_at)->translatedFormat('d F Y H:i:s');
                })
                ->addColumn('pelaku', function ($data) {
                    return $data->causer ? $data->causer->name : 'Sistem';
                })
                ->editColumn('description', function (Activity $activity) {
                    $presentation = $this->descriptionPresentation($activity);

                    return view('app.activity-log.description-badge', $presentation)->render();
                })
                ->addColumn('model', function (Activity $activity) {
                    return $this->modelLabel($activity);
                })
                ->addColumn('perubahan', function ($data) {
                    $buttons = '<div class="d-flex align-items-center justify-content-center">';

                    if ($data->properties && count($data->properties) > 0) {
                        $buttons .= '<button class="btn btn-sm btn-info btn-detail mr-1" data-properties=\''.htmlspecialchars(json_encode($data->properties), ENT_QUOTES, 'UTF-8').'\' title="Detail Perubahan"><i class="fa fa-info-circle"></i></button>';
                    }

                    if ($data->event !== 'deleted' && $data->subject) {
                        if ($data->subject_type === 'App\Models\Incoming' && $data->subject_id) {
                            $buttons .= '<a href="'.route('surat.detailItem', ['masuk', $data->subject_id]).'" target="_blank" class="btn btn-sm btn-success" title="Data Sekarang"><i class="fa fa-eye"></i></a>';
                        } elseif ($data->subject_type === 'App\Models\Outcoming' && $data->subject_id) {
                            $buttons .= '<a href="'.route('surat.detailItem', ['keluar', $data->subject_id]).'" target="_blank" class="btn btn-sm btn-success" title="Data Sekarang"><i class="fa fa-eye"></i></a>';
                        } elseif ($data->subject_type === 'App\Models\Digital' && $data->subject_id) {
                            $buttons .= '<a href="'.route('surat.detailItem', ['digital', $data->subject_id]).'" target="_blank" class="btn btn-sm btn-success" title="Data Sekarang"><i class="fa fa-eye"></i></a>';
                        }
                    }

                    $buttons .= '</div>';

                    return $buttons === '<div class="d-flex align-items-center justify-content-center"></div>' ? '-' : $buttons;
                })
                ->rawColumns(['description', 'perubahan'])
                ->toJson();
        }

        $startYear = min((int) config('app.start_year', 2025), Carbon::now()->year);
        $currentYear = Carbon::now()->year;
        $daftarTahun = range($startYear, $currentYear);

        $daftarBulan = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        $data = [
            'judul' => 'Log Aktivitas',
            'users' => User::orderBy('name')->get(),
            'daftarTahun' => $daftarTahun,
            'daftarBulan' => $daftarBulan,
            'filterUserId' => $request->query('user_id', ''),
            'filterSubjectType' => $request->query('subject_type', ''),
            'filterEvent' => $request->query('event', ''),
            'filterTahun' => $request->query('tahun', ''),
            'filterBulan' => $request->query('bulan', ''),
        ];

        return view('app.activity-log.index', $data);
    }

    /**
     * @return array{label: string, badgeClass: string}
     */
    private function descriptionPresentation(Activity $activity): array
    {
        return match ($activity->event) {
            'created' => ['label' => 'Data Dibuat', 'badgeClass' => 'badge-success'],
            'updated' => ['label' => 'Data Diubah', 'badgeClass' => 'badge-info'],
            'deleted' => ['label' => 'Data Dihapus', 'badgeClass' => 'badge-danger'],
            'exported' => ['label' => 'Ekspor Disiapkan', 'badgeClass' => 'badge-warning'],
            default => match ($activity->description) {
                'User successfully logged in' => ['label' => 'Masuk ke Sistem', 'badgeClass' => 'badge-primary'],
                'User successfully logged out' => ['label' => 'Keluar dari Sistem', 'badgeClass' => 'badge-secondary'],
                default => ['label' => 'Aktivitas Sistem', 'badgeClass' => 'badge-secondary'],
            },
        };
    }

    private function modelLabel(Activity $activity): string
    {
        if (! $activity->subject_type) {
            return match ($activity->log_name) {
                'auth' => 'Autentikasi',
                'export' => 'Ekspor Data',
                'api-token' => 'Token API',
                default => 'Sistem',
            };
        }

        return match ($activity->subject_type) {
            Incoming::class => 'Surat Masuk',
            Outcoming::class => 'Surat Keluar',
            Digital::class => 'Surat Digital',
            Classification::class => 'Klasifikasi',
            Filelist::class => 'Berkas',
            User::class => 'Pengguna',
            default => Str::headline(class_basename($activity->subject_type)),
        };
    }
}
