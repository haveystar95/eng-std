import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../data/providers.dart';
import 'dialog_models.dart';
import 'dialog_repository.dart';
import 'realtime_channel.dart';
import 'realtime_webrtc_channel.dart';

/// The dialog backend — the real `/practice/dialogs` API (assembles the lesson + mints the ephemeral
/// realtime token). Swap back to `FakeDialogRepository(ref.watch(appDatabaseProvider))` for an
/// offline scripted demo.
final dialogRepositoryProvider = Provider<DialogRepository>((ref) {
  return ApiDialogRepository(ref.watch(apiClientProvider));
});

/// A fresh transport per dialog — the real WebRTC channel to OpenAI Realtime. Swap to
/// `() => FakeRealtimeChannel()` for a scripted, no-audio demo.
final realtimeChannelFactoryProvider = Provider<RealtimeChannel Function()>((ref) {
  return () => WebRtcRealtimeChannel();
});

/// The collection's most recent finished dialog (network), for the collection-screen result row.
/// Null when there is none. Degrades to null offline (the entry then shows the plain «Разговор»
/// button). Invalidate after a dialog finishes so the row refreshes.
final lastDialogProvider = FutureProvider.family<LastDialogResult?, String>((ref, collectionId) async {
  try {
    final raw = await ref.watch(apiClientProvider).lastDialog(collectionId);
    return raw == null ? null : LastDialogResult.fromJson(raw);
  } catch (_) {
    return null;
  }
});
