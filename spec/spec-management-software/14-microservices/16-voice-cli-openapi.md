# Voice-CLI Service OpenAPI Specification

**Version:** 1.0.0  
**Status:** Planned  
**Updated:** 2026-01-30  

---

## Overview

Complete OpenAPI 3.1 specification for the Voice-CLI service REST API and WebSocket streaming protocol.

**Cross-References:**
- [Voice-CLI Service](./15-voice-cli-service.md) — Service implementation details
- [Gateway OpenAPI](./09-gateway-openapi.md) — Gateway routing patterns
- [AI-Bridge Service](./08-ai-bridge-service.md) — LLM fallback integration

---

## OpenAPI Specification

```yaml
openapi: 3.1.0
info:
  title: Voice-CLI Service API
  description: |
    Real-time voice transcription and command recognition service.
    Provides audio capture, speech-to-text via Local Whisper, grammar-based
    intent recognition with LLM fallback, and command execution.
  version: 1.0.0
  contact:
    name: Voice-CLI Team
  license:
    name: MIT

servers:
  - url: http://localhost:8086
    description: Local development
  - url: http://voice-cli:8086
    description: Docker network
  - url: '{protocol}://{host}:{port}'
    description: Custom deployment
    variables:
      protocol:
        default: http
        enum: [http, https]
      host:
        default: localhost
      port:
        default: '8086'

tags:
  - name: Sessions
    description: Voice session management
  - name: Transcription
    description: Speech-to-text operations
  - name: Intents
    description: Intent recognition and patterns
  - name: Commands
    description: Command execution
  - name: Models
    description: Whisper model management
  - name: Conversations
    description: Conversation history
  - name: Health
    description: Service health checks

paths:
  # ============================================================
  # SESSION MANAGEMENT
  # ============================================================
  /api/v1/sessions:
    post:
      operationId: createSession
      tags: [Sessions]
      summary: Create a new voice session
      description: |
        Creates a new voice session with optional configuration.
        Sessions are used to manage audio streaming and transcription state.
      requestBody:
        required: false
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/CreateSessionRequest'
      responses:
        '201':
          description: Session created successfully
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/SessionResponse'
        '400':
          $ref: '#/components/responses/BadRequest'
        '429':
          $ref: '#/components/responses/TooManyRequests'
        '500':
          $ref: '#/components/responses/InternalError'

  /api/v1/sessions/{sessionId}:
    parameters:
      - $ref: '#/components/parameters/SessionId'
    get:
      operationId: getSession
      tags: [Sessions]
      summary: Get session details
      description: Retrieves details of an existing voice session.
      responses:
        '200':
          description: Session details
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/SessionResponse'
        '404':
          $ref: '#/components/responses/NotFound'
        '500':
          $ref: '#/components/responses/InternalError'
    delete:
      operationId: closeSession
      tags: [Sessions]
      summary: Close a voice session
      description: |
        Closes and cleans up a voice session.
        Any ongoing transcription will be finalized.
      responses:
        '200':
          description: Session closed
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/SuccessResponse'
        '404':
          $ref: '#/components/responses/NotFound'
        '500':
          $ref: '#/components/responses/InternalError'

  /api/v1/sessions/{sessionId}/status:
    parameters:
      - $ref: '#/components/parameters/SessionId'
    get:
      operationId: getSessionStatus
      tags: [Sessions]
      summary: Get session status
      description: Returns real-time status of a voice session.
      responses:
        '200':
          description: Session status
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/SessionStatusResponse'
        '404':
          $ref: '#/components/responses/NotFound'
        '500':
          $ref: '#/components/responses/InternalError'

  # ============================================================
  # TRANSCRIPTION
  # ============================================================
  /api/v1/transcribe:
    post:
      operationId: transcribeAudio
      tags: [Transcription]
      summary: Transcribe audio file
      description: |
        Transcribes a complete audio segment using Whisper.
        Supports PCM16, WebM, and WAV formats.
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/TranscribeRequest'
          multipart/form-data:
            schema:
              type: object
              required: [audio]
              properties:
                audio:
                  type: string
                  format: binary
                  description: Audio file (WAV, WebM, or raw PCM)
                language:
                  type: string
                  description: ISO 639-1 language code or "auto"
                  default: auto
                model:
                  $ref: '#/components/schemas/WhisperModel'
      responses:
        '200':
          description: Transcription result
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/TranscribeResponse'
        '400':
          $ref: '#/components/responses/BadRequest'
        '413':
          description: Audio file too large
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ErrorResponse'
        '422':
          description: Unsupported audio format
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ErrorResponse'
        '500':
          $ref: '#/components/responses/InternalError'

  /api/v1/transcribe/stream:
    post:
      operationId: streamTranscribe
      tags: [Transcription]
      summary: Stream transcription via SSE
      description: |
        Transcribes audio with real-time partial results via Server-Sent Events.
        Returns partial transcripts as they become available.
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/StreamTranscribeRequest'
      responses:
        '200':
          description: SSE stream of transcription events
          content:
            text/event-stream:
              schema:
                type: string
                description: |
                  SSE events with the following types:
                  - `partial`: Interim transcription result
                  - `final`: Complete transcription result
                  - `error`: Transcription error
              examples:
                partial:
                  value: |
                    event: partial
                    data: {"text": "Hello, how are"}
                    
                    event: partial
                    data: {"text": "Hello, how are you doing"}
                    
                    event: final
                    data: {"id": "tr_abc123", "text": "Hello, how are you doing today?", "language": "en", "confidence": 0.95}
        '400':
          $ref: '#/components/responses/BadRequest'
        '500':
          $ref: '#/components/responses/InternalError'

  # ============================================================
  # INTENT RECOGNITION
  # ============================================================
  /api/v1/intents/recognize:
    post:
      operationId: recognizeIntent
      tags: [Intents]
      summary: Recognize intent from text
      description: |
        Analyzes text to recognize command intent using grammar patterns
        with optional LLM fallback for complex queries.
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/RecognizeRequest'
      responses:
        '200':
          description: Intent recognition result
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/RecognizeResponse'
        '400':
          $ref: '#/components/responses/BadRequest'
        '500':
          $ref: '#/components/responses/InternalError'

  /api/v1/intents:
    get:
      operationId: listIntents
      tags: [Intents]
      summary: List available intents
      description: Returns all recognized command intents with their descriptions.
      responses:
        '200':
          description: List of intents
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/IntentListResponse'
        '500':
          $ref: '#/components/responses/InternalError'

  /api/v1/intents/patterns:
    post:
      operationId: addPattern
      tags: [Intents]
      summary: Add custom command pattern
      description: |
        Registers a custom regex pattern for intent recognition.
        Custom patterns take priority over built-in patterns.
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/AddPatternRequest'
      responses:
        '201':
          description: Pattern added
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/PatternResponse'
        '400':
          $ref: '#/components/responses/BadRequest'
        '409':
          description: Pattern already exists
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ErrorResponse'
        '500':
          $ref: '#/components/responses/InternalError'
    get:
      operationId: listPatterns
      tags: [Intents]
      summary: List custom patterns
      description: Returns all custom command patterns.
      parameters:
        - name: intent
          in: query
          schema:
            $ref: '#/components/schemas/CommandIntent'
          description: Filter by intent type
      responses:
        '200':
          description: List of patterns
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/PatternListResponse'
        '500':
          $ref: '#/components/responses/InternalError'

  /api/v1/intents/patterns/{patternId}:
    parameters:
      - name: patternId
        in: path
        required: true
        schema:
          type: string
        description: Pattern identifier
    delete:
      operationId: deletePattern
      tags: [Intents]
      summary: Delete custom pattern
      responses:
        '200':
          description: Pattern deleted
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/SuccessResponse'
        '404':
          $ref: '#/components/responses/NotFound'
        '500':
          $ref: '#/components/responses/InternalError'

  # ============================================================
  # COMMAND EXECUTION
  # ============================================================
  /api/v1/commands/execute:
    post:
      operationId: executeCommand
      tags: [Commands]
      summary: Execute a command
      description: |
        Executes a recognized command intent with provided slots.
        Routes to appropriate service handlers (SpecManager, Nexus-Flow, etc.).
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/ExecuteRequest'
      responses:
        '200':
          description: Command execution result
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ExecuteResponse'
        '400':
          $ref: '#/components/responses/BadRequest'
        '404':
          description: No handler for intent
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ErrorResponse'
        '500':
          $ref: '#/components/responses/InternalError'

  /api/v1/commands/history:
    get:
      operationId: getCommandHistory
      tags: [Commands]
      summary: Get command history
      description: Returns history of executed commands.
      parameters:
        - $ref: '#/components/parameters/Limit'
        - $ref: '#/components/parameters/Offset'
        - name: conversationId
          in: query
          schema:
            type: string
          description: Filter by conversation
        - name: intent
          in: query
          schema:
            $ref: '#/components/schemas/CommandIntent'
          description: Filter by intent type
        - name: success
          in: query
          schema:
            type: boolean
          description: Filter by success status
      responses:
        '200':
          description: Command history
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/CommandHistoryResponse'
        '500':
          $ref: '#/components/responses/InternalError'

  # ============================================================
  # MODEL MANAGEMENT
  # ============================================================
  /api/v1/models:
    get:
      operationId: listModels
      tags: [Models]
      summary: List Whisper models
      description: Returns all available and downloaded Whisper models.
      responses:
        '200':
          description: List of models
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ModelListResponse'
        '500':
          $ref: '#/components/responses/InternalError'

  /api/v1/models/{modelId}:
    parameters:
      - name: modelId
        in: path
        required: true
        schema:
          $ref: '#/components/schemas/WhisperModel'
        description: Whisper model identifier
    get:
      operationId: getModelInfo
      tags: [Models]
      summary: Get model information
      description: Returns detailed information about a Whisper model.
      responses:
        '200':
          description: Model information
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ModelInfoResponse'
        '404':
          $ref: '#/components/responses/NotFound'
        '500':
          $ref: '#/components/responses/InternalError'
    delete:
      operationId: deleteModel
      tags: [Models]
      summary: Delete a downloaded model
      description: Removes a downloaded Whisper model from disk.
      responses:
        '200':
          description: Model deleted
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/SuccessResponse'
        '404':
          $ref: '#/components/responses/NotFound'
        '409':
          description: Model is currently in use
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ErrorResponse'
        '500':
          $ref: '#/components/responses/InternalError'

  /api/v1/models/{modelId}/download:
    parameters:
      - name: modelId
        in: path
        required: true
        schema:
          $ref: '#/components/schemas/WhisperModel'
    post:
      operationId: downloadModel
      tags: [Models]
      summary: Download a Whisper model
      description: |
        Initiates download of a Whisper model.
        Returns immediately; use status endpoint to track progress.
      responses:
        '202':
          description: Download started
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/DownloadResponse'
        '409':
          description: Model already downloaded or download in progress
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ErrorResponse'
        '500':
          $ref: '#/components/responses/InternalError'

  /api/v1/models/{modelId}/status:
    parameters:
      - name: modelId
        in: path
        required: true
        schema:
          $ref: '#/components/schemas/WhisperModel'
    get:
      operationId: getModelStatus
      tags: [Models]
      summary: Get model download/load status
      description: Returns current status of model download or loading.
      responses:
        '200':
          description: Model status
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ModelStatusResponse'
        '404':
          $ref: '#/components/responses/NotFound'
        '500':
          $ref: '#/components/responses/InternalError'

  # ============================================================
  # CONVERSATIONS
  # ============================================================
  /api/v1/conversations:
    get:
      operationId: listConversations
      tags: [Conversations]
      summary: List conversations
      description: Returns all voice conversation sessions.
      parameters:
        - $ref: '#/components/parameters/Limit'
        - $ref: '#/components/parameters/Offset'
        - name: projectId
          in: query
          schema:
            type: string
          description: Filter by project
      responses:
        '200':
          description: List of conversations
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ConversationListResponse'
        '500':
          $ref: '#/components/responses/InternalError'

  /api/v1/conversations/{conversationId}:
    parameters:
      - name: conversationId
        in: path
        required: true
        schema:
          type: string
    get:
      operationId: getConversation
      tags: [Conversations]
      summary: Get conversation details
      description: Returns details of a specific conversation.
      responses:
        '200':
          description: Conversation details
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ConversationResponse'
        '404':
          $ref: '#/components/responses/NotFound'
        '500':
          $ref: '#/components/responses/InternalError'
    delete:
      operationId: deleteConversation
      tags: [Conversations]
      summary: Delete a conversation
      description: Deletes a conversation and all associated data.
      responses:
        '200':
          description: Conversation deleted
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/SuccessResponse'
        '404':
          $ref: '#/components/responses/NotFound'
        '500':
          $ref: '#/components/responses/InternalError'

  /api/v1/conversations/{conversationId}/transcripts:
    parameters:
      - name: conversationId
        in: path
        required: true
        schema:
          type: string
    get:
      operationId: getConversationTranscripts
      tags: [Conversations]
      summary: Get conversation transcripts
      description: Returns all transcripts for a conversation.
      parameters:
        - $ref: '#/components/parameters/Limit'
        - $ref: '#/components/parameters/Offset'
      responses:
        '200':
          description: List of transcripts
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/TranscriptListResponse'
        '404':
          $ref: '#/components/responses/NotFound'
        '500':
          $ref: '#/components/responses/InternalError'

  # ============================================================
  # CONFIGURATION
  # ============================================================
  /api/v1/config:
    get:
      operationId: getConfig
      tags: [Configuration]
      summary: Get current configuration
      description: Returns current Voice-CLI configuration.
      responses:
        '200':
          description: Configuration
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ConfigResponse'
        '500':
          $ref: '#/components/responses/InternalError'
    patch:
      operationId: updateConfig
      tags: [Configuration]
      summary: Update configuration
      description: Updates Voice-CLI configuration settings.
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/UpdateConfigRequest'
      responses:
        '200':
          description: Configuration updated
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/ConfigResponse'
        '400':
          $ref: '#/components/responses/BadRequest'
        '500':
          $ref: '#/components/responses/InternalError'

  # ============================================================
  # HEALTH CHECKS
  # ============================================================
  /health:
    get:
      operationId: healthCheck
      tags: [Health]
      summary: Full health check
      description: Returns comprehensive health status of the service.
      responses:
        '200':
          description: Service is healthy
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/HealthResponse'
        '503':
          description: Service is unhealthy
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/HealthResponse'

  /health/ready:
    get:
      operationId: readinessCheck
      tags: [Health]
      summary: Kubernetes readiness probe
      description: Returns 200 if service is ready to accept traffic.
      responses:
        '200':
          description: Ready
        '503':
          description: Not ready

  /health/live:
    get:
      operationId: livenessCheck
      tags: [Health]
      summary: Kubernetes liveness probe
      description: Returns 200 if service is alive.
      responses:
        '200':
          description: Alive
        '503':
          description: Not alive

# ============================================================
# COMPONENTS
# ============================================================
components:
  # ----------------------------------------------------------
  # PARAMETERS
  # ----------------------------------------------------------
  parameters:
    SessionId:
      name: sessionId
      in: path
      required: true
      schema:
        type: string
      description: Voice session identifier

    Limit:
      name: limit
      in: query
      schema:
        type: integer
        minimum: 1
        maximum: 100
        default: 50
      description: Maximum number of items to return

    Offset:
      name: offset
      in: query
      schema:
        type: integer
        minimum: 0
        default: 0
      description: Number of items to skip

  # ----------------------------------------------------------
  # SCHEMAS
  # ----------------------------------------------------------
  schemas:
    # === Enums ===
    WhisperModel:
      type: string
      enum: [tiny, base, small, medium, large-v3]
      description: Whisper model size
      example: large-v3

    AudioEncoding:
      type: string
      enum: [pcm16, pcm32f, opus, webm]
      description: Audio encoding format
      example: pcm16

    CommandIntent:
      type: string
      enum:
        - CREATE_SPEC
        - UPDATE_SPEC
        - DELETE_SPEC
        - READ_SPEC
        - LIST_SPECS
        - SEARCH_SPECS
        - CREATE_STAGE
        - UPDATE_STAGE
        - MOVE_STAGE
        - COMPLETE_STAGE
        - RUN_FLOW
        - PAUSE_FLOW
        - RESUME_FLOW
        - CANCEL_FLOW
        - SET_VARIABLE
        - GET_VARIABLE
        - NAVIGATE
        - GO_BACK
        - REFRESH
        - HELP
        - UNDO
        - REDO
        - CANCEL
        - CONVERSATION
        - CLARIFY
      description: Recognized command intent type

    RecognitionMethod:
      type: string
      enum: [grammar, llm, hybrid]
      description: Method used for intent recognition

    VADState:
      type: string
      enum: [silence, speech_start, speech, speech_end]
      description: Voice Activity Detection state

    SessionStatus:
      type: string
      enum: [active, recording, processing, idle, closed]
      description: Voice session status

    # === Request Schemas ===
    CreateSessionRequest:
      type: object
      properties:
        conversationId:
          type: string
          description: Associate with existing conversation
        projectId:
          type: string
          description: Project context for commands
        config:
          $ref: '#/components/schemas/SessionConfig'

    SessionConfig:
      type: object
      properties:
        audioFormat:
          $ref: '#/components/schemas/AudioEncoding'
        sampleRate:
          type: integer
          enum: [8000, 16000, 24000, 44100, 48000]
          default: 16000
        vadEnabled:
          type: boolean
          default: true
        vadThreshold:
          type: number
          format: float
          minimum: 0
          maximum: 1
          default: 0.5
        whisperModel:
          $ref: '#/components/schemas/WhisperModel'
        language:
          type: string
          description: ISO 639-1 language code or "auto"
          default: auto
        intentEnabled:
          type: boolean
          default: true
        llmFallback:
          type: boolean
          default: true

    TranscribeRequest:
      type: object
      required: [audio]
      properties:
        audio:
          type: string
          format: byte
          description: Base64 encoded audio data
        format:
          $ref: '#/components/schemas/AudioEncoding'
        sampleRate:
          type: integer
          default: 16000
        language:
          type: string
          default: auto
        model:
          $ref: '#/components/schemas/WhisperModel'
        wordTimestamps:
          type: boolean
          default: true

    StreamTranscribeRequest:
      type: object
      required: [audio]
      properties:
        audio:
          type: string
          format: byte
          description: Base64 encoded audio data
        format:
          $ref: '#/components/schemas/AudioEncoding'
        sampleRate:
          type: integer
          default: 16000
        language:
          type: string
          default: auto
        model:
          $ref: '#/components/schemas/WhisperModel'

    RecognizeRequest:
      type: object
      required: [text]
      properties:
        text:
          type: string
          minLength: 1
          maxLength: 2000
          description: Text to analyze for intent
        context:
          $ref: '#/components/schemas/ConversationContext'

    ConversationContext:
      type: object
      properties:
        recentTranscripts:
          type: array
          items:
            type: string
          maxItems: 10
          description: Recent transcript history for context
        currentSpecId:
          type: string
          description: Currently active spec
        currentFlowId:
          type: string
          description: Currently active flow
        projectId:
          type: string
          description: Current project context

    AddPatternRequest:
      type: object
      required: [intent, patterns]
      properties:
        intent:
          $ref: '#/components/schemas/CommandIntent'
        patterns:
          type: array
          items:
            type: string
          minItems: 1
          description: Regex patterns for matching
        slots:
          type: array
          items:
            $ref: '#/components/schemas/SlotDefinition'
        priority:
          type: integer
          default: 0
          description: Higher priority patterns are matched first
        examples:
          type: array
          items:
            type: string
          description: Example phrases for documentation

    SlotDefinition:
      type: object
      required: [name, type]
      properties:
        name:
          type: string
        type:
          type: string
          enum: [text, entity, number, date, enum, path]
        required:
          type: boolean
          default: false
        patterns:
          type: array
          items:
            type: string

    ExecuteRequest:
      type: object
      required: [intent]
      properties:
        intent:
          $ref: '#/components/schemas/CommandIntent'
        slots:
          type: object
          additionalProperties: true
          description: Slot values for command parameters
        conversationId:
          type: string
          description: Conversation context

    UpdateConfigRequest:
      type: object
      properties:
        defaultModel:
          $ref: '#/components/schemas/WhisperModel'
        defaultLanguage:
          type: string
        vadEnabled:
          type: boolean
        vadThreshold:
          type: number
          format: float
        intentEnabled:
          type: boolean
        llmFallback:
          type: boolean
        llmTimeout:
          type: string
          description: Duration string (e.g., "5s")

    # === Response Schemas ===
    SuccessResponse:
      type: object
      required: [success]
      properties:
        success:
          type: boolean
          example: true
        message:
          type: string

    ErrorResponse:
      type: object
      required: [success, error]
      properties:
        success:
          type: boolean
          example: false
        error:
          $ref: '#/components/schemas/ErrorDetail'

    ErrorDetail:
      type: object
      required: [code, constant, message]
      properties:
        code:
          type: integer
          description: Numeric error code (11xxx range)
          example: 11022
        constant:
          type: string
          description: Error constant name
          example: ERR_WHISPER_TRANSCRIBE_FAILED
        message:
          type: string
          description: Human-readable error message
        details:
          type: object
          additionalProperties: true
        retryable:
          type: boolean
          default: false
        stack:
          type: array
          items:
            type: string
          maxItems: 40
          description: Stack trace frames

    SessionResponse:
      type: object
      required: [success, data]
      properties:
        success:
          type: boolean
          example: true
        data:
          $ref: '#/components/schemas/Session'

    Session:
      type: object
      properties:
        id:
          type: string
        conversationId:
          type: string
        status:
          $ref: '#/components/schemas/SessionStatus'
        config:
          $ref: '#/components/schemas/SessionConfig'
        createdAt:
          type: string
          format: date-time
        lastActivity:
          type: string
          format: date-time

    SessionStatusResponse:
      type: object
      required: [success, data]
      properties:
        success:
          type: boolean
        data:
          type: object
          properties:
            id:
              type: string
            status:
              $ref: '#/components/schemas/SessionStatus'
            isRecording:
              type: boolean
            duration:
              type: integer
              description: Recording duration in milliseconds
            chunkCount:
              type: integer
            audioLevel:
              type: number
              format: float
            vadState:
              $ref: '#/components/schemas/VADState'

    TranscribeResponse:
      type: object
      required: [success, data]
      properties:
        success:
          type: boolean
        data:
          $ref: '#/components/schemas/Transcript'

    Transcript:
      type: object
      properties:
        id:
          type: string
        text:
          type: string
        language:
          type: string
        languageProbability:
          type: number
          format: float
        durationMs:
          type: integer
        words:
          type: array
          items:
            $ref: '#/components/schemas/TranscriptWord'
        processingMs:
          type: integer

    TranscriptWord:
      type: object
      properties:
        word:
          type: string
        start:
          type: integer
          description: Start time in milliseconds
        end:
          type: integer
          description: End time in milliseconds
        probability:
          type: number
          format: float

    RecognizeResponse:
      type: object
      required: [success, data]
      properties:
        success:
          type: boolean
        data:
          $ref: '#/components/schemas/IntentMatch'

    IntentMatch:
      type: object
      properties:
        intent:
          $ref: '#/components/schemas/CommandIntent'
        confidence:
          type: number
          format: float
          minimum: 0
          maximum: 1
        slots:
          type: object
          additionalProperties:
            $ref: '#/components/schemas/SlotValue'
        rawText:
          type: string
        method:
          $ref: '#/components/schemas/RecognitionMethod'

    SlotValue:
      type: object
      properties:
        raw:
          type: string
        normalized:
          description: Normalized value (type depends on slot type)
        type:
          type: string
        confidence:
          type: number
          format: float

    IntentListResponse:
      type: object
      required: [success, data]
      properties:
        success:
          type: boolean
        data:
          type: array
          items:
            type: object
            properties:
              intent:
                $ref: '#/components/schemas/CommandIntent'
              description:
                type: string
              category:
                type: string
                enum: [spec, stage, flow, variable, navigation, system, conversation]
              slots:
                type: array
                items:
                  $ref: '#/components/schemas/SlotDefinition'

    PatternResponse:
      type: object
      required: [success, data]
      properties:
        success:
          type: boolean
        data:
          $ref: '#/components/schemas/Pattern'

    Pattern:
      type: object
      properties:
        id:
          type: string
        intent:
          $ref: '#/components/schemas/CommandIntent'
        patterns:
          type: array
          items:
            type: string
        slots:
          type: array
          items:
            $ref: '#/components/schemas/SlotDefinition'
        priority:
          type: integer
        enabled:
          type: boolean
        createdAt:
          type: string
          format: date-time

    PatternListResponse:
      type: object
      required: [success, data]
      properties:
        success:
          type: boolean
        data:
          type: array
          items:
            $ref: '#/components/schemas/Pattern'
        pagination:
          $ref: '#/components/schemas/Pagination'

    ExecuteResponse:
      type: object
      required: [success, data]
      properties:
        success:
          type: boolean
        data:
          type: object
          properties:
            intent:
              $ref: '#/components/schemas/CommandIntent'
            success:
              type: boolean
            message:
              type: string
            data:
              type: object
              additionalProperties: true
            speakText:
              type: string
              description: Text for TTS response
            actions:
              type: array
              items:
                $ref: '#/components/schemas/FollowupAction'

    FollowupAction:
      type: object
      properties:
        label:
          type: string
        intent:
          $ref: '#/components/schemas/CommandIntent'
        slots:
          type: object
          additionalProperties:
            type: string

    CommandHistoryResponse:
      type: object
      required: [success, data]
      properties:
        success:
          type: boolean
        data:
          type: array
          items:
            type: object
            properties:
              id:
                type: string
              intent:
                $ref: '#/components/schemas/CommandIntent'
              success:
                type: boolean
              message:
                type: string
              executionTimeMs:
                type: integer
              createdAt:
                type: string
                format: date-time
        pagination:
          $ref: '#/components/schemas/Pagination'

    ModelListResponse:
      type: object
      required: [success, data]
      properties:
        success:
          type: boolean
        data:
          type: array
          items:
            $ref: '#/components/schemas/ModelInfo'

    ModelInfoResponse:
      type: object
      required: [success, data]
      properties:
        success:
          type: boolean
        data:
          $ref: '#/components/schemas/ModelInfo'

    ModelInfo:
      type: object
      properties:
        id:
          $ref: '#/components/schemas/WhisperModel'
        name:
          type: string
        size:
          type: string
          description: Human-readable size (e.g., "1.5 GB")
        parameters:
          type: string
          description: Model parameters (e.g., "1550M")
        downloaded:
          type: boolean
        path:
          type: string
        languages:
          type: array
          items:
            type: string
          description: Supported languages (null for multilingual)
        lastUsed:
          type: string
          format: date-time

    ModelStatusResponse:
      type: object
      required: [success, data]
      properties:
        success:
          type: boolean
        data:
          type: object
          properties:
            id:
              $ref: '#/components/schemas/WhisperModel'
            status:
              type: string
              enum: [not_downloaded, downloading, downloaded, loading, loaded, error]
            progress:
              type: number
              format: float
              description: Download progress (0-1)
            error:
              type: string

    DownloadResponse:
      type: object
      required: [success, data]
      properties:
        success:
          type: boolean
        data:
          type: object
          properties:
            id:
              $ref: '#/components/schemas/WhisperModel'
            status:
              type: string
              example: downloading
            estimatedSize:
              type: string

    ConversationListResponse:
      type: object
      required: [success, data]
      properties:
        success:
          type: boolean
        data:
          type: array
          items:
            $ref: '#/components/schemas/ConversationSummary'
        pagination:
          $ref: '#/components/schemas/Pagination'

    ConversationSummary:
      type: object
      properties:
        id:
          type: string
        title:
          type: string
        projectId:
          type: string
        transcriptCount:
          type: integer
        commandCount:
          type: integer
        createdAt:
          type: string
          format: date-time
        lastActivityAt:
          type: string
          format: date-time

    ConversationResponse:
      type: object
      required: [success, data]
      properties:
        success:
          type: boolean
        data:
          type: object
          properties:
            id:
              type: string
            title:
              type: string
            projectId:
              type: string
            transcriptCount:
              type: integer
            commandCount:
              type: integer
            createdAt:
              type: string
              format: date-time
            lastActivityAt:
              type: string
              format: date-time

    TranscriptListResponse:
      type: object
      required: [success, data]
      properties:
        success:
          type: boolean
        data:
          type: array
          items:
            $ref: '#/components/schemas/Transcript'
        pagination:
          $ref: '#/components/schemas/Pagination'

    ConfigResponse:
      type: object
      required: [success, data]
      properties:
        success:
          type: boolean
        data:
          type: object
          properties:
            defaultModel:
              $ref: '#/components/schemas/WhisperModel'
            defaultLanguage:
              type: string
            vadEnabled:
              type: boolean
            vadThreshold:
              type: number
            intentEnabled:
              type: boolean
            llmFallback:
              type: boolean
            llmTimeout:
              type: string
            sampleRate:
              type: integer

    HealthResponse:
      type: object
      properties:
        status:
          type: string
          enum: [healthy, degraded, unhealthy]
        version:
          type: string
        uptime:
          type: integer
          description: Uptime in seconds
        checks:
          type: object
          properties:
            database:
              $ref: '#/components/schemas/HealthCheck'
            whisper:
              $ref: '#/components/schemas/HealthCheck'
            aiBridge:
              $ref: '#/components/schemas/HealthCheck'

    HealthCheck:
      type: object
      properties:
        status:
          type: string
          enum: [pass, warn, fail]
        message:
          type: string
        latency:
          type: integer
          description: Latency in milliseconds

    Pagination:
      type: object
      properties:
        total:
          type: integer
        limit:
          type: integer
        offset:
          type: integer
        hasMore:
          type: boolean

  # ----------------------------------------------------------
  # RESPONSES
  # ----------------------------------------------------------
  responses:
    BadRequest:
      description: Invalid request parameters
      content:
        application/json:
          schema:
            $ref: '#/components/schemas/ErrorResponse'
          example:
            success: false
            error:
              code: 11002
              constant: ERR_AUDIO_FORMAT_INVALID
              message: Unsupported audio format. Expected pcm16, got mp3.
              retryable: false

    NotFound:
      description: Resource not found
      content:
        application/json:
          schema:
            $ref: '#/components/schemas/ErrorResponse'
          example:
            success: false
            error:
              code: 11050
              constant: ERR_SESSION_NOT_FOUND
              message: Session 'sess_abc123' does not exist
              retryable: false

    TooManyRequests:
      description: Rate limit exceeded
      content:
        application/json:
          schema:
            $ref: '#/components/schemas/ErrorResponse'
          example:
            success: false
            error:
              code: 11052
              constant: ERR_SESSION_LIMIT_EXCEEDED
              message: Maximum concurrent sessions (100) exceeded
              retryable: true

    InternalError:
      description: Internal server error
      content:
        application/json:
          schema:
            $ref: '#/components/schemas/ErrorResponse'
          example:
            success: false
            error:
              code: 11022
              constant: ERR_WHISPER_TRANSCRIBE_FAILED
              message: Transcription failed due to internal error
              retryable: true
              stack:
                - "voice-cli/pkg/voice/whisper.go:142 (*WhisperEngine).Transcribe"
                - "voice-cli/internal/handlers/transcribe.go:87 (*Handler).Handle"

  # ----------------------------------------------------------
  # SECURITY
  # ----------------------------------------------------------
  securitySchemes:
    BearerAuth:
      type: http
      scheme: bearer
      bearerFormat: JWT
      description: JWT token from Gateway authentication

    ApiKeyAuth:
      type: apiKey
      in: header
      name: X-API-Key
      description: API key for service-to-service communication

security:
  - BearerAuth: []
  - ApiKeyAuth: []
```

