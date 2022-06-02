<?php

$config = [
  'admin' => [
    'core:AdminPassword',
  ],
  'example-userpass' => [
    'exampleauth:UserPass',
    'admin:admin' => [
      'uid' => ['admin'],
      'givenName' => ['Admin'],
      'sn' => ['von Admin'],
      'mail' => ['admin.admin@bazooka.com'],
      'groups' => ['admins'],
      'eduPersonPrincipalName' => 'user1@helsinki.fi',
    ],
    'user:user' => [
      'id' => [21311],
      'name' => ['Martti Matikka'],
      'firstname' => ['Martti'],
      'lastname' => ['Matikka'],
      'mail' => ['martti@matikka.fi'],
    ],
  ],
];
