<?php
namespace HierAuth\Auth;

use Cake\Auth\BaseAuthorize;
use Cake\Cache\Cache;
use Cake\Controller\ComponentRegistry;
use Cake\Core\Exception\Exception;
use Cake\Network\Request;
use Symfony\Component\Yaml\Yaml;

/**
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
class HierAuthorize extends BaseAuthorize
{

    protected $_denySign = '-';
    protected $_referenceSign = '@';
    protected $_allSign = 'ALL';

    protected $_hierarchy;
    protected $_acl;

    protected $_rootHierarchy;

    protected $_defaultConfig = [
        'hierarchyFile' => 'hierarchy.yml',
        'aclFile' => 'acl.yml',
        'roleColumn' => 'roles',
    ];

    /**
     * @param ComponentRegistry $registry The controller for this request.
     * @param array $config An array of config. This class does not use any config.
     */
    public function __construct(ComponentRegistry $registry, array $config = [])
    {
        parent::__construct($registry, $config);

        if (!file_exists(CONFIG . $this->config('hierarchyFile'))) {
            throw new Exception(
                sprintf("Provided hierarchy config file %s doesn't exist.", $this->config('hierarchyFile'))
            );
        }

        if (!file_exists(CONFIG . $this->config('aclFile'))) {
            throw new Exception(sprintf("Provided ACL config file %s doesn't exist.", $this->config('aclFile')));
        }

        // caching
        $hierarchyModified = filemtime(CONFIG . $this->config('hierarchyFile'));
        $aclModified = filemtime(CONFIG . $this->config('aclFile'));

        $lastModified = ($hierarchyModified > $aclModified) ? $hierarchyModified : $aclModified;

        if (Cache::read('hierarchy_auth_build_time') < $lastModified) {
            $this->_hierarchy = $this->_getHierarchy();
            $this->_acl = $this->_getAcl();

            Cache::write('hierarchy_auth_cache', ['acl' => $this->_acl, 'hierarchy' => $this->_hierarchy]);
            Cache::write('hierarchy_auth_build_time', time());
        } else {
            $cache = Cache::read('hierarchy_auth_cache');
            $this->_hierarchy = $cache['hierarchy'];
            $this->_acl = $cache['acl'];
        }
    }

    /**
     * @param array $user Active user data
     * @param Request $request Request instance.
     * @return bool
     */
    public function authorize($user, Request $request)
    {
        $controller = $request->param('controller');
        $action = $request->param('action');

        return $this->validate($user, $controller, $action);
    }

    /**
     * Authorize user based on controller, and action
     *
     * @param array $user Active user data
     * @param string $controller Controller to validate
     * @param string $action Action to validate
     * @return bool
     */
    public function validate($user, $controller, $action)
    {
        $userRoles = $this->_getRoles($user);
        $authRoles = [];

        if (isset($this->_acl[$this->_allSign])) {
            $authRoles = $this->_acl[$this->_allSign];
        }

        if (isset($this->_acl[$controller][$this->_allSign])) {
            $authRoles = array_merge($this->_acl[$controller][$this->_allSign], $authRoles);
        }

        if (isset($this->_acl[$controller][$action])) {
            $authRoles = array_merge($this->_acl[$controller][$action], $authRoles);
        }

        foreach ($userRoles as $userRole) {
            if (isset($authRoles[$userRole]) && $authRoles[$userRole]) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retrieve and parse hierarchy configuration
     *
     * @return array
     */
    protected function _getHierarchy()
    {
        $file = $this->config('hierarchyFile');

        $yaml = file_get_contents(CONFIG . $file);
        try {
            $yaml = Yaml::parse($yaml);
        } catch (\Exception $e) {
            throw new Exception(sprintf('Malformed hierarchy config file, check YAML syntax: %s', $e->getMessage()));
        }

        if (!isset($yaml['hierarchy'])) {
            throw new Exception("The hierarchy configuration must be under key \"hierarchy\". No such key was found.");
        }

        $hierarchy = $this->_parseHierarchy($yaml['hierarchy']);

        return $hierarchy;
    }

    /**
     * Parses and flattens hierarchy settings.
     *
     * @param array $hierarchy Hierarchy settings as an array.
     * @param int $recLevel Recursion level.
     * @return array
     */

    /**
     * @param array $hierarchy An array of the hierarchy data
     * @param int $recLevel Recursion level
     * @return array
     * @throws Exception
     */
    protected function _parseHierarchy(array $hierarchy, $recLevel = 0)
    {
        if (!isset($this->_rootHierarchy)) {
            $this->_rootHierarchy = $hierarchy;
        }

        $offset = 0;
        foreach ($hierarchy as $key => $subRole) {
            // recursively go through roles
            if (is_array($subRole)) {
                $subRole = $this->_parseHierarchy($subRole);
                $hierarchy[$key] = array_unique($subRole); // remove duplicate roles
            } else {
                // flatten references
                if (substr($subRole, 0, strlen($this->_referenceSign)) == $this->_referenceSign) {
                    // check if reference is valid
                    if (!isset($this->_rootHierarchy[substr($subRole, strlen($this->_referenceSign))])) {
                        throw new Exception(sprintf("A reference in hierarchy doesn't exist: %s", $subRole));
                    }

                    // recursion protection
                    if ($recLevel >= 10) {
                        throw new Exception(sprintf("Recursion occured. Check reference: %s", $subRole));
                    }

                    // replace reference with referenced roles
                    $subRole = $this->_parseHierarchy(
                        $this->_rootHierarchy[substr($subRole, strlen($this->_referenceSign))],
                        ++$recLevel
                    );
                    array_splice($hierarchy, $offset, 1, $subRole);
                }
            }
            $offset++;
        }

        return $hierarchy;
    }

    /**
     * Retrieve and parse acl configuration
     *
     * @return array
     */
    protected function _getAcl()
    {
        $file = $this->config('aclFile');

        $yaml = file_get_contents(CONFIG . $file);
        try {
            $yaml = Yaml::parse($yaml);
        } catch (\Exception $e) {
            throw new Exception(sprintf('Malformed acl configuration file. Check syntax: %s', $e->getMessage()));
        }

        if (!isset($yaml['controllers'])) {
            throw new Exception('The ACL configuration must be under key \"controllers\". No such key was found.');
        }

        return $this->_parseAcl($yaml['controllers']);
    }


    /**
     * Parses ACL configuration
     *
     * @param array $acl Acl configuration in array format
     * @return array
     */
    protected function _parseAcl(array $acl)
    {
        $parsedAcl = [];

        // check global ACL access rights
        if (isset($acl[$this->_allSign])) {
            $parsedAcl[$this->_allSign] = $this->_iterateAccessRights($acl[$this->_allSign]);
            unset($acl[$this->_allSign]);
        }

        // iterate through controllers and format role authorization
        foreach ($acl as $controller => $actions) {
            $parsedAcl[$controller] = [];
            // check controller-wide access rights
            if (isset($actions[$this->_allSign])) {
                $parsedAcl[$controller][$this->_allSign] = $this->_iterateAccessRights($actions[$this->_allSign]);
                unset($actions[$this->_allSign]);
            }

            // check controller actions' access rights
            foreach ($actions as $action => $roles) {
                $parsedAcl[$controller][$action] = $this->_iterateAccessRights($roles);
            }
        }

        return $parsedAcl;
    }

    /**
     * Helper function
     * Convert YAML config access rights
     *
     * @param array $yamlRoles Array of roles to iterate through
     * @return array
     */
    protected function _iterateAccessRights(array $yamlRoles)
    {
        $checkedRoles = [];

        foreach ($yamlRoles as $role) {
            if (substr($role, 0, strlen($this->_denySign)) == $this->_denySign) {
                $checkedRoles = array_merge(
                    $checkedRoles,
                    $this->_flattenSuperRole(substr($role, strlen($this->_denySign)), false)
                );
            } else {
                $checkedRoles = array_merge($checkedRoles, $this->_flattenSuperRole($role, true));
            }
        }

        return $checkedRoles;
    }

    /**
     * Helper function
     * Check and return roles belonging to a super role with super role's access rights
     *
     * @param string $role Referenced role to flatten
     * @param bool $authorized Super role is authorized or not
     * @return array
     */
    protected function _flattenSuperRole($role, $authorized)
    {
        if (isset($this->_hierarchy[$role])) {
            $roles = [];
            foreach ($this->_hierarchy[$role] as $subRole) {
                $roles[$subRole] = $authorized;
            }
            $roles[$role] = $authorized;
        } else {
            $roles = [$role => $authorized];
        }

        return $roles;
    }

    /**
     * Retrieve role labels based on configuration.
     *
     * @param array $user Active user data
     * @return bool|array
     */
    protected function _getRoles(array $user)
    {
        // check if json column based authentication
        if ($this->config('roleColumn')) {
            if (!isset($user[$this->config('roleColumn')])) {
                throw new Exception(
                    sprintf('Provided roleColumn "%s" doesn\'t exist for this user.', $this->config('roleColumn'))
                );
            }

            $roles = $user[$this->config('roleColumn')];
            // check if column is received already decoded, if not, json decode.
            if (!is_array($roles)) {
                $roles = json_decode($roles, true);
                if (!is_array($roles)) {
                    throw new Exception(
                        sprintf('roleColumn "%s" is not in a valid format.', $this->config('roleColumn'))
                    );
                }
            }
        } else {
            $roleKeys = $this->config('roleKeys');

            // check if role keys are configured correctly
            if (!is_array($roleKeys)) {
                throw new Exception('roleKeys must be an array.');
            }

            $roles = [];
            // collect all roles based on configuration, from provided associations
            foreach ($roleKeys as $role => $settings) {
                // check if multiple roles or single role per user
                if (!empty($settings['multi'])) {
                    if (!isset($user[$role])) {
                        throw new Exception(sprintf('Provided association %s doesn\'t exist.', $role));
                    }

                    foreach ($user[$role] as $userRole) {
                        if (!isset($userRole[$settings['column']])) {
                            throw new Exception(
                                sprintf(
                                    'Provided column %s with association %s doesn\'t exist.',
                                    $settings['column'],
                                    $role
                                )
                            );
                        }

                        $roles[] = $userRole[$settings['column']];
                    }
                } else {
                    if (!isset($user[$role]) || !isset($user[$role][$settings['column']])) {
                        throw new Exception(
                            sprintf(
                                'Provided column %s with association %s doesn\'t exist.',
                                $settings['column'],
                                $role
                            )
                        );
                    }

                    $roles[] = $user[$role][$settings['column']];
                }
            }
        }

        return $roles;
    }
}
