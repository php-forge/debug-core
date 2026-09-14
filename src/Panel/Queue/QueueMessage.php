<?php

declare(strict_types=1);

namespace PHPForge\Debug\Panel\Queue;

/**
 * Identity, formats, and presentation text of the Queue panel, shared by every adapter that renders it.
 */
enum QueueMessage: string
{
    /**
     * Closing sentence of the async callout, pointing at the worker snapshots in the History sidebar.
     */
    case ASYNC_SNAPSHOTS = ' debug snapshots that capture the matching exec and error events.';

    /**
     * Opening sentence of the async callout, prefixing the detected out-of-process driver names.
     */
    case ASYNC_TITLE = 'Async driver: ';

    /**
     * Middle sentence of the async callout, explaining why exec and error events are missing.
     */
    case ASYNC_WORKER = ' Push events show here, but jobs run in a separate worker process; see the History '
        . 'sidebar for ';

    /**
     * Header of the retry-count column, also used as its detail field label.
     */
    case ATTEMPT = 'Attempt';

    /**
     * Snapshot kind named in the async callout, matching the History sidebar label.
     */
    case CLI = 'CLI';

    /**
     * Header of the queue component column, also used as its detail field label.
     */
    case COMPONENT = 'Component';

    /**
     * `date()` format of the absolute timestamp in the detail overview.
     */
    case DATE_FORMAT = 'M j, Y · H:i:s';

    /**
     * Detail field label of the delay override.
     */
    case DELAY = 'Delay';

    /**
     * Detail field label of the adapter-owned per-event detail link.
     */
    case DETAILS = 'Details';

    /**
     * Suffix appended to the executed-event count in the summary header.
     */
    case DONE_SUFFIX = ' done';

    /**
     * Header of the driver column, also used as its detail field label.
     */
    case DRIVER = 'Driver';

    /**
     * Detail field label of the fully qualified driver class.
     */
    case DRIVER_CLASS = 'Driver class';

    /**
     * Header of the execution-time column, also used as its detail field label.
     */
    case DURATION = 'Duration';

    /**
     * Explanation of the empty state when the request neither pushed nor ran a job.
     */
    case EMPTY_EXPLANATION = 'This request pushed no job and ran none, so the lifecycle log is empty.';

    /**
     * Headline of the empty state when the request captured no lifecycle event.
     */
    case EMPTY_HEADLINE = 'No queue activity in this request';

    /**
     * Call to action of the empty state, introducing the lifecycle hooks that populate the panel.
     */
    case EMPTY_HOOKS = 'Events appear here when a queue component emits ';

    /**
     * `sprintf()` template of the detail group label of one event.
     */
    case EVENT_GROUP = 'Event %d';

    /**
     * Suffix appended to a single captured event in the summary header.
     */
    case EVENT_SUFFIX = ' event';

    /**
     * Suffix appended to the captured event count in the summary header.
     */
    case EVENTS_SUFFIX = ' events';

    /**
     * Detail field label reporting whether the job ran in process or in a worker.
     */
    case EXECUTION = 'Execution';

    /**
     * Suffix appended to the failed-event count in the summary header.
     */
    case FAILED_SUFFIX = ' failed';

    /**
     * Lifecycle hook capturing a failed execution.
     */
    case HOOK_AFTER_ERROR = 'afterError';

    /**
     * Lifecycle hook capturing a completed execution.
     */
    case HOOK_AFTER_EXEC = 'afterExec';

    /**
     * Lifecycle hook capturing a queued job.
     */
    case HOOK_AFTER_PUSH = 'afterPush';

    /**
     * Stable identifier associating the panel with the captured payload, also used as its icon key.
     */
    case ID = 'queue';

    /**
     * Execution mode of a job the request ran synchronously.
     */
    case IN_PROCESS = 'In process';

    /**
     * Header of the job class column, also used as its detail field label.
     */
    case JOB = 'Job';

    /**
     * Detail field label of the driver-assigned job identifier.
     */
    case JOB_ID = 'Job id';

    /**
     * Label of the adapter-owned link to the per-event detail page.
     */
    case JOB_LINK = 'Open job detail';

    /**
     * Heading above the lifecycle table.
     */
    case LIFECYCLE = 'Lifecycle events';

    /**
     * Note of the detail group when the capture carried no job payload.
     */
    case NO_PAYLOAD = 'The event carried no job payload.';

    /**
     * Header of the position column, which sorts by capture order.
     */
    case NUMBER = '#';

    /**
     * Detail field label of the decoded job payload.
     */
    case PAYLOAD = 'Payload';

    /**
     * Placeholder shown wherever the capture left a field empty.
     */
    case PLACEHOLDER = '—';

    /**
     * Detail field label of the priority override.
     */
    case PRIORITY = 'Priority';

    /**
     * Detail field label of the absolute queue time.
     */
    case PUSHED_AT = 'Pushed at';

    /**
     * Suffix appended to the queued-event count in the summary header.
     */
    case QUEUED_SUFFIX = ' queued';

    /**
     * `sprintf()` template of the heading preceding each detail group.
     */
    case RECORD_HEADING = '%d. %s';

    /**
     * Header of the lifecycle phase column, also used as its detail field label.
     */
    case STATUS = 'Status';

    /**
     * Badge label of a completed execution.
     */
    case STATUS_DONE = 'Done';

    /**
     * Badge label of a failed execution.
     */
    case STATUS_FAILED = 'Failed';

    /**
     * Badge label of a queued job.
     */
    case STATUS_QUEUED = 'Queued';

    /**
     * Header of the queue-time column.
     */
    case TIME = 'Time';

    /**
     * `date()` format of the clock-only timestamp in the lifecycle table.
     */
    case TIME_FORMAT = 'H:i:s';

    /**
     * Panel title used in the debugger navigation.
     */
    case TITLE = 'Queue';

    /**
     * Label of the toolbar metric counting the captured events.
     */
    case TOOLBAR = 'Jobs';

    /**
     * Detail field label of the time-to-reserve override.
     */
    case TTR = 'TTR';

    /**
     * Execution mode of a job handed to an out-of-process worker.
     */
    case WORKER_PROCESS = 'Worker process';
}
