<?php

/**
 * Documentation
 * https://api.ovh.com/console/
 */

require __DIR__ . '/vendor/autoload.php';

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Ovh\Api;
use Symfony\Component\Yaml\Yaml;

$options = getopt('', ['dry-run']);
$dryRun = isset($options['dry-run']);

$log = new Logger('snapshot');
$log->pushHandler(new StreamHandler(__DIR__ . '/snapshot.log', Logger::DEBUG));
$log->pushProcessor(new PsrLogMessageProcessor());

$config = Yaml::parse(file_get_contents(__DIR__ . '/snapshot.yml'));

$ovh = new Api($config['applicationKey'], $config['applicationSecret'], $config['apiEndpoint'], $config['consumerKey']);

foreach ($config['vps'] as $vpsServiceName) {
    echo '--------------------------------------------------' . PHP_EOL;
    echo 'VPS: ' . $vpsServiceName . PHP_EOL;

    try {
        $url = '/vps/' . $vpsServiceName;
        $vpsDetails = $ovh->get($url);
    } catch (\GuzzleHttp\Exception\RequestException $exception) {
        $message = 'The VPS does not exist or is not assigned to this account.';
        echo '    ❌ Error: ' . $message . PHP_EOL;
        $log->error($message, [
            'vps' => $vpsServiceName,
            'url' => $url,
            'exception' => $exception,
        ]);

        continue;
    }

    try {
        $url = '/vps/' . $vpsServiceName . '/snapshot';
        $snapshotDetails = $ovh->get($url);
        $snapshotTime = new DateTime($snapshotDetails['creationDate']);

        echo '    ✔ Deleting snapshot created at ' . $snapshotTime->format('Y-m-d H:i:s') . PHP_EOL;
        if ($dryRun !== true) {
            try {
                $url = '/vps/' . $vpsServiceName . '/snapshot';
                $taskDetails = $ovh->delete($url);
                $log->debug('VPS snapshot deleted.', [
                    'vps' => $vpsServiceName,
                    'url' => $url,
                    'snapshot' => $snapshotDetails,
                    'task' => $taskDetails,
                ]);

                $taskId = $taskDetails['id'];
                $maxIterations = 18; // 3 minutes
                $currentIteration = 0;
                do {
                    try {
                        sleep(10); // Wait 10 seconds
                        $task = $ovh->get('/vps/' . $vpsServiceName . '/tasks/' . $taskId);
                        $log->debug('Awaiting the deletion of snapshot to finish.', [
                            'vps' => $vpsServiceName,
                            'url' => $url,
                            'task' => $task,
                        ]);
                    } catch (\GuzzleHttp\Exception\RequestException $exception) {
                        $message = 'Could not retrieve task status for the deletion of snapshot.';
                        echo '    ❌ Error: ' . $message . PHP_EOL;
                        $log->error($message, [
                            'vps' => $vpsServiceName,
                            'snapshotDetails' => $snapshotDetails,
                            'url' => $url,
                            'exception' => $exception,
                        ]);
                    }
                    $currentIteration++;
                } while (
                    !in_array($task['state'] ?? 'error', ['done', 'blocked', 'cancelled', 'error']) &&
                    $currentIteration < $maxIterations
                );
            } catch (\GuzzleHttp\Exception\RequestException $exception) {
                $message = 'Could not delete the exiting snapshot of the VPS.';
                echo '    ❌ Error: ' . $message . PHP_EOL;
                $log->error($message, [
                    'vps' => $vpsServiceName,
                    'snapshotDetails' => $snapshotDetails,
                    'url' => $url,
                    'exception' => $exception,
                ]);
            }
        }
    } catch (\GuzzleHttp\Exception\RequestException $exception) {
        if ($exception->getCode() === 404) {
            // There's no error. The Snapshot is not created.
            $log->debug('The VPS doesn\'t have a snapshot created.', [
                'vps' => $vpsServiceName,
                'url' => $url,
                'exception' => $exception,
            ]);
        } else {
            $message = 'Could not retrieve snapshot information.';
            echo '    ❌ Error: ' . $message . PHP_EOL;
            $log->error($message, [
                'vps' => $vpsServiceName,
                'url' => $url,
                'exception' => $exception,
            ]);
        }
    }

    echo '    ✔ Creating snapshot "' . $vpsServiceName . '"' . PHP_EOL;
    if ($dryRun !== true) {
        try {
            $taskDetails = $ovh->post('/vps/' . $vpsServiceName . '/createSnapshot');

            $log->debug('VPS snapshot creation is started.', [
                'vps' => $vpsServiceName,
                'url' => $url,
                'task' => $taskDetails,
            ]);
        } catch (\GuzzleHttp\Exception\RequestException $exception) {
            $error = json_decode((string)$exception->getResponse()->getBody(), true);
            if (preg_match('/There is already \d+ task\(s\) occuring/', $error['message']) === 1) {
                // There's no error. The Snapshot is in process of creation.
                $log->debug('A Snapshot is already in process of creation.', [
                    'vps' => $vpsServiceName,
                    'url' => $url,
                    'exception' => $exception,
                ]);
            } else {
                $message = 'Could not create the snapshot.';
                echo '    ❌ Error: ' . $message . PHP_EOL;
                $log->error($message, [
                    'vps' => $vpsServiceName,
                    'url' => $url,
                    'exception' => $exception,
                ]);
            }
        }
    }
}
