<?php
/**
 * Copyright (C) 2026  Jaap Jansma (jaap.jansma@civicoop.org)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

$GLOBALS['TL_DCA']['tl_member_login_token'] = [
  'config' => [
    'sql' => [
      'keys' => [
        'id' => 'primary',
        'member' => 'index',
        'token' => 'unique',
      ],
    ],
  ],
  'fields' => [
    'id' => [
      'sql' => 'int(10) unsigned NOT NULL auto_increment',
    ],
    'member' => [
      'sql' => "int(10) unsigned NOT NULL default '0'",
    ],
    'token' => [
      'sql' => "varchar(255) NOT NULL default ''",
    ],
    'tstamp' => [
      'sql' => "int(10) unsigned NOT NULL default '0'",
    ],
    'expires' => [
      'sql' => "int(10) unsigned NOT NULL default '0'",
    ],
    'jumpTo' => [
      'sql' => "varchar(255) NOT NULL default ''",
    ],
  ],
];