<?php

namespace Suran\Quiz\Tables;

use Bitrix\Main;

class QuizGameResultTable extends Main\Entity\DataManager
{
    /**
     * Returns DB table name for entity.
     *
     * @return string
     */
    public static function getTableName()
    {
        return 'quiz_game_result';
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
            'game_id' => new Main\Entity\IntegerField('game_id', [
                'required' => true,
            ]),
            'game' => new Main\Entity\ReferenceField(
                'game',
                'Suran\Quiz\Tables\QuizGame',
                array('=this.game_id' => 'ref.ID'),
                array('join_type' => 'LEFT')
            ),
            'user_id' => new Main\Entity\IntegerField('user_id', [
                'required' => true,
            ]),
            'user' => new Main\Entity\ReferenceField(
                'game',
                'Suran\Quiz\Tables\QuizUser',
                array('=this.user_id' => 'ref.ID'),
                array('join_type' => 'LEFT')
            ),
            'round_1' => new Main\Entity\IntegerField('round_1'),
            'round_2' => new Main\Entity\IntegerField('round_2'),
            'round_3' => new Main\Entity\IntegerField('round_3'),
            'round_4' => new Main\Entity\IntegerField('round_4'),
        ];
    }
}