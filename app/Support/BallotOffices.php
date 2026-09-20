<?php

namespace App\Support;

use App\Models\BallotReceipt;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\ManualTally;
use App\Models\Position;
use App\Models\Vote;
use App\Models\Voter;

class BallotOffices
{
    /**
     * @return array<string, list<string>>
     */
    public static function all(): array
    {
        return [
            'Chairperson' => [
                'Godwin Boadi',
            ],
            'Vice Chairperson' => [
                'Happy Kwabi Darkoah',
                'Raymond Baah',
                'Mark Ofoe Zotorvi',
            ],
            'Secretary' => [
                'Abigail Esinam Tamakloe',
            ],
            'Financial Secretary' => [
                'Aaron Tetteh Madji',
                'Faith Nfodzo Dzidefo',
                'Helina Ayisiwaa',
            ],
            'Organizer' => [
                'John Wiafe',
                'Abigail Lartey',
            ],
            'Deputy Organizer' => [
                'Ernest Asante Thompson',
                'Isaac Offei',
            ],
        ];
    }

    public static function sync(Election $election): void
    {
        Vote::query()->where('election_id', $election->id)->delete();
        BallotReceipt::query()->where('election_id', $election->id)->delete();
        ManualTally::query()->where('election_id', $election->id)->delete();
        Candidate::query()->whereIn('position_id', $election->positions()->select('id'))->delete();
        $election->positions()->delete();

        Voter::query()->where('election_id', $election->id)->update([
            'voted_at' => null,
            'vote_channel' => null,
        ]);

        $order = 1;
        foreach (self::all() as $name => $candidates) {
            $position = Position::query()->create([
                'election_id' => $election->id,
                'name' => $name,
                'sort_order' => $order++,
            ]);

            foreach ($candidates as $index => $candidate) {
                Candidate::query()->create([
                    'position_id' => $position->id,
                    'name' => $candidate,
                    'sort_order' => $index + 1,
                ]);
            }
        }
    }
}
