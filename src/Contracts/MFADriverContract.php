<?php namespace Mchuluq\LaravelMFA\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface MFADriverContract{
    /**
     * Get the driver name.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get the driver display name.
     *
     * @return string
     */
    public function getDisplayName(): string;

    /**
     * Get the driver description.
     *
     * @return string
     */
    public function getDescription(): string;

    /**
     * Check if the driver is enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool;

    /**
     * Check if user has this MFA method configured.
     *
     * @param Authenticatable $user
     * @return bool
     */
    public function isConfigured(Authenticatable $user): bool;

    /**
     * Setup MFA for the user.
     *
     * @param Authenticatable $user
     * @param array $options
     * @return mixed
     */
    public function setup(Authenticatable $user, array $options = []);

    /**
     * Verify the MFA code/credential.
     *
     * @param Authenticatable $user
     * @param mixed $credential
     * @param array $options
     * @return bool
     */
    public function verify(Authenticatable $user, $credential, array $options = []): bool;

    /**
     * Generate/Send challenge to user.
     *
     * @param Authenticatable $user
     * @param array $options
     * @return mixed
     */
    public function challenge(Authenticatable $user, array $options = []);

    /**
     * Disable MFA for the user.
     *
     * @param Authenticatable $user
     * @return bool
     */
    public function disable(Authenticatable $user): bool;

    /**
     * Get the challenge view name.
     *
     * @return string
     */
    public function getChallengeView(): string;

    /**
     * Get the setup view name.
     *
     * @return string
     */
    public function getSetupView(): string;

    /**
     * Get the management view name.
     *
     * @return string
     */
    public function getManagementView(): string;

    /**
     * Get driver-specific data for the user.
     *
     * @param Authenticatable $user
     * @return mixed
     */
    public function getData(Authenticatable $user);

    /**
     * Validate setup data.
     *
     * @param array $data
     * @return array
     */
    public function validateSetup(array $data): array;

    /**
     * Validate verification data.
     *
     * @param array $data
     * @return array
     */
    public function validateVerification(array $data): array;

    /**
     * Get recovery options for this driver.
     *
     * @param Authenticatable $user
     * @return array
     */
    public function getRecoveryOptions(Authenticatable $user): array;

    /**
     * Handle recovery process.
     *
     * @param Authenticatable $user
     * @param string $method
     * @param mixed $credential
     * @return bool
     */
    public function recover(Authenticatable $user, string $method, $credential): bool;
}