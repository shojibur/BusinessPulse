<?php

namespace App\Services\AI;

use App\Enums\AssigneeResolution;
use App\Enums\DeadlineResolution;
use App\Enums\MeetingErrorCode;
use App\Models\Meeting;
use App\Models\TaskDraft;
use App\Models\User;
use App\Services\AI\Exceptions\ProviderException;
use App\Services\AI\Exceptions\RecoverableProviderException;
use App\Services\Meetings\AssigneeResolver;
use App\Services\Meetings\AssigneeResult;
use App\Services\Meetings\DeadlineResolver;
use App\Services\Meetings\DeadlineResult;
use App\Services\Meetings\ExcerptVerifier;
use App\Services\Meetings\MeetingStageException;
use App\Services\Meetings\TextNormalizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

/**
 * Extracts decisions and proposed task drafts. The provider's output is data: validated against a schema, every excerpt
 * verified against the real transcript, and every assignee and deadline decided by deterministic resolvers that
 * ignore the model's ids and dates. Fabricated, unverifiable or injected items are discarded and counted.
 */
final class MeetingTaskExtractor
{
    private const REQUEST_TIMEOUT = 60;

    /** Total wording budget of one job attempt, below the 90 s job timeout. */
    private const BUDGET_SECONDS = 80;

    public function __construct(
        private readonly OpenAiClient $client,
        private readonly ExcerptVerifier $excerpts,
        private readonly AssigneeResolver $assignees,
        private readonly DeadlineResolver $deadlines,
        private readonly TextNormalizer $text,
    ) {}

    /**
     * @param  Collection<int, User>  $allowedStaff
     * @param  Collection<int, TaskDraft>  $approvedDrafts
     *
     * @throws RecoverableProviderException
     * @throws MeetingStageException
     */
    public function extract(Meeting $meeting, Collection $allowedStaff, Collection $approvedDrafts): ExtractionResult
    {
        if (! OpenAiClient::textConfigured()) {
            throw new MeetingStageException(MeetingErrorCode::ProviderNotConfigured); // no call is attempted
        }

        $transcript = $this->text->nfc((string) $meeting->transcript);

        if (mb_strlen(trim($transcript)) < 20) {
            throw new MeetingStageException(MeetingErrorCode::EmptyTranscript);
        }

        $payload = $this->requestValidated($meeting, $transcript, $allowedStaff);
        $company = $meeting->department->company;

        $discarded = 0;
        $decisions = [];
        foreach (array_slice($payload['decisions'], 0, 10) as $decision) {
            $summary = trim($decision['summary']);
            $excerpt = $this->excerpts->verify($transcript, $decision['excerpt']);

            if ($summary === '' || $excerpt === null) {
                $discarded++;

                continue;
            }

            $decisions[] = ['summary' => $summary, 'excerpt' => $excerpt];
        }

        $drafts = [];
        foreach (array_slice($payload['task_drafts'], 0, 20) as $task) {
            $title = trim($task['title']);
            $excerpt = $this->excerpts->verify($transcript, $task['excerpt']);

            if ($title === '' || $excerpt === null) {
                $discarded++;

                continue;
            }

            // The model's assignee_id is never an input: identity comes from name matching in the meeting's department.
            // A suggestion counts only when its own words appear in the supporting quote. A name or deadline the quote does
            // not contain is unsupported (possibly fabricated or injected) and needs a person, even if it matches a staff
            // member or looks like a date phrase.
            $name = $task['assignee_name'] ?? null;
            $phrase = $task['deadline_phrase'] ?? null;
            $assignee = $this->assignees->resolve($this->supported($name, $excerpt) ? $name : null, $allowedStaff);
            $deadline = $this->deadlines->resolve($this->supported($phrase, $excerpt) ? $phrase : null, $meeting->meeting_date, $company);

            if (! $this->isBlank($name) && ! $this->supported($name, $excerpt)) {
                $assignee = new AssigneeResult(AssigneeResolution::Unmatched, null);
            }

            if (! $this->isBlank($phrase) && ! $this->supported($phrase, $excerpt)) {
                $deadline = new DeadlineResult(DeadlineResolution::Ambiguous, null);
            }

            $duplicatesApproved = $assignee->userId !== null && $approvedDrafts->contains(
                fn ($approved) => $approved->assignee_id === $assignee->userId
                    && $this->text->normalize($approved->suggested_title) === $this->text->normalize($title),
            );

            if ($duplicatesApproved) {
                $discarded++;

                continue;
            }

            $drafts[] = new DraftCandidate(
                $title,
                trim((string) ($task['description'] ?? '')),
                isset($task['assignee_name']) ? (string) $task['assignee_name'] : null,
                $assignee,
                isset($task['deadline_phrase']) ? (string) $task['deadline_phrase'] : null,
                $deadline,
                $excerpt,
            );
        }

        return new ExtractionResult($decisions, $drafts, $discarded);
    }

