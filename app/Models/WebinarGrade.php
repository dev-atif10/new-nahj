<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Webinar;
use App\User;

class WebinarGrade extends Model
{
    protected $table = 'webinar_grades';
    protected $guarded = [];
 
    public function webinar()
    {
        return $this->belongsTo(Webinar::class, 'webinar_id');
    }

    public function student()
    { 
        return $this->belongsTo(User::class, 'student_id');
    }
}