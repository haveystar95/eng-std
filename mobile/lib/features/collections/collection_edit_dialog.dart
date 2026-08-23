import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/models.dart';
import '../../data/providers.dart';

/// Create (existing == null) or rename a collection — a paper bottom sheet (rule 08). Just a
/// «Название» field: backend2 has no emoji and the paper design covers collections with photos, so
/// the old emoji picker is gone. Manual create («Собрать вручную» from the create screen) and rename
/// (from the ⋯ menu) both land here.
Future<void> showCollectionEditor(BuildContext context, WidgetRef ref, {WordCollection? existing}) {
  return showAppBottomSheet<void>(
    context: context,
    builder: (_) => _CollectionSheet(existing: existing),
  );
}

class _CollectionSheet extends ConsumerStatefulWidget {
  const _CollectionSheet({this.existing});
  final WordCollection? existing;

  @override
  ConsumerState<_CollectionSheet> createState() => _CollectionSheetState();
}

class _CollectionSheetState extends ConsumerState<_CollectionSheet> {
  late final _title = TextEditingController(text: widget.existing?.title ?? '');
  bool _submitting = false;

  bool get _isEdit => widget.existing != null;

  @override
  void dispose() {
    _title.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final title = _title.text.trim();
    if (title.isEmpty) {
      AppHaptics.warning();
      return;
    }
    setState(() => _submitting = true);
    final navigator = Navigator.of(context);
    final api = ref.read(apiClientProvider);
    final profile = ref.read(authControllerProvider).value?.profile;
    try {
      if (_isEdit) {
        await api.updateCollection(widget.existing!.id, title: title);
      } else {
        await api.createCollection(
          title: title,
          sourceLang: profile?.nativeLanguage,
          targetLang: profile?.targetLanguage,
        );
      }
      // Pull the new/updated collection into the local mirror; the read streams update on land.
      ref.read(syncServiceProvider).sync();
      if (mounted) navigator.pop();
    } catch (_) {
      AppHaptics.warning();
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          _isEdit ? l.collectionSheetEditTitle : l.collectionSheetCreateTitle,
          style: AppText.sheetButton.copyWith(fontSize: 19),
        ),
        const SizedBox(height: 18),
        Text(
          l.collectionNameLabel.toUpperCase(),
          style: AppText.sectionLabel.copyWith(fontSize: 11, color: AppColors.tertiary),
        ),
        const SizedBox(height: 7),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          decoration: BoxDecoration(
            color: AppColors.field,
            borderRadius: BorderRadius.circular(AppRadii.field),
            border: Border.all(color: AppColors.hairline),
          ),
          child: TextField(
            controller: _title,
            autofocus: true,
            cursorColor: AppColors.ink,
            textCapitalization: TextCapitalization.sentences,
            style: const TextStyle(
              fontFamily: AppFonts.literata,
              fontWeight: FontWeight.w500,
              fontSize: 17,
              color: AppColors.ink,
            ),
            decoration: InputDecoration.collapsed(
              hintText: l.collectionNameHint,
              hintStyle: const TextStyle(
                fontFamily: AppFonts.literata,
                fontWeight: FontWeight.w500,
                fontSize: 17,
                color: AppColors.tertiary,
              ),
            ),
            onSubmitted: (_) => _submit(),
          ),
        ),
        const SizedBox(height: 18),
        PrimaryButton(
          label: _isEdit ? l.wordSheetSaveButton : l.collectionSheetCreateButton,
          onPressed: _submitting ? null : _submit,
        ),
      ],
    );
  }
}
