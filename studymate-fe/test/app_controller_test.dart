import 'package:flutter_test/flutter_test.dart';
import 'package:studymate_mobile/main.dart';

void main() {
  group('AppController', () {
    test('clearCoachMessages should reset coach messages', () {
      final api = ApiClient('http://localhost:8000/api');
      final controller = AppController(api);
      
      controller.coachMessages.add({
        'sender': 'User',
        'message': 'Test message',
        'timestamp': '',
      });
      
      expect(controller.coachMessages.length, greaterThan(1));
      
      controller.clearCoachMessages();
      
      expect(controller.coachMessages.length, 1);
      expect(controller.coachMessages.first['sender'], 'AI Coach');
    });

    test('setFontScale should update font scale and notify listeners', () async {
      final api = ApiClient('http://localhost:8000/api');
      final controller = AppController(api);
      
      // Wait for _loadFontScale to complete
      await Future.delayed(const Duration(milliseconds: 100));
      
      bool wasNotified = false;
      controller.addListener(() {
        wasNotified = true;
      });
      
      expect(controller.fontScale, 1.0);
      
      await controller.setFontScale(1.2);
      
      expect(controller.fontScale, 1.2);
      expect(wasNotified, true);
    });
  });
}
