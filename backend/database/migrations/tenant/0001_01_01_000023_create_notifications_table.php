<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant database: `notifications` table (M6, R27.6).
 *
 * Backs the in-app notification centre: a durable history of the key events a
 * tenant user is informed about (account-created, order-placed, order-confirmed,
 * payment-recorded, …), each addressed to a single tenant user and carrying a
 * read/unread state the user can toggle via mark-one-read and mark-all-read
 * (R27.6). The same rows are the in-app mirror of the queued email side-effects
 * (R27.1–R27.4) and, where the `whatsapp_notifications` flag is enabled, of the
 * optional SMS/WhatsApp channels (R27.5).
 *
 * Isolation is physical: this is a tenant-owned table reached only once tenancy
 * is initialized, so there is NO `tenant_id` column — the row lives in the
 * active tenant database. The owning principal is captured in `user_id`
 * (R27.6).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            // The tenant user this notification is addressed to. The in-app
            // notification centre is per-user: a user only ever lists, reads,
            // and marks-all-read their OWN notifications (R27.6).
            $table->unsignedBigInteger('user_id');

            // A stable machine key for the event that produced this
            // notification (e.g. `account_created`, `order_placed`,
            // `order_confirmed`, `payment_recorded`). Lets the UI pick an icon
            // / grouping without parsing the human text.
            $table->string('type');

            // The human-readable title + body shown in the notification centre.
            $table->string('title');
            $table->text('body')->nullable();

            // Optional structured context (order id, invoice number, amount, …)
            // so the UI can deep-link without re-deriving it from the text.
            $table->json('data')->nullable();

            // Read-state (R27.6). `read_at` is null while unread; set to the
            // moment the user marked it read (single or mark-all-read).
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            // (user_id, read_at): the notification centre lists a user's own
            // notifications and counts/queries their unread ones (read_at IS
            // NULL) — the two filters every list / unread-count query uses
            // (R27.6, R38.4).
            $table->index(['user_id', 'read_at'], 'notifications_user_read_index');

            // (user_id, id): list a user's own notifications newest-first.
            $table->index(['user_id', 'id'], 'notifications_user_recent_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
