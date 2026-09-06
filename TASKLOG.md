# TASKLOG.md — Execution Plan & Work Breakdown Structure (WBS)

## Phase 1: Backend Environment Setup in Laravel

- [X] **Task 1.1: Laravel Project Initialization on Laragon**
  * Execute `composer create-project laravel/laravel idiombits-api` inside the Laragon `www` directory.
  * Enable the `pdo_pgsql` and `pgsql` PHP extensions in `php.ini`.
  * Require the Redis client package via Composer: `composer require predis/predis`.
  * Configure environment variables in `.env` for PostgreSQL and Redis infrastructure:
    * Set database connection: `DB_CONNECTION=pgsql`, `DB_HOST=127.0.0.1`, `DB_PORT=5432`, `DB_DATABASE=idiombits`, `DB_USERNAME=postgres`, `DB_PASSWORD=`.
    * Set queue driver and Redis host: `QUEUE_CONNECTION=redis`, `REDIS_CLIENT=predis`, `REDIS_HOST=127.0.0.1`, `REDIS_PORT=6379`.
    * Append application domain constants: `MIN_NOTIFICATION_INTERVAL=120`, `MAX_NOTIFICATION_INTERVAL=240`.
  * Install API infrastructure and Sanctum authentication:
    * Run `php artisan install:api` (this command creates `routes/api.php` and publishes the Sanctum migration).
    * Open `app/Models/User.php` and add the import `use Laravel\Sanctum\HasApiTokens;` and include the `HasApiTokens` trait inside the `User` class definition.

- [x] **Task 1.2: Base Migrations Creation for PostgreSQL**
  * Modify default user migration `database/migrations/xxxx_xx_xx_create_users_table.php` adding:
    * `$table->ulid('ulid')->unique()`
    * `$table->time('active_hours_start')->default('08:00')`
    * `$table->time('active_hours_end')->default('22:00')`
    * `$table->time('quiz_preferred_time')->default('20:00')`
  * Run `php artisan make:migration create_phrases_table` and define fields (leave default `id` and `timestamps()` untouched):
    * `$table->ulid('ulid')->unique()`
    * `$table->foreignId('user_id')->constrained()->cascadeOnDelete()`
    * `$table->string('original_text', 150)`
    * `$table->string('source_language', 5)`
    * `$table->string('status', 50)` (values managed via PHP Enum `PhraseStatus`)
    * `$table->string('tag', 50)->nullable()`
    * `$table->unsignedTinyInteger('queue_position')->nullable()`
    * `$table->unsignedTinyInteger('success_streak')->default(0)`
    * `$table->timestamp('learned_at')->nullable()`
    * Performance indexes: `$table->index(['user_id', 'status'], 'idx_phrases_active_window')` and `$table->index(['status', 'learned_at'], 'idx_phrases_audit_eligible')`.
  * Run `php artisan make:migration create_phrase_payloads_table` and define fields (leave default `id` and `timestamps()` untouched):
    * `$table->foreignId('phrase_id')->unique()->constrained()->cascadeOnDelete()`
    * `$table->string('idiomatic_translation', 255)`
    * `$table->jsonb('payload_data')`
  * Run `php artisan make:migration create_daily_quiz_sessions_table` and define fields (leave default `id` and `timestamps()` untouched):
    * `$table->ulid('ulid')->unique()`
    * `$table->foreignId('user_id')->constrained()->cascadeOnDelete()`
    * `$table->date('scheduled_for')`
    * `$table->timestamp('expires_at')`
    * `$table->string('status', 50)` (values managed via PHP Enum `DailyQuizSessionStatus`)
    * Unique constraint: `$table->unique(['user_id', 'scheduled_for'])`.
  * Run `php artisan make:migration create_quiz_logs_table` and define fields (leave default `id` and `timestamps()` untouched):
    * `$table->foreignId('quiz_session_id')->constrained('daily_quiz_sessions')->cascadeOnDelete()`
    * `$table->foreignId('user_id')->constrained()->cascadeOnDelete()`
    * `$table->foreignId('phrase_id')->constrained()->cascadeOnDelete()`
    * `$table->boolean('is_success')`
    * `$table->string('quiz_type', 50)` (values managed via PHP Enum `QuizLogType`)
    * `$table->string('interaction_type', 50)` (values managed via PHP Enum `QuizLogInteractionType`)
  * Run database migrations:
    * `php artisan migrate`

