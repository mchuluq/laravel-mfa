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

    // Convert base64url (RFC 4648 §5) to standard base64
    function base64UrlToBase64(b64url) {
        if (!b64url) return '';
        let b64 = b64url.replace(/-/g, '+').replace(/_/g, '/');
        while (b64.length % 4) b64 += '=';
        return b64;
    }

    function bufferFromBase64Url(b64url) {
        try {
            const b64 = base64UrlToBase64(b64url);
            const binary = atob(b64);
            return Uint8Array.from(binary, c => c.charCodeAt(0));
        } catch (e) {
            throw new Error('Invalid base64 data: ' + (b64url || ''));
        }
    }

    authenticateBtn.addEventListener('click', async function() {
        try {
            // Disable button
            authenticateBtn.disabled = true;
            authenticateBtn.innerHTML = '<span class="spinner-border spinner-border-sm mr-2"></span>{{ __("Preparing...") }}';
            errorMessage.style.display = 'none';

            // Add pulse animation
            keyIcon.classList.add('pulse-animation');

            // Get authentication options
            const optionsResponse = await fetch('{{ route("mfa.webauthn.auth.options") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            });

            if (!optionsResponse.ok) {
                throw new Error('{{ __("Failed to get authentication options") }}');
            }

            const { options } = await optionsResponse.json();

            // Update status
            statusMessage.innerHTML = `
                <h5 class="mb-3">{{ __('Waiting for authentication') }}</h5>
                <p class="text-muted">{{ __('Follow your browser\'s prompts to authenticate.') }}</p>
            `;
            authenticateBtn.textContent = '{{ __("Authenticating...") }}';

            // Prepare options for WebAuthn API
            console.log('WebAuthn raw options:', options.publicKey);
            const publicKeyCredentialRequestOptions = {
                challenge: bufferFromBase64Url(options.publicKey.challenge),
                timeout: options.publicKey.timeout,
                rpId: options.publicKey.rpId,
                userVerification: options.publicKey.userVerification,
                allowCredentials: options.publicKey.allowCredentials?.map(cred => ({
                    type: cred.type,
                    id: bufferFromBase64Url(cred.id),
                    transports: cred.transports
                }))
            };

            // Get credential from authenticator
            const assertion = await navigator.credentials.get({
                publicKey: publicKeyCredentialRequestOptions
            });

            // Prepare credential for verification
            const credential = {
                id: assertion.id,
                rawId: btoa(String.fromCharCode(...new Uint8Array(assertion.rawId))),
                response: {
                    authenticatorData: btoa(String.fromCharCode(...new Uint8Array(assertion.response.authenticatorData))),
                    clientDataJSON: btoa(String.fromCharCode(...new Uint8Array(assertion.response.clientDataJSON))),
                    signature: btoa(String.fromCharCode(...new Uint8Array(assertion.response.signature))),
                    userHandle: assertion.response.userHandle ? btoa(String.fromCharCode(...new Uint8Array(assertion.response.userHandle))) : null
                },
                type: assertion.type
            };

            // Verify with server
            authenticateBtn.textContent = '{{ __("Verifying...") }}';
            
            const verifyResponse = await fetch('{{ route("mfa.challenge.verify", ["driver" => $driver]) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ credential })
            });

            const result = await verifyResponse.json();

            if (result.success) {
                keyIcon.classList.remove('pulse-animation');
                keyIcon.className = 'fas fa-check-circle fa-4x text-success';
                
                statusMessage.innerHTML = `
                    <h5 class="text-success mb-3">{{ __('Authentication successful!') }}</h5>
                    <p class="text-muted">{{ __('Redirecting you now...') }}</p>
                `;
                
                // Redirect
                setTimeout(() => {
                    window.location.href = result.redirect;
                }, 1000);
            } else {
                throw new Error(result.message || '{{ __("Authentication failed") }}');
            }

        } catch (error) {
            console.error('WebAuthn error:', error);
            
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