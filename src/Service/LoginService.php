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

namespace Krabo\LoginWithCodeBundle\Service;

use Contao\FrontendUser;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

class LoginService {

  private UserProviderInterface $userProvider;
  private UserCheckerInterface $userChecker;
  private EventDispatcherInterface $dispatcher;
  private LoggerInterface $logger;
  private TokenStorageInterface $tokenStorage;
  private AuthenticationSuccessHandlerInterface $authenticationSuccessHandler;

  public function __construct(UserProviderInterface $userProvider, UserCheckerInterface $userChecker, EventDispatcherInterface $dispatcher, TokenStorageInterface $tokenStorage, AuthenticationSuccessHandlerInterface $authenticationSuccessHandler, LoggerInterface $logger) {
    $this->userProvider = $userProvider;
    $this->userChecker = $userChecker;
    $this->dispatcher = $dispatcher;
    $this->tokenStorage = $tokenStorage;
    $this->authenticationSuccessHandler = $authenticationSuccessHandler;
    $this->logger = $logger;
  }

  public function authenticate(Request $request, string $username, ?string $password = null):? Response {
    try {
      $user = $this->userProvider->loadUserByIdentifier($username);
      if ($user instanceof FrontendUser) {
        $this->userChecker->checkPreAuth($user);
        $this->userChecker->checkPostAuth($user);
        $usernamePasswordToken = new UsernamePasswordToken($user, $password, 'frontend', $user->getRoles());
        $this->tokenStorage->setToken($usernamePasswordToken);
        $event = new InteractiveLoginEvent($request, $usernamePasswordToken);
        $this->dispatcher->dispatch($event);
        $this->logger->log(LogLevel::INFO, sprintf('User "%s" was logged in automatically', $username));
        return $this->authenticationSuccessHandler->onAuthenticationSuccess($request, $usernamePasswordToken);
      }
    } catch (\Exception $exception) {
    }
    return NULL;
  }

}