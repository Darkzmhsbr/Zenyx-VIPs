<?php
declare(strict_types=1);

namespace Database\Migrations;

use App\Core\Migration;

/**
 * Migration para criar tabela de pagamentos
 * 
 * @author Bot Zenyx
 * @version 1.0.0
 */
final class CreatePaymentsTable extends Migration
{
    public function up(): void
    {
        $this->create('payments', function($table) {
            $table->id();
            $table->bigInteger('user_id')->unsigned();
            $table->bigInteger('bot_id')->unsigned();
            $table->bigInteger('plan_id')->unsigned()->nullable();
            $table->string('transaction_id', 100)->unique()->nullable();
            $table->string('external_reference', 100)->unique();
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded', 'expired'])->default('pending');
            $table->enum('payment_method', ['pix', 'credit_card', 'boleto'])->default('pix');
            $table->text('pix_code')->nullable();
            $table->text('pix_qrcode')->nullable();
            $table->datetime('paid_at')->nullable();
            $table->datetime('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            // Índices
            $table->index('user_id');
            $table->index('bot_id');
            $table->index('plan_id');
            $table->index('transaction_id');
            $table->index('external_reference');
            $table->index('status');
            $table->index('payment_method');
            $table->index('created_at');
            
            // Chaves estrangeiras
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('CASCADE');
                  
            $table->foreign('bot_id')
                  ->references('id')
                  ->on('bots')
                  ->onDelete('CASCADE');
        });
    }

    public function down(): void
    {
        $this->dropIfExists('payments');
    }
}