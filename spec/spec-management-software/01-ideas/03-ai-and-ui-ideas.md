## Original input (verbatim)

So far, the ideas that we have, read the ideas, read the spec, and try to answer me and try to improve the spec. Uh, so first try to answer me and read it very carefully. That, do we have in this spec that the voice model, uh, names and how the mo- voice is going to be picked? So inside the application, we're going to actually select, uh, the, the models to run with the LLM server, uh, the executable file server. So the, uh, shell command to run this should be also coming from the config.json file as a seeding value. So seeding value needs to be mentioned very carefully, which are the seeding values. So here, the principle we'll applied is from the, uh, spec, general spec that you will understand and also check the, uh, WordPress plugin manager... uh, WordPress plugin inside the exam manager, we have this type of things, like how the seeding will work. So every time I mention about the seeding, try to follow this. So update this about the seeding and, and try to update the, uh, update the information. So, uh, when I see the screen for the first time, I should have the project names as a card. And from these project names, um, from these project names, uh, we can edit these or also add new project. Uh, when we do add new project, it could start from a preset. Uh, usually, it can start from the general spec, or probably we could have different, different preset, uh, that it could start from. So we will have some tiny, tiny bits of elements, like common elements, like the coding guideline, file formatting guideline, language-specific guideline, coding, uh, error management guideline for a specific language, uh, guideline, things like that. So think about this mindset that should be in the spec manager, spec management software, so that it can handle this type of things. First generalize and then specific. First generalize, and then app-specific, language-specific, app-specific, personalized-specific. Try to create, uh, organizing or grouping, how it's going to be applied. I give the window to you to decide. It needs to be very good in terms of the back-end design and also the front-end design. So when we, um, log in, um, I mean, when we-- after the login, uh, we should see the recent, uh, applications, uh, or the specs, and it should also synchronize from the folder system that what is exist there. So it should read and say, like, "These are the folder spec I read. I should import this. I think of this as this and that," and things like that. Every spec should have a JSON file that tells, like, uh, information about the spec project, uh, the project name, um, version, a little bit of summary, who is the author, who is the designer, uh, who is the responsible person, email address, and so on. So these are the fields also we need in the database system as well. And, and when the, uh, user actually, uh, edits the... go into the edit or new mode. Le- let's go into the edit mode. So we already have the spec files, right? We should see the folder in the folder tree in the left-hand side. So we should be able to move this folder, edit these folders, edit the files, and when we click on the file, we should have a voice, uh, let's say, icon, where we could just give the voice. And the voice that we give, that actually goes as, uh, a prompt. So inside the folder, we can have the prompt folder that will go as the prompts, and each time the prompt is created, it will also save into the file system. So that prompt will go into another layer. That means it will proofread, it will create some, uh, let's say, plan first, like you, how you do it. Think about it, long chain commands. Uh, based on that, so it's going to use the reasoning model to do that. It will create task, child task, and so on. So that needs to be mentioned very carefully. So inside the child task, make sure that the child tasks are very well written, so that the AI can execute in the next level. So based on the instructions given, uh, the prompts, you can write as an instruction. I think instructions would be the right term. Um, so how it's going to be saved is, uh, there would be, uh, instructions folder, and inside the instructions folder, it will name the folder first, um, and the front end or back end, and then also against which file I'm giving the prompt. It could be based on which file, or it could be global. So if it is global, then in the instruction, it will just put in that, that, that level. But if it is, uh, if I'm actually inside a file and then give the prompt, then it would be for that file. But it's not like that they cannot modify the other files as well. So when the file actually modified by the AI, based on the prompt, it should also keep the history of, for this instruction, we have modified this, this, this file. So the history needs to be very clear. So far, what I have shared, do you have any question and confusion? If you have, please share.

---

## Proofread prompt

Update and improve the existing Spec Management Software specification by explicitly covering model selection, seeding values, presets, project metadata, voice driven instruction capture, and instruction to change tracking. Do not write code. Only update the specification content, UI requirements, data model tables, and acceptance criteria.

1. Read and reconcile existing documents

1) Read the current idea files and spec files.
2) Identify gaps related to AI model selection, seeding, presets, project metadata, and instruction history.
3) Update the specs to remove ambiguity and ensure all workflows are implementable.

2. AI model selection and seeding (must be explicit)
   Goal: The spec must clearly define voice model names, how they are selected, and how LLM server execution is configured.

2.1 Seeding values (config.json)

1. Add a section titled Seeding values and defaults.
2. Define which values are seeded from config.json on first run and then persisted to SQLite.
3. Explicitly list at minimum:

   1. llamaServerExecutablePath
   2. llamaServerShellCommandTemplate
   3. llamaServerWorkingDirectory
   4. modelRoots (one or more folders where models live)
   5. defaultReasoningModelId
   6. defaultVoiceModelId
   7. modelRegistryRefreshMode (manual, onStartup, scheduled)
   8. maxConcurrentModelRequests
   9. optional feature flags (analytics optional, ipValidation optional)
4. Define the rule:

   1. First run seeds config.json into DB
   2. After seeding, DB values are the source of truth
   3. UI can modify values, and DB updates are persisted
   4. config.json remains the bootstrap seed only unless explicitly re-seeded by an admin action

2.2 Model registry and selection

1. The app must present a model selection UI for:

   1. reasoning model
   2. voice model
2. Define model identity fields that must be stored in DB:

   1. modelId
   2. displayName
   3. modelType (reasoning, voice)
   4. modelPath
   5. tags (optional)
   6. isEnabled
3. Define how voice model names appear in UI and how a model is picked:

   1. per user default
   2. per project default
   3. per instruction override when launching a run
