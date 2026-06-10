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

use Contao\MemberModel;
use Contao\ModuleModel;
use Contao\OptInModel;
use Contao\System;
use Contao\Versions;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security;

class ActivateStage extends AbstractStage {
  private LoggerInterface $logger;

  public function __construct(LoggerInterface $logger) {
    $this->logger = $logger;
  }

  public function getHeadline(): string {
    return $this->translate('MSC.krabo_login.activate_headline');
  }

  public function getDescription(): string {
    return $this->translate('MSC.krabo_login.activate_description');
  }

  public function getForm(Request $request, ModuleModel $module): string {
    $this->goToNext = true;
    $this->nextStage = 'krabo.login.stage.ask_for_email';
    $token = $request->query->get('token');
    if (empty($token)) {
      $token = $request->request->get('token');
    }

    $optIn = System::getContainer()->get('contao.opt_in');
    if ((!$optInToken = $optIn->find($token)) || !$optInToken->isValid() || \count($arrRelated = $optInToken->getRelatedRecords()) != 1 || key($arrRelated) != 'tl_member' || \count($arrIds = current($arrRelated)) != 1 || (!$member = MemberModel::findByPk($arrIds[0]))) {
      $this->message = $this->translate('MSC.krabo_login.activate_error');
      return '';
    }
    if ($optInToken->isConfirmed() || $optInToken->getEmail() != $member->email) {
      $this->message = $this->translate('MSC.krabo_login.activate_error');
      return '';
    }


    $request->getSession()->set(Security::LAST_USERNAME, $member->username);

    // Initialize the versioning (see #8301)
    $objVersions = new Versions('tl_member', $member->id);
    $objVersions->setUsername($member->username);
    $objVersions->setEditUrl(System::getContainer()->get('router')->generate('contao_backend', array('do'=>'member', 'act'=>'edit', 'id'=>$member->id, 'rt'=>'1')));
    $objVersions->initialize();
    $member->tstamp = time();
    $member->disable = '';
    $member->locked = 0; // see #8545
    $member->save();

    System::getContainer()->get('contao.repository.remember_me')->deleteByUsername($member->username);

    if ($models = OptInModel::findUnconfirmedByRelatedTableAndId('tl_member', $member->id))
    {
      foreach ($models as $model)
      {
        $model->delete();
      }
    }

    $optInToken->confirm();

    // Create a new version
    if ($GLOBALS['TL_DCA']['tl_member']['config']['enableVersioning'] ?? null)
    {
      $objVersions->create();
    }

    // HOOK: set new password callback
    if (isset($GLOBALS['TL_HOOKS']['setNewPassword']) && \is_array($GLOBALS['TL_HOOKS']['setNewPassword']))
    {
      foreach ($GLOBALS['TL_HOOKS']['setNewPassword'] as $callback)
      {
        $this->import($callback[0]);
        $this->{$callback[0]}->{$callback[1]}($member, $this->objWidget->value, $this);
      }
    }

    $this->message = $this->translate('MSC.krabo_login.activate_success');

    $this->nextStage = 'krabo.login.stage.login';
    return '';
  }


  public function process(Request $request, ModuleModel $module)
  {
    // Do nothing
  }
}