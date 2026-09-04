<?php

namespace App\Queries;

abstract class Query
{
    abstract public function handle();

    public function __invoke()
    {
        return $this->handle();
    }

    public static function run(...$arguments)
    {
        return (new static(...$arguments))->handle();
    }
}
