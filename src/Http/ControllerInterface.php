<?php

namespace App\Http;

interface ControllerInterface
{
    public function handle(): void;
}