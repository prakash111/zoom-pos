import 'package:flutter/material.dart';
import '../api/api_client.dart';

/// Context provided down the widget tree of a dynamic schema page.
/// Enables any input or interactive element to bind values and dispatch actions.
class DynamicSchemaContext extends InheritedWidget {
  const DynamicSchemaContext({
    super.key,
    required this.formValues,
    required this.setFormValue,
    required this.dispatchAction,
    this.apiClient,
    required super.child,
  });

  final Map<String, dynamic> formValues;
  final void Function(String key, dynamic value) setFormValue;
  final Future<void> Function(Map<String, dynamic> action) dispatchAction;
  final ApiClient? apiClient;

  static DynamicSchemaContext? of(BuildContext context) {
    return context.dependOnInheritedWidgetOfExactType<DynamicSchemaContext>();
  }

  @override
  bool updateShouldNotify(DynamicSchemaContext oldWidget) => true;
}
