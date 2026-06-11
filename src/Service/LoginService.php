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
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategyInterface;

class LoginService {

  private UserProviderInterface $userProvider;
  private UserCheckerInterface $userChecker;
  private EventDispatcherInterface $dispatcher;
  private LoggerInterface $logger;
  private TokenStorageInterface $tokenStorage;
  private AuthenticationSuccessHandlerInterface $successHandler;

  private AuthenticationManagerInterface $authenticationManager;

  private SessionAuthenticationStrategyInterface $sessionStrategy;

  public function __construct(UserProviderInterface $userProvider, UserCheckerInterface $userChecker, EventDispatcherInterface $dispatcher, TokenStorageInterface $tokenStorage, AuthenticationSuccessHandlerInterface $authenticationSuccessHandler, LoggerInterface $logger, AuthenticationManagerInterface $authenticationManager, SessionAuthenticationStrategyInterface $sessionStrategy) {
    $this->userProvider = $userProvider;
    $this->userChecker = $userChecker;
    $this->dispatcher = $dispatcher;
    $this->tokenStorage = $tokenStorage;
    $this->successHandler = $authenticationSuccessHandler;
    $this->authenticationManager = $authenticationManager;
    $this->logger = $logger;
    $this->sessionStrategy = $sessionStrategy;
  }

  public function authenticate(Request $request, string $username, string $password):? Response {
    try {
      $user = $this->userProvider->loadUserByIdentifier($username);
      if ($user instanceof FrontendUser) {
        $previousToken = $this->tokenStorage->getToken();
        $usernamePasswordToken = new UsernamePasswordToken($user, $password, 'contao_frontend', $user->getRoles());
        $returnValue = $this->authenticationManager->authenticate($usernamePasswordToken);
        if ($returnValue instanceof TokenInterface) {
          $this->migrateSession($request, $returnValue, $previousToken);
          return $this->onSuccess($request, $returnValue);
        } elseif ($returnValue instanceof Response) {
          return $returnValue;
        }
      }
    } catch (\Exception $exception) {
    }
    return NULL;
  }

  public function authenticatePasswordless(Request $request, string $username):? Response {
    try {
      $user = $this->userProvider->loadUserByIdentifier($username);
      if ($user instanceof FrontendUser) {
        $previousToken = $this->tokenStorage->getToken();
        $this->userChecker->checkPreAuth($user);
        $this->userChecker->checkPostAuth($user);
        $usernamePasswordToken = new UsernamePasswordToken($user, null, 'frontend', $user->getRoles());
        $this->migrateSession($request, $usernamePasswordToken, $previousToken);
        return $this->onSuccess($request, $usernamePasswordToken);
      }
    } catch (\Exception $exception) {
    }
    return NULL;
  }

  private function onSuccess(Request $request, TokenInterface $token): Response
  {
    $this->tokenStorage->setToken($token);

    $session = $request->getSession();
    $session->remove(Security::AUTHENTICATION_ERROR);
    $session->remove(Security::LAST_USERNAME);

    $loginEvent = new InteractiveLoginEvent($request, $token);
    $this->dispatcher->dispatch($loginEvent, SecurityEvents::INTERACTIVE_LOGIN);

    $response = $this->successHandler->onAuthenticationSuccess($request, $token);

    if (!$response instanceof Response) {
      throw new \RuntimeException('Authentication Success Handler did not return a Response.');
    }

    return $response;
  }

  private function migrateSession(Request $request, TokenInterface $token, ?TokenInterface $previousToken): void
  {
    if ($previousToken) {
      $user = method_exists($token, 'getUserIdentifier') ? $token->getUserIdentifier() : $token->getUsername();
      $previousUser = method_exists($previousToken, 'getUserIdentifier') ? $previousToken->getUserIdentifier() : $previousToken->getUsername();

      if ('' !== ($user ?? '') && $user === $previousUser) {
        return;
      }
    }

    $this->sessionStrategy->onAuthentication($request, $token);
  }

}