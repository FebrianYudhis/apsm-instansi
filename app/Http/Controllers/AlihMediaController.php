<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessAlihMediaWatermarkJob;
use App\Models\Filelist;
use App\Models\Status;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;
use RealRashid\SweetAlert\Facades\Alert;
use Throwable;

class AlihMediaController extends Controller
{
    public function proses(Request $request, $id)
    {
        $request->validate([
            'passcode_access' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
        ]);

        $mfaSecret = config('services.mfa.secret');
        if (empty($mfaSecret)) {
            Alert::error('Gagal', 'MFA_SECRET belum dikonfigurasi');

            return redirect()->route('alih-media.penyeleksian');
        }

        $google2fa = new Google2FA;
        $valid = false;
        try {
            if (! empty($request->passcode_access)) {
                $valid = $google2fa->verifyKey($mfaSecret, $request->passcode_access);
            }
        } catch (\Exception $e) {
            $valid = false;
        }

        if (! $valid) {
            Alert::error('Gagal', 'Kode Token MFA salah');

            return redirect()->route('alih-media.penyeleksian');
        }

        $berkas = Filelist::with(['incomings', 'outcomings', 'status'])->find($id);
        if (! $berkas) {
            Alert::error('Gagal', 'Berkas tidak ditemukan');

            return redirect()->route('alih-media.penyeleksian');
        }

        if (! in_array(optional($berkas->status)->nama_status, ['Aktif', 'Inaktif'], true)) {
            Alert::error('Gagal', 'Hanya berkas berstatus Aktif atau Inaktif yang dapat dialihmediakan');

            return redirect()->route('alih-media.penyeleksian');
        }

        $statusFokusAlihMedia = $this->getStatusFokusAlihMedia();
        if (! in_array(optional($berkas->status)->nama_status, $statusFokusAlihMedia, true)) {
            Alert::error('Gagal', 'Penyeleksian alih media saat ini hanya untuk berkas berstatus '.implode(' atau ', $statusFokusAlihMedia));

            return redirect()->route('alih-media.penyeleksian');
        }

        if ($berkas->keterangan_akhir !== 'Permanen') {
            Alert::error('Gagal', 'Hanya berkas dengan keterangan akhir permanen yang dapat dialihmediakan');

            return redirect()->route('alih-media.penyeleksian');
        }

        $items = collect()
            ->merge($berkas->incomings->map(function ($item) {
                return ['jenis' => 'masuk', 'data' => $item];
            }))
            ->merge($berkas->outcomings->map(function ($item) {
                return ['jenis' => 'keluar', 'data' => $item];
            }));

        if ($items->isEmpty()) {
            Alert::error('Gagal', 'Berkas tidak memiliki isi surat untuk diproses');

            return redirect()->route('alih-media.penyeleksian');
        }

        if (! is_file(public_path('gambar/logo-watermark.png'))) {
            Alert::warning('Gagal', 'Gambar watermark public/gambar/logo-watermark.png tidak ditemukan');

            return redirect()->route('alih-media.penyeleksian');
        }

        $totalIsi = $berkas->incomings->count() + $berkas->outcomings->count();
        $totalWatermarked = $berkas->incomings->filter(function ($surat) {
            return $surat->hasExistingWatermarkedFile();
        })->count()
            + $berkas->outcomings->filter(function ($surat) {
                return $surat->hasExistingWatermarkedFile();
            })->count();

        if ($totalIsi > 0 && $totalIsi === $totalWatermarked) {
            Alert::info('Selesai', 'Semua PDF pada berkas ini sudah pernah diproses');

            return redirect()->route('alih-media.penyeleksian');
        }

        if ((int) $berkas->alih_media_status_id === Filelist::ALIH_MEDIA_PROCESSING) {
            Alert::info('Sedang Diproses', 'Berkas ini sudah masuk antrean/proses alih media');

            return redirect()->route('alih-media.penyeleksian');
        }

        $result = DB::transaction(function () use ($id, $statusFokusAlihMedia) {
            $lockedBerkas = Filelist::with(['incomings', 'outcomings', 'status'])
                ->lockForUpdate()
                ->find($id);

            if (! $lockedBerkas) {
                return 'not_found';
            }

            if ($lockedBerkas->alih_media_status_id !== null) {
                return 'already_started';
            }

            if (
                ! in_array(optional($lockedBerkas->status)->nama_status, [
                    Status::ACTIVE,
                    Status::INACTIVE,
                ], true)
                || ! in_array(optional($lockedBerkas->status)->nama_status, $statusFokusAlihMedia, true)
                || $lockedBerkas->keterangan_akhir !== 'Permanen'
            ) {
                return 'not_eligible';
            }

            if ($lockedBerkas->incomings->isEmpty() && $lockedBerkas->outcomings->isEmpty()) {
                return 'empty';
            }

            $lockedBerkas->alih_media_status_id = Filelist::ALIH_MEDIA_PROCESSING;
            $lockedBerkas->saveOrFail();

            return 'queued';
        });

        if ($result !== 'queued') {
            $messages = [
                'not_found' => 'Berkas tidak ditemukan',
                'already_started' => 'Berkas sudah masuk antrean atau proses alih media',
                'not_eligible' => 'Status atau keterangan akhir berkas sudah berubah dan tidak lagi memenuhi syarat',
                'empty' => 'Berkas tidak memiliki isi surat untuk diproses',
            ];
            Alert::error('Gagal', $messages[$result] ?? 'Berkas gagal masuk antrean alih media');

            return redirect()->route('alih-media.penyeleksian');
        }

        if (! $this->dispatchWatermarkJob((int) $id, null)) {
            Alert::error('Gagal', 'Berkas gagal masuk antrean. Status dikembalikan agar dapat dicoba lagi.');

            return redirect()->route('alih-media.penyeleksian');
        }

        Alert::success('Berhasil', 'Berkas masuk antrean alih media. Watermark akan diproses di latar belakang.');

        return redirect()->route('alih-media.penyeleksian');
    }

