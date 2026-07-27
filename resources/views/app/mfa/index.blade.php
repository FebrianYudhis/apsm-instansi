@extends('layouts.main')

@section('konten')
    <div class="row justify-content-center">
        <div class="col-md-8 mt-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0 text-white">Pengaturan Multi-Factor Authentication (MFA)</h5>
                </div>
                <div class="card-body text-center">
                    @if($isGenerated)
                        <div class="alert alert-warning">
                            <strong>Perhatian!</strong> Sistem baru saja membuatkan kunci MFA untuk Anda. 
                            Harap salin kunci rahasia di bawah ini dan masukkan ke file <code>.env</code> Anda pada variabel <code>MFA_SECRET</code>, 
                            kemudian jalankan aplikasi kembali.
                        </div>
                    @else
                        <div class="alert alert-info">
                            Gunakan aplikasi seperti <strong>Google Authenticator</strong> atau <strong>Authy</strong> di HP Anda untuk memindai kode QR di bawah ini.
                        </div>
                    @endif

                    <div class="my-4">
                        <img src="{{ $qrImage }}" alt="QR Code MFA" class="img-thumbnail" style="width: 250px; height: 250px;">
                    </div>

                    @if($isGenerated)
                        <h5>Kunci Rahasia (Secret Key)</h5>
                        <div class="d-inline-block bg-light p-3 rounded border">
                            <code class="lead" style="font-size: 1.5rem; letter-spacing: 2px;">{{ $secret }}</code>
                        </div>
                    @endif
                    
                    <p class="text-muted mt-4 text-left">
                        <strong>Panduan:</strong><br>
                        1. Buka aplikasi Google Authenticator.<br>
                        2. Pilih opsi tanda tambah (+) dan ketuk <em>Scan a QR code</em>.<br>
                        3. Arahkan kamera HP ke gambar QR Code di atas.<br>
                        4. Aplikasi akan otomatis menyimpan token APSM Anda. Anda atau tamu harus memasukkan 6-digit angka dari aplikasi tersebut setiap kali ingin mengakses naskah rahasia.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
