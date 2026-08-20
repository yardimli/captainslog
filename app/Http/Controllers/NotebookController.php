<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotebookController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:160',
                Rule::unique('notebooks')->where(fn ($query) => $query->where('user_id', $request->user()->id)->whereNull('deleted_at')),
            ],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $request->user()->notebooks()->create([
            'name' => trim($data['name']),
            'color' => strtolower($data['color'] ?? '#6366f1'),
            'position' => ((int) $request->user()->notebooks()->max('position')) + 1,
        ]);

        return back()->with('status', 'Notebook created.');
    }
}