    private function isBlank(?string $value): bool
    {
        return $value === null || trim($value) === '';
    }

    /** Whether the suggested words occur in the quote as whole words (normalized text). Blank is trivially supported. */
    private function supported(?string $words, string $excerpt): bool
    {
        if ($this->isBlank($words)) {
            return true;
        }

        $needle = $this->text->normalize((string) $words);

        return preg_match('/(?<![\p{L}\p{N}])'.preg_quote($needle, '/').'(?![\p{L}\p{N}])/u', $this->text->normalize($excerpt)) === 1;
    }

    /**
     * At most two requests inside one job attempt: the first, and one corrective request while the budget allows.
     *
     * @param  Collection<int, User>  $staff
     * @return array{decisions: list<array<string, mixed>>, task_drafts: list<array<string, mixed>>}
     */
    private function requestValidated(Meeting $meeting, string $transcript, Collection $staff): array
    {
        $startedAt = hrtime(true);
        $correction = null;

        foreach ([1, 2] as $request) {
            $timeout = self::REQUEST_TIMEOUT;

            if ($request === 2) {
                $remaining = self::BUDGET_SECONDS - (hrtime(true) - $startedAt) / 1e9;

                if ($remaining < 10) {
                    break;
                }

                $timeout = (int) min(self::REQUEST_TIMEOUT, floor($remaining - 5));
            }

            try {
                $response = $this->client->chat(ExtractionPrompt::build($meeting, $transcript, $staff, $correction, $timeout));
            } catch (RecoverableProviderException $e) {
                throw $e;
            } catch (ProviderException $e) {
                throw new MeetingStageException(MeetingErrorCode::fromProvider($e->errorCode()));
            }

            $data = $response->data;

            if ($data === []) {
                $correction = 'Your previous reply was not valid JSON. Return JSON that matches the schema only.';

                continue;
            }

            $data['decisions'] = array_slice((array) ($data['decisions'] ?? []), 0, 10);
            $data['task_drafts'] = array_slice((array) ($data['task_drafts'] ?? []), 0, 20);

            $validator = Validator::make($data, [
                'decisions' => ['present', 'array'],
                'decisions.*.summary' => ['required', 'string', 'max:300'],
                'decisions.*.excerpt' => ['required', 'string'],
                'task_drafts' => ['present', 'array'],
                'task_drafts.*.title' => ['required', 'string', 'max:150'],
                'task_drafts.*.description' => ['nullable', 'string', 'max:1000'],
                'task_drafts.*.assignee_name' => ['nullable', 'string', 'max:150'],
                'task_drafts.*.assignee_id' => ['nullable', 'integer'],
                'task_drafts.*.deadline_phrase' => ['nullable', 'string', 'max:255'],
                'task_drafts.*.excerpt' => ['required', 'string'],
            ]);

            if ($validator->passes()) {
                $valid = $validator->validated(); // unknown provider fields are never read

                return ['decisions' => array_values($valid['decisions']), 'task_drafts' => array_values($valid['task_drafts'])];
            }

            // Field paths only, never values.
            $correction = 'Your previous reply failed these checks: '.implode(', ', array_keys($validator->errors()->toArray())).'. Return corrected JSON only.';
        }

        throw new MeetingStageException(MeetingErrorCode::InvalidAiOutput);
    }
}
