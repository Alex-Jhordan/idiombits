# AGENTS.md — AI Agent Collaboration & System Guidelines

This document establishes the operational parameters, architectural principles, and execution protocols for AI Agents operating within the **IdiomBits** codebase. All automated collaborators must strictly follow these instructions to maintain system integrity.

## 1. System Identity & Core Philosophy

**IdiomBits** is an offline-first micro-learning ecosystem designed around the principle of **Invisible Design (Zero Friction)**.

- **Primary Directive:** Minimize cognitive load for the user at every layer.
- **Strict Constraints:**
  - Ingestion must adhere to the **2-Tap Rule** (under 2 interactions to record text/voice/image).
  - Text inputs are capped at a strict maximum of **150 characters**.
  - The active learning inventory is bounded to an **Active Window of exactly 5 phrases** in `in_progress` status per user.
  - Quizzes are limited to micro-sessions of **3 questions / 15-second cap** per question (`DailyQuizSession`).

---

## 2. Technical Stack & Architecture Guidelines

All implementations must conform strictly to the established technical stack:

### Backend (Laravel)

- **Framework & Engine:** Laravel 13 running on PHP 8.3+.
- **Database:** PostgreSQL 16+ using native `JSONB` for payload enrichment storage, `BIGINT` primary keys for high-performance internal joins, **`ULID`** unique public keys on user-facing domain entities (`users`, `phrases`, `daily_quiz_sessions`) for secure API endpoints and conflict-free offline mobile synchronization, and database transaction blocks (`DB::transaction`).
- **Authentication:** Stateful API token management using **Laravel Sanctum** (`Bearer` tokens).
- **Asynchronous Processing:** Operations involving external AI APIs must run via native **Laravel Queue Worker** (`queue:work`) backed by **Redis** queues (`predis`) with explicit exponential backoff configuration (`$tries = 3`, `$backoff = [5, 15, 30]`).
- **AI Provider:** Integration via `GeminiService` calling Google Gemini (`gemini-2.5-flash`) using structured JSON schema constraints (`response_mime_type: "application/json"`).

### Mobile (Flutter)

- **Framework:** Flutter 3.x with Dart.
- **State Management:** **BLoC** (Business Logic Component) pattern.
- **Local Storage:** **Isar Database** for offline-first local persistence before remote sync, combined with **`FlutterSecureStorage`** for token security.
- **Offline-First:** Network operations must write locally first (`PendingCaptures`) with generated ULIDs, delegating remote dispatch to background synchronization workers (**WorkManager** / `workmanager`).
- **Native Integration:** Widgets powered by `home_widget` for Android/iOS reading directly from Isar Database.
- **Audio Output:** Text-to-Speech powered by `flutter_tts`.

---

## 3. Workflow Execution Protocol

When implementing tasks from `TASKLOG.md`:

1. **Check Task Status:** Identify the next uncompleted task (`- [ ]`) in `TASKLOG.md` in sequential order.
2. **Strict Scope:** Implement **ONLY** the files, classes, migrations, or components explicitly specified in the current task. Do not jump ahead to future phases or speculate on unwritten requirements.
3. **Data & State Integrity:** Ensure all state transitions (e.g., phrase promotion from `queued` to `in_progress`, streak incrementing, and 14-day audit scheduling) execute within database transactions (`DB::transaction`) to avoid orphaned states.
4. **Offline-First Verification:** For client-side UI and repository work, verify that local storage (Isar) is updated prior to issuing remote REST API requests.
5. **Mark Completion:** Update `TASKLOG.md` upon successful verification of the task by marking the corresponding checkbox `- [x]`.

---

## 4. Code Generation & Conventions

- **Database Migrations:** Never generate migration files manually to prevent timestamp collision or naming mismatch. Always delegate file creation to the official Artisan CLI command (`php artisan make:migration create_[table_name]_table`).
- **Laravel Enums & Models:** 
  - Enums must reside in `app/Enums/` using PHP Backed Enums (`string`). Enum cases must use `PascalCase`, while their backed string values must strictly use `snake_case` (e.g., `case InProgress = 'in_progress';`).
  - Models must reside in `app/Models/` using singular PascalCase (`Phrase.php`).
- **Controllers & Resources:** API V1 controllers belong in `app/Http/Controllers/Api/V1/` (`PhraseController.php`). Resources belong in `app/Http/Resources/Api/V1/`.
- **Jobs & Services:** Jobs belong in `app/Jobs/` (`ProcessPhraseWithGemini.php`). External services belong in `app/Services/` (`GeminiService.php`).
- **Flutter Architecture:** Maintain strict feature-folder separation under `lib/features/[feature_name]/` broken into `data/`, `bloc/`, and `presentation/`.

---

## 5. Security & Safety Rules

- **API Keys & Secrets:** Never hardcode credentials, Bearer tokens, or API keys (`GEMINI_API_KEY`) in codebase files. Always pull from `config(...)` or environment files (`.env`).
- **Data Sanitization:** Validate all multimodal inputs prior to queue dispatch. Reject payloads exceeding 150 characters on both client and server layers.