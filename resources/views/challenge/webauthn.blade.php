@extends('mfa::layouts.challenge')

@section('title', 'Security Key Verification')

@section('alert')
<div class="alert alert-primary">
    <i class="fas fa-key mr-2"></i>Gunakan security key atau autentikasi biometric untuk memverifikasi identitas Anda.
</div>
@endsection

@section('content')
<div class="card">
    <div class="p-3 text-center">
        <i class="fas fa-key fa-4x text-primary" id="key-icon"></i>
    </div>
    <div class="card-body text-center">
        <div id="status-message">
            <h5 class="card-title">Siap Untuk Autentikasi</h5>
            <p class="card-text mb-3">Klik tombol berikut untuk memulai autentikasi identitas Anda.</p>
        </div>
        <div id="error-message" class="alert alert-danger mt-3" style="display: none;"></div>
    </div>
    <div class="card-footer">
        <button type="button" class="btn btn-primary btn-block" id="authenticate-btn"><i class="fas fa-fingerprint mr-2"></i>Autentikasi</button>
    </div>
</div>

@if(count($drivers ?? []) > 1)
    <div class="text-center mt-3">
        <a href="{{ route('mfa.challenge.index') }}?select=1" class="text-primary">Coba cara lain</a>
    </div>
@endif
@endsection

@push('styles')
<style>
@keyframes pulse {
    0%, 100% {
        transform: scale(1);
        opacity: 1;
    }
    50% {
        transform: scale(1.1);
        opacity: 0.8;
    }
}

.pulse-animation {
    animation: pulse 2s ease-in-out infinite;
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Check WebAuthn support
    if (!window.PublicKeyCredential) {
        document.getElementById('authenticate-btn').disabled = true;
        document.getElementById('status-message').innerHTML = `
            <h5 class="text-danger mb-3">{{ __('Not Supported') }}</h5>
            <p class="text-muted">{{ __('Your browser doesn\'t support WebAuthn. Please use a different verification method or try another browser.') }}</p>
        `;
        return;
    }

    const authenticateBtn = document.getElementById('authenticate-btn');
    const statusMessage = document.getElementById('status-message');
    const errorMessage = document.getElementById('error-message');
    const keyIcon = document.getElementById('key-icon');

    authenticateBtn.addEventListener('click', async function() {
        try {
            // Disable button
            authenticateBtn.disabled = true;
            authenticateBtn.innerHTML = '<span class="spinner-border spinner-border-sm mr-2"></span>{{ __("Preparing...") }}';
            errorMessage.style.display = 'none';
            keyIcon.classList.add('pulse-animation');
            // Update status
            statusMessage.innerHTML = `
                <h5 class="mb-3">{{ __('Waiting for authentication') }}</h5>
                <p class="text-muted">{{ __('Follow your browser\'s prompts to authenticate.') }}</p>
            `;
            authenticateBtn.textContent = '{{ __("Authenticating...") }}';

            // request options
            console.log('Meminta authentication options...');
            const optionsResp = await axios.post('{{ route("mfa.webauthn.auth.options") }}',{},{
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            }).catch(function (error) {
                console.log("authentication options error : ",error);
            });
            const options = optionsResp.data?.options?.publicKey;
 
            // Start authentication
            authenticateBtn.textContent = '{{ __("Verifying...") }}';
            console.log('Memulai WebAuthn...', options);
            const assertion = await window.simple_webauthn_start_auth({optionsJSON:options});
            console.log('Assertion diterima, mengirim ke server...', assertion);
            await axios.post('{{ route("mfa.challenge.verify", ["driver" => $driver]) }}', { credential : assertion} ,{
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            }).then(function (response) {
                keyIcon.classList.remove('pulse-animation');
                keyIcon.className = 'fas fa-check-circle fa-4x text-success';
                
                statusMessage.innerHTML = `
                    <h5 class="text-success mb-3">{{ __('Authentication successful!') }}</h5>
                    <p class="text-muted">{{ __('Redirecting you now...') }}</p>
                `;
        
                setTimeout(() => {
                    if(response.data.redirect){
                        window.location.href = response.data.redirect;
                    }    
                },1000);
            });
            console.log('Autentikasi berhasil ✅');
        } catch (err) {
            console.error(err);

            keyIcon.classList.remove('pulse-animation');
            let errorMsg = '{{ __("Authentication failed.") }} ';
            if (error.name === 'NotAllowedError') {
                errorMsg += '{{ __("Authentication was cancelled or timed out.") }}';
            } else if (error.name === 'InvalidStateError') {
                errorMsg += '{{ __("This security key is not registered.") }}';
            } else {
                errorMsg += error.message || '{{ __("Please try again.") }}';
            }
            errorMessage.textContent = errorMsg;
            errorMessage.style.display = 'block';
            statusMessage.innerHTML = `<h5 class="text-danger mb-3">{{ __('Authentication failed') }}</h5><p class="text-muted">{{ __('Please try again or use a different method.') }}</p>`;
            authenticateBtn.disabled = false;
            authenticateBtn.innerHTML = '<i class="fas fa-fingerprint mr-2"></i>{{ __("Try Again") }}';
        }
    });
});
</script>
@endpush