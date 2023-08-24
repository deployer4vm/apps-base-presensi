<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

use Illuminate\Support\Facades\Log;

/**
 * saat somthing
 */
class OnApiResponse
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $response_output,$response_httpcode;

    /**
     * Create a new event instance.
     *
     * @param Array $somthing somthing
     * 
     * @return void
     */
    public function __construct($responseOutput,$responseHttpcode)
    {
        $this->response_output = $responseOutput;
        $this->response_httpcode = $responseHttpcode;
    }
}
