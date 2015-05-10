<?php
namespace HierAuth\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class RolesFixture extends TestFixture
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
            'label' => 'DEVELOPER',
        ],
        [
            'id' => 2,
            'label' => 'SALES',
        ],
        [
            'id' => 3,
            'label' => 'CONTACT',
        ],
        [
            'id' => 4,
            'label' => 'FINANCE',
        ],
        [
            'id' => 5,
            'label' => 'LABOR',
        ],
        [
            'id' => 6,
            'label' => 'MEMBER',
        ],
        [
            'id' => 7,
            'label' => 'NEWBIE',
        ],
    ];
}