    public function ulangi($id)
    {
        $berkas = Filelist::with(['incomings', 'outcomings'])->find($id);
        if (! $berkas) {
            Alert::error('Gagal', 'Berkas tidak ditemukan');

            return redirect()->route('alih-media.diproses');
        }

        if ((int) $berkas->alih_media_status_id !== Filelist::ALIH_MEDIA_FAILED) {
            Alert::warning('Gagal', 'Hanya berkas dengan status gagal yang dapat diulangi');

            return redirect()->route('alih-media.diproses');
        }

        if ($berkas->keterangan_akhir !== 'Permanen') {
            Alert::error('Gagal', 'Hanya berkas dengan keterangan akhir permanen yang dapat dialihmediakan');

            return redirect()->route('alih-media.diproses');
        }

        $items = collect()
            ->merge($berkas->incomings->map(function ($item) {
                return ['jenis' => 'masuk', 'data' => $item];
            }))
            ->merge($berkas->outcomings->map(function ($item) {
                return ['jenis' => 'keluar', 'data' => $item];
            }));

        if ($items->isEmpty()) {
            Alert::error('Gagal', 'Berkas tidak memiliki isi surat untuk diproses');

            return redirect()->route('alih-media.diproses');
        }

        if (! is_file(public_path('gambar/logo-watermark.png'))) {
            Alert::warning('Gagal', 'Gambar watermark public/gambar/logo-watermark.png tidak ditemukan');

            return redirect()->route('alih-media.diproses');
        }

        $result = DB::transaction(function () use ($id) {
            $lockedBerkas = Filelist::with(['incomings', 'outcomings'])
                ->lockForUpdate()
                ->find($id);

            if (! $lockedBerkas) {
                return 'not_found';
            }

            if ((int) $lockedBerkas->alih_media_status_id !== Filelist::ALIH_MEDIA_FAILED) {
                return 'not_failed';
            }

            if ($lockedBerkas->keterangan_akhir !== 'Permanen') {
                return 'not_permanent';
            }

            $totalIsiLocked = $lockedBerkas->incomings->count() + $lockedBerkas->outcomings->count();
            if ($totalIsiLocked === 0) {
                return 'empty';
            }

            $totalWatermarkedLocked = $lockedBerkas->incomings->filter(function ($surat) {
                return $surat->hasExistingWatermarkedFile();
            })->count()
                + $lockedBerkas->outcomings->filter(function ($surat) {
                    return $surat->hasExistingWatermarkedFile();
                })->count();

            if ($totalIsiLocked === $totalWatermarkedLocked) {
                $lockedBerkas->alih_media_status_id = Filelist::ALIH_MEDIA_DONE;
                $lockedBerkas->saveOrFail();

                return 'completed';
            }

            $lockedBerkas->alih_media_status_id = Filelist::ALIH_MEDIA_PROCESSING;
            $lockedBerkas->saveOrFail();

            return 'queued';
        });

        if ($result === 'completed') {
            Alert::info('Selesai', 'Semua PDF sudah ber-watermark. Status alih media diperbarui menjadi selesai.');

            return redirect()->route('alih-media.diproses');
        }

        if ($result !== 'queued') {
            $messages = [
                'not_found' => 'Berkas tidak ditemukan',
                'not_failed' => 'Status berkas sudah berubah dan tidak dapat diulangi',
                'not_permanent' => 'Berkas tidak lagi berketerangan akhir permanen',
                'empty' => 'Berkas tidak memiliki isi surat untuk diproses',
            ];
            Alert::error('Gagal', $messages[$result] ?? 'Proses alih media tidak dapat diulangi');

            return redirect()->route('alih-media.diproses');
        }

        if (! $this->dispatchWatermarkJob((int) $id, Filelist::ALIH_MEDIA_FAILED)) {
            Alert::error('Gagal', 'Proses gagal masuk antrean. Status dikembalikan menjadi gagal agar dapat diulangi.');

            return redirect()->route('alih-media.diproses');
        }

        Alert::success('Berhasil', 'Proses alih media diulangi. PDF yang sudah ber-watermark akan dilewati.');

        return redirect()->route('alih-media.diproses');
    }

