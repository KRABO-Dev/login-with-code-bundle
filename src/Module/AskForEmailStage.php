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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Validator;

class AskForEmailStage extends AbstractStage {

  private AuthenticationUtils $authenticationUtils;

  public function __construct(AuthenticationUtils $authenticationUtils) {
    $this->authenticationUtils = $authenticationUtils;
  }

  public function getBreadCrumbTitle(): string {
    return $this->translate('MSC.krabo_login.email_breadcrumb');
  }

  public function getHeadline(): string {
    return $this->translate('MSC.krabo_login.email_headline');
  }

  public function getDescription(): string {
    return $this->translate('MSC.krabo_login.email_description');
  }

  public function getForm(Request $request, ModuleModel $module): string {
    $template = new FrontendTemplate('ask_for_email_stage');
    $template->email = StringUtil::specialchars($this->authenticationUtils->getLastUsername());
    $template->submitLabel = $this->translate('MSC.krabo_login.submit_email');
    return $template->parse();
  }

  public function process(Request $request, ModuleModel $module) {
    $request->getSession()->set(Security::LAST_USERNAME, $request->request->get('email'));
    $email = $request->request->get('email');
    if (!Validator::isEmail($email)) {
      $this->message = $this->translate('MSC.krabo_login.invalid_email');
      $this->messageStatus = 'error';
    } else {
      $member = MemberModel::findByEmail($request->request->get('email'));
      if (null === $member) {
        $this->nextStage = 'krabo.login.stage.register';
      } else {
        $this->nextStage = 'krabo.login.stage.login';
      }
    }
  }

}