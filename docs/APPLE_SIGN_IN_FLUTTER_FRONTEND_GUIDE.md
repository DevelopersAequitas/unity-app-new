# Peers Global Unity — Sign in with Apple Frontend Integration Guide (`unity_app`)

**Target Mobile Codebase:** `D:\office project\peers-unity\unity_app` (Flutter)  
**Target Backend Codebase:** `unity-app-new` (Laravel 12 API)  
**Prod API Base URL:** `https://peersunity.com/api/v1`  
**Dev API Base URL:** `https://dev.peersunity.com/api/v1`

---

## 1. Backend APIs & Configuration Status

The Laravel backend is live with the following Sign in with Apple routes ([`routes/api.php`](../routes/api.php)):

| Method | Endpoint | Purpose |
| :--- | :--- | :--- |
| `POST` | `/api/v1/auth/apple/login` | Primary login/signup endpoint for Flutter (`identity_token` + optional first-login profile fields). Aliases: `/api/v1/auth/social/apple`, `/api/v1/auth/apple/verify` (plus non-`/v1` `/api/auth/apple/*`). |
| `GET \| POST` | `/api/v1/auth/apple/callback` | Apple `form_post` Return URL for **Android** & **Web** Chrome Custom Tab redirect (`intent://callback?...#Intent;package=com.peers.peersunity(.dev);scheme=signinwithapple;end`). Also supports JSON mode when `Accept: application/json` + `id_token` is sent. |

### Server Prerequisites (One-Time Setup)
1. **Manual PostgreSQL SQL** (if `users.apple_id` has not been created yet):
   ```sql
   BEGIN;

   ALTER TABLE users
       ADD COLUMN IF NOT EXISTS apple_id VARCHAR(255) NULL;

   CREATE INDEX IF NOT EXISTS idx_users_apple_id
       ON users (apple_id)
       WHERE apple_id IS NOT NULL;

   COMMIT;
   ```
2. **Backend `.env` Values:**
   ```env
   APPLE_CLIENT_ID=<YOUR_APPLE_SERVICES_ID>
   APPLE_BUNDLE_ID=com.brapps.pgu
   APPLE_DEV_BUNDLE_ID=com.brapps.pgu.dev
   APPLE_REDIRECT_URI=https://peersunity.com/api/v1/auth/apple/callback
   APPLE_ANDROID_PACKAGE=com.peers.peersunity
   APPLE_ANDROID_DEV_PACKAGE=com.peers.peersunity.dev
   ```
3. **Apple Developer Console (Services ID):**
   - **Domains and Subdomains:** `peersunity.com, dev.peersunity.com, unity.peersglobal.com`
   - **Return URLs:** `https://peersunity.com/api/v1/auth/apple/callback, https://dev.peersunity.com/api/v1/auth/apple/callback`

---

## 2. Backend API Contract (`POST /api/v1/auth/apple/login`)

### Request Payload

```json
{
  "identity_token": "<apple_oidc_identity_jwt>",
  "authorization_code": "<apple_authorization_code>",
  "nonce": "<raw_or_sha256_nonce>",
  "first_name": "Aarav",
  "last_name": "Patel",
  "name": "Aarav Patel",
  "email": "aarav@example.com",
  "device_token": "<fcm_device_token>",
  "platform": "ios"
}
```

| Field | Type | Required | Notes |
| :--- | :--- | :--- | :--- |
| `identity_token` | `string` | **Yes** | Apple JWT (`id_token` and `token` are also accepted by the backend as aliases). |
| `authorization_code` | `string` | Optional | Forwarded from `AuthorizationCredentialAppleID.authorizationCode`. |
| `nonce` | `string\|null` | Conditional | Pass the **raw** nonce string generated before calling `SignInWithApple.getAppleIDCredential()`. Backend verifies both raw and SHA-256-hashed forms against JWT `nonce`. |
| `first_name`, `last_name`, `name` | `string` | Optional | Apple sends name **only on the user's very first sign-in** with your app. Send whenever non-empty; backend also accepts Apple's raw `user` JSON object. |
| `email` | `string` | Optional | Real email or `@privaterelay.appleid.com`; omitted by Apple on repeat logins (backend still resolves existing users via verified `sub` / `users.apple_id`). |
| `device_token` | `string` | Optional | FCM push token (for `UserPushToken`). |
| `platform` | `string` | Optional | `"ios"` or `"android"`. |

### Success Response (`200 OK`)

Returned for both existing users and newly created social users:

