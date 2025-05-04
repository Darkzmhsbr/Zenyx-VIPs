<?php
declare(strict_types=1);

namespace Database\Migrations;

use App\Core\Migration;

/**
 * Migration para criar tabela de bots
 * 
 * @author Bot Zenyx
 * @version 1.0.0
 */
final class CreateBotsTable extends Migration
{
    public function up(): void
    {
        $this->create('bots', function($table) {
            $table->id();
            $table->bigInteger('user_id')->unsigned();
            $table->string('token', 255)->unique();
            $table->string('username', 100);
            $table->string('pushinpay_token', 255)->nullable();
            $table->string('webhook_url', 255)->nullable();
            $table->enum('status', ['active', 'inactive', 'suspended', 'error'])->default('active');
            $table->json('settings')->nullable();
            $table->text('welcome_message')->nullable();
            $table->json('welcome_media')->nullable();
            $table->string('channel_id', 50)->nullable();
            $table->string('group_id', 50)->nullable();
            $table->timestamps();
            
            // Índices
            $table->index('user_id');
            $table->index('token');
            $table->index('username');
            $table->index('status');
            $table->index('created_at');
            
            // Chave estrangeira
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('CASCADE');
        });
    }

    public function down(): void
    {
        $this->dropIfExists('bots');
    }
}