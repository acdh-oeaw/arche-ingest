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

$parser = new AP(null, 'Updates information on ingestion progress in the Redmine.');
$parser->addArgument('--user', help: "Redmine user.");
$parser->addArgument('--pswd', help: "Redmine user's password");
$parser->addArgument('--token', help: "Redmine authorization token. Can be used instead of --user and --pswd.");
$parser->addArgument('--message', help: "Message to be stored as a Redmine issue note. If not provided, a default message is used. There are different default messages for each subtask and successful/unsuccessful status code.");
$parser->addArgument('--append', help: "Message to be appended to the main one. Useful for combining the default message with a custom one.");
$parser->addArgument('--status', choices: ['New', 'In Progress', 'Resolved', 'Feedback',
    'Closed', 'Rejected', 'Needs review', 'On Hold'], help: "Set Redmine issue status to a given value. If not provided, the current status is preserved.");
$parser->addArgument('--done', type: AP::TYPE_INT, help: "Set Redmine issue 'done' field to a given value (from 0 to 100). If not provided, the current field value is kept.");
$parser->addArgument('--statusCode', type: AP::TYPE_INT, default: 0, help: "Indicates if a successful (--statusCode 0) on unsuccessful (any other value) variant of a message should be used as the Redmine issue note. If not provided, the successful variant is used. Has no impact if --message parameter is used.");
$parser->addArgument('--redmineApiUrl', default: 'https://redmine.acdh.oeaw.ac.at', help: "Redmine API base URL");
$parser->addArgument('mainIssueId', help: "Redmine issue ID of a main collection ingestion tracking issue, e.g. https://redmine.acdh.oeaw.ac.at/issues/21016"); // use 21085 for testing
$parser->addArgument('subtask', choices: array_keys($subtasks), help: "Ingestion subtask to note the information about");
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
    'status_id' => '*',
    'limit'     => 100,
]);
foreach ($issues['issues'] ?? [] as $i) {
    if ($i['subject'] == $args->subtask) {
        $issue = $i['id'];
        break;
    } else {
        $subissues = $issuesApi->all([
            'parent_id' => $i['id'],
            'status_id' => '*',
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
if (!empty($args->append)) {
    $msg = rtrim($msg) . "\n\n" . $args->append;
}
$issuesApi->addNoteToIssue($issue, $msg);

echo "Redmine issue $issue updated successfully\n";
exit(0);

