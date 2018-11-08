<?php

namespace PhpToGo\Converter;

interface Hook
{
    public function register(GoLikePrettyPrinter $printer);
}