# Personal data and third-party processing checklist

**Read first.** Stage 1 uses **fictional data only**. This document is a checklist, **not legal advice**. The project makes **no claim of compliance** with the Malaysia Personal Data Protection Act 2010 (PDPA) or any other law. This document must be completed and the actions in section 7 done **before any real data is processed**.

## 1. Personal data the application processes
| Data | Where | Notes |
|---|---|---|
| User names, emails, password hashes | `users` | Passwords hashed (bcrypt); hashes are never sent to the browser or the AI provider |
| Staff voice recordings | private disk, `meetings.recording_path` | Needs participant consent; see section 4 |
| Transcripts with staff names and statements | `meetings` | Untrusted text; may contain personal statements |
| Task assignments, comments | `tasks`, `task_comments` | Linked to named staff |
| Activity logs and notifications | `activity_logs`, `notifications` | Append-only history of who did what |

## 2. Data sent to the third-party AI provider (per feature)
| Feature | Data sent |
|---|---|
| Intelligence brief | Structured verified metrics and issue evidence (no emails, no passwords) |
| Transcription | The audio file of the recording |
| Task extraction | Transcript text, meeting date, company timezone, display names and ids of the meeting's department staff |
| Voice brief (speech) | The brief text |

Source: `docs/architecture.md` section 6. API keys stay on the server (`.env`) and are never stored in the database or sent to the browser.

## 3. Principle checklist
Status values: `open`, `answered`, `needs client decision`.

| Principle | Question | Current Stage 1 behavior | Evidence in the application | Status |
|---|---|---|---|---|
| General | Is a lawful purpose and a data owner defined? | Fictional demo data only | `docs/stage-1-scope.md` | needs client decision |
| Notice and choice | Are participants told they are recorded and that audio goes to an AI provider? | Uploader must tick a consent statement per meeting; no participant notice template exists | `recording_consent` required in `UploadRecordingRequest` (S06) | open |
| Disclosure | Who receives the data? | Only the AI provider (section 2) and users within their role and department scope | Policies and scoping (S02 to S08) | needs client decision |
| Security | Are access, storage and logs protected? | Role and department scoping, 404 for out-of-scope records, private storage, authorized streaming, secrets only in `.env`, throttling | `docs/security-verification.md` (manual run pending), feature tests | open until the matrix is executed |
| Retention | How long are recordings and transcripts kept? | Default 30 days for recordings; daily `recordings:purge` and Owner deletion are built (manual verification pending) | `docs/retention-and-deletion.md`, RF-02 | open (blocker for real data) |
| Data integrity | Can data be corrected? | Sales corrections supersede, transcripts can be edited before extraction; history is not deleted | S03, S06 | answered for sales and transcripts |
| Access | Can a person ask what is held about them? | No access or correction procedure exists | none | open |
| Cross-border transfer | Is data sent outside Malaysia? | Yes, to the AI provider when live AI is used; region not assessed | none | needs client decision |

## 4. In-application controls that exist (with owning spec)
- Recording consent checkbox required before upload and stored per meeting (S06 upload requirements).
- Private storage with random file names; recordings and brief audio served only through authorized streaming routes (S05, S06).
- Role-based access with department scoping and policies on every action (S02 and later specs).
- Log redaction: provider errors are sanitized before they are stored or logged (S05, S06).
- Retention command `recordings:purge` (daily, 02:00) and Owner early deletion of a recording (S06 requirement 15). Built and covered by automated tests; run the manual verification in `docs/retention-and-deletion.md` before relying on them for real data.

## 5. Open client decisions (`docs/stage-1-scope.md` section 8)
Recording consent policy; data residency and the position on sending data to a third-party AI provider; retention periods; languages used in meetings; the user roster and role mapping.

## 6. Provider terms
Whether the provider keeps or trains on submitted data, its retention period and its regional processing options must be **confirmed from the provider's current terms and account settings**. This document states no provider claim because none has been verified.

## 7. Actions needed before real data is processed
1. A data processing agreement with the AI provider.
2. A recording notice for meeting participants.
3. A retention decision from the client, and the manual verification of the retention purge and Owner deletion (RF-02).
4. An access and correction procedure for personal data.
5. A named breach response contact and procedure.
6. A cross-border transfer assessment.
7. Execution of `docs/security-verification.md` with all cells PASS.
8. Client confirmation of the open decisions in section 5.
