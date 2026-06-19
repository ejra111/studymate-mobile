import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/meetup_provider.dart';
import './meetup_details_screen.dart';
import './create_meetup_screen.dart';

class MeetupListScreen extends StatefulWidget {
  final String userId;
  final List<String>? initialParticipantIds;
  final String? studyGroupId;

  const MeetupListScreen({
    super.key,
    required this.userId,
    this.initialParticipantIds,
    this.studyGroupId,
  });

  @override
  State<MeetupListScreen> createState() => _MeetupListScreenState();
}

class _MeetupListScreenState extends State<MeetupListScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<MeetupProvider>().loadUserMeetups(widget.userId);
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Meetup Saya'),
        backgroundColor: const Color(0xFF2D4C81),
        actions: [
          IconButton(
            icon: const Icon(Icons.add),
            onPressed: () async {
              final result = await Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (context) => CreateMeetupScreen(
                    creatorId: widget.userId,
                    participantIds: widget.initialParticipantIds ?? [],
                    studyGroupId: widget.studyGroupId,
                  ),
                ),
              );
              if (result != null && mounted) {
                context.read<MeetupProvider>().loadUserMeetups(widget.userId);
              }
            },
          ),
        ],
      ),
      body: Consumer<MeetupProvider>(
        builder: (context, provider, child) {
          if (provider.isLoading && provider.meetups.isEmpty) {
            return const Center(child: CircularProgressIndicator());
          }

          if (provider.error != null) {
            return Center(child: Text('Error: ${provider.error}'));
          }

          if (provider.meetups.isEmpty) {
            return const Center(child: Text('Tidak ada meetup'));
          }

          return ListView.builder(
            itemCount: provider.meetups.length,
            padding: const EdgeInsets.all(8),
            itemBuilder: (context, index) {
              final meetup = provider.meetups[index];
              return Card(
                margin: const EdgeInsets.symmetric(vertical: 4),
                child: ListTile(
                  title: Text(meetup.title),
                  subtitle: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(meetup.locationName),
                      Text('${meetup.meetingDate.day}/${meetup.meetingDate.month}/${meetup.meetingDate.year} - ${meetup.meetingTime}'),
                    ],
                  ),
                  trailing: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(
                      color: _getStatusColor(meetup.status),
                      borderRadius: BorderRadius.circular(4),
                    ),
                    child: Text(
                      meetup.status,
                      style: const TextStyle(color: Colors.white, fontSize: 12),
                    ),
                  ),
                  onTap: () => Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (context) => MeetupDetailsScreen(
                        meetupId: meetup.id,
                        currentUserId: widget.userId,
                      ),
                    ),
                  ),
                ),
              );
            },
          );
        },
      ),
    );
  }

  Color _getStatusColor(String status) {
    switch (status) {
      case 'PENDING': return Colors.orange;
      case 'ACCEPTED': return Colors.blue;
      case 'ACTIVE': return Colors.green;
      case 'STARTED': return Colors.teal;
      case 'FINISHED': return Colors.grey;
      default: return Colors.grey;
    }
  }
}
