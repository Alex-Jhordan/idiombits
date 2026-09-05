# Architecture, Data Flow, and AI Integration

## 1. Defined Technology Stack

The system adopts a decoupled, API-First architecture to ensure scalability, efficient asynchronous processing, and a native mobile user experience.

### Mobile Frontend

- **Framework:** Flutter 3.x (Dart) for Android and iOS.
- **Package Manager:** pub (`pubspec.yaml`).
- **State Management & Local Persistence:** `flutter_bloc` for reactive state management and Isar Database for offline-first local storage.
- **Widget Integration:** `home_widget` package for updating native home and lock screen widgets.
- **Background Tasks:** `workmanager` for background synchronization and retry strategies.

### Backend API

- **Framework:** Laravel 13.x (PHP 8.3).
- **Package Managers:** Composer for PHP/Laravel dependencies; pnpm for JS scripts/assets.
- **Local Development Environment:** Laragon.
- **Authentication:** Laravel Sanctum (issuing lightweight Bearer tokens upon credential validation).
- **Queues & Asynchrony:** Redis (local service) with `predis/predis` and native Laravel Worker (`php artisan queue:work`) as the queue runner for background AI processing and execution of the task scheduler (`php artisan schedule:run`) responsible for the cadence of environmental notifications.
- **Relational Database:** PostgreSQL with native JSONB support (for AI enriched payloads) and pgvector support readiness.
- **AI Engine:** Google Gemini API (`gemini-2.5-flash`), integrated via HTTP client inside asynchronous Jobs with Structured Outputs (`response_mime_type: "application/json"` and strict JSON Schema).

---

## 2. Component Architecture and Data Flow

System Integration & Data Flow Step-by-Step:

1. **Capture:** The user initiates authentication or submits an entry (Text / Voice / Image) from the Flutter Mobile App to the Laravel 13 API via Sanctum authentication.
2. **Immediate Response:** The API persists the base record in PostgreSQL using a client-side or server-generated ULID and returns a Bearer token or immediate confirmation to the user with the initial `captured` status.
3. **Queueing:** The API asynchronously dispatches a processing Job (`ProcessPhraseWithGemini`) to the Redis Queue Worker managed by the native Laravel Worker.
4. **AI Processing:** The Redis Worker sends a request to the Google Gemini API (`gemini-2.5-flash`) with a strict system prompt and `response_mime_type: "application/json"`.
5. **Persistence:** Gemini returns the structured translation and enrichment payload. The Worker updates the record in PostgreSQL, storing this data inside the `payload_data` JSONB column of the `phrase_payloads` table.
6. **Silent Sync:** The system notifies the local Isar database on the mobile device via a Silent Push Notification or background fetch to update the offline cache without user interruption.
7. **Passive Notification Dispatch and Pacing:** The task scheduler (`DispatchAmbientNotificationsJob`) periodically evaluates users with active phrases. It verifies that the current time falls within the user's active hours window (`active_hours_start` to `active_hours_end`) and dispatches silent notifications adhering to random intervals bounded by `MIN_NOTIFICATION_INTERVAL` (120 min) and `MAX_NOTIFICATION_INTERVAL` (240 min).

---

## 3. AI Processing Pipeline (Google Gemini)

Phrase processing runs asynchronously via a dedicated Laravel Job named `ProcessPhraseWithGemini`.

### Prompt Strategy & Structured Output

The Gemini API request is configured using strict JSON output mode (`response_mime_type="application/json"` with a strict JSON Schema).

**System Prompt:**

> "You are an expert in linguistics and second language acquisition for the IdiomBits application. Your task is to receive a phrase, expression, or word in Spanish or English and return its natural idiomatic equivalent in the opposite language (do not translate literally; use real-world collocations). If the input is a single word, DO NOT generate a literal dictionary translation; identify and autonomously transform it into a lexical chunk or high-frequency phrase (e.g., if you receive 'get', generate 'get along with'). Generate exactly two variations in different verb tenses from the original and one complementary phrase that naturally continues the conversation."

**Expected JSON Schema Response:**

- `source_language` (string): ISO language code of the input text (e.g., `es` or `en`).
- `original_text` (string): Original captured word or phrase.
- `idiomatic_translation` (string): Natural idiomatic translation in the target language.
- `time_variations` (array of objects): Mandatory array containing exactly 2 items. Each object contains:
  - `tense` (string): Verb tense used (`past`, `present`, `future`, `conditional`).
  - `sentence` (string): Rephrased text in that tense.
  - `translation` (string): Equivalent idiomatic translation in the target language.
