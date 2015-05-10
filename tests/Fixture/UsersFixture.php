<?php
namespace HierAuth\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class UsersFixture extends TestFixture
{

    /**
     * Fields
     *
     * @var array
     */
    public $fields = [
        'id' => ['type' => 'integer'],
        'right_id' => ['type' => 'integer'],
        'username' => ['type' => 'string', 'length' => 20],
        'password' => ['type' => 'string', 'length' => 255],
        'col_roles' => ['type' => 'string', 'length' => 255],
        '_constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
            'username' => ['type' => 'unique', 'columns' => ['username']]
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
            'username' => 'Bob',
            'password' => 'bob',
            'right_id' => 1,
        ],
        [
            'id' => 2,
            'username' => 'Alice',
            'password' => 'alice',
            'right_id' => 2,
        ],
        [
            'id' => 3,
            'username' => 'Jim',
            'password' => 'jim',
            'right_id' => 2,
        ],
        [
            'id' => 4,
            'username' => 'Henry',
            'password' => 'henry',
            'col_roles' => '["CONTACT", "MEMBER"]',
        ],
        [
            'id' => 5,
            'username' => 'Malformed',
            'password' => 'malformed',
            'col_roles' => 'malformed roles column',
        ],
    ];
}
