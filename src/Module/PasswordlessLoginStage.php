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

namespace Krabo\LoginWithCodeBundle\Module;

use Contao\FrontendTemplate;
use Contao\MemberModel;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Doctrine\DBAL\Connection;
use Krabo\LoginWithCodeBundle\Service\LoginService;
use NotificationCenter\Model\Notification;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class PasswordlessLoginStage extends AbstractStage {

  private AuthenticationUtils $authenticationUtils;
  private LoginService $loginService;
  private Connection $connection;
  private $expires = 5;

  public function __construct(AuthenticationUtils $authenticationUtils, LoginService $loginService, Connection $connection) {
    $this->authenticationUtils = $authenticationUtils;
    $this->loginService = $loginService;
    $this->connection = $connection;
  }

  public function getBreadCrumbTitle(): string {
    return $this->translate('MSC.krabo_login.login_request_code_breadcrumb');
  }

  public function getHeadline(): string {
    return $this->translate('MSC.krabo_login.login_request_code_headline');
  }

  public function getDescription(): string {
    return sprintf($this->translate('MSC.krabo_login.login_request_code_description'), $this->expires);
  }

  public function getBreadCrumb(): array {
    return [
      'krabo.login.stage.ask_for_email',
    ];
  }

  public function getForm(Request $request, ModuleModel $module): string {
    $template = new FrontendTemplate('passwordless_login_stage');
    $template->email = StringUtil::specialchars($this->authenticationUtils->getLastUsername());
    $template->submitLabel = $this->translate('MSC.krabo_login.login_request_code_submit');
    $function = $request->request->get('function');
    if ($function == 'passwordless_login' || $function == 'resend') {
      $this->sendCode($request, $module->nc_passwordless_notification);
    }
    return $template->parse();
  }

  public function process(Request $request, ModuleModel $module) {
    $this->nextStage = 'krabo.login.stage.passwordless_login';
    $function = $request->request->get('function');
    if ($function === 'change_email') {
      $request->getSession()->set(Security::LAST_USERNAME, NULL);
      $this->nextStage = 'krabo.login.stage.ask_for_email';
      return;
    }
    if ($function === 'resend') {
      $this->messageStatus = 'success';
      $this->message = $this->translate('MSC.krabo_login.code_resend');
      return;
    }
    $username = $this->authenticationUtils->getLastUsername();
    $member = MemberModel::findByUsername($username);
    if (null === $member) {
      $this->message = $this->translate('MSC.krabo_login.email_not_found');
      $this->nextStage = 'krabo.login.stage.ask_for_email';
      return;
    }

    $token = $request->headers->get('X-Krabo-Token');
    if ($token == '') {
      $this->message = $this->translate('MSC.krabo_login.invalid_token');
      return;
    }
    $statement = $this->connection->createQueryBuilder()
      ->select('t.id AS id', 't.member AS member', 't.jumpTo AS jumpTo')
      ->from('tl_member_login_token', 't')
      ->where('t.token =:token')
      ->andWhere('t.expires >=:time')
      ->andWhere('t.member = :member')
      ->setParameter('token', $token)
      ->setParameter('time', time())
      ->setParameter('member', $member->id)
      ->executeQuery()
    ;
    $result = $statement->fetchAssociative();
    if (false === $result) {
      $this->message = $this->translate('MSC.krabo_login.invalid_token');
      return;
    }

    $target = $module->getRelated('jumpTo');
    $targetPath = $target instanceof PageModel ? $target->getAbsoluteUrl() : $request->getRequestUri();
    $request->request->set('_target_path', base64_encode($targetPath));
    $request->request->set('_always_use_target_path', true);

    $response = $this->loginService->authenticatePasswordless($request, $username);
    if ($response === NULL) {
      $this->message = $this->translate('MSC.krabo_login.invalid_token');
      return;
    }
    $this->connection->createQueryBuilder()
      ->delete('tl_member_login_token')
      ->where('id=:id')
      ->setParameter('id', $result['id'])
      ->executeStatement();
    $this->nextStage = 'krabo.login.stage.logged_in';
    $this->response = $response;

    if ($module->krabo_login_show_popup && class_exists('Isotope\Message', true)) {
      \Isotope\Message::addConfirmation($this->translate('MSC.krabo_login.logged_in'));
    }
    if ($module->krabo_login_merged_cart_jumpTo && $this->loginService->needToMergeCart($member->id)) {
      $target = $module->getRelated('krabo_login_merged_cart_jumpTo');
      if ($target instanceof PageModel) {
        $this->response = new RedirectResponse($target->getAbsoluteUrl());
      }
    }
  }

  private function sendCode(Request $request, $notificationId): void {
    $username = $this->authenticationUtils->getLastUsername();
    $member = MemberModel::findByUsername($username);
    $token = $this->generateCode($member, 4);
    $this->connection->createQueryBuilder()
      ->insert('tl_member_login_token')
      ->values([
        'tstamp' => '?',
        'expires' => '?',
        'member' => '?',
        'token' => '?',
        'jumpTo' => '?',
      ])
      ->setParameter(0, time())
      ->setParameter(1, strtotime('+' . $this->expires . ' minutes'))
      ->setParameter(2, $member->id)
      ->setParameter(3, $token)
      ->setParameter(4, '/' . ltrim($request->request->get('_target_path'), '/'))
      ->executeStatement()
    ;

    // Send notification
    $notificationTokens = $this->getNotificationTokens($request, $member, $token);

    /** @var Notification $notification */
    if (null !== $notification = Notification::findByPk($notificationId)) {
      $notification->send($notificationTokens);
    }
  }

  private function getNotificationTokens(Request $request, MemberModel $member, string $token): array
  {
    $notificationTokens = [
      'recipient_email' => $member->email,
      'domain' => $request->getHost(),
      'token' => $token,
    ];

    foreach ($member->row() as $field => $value) {
      $notificationTokens['member_'.$field] = $value;
    }

    return $notificationTokens;
  }

  private function generateCode(MemberModel $data, int $codeLength): string {
    $secret = openssl_random_pseudo_bytes(8);
    $encrypted = hash_hmac('sha1', implode(":", $data->row()), $secret);

    // Convert encrypted data to a X digit code,
    // This code below is taken from HOTP generation algorithm
    $hmac_result = [];
    // Convert to decimal
    foreach (str_split($encrypted, 2) as $hex) {
      $hmac_result[] = hexdec($hex);
    }
    $offset = (int)$hmac_result[count($hmac_result)-1] & 0xf;
    $code = (int)($hmac_result[$offset] & 0x7f) << 24
      | ($hmac_result[$offset+1] & 0xff) << 16
      | ($hmac_result[$offset+2] & 0xff) << 8
      | ($hmac_result[$offset+3] & 0xff);

    $code = $code % pow(10, $codeLength);
    return str_pad($code, $codeLength, "0", STR_PAD_LEFT);
  }

}