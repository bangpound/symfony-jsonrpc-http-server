<?php
namespace DemoApp;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\RouteCollectionBuilder;
use Yoanm\SymfonyJsonRpcHttpServer\DependencyInjection\JsonRpcHttpServerExtension;

class KernelWithCustomResolver extends AbstractKernel
{
    public function registerBundles()
    {
        $contents = require $this->getProjectDir().'/'.$this->getConfigDirectory().'/bundles.php';
        foreach ($contents as $class => $envs) {
            if (isset($envs['all']) || isset($envs[$this->environment])) {
                yield new $class();
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader)
    {
        /**** Add and load extension **/
        $container->registerExtension($extension = new JsonRpcHttpServerExtension());
        // No need to load the configuration manually as "json_rpc_http_server: ~" has been added in the config
        //$container->loadFromExtension($extension->getAlias());

        /**** Continue as usual **/
        $container->setParameter('container.dumper.inline_class_loader', true);
        $confDir = $this->getProjectDir().'/'.$this->getConfigDirectory();
        $loader->load($confDir.'/config'.self::CONFIG_EXTS, 'glob');
        $loader->load($confDir.'/services'.self::CONFIG_EXTS, 'glob');
    }

    /**
     * {@inheritdoc}
     */
    protected function configureRoutes(RouteCollectionBuilder $routes)
    {
        $confDir = $this->getProjectDir().'/'.$this->getConfigDirectory();
        $routes->import($confDir.'/routes'.self::CONFIG_EXTS, '/', 'glob');
    }

    /**
     * {@inheritdoc}
     */
    public function getConfigDirectory() : string
    {
        return 'default_config_with_resolver';
    }
}
