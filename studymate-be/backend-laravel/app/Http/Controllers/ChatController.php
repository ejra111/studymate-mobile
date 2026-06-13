<?php

namespace App\Http\Controllers;

use App\Models\PrivateMessage;
use App\Models\User;
use App\Models\StudyNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Events\PrivateMessageReceived;
use App\Events\StudyNotificationCreated;

class ChatController extends Controller
{
    public function getMessages(Request $request, $userId, $friendId)
    {
        $messages = PrivateMessage::where(function ($q) use ($userId, $friendId) {
            $q->where('sender_id', $userId)->where('receiver_id', $friendId);
        })->orWhere(function ($q) use ($userId, $friendId) {
            $q->where('sender_id', $friendId)->where('receiver_id', $userId);
        })
        ->with(['sender', 'receiver'])
        ->orderBy('created_at', 'asc')
        ->get();

        return response()->json($messages);
    }

    public function sendMessage(Request $request)
    {
        $request->validate([
            'sender_id' => 'required|exists:users,id',
            'receiver_id' => 'required|exists:users,id',
            'message' => 'required|string',
        ]);

        $senderId = $request->sender_id;
        $receiverId = $request->receiver_id;

        $message = PrivateMessage::create([
            'id' => (string) Str::uuid(),
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'message' => $request->message,
        ]);

        PrivateMessageReceived::dispatch($message);

        // Create notification for the receiver
        $sender = User::find($senderId);
        
        // Prevent notification flood: only notify if no unread message notification from same sender in last 1 minute
        $recentNotif = StudyNotification::where('receiver_id', $receiverId)
            ->where('sender_id', $senderId)
            ->where('type', 'private_message')
            ->whereNull('read_at')
            ->where('created_at', '>', now()->subMinutes(1))
            ->exists();

        if (!$recentNotif) {
            $notif = StudyNotification::create([
                'id' => (string) Str::uuid(),
                'sender_id' => $senderId,
                'receiver_id' => $receiverId,
                'type' => 'private_message',
                'message' => "Pesan baru dari {$sender->name}: " . Str::limit($request->message, 50),
                'data' => ['senderId' => $senderId]
            ]);
            StudyNotificationCreated::dispatch($notif);
        }

        return response()->json($message->load(['sender', 'receiver']), 201);
    }
}
