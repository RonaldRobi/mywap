import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:intl/intl.dart' as intl;

import 'app_localizations_en.dart';
import 'app_localizations_ms.dart';

// ignore_for_file: type=lint

/// Callers can lookup localized strings with an instance of AppLocalizations
/// returned by `AppLocalizations.of(context)`.
///
/// Applications need to include `AppLocalizations.delegate()` in their app's
/// `localizationDelegates` list, and the locales they support in the app's
/// `supportedLocales` list. For example:
///
/// ```dart
/// import 'l10n/app_localizations.dart';
///
/// return MaterialApp(
///   localizationsDelegates: AppLocalizations.localizationsDelegates,
///   supportedLocales: AppLocalizations.supportedLocales,
///   home: MyApplicationHome(),
/// );
/// ```
///
/// ## Update pubspec.yaml
///
/// Please make sure to update your pubspec.yaml to include the following
/// packages:
///
/// ```yaml
/// dependencies:
///   # Internationalization support.
///   flutter_localizations:
///     sdk: flutter
///   intl: any # Use the pinned version from flutter_localizations
///
///   # Rest of dependencies
/// ```
///
/// ## iOS Applications
///
/// iOS applications define key application metadata, including supported
/// locales, in an Info.plist file that is built into the application bundle.
/// To configure the locales supported by your app, you’ll need to edit this
/// file.
///
/// First, open your project’s ios/Runner.xcworkspace Xcode workspace file.
/// Then, in the Project Navigator, open the Info.plist file under the Runner
/// project’s Runner folder.
///
/// Next, select the Information Property List item, select Add Item from the
/// Editor menu, then select Localizations from the pop-up menu.
///
/// Select and expand the newly-created Localizations item then, for each
/// locale your application supports, add a new item and select the locale
/// you wish to add from the pop-up menu in the Value field. This list should
/// be consistent with the languages listed in the AppLocalizations.supportedLocales
/// property.
abstract class AppLocalizations {
  AppLocalizations(String locale) : localeName = intl.Intl.canonicalizedLocale(locale.toString());

  final String localeName;