---

## WebSocket Protocol Specification

### Connection

```
Endpoint: ws://localhost:8086/ws/stream
         wss://voice-cli.example.com/ws/stream

Headers:
  Authorization: Bearer <jwt_token>
  X-Project-ID: <project_id> (optional)
```

### Message Format

All WebSocket messages use JSON format:

```json
{
  "type": "<message_type>",
  "id": "<optional_message_id>",
  "payload": { ... }
}
```

### Client → Server Messages

| Type | Description | Payload |
|------|-------------|---------|
| `audio` | Audio chunk | `{ data: string, timestamp: number, final?: boolean }` |
| `config` | Session configuration | `SessionConfig` object |
| `control` | Control commands | `{ action: "start" \| "stop" \| "pause" \| "resume" \| "cancel" }` |
| `text` | Text input (bypass audio) | `{ text: string }` |

#### Audio Message

```json
{
  "type": "audio",
  "id": "chunk_001",
  "payload": {
    "data": "base64_encoded_pcm16_audio",
    "timestamp": 1706644800000,
    "final": false
  }
}
```

#### Config Message

```json
{
  "type": "config",
  "payload": {
    "audioFormat": "pcm16",
    "sampleRate": 16000,
    "vadEnabled": true,
    "vadThreshold": 0.5,
    "whisperModel": "large-v3",
    "language": "auto",
    "intentEnabled": true,
    "llmFallback": true
  }
}
```

