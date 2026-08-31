import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/api/api_client.dart';
import '../../../core/api/api_exception.dart';
import '../../../core/config/locale_provider.dart';
import '../../../core/models/language_model.dart';
import '../../../core/widgets/error_view.dart';
import '../../../core/widgets/loading_indicator.dart';
import '../languages_repository.dart';

/// Store default language selector — and, since this is the app's single
/// language control point, also sets this app's own display language
/// (LocaleProvider) for whichever locale is picked. The web page's
/// per-phrase translation override editor is intentionally out of scope
/// for mobile.
class LanguagesScreen extends StatefulWidget {
  const LanguagesScreen({super.key});

  @override
  State<LanguagesScreen> createState() => _LanguagesScreenState();
}

class _LanguagesScreenState extends State<LanguagesScreen> {
  late final LanguagesRepository _repository;
  late Future<({String defaultLanguage, List<LanguageModel> languages})> _future;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _repository = LanguagesRepository(context.read<ApiClient>());
    _future = _repository.fetchLanguages();
  }

  void _reload() => setState(() => _future = _repository.fetchLanguages());

  Future<void> _select(String code) async {
    setState(() => _saving = true);
    try {
      await _repository.setDefault(code);
      if (!mounted) return;
      // Also drives this app's own display language — the single control
      // point for both the storefront default and the app's own UI locale.
      await context.read<LocaleProvider>().setLocale(Locale(code));
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Store default language updated.')));
      _reload();
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Languages')),
      body: FutureBuilder<({String defaultLanguage, List<LanguageModel> languages})>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) return const LoadingIndicator();
          if (snapshot.hasError) return ErrorView(message: 'Could not load languages.', onRetry: _reload);

          final defaultLanguage = snapshot.data!.defaultLanguage;
          final languages = snapshot.data!.languages;

          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: languages.length + 1,
            separatorBuilder: (_, __) => const SizedBox(height: 8),
            itemBuilder: (context, index) {
              if (index == 0) {
                return Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: Text(
                    "Sets your online store's default language, and this app's own display "
                    'language. Also sent as the Accept-Language header on every request, so '
                    "the backend's replies match your choice.",
                    style: TextStyle(color: Colors.grey.shade600, fontSize: 12),
                  ),
                );
              }
              final lang = languages[index - 1];
              final selected = lang.code == defaultLanguage;
              return Card(
                color: selected ? Theme.of(context).colorScheme.primaryContainer : null,
                child: ListTile(
                  leading: Text(lang.flag, style: const TextStyle(fontSize: 24)),
                  title: Text(lang.name),
                  subtitle: Text(lang.nativeName),
                  trailing: selected
                      ? const Icon(Icons.check_circle)
                      : _saving
                          ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2))
                          : null,
                  onTap: _saving || selected ? null : () => _select(lang.code),
                ),
              );
            },
          );
        },
      ),
    );
  }
}