---

## Phase 2: Eloquent Models, Enums, and Core Services

- [X] **Task 2.1: Eloquent Models and PHP Enums in Laravel**
  * Create Backed Enums in `app/Enums/`:
    * Run `php artisan make:enum PhraseStatus` with string values: `Captured = 'captured'`, `Queued = 'queued'`, `InProgress = 'in_progress'`, `Learned = 'learned'`, `ReLearning = 're_learning'`.
    * Run `php artisan make:enum DailyQuizSessionStatus` with string values: `Pending = 'pending'`, `Completed = 'completed'`, `Expired = 'expired'`.
    * Run `php artisan make:enum QuizLogType` with string values: `Ordinary = 'ordinary'`, `Audit = 'audit'`.
    * Run `php artisan make:enum QuizLogInteractionType` with string values: `Cloze = 'cloze'`, `SentenceScramble = 'sentence_scramble'`, `SelfAssessment = 'self_assessment'`.
  * Configure `app/Models/User.php`:
    * Add `#[RouteKey('ulid')]` class attribute for route model binding.
    * Include trait `Illuminate\Database\Eloquent\Concerns\HasUlids`.
    * Implement `uniqueIds(): array` method returning `['ulid']` to target the explicit column.
    * Define explicit mass assignment `$fillable`: `['ulid', 'name', 'email', 'password', 'active_hours_start', 'active_hours_end', 'quiz_preferred_time']`.
    * Define relationship methods returning Eloquent types: `phrases(): HasMany`, `dailyQuizSessions(): HasMany`, and `quizLogs(): HasMany`.
  * Create and configure `app/Models/Phrase.php` (`php artisan make:model Phrase`):
    * Add `#[RouteKey('ulid')]` class attribute for route model binding.
    * Implement `booted()` method to automatically generate `ulid` via `Str::ulid()` upon creation if not provided by client (Flutter offline sync).
    * Define explicit mass assignment `$fillable`: `['ulid', 'user_id', 'original_text', 'source_language', 'status', 'tag', 'queue_position', 'success_streak', 'learned_at']`.
    * Cast `status` to `PhraseStatus::class` and `learned_at` to `'datetime'`.
    * Define relationship methods returning Eloquent types: `user(): BelongsTo`, `phrasePayload(): HasOne`, and `quizLogs(): HasMany`.
  * Create and configure `app/Models/PhrasePayload.php` (`php artisan make:model PhrasePayload`):
    * Define explicit mass assignment `$fillable`: `['phrase_id', 'idiomatic_translation', 'payload_data']`.
    * Cast `'payload_data' => 'array'` to automatically abstract the PostgreSQL JSONB field.
    * Define relationship method: `phrase(): BelongsTo`.
  * Create and configure `app/Models/DailyQuizSession.php` (`php artisan make:model DailyQuizSession`):
    * Add `#[RouteKey('ulid')]` class attribute for route model binding.
    * Include trait `Illuminate\Database\Eloquent\Concerns\HasUlids`.
    * Implement `uniqueIds(): array` method returning `['ulid']`.
    * Define explicit mass assignment `$fillable`: `['ulid', 'user_id', 'scheduled_for', 'expires_at', 'status']`.
    * Cast `scheduled_for` to `'date'`, `expires_at` to `'datetime'`, and `status` to `DailyQuizSessionStatus::class`.
    * Define relationship methods returning Eloquent types: `user(): BelongsTo` and `quizLogs(): HasMany`.
  * Create and configure `app/Models/QuizLog.php` (`php artisan make:model QuizLog`):
    * Define explicit mass assignment `$fillable`: `['quiz_session_id', 'user_id', 'phrase_id', 'is_success', 'quiz_type', 'interaction_type']`.
    * Cast `is_success` to `'boolean'`, `quiz_type` to `QuizLogType::class`, and `interaction_type` to `QuizLogInteractionType::class`.
    * Define relationship methods returning Eloquent types: `quizSession(): BelongsTo`, `user(): BelongsTo`, and `phrase(): BelongsTo`.

