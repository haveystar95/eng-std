// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for English (`en`).
class AppLocalizationsEn extends AppLocalizations {
  AppLocalizationsEn([String locale = 'en']) : super(locale);

  @override
  String triageCounter(int current, int total) {
    return '$current of $total';
  }

  @override
  String get triageSwipeHint => 'Swipe or tap the buttons · tap to flip';

  @override
  String get triageVerdictUnknown => 'Don\'t know';

  @override
  String get triageVerdictUnsure => 'Not sure';

  @override
  String get triageVerdictKnown => 'Know';

  @override
  String get triageUndo => 'Undo last word';

  @override
  String get triageTermTypeWord => 'word';

  @override
  String get triageTermTypePhrase => 'phrase';

  @override
  String get triageTermTypeIdiom => 'idiom';

  @override
  String get triageTermTypePhrasalVerb => 'phrasal verb';

  @override
  String get triageAllDoneTitle => 'All sorted';

  @override
  String get triageAllDoneBody => 'No new words left to sort in this set.';

  @override
  String get triageMoreLaterTitle => 'That\'s all for now';

  @override
  String triageMoreLaterBody(int count) {
    return '$count more after syncing — come back when you\'re online.';
  }

  @override
  String get triageDone => 'Done';

  @override
  String get triageSummaryBatchTitle => 'Batch sorted';

  @override
  String get triageSummaryDoneTitle => 'Sorting complete';

  @override
  String get triageTallyKnown => 'Know';

  @override
  String get triageTallyLearning => 'Learning';

  @override
  String get triageTallyUnsure => 'Not sure';