- `complementary_phrase` (object): Contextual follow-up sentence to reinforce learning. Contains:
  - `phrase` (string): Complementary phrase in the source language.
  - `translation` (string): Equivalent idiomatic translation in the target language.

### Resilience and Error Handling

- **Timeouts:** Maximum 10-second timeout per HTTP request to Gemini.
- **Retry Strategy:** If the Gemini API fails or times out, Laravel Worker retries with Exponential Backoff (`$tries = 3`, `$backoff = [5, 15, 30]`).
- **Failure Handling:** If 3 retries fail, the `failed()` method in the Job logs the error and ensures the phrase remains in a safe recoverable state.

---

## 4. Offline-First Synchronization Strategy (Flutter)

To ensure near-zero friction, the mobile application does not depend on immediate internet connectivity for capture or authenticated local sessions:

1. **Immediate Local Storage:** When a user captures a phrase via text, voice, or image, Flutter saves the item instantly to the Isar local database with state `captured`, `isSynced = false`, and a locally generated ULID (using the `ulid` package) to support conflict-free background synchronization.
2. **Background Sync (`workmanager`):**
   - If online, the item posts directly to the Laravel 13 API using the Sanctum Bearer token via `/api/v1/phrases/store` or `/api/v1/phrases/sync`.
   - If offline, `workmanager` schedules a background sync task to post pending entries as soon as network connectivity is restored.
3. **Widget Refresh:** Widgets pull data directly from Isar local storage. When Laravel finishes processing with Gemini and dispatches a silent push notification, the app updates the local Isar record and triggers a seamless widget refresh via `HomeWidget`.

---

## 5. Main API Endpoints Specification (REST)

| Category | Method | Endpoint | Description |
| :--- | :--- | :--- | :--- |
| Public | POST | `/api/v1/register` | Traditional registration (name, email, password, active hours). Returns Bearer token. |
| Public | POST | `/api/v1/login` | Email/password login. Returns Bearer token and user profile. |
| Sanctum | POST | `/api/v1/logout` | Revokes active access token. |
| Sanctum | POST | `/api/v1/phrases/store` | Receives plain text, audio, or image with client ULID. Dispatches `ProcessPhraseWithGemini`. |
| Sanctum | GET | `/api/v1/phrases/active` | Returns the 5 items loaded in the Active Window (`in_progress`) for widget sync. |
| Sanctum | POST | `/api/v1/phrases/sync` | Delta synchronization endpoint between local Isar storage and PostgreSQL. |
| Sanctum | GET | `/api/v1/quizzes/daily` | Returns the set of 3 questions for the daily quiz (`in_progress` and eligible audit items). |
| Sanctum | POST | `/api/v1/quizzes/submit` | Submits answers for 3 questions to update counters and transition states. |

---

## 6. Environment Configuration and Global System Constants

To ensure a learning pace free from cognitive fatigue, the backend manages notification timing by combining system configuration constants (`.env` / `config`) with user preference settings:

**Global Application Constants (`.env` / `config/app.php`):**
- **MIN_NOTIFICATION_INTERVAL:** Minimum mandatory cooldown period between consecutive notifications for the same user (`120` minutes), preventing saturation.
- **MAX_NOTIFICATION_INTERVAL:** Maximum allowed interval without sending notifications during active hours if phrases are pending review (`240` minutes), ensuring pedagogical continuity.

**User-Configurable Parameters (Persisted in PostgreSQL `users` table):**
- **active_hours_start:** Start time of the daily learning window (default `08:00`).
- **active_hours_end:** End time of the daily learning window (default `22:00`).
- **quiz_preferred_time:** Preferred daily time for the verification quiz (default `20:00`).

**Notification Scheduler Logic:**
A scheduled job (`DispatchAmbientNotificationsJob`) registered in `routes/console.php` (`everyFifteenMinutes()`) performs a periodic evaluation. For each active user:

1. Checks if the current local time falls within the range [`active_hours_start`, `active_hours_end`].
2. Calculates whether the time elapsed since the last notification is `>= MIN_NOTIFICATION_INTERVAL`.
3. Applies a bounded random algorithm to select the sending time within the range [`MIN_NOTIFICATION_INTERVAL`, `MAX_NOTIFICATION_INTERVAL`].
4. Sends a silent PUSH notification to the device to refresh the status bar and the Isar/Widget store.