#### Control Message

```json
{
  "type": "control",
  "payload": {
    "action": "start"
  }
}
```

### Server → Client Messages

| Type | Description | Payload |
|------|-------------|---------|
| `status` | Session status | `{ sessionId, status, config }` |
| `partial` | Partial transcript | `{ text: string }` |
| `transcript` | Final transcript | `Transcript` object |
| `intent` | Recognized intent | `IntentMatch` object |
| `command` | Command result | `CommandResult` object |
| `vad` | VAD state change | `{ state: VADState, probability: number }` |
| `level` | Audio level | `{ level: number }` |
| `error` | Error message | `ErrorDetail` object |

#### Partial Transcript

```json
{
  "type": "partial",
  "payload": {
    "text": "create a new feature"
  }
}
```

#### Final Transcript

```json
{
  "type": "transcript",
  "payload": {
    "id": "tr_abc123",
    "text": "create a new feature spec for authentication",
    "isFinal": true,
    "language": "en",
    "confidence": 0.94,
    "words": [
      { "word": "create", "start": 0, "end": 250, "probability": 0.98 },
      { "word": "a", "start": 260, "end": 320, "probability": 0.99 }
    ],
    "durationMs": 2340
  }
}
```

#### Intent Recognition

```json
{
  "type": "intent",
  "payload": {
    "intent": "CREATE_SPEC",
    "confidence": 0.92,
    "slots": {
      "title": { "raw": "authentication", "normalized": "authentication", "type": "text", "confidence": 0.95 },
      "type": { "raw": "feature", "normalized": "feature", "type": "enum", "confidence": 0.98 }
    },
    "rawText": "create a new feature spec for authentication",
    "method": "grammar"
  }
}
```

