<?php

namespace Suran\Quiz\Exceptions;


class CommandException extends \Exception
{
    public function __construct($message = '', $command = '', $code = 0, \Throwable $previous = null)
    {
        $message = 'Ошибка исполнения команды бота "'.$command.'": ' . $message;
        parent::__construct($message, $code, $previous);
    }
}