- [x] **Task 2.2: Integration Service with Google Gemini API**
  * Create `app/Services/GeminiService.php` as a domain service class.
  * Update `config/services.php` to define `gemini` array credentials mapping to env variables with defaults:
    * `key`: `env('GEMINI_API_KEY')`
    * `base_url`: `env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta')`
    * `model`: `env('GEMINI_MODEL', 'gemini-3-flash-preview')`
  * Configure `app/Services/GeminiService.php`:
    * Implement constructor injection for optional `?string $apiKey`, `?string $baseUrl`, and `?string $model`, falling back to `config('services.gemini.*')`.
    * Implement `enrichPhrase(string $text, ?string $tag = null): array` method:
      * Validate `$apiKey` and throw `RuntimeException` if empty.
      * Build request endpoint dynamically: `{$this->baseUrl}/models/{$this->model}:generateContent`.
      * Send `POST` request using `Illuminate\Support\Facades\Http::acceptJson()->withQueryParameters(['key' => $this->apiKey])`.
      * Structure request body with `contents.0.parts.0.text` containing prompt string (appending tag context if present).
      * Include `generationConfig` with `responseMimeType => 'application/json'` and `responseSchema` (using OpenAPI-like schema format with camelCase keys):
        * Object requiring: `source_language`, `original_text`, `idiomatic_translation`, `time_variations`, `complementary_phrase`.
        * `time_variations`: ARRAY of OBJECTs requiring `tense`, `sentence`, `translation`.
        * `complementary_phrase`: OBJECT requiring `phrase`, `translation`.
      * Process response with `$response->throw()->json()`, parse the candidate text string from `candidates.0.content.parts.0.text`, decode with `json_decode(..., true)`, and validate the JSON output array before returning.

- [x] **Task 2.3: Asynchronous Job Processing with Redis Worker**
  * Run `php artisan make:job ProcessPhraseWithGemini` to generate `app/Jobs/ProcessPhraseWithGemini.php`.
  * Configure `app/Jobs/ProcessPhraseWithGemini.php`:
    * Implement interface `Illuminate\Contracts\Queue\ShouldQueue` and use `Illuminate\Foundation\Queue\Queueable` trait.
    * Define job resilience properties: `$tries = 3` and `$backoff = [5, 15, 30]`.
    * Inject model dependency `public Phrase $phrase` in `__construct(public Phrase $phrase)`.
    * Implement `handle(GeminiService $geminiService): void` method:
      * Invoke `$geminiService->enrichPhrase($this->phrase->original_text, $this->phrase->tag)`.
      * Persist enrichment result in `phrase_payloads` using `phrasePayload()->create(...)`.
      * Execute Active Window logic inside a database transaction (`DB::transaction`): check if user has fewer than 5 active phrases (`InProgress`); if so, transition status to `InProgress`, otherwise set to `Queued` calculating the corresponding `queue_position`.
    * Implement `failed(?Throwable $exception): void` method to log AI processing failures or handle fallback status if all retries are exhausted.

---

## Phase 3: REST API Endpoints and Scheduler in Laravel

- [ ] **Task 3.1: Sanctum Authentication and API Routes**
  * Run `php artisan make:controller Api/V1/AuthController` to generate `app/Http/Controllers/Api/V1/AuthController.php`.
  * Configure `app/Http/Controllers/Api/V1/AuthController.php`:
    * Implement `register(Request $request): JsonResponse` method to validate inputs, create new users, and return user payload with initial Bearer token.
    * Implement `login(Request $request): JsonResponse` method to authenticate credentials requiring `device_name` and issue Bearer tokens via `$user->createToken($request->device_name)->plainTextToken`.
  * Configure `routes/api.php`:
    * Define public authentication routes (`/v1/register`, `/v1/login`) pointing to `AuthController`.
    * Define protected route group under prefix `v1` using `auth:sanctum` middleware for all application endpoints.

