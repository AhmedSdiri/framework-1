<?php


namespace App\Models\Entities;

use Smvc\Database\Entity;

class Car extends Entity
{
    public $carid;
    public $makeid;
    public $model;
    public $type;
    public $costs;
}