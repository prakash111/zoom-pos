import 'package:flutter/services.dart';

/// Plays the SuperAdmin-selected KDS/dashboard alert preset.
class SoundAlertService {
  SoundAlertService._();

  static final SoundAlertService instance = SoundAlertService._();

  Future<void> play({required String preset}) async {
    switch (preset) {
      case 'bell':
        await SystemSound.play(SystemSoundType.alert);
        break;
      case 'alert':
      case 'alarm':
        await SystemSound.play(SystemSoundType.alert);
        await Future<void>.delayed(const Duration(milliseconds: 180));
        await SystemSound.play(SystemSoundType.alert);
        break;
      case 'chime':
      default:
        await SystemSound.play(SystemSoundType.click);
        break;
    }
    HapticFeedback.mediumImpact();
  }
}
