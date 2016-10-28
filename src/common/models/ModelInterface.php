<?php

namespace App\Models;

interface ModelInterface
{
    public function validate();
    public function create();
    public function update();
}