<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Branch;
use App\Models\BroadcastMessage;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Supporter;
use App\Models\UsrahGroup;
use App\Models\User;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BroadcastController extends Controller
{
    public function __construct(private readonly AdminService $broadcasts) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $isSuperadmin = $user->hasRole('Superadmin');
        $isBranchAdmin = $user->isBranchAdmin();
        $branchIds = $isBranchAdmin ? $user->managedBranchIds() : [];

        $messages = BroadcastMessage::query()
            ->with(['organization:id,name', 'targetOrganization:id,name'])
            ->when($branchIds !== [], fn ($q) => $q->whereIn('branch_id', $branchIds))
            ->latest()
            ->take(20)
            ->get();

        $recipientsByMessage = $this->resolveRecipientPreviews($messages);

        $messages = $messages->map(fn (BroadcastMessage $message) => [
                'id' => $message->id,
                'title' => $message->title,
                'action_type' => $message->action_type,
                'action_id' => $message->action_id,
                'action_label' => $message->action_label,
                'action_url' => $message->action_url,
                'target_criteria' => $message->target_criteria,
                'target_criteria_label' => match ($message->target_criteria) {
                    'all' => 'Semua Ahli',
                    'organization' => 'Ahli Organisasi',
                    'branch' => 'Ahli Cawangan',
                    'specific_members' => 'Individu Tertentu',
                    'supporters' => 'Pendukung (Bukan Ahli)',
                    default => $message->target_criteria,
                },
                'sender_name' => $message->sender_label ?? $message->organization?->name,
                'target_organization_name' => $message->targetOrganization?->name,
                'recipient_count' => is_array($message->recipient_ids) ? count($message->recipient_ids) : 0,
                'recipients_preview' => array_slice($recipientsByMessage[$message->id] ?? [], 0, 3),
                'recipients_more' => max(0, (int) count($recipientsByMessage[$message->id] ?? []) - 3),
                'notification_channels' => $message->notification_channels ?? ['in_app'],
                'sent_at' => $message->sent_at?->toDateTimeString(),
                'status' => $message->status ?? 'queued',
                'status_label' => match ($message->status) {
                    'processing' => 'Sedang Dihantar',
                    'completed' => 'Selesai Dihantar',
                    'partial' => 'Selesai (Sebahagian Gagal)',
                    'failed' => 'Gagal',
                    default => 'Menunggu Beratur (Queue)',
                },
                'total_recipients' => $message->recipient_count,
                'success_count' => $message->success_count,
                'failed_count' => $message->failed_count,
                'started_at' => $message->started_at?->toDateTimeString(),
                'finished_at' => $message->finished_at?->toDateTimeString(),
                'error_message' => $message->error_message,
            ]);

        $announcements = Announcement::query()
            ->with(['organization:id,name,slug', 'author:id,name', 'images'])
            ->withCount([
                'reactions as likes_count' => fn ($q) => $q->where('reaction', 'like'),
                'reads as reads_count',
            ])
            ->when(! $isSuperadmin, fn ($q) => $q->where('organization_id', $user->current_organization_id))
            ->when($branchIds !== [], fn ($q) => $q->whereIn('branch_id', $branchIds))
            ->latest('published_at')
            ->latest('id')
            ->take(50)
            ->get()
            ->map(fn (Announcement $item) => [
                'id' => $item->id,
                'organization_id' => $item->organization_id,
                'organization_name' => $item->organization?->name,
                'title' => $item->title,
                'content' => $item->content,
                'is_pinned' => (bool) $item->is_pinned,
                'published_at' => $item->published_at?->toDateTimeString(),
                'published_human' => $item->published_at?->locale('ms')->isoFormat('D MMM YYYY, h:mm A'),
                'cover_image_url' => $item->coverImageUrl(),
                'author_name' => $item->author?->name,
                'likes_count' => (int) $item->likes_count,
                'reads_count' => (int) $item->reads_count,
                'target_criteria' => $item->target_criteria,
                'images' => $item->images->map(fn ($img) => [
                    'id' => $img->id,
                    'url' => $img->imageUrl(),
                    'caption' => $img->caption,
                ]),
            ]);

        $organizations = $isSuperadmin
                ? Organization::query()->orderBy('min_age')->get(['id', 'name', 'slug'])
                : collect([['id' => $user->organization?->id, 'name' => $user->organization?->name]]);

        $usrahGroups = UsrahGroup::query()
            ->when($user->isBranchAdmin(), fn ($q) => $q->whereIn('branch_id', $user->managedBranchIds()))
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $branches = Branch::query()
            ->where('is_active', true)
            ->when(! $isSuperadmin, fn ($q) => $q->where('organization_id', $user->current_organization_id))
            ->when($branchIds !== [], fn ($q) => $q->whereKey($branchIds))
            ->select('id', 'name', 'organization_id')
            ->orderBy('name')
            ->get();

        $supporterCounts = $isBranchAdmin ? collect() : Supporter::query()
            ->where('is_active', true)
            ->whereNotNull('email')
            ->whereNull('converted_at')
            ->when(! $isSuperadmin, fn ($q) => $q->where('organization_id', $user->current_organization_id))
            ->selectRaw('organization_id, COUNT(*) as total')
            ->groupBy('organization_id')
            ->pluck('total', 'organization_id')
            ->map(fn ($total) => (int) $total);

        return Inertia::render('Admin/Broadcasts', [
            'recentMessages' => $messages,
            'defaultOrganizationId' => $user->current_organization_id,
            'isSuperadmin' => $isSuperadmin,
            'isBranchAdmin' => $isBranchAdmin,
            'announcements' => $announcements,
            'organizations' => $organizations,
            'usrahGroups' => $usrahGroups,
            'branches' => $branches,
            'supporterCounts' => $supporterCounts,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $isSuperadmin = $user->hasRole('Superadmin');
        $isBranchAdmin = $user->isBranchAdmin();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'action_type' => ['nullable', 'in:none,event,product,external'],
            'action_id' => ['nullable', 'integer'],
            'action_label' => ['nullable', 'string', 'max:60'],
            'action_url' => ['nullable', 'string', 'max:2048'],
            'target_criteria' => ['required', 'in:all,organization,branch,specific_members,supporters'],
            'target_organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'recipient_ids' => ['nullable', 'array'],
            'recipient_ids.*' => ['integer', 'exists:users,id'],
            'notification_channels' => ['required', 'array', 'min:1'],
            'notification_channels.*' => ['string', 'in:in_app,email'],
            'email_use_template' => ['boolean'],
        ]);

        $targetOrgId = null;

        if ($isBranchAdmin) {
            if (($data['action_type'] ?? 'none') === 'product') {
                throw ValidationException::withMessages([
                    'action_type' => 'Admin cawangan tidak boleh memautkan produk dalam siaran cawangan.',
                ]);
            }

            $data['target_criteria'] = 'branch';
            $data['branch_id'] = $user->resolveManagedBranchId($data['branch_id'] ?? null);
            $data['recipient_ids'] = null;
            $targetOrgId = $user->current_organization_id;
        } elseif ($data['target_criteria'] === 'all') {
            // 'Semua Ahli' untuk org-admin bermaksud semua ahli organisasinya sahaja,
            // bukan seluruh platform merentas tier. Superadmin: seluruh platform.
            $targetOrgId = $isSuperadmin ? null : $user->current_organization_id;
        } elseif ($data['target_criteria'] === 'organization') {
            if ($isSuperadmin) {
                $targetOrgId = $data['target_organization_id'] ?? $user->current_organization_id;
            } else {
                $targetOrgId = $user->current_organization_id;
            }
        } elseif ($data['target_criteria'] === 'specific_members') {
            if (empty($data['recipient_ids'])) {
                return back()->withErrors(['recipient_ids' => 'Sila pilih sekurang-kurangnya seorang ahli.']);
            }
            $targetOrgId = null;
        } elseif ($data['target_criteria'] === 'supporters') {
            // Pendukung cuma ada emel; siaran wajib melalui saluran emel.
            if ($isSuperadmin && blank($data['target_organization_id'] ?? null)) {
                throw ValidationException::withMessages([
                    'target_organization_id' => 'Pilih organisasi Pendukung yang ingin dihubungi.',
                ]);
            }

            $targetOrgId = $isSuperadmin
                ? (int) $data['target_organization_id']
                : $user->current_organization_id;
            $data['notification_channels'] = ['email'];
        }

        $action = $this->resolveAction($data, $user, $isSuperadmin);

        $this->broadcasts->broadcast([
            'organization_id' => $user->current_organization_id,
            'sender_label' => $isSuperadmin && $targetOrgId === null ? BroadcastMessage::PLATFORM_SENDER_LABEL : null,
            'target_organization_id' => $targetOrgId,
            'branch_id' => $data['branch_id'] ?? null,
            'title' => $data['title'],
            'content' => $data['content'],
            'action_type' => $action['action_type'],
            'action_id' => $action['action_id'],
            'action_label' => $action['action_label'],
            'action_url' => $action['action_url'],
            'target_criteria' => $data['target_criteria'],
            'recipient_ids' => $data['recipient_ids'] ?? null,
            'notification_channels' => $data['notification_channels'],
            'email_use_template' => $request->boolean('email_use_template', false),
        ]);

        return back()->with('success', 'Push notification sedang diproses dan akan dihantar berperingkat.');
    }

    /**
     * Carian destinasi untuk borang siaran (program atau produk).
     * Admin hanya perlu taip nama; id disimpan di sebalik tabir.
     */
    public function targets(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q'));
        $type = (string) $request->query('type');

        if (! in_array($type, ['event', 'product'], true)) {
            return response()->json([]);
        }

        $hasQuery = mb_strlen($query) >= 2;
        $needle = '%'.mb_strtolower($query).'%';

        $user = $request->user();
        $isSuperadmin = $user->hasRole('Superadmin');
        $isBranchAdmin = $user->isBranchAdmin();
        $orgId = (int) $user->current_organization_id;

        if ($isBranchAdmin && $type === 'product') {
            return response()->json([]);
        }

        if ($type === 'event') {
            $now = now();

            $items = Event::query()
                // LOWER() supaya carian tidak sensitif huruf besar/kecil pada
                // PostgreSQL (LIKE biasa adalah case-sensitive di sana).
                ->when($hasQuery, fn ($q) => $q->whereRaw('LOWER(title) LIKE ?', [$needle]))
                ->when($isBranchAdmin, fn ($q) => $q->whereIn('branch_id', $user->managedBranchIds()))
                ->when(! $isSuperadmin && ! $isBranchAdmin, function ($q) use ($orgId) {
                    $q->where(function ($inner) use ($orgId) {
                        $inner->where('organization_id', $orgId)
                            ->orWhereNull('organization_id')
                            ->orWhereHas('organizations', fn ($pivot) => $pivot->where('organizations.id', $orgId));
                    });
                })
                // Program yang masih berjalan/akan datang dahulu, kemudian
                // program lepas yang paling baru.
                ->orderByRaw('CASE WHEN end_time >= ? THEN 0 ELSE 1 END', [$now])
                ->orderByRaw('CASE WHEN end_time >= ? THEN start_time END ASC', [$now])
                ->orderByRaw('CASE WHEN end_time < ? THEN start_time END DESC', [$now])
                ->take(20)
                ->get(['id', 'title', 'slug', 'start_time', 'end_time', 'organization_id'])
                ->map(fn (Event $event) => [
                    'id' => $event->id,
                    'label' => $event->title,
                    'meta' => $event->start_time?->locale('ms')->isoFormat('D MMM YYYY, h:mm A'),
                ]);

            return response()->json($items);
        }

        $items = Product::query()
            ->when($hasQuery, fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', [$needle]))
            ->when(! $isSuperadmin, function ($q) use ($orgId) {
                $q->where(function ($inner) use ($orgId) {
                    $inner->where('organisasi_id', $orgId)->orWhereNull('organisasi_id');
                });
            })
            ->where('status', true)
            ->orderBy('name')
            ->take(20)
            ->get(['id', 'name', 'price', 'stock'])
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'label' => $product->name,
                'meta' => 'RM '.number_format((float) $product->price, 2).' · Stok '.$product->stock,
            ]);

        return response()->json($items);
    }

    /**
     * Tukar pilihan destinasi borang kepada kolum yang disimpan.
     * `action_url` diselesaikan di sini supaya emel dan web tidak perlu
     * membina semula pautan ketika notifikasi dihantar.
     *
     * @param  array<string, mixed>  $data
     * @return array{action_type: ?string, action_id: ?int, action_label: ?string, action_url: ?string}
     */
    private function resolveAction(array $data, User $user, bool $isSuperadmin): array
    {
        $type = (string) ($data['action_type'] ?? 'none');

        if (! in_array($type, ['event', 'product', 'external'], true)) {
            return ['action_type' => null, 'action_id' => null, 'action_label' => null, 'action_url' => null];
        }

        $label = trim((string) ($data['action_label'] ?? ''));

        if ($type === 'external') {
            $url = trim((string) ($data['action_url'] ?? ''));
            $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

            if (! in_array($scheme, ['http', 'https'], true)) {
                throw ValidationException::withMessages([
                    'action_url' => 'Pautan mesti bermula dengan http:// atau https://.',
                ]);
            }

            return [
                'action_type' => 'external',
                'action_id' => null,
                'action_label' => $label !== '' ? $label : 'Buka Pautan',
                'action_url' => $url,
            ];
        }

        $id = (int) ($data['action_id'] ?? 0);

        if ($id <= 0) {
            throw ValidationException::withMessages([
                'action_id' => 'Sila pilih destinasi daripada carian.',
            ]);
        }

        if ($type === 'event') {
            $event = Event::find($id);

            if ($event === null) {
                throw ValidationException::withMessages(['action_id' => 'Program tidak dijumpai.']);
            }

            if ($user->isBranchAdmin() && ! $user->canManageBranch($event->branch_id)) {
                abort(403, 'Tiada kebenaran untuk program ini.');
            }

            if (! $isSuperadmin && ! $user->isBranchAdmin() && $event->organization_id !== null && (int) $event->organization_id !== (int) $user->current_organization_id) {
                abort(403, 'Tiada kebenaran untuk program ini.');
            }

            return [
                'action_type' => 'event',
                'action_id' => $event->id,
                'action_label' => $label !== '' ? $label : 'Lihat Program',
                'action_url' => route('events.show', $event->slug),
            ];
        }

        $product = Product::find($id);

        if ($product === null) {
            throw ValidationException::withMessages(['action_id' => 'Produk tidak dijumpai.']);
        }

        if ($user->isBranchAdmin()) {
            abort(403, 'Admin cawangan tidak boleh memautkan produk.');
        }

        if (! $isSuperadmin && $product->organisasi_id !== null && (int) $product->organisasi_id !== (int) $user->current_organization_id) {
            abort(403, 'Tiada kebenaran untuk produk ini.');
        }

        return [
            'action_type' => 'product',
            'action_id' => $product->id,
            'action_label' => $label !== '' ? $label : 'Lihat Produk',
            'action_url' => route('products.show', $product->id),
        ];
    }

    /**
     * Log peristiwa untuk satu siaran (JSON). Dimuat secara berasingan supaya
     * halaman sejarah tidak memuat semua log per-penerima sekali gus.
     */
    public function logs(BroadcastMessage $broadcast): JsonResponse
    {
        $user = request()->user();

        if ($user?->isBranchAdmin() && ! $user->canManageBranch($broadcast->branch_id)) {
            abort(403);
        }

        $logs = $broadcast->logs()
            ->take(200)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'event' => $log->event,
                'channel' => $log->channel,
                'user_id' => $log->user_id,
                'message' => $log->message,
                'created_at' => $log->created_at?->toDateTimeString(),
                'created_human' => $log->created_at?->locale('ms')->diffForHumans(),
            ])
            ->values();

        return response()->json(['logs' => $logs]);
    }

    /**
     * Senarai penerima (nama + organisasi) bagi setiap siaran 'specific_members',
     * untuk paparan sejarah. Dikumpul sekali gus (bukan per-mesej) bagi elak N+1.
     */
    private function resolveRecipientPreviews(\Illuminate\Support\Collection $messages): array
    {
        $ids = $messages
            ->where('target_criteria', 'specific_members')
            ->flatMap(fn (BroadcastMessage $message) => array_map('intval', (array) ($message->recipient_ids ?? [])))
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $users = User::withoutGlobalScope(\App\Models\Scopes\OrganizationScope::class)
            ->whereIn('id', $ids)
            ->get(['id', 'name', 'current_organization_id'])
            ->keyBy('id');

        $orgNames = Organization::whereIn('id', $users->pluck('current_organization_id')->filter()->unique())
            ->pluck('name', 'id');

        $previews = [];

        foreach ($messages as $message) {
            if ($message->target_criteria !== 'specific_members') {
                continue;
            }

            $previews[$message->id] = collect($message->recipient_ids ?? [])
                ->map(function ($userId) use ($users, $orgNames) {
                    $user = $users->get((int) $userId);

                    return [
                        'id' => (int) $userId,
                        'name' => $user?->name,
                        'organization_name' => $user?->current_organization_id
                            ? ($orgNames[$user->current_organization_id] ?? null)
                            : null,
                    ];
                })
                ->values()
                ->all();
        }

        return $previews;
    }
}
