<?php

namespace Abs\SerialNumberPkg;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SerialNumberSegment extends Model {
	use SoftDeletes;
	protected $table = 'serial_number_segments';
	protected $fillable = [
		'created_by_id',
		'updated_by_id',
		'deleted_by_id',
	];
}
