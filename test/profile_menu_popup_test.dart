import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:zoom_pos_mobile/core/config/theme_provider.dart';
import 'package:zoom_pos_mobile/core/models/user_model.dart';
import 'package:zoom_pos_mobile/core/storage/app_preferences.dart';
import 'package:zoom_pos_mobile/screens/dashboard/widgets/profile_menu_popup.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    SharedPreferences.setMockInitialValues({});
  });

  testWidgets(
      'ProfileMenuPopup renders language switcher before preferences and theme switch after server address',
      (tester) async {
    final themeProvider = ThemeProvider();
    final preferences = AppPreferences();

    final testUser = UserModel(
      id: '1',
      name: 'Prakash Singh',
      email: 'prakash@example.com',
      role: 'admin',
      companyId: '1',
      permissions: const {},
    );

    await tester.pumpWidget(
      MultiProvider(
        providers: [
          ChangeNotifierProvider<ThemeProvider>.value(value: themeProvider),
          Provider<AppPreferences>.value(value: preferences),
        ],
        child: MaterialApp(
          home: Scaffold(
            appBar: AppBar(
              actions: [
                ProfileMenuPopup(user: testUser),
              ],
            ),
          ),
        ),
      ),
    );

    await tester.pumpAndSettle();

    // Verify user avatar initials button exists
    expect(find.text('PS'), findsOneWidget);

    // Open popup menu
    await tester.tap(find.text('PS'));
    await tester.pumpAndSettle();

    // Verify user info
    expect(find.text('Prakash Singh'), findsOneWidget);
    expect(find.text('prakash@example.com'), findsOneWidget);

    // Verify menu items in order
    expect(find.text('Languages & Translations'), findsOneWidget);
    expect(find.text('Preferences'), findsOneWidget);
    expect(find.text('Change Password'), findsOneWidget);
    expect(find.text('Server address'), findsOneWidget);
    expect(find.text('Light Mode'), findsOneWidget);
    expect(find.text('Log Out'), findsOneWidget);

    // Verify position ordering: Languages & Translations should be above Preferences
    final langPos = tester.getTopLeft(find.text('Languages & Translations'));
    final prefPos = tester.getTopLeft(find.text('Preferences'));
    final serverPos = tester.getTopLeft(find.text('Server address'));
    final themePos = tester.getTopLeft(find.text('Light Mode'));

    expect(langPos.dy, lessThan(prefPos.dy),
        reason: 'Language switcher must appear before Preferences');
    expect(themePos.dy, greaterThan(serverPos.dy),
        reason: 'Theme switch toggle must appear after Server Address');

    // Tap theme toggle and verify theme switches
    await tester.tap(find.text('Light Mode'));
    await tester.pumpAndSettle();

    expect(themeProvider.themeMode, ThemeMode.dark);
  });
}
