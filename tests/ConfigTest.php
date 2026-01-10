<?php

declare(strict_types=1);

namespace Sirix\Router\RadixRouter\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\CacheInterface;
use ReflectionException;
use ReflectionObject;
use Sirix\Router\RadixRouter\UrlGenerator;
use Sirix\Router\RadixRouter\UrlMatcher;
use Yiisoft\Definitions\Exception\CircularReferenceException;
use Yiisoft\Definitions\Exception\InvalidConfigException;
use Yiisoft\Definitions\Exception\NotInstantiableException;
use Yiisoft\Di\BuildingException;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\Router\RouteCollectionInterface;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Router\UrlMatcherInterface;
use Yiisoft\Test\Support\SimpleCache\MemorySimpleCache;

use function dirname;

final class ConfigTest extends TestCase
{
    /**
     * @throws NotFoundExceptionInterface
     * @throws InvalidConfigException
     * @throws BuildingException
     * @throws NotInstantiableException
     * @throws CircularReferenceException
     */
    public function testDi(): void
    {
        $container = $this->createContainer();

        $urlGenerator = $container->get(UrlGeneratorInterface::class);

        $this->assertInstanceOf(UrlGenerator::class, $urlGenerator);
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws InvalidConfigException
     * @throws ReflectionException
     * @throws BuildingException
     * @throws NotInstantiableException
     * @throws CircularReferenceException
     */
    public function testDiWeb(): void
    {
        $container = $this->createContainer('web');

        $urlMatcher = $container->get(UrlMatcherInterface::class);

        $this->assertInstanceOf(UrlMatcher::class, $urlMatcher);
        $this->assertInstanceOf(MemorySimpleCache::class, $this->getPropertyValue($urlMatcher, 'cache'));
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws InvalidConfigException
     * @throws ReflectionException
     * @throws BuildingException
     * @throws NotInstantiableException
     * @throws CircularReferenceException
     */
    public function testDiWebWithDisabledCache(): void
    {
        $params = $this->getParams();
        $params['sirix/yii-radixrouter']['enableCache'] = false;
        $container = $this->createContainer('web', $params);

        $urlMatcher = $container->get(UrlMatcherInterface::class);

        $this->assertInstanceOf(UrlMatcher::class, $urlMatcher);
        $this->assertNull($this->getPropertyValue($urlMatcher, 'cache'));
    }

    /**
     * @param null|array<string, mixed> $params
     *
     * @throws InvalidConfigException
     */
    private function createContainer(?string $postfix = null, ?array $params = null): Container
    {
        return new Container(
            ContainerConfig::create()->withDefinitions(
                $this->getDiConfig($postfix, $params)
                + [
                    CacheInterface::class => new MemorySimpleCache(),
                    RouteCollectionInterface::class => $this->createMock(RouteCollectionInterface::class),
                ]
            )
        );
    }

    /**
     * @param null|array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function getDiConfig(?string $postfix = null, ?array $params = null): array
    {
        $params ??= $this->getParams();
        return require dirname(__DIR__) . '/config/di' . (null !== $postfix ? '-' . $postfix : '') . '.php';
    }

    /**
     * @return array<string, mixed>
     */
    private function getParams(): array
    {
        return require dirname(__DIR__) . '/config/params.php';
    }

    /**
     * @throws ReflectionException
     */
    private function getPropertyValue(object $object, string $propertyName): mixed
    {
        $property = (new ReflectionObject($object))->getProperty($propertyName);

        return $property->getValue($object);
    }
}
