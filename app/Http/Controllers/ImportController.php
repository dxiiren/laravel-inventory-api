<?php

namespace App\Http\Controllers;

use App\Models\Import;

class ImportController extends Controller
{
    public function show(Import $import): Import
    {
        return $import;
    }
}
