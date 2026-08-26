<?php

namespace Tests\Feature\Api\V1;

use App\Models\Organization;
use App\Models\Poll;
use App\Models\PollAnswer;
use App\Models\PollOption;
use App\Models\PollQuestion;
use App\Models\PollResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PollApiTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private Organization $otherOrg;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Member', 'guard_name' => 'web']);

        $this->org = Organization::factory()->create(['name' => 'PKPIM']);
        $this->otherOrg = Organization::factory()->create(['name' => 'ABIM']);

        $this->member = User::factory()->create([
            'current_organization_id' => $this->org->id,
            'profile_completed_at' => now(),
            'member_no' => 'PKPIM-0001',
        ]);
        $this->member->assignRole('Member');
    }

    private function makePoll(?int $orgId = null, array $overrides = []): Poll
    {
        $poll = Poll::create(array_merge([
            'organization_id' => $orgId ?? $this->org->id,
            'title' => 'Undian Test',
            'description' => 'Undian ujian',
            'type' => 'poll',
            'target_type' => 'all',
            'ends_at' => now()->addDays(3),
            'show_results' => true,
            'is_active' => true,
        ], $overrides));

        $question = PollQuestion::create([
            'poll_id' => $poll->id,
            'question_text' => 'Soalan 1',
            'type' => 'single_choice',
            'sort_order' => 0,
        ]);

        PollOption::create(['poll_question_id' => $question->id, 'option_text' => 'Pilihan A', 'sort_order' => 0]);
        PollOption::create(['poll_question_id' => $question->id, 'option_text' => 'Pilihan B', 'sort_order' => 1]);

        return $poll;
    }

    public function test_unauthenticated_cannot_list_polls(): void
    {
        $this->getJson('/api/v1/polls')->assertStatus(401);
    }

    public function test_member_can_list_available_and_answered_polls(): void
    {
        $poll = $this->makePoll();
        $this->makePoll($this->otherOrg->id);

        Sanctum::actingAs($this->member);

        $this->getJson('/api/v1/polls')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'availablePolls' => [
                        ['id', 'title', 'type', 'ends_at', 'has_responded'],
                    ],
                    'answeredPolls' => [],
                ],
            ])
            ->assertJsonCount(1, 'data.availablePolls')
            ->assertJsonPath('data.availablePolls.0.id', $poll->id)
            ->assertJsonPath('data.availablePolls.0.has_responded', false);
    }

    public function test_member_can_view_poll(): void
    {
        $poll = $this->makePoll();

        Sanctum::actingAs($this->member);

        $this->getJson("/api/v1/polls/{$poll->id}")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'poll' => [
                        'id', 'title', 'description', 'type',
                        'questions' => [
                            ['id', 'question_text', 'options' => [['id', 'option_text']]],
                        ],
                    ],
                ],
            ])
            ->assertJsonPath('data.poll.id', $poll->id)
            ->assertJsonPath('data.poll.questions.0.question_text', 'Soalan 1');
    }

    public function test_member_can_respond_to_poll(): void
    {
        $poll = $this->makePoll();
        $question = $poll->questions()->first();
        $option = $question->options()->first();

        Sanctum::actingAs($this->member);

        $this->postJson("/api/v1/polls/{$poll->id}/respond", [
            'answers' => [
                ['question_id' => $question->id, 'option_ids' => [$option->id]],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.response_id', fn ($id) => is_int($id));

        $this->assertDatabaseHas('poll_responses', [
            'poll_id' => $poll->id,
            'user_id' => $this->member->id,
        ]);

        $this->assertDatabaseHas('poll_answers', [
            'poll_question_id' => $question->id,
            'poll_option_id' => $option->id,
        ]);
    }

    public function test_duplicate_poll_respond_returns_409(): void
    {
        $poll = $this->makePoll();
        $question = $poll->questions()->first();
        $option = $question->options()->first();

        Sanctum::actingAs($this->member);

        $payload = [
            'answers' => [
                ['question_id' => $question->id, 'option_ids' => [$option->id]],
            ],
        ];

        $this->postJson("/api/v1/polls/{$poll->id}/respond", $payload)->assertOk();
        $this->postJson("/api/v1/polls/{$poll->id}/respond", $payload)->assertStatus(409);
    }

    public function test_member_can_view_poll_results(): void
    {
        $poll = $this->makePoll();
        $question = $poll->questions()->first();
        $option = $question->options()->first();

        $response = PollResponse::create([
            'user_id' => $this->member->id,
            'poll_id' => $poll->id,
            'organization_id' => $this->org->id,
            'submitted_at' => now(),
        ]);
        PollAnswer::create([
            'poll_response_id' => $response->id,
            'poll_question_id' => $question->id,
            'poll_option_id' => $option->id,
        ]);

        Sanctum::actingAs($this->member);

        $this->getJson("/api/v1/polls/{$poll->id}/results")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'poll' => ['id', 'title'],
                    'questions' => [
                        ['id', 'question_text', 'options' => [['id', 'option_text', 'count', 'percentage']]],
                    ],
                    'total_responses',
                    'my_answers',
                ],
            ])
            ->assertJsonPath('data.total_responses', 1)
            ->assertJsonPath('data.questions.0.options.0.count', 1)
            ->assertJsonPath('data.my_answers', [$option->id]);
    }

    public function test_results_hidden_when_no_response_and_show_results_disabled(): void
    {
        $poll = $this->makePoll($this->org->id, ['show_results' => false]);

        Sanctum::actingAs($this->member);

        $this->getJson("/api/v1/polls/{$poll->id}/results")->assertStatus(403);
    }
}
