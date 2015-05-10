<?php
namespace HierAuth\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class RightsFixture extends TestFixture
{

    /**
     * Fields
     *
     * @var array
     */
    public $fields = [
        'id' => ['type' => 'integer'],
        'label' => ['type' => 'string', 'length' => 20],
        '_constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
        ],
        '_options' => [
            'engine' => 'InnoDB', 'collation' => 'utf8_general_ci'
        ],
    ];

    /**
     * Records
     *
     * @var array
     */
    public $records = [
        [
            'id' => 1,
            'label' => 'SENIOR',
        ],
        [
            'id' => 2,
            'label' => 'JUNIOR',
        ],
    ];
}
