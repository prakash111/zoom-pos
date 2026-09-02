import 'package:flutter/services.dart';
import 'package:just_audio/just_audio.dart';

/// Plays the KDS/dashboard order alert sound — a tenant-configured preset
/// (mapped to a built-in system alert sound, since presets are labels rather
/// than shipped audio assets) or, when the tenant pasted a custom sound URL
/// under Settings > Notifications > Restaurant Order Alerts, that hosted
/// audio file via `just_audio`.
class SoundAlertService {
  SoundAlertService._();

  static final SoundAlertService instance = SoundAlertService._();

  AudioPlayer? _player;

  Future<void> play({required String preset, String? soundUrl}) async {
    if (soundUrl != null && soundUrl.isNotEmpty) {
      try {
        _player ??= AudioPlayer();
        await _player!.setUrl(soundUrl);
        await _player!.play();
        return;
      } catch (_) {
        // Fall through to the system sound if the custom URL fails to load.
      }
    }

    switch (preset) {
      case 'bell':
        await SystemSound.play(SystemSoundType.alert);
        break;
      case 'alert':
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