```json
{
  "success": true,
  "status": true,
  "message": "Authenticated successfully",
  "data": {
    "token": "<laravel_sanctum_bearer_token>",
    "is_new_user": true,
    "is_registered": true,
    "has_phone": false,
    "provider": "apple",
    "provider_user_id": "001234.abcdef...",
    "apple_id": "001234.abcdef...",
    "user": {
      "id": "9d8a1f20-...",
      "first_name": "Aarav",
      "last_name": "Patel",
      "name": "Aarav Patel",
      "display_name": "Aarav Patel",
      "email": "aarav@example.com",
      "phone": null,
      "avatar": "",
      "avatar_url": null,
      "life_impacted_count": 0,
      "coins_balance": 0,
      "dob": null,
      "location": null,
      "address": null
    }
  }
}
```

### Error Responses
- **`401 Unauthorized`** (invalid/expired JWT signature, wrong `iss`, wrong `aud`, expired `exp`, or mismatched `nonce`):
  ```json
  {
    "success": false,
    "status": false,
    "message": "Invalid or expired Apple token.",
    "error_code": "INVALID_APPLE_TOKEN"
  }
  ```
- **`422 Unprocessable Entity`** (missing `identity_token` or malformed payload).

### Routing Rules in Flutter Based on `data.user.phone`
Matches the existing Google & Facebook flow in `SocialAuthHelper`:
1. **`data.user.phone` is non-empty (`has_phone == true`):** Save `data.token` + `data.user` and navigate directly to **Home** (`AppRoutes.home`).
2. **`data.user.phone` is `null` or `""` (`has_phone == false`):** Save `data.token` and navigate to `SocialRegistrationScreen` with `SocialAuthUser(provider: 'apple', ...)` so the user completes Birthday (`dob`), WhatsApp Phone Verification (`phone`), Company Details, and Category / Referral via `POST /api/v1/user/profile`.

---

## 3. Native Platform Configuration (`unity_app`)

### Step 3.1: `pubspec.yaml`
Add `sign_in_with_apple` and `crypto`:

```yaml
dependencies:
  sign_in_with_apple: ^6.1.4
  crypto: ^3.0.6
```

Run `flutter pub get`.

### Step 3.2: iOS (`ios/Runner/Runner.entitlements` + Xcode)
Add `com.apple.developer.applesignin` to `ios/Runner/Runner.entitlements`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
	<key>aps-environment</key>
	<string>production</string>
	<key>com.apple.developer.associated-domains</key>
	<array>
		<string>applinks:peersunity.com</string>
		<string>applinks:dev.peersunity.com</string>
	</array>
	<key>com.apple.developer.applesignin</key>
	<array>
		<string>Default</string>
	</array>
</dict>
</plist>
```

Also ensure the **Sign in with Apple** capability is enabled in Apple Developer Console & Xcode Signing & Capabilities for **both** Bundle IDs:
- Prod: `com.brapps.pgu`
- Dev: `com.brapps.pgu.dev`

*(If using separate entitlements files per Xcode flavor/target, include `com.apple.developer.applesignin` in both.)*

### Step 3.3: Android (`android/app/src/main/AndroidManifest.xml`)
Inside `<application>`, register `SignInWithAppleCallback` so Chrome Custom Tabs returns the callback intent to the app:

```xml
<!-- Sign in with Apple Chrome Custom Tab Callback (Prod & Dev) -->
<activity
    android:name="com.aboutyou.dart_packages.sign_in_with_apple.SignInWithAppleCallback"
    android:exported="true">
    <intent-filter>
        <action android:name="android.intent.action.VIEW" />
        <category android:name="android.intent.category.DEFAULT" />
        <category android:name="android.intent.category.BROWSABLE" />
        <data android:scheme="signinwithapple" />
        <data android:path="/callback" />
    </intent-filter>
</activity>
```

> **Note:** On Android, the Laravel callback at `/api/v1/auth/apple/callback` automatically resolves `com.peers.peersunity` for prod (`peersunity.com`) and `com.peers.peersunity.dev` for dev (`dev.peersunity.com`) when constructing `intent://callback?...#Intent;package=...;scheme=signinwithapple;end`.

---

## 4. Flutter Code Implementation

### Step 4.1: `lib/core/constants/api_endpoints.dart`
Add the Apple auth constants and dynamic callback URL getter:

```dart
  static const String appleLogin = '/auth/apple/login';
  static const String appleVerify = '/auth/apple/verify';

  static String get appleCallbackUrl {
    final cleanBase = baseUrl.replaceAll(RegExp(r'/+$'), '');
    return '$cleanBase/auth/apple/callback';
  }
```