  static AppLocalizations of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations)!;
  }

  static const LocalizationsDelegate<AppLocalizations> delegate = _AppLocalizationsDelegate();

  /// A list of this localizations delegate along with the default localizations
  /// delegates.
  ///
  /// Returns a list of localizations delegates containing this delegate along with
  /// GlobalMaterialLocalizations.delegate, GlobalCupertinoLocalizations.delegate,
  /// and GlobalWidgetsLocalizations.delegate.
  ///
  /// Additional delegates can be added by appending to this list in
  /// MaterialApp. This list does not have to be used at all if a custom list
  /// of delegates is preferred or required.
  static const List<LocalizationsDelegate<dynamic>> localizationsDelegates = <LocalizationsDelegate<dynamic>>[
    delegate,
    GlobalMaterialLocalizations.delegate,
    GlobalCupertinoLocalizations.delegate,
    GlobalWidgetsLocalizations.delegate,
  ];

  /// A list of this localizations delegate's supported locales.
  static const List<Locale> supportedLocales = <Locale>[
    Locale('en'),
    Locale('ms')
  ];

  /// No description provided for @appTitle.
  ///
  /// In en, this message translates to:
  /// **'myWAP'**
  String get appTitle;

  /// No description provided for @login.
  ///
  /// In en, this message translates to:
  /// **'Login'**
  String get login;

  /// No description provided for @logout.
  ///
  /// In en, this message translates to:
  /// **'Log Out'**
  String get logout;

  /// No description provided for @retry.
  ///
  /// In en, this message translates to:
  /// **'Try Again'**
  String get retry;

  /// No description provided for @back.
  ///
  /// In en, this message translates to:
  /// **'Back'**
  String get back;

  /// No description provided for @yes.
  ///
  /// In en, this message translates to:
  /// **'Yes'**
  String get yes;

  /// No description provided for @cancel.
  ///
  /// In en, this message translates to:
  /// **'Cancel'**
  String get cancel;

  /// No description provided for @pleaseWait.
  ///
  /// In en, this message translates to:
  /// **'Please wait...'**
  String get pleaseWait;

  /// No description provided for @settingsTitle.
  ///
  /// In en, this message translates to:
  /// **'Settings'**
  String get settingsTitle;

  /// No description provided for @settingsLanguage.
  ///
  /// In en, this message translates to:
  /// **'Language'**
  String get settingsLanguage;

  /// No description provided for @settingsLanguageSubtitle.
  ///
  /// In en, this message translates to:
  /// **'App display language'**
  String get settingsLanguageSubtitle;

  /// No description provided for @settingsLanguageSheetTitle.
  ///
  /// In en, this message translates to:
  /// **'Choose Language'**
  String get settingsLanguageSheetTitle;

  /// No description provided for @menuSettingsTooltip.
  ///
  /// In en, this message translates to:
  /// **'Settings'**
  String get menuSettingsTooltip;

  /// No description provided for @navHomeMember.
  ///
  /// In en, this message translates to:
  /// **'Home'**
  String get navHomeMember;

  /// No description provided for @navHomeGuest.
  ///
  /// In en, this message translates to:
  /// **'Home'**
  String get navHomeGuest;

  /// No description provided for @navProgram.
  ///
  /// In en, this message translates to:
  /// **'Programs'**
  String get navProgram;

  /// No description provided for @navInfaq.
  ///
  /// In en, this message translates to:
  /// **'Infaq'**
  String get navInfaq;

  /// No description provided for @navMenu.
  ///
  /// In en, this message translates to:
  /// **'Menu'**
  String get navMenu;

  /// No description provided for @menuTitle.
  ///
  /// In en, this message translates to:
  /// **'Menu'**
  String get menuTitle;

  /// No description provided for @menuMemberFallbackName.
  ///
  /// In en, this message translates to:
  /// **'myWAP Member'**
  String get menuMemberFallbackName;

  /// No description provided for @menuViewProfile.
  ///
  /// In en, this message translates to:
  /// **'View your profile'**
  String get menuViewProfile;

  /// No description provided for @menuGuestTagline.
  ///
  /// In en, this message translates to:
  /// **'Community content for everyone'**
  String get menuGuestTagline;

  /// No description provided for @menuRegister.
  ///
  /// In en, this message translates to:
  /// **'Register'**
  String get menuRegister;

  /// No description provided for @menuSectionMembership.
  ///
  /// In en, this message translates to:
  /// **'Membership'**
  String get menuSectionMembership;

  /// No description provided for @menuCard.
  ///
  /// In en, this message translates to:
  /// **'Digital ID'**
  String get menuCard;

  /// No description provided for @menuCardSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Digital card & QR code'**
  String get menuCardSubtitle;

  /// No description provided for @menuProfile.
  ///
  /// In en, this message translates to:
  /// **'My Profile'**
  String get menuProfile;

  /// No description provided for @menuProfileSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Account details & settings'**
  String get menuProfileSubtitle;

  /// No description provided for @menuInvite.
  ///
  /// In en, this message translates to:
  /// **'Invite Member'**
  String get menuInvite;

  /// No description provided for @menuInviteSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Share referral link'**
  String get menuInviteSubtitle;

  /// No description provided for @menuSectionFinance.
  ///
  /// In en, this message translates to:
  /// **'Finance'**
  String get menuSectionFinance;

  /// No description provided for @menuFees.
  ///
  /// In en, this message translates to:
  /// **'Fees & Payments'**
  String get menuFees;

  /// No description provided for @menuFeesSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Fee status and receipts'**
  String get menuFeesSubtitle;

  /// No description provided for @menuInfaq.
  ///
  /// In en, this message translates to:
  /// **'Infaq'**
  String get menuInfaq;

  /// No description provided for @menuInfaqSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Make a contribution'**
  String get menuInfaqSubtitle;

  /// No description provided for @menuSectionPrograms.
  ///
  /// In en, this message translates to:
  /// **'Programs & Community'**
  String get menuSectionPrograms;

  /// No description provided for @menuPrograms.
  ///
  /// In en, this message translates to:
  /// **'Programs'**
  String get menuPrograms;

  /// No description provided for @menuProgramsSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Upcoming events'**
  String get menuProgramsSubtitle;

  /// No description provided for @menuMyRegistrations.
  ///
  /// In en, this message translates to:
  /// **'My Registrations'**
  String get menuMyRegistrations;

  /// No description provided for @menuMyRegistrationsSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Programs you joined'**
  String get menuMyRegistrationsSubtitle;

  /// No description provided for @menuUsrah.
  ///
  /// In en, this message translates to:
  /// **'Usrah'**
  String get menuUsrah;

  /// No description provided for @menuUsrahSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Groups & schedule'**
  String get menuUsrahSubtitle;

  /// No description provided for @menuPolls.
  ///
  /// In en, this message translates to:
  /// **'Polls'**
  String get menuPolls;

  /// No description provided for @menuPollsSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Polls & surveys'**
  String get menuPollsSubtitle;

  /// No description provided for @menuFacilities.
  ///
  /// In en, this message translates to:
  /// **'Book Facilities'**
  String get menuFacilities;

  /// No description provided for @menuFacilitiesSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Rooms, halls & equipment'**
  String get menuFacilitiesSubtitle;

  /// No description provided for @menuSectionReading.
  ///
  /// In en, this message translates to:
  /// **'Reading & Media'**
  String get menuSectionReading;

  /// No description provided for @menuNews.
  ///
  /// In en, this message translates to:
  /// **'Latest News'**
  String get menuNews;

  /// No description provided for @menuNewsSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Organisation news'**
  String get menuNewsSubtitle;

  /// No description provided for @menuArticles.
  ///
  /// In en, this message translates to:
  /// **'Articles'**
  String get menuArticles;

  /// No description provided for @menuArticlesSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Writings & features'**
  String get menuArticlesSubtitle;

  /// No description provided for @menuVideos.
  ///
  /// In en, this message translates to:
  /// **'Videos'**
  String get menuVideos;

  /// No description provided for @menuVideosSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Lectures & tazkirah'**
  String get menuVideosSubtitle;

  /// No description provided for @menuLibrary.
  ///
  /// In en, this message translates to:
  /// **'Library'**
  String get menuLibrary;

  /// No description provided for @menuLibrarySubtitle.
  ///
  /// In en, this message translates to:
  /// **'Reading & reference materials'**
  String get menuLibrarySubtitle;

  /// No description provided for @menuSectionMall.
  ///
  /// In en, this message translates to:
  /// **'myWAP Mall'**
  String get menuSectionMall;

  /// No description provided for @menuProducts.
  ///
  /// In en, this message translates to:
  /// **'Products'**
  String get menuProducts;

  /// No description provided for @menuProductsSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Items for sale'**
  String get menuProductsSubtitle;

  /// No description provided for @menuOrders.
  ///
  /// In en, this message translates to:
  /// **'My Orders'**
  String get menuOrders;

  /// No description provided for @menuOrdersSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Check order status'**
  String get menuOrdersSubtitle;

  /// No description provided for @menuSectionOrgHelp.
  ///
  /// In en, this message translates to:
  /// **'Organisation & Help'**
  String get menuSectionOrgHelp;

  /// No description provided for @menuOrgInfo.
  ///
  /// In en, this message translates to:
  /// **'Organisation Info'**
  String get menuOrgInfo;

  /// No description provided for @menuOrgInfoSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Chart & information'**
  String get menuOrgInfoSubtitle;

  /// No description provided for @menuAnnouncements.
  ///
  /// In en, this message translates to:
  /// **'Announcements'**
  String get menuAnnouncements;

  /// No description provided for @menuAnnouncementsSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Latest updates'**
  String get menuAnnouncementsSubtitle;

  /// No description provided for @menuNotifications.
  ///
  /// In en, this message translates to:
  /// **'Notifications'**
  String get menuNotifications;

  /// No description provided for @menuNotificationsSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Your notifications'**
  String get menuNotificationsSubtitle;

  /// No description provided for @menuManual.
  ///
  /// In en, this message translates to:
  /// **'User Manual'**
  String get menuManual;

  /// No description provided for @menuManualSubtitle.
  ///
  /// In en, this message translates to:
  /// **'App usage guide'**
  String get menuManualSubtitle;

  /// No description provided for @menuAdmin.
  ///
  /// In en, this message translates to:
  /// **'Admin Panel'**
  String get menuAdmin;

  /// No description provided for @menuAdminSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Organisation management'**
  String get menuAdminSubtitle;

  /// No description provided for @menuSectionExplore.
  ///
  /// In en, this message translates to:
  /// **'Explore'**
  String get menuSectionExplore;

  /// No description provided for @menuGuestHome.
  ///
  /// In en, this message translates to:
  /// **'Home'**
  String get menuGuestHome;

  /// No description provided for @menuGuestHomeSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Main page'**
  String get menuGuestHomeSubtitle;

  /// No description provided for @menuGuestEvents.
  ///
  /// In en, this message translates to:
  /// **'Programs & Events'**
  String get menuGuestEvents;

  /// No description provided for @menuGuestEventsSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Events open to the public'**
  String get menuGuestEventsSubtitle;

  /// No description provided for @menuGuestOrgInfoSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Get to know us'**
  String get menuGuestOrgInfoSubtitle;

  /// No description provided for @menuSectionDonation.
  ///
  /// In en, this message translates to:
  /// **'Contributions'**
  String get menuSectionDonation;

  /// No description provided for @menuGuestInfaq.
  ///
  /// In en, this message translates to:
  /// **'Infaq & Contributions'**
  String get menuGuestInfaq;

  /// No description provided for @menuGuestInfaqSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Give your support'**
  String get menuGuestInfaqSubtitle;

  /// No description provided for @menuLogoutConfirmTitle.
  ///
  /// In en, this message translates to:
  /// **'Log Out'**
  String get menuLogoutConfirmTitle;

  /// No description provided for @menuLogoutConfirmMessage.
  ///
  /// In en, this message translates to:
  /// **'Are you sure you want to log out of myWAP?'**
  String get menuLogoutConfirmMessage;

  /// No description provided for @loginFailed.
  ///
  /// In en, this message translates to:
  /// **'Login failed. Please try again.'**
  String get loginFailed;

  /// No description provided for @biometricAuthFailed.
  ///
  /// In en, this message translates to:
  /// **'Biometric verification failed. Please log in manually.'**
  String get biometricAuthFailed;

  /// No description provided for @loginWelcomeTitle.
  ///
  /// In en, this message translates to:
  /// **'Welcome back'**
  String get loginWelcomeTitle;

  /// No description provided for @loginWelcomeSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Log in to continue to myWAP.'**
  String get loginWelcomeSubtitle;

  /// No description provided for @loginIdentifierLabel.
  ///
  /// In en, this message translates to:
  /// **'Email or Identification Number'**
  String get loginIdentifierLabel;

  /// No description provided for @loginIdentifierHint.
  ///
  /// In en, this message translates to:
  /// **'Example: name@email.com or 900101...'**
  String get loginIdentifierHint;

  /// No description provided for @loginIdentifierRequired.
  ///
  /// In en, this message translates to:
  /// **'Please enter your email or identification number.'**
  String get loginIdentifierRequired;

  /// No description provided for @passwordLabel.
  ///
  /// In en, this message translates to:
  /// **'Password'**
  String get passwordLabel;

  /// No description provided for @passwordHint.
  ///
  /// In en, this message translates to:
  /// **'Enter your password'**
  String get passwordHint;

  /// No description provided for @passwordRequired.
  ///
  /// In en, this message translates to:
  /// **'Please enter your password.'**
  String get passwordRequired;

  /// No description provided for @forgotPassword.
  ///
  /// In en, this message translates to:
  /// **'Forgot password?'**
  String get forgotPassword;

  /// No description provided for @biometricFaceIdLogin.
  ///
  /// In en, this message translates to:
  /// **'Log in with Face ID'**
  String get biometricFaceIdLogin;

  /// No description provided for @biometricFingerprintLogin.
  ///
  /// In en, this message translates to:
  /// **'Log in with Fingerprint'**
  String get biometricFingerprintLogin;

  /// No description provided for @firstLoginPrompt.
  ///
  /// In en, this message translates to:
  /// **'First time logging in?'**
  String get firstLoginPrompt;

  /// No description provided for @clickHere.
  ///
  /// In en, this message translates to:
  /// **'Click here'**
  String get clickHere;

  /// No description provided for @forgotMemberNo.
  ///
  /// In en, this message translates to:
  /// **'Forgot Member No.?'**
  String get forgotMemberNo;

  /// No description provided for @noAccount.
  ///
  /// In en, this message translates to:
  /// **'Don\'t have an account?'**
  String get noAccount;

  /// No description provided for @registerNow.
  ///
  /// In en, this message translates to:
  /// **'Register Now'**
  String get registerNow;

  /// No description provided for @browseAsGuest.
  ///
  /// In en, this message translates to:
  /// **'Browse as guest'**
  String get browseAsGuest;

  /// No description provided for @officialPlatform.
  ///
  /// In en, this message translates to:
  /// **'OFFICIAL ECOSYSTEM PLATFORM'**
  String get officialPlatform;

  /// No description provided for @movementPlatformTagline.
  ///
  /// In en, this message translates to:
  /// **'Digital Platform for the Movement Ecosystem'**
  String get movementPlatformTagline;

  /// No description provided for @enableFaceIdTitle.
  ///
  /// In en, this message translates to:
  /// **'Enable Face ID?'**
  String get enableFaceIdTitle;

  /// No description provided for @enableFingerprintTitle.
  ///
  /// In en, this message translates to:
  /// **'Enable Fingerprint?'**
  String get enableFingerprintTitle;

  /// No description provided for @biometricPromptBody.
  ///
  /// In en, this message translates to:
  /// **'Log in faster next time using your device biometrics.'**
  String get biometricPromptBody;

  /// No description provided for @later.
  ///
  /// In en, this message translates to:
  /// **'Later'**
  String get later;

  /// No description provided for @enable.
  ///
  /// In en, this message translates to:
  /// **'Enable'**
  String get enable;

  /// No description provided for @registerTitle.
  ///
  /// In en, this message translates to:
  /// **'Register New Member'**
  String get registerTitle;

  /// No description provided for @registerSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Fill in the details below to join the myWAP ecosystem.'**
  String get registerSubtitle;

  /// No description provided for @alreadyHaveAccount.
  ///
  /// In en, this message translates to:
  /// **'Already have an account?'**
  String get alreadyHaveAccount;

  /// No description provided for @invitedBy.
  ///
  /// In en, this message translates to:
  /// **'Invited by {name}'**
  String invitedBy(String name);

  /// No description provided for @fullNameLabel.
  ///
  /// In en, this message translates to:
  /// **'Full Name'**
  String get fullNameLabel;

  /// No description provided for @fullNameHint.
  ///
  /// In en, this message translates to:
  /// **'Example: Ahmad Firdaus'**
  String get fullNameHint;

  /// No description provided for @fullNameRequired.
  ///
  /// In en, this message translates to:
  /// **'Please enter your name.'**
  String get fullNameRequired;

  /// No description provided for @emailLabel.
  ///
  /// In en, this message translates to:
  /// **'Email'**
  String get emailLabel;

  /// No description provided for @emailHint.
  ///
  /// In en, this message translates to:
  /// **'name@email.com'**
  String get emailHint;

  /// No description provided for @emailRequired.
  ///
  /// In en, this message translates to:
  /// **'Please enter your email address.'**
  String get emailRequired;

  /// No description provided for @emailInvalid.
  ///
  /// In en, this message translates to:
  /// **'Invalid email address. Example: name@email.com'**
  String get emailInvalid;

  /// No description provided for @icPassportLabel.
  ///
  /// In en, this message translates to:
  /// **'Identification Number / Passport'**
  String get icPassportLabel;

  /// No description provided for @icHint.
  ///
  /// In en, this message translates to:
  /// **'Example: 900101011234'**
  String get icHint;

  /// No description provided for @icRequired.
  ///
  /// In en, this message translates to:
  /// **'Please enter your Identification Number or Passport No.'**
  String get icRequired;

  /// No description provided for @icInvalid.
  ///
  /// In en, this message translates to:
  /// **'Invalid IC No. Enter a 12-digit Identification Number (e.g. 900101011234) or Passport No.'**
  String get icInvalid;

  /// No description provided for @phoneOptionalLabel.
  ///
  /// In en, this message translates to:
  /// **'Phone Number (Optional)'**
  String get phoneOptionalLabel;

  /// No description provided for @phoneHint.
  ///
  /// In en, this message translates to:
  /// **'Example: 0123456789'**
  String get phoneHint;

  /// No description provided for @phoneInvalid.
  ///
  /// In en, this message translates to:
  /// **'Invalid phone number. Example: 0123456789'**
  String get phoneInvalid;

  /// No description provided for @dobRequiredForIc.
  ///
  /// In en, this message translates to:
  /// **'Please select your date of birth because the IC No. is incomplete or not a Malaysian IC format.'**
  String get dobRequiredForIc;

  /// No description provided for @dobLabelRequired.
  ///
  /// In en, this message translates to:
  /// **'Date of Birth (Required if IC No. is not 12 digits)'**
  String get dobLabelRequired;

  /// No description provided for @selectDob.
  ///
  /// In en, this message translates to:
  /// **'Select date of birth'**
  String get selectDob;

  /// No description provided for @registerSuccessTitle.
  ///
  /// In en, this message translates to:
  /// **'Registration Successful!'**
  String get registerSuccessTitle;

  /// No description provided for @registerSuccessSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Your account has been activated. Please log in for the first time using your IC No. to set a password.'**
  String get registerSuccessSubtitle;

  /// No description provided for @yourMemberNo.
  ///
  /// In en, this message translates to:
  /// **'YOUR MEMBER NO.'**
  String get yourMemberNo;

  /// No description provided for @firstTimeLogin.
  ///
  /// In en, this message translates to:
  /// **'First Time Login'**
  String get firstTimeLogin;

  /// No description provided for @dobRequired.
  ///
  /// In en, this message translates to:
  /// **'Please select your date of birth.'**
  String get dobRequired;

  /// No description provided for @memberNoFoundTitle.
  ///
  /// In en, this message translates to:
  /// **'Member No. Found'**
  String get memberNoFoundTitle;

  /// No description provided for @memberNoFoundSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Here is your registered Member No.'**
  String get memberNoFoundSubtitle;

  /// No description provided for @memberNoLabel.
  ///
  /// In en, this message translates to:
  /// **'Member No.'**
  String get memberNoLabel;

  /// No description provided for @registeredEmail.
  ///
  /// In en, this message translates to:
  /// **'Registered email: {email}'**
  String registeredEmail(String email);

  /// No description provided for @forgotMemberNoTitle.
  ///
  /// In en, this message translates to:
  /// **'Forgot Member No.'**
  String get forgotMemberNoTitle;

  /// No description provided for @forgotMemberNoVerifySubtitle.
  ///
  /// In en, this message translates to:
  /// **'Verify your date of birth to display your Member No.'**
  String get forgotMemberNoVerifySubtitle;

  /// No description provided for @forgotMemberNoEnterIcSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Enter your Identification Number to find your Member No.'**
  String get forgotMemberNoEnterIcSubtitle;

  /// No description provided for @rememberMemberNo.
  ///
  /// In en, this message translates to:
  /// **'Remember your Member No.?'**
  String get rememberMemberNo;

  /// No description provided for @icLabel.
  ///
  /// In en, this message translates to:
  /// **'Identification Number'**
  String get icLabel;

  /// No description provided for @icRequiredShort.
  ///
  /// In en, this message translates to:
  /// **'Please enter your IC No.'**
  String get icRequiredShort;

  /// No description provided for @dobLabel.
  ///
  /// In en, this message translates to:
  /// **'Date of Birth'**
  String get dobLabel;

  /// No description provided for @verify.
  ///
  /// In en, this message translates to:
  /// **'Verify'**
  String get verify;

  /// No description provided for @next.
  ///
  /// In en, this message translates to:
  /// **'Next'**
  String get next;

  /// No description provided for @resetLinkSent.
  ///
  /// In en, this message translates to:
  /// **'A password reset link has been sent.'**
  String get resetLinkSent;

  /// No description provided for @forgotPasswordTitle.
  ///
  /// In en, this message translates to:
  /// **'Forgot Password'**
  String get forgotPasswordTitle;

  /// No description provided for @forgotPasswordSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Enter your Identification Number. We will send a reset link to your registered email.'**
  String get forgotPasswordSubtitle;

  /// No description provided for @rememberPassword.
  ///
  /// In en, this message translates to:
  /// **'Remember your password?'**
  String get rememberPassword;

  /// No description provided for @sendResetLink.
  ///
  /// In en, this message translates to:
  /// **'Send Reset Link'**
  String get sendResetLink;

  /// No description provided for @backToLogin.
  ///
  /// In en, this message translates to:
  /// **'Back to Login'**
  String get backToLogin;

  /// No description provided for @otpFailed.
  ///
  /// In en, this message translates to:
  /// **'OTP verification failed. Please try again.'**
  String get otpFailed;

  /// No description provided for @firstLoginTitle.
  ///
  /// In en, this message translates to:
  /// **'First Time Login'**
  String get firstLoginTitle;

  /// No description provided for @firstLoginVerifySubtitle.
  ///
  /// In en, this message translates to:
  /// **'Enter the 6-digit code sent to your email and set a new password.'**
  String get firstLoginVerifySubtitle;

  /// No description provided for @firstLoginNoEmailSubtitle.
  ///
  /// In en, this message translates to:
  /// **'We could not find a registered email. Please enter your email to receive an OTP code.'**
  String get firstLoginNoEmailSubtitle;

  /// No description provided for @firstLoginEnterIcSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Enter your Identification Number to receive an OTP code by email.'**
  String get firstLoginEnterIcSubtitle;

  /// No description provided for @alreadyLoggedIn.
  ///
  /// In en, this message translates to:
  /// **'Logged in before?'**
  String get alreadyLoggedIn;

  /// No description provided for @normalLogin.
  ///
  /// In en, this message translates to:
  /// **'Normal Login'**
  String get normalLogin;

  /// No description provided for @emailRequiredShort.
  ///
  /// In en, this message translates to:
  /// **'Please enter your email.'**
  String get emailRequiredShort;

  /// No description provided for @emailInvalidShort.
  ///
  /// In en, this message translates to:
  /// **'Invalid email.'**
  String get emailInvalidShort;

  /// No description provided for @otpLabel.
  ///
  /// In en, this message translates to:
  /// **'OTP Code (6-digit)'**
  String get otpLabel;

  /// No description provided for @otpInvalid.
  ///
  /// In en, this message translates to:
  /// **'OTP code must be 6 digits.'**
  String get otpInvalid;

  /// No description provided for @newPasswordLabel.
  ///
  /// In en, this message translates to:
  /// **'New Password'**
  String get newPasswordLabel;

  /// No description provided for @newPasswordHint.
  ///
  /// In en, this message translates to:
  /// **'Minimum 8 characters'**
  String get newPasswordHint;

  /// No description provided for @newPasswordMin.
  ///
  /// In en, this message translates to:
  /// **'Password must be at least 8 characters.'**
  String get newPasswordMin;

  /// No description provided for @confirmPasswordLabel.
  ///
  /// In en, this message translates to:
  /// **'Confirm Password'**
  String get confirmPasswordLabel;

  /// No description provided for @confirmPasswordHint.
  ///
  /// In en, this message translates to:
  /// **'Re-enter password'**
  String get confirmPasswordHint;

  /// No description provided for @passwordMismatch.
  ///
  /// In en, this message translates to:
  /// **'Passwords do not match.'**
  String get passwordMismatch;

  /// No description provided for @verifyAndLogin.
  ///
  /// In en, this message translates to:
  /// **'Verify & Log In'**
  String get verifyAndLogin;

  /// No description provided for @sendOtp.
  ///
  /// In en, this message translates to:
  /// **'Send OTP Code'**
  String get sendOtp;

  /// No description provided for @onboardingSlide1Title.
  ///
  /// In en, this message translates to:
  /// **'Welcome to myWAP'**
  String get onboardingSlide1Title;

  /// No description provided for @onboardingSlide1Body.
  ///
  /// In en, this message translates to:
  /// **'One app for membership, programs and movement services.'**
  String get onboardingSlide1Body;

  /// No description provided for @onboardingSlide2Title.
  ///
  /// In en, this message translates to:
  /// **'Everything in One Place'**
  String get onboardingSlide2Title;

  /// No description provided for @onboardingSlide2Body.
  ///
  /// In en, this message translates to:
  /// **'Follow events, manage membership and discover your organisation’s facilities.'**
  String get onboardingSlide2Body;

  /// No description provided for @onboardingSlide3Title.
  ///
  /// In en, this message translates to:
  /// **'Ready to Get Started'**
  String get onboardingSlide3Title;

  /// No description provided for @onboardingSlide3Body.
  ///
  /// In en, this message translates to:
  /// **'Explore news, articles and programs without an account — or register as a member for full access.'**
  String get onboardingSlide3Body;

  /// No description provided for @onboardingNext.
  ///
  /// In en, this message translates to:
  /// **'Next'**
  String get onboardingNext;

  /// No description provided for @onboardingStart.
  ///
  /// In en, this message translates to:
  /// **'Start'**
  String get onboardingStart;

  /// No description provided for @onboardingSkip.
  ///
  /// In en, this message translates to:
  /// **'Skip'**
  String get onboardingSkip;

  /// No description provided for @publicWelcomeTitle.
  ///
  /// In en, this message translates to:
  /// **'Welcome to myWAP'**
  String get publicWelcomeTitle;

  /// No description provided for @publicWelcomeBody.
  ///
  /// In en, this message translates to:
  /// **'Log in for full member access, or continue exploring content as a guest.'**
  String get publicWelcomeBody;

  /// No description provided for @close.
  ///
  /// In en, this message translates to:
  /// **'Close'**
  String get close;

  /// No description provided for @loadContentError.
  ///
  /// In en, this message translates to:
  /// **'Failed to load content. Please try again.'**
  String get loadContentError;

  /// No description provided for @publicHeroSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Community news, articles, programs and infaq campaigns — open to everyone.'**
  String get publicHeroSubtitle;

  /// No description provided for @viewAll.
  ///
  /// In en, this message translates to:
  /// **'View All'**
  String get viewAll;

  /// No description provided for @articlesTitle.
  ///
  /// In en, this message translates to:
  /// **'Articles'**
  String get articlesTitle;

  /// No description provided for @noArticles.
  ///
  /// In en, this message translates to:
  /// **'No articles at the moment.'**
  String get noArticles;

  /// No description provided for @videoTitle.
  ///
  /// In en, this message translates to:
  /// **'Videos'**
  String get videoTitle;

  /// No description provided for @noVideos.
  ///
  /// In en, this message translates to:
  /// **'No videos at the moment.'**
  String get noVideos;

  /// No description provided for @libraryTitle.
  ///
  /// In en, this message translates to:
  /// **'Library'**
  String get libraryTitle;

  /// No description provided for @noLibrary.
  ///
  /// In en, this message translates to:
  /// **'No library materials at the moment.'**
  String get noLibrary;

  /// No description provided for @libraryMemberPrompt.
  ///
  /// In en, this message translates to:
  /// **'Log in as a member to read in the Digital Library.'**
  String get libraryMemberPrompt;

  /// No description provided for @newsTitle.
  ///
  /// In en, this message translates to:
  /// **'Latest News'**
  String get newsTitle;

  /// No description provided for @noNews.
  ///
  /// In en, this message translates to:
  /// **'No latest news at the moment.'**
  String get noNews;

  /// No description provided for @upcomingEventsTitle.
  ///
  /// In en, this message translates to:
  /// **'Upcoming Programs'**
  String get upcomingEventsTitle;

  /// No description provided for @noUpcomingEvents.
  ///
  /// In en, this message translates to:
  /// **'No programs scheduled.'**
  String get noUpcomingEvents;

  /// No description provided for @infaqTitle.
  ///
  /// In en, this message translates to:
  /// **'Infaq'**
  String get infaqTitle;

  /// No description provided for @noInfaqCampaigns.
  ///
  /// In en, this message translates to:
  /// **'No infaq campaigns at the moment.'**
  String get noInfaqCampaigns;

  /// No description provided for @becomeMemberTitle.
  ///
  /// In en, this message translates to:
  /// **'Become a myWAP Member'**
  String get becomeMemberTitle;

  /// No description provided for @becomeMemberBody.
  ///
  /// In en, this message translates to:
  /// **'Register for access to your member card, fee status, programs, usrah and more.'**
  String get becomeMemberBody;

  /// No description provided for @adminFallback.
  ///
  /// In en, this message translates to:
  /// **'Admin'**
  String get adminFallback;

  /// No description provided for @generalCategory.
  ///
  /// In en, this message translates to:
  /// **'General'**
  String get generalCategory;

  /// No description provided for @loginPromptTitle.
  ///
  /// In en, this message translates to:
  /// **'Login Required'**
  String get loginPromptTitle;

  /// No description provided for @loginPromptBody.
  ///
  /// In en, this message translates to:
  /// **'This feature is available to members. Please log in or register an account to continue.'**
  String get loginPromptBody;

  /// No description provided for @guestBannerMessage.
  ///
  /// In en, this message translates to:
  /// **'Log in for full member access.'**
  String get guestBannerMessage;

  /// No description provided for @profileTitle.
  ///
  /// In en, this message translates to:
  /// **'Profile'**
  String get profileTitle;

  /// No description provided for @unexpectedError.
  ///
  /// In en, this message translates to:
  /// **'Unexpected error.'**
  String get unexpectedError;

  /// No description provided for @unexpectedErrorRetry.
  ///
  /// In en, this message translates to:
  /// **'Unexpected error. Please try again.'**
  String get unexpectedErrorRetry;

  /// No description provided for @saving.
  ///
  /// In en, this message translates to:
  /// **'Saving...'**
  String get saving;

  /// No description provided for @delete.
  ///
  /// In en, this message translates to:
  /// **'Delete'**
  String get delete;

  /// No description provided for @choose.
  ///
  /// In en, this message translates to:
  /// **'Select'**
  String get choose;

  /// No description provided for @notSpecified.
  ///
  /// In en, this message translates to:
  /// **'Not specified'**
  String get notSpecified;

  /// No description provided for @noOrganization.
  ///
  /// In en, this message translates to:
  /// **'No organisation'**
  String get noOrganization;

  /// No description provided for @noBranch.
  ///
  /// In en, this message translates to:
  /// **'No branch'**
  String get noBranch;

  /// No description provided for @memberNoShort.
  ///
  /// In en, this message translates to:
  /// **'Member No.: {number}'**
  String memberNoShort(String number);

  /// No description provided for @phoneLabel.
  ///
  /// In en, this message translates to:
  /// **'Phone'**
  String get phoneLabel;

  /// No description provided for @icNoLabel.
  ///
  /// In en, this message translates to:
  /// **'IC No.'**
  String get icNoLabel;

  /// No description provided for @branchLabel.
  ///
  /// In en, this message translates to:
  /// **'Branch'**
  String get branchLabel;

  /// No description provided for @localityLabel.
  ///
  /// In en, this message translates to:
  /// **'Locality'**
  String get localityLabel;

  /// No description provided for @professionLabel.
  ///
  /// In en, this message translates to:
  /// **'Profession'**
  String get professionLabel;

  /// No description provided for @educationLabel.
  ///
  /// In en, this message translates to:
  /// **'Education'**
  String get educationLabel;

  /// No description provided for @addressLabel.
  ///
  /// In en, this message translates to:
  /// **'Address'**
  String get addressLabel;

  /// No description provided for @emergencyContactLabel.
  ///
  /// In en, this message translates to:
  /// **'Emergency Contact'**
  String get emergencyContactLabel;

  /// No description provided for @noProfileData.
  ///
  /// In en, this message translates to:
  /// **'No profile data found.'**
  String get noProfileData;

  /// No description provided for @contactInfoSection.
  ///
  /// In en, this message translates to:
  /// **'Contact Information'**
  String get contactInfoSection;

  /// No description provided for @detailsSection.
  ///
  /// In en, this message translates to:
  /// **'Details'**
  String get detailsSection;

  /// No description provided for @securitySection.
  ///
  /// In en, this message translates to:
  /// **'Security'**
  String get securitySection;

  /// No description provided for @editProfile.
  ///
  /// In en, this message translates to:
  /// **'Edit Profile'**
  String get editProfile;

  /// No description provided for @memberJourney.
  ///
  /// In en, this message translates to:
  /// **'Member Journey'**
  String get memberJourney;

  /// No description provided for @noJourneyRecords.
  ///
  /// In en, this message translates to:
  /// **'No journey records yet.'**
  String get noJourneyRecords;

  /// No description provided for @accountSection.
  ///
  /// In en, this message translates to:
  /// **'Account'**
  String get accountSection;

  /// No description provided for @profileIncomplete.
  ///
  /// In en, this message translates to:
  /// **'Your profile is incomplete'**
  String get profileIncomplete;

  /// No description provided for @completeProfilePrompt.
  ///
  /// In en, this message translates to:
  /// **'Complete your information for the best experience.'**
  String get completeProfilePrompt;

  /// No description provided for @complete.
  ///
  /// In en, this message translates to:
  /// **'Complete'**
  String get complete;

  /// No description provided for @feeActive.
  ///
  /// In en, this message translates to:
  /// **'Member Fee Active'**
  String get feeActive;

  /// No description provided for @feeUnpaid.
  ///
  /// In en, this message translates to:
  /// **'Fee Unpaid'**
  String get feeUnpaid;

  /// No description provided for @lastPayment.
  ///
  /// In en, this message translates to:
  /// **'Last payment: {date}'**
  String lastPayment(String date);

  /// No description provided for @amountDue.
  ///
  /// In en, this message translates to:
  /// **'Outstanding amount: {amount}'**
  String amountDue(String amount);

  /// No description provided for @reference.
  ///
  /// In en, this message translates to:
  /// **'Reference: {reference}'**
  String reference(String reference);

  /// No description provided for @joinedOrg.
  ///
  /// In en, this message translates to:
  /// **'Joined {organization}'**
  String joinedOrg(String organization);

  /// No description provided for @orgFallback.
  ///
  /// In en, this message translates to:
  /// **'organisation'**
  String get orgFallback;

  /// No description provided for @biometricEnableFailed.
  ///
  /// In en, this message translates to:
  /// **'Failed to enable. Make sure Face ID / fingerprint is set up on your device.'**
  String get biometricEnableFailed;

  /// No description provided for @biometricDisableFailed.
  ///
  /// In en, this message translates to:
  /// **'Failed to disable biometric login. Please try again.'**
  String get biometricDisableFailed;

  /// No description provided for @biometricUnlockSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Unlock your account with device biometrics.'**
  String get biometricUnlockSubtitle;

  /// No description provided for @changePassword.
  ///
  /// In en, this message translates to:
  /// **'Change Password'**
  String get changePassword;

  /// No description provided for @changePasswordSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Update your account password'**
  String get changePasswordSubtitle;

  /// No description provided for @photoUploaded.
  ///
  /// In en, this message translates to:
  /// **'Photo uploaded successfully.'**
  String get photoUploaded;

  /// No description provided for @profileUpdated.
  ///
  /// In en, this message translates to:
  /// **'Your profile photo has been updated.'**
  String get profileUpdated;

  /// No description provided for @profilePhoto.
  ///
  /// In en, this message translates to:
  /// **'Profile Photo'**
  String get profilePhoto;

  /// No description provided for @uploading.
  ///
  /// In en, this message translates to:
  /// **'Uploading...'**
  String get uploading;

  /// No description provided for @changeProfilePhoto.
  ///
  /// In en, this message translates to:
  /// **'Change your profile photo'**
  String get changeProfilePhoto;

  /// No description provided for @deleteAccount.
  ///
  /// In en, this message translates to:
  /// **'Delete Account'**
  String get deleteAccount;

  /// No description provided for @deleteAccountWarning.
  ///
  /// In en, this message translates to:
  /// **'Your account will be permanently deleted. This action cannot be undone. Enter your password to confirm.'**
  String get deleteAccountWarning;

  /// No description provided for @accountDeleted.
  ///
  /// In en, this message translates to:
  /// **'Your account has been deleted.'**
  String get accountDeleted;

  /// No description provided for @deleteAccountSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Permanently delete your account'**
  String get deleteAccountSubtitle;

  /// No description provided for @basicInfoSection.
  ///
  /// In en, this message translates to:
  /// **'Basic Information'**
  String get basicInfoSection;

  /// No description provided for @educationProfessionSection.
  ///
  /// In en, this message translates to:
  /// **'Education & Profession'**
  String get educationProfessionSection;

  /// No description provided for @educationLevelLabel.
  ///
  /// In en, this message translates to:
  /// **'Education Level'**
  String get educationLevelLabel;

  /// No description provided for @currentProfessionLabel.
  ///
  /// In en, this message translates to:
  /// **'Current Profession'**
  String get currentProfessionLabel;

  /// No description provided for @industryLabel.
  ///
  /// In en, this message translates to:
  /// **'Industry'**
  String get industryLabel;

  /// No description provided for @expertiseLabel.
  ///
  /// In en, this message translates to:
  /// **'Expertise'**
  String get expertiseLabel;

  /// No description provided for @topicsLabel.
  ///
  /// In en, this message translates to:
  /// **'Topics of Interest'**
  String get topicsLabel;

  /// No description provided for @commaSeparated.
  ///
  /// In en, this message translates to:
  /// **'Separate with commas'**
  String get commaSeparated;

  /// No description provided for @directorySection.
  ///
  /// In en, this message translates to:
  /// **'Directory'**
  String get directorySection;

  /// No description provided for @showInPublicDirectory.
  ///
  /// In en, this message translates to:
  /// **'Show in Public Directory'**
  String get showInPublicDirectory;

  /// No description provided for @allowOthersViewProfile.
  ///
  /// In en, this message translates to:
  /// **'Allow others to view your profile'**
  String get allowOthersViewProfile;

  /// No description provided for @addressSection.
  ///
  /// In en, this message translates to:
  /// **'Address'**
  String get addressSection;

  /// No description provided for @addressLine1.
  ///
  /// In en, this message translates to:
  /// **'Address (Line 1)'**
  String get addressLine1;

  /// No description provided for @addressLine2.
  ///
  /// In en, this message translates to:
  /// **'Address (Line 2)'**
  String get addressLine2;

  /// No description provided for @postcodeLabel.
  ///
  /// In en, this message translates to:
  /// **'Postcode'**
  String get postcodeLabel;

  /// No description provided for @cityLabel.
  ///
  /// In en, this message translates to:
  /// **'City'**
  String get cityLabel;

  /// No description provided for @stateLabel.
  ///
  /// In en, this message translates to:
  /// **'State'**
  String get stateLabel;

  /// No description provided for @emergencySection.
  ///
  /// In en, this message translates to:
  /// **'Emergency Contact'**
  String get emergencySection;

  /// No description provided for @nameLabel.
  ///
  /// In en, this message translates to:
  /// **'Name'**
  String get nameLabel;

  /// No description provided for @saveChanges.
  ///
  /// In en, this message translates to:
  /// **'Save Changes'**
  String get saveChanges;

  /// No description provided for @organizationLabel.
  ///
  /// In en, this message translates to:
  /// **'Organisation'**
  String get organizationLabel;

  /// No description provided for @changeBranch.
  ///
  /// In en, this message translates to:
  /// **'Change Branch'**
  String get changeBranch;

  /// No description provided for @branchRequestPending.
  ///
  /// In en, this message translates to:
  /// **'Request to change to {branch} is awaiting admin approval.'**
  String branchRequestPending(String branch);

  /// No description provided for @branchRequestSent.
  ///
  /// In en, this message translates to:
  /// **'Branch change request has been sent for admin approval.'**
  String get branchRequestSent;

  /// No description provided for @fieldRequired.
  ///
  /// In en, this message translates to:
  /// **'Please fill in {field}.'**
  String fieldRequired(String field);

  /// No description provided for @emailInvalidEdit.
  ///
  /// In en, this message translates to:
  /// **'Please enter a valid email.'**
  String get emailInvalidEdit;

  /// No description provided for @selectDate.
  ///
  /// In en, this message translates to:
  /// **'Select date'**
  String get selectDate;

  /// No description provided for @profileUpdatedSuccess.
  ///
  /// In en, this message translates to:
  /// **'Profile updated successfully.'**
  String get profileUpdatedSuccess;

  /// No description provided for @chooseNewBranch.
  ///
  /// In en, this message translates to:
  /// **'Please select a new branch.'**
  String get chooseNewBranch;

  /// No description provided for @changeBranchTitle.
  ///
  /// In en, this message translates to:
  /// **'Change Branch'**
  String get changeBranchTitle;

  /// No description provided for @changeBranchNote.
  ///
  /// In en, this message translates to:
  /// **'This request must be approved by an admin before your branch changes.'**
  String get changeBranchNote;

  /// No description provided for @currentBranch.
  ///
  /// In en, this message translates to:
  /// **'Current branch'**
  String get currentBranch;

  /// No description provided for @branchRequestPendingShort.
  ///
  /// In en, this message translates to:
  /// **'Request to {branch} is still pending. Sending a new request will replace it.'**
  String branchRequestPendingShort(String branch);

  /// No description provided for @newBranchLabel.
  ///
  /// In en, this message translates to:
  /// **'New branch'**
  String get newBranchLabel;

  /// No description provided for @selectBranch.
  ///
  /// In en, this message translates to:
  /// **'Select branch'**
  String get selectBranch;

  /// No description provided for @branchOption.
  ///
  /// In en, this message translates to:
  /// **'Branch {id}'**
  String branchOption(String id);

  /// No description provided for @noOtherBranches.
  ///
  /// In en, this message translates to:
  /// **'No other branches are available for your organisation.'**
  String get noOtherBranches;

  /// No description provided for @branchRequestNote.
  ///
  /// In en, this message translates to:
  /// **'The request will be sent to an admin for approval. Your branch changes only after approval.'**
  String get branchRequestNote;

  /// No description provided for @sendRequest.
  ///
  /// In en, this message translates to:
  /// **'Send Request'**
  String get sendRequest;

  /// No description provided for @completeProfileTitle.
  ///
  /// In en, this message translates to:
  /// **'Complete Profile'**
  String get completeProfileTitle;

  /// No description provided for @completeProfilePrompt2.
  ///
  /// In en, this message translates to:
  /// **'Complete the following information to activate your profile.'**
  String get completeProfilePrompt2;

  /// No description provided for @requiredInfoSection.
  ///
  /// In en, this message translates to:
  /// **'Required Information'**
  String get requiredInfoSection;

  /// No description provided for @moreInfoSection.
  ///
  /// In en, this message translates to:
  /// **'More Information'**
  String get moreInfoSection;

  /// No description provided for @interestsSection.
  ///
  /// In en, this message translates to:
  /// **'Interests'**
  String get interestsSection;

  /// No description provided for @saveAndFinish.
  ///
  /// In en, this message translates to:
  /// **'Save & Finish'**
  String get saveAndFinish;

  /// No description provided for @branchRequired.
  ///
  /// In en, this message translates to:
  /// **'Branch *'**
  String get branchRequired;

  /// No description provided for @branchRequiredError.
  ///
  /// In en, this message translates to:
  /// **'Please select a branch.'**
  String get branchRequiredError;

  /// No description provided for @profileCompleted.
  ///
  /// In en, this message translates to:
  /// **'Profile completed successfully.'**
  String get profileCompleted;

  /// No description provided for @passwordUpdated.
  ///
  /// In en, this message translates to:
  /// **'Your password has been updated.'**
  String get passwordUpdated;

  /// No description provided for @changePasswordSubtitle2.
  ///
  /// In en, this message translates to:
  /// **'Update your myWAP account password.'**
  String get changePasswordSubtitle2;

  /// No description provided for @currentPasswordLabel.
  ///
  /// In en, this message translates to:
  /// **'Current Password *'**
  String get currentPasswordLabel;

  /// No description provided for @currentPasswordRequired.
  ///
  /// In en, this message translates to:
  /// **'Please enter your current password.'**
  String get currentPasswordRequired;

  /// No description provided for @newPasswordRequiredLabel.
  ///
  /// In en, this message translates to:
  /// **'New Password *'**
  String get newPasswordRequiredLabel;

  /// No description provided for @newPasswordRequired.
  ///
  /// In en, this message translates to:
  /// **'Please enter a new password.'**
  String get newPasswordRequired;

  /// No description provided for @min8.
  ///
  /// In en, this message translates to:
  /// **'Minimum 8 characters.'**
  String get min8;

  /// No description provided for @confirmNewPasswordLabel.
  ///
  /// In en, this message translates to:
  /// **'Confirm New Password *'**
  String get confirmNewPasswordLabel;

  /// No description provided for @show.
  ///
  /// In en, this message translates to:
  /// **'Show'**
  String get show;

  /// No description provided for @hide.
  ///
  /// In en, this message translates to:
  /// **'Hide'**
  String get hide;

  /// No description provided for @savePassword.
  ///
  /// In en, this message translates to:
  /// **'Save Password'**
  String get savePassword;

  /// No description provided for @organizationJourney.
  ///
  /// In en, this message translates to:
  /// **'Organisation Journey'**
  String get organizationJourney;

  /// No description provided for @programsAttended.
  ///
  /// In en, this message translates to:
  /// **'Programs Attended'**
  String get programsAttended;

  /// No description provided for @genderMale.
  ///
  /// In en, this message translates to:
  /// **'Male'**
  String get genderMale;

  /// No description provided for @genderFemale.
  ///
  /// In en, this message translates to:
  /// **'Female'**
  String get genderFemale;

  /// No description provided for @maritalSingle.
  ///
  /// In en, this message translates to:
  /// **'Single'**
  String get maritalSingle;

  /// No description provided for @maritalMarried.
  ///
  /// In en, this message translates to:
  /// **'Married'**
  String get maritalMarried;

  /// No description provided for @maritalDivorced.
  ///
  /// In en, this message translates to:
  /// **'Divorced'**
  String get maritalDivorced;

  /// No description provided for @maritalWidowed.
  ///
  /// In en, this message translates to:
  /// **'Widowed'**
  String get maritalWidowed;

  /// No description provided for @checkMarkedFields.
  ///
  /// In en, this message translates to:
  /// **'Please review the information marked in red.'**
  String get checkMarkedFields;

  /// No description provided for @genderLabel.
  ///
  /// In en, this message translates to:
  /// **'Gender'**
  String get genderLabel;

  /// No description provided for @maritalStatusLabel.
  ///
  /// In en, this message translates to:
  /// **'Marital Status'**
  String get maritalStatusLabel;

  /// No description provided for @linkedinLabel.
  ///
  /// In en, this message translates to:
  /// **'LinkedIn URL'**
  String get linkedinLabel;

  /// No description provided for @memberFallback.
  ///
  /// In en, this message translates to:
  /// **'Member'**
  String get memberFallback;

  /// No description provided for @greetingMorning.
  ///
  /// In en, this message translates to:
  /// **'Good Morning'**
  String get greetingMorning;

  /// No description provided for @greetingAfternoon.
  ///
  /// In en, this message translates to:
  /// **'Good Afternoon'**
  String get greetingAfternoon;

  /// No description provided for @greetingEvening.
  ///
  /// In en, this message translates to:
  /// **'Good Evening'**
  String get greetingEvening;

  /// No description provided for @greetingSalam.
  ///
  /// In en, this message translates to:
  /// **'Assalamualaikum, {name}'**
  String greetingSalam(String name);

  /// No description provided for @welcomeDefault.
  ///
  /// In en, this message translates to:
  /// **'Welcome to myWAP'**
  String get welcomeDefault;

  /// No description provided for @sectionProgramSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Upcoming events and activities'**
  String get sectionProgramSubtitle;

  /// No description provided for @noUpcomingEventsShort.
  ///
  /// In en, this message translates to:
  /// **'No upcoming events at the moment.'**
  String get noUpcomingEventsShort;

  /// No description provided for @sectionInfaqSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Active donation campaigns'**
  String get sectionInfaqSubtitle;

  /// No description provided for @sectionPolls.
  ///
  /// In en, this message translates to:
  /// **'Polls'**
  String get sectionPolls;

  /// No description provided for @sectionPollsSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Join active polls & surveys'**
  String get sectionPollsSubtitle;

  /// No description provided for @sectionNewsSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Latest news and announcements'**
  String get sectionNewsSubtitle;

  /// No description provided for @sectionArticlesSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Selected writings & features'**
  String get sectionArticlesSubtitle;

  /// No description provided for @sectionLibrary.
  ///
  /// In en, this message translates to:
  /// **'Library'**
  String get sectionLibrary;

  /// No description provided for @sectionLibrarySubtitle.
  ///
  /// In en, this message translates to:
  /// **'Digital books to read'**
  String get sectionLibrarySubtitle;

  /// No description provided for @quickFees.
  ///
  /// In en, this message translates to:
  /// **'Fees'**
  String get quickFees;

  /// No description provided for @quickBook.
  ///
  /// In en, this message translates to:
  /// **'Book'**
  String get quickBook;

  /// No description provided for @quickNews.
  ///
  /// In en, this message translates to:
  /// **'News'**
  String get quickNews;

  /// No description provided for @quickCard.
  ///
  /// In en, this message translates to:
  /// **'Digital ID'**
  String get quickCard;

  /// No description provided for @quickUsrah.
  ///
  /// In en, this message translates to:
  /// **'Usrah'**
  String get quickUsrah;

  /// No description provided for @quickMall.
  ///
  /// In en, this message translates to:
  /// **'Mall'**
  String get quickMall;

  /// No description provided for @quickInvite.
  ///
  /// In en, this message translates to:
  /// **'Invite'**
  String get quickInvite;

  /// No description provided for @memberCardTitle.
  ///
  /// In en, this message translates to:
  /// **'MEMBERSHIP CARD · {organization}'**
  String memberCardTitle(String organization);

  /// No description provided for @orgFallbackUpper.
  ///
  /// In en, this message translates to:
  /// **'ORGANISATION'**
  String get orgFallbackUpper;

  /// No description provided for @sinceLabel.
  ///
  /// In en, this message translates to:
  /// **'Since'**
  String get sinceLabel;

  /// No description provided for @activeMember.
  ///
  /// In en, this message translates to:
  /// **'Active Member'**
  String get activeMember;

  /// No description provided for @scanQrHint.
  ///
  /// In en, this message translates to:
  /// **'Scan this QR for membership verification.'**
  String get scanQrHint;

  /// No description provided for @viewFullCard.
  ///
  /// In en, this message translates to:
  /// **'View Full Card'**
  String get viewFullCard;

  /// No description provided for @eventPhysical.
  ///
  /// In en, this message translates to:
  /// **'PHYSICAL'**
  String get eventPhysical;

  /// No description provided for @eventOnline.
  ///
  /// In en, this message translates to:
  /// **'ONLINE'**
  String get eventOnline;

  /// No description provided for @nextEvent.
  ///
  /// In en, this message translates to:
  /// **'NEXT EVENT'**
  String get nextEvent;

  /// No description provided for @collectedPercent.
  ///
  /// In en, this message translates to:
  /// **'{percent}% collected'**
  String collectedPercent(int percent);

  /// No description provided for @pollAnswered.
  ///
  /// In en, this message translates to:
  /// **'You have answered'**
  String get pollAnswered;

  /// No description provided for @pollNotAnswered.
  ///
  /// In en, this message translates to:
  /// **'Not answered · {count} responses'**
  String pollNotAnswered(int count);

  /// No description provided for @noFileToOpen.
  ///
  /// In en, this message translates to:
  /// **'No file to open.'**
  String get noFileToOpen;

  /// No description provided for @biometricFaceIdLoginLower.
  ///
  /// In en, this message translates to:
  /// **'Log in with Face ID'**
  String get biometricFaceIdLoginLower;

  /// No description provided for @biometricFingerprintLoginLower.
  ///
  /// In en, this message translates to:
  /// **'Log in with Fingerprint'**
  String get biometricFingerprintLoginLower;

  /// No description provided for @ecomMarketTitle.
  ///
  /// In en, this message translates to:
  /// **'Market'**
  String get ecomMarketTitle;

  /// No description provided for @ecomCartTitle.
  ///
  /// In en, this message translates to:
  /// **'Cart'**
  String get ecomCartTitle;

  /// No description provided for @ecomSearchProductsHint.
  ///
  /// In en, this message translates to:
  /// **'Search products...'**
  String get ecomSearchProductsHint;

  /// No description provided for @ecomSortLabel.
  ///
  /// In en, this message translates to:
  /// **'Sort:'**
  String get ecomSortLabel;

  /// No description provided for @ecomSortLatest.
  ///
  /// In en, this message translates to:
  /// **'Latest'**
  String get ecomSortLatest;

  /// No description provided for @ecomSortPriceLow.
  ///
  /// In en, this message translates to:
  /// **'Price: Low'**
  String get ecomSortPriceLow;

  /// No description provided for @ecomSortPriceHigh.
  ///
  /// In en, this message translates to:
  /// **'Price: High'**
  String get ecomSortPriceHigh;

  /// No description provided for @ecomFilterAll.
  ///
  /// In en, this message translates to:
  /// **'All'**
  String get ecomFilterAll;

  /// No description provided for @ecomNoProducts.
  ///
  /// In en, this message translates to:
  /// **'No products found.'**
  String get ecomNoProducts;

  /// No description provided for @ecomProductFallback.
  ///
  /// In en, this message translates to:
  /// **'Product'**
  String get ecomProductFallback;

  /// No description provided for @ecomAddedToCart.
  ///
  /// In en, this message translates to:
  /// **'Added to cart.'**
  String get ecomAddedToCart;

  /// No description provided for @ecomMemberBadge.
  ///
  /// In en, this message translates to:
  /// **'Member'**
  String get ecomMemberBadge;

  /// No description provided for @ecomBuy.
  ///
  /// In en, this message translates to:
  /// **'Buy'**
  String get ecomBuy;

  /// No description provided for @ecomProductDetailTitle.
  ///
  /// In en, this message translates to:
  /// **'Product Details'**
  String get ecomProductDetailTitle;

  /// No description provided for @ecomProductNotFound.
  ///
  /// In en, this message translates to:
  /// **'Product not found.'**
  String get ecomProductNotFound;

  /// No description provided for @ecomMemberPrice.
  ///
  /// In en, this message translates to:
  /// **'Member Price'**
  String get ecomMemberPrice;

  /// No description provided for @ecomDescription.
  ///
  /// In en, this message translates to:
  /// **'Description'**
  String get ecomDescription;

  /// No description provided for @ecomOptions.
  ///
  /// In en, this message translates to:
  /// **'Options'**
  String get ecomOptions;

  /// No description provided for @ecomRelatedProducts.
  ///
  /// In en, this message translates to:
  /// **'Related Products'**
  String get ecomRelatedProducts;

  /// No description provided for @ecomQuantity.
  ///
  /// In en, this message translates to:
  /// **'Quantity'**
  String get ecomQuantity;

  /// No description provided for @ecomAddToCart.
  ///
  /// In en, this message translates to:
  /// **'Add to Cart'**
  String get ecomAddToCart;

  /// No description provided for @ecomBuyNow.
  ///
  /// In en, this message translates to:
  /// **'Buy Now'**
  String get ecomBuyNow;

  /// No description provided for @ecomCartEmpty.
  ///
  /// In en, this message translates to:
  /// **'Your cart is empty.'**
  String get ecomCartEmpty;

  /// No description provided for @ecomExploreMarket.
  ///
  /// In en, this message translates to:
  /// **'Explore Market'**
  String get ecomExploreMarket;

  /// No description provided for @ecomRemoveItemTitle.
  ///
  /// In en, this message translates to:
  /// **'Remove Item?'**
  String get ecomRemoveItemTitle;

  /// No description provided for @ecomRemoveItemMessage.
  ///
  /// In en, this message translates to:
  /// **'Remove \"{name}\" from the cart?'**
  String ecomRemoveItemMessage(String name);

  /// No description provided for @ecomRemove.
  ///
  /// In en, this message translates to:
  /// **'Remove'**
  String get ecomRemove;

  /// No description provided for @ecomTotal.
  ///
  /// In en, this message translates to:
  /// **'Total'**
  String get ecomTotal;

  /// No description provided for @ecomContinueCheckout.
  ///
  /// In en, this message translates to:
  /// **'Continue to Checkout'**
  String get ecomContinueCheckout;

  /// No description provided for @ecomOrderPaidSuccess.
  ///
  /// In en, this message translates to:
  /// **'Order placed and paid successfully.'**
  String get ecomOrderPaidSuccess;

  /// No description provided for @ecomOrderCreatedAwaitPayment.
  ///
  /// In en, this message translates to:
  /// **'Order placed. Please complete your payment.'**
  String get ecomOrderCreatedAwaitPayment;

  /// No description provided for @ecomOrderCreatedSuccess.
  ///
  /// In en, this message translates to:
  /// **'Order placed successfully!'**
  String get ecomOrderCreatedSuccess;

  /// No description provided for @ecomCheckoutTitle.
  ///
  /// In en, this message translates to:
  /// **'Checkout'**
  String get ecomCheckoutTitle;

  /// No description provided for @ecomProcessingOrder.
  ///
  /// In en, this message translates to:
  /// **'Processing order...'**
  String get ecomProcessingOrder;

  /// No description provided for @ecomShippingInfo.
  ///
  /// In en, this message translates to:
  /// **'Shipping Information'**
  String get ecomShippingInfo;

  /// No description provided for @ecomFullNameHint.
  ///
  /// In en, this message translates to:
  /// **'Full name'**
  String get ecomFullNameHint;

  /// No description provided for @ecomNameRequired.
  ///
  /// In en, this message translates to:
  /// **'Name is required'**
  String get ecomNameRequired;

  /// No description provided for @ecomPhoneRequired.
  ///
  /// In en, this message translates to:
  /// **'Phone number is required'**
  String get ecomPhoneRequired;

  /// No description provided for @ecomAddressOptional.
  ///
  /// In en, this message translates to:
  /// **'Address (optional)'**
  String get ecomAddressOptional;

  /// No description provided for @ecomPostcodeOptional.
  ///
  /// In en, this message translates to:
  /// **'Postcode (optional)'**
  String get ecomPostcodeOptional;

  /// No description provided for @ecomOrderSummary.
  ///
  /// In en, this message translates to:
  /// **'Order Summary'**
  String get ecomOrderSummary;

  /// No description provided for @ecomSubmitOrder.
  ///
  /// In en, this message translates to:
  /// **'Submit Order'**
  String get ecomSubmitOrder;

  /// No description provided for @ecomOrdersTitle.
  ///
  /// In en, this message translates to:
  /// **'Orders'**
  String get ecomOrdersTitle;

  /// No description provided for @ecomNoOrders.
  ///
  /// In en, this message translates to:
  /// **'No orders at the moment.'**
  String get ecomNoOrders;

  /// No description provided for @ecomOrderNumber.
  ///
  /// In en, this message translates to:
  /// **'Order #{number}'**
  String ecomOrderNumber(String number);

  /// No description provided for @ecomOrderMeta.
  ///
  /// In en, this message translates to:
  /// **'{count} items • {date}'**
  String ecomOrderMeta(int count, String date);

  /// No description provided for @ecomTotalLabel.
  ///
  /// In en, this message translates to:
  /// **'Total: {amount}'**
  String ecomTotalLabel(String amount);

  /// No description provided for @ecomParcelShippedHint.
  ///
  /// In en, this message translates to:
  /// **'Parcel has been shipped — tap to Track & Receive'**
  String get ecomParcelShippedHint;

  /// No description provided for @ecomOrderReceived.
  ///
  /// In en, this message translates to:
  /// **'Order receipt confirmed. Thank you!'**
  String get ecomOrderReceived;

  /// No description provided for @ecomPaymentSuccess.
  ///
  /// In en, this message translates to:
  /// **'Payment successful. Thank you!'**
  String get ecomPaymentSuccess;

  /// No description provided for @ecomPaymentIncomplete.
  ///
  /// In en, this message translates to:
  /// **'Payment not completed. Please try again.'**
  String get ecomPaymentIncomplete;

  /// No description provided for @ecomOrderDetailTitle.
  ///
  /// In en, this message translates to:
  /// **'Order Details'**
  String get ecomOrderDetailTitle;

  /// No description provided for @ecomPay.
  ///
  /// In en, this message translates to:
  /// **'Pay'**
  String get ecomPay;

  /// No description provided for @ecomReceiveOrder.
  ///
  /// In en, this message translates to:
  /// **'Receive Order'**
  String get ecomReceiveOrder;

  /// No description provided for @ecomOrderNotFound.
  ///
  /// In en, this message translates to:
  /// **'Order not found.'**
  String get ecomOrderNotFound;

  /// No description provided for @ecomItemsSection.
  ///
  /// In en, this message translates to:
  /// **'Items'**
  String get ecomItemsSection;

  /// No description provided for @ecomNoItems.
  ///
  /// In en, this message translates to:
  /// **'No items.'**
  String get ecomNoItems;

  /// No description provided for @ecomPaymentSection.
  ///
  /// In en, this message translates to:
  /// **'Payment'**
  String get ecomPaymentSection;

  /// No description provided for @ecomNoPayments.
  ///
  /// In en, this message translates to:
  /// **'No payments.'**
  String get ecomNoPayments;

  /// No description provided for @ecomSummary.
  ///
  /// In en, this message translates to:
  /// **'Summary'**
  String get ecomSummary;

  /// No description provided for @ecomSubtotal.
  ///
  /// In en, this message translates to:
  /// **'Subtotal'**
  String get ecomSubtotal;

  /// No description provided for @ecomPostage.
  ///
  /// In en, this message translates to:
  /// **'Postage'**
  String get ecomPostage;

  /// No description provided for @ecomTrackingCopied.
  ///
  /// In en, this message translates to:
  /// **'Tracking no. copied to clipboard.'**
  String get ecomTrackingCopied;

  /// No description provided for @ecomParcelTracking.
  ///
  /// In en, this message translates to:
  /// **'Parcel Tracking'**
  String get ecomParcelTracking;

  /// No description provided for @ecomTrack.
  ///
  /// In en, this message translates to:
  /// **'Track'**
  String get ecomTrack;

  /// No description provided for @ecomCopyTrackingTooltip.
  ///
  /// In en, this message translates to:
  /// **'Copy Tracking No.'**
  String get ecomCopyTrackingTooltip;

  /// No description provided for @ecomPaymentFallback.
  ///
  /// In en, this message translates to:
  /// **'Payment'**
  String get ecomPaymentFallback;

  /// No description provided for @ecomStatusPendingPayment.
  ///
  /// In en, this message translates to:
  /// **'Awaiting Payment'**
  String get ecomStatusPendingPayment;

  /// No description provided for @ecomStatusPaid.
  ///
  /// In en, this message translates to:
  /// **'Paid'**
  String get ecomStatusPaid;

  /// No description provided for @ecomStatusProcessing.
  ///
  /// In en, this message translates to:
  /// **'Processing'**
  String get ecomStatusProcessing;

  /// No description provided for @ecomStatusShipped.
  ///
  /// In en, this message translates to:
  /// **'Shipped'**
  String get ecomStatusShipped;

  /// No description provided for @ecomStatusCompleted.
  ///
  /// In en, this message translates to:
  /// **'Completed'**
  String get ecomStatusCompleted;

  /// No description provided for @ecomStatusCancelled.
  ///
  /// In en, this message translates to:
  /// **'Cancelled'**
  String get ecomStatusCancelled;

  /// No description provided for @ecomPaymentStatusSuccess.
  ///
  /// In en, this message translates to:
  /// **'Successful'**
  String get ecomPaymentStatusSuccess;

  /// No description provided for @ecomPaymentStatusPending.
  ///
  /// In en, this message translates to:
  /// **'Pending'**
  String get ecomPaymentStatusPending;

  /// No description provided for @ecomPaymentStatusFailed.
  ///
  /// In en, this message translates to:
  /// **'Failed'**
  String get ecomPaymentStatusFailed;

  /// No description provided for @facTitle.
  ///
  /// In en, this message translates to:
  /// **'Facilities'**
  String get facTitle;

  /// No description provided for @facNoFacilities.
  ///
  /// In en, this message translates to:
  /// **'No facilities at the moment.'**
  String get facNoFacilities;

  /// No description provided for @facMyBookings.
  ///
  /// In en, this message translates to:
  /// **'My Bookings'**
  String get facMyBookings;

  /// No description provided for @facNoBookings.
  ///
  /// In en, this message translates to:
  /// **'No bookings at the moment.'**
  String get facNoBookings;

  /// No description provided for @facTypeDaily.
  ///
  /// In en, this message translates to:
  /// **'Daily'**
  String get facTypeDaily;

  /// No description provided for @facTypeHalfDay.
  ///
  /// In en, this message translates to:
  /// **'Half Day'**
  String get facTypeHalfDay;

  /// No description provided for @facTypeHourly.
  ///
  /// In en, this message translates to:
  /// **'Hourly'**
  String get facTypeHourly;

  /// No description provided for @facCapacity.
  ///
  /// In en, this message translates to:
  /// **'Capacity: {capacity}'**
  String facCapacity(String capacity);

  /// No description provided for @facUnitDay.
  ///
  /// In en, this message translates to:
  /// **'day'**
  String get facUnitDay;

  /// No description provided for @facUnitHalfDay.
  ///
  /// In en, this message translates to:
  /// **'half day'**
  String get facUnitHalfDay;

  /// No description provided for @facUnitHour.
  ///
  /// In en, this message translates to:
  /// **'hour'**
  String get facUnitHour;

  /// No description provided for @facMemberPrice.
  ///
  /// In en, this message translates to:
  /// **'Member: {amount}'**
  String facMemberPrice(String amount);

  /// No description provided for @facViewBook.
  ///
  /// In en, this message translates to:
  /// **'View & Book'**
  String get facViewBook;

  /// No description provided for @facIntroTitle.
  ///
  /// In en, this message translates to:
  /// **'Services & Facilities'**
  String get facIntroTitle;

  /// No description provided for @facIntroSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Book available service spaces and facilities.'**
  String get facIntroSubtitle;

  /// No description provided for @facAdminRemarks.
  ///
  /// In en, this message translates to:
  /// **'Admin note: {remarks}'**
  String facAdminRemarks(String remarks);

  /// No description provided for @facDetailTitle.
  ///
  /// In en, this message translates to:
  /// **'Facility Details'**
  String get facDetailTitle;

  /// No description provided for @facDescription.
  ///
  /// In en, this message translates to:
  /// **'Description'**
  String get facDescription;

  /// No description provided for @facUntil.
  ///
  /// In en, this message translates to:
  /// **'until {end}'**
  String facUntil(String end);

  /// No description provided for @facErrorCompleteDateTime.
  ///
  /// In en, this message translates to:
  /// **'Please complete the date, start time and duration.'**
  String get facErrorCompleteDateTime;

  /// No description provided for @facErrorSlotPast.
  ///
  /// In en, this message translates to:
  /// **'The selected slot has passed. Please choose another time.'**
  String get facErrorSlotPast;

  /// No description provided for @facErrorContactRequired.
  ///
  /// In en, this message translates to:
  /// **'Please complete the contact information.'**
  String get facErrorContactRequired;

  /// No description provided for @facBookingSubmitted.
  ///
  /// In en, this message translates to:
  /// **'Booking submitted successfully.'**
  String get facBookingSubmitted;

  /// No description provided for @facBookFacility.
  ///
  /// In en, this message translates to:
  /// **'Book {name}'**
  String facBookFacility(String name);

  /// No description provided for @facBookingInstructions.
  ///
  /// In en, this message translates to:
  /// **'Simple steps: choose a day, start time and duration. Overlapping slots will be rejected.'**
  String get facBookingInstructions;

  /// No description provided for @facSelectDay.
  ///
  /// In en, this message translates to:
  /// **'Select Day'**
  String get facSelectDay;

  /// No description provided for @facStartTime.
  ///
  /// In en, this message translates to:
  /// **'Start Time'**
  String get facStartTime;

  /// No description provided for @facBookingDuration.
  ///
  /// In en, this message translates to:
  /// **'Booking Duration'**
  String get facBookingDuration;

  /// No description provided for @facSelectStartFirst.
  ///
  /// In en, this message translates to:
  /// **'Select a start time first to see available durations.'**
  String get facSelectStartFirst;

  /// No description provided for @facContactName.
  ///
  /// In en, this message translates to:
  /// **'Contact Name'**
  String get facContactName;

  /// No description provided for @facContactPhone.
  ///
  /// In en, this message translates to:
  /// **'Contact Phone'**
  String get facContactPhone;

  /// No description provided for @facSubmitBooking.
  ///
  /// In en, this message translates to:
  /// **'Submit Booking'**
  String get facSubmitBooking;

  /// No description provided for @facDurationOneDay.
  ///
  /// In en, this message translates to:
  /// **'1 Day'**
  String get facDurationOneDay;

  /// No description provided for @facDurationDays.
  ///
  /// In en, this message translates to:
  /// **'{count} Days'**
  String facDurationDays(int count);

  /// No description provided for @facDurationHours.
  ///
  /// In en, this message translates to:
  /// **'{count} Hours'**
  String facDurationHours(int count);

  /// No description provided for @facToday.
  ///
  /// In en, this message translates to:
  /// **'Today'**
  String get facToday;

  /// No description provided for @facBookingSummary.
  ///
  /// In en, this message translates to:
  /// **'Booking Summary'**
  String get facBookingSummary;

  /// No description provided for @facStart.
  ///
  /// In en, this message translates to:
  /// **'Start'**
  String get facStart;

  /// No description provided for @facEnd.
  ///
  /// In en, this message translates to:
  /// **'End'**
  String get facEnd;

  /// No description provided for @facEstimate.
  ///
  /// In en, this message translates to:
  /// **'Estimate'**
  String get facEstimate;

  /// No description provided for @facMemberRateNote.
  ///
  /// In en, this message translates to:
  /// **'Member rate. Payment is confirmed on site.'**
  String get facMemberRateNote;

  /// No description provided for @facStandardRateNote.
  ///
  /// In en, this message translates to:
  /// **'Standard rate. Payment is confirmed on site.'**
  String get facStandardRateNote;

  /// No description provided for @facStatusApproved.
  ///
  /// In en, this message translates to:
  /// **'Approved'**
  String get facStatusApproved;

  /// No description provided for @facStatusRejected.
  ///
  /// In en, this message translates to:
  /// **'Rejected'**
  String get facStatusRejected;

  /// No description provided for @facStatusPending.
  ///
  /// In en, this message translates to:
  /// **'Pending'**
  String get facStatusPending;

  /// No description provided for @facStatusPendingApproval.
  ///
  /// In en, this message translates to:
  /// **'Awaiting approval'**
  String get facStatusPendingApproval;

  /// No description provided for @facBookingSent.
  ///
  /// In en, this message translates to:
  /// **'Booking Sent'**
  String get facBookingSent;

  /// No description provided for @facStatusLine.
  ///
  /// In en, this message translates to:
  /// **'Status: {status}'**
  String facStatusLine(String status);

  /// No description provided for @facBookingConfirmationNote.
  ///
  /// In en, this message translates to:
  /// **'You will be notified once the booking is confirmed. Payment is made when you arrive at the location.'**
  String get facBookingConfirmationNote;

  /// No description provided for @facDone.
  ///
  /// In en, this message translates to:
  /// **'Done'**
  String get facDone;

  /// No description provided for @facPaymentStatusPaid.
  ///
  /// In en, this message translates to:
  /// **'Paid'**
  String get facPaymentStatusPaid;

  /// No description provided for @facPaymentStatusUnpaid.
  ///
  /// In en, this message translates to:
  /// **'Unpaid'**
  String get facPaymentStatusUnpaid;

  /// No description provided for @pollTitle.
  ///
  /// In en, this message translates to:
  /// **'Polls'**
  String get pollTitle;

  /// No description provided for @pollNoPolls.
  ///
  /// In en, this message translates to:
  /// **'No polls at the moment.'**
  String get pollNoPolls;

  /// No description provided for @pollLatestPolls.
  ///
  /// In en, this message translates to:
  /// **'Latest Polls'**
  String get pollLatestPolls;

  /// No description provided for @pollNoOpenPolls.
  ///
  /// In en, this message translates to:
  /// **'No open polls at the moment.'**
  String get pollNoOpenPolls;

  /// No description provided for @pollAnsweredSection.
  ///
  /// In en, this message translates to:
  /// **'Answered'**
  String get pollAnsweredSection;

  /// No description provided for @pollNoAnswered.
  ///
  /// In en, this message translates to:
  /// **'No polls answered yet.'**
  String get pollNoAnswered;

  /// No description provided for @pollTypePoll.
  ///
  /// In en, this message translates to:
  /// **'Poll'**
  String get pollTypePoll;

  /// No description provided for @pollTypeSurvey.
  ///
  /// In en, this message translates to:
  /// **'Survey'**
  String get pollTypeSurvey;

  /// No description provided for @pollResponsesCount.
  ///
  /// In en, this message translates to:
  /// **'{count} responses'**
  String pollResponsesCount(int count);

  /// No description provided for @pollEnded.
  ///
  /// In en, this message translates to:
  /// **'Ended'**
  String get pollEnded;

  /// No description provided for @pollEndsAt.
  ///
  /// In en, this message translates to:
  /// **'Ends: {date}'**
  String pollEndsAt(String date);

  /// No description provided for @pollNoEndDate.
  ///
  /// In en, this message translates to:
  /// **'No end date'**
  String get pollNoEndDate;

  /// No description provided for @pollAnswerAll.
  ///
  /// In en, this message translates to:
  /// **'Please answer all questions.'**
  String get pollAnswerAll;

  /// No description provided for @pollDetailTitle.
  ///
  /// In en, this message translates to:
  /// **'Poll Details'**
  String get pollDetailTitle;

  /// No description provided for @pollEndedLong.
  ///
  /// In en, this message translates to:
  /// **'Poll has ended'**
  String get pollEndedLong;

  /// No description provided for @pollEndsOn.
  ///
  /// In en, this message translates to:
  /// **'Ends on {date}'**
  String pollEndsOn(String date);

  /// No description provided for @pollSubmitAnswers.
  ///
  /// In en, this message translates to:
  /// **'Submit Answers'**
  String get pollSubmitAnswers;

  /// No description provided for @pollResultsTitle.
  ///
  /// In en, this message translates to:
  /// **'Poll Results'**
  String get pollResultsTitle;

  /// No description provided for @pollResultsFallback.
  ///
  /// In en, this message translates to:
  /// **'Results'**
  String get pollResultsFallback;

  /// No description provided for @pollTotalAnswers.
  ///
  /// In en, this message translates to:
  /// **'Total answers: {count}'**
  String pollTotalAnswers(int count);

  /// No description provided for @infaqFilterAll.
  ///
  /// In en, this message translates to:
  /// **'All'**
  String get infaqFilterAll;

  /// No description provided for @infaqTypeFundraise.
  ///
  /// In en, this message translates to:
  /// **'Fundraise'**
  String get infaqTypeFundraise;

  /// No description provided for @infaqTypeFreeDonation.
  ///
  /// In en, this message translates to:
  /// **'Free Donation'**
  String get infaqTypeFreeDonation;

  /// No description provided for @infaqPercentCollected.
  ///
  /// In en, this message translates to:
  /// **'{percent}% collected'**
  String infaqPercentCollected(String percent);

  /// No description provided for @infaqTargetAmount.
  ///
  /// In en, this message translates to:
  /// **'Target {amount}'**
  String infaqTargetAmount(String amount);

  /// No description provided for @infaqDaysRunning.
  ///
  /// In en, this message translates to:
  /// **'{days} days running'**
  String infaqDaysRunning(int days);

  /// No description provided for @infaqCampaignsTitle.
  ///
  /// In en, this message translates to:
  /// **'Infaq Campaigns'**
  String get infaqCampaignsTitle;

  /// No description provided for @infaqIntroSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Join us in contributing and helping those in need.'**
  String get infaqIntroSubtitle;

  /// No description provided for @infaqActiveCampaigns.
  ///
  /// In en, this message translates to:
  /// **'ACTIVE CAMPAIGNS'**
  String get infaqActiveCampaigns;

  /// No description provided for @infaqCollected.
  ///
  /// In en, this message translates to:
  /// **'COLLECTED'**
  String get infaqCollected;

  /// No description provided for @infaqDonationLinkUnavailable.
  ///
  /// In en, this message translates to:
  /// **'Donation link is unavailable.'**
  String get infaqDonationLinkUnavailable;

  /// No description provided for @infaqDonationLinkInvalid.
  ///
  /// In en, this message translates to:
  /// **'Invalid donation link.'**
  String get infaqDonationLinkInvalid;

  /// No description provided for @infaqDonationLinkOpenFailed.
  ///
  /// In en, this message translates to:
  /// **'Unable to open the donation link.'**
  String get infaqDonationLinkOpenFailed;

  /// No description provided for @infaqDetailTitle.
  ///
  /// In en, this message translates to:
  /// **'Infaq Details'**
  String get infaqDetailTitle;

  /// No description provided for @infaqDonorsCount.
  ///
  /// In en, this message translates to:
  /// **'{count} donors'**
  String infaqDonorsCount(int count);

  /// No description provided for @infaqDescription.
  ///
  /// In en, this message translates to:
  /// **'Description'**
  String get infaqDescription;

  /// No description provided for @infaqDonateNow.
  ///
  /// In en, this message translates to:
  /// **'Donate Now'**
  String get infaqDonateNow;

  /// No description provided for @infaqRecentDonations.
  ///
  /// In en, this message translates to:
  /// **'Recent Donations'**
  String get infaqRecentDonations;

  /// No description provided for @infaqOtherInfaqs.
  ///
  /// In en, this message translates to:
  /// **'Other Infaq'**
  String get infaqOtherInfaqs;

  /// No description provided for @infaqAnonymousDonor.
  ///
  /// In en, this message translates to:
  /// **'Anonymous'**
  String get infaqAnonymousDonor;

  /// No description provided for @referralTitle.
  ///
  /// In en, this message translates to:
  /// **'Invite Member'**
  String get referralTitle;

  /// No description provided for @referralInviteFriends.
  ///
  /// In en, this message translates to:
  /// **'Invite friends to join myWAP'**
  String get referralInviteFriends;

  /// No description provided for @referralMemberNo.
  ///
  /// In en, this message translates to:
  /// **'Member No.: {number}'**
  String referralMemberNo(String number);

  /// No description provided for @referralCopyLink.
  ///
  /// In en, this message translates to:
  /// **'Copy Referral Link'**
  String get referralCopyLink;

  /// No description provided for @referralStatTotal.
  ///
  /// In en, this message translates to:
  /// **'Total'**
  String get referralStatTotal;

  /// No description provided for @referralStatActive.
  ///
  /// In en, this message translates to:
  /// **'Active'**
  String get referralStatActive;

  /// No description provided for @referralStatPending.
  ///
  /// In en, this message translates to:
  /// **'Pending'**
  String get referralStatPending;

  /// No description provided for @referralReferredMembers.
  ///
  /// In en, this message translates to:
  /// **'Referred Members'**
  String get referralReferredMembers;

  /// No description provided for @referralNoReferredMembers.
  ///
  /// In en, this message translates to:
  /// **'No members have been invited using your link yet.'**
  String get referralNoReferredMembers;

  /// No description provided for @directorySearchHint.
  ///
  /// In en, this message translates to:
  /// **'Search members, industries, expertise...'**
  String get directorySearchHint;

  /// No description provided for @directoryFilterAll.
  ///
  /// In en, this message translates to:
  /// **'All'**
  String get directoryFilterAll;

  /// No description provided for @directoryNoMembers.
  ///
  /// In en, this message translates to:
  /// **'No members found.'**
  String get directoryNoMembers;

  /// No description provided for @directoryNoLink.
  ///
  /// In en, this message translates to:
  /// **'No link'**
  String get directoryNoLink;

  /// No description provided for @newsLoadMore.
  ///
  /// In en, this message translates to:
  /// **'Load More'**
  String get newsLoadMore;

  /// No description provided for @newsLoginToReact.
  ///
  /// In en, this message translates to:
  /// **'Log in as a member to react.'**
  String get newsLoginToReact;

  /// No description provided for @newsAllOrganizations.
  ///
  /// In en, this message translates to:
  /// **'All Organisations'**
  String get newsAllOrganizations;

  /// No description provided for @newsSearchVideos.
  ///
  /// In en, this message translates to:
  /// **'Search videos…'**
  String get newsSearchVideos;

  /// No description provided for @newsLiveFilter.
  ///
  /// In en, this message translates to:
  /// **'Live'**
  String get newsLiveFilter;

  /// No description provided for @newsNoVideoSearch.
  ///
  /// In en, this message translates to:
  /// **'No videos match your search.'**
  String get newsNoVideoSearch;

  /// No description provided for @newsVideoOpenFailed.
  ///
  /// In en, this message translates to:
  /// **'Unable to open the video.'**
  String get newsVideoOpenFailed;

  /// No description provided for @newsSortLatest.
  ///
  /// In en, this message translates to:
  /// **'Latest'**
  String get newsSortLatest;

  /// No description provided for @newsCommentSent.
  ///
  /// In en, this message translates to:
  /// **'Comment sent successfully.'**
  String get newsCommentSent;

  /// No description provided for @newsCommentFailed.
  ///
  /// In en, this message translates to:
  /// **'Failed to send comment. Please try again.'**
  String get newsCommentFailed;

  /// No description provided for @newsReportSpam.
  ///
  /// In en, this message translates to:
  /// **'Spam or advertising'**
  String get newsReportSpam;

  /// No description provided for @newsReportHarassment.
  ///
  /// In en, this message translates to:
  /// **'Harassment / bullying'**
  String get newsReportHarassment;

  /// No description provided for @newsReportHate.
  ///
  /// In en, this message translates to:
  /// **'Hate speech'**
  String get newsReportHate;

  /// No description provided for @newsReportSexual.
  ///
  /// In en, this message translates to:
  /// **'Sexual content'**
  String get newsReportSexual;

  /// No description provided for @newsReportViolence.
  ///
  /// In en, this message translates to:
  /// **'Violence'**
  String get newsReportViolence;

  /// No description provided for @newsReportMisinformation.
  ///
  /// In en, this message translates to:
  /// **'False information'**
  String get newsReportMisinformation;

  /// No description provided for @newsReportOther.
  ///
  /// In en, this message translates to:
  /// **'Other'**
  String get newsReportOther;

  /// No description provided for @newsReportCommentTitle.
  ///
  /// In en, this message translates to:
  /// **'Report Comment'**
  String get newsReportCommentTitle;

  /// No description provided for @newsReportSent.
  ///
  /// In en, this message translates to:
  /// **'Report sent. Thank you.'**
  String get newsReportSent;

  /// No description provided for @newsReportFailed.
  ///
  /// In en, this message translates to:
  /// **'Failed to send report.'**
  String get newsReportFailed;

  /// No description provided for @newsBlockUserTitle.
  ///
  /// In en, this message translates to:
  /// **'Block User'**
  String get newsBlockUserTitle;

  /// No description provided for @newsBlockConfirmBody.
  ///
  /// In en, this message translates to:
  /// **'Comments from {name} will no longer be shown to you.'**
  String newsBlockConfirmBody(String name);

  /// No description provided for @newsThisUser.
  ///
  /// In en, this message translates to:
  /// **'this user'**
  String get newsThisUser;

  /// No description provided for @newsBlock.
  ///
  /// In en, this message translates to:
  /// **'Block'**
  String get newsBlock;

  /// No description provided for @newsUserBlocked.
  ///
  /// In en, this message translates to:
  /// **'The user has been blocked.'**
  String get newsUserBlocked;

  /// No description provided for @newsBlockFailed.
  ///
  /// In en, this message translates to:
  /// **'Failed to block. Please try again.'**
  String get newsBlockFailed;

  /// No description provided for @newsCommentsCount.
  ///
  /// In en, this message translates to:
  /// **'Comments ({count})'**
  String newsCommentsCount(String count);

  /// No description provided for @newsLoginToComment.
  ///
  /// In en, this message translates to:
  /// **'Log in as a member to read and write comments.'**
  String get newsLoginToComment;

  /// No description provided for @newsCommentHint.
  ///
  /// In en, this message translates to:
  /// **'Write a comment...'**
  String get newsCommentHint;

  /// No description provided for @newsNoComments.
  ///
  /// In en, this message translates to:
  /// **'No comments yet.'**
  String get newsNoComments;

  /// No description provided for @newsOptionsTooltip.
  ///
  /// In en, this message translates to:
  /// **'Options'**
  String get newsOptionsTooltip;

  /// No description provided for @newsReportComment.
  ///
  /// In en, this message translates to:
  /// **'Report comment'**
  String get newsReportComment;

  /// No description provided for @newsBlockUser.
  ///
  /// In en, this message translates to:
  /// **'Block user'**
  String get newsBlockUser;

  /// No description provided for @newsByAuthor.
  ///
  /// In en, this message translates to:
  /// **'By {name}'**
  String newsByAuthor(String name);

  /// No description provided for @newsPinned.
  ///
  /// In en, this message translates to:
  /// **'Pinned'**
  String get newsPinned;

  /// No description provided for @libraryNoFavourites.
  ///
  /// In en, this message translates to:
  /// **'No books in favourites yet.'**
  String get libraryNoFavourites;

  /// No description provided for @libraryFilterAll.
  ///
  /// In en, this message translates to:
  /// **'All'**
  String get libraryFilterAll;

  /// No description provided for @libraryFilterFavourites.
  ///
  /// In en, this message translates to:
  /// **'Favourites'**
  String get libraryFilterFavourites;

  /// No description provided for @libraryRead.
  ///
  /// In en, this message translates to:
  /// **'Read'**
  String get libraryRead;

  /// No description provided for @libraryOpenFailed.
  ///
  /// In en, this message translates to:
  /// **'Unable to open the book. Check your internet connection and try again.'**
  String get libraryOpenFailed;

  /// No description provided for @libraryBookFallback.
  ///
  /// In en, this message translates to:
  /// **'Book'**
  String get libraryBookFallback;

  /// No description provided for @libraryRemoveFavourite.
  ///
  /// In en, this message translates to:
  /// **'Remove from favourites'**
  String get libraryRemoveFavourite;

  /// No description provided for @libraryAddFavourite.
  ///
  /// In en, this message translates to:
  /// **'Save to favourites'**
  String get libraryAddFavourite;

  /// No description provided for @libraryPreviousPage.
  ///
  /// In en, this message translates to:
  /// **'Previous page'**
  String get libraryPreviousPage;

  /// No description provided for @libraryNextPage.
  ///
  /// In en, this message translates to:
  /// **'Next page'**
  String get libraryNextPage;

  /// No description provided for @libraryPreparing.
  ///
  /// In en, this message translates to:
  /// **'Preparing book…'**
  String get libraryPreparing;

  /// No description provided for @libraryDownloading.
  ///
  /// In en, this message translates to:
  /// **'Downloading book… {percent}%'**
  String libraryDownloading(String percent);

  /// No description provided for @libraryTryAgain.
  ///
  /// In en, this message translates to:
  /// **'Try Again'**
  String get libraryTryAgain;

  /// No description provided for @manualNoManuals.
  ///
  /// In en, this message translates to:
  /// **'No manuals at the moment.'**
  String get manualNoManuals;

  /// No description provided for @manualIntro.
  ///
  /// In en, this message translates to:
  /// **'The guides below are shown according to your account role.'**
  String get manualIntro;

  /// No description provided for @manualFallback.
  ///
  /// In en, this message translates to:
  /// **'Manual'**
  String get manualFallback;

  /// No description provided for @manualUpdated.
  ///
  /// In en, this message translates to:
  /// **'Updated: {date}'**
  String manualUpdated(String date);

  /// No description provided for @manualOpenFailed.
  ///
  /// In en, this message translates to:
  /// **'Unable to open the manual. Check your internet connection and try again.'**
  String get manualOpenFailed;

  /// No description provided for @manualPreviousPage.
  ///
  /// In en, this message translates to:
  /// **'Previous page'**
  String get manualPreviousPage;

  /// No description provided for @manualNextPage.
  ///
  /// In en, this message translates to:
  /// **'Next page'**
  String get manualNextPage;

  /// No description provided for @manualPreparing.
  ///
  /// In en, this message translates to:
  /// **'Preparing manual…'**
  String get manualPreparing;

  /// No description provided for @manualDownloading.
  ///
  /// In en, this message translates to:
  /// **'Downloading manual… {percent}%'**
  String manualDownloading(String percent);

  /// No description provided for @manualTryAgain.
  ///
  /// In en, this message translates to:
  /// **'Try Again'**
  String get manualTryAgain;

  /// No description provided for @orgInfoTitle.
  ///
  /// In en, this message translates to:
  /// **'Organisation Info'**
  String get orgInfoTitle;

  /// No description provided for @orgNoInfo.
  ///
  /// In en, this message translates to:
  /// **'No organisation information to display.'**
  String get orgNoInfo;

  /// No description provided for @orgSelectTitle.
  ///
  /// In en, this message translates to:
  /// **'Select Organisation'**
  String get orgSelectTitle;

  /// No description provided for @orgAbout.
  ///
  /// In en, this message translates to:
  /// **'About the Organisation'**
  String get orgAbout;

  /// No description provided for @orgContactSocial.
  ///
  /// In en, this message translates to:
  /// **'Contact & Social Media'**
  String get orgContactSocial;

  /// No description provided for @orgChart.
  ///
  /// In en, this message translates to:
  /// **'Organisation Chart'**
  String get orgChart;

  /// No description provided for @orgChartEmpty.
  ///
  /// In en, this message translates to:
  /// **'The organisation chart is not available yet.'**
  String get orgChartEmpty;

  /// No description provided for @orgYourOrganization.
  ///
  /// In en, this message translates to:
  /// **'Your Organisation'**
  String get orgYourOrganization;

  /// No description provided for @orgTotalMembers.
  ///
  /// In en, this message translates to:
  /// **'Total Members'**
  String get orgTotalMembers;

  /// No description provided for @orgLeadership.
  ///
  /// In en, this message translates to:
  /// **'Leadership'**
  String get orgLeadership;

  /// No description provided for @orgMembers.
  ///
  /// In en, this message translates to:
  /// **'Members'**
  String get orgMembers;

  /// No description provided for @orgAgeLimit.
  ///
  /// In en, this message translates to:
  /// **'Age Limit'**
  String get orgAgeLimit;

  /// No description provided for @orgWebsite.
  ///
  /// In en, this message translates to:
  /// **'Website'**
  String get orgWebsite;

  /// No description provided for @orgMainLeadership.
  ///
  /// In en, this message translates to:
  /// **'MAIN LEADERSHIP'**
  String get orgMainLeadership;

  /// No description provided for @orgContact.
  ///
  /// In en, this message translates to:
  /// **'Contact'**
  String get orgContact;

  /// No description provided for @orgCardTapHint.
  ///
  /// In en, this message translates to:
  /// **'Tap the card to view the back.'**
  String get orgCardTapHint;

  /// No description provided for @orgCardViewFront.
  ///
  /// In en, this message translates to:
  /// **'View card front'**
  String get orgCardViewFront;

  /// No description provided for @orgCardViewBack.
  ///
  /// In en, this message translates to:
  /// **'View card back'**
  String get orgCardViewBack;

  /// No description provided for @orgCardQrHint.
  ///
  /// In en, this message translates to:
  /// **'Scan the QR code for membership verification.'**
  String get orgCardQrHint;

  /// No description provided for @orgCardBackMissing.
  ///
  /// In en, this message translates to:
  /// **'The back-of-card design has not been uploaded yet.'**
  String get orgCardBackMissing;

  /// No description provided for @notifMarkAllRead.
  ///
  /// In en, this message translates to:
  /// **'Mark All as Read'**
  String get notifMarkAllRead;

  /// No description provided for @notifEmpty.
  ///
  /// In en, this message translates to:
  /// **'No notifications at the moment.'**
  String get notifEmpty;

  /// No description provided for @notifNoAnnouncements.
  ///
  /// In en, this message translates to:
  /// **'No announcements at the moment.'**
  String get notifNoAnnouncements;

  /// No description provided for @notifFallbackTitle.
  ///
  /// In en, this message translates to:
  /// **'Notification'**
  String get notifFallbackTitle;

  /// No description provided for @notifJustNow.
  ///
  /// In en, this message translates to:
  /// **'Just now'**
  String get notifJustNow;

  /// No description provided for @notifMinutesAgo.
  ///
  /// In en, this message translates to:
  /// **'{count} minutes ago'**
  String notifMinutesAgo(String count);

  /// No description provided for @notifHoursAgo.
  ///
  /// In en, this message translates to:
  /// **'{count} hours ago'**
  String notifHoursAgo(String count);

  /// No description provided for @notifDaysAgo.
  ///
  /// In en, this message translates to:
  /// **'{count} days ago'**
  String notifDaysAgo(String count);

  /// No description provided for @notifTypeEvent.
  ///
  /// In en, this message translates to:
  /// **'Event'**
  String get notifTypeEvent;

  /// No description provided for @notifTypeFee.
  ///
  /// In en, this message translates to:
  /// **'Fee'**
  String get notifTypeFee;

  /// No description provided for @notifTypeForm.
  ///
  /// In en, this message translates to:
  /// **'Form'**
  String get notifTypeForm;

  /// No description provided for @notifTypeFacilityBooking.
  ///
  /// In en, this message translates to:
  /// **'Facility Booking'**
  String get notifTypeFacilityBooking;

  /// No description provided for @notifTypeMembership.
  ///
  /// In en, this message translates to:
  /// **'Membership'**
  String get notifTypeMembership;

  /// No description provided for @notifTypeBroadcast.
  ///
  /// In en, this message translates to:
  /// **'Broadcast'**
  String get notifTypeBroadcast;

  /// No description provided for @notifTypeNotification.
  ///
  /// In en, this message translates to:
  /// **'Notification'**
  String get notifTypeNotification;

  /// No description provided for @notifNoAdditionalContent.
  ///
  /// In en, this message translates to:
  /// **'No additional content for this notification.'**
  String get notifNoAdditionalContent;

  /// No description provided for @notifOpenLink.
  ///
  /// In en, this message translates to:
  /// **'Open Link'**
  String get notifOpenLink;

  /// No description provided for @notifOpenLinkFailed.
  ///
  /// In en, this message translates to:
  /// **'Unable to open the link.'**
  String get notifOpenLinkFailed;

  /// No description provided for @notifUnavailable.
  ///
  /// In en, this message translates to:
  /// **'Unable to open the notification.'**
  String get notifUnavailable;

  /// No description provided for @chatTitle.
  ///
  /// In en, this message translates to:
  /// **'Chat'**
  String get chatTitle;

  /// No description provided for @chatEmpty.
  ///
  /// In en, this message translates to:
  /// **'No messages yet. Send a message to ask the AI assistant.'**
  String get chatEmpty;

  /// No description provided for @chatTyping.
  ///
  /// In en, this message translates to:
  /// **'Typing...'**
  String get chatTyping;

  /// No description provided for @chatInputHint.
  ///
  /// In en, this message translates to:
  /// **'Type a message...'**
  String get chatInputHint;

  /// No description provided for @formRequiredFields.
  ///
  /// In en, this message translates to:
  /// **'Please complete all required fields.'**
  String get formRequiredFields;

  /// No description provided for @formTitle.
  ///
  /// In en, this message translates to:
  /// **'Form'**
  String get formTitle;

  /// No description provided for @formFee.
  ///
  /// In en, this message translates to:
  /// **'Fee: {amount}'**
  String formFee(String amount);

  /// No description provided for @formSubmit.
  ///
  /// In en, this message translates to:
  /// **'Submit Form'**
  String get formSubmit;

  /// No description provided for @formTerms.
  ///
  /// In en, this message translates to:
  /// **'Terms: {terms}'**
  String formTerms(String terms);

  /// No description provided for @formSubmittedTitle.
  ///
  /// In en, this message translates to:
  /// **'Form Submitted'**
  String get formSubmittedTitle;

  /// No description provided for @formSubmittedBody.
  ///
  /// In en, this message translates to:
  /// **'Thank you! Your response has been received.'**
  String get formSubmittedBody;

  /// No description provided for @formOpenInBrowser.
  ///
  /// In en, this message translates to:
  /// **'Please open the link through a web browser.'**
  String get formOpenInBrowser;

  /// No description provided for @formContinueRegistration.
  ///
  /// In en, this message translates to:
  /// **'Continue Registration'**
  String get formContinueRegistration;

  /// No description provided for @formBackHome.
  ///
  /// In en, this message translates to:
  /// **'Back to Home'**
  String get formBackHome;

  /// No description provided for @formSelectDate.
  ///
  /// In en, this message translates to:
  /// **'Select date'**
  String get formSelectDate;

  /// No description provided for @formSelectOption.
  ///
  /// In en, this message translates to:
  /// **'Select an option'**
  String get formSelectOption;

  /// No description provided for @formChooseFile.
  ///
  /// In en, this message translates to:
  /// **'Choose File'**
  String get formChooseFile;

  /// No description provided for @finFeeStatusTitle.
  ///
  /// In en, this message translates to:
  /// **'Fee Status'**
  String get finFeeStatusTitle;

  /// No description provided for @finAnnualFeeAmount.
  ///
  /// In en, this message translates to:
  /// **'Annual Fee Amount'**
  String get finAnnualFeeAmount;

  /// No description provided for @finOutstandingAmount.
  ///
  /// In en, this message translates to:
  /// **'Outstanding Amount'**
  String get finOutstandingAmount;

  /// No description provided for @finPaymentDate.
  ///
  /// In en, this message translates to:
  /// **'Payment Date'**
  String get finPaymentDate;

  /// No description provided for @finFeeDueMessage.
  ///
  /// In en, this message translates to:
  /// **'Please settle your membership fee to keep your membership active.'**
  String get finFeeDueMessage;

  /// No description provided for @finFeePaidMessage.
  ///
  /// In en, this message translates to:
  /// **'Your membership fee is PAID. Thank you for your support.'**
  String get finFeePaidMessage;

  /// No description provided for @finStatusActive.
  ///
  /// In en, this message translates to:
  /// **'ACTIVE'**
  String get finStatusActive;

  /// No description provided for @finStatusUnpaid.
  ///
  /// In en, this message translates to:
  /// **'UNPAID'**
  String get finStatusUnpaid;

  /// No description provided for @finOverviewTitle.
  ///
  /// In en, this message translates to:
  /// **'Fees & Finance'**
  String get finOverviewTitle;

  /// No description provided for @finActiveInfaqCampaigns.
  ///
  /// In en, this message translates to:
  /// **'Active Infaq Campaigns'**
  String get finActiveInfaqCampaigns;

  /// No description provided for @finNoActiveCampaigns.
  ///
  /// In en, this message translates to:
  /// **'No active infaq campaigns at the moment.'**
  String get finNoActiveCampaigns;

  /// No description provided for @finPaymentHistory.
  ///
  /// In en, this message translates to:
  /// **'Payment History'**
  String get finPaymentHistory;

  /// No description provided for @finNoPaymentHistory.
  ///
  /// In en, this message translates to:
  /// **'No payment history yet.'**
  String get finNoPaymentHistory;

  /// No description provided for @finMembershipFee.
  ///
  /// In en, this message translates to:
  /// **'Membership Fee'**
  String get finMembershipFee;

  /// No description provided for @finPayment.
  ///
  /// In en, this message translates to:
  /// **'Payment'**
  String get finPayment;

  /// No description provided for @finPaymentSuccessThanks.
  ///
  /// In en, this message translates to:
  /// **'Fee payment successful. Thank you!'**
  String get finPaymentSuccessThanks;

  /// No description provided for @finPaymentPendingGateway.
  ///
  /// In en, this message translates to:
  /// **'Payment is not verified yet. Please check again after completing it at the gateway.'**
  String get finPaymentPendingGateway;

  /// No description provided for @finFeePaidSuccess.
  ///
  /// In en, this message translates to:
  /// **'Membership fee paid successfully.'**
  String get finFeePaidSuccess;

  /// No description provided for @finPaymentFailed.
  ///
  /// In en, this message translates to:
  /// **'Fee payment could not be processed.'**
  String get finPaymentFailed;

  /// No description provided for @finProcessing.
  ///
  /// In en, this message translates to:
  /// **'Processing...'**
  String get finProcessing;

  /// No description provided for @finPayFeeNow.
  ///
  /// In en, this message translates to:
  /// **'Pay Fee Now'**
  String get finPayFeeNow;

  /// No description provided for @finReceiptUnavailable.
  ///
  /// In en, this message translates to:
  /// **'Receipt is not available.'**
  String get finReceiptUnavailable;

  /// No description provided for @finReceiptOpenFailed.
  ///
  /// In en, this message translates to:
  /// **'Failed to open the receipt. Please try again.'**
  String get finReceiptOpenFailed;

  /// No description provided for @finReceiptDownloadFailed.
  ///
  /// In en, this message translates to:
  /// **'Failed to download the receipt.'**
  String get finReceiptDownloadFailed;

  /// No description provided for @finDownloadReceipt.
  ///
  /// In en, this message translates to:
  /// **'Download Receipt'**
  String get finDownloadReceipt;

  /// No description provided for @usrahMyGroups.
  ///
  /// In en, this message translates to:
  /// **'My Usrah Groups'**
  String get usrahMyGroups;

  /// No description provided for @usrahNoGroups.
  ///
  /// In en, this message translates to:
  /// **'You have not joined any usrah group yet.'**
  String get usrahNoGroups;

  /// No description provided for @usrahAttendanceHistory.
  ///
  /// In en, this message translates to:
  /// **'Attendance History'**
  String get usrahAttendanceHistory;

  /// No description provided for @usrahNoAttendance.
  ///
  /// In en, this message translates to:
  /// **'No attendance records at the moment.'**
  String get usrahNoAttendance;

  /// No description provided for @usrahMembersCount.
  ///
  /// In en, this message translates to:
  /// **'Members ({count})'**
  String usrahMembersCount(String count);

  /// No description provided for @usrahLeader.
  ///
  /// In en, this message translates to:
  /// **'Leader'**
  String get usrahLeader;

  /// No description provided for @usrahPresent.
  ///
  /// In en, this message translates to:
  /// **'Present'**
  String get usrahPresent;

  /// No description provided for @usrahAbsent.
  ///
  /// In en, this message translates to:
  /// **'Absent'**
  String get usrahAbsent;

  /// No description provided for @adminPanelTitle.
  ///
  /// In en, this message translates to:
  /// **'Admin Panel'**
  String get adminPanelTitle;

  /// No description provided for @adminUnexpectedError.
  ///
  /// In en, this message translates to:
  /// **'Unexpected error.'**
  String get adminUnexpectedError;

  /// No description provided for @adminManagementSection.
  ///
  /// In en, this message translates to:
  /// **'Management'**
  String get adminManagementSection;

  /// No description provided for @adminAdvancedManagementSection.
  ///
  /// In en, this message translates to:
  /// **'Advanced Management'**
  String get adminAdvancedManagementSection;

  /// No description provided for @adminSummarySection.
  ///
  /// In en, this message translates to:
  /// **'Summary'**
  String get adminSummarySection;

  /// No description provided for @adminMonthlyRevenueSection.
  ///
  /// In en, this message translates to:
  /// **'Monthly Revenue'**
  String get adminMonthlyRevenueSection;

  /// No description provided for @adminRecentActivitySection.
  ///
  /// In en, this message translates to:
  /// **'Recent Activity'**
  String get adminRecentActivitySection;

  /// No description provided for @adminNoRecentActivity.
  ///
  /// In en, this message translates to:
  /// **'No recent activity.'**
  String get adminNoRecentActivity;

  /// No description provided for @adminMembersTab.
  ///
  /// In en, this message translates to:
  /// **'Members'**
  String get adminMembersTab;

  /// No description provided for @adminFeesTab.
  ///
  /// In en, this message translates to:
  /// **'Fees'**
  String get adminFeesTab;

  /// No description provided for @adminAttendanceTab.
  ///
  /// In en, this message translates to:
  /// **'Attendance'**
  String get adminAttendanceTab;

  /// No description provided for @adminBroadcastTab.
  ///
  /// In en, this message translates to:
  /// **'Broadcast'**
  String get adminBroadcastTab;

  /// No description provided for @adminWebManagePrograms.
  ///
  /// In en, this message translates to:
  /// **'Manage Programs'**
  String get adminWebManagePrograms;

  /// No description provided for @adminWebManageFees.
  ///
  /// In en, this message translates to:
  /// **'Manage Fees'**
  String get adminWebManageFees;

  /// No description provided for @adminWebFinance.
  ///
  /// In en, this message translates to:
  /// **'Finance'**
  String get adminWebFinance;

  /// No description provided for @adminWebTransactions.
  ///
  /// In en, this message translates to:
  /// **'Transactions'**
  String get adminWebTransactions;

  /// No description provided for @adminWebForms.
  ///
  /// In en, this message translates to:
  /// **'Forms'**
  String get adminWebForms;

  /// No description provided for @adminWebPolls.
  ///
  /// In en, this message translates to:
  /// **'Polls'**
  String get adminWebPolls;

  /// No description provided for @adminWebNewsArticles.
  ///
  /// In en, this message translates to:
  /// **'News & Articles'**
  String get adminWebNewsArticles;

  /// No description provided for @adminWebAnnouncements.
  ///
  /// In en, this message translates to:
  /// **'Announcements'**
  String get adminWebAnnouncements;

  /// No description provided for @adminWebFacilities.
  ///
  /// In en, this message translates to:
  /// **'Facilities'**
  String get adminWebFacilities;

  /// No description provided for @adminWebBranches.
  ///
  /// In en, this message translates to:
  /// **'Branches'**
  String get adminWebBranches;

  /// No description provided for @adminWebPositions.
  ///
  /// In en, this message translates to:
  /// **'Positions'**
  String get adminWebPositions;

  /// No description provided for @adminWebExportMembers.
  ///
  /// In en, this message translates to:
  /// **'Export Members'**
  String get adminWebExportMembers;

  /// No description provided for @adminWebDonors.
  ///
  /// In en, this message translates to:
  /// **'Donors'**
  String get adminWebDonors;

  /// No description provided for @adminWebChangeBranch.
  ///
  /// In en, this message translates to:
  /// **'Change Branch'**
  String get adminWebChangeBranch;

  /// No description provided for @adminWebOnlyTitle.
  ///
  /// In en, this message translates to:
  /// **'Web Version Features'**
  String get adminWebOnlyTitle;

  /// No description provided for @adminWebOnlyMessage.
  ///
  /// In en, this message translates to:
  /// **'For more complete features, please log in on the PC/laptop/web version.'**
  String get adminWebOnlyMessage;

  /// No description provided for @adminOkButton.
  ///
  /// In en, this message translates to:
  /// **'OK'**
  String get adminOkButton;

  /// No description provided for @adminStatTotalMembers.
  ///
  /// In en, this message translates to:
  /// **'Total Members'**
  String get adminStatTotalMembers;

  /// No description provided for @adminStatActiveMembers.
  ///
  /// In en, this message translates to:
  /// **'Active Members'**
  String get adminStatActiveMembers;

  /// No description provided for @adminStatRevenue.
  ///
  /// In en, this message translates to:
  /// **'Revenue'**
  String get adminStatRevenue;

  /// No description provided for @adminStatUpcomingEvents.
  ///
  /// In en, this message translates to:
  /// **'Upcoming Events'**
  String get adminStatUpcomingEvents;

  /// No description provided for @adminNoMonthlyRevenueData.
  ///
  /// In en, this message translates to:
  /// **'No monthly revenue data.'**
  String get adminNoMonthlyRevenueData;

  /// No description provided for @adminActivityFallback.
  ///
  /// In en, this message translates to:
  /// **'Activity'**
  String get adminActivityFallback;

  /// No description provided for @adminActivateMemberTitle.
  ///
  /// In en, this message translates to:
  /// **'Reactivate member?'**
  String get adminActivateMemberTitle;

  /// No description provided for @adminDeactivateMemberTitle.
  ///
  /// In en, this message translates to:
  /// **'Deactivate member?'**
  String get adminDeactivateMemberTitle;

  /// No description provided for @adminCancelButton.
  ///
  /// In en, this message translates to:
  /// **'Cancel'**
  String get adminCancelButton;

  /// No description provided for @adminActivateButton.
  ///
  /// In en, this message translates to:
  /// **'Activate'**
  String get adminActivateButton;

  /// No description provided for @adminDeactivateButton.
  ///
  /// In en, this message translates to:
  /// **'Deactivate'**
  String get adminDeactivateButton;

  /// No description provided for @adminMemberReactivated.
  ///
  /// In en, this message translates to:
  /// **'Member reactivated.'**
  String get adminMemberReactivated;

  /// No description provided for @adminMemberDeactivated.
  ///
  /// In en, this message translates to:
  /// **'Member deactivated.'**
  String get adminMemberDeactivated;

  /// No description provided for @adminDeleteMemberTitle.
  ///
  /// In en, this message translates to:
  /// **'Delete member?'**
  String get adminDeleteMemberTitle;

  /// No description provided for @adminDeleteMemberMessage.
  ///
  /// In en, this message translates to:
  /// **'{name} will be moved to Trash. Financial records & history are preserved and can be restored.'**
  String adminDeleteMemberMessage(String name);

  /// No description provided for @adminDeleteButton.
  ///
  /// In en, this message translates to:
  /// **'Delete'**
  String get adminDeleteButton;

  /// No description provided for @adminMemberDeleted.
  ///
  /// In en, this message translates to:
  /// **'Member has been moved to Trash.'**
  String get adminMemberDeleted;

  /// No description provided for @adminMembersTitle.
  ///
  /// In en, this message translates to:
  /// **'Members'**
  String get adminMembersTitle;

  /// No description provided for @adminSearchMembersHint.
  ///
  /// In en, this message translates to:
  /// **'Search name or member no.'**
  String get adminSearchMembersHint;

  /// No description provided for @adminFilterAll.
  ///
  /// In en, this message translates to:
  /// **'All'**
  String get adminFilterAll;

  /// No description provided for @adminFilterActive.
  ///
  /// In en, this message translates to:
  /// **'Active'**
  String get adminFilterActive;

  /// No description provided for @adminFilterIncomplete.
  ///
  /// In en, this message translates to:
  /// **'Incomplete'**
  String get adminFilterIncomplete;

  /// No description provided for @adminNoMembersFound.
  ///
  /// In en, this message translates to:
  /// **'No members found.'**
  String get adminNoMembersFound;

  /// No description provided for @adminNoName.
  ///
  /// In en, this message translates to:
  /// **'No Name'**
  String get adminNoName;

  /// No description provided for @adminMemberNoPrefix.
  ///
  /// In en, this message translates to:
  /// **'No. {number}'**
  String adminMemberNoPrefix(String number);

  /// No description provided for @adminFieldMemberNo.
  ///
  /// In en, this message translates to:
  /// **'Member No.'**
  String get adminFieldMemberNo;

  /// No description provided for @adminFieldEmail.
  ///
  /// In en, this message translates to:
  /// **'Email'**
  String get adminFieldEmail;

  /// No description provided for @adminFieldPhone.
  ///
  /// In en, this message translates to:
  /// **'Phone'**
  String get adminFieldPhone;

  /// No description provided for @adminFieldIcNo.
  ///
  /// In en, this message translates to:
  /// **'IC No.'**
  String get adminFieldIcNo;

  /// No description provided for @adminFieldBranch.
  ///
  /// In en, this message translates to:
  /// **'Branch'**
  String get adminFieldBranch;

  /// No description provided for @adminFieldOrganization.
  ///
  /// In en, this message translates to:
  /// **'Organisation'**
  String get adminFieldOrganization;

  /// No description provided for @adminFieldStatus.
  ///
  /// In en, this message translates to:
  /// **'Status'**
  String get adminFieldStatus;

  /// No description provided for @adminFieldAccount.
  ///
  /// In en, this message translates to:
  /// **'Account'**
  String get adminFieldAccount;

  /// No description provided for @adminFieldRegistered.
  ///
  /// In en, this message translates to:
  /// **'Registered'**
  String get adminFieldRegistered;

  /// No description provided for @adminFieldProfileCompleted.
  ///
  /// In en, this message translates to:
  /// **'Profile Completed'**
  String get adminFieldProfileCompleted;

  /// No description provided for @adminAccountActivated.
  ///
  /// In en, this message translates to:
  /// **'Activated'**
  String get adminAccountActivated;

  /// No description provided for @adminAccountDeactivated.
  ///
  /// In en, this message translates to:
  /// **'Deactivated'**
  String get adminAccountDeactivated;

  /// No description provided for @adminDeactivateMemberButton.
  ///
  /// In en, this message translates to:
  /// **'Deactivate Member'**
  String get adminDeactivateMemberButton;

  /// No description provided for @adminReactivateMemberButton.
  ///
  /// In en, this message translates to:
  /// **'Reactivate'**
  String get adminReactivateMemberButton;

  /// No description provided for @adminDeleteMemberButton.
  ///
  /// In en, this message translates to:
  /// **'Delete Member'**
  String get adminDeleteMemberButton;

  /// No description provided for @adminFeesTitle.
  ///
  /// In en, this message translates to:
  /// **'Fees'**
  String get adminFeesTitle;

  /// No description provided for @adminFeePaid.
  ///
  /// In en, this message translates to:
  /// **'Paid'**
  String get adminFeePaid;

  /// No description provided for @adminFeeUnpaid.
  ///
  /// In en, this message translates to:
  /// **'Unpaid'**
  String get adminFeeUnpaid;

  /// No description provided for @adminNoFeeRecords.
  ///
  /// In en, this message translates to:
  /// **'No fee records.'**
  String get adminNoFeeRecords;

  /// No description provided for @adminFeeYear.
  ///
  /// In en, this message translates to:
  /// **'Fee {year}'**
  String adminFeeYear(String year);

  /// No description provided for @adminAttendanceTitle.
  ///
  /// In en, this message translates to:
  /// **'Attendance'**
  String get adminAttendanceTitle;

  /// No description provided for @adminNoUpcomingEventsToManage.
  ///
  /// In en, this message translates to:
  /// **'No upcoming events to manage.'**
  String get adminNoUpcomingEventsToManage;

  /// No description provided for @adminAttendanceRecorded.
  ///
  /// In en, this message translates to:
  /// **'Attendance recorded.'**
  String get adminAttendanceRecorded;

  /// No description provided for @adminEventAttendanceTitle.
  ///
  /// In en, this message translates to:
  /// **'Event Attendance'**
  String get adminEventAttendanceTitle;

  /// No description provided for @adminProgramFallback.
  ///
  /// In en, this message translates to:
  /// **'Program'**
  String get adminProgramFallback;

  /// No description provided for @adminRegistrationsLabel.
  ///
  /// In en, this message translates to:
  /// **'Registrations'**
  String get adminRegistrationsLabel;

  /// No description provided for @adminAttendedLabel.
  ///
  /// In en, this message translates to:
  /// **'Attended'**
  String get adminAttendedLabel;

  /// No description provided for @adminNoRegistrations.
  ///
  /// In en, this message translates to:
  /// **'No registrations for this event.'**
  String get adminNoRegistrations;

  /// No description provided for @adminScanQr.
  ///
  /// In en, this message translates to:
  /// **'Scan QR'**
  String get adminScanQr;

  /// No description provided for @adminAttendedAt.
  ///
  /// In en, this message translates to:
  /// **'Attended {datetime}'**
  String adminAttendedAt(String datetime);

  /// No description provided for @adminNotYetLabel.
  ///
  /// In en, this message translates to:
  /// **'Not yet'**
  String get adminNotYetLabel;

  /// No description provided for @adminAttendanceVerifiedTitle.
  ///
  /// In en, this message translates to:
  /// **'Attendance Verified'**
  String get adminAttendanceVerifiedTitle;

  /// No description provided for @adminAttendanceScanSuccess.
  ///
  /// In en, this message translates to:
  /// **'{name} ({memberNo}) has been recorded as present.'**
  String adminAttendanceScanSuccess(String name, String memberNo);

  /// No description provided for @adminScanNotRecognized.
  ///
  /// In en, this message translates to:
  /// **'Scan not recognized.'**
  String get adminScanNotRecognized;

  /// No description provided for @adminScanFailedTitle.
  ///
  /// In en, this message translates to:
  /// **'Scan Failed'**
  String get adminScanFailedTitle;

  /// No description provided for @adminManualScanTitle.
  ///
  /// In en, this message translates to:
  /// **'Manual Scan'**
  String get adminManualScanTitle;

  /// No description provided for @adminMemberNoOrIdLabel.
  ///
  /// In en, this message translates to:
  /// **'Member no. / ID'**
  String get adminMemberNoOrIdLabel;

  /// No description provided for @adminScanButton.
  ///
  /// In en, this message translates to:
  /// **'Scan'**
  String get adminScanButton;

  /// No description provided for @adminPointCameraToMemberQr.
  ///
  /// In en, this message translates to:
  /// **'Point the camera at the member\'s QR code.'**
  String get adminPointCameraToMemberQr;

  /// No description provided for @adminEnterManuallyButton.
  ///
  /// In en, this message translates to:
  /// **'Enter Manually'**
  String get adminEnterManuallyButton;

  /// No description provided for @adminCameraPermissionNeeded.
  ///
  /// In en, this message translates to:
  /// **'Camera permission is required to scan QR codes.'**
  String get adminCameraPermissionNeeded;

  /// No description provided for @adminCameraUnavailable.
  ///
  /// In en, this message translates to:
  /// **'Camera cannot be accessed.'**
  String get adminCameraUnavailable;

  /// No description provided for @adminMemberFallback.
  ///
  /// In en, this message translates to:
  /// **'Member'**
  String get adminMemberFallback;

  /// No description provided for @adminBroadcastSuccess.
  ///
  /// In en, this message translates to:
  /// **'Broadcast sent successfully.'**
  String get adminBroadcastSuccess;

  /// No description provided for @adminBroadcastTitle.
  ///
  /// In en, this message translates to:
  /// **'Broadcast'**
  String get adminBroadcastTitle;

  /// No description provided for @adminBroadcastTitleLabel.
  ///
  /// In en, this message translates to:
  /// **'Title'**
  String get adminBroadcastTitleLabel;

  /// No description provided for @adminBroadcastTitleHint.
  ///
  /// In en, this message translates to:
  /// **'Example: Annual General Meeting'**
  String get adminBroadcastTitleHint;

  /// No description provided for @adminBroadcastTitleRequired.
  ///
  /// In en, this message translates to:
  /// **'Please enter a title.'**
  String get adminBroadcastTitleRequired;

  /// No description provided for @adminBroadcastMessageLabel.
  ///
  /// In en, this message translates to:
  /// **'Message'**
  String get adminBroadcastMessageLabel;

  /// No description provided for @adminBroadcastMessageHint.
  ///
  /// In en, this message translates to:
  /// **'Write the broadcast message here...'**
  String get adminBroadcastMessageHint;

  /// No description provided for @adminBroadcastMessageRequired.
  ///
  /// In en, this message translates to:
  /// **'Please enter a message.'**
  String get adminBroadcastMessageRequired;

  /// No description provided for @adminBroadcastAudienceLabel.
  ///
  /// In en, this message translates to:
  /// **'Audience'**
  String get adminBroadcastAudienceLabel;

  /// No description provided for @adminAudienceAll.
  ///
  /// In en, this message translates to:
  /// **'All'**
  String get adminAudienceAll;

  /// No description provided for @adminAudienceMembers.
  ///
  /// In en, this message translates to:
  /// **'Members'**
  String get adminAudienceMembers;

  /// No description provided for @adminAudienceUsrah.
  ///
  /// In en, this message translates to:
  /// **'Usrah'**
  String get adminAudienceUsrah;

  /// No description provided for @adminAudienceOrganization.
  ///
  /// In en, this message translates to:
  /// **'Organisation'**
  String get adminAudienceOrganization;

  /// No description provided for @adminOrganizationIdOptional.
  ///
  /// In en, this message translates to:
  /// **'Organisation ID (optional)'**
  String get adminOrganizationIdOptional;

  /// No description provided for @adminSending.
  ///
  /// In en, this message translates to:
  /// **'Sending...'**
  String get adminSending;

  /// No description provided for @adminSendBroadcast.
  ///
  /// In en, this message translates to:
  /// **'Send Broadcast'**
  String get adminSendBroadcast;

  /// No description provided for @scanInvalidQr.
  ///
  /// In en, this message translates to:
  /// **'This QR code is not a myWAP program attendance code.'**
  String get scanInvalidQr;

  /// No description provided for @scanCheckInSuccess.
  ///
  /// In en, this message translates to:
  /// **'Your attendance for \"{eventTitle}\" has been recorded.'**
  String scanCheckInSuccess(String eventTitle);

  /// No description provided for @scanThisProgram.
  ///
  /// In en, this message translates to:
  /// **'this program'**
  String get scanThisProgram;

  /// No description provided for @scanUnexpectedErrorRetry.
  ///
  /// In en, this message translates to:
  /// **'Unexpected error. Please try again.'**
  String get scanUnexpectedErrorRetry;

  /// No description provided for @scanLoginRequiredTitle.
  ///
  /// In en, this message translates to:
  /// **'Login Required'**
  String get scanLoginRequiredTitle;

  /// No description provided for @scanLoginRequiredBody.
  ///
  /// In en, this message translates to:
  /// **'Program attendance code detected. Please log in or register as a member to record your attendance.'**
  String get scanLoginRequiredBody;

  /// No description provided for @scanCancelButton.
  ///
  /// In en, this message translates to:
  /// **'Cancel'**
  String get scanCancelButton;

  /// No description provided for @scanLoginButton.
  ///
  /// In en, this message translates to:
  /// **'Log In'**
  String get scanLoginButton;

  /// No description provided for @scanAttendanceVerifiedTitle.
  ///
  /// In en, this message translates to:
  /// **'Attendance Verified'**
  String get scanAttendanceVerifiedTitle;

  /// No description provided for @scanFailedTitle.
  ///
  /// In en, this message translates to:
  /// **'Scan Failed'**
  String get scanFailedTitle;

  /// No description provided for @scanDoneButton.
  ///
  /// In en, this message translates to:
  /// **'Done'**
  String get scanDoneButton;

  /// No description provided for @scanTryAgainButton.
  ///
  /// In en, this message translates to:
  /// **'Try Again'**
  String get scanTryAgainButton;

  /// No description provided for @scanQrTitle.
  ///
  /// In en, this message translates to:
  /// **'Scan Attendance QR'**
  String get scanQrTitle;

  /// No description provided for @scanPointCameraHint.
  ///
  /// In en, this message translates to:
  /// **'Point the camera at the QR code on the program poster/screen to check in.'**
  String get scanPointCameraHint;

  /// No description provided for @scanCloseButton.
  ///
  /// In en, this message translates to:
  /// **'Close'**
  String get scanCloseButton;

  /// No description provided for @scanProgramAttendanceTitle.
  ///
  /// In en, this message translates to:
  /// **'Program Attendance'**
  String get scanProgramAttendanceTitle;

  /// No description provided for @eventRsvpLoginPrompt.
  ///
  /// In en, this message translates to:
  /// **'Log in as a member to RSVP for this program.'**
  String get eventRsvpLoginPrompt;

  /// No description provided for @eventRsvpUpdated.
  ///
  /// In en, this message translates to:
  /// **'RSVP updated successfully.'**
  String get eventRsvpUpdated;

  /// No description provided for @eventRegisterLoginPrompt.
  ///
  /// In en, this message translates to:
  /// **'Log in as a member to register for this program.'**
  String get eventRegisterLoginPrompt;

  /// No description provided for @eventRegisterProgram.
  ///
  /// In en, this message translates to:
  /// **'Register for Program'**
  String get eventRegisterProgram;

  /// No description provided for @eventFormFallback.
  ///
  /// In en, this message translates to:
  /// **'Form'**
  String get eventFormFallback;

  /// No description provided for @eventFee.
  ///
  /// In en, this message translates to:
  /// **'Fee: {amount}'**
  String eventFee(String amount);

  /// No description provided for @eventDetailsTitle.
  ///
  /// In en, this message translates to:
  /// **'Program Details'**
  String get eventDetailsTitle;

  /// No description provided for @eventTitleFallback.
  ///
  /// In en, this message translates to:
  /// **'Programs & Events'**
  String get eventTitleFallback;

  /// No description provided for @eventParticipants.
  ///
  /// In en, this message translates to:
  /// **'{count} participants'**
  String eventParticipants(int count);

  /// No description provided for @eventDescription.
  ///
  /// In en, this message translates to:
  /// **'Description'**
  String get eventDescription;

  /// No description provided for @eventRegisterAsParticipant.
  ///
  /// In en, this message translates to:
  /// **'Register as Participant'**
  String get eventRegisterAsParticipant;

  /// No description provided for @eventRegisterHint.
  ///
  /// In en, this message translates to:
  /// **'Get a registration number and a place (a fee applies if paid).'**
  String get eventRegisterHint;

  /// No description provided for @eventAlreadyRegistered.
  ///
  /// In en, this message translates to:
  /// **'You are already registered'**
  String get eventAlreadyRegistered;

  /// No description provided for @eventRegistrationNo.
  ///
  /// In en, this message translates to:
  /// **'Registration No.: {number}'**
  String eventRegistrationNo(String number);

  /// No description provided for @eventStatus.
  ///
  /// In en, this message translates to:
  /// **'Status: {status}'**
  String eventStatus(String status);

  /// No description provided for @eventViewMyRegistrations.
  ///
  /// In en, this message translates to:
  /// **'View My Registrations'**
  String get eventViewMyRegistrations;

  /// No description provided for @eventCancelAttendance.
  ///
  /// In en, this message translates to:
  /// **'Cancel Attendance'**
  String get eventCancelAttendance;

  /// No description provided for @eventWillAttend.
  ///
  /// In en, this message translates to:
  /// **'I Will Attend'**
  String get eventWillAttend;

  /// No description provided for @eventRsvpHint.
  ///
  /// In en, this message translates to:
  /// **'Attendance only — no registration form required.'**
  String get eventRsvpHint;

  /// No description provided for @eventsTitle.
  ///
  /// In en, this message translates to:
  /// **'Programs & Events'**
  String get eventsTitle;

  /// No description provided for @eventsMyRegistrationsTooltip.
  ///
  /// In en, this message translates to:
  /// **'My Registrations'**
  String get eventsMyRegistrationsTooltip;

  /// No description provided for @eventsSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Discover and join upcoming programs.'**
  String get eventsSubtitle;

  /// No description provided for @eventPhysicalTitle.
  ///
  /// In en, this message translates to:
  /// **'Physical'**
  String get eventPhysicalTitle;

  /// No description provided for @eventOnlineTitle.
  ///
  /// In en, this message translates to:
  /// **'Online'**
  String get eventOnlineTitle;

  /// No description provided for @eventsEmpty.
  ///
  /// In en, this message translates to:
  /// **'No programs scheduled'**
  String get eventsEmpty;

  /// No description provided for @eventsEmptyBody.
  ///
  /// In en, this message translates to:
  /// **'New programs will appear here once scheduled.'**
  String get eventsEmptyBody;

  /// No description provided for @myRegistrationsTitle.
  ///
  /// In en, this message translates to:
  /// **'My Registrations'**
  String get myRegistrationsTitle;

  /// No description provided for @noRegistrations.
  ///
  /// In en, this message translates to:
  /// **'You have not registered for any event.'**
  String get noRegistrations;

  /// No description provided for @registrationPaid.
  ///
  /// In en, this message translates to:
  /// **'Payment Complete'**
  String get registrationPaid;

  /// No description provided for @registrationUnpaid.
  ///
  /// In en, this message translates to:
  /// **'Payment Pending'**
  String get registrationUnpaid;

  /// No description provided for @attended.
  ///
  /// In en, this message translates to:
  /// **'Attended'**
  String get attended;

  /// No description provided for @eventCompleteRequired.
  ///
  /// In en, this message translates to:
  /// **'Please complete all required fields.'**
  String get eventCompleteRequired;

  /// No description provided for @eventUploadSupportDoc.
  ///
  /// In en, this message translates to:
  /// **'Please upload the supporting document.'**
  String get eventUploadSupportDoc;

  /// No description provided for @eventRegistrationSuccess.
  ///
  /// In en, this message translates to:
  /// **'Registration successful.'**
  String get eventRegistrationSuccess;

  /// No description provided for @eventRegistrationPaymentPending.
  ///
  /// In en, this message translates to:
  /// **'Registration submitted. Please complete payment.'**
  String get eventRegistrationPaymentPending;

  /// No description provided for @eventFormNotFound.
  ///
  /// In en, this message translates to:
  /// **'Form not found.'**
  String get eventFormNotFound;

  /// No description provided for @eventFallbackShort.
  ///
  /// In en, this message translates to:
  /// **'Program'**
  String get eventFallbackShort;

  /// No description provided for @eventPaymentMethod.
  ///
  /// In en, this message translates to:
  /// **'Payment Method'**
  String get eventPaymentMethod;

  /// No description provided for @eventFpx.
  ///
  /// In en, this message translates to:
  /// **'FPX (Bank Transfer)'**
  String get eventFpx;

  /// No description provided for @eventDuitNow.
  ///
  /// In en, this message translates to:
  /// **'DuitNow QR'**
  String get eventDuitNow;

  /// No description provided for @submitting.
  ///
  /// In en, this message translates to:
  /// **'Submitting...'**
  String get submitting;

  /// No description provided for @eventSubmitRegistration.
  ///
  /// In en, this message translates to:
  /// **'Submit Registration'**
  String get eventSubmitRegistration;

  /// No description provided for @eventTerms.
  ///
  /// In en, this message translates to:
  /// **'Terms: {terms}'**
  String eventTerms(String terms);

  /// No description provided for @eventFeeCategory.
  ///
  /// In en, this message translates to:
  /// **'Fee Category'**
  String get eventFeeCategory;

  /// No description provided for @eventUploadSupportDocTitle.
  ///
  /// In en, this message translates to:
  /// **'Upload Supporting Document *'**
  String get eventUploadSupportDocTitle;

  /// No description provided for @eventUploadSupportDocHint.
  ///
  /// In en, this message translates to:
  /// **'Please upload the supporting document (e.g. student card).'**
  String get eventUploadSupportDocHint;

  /// No description provided for @eventChooseFile.
  ///
  /// In en, this message translates to:
  /// **'Choose File'**
  String get eventChooseFile;

  /// No description provided for @eventAlreadyRegisteredTitle.
  ///
  /// In en, this message translates to:
  /// **'You Are Already Registered'**
  String get eventAlreadyRegisteredTitle;

  /// No description provided for @eventBackToProgram.
  ///
  /// In en, this message translates to:
  /// **'Back to Program'**
  String get eventBackToProgram;

  /// No description provided for @eventRegistrationReceived.
  ///
  /// In en, this message translates to:
  /// **'Your registration has been received.'**
  String get eventRegistrationReceived;

  /// No description provided for @eventRegistrationSuccessfulTitle.
  ///
  /// In en, this message translates to:
  /// **'Registration Successful'**
  String get eventRegistrationSuccessfulTitle;

  /// No description provided for @paymentAlreadyPaid.
  ///
  /// In en, this message translates to:
  /// **'I Have Paid'**
  String get paymentAlreadyPaid;

  /// No description provided for @pageNotFoundTitle.
  ///
  /// In en, this message translates to:
  /// **'Page Not Found'**
  String get pageNotFoundTitle;

  /// No description provided for @pageNotFoundBody.
  ///
  /// In en, this message translates to:
  /// **'This page was not found'**
  String get pageNotFoundBody;

  /// No description provided for @pageNotFoundHint.
  ///
  /// In en, this message translates to:
  /// **'Please try again, or return to the home page.'**
  String get pageNotFoundHint;

  /// No description provided for @goToHome.
  ///
  /// In en, this message translates to:
  /// **'Go to Home'**
  String get goToHome;

  /// No description provided for @paymentTitle.
  ///
  /// In en, this message translates to:
  /// **'Payment'**
  String get paymentTitle;

  /// No description provided for @comingSoon.
  ///
  /// In en, this message translates to:
  /// **'This feature will be available soon.'**
  String get comingSoon;

  /// No description provided for @errorTimeout.
  ///
  /// In en, this message translates to:
  /// **'Connection timed out. Please try again.'**
  String get errorTimeout;

  /// No description provided for @errorOffline.
  ///
  /// In en, this message translates to:
  /// **'No internet connection.'**
  String get errorOffline;

  /// No description provided for @errorSessionExpired.
  ///
  /// In en, this message translates to:
  /// **'Session expired, please log in again.'**
  String get errorSessionExpired;

  /// No description provided for @errorForbidden.
  ///
  /// In en, this message translates to:
  /// **'You do not have permission to access this.'**
  String get errorForbidden;

  /// No description provided for @errorNotFound.
  ///
  /// In en, this message translates to:
  /// **'Not found.'**
  String get errorNotFound;

  /// No description provided for @errorValidation.
  ///
  /// In en, this message translates to:
  /// **'Please check your information.'**
  String get errorValidation;

  /// No description provided for @errorTooMany.
  ///
  /// In en, this message translates to:
  /// **'Too many attempts. Please try again later.'**
  String get errorTooMany;

  /// No description provided for @errorUnknown.
  ///
  /// In en, this message translates to:
  /// **'Unexpected error. Please try again.'**
  String get errorUnknown;

  /// No description provided for @newNotification.
  ///
  /// In en, this message translates to:
  /// **'New notification'**
  String get newNotification;

  /// No description provided for @chatNoAnswer.
  ///
  /// In en, this message translates to:
  /// **'No answer.'**
  String get chatNoAnswer;

  /// No description provided for @chatNotConfigured.
  ///
  /// In en, this message translates to:
  /// **'The AI chatbot is not configured yet. Please contact the admin.'**
  String get chatNotConfigured;

  /// No description provided for @biometricLoginReason.
  ///
  /// In en, this message translates to:
  /// **'Verify your identity to log in'**
  String get biometricLoginReason;

  /// No description provided for @biometricEnableReason.
  ///
  /// In en, this message translates to:
  /// **'Verify Face ID / fingerprint to enable quick login'**
  String get biometricEnableReason;

  /// No description provided for @biometricLoginMyWapReason.
  ///
  /// In en, this message translates to:
  /// **'Verify Face ID / fingerprint to log in to myWAP'**
  String get biometricLoginMyWapReason;

  /// No description provided for @pushChannelName.
  ///
  /// In en, this message translates to:
  /// **'myWAP Notifications'**
  String get pushChannelName;

  /// No description provided for @pushChannelDescription.
  ///
  /// In en, this message translates to:
  /// **'Push notifications from myWAP'**
  String get pushChannelDescription;

  /// No description provided for @ecomSoldOut.
  ///
  /// In en, this message translates to:
  /// **'Sold Out'**
  String get ecomSoldOut;

  /// No description provided for @ecomChooseOptions.
  ///
  /// In en, this message translates to:
  /// **'Choose Options'**
  String get ecomChooseOptions;

  /// No description provided for @ecomStockLeft.
  ///
  /// In en, this message translates to:
  /// **'{count} left'**
  String ecomStockLeft(int count);

  /// No description provided for @ecomStockAvailable.
  ///
  /// In en, this message translates to:
  /// **'{count} available'**
  String ecomStockAvailable(int count);

  /// No description provided for @ecomStockRemaining.
  ///
  /// In en, this message translates to:
  /// **'{count} left'**
  String ecomStockRemaining(int count);

  /// No description provided for @ecomOutOfStockBody.
  ///
  /// In en, this message translates to:
  /// **'This product is out of stock.'**
  String get ecomOutOfStockBody;

  /// No description provided for @ecomMaxStockReached.
  ///
  /// In en, this message translates to:
  /// **'Maximum stock reached'**
  String get ecomMaxStockReached;

  /// No description provided for @ecomOptionSoldOut.
  ///
  /// In en, this message translates to:
  /// **'Sold out'**
  String get ecomOptionSoldOut;
}

class _AppLocalizationsDelegate extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  Future<AppLocalizations> load(Locale locale) {
    return SynchronousFuture<AppLocalizations>(lookupAppLocalizations(locale));
  }

  @override
  bool isSupported(Locale locale) => <String>['en', 'ms'].contains(locale.languageCode);

  @override
  bool shouldReload(_AppLocalizationsDelegate old) => false;
}

AppLocalizations lookupAppLocalizations(Locale locale) {


  // Lookup logic when only language code is specified.
  switch (locale.languageCode) {
    case 'en': return AppLocalizationsEn();
    case 'ms': return AppLocalizationsMs();
  }

  throw FlutterError(
    'AppLocalizations.delegate failed to load unsupported locale "$locale". This is likely '
    'an issue with the localizations generation tool. Please file an issue '
    'on GitHub with a reproducible sample app and the gen-l10n configuration '
    'that was used.'
  );
}
