<?php

use Suran\Quiz\Bot;
use Suran\Quiz\Exceptions\CommandException;
use Suran\Quiz\Exceptions\GameException;
use Suran\Quiz\Exceptions\CliException;
use Suran\Quiz\Game;
use Telegram\Bot\Objects\CallbackQuery;

const NO_KEEP_STATISTIC = true;

if (empty($_SERVER['DOCUMENT_ROOT']))
    $_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');


try {
    $bot = new Bot();
    $update = $bot->getWebhookUpdates();
    $callback = $update->getCallbackQuery();

    if ($callback instanceof CallbackQuery) {
        $bot->setChat($callback->getMessage()->getChat()->getId());
        $bot->doAction($callback->getId(), $callback->getData());
        return;
    }

    $message = $update->getMessage();
    $text = $message->getText();

    if (!$text) return;

    $chatId = $message->getChat()->getId();
    $bot->setChat($chatId);

    if ($bot->isCommand($text)) {
        $bot->executeCommand($text);
        return;
    }

    $game = new Game($chatId);
    if (!$game->isQuestionInProcess()) return;

    $user = $message->getFrom();
    $game->addAnswer([
        'text' => trim(strtolower($text)), // обрезать длину
        'username' => $user->getUsername(),
        'first_name' => $user->getFirstName(),
        'last_name' => $user->getLastName(),
        'full_name' => implode(
            ' ',
            [$user->getFirstName(), $user->getLastName()]
        ),
    ]);

} catch (GameException $e) {
    $bot->sendChatMessage($e->getMessage());
} catch (\Exception | CliException | CommandException $e) {
    $bot->sendMessageToAdmin($e->getMessage());
    $bot->sendChatMessage('Упс :(. Что-то сломалось. Мы уже занимаемся проблемой');
}