  @override
  String triageRemainingAfterSync(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count more after syncing',
      one: '$count more after syncing',
    );
    return '$_temp0';
  }

  @override
  String triageLoadError(String error) {
    return 'Couldn\'t load: $error';
  }

  @override
  String get homeGeneratePlaceholder => 'e.g. a visit to the doctor';

  @override
  String get homeGenerateChipInterview => 'Job interview';

  @override
  String get tabHome => 'Today';

  @override
  String get tabCollections => 'Collections';

  @override
  String get homeSessionTitle => 'Session';

  @override
  String collectionWordsCount(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count words',
      one: '$count word',
    );
    return '$_temp0';
  }

  @override
  String collectionDueSuffix(int count) {
    return '$count due today';
  }

  @override
  String collectionDensityMastered(int count) {
    return 'Mastered $count';
  }

  @override
  String collectionDensityInWork(int count) {
    return 'In progress $count';
  }

  @override
  String collectionDensityToSort(int count) {
    return 'To sort $count';
  }

  @override
  String collectionTriageButton(int count) {
    return 'Sort $count';
  }

  @override
  String get collectionTriageSubtitle => 'New words in this set';

  @override
  String collectionLearnButton(int count) {
    return 'Learn $count';
  }

  @override
  String collectionReviewButton(int count) {
    return 'Review $count';
  }

  @override
  String get collectionPracticeButton => 'Free practice';

  @override
  String get collectionPracticeSubtitle => 'Nothing urgent — just practice';

  @override
  String get collectionWordsLabel => 'Words';

  @override
  String get collectionReferenceBadge => 'reference';

  @override
  String pairBadgeSemantics(String learned, String support) {
    return 'Language pair: $learned with $support';
  }

  @override
  String get collectionReferenceHint =>
      'A reference collection: read the words and hear them. There are no trainers for this language yet.';

  @override
  String get collectionAddWord => 'Add a word';

  @override
  String get collectionEmptyTitle => 'No words yet';

  @override
  String get collectionEmptyBody => 'Tap “Add a word” to start';

  @override
  String get collectionTriageBannerTitle => 'Sort the set';

  @override
  String get collectionTriageBannerBody => 'Mark what you already know — the rest goes to practice';

  @override
  String get collectionTriageBannerStart => 'Start';

  @override
  String get actionEdit => 'Edit';

  @override
  String get actionDelete => 'Delete';

  @override
  String collectionDeleteWordTitle(String term) {
    return 'Delete “$term”?';
  }

  @override
  String get collectionDeleteWordMessage => 'The word stays in other sets; your progress is kept.';

  @override
  String get wordSheetAddTitle => 'Add a word';

  @override
  String get wordSheetEditTitle => 'Edit word';

  @override
  String get wordFieldTerm => 'Term';

  @override
  String get wordFieldTranslation => 'Translation';

  @override
  String get wordTermHint => 'word or phrase';

  @override
  String get wordTranslationHintOptional => 'optional — we\'ll fill it in';

  @override
  String get wordSheetAddHelper => 'Transcription, example and photo are added automatically.';

  @override
  String get wordSheetEditHelper => 'Example and photo stay unless you change the term.';

  @override
  String get wordSheetAddButton => 'Add to set';

  @override
  String get wordSheetSaveButton => 'Save';

  @override
  String get wordSheetDeleteLink => 'Remove from set';

  @override
  String get collectionMoveWord => 'Move to…';

  @override
  String get collectionMoveWordTitle => 'Move where';

  @override
  String collectionMoveWordDone(String folder) {
    return 'Moved to “$folder”';
  }

  @override
  String get collectionMoveWordFailed => 'Could not move it';

  @override
  String get collectionMoveWordNowhere => 'You have no other collections yet';

  @override
  String get collectionMenuRename => 'Rename';

  @override
  String get collectionMenuDelete => 'Delete set';

  @override
  String get collectionMenuRemoveFromMine => 'Remove from mine';

  @override
  String collectionUnsubscribeTitle(String title) {
    return 'Remove “$title” from yours?';
  }

  @override
  String get collectionUnsubscribeMessage =>
      'The set leaves «Mine». Its words and your progress are kept — you can add it again from the store.';

  @override
  String collectionDeleteTitle(String title) {
    return 'Delete “$title”?';
  }

  @override
  String get collectionDeleteMessage =>
      'The collection goes; the words stay in training. A word leaves training only from its own card.';

  @override
  String get commonCancel => 'Cancel';

  @override
  String get commonCloseMenu => 'Close menu';

  @override
  String approxWords(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count words',
      one: '$count word',
    );
    return '$_temp0';
  }

  @override
  String get collectionsTitle => 'Collections';

  @override
  String get collectionsEmptyTitle => 'No collections yet';

  @override
  String get collectionsEmptyBody => 'Describe a situation — AI will build your first set.';

  @override
  String get collectionsCreateManual => 'Create manually';

  @override
  String get collectionsCreateManualHint => 'An empty collection — you add the words';

  @override
  String get collectionsCreateGenerate => 'Generate';

  @override
  String get collectionsCreateGenerateHint => 'AI builds a set from a situation you describe';

  @override
  String get collectionsNewCollection => 'New collection';

  @override
  String collectionsTileMastered(int count, int mastered) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count words',
      one: '$count word',
    );
    return '$_temp0 · $mastered mastered';
  }

  @override
  String get generationGeneratingTitle => 'Building the collection…';

  @override
  String generationGeneratingMeta(String topic, String levels, String size) {
    return '$topic · $levels · $size';
  }

  @override
  String get generationGeneratingNote => 'Picking words and photos · usually 20–30 seconds';

  @override
  String get generationQueuedNote => 'We\'ll send it as soon as you\'re online';

  @override
  String get generationFailedTitle => 'Didn\'t work out';

  @override
  String generationFailedBody(String topic) {
    return 'The service didn\'t answer “$topic”. No generation was spent.';
  }

  @override
  String get generationQuotaTitle => 'Out of generations for today';

  @override
  String generationQuotaBody(String topic, String time) {
    return '“$topic” wasn\'t created. The limit resets at $time — you can retry then.';
  }

  @override
  String generationQuotaBodyNoTime(String topic) {
    return '“$topic” wasn\'t created: today\'s generation limit is spent.';
  }

  @override
  String get generationQuotaPremium => 'Get Premium';

  @override
  String get generationRetry => 'Retry';

  @override
  String get generationHide => 'Hide';

  @override
  String generateEnqueueFailed(String error) {
    return 'Could not queue the generation: $error';
  }

  @override
  String get generationReadyLabel => 'Ready';

  @override
  String generationReadyLoading(String topic) {
    return 'Ready — loading “$topic”…';
  }

  @override
  String generationUnderBadge(int delivered, int requested) {
    return '$delivered of $requested';
  }

  @override
  String get generationReadyUnder => 'Ready · fewer than asked';

  @override
  String get generateScreenTitle => 'New collection';

  @override
  String get generateSituationLabel => 'Describe the situation';

  @override
  String get generateSituationHelper =>
      'The more specific the situation, the sharper the set. E.g. “first doctor\'s visit — symptoms and lab tests”.';

  @override
  String get generatePlaceholder0 => 'Renting a flat — talking to the agent';

  @override
  String get generatePlaceholder1 => 'First doctor\'s visit — symptoms and tests';

  @override
  String get generatePlaceholder2 => 'IT interview — talking through projects';

  @override
  String get generatePlaceholder3 => 'Opening a bank account';

  @override
  String get generatePlaceholder4 => 'Ordering food at a café';

  @override
  String get generateSizeLabel => 'Size';

  @override
  String get generateSizeSmall => 'Small';

  @override
  String get generateSizeMedium => 'Medium';

  @override
  String get generateSizeLarge => 'Large';

  @override
  String get generateLevelLabel => 'Level';

  @override
  String get generateLevelMulti => 'several allowed';

  @override
  String get generateLanguageLabel => 'Language to learn';

  @override
  String get generateLanguageDefault => 'default';

  @override
  String generateQuotaRemaining(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count generations left today',
      one: '$count generation left today',
    );
    return '$_temp0';
  }

  @override
  String generateQuotaExhausted(String time) {
    return 'Out of generations for today · resets at $time';
  }

  @override
  String get generateSubmit => 'Generate';

  @override
  String get generatePremiumUpsell => 'Need more? Premium — up to 20 a day';

  @override
  String get generateManual => 'Build a collection manually';

  @override
  String generateVoiceListening(String time) {
    return 'Listening · $time';
  }

  @override
  String get generateVoiceStop => 'Stop';

  @override
  String get generateVoiceHelper =>
      'Text appears in the field as it\'s recognised — after you stop you can edit it by hand.';

  @override
  String get generateVoicePermissionDenied =>
      'Microphone and speech recognition access is needed — enable it in Settings';

  @override
  String get collectionSheetCreateTitle => 'New collection';

  @override
  String get collectionSheetEditTitle => 'Rename collection';

  @override
  String get collectionNameLabel => 'Name';

  @override
  String get collectionNameHint => 'e.g. Travel';

  @override
  String get collectionSheetCreateButton => 'Create';

  @override
  String get searchFieldHint => 'Find a word';

  @override
  String get searchRecentLabel => 'You searched';

  @override
  String searchPressEnter(String query) {
    return 'Press Enter to search for “$query” whole';
  }

  @override
  String get searchOpenCard => 'Open the card';

  @override
  String get searchSimilar => 'Similar';

  @override
  String get searchBuildCard => 'Build the card';

  @override
  String get searchBuildCardNote => 'Meaning and example. Again — free';

  @override
  String get searchLooking => 'Looking…';

  @override
  String get searchBuildTranslation => 'translation';

  @override
  String get searchBuildMeaning => 'meaning';

  @override
  String get searchBuildExample => 'example';

  @override
  String get searchBuildNote =>
      'A couple of seconds. You can close this — the card will be in search.';

  @override
  String searchLimitUsed(int used, int cap) {
    return '$used of $cap today';
  }

  @override
  String get searchLimitTitle => 'Model-written cards come back at midnight';

  @override
  String get searchLookupFailed => 'Could not look this word up';

  @override
  String get searchNotRecognized => 'Couldn’t make that out — check the spelling';

  @override
  String get searchQueryTooLong => 'Search is for words and short phrases';

  @override
  String get searchSaveToDefault => '+ Saved';

  @override
  String searchAlreadyIn(String collection) {
    return 'Already in “$collection”';
  }

  @override
  String searchSavedShelf(String collection) {
    return 'Saved to “$collection” · waiting to be sorted';
  }

  @override
  String searchSavedLearning(String collection) {
    return 'Saved to “$collection” · in progress';
  }

  @override
  String get searchLearnNow => 'Learn it now';

  @override
  String get searchAddToCollection => 'Add to collection';

  @override
  String get searchNewCollection => 'New collection';

  @override
  String searchNewCollectionInPair(String pair) {
    return 'New collection · $pair';
  }

  @override
  String get searchSaveFailed => 'Could not save';

  @override
  String get searchPairFrom => 'From';

  @override
  String get searchPairTo => 'Into';

  @override
  String get searchPairSwap => 'Swap the languages';

  @override
  String get searchPairNoDefault =>
      '“Saved” is a collection of another pair. Pick a collection of this pair, or make a new one.';

  @override
  String get searchPairMismatchTitle => 'A word of another language';

  @override
  String searchPairMismatchMessage(String expected, String actual) {
    return 'This collection studies $expected, and the word is in $actual. One collection, one pair — so this word needs a collection of its own pair.';
  }

  @override
  String get searchPairMismatchCreate => 'Make a collection';

  @override
  String get wordCardExampleLabel => 'Example';

  @override
  String wordCardAlso(String words) {
    return 'also: $words';
  }

  @override
  String get wordCardFolderHint => 'On the right — pick another collection';

  @override
  String wordCardSavedIn(String folder) {
    return 'In “$folder”';
  }

  @override
  String get wordCardAddToAnother => 'Add to another collection';

  @override
  String get wordCardProgressLabel => 'Word progress';

  @override
  String wordCardProgressCount(int step, int total) {
    return '$step of $total';
  }

  @override
  String wordCardPhotoCredit(String author) {
    return 'Photo: $author';
  }

  @override
  String get wordCardSpeak => 'Speak';

  @override
  String get wordCardBack => 'Back';

  @override
  String get wordCardMenu => 'More';

  @override
  String get wordCardNoPhoto => 'No photo';

  @override
  String get progressTitle => 'Progress';

  @override
  String progressStreakDays(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count-day streak',
      one: '$count-day streak',
    );
    return '$_temp0';
  }

  @override
  String progressBestResult(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Best result — $count days',
      one: 'Best result — $count day',
    );
    return '$_temp0';
  }

  @override
  String get progressDayMon => 'Mon';

  @override
  String get progressDayTue => 'Tue';

  @override
  String get progressDayWed => 'Wed';

  @override
  String get progressDayThu => 'Thu';

  @override
  String get progressDayFri => 'Fri';

  @override
  String get progressDaySat => 'Sat';

  @override
  String get progressDaySun => 'Sun';

  @override
  String get progressLearnedTotal => 'Learned total';

  @override
  String get progressThisWeek => 'This week';

  @override
  String get progressToday => 'Reviews today';

  @override
  String get progressActivityMonth => 'Activity this month';

  @override
  String progressMonth(String month) {
    String _temp0 = intl.Intl.selectLogic(month, {
      '1': 'January',
      '2': 'February',
      '3': 'March',
      '4': 'April',
      '5': 'May',
      '6': 'June',
      '7': 'July',
      '8': 'August',
      '9': 'September',
      '10': 'October',
      '11': 'November',
      '12': 'December',
      'other': '',
    });
    return '$_temp0';
  }

  @override
  String progressAllWords(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'All $count words',
      one: 'All $count word',
    );
    return '$_temp0';
  }

  @override
  String get homeLimitReachedTitle => 'Daily new-word limit reached';

  @override
  String get homeLimitReachedHint =>
      'New words resume tomorrow. For now, free-practice any collection.';

  @override
  String get homeOfflineBanner =>
      'No connection. Reviews work as usual — we\'ll sync when you\'re back online.';

  @override
  String get homeUnreachableTitle => 'The server isn\'t answering';

  @override
  String get homeUnreachableBody =>
      'Today\'s plan couldn\'t be loaded. Pull down to try again — everything saved is still here.';

  @override
  String get profileTitle => 'Profile';

  @override
  String get commonSave => 'Save';

  @override
  String get sessionPhaseIntro => 'Getting to know';

  @override
  String get sessionPhaseAssemble => 'Assemble';

  @override
  String get sessionPhaseReview => 'Review';

  @override
  String get sessionPhasePractice => 'Free practice';

  @override
  String sessionInstrChoose(String lang) {
    return 'choose the $lang equivalent';
  }

  @override
  String get sessionInstrAssemble => 'assemble it from the words';

  @override
  String get sessionInstrAssembleLetters => 'assemble it from the letters';

  @override
  String get sessionAssemblyEmptyHint => 'Tap the words below';

  @override
  String get sessionAssemblyEmptyHintLetters => 'Tap the letters below';

  @override
  String get sessionInstrAssembleSentence => 'put the sentence together';

  @override
  String sessionInstrType(String lang) {
    return 'write it in $lang';
  }

  @override
  String get sessionInstrListenChoose => 'listen and choose the translation · replay any time';

  @override
  String sessionInstrListenType(String lang) {
    return 'listen and write it in $lang';
  }

  @override
  String get sessionInstrDictation => 'listen and write the sentence down';

  @override
  String get sessionInstrPickCorrect => 'pick the correct sentence';

  @override
  String get sessionInstrDescriptionMatch => 'pick the word this describes';

  @override
  String sessionPickCorrectShouldBe(String correction) {
    return 'should be: $correction';
  }

  @override
  String get sessionClozeInsert => 'Insert the word';

  @override
  String get sessionChipReturnHint => 'Tap a word in the line to send it back';

  @override
  String get sessionHintFirstLetter => 'Hint: first letter';

  @override
  String get sessionDontRemember => 'Don\'t remember';

  @override
  String get sessionWrongKeyboard =>
      'That looks like the wrong keyboard — switch it and type again';

  @override
  String get sessionCheck => 'Check';

  @override
  String get sessionIntroBadge => 'new word';

  @override
  String get sessionIntroGot => 'Got it';

  @override
  String get sessionIntroAlso => 'also:';

  @override
  String get sessionInstrSpeakWord => 'say the word out loud';

  @override
  String get sessionInstrSpeakExample => 'read the sentence out loud';

  @override
  String get sessionSpeakStop => 'Done';

  @override
  String get sessionSpeakListening => 'Listening…';

  @override
  String get sessionSpeakNotHeard => 'Didn\'t catch that. Try again — a little closer to the mic.';

  @override
  String get sessionSpeakNoMic => 'The microphone isn\'t available. You can skip this card.';

  @override
  String buildStamp(String client, String server) {
    return 'client $client · server $server';
  }

  @override
  String get buildStampUnstamped => 'unstamped';

  @override
  String get buildStampWaiting => '…';

  @override
  String get buildStampNoServer => 'offline';

  @override
  String get qaReportButton => 'Report';

  @override
  String qaReportSent(String id) {
    return 'Report saved: $id';
  }

  @override
  String get qaReportFailed => 'Report was not sent';

  @override
  String get speechPermissionRecognitionDenied =>
      'Speech recognition is off. Allow speech recognition in Settings — without it the phone hears you but cannot understand.';

  @override
  String get speechPermissionMicDenied =>
      'The microphone is off. Allow microphone access in Settings.';

  @override
  String get speechPermissionBothDenied =>
      'Allow the microphone and speech recognition in Settings — both are needed.';

  @override
  String get speechPermissionOpenSettings => 'Open Settings';

  @override
  String get sessionSpeakCutOff => 'Didn\'t catch the whole thing — say it again.';

  @override
  String get sessionSpeakSkip => 'Skip';

  @override
  String get sessionSpeakSkipHint =>
      'Skipping costs nothing — the word will come back in its own time.';

  @override
  String get sessionSpeakYourTurn => 'Your turn — tap and speak';

  @override
  String get sessionSpeakWaitForRole => 'They\'re still speaking';

  @override
  String get sessionSpeakRecording => 'Recording — say it, then tap Done';

  @override
  String get sessionSpeakVerdictCorrect => 'Right';

  @override
  String sessionSpeakVerdictAlmost(String words) {
    return 'Almost — missing: $words';
  }

  @override
  String get sessionSpeakVerdictWrong => 'Not that';

  @override
  String get sessionSpeakHint =>
      'We\'re checking that you remembered the word, not how you pronounce it.';

  @override
  String sessionSpeakHintKey(String key) {
    return 'Say the line — what counts is “$key”.';
  }

  @override
  String get sessionSpeakHintWhole => 'Say the whole line.';

  @override
  String sessionSpeakHeard(String text) {
    return 'Heard: “$text”';
  }

  @override
  String get sessionEchoTry => 'Say it back';

  @override
  String get sessionEchoHeard => 'Heard you';

  @override
  String get sessionEchoAgain => 'Give it another go';

  @override
  String get sessionInstrRecogniseTranslation => 'choose the translation';

  @override
  String get ladderStep0 => 'first look';

  @override
  String get ladderStep1 => 'recognition';

  @override
  String get ladderStep3 => 'practice';

  @override
  String get ladderStep4 => 'writing';

  @override
  String get ladderStep5 => 'dictation';

  @override
  String get statusToSort => 'To sort';

  @override
  String get statusInWork => 'In progress';

  @override
  String get statusMastered => 'Mastered';

  @override
  String get statusPaused => 'Paused';

  @override
  String statusLadderStep(int step, int total, String rung) {
    return 'Step $step of $total: $rung';
  }

  @override
  String get statusLegendTitle => 'What the dots mean';

  @override
  String get poolKnownLegend => 'Marked “I know it” — it never walked the ladder.';

  @override
  String get ladderKnownDash => 'known';

  @override
  String get ladderTrainWord => 'Train this word';

  @override
  String get sessionNext => 'Next';

  @override
  String get sessionDone => 'Done';

  @override
  String get sessionFeedbackCorrect => 'Correct';

  @override
  String get sessionFeedbackAlmost => 'Almost:';

  @override
  String get sessionFeedbackWrong => 'Not quite — the correct form is below';

  @override
  String get sessionFeedbackWrongAbove => 'Not quite — the correct answer is marked above';

  @override
  String get sessionDueToday => 'today';

  @override
  String get sessionDueTomorrow => 'tomorrow';

  @override
  String sessionDueInDays(int days) {
    String _temp0 = intl.Intl.pluralLogic(
      days,
      locale: localeName,
      other: 'in $days days',
      one: 'in $days day',
    );
    return '$_temp0';
  }

  @override
  String sessionSeeAgain(String when) {
    return 'See it again $when';
  }

  @override
  String get sessionSummaryTitle => 'Session complete';

  @override
  String sessionStatReviewed(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Reviewed',
      one: 'Reviewed',
    );
    return '$_temp0';
  }

  @override
  String sessionStatNew(int count) {
    String _temp0 = intl.Intl.pluralLogic(count, locale: localeName, other: 'New', one: 'New');
    return '$_temp0';
  }

  @override
  String sessionStatErrors(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Mistakes',
      one: 'Mistake',
    );
    return '$_temp0';
  }

  @override
  String sessionPracticeStatDone(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Practiced',
      one: 'Practiced',
    );
    return '$_temp0';
  }

  @override
  String get sessionPracticeAgain => 'Again';

  @override
  String get sessionDailyGoal => 'Daily goal';

  @override
  String get sessionGoalClosed => 'Daily goal reached';

  @override
  String sessionStreak(int days) {
    String _temp0 = intl.Intl.pluralLogic(
      days,
      locale: localeName,
      other: 'Streak — $days days',
      one: 'Streak — $days day',
    );
    return '$_temp0';
  }

  @override
  String get sessionSessionWords => 'This session\'s words';

  @override
  String sessionStrugglingTitle(String term) {
    return 'Struggling: $term';
  }

  @override
  String get sessionStrugglingBody =>
      'This one\'s tricky. Try a different example — sometimes it\'s the context, not the word.';

  @override
  String get sessionNewExample => 'New example';

  @override
  String get sessionNewExampleExhausted => 'You\'ve used today\'s examples';

  @override
  String get sessionPracticeBanner => 'Free practice — progress doesn\'t change';

  @override
  String get sessionExitTitle => 'End the session?';

  @override
  String get sessionExitBody => 'Answered words are saved — you can come back any time.';

  @override
  String get sessionExitConfirm => 'Exit';

  @override
  String get sessionExitCancel => 'Continue';

  @override
  String get sessionClose => 'Close';

  @override
  String get sessionListenReplay => 'Replay audio';

  @override
  String get sessionListenReplaySlow => 'Slower';

  @override
  String get sessionEmpty => 'Nothing to review here yet';

  @override
  String get sessionDailyNewLimit => 'You\'ve reached today\'s new-word limit. Come back tomorrow';

  @override
  String get practiceDialogEntry => 'Conversation · 3 min';

  @override
  String get practiceDialogEntrySubtitle => 'Voice practice with AI';

  @override
  String get practiceDialogOfflineHint => 'Needs internet';

  @override
  String get practiceDialogPrestartTitle => 'Talk with the AI';

  @override
  String practiceDialogPrestartBody(String lang) {
    return 'The AI will speak with you in the collection\'s language — $lang. Answer out loud and try to use these words.';
  }

  @override
  String get practiceDialogPrestartWordsLabel => 'Words to use';

  @override
  String get practiceDialogStart => 'Start the conversation';

  @override
  String get practiceDialogStateConnecting => 'connecting…';

  @override
  String get practiceDialogStateSpeaking => 'speaking';

  @override
  String get practiceDialogStateListening => 'listening to you';

  @override
  String practiceDialogCoverageLabel(int used, int total) {
    return '$used / $total';
  }

  @override
  String get practiceDialogExitTitle => 'End the conversation?';

  @override
  String get practiceDialogExitMessage => 'The conversation will end and you\'ll see a recap.';

  @override
  String get practiceDialogExitConfirm => 'End';

  @override
  String get practiceDialogExitCancel => 'Keep going';

  @override
  String get practiceDialogFinaleTitle => 'Conversation over';

  @override
  String practiceDialogFinaleWords(int used, int total) {
    return 'Words used: $used of $total';
  }

  @override
  String get practiceDialogFinaleDone => 'Done';

  @override
  String get practiceDialogErrorSubscription => 'Conversations are a Premium feature.';

  @override
  String practiceDialogErrorRateLimited(String time) {
    return 'No conversations left today. More after $time.';
  }

  @override
  String get practiceDialogErrorRateLimitedNoTime =>
      'No conversations left today. Try again tomorrow.';

  @override
  String get practiceDialogErrorOffline => 'You\'re offline. A conversation needs internet.';

  @override
  String get practiceDialogErrorGeneric => 'Couldn\'t start the conversation. Please try again.';

  @override
  String get practiceDialogClose => 'Close';

  @override
  String get practiceDialogRepeat => 'Practice again';

  @override
  String practiceDialogResultWords(int used, int total) {
    return 'words: $used of $total';
  }

  @override
  String get storeSegmentMine => 'Mine';

  @override
  String get storeSegmentReady => 'Ready-made';

  @override
  String storeWordsCount(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count words',
      one: '$count word',
    );
    return '$_temp0';
  }

  @override
  String get storeInLibrary => 'In library';

  @override
  String get storeAddToMine => 'Add to mine';

  @override
  String get storeAvailableWithPremium => 'Available with Premium';

  @override
  String storeAllSetsUnlock(int count) {
    return 'Unlocks all $count sets at once';
  }

  @override
  String get storeInsideLabel => 'What\'s inside';

  @override
  String storeMoreWords(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count more words',
      one: '$count more word',
    );
    return 'and $_temp0';
  }

  @override
  String get storeLangPairSheetTitle => 'Language pair';

  @override
  String get storeEmptyTitle => 'Sets are coming soon';

  @override
  String get storeEmptyBody => 'Ready-made collections by situation will appear here shortly.';

  @override
  String get storePreviewAdded => 'Set added to Mine';

  @override
  String get storeSubscribeError => 'Couldn\'t add the set. Please try again.';

  @override
  String get paywallClose => 'Close';

  @override
  String get paywallTitleQuota => 'More collections in one evening';

  @override
  String get paywallTitleGeneric => 'Premium, no limits';

  @override
  String paywallTitleStore(String title, int count) {
    return '$title and $count more sets';
  }

  @override
  String get paywallSubtitleQuota => 'Premium raises the daily limit to twenty generations.';

  @override
  String get paywallSubtitleStore =>
      'Premium collections are curated and all unlock at once — we don\'t sell them one by one.';

  @override
  String get paywallSubtitleGeneric => 'One plan unlocks everything that makes learning faster.';

  @override
  String get paywallBenefitGenerations => 'Up to 20 generations a day';

  @override
  String get paywallBenefitStore => 'Every premium collection in the store';

  @override
  String get paywallBenefitModes => 'Future training modes';

  @override
  String get paywallFreeForever => 'Reviews, sorting and offline — always free.';

  @override
  String get paywallPeriodYear => 'Year';

  @override
  String get paywallPeriodMonth => 'Month';

  @override
  String get paywallPriceYear => '\$29.99';

  @override
  String get paywallPriceMonth => '\$4.99';

  @override
  String get paywallYearPerMonth => '\$2.50 / month';

  @override
  String get paywallPerMonth => 'per month';

  @override
  String get paywallDiscountBadge => '−50%';

  @override
  String get paywallContinue => 'Continue';

  @override
  String paywallLegalYear(String price) {
    return 'Subscription renews automatically. $price per year is charged to your Apple ID; cancel in App Store settings at least 24 hours before the period ends.';
  }

  @override
  String paywallLegalMonth(String price) {
    return 'Subscription renews automatically. $price per month is charged to your Apple ID; cancel in App Store settings at least 24 hours before the period ends.';
  }

  @override
  String get paywallRestore => 'Restore purchases';

  @override
  String get paywallTerms => 'Terms';

  @override
  String get paywallPrivacy => 'Privacy';

  @override
  String get paywallDevPurchased => 'Premium activated (dev mode)';

  @override
  String get profileSectionDev => 'Development';

  @override
  String get devFlagStore => 'Collections store';

  @override
  String get devFlagPaywall => 'Paywall';

  @override
  String get devFlagPremium => 'Premium (dev)';

  @override
  String get perfMonitorTitle => 'Perf monitor';

  @override
  String get perfMonitorToggle => 'Record stalls, slow frames and slow taps';

  @override
  String get perfMonitorToggleHint => 'Off by default — costs nothing while off';

  @override
  String get perfMonitorEmpty => 'nothing recorded';

  @override
  String get perfMonitorCopy => 'Copy to clipboard';

  @override
  String get perfMonitorClear => 'Clear';

  @override
  String perfMonitorCopied(String path) {
    return 'Copied. File: $path';
  }

  @override
  String get sessionOffline => 'No connection';

  @override
  String get sessionLoadFailed => 'Couldn\'t load the session';

  @override
  String get syncStuckBanner => 'Answers aren\'t reaching the server — check your connection';

  @override
  String get syncUnreachableBanner => 'Server unreachable · showing what\'s saved';

  @override
  String get poolEnrollAction => 'Learn this word';

  @override
  String get poolEnrollNote => 'It joins the queue and starts coming up in your sessions.';

  @override
  String get poolUnenrollAction => 'Stop studying';

  @override
  String poolUnenrollTitle(String term) {
    return 'Stop studying “$term”?';
  }

  @override
  String get poolUnenrollMessage =>
      'The word stops coming up in your sessions. Its progress and history are kept — you can bring it back at any time.';

  @override
  String get poolUnenrollConfirm => 'Stop';

  @override
  String get myWordsTitle => 'My words';

  @override
  String myWordsCount(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count words',
      one: '$count word',
    );
    return '$_temp0';
  }

  @override
  String get myWordsSearchHint => 'Search your words';

  @override
  String get myWordsFilterAll => 'All';

  @override
  String get myWordsFilterNew => 'New';

  @override
  String get myWordsFilterLearning => 'Recognition';

  @override
  String get myWordsFilterReview => 'Review';

  @override
  String get myWordsSourceAll => 'All collections';

  @override
  String get myWordsSourceNone => 'No collection';

  @override
  String get myWordsEmptyTitle => 'Nothing here yet';

  @override
  String get myWordsEmptyMessage =>
      'Words land here when you sweep a collection with “don’t know” or “not sure” — or tap “Learn this word” on a word card.';

  @override
  String get myWordsNothingFound => 'Nothing found';

  @override
  String homeStreakBadge(int count) {
    return 'Streak $count';
  }

  @override
  String get challengeLabel => 'Word challenge';

  @override
  String challengeStreak(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count in a row',
      one: '$count in a row',
    );
    return '$_temp0';
  }

  @override
  String get challengeStreakReset => 'streak reset';

  @override
  String get challengePraise => 'You know it!';

  @override
  String challengeAnswer(String term, String translation) {
    return '$term — $translation';
  }

  @override
  String challengeExample(String sentence, String translation) {
    return '$sentence — $translation';
  }

  @override
  String challengeMistake(String chosen, String term) {
    return 'You picked “$chosen” — that is $term';
  }

  @override
  String get challengeLearn => 'Learn it';

  @override
  String get challengeTomorrow => 'New one tomorrow';

  @override
  String get challengeCollapsed => 'A new word tomorrow';

  @override
  String get challengeLearning => 'In your queue';

  @override
  String get homeSessionBadge => 'Session';

  @override
  String homeSessionUnitWords(int count) {
    String _temp0 = intl.Intl.pluralLogic(count, locale: localeName, other: 'words', one: 'word');
    return '$_temp0';
  }

  @override
  String get homeSessionRowRepeat => 'To review';

  @override
  String get homeSessionRowNew => 'New';

  @override
  String get homeSessionRowTriage => 'To sort';

  @override
  String homeSessionCardWords(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count words',
      one: '$count word',
    );
    return '$_temp0';
  }

  @override
  String homeSessionCardMinutes(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '≈ $count minutes',
      one: '≈ $count minute',
    );
    return '$_temp0';
  }

  @override
  String sessionSizeCards(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count cards',
      one: '$count card',
    );
    return '~$_temp0';
  }

  @override
  String get homeSessionStart => 'Start';

  @override
  String get homeHardestTitle => 'Hardest today';

  @override
  String homeHardestErrors(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count mistakes',
      one: '$count mistake',
    );
    return '$_temp0';
  }

  @override
  String get homeDoneTitle => 'Done for today';

  @override
  String homeDoneOfWords(int done, int total) {
    String _temp0 = intl.Intl.pluralLogic(
      total,
      locale: localeName,
      other: '$done of $total words',
      one: '$done of $total word',
    );
    return '$_temp0';
  }

  @override
  String homeDoneCards(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count cards',
      one: '$count card',
    );
    return '$_temp0';
  }

  @override
  String homeDoneMinutes(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count min',
      one: '$count min',
    );
    return '$_temp0';
  }

  @override
  String homeChainProgress(int position, int total) {
    String _temp0 = intl.Intl.pluralLogic(
      total,
      locale: localeName,
      other: 'card $position of $total',
      one: 'card $position of $total',
    );
    return '$_temp0';
  }

  @override
  String homeDoneDuration(int minutes, int seconds) {
    return '$minutes min $seconds s';
  }

  @override
  String homeDoneDurationSeconds(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count seconds',
      one: '$count second',
    );
    return '$_temp0';
  }

  @override
  String get homeIdleTitle => 'All reviewed';

  @override
  String get homeIdleTakeNew => 'Take new words';

  @override
  String get homeIdleQueueStalled => 'No new words taken today — the queue is standing still.';

  @override
  String homeNextReviewLine(String when, int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Next review $when, $count words.',
      one: 'Next review $when, $count word.',
    );
    return '$_temp0';
  }

  @override
  String get homeWhenTomorrow => 'tomorrow';

  @override
  String homeExtraFromCollection(int count, String title) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'You can add $count more words from “$title”.',
      one: 'You can add $count more word from “$title”.',
    );
    return '$_temp0';
  }

  @override
  String homeExtraNew(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'You can take $count words already waiting in the queue.',
      one: 'You can take $count word already waiting in the queue.',
    );
    return '$_temp0';
  }

  @override
  String homeExtraButton(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count more words',
      one: '$count more word',
    );
    return '$_temp0';
  }

  @override
  String homeStoreLink(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'or take one of $count ready-made',
      one: 'or take one of $count ready-made',
    );
    return '$_temp0';
  }

  @override
  String get homeStatLearned => 'Learned';

  @override
  String get homeStatWeek => 'This week';

  @override
  String get homeStatInWork => 'In progress';

  @override
  String homeTomorrowRow(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count words come back tomorrow',
      one: '$count word comes back tomorrow',
    );
    return '$_temp0';
  }

  @override
  String homeAwardPromoted(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '+$count words moved up',
      one: '+$count word moved up',
    );
    return '$_temp0';
  }

  @override
  String homeAwardExample(String term, String rung) {
    return '$term reached “$rung”';
  }

  @override
  String get homeGenerateCardTitle => 'Generate a set';

  @override
  String get homeGenerateCardHint => 'Describe the situation — we’ll build a set for it';

  @override
  String get homeStoreShowcaseTitle => 'Ready-made sets';

  @override
  String homeStoreShowcaseAll(int count) {
    return 'all $count';
  }

  @override
  String get homeFirstDayPromise => '5 minutes a day — 20 words a week';

  @override
  String get homeGenerateChipVet => 'At the vet';

  @override
  String get homeGenerateChipMoving => 'Moving abroad';

  @override
  String get homeFirstDayTitle => 'Let\'s start with a first set';

  @override
  String homeSortOffer(int count, String title) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'You can sort $count more words from “$title”',
      one: 'You can sort $count more word from “$title”',
    );
    return '$_temp0';
  }

  @override
  String homeTriageAction(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Sort $count words',
      one: 'Sort $count word',
    );
    return '$_temp0';
  }

  @override
  String get homeSortFirstTitle => 'Time to sort your words';

  @override
  String profileNativeLangConfirmTitle(String language) {
    return 'Translations in $language?';
  }

  @override
  String get profileNativeLangConfirmBody =>
      'New collections and plans will use it. Existing collections stay as they are — their translations are not rewritten.';

  @override
  String get tabPlan => 'Plan';

  @override
  String get searchOpen => 'Search';

  @override
  String get commonBack => 'Back';

  @override
  String get devVoicesTitle => 'Line voices';

  @override
  String get devVoicesLead =>
      'The same five lines of the qa plan, read by every candidate at one pace. Judge them on the phone rather than in the files: the speaker and the headphones decide more than a spectrogram.';

  @override
  String get devVoicesSystem => 'The phone\'s own voice';

  @override
  String get devVoicesSystemNote =>
      'Spoken live, at the line tempo. The voice is whichever one iOS Settings has; an enhanced voice is downloaded there.';

  @override
  String devVoicesPrice(String price) {
    return '≈ $price per plan';
  }

  @override
  String get devVoicesPlayAll => 'All five';

  @override
  String get devVoicesStop => 'Stop';

  @override
  String get devVoicesFavourite => 'This session\'s favourite';

  @override
  String get planTitle => 'Plan';

  @override
  String get planEmptyTitle => 'The conversation you are getting ready for';

  @override
  String get planEmptySub => 'a scene a day · 20 minutes · a rehearsal out loud';

  @override
  String get planRuleSituation => 'One situation a day. Days open one at a time';

  @override
  String get planRuleStages =>
      'Six stages in order: words → phrases → dialogue → listen and reply → speak on my own → talk';

  @override
  String get planRuleReturn => 'Whatever did not work comes back the next day. Nothing is lost';

  @override
  String get planExampleDoctorTitle => 'A doctor\'s visit';

  @override
  String get planExampleDoctorDay1 => 'Booking the visit';

  @override
  String get planExampleDoctorGoal11 => 'ask about the time';

  @override
  String get planExampleDoctorGoal12 => 'agree on a slot';

  @override
  String get planExampleDoctorGoal13 => 'name your insurance';

  @override
  String get planExampleDoctorDay2 => 'At the doctor\'s';

  @override
  String get planExampleDoctorGoal21 => 'describe the pain';

  @override
  String get planExampleDoctorGoal22 => 'answer about medicines';

  @override
  String get planExampleDoctorGoal23 => 'follow the instructions';

  @override
  String get planExampleDoctorDay3 => 'The pharmacy';

  @override
  String get planExampleDoctorGoal31 => 'name the prescription';

  @override
  String get planExampleDoctorGoal32 => 'understand the dosage';

  @override
  String get planExampleDoctorGoal33 => 'ask for an alternative';

  @override
  String get planExampleInterviewTitle => 'A job interview';

  @override
  String get planExampleInterviewDay1 => 'Introductions';

  @override
  String get planExampleInterviewGoal11 => 'talk about yourself';

  @override
  String get planExampleInterviewGoal12 => 'name your experience';

  @override
  String get planExampleInterviewGoal13 => 'explain why you left';

  @override
  String get planExampleInterviewDay2 => 'Questions about the job';

  @override
  String get planExampleInterviewGoal21 => 'describe a project';

  @override
  String get planExampleInterviewGoal22 => 'answer about deadlines';

  @override
  String get planExampleInterviewGoal23 => 'own a mistake';

  @override
  String get planExampleInterviewDay3 => 'Salary';

  @override
  String get planExampleInterviewGoal31 => 'name your range';

  @override
  String get planExampleInterviewGoal32 => 'ask about bonuses';

  @override
  String get planExampleInterviewGoal33 => 'agree a start date';

  @override
  String get planExampleLandlordTitle => 'A call to the landlord';

  @override
  String get planExampleLandlordDay1 => 'The deposit';

  @override
  String get planExampleLandlordGoal11 => 'ask the amount';

  @override
  String get planExampleLandlordGoal12 => 'find out when it comes back';

  @override
  String get planExampleLandlordGoal13 => 'give your account';

  @override
  String get planExampleLandlordDay2 => 'Repairs';

  @override
  String get planExampleLandlordGoal21 => 'describe what broke';

  @override
  String get planExampleLandlordGoal22 => 'ask for a repairman';

  @override
  String get planExampleLandlordGoal23 => 'agree on a time';

  @override
  String get planExampleLandlordDay3 => 'The lease';

  @override
  String get planExampleLandlordGoal31 => 'ask about the term';

  @override
  String get planExampleLandlordGoal32 => 'check about pets';

  @override
  String get planExampleLandlordGoal33 => 'name the move-out date';

  @override
  String planExampleMeta(String days, String level) {
    return '$days · $level';
  }

  @override
  String planExampleMore(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n more days',
      one: '$n more day',
    );
    return '$_temp0';
  }

  @override
  String get planExampleWhole => 'the whole plan';

  @override
  String get planEmptyCta => 'Build a plan';

  @override
  String get planFinishedTitle => 'Finished plans';

  @override
  String planFinishedItemDate(String date) {
    return 'finished $date';
  }

  @override
  String planHeaderBrow(int n, int total) {
    return 'Plan · day $n of $total';
  }

  @override
  String planPlateLabel(int n) {
    return 'Day $n';
  }

  @override
  String planCardsCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n cards', one: '$n card');
    return '$_temp0';
  }

  @override
  String planMinutesCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n minutes',
      one: '$n minute',
    );
    return '$_temp0';
  }

  @override
  String get planPlateStageWords => 'Words';

  @override
  String get planPlateStagePhrases => 'Phrases';

  @override
  String get planPlateStageDialog => 'Dialogue';

  @override
  String get planPlateStageListen => 'Listen and answer';

  @override
  String get planPlateStageSpeak => 'Speak myself';

  @override
  String get planPlateStateDone => 'done';

  @override
  String get planPlateStateCurrent => 'in progress';

  @override
  String get planPlateStateAhead => 'ahead';

  @override
  String planNewWordsCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n new words',
      one: '$n new word',
    );
    return '$_temp0';
  }

  @override
  String planPlateStageSubStart(String words) {
    return 'start here · $words';
  }

  @override
  String planPlateStageSubUnfinished(String cards) {
    return 'unfinished · $cards';
  }

  @override
  String get planPlateCtaStart => 'Start';

  @override
  String planPlateLabelCatchUp(int n) {
    return 'Day $n · catching up';
  }

  @override
  String planPlateBuildingTitle(int n) {
    return 'Building day $n';
  }

  @override
  String get planPlateBuildingSub => 'about a minute · you can close the app';

  @override
  String get planPlateFailedTitle => 'Couldn’t put the day together';

  @override
  String get planPlateCtaRetry => 'Try again';

  @override
  String get planPlateCtaContinue => 'Continue';

  @override
  String planClosedTitle(int n) {
    return 'Day $n closed';
  }

  @override
  String planClosedCount(String cards, String minutes) {
    return '$cards · $minutes';
  }

  @override
  String planClosedNextTomorrow(int n, String date) {
    return 'Day $n opens tomorrow, $date';
  }

  @override
  String planClosedNextOn(int n, String date) {
    return 'Day $n opens $date';
  }

  @override
  String planClosedReturn(int n, int k) {
    String _temp0 = intl.Intl.pluralLogic(
      k,
      locale: localeName,
      other: '$k cards return on day $n →',
      one: '$k card returns on day $n →',
    );
    return '$_temp0';
  }

  @override
  String planRouteDayRepeatSub(int a, int b) {
    return 'words and phrases of days $a–$b';
  }

  @override
  String planRouteDayRepeatSubOne(int a) {
    return 'words and phrases of day $a';
  }

  @override
  String planRouteDayTitle(int n, String title) {
    return 'Day $n · $title';
  }

  @override
  String get planRouteDayReview => 'Review';

  @override
  String get planRouteDayRehearsal => 'Rehearsal';

  @override
  String get planRouteDayRehearsalSub => 'the whole route out loud';

  @override
  String get planRouteMetaPassed => 'done';

  @override
  String planMinutesShort(int n) {
    return '$n min';
  }

  @override
  String planRouteMetaOpensAfter(int n) {
    return 'opens after day $n';
  }

  @override
  String get planRouteMetaOpensTomorrow => 'opens tomorrow';

  @override
  String get planRouteEventNoDate => 'set a date';

  @override
  String get planRouteEventFallback => 'Event';

  @override
  String get planDoneTitle => 'Plan completed';

  @override
  String planDaysCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n days', one: '$n day');
    return '$_temp0';
  }

  @override
  String planDoneMeta(String days) {
    return '$days';
  }

  @override
  String planDoneCollection(String name) {
    return 'The plan\'s words and phrases stay in the collection “$name” — they will keep coming back for review';
  }

  @override
  String get planDoneCta => 'Build a new plan';

  @override
  String get planDoneCtaReadonly => 'Open the collection';

  @override
  String get planMenuDate => 'Change the date';

  @override
  String get planMenuNew => 'Build a new plan';

  @override
  String get planMenuCollection => 'Open the collection';

  @override
  String get planMenuDelete => 'Delete the plan';

  @override
  String get planMenuLabel => 'Plan menu';

  @override
  String planDateTitle(String event) {
    return 'When is the $event?';
  }

  @override
  String get planDateTitleNoEvent => 'When is the event?';

  @override
  String planDateOptionCurrent(String date) {
    return '$date · as it is';
  }

  @override
  String get planDateOptionOther => 'Another date';

  @override
  String get planDateOptionOtherSub => 'pick in the calendar';

  @override
  String get planDateCta => 'Apply';

  @override
  String get planDateCancel => 'Cancel';

  @override
  String get planDateRemove => 'No date';

  @override
  String get planNewTitle => 'Start a different plan?';

  @override
  String planNewBody(int n, int total, String name) {
    return 'This plan will end on day $n of $total. Everything already in progress stays in the collection “$name” and keeps coming back for review';
  }

  @override
  String get planNewCta => 'Build a new one';

  @override
  String get planNewKeep => 'Keep this one';

  @override
  String get planDeleteTitle => 'Delete the plan?';

  @override
  String planDeleteBody(String name) {
    return 'The plan disappears from the history. The collection “$name” and its words stay';
  }

  @override
  String get planDeleteBodyNoCollection => 'The plan disappears from the history';

  @override
  String get planDeleteConfirm => 'Delete';

  @override
  String planOverdueMeta(String days, int total) {
    return 'Completed $days of $total';
  }

  @override
  String get planOverdueFinish => 'Finish the plan';

  @override
  String planOverdueContinue(String days) {
    return 'Keep going · $days';
  }

  @override
  String planHintFirstStart(String stage) {
    return 'Start with the “$stage” stage. The rest open in order';
  }

  @override
  String planHintFirstRoute(int n) {
    return 'Day $n is open. The next one opens once you finish this one';
  }

  @override
  String planRebuiltTitle(int from, int to) {
    return 'The route was rebuilt: $from days became $to';
  }

  @override
  String get planHintFirstReturn => 'These cards come back the next day — that is how they stick';

  @override
  String get planSheetTitle => 'How the plan works';

  @override
  String get planSheetCta => 'Got it';

  @override
  String get planTabOffline => 'no network';

  @override
  String get planTabLoadFailedTitle => 'Could not load the plan';

  @override
  String get planTabRetry => 'Retry';

  @override
  String get planEntryNext => 'Next';

  @override
  String get planEntryGoalTitle => 'What are you getting ready for?';

  @override
  String get planEntryGoalSub =>
      'Tell the situation in your own words: what happens, who you talk to, what worries you';

  @override
  String get planEntryGoalExample1 =>
      'Job interview as a cook on Friday. Worked three years in a restaurant';

  @override
  String get planEntryGoalExample2 =>
      'Doctor\'s appointment in Berlin — my back hurts, I need a prescription';

  @override
  String get planEntryGoalExample3 => 'Renting a flat in Lisbon for a year, with a dog';

  @override
  String get planEntryGoalExample4 => 'Calling the bank: my card is blocked, I\'m not a resident';

  @override
  String get planEntryGoalStoriesTitle => 'How others put it';

  @override
  String get planEntryGoalStory1 =>
      'Job interview as a cook on Friday — three years in a restaurant, afraid of questions about experience';

  @override
  String get planEntryGoalStory2 =>
      'To the doctor with my child in Berlin, first time at the local clinic — my son has a fever';

  @override
  String get planEntryGoalStory3 =>
      'Call to the landlord about the deposit — renting for a year, moving out in May';

  @override
  String get planEntryGoalCompanion =>
      'Who you are and what matters — job, experience, city. The plan is built around it.';

  @override
  String get planEntryGoalCompanionShort =>
      'Add a few words about yourself: your job, your experience — and the plan will be about you';

  @override
  String get planEntryGoalDictate => 'or dictate it';

  @override
  String get planEntryGoalDictateEdit => 'you can edit it by hand';

  @override
  String planEntryGoalListening(String time) {
    return '$time · go ahead, I\'m listening';
  }

  @override
  String get planEntryTapeGoal => 'Goal';

  @override
  String get planEntryTapeLanguage => 'Language';

  @override
  String get planEntryTapeDays => 'Days';

  @override
  String planEntryTapeLanguageValue(String language, String level) {
    return '$language · $level';
  }

  @override
  String get planEntryLanguageTitle => 'Which language will you speak?';

  @override
  String get planEntryLanguageLabel => 'Language';

  @override
  String get planEntryLevelLabel => 'Level';

  @override
  String get planEntryLevelBeginner => 'Beginner';

  @override
  String get planEntryLevelBeginnerSub => 'I know some words';

  @override
  String get planEntryLevelIntermediate => 'Intermediate';

  @override
  String get planEntryLevelIntermediateSub => 'I follow simple speech, I speak with mistakes';

  @override
  String get planEntryDaysTitle => 'How many days until the conversation?';

  @override
  String planEntryDaysScenes(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n situations',
      one: '$n situation',
    );
    return '$_temp0';
  }

  @override
  String planEntryDaysReviews(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n reviews',
      one: '$n review',
    );
    return '$_temp0';
  }

  @override
  String get planEntryDaysRehearsal => 'rehearsal';

  @override
  String get planEntryDateTitle => 'When is the conversation?';

  @override
  String get planEntryDateLabel => 'Date';

  @override
  String planEntryDateIn(String weekday, int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'in $n days',
      one: 'in $n day',
    );
    return '$weekday · $_temp0';
  }

  @override
  String get planEntryDateUnknown => 'No date yet';

  @override
  String get planEntryDateUnknownSub => 'a plan with no date, days run back to back';

  @override
  String get planEntryDateOther => 'Another date';

  @override
  String get planEntryDateOtherSub => 'pick in the calendar';

  @override
  String planEntryDateRehearsalOn(String date) {
    return 'The rehearsal lands on $date — the day before the conversation';
  }

  @override
  String get planEntryDateCta => 'Build the plan';

  @override
  String get planEntryPreviewTitle => 'Your plan is ready';

  @override
  String get planEntryPreviewLoadingTitle => 'Building your plan';

  @override
  String get planEntryPreviewAbout => 'About 10 seconds';

  @override
  String get planEntryPreviewLine1 => 'Picking situations for your conversation';

  @override
  String get planEntryPreviewLine2 => 'Laying them out by day';

  @override
  String get planEntryPreviewLine3 => 'Gathering words and phrases';

  @override
  String get planEntryPreviewHowLabel => 'How it will go';

  @override
  String get planEntryPreviewEdit => 'Change';

  @override
  String get planEntryPreviewCta => 'Start';

  @override
  String get planEntryPreviewErrorTitle => 'The plan did not build';

  @override
  String get planEntryPreviewErrorWhat => 'The network dropped halfway';

  @override
  String get planEntryPreviewErrorSub =>
      'Your answers are saved — try again, no need to retell anything';

  @override
  String get planEntryPreviewErrorRetry => 'Try again';

  @override
  String get planEntryPreviewUnclearTitle => 'A little more, please';

  @override
  String planEntryPreviewUnclearQuote(String goal) {
    return '“$goal” — what is it about?';
  }

  @override
  String get planEntryPreviewUnclearSub =>
      'Say where you will be speaking and with whom: a doctor\'s visit, a call to the bank, a chat with a neighbour';

  @override
  String get planEntryPreviewUnclearCta => 'Back to the goal';

  @override
  String get planEntryPushTitle => 'Plan is ready';

  @override
  String planEntryPushBody(String until, String dayTitle) {
    return '$until. Day 1 — “$dayTitle”';
  }

  @override
  String planEntryPushBodyNoDate(String days, String dayTitle) {
    return '$days. Day 1 — “$dayTitle”';
  }

  @override
  String planNotifyDayReadyTitle(int n) {
    return 'Day $n is ready';
  }

  @override
  String planNotifyDayReadyBody(String title) {
    return '“$title” — you can start';
  }

  @override
  String planNotifyReminderTitle(int n) {
    return 'Day $n is waiting';
  }

  @override
  String planNotifyReminderBody(String title) {
    return '“$title” — pick up where you left off';
  }

  @override
  String planNotifyEventTodayTitle(String event) {
    return 'Today: $event';
  }

  @override
  String get planNotifyEventTodayTitleNoName => 'Today is the conversation';

  @override
  String get planNotifyEventTodayBody => 'Say it yourself first — run it out loud';

  @override
  String planNotifySkippedTitle(int n) {
    return 'Day $n has been waiting since yesterday';
  }

  @override
  String get planNotifySkippedBody => 'The route has moved: the next days start from today';

  @override
  String get planBannerNow => 'now';

  @override
  String get planBannerAppMark => 'S';

  @override
  String get planEntryOffline => 'The plan cannot be built without a network';

  @override
  String get planWindowBack => 'Back';

  @override
  String get planWindowStateNotStarted => 'not started';

  @override
  String get planWindowStateInProgress => 'in progress';

  @override
  String planWindowApprox(String minutes) {
    return '≈ $minutes';
  }

  @override
  String planWindowJoin(String first, String second) {
    return '$first · $second';
  }

  @override
  String planWindowJoinNumber(String first, String second) {
    return '$first · $second';
  }

  @override
  String planWindowPassedLine(String minutes) {
    return 'Day passed · $minutes';
  }

  @override
  String planWindowBrowDone(int n) {
    return '$n passed';
  }

  @override
  String planWindowBrowReturns(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n return tomorrow',
      one: '$n returns tomorrow',
    );
    return '$_temp0';
  }

  @override
  String get planWindowListen => 'Listen';

  @override
  String planWindowGoalsLearn(String goals) {
    return 'You will learn to $goals';
  }

  @override
  String get planWindowGoalsLearned => 'Learned:';

  @override
  String planWindowGoalsJoin(String head, String next) {
    return '$head, $next';
  }

  @override
  String planWindowGoalsJoinLast(String head, String last) {
    return '$head and $last';
  }

  @override
  String get planWindowSheetTalk => 'In the conversation';

  @override
  String get planWindowSheetNotStarted => 'not started';

  @override
  String planWindowSheetReturnsOn(int n) {
    return 'comes back on day $n';
  }

  @override
  String get planWindowSheetReturnsTomorrow => 'comes back tomorrow';

  @override
  String get planWindowSheetClose => 'Close';

  @override
  String dayMinutes(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n minutes',
      one: '$n minute',
    );
    return '$_temp0';
  }

  @override
  String get planSessionStateDone => 'done';

  @override
  String get planSessionStateAhead => 'ahead';

  @override
  String planSessionApproxMinutes(int n) {
    return '≈ $n min';
  }

  @override
  String planSessionDescWords(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n words of the day',
      one: '$n word of the day',
    );
    return '$_temp0 — look, listen and say them aloud';
  }

  @override
  String planSessionDescPhrases(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n phrases of the day',
      one: '$n phrase of the day',
    );
    return '$_temp0 — one slot changes, the phrase stays';
  }

  @override
  String get planSessionDescDialogue =>
      'The conversation step by step: understand the line and answer with the day\'s phrases';

  @override
  String get planSessionDescListen => 'The whole conversation by ear, then questions about it';

  @override
  String get planSessionDescSpeak =>
      'They ask — answer about yourself, in your own words. You know the frames; the slot is yours';

  @override
  String get planSessionNoHints => 'No hints';

  @override
  String get planSessionNoHintsSub => 'Dialogue and “Speak myself” go straight to voice';

  @override
  String get planSessionNoHintsTalk => 'In the talk — no hints, the partner\'s text stays closed';

  @override
  String get planSessionStart => 'Start';

  @override
  String planSessionBuild(String version) {
    return 'build $version';
  }

  @override
  String planSessionLeftWords(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n words left',
      one: '$n word left',
    );
    return '$_temp0';
  }

  @override
  String planSessionLeftPhrases(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n phrases left',
      one: '$n phrase left',
    );
    return '$_temp0';
  }

  @override
  String planSessionSceneLine(String scene, String role) {
    return '$scene · $role';
  }

  @override
  String get planSessionClose => 'Close';

  @override
  String get planSessionTaskRememberWord => 'Remember the word';

  @override
  String get planSessionTaskSayWord => 'Say the word aloud';

  @override
  String planSessionCompanionSayWord(String role) {
    return 'Say it the way you hear it — $role will understand';
  }

  @override
  String get planSessionTaskChooseTranslation => 'Choose the translation';

  @override
  String get planSessionTaskChooseWord => 'Choose the word';

  @override
  String get planSessionTaskChooseHeard => 'Choose what you heard';

  @override
  String get planSessionTaskAssembleParts => 'Build it from parts';

  @override
  String get planSessionTaskInsertWord => 'Put the word in the slot';

  @override
  String get planSessionTaskLookListen => 'Look and listen';

  @override
  String get planSessionTaskAssemblePhrase => 'Build the phrase';

  @override
  String get planSessionTaskInsert => 'Fill the slot';

  @override
  String get planSessionTaskSayPhrase => 'Say the phrase aloud';

  @override
  String get planSessionTaskSayEachMeaning => 'Say the phrase with each meaning';

  @override
  String get planSessionOwnWordNow => 'and now with your own word';

  @override
  String get planSessionStateReplay => 'replay';

  @override
  String get planSessionTaskWhatAnswer => 'What will you answer?';

  @override
  String get planSessionBrowWord => 'Word';

  @override
  String get planSessionBrowTranslation => 'Translation';

  @override
  String get planSessionBrowByEar => 'By ear';

  @override
  String get planSessionBrowSlot => 'Slot';

  @override
  String get planSessionBrowFrame => 'Frame';

  @override
  String get planSessionBrowPhrase => 'Phrase';

  @override
  String get planSessionBrowAssembled => 'Assembled';

  @override
  String planSessionBrowPartnerAsks(String role) {
    return '$role · asks';
  }

  @override
  String get planSessionWhatHeard => 'What did you hear?';

  @override
  String get planSessionUnderstood => 'Got it';

  @override
  String get planSessionCheck => 'Check';

  @override
  String get planSessionNext => 'Next';

  @override
  String get planSessionByParts => 'in parts';

  @override
  String get planSessionMicTap => 'tap to speak';

  @override
  String get planSessionMicListening => 'go ahead, I\'m listening';

  @override
  String get planSessionMicHeard => 'heard';

  @override
  String get planSessionPlaying => 'playing';

  @override
  String get planSessionMicMissed => 'didn\'t catch that, once more';

  @override
  String get planSessionEcho => 'heard — just like the recording';

  @override
  String get planSessionSkip => 'Skip';

  @override
  String get planSessionDebugHeard => 'what was heard';

  @override
  String get planSessionNoMicTitle => 'Microphone needed';

  @override
  String get planSessionNoMicBody =>
      'Without it, “Listen and answer” and “Speak myself” can\'t be done. Words and phrases work with choices and tiles.';

  @override
  String get planSessionNoMicAllow => 'Allow';

  @override
  String get planSessionTryAgain => 'Try again';

  @override
  String get planSessionReturnsTomorrow => 'Coming back tomorrow';

  @override
  String get planSessionExitTitle => 'Leave? Your progress is saved';

  @override
  String planSessionExitBody(String stage) {
    return 'The cards you\'ve done in “$stage” stay done — you\'ll pick up where you left off.';
  }

  @override
  String get planSessionExitStay => 'Continue';

  @override
  String get planSessionExitLeave => 'Leave';

  @override
  String get planSessionOffline =>
      'no connection — the answer will be sent once you\'re back online';

  @override
  String get planSessionLoadFailed => 'The day didn\'t load';

  @override
  String planSessionLeftExchanges(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n lines left',
      one: '$n line left',
    );
    return '$_temp0';
  }

  @override
  String planSessionLeftQuestions(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n questions left',
      one: '$n question left',
    );
    return '$_temp0';
  }

  @override
  String get planSessionLastQuestion => 'last question';

  @override
  String get planSessionWholeTalk => 'the whole conversation';

  @override
  String get planSessionReviewLabel => 'review';

  @override
  String get planSessionPaceLabel => 'pace';

  @override
  String get planSessionTaskCollectAnswer => 'Build your answer';

  @override
  String get planSessionTaskSayLine => 'Say your line';

  @override
  String get planSessionTaskRescue => 'Didn\'t catch it — ask again';

  @override
  String get planSessionAnyChip => 'any of them is your answer';

  @override
  String get planSessionNotUnderstood => 'Didn\'t catch that';

  @override
  String get planSessionSlowly => 'slowly';

  @override
  String get planSessionTaskListenTalk => 'Listen to the conversation';

  @override
  String get planSessionByPartsAction => 'In parts';

  @override
  String get planSessionContinue => 'Continue';

  @override
  String get planSessionReplay => 'Once more';

  @override
  String planSessionExchangesCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n exchanges',
      one: '$n exchange',
    );
    return '$_temp0';
  }

  @override
  String planSessionPlayerPaused(int n) {
    return 'paused · exchange $n';
  }

  @override
  String get planSessionPause => 'Pause';

  @override
  String planSessionPlayerRoles(String role) {
    return '$role and you';
  }

  @override
  String get planSessionPlayerDone => 'listened to the end';

  @override
  String get planSessionYou => 'you';

  @override
  String get planSessionTaskWhatUnderstood => 'What did you understand?';

  @override
  String get planSessionBrowQuestion => 'Question';

  @override
  String get planSessionFromMemory => 'from memory · no sound';

  @override
  String get planSessionTaskWhereHeard => 'Where it was said';

  @override
  String get planSessionTaskListenChoose => 'Listen and choose the answer';

  @override
  String get planSessionThisIsAnswer => 'This is the answer';

  @override
  String get planSessionTaskNormalPace => 'Now at normal speed';

  @override
  String planSessionBrowSlowRate(String rate) {
    return 'Slow · $rate×';
  }

  @override
  String get planSessionBrowNormalPace => 'Normal speed';

  @override
  String get planSessionTextOpen => 'text shown';

  @override
  String get planSessionTextClosed => 'text hidden';

  @override
  String get planSessionUnderstoodAction => 'Got it';

  @override
  String get planSessionTaskCatchNumber => 'Catch the number';

  @override
  String get planSessionWhichNumber => 'Which number did you hear?';

  @override
  String get planSessionTaskAskYourself => 'Ask, in your own words';

  @override
  String planSessionAskIntent(String text) {
    return 'Ask: «$text»';
  }

  @override
  String get planSessionTaskAnswerOwnWords => 'Answer in your own words';

  @override
  String get planSessionTaskRepeatPause => 'Repeat after a pause';

  @override
  String get planSessionTaskRetell => 'Say in your language what you heard';

  @override
  String get planSessionBrowLine => 'Line';

  @override
  String get planSessionListenCue => 'listen';

  @override
  String get planSessionWaiting => 'waiting';

  @override
  String get planSessionNotThat => 'not that — let\'s try again';

  @override
  String get planSessionHintAction => 'Hint';

  @override
  String get planSessionDayTotal => 'Day total';

  @override
  String planSessionDayDoneTitle(String minutes) {
    return 'Day done · $minutes';
  }

  @override
  String get planSessionCloseDay => 'Close the day';

  @override
  String planSessionDayReturns(String cards, String parts) {
    return '$cards: $parts.';
  }

  @override
  String planSessionReturnWords(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n words', one: '$n word');
    return '$_temp0';
  }

  @override
  String planSessionReturnPhrases(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n phrases',
      one: '$n phrase',
    );
    return '$_temp0';
  }

  @override
  String planSessionReturnExchanges(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n lines', one: '$n line');
    return '$_temp0';
  }

  @override
  String planSessionAnd(String a, String b) {
    return '$a and $b';
  }

  @override
  String planSessionNextDayBuilding(int n) {
    return 'Day $n — building';
  }

  @override
  String planSessionNextDayReady(int n) {
    return 'Day $n — ready';
  }

  @override
  String get planSessionCloseFailed => 'The day didn\'t close — no connection';

  @override
  String get planPlateStageTalk => 'Talk';

  @override
  String get planPlateStageRecall => 'Recall';

  @override
  String planTalkEntryMinutes(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'about $n minutes',
      one: 'about $n minute',
    );
    return '$_temp0';
  }

  @override
  String get planTalkEntryWhole => 'The whole talk';

  @override
  String planTalkEntryScenes(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n scenes',
      one: '$n scene',
    );
    return '$_temp0';
  }

  @override
  String get planTalkEntryRuleStart => 'They speak first. Answer, and ask your own questions.';

  @override
  String planTalkEntryRuleRescue(String gender) {
    String _temp0 = intl.Intl.selectLogic(gender, {'female': 'she', 'male': 'he', 'other': 'they'});
    return 'Lost the thread — tap «Didn\'t get it» and $_temp0 will say it more simply.';
  }

  @override
  String get planTalkEntryRuleCounts =>
      'What counts: what you said, the day\'s phrases, the questions you got.';

  @override
  String get planTalkStart => 'Start the talk';

  @override
  String get planTalkRescueAction => 'Didn\'t get it';

  @override
  String planTalkHintChip(String intent) {
    return 'Say that $intent';
  }

  @override
  String get planTalkOpenText => 'text';

  @override
  String get planTalkInterrupted => 'cut short';

  @override
  String get planTalkSilenceEnds => 'silence ends it';

  @override
  String get planTalkUnheard => 'didn\'t catch that — say it again';

  @override
  String get planTalkOffline => 'Connection lost — the talk goes on from here';

  @override
  String get planTalkSilent => 'No answer — try once more';

  @override
  String get planTalkOpenFailed => 'The talk did not start — try once more';

  @override
  String get planTalkNotInDay => 'This day has no talk';

  @override
  String get planTalkEnded => 'Talk over';

  @override
  String get planTalkSummaryAction => 'Summary';

  @override
  String planTalkSaidLines(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'You said $n lines yourself',
      one: 'You said $n line yourself',
    );
    return '$_temp0';
  }

  @override
  String get planTalkUnderstoodAll => 'You got every question';

  @override
  String planTalkUnderstoodExcept(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'You got the questions but $n — they come back',
      one: 'You got the questions but one — it comes back',
    );
    return '$_temp0';
  }

  @override
  String planTalkRescues(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'asked again $n times',
      one: 'asked again $n time',
    );
    return '$_temp0';
  }

  @override
  String get planTalkReady => 'You are ready for the conversation';

  @override
  String get planTalkHighlights => 'What went well';

  @override
  String get planSessionListenWholeOne => 'Listen to the whole of it — only one answer fits';

  @override
  String get planSessionFrameWhole => 'This phrase is said whole — nothing in it changes';

  @override
  String get planSessionChangeable => 'this part can change';

  @override
  String get planSessionInTalk => 'In the talk';

  @override
  String get planSessionOwnWordChip => 'your own word';

  @override
  String get planTalkNoMic => 'Microphone needed — the talk can\'t go on without it';

  @override
  String get planSessionDescRecall =>
      'Your own lines from every scene — look, listen and say them aloud';

  @override
  String get planSessionRecallTask => 'Recall your lines';

  @override
  String get planSessionRecallLast => 'Next — say them aloud';

  @override
  String get planWindowRehearsalBefore => 'before the event';

  @override
  String planWindowOnWeekday(String weekday) {
    String _temp0 = intl.Intl.selectLogic(weekday, {
      'mon': 'on Monday',
      'tue': 'on Tuesday',
      'wed': 'on Wednesday',
      'thu': 'on Thursday',
      'fri': 'on Friday',
      'sat': 'on Saturday',
      'sun': 'on Sunday',
      'other': '',
    });
    return '$_temp0';
  }

  @override
  String get planWindowReviewTitle => 'What came before';

  @override
  String get planWindowRehearsalLead => 'You will talk the whole conversation through';

  @override
  String get planWindowReviewLead =>
      'You will bring back the phrases of past days and talk them through';

  @override
  String get planWindowFromScenes => 'From these scenes';

  @override
  String get planWindowFromDays => 'From these days';

  @override
  String get planSessionTaskUnderstood => 'Check what you understood';

  @override
  String planSessionHeardLine(String text) {
    return 'heard: $text';
  }

  @override
  String get planPlateStageRepetition => 'Review';

  @override
  String get planSessionPassedWords => 'Words done';

  @override
  String get planSessionPassedPhrases => 'Phrases done';

  @override
  String get planSessionPassedDialogue => 'Dialogue done';

  @override
  String get planSessionPassedListen => 'Listen and answer — done';

  @override
  String get planSessionPassedSpeak => 'Speak myself — done';

  @override
  String get planSessionPassedRecall => 'Recall — done';

  @override
  String get planSessionPassedRepetition => 'Review done';

  @override
  String planSessionPassedMinutes(String title, String minutes) {
    return '$title · $minutes';
  }

  @override
  String planSessionFirstTryWords(int n, int first) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n words, $first at the first try',
      one: '$n word, $first at the first try',
    );
    return '$_temp0';
  }

  @override
  String planSessionFirstTryPhrases(int n, int first) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n phrases, $first at the first try',
      one: '$n phrase, $first at the first try',
    );
    return '$_temp0';
  }

  @override
  String planSessionFirstTryLines(int n, int first) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n lines, $first at the first try',
      one: '$n line, $first at the first try',
    );
    return '$_temp0';
  }

  @override
  String planSessionFirstTryQuestions(int n, int first) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n questions, $first at the first try',
      one: '$n question, $first at the first try',
    );
    return '$_temp0';
  }

  @override
  String planSessionFirstTryCards(int n, int first) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n cards, $first at the first try',
      one: '$n card, $first at the first try',
    );
    return '$_temp0';
  }

  @override
  String planSessionRecallLinesOfScenes(int n, int scenes) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n lines', one: '$n line');
    String _temp1 = intl.Intl.pluralLogic(
      scenes,
      locale: localeName,
      other: '$scenes scenes',
      one: '$scenes scene',
    );
    return '$_temp0 from $_temp1';
  }

  @override
  String planSessionStageReturns(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n come back tomorrow',
      one: '$n comes back tomorrow',
    );
    return '$_temp0';
  }

  @override
  String get planSessionStageNoReturns => 'nothing comes back tomorrow';

  @override
  String planSessionSaidAloud(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'said $n lines of your own aloud',
      one: 'said $n line of your own aloud',
    );
    return '$_temp0';
  }

  @override
  String planSessionRescuedSlower(int n, String role) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'asked again $n times — the $role said it slower',
      two: 'asked again twice — the $role said it slower',
      one: 'asked again — the $role said it slower',
    );
    return '$_temp0';
  }

  @override
  String get planSessionWarmWords => 'You know these words now — next they turn up in phrases';

  @override
  String get planSessionWarmPhrases =>
      'The phrases are built and said aloud — they will come in handy in the dialogue';

  @override
  String get planSessionWarmListen => 'You understand the other side by ear';

  @override
  String get planSessionWarmSpeak => 'You said your lines yourself — next comes a live talk';

  @override
  String get planSessionWarmRecall => 'Your lines are in place — next comes the whole talk';

  @override
  String get planSessionWarmRepetition => 'Everything that came back has been said once more';

  @override
  String get planTalkEntrySay => 'Say in the talk';

  @override
  String get planVoiceTitle => 'Which voice should say your lines?';

  @override
  String get planVoiceMale => 'Male';

  @override
  String get planVoiceMaleHint => 'lower and calmer';

  @override
  String get planVoiceFemale => 'Female';

  @override
  String get planVoiceFemaleHint => 'higher and softer';

  @override
  String get planVoiceInProfile => 'You can change it in your profile';

  @override
  String get planTalkConstructions => 'Constructions in the talk';

  @override
  String planTalkYouSaid(String said) {
    return 'you said: $said';
  }

  @override
  String get planTalkRepeatBefore => 'go over it before the conversation';

  @override
  String planTalkAlmostLine(String line) {
    return 'almost — say it whole: $line';
  }

  @override
  String planTalkFromLessonLine(String line) {
    return 'from the lesson: $line';
  }

  @override
  String get planTalkAlmostJudge => 'Almost — say it whole';

  @override
  String get planTalkExtraSaid => 'You also used';

  @override
  String get planTalkEndedByTime => 'The talk ran out of time — what you did not say comes back';

  @override
  String get planTalkEndedByTimeOnly => 'The talk ran out of time';

  @override
  String planTalkSceneNumber(int n) {
    return 'Scene $n';
  }

  @override
  String planTalkSceneOf(int n, int total) {
    return 'Scene $n of $total';
  }

  @override
  String get planTalkSceneContinue => 'Continue';

  @override
  String planTalkEntryRuleStartRole(String role, String gender) {
    String _temp0 = intl.Intl.selectLogic(gender, {
      'female': '$role speaks first.',
      'other': '$role speaks first.',
    });
    return '$_temp0 Answer, and ask your own questions.';
  }

  @override
  String get planTalkEntrySceneByScene => 'The talk goes scene by scene';

  @override
  String planWindowReturnedFromDay(int n) {
    return 'Back from day $n';
  }

  @override
  String get planWindowReturnedFrom => 'Back from earlier';

  @override
  String get planWindowStageAgain => 'once more';

  @override
  String get planWindowTalkLimitToday => 'limit for today';

  @override
  String get planWindowDaySummary => 'Day summary';

  @override
  String get planWindowSummary => 'Summary';

  @override
  String get planWindowTalkReplayLimit =>
      'The talk was already replayed today — come back tomorrow';

  @override
  String get startSlogan => 'Prepared to speak.';

  @override
  String get startSignInApple => 'Sign in with Apple';

  @override
  String get startSignInGoogle => 'Sign in with Google';

  @override
  String get startSignInFailed => 'Couldn’t sign in. Try again';

  @override
  String startLegal(String terms, String privacy) {
    return 'By continuing you accept the $terms and $privacy';
  }

  @override
  String get startLegalTerms => 'Terms';

  @override
  String get startLegalPrivacy => 'Privacy Policy';

  @override
  String get introSkip => 'Skip';

  @override
  String get introStart => 'Start';

  @override
  String get introATitle => 'A big conversation\nis coming.\nYou’ll be ready.';

  @override
  String get introAThought =>
      'A doctor, a landlord, an interview, the bank. Name the event — the plan builds itself around it.';

  @override
  String get introALineNative => '';

  @override
  String get introSceneAirport => 'airport';

  @override
  String get introSceneBank => 'bank';

  @override
  String get introSceneInterview => 'interview';

  @override
  String get introSceneRent => 'rent';

  @override
  String get introSceneDoctor => 'doctor';

  @override
  String get introBTitle => 'A language\nfor every conversation.';

  @override
  String get introBThought =>
      'English, German, Romanian, Polish, Spanish, Italian, French. Pick it per plan; hints stay in your own language.';

  @override
  String get introCBrow => '6 days · 20 min a day';

  @override
  String get introCToday => 'today';

  @override
  String get introCDate => 'October 2';

  @override
  String get introCEvent => 'doctor’s appointment';

  @override
  String get introStageWords => 'Words';

  @override
  String get introStagePhrases => 'Phrases';

  @override
  String get introStageDialogue => 'Dialogue';

  @override
  String get introStageListen => 'Listen';

  @override
  String get introStageSpeak => 'Speak';

  @override
  String get introStageTalk => 'Talk';

  @override
  String get introCTitle => 'Twenty minutes a day —\nout loud.';

  @override
  String get introCThought =>
      'Words, phrases, dialogue: you listen and speak, not type. Exactly the days left before the event.';

  @override
  String get introDLineNative => '';

  @override
  String get introDTap => 'tap to speak';

  @override
  String get introDTitle => 'A live conversation\nwith AI — no script.';

  @override
  String get introDThought =>
      'It hears what you said and answers that. Ask again, wander off — it keeps up.';

  @override
  String get introECaption => 'three sets from the store · your own words from plans';

  @override
  String get introECoverCity => 'Home and city';

  @override
  String get introECoverHealth => 'Health';

  @override
  String get introECoverWork => 'Work';

  @override
  String introEWordCount(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count words',
      one: '$count word',
    );
    return '$_temp0';
  }

  @override
  String get introETitle => 'Your words\nstay with you.';

  @override
  String get introEThought =>
      'Your own collections and ready-made sets. Review between plans — nothing gets lost.';

  @override
  String get accountSignedInApple => 'Signed in with Apple';

  @override
  String get accountSignedInGoogle => 'Signed in with Google';

  @override
  String get accountGroupSubscription => 'Subscription';

  @override
  String get accountFree => 'Free';

  @override
  String get accountFreeValue => 'one plan, day 1';

  @override
  String get accountPremium => 'Premium';

  @override
  String accountPremiumUntil(String date) {
    return 'renews $date';
  }

  @override
  String get accountPremiumForever => 'no end date';

  @override
  String get accountManageSubscription => 'Manage subscription';

  @override
  String get accountRestorePurchases => 'Restore purchases';

  @override
  String get accountGroupLearning => 'Learning';

  @override
  String get accountVoice => 'Learner voice';

  @override
  String get accountVoiceMale => 'male';

  @override
  String get accountVoiceFemale => 'female';

  @override
  String get accountSessionSounds => 'Session sounds';

  @override
  String get accountUiLanguage => 'App language';

  @override
  String get accountUiRussian => 'Русский';

  @override
  String get accountUiEnglish => 'English';

  @override
  String get accountNativeLanguage => 'Native language';

  @override
  String get accountGroupReminders => 'Reminders';

  @override
  String get accountRemind => 'Remind me about the day';

  @override
  String get accountTime => 'Time';

  @override
  String get accountGroupApp => 'App';

  @override
  String get accountTerms => 'Terms';

  @override
  String get accountPrivacy => 'Privacy';

  @override
  String get accountSupport => 'Support';

  @override
  String get accountSupportValue => 'email';

  @override
  String get accountRate => 'Rate Ritora';

  @override
  String get accountSignOut => 'Sign out';

  @override
  String get accountDelete => 'Delete account';

  @override
  String get accountNameTitle => 'Name';

  @override
  String get accountNameHint => 'What’s your name';

  @override
  String get accountDone => 'Done';

  @override
  String get accountDeleteTitle => 'Delete account?';

  @override
  String accountDeleteBodyPlan(String plan) {
    return 'Your plan “$plan”, the days you’ve done and your settings will be gone. They can’t be restored.';
  }

  @override
  String get accountDeleteBody =>
      'The days you’ve done and your settings will be gone. They can’t be restored.';

  @override
  String get accountDeleteNote => 'Cancel your subscription in the App Store';

  @override
  String get accountDeleteFailed => 'Couldn’t delete. Try again';

  @override
  String get accountDeleting => 'Deleting…';

  @override
  String get accountRemindersTitle => 'Reminders';

  @override
  String get accountRemindersNote =>
      'We remind you once a day, when the next day of the plan is waiting';

  @override
  String get accountRemindersDenied => 'Notifications are off in iOS Settings — turn them on there';

  @override
  String notifyAskTitle(int day, String time) {
    return 'Remind you about day $day tomorrow at $time?';
  }

  @override
  String get notifyAskBody => 'Once a day, nothing more';

  @override
  String get notifyAskLater => 'Not now';

  @override
  String get notifyAskYes => 'Remind me';

  @override
  String get micAskTitle => 'Ritora listens to how you speak';

  @override
  String get micAskBody =>
      'The microphone is how you speak: in Dialogue, Speak and the conversation';

  @override
  String get micAskLater => 'Later';

  @override
  String get micAskAllow => 'Allow microphone';

  @override
  String get planPlateNoNetwork => 'No network';

  @override
  String get planPlateBySubscription => 'with a subscription';

  @override
  String get planPlateOpensWithSubscription => 'Opens with a subscription';

  @override
  String get planPlateSubscription => 'Subscription';

  @override
  String get planRouteMetaOpensWithSubscription => 'opens with a subscription';

  @override
  String get planRouteMetaBySubscription => 'with a subscription';

  @override
  String get planKitLabel => 'Rescue kit';

  @override
  String planKitSub(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n lines for any moment',
      one: '$n line for any moment',
    );
    return '$_temp0';
  }

  @override
  String planKitAll(int n) {
    return 'all $n →';
  }

  @override
  String get planKitCollapse => 'collapse';

  @override
  String get planEntrySubscriptionTitle => 'A second plan comes with a subscription';

  @override
  String get planEntryActiveLimitTitle => 'No more than three plans at once';

  @override
  String get planEntryToTab => 'Back to the plan';
}
