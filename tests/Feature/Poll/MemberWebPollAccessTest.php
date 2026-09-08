<?php

namespace Tests\Feature\Poll;

use App\Models\Organization;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MemberWebPollAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_open_all_orgs_poll_owned_by_another_org(): void
    {
        Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Member', 'guard_name' => 'web']);

        $orgA = Organization::factory()->create(['name' => 'Org A']);
        $orgB = Organization::factory()->create(['name' => 'Org B']);

        $member = User::factory()->create([
            'current_organization_id' => $orgA->id,
            'profile_completed_at' => now(),
        ]);
        $member->assignRole('Member');

        $poll = Poll::create([
            'organization_id' => $orgB->id, 'title' => 'Rentas Org', 'type' => 'poll',
            'target_type' => 'all_orgs', 'ends_at' => now()->addDays(3), 'show_results' => true, 'is_active' => true,
        ]);
        $q = PollQuestion::create(['poll_id' => $poll->id, 'question_text' => 'S', 'type' => 'single_choice', 'sort_order' => 0]);
        PollOption::create(['poll_question_id' => $q->id, 'option_text' => 'A', 'sort_order' => 0]);

        // Regresi: Route::bind('poll', withoutGlobalScopes) dulu hidup dalam fail
        // route; route:cache di deploy membuangnya lalu undian all_orgs org lain
        // jadi 404. Bind kini didaftar dalam service provider supaya kekal.
        $this->actingAs($member, 'web')
            ->get(route('member.polls.show', $poll->id))
            ->assertOk();

        $this->actingAs($member, 'web')
            ->get(route('member.polls.results', $poll->id))
            ->assertOk();
    }
}
