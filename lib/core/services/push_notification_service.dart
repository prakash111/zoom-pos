import 'dart:convert';
import 'dart:io';
import 'dart:typed_data';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../features/auth/auth_provider.dart';
import '../../features/pos/sales_repository.dart';
import '../../features/receivables/screens/due_receivables_screen.dart';
import '../../features/restaurant/screens/restaurant_kds_screen.dart';
import '../../features/sales/screens/sale_detail_screen.dart';
import '../api/api_client.dart';
import '../config/app_config.dart';
import '../utils/currency_formatter.dart';

const _pushConfigCacheKey = 'zoom_pos.push_config';
const _dismissAction = 'dismiss_alarm';

final GlobalKey<NavigatorState> appNavigatorKey = GlobalKey<NavigatorState>();
final GlobalKey<ScaffoldMessengerState> appMessengerKey =
    GlobalKey<ScaffoldMessengerState>();

@pragma('vm:entry-point')
Future<void> firebasePushBackgroundHandler(RemoteMessage message) async {
  if (!Platform.isAndroid) return;
  await _initializeFirebaseFromCache();
  await _showLocalMessage(message.data);
}

@pragma('vm:entry-point')
void localNotificationBackgroundResponse(NotificationResponse response) {
  if (response.actionId != _dismissAction) return;
  FlutterLocalNotificationsPlugin().cancel(id: response.id ?? 0);
}

Future<Map<String, dynamic>?> _cachedPushConfig() async {
  final preferences = await SharedPreferences.getInstance();
  final raw = preferences.getString(_pushConfigCacheKey);
  if (raw == null) return null;
  final decoded = jsonDecode(raw);
  return decoded is Map ? Map<String, dynamic>.from(decoded) : null;
}

Future<void> _initializeFirebaseFromCache() async {
  if (Firebase.apps.isNotEmpty) return;
  final config = await _cachedPushConfig();
  if (config == null || config['enabled'] != true) return;
  final apiKey = config['android_api_key']?.toString() ?? '';
  final appId = config['android_app_id']?.toString() ?? '';
  final senderId = config['messaging_sender_id']?.toString() ?? '';
  final projectId = config['project_id']?.toString() ?? '';
  if ([apiKey, appId, senderId, projectId].any((value) => value.isEmpty))
    return;

  await Firebase.initializeApp(
    options: FirebaseOptions(
      apiKey: apiKey,
      appId: appId,
      messagingSenderId: senderId,
      projectId: projectId,
    ),
  );
}

int _notificationId(Map<String, dynamic> data) {
  final value = data['notification_id']?.toString() ??
      '${data['type']}_${data['sale_id'] ?? data['kitchen_ticket_id'] ?? ''}';
  var hash = 0;
  for (final unit in value.codeUnits) {
    hash = ((hash * 31) + unit) & 0x7fffffff;
  }
  return hash;
}

AndroidNotificationChannel _channelFor(Map<String, dynamic> data) {
  final isOrder = data['type'] == 'delayed_order_alarm';
  final preset =
      (data[isOrder ? 'order_sound' : 'invoice_sound'] ?? 'alarm').toString();
  return AndroidNotificationChannel(
    (data[isOrder ? 'order_channel_id' : 'invoice_channel_id'] ??
            (isOrder ? 'delayed_orders_alarm' : 'due_invoice_reminders'))
        .toString(),
    (data[isOrder ? 'order_channel_name' : 'invoice_channel_name'] ??
            (isOrder ? 'Delayed order alarms' : 'Due invoice reminders'))
        .toString(),
    description: isOrder
        ? 'Persistent alarms for kitchen orders that need attention'
        : 'Scheduled reminders for unpaid invoices',
    importance: Importance.max,
    playSound: true,
    sound: _systemSound(preset),
    enableVibration: true,
    audioAttributesUsage: AudioAttributesUsage.alarm,
  );
}

AndroidNotificationSound _systemSound(String preset) {
  final uri = switch (preset) {
    'notification' => 'content://settings/system/notification_sound',
    'ringtone' => 'content://settings/system/ringtone',
    _ => 'content://settings/system/alarm_alert',
  };
  return UriAndroidNotificationSound(uri);
}

