import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../../core/config/theme_provider.dart';
import '../../../../core/models/user_model.dart';
import '../../../../core/storage/app_preferences.dart';
import '../../../../features/auth/auth_provider.dart';
import '../../../../features/languages/screens/languages_screen.dart';
import '../../../../features/settings/screens/app_preferences_screen.dart';
import '../../../../features/settings/screens/change_password_screen.dart';
import '../../../../features/settings/server_settings_screen.dart';
import '../../../../l10n/app_localizations.dart';

/// Top-right Profile popup menu featuring relocated Language Switcher (before Preferences)
/// and Theme Switch Toggle (after Server Address).
class ProfileMenuPopup extends StatelessWidget {
  const ProfileMenuPopup({
    super.key,
    this.user,
    this.onLogout,
  });

  final UserModel? user;
  final VoidCallback? onLogout;

  Future<void> _defaultConfirmLogout(BuildContext context) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Log Out'),
        content: const Text('Are you sure you want to exit your session?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: const Color(0xFFEF4444),
            ),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Log Out'),
          ),
        ],
      ),
    );

    if (confirmed == true && context.mounted) {
      await context.read<AuthProvider>().logout();
    }
  }

  @override
  Widget build(BuildContext context) {
    final initials = (user?.name ?? '')
        .trim()
        .split(RegExp(r'\s+'))
        .where((part) => part.isNotEmpty)
        .take(2)
        .map((part) => part[0])
        .join()
        .toUpperCase();

    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;
    final l10n = AppLocalizations.of(context);
    final isDark = theme.brightness == Brightness.dark;

    return PopupMenuButton<String>(
      tooltip: 'Account & Settings',
      offset: const Offset(0, 48),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      icon: CircleAvatar(
        radius: 16,
        backgroundColor: colorScheme.primaryContainer,
        child: Text(
          initials.isNotEmpty ? initials : 'U',
          style: TextStyle(
            color: colorScheme.onPrimaryContainer,
            fontSize: 12,
            fontWeight: FontWeight.bold,
          ),
        ),
      ),
      onSelected: (value) {
        switch (value) {
          case 'languages':
            Navigator.of(context).push(
              MaterialPageRoute(builder: (_) => const LanguagesScreen()),
            );
            break;
          case 'preferences':
            Navigator.of(context).push(
              MaterialPageRoute(builder: (_) => const AppPreferencesScreen()),
            );
            break;
          case 'change_password':
            Navigator.of(context).push(
              MaterialPageRoute(builder: (_) => const ChangePasswordScreen()),
            );
            break;
          case 'server':
            Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) => ServerSettingsScreen(
                  preferences: context.read<AppPreferences>(),
                ),
              ),
            );
            break;
          case 'theme_toggle':
            final nextMode = isDark ? ThemeMode.light : ThemeMode.dark;
            context.read<ThemeProvider>().setThemeMode(nextMode);
            break;
          case 'logout':
            if (onLogout != null) {
              onLogout!();
            } else {
              _defaultConfirmLogout(context);
            }
            break;
        }
      },
      itemBuilder: (context) => [
        if (user != null) ...[
          PopupMenuItem<String>(
            enabled: false,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  user!.name,
                  style: theme.textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.bold,
                    color: colorScheme.onSurface,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                Text(
                  user!.email,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: colorScheme.onSurfaceVariant,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                if (user!.role.isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.only(top: 4),
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 6, vertical: 2),
                      decoration: BoxDecoration(
                        color: colorScheme.primary.withValues(alpha: 0.12),
                        borderRadius: BorderRadius.circular(4),
                      ),
                      child: Text(
                        user!.role.toUpperCase(),
                        style: TextStyle(
                          fontSize: 9,
                          fontWeight: FontWeight.w700,
                          color: colorScheme.primary,
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          ),
          const PopupMenuDivider(),
        ],
        // 1. Language Switcher directly BEFORE Preferences
        PopupMenuItem<String>(
          value: 'languages',
          child: Row(
            children: [
              const Icon(Icons.language_outlined, size: 20),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  l10n.text('Languages & Translations',
                      fallback: 'Languages & Translations'),
                  overflow: TextOverflow.ellipsis,
                ),
              ),
            ],
          ),
        ),
        // 2. Preferences
        const PopupMenuItem<String>(
          value: 'preferences',
          child: Row(
            children: [
              Icon(Icons.tune, size: 20),
              SizedBox(width: 12),
              Text('Preferences'),
            ],
          ),
        ),
        // 3. Change Password
        const PopupMenuItem<String>(
          value: 'change_password',
          child: Row(
            children: [
              Icon(Icons.lock_outline, size: 20),
              SizedBox(width: 12),
              Text('Change Password'),
            ],
          ),
        ),
        // 4. Server Address
        PopupMenuItem<String>(
          value: 'server',
          child: Row(
            children: [
              const Icon(Icons.dns_outlined, size: 20),
              const SizedBox(width: 12),
              Text(l10n.serverAddress),
            ],
          ),
        ),
        // 5. Theme Switch Toggle directly AFTER Server Address
        PopupMenuItem<String>(
          value: 'theme_toggle',
          child: Row(
            children: [
              Icon(
                isDark ? Icons.dark_mode_outlined : Icons.light_mode_outlined,
                size: 20,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(isDark ? 'Dark Mode' : 'Light Mode'),
              ),
              Switch.adaptive(
                value: isDark,
                onChanged: (val) {
                  Navigator.of(context).pop();
                  context
                      .read<ThemeProvider>()
                      .setThemeMode(val ? ThemeMode.dark : ThemeMode.light);
                },
              ),
            ],
          ),
        ),
        const PopupMenuDivider(),
        // 6. Log Out
        const PopupMenuItem<String>(
          value: 'logout',
          child: Row(
            children: [
              Icon(Icons.logout, color: Color(0xFFEF4444), size: 20),
              SizedBox(width: 12),
              Text(
                'Log Out',
                style: TextStyle(
                  color: Color(0xFFEF4444),
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}
