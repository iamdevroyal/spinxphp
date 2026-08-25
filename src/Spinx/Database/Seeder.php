<?php

declare(strict_types=1);

namespace Spinx\Database;

abstract class Seeder
{
    abstract public function run(): void;
}
