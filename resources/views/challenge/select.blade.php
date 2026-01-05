@extends('mfa::layouts.challenge')

@section('title', 'Pilih Metode Verifikasi')

@section('alert')
<div class="alert alert-primary">
    Pilih metode verifikasi yang Anda inginkan untuk melanjutkan.
</div>
@endsection

@section('content')
<div class="card">
    <div class="card-body p-0">
        <div class="list-group list-group-flush">
            @foreach($drivers as $driverName => $driverData)
                @php
                    $driver = $driverData['driver'];
                    $method = $driverData['method'];
                    $isPrimary = $primaryMethod && $primaryMethod->driver === $driverName;
                @endphp
                
                <a href="{{ route('mfa.challenge.show', ['driver' => $driverName]) }}" 
                   class="list-group-item list-group-item-action">
                    <div class="d-flex align-items-center">
                        <div class="mr-3">
                            @if($driverName === 'totp')
                                <i class="fas fa-mobile-alt fa-2x text-primary fa-fw"></i>
                            @elseif($driverName === 'email_otp')
                                <i class="fas fa-envelope fa-2x text-primary fa-fw"></i>
                            @elseif($driverName === 'webauthn')
                                <i class="fas fa-key fa-2x text-primary fa-fw"></i>
                            @endif
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0">
                                {{ $driver->getDisplayName() }}
                                @if($isPrimary)
                                    <span class="badge badge-primary">{{ __('Primary') }}</span>
                                @endif
                            </h6>
                            <small class="text-muted">{{ $driver->getDescription() }}</small>
                        </div>
                        <div>
                            <i class="fas fa-chevron-right text-muted"></i>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.list-group-item-action:hover {
    background-color: #f8f9fa;
    cursor: pointer;
}
</style>
@endpush