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
use Contao\Environment;
use Contao\Idna;
use Contao\MemberModel;
use Contao\ModuleModel;
use Contao\System;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class NotActiveStage extends AbstractStage {

  private AuthenticationUtils $authenticationUtils;
  private LoggerInterface $logger;

  public function __construct(AuthenticationUtils $authenticationUtils, LoggerInterface $logger) {
    $this->authenticationUtils = $authenticationUtils;
    $this->logger = $logger;
  }

  public function getBreadCrumbTitle(): string {
    return $this->translate('MSC.krabo_login.not_active_breadcrumb');
  }

  public function getHeadline(): string {
    return $this->translate('MSC.krabo_login.not_active_headline');
  }

  public function getDescription(): string {
    return sprintf($this->translate('MSC.krabo_login.not_active_description'), $this->authenticationUtils->getLastUsername());
  }

  public function getBreadCrumb(): array {
    return [
      'krabo.login.stage.ask_for_email',
    ];
  }

  public function getForm(Request $request, ModuleModel $module): string {
    $member = MemberModel::findByEmail($this->authenticationUtils->getLastUsername());
    $this->sendActivationMail($member->row(), $module->nc_activation_notification);
    return '';
  }

  public function process(Request $request, ModuleModel $module) {
    $this->nextStage = 'krabo.login.stage.not-active';
  }

  protected function sendActivationMail($arrData, $notficationId)
  {
    $objNotification = \NotificationCenter\Model\Notification::findByPk($notficationId);

    if ($objNotification === null) {
      $this->log('The notification was not found ID ' . $notficationId, __METHOD__, TL_ERROR);
      return;
    }

    $optIn = System::getContainer()->get('contao.opt_in');
    $optInToken = $optIn->create('reg', $arrData['email'], array('tl_member'=>array($arrData['id'])));

    // Prepare the simple token data
    $arrTokenData = [];
    // Add member tokens
    foreach ($arrData as $k => $v)
    {
      $arrTokenData['member_' . $k] = $v;
    }
    $arrTokenData['recipient_email'] = $arrData['email'];
    $arrTokenData['activation'] = $optInToken->getIdentifier();
    $arrTokenData['domain'] = Idna::decode(Environment::get('host'));
    $arrTokenData['link'] = Idna::decode(Environment::get('base')) . Environment::get('request') . ((strpos(Environment::get('request'), '?') !== false) ? '&' : '?') . 'token=' . $optInToken->getIdentifier();
    $arrTokenData['link'] .= '&stage=krabo.login.stage.activate';
    $objNotification->send($arrTokenData, $GLOBALS['TL_LANGUAGE']);
    $this->logger->info('An account activation mail is sent for user ID ' . $arrData['id'] . ' (' . $arrData['email'] . ')', array('contao' => new ContaoContext(__FUNCTION__, TL_ACCESS)));
  }



}