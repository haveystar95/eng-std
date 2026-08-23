import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../data/models.dart';
import '../../data/providers.dart';

/// Add (existing == null) or edit a word inside a collection — a paper bottom
/// sheet (rule 08). Just Термин + optional Перевод; transcription/example/photo
/// are derived by the backend (кадры 5b/5c). Editing = remove old + add new
/// (backend2 has no word-update). [onRequestDelete] powers the edit sheet's
/// «Удалить из коллекции» link (the caller shows the confirm alert).
Future<void> showWordEditor(
  BuildContext context,
  WidgetRef ref, {
  required String collectionId,
  Word? existing,
  VoidCallback? onRequestDelete,
}) {
  return showAppBottomSheet<void>(
    context: context,
    builder: (_) => _WordSheet(
      collectionId: collectionId,
      existing: existing,
      onRequestDelete: onRequestDelete,
    ),
  );
}

class _WordSheet extends ConsumerStatefulWidget {
  const _WordSheet({required this.collectionId, this.existing, this.onRequestDelete});
  final String collectionId;
  final Word? existing;
  final VoidCallback? onRequestDelete;

  @override
  ConsumerState<_WordSheet> createState() => _WordSheetState();
}

class _WordSheetState extends ConsumerState<_WordSheet> {
  late final _term = TextEditingController(text: widget.existing?.term ?? '');
  late final _translation = TextEditingController(text: widget.existing?.translation ?? '');
  bool _submitting = false;

  bool get _isEdit => widget.existing != null;

  @override
  void dispose() {
    _term.dispose();
    _translation.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final term = _term.text.trim();
    if (term.isEmpty) {
      AppHaptics.warning();
      return;
    }
    setState(() => _submitting = true);
    final type = term.contains(' ') ? 'phrase' : 'word';
    final api = ref.read(apiClientProvider);
    try {
      // backend2 has no word-update; editing = remove old + add new.
      if (widget.existing != null) {
        await api.removeWord(widget.collectionId, widget.existing!.termId);
      }
      // B1: send WITHOUT a translation when the field is left empty — the backend enriches the term
      // (перевод «подберём сами»: translation/transcription/example/photo arrive via a later /sync).
      final translation = _translation.text.trim();
      await api.addWord(
        widget.collectionId,
        text: term,
        translation: translation.isEmpty ? null : translation,
        type: type,
      );
      // Pull the new term/item into the local mirror; the read streams update when it lands.
      ref.read(syncServiceProvider).sync();
      if (mounted) Navigator.of(context).pop();
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
          _isEdit ? l.wordSheetEditTitle : l.wordSheetAddTitle,
          style: AppText.sheetButton.copyWith(fontSize: 19),
        ),
        const SizedBox(height: 18),
        _Field(
          label: l.wordFieldTerm,
          controller: _term,
          hint: l.wordTermHint,
          isTerm: true,
          autofocus: !_isEdit,
          trailing: _isEdit && (widget.existing?.transcription?.isNotEmpty ?? false)
              ? Text('/${widget.existing!.transcription}/', style: AppText.transcription)
              : null,
        ),
        const SizedBox(height: 14),
        _Field(
          label: l.wordFieldTranslation,
          controller: _translation,
          hint: l.wordTranslationHintOptional,
          isTerm: false,
        ),
        const SizedBox(height: 12),
        Text(
          _isEdit ? l.wordSheetEditHelper : l.wordSheetAddHelper,
          style: AppText.transcription.copyWith(fontSize: 12.5, color: AppColors.secondary),
        ),
        const SizedBox(height: 18),
        PrimaryButton(
          label: _isEdit ? l.wordSheetSaveButton : l.wordSheetAddButton,
          onPressed: _submitting ? null : _submit,
        ),
        if (_isEdit && widget.onRequestDelete != null) ...[
          const SizedBox(height: 16),
          Center(
            child: InkWell(
              onTap: () {
                Navigator.of(context).pop();
                widget.onRequestDelete!.call();
              },
              borderRadius: BorderRadius.circular(AppRadii.small),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                child: Text(
                  l.wordSheetDeleteLink,
                  style: AppText.verdictLabel.copyWith(color: AppColors.verdictUnknown),
                ),
              ),
            ),
          ),
        ],
      ],
    );
  }
}

/// A labelled sheet field: caps label + white input on hairline (§2б).
class _Field extends StatelessWidget {
  const _Field({
    required this.label,
    required this.controller,
    required this.hint,
    required this.isTerm,
    this.trailing,
    this.autofocus = false,
  });

  final String label;
  final TextEditingController controller;
  final String hint;
  final bool isTerm;
  final Widget? trailing;
  final bool autofocus;

  @override
  Widget build(BuildContext context) {
    final inputStyle = isTerm
        ? const TextStyle(
            fontFamily: AppFonts.literata,
            fontWeight: FontWeight.w500,
            fontSize: 17,
            color: AppColors.ink,
          )
        : AppText.translation.copyWith(fontSize: 15, color: AppColors.ink);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label.toUpperCase(),
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
          child: Row(
            children: [
              Expanded(
                child: TextField(
                  controller: controller,
                  autofocus: autofocus,
                  cursorColor: AppColors.ink,
                  style: inputStyle,
                  decoration: InputDecoration.collapsed(
                    hintText: hint,
                    hintStyle: inputStyle.copyWith(color: AppColors.tertiary),
                  ),
                ),
              ),
              if (trailing != null) ...[const SizedBox(width: 10), trailing!],
            ],
          ),
        ),
      ],
    );
  }
}
