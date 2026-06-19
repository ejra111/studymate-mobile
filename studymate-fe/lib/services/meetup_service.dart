import '../models/meetup.dart';
import '../main.dart';

class MeetupService {
  final ApiClient apiClient;

  MeetupService(this.apiClient);

  Future<List<Meetup>> getUserMeetups(String userId) async {
    final data = await apiClient.get('/meetups/user/$userId');
    return (data as List).map((json) => Meetup.fromJson(json)).toList();
  }

  Future<Meetup> getMeetup(String meetupId) async {
    final data = await apiClient.get('/meetups/$meetupId');
    return Meetup.fromJson(data);
  }

  Future<Meetup> createMeetup(Map<String, dynamic> data) async {
    final response = await apiClient.post('/meetups/', data);
    return Meetup.fromJson(response);
  }

  Future<Meetup> updateParticipantStatus(
      String meetupId, String userId, String status) async {
    final response = await apiClient.put('/meetups/$meetupId/participant', {
      'user_id': userId,
      'status': status,
    });
    return Meetup.fromJson(response);
  }

  Future<MeetupLocation> updateLocation(
      String meetupId, String userId, double lat, double lng, DateTime time) async {
    final response = await apiClient.post('/meetups/location', {
      'meetup_id': meetupId,
      'user_id': userId,
      'latitude': lat,
      'longitude': lng,
      'timestamp': time.toIso8601String(),
    });
    return MeetupLocation.fromJson(response);
  }

  Future<void> checkin(String meetupId, String userId, double lat, double lng) async {
    await apiClient.post('/meetups/checkin', {
      'meetup_id': meetupId,
      'user_id': userId,
      'latitude': lat,
      'longitude': lng,
    });
  }

  Future<EmergencyAlert> triggerEmergency(
      String meetupId, String userId, double lat, double lng) async {
    final response = await apiClient.post('/meetups/emergency', {
      'meetup_id': meetupId,
      'user_id': userId,
      'latitude': lat,
      'longitude': lng,
    });
    return EmergencyAlert.fromJson(response);
  }

  Future<Meetup> updateMeetupStatus(String meetupId, String status) async {
    final response = await apiClient.put('/meetups/$meetupId/status', {
      'status': status,
    });
    return Meetup.fromJson(response);
  }
}
