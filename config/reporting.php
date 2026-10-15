<?php

return [

    /*
    |---------------------------------------------------------------------------
    | Reporting
    |---------------------------------------------------------------------------
    |
    | Metric definitions from Spec section 13. The thresholds live here rather
    | than in code so DICT can change them without a deployment, and so every
    | report agrees on what "positive" means.
    |
    */

    'positive_rating_threshold' => (int) env('REPORT_POSITIVE_THRESHOLD', 4),

    /*
    | Issue categories that mean "no problem". Everything else counts towards the
    | connection-issue rate.
    */
    'no_issue_categories' => ['none'],

    /*
    | Below this many rated responses, per-site and per-supplier averages are
    | reported but flagged as not meaningful (Spec 13: avoid misleading rankings
    | from small numbers).
    */
    'minimum_sample_size' => (int) env('REPORT_MIN_SAMPLE_SIZE', 10),

    /*
    | Rows the printed PDF appendix will include. One document is printed in a
    | single pass, so it is capped rather than paginated; past this many rows the
    | browser starts to struggle. The CSV export is unlimited and stays the way
    | to get every response. The printed document states when it was capped, so a
    | partial appendix can never be mistaken for a complete one.
    */
    'pdf_appendix_max_rows' => (int) env('REPORT_PDF_MAX_ROWS', 2000),

];
