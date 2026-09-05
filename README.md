# IdiomBits — Micro-Learning & Incidental Exposure System

**IdiomBits** is an offline-first micro-learning application engineered to eliminate cognitive friction in language acquisition. Grounded in the concept of **Invisible Design**, IdiomBits transforms language practice into passive, incidental exposure across daily mobile device interactions—powered by Google Gemini AI, native widgets, ambient notifications, and micro-quizzes.

---

## 💡 System Architecture Overview

                        +---------------------------------------------+  
                        |           FLUTTER MOBILE CLIENT             |  
                        |   (Offline-First / BLoC / Isar / Widgets)   |  
                        +----------------------+----------------------+  
                                               |  
                                     REST API (Bearer Token)  
                                               |  
                        +----------------------v----------------------+  
                        |               LARAVEL BACKEND               |  
                        |      (Sanctum / Eloquent / Redis Queue)     |  
                        +----------------------+----------------------+
                                               |  
                                        Redis Queue Job  
                                               |  
                        +----------------------v----------------------+  
                        |          GOOGLE GEMINI 2.5 FLASH            |  
                        |    (Structured JSON Payload Generator)      |  
                        +---------------------------------------------+  

---

## 🛠️ Core Technology Stack

| Layer | Framework / Technology | Purpose |
| :--- | :--- | :--- |
| **Backend API** | Laravel 13 / PHP 8.3+ | Core RESTful API & Authentication |
| **Database** | PostgreSQL 16+ | Relational data persistence (JSONB support) |
| **Queue & Processing** | Redis (`predis`) + Laravel Worker (`queue:work`) | Asynchronous job execution & retry backoff |
| **AI Enrichment** | Google Gemini API (`gemini-2.5-flash`) | Contextual translation, tenses & phrasing |
| **Mobile App** | Flutter 3.x (Android & iOS) | Cross-platform client UI & local engine |
| **State Management** | Flutter BLoC | Deterministic reactive state handling |
| **Local Database** | Isar Database | High-performance client-side storage |
| **Token Security** | FlutterSecureStorage | Secure offline storage for Sanctum Bearer tokens |
| **Background Sync** | WorkManager (`workmanager`) | Connectivity-aware data synchronization |
| **Passive UI** | home_widget (Android/iOS) | Home screen & Lock screen integration |
| **Audio Output** | flutter_tts | Native Text-to-Speech playback |

---

## Key Features & Highlights

### ⚡ Zero-Friction 2-Tap Capture

- **Multimodal Ingestion:** Instant capture via **Text**, **Voice (Speech-to-Text)**, or **Image (OCR with cropping)**.
- **Automatic Idiomatization:** Single words are automatically converted by AI into natural lexical chunks or collocations.
- **150-Character Guardrail:** Enforces concise micro-phrases over bulky paragraphs.
- **Client ULID Generation:** Generates unique identifiers offline before dispatching to backend endpoints.

### 🎯 The 5-Slot Active Window

- **Constrained Focus:** Limits active learning strictly to **5 phrases** in `in_progress` status per user to prevent cognitive overload.
- **FIFO Pipeline:** Automatic ingestion from `queued` inventory whenever an active phrase graduates to `learned`.
- **Streak Progression:** Requires 2 consecutive correct answers in quizzes (`success_streak = 2`) to graduate a phrase.

### 📱 Passive & Ambient Exposure

- **Interactive Home Screen Widget:** Powered by `home_widget` reading directly from local Isar Database, featuring a 3D flip card toggle between native phrase and AI translation, audio TTS via `flutter_tts`, and phrase navigation without launching the app.
- **Low-Priority Silent Notifications:** Ambient status-bar updates dispatched within user-defined active hours (`active_hours_start` to `active_hours_end`).
- **Direct Notification Actions:** Inline buttons for background audio playback and full translation reveals.

### ⏱️ 45-Second Daily Micro-Quizzes

- **3-Question Micro-Sessions:** Dynamic formats including **Cloze tests**, **Sentence scrambles**, and **Self-assessments**.
- **15-Second Time-Cap:** Enforces rapid recall per question with immediate UI streak feedback.
- **14-Day Audit Cycle:** Graduated phrases re-enter audit sessions after 14 days (`learned_at <= NOW() - 14 days`) to guarantee long-term retention.

---

## 🗄️ Database Entity-Relationship Diagram (ERD)

```
+-----------+                  +-----------+
  |   users   | 1 ------------ * |  phrases  | 1 --- 1 +-------------------+
  +-----+-----+                  +-----+-----+         | phrase_payloads   |
        |                              |               +-------------------+
        |                              |
        | 1                            | 1
        |                              |
        v *                            v *
  +-----+-------------------+    +-----+-----+
  |  daily_quiz_sessions    |    |           |
  +-----------+-------------+    |           |
              | 1                | quiz_logs |
              |                  |           |
              v *                |           |
              +----------------->+-----------+
```

---

## 🚀 Environment Setup & Installation Guide

### Prerequisites

- **PHP 8.3+** and **Composer 2.x**
- **PostgreSQL 16+** & **Redis**
- **Flutter SDK 3.x** & **Dart SDK**
- **Google Gemini API Key**

### Backend Setup (Laravel)

```bash
# 1. Clone the repository and enter the backend directory 
cd backend-api 

# 2. Install PHP dependencies 
composer install 

# 3. Environment Configuration 
cp .env.example .env 

# Configure PostgreSQL & Redis inside .env: 
# DB_CONNECTION=pgsql 
# DB_HOST=127.0.0.1 
# DB_PORT=5432 
# DB_DATABASE=idiombits 
# DB_USERNAME=postgres 
# DB_PASSWORD=secret 
# QUEUE_CONNECTION=redis 
# GEMINI_API_KEY=your_gemini_api_key_here 

# 4. Generate Application Key & Run Migrations 
php artisan key:generate 
php artisan migrate 

# 5. Start Laravel Queue Worker 
php artisan queue:work redis --tries=3 --backoff=5,15,30

### Mobile Setup (Flutter)

# 1. Navigate to the mobile application directory 
cd idiombits_mobile 

# 2. Fetch dependencies 
flutter pub get 

# 3. Run Isar code generation 
flutter pub run build_runner build --delete-conflicting-outputs 

# 4. Launch Application (Emulator or Device) 
flutter run

## 📡 Core API Specification

| Method | Endpoint                | Description                                    | Auth Required |
| ------ | ----------------------- | ---------------------------------------------- | ------------- |
| POST   | /api/v1/register        | Create a new user account                      | ❌            |
| POST   | /api/v1/login           | Authenticate & retrieve Bearer Token           | ❌            |
| GET    | /api/v1/phrases/active  | Fetch the 5 active in_progress phrases         | ✅            |
| POST   | /api/v1/phrases/capture | Ingest new phrase for AI enrichment            | ✅            |
| GET    | /api/v1/phrases/sync    | Delta synchronization using last_synced_at     | ✅            |
| POST   | /api/v1/quiz/submit     | Process micro-session answers & update streaks | ✅            |
