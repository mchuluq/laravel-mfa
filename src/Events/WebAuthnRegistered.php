<?php namespace Mchuluq\LaravelMFA\Events;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Event fired when a WebAuthn key is registered.
 */
class WebAuthnRegistered extends MFAEvent{
    /**
     * The registered key ID.
     *
     * @var int
     */
    public $keyId;

    /**
     * The key name.
     *
     * @var string
     */
    public $keyName;

    /**
     * Create a new event instance.
     *
     * @param Authenticatable $user
     * @param int $keyId
     * @param string $keyName
     * @param array $data
     * @return void
     */
    public function __construct(Authenticatable $user, int $keyId, string $keyName = '', array $data = []){
        $this->keyId = $keyId;
        $this->keyName = $keyName;
        
        $data['key_id'] = $keyId;
        $data['key_name'] = $keyName;
        
        parent::__construct($user, 'webauthn', $data);
    }
}