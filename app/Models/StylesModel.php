<?php namespace App\Models;

use CodeIgniter\Model;

class StylesModel extends Model
{
    protected $table         = 'styles';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'app_id',
        'name'
    ];
    protected $returnType    = 'array';
}