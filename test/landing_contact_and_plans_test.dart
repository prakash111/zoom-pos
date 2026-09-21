import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:zoom_pos_mobile/core/api/api_client.dart';
import 'package:zoom_pos_mobile/core/storage/app_preferences.dart';
import 'package:zoom_pos_mobile/core/config/platform_branding_provider.dart';
import 'package:zoom_pos_mobile/core/storage/secure_storage_service.dart';
import 'package:zoom_pos_mobile/core/models/subscription_model.dart';
import 'package:zoom_pos_mobile/features/landing/models/landing_data.dart';
import 'package:zoom_pos_mobile/features/landing/services/contact_form_service.dart';
import 'package:zoom_pos_mobile/features/landing/widgets/interactive_contact_form.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('Pricing Plan Models & Dynamic Feature Tests', () {
    test('LandingPlanItem correctly parses dynamic limits and extensions', () {
      final json = {
        'id': 'pro_plan',
        'name': 'Professional',
        'price': 49.99,
        'currency': 'USD',
        'billing_period': 'monthly',
        'description': 'For growing businesses',
        'invoice_limit': -1,
        'device_limit': 5,
        'staff_limit': 10,
        'extensions': ['leadmanagement', 'whatsapp_api', 'custom_domain'],
        'features': ['Multi-store Support', 'Advanced Analytics'],
        'is_featured': true,
      };

      final plan = LandingPlanItem.fromJson(json);

      expect(plan.id, 'pro_plan');
      expect(plan.name, 'Professional');
      expect(plan.price, 49.99);
      expect(plan.invoiceLimit, -1);
      expect(plan.deviceLimit, 5);
      expect(plan.staffLimit, 10);
      expect(plan.extensions,
          containsAll(['leadmanagement', 'whatsapp_api', 'custom_domain']));
      expect(plan.features, hasLength(2));
      expect(plan.isFeatured, true);
    });

    test('SubscriptionPlan correctly parses limits from limits map if present',
        () {
      final json = {
        'id': 'starter',
        'name': 'Starter',
        'display_name': 'Starter Plan',
        'price': 19.0,
        'currency': 'USD',
        'billing_period': 'monthly',
        'invoice_limit': 500,
        'device_limit': 2,
        'staff_limit': 3,
        'extensions': ['whatsapp_api'],
        'features': ['Offline POS'],
      };

      final plan = SubscriptionPlan.fromJson(json);

      expect(plan.name, 'Starter');
      expect(plan.invoiceLimit, 500);
      expect(plan.deviceLimit, 2);
      expect(plan.staffLimit, 3);
      expect(plan.extensions, ['whatsapp_api']);
      expect(plan.features, ['Offline POS']);
    });
  });

  group('Contact Form Model & Submission Tests', () {
    test('ContactSubmission correctly formats JSON payload', () {
      const submission = ContactSubmission(
        name: 'Jane Doe',
        email: 'jane@store.com',
        phone: '+1234567890',
        storeType: 'Retail & Supermarket',
        subject: 'Custom Domain Question',
        message: 'Can I connect a custom domain to my storefront?',
      );

      final json = submission.toJson();

      expect(json['name'], 'Jane Doe');
      expect(json['email'], 'jane@store.com');
      expect(json['phone'], '+1234567890');
      expect(json['store_type'], 'Retail & Supermarket');
      expect(json['subject'], 'Custom Domain Question');
      expect(json['message'],
          'Can I connect a custom domain to my storefront?');
    });

    test('ContactSubmission trims whitespace from fields', () {
      const submission = ContactSubmission(
        name: '  John Smith  ',
        email: '  john@example.com  ',
        message: '  Hello  ',
      );

      final json = submission.toJson();

      expect(json['name'], 'John Smith');
      expect(json['email'], 'john@example.com');
      expect(json['message'], 'Hello');
      expect(json.containsKey('phone'), isFalse);
      expect(json.containsKey('store_type'), isFalse);
    });
  });

  group('LandingContact & Head Office / Working Hours Tests', () {
    test('LandingContact correctly parses headOfficeAddress and workingHours from json', () {
      final json = {
        'support_phone': '+1 (800) 555-0199',
        'support_whatsapp': '+1 (800) 555-0199',
        'support_email': 'contact@company.com',
        'head_office_address': '742 Evergreen Terrace, Springfield, OR',
        'working_hours': 'Monday - Saturday (08 am - 08 pm)',
        'settings': {
          'page_title': 'Speak with Specialists',
          'page_subtitle': 'Direct line to our cloud engineers.',
        },
      };

      final contact = LandingContact.fromJson(json);

      expect(contact.supportPhone, '+1 (800) 555-0199');
      expect(contact.supportEmail, 'contact@company.com');
      expect(contact.headOfficeAddress, '742 Evergreen Terrace, Springfield, OR');
      expect(contact.workingHours, 'Monday - Saturday (08 am - 08 pm)');
      expect(contact.pageTitle, 'Speak with Specialists');
      expect(contact.pageSubtitle, 'Direct line to our cloud engineers.');
    });

    test('LandingContact uses fallback values when fields are absent', () {
      final contact = LandingContact.fromJson({});

      expect(contact.headOfficeAddress, 'Metrotech Center, NY 11201');
      expect(contact.workingHours, 'Monday - Friday (07 am - 05 pm)');
      expect(contact.supportPhone, '+918535075196');
      expect(contact.supportEmail, 'support@zoomnearby.com');
    });

    test('LandingData parses contact_info object at root', () {
      final json = {
        'contact': {
          'support_phone': '+123456',
        },
        'contact_info': {
          'head_office_address': '100 Innovation Way, Suite 400',
          'working_hours': '24/7 Enterprise Support',
          'support_phone': '+1 (999) 888-7777',
        },
      };

      final data = LandingData.fromJson(json);

      expect(data.contact.headOfficeAddress, '100 Innovation Way, Suite 400');
      expect(data.contact.workingHours, '24/7 Enterprise Support');
      expect(data.contact.supportPhone, '+1 (999) 888-7777');
    });
  });

  group('PlatformBrandingProvider landingPageEnabled toggle tests', () {
    test('landingPageEnabled defaults to true and reads from SharedPreferences',
        () async {
      SharedPreferences.setMockInitialValues({
        'zoom_pos.platform_landing_enabled': false,
      });

      final provider = PlatformBrandingProvider();
      expect(provider.landingPageEnabled, true);

      await provider.load();
      expect(provider.landingPageEnabled, false);
    });
  });

  group('InteractiveContactForm Widget Tests', () {
    testWidgets('renders all form inputs and submit button',
        (WidgetTester tester) async {
      SharedPreferences.setMockInitialValues({});
      final prefs = AppPreferences();
      final secure = SecureStorageService();
      final apiClient = ApiClient(secureStorage: secure, preferences: prefs);

      const contact = LandingContact(
        pageTitle: 'Contact Our Team',
        pageSubtitle: 'We are here to help',
        supportWhatsapp: '+123456789',
        supportPhone: '+123456789',
        supportEmail: 'support@example.com',
      );

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Provider<ApiClient>.value(
              value: apiClient,
              child: SingleChildScrollView(
                child: InteractiveContactForm(
                  contact: contact,
                  isDark: true,
                  surfaceCard: const Color(0xFF131D2D),
                  borderColor: const Color(0xFF1E293B),
                  textPrimary: const Color(0xFFF8FAFC),
                  textSecondary: const Color(0xFF94A3B8),
                  primaryColor: const Color(0xFF10B981),
                  accentColor: const Color(0xFF38BDF8),
                ),
              ),
            ),
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(find.text('Get in Touch with Our Team'), findsOneWidget);
      expect(find.text('Full Name *'), findsOneWidget);
      expect(find.text('Business Email *'), findsOneWidget);
      expect(find.text('Phone Number'), findsOneWidget);
      expect(find.text('Store / Business Type'), findsOneWidget);
      expect(find.text('Subject'), findsOneWidget);
      expect(find.text('Message *'), findsOneWidget);
      expect(find.text('Send Inquiry'), findsOneWidget);
    });

    testWidgets('triggers client-side validation when required fields empty',
        (WidgetTester tester) async {
      SharedPreferences.setMockInitialValues({});
      final prefs = AppPreferences();
      final secure = SecureStorageService();
      final apiClient = ApiClient(secureStorage: secure, preferences: prefs);

      const contact = LandingContact(
        pageTitle: 'Contact Our Team',
        pageSubtitle: 'We are here to help',
        supportWhatsapp: '+123456789',
        supportPhone: '+123456789',
        supportEmail: 'support@example.com',
      );

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Provider<ApiClient>.value(
              value: apiClient,
              child: SingleChildScrollView(
                child: InteractiveContactForm(
                  contact: contact,
                  isDark: false,
                  surfaceCard: const Color(0xFFFFFFFF),
                  borderColor: const Color(0xFFE2E8F0),
                  textPrimary: const Color(0xFF0F172A),
                  textSecondary: const Color(0xFF64748B),
                  primaryColor: const Color(0xFF10B981),
                  accentColor: const Color(0xFF38BDF8),
                ),
              ),
            ),
          ),
        ),
      );

      await tester.pumpAndSettle();

      // Tap submit button without entering anything
      await tester.tap(find.text('Send Inquiry'));
      await tester.pumpAndSettle();

      expect(find.text('Please enter your name'), findsOneWidget);
      expect(find.text('Please enter your email'), findsOneWidget);
      expect(find.text('Please enter your message'), findsOneWidget);
    });
  });
}
