<?php

date_default_timezone_set('Asia/Kolkata');

require_once 'Constants.php';
require_once 'DBFunctions.php';
require_once 'MailFunctions.php';

class BackupDB_DuelLinks
{
    private function isRequired()
    {
        $isRequired = false;

        $config = [
            'host' => getenv('DB_HOST_LOGS'),
            'port' => getenv('DB_PORT_LOGS'),
            'user' => getenv('DB_USER_LOGS'),
            'password' => getenv('DB_PASSWORD_LOGS'),
            'dbName' => getenv('DB_NAME_LOGS')
        ];

        if(isAvailable($config))
        {
            $query = 'SELECT `action_time` FROM `cron_logs` WHERE type = ' . Constants::TYPE_BACKUP_DB_DUEL_LINKS;
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
                'from' => getenv('FROM_EMAIL_ID'),
                'to' => getenv('TO_EMAIL_ID'),
                'subject' => 'Could not connect to cron logs - EOM',
                'body' => ''
            ];

            sendMail($payload);
        }

        return $isRequired;
    }

    private function updateLogs()
    {
        $config = [
            'host' => getenv('DB_HOST_LOGS'),
            'port' => getenv('DB_PORT_LOGS'),
            'user' => getenv('DB_USER_LOGS'),
            'password' => getenv('DB_PASSWORD_LOGS'),
            'dbName' => getenv('DB_NAME_LOGS')
        ];
        $query = 'UPDATE `cron_logs` SET `action_time` = "' . date('Y-m-d H:i:s') . '" WHERE type = ' . Constants::TYPE_BACKUP_DB_DUEL_LINKS;
        runQuery($config, $query);
    }

    public function execute()
    {
        if($this->isRequired())
        {
            $config = [
                'host' => getenv('DB_HOST_DUEL_LINKS'),
                'port' => getenv('DB_PORT_DUEL_LINKS'),
                'user' => getenv('DB_USER_DUEL_LINKS'),
                'password' => getenv('DB_PASSWORD_DUEL_LINKS'),
                'dbName' => getenv('DB_NAME_DUEL_LINKS')
            ];
            processDatabase($config, getenv('DB_NAME_DUEL_LINKS'), 'DUEL_LINKS');

            $this->updateLogs();
        }
    }
}

$runner = new BackupDB_DuelLinks();
$runner->execute();