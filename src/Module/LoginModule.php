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

use Contao\CoreBundle\Controller\FrontendModule\AbstractFrontendModuleController;
use Contao\CoreBundle\Exception\AjaxRedirectResponseException;
use Contao\CoreBundle\Security\Authentication\Token\TokenChecker;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\System;
use Contao\Template;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class LoginModule extends AbstractFrontendModuleController  {
  private CsrfTokenManagerInterface $csrfTokenManager;
  private TokenChecker $tokenChecker;

  public function __construct(TokenChecker $tokenChecker, CsrfTokenManagerInterface $csrfTokenManager)
  {
    $this->tokenChecker = $tokenChecker;
    $this->csrfTokenManager = $csrfTokenManager;
  }

  protected function getResponse(Template $template, ModuleModel $model, Request $request): ?Response
  {
    $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/loginwithcode/scripts.js|static';
    System::loadLanguageFile('default');
    $currentStageName = 'krabo.login.stage.ask_for_email';
    if ($request->query->has('stage') && $request->query->has('token')) {
      $currentStageName = $request->query->get('stage');
    }
    if ($this->tokenChecker->hasFrontendUser()) {
      $currentStageName = 'krabo.login.stage.logged_in';
    }
    $currentStage = System::getContainer()->get($currentStageName);

    $isAjax = false;
    if ($this->isAjaxForm($request, $model->id)) {
      $isAjax = true;
      $submittedStage = $request->request->get('stage');
      if ($submittedStage) {
        $currentStageName = $submittedStage;
        $currentStage = System::getContainer()->get($currentStageName);
        $currentStage->process($request, $model);
        if ($response = $this->processResponse($currentStage, $isAjax)) {
          return $response;
        }
        $message = $currentStage->getMessage();
        $messageStatus = $currentStage->getMessageStatus();
        $currentStageName = $currentStage->getNextStage();
        $currentStage = System::getContainer()->get($currentStageName);
      }
    }
    $stageForm = $currentStage->getForm($request, $model);

    if ($currentStage->shouldGoToNext()) {
      if ($response = $this->processResponse($currentStage, $isAjax)) {
        return $response;
      }
      $message = $currentStage->getMessage();
      $messageStatus = $currentStage->getMessageStatus();
      $currentStageName = $currentStage->getNextStage();
      $currentStage = System::getContainer()->get($currentStageName);
      $stageForm = $currentStage->getForm($request, $model);
    }

    $template->enctype = $currentStage->hasUpload() ? 'multipart/form-data' : 'application/x-www-form-urlencoded';
    $template->requestToken = $this->csrfTokenManager->getDefaultTokenValue();
    $template->message = $message;
    $template->messageStatus = $messageStatus;
    $template->action = $request->getRequestUri();
    $template->formId = 'tl_krabo_login_'.$model->id;
    $template->stageForm = $stageForm;
    $template->isAjax = $isAjax;
    $template->headline = $currentStage->getHeadline();
    $template->description = $currentStage->getDescription();
    $template->stage = $currentStageName;
    if ($isAjax) {
      echo $template->parse();
      exit();
    }
    return new Response($template->parse());
  }

  private function processResponse(AbstractStage $stage, bool $isAjax):? Response {
    if ($response = $stage->getResponse()) {
      if ($isAjax && $response instanceof RedirectResponse) {
        $location = $response->getTargetUrl();
        return new Response($location, $response->getStatusCode(), ['X-Ajax-Location' => $location]);
      }
      return $response;
    }
    return null;
  }

  private function isAjaxForm(Request $request, $id): bool
  {
    if ($request->isXmlHttpRequest() && $request->headers->get('X-Contao-Ajax-Form') === 'tl_krabo_login_'.$id) {
      return true;
    }
    return false;
  }

}