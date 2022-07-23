<?php

namespace Suran\Quiz\Exceptions;

class CliException extends \Exception
{
    public function __construct($message = '', $code = 0, \Throwable $previous = null)
    {
        $message = 'Ошибка командной строки: ' . $message;
        parent::__construct($message, $code, $previous);
    }
}