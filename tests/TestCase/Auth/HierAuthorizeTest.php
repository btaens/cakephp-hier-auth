<?php
namespace HierAuth\Test\TestCase\Auth;

use Cake\Cache\Cache;
use Cake\Controller\ComponentRegistry;
use Cake\Core\Exception\Exception;
use Cake\Network\Request;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use HierAuth\Auth\HierAuthorize;

/**
 * @property Request $request
 * @property ComponentRegistry $registry
 * @property Table $Users
 */
class HierAuthorizeTest extends TestCase
{

    public $fixtures = [
        'plugin.hier_auth.users',
        'plugin.hier_auth.roles',
        'plugin.hier_auth.rights',
        'plugin.hier_auth.roles_users',
    ];

    public $request;
    public $response;
    public $registry;

    public $Users;

    /**
     * Setup the test case, backup the static object values so they can be restored.
     * Specifically backs up the contents of Configure and paths in App if they have
     * not already been backed up.
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        Cache::clear(false);

        $this->request = new Request();
        $this->registry = new ComponentRegistry();

        $tableRegistry = new TableRegistry();
        $this->Users = $tableRegistry->get('Users');

        $this->Users->belongsToMany('Roles', [
            'foreignKey' => 'user_id',
            'targetForeignKey' => 'role_id',
            'joinTable' => 'roles_users',
        ]);
        $this->Users->belongsTo('Rights', [
            'foreignKey' => 'right_id',
        ]);

        $malformedHierarchy = "hierarchy:\nmalformed";

        file_put_contents(CONFIG . 'hierarchy.malformed.yml', $malformedHierarchy);

        $missingKeyHierarchy = "ROOT:\nADMIN:";

        file_put_contents(CONFIG . 'hierarchy.nokey.yml', $missingKeyHierarchy);

        $malformedAcl = "controllers:\nmalformed";

        file_put_contents(CONFIG . 'acl.malformed.yml', $malformedAcl);

        $missingKeyAcl = "users:\nPosts:";

        file_put_contents(CONFIG . 'acl.nokey.yml', $missingKeyAcl);

        $missingReferenceHierarchy = "hierarchy:\n    ROOT:\n        - @MISSING_ROLE\n    ADMIN:";

        file_put_contents(CONFIG . 'hierarchy.missingref.yml', $missingReferenceHierarchy);

        $recursionHierarchy = "hierarchy:\n    ROOT:\n        - @ADMIN\n        - NEWBIE\n    ADMIN:\n        - @ROOT\n        - MEMBER";

        file_put_contents(CONFIG . 'hierarchy.recursion.yml', $recursionHierarchy);
    }

    /**
     * teardown any static object changes and restore them.
     *
     * @return void
     */
    public function tearDown()
    {
        unlink(CONFIG . 'hierarchy.malformed.yml');
        unlink(CONFIG . 'hierarchy.nokey.yml');
        unlink(CONFIG . 'hierarchy.missingref.yml');
        unlink(CONFIG . 'hierarchy.recursion.yml');
        unlink(CONFIG . 'acl.malformed.yml');
        unlink(CONFIG . 'acl.nokey.yml');

        Cache::clear(false);
        parent::tearDown();
    }

    public function testConstructor()
    {
        $config = [
            'hierarchyFile' => 'hierarchy.sample.yml',
            'aclFile' => 'acl.sample.yml',
            'roleColumn' => false,
            'roleKeys' => [
                'roles' => [
                    'multi' => true,
                    'column' => 'label',
                ],
                'right' => [
                    'multi' => false,
                    'column' => 'label',
                ],
            ],
        ];

        $hyAuth = new HierAuthorize($this->registry, $config);
        $this->assertEquals($config['hierarchyFile'], $hyAuth->config('hierarchyFile'));
    }

    public function testMissingAclException()
    {
        Cache::clear(false);
        $this->setExpectedException('Exception');

        $config = [
            'hierarchyFile' => 'hierarchy.sample.yml',
            'aclFile' => 'acl.missing.yml',
            'roleColumn' => false,
        ];
        $hyAuth = new HierAuthorize($this->registry, $config);

        throw new Exception('No missing hierarchy exception was thrown.');
    }

    public function testMissingHierarchyException()
    {
        Cache::clear(false);
        $this->setExpectedException('Exception');

        $config = [
            'hierarchyFile' => 'hierarchy.missing.yml',
            'aclFile' => 'acl.sample.yml',
            'roleColumn' => false,
        ];
        $hyAuth = new HierAuthorize($this->registry, $config);
    }

