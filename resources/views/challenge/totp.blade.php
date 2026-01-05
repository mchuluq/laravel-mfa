@extends('mfa::layouts.challenge')

@section('title', 'Verifikasi dengan Aplikasi Autentikator')

@section('alert')
<div class="alert alert-primary">
    Periksa kode OTP dari aplikasi autentikator pada perangkat Anda.
</div>
@endsection

@section('content')
<form action="{{ route('mfa.challenge.verify', ['driver' => $driver]) }}" method="POST" id="verify-form">
    @csrf
    <div class="card">
        <div class="card-body">
            <div class="form-group">
                <label for="code" class="font-weight-bold">Kode verifikasi</label>
                <input type="text" id="code" name="code" class="form-control text-center @error('code') is-invalid @enderror" placeholder="000000" maxlength="6" pattern="[0-9]*" inputmode="numeric" autocomplete="one-time-code" required autofocus style="letter-spacing: 0.5em; font-size: 1.5rem;">
                @error('code')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                @enderror
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary btn-block" id="verify-btn">Verifikasi kode</button>
        </div>
    </div>
    <!-- Backup Code Link -->
    <div class="text-center mt-3">
        <a href="#" id="backup-code-toggle" class="text-muted">Gunakan kode cadangan</a>
    </div>
</form>

<!-- Backup Code Form (Hidden) -->
<form action="{{ route('mfa.challenge.verify', ['driver' => $driver]) }}" method="POST" id="backup-form" style="display: none;">
    @csrf
    <div class="card">
        <div class="card-body">
            <div class="form-group">
                <label for="backup-code" class="font-weight-bold">Kode cadangan</label>
                <input type="text" id="backup-code" name="code" class="form-control" placeholder="XXXX-XXXX-XXXX" maxlength="16">
                <small class="form-text text-muted">Masukkan kode cadangan untuk verifikasi identitas anda</small>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary btn-block">Verifikasi dengan Kode Cadangan</button>
        </div>
    </div>
    <div class="text-center mt-3">
        <a href="#" id="back-to-code" class="text-muted">Kembali ke kode verifikasi</a>
    </div>
</form>

@if(count($drivers ?? []) > 1)
    <div class="text-center mt-3">
        <a href="{{ route('mfa.challenge.index') }}?select=1" class="text-primary">Coba cara lain</a>
    </div>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-format code input
    const codeInput = document.getElementById('code');
    if (codeInput) {
        codeInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
            
            // Auto-submit when 6 digits entered
            if (this.value.length === 6) {
                setTimeout(() => {
                    document.getElementById('verify-form').submit();
                }, 100);
            }
        });
    }

    // Toggle between regular and backup code forms
    const verifyForm = document.getElementById('verify-form');
    const backupForm = document.getElementById('backup-form');
    const backupToggle = document.getElementById('backup-code-toggle');
    const backToCode = document.getElementById('back-to-code');

    if (backupToggle) {
        backupToggle.addEventListener('click', function(e) {
            e.preventDefault();
            verifyForm.style.display = 'none';
            backupForm.style.display = 'block';
            this.parentElement.style.display = 'none';
            document.getElementById('backup-code').focus();
        });
    }

    if (backToCode) {
        backToCode.addEventListener('click', function(e) {
            e.preventDefault();
            backupForm.style.display = 'none';
            verifyForm.style.display = 'block';
            document.getElementById('backup-code-toggle').parentElement.style.display = 'block';
            codeInput.focus();
        });
    }

    // Prevent multiple submissions
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            const btn = this.querySelector('button[type="submit"]');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm mr-2"></span>' + '{{ __("Verifying...") }}';
            }
        });
    });
});
</script>
@endpush