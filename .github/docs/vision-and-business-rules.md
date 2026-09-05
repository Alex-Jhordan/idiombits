# Product Vision, Pedagogical Approach, and Business Rules

## 1. Project Overview

IdiomBits solves the core issues of low retention and high friction in traditional language learning methods (such as static vocabulary lists, rigid theoretical courses, and generic gamified apps).

The system is a **ubiquitous contextual micro-learning platform** designed to capture a user's real and immediate language needs during their daily routine. It automatically processes entries using Large Language Models (LLMs - Google Gemini 2.5 Flash) to retrieve real idiomatic equivalents and passively re-exposes them via home/lock screen widgets and hyper-short quizzes without interrupting daily workflows. All user knowledge and progress are securely stored in a personal account using traditional authentication (email and password), utilizing an offline-first architecture powered by **Flutter 3.x** on the mobile client and a **Laravel** REST API with **PostgreSQL** and **Redis** on the backend.

## 2. Pedagogical Framework & Core Pillars

IdiomBits is grounded in three key principles of second language acquisition (SLA) and cognitive psychology:

- **Need-Based Learning (Real Need Driven):** Captures only expressions, phrases, or words spontaneously used or encountered by the user. This ensures direct personal relevance, activating the cognitive *notice-the-gap* mechanism.
- **Lexical Approach (Chunks & Idiomatic Equivalents):** Rejects literal word-for-word translation. IdiomBits processes complete units of meaning (lexical chunks or collocations), complete with tense variations and complementary phrases for operational fluency. Single-word entries (e.g., "get") are autonomously converted by AI into meaningful collocations (e.g., "get along with") to maintain pedagogical value without blocking entry.
- **Incidental Exposure, Spacing Effect, and Interleaved Practice:** Long-term retention relies on passive visual micro-doses delivered via background widgets throughout the day (leveraging subconscious processing and contextual priming), complemented by brief active verification quizzes using randomized mechanics to enforce long-term memory retrieval.

## 3. Key Use Cases

**Use Case 1: Account Management & Authentication**

- **Traditional Registration:** User creates an account with name, email, secure password, and active hours preferences (`active_hours_start`, `active_hours_end`, `quiz_preferred_time`).
- **Authentication & Sync:** User logs in with email and password. The system issues a secure access token (via Laravel Sanctum) managed locally via `FlutterSecureStorage` to synchronize phrases, quiz states, and user configurations across devices.

**Use Case 2: Real-Time Bidirectional Capture (Offline-First)**

- The user enters a phrase or single word via text, voice, or image in any language (paragraph or long text inputs are blocked at the UI level to enforce micro-learning).
- The Flutter 3.x client generates a local **ULID** immediately, persisting the entry in **Isar Database** with a `captured` state and `isSynced = false` for instant UI feedback.
- Optionally, the user can attach a contextual tag. If omitted, processing proceeds directly.
- The system (via Gemini API processed asynchronously in Redis queues) enriches the entry with its real idiomatic equivalent, time variations, and a complementary phrase, storing the payload as structured JSONB.

**Use Case 3: Passive Environmental Exposure (Widgets & Notifications)** Active phrases are re-exposed exclusively through non-intrusive channels outside the main application interface:

- **Interactive/Dynamic Widgets (Android / iOS):**
  - **Home Screen Widget:** Fixed rotational display of the 5 active phrases in the Active Window. Features a card flip interaction to switch between base text and idiomatic translation, and includes a native Text-to-Speech (TTS) audio trigger for both languages without launching the app.
  - **Lock Screen Widget:** Ultra-compact format designed for passive, incidental reading whenever the device screen turns on.
- **Ambient Silent Notifications:** Status bar notifications (no sound, no vibration) scheduled within the user's defined active hours using a dynamic cooldown algorithm (`MIN_NOTIFICATION_INTERVAL` = 120 min, `MAX_NOTIFICATION_INTERVAL` = 240 min). Includes quick actions to play native audio (`flutter_tts`) and reveal translations upon expansion without breaking workflow.

**Use Case 4: Micro-Session Knowledge Verification (Daily Quiz)** Active evaluation fixed at exactly 3 questions (45 seconds total duration) to validate conscious retention.

