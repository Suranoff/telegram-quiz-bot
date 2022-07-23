<?php

namespace Suran;

use Suran\Quiz\Exceptions\CliException;

class Process
{
    private $pid;
    private $command;

    public function __construct($cl = '')
    {
        if (!$cl) return;

        $this->command = $cl;
        $this->runCom();
    }

    private function runCom()
    {
        $command = 'nohup ' . $this->command . ' > /dev/null 2>&1 & echo $!';
        exec($command, $op);

        if (!is_numeric($op[0]))
            throw new CliException('Не удалось создать процесс игры для команды - ' . $this->command);

        $this->pid = (int)$op[0];
    }

    public function setPid($pid)
    {
        $this->pid = $pid;
    }

    public function getPid()
    {
        return $this->pid;
    }

    public function isAlive()
    {
        $command = 'ps -p ' . $this->pid;
        exec($command, $op);
        if (!isset($op[1])) return false;

        return true;
    }

    public function stop()
    {
        $command = 'kill ' . $this->pid;
        exec($command);
        if (!$this->isAlive()) return true;

        return false;
    }
}