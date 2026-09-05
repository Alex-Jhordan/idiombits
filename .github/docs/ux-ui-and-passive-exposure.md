# UX/UI Design & Passive Exposure Channels

## 1. UX Design Philosophy: Zero Friction (Invisible Design)

The UX design of **IdiomBits** aims to eliminate cognitive overload. Unlike traditional language learning platforms that force users through complex menus or long lesson modules, IdiomBits operates as an immediate, utility-first micro-learning tool:

- **2-Tap Rule:** Capturing a phrase or word requires no more than 2 taps from the moment the app is launched.
- **Operational Minimalism:** The main interface centers strictly on the Active Window (the 5 `in_progress` phrases), immediate access to the daily quiz, and a single floating capture trigger (FAB).
- **Lightweight Access Management:** Clean authentication screens optimized for rapid signup/login issuing a persistent Laravel Sanctum Bearer token. Session state is securely cached locally (`FlutterSecureStorage` + **Isar Database**) to bypass credential prompts on mobile client launches.
- **Zero Configuration Barriers:** No mandatory pre-categorization fields. Context tagging is 100% optional.

---

## 2. Multimodal Input Strategy (Capture)

The app offers three multimodal capture channels backed by real-time validation to uphold the micro-learning premise:

- **Direct Text Input:**
  - Clean text field with automatic language detection (`es` / `en`).
  - Real-time character length validation: enforces a 150-character limit (preventing long paragraph pastes) and displays a micro-note if a single word is entered (*"AI will convert this word into a high-frequency lexical chunk or phrase"*).
  - Optional contextual tag input field (e.g., `#business`, `#restaurant`).
- **Voice Input (Speech-to-Text):**
  - Press-and-hold microphone button records spoken audio.
  - Releasing the button automatically processes and transcribes audio on-device or streams it to the API queue, preparing it for AI enrichment.
  - Optimized for hands-free, on-the-go capture.
- **Image Input (OCR / Computer Vision):**
  - Supports direct camera capture or gallery screenshot import.
  - Interactive cropping frame allows framing the exact phrase segment to extract via Optical Character Recognition, ignoring irrelevant surrounding text.

---

## 3. Screen Architecture & App Distribution

- **Screen 1 — Authentication (Auth):** User Login and Registration forms.
- **Screen 2 — Main Dashboard:** Houses the Active Window displaying the 5 in-progress phrases.
- **Screen 3 — Multimodal Capture:** Slide-up BottomSheet for text, voice, and image capture with client-side **ULID** generation.
- **Screen 4 — Daily Evaluation (Quiz):** Interactive micro-session containing the 3 assigned daily questions.
- **Screen 5 — Settings & Preferences:** Profile management, active hour range (`active_hours_start` to `active_hours_end`), and preferred quiz time (`quiz_preferred_time`) setup.

### Screen 1: Authentication & Access Module

- **Visual Layout:** Minimalist layout centered on the IdiomBits identity, featuring an active tab slider between "Login" and "Register".
- **Form Fields:** Name (registration only), Email, Password, and initial schedule configuration.
- **Offline Session Persistence:** If previously authenticated, the app validates Bearer token persistence in local secure storage (`FlutterSecureStorage`) and routes directly to the Dashboard without prompting for credentials, even without active network connectivity.
- **Error Handling:** Clear inline validation messages (e.g., *"Invalid credentials"*, *"Email already registered"*).

### Screen 2: Main Dashboard (Active Window)

- **Top Header:** Daily Quiz status indicator showing real-time availability or a countdown timer to `quiz_preferred_time`.
- **Central Area (5 Active Slots):**
  - Compact cards representing the 5 `in_progress` phrases.
  - Each card displays: original text, idiomatic equivalence (`idiomatic_translation`), progress streak (`[ ○ ○ ]` / `[ ● ○ ]`), TTS audio trigger buttons via `flutter_tts` for both languages, and an expandable preview showing its 2 verb tense variations (`time_variations`) and complementary phrase (`complementary_phrase`).
- **Bottom Section:** Counter for queued phrases (`queued`) and an accessible entry point to graduated phrases (`learned`).
- **Floating Trigger:** Prominent Floating Action Button (FAB) anchored to the bottom-right corner.

