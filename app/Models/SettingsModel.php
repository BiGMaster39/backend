<?php namespace App\Models;

use CodeIgniter\Model;

class SettingsModel extends Model
{
    protected $table         = 'settings';
    protected $primaryKey    = 'set_key';
    protected $allowedFields = [
        'set_key',
        'value'
    ];
    protected $returnType    = 'array';
}