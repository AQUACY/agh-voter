<?php

namespace Database\Seeders;

use App\Models\Election;
use App\Models\User;
use App\Models\Voter;
use App\Support\BallotOffices;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => env('EC_EMAIL', 'ec@aghospital.org')],
            [
                'name' => 'Electoral Commission',
                'password' => Hash::make(env('EC_PASSWORD', 'password')),
                'role' => 'ec',
            ],
        );

        User::query()->updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@aghospital.org')],
            [
                'name' => 'System Administrator',
                'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
                'role' => 'admin',
            ],
        );

        $election = Election::query()->latest('id')->first();

        if (! $election) {
            $election = Election::query()->create([
                'name' => 'Asesewa Government Hospital Welfare Election',
                'status' => 'open',
                'opens_at' => now()->subHour(),
                'closes_at' => now()->setTimezone('Africa/Accra')->setDate(2026, 9, 23)->setTime(18, 0),
            ]);
        } else {
            $election->update([
                'name' => 'Asesewa Government Hospital Welfare Election',
                'status' => 'open',
                'published_at' => null,
                'opens_at' => now()->subHour(),
                'closes_at' => now()->setTimezone('Africa/Accra')->setDate(2026, 9, 23)->setTime(18, 0),
            ]);
        }

        BallotOffices::sync($election);

        foreach ([
            ['AGH00123', 'Demo Voter', '0241231234'],
            ['AGH00124', 'Ama Nurse', '0245556677'],
            ['AGH00125', 'Kofi Orderly', '0201112233'],
        ] as $row) {
            Voter::query()->updateOrCreate(
                [
                    'election_id' => $election->id,
                    'staff_id' => $row[0],
                ],
                [
                    'name' => $row[1],
                    'phone' => $row[2],
                    'voted_at' => null,
                    'vote_channel' => null,
                ],
            );
        }
    }
}
