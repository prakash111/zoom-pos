import 'package:flutter_test/flutter_test.dart';
import 'package:zoom_pos_mobile/core/api/api_exception.dart';
import 'package:zoom_pos_mobile/core/models/company_model.dart';
import 'package:zoom_pos_mobile/core/models/product_model.dart';
import 'package:zoom_pos_mobile/core/sdui/sdui_component_registry.dart';

void main() {
  group('Storefront & Tenant Settings Feature Tests', () {
    test('CompanyModel correctly parses licensed modules and store feature flags', () {
      final company = CompanyModel.fromJson({
        'id': 42,
        'name': 'Subodh Store',
        'slug': 'subodh-store-novc',
        'licensed_modules': ['retail', 'restaurant', 'pos'],
        'require_customer_verification': true,
        'enable_order_notifications': true,
        'enable_product_reviews': true,
      });

      expect(company.id, '42');
      expect(company.name, 'Subodh Store');
      expect(company.slug, 'subodh-store-novc');
      expect(company.licensedModules, contains('retail'));
      expect(company.licensedModules, contains('restaurant'));
      expect(company.isModuleEnabled('retail'), isTrue);
      expect(company.isModuleEnabled('pharmacy'), isFalse);
      expect(company.requireCustomerVerification, isTrue);
      expect(company.enableOrderNotifications, isTrue);
      expect(company.enableProductReviews, isTrue);
    });

    test('CompanyModel respects disabled notifications and reviews flags', () {
      final company = CompanyModel.fromJson({
        'id': 43,
        'name': 'Quiet Store',
        'licensed_modules': ['retail'],
        'require_customer_verification': false,
        'enable_order_notifications': false,
        'enable_product_reviews': false,
      });

      expect(company.requireCustomerVerification, isFalse);
      expect(company.enableOrderNotifications, isFalse);
      expect(company.enableProductReviews, isFalse);
    });

    test('ProductModel parses dynamic average rating and reviews count', () {
      final product = ProductModel.fromJson({
        'id': 101,
        'name': 'Wireless Ergonomic Keyboard',
        'barcode': 'KBD-001',
        'sku': 'TECH-KBD',
        'sale_price': 89.99,
        'cost_price': 45.00,
        'current_stock': 25,
        'minimum_stock': 5,
        'unit': 'pcs',
        'category_name': 'Electronics',
        'average_rating': 4.8,
        'reviews_count': 37,
      });

      expect(product.id, '101');
      expect(product.name, 'Wireless Ergonomic Keyboard');
      expect(product.averageRating, 4.8);
      expect(product.reviewsCount, 37);
    });

    test('ProductModel defaults average rating and reviews count when not provided', () {
      final product = ProductModel.fromJson({
        'id': 102,
        'name': 'Generic Item',
        'barcode': 'GEN-002',
        'sku': 'GEN-002',
        'sale_price': 10.00,
        'cost_price': 5.00,
        'current_stock': 10,
        'minimum_stock': 2,
        'unit': 'pcs',
      });

      expect(product.averageRating, 5.0);
      expect(product.reviewsCount, 0);
    });

    test('ApiException preserves responseData for verification_required payload', () {
      final exception = ApiException(
        'Account verification required before placing your order.',
        statusCode: 200,
        responseData: {
          'success': false,
          'verification_required': true,
          'message': 'A 6-digit verification code has been dispatched.',
          'channels': ['email', 'sms'],
        },
      );

      expect(exception.message, contains('Account verification required'));
      expect(exception.responseData, isNotNull);
      expect(exception.responseData!['verification_required'], isTrue);
      expect(exception.responseData!['channels'], equals(['email', 'sms']));
    });

    test('SduiComponentRegistry resolves settings_notifications, settings_integrations, and storefront', () {
      final notifScreen = SduiComponentRegistry.resolveRoute('settings_notifications');
      expect(notifScreen, isNotNull);

      final intScreen = SduiComponentRegistry.resolveRoute('settings_integrations');
      expect(intScreen, isNotNull);

      final storefrontScreen = SduiComponentRegistry.resolveRoute('storefront');
      expect(storefrontScreen, isNotNull);
    });
  });
}
