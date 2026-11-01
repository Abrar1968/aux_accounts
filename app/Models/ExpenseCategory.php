<?php

namespace App\Models;

class ExpenseCategory extends BaseModel
{
    protected $table = 'expense_categories';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'statement_line' => 'boolean',
        ];
    }
}
