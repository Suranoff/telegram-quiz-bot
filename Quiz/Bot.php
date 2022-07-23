<?php

namespace Suran\Quiz;


use Suran\Helpers\Str;
use Suran\Quiz\Exceptions\CommandException;
use Suran\Quiz\Exceptions\GameException;
use Suran\Quiz\Tables\QuizQuestionCategoryTable;
use Suran\Quiz\Tables\QuizQuestionTable;
use Telegram\Bot\Api;
use Bitrix\Main\Entity\Query;

class Bot extends Api
{
    const API_KEY = '5149235372:AAHnsz_pTjlPjqqZPC8mKP-cCxfCTB34KHU';
    const NAME = 'QuizChat_Bot';
    const ADMIN_CHAT = 269993795;

    protected $chatId;

    public function __construct(int $chatId = 0, $async = false, $http_client_handler = null)
    {
        parent::__construct(self::API_KEY, $async, $http_client_handler);

        if ($chatId)
            $this->chatId = $chatId;
    }

    public function setChat(int $chatId)
    {
        $this->chatId = $chatId;
    }

    public function doAction(int $callbackId, string $data)
    {
        $data = json_decode($data, true);

        if (!$data['action']) return false;

        $actionMethod = 'action' . Str::firstToUp($data['action']);

        if (!method_exists($this, $actionMethod)) return false;

        return $this->$actionMethod($callbackId, $data);
    }

    protected function actionChoiceCategory(int $callbackId, array $data)
    {
        $this->answerCallbackQuery([
            'callback_query_id' => $callbackId
        ]);

        if ((int)$data['category_id'] < 0)
            throw new CommandException('Неправильный выбор категории - ' . json_encode($data));

        $game = new Game($this->chatId);
        if (!$game->init(10, $data['category_id']))
            throw new CommandException('Не удалось запустить игру ' . $this->chatId, 'start');
    }

    public function sendChatMessage(string $sText, $useHtml = false): \Telegram\Bot\Objects\Message
    {
        return $this->sendMessage([
            'chat_id' => $this->chatId,
            'text' => $sText,
            'disable_notification' => true,
            'parse_mode' => $useHtml ? 'HTML' : ''
        ]);
    }

    protected function sendChoiceCategoryMessage(array $categories): \Telegram\Bot\Objects\Message
    {
        $keyboard = [
            [
                'text' => 'Любая',
                'callback_data' => json_encode([
                    'action' => 'choiceCategory',
                    'category_id' => 0,
                ]),
            ]
        ];
//        foreach ($categories as $category) {
//            $keyboard[] = [
//                'text' => $category['name'],
//                'callback_data' => json_encode([
//                    'action' => 'choiceCategory',
//                    'category_id' => $category['id'],
//                ]),
//            ];
//        }

        return $this->sendMessage([
            'chat_id' => $this->chatId,
            'text' => 'Выберите категорию',
            'disable_notification' => true,
            'reply_markup' => json_encode(['inline_keyboard' => array_chunk($keyboard, 2)]),
        ]);
    }

    public function isCommand(string $text): bool
    {
        return !empty($this->getCommandBus()->parseCommand($text));
    }

    public function executeCommand(string $text, ...$params)
    {
        $arCommand = $this->getCommandBus()->parseCommand($text);

        if ($arCommand[2] != static::NAME) return false;

        $commandMethod = 'command' . Str::firstToUp($arCommand[1]);

        if (!method_exists($this, $commandMethod)) return false;

        return $this->$commandMethod($params);
    }

    public function sendMessageToAdmin(string $sText): \Telegram\Bot\Objects\Message
    {
        return $this->sendMessage([
            'chat_id' => self::ADMIN_CHAT,
            'text' => $sText,
        ]);
    }

    protected function answerCallbackQuery($params = []): bool
    {
        return !$this->post('answerCallbackQuery', $params)->isError();
    }

    public function commandHelp($params = [])
    {

    }

    /**
     * @throws GameException
     * @throws \Telegram\Bot\Exceptions\TelegramSDKException
     */
    protected function commandStart()
    {
        $game = new Game($this->chatId);
        if ($game->inProcess())
            throw new GameException('Игра уже идет');

        $query = new Query(QuizQuestionTable::getEntity());
        $query->setSelect(['distinct_id']);
        $query->registerRuntimeField('distinct_id', [
            'data_type'=>'integer',
            'expression' => ['DISTINCT %s', 'category_id']
        ]);
        $categoriesIds = array_column($query->exec()->fetchAll(), 'distinct_id');
        $query = new Query(QuizQuestionCategoryTable::getEntity());
        $query->setSelect(['name', 'id']);
        $query->whereIn('id', $categoriesIds);
        $categories = $query->exec()->fetchAll();

        $this->sendChoiceCategoryMessage($categories);
        return true;
    }

    /**
     * @throws \Telegram\Bot\Exceptions\TelegramSDKException
     * @throws CommandException
     */
    protected function commandStop()
    {
        $game = new Game($this->chatId);
        return $game->stop();
    }
}