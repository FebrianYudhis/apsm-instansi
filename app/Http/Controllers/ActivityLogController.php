<?php

namespace App\Http\Controllers;

use App\Models\Classification;
use App\Models\Digital;
use App\Models\Filelist;
use App\Models\Incoming;
use App\Models\Outcoming;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Yajra\DataTables\DataTables;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Activity::with(['causer', 'subject']);

            if ($request->filled('user_id')) {
                if ($request->user_id === 'sistem') {
                    $query->whereNull('causer_id');
                } else {
                    $query->where('causer_id', $request->user_id);
                }
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
                            $buttons .= '<a href="'.route('digital.edit', $data->subject_id).'" target="_blank" class="btn btn-sm btn-success" title="Data Sekarang"><i class="fa fa-eye"></i></a>';
                        }
                    }

                    $buttons .= '</div>';

                    return $buttons === '<div class="d-flex align-items-center justify-content-center"></div>' ? '-' : $buttons;
                })
                ->rawColumns(['description', 'perubahan'])
                ->toJson();
        }

        $data = [
            'judul' => 'Log Aktivitas',
            'users' => User::all(),
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
