import 'package:flutter/foundation.dart';
import '../models/meetup.dart';
import '../services/meetup_service.dart';
import '../main.dart';
import 'dart:async';
import 'package:geolocator/geolocator.dart';
import 'package:permission_handler/permission_handler.dart';

class MeetupProvider with ChangeNotifier {
  late MeetupService _service;
  List<Meetup> _meetups = [];
  Meetup? _currentMeetup;
  bool _isLoading = false;
  String? _error;
  Timer? _locationTimer;
  bool _isTrackingLocation = false;
  EmergencyAlert? _latestAlert;
  final Map<String, MeetupLocation> _latestLocations = {}; // key: userId

  List<Meetup> get meetups => _meetups;
  Meetup? get currentMeetup => _currentMeetup;
  bool get isLoading => _isLoading;
  String? get error => _error;
  bool get isTrackingLocation => _isTrackingLocation;
  EmergencyAlert? get latestAlert => _latestAlert;
  Map<String, MeetupLocation> get latestLocations => _latestLocations;

  void initialize(ApiClient apiClient) {
    _service = MeetupService(apiClient);
  }

  Future<void> loadUserMeetups(String userId) async {
    _isLoading = true;
    notifyListeners();
    try {
      _meetups = await _service.getUserMeetups(userId);
      _error = null;
    } catch (e) {
      _error = e.toString();
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<void> loadMeetup(String meetupId) async {
    _isLoading = true;
    notifyListeners();
    try {
      _currentMeetup = await _service.getMeetup(meetupId);
      // Populate latest locations from meetup.locations
      if (_currentMeetup?.locations != null) {
        for (final loc in _currentMeetup!.locations!) {
          _latestLocations[loc.userId] = loc;
        }
      }
      _error = null;
    } catch (e) {
      _error = e.toString();
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<Meetup?> createMeetup(Map<String, dynamic> data) async {
    _isLoading = true;
    notifyListeners();
    try {
      final meetup = await _service.createMeetup(data);
      _meetups.add(meetup);
      _error = null;
      notifyListeners();
      return meetup;
    } catch (e) {
      _error = e.toString();
      notifyListeners();
      return null;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<void> updateParticipantStatus(String meetupId, String userId, String status) async {
    try {
      final meetup = await _service.updateParticipantStatus(meetupId, userId, status);
      final index = _meetups.indexWhere((m) => m.id == meetupId);
      if (index != -1) {
        _meetups[index] = meetup;
      }
      if (_currentMeetup?.id == meetupId) {
        _currentMeetup = meetup;
      }
      _error = null;
      notifyListeners();
    } catch (e) {
      _error = e.toString();
      notifyListeners();
    }
  }

  void onMeetupCreated(Map<String, dynamic> data) {
    final meetup = Meetup.fromJson(data['meetup']);
    final existingIndex = _meetups.indexWhere((m) => m.id == meetup.id);
    if (existingIndex == -1) {
      _meetups.insert(0, meetup);
    } else {
      _meetups[existingIndex] = meetup;
    }
    notifyListeners();
  }

  void onMeetupUpdated(Map<String, dynamic> data) {
    final meetup = Meetup.fromJson(data['meetup']);
    final index = _meetups.indexWhere((m) => m.id == meetup.id);
    if (index != -1) {
      _meetups[index] = meetup;
    }
    if (_currentMeetup?.id == meetup.id) {
      _currentMeetup = meetup;
    }
    notifyListeners();
  }

  void onMeetupLocationUpdated(Map<String, dynamic> data) {
    final location = MeetupLocation.fromJson(data['location']);
    _latestLocations[location.userId] = location;
    notifyListeners();
  }

  void onEmergencyAlert(Map<String, dynamic> data) {
    _latestAlert = EmergencyAlert.fromJson(data['alert']);
    notifyListeners();
  }

  void updateCurrentMeetup(Meetup meetup) {
    _currentMeetup = meetup;
    final index = _meetups.indexWhere((m) => m.id == meetup.id);
    if (index != -1) {
      _meetups[index] = meetup;
    }
    notifyListeners();
  }

  Future<void> startLocationTracking(String meetupId, String userId) async {
    if (_isTrackingLocation) return;

    final status = await Permission.location.request();
    if (status.isDenied) {
      _error = 'Location permission denied';
      notifyListeners();
      return;
    }

    _isTrackingLocation = true;
    notifyListeners();

    await _sendLocationUpdate(meetupId, userId);

    _locationTimer = Timer.periodic(const Duration(seconds: 30), (timer) {
      if (_isTrackingLocation) {
        _sendLocationUpdate(meetupId, userId);
      }
    });
  }

  Future<void> _sendLocationUpdate(String meetupId, String userId) async {
    try {
      final position = await Geolocator.getCurrentPosition(
        desiredAccuracy: LocationAccuracy.high,
      );
      await _service.updateLocation(
        meetupId,
        userId,
        position.latitude,
        position.longitude,
        DateTime.now(),
      );
    } catch (e) {
      if (kDebugMode) {
        print('Location update error: $e');
      }
    }
  }

  void stopLocationTracking() {
    _locationTimer?.cancel();
    _locationTimer = null;
    _isTrackingLocation = false;
    notifyListeners();
  }

  Future<void> checkin(String meetupId, String userId) async {
    try {
      final position = await Geolocator.getCurrentPosition(
        desiredAccuracy: LocationAccuracy.high,
      );
      await _service.checkin(meetupId, userId, position.latitude, position.longitude);
      await loadMeetup(meetupId);
    } catch (e) {
      _error = e.toString();
      notifyListeners();
    }
  }

  Future<void> triggerEmergency(String meetupId, String userId) async {
    try {
      final position = await Geolocator.getCurrentPosition(
        desiredAccuracy: LocationAccuracy.high,
      );
      final alert = await _service.triggerEmergency(
        meetupId,
        userId,
        position.latitude,
        position.longitude,
      );
      _latestAlert = alert;
      notifyListeners();
    } catch (e) {
      _error = e.toString();
      notifyListeners();
    }
  }

  Future<void> updateMeetupStatus(String meetupId, String status) async {
    try {
      final meetup = await _service.updateMeetupStatus(meetupId, status);
      final index = _meetups.indexWhere((m) => m.id == meetupId);
      if (index != -1) {
        _meetups[index] = meetup;
      }
      if (_currentMeetup?.id == meetupId) {
        _currentMeetup = meetup;
      }
      _error = null;
      notifyListeners();
    } catch (e) {
      _error = e.toString();
      notifyListeners();
    }
  }

  void clearAlert() {
    _latestAlert = null;
    notifyListeners();
  }

  @override
  void dispose() {
    _locationTimer?.cancel();
    super.dispose();
  }
}
