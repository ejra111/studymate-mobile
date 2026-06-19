import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import 'package:provider/provider.dart';
import '../providers/meetup_provider.dart';
import '../models/meetup.dart';
import 'dart:ui';

class MeetupDetailsScreen extends StatefulWidget {
  final String meetupId;
  final String currentUserId;

  const MeetupDetailsScreen({
    super.key,
    required this.meetupId,
    required this.currentUserId,
  });

  @override
  State<MeetupDetailsScreen> createState() => _MeetupDetailsScreenState();
}

class _MeetupDetailsScreenState extends State<MeetupDetailsScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<MeetupProvider>().loadMeetup(widget.meetupId);
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Detail Meetup'),
        backgroundColor: const Color(0xFF2D4C81),
      ),
      body: Consumer<MeetupProvider>(
        builder: (context, provider, child) {
          if (provider.isLoading && provider.currentMeetup == null) {
            return const Center(child: CircularProgressIndicator());
          }

          final meetup = provider.currentMeetup;
          if (meetup == null) {
            return const Center(child: Text('Meetup tidak ditemukan'));
          }

          final isActive = ['ACTIVE', 'STARTED'].contains(meetup.status);
          final myParticipant = meetup.participants?.firstWhere(
            (p) => p.userId == widget.currentUserId,
            orElse: () => MeetupParticipant(id: '', meetupId: '', userId: '', status: ''),
          );

          return Stack(
            children: [
              SingleChildScrollView(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        meetup.title,
                        style: const TextStyle(
                          fontSize: 24,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(height: 8),
                      if (meetup.description != null)
                        Text(
                          meetup.description!,
                          style: const TextStyle(fontSize: 16),
                        ),
                      const SizedBox(height: 16),
                      _buildInfoCard(
                        '📅 Tanggal & Waktu',
                        '${meetup.meetingDate.day}/${meetup.meetingDate.month}/${meetup.meetingDate.year} - ${meetup.meetingTime}',
                      ),
                      _buildInfoCard('📍 Lokasi', meetup.locationName),
                      _buildInfoCard('⏱️ Durasi', '${meetup.estimatedDuration} menit'),
                      _buildInfoCard(
                        'Status',
                        meetup.status,
                        color: _getStatusColor(meetup.status),
                      ),
                      const SizedBox(height: 16),
                      _buildMap(meetup, provider.latestLocations),
                      const SizedBox(height: 16),
                      const Text(
                        'Peserta',
                        style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
                      ),
                      const SizedBox(height: 8),
                      ...meetup.participants?.map((p) => _buildParticipantItem(p)).toList() ?? [],
                      if (myParticipant?.status == 'PENDING') ...[
                        const SizedBox(height: 16),
                        Row(
                          children: [
                            Expanded(
                              child: ElevatedButton.icon(
                                style: ElevatedButton.styleFrom(backgroundColor: Colors.green),
                                onPressed: () async {
                                  await provider.updateParticipantStatus(
                                    widget.meetupId,
                                    widget.currentUserId,
                                    'ACCEPTED',
                                  );
                                },
                                icon: const Icon(Icons.check, color: Colors.white),
                                label: const Text('Terima', style: TextStyle(color: Colors.white)),
                              ),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: ElevatedButton.icon(
                                style: ElevatedButton.styleFrom(backgroundColor: Colors.red),
                                onPressed: () async {
                                  await provider.updateParticipantStatus(
                                    widget.meetupId,
                                    widget.currentUserId,
                                    'REJECTED',
                                  );
                                },
                                icon: const Icon(Icons.close, color: Colors.white),
                                label: const Text('Tolak', style: TextStyle(color: Colors.white)),
                              ),
                            ),
                          ],
                        ),
                      ],
                      if (meetup.creatorId == widget.currentUserId && !isActive) ...[
                        const SizedBox(height: 16),
                        ElevatedButton.icon(
                          style: ElevatedButton.styleFrom(backgroundColor: Colors.green),
                          onPressed: () async {
                            await provider.updateMeetupStatus(widget.meetupId, 'ACTIVE');
                          },
                          icon: const Icon(Icons.play_arrow, color: Colors.white),
                          label: const Text('Mulai Meetup', style: TextStyle(color: Colors.white)),
                        ),
                      ],
                      if (myParticipant?.status == 'ACCEPTED') ...[
                        const SizedBox(height: 16),
                        Row(
                          children: [
                            Expanded(
                              child: ElevatedButton.icon(
                                style: ElevatedButton.styleFrom(
                                  backgroundColor: provider.isTrackingLocation ? Colors.red : Colors.blue,
                                ),
                                onPressed: () {
                                  if (provider.isTrackingLocation) {
                                    context.read<MeetupProvider>().stopLocationTracking();
                                  } else {
                                    context.read<MeetupProvider>().startLocationTracking(
                                          widget.meetupId,
                                          widget.currentUserId,
                                        );
                                  }
                                },
                                icon: Icon(
                                  provider.isTrackingLocation ? Icons.location_off : Icons.location_on,
                                  color: Colors.white,
                                ),
                                label: Text(
                                  provider.isTrackingLocation ? 'Berhenti Tracking Lokasi' : 'Mulai Tracking Lokasi',
                                  style: const TextStyle(color: Colors.white),
                                ),
                              ),
                            ),
                          ],
                        ),
                        if (provider.isTrackingLocation && myParticipant?.status != 'ARRIVED') ...[
                          const SizedBox(height: 8),
                          ElevatedButton.icon(
                            style: ElevatedButton.styleFrom(backgroundColor: Colors.orange),
                            onPressed: () => context.read<MeetupProvider>().checkin(
                                  widget.meetupId,
                                  widget.currentUserId,
                                ),
                            icon: const Icon(Icons.check_circle, color: Colors.white),
                            label: const Text('Check In', style: TextStyle(color: Colors.white)),
                          ),
                        ],
                        const SizedBox(height: 16),
                        const Text(
                          'Lokasi Peserta Terbaru',
                          style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
                        ),
                        const SizedBox(height: 8),
                        if (provider.latestLocations.isEmpty)
                          const Text('Belum ada peserta yang membagikan lokasi.')
                        else
                          ...provider.latestLocations.values.map((loc) {
                            final user = loc.user;
                            final name = user?['name'] ?? 'Unknown';
                            return ListTile(
                              leading: CircleAvatar(
                                backgroundColor: const Color(0xFF2D4C81),
                                child: Text(name[0].toUpperCase()),
                              ),
                              title: Text(name),
                              subtitle: Text(
                                '${loc.latitude.toStringAsFixed(4)}, ${loc.longitude.toStringAsFixed(4)}',
                              ),
                              trailing: IconButton(
                                icon: const Icon(Icons.map),
                                onPressed: () {
                                  // Optional: Open map centering on this user's location
                                },
                              ),
                            );
                          }).toList(),
                      ],
                      const SizedBox(height: 100),
                    ],
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  Widget _buildInfoCard(String title, String value, {Color? color}) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Row(
          children: [
            Expanded(
              child: Text(
                title,
                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
              ),
            ),
            Text(
              value,
              style: TextStyle(fontSize: 14, color: color),
            ),
          ],
        ),
      ),
    );
  }

  Color _getStatusColor(String status) {
    switch (status) {
      case 'PENDING':
        return Colors.orange;
      case 'ACCEPTED':
        return Colors.blue;
      case 'ACTIVE':
        return Colors.green;
      case 'STARTED':
        return Colors.teal;
      case 'FINISHED':
        return Colors.grey;
      default:
        return Colors.grey;
    }
  }

  Widget _buildParticipantItem(MeetupParticipant p) {
    final name = p.user?['name'] ?? 'Unknown';
    final avatarUrl = p.user?['avatarUrl'] ?? p.user?['avatar_url'];
    return ListTile(
      title: Text(name),
      subtitle: Text(p.status),
      leading: CircleAvatar(
        backgroundColor: const Color(0xFF2D4C81),
        child: avatarUrl != null
            ? ClipOval(
                child: Image.network(
                  avatarUrl,
                  width: 40,
                  height: 40,
                  fit: BoxFit.cover,
                  errorBuilder: (_, __, ___) => Text(name[0].toUpperCase()),
                ),
              )
            : Text(name[0].toUpperCase()),
      ),
    );
  }

  Widget _buildMap(Meetup meetup, Map<String, MeetupLocation> latestLocations) {
    final markers = <Marker>[
      // Meetup location marker
      Marker(
        point: LatLng(meetup.latitude, meetup.longitude),
        width: 80,
        height: 80,
        child: const Icon(Icons.location_on, color: Colors.red, size: 40),
      ),
    ];

    // Add markers for each participant's latest location
    for (final entry in latestLocations.entries) {
      final location = entry.value;
      final user = location.user;
      final userName = user?['name'] ?? 'Unknown';
      markers.add(
        Marker(
          point: LatLng(location.latitude, location.longitude),
          width: 120,
          height: 60,
          child: Column(
            children: [
              CircleAvatar(
                radius: 15,
                backgroundColor: const Color(0xFF2D4C81),
                child: Text(
                  userName[0].toUpperCase(),
                  style: const TextStyle(color: Colors.white),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 2),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(4),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.grey.withOpacity(0.5),
                      blurRadius: 2,
                      offset: const Offset(0, 1),
                    ),
                  ],
                ),
                child: Text(
                  userName,
                  style: const TextStyle(fontSize: 10, fontWeight: FontWeight.bold),
                ),
              ),
            ],
          ),
        ),
      );
    }

    return Container(
      height: 300,
      decoration: BoxDecoration(borderRadius: BorderRadius.circular(8)),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(8),
        child: FlutterMap(
          options: MapOptions(
            initialCenter: LatLng(meetup.latitude, meetup.longitude),
            initialZoom: 15,
          ),
          children: [
            TileLayer(
              urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
              userAgentPackageName: 'com.example.studymate_mobile',
            ),
            MarkerLayer(markers: markers),
          ],
        ),
      ),
    );
  }
}
