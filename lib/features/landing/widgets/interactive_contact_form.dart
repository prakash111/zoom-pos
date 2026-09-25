import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/config/bootstrap_cache.dart';
import '../../../core/config/countries.dart';
import '../../../widgets/inputs/phone_number_field.dart';
import '../models/landing_data.dart';
import '../models/landing_translations.dart';
import 'package:zoom_pos_mobile/core/config/locale_provider.dart';
import '../services/contact_form_service.dart';

class InteractiveContactForm extends StatefulWidget {
  const InteractiveContactForm({
    super.key,
    required this.contact,
    required this.isDark,
    required this.surfaceCard,
    required this.borderColor,
    required this.textPrimary,
    required this.textSecondary,
    required this.primaryColor,
    required this.accentColor,
  });

  final LandingContact contact;
  final bool isDark;
  final Color surfaceCard;
  final Color borderColor;
  final Color textPrimary;
  final Color textSecondary;
  final Color primaryColor;
  final Color accentColor;

  @override
  State<InteractiveContactForm> createState() => _InteractiveContactFormState();
}

class _InteractiveContactFormState extends State<InteractiveContactForm> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _emailController = TextEditingController();
  final _phoneController = TextEditingController();
  final _subjectController = TextEditingController();
  final _messageController = TextEditingController();

  String? _selectedStoreType;
  bool _isSubmitting = false;
  bool _submittedSuccess = false;

  final List<String> _storeTypes = const [
    'Retail & Supermarket',
    'Restaurant / Cafe / QSR',
    'Salon & Spa',
    'Pharmacy',
    'Service Business',
    'Other',
  ];

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    _phoneController.dispose();
    _subjectController.dispose();
    _messageController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_isSubmitting) return;
    if (!(_formKey.currentState?.validate() ?? false)) return;

    setState(() {
      _isSubmitting = true;
    });

    final defaultDial = BootstrapCache.instance.defaultDialCode;
    final normalizedPhone = normalizePhoneNumber(
      _phoneController.text,
      defaultDialCode: defaultDial,
    );

    final service = ContactFormService(context.read<ApiClient>());
    final submission = ContactSubmission(
      name: _nameController.text,
      email: _emailController.text,
      phone: normalizedPhone.isNotEmpty ? normalizedPhone : _phoneController.text,
      storeType: _selectedStoreType,
      subject: _subjectController.text,
      message: _messageController.text,
    );

    final result = await service.submitInquiry(submission);

    if (!mounted) return;

    setState(() {
      _isSubmitting = false;
    });

    if (result.success) {
      setState(() {
        _submittedSuccess = true;
      });
      _nameController.clear();
      _emailController.clear();
      _phoneController.clear();
      _subjectController.clear();
      _messageController.clear();
      _selectedStoreType = null;
      _formKey.currentState?.reset();

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          backgroundColor: const Color(0xFF10B981),
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
          content: Row(
            children: [
              const Icon(Icons.check_circle, color: Colors.white, size: 20),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  result.message,
                  style: const TextStyle(
                      color: Colors.white, fontWeight: FontWeight.w600),
                ),
              ),
            ],
          ),
          duration: const Duration(seconds: 4),
        ),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          backgroundColor: const Color(0xFFEF4444),
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
          content: Row(
            children: [
              const Icon(Icons.error_outline, color: Colors.white, size: 20),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  result.message,
                  style: const TextStyle(
                      color: Colors.white, fontWeight: FontWeight.w600),
                ),
              ),
            ],
          ),
          duration: const Duration(seconds: 4),
        ),
      );
    }
  }

  InputDecoration _inputDecoration({
    required String label,
    required String hint,
    IconData? prefixIcon,
  }) {
    final inputBg = widget.isDark
        ? const Color(0xFF0F172A)
        : const Color(0xFFF1F5F9);

    return InputDecoration(
      labelText: label,
      hintText: hint,
      labelStyle: TextStyle(color: widget.textSecondary, fontSize: 14),
      hintStyle: TextStyle(
          color: widget.textSecondary.withValues(alpha: 0.6), fontSize: 13),
      prefixIcon: prefixIcon != null
          ? Icon(prefixIcon, color: widget.textSecondary, size: 20)
          : null,
      filled: true,
      fillColor: inputBg,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: BorderSide(color: widget.borderColor),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: BorderSide(color: widget.borderColor),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: BorderSide(color: widget.primaryColor, width: 1.8),
      ),
      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: const BorderSide(color: Color(0xFFEF4444)),
      ),
      focusedErrorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: const BorderSide(color: Color(0xFFEF4444), width: 1.8),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final localeProvider = Provider.of<LocaleProvider>(context);
    final localeCode = localeProvider.locale.languageCode;
    String _t(String text) => LandingTranslations.tr(text, localeCode);

    return Container(
      padding: const EdgeInsets.all(32),
      decoration: BoxDecoration(
        color: widget.surfaceCard,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: widget.borderColor),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: widget.isDark ? 0.35 : 0.06),
            blurRadius: 24,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: _submittedSuccess
          ? Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: widget.primaryColor.withValues(alpha: 0.15),
                    shape: BoxShape.circle,
                  ),
                  child: Icon(Icons.check_circle_rounded,
                      size: 48, color: widget.primaryColor),
                ),
                const SizedBox(height: 20),
                Text(
                  _t('Inquiry Received!'),
                  style: TextStyle(
                    color: widget.textPrimary,
                    fontWeight: FontWeight.w800,
                    fontSize: 22,
                  ),
                ),
                const SizedBox(height: 10),
                ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 480),
                  child: Text(
                    _t('Thank you for reaching out. Our business solutions team has received your message and will contact you via email or phone shortly.'),
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: widget.textSecondary,
                      fontSize: 14,
                      height: 1.5,
                    ),
                  ),
                ),
                const SizedBox(height: 24),
                OutlinedButton.icon(
                  style: OutlinedButton.styleFrom(
                    foregroundColor: widget.textPrimary,
                    side: BorderSide(color: widget.borderColor),
                    padding: const EdgeInsets.symmetric(
                        horizontal: 20, vertical: 12),
                    shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(10)),
                  ),
                  onPressed: () {
                    setState(() {
                      _submittedSuccess = false;
                    });
                  },
                  icon: const Icon(Icons.refresh, size: 18),
                  label: Text(_t('Send Another Message')),
                ),
              ],
            )
          : Form(
              key: _formKey,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    _t('Get in Touch with Our Team'),
                    style: TextStyle(
                      color: widget.textPrimary,
                      fontWeight: FontWeight.w800,
                      fontSize: 20,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    _t('Have questions about onboarding, hardware compatibility, or pricing? Fill out the form below.'),
                    style: TextStyle(
                      color: widget.textSecondary,
                      fontSize: 13,
                    ),
                  ),
                  const SizedBox(height: 24),
                  LayoutBuilder(
                    builder: (context, constraints) {
                      final isWide = constraints.maxWidth >= 600;
                      if (isWide) {
                        return Column(
                          children: [
                            Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Expanded(
                                  child: TextFormField(
                                    controller: _nameController,
                                    style: TextStyle(color: widget.textPrimary),
                                    decoration: _inputDecoration(
                                      label: _t('Full Name *'),
                                      hint: 'John Doe',
                                      prefixIcon: Icons.person_outline,
                                    ),
                                    validator: (val) {
                                      if (val == null || val.trim().isEmpty) {
                                        return _t('Please enter your name');
                                      }
                                      return null;
                                    },
                                  ),
                                ),
                                const SizedBox(width: 16),
                                Expanded(
                                  child: TextFormField(
                                    controller: _emailController,
                                    keyboardType: TextInputType.emailAddress,
                                    style: TextStyle(color: widget.textPrimary),
                                    decoration: _inputDecoration(
                                      label: _t('Business Email *'),
                                      hint: 'you@store.com',
                                      prefixIcon: Icons.email_outlined,
                                    ),
                                    validator: (val) {
                                      if (val == null || val.trim().isEmpty) {
                                        return _t('Please enter your email');
                                      }
                                      if (!RegExp(r'^[^@]+@[^@]+\.[^@]+')
                                          .hasMatch(val.trim())) {
                                        return _t('Please enter a valid email address');
                                      }
                                      return null;
                                    },
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 16),
                            Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Expanded(
                                  child: PhoneNumberField(
                                    controller: _phoneController,
                                    label: _t('Phone Number'),
                                    fillColor: widget.isDark
                                        ? const Color(0xFF0F172A)
                                        : const Color(0xFFF1F5F9),
                                    borderColor: widget.borderColor,
                                    focusedBorderColor: widget.primaryColor,
                                    textStyle: TextStyle(color: widget.textPrimary, fontSize: 14),
                                    borderRadius: BorderRadius.circular(10),
                                    initialDialCode: BootstrapCache.instance.defaultDialCode,
                                    hint: BootstrapCache.instance.defaultDialCode == '+91'
                                        ? '98765 43210'
                                        : '(555) 000-0000',
                                  ),
                                ),
                                const SizedBox(width: 16),
                                Expanded(
                                  child: DropdownButtonFormField<String>(
                                    isExpanded: true,
                                    initialValue: _selectedStoreType,
                                    dropdownColor: widget.surfaceCard,
                                    style: TextStyle(color: widget.textPrimary),
                                    decoration: _inputDecoration(
                                      label: _t('Store / Business Type'),
                                      hint: _t('Select business type…'),
                                      prefixIcon: Icons.storefront_outlined,
                                    ),
                                    items: _storeTypes.map((type) {
                                      return DropdownMenuItem<String>(
                                        value: type,
                                        child: Text(_t(type)),
                                      );
                                    }).toList(),
                                    onChanged: (val) {
                                      setState(() {
                                        _selectedStoreType = val;
                                      });
                                    },
                                  ),
                                ),
                              ],
                            ),
                          ],
                        );
                      }

                      return Column(
                        children: [
                          TextFormField(
                            controller: _nameController,
                            style: TextStyle(color: widget.textPrimary),
                            decoration: _inputDecoration(
                              label: _t('Full Name *'),
                              hint: 'John Doe',
                              prefixIcon: Icons.person_outline,
                            ),
                            validator: (val) {
                              if (val == null || val.trim().isEmpty) {
                                return _t('Please enter your name');
                              }
                              return null;
                            },
                          ),
                          const SizedBox(height: 16),
                          TextFormField(
                            controller: _emailController,
                            keyboardType: TextInputType.emailAddress,
                            style: TextStyle(color: widget.textPrimary),
                            decoration: _inputDecoration(
                              label: _t('Business Email *'),
                              hint: 'you@store.com',
                              prefixIcon: Icons.email_outlined,
                            ),
                            validator: (val) {
                              if (val == null || val.trim().isEmpty) {
                                return _t('Please enter your email');
                              }
                              if (!RegExp(r'^[^@]+@[^@]+\.[^@]+')
                                  .hasMatch(val.trim())) {
                                return _t('Please enter a valid email address');
                              }
                              return null;
                            },
                          ),
                          const SizedBox(height: 16),
                          PhoneNumberField(
                            controller: _phoneController,
                            label: _t('Phone Number'),
                            fillColor: widget.isDark
                                ? const Color(0xFF0F172A)
                                : const Color(0xFFF1F5F9),
                            borderColor: widget.borderColor,
                            focusedBorderColor: widget.primaryColor,
                            textStyle: TextStyle(color: widget.textPrimary, fontSize: 14),
                            borderRadius: BorderRadius.circular(10),
                            initialDialCode: BootstrapCache.instance.defaultDialCode,
                            hint: BootstrapCache.instance.defaultDialCode == '+91'
                                ? '98765 43210'
                                : '(555) 000-0000',
                          ),
                          const SizedBox(height: 16),
                          DropdownButtonFormField<String>(
                            isExpanded: true,
                            initialValue: _selectedStoreType,
                            dropdownColor: widget.surfaceCard,
                            style: TextStyle(color: widget.textPrimary),
                            decoration: _inputDecoration(
                              label: _t('Store / Business Type'),
                              hint: _t('Select business type…'),
                              prefixIcon: Icons.storefront_outlined,
                            ),
                            items: _storeTypes.map((type) {
                              return DropdownMenuItem<String>(
                                value: type,
                                child: Text(_t(type)),
                              );
                            }).toList(),
                            onChanged: (val) {
                              setState(() {
                                _selectedStoreType = val;
                              });
                            },
                          ),
                        ],
                      );
                    },
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: _subjectController,
                    style: TextStyle(color: widget.textPrimary),
                    decoration: _inputDecoration(
                      label: _t('Subject'),
                      hint: _t('Questions before signing up'),
                      prefixIcon: Icons.help_outline_rounded,
                    ),
                  ),
                  const SizedBox(height: 16),
                  TextFormField(
                    controller: _messageController,
                    maxLines: 4,
                    style: TextStyle(color: widget.textPrimary),
                    decoration: _inputDecoration(
                      label: _t('Message *'),
                      hint: _t("Tell us a bit about your business requirements…"),
                    ),
                    validator: (val) {
                      if (val == null || val.trim().isEmpty) {
                        return _t('Please enter your message');
                      }
                      if (val.trim().length < 5) {
                        return _t('Message must be at least 5 characters long');
                      }
                      return null;
                    },
                  ),
                  const SizedBox(height: 24),
                  SizedBox(
                    width: double.infinity,
                    height: 50,
                    child: FilledButton(
                      style: FilledButton.styleFrom(
                        backgroundColor: widget.primaryColor,
                        foregroundColor: Colors.white,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(10),
                        ),
                        elevation: 2,
                      ),
                      onPressed: _isSubmitting ? null : _submit,
                      child: _isSubmitting
                          ? const SizedBox(
                              width: 22,
                              height: 22,
                              child: CircularProgressIndicator(
                                strokeWidth: 2.2,
                                color: Colors.white,
                              ),
                            )
                          : Row(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                const Icon(Icons.send_rounded, size: 18),
                                const SizedBox(width: 8),
                                Text(
                                  _t('Send Inquiry'),
                                  style: const TextStyle(
                                    fontWeight: FontWeight.bold,
                                    fontSize: 15,
                                  ),
                                ),
                              ],
                            ),
                    ),
                  ),
                ],
              ),
            ),
    );
  }
}
