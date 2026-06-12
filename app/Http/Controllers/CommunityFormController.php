<?php

namespace App\Http\Controllers;

use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommunityFormController extends Controller
{
    public function store(Request $request, string $type)
    {
        abort_unless(in_array($type, ['contact', 'application', 'partnership']), 404);

        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:180'],
            'subject' => [$type === 'application' ? 'required' : 'nullable', 'string', 'max:160'],
            'message' => ['required', 'string', 'min:20', 'max:5000'],
        ];
        if ($type === 'application') {
            $rules += [
                'age' => ['required', 'integer', 'min:16', 'max:99'],
                'experience' => ['required', 'string', 'min:20', 'max:3000'],
                'availability' => ['required', 'string', 'min:5', 'max:1000'],
            ];
        }
        $data = $request->validate($rules);

        if ($type === 'application') {
            Application::create([
                'user_id' => $request->user()?->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'position' => $data['subject'],
                'answers' => [
                    'age' => $data['age'],
                    'experience' => $data['experience'],
                    'motivation' => $data['message'],
                    'availability' => $data['availability'],
                    'discord_username' => $request->user()?->discord_username,
                ],
                'status' => 'new',
            ]);

            return back()->with('success', 'Je sollicitatie is ontvangen. Management kan deze nu in MijnCN beoordelen.');
        }

        DB::table('inquiries')->insert([
            'type' => $type,
            'name' => $data['name'],
            'email' => $data['email'],
            'subject' => $data['subject'] ?? null,
            'message' => $data['message'],
            'status' => 'new',
            'meta' => json_encode(['user_id' => $request->user()?->id]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Bedankt. Je bericht is veilig ontvangen.');
    }
}
