# Software Requirements Specification (SRS)

## 1. Functional Requirements (FR)

### Module 1: Authentication, User Management, and Access Control

- **`FR-1.1` (Traditional User Registration):** The system must allow new users to register using a name, a unique email address, a secure password, and initial daily schedule parameters (`active_hours_start`, `active_hours_end`, `quiz_preferred_time`).
- **`FR-1.2` (Authentication and Token Issuance):** The system must validate user credentials and issue an individual Bearer access token via Laravel Sanctum to authenticate REST API requests.
- **`FR-1.3` (Logout and Token Revocation):** The system must allow the user to explicitly revoke their active access token (Logout), clearing the local session and security keys on the mobile device.
- **`FR-1.4` (Client-Side Session Persistence):** The mobile application must securely store the issued Bearer token using `FlutterSecureStorage` and sync user metadata in local **Isar Database**, allowing direct access to the Dashboard without prompting for credentials on subsequent launches.

### Module 2: Multimodal Capture and Ingestion (Offline-First)

- **`FR-2.1` (Direct Text Capture):** The system must allow manual entry of text or single words in Spanish or English within a maximum of 2 taps from the main application screen, generating a local **ULID** immediately.
- **`FR-2.2` (Voice Capture - Speech-to-Text):** The system must enable audio recording via press-and-hold interaction, convert the input to text locally or process the audio payload asynchronously, and prepare it for AI enrichment.
- **`FR-2.3` (Image Capture - OCR):** The system must allow camera capture or gallery import with an interactive cropping frame, isolating text segments to process via Optical Character Recognition before submission.
- **`FR-2.4` (Single-Word Automatic Treatment):** If captured text consists of a single isolated word, the system must instruct the AI prompt to autonomously transform it into a high-frequency lexical chunk or idiomatic phrase instead of generating a literal dictionary translation.
- **`FR-2.5` (Length Validation and Paraphrasing):** The system must reject entries exceeding 150 characters at both UI and API levels, notifying the user that learning units must remain concise phrases or expressions.
- **`FR-2.6` (Optional Tagging):** The system must allow attaching a single optional context tag during capture for thematic categorization.

### Module 3: Asynchronous AI Enrichment & State Engine

- **`FR-3.1` (Structured Payload Generation):** The system must process captured items via an asynchronous Laravel 13 Job (`ProcessPhraseWithGemini`) connected to Redis (`queue:work`) and Google Gemini API (`gemini-2.5-flash`), strictly retrieving:
  - Equivalent idiomatic translation or cultural counterpart (`idiomatic_translation`).
  - Two explicit sentence variations in verb tenses differing from the original text (`time_variations` array with `tense`, `sentence`, and `translation`).
  - One complementary phrase to naturally extend a conversation (`complementary_phrase` object with `phrase` and `translation`).
- **`FR-3.2` (Deduplication):** The system must verify if normalized text already exists for the user. If found, it will bypass redundant Gemini API calls and re-link or return the existing entity.
- **`FR-3.3` (Initial State Assignment):** Any phrase successfully processed by AI must transition to `in_progress` status if the Active Window has an open slot (fewer than 5 phrases). If the Active Window is full, the phrase must transition to `queued` status following strict FIFO ordering.

### Module 4: Passive Environmental Exposure, Widgets, and Notifications

- **`FR-4.1` (Active Window Capacity Enforcement):** The system must strictly restrict the number of simultaneous phrases in `in_progress` status to exactly 5 per user (or fewer if total user inventory is under 5).
- **`FR-4.2` (Native Widget Synchronization):** The system must update local Isar database records and trigger refreshes via the `home_widget` package for Home Screen and Lock Screen widgets on Android and iOS whenever `in_progress` phrases update.
- **`FR-4.3` (Home Screen Widget Interactivity):** The Home Screen widget must support:
  - Card flipping interaction to toggle between original text and idiomatic translation.
  - Native Text-to-Speech (`flutter_tts`) audio playback in both languages without launching the main app.
  - Cycling through the 5 active phrases via navigation controls.
- **`FR-4.4` (Ambient Silent Notifications):** The system must dispatch silent notifications (no sound, no vibration) bounded within user active hours (`active_hours_start` to `active_hours_end`). Dispatch times must respect a minimum cooldown of `MIN_NOTIFICATION_INTERVAL` (120 min) and a maximum interval of `MAX_NOTIFICATION_INTERVAL` (240 min).
- **`FR-4.5` (Notification Quick Actions):** Status bar notifications must expose immediate actions to play native audio or expand the card to reveal the translation, tense variations, and complementary phrase without breaking user workflow.

### Module 5: Active Verification, Evaluation, and Auditing (Quizzes)

- **`FR-5.1` (Micro-Session Structure):** Daily verification sessions (`DailyQuizSession`) must consist of exactly 3 questions with a strict 15-second time limit per question.
- **`FR-5.2` (Randomized Interaction Modes):** Each quiz question must dynamically select an interaction format among cloze test (fill-in-the-blank), sentence scramble (block ordering), or self-assessment (reveal and self-grade).
- **`FR-5.3` (Quiz Availability and Expiration):** Quizzes become available at `quiz_preferred_time`. If not completed before `active_hours_end`, the session status transitions to `expired` without penalizing streaks or modifying phrase counters.
- **`FR-5.4` (Graduation Criterion to Learned):** A phrase in `in_progress` status requires accumulating exactly 2 consecutive correct quiz answers (`success_streak = 2`) across separate sessions to be promoted to `learned` status, immediately opening a slot in the Active Window.
- **`FR-5.5` (14-Day Periodic Audit):** Any phrase in `learned` status reaching exactly 14 days since graduation (`learned_at <= NOW() - 14 days`) automatically becomes eligible for selection as question #3 in the Daily Quiz.
- **`FR-5.6` (Success, Failure, and Re-Learning Handling):**
  - If a phrase passes an audit question, it retains `learned` status and resets its timer (`learned_at = NOW()`).
  - If a user fails or times out (15s) on an `in_progress` phrase, its streak resets to 0 (`success_streak = 0`).
  - If a user fails an audit question on a `learned` phrase, it immediately transitions to `re_learning` status with Priority 0 in the Active Window queue.
- **`FR-5.7` (Quiz Session Atomicity):** Exiting or closing the application midway through a daily quiz session discards partial progress and leaves database phrase counters unchanged.

---

## 2. Non-Functional Requirements (NFR)

- **`NFR-1` (Local Capture Latency):** Local storage persistence in Isar Database during phrase capture must execute in under 100 ms on mobile devices, providing instant UI feedback before remote REST API synchronization.
- **`NFR-2` (Offline-First Availability):** The Flutter mobile app must support token-based offline authentication, local phrase capture with client-side ULID generation, and offline widget rendering without requiring active internet connectivity.
- **`NFR-3` (Energy and System Efficiency):** Background task execution via `workmanager` for widget updates and silent notifications must not consume more than 2% of daily device battery life.
- **`NFR-4` (Security and Authentication):** REST API endpoints in Laravel 13 must enforce HTTPS/TLS 1.3 protocol and validate individual Bearer tokens issued via Laravel Sanctum.
- **`NFR-5` (AI Resilience and Reliability):** Requests to Gemini API inside the native Laravel Queue Worker (`queue:work` with `predis`) must implement a 10-second HTTP timeout and up to 3 retries with Exponential Backoff (`$backoff = [5, 15, 30]`).
- **`NFR-6` (Data Transactional Integrity):** State transitions (`PhraseStatus`), FIFO queue reallocation, Sanctum token management, and quiz result logging must execute within PostgreSQL database transactions to prevent state inconsistencies.