class Meetup {
  final String id;
  final String creatorId;
  final String? studyGroupId;
  final String title;
  final String? description;
  final DateTime meetingDate;
  final String meetingTime;
  final int estimatedDuration;
  final double latitude;
  final double longitude;
  final String locationName;
  final String status;
  final DateTime createdAt;
  final DateTime updatedAt;
  final List<MeetupParticipant>? participants;
  final List<MeetupLocation>? locations;

  Meetup({
    required this.id,
    required this.creatorId,
    this.studyGroupId,
    required this.title,
    this.description,
    required this.meetingDate,
    required this.meetingTime,
    required this.estimatedDuration,
    required this.latitude,
    required this.longitude,
    required this.locationName,
    required this.status,
    required this.createdAt,
    required this.updatedAt,
    this.participants,
    this.locations,
  });

  static double parseDouble(dynamic value) {
    if (value == null) return 0.0;
    if (value is double) return value;
    if (value is int) return value.toDouble();
    if (value is String) {
      if (value.trim().isEmpty) return 0.0;
      try {
        return double.parse(value);
      } catch (e) {
        return 0.0;
      }
    }
    return 0.0;
  }

  static int parseInt(dynamic value) {
    if (value == null) return 0;
    if (value is int) return value;
    if (value is double) return value.toInt();
    if (value is String) {
      if (value.trim().isEmpty) return 0;
      try {
        return int.parse(value);
      } catch (e) {
        try {
          return double.parse(value).toInt();
        } catch (e2) {
          return 0;
        }
      }
    }
    return 0;
  }

  factory Meetup.fromJson(Map<String, dynamic> json) {
    return Meetup(
      id: json['id'] as String,
      creatorId: json['creator_id'] as String,
      studyGroupId: json['study_group_id'] as String?,
      title: json['title'] as String,
      description: json['description'] as String?,
      meetingDate: DateTime.parse(json['meeting_date'] as String),
      meetingTime: json['meeting_time'] as String,
      estimatedDuration: parseInt(json['estimated_duration']),
      latitude: parseDouble(json['latitude']),
      longitude: parseDouble(json['longitude']),
      locationName: json['location_name'] as String,
      status: json['status'] as String,
      createdAt: DateTime.parse(json['created_at'] as String),
      updatedAt: DateTime.parse(json['updated_at'] as String),
      participants: (json['participants'] as List<dynamic>?)
          ?.map((p) => MeetupParticipant.fromJson(p as Map<String, dynamic>))
          .toList(),
      locations: (json['locations'] as List<dynamic>?)
          ?.map((l) => MeetupLocation.fromJson(l as Map<String, dynamic>))
          .toList(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'creator_id': creatorId,
      'study_group_id': studyGroupId,
      'title': title,
      'description': description,
      'meeting_date': meetingDate.toIso8601String(),
      'meeting_time': meetingTime,
      'estimated_duration': estimatedDuration,
      'latitude': latitude,
      'longitude': longitude,
      'location_name': locationName,
      'status': status,
    };
  }
}

class MeetupParticipant {
  final String id;
  final String meetupId;
  final String userId;
  final String status;
  final dynamic user;

  MeetupParticipant({
    required this.id,
    required this.meetupId,
    required this.userId,
    required this.status,
    this.user,
  });

  factory MeetupParticipant.fromJson(Map<String, dynamic> json) {
    return MeetupParticipant(
      id: json['id'] as String,
      meetupId: json['meetup_id'] as String,
      userId: json['user_id'] as String,
      status: json['status'] as String,
      user: json['user'],
    );
  }
}

class MeetupLocation {
  final String id;
  final String meetupId;
  final String userId;
  final double latitude;
  final double longitude;
  final DateTime timestamp;
  final dynamic user;

  MeetupLocation({
    required this.id,
    required this.meetupId,
    required this.userId,
    required this.latitude,
    required this.longitude,
    required this.timestamp,
    this.user,
  });

  factory MeetupLocation.fromJson(Map<String, dynamic> json) {
    return MeetupLocation(
      id: json['id'] as String,
      meetupId: json['meetup_id'] as String,
      userId: json['user_id'] as String,
      latitude: Meetup.parseDouble(json['latitude']),
      longitude: Meetup.parseDouble(json['longitude']),
      timestamp: DateTime.parse(json['timestamp'] as String),
      user: json['user'],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'meetup_id': meetupId,
      'user_id': userId,
      'latitude': latitude,
      'longitude': longitude,
      'timestamp': timestamp.toIso8601String(),
    };
  }
}

class EmergencyAlert {
  final String id;
  final String meetupId;
  final String userId;
  final double latitude;
  final double longitude;
  final DateTime alertTime;

  EmergencyAlert({
    required this.id,
    required this.meetupId,
    required this.userId,
    required this.latitude,
    required this.longitude,
    required this.alertTime,
  });

  factory EmergencyAlert.fromJson(Map<String, dynamic> json) {
    return EmergencyAlert(
      id: json['id'] as String,
      meetupId: json['meetup_id'] as String,
      userId: json['user_id'] as String,
      latitude: Meetup.parseDouble(json['latitude']),
      longitude: Meetup.parseDouble(json['longitude']),
      alertTime: DateTime.parse(json['alert_time'] as String),
    );
  }
}