### Step 4.2: `lib/features/auth/data/datasources/auth_remote_datasource.dart`
1. Add to abstract class `AuthRemoteDataSource`:
```dart
  Future<AuthResponseModel> loginWithApple({
    required String identityToken,
    String? authorizationCode,
    String? nonce,
    String? email,
    String? name,
    String? firstName,
    String? lastName,
    String? deviceToken,
    String? platform,
  });
```
2. Implement inside `AuthRemoteDataSourceImpl`:
```dart
  @override
  Future<AuthResponseModel> loginWithApple({
    required String identityToken,
    String? authorizationCode,
    String? nonce,
    String? email,
    String? name,
    String? firstName,
    String? lastName,
    String? deviceToken,
    String? platform,
  }) async {
    try {
      final token =
          deviceToken ??
          await PushNotificationService.instance.getDeviceToken();
      final response = await _dio.post(
        ApiEndpoints.appleLogin,
        data: {
          'identity_token': identityToken,
          if (authorizationCode != null && authorizationCode.isNotEmpty)
            'authorization_code': authorizationCode,
          if (nonce != null && nonce.isNotEmpty) 'nonce': nonce,
          if (email != null && email.isNotEmpty) 'email': email,
          if (name != null && name.isNotEmpty) 'name': name,
          if (firstName != null && firstName.isNotEmpty)
            'first_name': firstName,
          if (lastName != null && lastName.isNotEmpty) 'last_name': lastName,
          if (token != null && token.isNotEmpty) 'device_token': token,
          'platform': platform ?? (Platform.isIOS ? 'ios' : 'android'),
        },
      );
      if (response.statusCode != null &&
          response.statusCode! >= 200 &&
          response.statusCode! < 300 &&
          response.data is Map<String, dynamic>) {
        return AuthResponseModel.fromJson(
          response.data as Map<String, dynamic>,
        );
      }
      throw Exception('Apple sign-in failed');
    } on DioException catch (e) {
      if (e.response?.data is Map<String, dynamic>) {
        final resData = e.response!.data as Map<String, dynamic>;
        final msg = resData['message'] as String?;
        if (msg != null && msg.isNotEmpty) throw Exception(msg);
      }
      rethrow;
    }
  }
```

### Step 4.3: `lib/features/auth/presentation/utils/social_auth_helper.dart`
Add imports (`dart:convert`, `dart:io`, `package:crypto/crypto.dart`, `package:sign_in_with_apple/sign_in_with_apple.dart`, `../../../../core/constants/api_endpoints.dart`) and implement `signInWithApple`:

```dart
  static const String _appleServicesId = '<YOUR_APPLE_SERVICES_ID>'; // Must match APPLE_CLIENT_ID in backend .env

  static String _sha256ofString(String input) {
    final bytes = utf8.encode(input);
    return sha256.convert(bytes).toString();
  }

  static Future<SocialAuthResult> signInWithApple(BuildContext context) async {
    try {
      final rawNonce = generateNonce();
      final hashedNonce = _sha256ofString(rawNonce);

      final credential = await SignInWithApple.getAppleIDCredential(
        scopes: [
          AppleIDAuthorizationScopes.email,
          AppleIDAuthorizationScopes.fullName,
        ],
        nonce: hashedNonce,
        webAuthenticationOptions: Platform.isIOS || Platform.isMacOS
            ? null
            : WebAuthenticationOptions(
                clientId: _appleServicesId,
                redirectUri: Uri.parse(ApiEndpoints.appleCallbackUrl),
              ),
      );

      final identityToken = credential.identityToken?.trim() ?? '';
      if (identityToken.isEmpty) {
        return SocialAuthResult.failure(
          'Apple Sign-In did not return an identity token. Please try again.',
        );
      }

      final firstName = (credential.givenName ?? '').trim();
      final lastName = (credential.familyName ?? '').trim();
      final fullName = '$firstName $lastName'.trim();
      final email = (credential.email ?? '').trim();
      final appleUserId = (credential.userIdentifier ?? '').trim();

      final remoteDataSource =
          AuthRemoteDataSourceImpl(dioClient: DioClient());

      final authResponse = await remoteDataSource.loginWithApple(
        identityToken: identityToken,
        authorizationCode: credential.authorizationCode,
        nonce: rawNonce,
        email: email.isNotEmpty ? email : null,
        name: fullName.isNotEmpty ? fullName : null,
        firstName: firstName.isNotEmpty ? firstName : null,
        lastName: lastName.isNotEmpty ? lastName : null,
      );

      if (authResponse.user != null &&
          authResponse.token != null &&
          authResponse.token!.isNotEmpty) {
        final returnedUser = authResponse.user!;
        final hasPhone =
            returnedUser.phone != null && returnedUser.phone!.isNotEmpty;

        if (hasPhone) {
          return SocialAuthResult.successExisting(
            user: returnedUser,
            token: authResponse.token!,
          );
        }

        final suWithToken = SocialAuthUser(
          provider: 'apple',
          providerUserId:
              returnedUser.id.isNotEmpty ? returnedUser.id : appleUserId,
          email: returnedUser.email.isNotEmpty ? returnedUser.email : email,
          firstName: (returnedUser.firstName ?? '').isNotEmpty
              ? returnedUser.firstName!
              : firstName,
          lastName: (returnedUser.lastName ?? '').isNotEmpty
              ? returnedUser.lastName!
              : lastName,
          token: authResponse.token,
        );

        return SocialAuthResult(
          isSuccess: true,
          isExistingUser: false,
          user: returnedUser,
          sessionToken: authResponse.token,
          socialUser: suWithToken,
        );
      }

      return SocialAuthResult.failure('Apple sign-in failed. Please try again.');
    } on SignInWithAppleAuthorizationException catch (e) {
      if (e.code == AuthorizationErrorCode.canceled) {
        return SocialAuthResult.cancelled();
      }
      debugPrint('[SocialAuthHelper] Apple auth error (${e.code}): ${e.message}');
      return SocialAuthResult.failure(
        'Apple sign-in failed. Please try again.',
      );
    } catch (e) {
      debugPrint('[SocialAuthHelper] Apple sign-in exception: $e');
      return SocialAuthResult.failure(
        'Unable to sign in with Apple right now. Please try again.',
      );
    }
  }
```

