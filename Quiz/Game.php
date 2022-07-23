<?php

namespace Suran\Quiz;

use Bitrix\Main\Entity\Query;
use Predis\Client;
use Suran\Helpers\Str;
use Suran\Process;
use Suran\Quiz\Exceptions\GameException;
use Suran\Quiz\Tables\QuizGameResultTable;
use Suran\Quiz\Tables\QuizGameTable;
use Suran\Quiz\Tables\QuizQuestionTable;
use Suran\Quiz\Tables\QuizUserTable;
use Telegram\Bot\Exceptions\TelegramSDKException;

class Game
{
    const ROUNDS = 4;
    const ROUND_INTERVAL = 15;
    const QUESTION_DELAY = 5;
    const HIDE_SYMBOL = '*';
    const NAME = 'QuizChat_Bot';
    const SCRIPT_PATH = '/var/www/www-root/data/www/top-holodilnik.ru/quiz-game.php';

    protected $chatId;
    protected $redis;
    protected $questionsCount;
    public $openHintKeys;
    public $bot;

    /**
     * @throws TelegramSDKException
     */
    function __construct(int $iChatId)
    {
        $this->redis = new Client();
        $this->chatId = $iChatId;
        $this->bot = new Bot($iChatId);
    }

    /**
     * @param int $count
     * @param int $category
     * @return bool
     * @throws GameException
     */
    public function init(int $count = 10, int $category = 0): bool
    {
        if ($this->inProcess())
            throw new GameException('Игра уже идет');

        $process = new Process(
            sprintf(
                'php -q %s "%s" "%d" "%d"',
                static::SCRIPT_PATH,
                $this->chatId,
                $count,
                $category
            )
        );

        $this->redis->set($this->getProcessKey(), $process->getPid());

        if ($process->getPid() > 0) {
            $this->sendStartGameMessage();
            return true;
        }

        return false;
    }

    /**
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\SystemException
     */
    public function setQuestions(int $count = 10, int $categoryId = 0): void
    {
        $query = new Query(QuizQuestionTable::getEntity());
        $query->setSelect(['question', 'answer']);
        if ($categoryId > 0)
            $query->setFilter(['category_id' => $categoryId]);
        $query->setLimit($count);
        $query->registerRuntimeField(
            'RAND', ['data_type' => 'float', 'expression' => ['RAND()']]
        );
        $query->addOrder('RAND');
        $result = $query->exec();
        $questionsKey = $this->getQuestionsKey();
        $this->questionsCount = $result->getSelectedRowsCount();
        foreach ($result->fetchAll() as $question) {
            $this->redis->rpush($questionsKey, [json_encode($question)]);
        }
    }

    protected function getProcessKey(): string
    {
        return $this->chatId . '_process';
    }

    protected function getQuestionsStatusKey(): string
    {
        return $this->chatId . '_questions_status';
    }

    protected function getQuestionsKey(): string
    {
        return $this->chatId . '_questions';
    }

    protected function getRightAnswersKey(): string
    {
        return $this->chatId . '_right_answers';
    }

    public function getQuestionsCount(): int
    {
        return (int)$this->questionsCount;
    }

    public function inProcess(): bool
    {
        return (bool)$this->redis->exists($this->getProcessKey());
    }

    public function getQuestion(): array
    {
        return json_decode($this->redis->lpop($this->getQuestionsKey()), true);
    }

    public function processQuestion()
    {
        $arQuestion = $this->getQuestion();
        $this->sendStartQuestionMessage(
            $arQuestion['question'],
            $this->getQuestionsCount() - $this->getRemainQuestions()
        );
        $this->startQuestion();
        $time = time(); // время начала вопроса
        $answersKey = $this->getAnswersKey();
        $hasAnswer = false;
        $this->openHintKeys = [];
        while (time() <= ($time + self::ROUNDS * self::ROUND_INTERVAL)) {
            $round = $this->getRound();
            while ($answer = $this->redis->lpop($answersKey)) {
                $answer = json_decode($answer, true);
                if ($answer['text'] == strtolower($arQuestion['answer'])) {
                    $this->addRightAnswer($answer, $round);
                    $this->sendRightAnswerMessage(
                        $arQuestion['answer'],
                        $answer['full_name'],
                        $this->getRoundPoints($round)
                    );
                    $hasAnswer = true;
                    break 2;
                }
            }

            if (time() >= ($time + self::ROUND_INTERVAL * $round) && $round < self::ROUNDS) {
                $this->sendHintMessage(
                    $arQuestion['question'],
                    $this->getHint($arQuestion['answer'], $round)
                );
                $this->nextRound();
            }
        }

        if (!$hasAnswer)
            $this->sendNobodyAnswerMessage($arQuestion['answer']);

        $this->stopQuestion();
    }

