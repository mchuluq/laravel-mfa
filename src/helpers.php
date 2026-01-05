<?php

use Mchuluq\LaravelMFA\MFAManager;

if (!function_exists('mfa')) {
    function mfa(?string $driver = null){
        $manager = app('mfa');
        if ($driver === null) {
            return $manager;
        }
        return $manager->driver($driver);
    }
}

if (!function_exists('mfa_enabled')) {
    function mfa_enabled(): bool{
        return config('mfa.enabled', true);
    }
}

if (!function_exists('mfa_verified')) {
    function mfa_verified(): bool{
        return app('mfa')->isVerified();
    }
}

if (!function_exists('mfa_required')) {
    function mfa_required(): bool{
        $user = auth()->user();
        if (!$user) {
            return false;
        }
        return app('mfa')->requiresMFA($user);
    }
}

if (!function_exists('mfa_challenge_url')) {
    function mfa_challenge_url(?string $driver = null): string{
        $route = config('mfa.middleware.challenge_route', 'mfa.challenge');
        if ($driver) {
            return route($route, ['driver' => $driver]);
        }
        return route($route);
    }
}