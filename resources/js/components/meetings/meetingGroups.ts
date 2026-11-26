import type { MeetingStatusValue } from '@/types';

export type MeetingGroup = 'upcoming' | 'in_progress' | 'completed';

export const groupLabels: Record<MeetingGroup, string> = {
    upcoming: 'Upcoming',
    in_progress: 'In progress',
    completed: 'Completed',
};

/**
 * Which tab a meeting belongs to: dated after today is upcoming; fully extracted is completed; everything else is
 * still being worked on (draft, processing, failed).
 */
export function groupOf(
    meeting: { meetingDate: string; status: MeetingStatusValue },
    businessToday: string,
): MeetingGroup {
    if (meeting.meetingDate > businessToday) {
        return 'upcoming';
    }

    return meeting.status === 'ready_for_review' ? 'completed' : 'in_progress';
}

export const groupOrder: MeetingGroup[] = [
    'upcoming',
    'in_progress',
    'completed',
];
