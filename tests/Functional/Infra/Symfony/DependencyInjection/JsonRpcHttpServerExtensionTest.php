<?php
namespace Tests\Functional\Infra\Symfony\DependencyInjection;

use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Tests\Common\Infra\Symfony\DependencyInjection\AbstractTestClass;
use Yoanm\SymfonyJsonRpcHttpServer\Infra\Endpoint\JsonRpcHttpEndpoint;
use Yoanm\SymfonyJsonRpcHttpServer\Infra\Resolver\ServiceNameResolver;
use Yoanm\SymfonyJsonRpcHttpServer\Infra\Symfony\DependencyInjection\JsonRpcHttpServerExtension;

/**
 * @covers \Yoanm\SymfonyJsonRpcHttpServer\Infra\Symfony\DependencyInjection\JsonRpcHttpServerExtension
 */
class JsonRpcHttpServerExtensionTest extends AbstractTestClass
{
    public function testShouldExposeEndpointService()
    {
        $this->load();

        $this->assertContainerBuilderHasService(self::EXPECTED_ENDPOINT_SERVICE_ID, JsonRpcHttpEndpoint::class);

        // Check that service is accessible through the container
        $this->assertNotNull($this->container->get(self::EXPECTED_ENDPOINT_SERVICE_ID));

        $this->assertEndpointIsUsable();
    }

    public function testShouldExposeServiceNameResolverService()
    {
        $this->load();

        $this->assertContainerBuilderHasService(
            self::EXPECTED_SERVICE_NAME_RESOLVER_SERVICE_ID,
            ServiceNameResolver::class
        );

        // Check that service is accessible through the container
        $this->assertNotNull($this->container->get(self::EXPECTED_SERVICE_NAME_RESOLVER_SERVICE_ID));

        $this->assertEndpointIsUsable();
    }

    public function testUsePSR11MethodResolverByDefault()
    {
        $this->load();

        // Assert that MethodManager have the right resolver
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            self::EXPECTED_METHOD_MANAGER_SERVICE_ID,
            0,
            new Reference('yoanm.jsonrpc_http_server.psr11.infra.resolver.method')
        );

        $this->assertEndpointIsUsable();
    }

    public function testHandleMethodResolverInjectionByTag()
    {
        $myCustomResolverServiceId = 'my_custom_resolver';

        $this->setDefinition($myCustomResolverServiceId, $this->createCustomMethodResolverDefinition());

        $this->load();

        // Assert that MethodManager have the right resolver
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(
            self::EXPECTED_METHOD_MANAGER_SERVICE_ID,
            0,
            new Reference($myCustomResolverServiceId)
        );

        $this->assertEndpointIsUsable();
    }

    public function testHandleManageJsonRpcMethodTag()
    {
        $jsonRpcMethodServiceId = uniqid();
        $jsonRpcMethodServiceId2 = uniqid();
        $methodName = 'my-method-name';
        $methodName2 = 'my-method-name-2';

        // A first method
        $this->setDefinition($jsonRpcMethodServiceId, $this->createJsonRpcMethodDefinition($methodName));
        // A second method
        $this->setDefinition($jsonRpcMethodServiceId2, $this->createJsonRpcMethodDefinition($methodName2));

        $this->load();

        // Assert that method mapping have been correctly injected
        $this->assertContainerBuilderHasServiceDefinitionWithMethodCall(
            self::EXPECTED_SERVICE_NAME_RESOLVER_SERVICE_ID,
            'addMethodMapping',
            [
                $methodName,
                $jsonRpcMethodServiceId
            ],
            0
        );
        // Assert that method mapping have been correctly injected
        $this->assertContainerBuilderHasServiceDefinitionWithMethodCall(
            self::EXPECTED_SERVICE_NAME_RESOLVER_SERVICE_ID,
            'addMethodMapping',
            [
                $methodName2,
                $jsonRpcMethodServiceId2
            ],
            1
        );

        $this->assertEndpointIsUsable();
    }

    public function testHandleNotManageJsonRpcMethodTagIfCustomResolverIsUsed()
    {
        $jsonRpcMethodServiceId = uniqid();
        $jsonRpcMethodServiceId2 = uniqid();
        $methodName = 'my-method-name';
        $methodName2 = 'my-method-name-2';

        // A first method
        $this->setDefinition($jsonRpcMethodServiceId, $this->createJsonRpcMethodDefinition($methodName));
        // A second method
        $this->setDefinition($jsonRpcMethodServiceId2, $this->createJsonRpcMethodDefinition($methodName2));

        // Add the custom method resolver
        $this->setDefinition(uniqid(), $this->createCustomMethodResolverDefinition());

        $this->load();

        // Assert that no method mapping have been added
        $this->assertEmpty(
            $this->container->getDefinition(self::EXPECTED_SERVICE_NAME_RESOLVER_SERVICE_ID)->getMethodCalls()
        );

        $this->assertEndpointIsUsable();
    }

    public function testShouldThrowAnExceptionIfJsonRpcMethodUsedWithTagIsNotPublic()
    {
        $jsonRpcMethodServiceId = uniqid();
        $jsonRpcMethodServiceId2 = uniqid();
        $methodName = 'my-method-name';
        $methodName2 = 'my-method-name-2';

        // A first method
        $this->setDefinition($jsonRpcMethodServiceId, $this->createJsonRpcMethodDefinition($methodName));
        // A second method
        $this->setDefinition(
            $jsonRpcMethodServiceId2,
            $this->createJsonRpcMethodDefinition($methodName2)
                // Clear previous tag
                ->clearTag(self::EXPECTED_JSONRPC_METHOD_TAG)
                // And add one without attribute
                ->addTag(self::EXPECTED_JSONRPC_METHOD_TAG)
        );

        $this->expectException(LogicException::class);
        // Check that exception is for the second method
        $this->expectExceptionMessage(
            sprintf(
                'Service %s is taggued as JSON-RPC method but does not have'
                . 'method name defined under "%s" tag attribute key',
                $jsonRpcMethodServiceId2,
                self::EXPECTED_JSONRPC_METHOD_TAG_METHOD_NAME_KEY
            )
        );

        $this->load();
    }

    public function testShouldThrowAnExceptionIfJsonRpcMethodUsedWithTagIsDoesNotHaveTheMethodTagAttribute()
    {
        $jsonRpcMethodServiceId = uniqid();
        $jsonRpcMethodServiceId2 = uniqid();
        $methodName = 'my-method-name';
        $methodName2 = 'my-method-name-2';

        // A first method
        $this->setDefinition($jsonRpcMethodServiceId, $this->createJsonRpcMethodDefinition($methodName));
        // A second method
        $this->setDefinition(
            $jsonRpcMethodServiceId2,
            $this->createJsonRpcMethodDefinition($methodName2)->setPrivate(true)
        );

        $this->expectException(LogicException::class);
        // Check that exception is for the second method
        $this->expectExceptionMessage(
            sprintf(
                'Service %s is taggued as JSON-RPC method but is not public. '
                .'Service must be public in order to retrieve it later',
                $jsonRpcMethodServiceId2
            )
        );

        $this->load();
    }
}