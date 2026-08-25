<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection {
    if (!interface_exists(ContainerInterface::class)) {
        interface ContainerInterface {
            public const EXCEPTION_ON_INVALID_REFERENCE = 1;
            public const NULL_ON_INVALID_REFERENCE = 2;

            public function set(string $id, ?object $service): void;
            public function get(string $id, int $invalidBehavior = self::EXCEPTION_ON_INVALID_REFERENCE): ?object;
            public function has(string $id): bool;
            public function initialized(string $id): bool;
            public function getParameter(string $name): array|bool|string|int|float|\UnitEnum|null;
            public function hasParameter(string $name): bool;
            public function setParameter(string $name, array|bool|string|int|float|\UnitEnum|null $value): void;
        }
    }
}

namespace Spinx\Tests\Support {
    use Symfony\Component\DependencyInjection\ContainerInterface;

    class TestContainer implements ContainerInterface
    {
        private array $services = [];
        private array $parameters = [];

        public function set(string $id, ?object $service): void
        {
            if ($service === null) {
                unset($this->services[$id]);
            } else {
                $this->services[$id] = $service;
            }
        }

        public function get(string $id, int $invalidBehavior = self::EXCEPTION_ON_INVALID_REFERENCE): ?object
        {
            if (isset($this->services[$id])) {
                return $this->services[$id];
            }

            if (class_exists($id)) {
                $instance = new $id();
                $this->services[$id] = $instance;
                return $instance;
            }

            if ($invalidBehavior === self::EXCEPTION_ON_INVALID_REFERENCE) {
                throw new \InvalidArgumentException("Service not found: {$id}");
            }

            return null;
        }

        public function has(string $id): bool
        {
            return isset($this->services[$id]) || class_exists($id);
        }

        public function initialized(string $id): bool
        {
            return isset($this->services[$id]);
        }

        public function getParameter(string $name): array|bool|string|int|float|\UnitEnum|null
        {
            return $this->parameters[$name] ?? null;
        }

        public function hasParameter(string $name): bool
        {
            return array_key_exists($name, $this->parameters);
        }

        public function setParameter(string $name, array|bool|string|int|float|\UnitEnum|null $value): void
        {
            $this->parameters[$name] = $value;
        }
    }
}
