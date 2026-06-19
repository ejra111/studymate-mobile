<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\MeetupLocation;

class MeetupLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $location;

    public function __construct(MeetupLocation $location)
    {
        $this->location = $location->load(['meetup', 'user']);
    }

    public function broadcastOn(): array
    {
        $channels = [];
        foreach ($this->location->meetup->participants as $participant) {
            $channels[] = new PrivateChannel('user.' . $participant->user_id);
        }
        return $channels;
    }

    public function broadcastAs()
    {
        return 'meetup.location.updated';
    }
}
