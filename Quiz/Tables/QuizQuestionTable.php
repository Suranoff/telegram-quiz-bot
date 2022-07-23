<?php

namespace Suran\Quiz\Tables;

use Bitrix\Main;

class QuizQuestionTable extends Main\Entity\DataManager
{
    /**
     * Returns DB table name for entity.
     *
     * @return string
     */
    public static function getTableName()
    {
        return 'quiz_question';
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
            'question' => new Main\Entity\StringField('question', [
                'required' => true,
                'validation' => [__CLASS__, 'validateQuestion'],
            ]),
            'answer' => new Main\Entity\StringField('answer', [
                'required' => true,
                'validation' => [__CLASS__, 'validateAnswer'],
            ]),
            'category_id' => new Main\Entity\IntegerField('category_id', [
                'required' => true,
            ]),
            'category' => new Main\Entity\ReferenceField(
                'category',
                'Suran\Quiz\Tables\QuizQuestionCategory',
                ['=this.category_id' => 'ref.ID'],
                ['join_type' => 'LEFT']
            ),
        ];
    }

    /**
     * Returns validators for question field.
     *
     * @return array
     */
    public static function validateQuestion()
    {
        return [
            new Main\Entity\Validator\Length(null, 2000),
            new Main\Entity\Validator\Unique('Вопрос должен быть уникальным'),
        ];
    }

    /**
     * Returns validators for answer field.
     *
     * @return array
     */
    public static function validateAnswer()
    {
        return [
            new Main\Entity\Validator\Length(null, 255),
        ];
    }
}