#### Command Result

```json
{
  "type": "command",
  "payload": {
    "intent": "CREATE_SPEC",
    "success": true,
    "message": "Created spec 'Authentication'",
    "data": {
      "id": "spec_xyz789",
      "title": "Authentication",
      "type": "feature"
    },
    "speakText": "I've created a new feature spec called Authentication",
    "actions": [
      { "label": "Open spec", "intent": "READ_SPEC", "slots": { "id": "spec_xyz789" } },
      { "label": "Add stages", "intent": "CREATE_STAGE", "slots": { "specId": "spec_xyz789" } }
    ]
  }
}
```

#### VAD State

```json
{
  "type": "vad",
  "payload": {
    "state": "speech_start",
    "probability": 0.87
  }
}
```

#### Audio Level

```json
{
  "type": "level",
  "payload": {
    "level": 0.42
  }
}
```

#### Error

```json
{
  "type": "error",
  "payload": {
    "code": 11022,
    "constant": "ERR_WHISPER_TRANSCRIBE_FAILED",
    "message": "Transcription failed: model not loaded",
    "retryable": true
  }
}
```

### Connection Lifecycle

```
┌────────┐                          ┌────────┐
│ Client │                          │ Server │
└───┬────┘                          └───┬────┘
    │                                   │
    │──── WebSocket Connect ───────────>│
    │                                   │
    │<─── status (session created) ─────│
    │                                   │
    │──── config (session settings) ───>│
    │                                   │
    │<─── status (config applied) ──────│
    │                                   │
    │──── control (start) ─────────────>│
    │                                   │
    │──── audio (chunks) ──────────────>│
    │<─── level ────────────────────────│
    │<─── vad (speech_start) ───────────│
    │──── audio (chunks) ──────────────>│
    │<─── partial ──────────────────────│
    │──── audio (chunks) ──────────────>│
    │<─── partial ──────────────────────│
    │<─── vad (speech_end) ─────────────│
    │                                   │
    │<─── transcript ───────────────────│
    │<─── intent ───────────────────────│
    │<─── command ──────────────────────│
    │                                   │
    │──── control (stop) ──────────────>│
    │                                   │
    │<─── status (session closed) ──────│
    │                                   │
    │──── WebSocket Close ─────────────>│
    │                                   │
```