4. Define how the LLM server is started:

   1. shell command comes from the seeded command template
   2. model selection results in resolved command arguments
   3. the spec must define failure behavior when the server is not running

2.3 Seeding principle alignment
When referencing seeding, follow the same principle used in the WordPress plugin manager spec inside the exam manager:

1. seed on first initialization
2. persist seeded values to DB
3. allow admin UI edits after seed
4. log seed events and configuration changes

3) Project dashboard, presets, and organization model
   3.1 First screen and project cards

1. After login, show a dashboard with project cards.
2. Each card shows:

   1. projectName
   2. projectVersion
   3. category path (if any)
   4. lastUpdatedAt
   5. summary
3. Actions on cards:

   1. open project
   2. edit metadata
   3. create new project
   4. delete project with confirmation

3.2 New project from preset

1. Creating a new project can start from a preset.
2. Presets include:

   1. general spec baseline
   2. language specific baseline (for example Go, TypeScript)
   3. application type baseline (WordPress plugin, backend API, full stack)
   4. personalization layer
3. Define a layered guideline system:

   1. global guidelines (applies to all projects)
   2. category guidelines (applies to projects under a category)
   3. language guidelines (applies to projects using a language)
   4. project specific guidelines (applies only to the project)
4. Examples of guideline modules to support:

   1. coding guidelines
   2. file formatting guidelines
   3. language specific error handling guidelines
   4. structured logging guidelines
   5. review and acceptance criteria templates

4) Folder sync and import workflow (post login)
   Goal: After login, the app must reconcile filesystem Spec folders with DB state.

1. On startup or post login, scan the Spec folder.
2. Show a sync screen or banner that explains:

   1. folders detected
   2. projects inferred
   3. categories inferred
   4. proposed imports or updates
3. Provide actions:

   1. import all
   2. review and import
   3. ignore specific folders
4. Define how it decides:

   1. what is a project root
   2. what is a category
   3. how nested categories are handled

5) Per project metadata json file
   Goal: Every spec project has a JSON metadata file that is also reflected in DB.

1. Require a project metadata JSON file inside each project root, for example:

   1. spec.project.json
2. Fields required:

   1. projectName
   2. projectSlug
   3. version
   4. summary
   5. authorName
   6. designerName
   7. responsiblePersonName
   8. responsiblePersonEmail
   9. createdAt
   10. updatedAt
3. Define mapping:

   1. DB stores these fields
   2. filesystem JSON is the on disk representation
   3. edits in UI update both DB and the JSON file

6) Editor mode UI and voice to instruction workflow
   6.1 Editor layout

1. Left side: folder tree for the project Spec structure.
2. Main panel: Markdown editor for the selected file.
3. File operations supported:

   1. rename file
   2. move file or folder
   3. create file
   4. delete file
4. Each file view includes a voice icon to create instructions from voice.

6.2 Prompts vs instructions
Use the term instructions for saved prompt artifacts.

Instruction capture flow:

1. User clicks voice icon.
2. User records voice.
3. Voice model converts voice to text.
4. Reasoning model:

   1. proofreads the text
   2. creates a plan
   3. creates tasks and child tasks using long chain commands
   4. outputs an instruction artifact suitable for execution
5. The instruction artifact is saved to the filesystem and recorded in DB.
6. The instruction can be global or file scoped, but it may modify multiple files.

6.3 Instruction storage structure
Define a deterministic structure under each project, for example:

1. instructions/

   1. global/
   2. backend/
   3. frontend/
   4. fileScoped/

      1. <relativeFilePathSanitized>/
         Each instruction record includes:
2. instructionId
3. createdAt
4. createdByUserId
5. scope (global, category, project, file)
6. targetFilePath (nullable)
7. instructionText
8. derivedTasks (stored as structured text or JSON)
9. status (draft, approved, executed, failed)

7) Instruction to change tracking and history clarity
   Goal: Whenever AI modifies files based on an instruction, history must show exactly what changed.

1. For each instruction execution, record:

   1. which files were modified
   2. which files were created
   3. which files were deleted
   4. before and after hashes or references to snapshot ids
2. Update history mechanisms:

   1. .history snapshots continue to exist as full snapshots
   2. add a DB level audit trail mapping instructionId to:

      1. snapshotId created before execution
      2. snapshotId created after execution
      3. gitCommitId created as part of the execution
3. UI history view must support:

   1. viewing instruction history
   2. seeing impacted files per instruction
   3. navigating from an instruction to relevant snapshots and git commits

8) Data model updates (Markdown tables only)
   Update the data model spec to include or extend tables for:

1. users
2. userSessions
3. modelRegistry
4. projectSettings (defaults for models per project)
5. presets
6. guidelines (global, category, language, project scoped)
7. projectMetadata
8. instructions
9. instructionTasks
10. instructionFileImpacts
11. configSeedEvents
    Ensure each table is defined using:
12. camelCase fields
13. types
14. required flags
15. defaults
16. notes
    Include primary keys, foreign keys, and relationship notes.

9) Acceptance criteria updates
   Add acceptance criteria checklists for:

1. model selection UI and persistence
2. seeding behavior and DB source of truth
3. project creation from presets
4. folder sync and import flow
5. project metadata JSON round trip correctness
6. voice to instruction pipeline behavior
7. instruction task breakdown quality gates
8. instruction to file impact history correctness

10) Assumptions and open questions
    At the end, list only the remaining unknowns. Do not re ask items already decided. If anything is ambiguous, state it as an open question with options, including:

1. whether the LLM server is started per project, per user, or globally
2. whether instruction execution is automatic or requires approval
3. whether instruction artifacts are stored as Markdown, JSON, or both
