<?php

namespace App\Models;

use Illuminate\Notifications\DatabaseNotification;

class Notification extends DatabaseNotification
{
    // Explicitly use the default connection (mm-stock)
    // instead of inheriting from the User (depart_it_db)
    protected $connection = 'mysql'; 
    protected $table = 'notifications';
}