    public function sendStartGameMessage()
    {
        $this->bot->sendChatMessage('🟢 <b>Игра начнется через ' . Game::QUESTION_DELAY . ' секунд. Приготовтесь!</b>', true);
    }

    public function sendRightAnswerMessage(string $answer, string $name, int $points)
    {
        $this->bot->sendChatMessage('🟣 <b>Правильно ' . $name . '! Ответ: ' . Str::firstToUp($answer) . '</b>' . PHP_EOL . PHP_EOL . '+' . $points . Str::morph($points, ' очко', ' очка', ' очков') . ' получает ' . $name, true);
    }

    public function sendNobodyAnswerMessage(string $answer)
    {
        $this->bot->sendChatMessage('🔴 <b>Ответ: ' . Str::firstToUp($answer) . '</b>' . PHP_EOL . PHP_EOL . 'К сожалению никто не ответил :(', true);
    }

    public function sendStopGameMessage()
    {
        $this->bot->sendChatMessage('🔴 <b>Игра остановлена</b>', true);
    }

    public function sendStartQuestionMessage(string $question, int $questionNumber)
    {
        $this->bot->sendChatMessage('🔵 <b>Вопрос №' . $questionNumber . '</b>' . PHP_EOL . PHP_EOL . $question, true);
    }

    public function sendHintMessage(string $question, string $hint)
    {
        $this->bot->sendChatMessage('🟡 <b>Подсказка: ' . Str::firstToUp($hint) . '</b>' . PHP_EOL . PHP_EOL . $question, true);
    }

    public function getHint(string $answer, int $round): string
    {
        $answerAr = preg_split('//u', $answer, null, PREG_SPLIT_NO_EMPTY);
        $answerArWithoutSpace = array_diff($answerAr, [' ']);
        $answerLength = count($answerArWithoutSpace);
        $pathStep = round(1 / (static::ROUNDS - 1), 2);
        $path = $round - 1;
        $countOpenLetters = ceil($pathStep * $path * $answerLength);

        if ($countOpenLetters == $answerArWithoutSpace) $countOpenLetters--;

        foreach ($answerArWithoutSpace as $keyLetter => $letter)
            if (in_array($keyLetter, $this->openHintKeys)) unset($answerArWithoutSpace[$keyLetter]);

        $countRandomLetters = $countOpenLetters - count($this->openHintKeys);
        if ($countRandomLetters == 0) {
            $newRandomKeys = [];
        } else if ($countRandomLetters == 1) {
            $newRandomKeys = [array_rand($answerArWithoutSpace, $countRandomLetters)];
        } else {
            $newRandomKeys = array_rand($answerArWithoutSpace, $countRandomLetters);
        }

        $this->openHintKeys = array_merge($this->openHintKeys, $newRandomKeys);

        $hint = '';
        foreach ($answerAr as $key => $letter)
            $hint .= in_array($key, $this->openHintKeys) || $letter === ' ' ? $letter : static::HIDE_SYMBOL;

        return $hint;
    }

    public function hasQuestions(): bool
    {
        return $this->redis->llen($this->getQuestionsKey()) > 0;
    }

    public function getRemainQuestions(): int
    {
        return $this->redis->llen($this->getQuestionsKey());
    }

    public function addRightAnswer(array $answer, int $round)
    {
        $answer['round'] = $round;
        $this->redis->rpush($this->getRightAnswersKey(), [json_encode($answer)]);
    }

    public function getAnswersKey(): string
    {
        return $this->chatId . '_answers';
    }

    protected function getRoundPoints(int $round): int
    {
        return static::ROUNDS - $round + 1;
    }

    public function getRoundKey(): string
    {
        return $this->chatId . '_round';
    }

    public function getRound(): int
    {
        return $this->redis->get($this->getRoundKey());
    }

    public function startQuestion()
    {
        $this->redis->transaction(function ($tx) {
            $tx->set($this->getQuestionsStatusKey(), 1);
            $tx->set($this->getRoundKey(), 1);
        });
    }

    public function stopQuestion()
    {
        $this->redis->transaction(function ($tx) {
            $tx->del($this->getQuestionsStatusKey());
            $tx->del($this->getAnswersKey());
        });
    }

