<?php

date_default_timezone_set('Asia/Kolkata');

require_once 'Constants.php';
require_once 'DBFunctions.php';
require_once 'MailFunctions.php';

class BackupLogs
{
    private function isRequired()
    {
        $isRequired = false;

        $config = [
            'host' => trim(getenv('DB_HOST_LOGS')),
            'port' => trim(getenv('DB_PORT_LOGS')),
            'user' => trim(getenv('DB_USER_LOGS')),
            'password' => trim(getenv('DB_PASSWORD_LOGS')),
            'dbName' => trim(getenv('DB_NAME_LOGS'))
        ];

        if(isAvailable($config))
        {
            $query = 'SELECT `action_time` FROM `cron_logs` WHERE type = ' . Constants::TYPE_BACKUP_LOGS;
            $result = runQuery($config, $query);
            if(!empty($result))
            {
                $rows = $result->fetch_all(MYSQLI_ASSOC);
                if(!empty($rows))
                {
                    $lastActionTime = strtotime($rows[0]['action_time']);
                    $now = time();
                    $isRequired = (($now - $lastActionTime) > (24 * 3600));
                }
                else
                {
                    $isRequired = true;
                }
            }
        }
        else
        {
            $payload = [
                'from' => trim(getenv('FROM_EMAIL_ID')),
                'to' => trim(getenv('TO_EMAIL_ID')),
                'subject' => 'Could not connect to cron logs for "movies" - EOM',
                'body' => ''
            ];

            sendMail($payload);
        }

        return $isRequired;
    }

    private function updateLogs()
    {
        $config = [
            'host' => trim(getenv('DB_HOST_LOGS')),
            'port' => trim(getenv('DB_PORT_LOGS')),
            'user' => trim(getenv('DB_USER_LOGS')),
            'password' => trim(getenv('DB_PASSWORD_LOGS')),
            'dbName' => trim(getenv('DB_NAME_LOGS'))
        ];
        $query = 'UPDATE `cron_logs` SET `action_time` = "' . date('Y-m-d H:i:s') . '" WHERE type = ' . Constants::TYPE_BACKUP_LOGS;
        runQuery($config, $query);
    }

    public function execute()
    {
        if($this->isRequired())
        {
            $this->getLogsForToday();

            $this->updateLogs();
        }
    }

    public function getLogsForToday()
    {
        $logs = [];
        $logIds = [];

        $offset = 0;
        $count = 1000;
        $totalCount = 0;

        $config = [
            'host' => trim(getenv('DB_HOST_LOGS')),
            'port' => trim(getenv('DB_PORT_LOGS')),
            'user' => trim(getenv('DB_USER_LOGS')),
            'password' => trim(getenv('DB_PASSWORD_LOGS')),
            'dbName' => trim(getenv('DB_NAME_LOGS'))
        ];

        $query = 'SELECT COUNT(*) as count FROM `logs` WHERE `created_at` >= "' . date('Y-m-d H:i:s', strtotime('-1 day')) . '"';
        $result = runQuery($config, $query);

        if(!empty($result))
        {
            $rows = $result->fetch_all(MYSQLI_ASSOC);
            $result->free();

            $row = $rows[0];
            $totalCount = $row['count'];
        }

        while($offset < $totalCount)
        {
            $query = 'SELECT * FROM `logs` WHERE `created_at` >= "' . date('Y-m-d H:i:s', strtotime('-1 day')) . '" LIMIT ' . $count . ' OFFSET ' . $offset;
            $result = runQuery($config, $query);
            if(!empty($result))
            {
                $rows = $result->fetch_all(MYSQLI_ASSOC);
                $result->free();

                $logIds = array_merge($logIds, array_column($rows, 'id'));

                $logs = array_merge($logs, $rows);
            }
            $offset += $count;
        }

        if(count($logs) > 0)
        {
            $fileName = 'logs.json';
            $payload = [
                'from' => trim(getenv('FROM_EMAIL_ID')),
                'to' => trim(getenv('TO_EMAIL_ID')),
                'subject' => 'Logs Backup - ' . date('d-m-Y'),
                'body' => "\nPFA",
                'attachments' => [
                    [
                        'filename' => $fileName,
                        'content' => base64_encode(json_encode($logs, JSON_PRETTY_PRINT))
                    ]
                ]
            ];

            echo "\n\t\t\tSending email\n";

            sendMail($payload);
        }

        if(count($logIds) > 0)
        {
            $query = 'DELETE FROM `logs` WHERE `id` IN (' . implode(', ', $logIds) . ')';
            runQuery($config, $query);
        }
    }
}

$runner = new BackupLogs();
$runner->execute();