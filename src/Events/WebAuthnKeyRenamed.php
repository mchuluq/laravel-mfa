<?php namespace Mchuluq\LaravelMFA\Events;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Event fired when a WebAuthn key is renamed.
 */
class WebAuthnKeyRenamed extends MFAEvent{
    /**
     * The key ID.
     *
     * @var int
     */
    public $keyId;

    /**
     * The old name.
     *
     * @var string
     */
    public $oldName;

    /**
     * The new name.
     *
     * @var string
     */
    public $newName;

    /**
     * Create a new event instance.
     *
     * @param Authenticatable $user
     * @param int $keyId
     * @param string $oldName
     * @param string $newName
     * @param array $data
     * @return void
     */
    public function __construct(Authenticatable $user, int $keyId, string $oldName, string $newName, array $data = []){
        $this->keyId = $keyId;
        $this->oldName = $oldName;
        $this->newName = $newName;
        
        $data['key_id'] = $keyId;
        $data['old_name'] = $oldName;
        $data['new_name'] = $newName;
        
        parent::__construct($user, 'webauthn', $data);
    }
}