#!/usr/bin/php
<?php

use zozlak\argparse\ArgumentParser as AP;
use acdhOeaw\arche\lib\ingest\Redmine;

$composerLocation = getenv('COMPOSER_DIR') ?: __DIR__;
require_once "$composerLocation/vendor/autoload.php";

$parser = new AP(null, 'Updates information on ingestion progress in the Redmine.');
$parser->addArgument('--user', help: "Redmine user.");
$parser->addArgument('--pswd', help: "Redmine user's password");
$parser->addArgument('--token', help: "Redmine authorization token. Can be used instead of --user and --pswd.");
$parser->addArgument('--message', help: "Message to be stored as a Redmine issue note. If not provided, a default message is used. There are different default messages for each subtask and successful/unsuccessful status code.");
$parser->addArgument('--append', help: "Message to be appended to the main one. Useful for combining the default message with a custom one.");
$parser->addArgument('--status', choices: Redmine::ISSUE_STATUSES, help: "Set Redmine issue status to a given value. If not provided, the current status is preserved.");
$parser->addArgument('--done', type: AP::TYPE_INT, help: "Set Redmine issue 'done' field to a given value (from 0 to 100). If not provided, the current field value is kept.");
$parser->addArgument('--statusCode', type: AP::TYPE_INT, default: 0, help: "Indicates if a successful (--statusCode 0) on unsuccessful (any other value) variant of a message should be used as the Redmine issue note. If not provided, the successful variant is used. Has no impact if --message parameter is used.");
$parser->addArgument('--redmineApiUrl', default: 'https://redmine.acdh.oeaw.ac.at', help: "Redmine API base URL");
$parser->addArgument('mainIssueId', help: "Redmine issue ID of a main collection ingestion tracking issue, e.g. https://redmine.acdh.oeaw.ac.at/issues/21016"); // use 21085 for testing
$parser->addArgument('subtask', choices: array_keys(REDMINE::SUBTASKS), help: "Ingestion subtask to note the information about");
$args   = $parser->parseArgs();

if ((empty($args->user) || empty($args->pswd)) && empty($args->token)) {
    echo "Either --token or --user and --pswd parameters have to be provided\n";
    exit(1);
}
$redmine = new Redmine($args->redmineApiUrl, (string) ($args->token ?? $args->user), (string) $args->pswd);
try {
    $message = ltrim($args->message . "\n\n" . $args->append);
    $id      = $redmine->updateIssue(
        $args->mainIssueId,
        $args->subtask,
        $args->statusCode === 0,
        $args->status,
        $args->done,
        $message,
        !empty($args->append)
    );
    echo "Redmine issue $id updated successfully\n";
    exit(0);
} catch (RuntimeException $e) {
    echo $e->getMessage() . "\n";
    exit(1);
}

