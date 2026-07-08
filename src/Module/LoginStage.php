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
use Contao\System;
use Krabo\LoginWithCodeBundle\Service\LoginService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Contao\CoreBundle\Monolog\ContaoContext;
use Psr\Log\LoggerInterface;

class LoginStage extends AbstractStage {

  private AuthenticationUtils $authenticationUtils;
  private LoginService $loginService;
  private LoggerInterface $logger;

  public function __construct(AuthenticationUtils $authenticationUtils, LoginService $loginService, LoggerInterface $logger) {
    $this->authenticationUtils = $authenticationUtils;
    $this->loginService = $loginService;
    $this->logger = $logger;
  }

  public function getBreadCrumbTitle(): string {
    return $this->translate('MSC.krabo_login.login_breadcrumb');
  }

  public function getHeadline(): string {
    return $this->translate('MSC.krabo_login.login_headline');
  }

  public function getDescription(): string {
    return $this->translate('MSC.krabo_login.login_description');
  }

  public function getBreadCrumb(): array {
    return [
      'krabo.login.stage.ask_for_email',
    ];
  }

  public function getForm(Request $request, ModuleModel $module): string {
    $template = new FrontendTemplate('login_stage');
    $template->email = StringUtil::specialchars($this->authenticationUtils->getLastUsername());
    $template->submitLabel = $this->translate('MSC.krabo_login.login_submit');
    $template->requestCodeLabel = $this->translate('MSC.krabo_login.request_code_label');
    $template->guestContinue = '';
    if ($module->krabo_login_guest_jumpTo) {
      $target = $module->getRelated('krabo_login_guest_jumpTo');
      if ($target instanceof PageModel) {
        $template->guestContinue = '<a href="' . $target->getAbsoluteUrl() . '" class="continue-as-guest">' . $this->translate('MSC.krabo_login.continue_as_guest') . '</a>';
      }
    }
    return $template->parse();
  }

  public function process(Request $request, ModuleModel $module) {
    $function = $request->request->get('function');
    if ($function === 'change_email') {
      $request->getSession()->set(Security::LAST_USERNAME, NULL);
      $this->nextStage = 'krabo.login.stage.ask_for_email';
      return;
    }
    if ($function === 'passwordless_login') {
      $this->nextStage = 'krabo.login.stage.passwordless_login';
      return;
    }
    $username = $this->authenticationUtils->getLastUsername();
    $member = MemberModel::findByUsername($username);
    if (null === $member) {
      $request->getSession()->set(Security::LAST_USERNAME, NULL);
      $this->message = $this->translate('MSC.krabo_login.email_not_found');
      $this->nextStage = 'krabo.login.stage.ask_for_email';
      return;
    }
    if ($function == 'password_reset') {
      $this->sendPasswordLink($member, $module->nc_password_reset_notification);
      $this->message = $this->translate('MSC.krabo_login.password_reset_description');
      $this->messageStatus = 'success';
      $this->nextStage = 'krabo.login.stage.login';
      return;
    }

    $target = $module->getRelated('jumpTo');
    $targetPath = $target instanceof PageModel ? $target->getAbsoluteUrl() : $request->getRequestUri();
    $request->request->set('_target_path', base64_encode($targetPath));
    $request->request->set('_always_use_target_path', true);

    $response = $this->loginService->authenticate($request, $username, $request->request->get('password'));
    if ($response === NULL) {
      $this->nextStage = 'krabo.login.stage.login';
      $this->message = $this->translate('MSC.krabo_login.invalid_password');
      return;
    }
    $this->nextStage = 'krabo.login.stage.logged_in';
    $this->response = $response;
  }

  protected function sendPasswordLink($objMember, $notficationId)
  {
    $objNotification = \NotificationCenter\Model\Notification::findByPk($notficationId);

    if ($objNotification === null) {
      $this->log('The notification was not found ID ' . $notficationId, __METHOD__, TL_ERROR);
      return;
    }

    /** @var \Contao\CoreBundle\OptIn\OptIn $optIn */
    $optIn = System::getContainer()->get('contao.opt-in');
    $optInToken = $optIn->create('pw', $objMember->email, array('tl_member'=>array($objMember->id)));
    $token = $optInToken->getIdentifier();

    $arrTokens = array();

    // Add member tokens
    foreach ($objMember->row() as $k => $v)
    {
      $arrTokens['member_' . $k] = $v;
    }

    $arrTokens['recipient_email'] = $objMember->email;
    $arrTokens['domain'] = \Idna::decode(\Environment::get('host'));
    $arrTokens['link'] = \Idna::decode(\Environment::get('base')) . \Environment::get('request') . ((($GLOBALS['TL_CONFIG']['disableAlias'] ?? false) || strpos(\Environment::get('request'), '?') !== false) ? '&' : '?') . 'token=' . $token;
    $arrTokens['link'] .= '&stage=krabo.login.stage.set_password';

    $objNotification->send($arrTokens, $GLOBALS['TL_LANGUAGE']);
    $this->logger->info('A new password has been requested for user ID ' . $objMember->id . ' (' . $objMember->email . ')', array('contao' => new ContaoContext(__FUNCTION__, TL_ACCESS)));
  }

}