- **Trigger:** Scheduled according to `quiz_preferred_time`, with an availability window extending until the end of the user's active schedule (`active_hours_end`).
- **Selection Logic:** Selects phrases currently in `in_progress` status and, when applicable, 1 phrase in `learned` status exceeding 14 days of rest (audit item). Phrase status remains untouched when entering the quiz; status changes occur strictly based on success/failure upon answering each question.
- **Time Limit:** Strict 15-second timer per question.
- **Micro-interactions:** Randomized selection across 3 quiz modes (Cloze test, Sentence scramble, and Self-assessment). Modes may repeat within the same session to enforce interleaved practice.

## 4. Phrase Lifecycle & State Rules

Every phrase (*IdiomBit*) transitions through distinct database states represented via native PHP Backed Enums (`PhraseStatus`) and Dart Enums:

`captured` (Transient / Offline) -> `queued` (Pending) -> `in_progress` (Active Window) -> `learned` (Consolidated) -> `re_learning` (High Priority Recovery)

1. **captured (Transient / Offline):** Recorded phrase persisted locally in Isar with a client-side ULID, not yet processed by AI (due to offline state or API background queue). Not visible in widgets or quizzes.
2. **queued (Pending):** Processed phrase with AI-enriched equivalents in `phrase_payloads` waiting for an open slot in the Active Window.
3. **in_progress (Active Window):** Occupies 1 of maximum 5 slots in the Active Window. Receives maximum widget exposure priority and serves as primary subject for daily quizzes.
4. **learned (Consolidated):** Phrase that has successfully passed 2 consecutive quizzes (across separate sessions). Frees a slot in the Active Window and exits daily exposure. Enters a 14-day resting period before becoming eligible for audit.
5. **re_learning (High Priority Recovery):** A previously learned phrase that reached 14 days of rest, was audited during a Daily Quiz, and was answered incorrectly. Transitions to `re_learning` and moves to the top of the queue with absolute priority (Priority 0) to take the next freed slot in the Active Window. Requires only 1 correct quiz answer in a future session to graduate back to `learned`.

## 5. System Business Rules

**Authentication and Data Isolation**

- API endpoints require Bearer Token authentication issued via Laravel Sanctum upon valid email/password login.
- Strict multi-tenancy isolation: Users can only query, capture, and evaluate phrases associated with their own `user_id`.

**Active Window Capacity (Maximum 5 Phrases)**

- To prevent cognitive overload, widgets, notifications, and quizzes rotate strictly across the 5 phrases marked as `in_progress`.
- As soon as a phrase reaches `learned` status, a slot opens and the next queued item (`queued` or `re_learning`) is automatically ingested (FIFO logic, with absolute priority given to `re_learning`).

**Graduation Rule & Counter Reset**

- A phrase in `in_progress` requires 2 correct answers on quizzes from different days/sessions to graduate to `learned` (visualized as `[ ● ○ ]` -> `[ ● ● ]`).
- If a user answers correctly on Day 1 (`[ ● ○ ]`) but fails or runs out of time (15s) on the same phrase in Day 2, the phrase score resets to 0 (`[ ○ ○ ]`) and remains in `in_progress`.

**Learned Phrase Audit Cycle (14 Days)**

- Upon reaching exactly 14 days since transitioning to `learned`, a phrase becomes eligible for audit while retaining its `learned` state (`idx_phrases_audit_eligible`).
- The Daily Quiz will randomly select 1 eligible phrase as question #3 of the session:
  - **Pass:** Remains in `learned` state and resets its 14-day audit timer (`learned_at = now()`).
  - **Fail:** State changes immediately to `re_learning` with Priority 0 in the Active Window queue.

**Character Limits & AI Chunk Enrichment**

- Text input fields reject entries exceeding 150 characters to prevent raw paragraph submission.
- Single-word inputs are expanded autonomously by AI into high-value lexical chunks/collocations rather than direct word-for-word translation.

**Deduplication & Single Source of Truth**

- Every processed phrase is persisted globally per user. If a user submits duplicate or semantically identical text, the system returns the existing entity without triggering redundant AI API calls.

**Active Schedule & Silent Notifications**

- Notifications generate no sound or vibration. They are dispatched within a bounded random distribution respecting `active_hours_start` and `active_hours_end`, maintaining a regulated cadence between dispatches via configured minimum (`MIN_NOTIFICATION_INTERVAL = 120` min) and maximum (`MAX_NOTIFICATION_INTERVAL = 240` min) time intervals.

**Quiz Session Atomicity & Timers**

- Every daily quiz consists of exactly 3 questions linked to a `DailyQuizSession`. Quitting midway discards partial session progress.
- Exceeding the 15-second per-question limit automatically marks the item as incorrect and advances to the next item.