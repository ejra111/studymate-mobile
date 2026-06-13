<?php

namespace App\Http\Controllers;

use App\Models\StudyNotification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NotificationController extends Controller
{
    /**
     * Get notifications for a user (newest first, unread prioritized)
     */
    public function index(string $userId)
    {
        $notifications = StudyNotification::query()
            ->where('receiver_id', $userId)
            ->with(['sender' => function($q) {
                $q->select('id', 'name', 'avatar_color', 'avatar_url');
            }])
            ->orderByRaw('CASE WHEN read_at IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function (StudyNotification $notif) {
                return [
                    'id' => $notif->id,
                    'type' => $notif->type,
                    'message' => $notif->message,
                    'data' => $notif->data,
                    'readAt' => $notif->read_at,
                    'createdAt' => $notif->created_at,
                    'sender' => $notif->sender ? [
                        'id' => $notif->sender->id,
                        'name' => $notif->sender->name,
                        'avatarColor' => $notif->sender->avatarColor,
                        'avatar_color' => $notif->sender->avatar_color,
                        'avatarUrl' => $notif->sender->avatarUrl,
                        'avatar_url' => $notif->sender->avatar_url,
                    ] : null,
                ];
            });

        $unreadCount = StudyNotification::query()
            ->where('receiver_id', $userId)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'notifications' => $notifications,
            'unreadCount' => $unreadCount,
        ]);
    }

    /**
     * Create a new notification (e.g. study invite)
     */
    public function store(Request $request)
    {
        $request->validate([
            'senderId' => 'required|exists:users,id',
            'receiverId' => 'required|exists:users,id',
            'type' => 'required|string|max:50',
            'message' => 'required|string|max:500',
            'data' => 'nullable|array',
        ]);

        $senderId = (string) $request->input('senderId');
        $receiverId = (string) $request->input('receiverId');

        \Log::info("Attempting to create notification: sender={$senderId}, receiver={$receiverId}, type=" . $request->input('type'));

        if ($senderId === $receiverId) {
            return response()->json(['message' => 'Tidak bisa mengirim undangan ke diri sendiri.'], 422);
        }

        // Cegah spam: check jika sudah ada undangan serupa dalam 5 menit terakhir
        $recent = StudyNotification::query()
            ->where('sender_id', $senderId)
            ->where('receiver_id', $receiverId)
            ->where('type', $request->input('type'))
            ->where('created_at', '>=', now()->subMinutes(5))
            ->exists();

        if ($recent) {
            return response()->json(['message' => 'Undangan sudah dikirim sebelumnya. Tunggu beberapa menit.'], 429);
        }

        $notif = StudyNotification::create([
            'id' => (string) Str::uuid(),
            'sender_id' => $senderId,
            'receiver_id' => $receiverId,
            'type' => $request->input('type'),
            'message' => $request->input('message'),
            'data' => array_merge($request->input('data') ?? [], ['status' => 'pending']),
        ]);

        return response()->json($notif->load('sender'), 201);
    }

    /**
     * Mark a single notification as read
     */
    public function markRead(string $id)
    {
        $notif = StudyNotification::find($id);
        if (!$notif) {
            return response()->json(['message' => 'Notifikasi tidak ditemukan.'], 404);
        }

        $notif->update(['read_at' => now()]);

        return response()->json(['message' => 'Notifikasi ditandai sudah dibaca.']);
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllRead(string $userId)
    {
        StudyNotification::query()
            ->where('receiver_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'Semua notifikasi ditandai sudah dibaca.']);
    }

    /**
     * Accept a study invite
     */
    public function acceptInvite(string $id)
    {
        $notif = StudyNotification::find($id);
        if (!$notif || $notif->type !== 'study_invite') {
            return response()->json(['message' => 'Undangan tidak ditemukan.'], 404);
        }

        if ($notif->read_at && isset($notif->data['status']) && $notif->data['status'] === 'accepted') {
            return response()->json(['message' => 'Undangan sudah diterima sebelumnya.'], 422);
        }

        // Create mutual friendship
        $senderId = $notif->sender_id;
        $receiverId = $notif->receiver_id;

        // Check if already friends
        $exists = \App\Models\Friend::where('user_id', $senderId)
            ->where('friend_id', $receiverId)
            ->exists();

        if (!$exists) {
            \App\Models\Friend::create([
                'id' => (string) Str::uuid(),
                'user_id' => $senderId,
                'friend_id' => $receiverId
            ]);
            \App\Models\Friend::create([
                'id' => (string) Str::uuid(),
                'user_id' => $receiverId,
                'friend_id' => $senderId
            ]);
        }

        // Update notification status
        $data = $notif->data ?? [];
        $data['status'] = 'accepted';
        $notif->update([
            'data' => $data,
            'read_at' => now(),
            'message' => "Kamu telah menerima ajakan belajar dari " . ($data['senderName'] ?? 'teman')
        ]);

        // Optional: Notify the sender that invite was accepted
        StudyNotification::create([
            'id' => (string) Str::uuid(),
            'sender_id' => $receiverId,
            'receiver_id' => $senderId,
            'type' => 'invite_accepted',
            'message' => User::find($receiverId)->name . " menerima ajakan belajarmu! Kalian sekarang berteman.",
        ]);

        return response()->json(['message' => 'Undangan diterima.']);
    }

    /**
     * Reject a study invite
     */
    public function rejectInvite(string $id)
    {
        $notif = StudyNotification::find($id);
        if (!$notif || $notif->type !== 'study_invite') {
            return response()->json(['message' => 'Undangan tidak ditemukan.'], 404);
        }

        // Update notification status
        $data = $notif->data ?? [];
        $data['status'] = 'rejected';
        $notif->update([
            'data' => $data,
            'read_at' => now(),
            'message' => "Kamu menolak ajakan belajar."
        ]);

        return response()->json(['message' => 'Undangan ditolak.']);
    }
}
