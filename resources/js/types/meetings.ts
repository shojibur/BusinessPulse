import type { Pagination } from './imports';

export type MeetingStatusValue =
    | 'draft'
    | 'uploaded'
    | 'transcribing'
    | 'transcribed'
    | 'extracting'
    | 'ready_for_review'
    | 'failed';

export type MeetingListItem = {
    id: number;
    title: string;
    meetingDate: string;
    department: { id: number; name: string };
    status: MeetingStatusValue;
    statusLabel: string;
    issueCount: number;
    pendingDraftCount: number;
    url: string;
};

export type MeetingFormMeeting = {
    id: number;
    title: string;
    meetingDate: string;
    departmentId: number;
    issueIds: number[];
    dateLocked: boolean;
    hasDrafts: boolean;
};

export type IssueOption = { id: number; title: string; departmentId?: number };

export type MeetingDetail = {
    id: number;
    title: string;
    meetingDate: string;
    department: { id: number; name: string };
    createdBy: string | null;
    status: MeetingStatusValue;
    statusLabel: string;
    failureStage: 'transcription' | 'extraction' | null;
    errorCode: string | null;
    errorMessage: string | null;
    hasTranscript: boolean;
    transcriptSource: 'audio' | 'manual' | null;
    transcriptEditedAt: string | null;
    transcriptChangedSinceExtraction: boolean;
    extractionRun: number;
    discardedCount: number;
    discardedNotice: string | null;
    recording: {
        present: boolean;
        originalName: string | null;
        mime: string | null;
        sizeBytes: number | null;
        uploadedAt: string | null;
        consentAt: string | null;
        consentBy: string | null;
        deletedAt: string | null;
        streamUrl: string | null;
        downloadUrl: string | null;
    };
    linkedIssues: { id: number; title: string; url: string }[];
    decisions: { summary: string; excerpt: string }[];
    draftCounts: {
        pending: number;
        approved: number;
        rejected: number;
        superseded: number;
    };
    retentionDays: number;
    can: {
        update: boolean;
        uploadRecording: boolean;
        enterTranscript: boolean;
        editTranscript: boolean;
        retry: boolean;
        reExtract: boolean;
        deleteRecording: boolean;
    };
    polling: boolean;
};

export type TaskDraftSummary = {
    id: number;
    title: string;
    description: string;
    excerpt: string;
    assignee: {
        resolution: 'resolved' | 'ambiguous' | 'unmatched' | 'none';
        name: string | null;
        suggestedName: string | null;
    };
    deadline: {
        resolution: 'resolved' | 'ambiguous' | 'none';
        date: string | null;
        suggestedText: string | null;
    };
    status: 'pending' | 'approved' | 'rejected';
};

export type MeetingLimits = {
    maxUploadMb: number;
    extensions: string[];
    intendedMinutes: number;
    transcriptMin: number;
    transcriptMax: number;
};

export type IssueMeetings = {
    items: {
        id: number;
        title: string;
        meetingDate: string;
        status: MeetingStatusValue;
        statusLabel: string;
        url: string;
    }[];
    createUrl: string;
};

export type { Pagination };