Future<void> _showLocalMessage(Map<String, dynamic> data) async {
  final plugin = FlutterLocalNotificationsPlugin();
  await plugin.initialize(
    settings: const InitializationSettings(
        android: AndroidInitializationSettings('@mipmap/ic_launcher')),
    onDidReceiveBackgroundNotificationResponse:
        localNotificationBackgroundResponse,
  );

  final id = _notificationId(data);
  if (data['action'] == 'clear') {
    await plugin.cancel(id: id);
    return;
  }

  final channel = _channelFor(data);
  await plugin
      .resolvePlatformSpecificImplementation<
          AndroidFlutterLocalNotificationsPlugin>()
      ?.createNotificationChannel(channel);
  final isOrder = data['type'] == 'delayed_order_alarm';
  final flags =
      isOrder ? Int32List.fromList(const [4]) : null; // Android FLAG_INSISTENT

  await plugin.show(
    id: id,
    title: data['title']?.toString() ??
        (isOrder ? 'Kitchen order needs attention' : 'Invoice payment is due'),
    body: data['body']?.toString() ?? '',
    notificationDetails: NotificationDetails(
      android: AndroidNotificationDetails(
        channel.id,
        channel.name,
        channelDescription: channel.description,
        importance: Importance.max,
        priority: Priority.high,
        category: AndroidNotificationCategory.alarm,
        audioAttributesUsage: AudioAttributesUsage.alarm,
        playSound: true,
        sound: channel.sound,
        fullScreenIntent: isOrder,
        ongoing: isOrder,
        autoCancel: !isOrder,
        additionalFlags: flags,
        actions: isOrder
            ? const [
                AndroidNotificationAction(_dismissAction, 'Dismiss',
                    cancelNotification: true),
                AndroidNotificationAction('open_alarm', 'Open POS',
                    showsUserInterface: true),
              ]
            : const [
                AndroidNotificationAction('open_invoice', 'Open invoice',
                    showsUserInterface: true)
              ],
      ),
    ),
    payload: jsonEncode(data),
  );
}

class PushNotificationService {
  PushNotificationService._();

  static final PushNotificationService instance = PushNotificationService._();

  final FlutterLocalNotificationsPlugin _local =
      FlutterLocalNotificationsPlugin();
  ApiClient? _apiClient;
  AuthProvider? _authProvider;
  String? _token;
  bool _ready = false;

  Future<void> initialize(
      {required ApiClient apiClient,
      required AuthProvider authProvider}) async {
    if (!Platform.isAndroid || _ready) return;
    _apiClient = apiClient;
    _authProvider = authProvider;
    FirebaseMessaging.onBackgroundMessage(firebasePushBackgroundHandler);

    await _local.initialize(
      settings: const InitializationSettings(
          android: AndroidInitializationSettings('@mipmap/ic_launcher')),
      onDidReceiveNotificationResponse: _onLocalResponse,
      onDidReceiveBackgroundNotificationResponse:
          localNotificationBackgroundResponse,
    );

    try {
      final response = await apiClient.get(ApiEndpoints.pushConfig);
      final config =
          Map<String, dynamic>.from(response['push'] as Map? ?? const {});
      final preferences = await SharedPreferences.getInstance();
      await preferences.setString(_pushConfigCacheKey, jsonEncode(config));
      if (config['enabled'] != true) return;

      if (Firebase.apps.isNotEmpty &&
          Firebase.app().options.projectId !=
              config['project_id']?.toString()) {
        await Firebase.app().delete();
      }

      await _initializeFirebaseFromCache();
      if (Firebase.apps.isEmpty) return;

      await _createConfiguredChannels(config);
      final androidNotifications = _local.resolvePlatformSpecificImplementation<
          AndroidFlutterLocalNotificationsPlugin>();
      await androidNotifications?.requestNotificationsPermission();
      await androidNotifications?.requestFullScreenIntentPermission();
      await FirebaseMessaging.instance.requestPermission(
        alert: true,
        badge: true,
        sound: true,
        criticalAlert: true,
      );
      FirebaseMessaging.onMessage
          .listen((message) => _handleIncoming(message.data));
      FirebaseMessaging.onMessageOpenedApp
          .listen((message) => _openPayload(message.data));
      final initial = await FirebaseMessaging.instance.getInitialMessage();
      if (initial != null)
        Future<void>.delayed(const Duration(milliseconds: 800),
            () => _openPayload(initial.data));

      _token = await FirebaseMessaging.instance.getToken();
      FirebaseMessaging.instance.onTokenRefresh.listen((token) {
        _token = token;
        _registerIfAuthenticated();
      });
      authProvider.addListener(_registerIfAuthenticated);
      authProvider.onBeforeLogout = unregister;
      _ready = true;
      await _registerIfAuthenticated();
    } catch (error) {
      debugPrint('PushNotificationService initialization failed: $error');
    }
  }

