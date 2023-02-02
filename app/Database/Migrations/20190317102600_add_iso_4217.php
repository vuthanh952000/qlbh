<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class Migration_add_iso_4217 extends Migration
{
	public function up(): void
	{
		execute_script(APPPATH . 'Database/Migrations/sqlscripts/3.3.0_add_iso_4217.sql');
	}

	public function down(): void
	{

	}
}