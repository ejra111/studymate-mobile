<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Meetup;

class MeetupUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $meetup;

    public function __construct(Meetup $meetup)
    {
        $this->meetup = $meetup->load(['creator', 'participants.user']);
    }

    public function broadcastOn(): array
    {
        $channels = [];
        foreach ($this->meetup->participants as $participant) {
            $channels[] = new PrivateChannel('user.' . $participant->user_id);
        }
        return $channels;
    }

    public function broadcastAs()
    {
        return 'meetup.updated';
    }
}
