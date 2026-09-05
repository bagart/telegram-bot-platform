<?php

// User-level overrides for module scheduled tasks (telegram.modules_schedule).
//
// Keyed by command name; every key is optional:
//   'expression' — replaces the module-provided cron expression;
//   'disabled'   — removes the task from the schedule entirely.
//
// This file is the single place to adjust periodicity without touching module
// code, and it survives module updates. Examples:

return [

    // 'summarizer:digests' => [
    //     'expression' => '*/5 * * * *',
    // ],

    // 'stt:prune' => [
    //     'disabled' => true,
    // ],

];