- [ ] **Task 3.2: Controllers and API Resources for Phrases and Quizzes**
  * Run `php artisan make:resource Api/V1/PhraseResource` to generate `app/Http/Resources/Api/V1/PhraseResource.php` and configure `toArray(Request $request): array` method to format JSON payload using public ULIDs (`ulid`), attributes, and nested `PhrasePayload` transformation.
  * Run `php artisan make:controller Api/V1/PhraseController` to generate `app/Http/Controllers/Api/V1/PhraseController.php` and implement methods:
    * `store(Request $request): JsonResponse` (or `: PhraseResource` with 202 status code): Validates input (including client-provided `ulid`), persists new phrase with status `Captured`, dispatches `ProcessPhraseWithGemini::dispatch($phrase)` to Redis queue, and returns `PhraseResource` with HTTP `202 Accepted`.
    * `active(Request $request): AnonymousResourceCollection`: Queries and returns a collection of the 5 active phrases (`InProgress`) for the authenticated user including the `phrasePayload` relationship, wrapped in `PhraseResource::collection()`.
    * `sync(Request $request): JsonResponse`: Receives unsynced local phrases payload, executes delta database synchronization, and returns updated phrase status mappings.
  * Run `php artisan make:controller Api/V1/QuizController` to generate `app/Http/Controllers/Api/V1/QuizController.php` and implement method:
    * `submit(Request $request): JsonResponse`: Processes daily quiz answer payloads inside a database transaction (`DB::transaction`), creates `QuizLog` entries, updates phrase `success_streak`, and manages status transitions (`Learned` graduation or `ReLearning` demotion), returning a structured status response.

- [ ] **Task 3.3: Scheduler and Job for Ambient Notifications**
  * Run `php artisan make:job DispatchAmbientNotificationsJob` to generate `app/Jobs/DispatchAmbientNotificationsJob.php`.
  * Configure `app/Jobs/DispatchAmbientNotificationsJob.php`:
    * Implement interface `Illuminate\Contracts\Queue\ShouldQueue` and use `Illuminate\Foundation\Queue\Queueable` trait.
    * Implement `handle(): void` method:
      * Query active users whose current local time falls within their configured window `[active_hours_start, active_hours_end]`.
      * Apply dynamic cooldown algorithm ensuring elapsed time since the last notification is `>= MIN_NOTIFICATION_INTERVAL` and `<= MAX_NOTIFICATION_INTERVAL`.
      * Dispatch push notification payload (via FCM/APNs integration) for eligible users.
  * Configure Scheduler in `routes/console.php`:
    * Import `Illuminate\Support\Facades\Schedule` and `App\Jobs\DispatchAmbientNotificationsJob`.
    * Register job execution: `Schedule::job(new DispatchAmbientNotificationsJob)->everyFifteenMinutes();`

---

## Phase 4: Flutter Mobile App (Architecture & Local Persistence)

- [ ] **Task 4.1: Flutter Initialization and Dependency Management**
  * Create Flutter project:
    * `flutter create --org com.idiombits --template=app --platforms=android,ios idiombits_mobile`
  * Add core and storage dependencies:
    * `flutter pub add flutter_bloc isar isar_flutter_libs path_provider http home_widget workmanager ulid flutter_local_notifications flutter_secure_storage flutter_tts`
  * Add dev dependencies for code generation:
    * `flutter pub add --dev build_runner isar_generator`
  * Create secure storage service in `lib/core/services/storage_service.dart` using `FlutterSecureStorage` to manage Sanctum Bearer tokens.
  * Create custom API HTTP Client wrapper in `lib/core/network/api_client.dart` enforcing default headers (`Accept: application/json`, `Content-Type: application/json`) and automatically injecting Sanctum `Authorization: Bearer <token>` headers on every request.
  * Initialize Isar database engine in `lib/main.dart` with background isolate support:
    * Resolve application directory using `getApplicationDocumentsDirectory()`.
    * Open Isar instance passing `LocalPhraseSchema`.

