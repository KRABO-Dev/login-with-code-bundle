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

use Contao\ModuleModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;
use Contao\System;


abstract class AbstractStage extends System{

  private ?TranslatorInterface $translator = null;

  protected string $nextStage;

  protected string $message = '';

  protected ?Response $response = null;

  protected bool $hasUpload = false;

  protected bool $goToNext = false;

  abstract public function getHeadline(): string;

  abstract public function getDescription(): string;

  abstract public function getForm(Request $request, ModuleModel $module): string;

  abstract public function process(Request $request, ModuleModel $module);

  public function getNextStage(): string {
    return $this->nextStage ?? 'krabo.login.stage.ask_for_email';
  }

  public function getResponse():? Response {
    return $this->response;
  }

  public function getMessage(): string
  {
    return $this->message;
  }

  public function hasUpload(): bool {
    return $this->hasUpload;
  }

  public function shouldGoToNext(): bool {
    return $this->goToNext;
  }

  protected function translate(string $key): string
  {
    return $this->getTranslatior()->trans($key, [], 'contao_default');
  }

  private function getTranslatior(): TranslatorInterface {
    if (!$this->translator) {
      $this->translator = System::getContainer()->get('translator');
    }
    return $this->translator;
  }

}