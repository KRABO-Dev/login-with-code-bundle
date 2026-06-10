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
use Contao\StringUtil;
use Krabo\LoginWithCodeBundle\Service\LoginService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class LoginStage extends AbstractStage {

  private AuthenticationUtils $authenticationUtils;
  private LoginService $loginService;

  public function __construct(AuthenticationUtils $authenticationUtils, LoginService $loginService) {
    $this->authenticationUtils = $authenticationUtils;
    $this->loginService = $loginService;
  }

  public function getHeadline(): string {
    return $this->translate('MSC.krabo_login.login_headline');
  }

  public function getDescription(): string {
    return $this->translate('MSC.krabo_login.login_description');
  }

  public function getForm(Request $request, ModuleModel $module): string {
    $template = new FrontendTemplate('login_stage');
    $template->email = StringUtil::specialchars($this->authenticationUtils->getLastUsername());
    $template->submitLabel = $this->translate('MSC.krabo_login.login_submit');
    $template->requestCodeLabel = $this->translate('MSC.krabo_login.request_code_label');
    return $template->parse();
  }

  public function process(Request $request, ModuleModel $module) {
    $function = $request->request->get('function');
    if ($function === 'change_email') {
      $this->nextStage = 'krabo.login.stage.ask_for_email';
      return;
    }
    if ($function === 'passwordless_login') {
      $this->nextStage = 'krabo.login.stage.passwordless_login';
      return;
    }
    if ($function == 'password_reset') {
      $this->nextStage = 'krabo.login.stage.password_reset';
      return;
    }
    $username = $this->authenticationUtils->getLastUsername();
    $member = MemberModel::findByUsername($username);
    if (null === $member) {
      $this->message = $this->translate('MSC.krabo_login.email_not_found');
      $this->nextStage = 'krabo.login.stage.ask_for_email';
      return;
    }
    $response = $this->loginService->authenticate($request, $username, $request->request->get('password'));
    if ($response === NULL) {
      $this->message = $this->translate('MSC.krabo_login.invalid_password');
      return;
    }
    $this->nextStage = 'krabo.login.stage.logged_in';
    $this->response = $response;
  }

}