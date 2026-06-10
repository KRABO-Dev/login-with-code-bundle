<?php

declare(strict_types=1);

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

$GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE'] = array_merge_recursive(
  (array) $GLOBALS['NOTIFICATION_CENTER']['NOTIFICATION_TYPE'],
  [
    'contao' => [
      'krabo_login_code' => [
        'recipients' => ['recipient_email'],
        'email_subject' => ['domain', 'token', 'member_*', 'recipient_email'],
        'email_text' => ['domain', 'token', 'member_*', 'recipient_email'],
        'email_html' => ['domain', 'token', 'member_*', 'recipient_email'],
        'file_name' => ['domain', 'token', 'member_*', 'recipient_email'],
        'file_content' => ['domain', 'token', 'member_*', 'recipient_email'],
        'email_sender_name' => ['recipient_email'],
        'email_sender_address' => ['recipient_email'],
        'email_recipient_cc' => ['recipient_email'],
        'email_recipient_bcc' => ['recipient_email'],
        'email_replyTo' => ['recipient_email'],
      ],
      'krabo_login_account_activation' => array(
        'recipients'           => array('recipient_email'),
        'email_subject'        => array('domain', 'link', 'activation', 'member_*', 'recipient_email'),
        'email_text'           => array('domain', 'link', 'activation', 'member_*', 'recipient_email'),
        'email_html'           => array('domain', 'link', 'activation', 'member_*', 'recipient_email'),
        'file_name'            => array('domain', 'link', 'activation', 'member_*', 'recipient_email'),
        'file_content'         => array('domain', 'link', 'activation', 'member_*', 'recipient_email'),
        'email_sender_name'    => array('recipient_email'),
        'email_sender_address' => array('recipient_email'),
        'email_recipient_cc'   => array('recipient_email'),
        'email_recipient_bcc'  => array('recipient_email'),
        'email_replyTo'        => array('recipient_email'),
      )
    ],
  ]
);