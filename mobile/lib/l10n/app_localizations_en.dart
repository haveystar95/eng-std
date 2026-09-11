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
  String get triageAllDoneTitle => 'All sorted';

  @override
  String get triageAllDoneBody => 'No new words left to sort in this set.';

  @override
  String get triageMoreLaterTitle => 'That\'s all for now';

  @override
  String triageMoreLaterBody(int count) {
    return '$count more after syncing — come back when you\'re online.';
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
  String get collectionTriageBannerTitle => 'Sort the set';

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
  String get homeUnreachableTitle => 'The server isn\'t answering';

  @override
  String get homeUnreachableBody =>
      'Today\'s plan couldn\'t be loaded. Pull down to try again — everything saved is still here.';

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
  String get onbLevelSubtitle => 'Roughly — we\'ll refine it from how you sort your words.';

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
  String get sessionChipReturnHint => 'Tap a word in the line to send it back';

  @override
  String get sessionHintFirstLetter => 'Hint: first letter';

  @override
  String get sessionDontRemember => 'Don\'t remember';

  @override
  String get sessionWrongKeyboard =>
      'That looks like the wrong keyboard — switch it and type again';

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
      'Speech recognition is off. Allow speech recognition in Settings — without it the phone hears you but cannot understand.';

  @override
  String get speechPermissionMicDenied =>
      'The microphone is off. Allow microphone access in Settings.';

  @override
  String get speechPermissionBothDenied =>
      'Allow the microphone and speech recognition in Settings — both are needed.';

  @override
  String get speechPermissionOpenSettings => 'Open Settings';

  @override
  String get sessionSpeakCutOff => 'Didn\'t catch the whole thing — say it again.';

  @override
  String get sessionSpeakSkip => 'Skip';

  @override
  String get sessionSpeakSkipHint =>
      'Skipping costs nothing — the word will come back in its own time.';

  @override
  String get sessionSpeakYourTurn => 'Your turn — tap and speak';

  @override
  String get sessionSpeakWaitForRole => 'They\'re still speaking';

  @override
  String get sessionSpeakRecording => 'Recording — say it, then tap Done';

  @override
  String get sessionSpeakVerdictCorrect => 'Right';

  @override
  String sessionSpeakVerdictAlmost(String words) {
    return 'Almost — missing: $words';
  }

  @override
  String get sessionSpeakVerdictWrong => 'Not that';

  @override
  String get sessionSpeakHint =>
      'We\'re checking that you remembered the word, not how you pronounce it.';

  @override
  String sessionSpeakHintKey(String key) {
    return 'Say the line — what counts is “$key”.';
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
  String get statusToSort => 'To sort';

  @override
  String get statusInWork => 'In progress';

  @override
  String get statusMastered => 'Mastered';

  @override
  String get statusPaused => 'Paused';

  @override
  String statusLadderStep(int step, int total, String rung) {
    return 'Step $step of $total: $rung';
  }

  @override
  String statusCountToSort(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Sort $count words',
      one: 'Sort $count word',
    );
    return '$_temp0';
  }

  @override
  String get statusLegendTitle => 'What the dots mean';

  @override
  String get poolKnownLegend => 'Marked “I know it” — it never walked the ladder.';

  @override
  String get ladderTitle => 'WORD LADDER';

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
  String get sessionInstrSituationalHear => 'choose what they said';

  @override
  String get sessionInstrSituationalSay => 'choose what you will say';

  @override
  String get sessionInstrAssembleTurn => 'put your reply together from the blocks';

  @override
  String get sessionInstrSituationalAsk => 'choose what you will ask';

  @override
  String get sessionSituationLabel => 'Situation';

  @override
  String get sessionSituationHearLabel => 'You are about to hear';

  @override
  String get sessionSituationRevealText => 'Show the text';

  @override
  String sessionSituationTask(String outcome) {
    return 'Your task — $outcome.';
  }

  @override
  String get planSittingContinue => 'Continue';

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
  String get paywallFreeForever => 'Reviews, sorting and offline — always free.';

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
  String get syncUnreachableBanner => 'Server unreachable · showing what\'s saved';

  @override
  String get poolNotStudyingNote => 'This word is on the shelf — you are not studying it yet.';

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
  String get challengeLabel => 'Word challenge';

  @override
  String challengeStreak(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count in a row',
      one: '$count in a row',
    );
    return '$_temp0';
  }

  @override
  String get challengeStreakReset => 'streak reset';

  @override
  String get challengePraise => 'You know it!';

  @override
  String challengeAnswer(String term, String translation) {
    return '$term — $translation';
  }

  @override
  String challengeExample(String sentence, String translation) {
    return '$sentence — $translation';
  }

  @override
  String challengeMistake(String chosen, String term) {
    return 'You picked “$chosen” — that is $term';
  }

  @override
  String get challengeLearn => 'Learn it';

  @override
  String get challengeTomorrow => 'New one tomorrow';

  @override
  String get challengeCollapsed => 'A new word tomorrow';

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
  String sessionSizeCards(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count cards',
      one: '$count card',
    );
    return '~$_temp0';
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
  String homeDoneOfWords(int done, int total) {
    String _temp0 = intl.Intl.pluralLogic(
      total,
      locale: localeName,
      other: '$done of $total words',
      one: '$done of $total word',
    );
    return '$_temp0';
  }

  @override
  String homeDoneCards(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count cards',
      one: '$count card',
    );
    return '$_temp0';
  }

  @override
  String homeDoneMinutes(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count min',
      one: '$count min',
    );
    return '$_temp0';
  }

  @override
  String homeChainProgress(int position, int total) {
    String _temp0 = intl.Intl.pluralLogic(
      total,
      locale: localeName,
      other: 'card $position of $total',
      one: 'card $position of $total',
    );
    return '$_temp0';
  }

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
  String homeExtraNew(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'You can take $count words already waiting in the queue.',
      one: 'You can take $count word already waiting in the queue.',
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
      other: 'or take one of $count ready-made',
      one: 'or take one of $count ready-made',
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
      other: '$count words come back tomorrow',
      one: '$count word comes back tomorrow',
    );
    return '$_temp0';
  }

  @override
  String homeAwardPromoted(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '+$count words moved up',
      one: '+$count word moved up',
    );
    return '$_temp0';
  }

  @override
  String homeAwardExample(String term, String rung) {
    return '$term reached “$rung”';
  }

  @override
  String get homeGenerateCardTitle => 'Generate a set';

  @override
  String get homeGenerateCardHint => 'Describe the situation — we’ll build a set for it';

  @override
  String get homeStoreShowcaseTitle => 'Ready-made sets';

  @override
  String homeStoreShowcaseAll(int count) {
    return 'all $count';
  }

  @override
  String get homeFirstDayPromise => '5 minutes a day — 20 words a week';

  @override
  String get homeGenerateChipVet => 'At the vet';

  @override
  String get homeGenerateChipMoving => 'Moving abroad';

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

  @override
  String get onbNativeTitle => 'Which language should translations be in?';

  @override
  String get onbNativeSubtitle =>
      'Translations, explanations and preparation plans use it. You can change it in your profile.';

  @override
  String get profileRowNativeLang => 'Native language';

  @override
  String get profileNativeLangHint => 'Existing collections stay as they are';

  @override
  String profileNativeLangConfirmTitle(String language) {
    return 'Translations in $language?';
  }

  @override
  String get profileNativeLangConfirmBody =>
      'New collections and plans will use it. Existing collections stay as they are — their translations are not rewritten.';

  @override
  String get tabPlan => 'Plan';

  @override
  String get searchOpen => 'Search';

  @override
  String get commonBack => 'Back';

  @override
  String get planStepEdit => 'Edit';

  @override
  String get planLevelZero => 'From scratch';

  @override
  String get planLevelBasic => 'I understand simple things';

  @override
  String get planLevelConversational => 'I can explain myself';

  @override
  String get planLevelFluent => 'Fluently';

  @override
  String get planWhenSheetTitle => 'When will it happen?';

  @override
  String get planBuilderBusyLine => 'Working through the goal — usually 15–30 seconds';

  @override
  String get planErrorOffline =>
      'No connection. The plan is built on the server — try again when you are online.';

  @override
  String get planErrorBuildFailed => 'The plan could not be built. Try again.';

  @override
  String get planErrorStartFailed => 'The plan could not be started. Try again.';

  @override
  String get planErrorLoadFailed => 'The plan could not be loaded.';

  @override
  String planDayNumber(int index) {
    return 'Day $index';
  }

  @override
  String planDaysCount(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count days',
      one: '$count day',
    );
    return '$_temp0';
  }

  @override
  String get planPreviewDropDay => 'Drop a day';

  @override
  String get planPricePlaceholder => '[price / terms — placeholder]';

  @override
  String get planDropDaySheet => 'Which day to drop?';

  @override
  String get planDropLastDay => 'The last preparation day cannot be dropped.';

  @override
  String planTightTitle(int days, int minutes) {
    String _temp0 = intl.Intl.pluralLogic(
      days,
      locale: localeName,
      other: 'In $days days at $minutes minutes we will cover half: these abilities.',
      one: 'In $days day at $minutes minutes we will cover half: these abilities.',
    );
    return '$_temp0';
  }

  @override
  String planTightAddMinutes(int minutes) {
    return 'Add $minutes minutes a day';
  }

  @override
  String get planTightKeep => 'Keep it';

  @override
  String planBuildingTitle(int index) {
    return 'Building day $index';
  }

  @override
  String get planBuildingBody => 'Choosing the lines you will say and the words that go into them.';

  @override
  String get planBuildingStep1 => 'Goal parsed';

  @override
  String get planBuildingStep2 => 'Lines chosen';

  @override
  String get planBuildingStep3 => 'Words and examples';

  @override
  String get planBuildingFailed =>
      'This day is taking longer than usual. The plan already exists — you can open it and come back to the day later.';

  @override
  String get planBuildingOpenAnyway => 'Open the plan';

  @override
  String planDayOfTotal(int index, int total) {
    return 'Day $index of $total';
  }

  @override
  String planCanAlready(int hit, int total) {
    return 'You can already · $hit of $total';
  }

  @override
  String planDaysHeader(int index, int total) {
    return 'Days · $index of $total';
  }

  @override
  String planDayOfPlan(int index, int total) {
    return 'Day $index of $total';
  }

  @override
  String get planRescueHint => 'for when you didn\'t understand or didn\'t catch it';

  @override
  String get planAbandonTitle => 'Give up on this plan?';

  @override
  String get planAbandonBody =>
      'The plan goes to the archive and its words into general review. The days that did build stay as ordinary collections.';

  @override
  String get planAbandonConfirm => 'Give up';

  @override
  String get planBuildingRefused =>
      'The server refused to build this day. There is nothing left to wait for — open the plan: the day\'s own screen says what actually happened.';

  @override
  String get planRowStartDay => 'Start the day';

  @override
  String get planMaturityMeeting => 'Meeting the words and phrases';

  @override
  String get planMaturityApplying => 'Using it in conversation';

  @override
  String get planMaturitySpeaking => 'You say it yourself';

  @override
  String get planEmptyTitle => 'Prepare for something specific';

  @override
  String get planEmptyBody =>
      'Collections are about topics you want to know. A plan is about the day you will have to speak: an appointment, an interview, signing a contract.';

  @override
  String get planEmptyStep1 => 'You name the goal and the date';

  @override
  String get planEmptyStep2 => 'Every day — phrases you will actually say, and the words in them';

  @override
  String get planEmptyStep3 => 'At the end — a role conversation and the whole situation out loud';

  @override
  String get planEmptyCta => 'Make a plan';

  @override
  String get planFinishedBadge => 'Preparation complete';

  @override
  String planFinishedSummary(int days, String date) {
    String _temp0 = intl.Intl.pluralLogic(
      days,
      locale: localeName,
      other: '$days days of preparation. The event was on $date.',
      one: '$days day of preparation. The event was on $date.',
    );
    return '$_temp0';
  }

  @override
  String get planWordsReleasedTitle => 'The plan\'s words stayed in the archive';

  @override
  String get planWordsReleasedBody =>
      'Nothing was lost — the progress, the schedule and the whole history are still there. They will not come back into your daily sessions on their own: to take one back into study, open its card and tap “Learn this word”.';

  @override
  String get planArchive => 'Archive';

  @override
  String get planFinishedNewPlan => 'Make a new one';

  @override
  String homePlanCardBadge(int index, int total) {
    return 'Plan · day $index of $total';
  }

  @override
  String homePlanCardEventIn(int days) {
    String _temp0 = intl.Intl.pluralLogic(
      days,
      locale: localeName,
      other: 'Event in $days days',
      one: 'Event in $days day',
    );
    return '$_temp0';
  }

  @override
  String get homePlanCardEventToday => 'Event today';

  @override
  String homePlanCardReadiness(int hit, int total) {
    return 'ready for the event · $hit of $total';
  }

  @override
  String get homePlanCardContinue => 'Continue';

  @override
  String get homePlanInviteTitle => 'Got a date and a goal?';

  @override
  String get homePlanInviteBody =>
      'We will build the preparation days — from a doctor\'s appointment to a job interview.';

  @override
  String get homePlanInviteCta => 'Make one';

  @override
  String get planNotifyChannelName => 'Preparation plan';

  @override
  String get planNotifyChannelBody => 'Reminders before the event you are preparing for';

  @override
  String planNotifyBeforeTitle(int days, int index) {
    String _temp0 = intl.Intl.pluralLogic(
      days,
      locale: localeName,
      other: '$days days to the event. Day $index is waiting',
      one: '$days day to the event. Day $index is waiting',
    );
    return '$_temp0';
  }

  @override
  String planNotifyBeforeBody(int percent, String title) {
    return 'Readiness $percent%. Today — $title.';
  }

  @override
  String planNotifyMorningTitle(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'The event is today. $count phrases in 3 minutes',
      one: 'The event is today. $count phrase in 3 minutes',
    );
    return '$_temp0';
  }

  @override
  String get planNotifyMorningBody =>
      'A quick rehearsal before you leave — only what you will say.';

  @override
  String get planNotifyEveningTitle => 'How did it go? Mark what you said';

  @override
  String get planNotifyEveningBody => 'Tick the abilities that came up — that closes the plan.';

  @override
  String planFinishedAtEvent(int used, int total) {
    return 'At the event you said $used of $total.';
  }

  @override
  String get planNoDate => 'No date';

  @override
  String get planEntryKicker => 'Preparation plan';

  @override
  String get planEntryGoalTitle => 'What are you preparing for?';

  @override
  String get planEntryGoalSubtitleLong =>
      'Tell it in your own words: where you will be, with whom, what you need to say and understand. The details are the phrases you will actually use.';

  @override
  String get planEntryGoalSubtitle =>
      'Tell it in your own words: where you will be, with whom, what you need to say and understand.';

  @override
  String get planEntryGoalPlaceholder =>
      'For example: taking my child to the doctor, I need to describe the symptoms and understand the treatment';

  @override
  String get planEntryLinesHint => '4–6 lines is fine';

  @override
  String get planEntryEnough => 'enough for a plan';

  @override
  String get planEntryDetailed => 'detail is good';

  @override
  String get planEntryTooShort =>
      'A couple of words is not enough. Add: which doctor, who is coming with you, what you need to understand.';

  @override
  String get planEntryExamplesTitle => 'This works too';

  @override
  String get planEntryAdditionsTitle => 'You could add';

  @override
  String get planEntryFinishTitle => 'Finish it for you';

  @override
  String get planEntryExample1 =>
      'Going to the doctor, my back hurts, I need to explain it and understand the treatment';

  @override
  String get planEntryExample2 =>
      'Online interview for a PHP developer role, remote, English-speaking team';

  @override
  String get planEntryExample3 => 'Flying on holiday with a child: airport, hotel, pharmacy';

  @override
  String get planEntryNext => 'Next';

  @override
  String get planEntryDictate => 'Dictate';

  @override
  String get planEntryLangTitle => 'Which language are you learning?';

  @override
  String planEntryTranslationsInto(String language) {
    return 'Translations into $language';
  }

  @override
  String get planEntrySettingsLink => 'change in settings';

  @override
  String get planEntryLevelTitle => 'How well do you speak it now?';

  @override
  String get planLevelZeroHint => 'I know separate words, I cannot put a sentence together';

  @override
  String get planLevelBasicHint => 'I can read messages, but I speak with pauses';

  @override
  String get planLevelConversationalHint =>
      'I can handle everyday things; for harder ones I search for words';

  @override
  String get planLevelFluentHint => 'I speak without preparation, I am polishing precision';

  @override
  String get planListenKicker => 'Optional step';

  @override
  String get planListenOfferTitle => 'Want me to tune this more precisely?';

  @override
  String get planListenOfferBody =>
      'Listen to three lines from your situation — how they will actually sound. One minute.';

  @override
  String get planListenListen => 'Listen';

  @override
  String get planListenSkip => 'Skip';

  @override
  String get planListenReassure =>
      'This is not a test. Nobody sees your answers, and the plan will be built without this step.';

  @override
  String get planListenEnough => 'That\'s enough';

  @override
  String planListenLine(int index) {
    return 'Line $index';
  }

  @override
  String planListenLineAt(int index, String place) {
    return 'Line $index · $place';
  }

  @override
  String get planListenReplayHint => 'Play it as many times as you need.';

  @override
  String get planListenShowText => 'Show the text';

  @override
  String get planListenFeelLabel => 'How did that feel';

  @override
  String get planListenGot => 'Got it';

  @override
  String get planListenNotQuite => 'Not quite';

  @override
  String get planListenNoRightAnswer =>
      'There is no right answer — this is about what goes into the plan.';

  @override
  String get planListenResultUnderstanding => 'Got it: I will put the weight on listening';

  @override
  String get planListenResultUnderstandingBody =>
      'Speech goes faster than is comfortable. The plan will hold more listening and less word drilling.';

  @override
  String get planListenResultSpeaking =>
      'You understand speech confidently: I will put the weight on speaking';

  @override
  String get planListenResultSpeakingBody =>
      'The lines come easily to you — the plan will hold more of your own answers out loud and less word drilling.';

  @override
  String get planListenResultFootnote => 'You can change this in the plan on any day.';

  @override
  String get planEntryRibbonListenUnderstanding => 'Listening · weight on understanding';

  @override
  String get planEntryRibbonListenSpeaking => 'Listening · weight on speaking';

  @override
  String get planEntryRibbonListenSkipped => 'Listening · step skipped';

  @override
  String get planEntryPass => 'Take it';

  @override
  String planEntryRibbonLangLevel(String language, String level) {
    return '$language · $level';
  }

  @override
  String get planEntryWhenTitle => 'When does it happen?';

  @override
  String get planEntryPickDate => 'Pick a date';

  @override
  String planEntryHintDays(int days) {
    String _temp0 = intl.Intl.pluralLogic(
      days,
      locale: localeName,
      other: 'In $days days · the server will lay the preparation across those days',
      one: 'In $days day · the server will lay the preparation across those days',
    );
    return '$_temp0';
  }

  @override
  String get planEntryHintToday => 'Today · the whole preparation fits into one sitting';

  @override
  String get planEntryHintNoDate => 'No date · at your own pace, one scene per sitting';

  @override
  String get planEntryMinutesTitle => 'How many minutes a day?';

  @override
  String get planEntryMinutesUnit => 'minutes';

  @override
  String get planEntryBuild => 'Build the plan';

  @override
  String get planBuildKicker => 'Building your plan';

  @override
  String get planBuildStep1 => 'Reading the goal';

  @override
  String get planBuildStep2 => 'Choosing the lines';

  @override
  String get planBuildStep3 => 'Collecting the words';

  @override
  String get planBuildFootnote =>
      'The lines come from real speech, not from a textbook. This takes a few seconds.';

  @override
  String get planBuildRetryKicker => 'Still building';

  @override
  String get planBuildRetryBody => 'It did not come together the first time — trying again.';

  @override
  String get planBuildRetryFootnote =>
      'Your answers are safe. The second attempt starts from the same step.';

  @override
  String get planBuildOfflineKicker => 'Building paused';

  @override
  String get planBuildOfflineTitle => 'The connection dropped halfway';

  @override
  String get planBuildOfflineBody =>
      'Your answers are saved — you will not have to type anything again. We will carry on as soon as the network is back.';

  @override
  String get planBuildRetryButton => 'Try again';

  @override
  String get planBuildNotifyButton => 'Tell me when it\'s ready';

  @override
  String get planBuildNotifyUnavailable => 'Notifications are not wired up yet';

  @override
  String get planBuildFailedKicker => 'It did not come together';

  @override
  String get planBuildFailedTitle => 'It did not come together. Your answers are saved';

  @override
  String get planBuildFailedBody =>
      'You will not have to type anything again. You can go back to your answers and start the build again — or write to us.';

  @override
  String get planBuildBackToAnswers => 'Back to my answers';

  @override
  String get planBuildWriteUs => 'Write to us';

  @override
  String get planBuildMailSubject => 'The plan did not build';

  @override
  String get planPreviewKicker => 'Your plan is ready';

  @override
  String planPreviewSubtitle(int scenes, String topics) {
    String _temp0 = intl.Intl.pluralLogic(
      scenes,
      locale: localeName,
      other: '$scenes scenes',
      one: '$scenes scene',
    );
    return 'From your own words — $_temp0: $topics.';
  }

  @override
  String planPreviewSubtitleNoDate(int scenes, String topics) {
    String _temp0 = intl.Intl.pluralLogic(
      scenes,
      locale: localeName,
      other: '$scenes scenes',
      one: '$scenes scene',
    );
    return 'From your own words — $_temp0: $topics. No date, so we go at your own pace.';
  }

  @override
  String planPreviewOrientation(int days, int minutes) {
    String _temp0 = intl.Intl.pluralLogic(
      days,
      locale: localeName,
      other: '$days DAYS OF PREPARATION',
      one: '$days DAY OF PREPARATION',
    );
    return '$_temp0 · $minutes MINUTES A DAY';
  }

  @override
  String planPreviewOrientationNoDate(int scenes, int minutes) {
    String _temp0 = intl.Intl.pluralLogic(
      scenes,
      locale: localeName,
      other: '$scenes SCENES',
      one: '$scenes SCENE',
    );
    return '$_temp0 · $minutes MINUTES A DAY · ONE PER SITTING';
  }

  @override
  String get planPreviewDaysTitle => 'Your days';

  @override
  String get planPreviewScenesTitle => 'Your scenes';

  @override
  String planPreviewDayLabel(int index) {
    return 'DAY $index';
  }

  @override
  String planPreviewSceneLabel(int index) {
    return 'SCENE $index';
  }

  @override
  String get planPreviewRescueBody =>
      'Five phrases just in case — from day one, for when you didn\'t understand or didn\'t catch it.';

  @override
  String get planPreviewRescueQuote =>
      '“Could you speak more slowly, please” — and four more like it, from day one.';

  @override
  String get planPreviewRehearsalEveLabel => 'THE DAY BEFORE';

  @override
  String get planPreviewRehearsalEndLabel => 'AT THE END';

  @override
  String get planPreviewRehearsalTitle => 'Say it yourself before the event';

  @override
  String planPreviewRehearsalEveBody(int scenes) {
    String _temp0 = intl.Intl.pluralLogic(
      scenes,
      locale: localeName,
      other: 'All $scenes scenes',
      one: 'One scene',
    );
    return '$_temp0 in a row, out loud, in one sitting.';
  }

  @override
  String get planPreviewRehearsalEndBody =>
      'It opens once you have walked every scene. You can set a date on any day.';

  @override
  String get planPreviewStartDay => 'Start the first day';

  @override
  String get planPreviewStartScene => 'Start the first scene';

  @override
  String get planPreviewEditAnswers => 'Change my answers';

  @override
  String get planPreviewSetDate => 'Set a date';

  @override
  String get devVoicesTitle => 'Line voices';

  @override
  String get devVoicesLead =>
      'The same five lines of the qa plan, read by every candidate at one pace. Judge them on the phone rather than in the files: the speaker and the headphones decide more than a spectrogram.';

  @override
  String get devVoicesSystem => 'The phone\'s own voice';

  @override
  String get devVoicesSystemNote =>
      'Spoken live, at the line tempo. The voice is whichever one iOS Settings has; an enhanced voice is downloaded there.';

  @override
  String devVoicesPrice(String price) {
    return '≈ $price per plan';
  }

  @override
  String get devVoicesPlayAll => 'All five';

  @override
  String get devVoicesStop => 'Stop';

  @override
  String get devVoicesFavourite => 'This session\'s favourite';

  @override
  String devVoiceTrouble(int silent, int failed) {
    return 'Voice: $silent lines fell back to the system voice, $failed downloads failed';
  }

  @override
  String get planStateNotStarted => 'not started';

  @override
  String get planStateInProgress => 'in progress';

  @override
  String get planStateDone => 'done';

  @override
  String get planStateMaterialDone => 'words and phrases done';

  @override
  String planStateMaterialAbout(int minutes) {
    String _temp0 = intl.Intl.pluralLogic(
      minutes,
      locale: localeName,
      other: 'words and phrases about $minutes minutes',
      one: 'words and phrases about $minutes minute',
    );
    return '$_temp0';
  }

  @override
  String planStateConversationAbout(int minutes) {
    String _temp0 = intl.Intl.pluralLogic(
      minutes,
      locale: localeName,
      other: 'conversation about $minutes minutes',
      one: 'conversation about $minutes minute',
    );
    return '$_temp0';
  }

  @override
  String get planSittingToConversation => 'To the conversation';

  @override
  String planStateMinutes(int minutes) {
    String _temp0 = intl.Intl.pluralLogic(
      minutes,
      locale: localeName,
      other: 'about $minutes minutes',
      one: 'about $minutes minute',
    );
    return '$_temp0';
  }

  @override
  String get planDayRepeat => 'Walk it again';

  @override
  String planHomeDayState(int index, String state) {
    return 'Day $index · $state';
  }

  @override
  String get devQaClockTitle => 'QA · the plan\'s “today”';

  @override
  String devQaClockShift(int days) {
    String _temp0 = intl.Intl.pluralLogic(
      days,
      locale: localeName,
      other: 'shifted by $days days',
      one: 'shifted by $days day',
      zero: 'no shift',
    );
    return '$_temp0';
  }

  @override
  String get devQaClockPlus => '+1 day';

  @override
  String get devQaClockReset => 'Reset';

  @override
  String get profileRowSounds => 'Sounds';

  @override
  String get profileSoundsHint => 'Correct · wrong · stage closed · day closed';

  @override
  String dayLabel(int n) {
    return 'Day $n';
  }

  @override
  String dayCards(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n cards', one: '$n card');
    return '$_temp0';
  }

  @override
  String dayMinutes(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n minutes',
      one: '$n minute',
    );
    return '$_temp0';
  }

  @override
  String dayApproxMinutes(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n minutes',
      one: '$n minute',
    );
    return '≈ $_temp0';
  }

  @override
  String dayApproxMin(int n) {
    return '≈ $n min';
  }

  @override
  String get dayLevelBeginner => 'Beginner';

  @override
  String get dayLevelIntermediate => 'Intermediate';

  @override
  String get dayGoalLabel => 'You will learn to';

  @override
  String get dayStageWords => 'Words';

  @override
  String get dayStagePhrases => 'Phrases';

  @override
  String get dayStageDialogue => 'Dialogue';

  @override
  String get dayStageListen => 'Listen and answer';

  @override
  String get dayStageSpeak => 'Speak myself';

  @override
  String dayStageCount(int done, int total) {
    return '$done / $total';
  }

  @override
  String dayNewWords(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n new words',
      one: '$n new word',
    );
    return '$_temp0';
  }

  @override
  String dayStageSubStart(String what) {
    return 'start here · $what';
  }

  @override
  String dayStageSubUnfinished(String cards, String minutes) {
    return 'unfinished · $cards · $minutes';
  }

  @override
  String dayStageSubHinted(int n) {
    return '$n with a hint';
  }

  @override
  String get dayCtaStart => 'Start';

  @override
  String dayCtaContinue(int n) {
    return 'Continue · $n left';
  }

  @override
  String get dayCtaPlan => 'To the plan';

  @override
  String dayClosedTitle(int n) {
    return 'Day $n closed';
  }

  @override
  String dayNumCards(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: 'cards', one: 'card');
    return '$_temp0';
  }

  @override
  String dayNumMinutes(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: 'minutes', one: 'minute');
    return '$_temp0';
  }

  @override
  String get dayNumFirstTry => 'first try';

  @override
  String daySectionWords(int n) {
    return 'Words · $n';
  }

  @override
  String daySectionPhrases(int n) {
    return 'Phrases · $n';
  }

  @override
  String daySectionTalk(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n exchanges',
      one: '$n exchange',
    );
    return 'Conversation · $_temp0';
  }

  @override
  String dayFromDay(int n) {
    return 'from day $n';
  }

  @override
  String dayWordsExtra(int n, int d) {
    return '+ $n from day $d';
  }

  @override
  String get dayHardest => 'Hardest of all';

  @override
  String dayTries(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n tries', one: '$n try');
    return '$_temp0';
  }

  @override
  String get dayInWork => 'In progress';

  @override
  String dayWordsCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n words', one: '$n word');
    return '$_temp0';
  }

  @override
  String dayPhrasesCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n phrases',
      one: '$n phrase',
    );
    return '$_temp0';
  }

  @override
  String dayExchangesCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n exchanges',
      one: '$n exchange',
    );
    return '$_temp0';
  }

  @override
  String dayWillReturn(int n, int k) {
    String _temp0 = intl.Intl.pluralLogic(
      k,
      locale: localeName,
      other: 'Return on day $n · $k cards',
      one: 'Returns on day $n · $k card',
    );
    return '$_temp0';
  }

  @override
  String dayShellCounter(String stage, int done, int total) {
    return '$stage · $done of $total';
  }

  @override
  String dayEntryLabel(int n) {
    return 'Stage $n of 5';
  }

  @override
  String dayEntryLine(String units, String cards, String minutes) {
    return '$units · $cards · $minutes';
  }

  @override
  String get dayEntryWordsSteps =>
      'meet it · say it · choose the translation · put it in the example';

  @override
  String get dayEntryPhrasesSteps => 'meet it · repeat aloud · assemble';

  @override
  String get dayEntryDialogueSteps => 'read and listen to the whole conversation';

  @override
  String get dayEntryListenSteps => 'listen · choose what was asked · answer';

  @override
  String get dayEntrySpeakSteps => 'say your line in each exchange — no text';

  @override
  String dayEntryNew(int n) {
    String _temp0 = intl.Intl.pluralLogic(n, locale: localeName, other: '$n new', one: '$n new');
    return '$_temp0';
  }

  @override
  String dayEntryReturned(int r, int d) {
    String _temp0 = intl.Intl.pluralLogic(
      r,
      locale: localeName,
      other: '$r returned from day $d',
      one: '$r returned from day $d',
    );
    return '$_temp0';
  }

  @override
  String dayEntryResume(int n, int total, String minutes) {
    return 'continuing · $n of $total left · $minutes';
  }

  @override
  String get dayEntryCta => 'Start';

  @override
  String get dayEntryResumeCta => 'Continue';

  @override
  String get dayIntroBadgeNew => 'new word';

  @override
  String get dayIntroBadgeRepeat => 'review';

  @override
  String get dayIntroCta => 'Got it';

  @override
  String dayReturnedBadge(int n) {
    return 'Returned from day $n';
  }

  @override
  String get dayPhraseBadgeNew => 'new phrase';

  @override
  String get dayPhraseInTalk => 'in the conversation';

  @override
  String get dayTaskPronounce => 'Say it';

  @override
  String get dayTaskRepeat => 'Repeat aloud';

  @override
  String get dayTaskAsked => 'What did they ask';

  @override
  String dayTaskAnswer(String lang) {
    return 'Answer in $lang';
  }

  @override
  String dayTaskSay(String lang) {
    return 'Say it in $lang';
  }

  @override
  String get dayTaskAssemble => 'Assemble what they said';

  @override
  String get dayTaskChoose => 'Choose the translation';

  @override
  String get dayTaskChooseWord => 'Choose the word';

  @override
  String get dayTaskCloze => 'Fill in the word';

  @override
  String dayTaskAssemblePhrase(String lang) {
    return 'Assemble in $lang';
  }

  @override
  String get daySayMic => 'Say it aloud';

  @override
  String get daySayListening => 'Listening…';

  @override
  String get daySayThinking => '…';

  @override
  String daySayHeard(String word) {
    return 'Heard: $word';
  }

  @override
  String get daySayHeardShort => 'Heard';

  @override
  String get daySayRetry => 'Didn\'t catch that. Once more';

  @override
  String get daySaySkip => 'Skip';

  @override
  String dayReturnDay(int n) {
    return 'Returns on day $n';
  }

  @override
  String get dayReturnStage => 'Returns at the end of the stage';

  @override
  String get dayNext => 'Next';

  @override
  String get dayDialogTitle => 'The whole conversation';

  @override
  String get dayDialogSub => 'Read and listen — then you will answer yourself';

  @override
  String get dayDialogRoleYou => 'You';

  @override
  String get dayDialogTranslate => 'translation';

  @override
  String daySpeakerSays(String role) {
    return '$role says';
  }

  @override
  String daySpeakerAnswers(String role) {
    return '$role answers';
  }

  @override
  String get daySpeakHint => 'Hint';

  @override
  String daySpeakHinted(int n) {
    return 'Counted with a hint · returns on day $n';
  }

  @override
  String get dayStageDoneWords => 'Words closed';

  @override
  String get dayStageDonePhrases => 'Phrases closed';

  @override
  String get dayStageDoneDialogue => 'Dialogue closed';

  @override
  String get dayStageDoneListen => '“Listen and answer” closed';

  @override
  String get dayStageDoneSpeak => '“Speak myself” closed';

  @override
  String dayStageDoneMeta(String cards, String minutes) {
    return '$cards · $minutes';
  }

  @override
  String dayStageFactsInWork(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n words in progress',
      one: '$n word in progress',
    );
    return '$_temp0';
  }

  @override
  String dayStageFactsPhrasesInWork(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n phrases in progress',
      one: '$n phrase in progress',
    );
    return '$_temp0';
  }

  @override
  String dayStageFactsExchangesInWork(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n exchanges in progress',
      one: '$n exchange in progress',
    );
    return '$_temp0';
  }

  @override
  String dayStageFactsHinted(int n) {
    return '$n with a hint';
  }

  @override
  String dayStageFactsReturn(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n return',
      one: '$n returns',
    );
    return '$_temp0';
  }

  @override
  String dayStageNext(String stage, String cards, String minutes) {
    return 'Next: $stage · $cards · $minutes';
  }

  @override
  String get dayExitTitle => 'Continue later?';

  @override
  String dayExitBody(String stage, int done, int total) {
    return 'You are on $stage · $done of $total. Your progress is saved';
  }

  @override
  String get dayExitStay => 'Stay';

  @override
  String get dayExitLeave => 'Leave';

  @override
  String daySheetStatePassed(int n) {
    return 'passed · day $n';
  }

  @override
  String daySheetStateHinted(int n) {
    return 'with a hint · day $n';
  }

  @override
  String daySheetStateReturns(int n) {
    return 'returns on day $n';
  }

  @override
  String get daySheetInTalk => 'In the conversation';

  @override
  String get daySheetRoleYou => 'You:';

  @override
  String get dayNoVoice => 'no recording — the phone reads it';

  @override
  String dayLockedByDay(int n) {
    return 'Finish day $n first';
  }

  @override
  String dayLockedUntil(String date) {
    return 'Opens $date';
  }

  @override
  String get dayLessonBuilding => 'Building the day · about a minute';

  @override
  String get dayLessonFailed => 'The day did not build';

  @override
  String get dayLessonRetry => 'Retry';

  @override
  String get dayBack => 'Back';

  @override
  String get dayTabTitle => 'Plan';

  @override
  String get dayTabRoute => 'Route';
}