  Future<void> _createConfiguredChannels(Map<String, dynamic> config) async {
    final android = _local.resolvePlatformSpecificImplementation<
        AndroidFlutterLocalNotificationsPlugin>();
    if (android == null) return;
    for (final type in const ['delayed_order_alarm', 'due_invoice_reminder']) {
      final data = <String, dynamic>{'type': type};
      final order = Map<String, dynamic>.from(
          config['order_channel'] as Map? ?? const {});
      final invoice = Map<String, dynamic>.from(
          config['invoice_channel'] as Map? ?? const {});
      data['order_channel_id'] = order['id'];
      data['order_channel_name'] = order['name'];
      data['order_sound'] = order['sound'];
      data['invoice_channel_id'] = invoice['id'];
      data['invoice_channel_name'] = invoice['name'];
      data['invoice_sound'] = invoice['sound'];
      await android.createNotificationChannel(_channelFor(data));
    }
  }

  Future<void> _handleIncoming(Map<String, dynamic> data) async {
    await _showLocalMessage(data);
    if (data['action'] == 'clear') {
      await _local.cancel(id: _notificationId(data));
      appMessengerKey.currentState?.hideCurrentMaterialBanner();
      return;
    }

    final isOrder = data['type'] == 'delayed_order_alarm';
    appMessengerKey.currentState
      ?..hideCurrentMaterialBanner()
      ..showMaterialBanner(MaterialBanner(
        leading: Icon(
            isOrder ? Icons.warning_amber_rounded : Icons.notifications_active,
            color: isOrder ? Colors.red : Colors.indigo),
        content: Text(
            '${data['title'] ?? (isOrder ? 'Kitchen order needs attention' : 'Invoice payment is due')}\n${data['body'] ?? ''}'),
        actions: [
          TextButton(
              onPressed: () {
                appMessengerKey.currentState?.hideCurrentMaterialBanner();
                _openPayload(data);
              },
              child: const Text('OPEN')),
          TextButton(
              onPressed: () async {
                appMessengerKey.currentState?.hideCurrentMaterialBanner();
                await _local.cancel(id: _notificationId(data));
                final ticketId = data['kitchen_ticket_id']?.toString();
                if (isOrder && ticketId != null) {
                  try {
                    await _apiClient?.post(
                        ApiEndpoints.restaurantKotDismissAlarm(ticketId));
                  } catch (_) {}
                }
              },
              child: const Text('DISMISS')),
        ],
      ));
  }

  Future<void> _registerIfAuthenticated() async {
    final token = _token;
    if (token == null || _authProvider?.status != AuthStatus.authenticated)
      return;
    try {
      await _apiClient?.post(ApiEndpoints.pushDevices, data: {
        'token': token,
        'platform': 'android',
        'device_name': Platform.localHostname,
      });
    } catch (error) {
      debugPrint('Push device registration failed: $error');
    }
  }

  Future<void> unregister() async {
    final token = _token;
    if (token == null) return;
    try {
      await _apiClient
          ?.delete(ApiEndpoints.pushDevices, data: {'token': token});
    } catch (_) {}
  }

  void _onLocalResponse(NotificationResponse response) {
    if (response.actionId == _dismissAction) {
      _local.cancel(id: response.id ?? 0);
      return;
    }
    final payload = response.payload;
    if (payload == null || payload.isEmpty) return;
    final decoded = jsonDecode(payload);
    if (decoded is Map) _openPayload(Map<String, dynamic>.from(decoded));
  }

  Future<void> _openPayload(Map<String, dynamic> data) async {
    if (_authProvider?.status != AuthStatus.authenticated) return;
    final navigator = appNavigatorKey.currentState;
    if (navigator == null) return;

    if (data['type'] == 'delayed_order_alarm') {
      await navigator
          .push(MaterialPageRoute(builder: (_) => const RestaurantKdsScreen()));
      return;
    }

    if (data['type'] == 'due_invoice_reminder') {
      final saleId = data['sale_id']?.toString();
      if (saleId == null || saleId.isEmpty) {
        await navigator.push(
            MaterialPageRoute(builder: (_) => const DueReceivablesScreen()));
        return;
      }
      try {
        final sale = await SalesRepository(_apiClient!).fetchSale(saleId);
        final symbol = _authProvider?.company?.currencySymbol ?? '\$';
        await navigator.push(MaterialPageRoute(
            builder: (_) => SaleDetailScreen(
                sale: sale, formatter: CurrencyFormatter(symbol))));
      } catch (_) {
        await navigator.push(
            MaterialPageRoute(builder: (_) => const DueReceivablesScreen()));
      }
    }
  }
}
