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
use Contao\OptInModel;
use Contao\Input;
use Contao\System;
use Contao\Versions;
use Contao\Widget;
use DcaLoader;
use Krabo\LoginWithCodeBundle\Service\LoginService;
use PageModel;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Security;

class SetPasswordStage extends AbstractStage {
  private LoggerInterface $logger;
  private LoginService $loginService;

  private $objWidget;

  private $objWidgetConfirm;

  public function __construct(LoggerInterface $logger, LoginService $loginService) {
    $this->logger = $logger;
    $this->loginService = $loginService;
  }

  public function getBreadCrumbTitle(): string {
    return $this->translate('MSC.krabo_login.set_password_breadcrumb');
  }

  public function getHeadline(): string {
    return $this->translate('MSC.krabo_login.set_password_headline');
  }

  public function getDescription(): string {
    return $this->translate('MSC.krabo_login.set_password_description');
  }

  public function getBreadCrumb(): array {
    return [
      'krabo.login.stage.ask_for_email',
    ];
  }

  public function getForm(Request $request, ModuleModel $module): string {
    $strFormId = 'tl_krabo_login_'.$module->id;
    $token = $request->query->get('token');
    if (empty($token)) {
      $token = $request->request->get('token');
    }

    $optIn = System::getContainer()->get('contao.opt_in');
    if ((!$optInToken = $optIn->find($token)) || !$optInToken->isValid() || \count($arrRelated = $optInToken->getRelatedRecords()) != 1 || key($arrRelated) != 'tl_member' || \count($arrIds = current($arrRelated)) != 1 || (!$member = MemberModel::findByPk($arrIds[0]))) {
      $this->message = $this->translate('MSC.krabo_login.set_password_error');
    }
    $request->getSession()->set(Security::LAST_USERNAME, $member->username);
    $this->initializeWidgets($request, $member);


    $template = new FrontendTemplate('set_password_stage');
    $template->submitLabel = $this->translate('MSC.krabo_login.set_password_submit');

    $template->token = $token;
    $template->fields = $this->fields;
    $template->passwordFields = $this->objWidget->generateLabel() . $this->objWidget->generate();
    if (Input::post('FORM_SUBMIT') == $strFormId) {
      $template->passwordFields = str_replace('value=""', 'value="'. Input::post('password') . '"', $template->passwordFields);
    }
    $template->passwordFields .= $this->objWidgetConfirm->generateLabel() . $this->objWidgetConfirm->generate();
    if (Input::post('FORM_SUBMIT') == $strFormId) {
      $template->passwordFields = str_replace('value=""', 'value="'. Input::post('password_confirm') . '"', $template->passwordFields);
    }

    return $template->parse();
  }

  public function initializeWidgets(Request $request, $member): void {
    System::loadLanguageFile('tl_member');
    $loader = new DcaLoader('tl_member');
    $loader->load();
    // Define the form field
    $arrField = $GLOBALS['TL_DCA']['tl_member']['fields']['password'];
    $strClass = $GLOBALS['TL_FFL']['password'] ?? null;
    // Fallback to default if the class is not defined
    if (!class_exists($strClass))
    {
      $strClass = 'FormPassword';
    }

    if (!$this->objWidget) {
      /** @var Widget $objWidget */
      $this->objWidget = new $strClass($strClass::getAttributesFromDca($arrField, 'password'));
      $this->objWidget->currentRecord = $member->id;
      $this->objWidget->rowClass = 'row_0 row_first even';
    }
    if (!$this->objWidgetConfirm) {
      $arrField['label'] = sprintf($GLOBALS['TL_LANG']['MSC']['confirm'][0], $arrField['label']);
      $this->objWidgetConfirm = new $strClass($strClass::getAttributesFromDca($arrField, 'password_confirm'));
      $this->objWidgetConfirm->currentRecord = $member->id;
      $this->objWidgetConfirm->rowClass = 'row_1 row_last odd';
    }
  }

  public function process(Request $request, ModuleModel $module) {
    if ($request->request->get('password') != $request->request->get('password_confirm')) {
      $this->nextStage = 'krabo.login.stage.set_password';
      $this->message = $this->translate('MSC.krabo_login.set_password_passwords_do_not_match');
      return;
    }

    $optIn = System::getContainer()->get('contao.opt_in');
    // Find an unconfirmed token with only one related record
    if ((!$optInToken = $optIn->find($request->request->get('token'))) || !$optInToken->isValid() || \count($arrRelated = $optInToken->getRelatedRecords()) != 1 || key($arrRelated) != 'tl_member' || \count($arrIds = current($arrRelated)) != 1 || (!$member = MemberModel::findByPk($arrIds[0]))) {
      $this->message = $this->translate('MSC.krabo_login.set_password_error');  
      $this->nextStage = 'krabo.login.stage.ask_for_email';
      return;
    }

    if ($optInToken->isConfirmed()) {
      $this->message = $this->translate('MSC.krabo_login.set_password_error');
      $this->nextStage = 'krabo.login.stage.ask_for_email';
      return;
    }
    if ($optInToken->getEmail() != $member->email) {
      $this->message = $this->translate('MSC.krabo_login.set_password_error');
      $this->nextStage = 'krabo.login.stage.ask_for_email';
      return;
    }

    // Initialize the versioning (see #8301)
    $objVersions = new Versions('tl_member', $member->id);
    $objVersions->setUsername($member->username);
    $objVersions->setEditUrl(System::getContainer()->get('router')->generate('contao_backend', array('do'=>'member', 'act'=>'edit', 'id'=>$member->id, 'rt'=>'1')));
    $objVersions->initialize();

    $this->initializeWidgets($request, $member);
    $this->objWidget->validate();
    $this->objWidgetConfirm->validate();
    if (!$this->objWidget->hasErrors() && !$this->objWidgetConfirm->hasErrors()) {
      $member->tstamp = time();
      $member->disable = '';
      $member->locked = 0; // see #8545
      $member->password = $this->objWidget->value;
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

      $target = $module->getRelated('jumpTo');
      $targetPath = $request->getBasePath() . $request->getPathInfo() . '?stage=krabo.login.stage.logged_in';
      $request->request->set('_target_path', base64_encode($targetPath));
      $response = $this->loginService->authenticatePasswordless($request, $member->username);
      if ($response === NULL) {
        $this->nextStage = 'krabo.login.stage.ask_for_email';
        $this->message = $this->translate('MSC.krabo_login.set_password_error');
        $this->messageStatus = 'error';
        return;
      }
      $this->response = $response;
      $this->messageStatus = 'success';
      $this->message = $this->translate('MSC.krabo_login.set_password_success');
      if ($module->krabo_login_show_popup && class_exists('Isotope\Message', true)) {
        \Isotope\Message::addConfirmation($this->translate('MSC.krabo_login.set_password_success'));
      }
      $this->nextStage = 'krabo.login.stage.logged_in';
    } else {
      $this->nextStage = 'krabo.login.stage.set_password';
      $this->message = $this->objWidget->getErrorAsString();
      $this->messageStatus = 'error';
    }

  }



}