---

## Error Codes Reference

| Code | Constant | Description |
|------|----------|-------------|
| 11001 | `ERR_AUDIO_CAPTURE_FAILED` | Failed to capture audio |
| 11002 | `ERR_AUDIO_FORMAT_INVALID` | Unsupported audio format |
| 11003 | `ERR_AUDIO_DECODE_FAILED` | Failed to decode audio data |
| 11010 | `ERR_VAD_INIT_FAILED` | VAD initialization error |
| 11011 | `ERR_VAD_PROCESS_ERROR` | VAD processing failed |
| 11020 | `ERR_WHISPER_MODEL_NOT_FOUND` | Whisper model not available |
| 11021 | `ERR_WHISPER_LOAD_FAILED` | Failed to load Whisper model |
| 11022 | `ERR_WHISPER_TRANSCRIBE_FAILED` | Transcription error |
| 11023 | `ERR_WHISPER_TIMEOUT` | Transcription timeout |
| 11030 | `ERR_INTENT_PARSE_FAILED` | Failed to parse intent |
| 11031 | `ERR_INTENT_NOT_RECOGNIZED` | No matching intent found |
| 11032 | `ERR_INTENT_SLOT_MISSING` | Required slot not provided |
| 11040 | `ERR_COMMAND_HANDLER_MISSING` | No handler for intent |
| 11041 | `ERR_COMMAND_EXEC_FAILED` | Command execution failed |
| 11050 | `ERR_SESSION_NOT_FOUND` | Session does not exist |
| 11051 | `ERR_SESSION_EXPIRED` | Session has expired |
| 11052 | `ERR_SESSION_LIMIT_EXCEEDED` | Too many active sessions |
| 11060 | `ERR_WS_CONNECTION_FAILED` | WebSocket connection error |
| 11061 | `ERR_WS_MESSAGE_INVALID` | Invalid WebSocket message |
| 11070 | `ERR_LLM_FALLBACK_FAILED` | LLM intent recognition failed |
| 11071 | `ERR_LLM_TIMEOUT` | LLM request timed out |

---

## Related Specifications

- [Voice-CLI Service](./15-voice-cli-service.md) — Service implementation
- [Gateway OpenAPI](./09-gateway-openapi.md) — Gateway routing
- [AI-Bridge Service](./08-ai-bridge-service.md) — LLM integration
- [Shared Pkg Modules](./02-shared-pkg.md) — Error handling patterns
