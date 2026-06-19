import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import 'package:geolocator/geolocator.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:geocoding/geocoding.dart' as geocoding;
import '../providers/meetup_provider.dart';
import 'package:provider/provider.dart';

class CreateMeetupScreen extends StatefulWidget {
  final String creatorId;
  final List<String> participantIds;
  final String? studyGroupId;

  const CreateMeetupScreen({
    super.key,
    required this.creatorId,
    required this.participantIds,
    this.studyGroupId,
  });

  @override
  State<CreateMeetupScreen> createState() => _CreateMeetupScreenState();
}

class _CreateMeetupScreenState extends State<CreateMeetupScreen> {
  final _formKey = GlobalKey<FormState>();
  final _titleController = TextEditingController();
  final _descriptionController = TextEditingController();
  final _locationNameController = TextEditingController();
  final _searchLocationController = TextEditingController();
  DateTime? _selectedDate;
  TimeOfDay? _selectedTime;
  LatLng? _selectedLocation;
  final MapController _mapController = MapController();
  bool _isSubmitting = false;
  List<geocoding.Placemark> _searchResults = [];
  bool _isSearching = false;

  @override
  void initState() {
    super.initState();
    _getCurrentLocation();
  }

  @override
  void dispose() {
    _titleController.dispose();
    _descriptionController.dispose();
    _locationNameController.dispose();
    _searchLocationController.dispose();
    super.dispose();
  }

