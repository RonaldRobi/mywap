<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Poll;
use App\Models\PollQuestion;
use App\Models\PollOption;
use App\Models\PollResponse;
use App\Models\PollAnswer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PollDemoSeeder extends Seeder
{
    public function run(): void
    {
        $orgs = Organization::all();
        if ($orgs->isEmpty()) {
            $this->command->warn('No organizations found. Skipping.');
            return;
        }

        $pkpim = $orgs->firstWhere('slug', 'pkpim') ?? $orgs->first();
        $abim  = $orgs->firstWhere('slug', 'abim') ?? $orgs->skip(1)->first() ?? $orgs->first();
        $wadah = $orgs->firstWhere('slug', 'wadah') ?? $orgs->last();

        // ─── Poll 1: Simple poll, all members PKPIM ────────────────────────────

        $poll1 = Poll::create([
            'organization_id' => $pkpim->id,
            'title' => 'Modul Tarbiyah yang Paling Digemari',
            'description' => 'Bantu kami mengenal pasti modul tarbiyah yang paling memberi impak kepada ahli PKPIM.',
            'type' => 'poll',
            'target_type' => 'all',
            'ends_at' => now()->addDays(7),
            'show_results' => true,
            'is_active' => true,
        ]);

        $q1 = PollQuestion::create([
            'poll_id' => $poll1->id,
            'question_text' => 'Modul tarbiyah mana yang paling memberi manfaat kepada anda?',
            'type' => 'single_choice',
            'sort_order' => 0,
        ]);

        $opts = ['Fardhu Ain', 'Fardhu Kifayah', 'Sirah Nabawiyah', 'Tafsir Al-Quran', 'Kemahiran Kepimpinan'];
        foreach ($opts as $i => $opt) {
            PollOption::create([
                'poll_question_id' => $q1->id,
                'option_text' => $opt,
                'sort_order' => $i,
            ]);
        }

        // ─── Poll 2: For all orgs ───────────────────────────────────────────────

        $poll2 = Poll::create([
            'organization_id' => $abim->id,
            'title' => 'Waktu Program Paling Sesuai',
            'description' => 'Kami nak tahu masa terbaik untuk program akan datang. Undian ini untuk semua organisasi.',
            'type' => 'poll',
            'target_type' => 'all_orgs',
            'ends_at' => now()->addDays(14),
            'show_results' => true,
            'is_active' => true,
        ]);

        $q2 = PollQuestion::create([
            'poll_id' => $poll2->id,
            'question_text' => 'Hari apa paling sesuai untuk program?',
            'type' => 'single_choice',
            'sort_order' => 0,
        ]);

        $days = ['Jumaat', 'Sabtu', 'Ahad'];
        foreach ($days as $i => $day) {
            PollOption::create([
                'poll_question_id' => $q2->id,
                'option_text' => $day,
                'sort_order' => $i,
            ]);
        }

        // ─── Poll 3: Survey, multiple questions ─────────────────────────────────

        $poll3 = Poll::create([
            'organization_id' => $wadah->id,
            'title' => 'Kajian Keperluan Pembangunan Profesional',
            'description' => 'Bantu kami memahami keperluan pembangunan profesional ahli WADAH untuk merancang program yang lebih relevan.',
            'type' => 'survey',
            'target_type' => 'all',
            'ends_at' => now()->addDays(21),
            'show_results' => true,
            'is_active' => true,
        ]);

        $q3a = PollQuestion::create([
            'poll_id' => $poll3->id,
            'question_text' => 'Bidang pembangunan profesional yang paling diminati?',
            'type' => 'multiple_choice',
            'sort_order' => 0,
        ]);

        $fields = ['Kewangan & Pelaburan', 'Keusahawanan Digital', 'Komunikasi & Public Speaking', 'Kesihatan Mental', 'Teknologi AI'];
        foreach ($fields as $i => $f) {
            PollOption::create([
                'poll_question_id' => $q3a->id,
                'option_text' => $f,
                'sort_order' => $i,
            ]);
        }

        $q3b = PollQuestion::create([
            'poll_id' => $poll3->id,
            'question_text' => 'Format program yang paling anda sukai?',
            'type' => 'single_choice',
            'sort_order' => 1,
        ]);

        $formats = ['Bengkel / Workshop', 'Webinar Online', 'Konvensyen Tahunan', 'Mentoring 1-on-1', 'Modul E-Learning'];
        foreach ($formats as $i => $f) {
            PollOption::create([
                'poll_question_id' => $q3b->id,
                'option_text' => $f,
                'sort_order' => $i,
            ]);
        }

        $q3c = PollQuestion::create([
            'poll_id' => $poll3->id,
            'question_text' => 'Berapa kerap anda menyertai program pembangunan diri?',
            'type' => 'single_choice',
            'sort_order' => 2,
        ]);

        $freqs = ['Setiap minggu', 'Setiap bulan', 'Setiap 3 bulan', 'Jarang-jarang'];
        foreach ($freqs as $i => $f) {
            PollOption::create([
                'poll_question_id' => $q3c->id,
                'option_text' => $f,
                'sort_order' => $i,
            ]);
        }

        // ─── Poll 4: Expired poll ──────────────────────────────────────────────

        $poll4 = Poll::create([
            'organization_id' => $pkpim->id,
            'title' => 'Cadangan Aktiviti Raya',
            'description' => 'Undian tahun lepas — dah tamat.',
            'type' => 'poll',
            'target_type' => 'all',
            'ends_at' => now()->subDays(5),
            'show_results' => true,
            'is_active' => true,
        ]);

        $q4 = PollQuestion::create([
            'poll_id' => $poll4->id,
            'question_text' => 'Aktiviti raya apa yang anda nak?',
            'type' => 'single_choice',
            'sort_order' => 0,
        ]);

        $raya = ['Rumah Terbuka', 'Ziarah Ahli', 'Gotong-royong', 'Makan Besar'];
        foreach ($raya as $i => $r) {
            PollOption::create([
                'poll_question_id' => $q4->id,
                'option_text' => $r,
                'sort_order' => $i,
            ]);
        }

        // ─── Generate some responses ─────────────────────────────────────────

        $members = User::whereDoesntHave('roles', fn($q) => $q->whereIn('name', ['Superadmin', 'Admin']))->get();

        foreach ($members as $member) {
            $orgId = $member->current_organization_id;

            // Respond to poll1 if PKPIM
            if ($orgId === $pkpim->id) {
                $this->makeResponse($member, $poll1, $q1, $orgId);
            }

            // Respond to poll2 (all_orgs)
            $this->makeResponse($member, $poll2, $q2, $orgId);

            // Respond to poll3 if WADAH
            if ($orgId === $wadah->id) {
                $resp = PollResponse::create([
                    'user_id' => $member->id,
                    'poll_id' => $poll3->id,
                    'organization_id' => $orgId,
                    'submitted_at' => now()->subHours(rand(1, 48)),
                ]);

                // Q1: multiple_choice — pick 2 random
                $options3a = $q3a->options->pluck('id')->shuffle()->take(2);
                foreach ($options3a as $optId) {
                    PollAnswer::create([
                        'poll_response_id' => $resp->id,
                        'poll_question_id' => $q3a->id,
                        'poll_option_id' => $optId,
                    ]);
                }

                // Q2: single_choice — pick 1 random
                PollAnswer::create([
                    'poll_response_id' => $resp->id,
                    'poll_question_id' => $q3b->id,
                    'poll_option_id' => $q3b->options->pluck('id')->random(),
                ]);

                // Q3: single_choice — pick 1 random
                PollAnswer::create([
                    'poll_response_id' => $resp->id,
                    'poll_question_id' => $q3c->id,
                    'poll_option_id' => $q3c->options->pluck('id')->random(),
                ]);
            }

            // Respond to poll4 if PKPIM (expired)
            if ($orgId === $pkpim->id) {
                $this->makeResponse($member, $poll4, $q4, $orgId);
            }
        }

        $this->command->info('✅  Demo polls seeded: '.Poll::count().' polls, '.PollResponse::count().' responses, '.PollAnswer::count().' answers');
    }

    private function makeResponse(User $user, Poll $poll, PollQuestion $question, int $orgId): void
    {
        $resp = PollResponse::create([
            'user_id' => $user->id,
            'poll_id' => $poll->id,
            'organization_id' => $orgId,
            'submitted_at' => now()->subHours(rand(1, 72)),
        ]);

        PollAnswer::create([
            'poll_response_id' => $resp->id,
            'poll_question_id' => $question->id,
            'poll_option_id' => $question->options->pluck('id')->random(),
        ]);
    }
}
