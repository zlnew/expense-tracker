<?php

namespace App\Actions;

abstract class Action
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