    public function tutupSemua()
    {
        $result = DB::transaction(function () {
            $berkasList = Filelist::with(['incomings', 'outcomings'])
                ->where('keterangan_akhir', 'Permanen')
                ->whereIn('alih_media_status_id', [
                    Filelist::ALIH_MEDIA_PROCESSING,
                    Filelist::ALIH_MEDIA_DONE,
                    Filelist::ALIH_MEDIA_FAILED,
                ])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($berkasList->isEmpty()) {
                return ['status' => 'empty'];
            }

            $belumSelesai = $berkasList->filter(function ($berkas) {
                $totalIsi = $berkas->incomings->count() + $berkas->outcomings->count();
                $totalWatermarked = $berkas->incomings->filter(function ($surat) {
                    return $surat->hasExistingWatermarkedFile();
                })->count()
                    + $berkas->outcomings->filter(function ($surat) {
                        return $surat->hasExistingWatermarkedFile();
                    })->count();

                return (int) $berkas->alih_media_status_id !== Filelist::ALIH_MEDIA_DONE
                        || $totalIsi === 0
                        || $totalIsi !== $totalWatermarked;
            });

            if ($belumSelesai->isNotEmpty()) {
                return [
                    'status' => 'incomplete',
                    'count' => $belumSelesai->count(),
                ];
            }

            Filelist::whereIn('id', $berkasList->pluck('id'))
                ->update(['alih_media_status_id' => Filelist::ALIH_MEDIA_CLOSED]);

            return ['status' => 'closed'];
        });

        if ($result['status'] === 'empty') {
            Alert::info('Tidak Ada Data', 'Tidak ada proses alih media yang perlu ditutup');

            return redirect()->route('alih-media.diproses');
        }

        if ($result['status'] === 'incomplete') {
            Alert::warning(
                'Belum Bisa Ditutup',
                $result['count'].' berkas alih media belum selesai atau belum lengkap watermark-nya. Selesaikan/ulangi proses terlebih dahulu.'
            );

            return redirect()->route('alih-media.diproses');
        }

        Alert::success('Berhasil', 'Semua proses alih media yang valid sudah dipindahkan ke menu Selesai');

        return redirect()->route('alih-media.diproses');
    }

    private function getStatusFokusAlihMedia(): array
    {
        $statusPemrosesan = Filelist::join('statuses', 'filelists.status_id', '=', 'statuses.id')
            ->where('filelists.keterangan_akhir', 'Permanen')
            ->whereIn('filelists.alih_media_status_id', [
                Filelist::ALIH_MEDIA_PROCESSING,
                Filelist::ALIH_MEDIA_DONE,
                Filelist::ALIH_MEDIA_FAILED,
            ])
            ->whereIn('statuses.nama_status', ['Aktif', 'Inaktif'])
            ->distinct()
            ->pluck('statuses.nama_status')
            ->values()
            ->all();

        return count($statusPemrosesan) > 0 ? $statusPemrosesan : ['Aktif', 'Inaktif'];
    }

    private function dispatchWatermarkJob(int $filelistId, ?int $fallbackStatus): bool
    {
        try {
            $job = (new ProcessAlihMediaWatermarkJob($filelistId))
                ->onConnection('database');

            app(Dispatcher::class)->dispatch($job);

            return true;
        } catch (Throwable $exception) {
            report($exception);

            DB::transaction(function () use ($filelistId, $fallbackStatus) {
                $berkas = Filelist::lockForUpdate()->find($filelistId);

                if (
                    $berkas
                    && (int) $berkas->alih_media_status_id === Filelist::ALIH_MEDIA_PROCESSING
                ) {
                    $berkas->alih_media_status_id = $fallbackStatus;
                    $berkas->saveOrFail();
                }
            });

            return false;
        }
    }
}
