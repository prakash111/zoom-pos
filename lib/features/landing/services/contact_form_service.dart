import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';

class ContactSubmission {
  final String name;
  final String email;
  final String? phone;
  final String? storeType;
  final String? subject;
  final String message;

  const ContactSubmission({
    required this.name,
    required this.email,
    this.phone,
    this.storeType,
    this.subject,
    required this.message,
  });

  Map<String, dynamic> toJson() => {
    'name': name.trim(),
    'email': email.trim(),
    if (phone != null && phone!.trim().isNotEmpty) 'phone': phone!.trim(),
    if (storeType != null && storeType!.trim().isNotEmpty) 'store_type': storeType!.trim(),
    if (subject != null && subject!.trim().isNotEmpty) 'subject': subject!.trim(),
    'message': message.trim(),
  };
}

class ContactFormResult {
  final bool success;
  final String message;
  final Map<String, dynamic>? errors;

  const ContactFormResult({
    required this.success,
    required this.message,
    this.errors,
  });
}

class ContactFormService {
  final ApiClient _apiClient;

  ContactFormService(this._apiClient);

  Future<ContactFormResult> submitInquiry(ContactSubmission submission) async {
    try {
      final response = await _apiClient.post(
        '/public/contact-us',
        data: submission.toJson(),
      );
      final success = response['success'] == true;
      final msg = response['message']?.toString() ??
          (success
              ? 'Thank you for reaching out! We will contact you shortly.'
              : 'Failed to submit inquiry.');
      return ContactFormResult(
        success: success,
        message: msg,
        errors: response['errors'] is Map
            ? Map<String, dynamic>.from(response['errors'] as Map)
            : null,
      );
    } on ApiException catch (e) {
      return ContactFormResult(
        success: false,
        message: e.message,
      );
    } catch (e) {
      return ContactFormResult(
        success: false,
        message: 'Unable to send your inquiry at this moment. Please try again.',
      );
    }
  }
}
