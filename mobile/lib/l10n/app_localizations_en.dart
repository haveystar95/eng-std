// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for English (`en`).
class AppLocalizationsEn extends AppLocalizations {
  AppLocalizationsEn([String locale = 'en']) : super(locale);

  @override
  String triageCounter(int current, int total) {
    return '$current of $total';
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
  String get triageAllDoneTitle => 'All triaged';

  @override
  String get triageAllDoneBody => 'No new words left to triage in this set.';

  @override
  String get triageMoreLaterTitle => 'That\'s all for now';

  @override
  String triageMoreLaterBody(int count) {
    return '$count more after syncing — come back when you\'re online.';
  }

  @override
  String get triageDone => 'Done';

  @override
  String get triageSummaryBatchTitle => 'Batch triaged';

  @override
  String get triageSummaryDoneTitle => 'Triage complete';

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
      other: '$count more after syncing',
      one: '$count more after syncing',
    );
    return '$_temp0';
  }

  @override
  String triageLoadError(String error) {
    return 'Couldn\'t load: $error';
  }

  @override
  String get homeGeneratePlaceholder => 'e.g. a visit to the doctor';

  @override
  String get homeGenerateChipDoctor => 'At the doctor';

  @override
  String get homeGenerateChipRent => 'Renting';

  @override
  String get homeGenerateChipInterview => 'Job interview';

  @override
  String homeCollectionProgress(int done, int total) {
    String _temp0 = intl.Intl.pluralLogic(
      total,
      locale: localeName,
      other: '$done of $total words',
      one: '$done of $total word',
    );
    return '$_temp0';
  }

  @override
  String get tabHome => 'Home';

  @override
  String get tabCollections => 'Collections';

  @override
  String get tabProfile => 'Profile';

  @override
  String get homeSessionTitle => 'Session';

  @override
  String collectionWordsCount(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count words',
      one: '$count word',
    );
    return '$_temp0';
  }

  @override
  String collectionDueSuffix(int count) {
    return '$count due today';
  }

  @override
  String collectionDensityConfirmed(int count) {
    return 'Confirmed $count';
  }

  @override
  String collectionDensityFamiliar(int count) {
    return 'Familiar $count';
  }

  @override
  String collectionDensityInProgress(int count) {
    return 'In progress $count';
  }

  @override
  String collectionTriageButton(int count) {
    return 'Triage $count';
  }

  @override
  String get collectionTriageSubtitle => 'New words in this set';

  @override
  String collectionLearnButton(int count) {
    return 'Learn $count';
  }

  @override
  String get collectionLearnSubtitle => 'New words — learn them';

  @override
  String collectionReviewButton(int count) {
    return 'Review $count';
  }

  @override
  String get collectionReviewSubtitle => 'Due for review';

  @override
  String get collectionPracticeButton => 'Free practice';

  @override
  String get collectionPracticeSubtitle => 'Nothing urgent — just practice';

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
      'A reference collection: read the words and hear them. There are no trainers for this language yet.';

  @override
  String get collectionAddWord => 'Add a word';

  @override
  String get collectionEmptyTitle => 'No words yet';

  @override
  String get collectionEmptyBody => 'Tap “Add a word” to start';

  @override
  String get collectionTriageBannerTitle => 'Triage the set';

  @override
  String get collectionTriageBannerBody => 'Mark what you already know — the rest goes to practice';

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
  String get wordSheetAddTitle => 'Add a word';

  @override
  String get wordSheetEditTitle => 'Edit word';

  @override
  String get wordFieldTerm => 'Term';

  @override
  String get wordFieldTranslation => 'Translation';

  @override
  String get wordTermHint => 'word or phrase';

  @override
  String get wordTranslationHintOptional => 'optional — we\'ll fill it in';

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
  String collectionDefaultUndeletable(String title) {
    return '“$title” is where saved words land, so it cannot be deleted. Renaming it is fine.';
  }

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
      'The set leaves «Mine». Its words and your progress are kept — you can add it again from the store.';

  @override
  String collectionDeleteTitle(String title) {
    return 'Delete “$title”?';
  }

  @override
  String get collectionDeleteMessage =>
      'The collection goes; the words stay in training. A word leaves training only from its own card.';

  @override
  String get commonCancel => 'Cancel';

  @override
  String get commonCloseMenu => 'Close menu';

  @override
  String approxWords(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count words',
      one: '$count word',
    );
    return '$_temp0';
  }

  @override
  String get collectionsTitle => 'Collections';

  @override
  String get collectionsEmptyTitle => 'No collections yet';

  @override
  String get collectionsEmptyBody => 'Describe a situation — AI will build your first set.';

  @override
  String get collectionsCreateManual => 'Create manually';

  @override
  String get collectionsCreateManualHint => 'An empty collection — you add the words';

  @override
  String get collectionsCreateGenerate => 'Generate';

  @override
  String get collectionsCreateGenerateHint => 'AI builds a set from a situation you describe';

  @override
  String get collectionsNewCollection => 'New collection';

  @override
  String collectionsTileMastered(int count, int mastered) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count words',
      one: '$count word',
    );
    return '$_temp0 · $mastered mastered';
  }

  @override
  String get generationGeneratingTitle => 'Building the collection…';

  @override
  String generationGeneratingMeta(String topic, String levels, String size) {
    return '$topic · $levels · $size';
  }

  @override
  String get generationGeneratingNote => 'Picking words and photos · usually 20–30 seconds';

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
    return '“$topic” wasn\'t created. The limit resets at $time — you can retry then.';
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
    return 'Ready — loading “$topic”…';
  }

  @override
  String generationUnderBadge(int delivered, int requested) {
    return '$delivered of $requested';
  }

  @override
  String get generationReadyUnder => 'Ready · fewer than asked';

  @override
  String get generateScreenTitle => 'New collection';

  @override
  String get generateSituationLabel => 'Describe the situation';

  @override
  String get generateSituationHelper =>
      'The more specific the situation, the sharper the set. E.g. “first doctor\'s visit — symptoms and lab tests”.';

  @override
  String get generatePlaceholder0 => 'Renting a flat — talking to the agent';

  @override
  String get generatePlaceholder1 => 'First doctor\'s visit — symptoms and tests';

  @override
  String get generatePlaceholder2 => 'IT interview — talking through projects';

  @override
  String get generatePlaceholder3 => 'Opening a bank account';

  @override
  String get generatePlaceholder4 => 'Ordering food at a café';

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
      other: '$count generations left today',
      one: '$count generation left today',
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
  String get generatePremiumUpsell => 'Need more? Premium — up to 20 a day';

  @override
  String get generateManual => 'Build a collection manually';

  @override
  String generateVoiceListening(String time) {
    return 'Listening · $time';
  }

  @override
  String get generateVoiceStop => 'Stop';

  @override
  String get generateVoiceHelper =>
      'Text appears in the field as it\'s recognised — after you stop you can edit it by hand.';

  @override
  String get generateVoiceRecordingNote => 'Speak — the keyboard returns when you stop';

  @override
  String get generateVoicePermissionDenied =>
      'Microphone and speech recognition access is needed — enable it in Settings';

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
  String get tabSearch => 'Search';

  @override
  String get searchTitle => 'Word search';

  @override
  String get searchFieldHint => 'Find a word';

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
  String get searchBuildCardNote => 'Meaning and example. Again — free';

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
      'A couple of seconds. You can close this — the card will be in search.';

  @override
  String searchLimitUsed(int used, int cap) {
    return '$used of $cap today';
  }

  @override
  String get searchLimitTitle => 'Model-written cards come back at midnight';

  @override
  String get searchLookupFailed => 'Could not look this word up';

  @override
  String get searchNotRecognized => 'Couldn’t make that out — check the spelling';

  @override
  String get searchQueryTooLong => 'Search is for words and short phrases';

  @override
  String get searchSaveToDefault => '+ Saved';

  @override
  String searchAlreadyIn(String collection) {
    return 'Already in “$collection”';
  }

  @override
  String searchSavedTo(String collection) {
    return 'Saved to “$collection” — it is being studied now';
  }

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
      '“Saved” is a collection of another pair. Pick a collection of this pair, or make a new one.';

  @override
  String get searchPairMismatchTitle => 'A word of another language';

  @override
  String searchPairMismatchMessage(String expected, String actual) {
    return 'This collection studies $expected, and the word is in $actual. One collection, one pair — so this word needs a collection of its own pair.';
  }

  @override
  String get searchPairMismatchCreate => 'Make a collection';

  @override
  String get wordCardExampleLabel => 'Example';

  @override
  String wordCardAlso(String words) {
    return 'also: $words';
  }

  @override
  String get wordCardFolderHint => 'On the right — pick another collection';

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
    return '$step of $total';
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
  String get tabProgress => 'Progress';

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
      other: 'Best result — $count days',
      one: 'Best result — $count day',
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
      other: 'All $count words',
      one: 'All $count word',
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
      'No connection. Reviews work as usual — we\'ll sync when you\'re back online.';

  @override
  String get homeGenerateOfflineNote =>
      'Generation needs a connection. Your topic is saved and will run once you\'re back online.';

  @override
  String get appWordmark => 'Слова';

  @override
  String get authTagline => 'Words for real situations — from the bank to a job interview.';

  @override
  String get authContinueGoogle => 'Continue with Google';

  @override
  String get authContinueApple => 'Continue with Apple';

  @override
  String get authTerms => 'Terms';

  @override
  String get authPrivacy => 'Privacy';

  @override
  String get authOfflineHint => 'No connection. The first sign-in needs the network.';

  @override
  String get authAppleUnavailable => 'Sign in with Apple isn\'t available yet.';

  @override
  String get onbLangTitle => 'Which language are you learning?';

  @override
  String get onbLangSubtitle => 'You can change it in your profile anytime.';

  @override
  String get onbLevelTitle => 'How confidently do you read?';

  @override
  String get onbLevelSubtitle => 'Roughly — we\'ll refine it from your triage answers.';

  @override
  String onbLevelExample(String level) {
    return 'At $level, collections include words like “wire transfer” and “make ends meet”.';
  }

  @override
  String get onbGoalTitle => 'How many words a day?';

  @override
  String get onbGoalSubtitle => 'The goal only affects reminders and progress.';

  @override
  String onbGoalMinutes(int count) {
    return '≈ $count min a day';
  }

  @override
  String get onbGoalRecommended => 'recommended';

  @override
  String get onbFooterNote =>
      'All of this lives in your profile — level, goal and language aren\'t locked behind onboarding.';

  @override
  String get onbNext => 'Next';

  @override
  String get onbStart => 'Start';

  @override
  String get cefrHintA1 => 'beginner';

  @override
  String get cefrHintA2 => 'elementary';

  @override
  String get cefrHintB1 => 'intermediate';

  @override
  String get cefrHintB2 => 'upper';

  @override
  String get cefrHintC1 => 'advanced';

  @override
  String get cefrHintC2 => 'near-native';

  @override
  String get profileTitle => 'Profile';

  @override
  String get profileSectionLearning => 'Learning';

  @override
  String get profileSectionApp => 'App';

  @override
  String get profileSectionSubscription => 'Subscription';

  @override
  String get profileSectionAccount => 'Account';

  @override
  String get profileRowLevel => 'Level';

  @override
  String get profileRowGoal => 'Daily goal';

  @override
  String get profileRowTargetLang => 'Learning language';

  @override
  String get profileRowUiLang => 'Interface language';

  @override
  String get profileRowAutoPronounce => 'Auto-pronounce';

  @override
  String get profileAutoPronounceHint => 'Speak the word when the card appears';

  @override
  String get profileRowTransliteration => 'Pronunciation hint';

  @override
  String get profileTransliterationHint => 'Show how the word reads, in your own letters';

  @override
  String get profileRowReminders => 'Reminders';

  @override
  String get profileRemindersHint => 'One a day, when there\'s something to review';

  @override
  String get profileRowReminderTime => 'Time';

  @override
  String get profileFreeTier => 'Free tier';

  @override
  String get profileFreeTierHint => '3 generations a day';

  @override
  String get profileSoon => 'Soon';

  @override
  String get profileSignOut => 'Sign out';

  @override
  String get profileDeleteAccount => 'Delete account';

  @override
  String profileGoalValue(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count words',
      one: '$count word',
    );
    return '$_temp0';
  }

  @override
  String get uiLangSystem => 'System';

  @override
  String get uiLangRussian => 'Русский';

  @override
  String get uiLangEnglish => 'English';

  @override
  String get profileUiLangSheet => 'Interface language';

  @override
  String get profileLevelSheet => 'Level';

  @override
  String get profileGoalSheet => 'Daily goal';

  @override
  String get reminderSheetTitle => 'When to remind you';

  @override
  String get reminderSheetSubtitle =>
      'It works best at a time when you usually have five free minutes.';

  @override
  String get commonSave => 'Save';

  @override
  String get deleteAccountTitle => 'Delete account?';

  @override
  String deleteAccountBody(String words, String streak) {
    return 'All data and progress will be erased permanently: $words, $streak and all collections.';
  }

  @override
  String deleteAccountWords(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count words',
      one: '$count word',
    );
    return '$_temp0';
  }

  @override
  String deleteAccountStreak(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count-day streak',
      one: '$count-day streak',
    );
    return '$_temp0';
  }

  @override
  String get deleteAccountConfirm => 'Delete';

  @override
  String get sessionPhaseIntro => 'Getting to know';

  @override
  String get sessionPhaseAssemble => 'Assemble';

  @override
  String get sessionPhaseReview => 'Review';

  @override
  String get sessionPhasePractice => 'Free practice';

  @override
  String get sessionInstrChoose => 'choose the English equivalent';

  @override
  String get sessionInstrAssemble => 'assemble it from the words';

  @override
  String get sessionAssemblyEmptyHint => 'Tap the words below';

  @override
  String get sessionInstrAssembleSentence => 'put the sentence together';

  @override
  String get sessionInstrType => 'write it in English';

  @override
  String get sessionInstrListenChoose => 'listen and choose the translation · replay any time';

  @override
  String get sessionInstrListenType => 'listen and write it in English';

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
  String get sessionChipReturnHint => 'Tap a word in the line to send it back';

  @override
  String get sessionHintFirstLetter => 'Hint: first letter';

  @override
  String get sessionDontRemember => 'Don\'t remember';

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
  String get sessionSpeakStart => 'Speak';

  @override
  String get sessionSpeakStop => 'Done';

  @override
  String get sessionSpeakListening => 'Listening…';

  @override
  String get sessionSpeakNotHeard => 'Didn\'t catch that. Try again — a little closer to the mic.';

  @override
  String get sessionSpeakNoMic => 'The microphone isn\'t available. You can skip this card.';

  @override
  String get sessionSpeakSkip => 'Skip';

  @override
  String get sessionSpeakSkipHint =>
      'Skipping costs nothing — the word will come back in its own time.';

  @override
  String get sessionSpeakHint =>
      'We\'re checking that you remembered the word, not how you pronounce it.';

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
  String get sessionEchoEnable => 'Turn on the microphone';

  @override
  String get sessionHeaderIntro => 'First look';

  @override
  String get sessionHeaderRecognition => 'Recognition';

  @override
  String get sessionInstrRecogniseTranslation => 'choose the translation';

  @override
  String get sessionRecogniseJustMet => 'you have just met this word';

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
  String get ladderTitle => 'WORD LADDER';

  @override
  String get ladderKnownDash => 'known';

  @override
  String get ladderTrainWord => 'Train this word';

  @override
  String get ladderTrainLockedIntro =>
      'This word opens for practice once you have met it in a study session.';

  @override
  String get sessionNext => 'Next';

  @override
  String get sessionDone => 'Done';

  @override
  String get sessionFeedbackCorrect => 'Correct';

  @override
  String get sessionFeedbackAlmost => 'Almost:';

  @override
  String get sessionFeedbackWrong => 'Not quite — the correct form is below';

  @override
  String get sessionFeedbackWrongAbove => 'Not quite — the correct answer is marked above';

  @override
  String get sessionDueToday => 'today';

  @override
  String get sessionDueTomorrow => 'tomorrow';

  @override
  String sessionDueInDays(int days) {
    String _temp0 = intl.Intl.pluralLogic(
      days,
      locale: localeName,
      other: 'in $days days',
      one: 'in $days day',
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
      other: 'Streak — $days days',
      one: 'Streak — $days day',
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
      'This one\'s tricky. Try a different example — sometimes it\'s the context, not the word.';

  @override
  String get sessionNewExample => 'New example';

  @override
  String get sessionNewExampleExhausted => 'You\'ve used today\'s examples';

  @override
  String get sessionPracticeBanner => 'Free practice — progress doesn\'t change';

  @override
  String get sessionExitTitle => 'End the session?';

  @override
  String get sessionExitBody => 'Answered words are saved — you can come back any time.';

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
  String sessionLoadError(String error) {
    return 'Couldn\'t load the session: $error';
  }

  @override
  String get authErrorOffline => 'No internet connection. Signing in needs a network.';

  @override
  String get authErrorGoogleUnsupported => 'Google sign-in isn\'t supported on this platform.';

  @override
  String get authErrorCancelled => 'Sign-in cancelled.';

  @override
  String get authErrorGoogle => 'Google sign-in failed. Please try again.';

  @override
  String get authErrorGoogleToken => 'Couldn\'t get a Google token.';

  @override
  String get authErrorLoginFailed => 'Couldn\'t sign in. Please try again.';

  @override
  String get authErrorApple => 'Sign in with Apple isn\'t available yet.';

  @override
  String get authErrorAppleToken => 'Couldn\'t get an Apple token.';

  @override
  String get practiceDialogEntry => 'Conversation · 3 min';

  @override
  String get practiceDialogEntrySubtitle => 'Voice practice with AI';

  @override
  String get practiceDialogOfflineHint => 'Needs internet';

  @override
  String get practiceDialogPrestartTitle => 'Talk with the AI';

  @override
  String practiceDialogPrestartBody(String lang) {
    return 'The AI will speak with you in the collection\'s language — $lang. Answer out loud and try to use these words.';
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
  String get practiceDialogExitMessage => 'The conversation will end and you\'ll see a recap.';

  @override
  String get practiceDialogExitConfirm => 'End';

  @override
  String get practiceDialogExitCancel => 'Keep going';

  @override
  String get practiceDialogFinaleTitle => 'Conversation over';

  @override
  String practiceDialogFinaleWords(int used, int total) {
    return 'Words used: $used of $total';
  }

  @override
  String get practiceDialogFinaleDone => 'Done';

  @override
  String get practiceDialogErrorSubscription => 'Conversations are a Premium feature.';

  @override
  String practiceDialogErrorRateLimited(String time) {
    return 'No conversations left today. More after $time.';
  }

  @override
  String get practiceDialogErrorRateLimitedNoTime =>
      'No conversations left today. Try again tomorrow.';

  @override
  String get practiceDialogErrorOffline => 'You\'re offline. A conversation needs internet.';

  @override
  String get practiceDialogErrorGeneric => 'Couldn\'t start the conversation. Please try again.';

  @override
  String get practiceDialogClose => 'Close';

  @override
  String get practiceDialogRepeat => 'Practice again';

  @override
  String practiceDialogResultWords(int used, int total) {
    return 'words: $used of $total';
  }

  @override
  String get storeSegmentMine => 'Mine';

  @override
  String get storeSegmentReady => 'Ready-made';

  @override
  String get storeSectionOther => 'Other';

  @override
  String storeWordsCount(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count words',
      one: '$count word',
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
    return 'Unlocks all $count sets at once';
  }

  @override
  String get storeInsideLabel => 'What\'s inside';

  @override
  String storeMoreWords(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count more words',
      one: '$count more word',
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
    return '$title and $count more sets';
  }

  @override
  String get paywallSubtitleQuota => 'Premium raises the daily limit to twenty generations.';

  @override
  String get paywallSubtitleStore =>
      'Premium collections are curated and all unlock at once — we don\'t sell them one by one.';

  @override
  String get paywallSubtitleGeneric => 'One plan unlocks everything that makes learning faster.';

  @override
  String get paywallBenefitGenerations => 'Up to 20 generations a day';

  @override
  String get paywallBenefitStore => 'Every premium collection in the store';

  @override
  String get paywallBenefitModes => 'Future training modes';

  @override
  String get paywallFreeForever => 'Reviews, triage and offline — always free.';

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
    return 'Subscription renews automatically. $price per year is charged to your Apple ID; cancel in App Store settings at least 24 hours before the period ends.';
  }

  @override
  String paywallLegalMonth(String price) {
    return 'Subscription renews automatically. $price per month is charged to your Apple ID; cancel in App Store settings at least 24 hours before the period ends.';
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
  String get paywallNeedsRealPremium => 'Needs real Premium (StoreKit is a separate block)';

  @override
  String get profileTryPremium => 'Try Premium';

  @override
  String profileFreeTierReset(String time) {
    return '3 generations a day · resets at $time';
  }

  @override
  String get profilePremiumActive => 'Premium';

  @override
  String get profilePremiumBadge => 'active';

  @override
  String get profilePremiumHint => 'Subscription active';

  @override
  String get profileManageSubscription => 'Manage subscription';

  @override
  String get profileRestorePurchases => 'Restore purchases';

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
  String get perfMonitorToggleHint => 'Off by default — costs nothing while off';

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
  String get syncStuckBanner => 'Answers aren\'t reaching the server — check your connection';

  @override
  String get poolNotStudyingNote => 'This word is in the catalogue — you are not studying it yet.';

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
      'The word stops coming up in your sessions. Its progress and history are kept — you can bring it back at any time.';

  @override
  String get poolUnenrollConfirm => 'Stop';

  @override
  String get poolInCatalogue => 'in the catalogue';

  @override
  String get myWordsTitle => 'My words';

  @override
  String myWordsCount(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count words',
      one: '$count word',
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
      'Words land here when you sweep a collection with “don’t know” or “not sure” — or tap “Learn this word” on a word card.';

  @override
  String get myWordsNothingFound => 'Nothing found';

  @override
  String get topicSessionAction => 'Session by topic';

  @override
  String get topicSessionTitle => 'Pick a topic';

  @override
  String homeStreakBadge(int count) {
    return 'Streak $count';
  }

  @override
  String get homeSessionCardTitle => 'Today\'s session';

  @override
  String homeSessionCardWords(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count words',
      one: '$count word',
    );
    return '$_temp0';
  }

  @override
  String homeSessionCardMinutes(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '≈ $count minutes',
      one: '≈ $count minute',
    );
    return '$_temp0';
  }

  @override
  String homeSessionPartRepeat(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count to review',
      one: '$count to review',
    );
    return '$_temp0';
  }

  @override
  String homeSessionPartNew(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count new',
      one: '$count new',
    );
    return '$_temp0';
  }

  @override
  String homeSessionPartTriage(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count to sort',
      one: '$count to sort',
    );
    return '$_temp0';
  }

  @override
  String get homeSessionStart => 'Start';

  @override
  String homeInWorkTitle(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'In progress — $count words',
      one: 'In progress — $count word',
    );
    return '$_temp0';
  }

  @override
  String homeInWorkWaiting(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count waiting in line',
      one: '$count waiting in line',
    );
    return '$_temp0';
  }

  @override
  String homeInWorkPace(int perDay, int days) {
    String _temp0 = intl.Intl.pluralLogic(
      days,
      locale: localeName,
      other: 'at $perDay new a day the queue clears in ~$days days',
      one: 'at $perDay new a day the queue clears in ~$days day',
    );
    return '$_temp0';
  }

  @override
  String homeInWorkQueueStands(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'take $count now and the queue moves today',
      one: 'take $count now and the queue moves today',
    );
    return '$_temp0';
  }

  @override
  String get homeEdgeTitle => 'About to slip';

  @override
  String get homeEdgeTomorrow => 'due tomorrow';

  @override
  String homeEdgeInDays(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'in $count days',
      one: 'in $count day',
    );
    return '$_temp0';
  }

  @override
  String get homeHardestTitle => 'Hardest today';

  @override
  String homeHardestErrors(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count mistakes',
      one: '$count mistake',
    );
    return '$_temp0';
  }

  @override
  String homeSectionCount(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count words',
      one: '$count word',
    );
    return '$_temp0';
  }

  @override
  String get homeDoneTitle => 'Done for today';

  @override
  String homeDoneOf(int done, int total) {
    return '$done of $total';
  }

  @override
  String homeDoneDuration(int minutes, int seconds) {
    return '$minutes min $seconds s';
  }

  @override
  String homeDoneDurationSeconds(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count seconds',
      one: '$count second',
    );
    return '$_temp0';
  }

  @override
  String get homeIdleTitle => 'All reviewed';

  @override
  String get homeIdleTakeNew => 'Take new words';

  @override
  String get homeIdleQueueStalled => 'No new words taken today — the queue is standing still.';

  @override
  String homeNextReviewLine(String when, int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Next review $when, $count words.',
      one: 'Next review $when, $count word.',
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
      other: 'You can add $count more words from “$title”.',
      one: 'You can add $count more word from “$title”.',
    );
    return '$_temp0';
  }

  @override
  String homeExtraButton(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count more words',
      one: '$count more word',
    );
    return '$_temp0';
  }

  @override
  String get homeContinueLabel => 'Continue';

  @override
  String homeContinueAbandoned(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'left $count days ago',
      one: 'left $count day ago',
    );
    return '$_temp0';
  }

  @override
  String get homeGenerateRow => 'Build a collection on a topic';

  @override
  String homeStoreLink(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'or pick from $count ready-made',
      one: 'or pick from $count ready-made',
    );
    return '$_temp0';
  }

  @override
  String get homeFirstDayTitle => 'Let\'s start with a first set';

  @override
  String homeFirstDayReadyTitle(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Take a ready-made set ($count topics)',
      one: 'Take a ready-made set ($count topic)',
    );
    return '$_temp0';
  }

  @override
  String get homeFirstDayReadyHint => 'The words are already chosen, voiced and levelled';

  @override
  String get homeFirstDayOwnTitle => 'Build your own from a description';

  @override
  String get homeFirstDayOwnHint =>
      'Describe a situation — AI will pick the words and phrases for it';

  @override
  String homeSortOffer(int count, String title) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'You can sort $count more words from “$title”',
      one: 'You can sort $count more word from “$title”',
    );
    return '$_temp0';
  }

  @override
  String homeTriageAction(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Sort $count words',
      one: 'Sort $count word',
    );
    return '$_temp0';
  }

  @override
  String get homeSortFirstTitle => 'Time to sort your words';
}
