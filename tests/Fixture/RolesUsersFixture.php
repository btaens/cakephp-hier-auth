<?php
namespace HierAuth\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class RolesUsersFixture extends TestFixture
{

    /**
     * Fields
     *
     * @var array
     */
    public $fields = [
        'id' => ['type' => 'integer'],
        'user_id' => ['type' => 'integer'],
        'role_id' => ['type' => 'integer'],
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
            'user_id' => 1,
            'role_id' => 1,
        ],
        [
            'user_id' => 2,
            'role_id' => 3,
        ],
    ];
}
