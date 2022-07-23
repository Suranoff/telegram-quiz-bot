<?php

namespace Suran\Quiz\Tables;

use Bitrix\Main;


class QuizGameTable extends Main\Entity\DataManager
{
    /**
     * Returns DB table name for entity.
     *
     * @return string
     */
    public static function getTableName()
    {
        return 'quiz_game';
    }

    /**
     * Returns entity map definition.
     *
     * @return array
     */
    public static function getMap()
    {
        return [
            'id' => new Main\Entity\IntegerField('id', [
                'primary' => true,
                'autocomplete' => true,
            ]),
            'chat_id' => new Main\Entity\StringField('chat_id', [
                'required' => true,
                'validation' => [__CLASS__, 'validateChatId'],
            ]),
            'date_end' => new Main\Entity\DatetimeField('date_end', [
                'required' => true,
                'default_value' => function () {
                    return new Main\Type\DateTime();
                },
            ]),
            'rounds_count' => new Main\Entity\IntegerField('rounds_count', [
                'required' => true,
            ]),
            'question_delay' => new Main\Entity\IntegerField('question_delay', [
                'required' => true,
            ]),
            'round_delay' => new Main\Entity\IntegerField('round_delay', [
                'required' => true,
            ]),
        ];
    }

    /**
     * Returns validators for chat_id field.
     *
     * @return array
     */
    public static function validateChatId()
    {
        return [
            new Main\Entity\Validator\Length(null, 50),
        ];
    }
}