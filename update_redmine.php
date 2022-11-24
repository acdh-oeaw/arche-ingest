#!/usr/bin/php
<?php

use zozlak\argparse\ArgumentParser as AP;
use \Redmine\Client\NativeCurlClient;

$composerLocation = getenv('COMPOSER_DIR') ?: __DIR__;
require_once "$composerLocation/vendor/autoload.php";

$subtasks = [
    'Virus scan'                                 => [
        "Virus scan performed successfully",
        "Virus scan failed"
    ],
    'Run repo-file-checker'                      => [
        "repo-file-checker exposed no error",
        "repo-file-checker found errors"
    ],
    'Prepare Ingest Files'                       => [
        "Successfully preparred files for an ingestion",
        "Failed to prepare files for an ingestion"
    ],
    'Upload AIP to Curation Instance (Minerva)'  => [
        "Successfully uploaded data to the curation instance",
        "Failed to upload data to the curation instance"
    ],
    'Upload AIP to Productive Instance (Apollo)' => [
        "Successfully uploaded data to the production instance",
        "Failed to upload data to the production instance"
    ],
    'Create PID'                                 => [
        "Successfully created PIDs",
        "Failed to create PIDs"
    ],
];

$parser = new AP();
$parser->addArgument('--user');
$parser->addArgument('--pswd');
$parser->addArgument('--token');
$parser->addArgument('--message');
$parser->addArgument('--status', choices: ['New', 'In Progress', 'Resolved', 'Feedback',
    'Closed', 'Rejected', 'Needs review', 'On Hold']);
$parser->addArgument('--done', type: AP::TYPE_INT);
$parser->addArgument('--statusCode', type: AP::TYPE_INT, default: 0);
$parser->addArgument('redmineApiUrl');
$parser->addArgument('mainIssueId'); // use 21085 for testing
$parser->addArgument('subtask', choices: array_keys($subtasks));
$args   = $parser->parseArgs();

if ((empty($args->user) || empty($args->pswd)) && empty($args->token)) {
    echo "Either --token or --user and --pswd parameters have to be provided\n";
    exit(1);
}
if (!empty($args->token)) {
    $redmine = new NativeCurlClient($args->redmineApiUrl, $args->token);
} else {
    $redmine = new NativeCurlClient($args->redmineApiUrl, $args->user, $args->pswd);
}
// check main redmine issue
$issuesApi = $redmine->getApi('issue');
if (!is_array($issuesApi->show($args->mainIssueId))) {
    echo "Can't access the $args->redmineApiUrl/issues/$args->mainIssueId. Check provided credentials and the redmine issue ID.\n";
    exit(2);
}
// find proper subtask
$issue  = null;
$issues = $issuesApi->all([
    'parent_id' => $args->mainIssueId,
    'limit'     => 100,
    ]);
foreach ($issues['issues'] ?? [] as $i) {
    if ($i['subject'] == $args->subtask) {
        $issue = $i['id'];
        break;
    } else {
        $subissues = $issuesApi->all([
            'parent_id' => $i['id'],
            'subject'   => $args->subtask,
        ]);
        if (is_array($subissues) && count($subissues['issues']) === 1) {
            $issue = $subissues['issues'][0]['id'];
            break;
        }
    }
}
if ($issue === null) {
    echo "Can't find the '$args->subtask' subtask. Please check your redmine issues structure.\n";
    exit(3);
}
// update the issue
if (!empty($args->status)) {
    $issuesApi->setIssueStatus($issue, $args->status);
}
if (!empty($args->done)) {
    $issuesApi->update($issue, ['done_ratio' => $args->done]);
}

$msg = $args->message ?? $subtasks[$args->subtask][$args->statusCode === 0 ? 0 : 1];
$issuesApi->addNoteToIssue($issue, $msg);
if (!empty(getenv('GITHUB_RUN_ID'))) {
    $msg = rtrim($msg) . "\n\n" . getenv('GITHUB_SERVER_URL') . '/' . getenv('GITHUB_REPOSITORY') . '/actions/runs/' . getenv('GITHUB_RUN_ID');
}

echo "Redmine issue $issue updated successfully\n";
exit(0);

