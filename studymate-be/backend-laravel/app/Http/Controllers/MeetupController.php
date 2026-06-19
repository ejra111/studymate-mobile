<?php

namespace App\Http\Controllers;

use App\Models\Meetup;
use App\Models\MeetupParticipant;
use App\Models\MeetupLocation;
use App\Models\MeetupCheckin;
use App\Models\EmergencyAlert;
use App\Models\StudyNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Events\MeetupCreated;
use App\Events\MeetupUpdated;
use App\Events\MeetupLocationUpdated;
use App\Events\EmergencyAlertTriggered;
use App\Events\StudyNotificationCreated;

class MeetupController extends Controller
{
    public function index(Request $request, $userId)
    {
        $meetups = Meetup::whereHas('participants', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->with(['creator', 'participants.user', 'studyGroup'])
            ->latest()
            ->get();
        
        return response()->json($meetups);
    }

    public function show(Request $request, $meetupId)
    {
        $meetup = Meetup::where('id', $meetupId)
            ->with(['creator', 'participants.user', 'locations.user', 'checkins.user', 'emergencyAlerts.user'])
            ->firstOrFail();
        
        return response()->json($meetup);
    }

    public function create(Request $request)
    {
        $request->validate([
            'creator_id' => 'required|exists:users,id',
            'title' => 'required|string',
            'description' => 'nullable|string',
            'meeting_date' => 'required|date',
            'meeting_time' => 'required|string',
            'estimated_duration' => 'required|integer',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'location_name' => 'required|string',
            'participant_ids' => 'array',
            'study_group_id' => 'nullable|exists:study_groups,id',
        ]);

        $meetup = Meetup::create([
            'id' => (string) Str::uuid(),
            'creator_id' => $request->creator_id,
            'study_group_id' => $request->study_group_id,
            'title' => $request->title,
            'description' => $request->description,
            'meeting_date' => $request->meeting_date,
            'meeting_time' => $request->meeting_time,
            'estimated_duration' => $request->estimated_duration,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'location_name' => $request->location_name,
            'status' => 'PENDING',
        ]);

        // Add creator as first participant (ACCEPTED
        MeetupParticipant::create([
            'id' => (string) Str::uuid(),
            'meetup_id' => $meetup->id,
            'user_id' => $request->creator_id,
            'status' => 'ACCEPTED',
        ]);

        // Add other participants
        $participantIds = $request->participant_ids ?? [];
        foreach ($participantIds as $participantId) {
            if ($participantId !== $request->creator_id) {
                $participant = MeetupParticipant::create([
                    'id' => (string) Str::uuid(),
                    'meetup_id' => $meetup->id,
                    'user_id' => $participantId,
                    'status' => 'PENDING',
                ]);
                
                // Send notification to participant
                $creator = \App\Models\User::find($request->creator_id);
                $notif = StudyNotification::create([
                    'id' => (string) Str::uuid(),
                    'sender_id' => $request->creator_id,
                    'receiver_id' => $participantId,
                    'type' => 'meetup_invite',
                    'message' => "{$creator->name} mengundangmu ke meetup: {$request->title}",
                    'data' => ['meetupId' => $meetup->id],
                ]);
                StudyNotificationCreated::dispatch($notif);
            }
        }

        MeetupCreated::dispatch($meetup);

        return response()->json($meetup->load(['creator', 'participants.user', 'studyGroup']), 201);
    }

    public function updateStatus(Request $request, $meetupId)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'status' => 'required|string|in:ACCEPTED,REJECTED',
        ]);

        $participant = MeetupParticipant::where('meetup_id', $meetupId)
            ->where('user_id', $request->user_id)
            ->firstOrFail();

        $participant->update(['status' => $request->status]);
        $meetup = Meetup::findOrFail($meetupId);

        // Check if all participants are accepted to set status to ACCEPTED
        $allAccepted = $meetup->participants->every(fn ($p) => in_array($p->status, ['ACCEPTED', 'ARRIVED', 'STARTED', 'FINISHED']));
        if ($allAccepted && $meetup->status === 'PENDING') {
            $meetup->update(['status' => 'ACCEPTED']);
        }

        MeetupUpdated::dispatch($meetup);

        return response()->json($meetup->load(['creator', 'participants.user', 'studyGroup']));
    }

    public function updateMeetupStatus(Request $request, $meetupId)
    {
        $request->validate([
            'status' => 'required|string|in:ACTIVE,STARTED,FINISHED',
        ]);

        $meetup = Meetup::findOrFail($meetupId);
        $meetup->update(['status' => $request->status]);

        MeetupUpdated::dispatch($meetup);

        return response()->json($meetup->load(['creator', 'participants.user', 'studyGroup']));
    }

    public function updateLocation(Request $request)
    {
        $request->validate([
            'meetup_id' => 'required|exists:meetups,id',
            'user_id' => 'required|exists:users,id',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'timestamp' => 'required|date',
        ]);

        $location = MeetupLocation::create([
            'id' => (string) Str::uuid(),
            'meetup_id' => $request->meetup_id,
            'user_id' => $request->user_id,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'timestamp' => $request->timestamp,
        ]);

        MeetupLocationUpdated::dispatch($location);

        return response()->json($location->load(['meetup', 'user']), 201);
    }

    public function checkin(Request $request)
    {
        $request->validate([
            'meetup_id' => 'required|exists:meetups,id',
            'user_id' => 'required|exists:users,id',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        // Check if user already checked in
        $existingCheckin = MeetupCheckin::where('meetup_id', $request->meetup_id)
            ->where('user_id', $request->user_id)
            ->first();

        if ($existingCheckin) {
            // Return existing check-in
            return response()->json($existingCheckin->load(['meetup', 'user']), 200);
        }

        $checkin = MeetupCheckin::create([
            'id' => (string) Str::uuid(),
            'meetup_id' => $request->meetup_id,
            'user_id' => $request->user_id,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'checkin_time' => now(),
        ]);

        // Update participant status to ARRIVED
        $participant = MeetupParticipant::where('meetup_id', $request->meetup_id)
            ->where('user_id', $request->user_id)
            ->first();
        $participant->update(['status' => 'ARRIVED']);

        // Check if all are arrived to set meetup STARTED
        $meetup = Meetup::findOrFail($request->meetup_id);
        $allArrived = $meetup->participants->every(fn ($p) => in_array($p->status, ['ARRIVED', 'STARTED', 'FINISHED']));
        if ($allArrived && $meetup->status === 'ACCEPTED') {
            $meetup->update(['status' => 'STARTED']);
        }

        MeetupUpdated::dispatch($meetup);

        return response()->json($checkin->load(['meetup', 'user']), 201);
    }

    public function triggerEmergency(Request $request)
    {
        $request->validate([
            'meetup_id' => 'required|exists:meetups,id',
            'user_id' => 'required|exists:users,id',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $alert = EmergencyAlert::create([
            'id' => (string) Str::uuid(),
            'meetup_id' => $request->meetup_id,
            'user_id' => $request->user_id,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'alert_time' => now(),
        ]);

        EmergencyAlertTriggered::dispatch($alert);

        // Send notifications to all other participants
        $meetup = Meetup::with('participants')->findOrFail($request->meetup_id);
        foreach ($meetup->participants as $participant) {
            if ($participant->user_id !== $request->user_id) {
                $user = \App\Models\User::find($request->user_id);
                $notif = StudyNotification::create([
                    'id' => (string) Str::uuid(),
                    'sender_id' => $request->user_id,
                    'receiver_id' => $participant->user_id,
                    'type' => 'emergency',
                    'message' => "DARURAT! {$user->name} butuh bantuan di meetup!",
                    'data' => ['meetupId' => $request->meetup_id, 'alertId' => $alert->id],
                ]);
                StudyNotificationCreated::dispatch($notif);
            }
        }

        return response()->json($alert->load(['meetup', 'user']), 201);
    }
}
