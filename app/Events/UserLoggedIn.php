<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Http\Request;
use App\Models\User;

class UserLoggedIn
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public User $user;
    public Request $request;

    /**
     * Create a new event instance.
     */
    public function __construct(User $user, Request $request)
    {
        $this->user = $user;
        $this->request = $request;
    }
}
