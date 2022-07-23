<?php

namespace Suran\Quiz\Tables;

use Bitrix\Main;

class QuizQuestionCategoryTable extends Main\Entity\DataManager
{
    /**
     * Returns DB table name for entity.
     *
     * @return string
     */
    public static function getTableName()
    {
        return 'quiz_question_category';
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
            'name' => new Main\Entity\StringField('name', [
                'required' => true,
                'validation' => [__CLASS__, 'validateName'],
            ]),
            'code' => new Main\Entity\StringField('code', [
                'required' => true,
                'validation' => [__CLASS__, 'validateCode'],
            ]),
        ];
    }

    /**
     * Returns validators for name field.
     *
     * @return array
     */
    public static function validateName()
    {
        return [
            new Main\Entity\Validator\Length(null, 255),
        ];
    }

    /**
     * Returns validators for code field.
     *
     * @return array
     */
    public static function validateCode()
    {
        return [
            new Main\Entity\Validator\Length(null, 50),
        ];
    }
}