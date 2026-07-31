<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketDetail extends Model
{
    use HasFactory;

    protected $table = 'ticket_details';

    protected $fillable = [
        'id_no',
        'step_no',
        'product_code',
        'product_name',
        'product_type',
        'target_wt',
        'actual_wt',
        'unit',
        'comp_date',
        'comp_time',
        'transfer_state',
        'error_code',
        'machine',
        'tank_no',
        'id_type',
        'product_lot',
        'recipe_code',
        'lr',
        'fabric_weight',
        'volume',
        'recipe_type',
        'conc',
        'conc_unit',
        'remark',
        'adjust',
        'price',
        'res_double1',
        'res_double2',
        'res_double3',
        'res_double4',
        'res_string1',
        'res_string2',
        'res_string3',
        'res_string4',
        'reweight',
        'dye_weight_time',
        're_dye',
        'user_code',
        'user_account',
        'batch_no',
        'record_order',
        'station',
        'process',
        'gravity',
        'current_stock',
    ];

    protected $casts = [
        'step_no' => 'integer',
        'target_wt' => 'decimal:4',
        'actual_wt' => 'decimal:4',
        'lr' => 'decimal:4',
        'fabric_weight' => 'decimal:4',
        'volume' => 'decimal:4',
        'conc' => 'decimal:4',
        'price' => 'decimal:4',
        'res_double1' => 'double',
        'res_double2' => 'double',
        'res_double3' => 'double',
        'res_double4' => 'double',
        'dye_weight_time' => 'datetime',
        'record_order' => 'integer',
        'gravity' => 'decimal:4',
        'current_stock' => 'decimal:4',
    ];
}
