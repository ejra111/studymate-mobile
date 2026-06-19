<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\EmergencyAlert;

class EmergencyAlertTriggered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $alert;

    public function __construct(EmergencyAlert $alert)
    {
        $this->alert = $alert->load(['meetup', 'user']);
    }

    public function broadcastOn(): array
    {
        $channels = [];
        foreach ($this->alert->meetup->participants as $participant) {
            if ($participant->user_id !== $this->alert->user_id) {
                $channels[] = new PrivateChannel('user.' . $participant->user_id);
            }
        }
        return $channels;
    }

    public function broadcastAs()
    {
        return 'emergency.alert';
    }
}