### Screen 3: Multimodal Capture Sheet

- **Layout:** Animated slide-up BottomSheet preserving Dashboard background context, triggered via the FAB.
- **Mode Selector:** Top tab bar to toggle between Text, Voice, and Camera/Gallery modes.
- **Input Area:** Dynamic text field with a character counter, microphone hold button, or image cropper based on the selected mode.
- **Primary Action:** *"Capture & Idiomatize"* button providing instant local save feedback with a generated client **ULID** to Isar Database for offline-first operation before REST API background dispatch.

### Screen 4: Micro-Evaluation Session (Daily Quiz)

- **Header:** 15-second countdown progress bar per question and a session progress counter (*Question X of 3*).
- **Central Interaction Card:** Dynamically displays one of three randomized formats:
  - **Cloze Test:** Select the missing target word in the idiomatic equivalence.
  - **Sentence Scramble:** Reorder word blocks within the time limit.
  - **Self-Assessment:** Reveal translation with honest self-grading buttons.
- **Support Actions:** Native TTS audio button (`flutter_tts`) to hear phrase target-language pronunciation.
- **Footer:** Immediate visual feedback (correct/incorrect) upon answer submission or timeout.

### Screen 5: User Settings & Configuration

- **User Profile:** Displays registered name, email, and a Logout action (revokes Sanctum Bearer token and wipes local credentials).
- **Active Hours Configuration:** Time pickers for `active_hours_start` and `active_hours_end` (restricting widget update and notification dispatch windows).
- **Quiz Scheduling:** Time picker for `quiz_preferred_time` (enforcing validation within configured active hours).
- **Data Management & Sync:** Local Isar storage status monitoring and background synchronization indicators.

---

## 4. Passive & Incidental Exposure Channels

### A. Dynamic Widgets (Home Screen & Lock Screen)

#### Home Screen Widget

- **Implementation:** Powered by the `home_widget` package reading directly from local Isar Database.
- **Supported Sizes:** Medium and Large.
- **Card Flip Interaction:** Tapping the widget card triggers a 3D flip animation, toggling between the original text and its AI-generated idiomatic translation.
- **Integrated Controls:**
  - **Audio Icon (TTS):** Plays native target-language audio directly from the widget without opening the main app.
  - **Card Navigation Controls:** Discrete arrow buttons to cycle through the 5 `in_progress` phrases loaded from local storage.

#### Lock Screen Widget

- **Supported Sizes:** Inline/Icon compact text unit and small interactive widget.
- **Behavior:** Displays the active phrase for quick incidental glances every time the device screen is woken.

### B. Ambient Silent Notifications

- **Non-Intrusive Delivery:** Dispatched via low-priority system channels. Appears ambiently in the status bar without sound or vibration.
- **Bounded Random Banners:** Bounded random dispatch algorithm operating within the user's active window (`active_hours_start` to `active_hours_end`), enforcing a minimum cooldown interval (`MIN_NOTIFICATION_INTERVAL` = 120 min) to prevent notification fatigue and a maximum interval (`MAX_NOTIFICATION_INTERVAL` = 240 min) to ensure consistent review flow.
- **Direct Notification Actions (Without Launching App):**
  - **"Listen":** Triggers background TTS playback of the target phrase.
  - **"Reveal Equivalence":** Expands the native banner card to display the idiomatic translation, verb tense variations, and complementary phrase directly within the notification shade.

---

## 5. Micro-Verification Session Design (Quizzes)

- **Fixed Volume:** Exactly 3 questions sourced from active phrases (`in_progress`) or audit candidates (`learned` > 14 days).
- **Strict Time Control:** 15-second limit per question with a decreasing visual time bar.
- **Estimated Duration:** Under 45 seconds per complete session (`DailyQuizSession`).
- **Availability Window:** Opens at `quiz_preferred_time` and closes at `active_hours_end`. Uncompleted sessions expire silently (`status = expired`) without resetting streaks.
- **Immediate Feedback:** Clear UI micro-animations reflecting results and instantly updating streak indicators (`[ ○ ○ ]` → `[ ● ○ ]`).