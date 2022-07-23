<?php

namespace Suran\Quiz\Tables;

use Bitrix\Main;

class QuizUserTable extends Main\Entity\DataManager
{
    /**
     * Returns DB table name for entity.
     *
     * @return string
     */
    public static function getTableName()
    {
        return 'quiz_user';
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
            'username' => new Main\Entity\StringField('username', [
                'required' => true,
                'validation' => [__CLASS__, 'validateUsername'],
            ]),
            'first_name' => new Main\Entity\StringField('first_name', [
                'validation' => [__CLASS__, 'validateFirstName'],
            ]),
            'last_name' => new Main\Entity\StringField('last_name', [
                'validation' => [__CLASS__, 'validateLastName'],
            ]),
        ];
    }

    /**
     * Returns validators for username field.
     *
     * @return array
     */
    public static function validateUsername()
    {
        return [
            new Main\Entity\Validator\Length(null, 250),
        ];
    }

    /**
     * Returns validators for first_name field.
     *
     * @return array
     */
    public static function validateFirstName()
    {
        return [
            new Main\Entity\Validator\Length(null, 250),
        ];
    }

    /**
     * Returns validators for last_name field.
     *
     * @return array
     */
    public static function validateLastName()
    {
        return [
            new Main\Entity\Validator\Length(null, 250),
        ];
    }
}