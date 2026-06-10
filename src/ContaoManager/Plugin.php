<?php

namespace Krabo\LoginWithCodeBundle\ContaoManager;

use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\ManagerPlugin\Routing\RoutingPluginInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class Plugin implements BundlePluginInterface, RoutingPluginInterface
{
    public function getBundles(ParserInterface $parser): array
    {
        return [
            BundleConfig::create('Krabo\LoginWithCodeBundle\LoginWithCodeBundle')
                ->setLoadAfter(['notification_center'])
        ];
    }

  /**
   * {@inheritdoc}
   */
  public function getRouteCollection(LoaderResolverInterface $resolver, KernelInterface $kernel)
  {
    return $resolver
      ->resolve(__DIR__.'/../Resources/config/routing.yml')
      ->load(__DIR__.'/../Resources/config/routing.yml')
      ;
  }


}