### Step 4.4: Login & Registration UI Button Guidelines
- Apple App Store Review **Guideline 4.8** requires **Sign in with Apple** to be prominently available on iOS wherever Google or Facebook login is shown.
- Use `SignInWithAppleButton` (or Apple Human Interface Guidelines-compliant black/white styling matching sibling Google/Facebook button height & width).
- Wire tap handler identically to `_onGoogleSignIn` / `_onFacebookSignIn`:
  - `result.isCancelled` -> do nothing.
  - `result.isExistingUser && result.user != null && result.sessionToken != null` -> persist auth cache & navigate to Home.
  - `!result.isExistingUser && result.socialUser != null` -> navigate to `SocialRegistrationScreen(socialUser: result.socialUser!)`.
  - Otherwise -> show `AppSnackBar.showError(context, result.errorMessage ?? 'Apple Sign-In failed')`.

---

## 5. Edge Cases & QA Verification Checklist

1. **First Sign-In vs Repeat Sign-In:**
   - Apple sends `givenName`, `familyName`, and `email` **only once** (the very first time the user authorizes the app).
   - On subsequent sign-ins, those fields are `null` in Flutter, and the backend automatically matches the existing user by verified JWT `sub` (`users.apple_id` / `social_accounts`) and returns full profile details (`first_name`, `last_name`, `email`, `phone`).
2. **Re-testing First-Time Sign-In on iOS:**
   - On the iPhone/Simulator: **Settings → [Your Name] → Sign-In & Security → Sign in with Apple → Peers Global Unity → Stop Using Apple ID**. The next tap triggers a fresh authorization prompt with name & email again.
3. **Hide My Email (`@privaterelay.appleid.com`):**
   - Fully supported by the backend — treated as a verified email for account creation/lookup. Do not reject `@privaterelay.appleid.com` in Flutter client validation.
4. **Android Flavor Testing (`dev` vs `prod`):**
   - `dev` debug/dev builds hit `https://dev.peersunity.com/api/v1/auth/apple/callback`, which redirects to `com.peers.peersunity.dev`.
   - `prod` release builds hit `https://peersunity.com/api/v1/auth/apple/callback`, which redirects to `com.peers.peersunity`.
   - Ensure `_appleServicesId` matches `APPLE_CLIENT_ID` on the server `.env` and uses the **Services ID** (not the iOS Bundle ID).
5. **Social Onboarding Phone Verification:**
   - When a brand-new Apple user has `phone == null`, `SocialRegistrationScreen` saves `dob`, verified `phone` (`+ mobile` + `phone_verification_token`), `company_name`, `website`, categories, and `referral_code` to `POST /api/v1/user/profile`.
