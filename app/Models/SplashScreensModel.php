<?php namespace App\Models;

use CodeIgniter\Model;

class SplashScreensModel extends Model
{
    protected $table         = 'splashscreens';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'background',
        'color',
        'image',
        'tagline',
        'logo',
        'delay',
        'theme',
        'app_id',
        'use_logo'
    ];
}