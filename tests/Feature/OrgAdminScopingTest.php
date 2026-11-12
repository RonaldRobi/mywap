<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\Donor;
use App\Models\Infaq;
use App\Models\InfaqDonation;
use App\Models\NewsPost;
use App\Models\Organization;
use App\Models\Popup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Organisation admins get the same management abilities as a Superadmin, but
 * strictly scoped to their own organisation. These tests lock that boundary so
 * a future change cannot silently widen it.
 */
class OrgAdminScopingTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;

    private Organization $orgB;

    private User $adminA;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Superadmin', 'Admin', 'org-admin', 'Member'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }

        $this->orgA = Organization::factory()->create(['name' => 'PKPIM']);
        $this->orgB = Organization::factory()->create(['name' => 'ABIM']);

        $this->adminA = User::factory()->create([
            'current_organization_id' => $this->orgA->id,
            'profile_completed_at' => now(),
        ]);
        $this->adminA->assignRole('Admin');

        $this->superadmin = User::factory()->create([
            'current_organization_id' => null,
            'profile_completed_at' => now(),
        ]);
        $this->superadmin->assignRole('Superadmin');
    }

    private function makeInfaq(Organization $org, string $title): Infaq
    {
        return Infaq::create([
            'organization_id' => $org->id,
            'title' => $title,
            'type' => 'one_off',
            'is_active' => true,
        ]);
    }

    private function makeArticle(Organization $org, string $title): Article
    {
        return Article::create([
            'author_id' => $this->superadmin->id,
            'organization_id' => $org->id,
            'title' => $title,
            'slug' => strtolower(str_replace(' ', '-', $title)).'-'.uniqid(),
            'content' => 'Kandungan',
            'is_published' => true,
        ]);
    }

    private function makePopup(?Organization $org, string $title): Popup
    {
        return Popup::create([
            'organization_id' => $org?->id,
            'title' => $title,
        ]);
    }

    private function makeNewsPost(?Organization $org, string $title): NewsPost
    {
        return NewsPost::create([
            'author_id' => $this->superadmin->id,
            'organization_id' => $org?->id,
            'title' => $title,
            'content' => 'Kandungan',
            'is_published' => true,
        ]);
    }

    // ─── Editors ──────────────────────────────────────────────────────────────

    public function test_org_admin_only_sees_donors_of_own_organisation(): void
    {
        $infaqA = $this->makeInfaq($this->orgA, 'Infaq A');
        $infaqB = $this->makeInfaq($this->orgB, 'Infaq B');

        $donorA = Donor::create(['name' => 'Penderma A', 'email' => 'a@example.test']);
        $donorB = Donor::create(['name' => 'Penderma B', 'email' => 'b@example.test']);

        InfaqDonation::create([
            'infaq_id' => $infaqA->id,
            'donor_id' => $donorA->id,
            'user_id' => null,
            'amount' => 100,
            'reference' => 'REF-A',
            'status' => 'confirmed',
        ]);
        InfaqDonation::create([
            'infaq_id' => $infaqB->id,
            'donor_id' => $donorB->id,
            'user_id' => null,
            'amount' => 200,
            'reference' => 'REF-B',
            'status' => 'confirmed',
        ]);

        $this->actingAs($this->adminA)
            ->get(route('admin.donors.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Admin/Donors/Index')
                ->has('donors.data', 1)
                ->where('donors.data.0.name', 'Penderma A')
                ->where('summary.total_amount', 100));

        $this->actingAs($this->adminA)
            ->get(route('admin.donors.show', $donorB))
            ->assertForbidden();
    }

    public function test_org_admin_infaq_is_scoped_and_cannot_be_reassigned(): void
    {
        $infaqB = $this->makeInfaq($this->orgB, 'Infaq B');

        $this->actingAs($this->adminA)
            ->put(route('superadmin.infaq.update', $infaqB), [
                'title' => 'Hack',
                'type' => 'one_off',
            ])
            ->assertForbidden();

        $this->actingAs($this->adminA)
            ->delete(route('superadmin.infaq.destroy', $infaqB))
            ->assertForbidden();

        // A submitted organisation_id is ignored; the campaign lands on own org.
        $this->actingAs($this->adminA)
            ->post(route('superadmin.infaq.store'), [
                'organization_id' => $this->orgB->id,
                'title' => 'Infaq Baru',
                'type' => 'one_off',
            ])
            ->assertSessionHasNoErrors();

        $created = Infaq::where('title', 'Infaq Baru')->firstOrFail();
        $this->assertSame($this->orgA->id, $created->organization_id);
    }

    public function test_org_admin_articles_are_scoped(): void
    {
        $articleB = $this->makeArticle($this->orgB, 'Artikel B');

        $this->actingAs($this->adminA)
            ->get(route('admin.articles.edit', $articleB))
            ->assertForbidden();
        $this->actingAs($this->adminA)
            ->put(route('admin.articles.update', $articleB), ['title' => 'Hack', 'content' => 'x'])
            ->assertForbidden();
        $this->actingAs($this->adminA)
            ->delete(route('admin.articles.destroy', $articleB))
            ->assertForbidden();

        $this->actingAs($this->adminA)
            ->get(route('admin.articles.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Admin/Articles/Index')->has('articles.data', 0));

        $this->actingAs($this->adminA)
            ->post(route('admin.articles.store'), [
                'organization_id' => $this->orgB->id,
                'title' => 'Artikel Baru',
                'content' => 'Kandungan',
                'is_published' => true,
            ])
            ->assertSessionHasNoErrors();

        $created = Article::where('title', 'Artikel Baru')->firstOrFail();
        $this->assertSame($this->orgA->id, $created->organization_id);
    }

    public function test_org_admin_popups_are_scoped(): void
    {
        $popupB = $this->makePopup($this->orgB, 'Popup B');

        $this->actingAs($this->adminA)
            ->put(route('admin.popups.update', $popupB), ['title' => 'Hack'])
            ->assertForbidden();
        $this->actingAs($this->adminA)
            ->delete(route('admin.popups.destroy', $popupB))
            ->assertForbidden();
    }

    public function test_org_admin_cannot_create_global_news_or_edit_its_posts(): void
    {
        $globalPost = $this->makeNewsPost(null, 'Global Post');

        $this->actingAs($this->adminA)
            ->put(route('admin.news.update', $globalPost), ['title' => 'Hack', 'content' => 'x'])
            ->assertForbidden();
        $this->actingAs($this->adminA)
            ->delete(route('admin.news.destroy', $globalPost))
            ->assertForbidden();

        // Submitting a null organisation now falls back to the admin's own org.
        $this->actingAs($this->adminA)
            ->post(route('admin.news.store'), [
                'organization_id' => null,
                'title' => 'Post Baru',
                'content' => 'Kandungan',
                'is_published' => true,
            ])
            ->assertSessionHasNoErrors();

        $created = NewsPost::where('title', 'Post Baru')->firstOrFail();
        $this->assertSame($this->orgA->id, $created->organization_id);
    }

    public function test_org_admin_category_scoping(): void
    {
        $global = Category::create(['name' => 'Global', 'organization_id' => null]);
        $catB = Category::create(['name' => 'Kategori B', 'organization_id' => $this->orgB->id]);

        $this->actingAs($this->adminA)
            ->put(route('categories.update', $global), ['name' => 'Hack'])
            ->assertForbidden();
        $this->actingAs($this->adminA)
            ->put(route('categories.update', $catB), ['name' => 'Hack'])
            ->assertForbidden();

        $this->actingAs($this->adminA)
            ->post(route('categories.store'), ['name' => 'Kategori A'])
            ->assertSessionHasNoErrors();

        $created = Category::where('name', 'Kategori A')->firstOrFail();
        $this->assertSame($this->orgA->id, $created->organization_id);

        // Own category can be edited.
        $this->actingAs($this->adminA)
            ->put(route('categories.update', $created), ['name' => 'Kategori A2'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Kategori A2', $created->fresh()->name);
    }
}
