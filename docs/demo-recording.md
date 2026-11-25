# Producing the demo recording

The recording is fictional: nobody real is recorded. Source text: `database/demo/meeting-transcript.txt` (the Sales recovery huddle of 28 Sep 2026, speakers Aisyah, Priya, Farid, Daniel, Wei Han, the two decisions, the four task lines and the injection line).

**Method 1, volunteers:** read the transcript aloud in a quiet room, clear voices, state at the start "I agree to be recorded for this demonstration". **Method 2, synthetic voice:** generate speech from the transcript with the speech model (`AI_SPEECH_MODEL`) or a text-to-speech tool.

**Requirements:** `database/demo/meeting-recording.mp3`, mono, English, at most 3 minutes and 5 MB (the application limit is 20 MB), all eight content items of `docs/demo-scenario.md` section 4, the injection line included.

**Commit rule:** commit the file only when it is at most 2 MB. Otherwise keep it out of Git (`.gitignore` already ignores `database/demo/meeting-recording.*`), record its SHA-256 and where the original is stored in `database/demo/README.md`. Use fictional content only.

**Fallback:** if the recording or the provider is not available, paste the transcript manually (walkthrough steps 10 to 12). Status: the recording is not produced yet (RF-04).
