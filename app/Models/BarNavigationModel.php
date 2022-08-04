<?php namespace App\Models;

use CodeIgniter\Model;

class BarNavigationModel extends Model
{
    protected $table         = 'bar_navigation';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'app_id',
        'type',
        'icon',
        'link',
        'name'
    ];
    protected $returnType    = 'array';
}