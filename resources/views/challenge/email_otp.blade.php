@extends('mfa::layouts.challenge')

@section('title', 'Email Verification')

@section('alert')
<div class="alert alert-primary">
    <i class="fas fa-envelope mr-2"></i>
    Kami mengirim verifikasi kode ke <strong>{{ $challengeData['email'] ?? 'your email' }}</strong>
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
                <small class="form-text text-muted" id="expires-help">
                    Kode akan kadaluarsa dalam <span id="countdown">{{ $challengeData['expires_in'] ?? 600 }}</span> detik
                </small>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-primary btn-block" id="verify-btn">Verifikasi kode</button>
        </div>
    </div>
</form>

<!-- Resend Link -->
<div class="text-center mt-3">
    <span class="text-muted">Tidak menerima kode ?</span>
    <a href="#" id="resend-link" class="text-primary">Kirim ulang</a>
    <div id="resend-timer" style="display: none;">
        <small class="text-muted">Anda dapat mengirim ulang setelah <span id="resend-countdown">60</span> detik</small>
    </div>
</div>

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

    // Countdown timer for code expiration
    let expiresIn = <?php echo $challengeData['expires_in'] ?? 600 ?>;
    const countdownEl = document.getElementById('countdown');
    const expiresHelp = document.getElementById('expires-help');
    const verifyBtn = document.getElementById('verify-btn');

    const countdownInterval = setInterval(() => {
        expiresIn--;
        
        if (expiresIn <= 0) {
            clearInterval(countdownInterval);
            expiresHelp.innerHTML = '<span class="text-danger">{{ __("Code has expired. Please request a new one.") }}</span>';
            verifyBtn.disabled = true;
        } else {
            const minutes = Math.floor(expiresIn / 60);
            const seconds = expiresIn % 60;
            countdownEl.textContent = minutes > 0 
                ? `${minutes}:${seconds.toString().padStart(2, '0')}`
                : seconds;
        }
    }, 1000);

    // Resend functionality
    const resendLink = document.getElementById('resend-link');
    const resendTimer = document.getElementById('resend-timer');
    const resendCountdownEl = document.getElementById('resend-countdown');
    let resendAvailable = false;
    let resendSeconds = 60;

    const resendInterval = setInterval(() => {
        resendSeconds--;
        resendCountdownEl.textContent = resendSeconds;
        
        if (resendSeconds <= 0) {
            clearInterval(resendInterval);
            resendAvailable = true;
            resendTimer.style.display = 'none';
            resendLink.parentElement.style.display = 'block';
        }
    }, 1000);

    // Show timer initially
    resendTimer.style.display = 'block';
    resendLink.parentElement.style.display = 'block';

    resendLink.addEventListener('click', async function(e) {
        e.preventDefault();
        
        if (!resendAvailable) return;
        
        this.textContent = '{{ __("Sending...") }}';
        this.style.pointerEvents = 'none';
        
        try {
            const response = await fetch('{{ route("mfa.challenge.resend", ["driver" => $driver]) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Reset timer
                expiresIn = data.data.expires_in || 600;
                resendSeconds = 60;
                resendAvailable = false;
                
                // Show success
                alert('{{ __("New code has been sent!") }}');
                
                // Restart timers
                resendTimer.style.display = 'block';
                
                // Restart resend interval
                clearInterval(resendInterval);
                setInterval(() => {
                    resendSeconds--;
                    resendCountdownEl.textContent = resendSeconds;
                    
                    if (resendSeconds <= 0) {
                        resendAvailable = true;
                        resendTimer.style.display = 'none';
                    }
                }, 1000);
            } else {
                throw new Error(data.message || '{{ __("Failed to resend code") }}');
            }
        } catch (error) {
            alert(error.message);
        } finally {
            this.textContent = '{{ __("Resend code") }}';
            this.style.pointerEvents = 'auto';
        }
    });

    // Prevent multiple submissions
    document.getElementById('verify-form').addEventListener('submit', function() {
        verifyBtn.disabled = true;
        verifyBtn.innerHTML = '<span class="spinner-border spinner-border-sm mr-2"></span>' + '{{ __("Verifying...") }}';
    });
});
</script>
@endpush