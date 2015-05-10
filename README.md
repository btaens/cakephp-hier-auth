[![Build Status](https://travis-ci.org/btaens/cakephp-hier-auth.svg?branch=master)](https://travis-ci.org/btaens/cakephp-hier-auth)
[![Coverage Status](https://coveralls.io/repos/btaens/cakephp-hier-auth/badge.svg?branch=master)](https://coveralls.io/r/btaens/cakephp-hier-auth?branch=master)

# HierAuth

HierAuth is a simple, hierarchical ACL authorization plugin for CakePHP 3. You can grant and deny access based on roles, and create virtual ones to include sub-roles.

## Installing

Using composer, install the plugin:

    composer require btaens/cakephp-hier-auth

Insert the following line into your ``config/bootstrap.php`` file:

    Plugin::load('HierAuth');

## Setup

Load and configure HierAuth through AuthComponent:

```php
$this->loadComponent('Auth', [
    'authorize' => [
        'HierAuth.Hier' => [
            'hierarchyFile' => 'hierarchy.yml',
            'aclFile' => 'acl.yml',
            'roleColumn' => false,
            'roleKeys' => [
                'roles' => [
                    'multi' => true,
                    'column' => 'label',
                ],
            ],
        ],
    ],
]);
```

### Hierarchy and ACL
``hierarchyFile`` is a [YAML](http://yaml.org) file in which you will define the hierarchy of your roles. Put this in your ``config`` directory.
The basic structure of it is the following:

```yaml
hierarchy:
   ROOT:
       - DEVELOPER
       - OWNER
   MODERATOR:
       - FINANCE
       - LABOR
   ADMIN:
       - @MODERATOR
       - SALES
       - CONTACT
   USER:
       - MEMBER
```

Here you have defined a ROOT role. ROOT doesn't necessarily have to be an actual role your users have, however any users with
the role DEVELOPER or OWNER will be granted access to any route that ROOT has access to.

ADMIN also includes @MODERATOR. This means that all roles in MODERATOR will have all access (or deny) rights of ADMIN.
You can do this recursively (up to 10 depth) as well, so one role could include another, which in turn could also
include another.

Not all roles need to be written into your hierarchy file if you don't need to setup a hierarchy for them, you can also
grant and deny access to roles not listed here.

You can also include existing roles, and give them the access rights of other existing roles:

```yaml
hierarchy:
    SALES:
        - CONTACT
```

In this case, all your users with the CONTACT role would get all the access rights of your SALES role.

``aclFile`` is a [YAML](http://yaml.org) file in which you will grant or deny access to your routes. Put this in your ``config`` directory.
The basic structure is the following:

```yaml
controllers:
    ALL: [ROOT, ADMIN]
    Posts:
        ALL: [MODERATOR, -ADMIN, CONTACT]
        index: [USER, NEWBIE]
```

The controllers you wish to define access rights to sit under a ``controllers`` key.
The ``ALL`` key (all caps) defines access to all sub-members (the one under ``controllers`` to all controllers, the ones under
the individual controllers to all its actions (even the ones not listed).

According to this example, ROOT and ADMIN are by default granted access to all controllers and actions (even the ones not listed).

All actions of Posts get access by MODERATOR, however all actions in Posts are denied to ADMIN (- signifies access denial), except CONTACT, who
is granted access as well. From this, you can see order matters (``[CONTACT, -ADMIN]`` would've meant CONTACT is denied, as that role is later set to denied
as it's part of ADMIN, however ``[-ADMIN, CONTACT]`` grants access to CONTACT, as first we denied it to all in ADMIN, but then granted it to CONTACT).

### Table setup

HierAuth can get your user's roles from multiple tables, all of which can be associated through hasMany or hasAndBelongsToMany,
or even a column of the User table itself, as a JSON field.

If you're using one of your user table's column for your users' role setup, you'll have to save the roles JSON encoded, or
return them through dataType manipulation in the form of an array (so a user with DEVELOPER and MEMBER roles would have, say,
a roles column with a value of: ``["DEVELOPER","MEMBER"]``.
Then you'd set ``roleColumn`` in the above config to be the name of the column: ``'roleColumn' => 'roles'``.

A more recommended way however is to store your roles in a seperate table, and associate it with your users' table.
Pass in the associations you'd like to use in the ``roleKeys`` config key, in the following manner:

```php
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
```

For each association, you have to provide whether the user has multiple or single ones through the ``multi`` key, which is
either true or false.
The ``column`` key is the column in the table from which HierAuth reads the role's label (the one you write an your acl and hirarchy
configuration). This can be 'id', however, a more verbose unique column is recommended for readability (and maintainability,
if you later want to move your database and the role table happens to start with a different id, you have to rewrite your entire
ACL roles configuration).

Whichever associations you choose to define, you also have to make sure they get saved in the session when the user logs in
through whichever authentication method you use. Make sure you put them in ``contain`` inside your authentication configuration:

```php
$this->loadComponent('Auth', [
    'authenticate' => [
        'Form' => [
            'contain' => [
                'Roles',
                'Right',
            ],
        ],
    ],
]);
```

### Requirements
- PHP >= 5.4.16
- CakePHP >= 3.0
- Symfony/YAML >= 2.6

### Future plans
- Create a component to check whether a user's role passes a certain super-role
- Create a helper as above

Inspired by dereuromark's [TinyAuth](https://github.com/dereuromark/cakephp-tinyauth).