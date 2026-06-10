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

use Contao\CoreBundle\Exception\ResponseException;
use Contao\CoreBundle\Monolog\ContaoContext;
use Contao\Date;
use Contao\DcaLoader;
use Contao\Encryption;
use Contao\Environment;
use Contao\Files;
use Contao\FilesModel;
use Contao\Folder;
use Contao\FormPassword;
use Contao\FrontendTemplate;
use Contao\FrontendUser;
use Contao\Idna;
use Contao\Input;
use Contao\MemberModel;
use Contao\ModuleModel;
use Contao\StringUtil;
use Contao\System;
use Contao\UploadableWidgetInterface;
use Contao\Versions;
use Contao\Widget;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class RegisterStage extends AbstractStage {

  private AuthenticationUtils $authenticationUtils;
  private LoggerInterface $logger;

  private $objWidget;

  private $objWidgetConfirm;

  private $fields = '';

  private $doNotSubmit;

  private $initialized = false;

  private $arrUser;

  public function __construct(AuthenticationUtils $authenticationUtils, LoggerInterface $logger) {
    $this->authenticationUtils = $authenticationUtils;
    $this->logger = $logger;
  }

  public function getHeadline(): string {
    return $this->translate('MSC.krabo_login.register_headline');
  }

  public function getDescription(): string {
    return $this->translate('MSC.krabo_login.register_description');
  }

  public function getForm(Request $request, ModuleModel $module): string {
    $this->initializeWidgets($module);
    $template = new FrontendTemplate('register_stage');
    $template->email = StringUtil::specialchars($this->authenticationUtils->getLastUsername());
    $template->submitLabel = $this->translate('MSC.krabo_login.register_submit');
    $template->fields = $this->objWidget->parse() . $this->objWidgetConfirm->parse() . $this->fields;
    return $template->parse();
  }

  public function initializeWidgets(ModuleModel $module): void {
    System::loadLanguageFile('tl_member');
    $loader = new DcaLoader('tl_member');
    $loader->load();

    $strFormId = 'tl_krabo_login_'.$module->id;
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
      $this->objWidget->rowClass = 'row_0 row_first even';
    }
    if (!$this->objWidgetConfirm) {
      $arrField['label'] = sprintf($GLOBALS['TL_LANG']['MSC']['confirm'][0], $arrField['label']);
      $this->objWidgetConfirm = new $strClass($strClass::getAttributesFromDca($arrField, 'password_confirm'));
      $this->objWidgetConfirm->rowClass = 'row_1 row_last odd';
    }

    if (!$this->initialized) {
      $objMember = null;

      $arrUser = array();
      $arrFields = array();
      $hasUpload = false;
      $i = 0;

      // Build the form
      $editable = StringUtil::deserialize($module->editable);
      foreach ($editable as $field) {
        $arrData = $GLOBALS['TL_DCA']['tl_member']['fields'][$field] ?? array();

        // Map checkboxWizards to regular checkbox widgets
        if (($arrData['inputType'] ?? null) == 'checkboxWizard') {
          $arrData['inputType'] = 'checkbox';
        }

        // Map fileTrees to upload widgets (see #8091)
        if (($arrData['inputType'] ?? null) == 'fileTree') {
          $arrData['inputType'] = 'upload';
        }

        $strClass = $GLOBALS['TL_FFL'][$arrData['inputType'] ?? null] ?? null;

        // Continue if the class is not defined
        if (!class_exists($strClass)) {
          continue;
        }

        $arrData['eval']['required'] = null;

        // Unset the unique field check upon follow-up registrations
        if ($objMember !== null && ($arrData['eval']['unique'] ?? null) && Input::post($field) == $objMember->$field) {
          $arrData['eval']['unique'] = false;
        }

        $objWidget = new $strClass($strClass::getAttributesFromDca($arrData, $field, $arrData['default'] ?? null, $field, 'tl_member', $module));

        // Append the module ID to prevent duplicate IDs (see #1493)
        $objWidget->id .= '_' . $module->id;
        $objWidget->storeValues = true;
        $objWidget->rowClass = 'row_' . $i . (($i == 0) ? ' row_first' : '') . ((($i % 2) == 0) ? ' even' : ' odd');

        // Increase the row count if it's a password field
        if ($objWidget instanceof FormPassword) {
          $objWidget->rowClassConfirm = 'row_' . ++$i . ((($i % 2) == 0) ? ' even' : ' odd');
        }

        // Validate input
        if (Input::post('FORM_SUBMIT') == $strFormId) {
          $objWidget->validate();

          $varValue = $objWidget->value;
          $passwordHasher = System::getContainer()->get('security.password_hasher_factory')->getPasswordHasher(FrontendUser::class);

          // Check whether the password matches the username
          if ($objWidget instanceof FormPassword && ($username = Input::post('username')) && $passwordHasher->verify($varValue, $username)) {
            $objWidget->addError($GLOBALS['TL_LANG']['ERR']['passwordName']);
          }

          $rgxp = $arrData['eval']['rgxp'] ?? null;

          // Convert date formats into timestamps (check the eval setting first -> #3063)
          if ($varValue !== null && $varValue !== '' && \in_array($rgxp, array('date', 'time', 'datim'))) {
            try {
              $objDate = new Date($varValue, Date::getFormatFromRgxp($rgxp));
              $varValue = $objDate->tstamp;
            } catch (\OutOfBoundsException $e) {
              $objWidget->addError(sprintf($GLOBALS['TL_LANG']['ERR']['invalidDate'], $varValue));
            }
          }

          // Convert arrays (see #4980)
          if (($arrData['eval']['multiple'] ?? null) && isset($arrData['eval']['csv']) && \is_array($varValue)) {
            $varValue = implode($arrData['eval']['csv'], $varValue);
          }

          // Make sure that unique fields are unique (check the eval setting first -> #3063)
          if (($arrData['eval']['unique'] ?? null) && (\is_array($varValue) || (string)$varValue !== '') && !$this->Database->isUniqueValue('tl_member', $field, $varValue)) {
            $objWidget->addError(sprintf($GLOBALS['TL_LANG']['ERR']['unique'], $arrData['label'][0] ?? $field));
          }

          // Save callback
          if (\is_array($arrData['save_callback'] ?? null) && $objWidget->submitInput() && !$objWidget->hasErrors()) {
            foreach ($arrData['save_callback'] as $callback) {
              try {
                if (\is_array($callback)) {
                  $this->import($callback[0]);
                  $varValue = $this->{$callback[0]}->{$callback[1]}($varValue, null);
                } elseif (\is_callable($callback)) {
                  $varValue = $callback($varValue, null);
                }
              } catch (ResponseException $e) {
                throw $e;
              } catch (\Exception $e) {
                $objWidget->class = 'error';
                $objWidget->addError($e->getMessage());
              }
            }
          }

          // Store the current value
          if ($objWidget->hasErrors()) {
            $doNotSubmit = true;
          } elseif ($objWidget->submitInput()) {
            // Set the correct empty value (see #6284, #6373)
            if ($varValue === '') {
              $varValue = $objWidget->getEmptyValue();
            }

            // Encrypt the value (see #7815)
            if ($arrData['eval']['encrypt'] ?? null) {
              $varValue = Encryption::encrypt($varValue);
            }

            // Set the new value
            $arrUser[$field] = $varValue;
          }
        }

        if ($objWidget instanceof UploadableWidgetInterface) {
          $hasUpload = true;
        }

        $temp = $objWidget->parse();
        $this->fields .= $temp;

        if (!isset($arrFields[$arrData['eval']['feGroup']][$field])) {
          $arrFields[$arrData['eval']['feGroup']][$field] = '';
        }

        $arrFields[$arrData['eval']['feGroup']][$field] .= $temp;

        ++$i;
      }
      $this->initialized = true;
      $this->doNotSubmit = $doNotSubmit;
      $this->hasUpload = $hasUpload;
      $this->arrUser = $arrUser;
    }
  }

  public function process(Request $request, ModuleModel $module) {
    $username = $this->authenticationUtils->getLastUsername();
    $function = $request->request->get('function');
    if ($function === 'change_email') {
      $this->nextStage = 'krabo.login.stage.ask_for_email';
      return;
    }

    if ($request->request->get('password') != $request->request->get('password_confirm')) {
      $this->nextStage = 'krabo.login.stage.register';
      $this->message = $this->translate('MSC.krabo_login.register_passwords_do_not_match');
      return;
    }

    $userNameField = $GLOBALS['TL_DCA']['tl_member']['fields']['username'];
    if (($userNameField['eval']['unique'] ?? null) && (\is_array($username) || (string) $username !== '') && !$this->Database->isUniqueValue('tl_member', 'username', $username)) {
      $this->nextStage = 'krabo.login.stage.register';
      $this->message = $this->translate('MSC.krabo_login.username_already_exists');
      return;
    }

    $this->initializeWidgets($module);
    $this->objWidget->validate();
    $this->objWidgetConfirm->validate();
    if (!$this->objWidget->hasErrors() && !$this->objWidgetConfirm->hasErrors()) {
      $arrData = $this->arrUser;
      $arrData['username'] = $username;
      $arrData['email'] = $username;
      $arrData['password'] = $this->objWidget->value;
      $this->createNewUser($arrData, $module);

      $this->message = $this->translate('MSC.krabo_login.registration_success');
      $this->nextStage = 'krabo.login.stage.login';
    } else {
      $this->nextStage = 'krabo.login.stage.register';
      $this->message = $this->translate('MSC.krabo_login.registration_error');
    }

  }

  protected function createNewUser($arrData, $module)
  {
    $arrData['tstamp'] = time();
    $arrData['login'] = '1';
    $arrData['dateAdded'] = $arrData['tstamp'];

    // Set default groups
    if (!\array_key_exists('groups', $arrData))
    {
      $arrData['groups'] = $module->reg_groups;
    }

    // Disable account
    $arrData['disable'] = 1;

    // Make sure newsletter is an array
    if (isset($arrData['newsletter']) && !\is_array($arrData['newsletter']))
    {
      $arrData['newsletter'] = array($arrData['newsletter']);
    }

    // Create the user
    $objNewUser = new MemberModel();
    $objNewUser->setRow($arrData);
    $objNewUser->save();

    // Store the new ID (see https://github.com/contao/contao/pull/196#discussion_r243555399)
    $arrData['id'] = $objNewUser->id;

    $this->sendActivationMail($arrData, $module->nc_activation_notification);

    // Assign home directory
    if ($module->reg_assignDir)
    {
      $objHomeDir = FilesModel::findByUuid($module->reg_homeDir);

      if ($objHomeDir !== null)
      {
        $this->import(Files::class, 'Files');
        $strUserDir = StringUtil::standardize($arrData['username'] ?? '') ?: 'user_' . $objNewUser->id;

        // Add the user ID if the directory exists
        while (is_dir(System::getContainer()->getParameter('kernel.project_dir') . '/' . $objHomeDir->path . '/' . $strUserDir))
        {
          $strUserDir .= '_' . $objNewUser->id;
        }

        // Create the user folder
        new Folder($objHomeDir->path . '/' . $strUserDir);

        $objUserDir = FilesModel::findByPath($objHomeDir->path . '/' . $strUserDir);

        // Save the folder ID
        $objNewUser->assignDir = 1;
        $objNewUser->homeDir = $objUserDir->uuid;
        $objNewUser->save();
      }
    }

    // HOOK: send insert ID and user data
    if (isset($GLOBALS['TL_HOOKS']['createNewUser']) && \is_array($GLOBALS['TL_HOOKS']['createNewUser']))
    {
      foreach ($GLOBALS['TL_HOOKS']['createNewUser'] as $callback)
      {
        $this->import($callback[0]);
        $this->{$callback[0]}->{$callback[1]}($objNewUser->id, $arrData, $this);
      }
    }

    // Create the initial version (see #7816)
    $objVersions = new Versions('tl_member', $objNewUser->id);
    $objVersions->setUsername($objNewUser->username);
    $objVersions->setEditUrl(System::getContainer()->get('router')->generate('contao_backend', array('do'=>'member', 'act'=>'edit', 'id'=>$objNewUser->id, 'rt'=>'1')));
    $objVersions->initialize();
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