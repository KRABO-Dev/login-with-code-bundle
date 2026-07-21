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
use Contao\PageModel;
use Contao\System;
use Contao\Versions;
use Doctrine\DBAL\Connection;
use Krabo\LoginWithCodeBundle\Service\LoginService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Security;

class ActivateStage extends AbstractStage {
  private LoginService $loginService;
  private Connection $connection;

  public function __construct(LoginService $loginService, Connection $connection) {
    $this->loginService = $loginService;
    $this->connection = $connection;
  }

  public function getBreadCrumbTitle(): string {
    return $this->translate('MSC.krabo_login.activate_breadcrumb');
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
      $this->nextStage = 'krabo.login.stage.activate';
      $this->message = $this->translate('MSC.krabo_login.activate_error');
      return '';
    }
    if ($optInToken->isConfirmed() || $optInToken->getEmail() != $member->email) {
      $this->nextStage = 'krabo.login.stage.activate';
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

    $this->connection->executeQuery("UPDATE `tl_newsletter_recipients` SET active = '1' WHERE `email` = '" . $member->email . "'");

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

    $target = $module->getRelated('reg_jumpTo');
    $targetPath = $target instanceof PageModel ? $target->getAbsoluteUrl() : $request->getRequestUri();
    $request->request->set('_target_path', base64_encode($targetPath));
    $request->request->set('_always_use_target_path', true);

    $response = $this->loginService->authenticatePasswordless($request, $member->username);
    if ($response === NULL) {
      $this->nextStage = 'krabo.login.stage.activate';
      $this->message = $this->translate('MSC.krabo_login.activate_error');
      return '';
    }

    $this->nextStage = 'krabo.login.stage.logged_in';
    $this->response = $response;
    $this->messageStatus = 'success';
    $this->message = $this->translate('MSC.krabo_login.activate_success');
    if ($module->krabo_login_show_popup && class_exists('Isotope\Message', true)) {
      \Isotope\Message::addConfirmation($this->translate('MSC.krabo_login.activate_success'));
    }
    if ($module->krabo_login_merged_cart_jumpTo && $this->loginService->needToMergeCart($member->id)) {
      $target = $module->getRelated('krabo_login_merged_cart_jumpTo');
      if ($target instanceof PageModel) {
        $this->response = new RedirectResponse($target->getAbsoluteUrl());
      }
    }
    return '';
  }


  public function process(Request $request, ModuleModel $module)
  {
    // Do nothing
  }
}