    public function isQuestionInProcess(): bool
    {
        return (bool)$this->redis->exists($this->getQuestionsStatusKey());
    }

    public function nextRound()
    {
        $this->redis->incr($this->getRoundKey());
    }

    public function end()
    {
        $this->redis->del([
            $this->getAnswersKey(),
            $this->getQuestionsKey(),
            $this->getRoundKey(),
            $this->getQuestionsStatusKey(),
            $this->getProcessKey(),
            $this->getRightAnswersKey(),
        ]);
    }

    public function stop(): bool
    {
        $pId = $this->redis->get($this->getProcessKey());

        if ($pId <= 0) return false;

        $process = new Process();
        $process->setPid($pId);
        $process->stop();
        $this->end();
        $this->sendStopGameMessage();

        return true;
    }

    public function saveResults()
    {
        $rightAnswers = $this->redis->lrange($this->getRightAnswersKey(), 0, -1);
        $result = QuizGameTable::add([
            'chat_id' => $this->chatId,
            'rounds_count' => static::ROUNDS,
            'question_delay' => static::QUESTION_DELAY,
            'round_delay' => static::ROUND_INTERVAL,
        ]);

        if (!$result->isSuccess())
            throw new \Exception(
                'Не удалось сохранить игру: ' . implode(PHP_EOL, $result->getErrorMessages())
            );

        $gameId = $result->getId();
        $usersByAnswers = [];
        foreach ($rightAnswers as $rightAnswer) {
            $rightAnswer = json_decode($rightAnswer, true);
            $userId = QuizUserTable::getList(['filter' => ['username' => $rightAnswer['username']]])->fetchRaw()['id'];
            if (!$userId) {
                $userId = QuizUserTable::add([
                    'username' => $rightAnswer['username'],
                    'first_name' => $rightAnswer['first_name'],
                    'last_name' => $rightAnswer['last_name'],
                ])->getId();
            }

            if ($userId > 0)
                $usersByAnswers[$userId][$rightAnswer['round']]++;
        }

        foreach ($usersByAnswers as $userId => $userAnswersByRounds) {
            $result = QuizGameResultTable::add([
                'game_id' => $gameId,
                'user_id' => $userId,
                'round_1' => (int)$userAnswersByRounds[1],
                'round_2' => (int)$userAnswersByRounds[2],
                'round_3' => (int)$userAnswersByRounds[3],
                'round_4' => (int)$userAnswersByRounds[4],
            ]);

            if (!$result->isSuccess())
                throw new \Exception(
                    'Не удалось сохранить результат игры: ' . implode(PHP_EOL, $result->getErrorMessages())
                );
        }
    }

    public function sendResults()
    {
        $rightAnswersByUsers = [];
        $rightAnswers = $this->redis->lrange($this->getRightAnswersKey(), 0, -1);
        $message = '🟠 <b>Итоги игры</b>' . PHP_EOL . PHP_EOL;

        if (!$rightAnswers) {
            $message .= 'Никто не дал хотя бы одного правильного ответа :(';
            $this->bot->sendChatMessage($message, true);
            return;
        }

        foreach ($rightAnswers as $rightAnswer) {
            $rightAnswer = json_decode($rightAnswer, true);
            $rightAnswersByUsers[$rightAnswer['username']]['full_name'] = $rightAnswer['full_name'];
            $rightAnswersByUsers[$rightAnswer['username']]['answers_count']++;
            $rightAnswersByUsers[$rightAnswer['username']]['points'] += $this->getRoundPoints($rightAnswer['round']);
        }

        usort($rightAnswersByUsers, function ($userStat1, $userStat2) {
            if ($userStat1['points'] == $userStat2['points'])
                return $userStat2['answers_count'] <=> $userStat1['answers_count'];

            return $userStat2['points'] <=> $userStat1['points'];
        });

        $i = 1;
        foreach ($rightAnswersByUsers as $userStat) {
            $message .= $i . '. ' . $userStat['full_name'] . ' - ' . $userStat['points'] . Str::morph($userStat['points'], ' очко', ' очка', ' очков') .' ' . '('.$userStat['answers_count'].' '.Str::morph($userStat['answers_count'], ' ответ', ' ответа', ' ответов').')' . PHP_EOL;
            $i++;
        }
        $this->bot->sendChatMessage($message, true);
    }

    public function addAnswer(array $answer)
    {
        $this->redis->rpush($this->getAnswersKey(), [
            json_encode($answer),
        ]);
    }
}