  Future<void> _searchLocation(String query) async {
    if (query.isEmpty) {
      setState(() {
        _searchResults = [];
        _isSearching = false;
      });
      return;
    }

    setState(() => _isSearching = true);

    try {
      List<geocoding.Location> locations = await geocoding.locationFromAddress(query);
      
      if (locations.isNotEmpty) {
        List<geocoding.Placemark> placemarks = await geocoding.placemarkFromCoordinates(
          locations.first.latitude,
          locations.first.longitude,
        );

        setState(() {
          _searchResults = placemarks;
          _isSearching = false;
        });
      } else {
        setState(() {
          _searchResults = [];
          _isSearching = false;
        });
      }
    } catch (e) {
      setState(() => _isSearching = false);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Gagal mencari lokasi: $e')),
        );
      }
    }
  }

  void _selectPlace(geocoding.Placemark place, List<geocoding.Location> locations) {
    final location = locations.first;
    
    setState(() {
      _selectedLocation = LatLng(location.latitude, location.longitude);
      _locationNameController.text = '${place.name}, ${place.locality}, ${place.administrativeArea}';
      _searchResults = [];
      _searchLocationController.clear();
    });

    _mapController.move(_selectedLocation!, 15);
  }

  Future<void> _getCurrentLocation() async {
    final status = await Permission.location.request();
    if (status.isGranted) {
      final position = await Geolocator.getCurrentPosition(
        desiredAccuracy: LocationAccuracy.high,
      );
      setState(() {
        _selectedLocation = LatLng(position.latitude, position.longitude);
      });
      _mapController.move(_selectedLocation!, 15);
    }
  }

  Future<void> _selectDate() async {
    final DateTime? picked = await showDatePicker(
      context: context,
      initialDate: DateTime.now(),
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 365)),
    );
    if (picked != null) {
      setState(() {
        _selectedDate = picked;
      });
    }
  }

  Future<void> _selectTime() async {
    final TimeOfDay? picked = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.now(),
    );
    if (picked != null) {
      setState(() {
        _selectedTime = picked;
      });
    }
  }

  void _onMapTap(LatLng point) {
    setState(() {
      _selectedLocation = point;
    });
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    if (_selectedDate == null || _selectedTime == null || _selectedLocation == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Lengkapi semua data terlebih dahulu!')),
      );
      return;
    }

    setState(() => _isSubmitting = true);

    final provider = context.read<MeetupProvider>();

    try {
      final meetup = await provider.createMeetup({
        'creator_id': widget.creatorId,
        'study_group_id': widget.studyGroupId,
        'title': _titleController.text.trim(),
        'description': _descriptionController.text.trim(),
        'meeting_date': _selectedDate!.toIso8601String(),
        'meeting_time': '${_selectedTime!.hour}:${_selectedTime!.minute.toString().padLeft(2, '0')}',
        'estimated_duration': 60,
        'latitude': _selectedLocation!.latitude,
        'longitude': _selectedLocation!.longitude,
        'location_name': _locationNameController.text.trim(),
        'participant_ids': widget.participantIds,
      });

      if (mounted) {
        if (meetup != null) {
          Navigator.pop(context, meetup);
        } else if (provider.error != null) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(provider.error!), backgroundColor: Colors.red),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Gagal membuat meetup: $e'), backgroundColor: Colors.red),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _isSubmitting = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Buat Meetup'),
        backgroundColor: const Color(0xFF2D4C81),
      ),
      body: _isSubmitting
          ? const Center(child: CircularProgressIndicator())
          : SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    TextFormField(
                      controller: _titleController,
                      decoration: const InputDecoration(
                        labelText: 'Judul Meetup *',
                        border: OutlineInputBorder(),
                      ),
                      validator: (value) =>
                          value?.trim().isEmpty ?? true ? 'Masukkan judul' : null,
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _descriptionController,
                      decoration: const InputDecoration(
                        labelText: 'Deskripsi',
                        border: OutlineInputBorder(),
                      ),
                      maxLines: 3,
                    ),
                    const SizedBox(height: 16),
                    Row(
                      children: [
                        Expanded(
                          child: ElevatedButton.icon(
                            onPressed: _selectDate,
                            icon: const Icon(Icons.calendar_today),
                            label: Text(
                              _selectedDate == null
                                  ? 'Pilih Tanggal *'
                                  : '${_selectedDate!.day}/${_selectedDate!.month}/${_selectedDate!.year}',
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: ElevatedButton.icon(
                            onPressed: _selectTime,
                            icon: const Icon(Icons.access_time),
                            label: Text(
                              _selectedTime == null
                                  ? 'Pilih Waktu *'
                                  : '${_selectedTime!.hour}:${_selectedTime!.minute.toString().padLeft(2, '0')}',
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 16),
                    TextField(
                      controller: _searchLocationController,
                      decoration: InputDecoration(
                        labelText: 'Cari Lokasi...',
                        prefixIcon: const Icon(Icons.search),
                        border: const OutlineInputBorder(),
                        suffixIcon: _searchLocationController.text.isNotEmpty
                            ? IconButton(
                                icon: const Icon(Icons.clear),
                                onPressed: () {
                                  _searchLocationController.clear();
                                  setState(() => _searchResults = []);
                                },
                              )
                            : null,
                      ),
                      onChanged: (query) {
                        Future.delayed(const Duration(milliseconds: 500), () {
                          if (_searchLocationController.text == query) {
                            _searchLocation(query);
                          }
                        });
                      },
                    ),
                    if (_isSearching)
                      const Padding(
                        padding: EdgeInsets.all(8.0),
                        child: Center(child: CircularProgressIndicator()),
                      )
                    else if (_searchResults.isNotEmpty)
                      Container(
                        height: 200,
                        margin: const EdgeInsets.only(top: 8),
                        decoration: BoxDecoration(
                          border: Border.all(color: Colors.grey),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: ListView.builder(
                          itemCount: _searchResults.length,
                          itemBuilder: (context, index) {
                            final place = _searchResults[index];
                            return ListTile(
                              leading: const Icon(Icons.location_on),
                              title: Text(place.name ?? 'Lokasi tidak dikenal'),
                              subtitle: Text('${place.locality ?? ''}, ${place.administrativeArea ?? ''}'),
                              onTap: () async {
                                final locationQuery = '${place.name}, ${place.locality}, ${place.administrativeArea}';
                                List<geocoding.Location> locations = await geocoding.locationFromAddress(locationQuery);
                                if (locations.isNotEmpty) {
                                  _selectPlace(place, locations);
                                }
                              },
                            );
                          },
                        ),
                      ),
                    const SizedBox(height: 16),
                    TextFormField(
                      controller: _locationNameController,
                      decoration: const InputDecoration(
                        labelText: 'Nama Lokasi *',
                        border: OutlineInputBorder(),
                      ),
                      validator: (value) =>
                          value?.trim().isEmpty ?? true ? 'Masukkan nama lokasi' : null,
                    ),
                    const SizedBox(height: 16),
                    Container(
                      height: 300,
                      decoration: BoxDecoration(
                        border: Border.all(color: Colors.grey),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(8),
                        child: FlutterMap(
                          mapController: _mapController,
                          options: MapOptions(
                            initialCenter:
                                _selectedLocation ?? const LatLng(-6.2088, 106.8456),
                            initialZoom: 15,
                            onTap: (_, point) => _onMapTap(point),
                          ),
                          children: [
                            TileLayer(
                              urlTemplate:
                                  'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                              userAgentPackageName: 'com.example.studymate_mobile',
                            ),
                            if (_selectedLocation != null)
                              MarkerLayer(
                                markers: [
                                  Marker(
                                    point: _selectedLocation!,
                                    width: 80,
                                    height: 80,
                                    child: const Icon(
                                      Icons.location_on,
                                      color: Colors.red,
                                      size: 40,
                                    ),
                                  ),
                                ],
                              ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 8),
                    const Text(
                      'Klik pada peta untuk memilih lokasi',
                      style: TextStyle(color: Colors.grey),
                    ),
                    const SizedBox(height: 16),
                    FilledButton.icon(
                      onPressed: _submit,
                      style: FilledButton.styleFrom(
                        backgroundColor: const Color(0xFF2D4C81),
                        padding: const EdgeInsets.symmetric(vertical: 16),
                      ),
                      icon: const Icon(Icons.send),
                      label: const Text(
                        'Buat Meetup',
                        style: TextStyle(fontSize: 18),
                      ),
                    ),
                  ],
                ),
              ),
            ),
    );
  }
}