    public function testMalformedHierarchyFileException()
    {
        Cache::clear(false);
        $this->setExpectedException('Exception');

        $config = [
            'hierarchyFile' => 'hierarchy.malformed.yml',
            'aclFile' => 'acl.sample.yml',
            'roleColumn' => false,
        ];
        $hyAuth = new HierAuthorize($this->registry, $config);
    }

    public function testMalformedAclFileException()
    {
        Cache::clear(false);
        $this->setExpectedException('Exception');

        $config = [
            'hierarchyFile' => 'hierarchy.sample.yml',
            'aclFile' => 'acl.malformed.yml',
            'roleColumn' => false,
        ];
        $hyAuth = new HierAuthorize($this->registry, $config);
    }

    public function testNoMainKeyHierarchyFileException()
    {
        Cache::clear(false);
        $this->setExpectedException('Exception');

        $config = [
            'hierarchyFile' => 'hierarchy.nokey.yml',
            'aclFile' => 'acl.sample.yml',
            'roleColumn' => false,
        ];
        $hyAuth = new HierAuthorize($this->registry, $config);
    }

    public function testNoMainKeyAclFileException()
    {
        Cache::clear(false);
        $this->setExpectedException('Exception');

        $config = [
            'hierarchyFile' => 'hierarchy.sample.yml',
            'aclFile' => 'acl.nokey.yml',
            'roleColumn' => false,
        ];
        $hyAuth = new HierAuthorize($this->registry, $config);
    }

    public function testMissingRefHierarchyFileException()
    {
        Cache::clear(false);
        $this->setExpectedException('Exception');

        $config = [
            'hierarchyFile' => 'hierarchy.missingref.yml',
            'aclFile' => 'acl.sample.yml',
            'roleColumn' => false,
        ];
        $hyAuth = new HierAuthorize($this->registry, $config);
    }

    public function testRecursionHierarchyFileException()
    {
        Cache::clear(false);
        $this->setExpectedException('Exception');

        $config = [
            'hierarchyFile' => 'hierarchy.recursion.yml',
            'aclFile' => 'acl.sample.yml',
            'roleColumn' => false,
        ];
        $hyAuth = new HierAuthorize($this->registry, $config);
    }

    public function testMultiRoleAuthorize()
    {
        $config = [
            'hierarchyFile' => 'hierarchy.sample.yml',
            'aclFile' => 'acl.sample.yml',
            'roleColumn' => false,
            'roleKeys' => [
                'roles' => [
                    'multi' => true,
                    'column' => 'label',
                ],
                'right' => [
                    'multi' => false,
                    'column' => 'label',
                ],
            ],
        ];

        $hyAuth = new HierAuthorize($this->registry, $config);

        $this->request->params['controller'] = 'Secrets';
        $this->request->params['action'] = 'view';
        $user = $this->_getUser(1);

        $access = $hyAuth->authorize($user, $this->request);
        $this->assertTrue($access);

        if (Cache::read('hierarchy_auth_cache')) {
            $this->assertTrue(true);
        } else {
            $this->assertTrue(false);
        }

        $this->request->params['controller'] = 'Secrets';
        $this->request->params['action'] = 'view';
        $user = $this->_getUser(3);

        $access = $hyAuth->authorize($user, $this->request);
        $this->assertFalse($access);
    }

    /**
     * @param $id
     * @return mixed
     */
    protected function _getUser($id)
    {
        return $this->Users->find()->where(['Users.id' => $id])->contain(['Roles', 'Rights'])->first()->toArray();
    }

    public function testColumnRoleAuthorize()
    {
        $config = [
            'hierarchyFile' => 'hierarchy.sample.yml',
            'aclFile' => 'acl.sample.yml',
            'roleColumn' => 'col_roles',
        ];

        $hyAuth = new HierAuthorize($this->registry, $config);

        $this->request->params['controller'] = 'Posts';
        $this->request->params['action'] = 'index';
        $user = $this->_getUser(4);

        $access = $hyAuth->authorize($user, $this->request);
        $this->assertTrue($access);
    }

