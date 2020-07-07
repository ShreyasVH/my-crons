<?php

date_default_timezone_set('Asia/Kolkata');

require_once 'Constants.php';
require_once 'DBFunctions.php';
require_once 'MailFunctions.php';

class BackupDBMovies
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
            $query = 'SELECT `action_time` FROM `cron_logs` WHERE type = ' . Constants::TYPE_BACKUP_DB_MOVIES;
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
        $query = 'UPDATE `cron_logs` SET `action_time` = "' . date('Y-m-d H:i:s') . '" WHERE type = ' . Constants::TYPE_BACKUP_DB_MOVIES;
        runQuery($config, $query);
    }

    public function execute()
    {
        if($this->isRequired())
        {
            $config = [
                'host' => trim(getenv('DB_HOST_MOVIES')),
                'port' => trim(getenv('DB_PORT_MOVIES')),
                'user' => trim(getenv('DB_USER_MOVIES')),
                'password' => trim(getenv('DB_PASSWORD_MOVIES')),
                'dbName' => trim(getenv('DB_NAME_MOVIES'))
            ];
            processDatabase($config, trim(getenv('DB_NAME_MOVIES')), 'MOVIES');

            $this->updateLogs();
        }
    }
}

$runner = new BackupDBMovies();
$runner->execute();