<?php namespace Mchuluq\LaravelMFA\Events;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Event fired when a WebAuthn key is deleted.
 */
class WebAuthnKeyDeleted extends MFAEvent{
    /**
     * The deleted key ID.
     *
     * @var int
     */
    public $keyId;

    /**
     * Create a new event instance.
     *
     * @param Authenticatable $user
     * @param int $keyId
     * @param array $data
     * @return void
     */
    public function __construct(Authenticatable $user, int $keyId, array $data = []){
        $this->keyId = $keyId;
        $data['key_id'] = $keyId;
        
        parent::__construct($user, 'webauthn', $data);
    }
}