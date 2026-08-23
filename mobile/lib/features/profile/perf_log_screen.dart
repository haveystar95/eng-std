import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../data/perf_log.dart';

/// Dev screen for the stall monitor (see [PerfLog]). The phone runs RELEASE builds, so there is no
/// `flutter run` console to read — this is how the numbers get off the device.
///
/// Reachable only when DEV_MENU is on, but its copy still goes through the ARB deck like every
/// other screen's: it used to be English literals, which the Cyrillic guard cannot see and which
/// left a Russian interface printing «Copy to clipboard» (QA-OBS-24).
class PerfLogScreen extends StatefulWidget {
  const PerfLogScreen({super.key});

  @override
  State<PerfLogScreen> createState() => _PerfLogScreenState();
}

class _PerfLogScreenState extends State<PerfLogScreen> {
  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Scaffold(
      backgroundColor: AppColors.paper,
      appBar: AppBar(
        backgroundColor: AppColors.paper,
        elevation: 0,
        foregroundColor: AppColors.ink,
        title: Text(l.perfMonitorTitle),
      ),
      body: SafeArea(
        child: Column(
          children: [
            SwitchListTile.adaptive(
              value: PerfLog.enabled,
              title: Text(l.perfMonitorToggle),
              subtitle: Text(l.perfMonitorToggleHint),
              onChanged: (on) => setState(() => PerfLog.instance.setEnabled(on)),
            ),
            const Divider(height: 1, color: AppColors.hairline),
            Expanded(
              child: ValueListenableBuilder<int>(
                valueListenable: PerfLog.instance.revision,
                builder: (context, _, _) {
                  final text = PerfLog.instance.text;
                  return SingleChildScrollView(
                    padding: const EdgeInsets.all(12),
                    // Lines are wide — let them scroll sideways instead of wrapping into mush.
                    child: SingleChildScrollView(
                      scrollDirection: Axis.horizontal,
                      child: SelectableText(
                        text.isEmpty ? l.perfMonitorEmpty : text,
                        style: const TextStyle(fontFamily: 'Courier', fontSize: 10, height: 1.45),
                      ),
                    ),
                  );
                },
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
              child: Column(
                children: [
                  PrimaryButton(
                    label: l.perfMonitorCopy,
                    onPressed: () async {
                      await Clipboard.setData(ClipboardData(text: PerfLog.instance.text));
                      final path = await PerfLog.instance.dumpToFile();
                      if (context.mounted) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(content: Text(l.perfMonitorCopied(path ?? 'n/a'))),
                        );
                      }
                    },
                  ),
                  const SizedBox(height: 8),
                  Center(
                    child: QuietButton(
                      label: l.perfMonitorClear,
                      onPressed: () => setState(PerfLog.instance.clear),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
