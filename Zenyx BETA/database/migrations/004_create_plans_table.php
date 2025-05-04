<?php
declare(strict_types=1);

namespace Database\Migrations;

use App\Core\Migration;

/**
 * Migration para criar tabela de planos
 * 
 * @author Bot Zenyx
 * @version 1.0.0
 */
final class CreatePlansTable extends Migration
{
    public function up(): void
    {
        $this->create('plans', function($table) {
            $table->id();
            $table->bigInteger('bot_id')->unsigned();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->enum('duration_type', ['days', 'months', 'years', 'lifetime']);
            $table->integer('duration_value')->nullable();
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('trial_days')->default(0);
            $table->integer('max_users')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            // Índices
            $table->index('bot_id');
            $table->index('is_active');
            $table->index('price');
            $table->index('sort_order');
            $table->index('created_at');
            
            // Chave estrangeira
            $table->foreign('bot_id')
                  ->references('id')
                  ->on('bots')
                  ->onDelete('CASCADE');
        });

        // Adiciona chave estrangeira na tabela payments
        $this->alter('payments', function($table) {
            $table->foreign('plan_id')
                  ->references('id')
                  ->on('plans')
                  ->onDelete('SET NULL');
        });
    }

    public function down(): void
    {
        // Remove chave estrangeira da tabela payments
        $this->execute("
            ALTER TABLE payments 
            DROP FOREIGN KEY payments_plan_id_foreign
        ");
        
        $this->dropIfExists('plans');
    }
}