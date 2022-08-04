<?php namespace App\Models;

use CodeIgniter\Model;

class PlansModel extends Model
{
    protected $table         = 'plans';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'count',
        'price',
        'save',
        'status',
        'name',
        'mark'
    ];
    protected $returnType    = 'array';
}