<?php

namespace WHMCS\Module\Server\Evorxa;

use WHMCS\Database\Capsule;

/**
 * Module tables. Created on demand and never dropped: a lost service-to-instance
 * mapping would leave paid servers running with nobody billing for them.
 */
class Schema
{
    const SERVICES = 'mod_evorxa_services';
    const LOG = 'mod_evorxa_log';
    const SETTINGS = 'mod_evorxa_settings';

    private static $checked = false;

    public static function ensure()
    {
        if (self::$checked) {
            return;
        }
        self::$checked = true;
        $schema = Capsule::schema();

        if (!$schema->hasTable(self::SERVICES)) {
            $schema->create(self::SERVICES, function ($table) {
                $table->increments('id');
                $table->unsignedInteger('service_id')->unique();
                $table->string('instance_id', 64)->nullable()->index();
                $table->unsignedInteger('project_id')->nullable();
                $table->unsignedInteger('package_id')->nullable();
                $table->string('upstream_cycle', 16)->nullable();
                $table->string('name', 100)->nullable();
                $table->string('state', 20)->default('new')->index();
                $table->string('app_slug', 64)->nullable();
                $table->string('os_label', 191)->nullable();
                $table->unsignedInteger('ssh_key_id')->nullable();
                $table->mediumText('snapshot')->nullable();
                $table->text('error')->nullable();
                $table->timestamp('creating_at')->nullable();
                $table->timestamp('ready_at')->nullable();
                $table->timestamp('ready_notified_at')->nullable();
                $table->timestamp('last_sync_at')->nullable();
                $table->timestamp('alerted_at')->nullable();
                $table->string('last_extend_billed_till', 40)->nullable();
                $table->timestamps();
            });
        }

        if (!$schema->hasTable(self::LOG)) {
            $schema->create(self::LOG, function ($table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('service_id')->nullable()->index();
                $table->string('instance_id', 64)->nullable();
                $table->string('actor', 32);
                $table->string('action', 64);
                $table->boolean('ok')->default(true);
                $table->text('message')->nullable();
                $table->integer('amount_cents')->nullable();
                $table->timestamp('created_at')->nullable()->index();
            });
        }

        if (!$schema->hasTable(self::SETTINGS)) {
            $schema->create(self::SETTINGS, function ($table) {
                $table->string('name', 64)->primary();
                $table->text('value')->nullable();
            });
        }
    }
}