    public function testMissingRoleColumn()
    {
        $this->setExpectedException('Exception');
        $config = [
            'hierarchyFile' => 'hierarchy.sample.yml',
            'aclFile' => 'acl.sample.yml',
            'roleColumn' => 'missing_roles',
        ];

        $hyAuth = new HierAuthorize($this->registry, $config);

        $this->request->params['controller'] = 'Posts';
        $this->request->params['action'] = 'index';
        $user = $this->_getUser(4);

        $access = $hyAuth->authorize($user, $this->request);
    }

    public function testMalformedRoleColumn()
    {
        $this->setExpectedException('Exception');
        $config = [
            'hierarchyFile' => 'hierarchy.sample.yml',
            'aclFile' => 'acl.sample.yml',
            'roleColumn' => 'col_roles',
        ];

        $hyAuth = new HierAuthorize($this->registry, $config);

        $this->request->params['controller'] = 'Posts';
        $this->request->params['action'] = 'index';
        $user = $this->_getUser(5);

        $access = $hyAuth->authorize($user, $this->request);
    }

    public function testMalformedRoleKeysConfig()
    {
        $this->setExpectedException('Exception');
        $config = [
            'hierarchyFile' => 'hierarchy.sample.yml',
            'aclFile' => 'acl.sample.yml',
            'roleColumn' => false,
            'roleKeys' => "malformed",
        ];

        $hyAuth = new HierAuthorize($this->registry, $config);

        $this->request->params['controller'] = 'Posts';
        $this->request->params['action'] = 'index';
        $user = $this->_getUser(3);

        $access = $hyAuth->authorize($user, $this->request);
    }

    public function testMissingRoleKeysConfig()
    {
        $this->setExpectedException('Exception');
        $config = [
            'hierarchyFile' => 'hierarchy.sample.yml',
            'aclFile' => 'acl.sample.yml',
            'roleColumn' => false,
            'roleKeys' => [
                'roles' => [
                    'multi' => true,
                    'column' => 'label',
                ],
                'missing_rights' => [
                    'multi' => false,
                    'column' => 'label',
                ],
            ],
        ];

        $hyAuth = new HierAuthorize($this->registry, $config);

        $this->request->params['controller'] = 'Posts';
        $this->request->params['action'] = 'index';
        $user = $this->_getUser(3);

        $access = $hyAuth->authorize($user, $this->request);
    }

    public function testMissingMultiRoleKeysConfig()
    {
        $this->setExpectedException('Exception');
        $config = [
            'hierarchyFile' => 'hierarchy.sample.yml',
            'aclFile' => 'acl.sample.yml',
            'roleColumn' => false,
            'roleKeys' => [
                'missing_roles' => [
                    'multi' => true,
                    'column' => 'label',
                ],
                'right' => [
                    'multi' => false,
                    'column' => 'label',
                ],
            ],
        ];

        $hyAuth = new HierAuthorize($this->registry, $config);

        $this->request->params['controller'] = 'Posts';
        $this->request->params['action'] = 'index';
        $user = $this->_getUser(3);

        $access = $hyAuth->authorize($user, $this->request);
    }

    public function testMissingMultiRoleKeysColumnConfig()
    {
        $this->setExpectedException('Exception');
        $config = [
            'hierarchyFile' => 'hierarchy.sample.yml',
            'aclFile' => 'acl.sample.yml',
            'roleColumn' => false,
            'roleKeys' => [
                'roles' => [
                    'multi' => true,
                    'column' => 'missing_label',
                ],
                'right' => [
                    'multi' => false,
                    'column' => 'label',
                ],
            ],
        ];

        $hyAuth = new HierAuthorize($this->registry, $config);

        $this->request->params['controller'] = 'Posts';
        $this->request->params['action'] = 'index';
        $user = $this->_getUser(2);

        $access = $hyAuth->authorize($user, $this->request);
    }

    public function testMissingColumnRoleKeysConfig()
    {
        $this->setExpectedException('Exception');
        $config = [
            'hierarchyFile' => 'hierarchy.sample.yml',
            'aclFile' => 'acl.sample.yml',
            'roleColumn' => false,
            'roleKeys' => [
                'roles' => [
                    'multi' => true,
                    'column' => 'label',
                ],
                'right' => [
                    'multi' => false,
                    'column' => 'missing_label',
                ],
            ],
        ];

        $hyAuth = new HierAuthorize($this->registry, $config);

        $this->request->params['controller'] = 'Posts';
        $this->request->params['action'] = 'index';
        $user = $this->_getUser(3);

        $access = $hyAuth->authorize($user, $this->request);
    }
}
