export type ImportStatusValue =
    | 'uploaded'
    | 'validated'
    | 'processing'
    | 'completed'
    | 'completed_with_errors'
    | 'failed'
    | 'cancelled';

export type ImportSummary = {
    id: number;
    status: ImportStatusValue;
    status_label: string;
    original_filename: string;
    min_date: string | null;
    max_date: string | null;
    total_rows: number;
    invalid_rows: number;
    uploader: string | null;
    /** ISO 8601 in the company timezone. */
    uploaded_at: string | null;
};

export type ImportDetail = ImportSummary & {
    file_size: number | null;
    file_hash: string | null;
    valid_rows: number;
    inserted_rows: number;
    corrected_rows: number;
    unchanged_rows: number;
    confirmed_at: string | null;
    completed_at: string | null;
    error_message: string | null;
};

export type Pagination = {
    current_page: number;
    last_page: number;
    total: number;
    prev_url: string | null;
    next_url: string | null;
};

export type Freshness =
    | { configured: false }
    | {
          configured: true;
          state: 'current' | 'behind';
          latest_sales_date: string | null;
          last_due_date: string;
          overdue_branch_days: number | null;
          overdue_from: string | null;
          overdue_to: string | null;
      };

export type CoverageGap = {
    branch_code: string;
    date: string;
    kind: 'inside_period' | 'after_latest';
};

export type WeekStateView = {
    week_start: string;
    week_end: string;
    status: 'open' | 'closed';
    completeness: 'complete' | 'incomplete' | 'absent' | null;
    expected_branch_days: number;
    present_branch_days: number;
    due_branch_days: number;
    coverage_pct: string | null;
    gaps: CoverageGap[];
    more_gaps: number;
};

export type ImportPreview = {
    counts: {
        total: number;
        valid: number;
        invalid: number;
        new: number;
        corrected: number;
        unchanged: number;
    };
    min_date: string | null;
    max_date: string | null;
    branches: string[];
    non_trading_day_rows: number;
    weeks: WeekStateView[];
    already_imported: { id: number; original_filename: string }[];
    refreshed: boolean;
    uncovered: {
        overdue_branch_days: number | null;
        last_due_date: string;
        first: string | null;
        last: string | null;
    };
};

export type ImportErrorRow = {
    id: number;
    row_number: number;
    column: string | null;
    code: string;
    message: string;
    raw_row: Record<string, string>;
};
