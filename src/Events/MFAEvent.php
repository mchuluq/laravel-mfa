<?php namespace Mchuluq\LaravelMFA\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class MFAEvent{

    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The authenticated user.
     *
     * @var Authenticatable
     */
    public $user;

    /**
     * The MFA driver name.
     *
     * @var string
     */
    public $driver;

    /**
     * Additional event data.
     *
     * @var array
     */
    public $data;

    /**
     * The time the event occurred.
     *
     * @var \Carbon\Carbon
     */
    public $timestamp;

    /**
     * Create a new event instance.
     *
     * @param Authenticatable $user
     * @param string $driver
     * @param array $data
     * @return void
     */
    public function __construct(Authenticatable $user, string $driver, array $data = []){
        $this->user = $user;
        $this->driver = $driver;
        $this->data = $data;
        $this->timestamp = now();
    }

    /**
     * Get the user ID.
     *
     * @return mixed
     */
    public function getUserId(){
        return $this->user->getAuthIdentifier();
    }

    /**
     * Get the user's email.
     *
     * @return string|null
     */
    public function getUserEmail(): ?string{
        return $this->user->email ?? null;
    }

    /**
     * Get the IP address.
     *
     * @return string|null
     */
    public function getIpAddress(): ?string{
        return request()->ip();
    }

    /**
     * Get the user agent.
     *
     * @return string|null
     */
    public function getUserAgent(): ?string{
        return request()->userAgent();
    }

    /**
     * Convert event to array for logging.
     *
     * @return array
     */
    public function toArray(): array{
        return [
            'event' => class_basename($this),
            'user_id' => $this->getUserId(),
            'user_email' => $this->getUserEmail(),
            'driver' => $this->driver,
            'ip_address' => $this->getIpAddress(),
            'user_agent' => $this->getUserAgent(),
            'timestamp' => $this->timestamp->toIso8601String(),
            'data' => $this->data,
        ];
    }
}