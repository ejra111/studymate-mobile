// This is a basic Flutter widget test.
//
// To perform an interaction with a widget in your test, use the WidgetTester
// utility in the flutter_test package. For example, you can send tap and scroll
// gestures. You can also use WidgetTester to find child widgets in the widget
// tree, read text, and verify that the values of widget properties are correct.

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shimmer/shimmer.dart';

import 'package:studymate_mobile/main.dart';

void main() {
  testWidgets('SkeletonLoader should render correctly', (WidgetTester tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: SkeletonLoader(
            width: 100,
            height: 20,
            borderRadius: 4,
          ),
        ),
      ),
    );
    
    expect(find.byType(Shimmer), findsOneWidget);
  });

  testWidgets('ErrorState should display message and retry button', (WidgetTester tester) async {
    bool retryPressed = false;
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: ErrorState(
            message: 'Test error message',
            onRetry: () => retryPressed = true,
          ),
        ),
      ),
    );
    
    expect(find.text('Test error message'), findsOneWidget);
    expect(find.text('Coba Lagi'), findsOneWidget);
    
    await tester.tap(find.text('Coba Lagi'));
    expect(retryPressed, true);
  });
}

