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

use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\FrontendTemplate;
use Contao\MemberModel;
use Contao\ModuleModel;
use Contao\StringUtil;
use Contao\System;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class PasswordResetStage extends AbstractStage {

  private AuthenticationUtils $authenticationUtils;
  private LoggerInterface $logger;

  public function __construct(AuthenticationUtils $authenticationUtils, LoggerInterface $logger) {
    $this->authenticationUtils = $authenticationUtils;
    $this->logger = $logger;
  }

  public function getHeadline(): string {
    return $this->translate('MSC.krabo_login.password_reset_headline');
  }

  public function getDescription(): string {
    return $this->translate('MSC.krabo_login.password_reset_description');
  }

  public function getForm(Request $request, ModuleModel $module): string {
    $username = $this->authenticationUtils->getLastUsername();
    $member = MemberModel::findByUsername($username);
    $this->sendPasswordLink($member, $module->nc_password_reset_notification);
    $template = new FrontendTemplate('password_reset_stage');
    $template->email = StringUtil::specialchars($this->authenticationUtils->getLastUsername());
    $template->backLabel = $this->translate('MSC.krabo_login.password_reset_back');
    return $template->parse();
  }

  public function process(Request $request, ModuleModel $module) {
    $function = $request->request->get('function');
    if ($function === 'back') {
      $this->nextStage = 'krabo.login.stage.ask_for_email';
    }
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