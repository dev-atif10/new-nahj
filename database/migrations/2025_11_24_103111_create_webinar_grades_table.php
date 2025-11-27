<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWebinarGradesTable extends Migration  
{
    public function up()
    {
        Schema::create('webinar_grades', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('webinar_id')->nullable();
            $table->unsignedBigInteger('student_id')->nullable();
            $table->unsignedBigInteger('term')->nullable()->default(1);
            $table->string('type')->nullable();
            $table->decimal('score', 6, 2)->nullable();
            $table->decimal('success_score', 6, 2)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->timestamps();

            // optional unique constraint to avoid duplicate entries for same webinar/student/term/type
            // $table->unique(['webinar_id', 'student_id', 'term', 'type'], 'uniq_webinar_student_term_type');
        });
    }

    public function down()
    {
        Schema::dropIfExists('webinar_grades');
    }
}
