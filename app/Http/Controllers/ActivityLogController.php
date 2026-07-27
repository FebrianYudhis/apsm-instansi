<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Yajra\DataTables\DataTables;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Activity::with('causer');

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
                ->addColumn('model', function ($data) {
                    if (! $data->subject_type) {
                        return '-';
                    }
                    $parts = explode('\\', $data->subject_type);

                    return end($parts);
                })
                ->addColumn('perubahan', function ($data) {
                    $buttons = '<div class="d-flex align-items-center justify-content-center">';

                    if ($data->properties && count($data->properties) > 0) {
                        $buttons .= '<button class="btn btn-sm btn-info btn-detail mr-1" data-properties=\''.htmlspecialchars(json_encode($data->properties), ENT_QUOTES, 'UTF-8').'\' title="Detail Perubahan"><i class="fa fa-info-circle"></i></button>';
                    }

                    if ($data->subject_type === 'App\Models\Incoming' && $data->subject_id) {
                        $buttons .= '<a href="'.route('surat.detailItem', ['masuk', $data->subject_id]).'" target="_blank" class="btn btn-sm btn-success" title="Data Sekarang"><i class="fa fa-eye"></i></a>';
                    } elseif ($data->subject_type === 'App\Models\Outcoming' && $data->subject_id) {
                        $buttons .= '<a href="'.route('surat.detailItem', ['keluar', $data->subject_id]).'" target="_blank" class="btn btn-sm btn-success" title="Data Sekarang"><i class="fa fa-eye"></i></a>';
                    } elseif ($data->subject_type === 'App\Models\Digital' && $data->subject_id) {
                        $buttons .= '<a href="'.route('digital.edit', $data->subject_id).'" target="_blank" class="btn btn-sm btn-success" title="Data Sekarang"><i class="fa fa-eye"></i></a>';
                    }

                    $buttons .= '</div>';

                    return $buttons === '<div class="d-flex align-items-center justify-content-center"></div>' ? '-' : $buttons;
                })
                ->rawColumns(['perubahan'])
                ->toJson();
        }

        $data = [
            'judul' => 'Log Aktivitas',
            'users' => User::all(),
        ];

        return view('app.activity-log.index', $data);
    }
}
