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

use Contao\CoreBundle\Security\Authentication\Token\TokenChecker;
use Contao\ModuleModel;
use Symfony\Component\HttpFoundation\Request;

class LoggedInStage extends AbstractStage {

  private TokenChecker $tokenChecker;

  public function __construct(TokenChecker $tokenChecker) {
    $this->tokenChecker = $tokenChecker;
  }

  public function getHeadline(): string {
    return $this->translate('MSC.krabo_login.logged_in_headline');
  }

  public function getDescription(): string {
    return sprintf($this->translate('MSC.krabo_login.logged_in_description'), $this->tokenChecker->getFrontendUsername());
  }

  public function getForm(Request $request, ModuleModel $module): string {
    return '';
  }

  public function process(Request $request, ModuleModel $module) {
    $this->nextStage = 'krabo.login.stage.logged_in';
  }

}