- [ ] **Task 4.2: Local Models and Isar Adapters**
  * Create local entity in `lib/features/phrases/data/models/local_phrase.dart` annotated with `@Collection()`:
    * Define fields: `id` (Isar auto-increment), `ulid` (indexed, unique string for Laravel backend matching), `originalText`, `idiomaticTranslation` (nullable), `sourceLanguage`, `status` (mapped from `PhraseStatus` enum), `payloadData` (nullable string for JSON variations), `isSynced` (bool, default `false`), and `updatedAt`.
  * Compile local schema using Dart build tool:
    * `dart run build_runner build --delete-conflicting-outputs`
  * Implement `lib/features/phrases/data/repositories/phrase_repository.dart` for Offline-First abstraction:
    * Handle fast local reads from Isar and delta synchronization with `/api/v1/phrases/sync` via `ApiClient`.

---

## Phase 5: Multimodal Capture and Flutter UI

- [ ] **Task 5.1: State Management with BLoC for Capture**
  * Define events in `lib/features/phrases/presentation/bloc/phrase_event.dart`:
    * `CaptureTextSubmitted`, `CaptureAudioRecorded`, `CaptureImageCropped`, and `FetchActivePhrases`.
  * Define states in `lib/features/phrases/presentation/bloc/phrase_state.dart`:
    * `PhraseInitial`, `PhraseLoading`, `PhraseSavedLocal`, `PhraseActiveLoaded`, and `PhraseSyncError`.
  * Implement `lib/features/phrases/presentation/bloc/phrase_bloc.dart`:
    * Generate unique local `ULID` using the `ulid` package.
    * Instantly persist capture to Isar with `isSynced = false` for immediate UI response.
    * Trigger background HTTP POST to `/api/v1/phrases/store` passing local `ulid`, `raw_text`, and medium details.

- [ ] **Task 5.2: Active Window UI and Capture FAB**
  * Configure `lib/features/phrases/presentation/pages/home_page.dart`:
    * Subscribe to `PhraseBloc` to render the strict active 5-card window (`InProgress`).
  * Design `lib/features/phrases/presentation/widgets/phrase_card.dart`:
    * Implement card flip animation toggling between original text, idiomatic translation, and time variations.
  * Add expandable FloatingActionButton with `ModalBottomSheet` for rapid multimodal capture (Text, Voice, Image).

---

## Phase 6: Passive Exposure Channels, Widgets, and Notifications

- [ ] **Task 6.1: Offline Synchronization and Background Fetch**
  * Create `lib/core/services/sync_service.dart`:
    * Query unsynced local Isar records (`isSynced == false`).
    * Push batch payload to backend `/api/v1/phrases/sync` and mark `isSynced = true` upon HTTP 200/202 response.
  * Configure `WorkManager` in `lib/main.dart`:
    * Schedule periodic background task invoking `SyncService` respecting device network connectivity state.

- [ ] **Task 6.2: Native Widget Integration (Android/iOS)**
  * Create `lib/core/services/widget_service.dart`:
    * Use `HomeWidget.saveWidgetData()` and `HomeWidget.updateWidget()` to push active phrase payload to native shared storage.
  * Design Android native view in `android/app/src/main/res/layout/active_phrase_widget.xml`.
  * Bind widget update triggers inside `PhraseBloc` to refresh content on active phrase window changes.

- [ ] **Task 6.3: Native Notifications and Quick Actions**
  * Configure `flutter_local_notifications` in `lib/core/services/notification_service.dart`:
    * Create low-priority, silent notification channel for passive exposure.
  * Implement quick action triggers:
    * **"Reveal Equivalence" Action:** Attach translation and variations directly to native `BigTextStyleInformation` on dispatch to expand inline without opening the app.
    * **"Listen" Action:** Register a Background Notification Action Callback in a dedicated Dart Isolate to initialize `flutter_tts` and synthesize speech in the background upon button press.