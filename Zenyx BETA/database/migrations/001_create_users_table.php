<?php
declare(strict_types=1);

namespace Database\Migrations;

use App\Core\Migration;

/**
 * Migration para criar tabela de usuários
 * 
 * @author Bot Zenyx
 * @version 1.0.0
 */
final class CreateUsersTable extends Migration
{
    public function up(): void
    {
        $this->create('users', function($table) {
            $table->id();
            $table->string('telegram_id', 50)->unique();
            $table->string('username', 50)->nullable();
            $table->string('first_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->decimal('balance', 10, 2)->default(0.00);
            $table->boolean('is_admin_vip')->default(false);
            $table->datetime('admin_vip_until')->nullable();
            $table->bigInteger('referrer_id')->unsigned()->nullable();
            $table->boolean('channel_verified')->default(false);
            $table->datetime('last_interaction')->nullable();
            $table->json('settings')->nullable();
            $table->enum('status', ['active', 'inactive', 'banned'])->default('active');
            $table->timestamps();
            
            // Índices
            $table->index('telegram_id');
            $table->index('username');
            $table->index('referrer_id');
            $table->index('status');
            $table->index('created_at');
            
            // Chave estrangeira
            $table->foreign('referrer_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('SET NULL');
        });
    }

    public function down(): void
    {
        $this->dropIfExists('users');
    }
}