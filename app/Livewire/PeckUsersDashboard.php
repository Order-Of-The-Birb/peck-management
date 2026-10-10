<?php

namespace App\Livewire;

use App\Actions\RefreshPeckDB;
use App\Actions\ResolveSquadronRoster;
use App\Actions\ResolveUsernames;
use App\Actions\ServerThunderApi;
use App\Actions\ThunderApi;
use App\Actions\ThunderApiException;
use App\Actions\ThunderApiUnauthorizedException;
use App\Models\PeckAlt;
use App\Models\PeckUser;
use App\Models\PeckUserContext;
use App\Models\ThunderApiToken;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

use function Illuminate\Support\defer;

class PeckUsersDashboard extends Component
{
    use WithPagination;

    public const ROLE_PRIVATE = 'Private';

    public const ROLE_SERGEANT = 'Sergeant';

    public const ROLE_OFFICER = 'Officer';

    public const ROLE_DEPUTY = 'Deputy';

    public const ROLE_COMMANDER = 'Commander';

    /**
     * Squadron roles that grant write access to the member database.
     *
     * @var list<string>
     */
    public const ADMIN_ROLES = [
        self::ROLE_OFFICER,
        self::ROLE_DEPUTY,
        self::ROLE_COMMANDER,
    ];

    public string $search = '';

    public string $sortBy = 'gaijin_id';

    public string $sortDirection = 'asc';

    public string $section = 'members';

    public bool $showMemberModal = false;

    public ?int $selectedMemberGaijinId = null;

    public string $memberTab = 'member';

    public bool $memberEditMode = false;

    public string $ownerSearch = '';

    /**
     * @var array{gaijin_id:?string,discord_id:?string,tz:?string,sqb_part:bool,owner:?string}
     */
    public array $memberForm = [
        'gaijin_id' => null,
        'discord_id' => null,
        'tz' => null,
        'sqb_part' => false,
        'owner' => null,
    ];

    public bool $showAddContextForm = false;

    /**
     * @var array{type:string,from:?string,to:?string,weekdays:list<int>,monthDay:?string,comment:string}
     */
    public array $contextForm = [
        'type' => PeckUserContext::TYPE_MISC,
        'from' => null,
        'to' => null,
        'weekdays' => [],
        'monthDay' => null,
        'comment' => '',
    ];

    public bool $showContextEntryModal = false;

    public ?int $selectedContextEntryId = null;

    public bool $contextEntryEditMode = false;

    public string $contextEntryComment = '';

    public bool $showKickConfirmModal = false;

    public string $kickReason = '';

    public ?string $manageRole = null;

    public string $manageActionError = '';

    public bool $showVerificationRequiredModal = false;

    public bool $thunderPromptDismissed = false;

    public bool $thunderApiError = false;

    public bool $thunderApiErrorDismissed = false;

    /**
     * @var list<array{action_label:string,actor:?string,datetime:?string,details:list<array{label:?string,value:string,glyphs:bool}>}>
     */
    public array $squadronLogs = [];

    public ?string $squadronLogsLastLog = null;

    public bool $squadronLogsLoading = false;

    public bool $squadronLogsFailed = false;

    public string $squadronLogsErrorMessage = '';

    public bool $squadronLogsHasMore = false;

    /**
     * @var list<array{uid:string,nickname:string,timestamp:?string,country:?string,timezone:?string,comment:string}>
     */
    public array $squadronApplicants = [];

    public bool $squadronApplicantsLoading = false;

    public bool $squadronApplicantsFailed = false;

    public string $squadronApplicantsErrorMessage = '';

    public bool $showApplicantModal = false;

    public ?string $selectedApplicantUid = null;

    public bool $showRejectApplicantModal = false;

    public string $rejectApplicantReason = '';

    public string $applicantActionError = '';

    /**
     * @var array<string, array<string, bool|string>>
     */
    protected array $queryString = [
        'search' => ['except' => ''],
        'sortBy' => ['except' => 'gaijin_id'],
        'sortDirection' => ['except' => 'asc'],
    ];

    private ?string $resolvedThunderRole = null;

    private bool $thunderRoleResolved = false;

    private ?string $resolvedEffectiveThunderToken = null;

    private bool $effectiveThunderTokenResolved = false;

    public function mount(string $section = 'members'): void
    {
        if (in_array($section, ['members', 'squadron_logs', 'squadron_applications', 'squadron_management'], true)) {
            $this->section = $section;
        }

        if ($this->section === 'squadron_logs') {
            $this->loadSquadronLogs();
        }

        if ($this->section === 'squadron_applications') {
            $this->loadSquadronApplicants();
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Resolve nicknames for every stored member so the local member database
     * can be searched by username.
     *
     * @return array<int, string>
     */
    protected function resolveAllMemberUsernames(?bool &$unreachable = null): array
    {
        $gaijinIds = PeckUser::query()
            ->pluck('gaijin_id')
            ->map(fn (mixed $gaijinId): int => (int) $gaijinId)
            ->values()
            ->all();

        if ($gaijinIds === []) {
            return [];
        }

        return app(ResolveUsernames::class)->resolve($gaijinIds, $this->effectiveThunderToken(), $unreachable);
    }

    /**
     * Return the Gaijin IDs whose resolved nickname contains the search term.
     *
     * @param  array<int, string>|null  $usernames
     * @return list<int>
     */
    protected function matchingUsernameIds(?array $usernames, string $term): array
    {
        if ($usernames === null || $usernames === []) {
            return [];
        }

        $term = mb_strtolower($term);

        $matches = [];

        foreach ($usernames as $gaijinId => $nickname) {
            if (is_string($nickname) && str_contains(mb_strtolower($nickname), $term)) {
                $matches[] = (int) $gaijinId;
            }
        }

        return $matches;
    }

    public function partialRefreshCooldownMinutes(): int
    {
        return max(1, (int) config('peck.partial_refresh.cooldown_minutes', 5));
    }

    public function partialRefreshCooldownSeconds(): int
    {
        $startedAt = Cache::get((string) config('peck.partial_refresh.lock_key'));

        if (! is_numeric($startedAt)) {
            return 0;
        }

        return max(0, ($this->partialRefreshCooldownMinutes() * 60) - (now()->timestamp - (int) $startedAt));
    }

    public function canPartialRefresh(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->canWrite();
    }

    public function requestPartialRefresh(): void
    {
        abort_unless($this->canPartialRefresh(), 403);

        $lockKey = (string) config('peck.partial_refresh.lock_key');
        $resultKey = (string) config('peck.partial_refresh.result_key');
        $cooldownMinutes = $this->partialRefreshCooldownMinutes();
        $now = now();

        if (! Cache::add($lockKey, $now->timestamp, $now->copy()->addMinutes($cooldownMinutes))) {
            return;
        }

        Cache::forget($resultKey);

        $this->dispatch('partial-refresh-cooldown-started', ['seconds' => $cooldownMinutes * 60]);

        defer(function () use ($resultKey, $cooldownMinutes): void {
            try {
                $stats = app(RefreshPeckDB::class)->handle();

                Cache::put($resultKey, [
                    'status' => 'success',
                    'message' => __('Refresh completed. :created users created.', [
                        'created' => $stats['users_created'],
                    ]),
                ], now()->addMinutes($cooldownMinutes));

                Log::info('Manual partial PECK database refresh completed.', $stats);
            } catch (Throwable $throwable) {
                Cache::put($resultKey, [
                    'status' => 'error',
                    'message' => $throwable->getMessage(),
                ], now()->addMinutes($cooldownMinutes));

                Log::error('Manual partial PECK database refresh failed.', [
                    'message' => $throwable->getMessage(),
                ]);
            }
        }, 'peck-partial-refresh')->always();
    }

    public function sort(string $column): void
    {
        if (! $this->isSortableColumn($column)) {
            return;
        }

        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function isSortedBy(string $column): bool
    {
        return $this->sortBy === $column;
    }

    /**
     * @return list<string>
     */
    public function sortableColumns(): array
    {
        return [
            'gaijin_id',
            'discord_id',
            'username',
            'status',
        ];
    }

    protected function isSortableColumn(string $column): bool
    {
        return in_array($column, $this->sortableColumns(), true);
    }

    public function isMembersSection(): bool
    {
        return $this->section === 'members';
    }

    public function isSquadronLogsSection(): bool
    {
        return $this->section === 'squadron_logs';
    }

    public function isSquadronApplicationsSection(): bool
    {
        return $this->section === 'squadron_applications';
    }

    public function isSquadronManagementSection(): bool
    {
        return $this->section === 'squadron_management';
    }

    public function isSquadronSection(): bool
    {
        return in_array($this->section, ['squadron_logs', 'squadron_applications', 'squadron_management'], true);
    }

    /**
     * @return list<string>
     */
    public function contextTypes(): array
    {
        return PeckUserContext::TYPES;
    }

    /**
     * @return list<string>
     */
    public function assignableRoles(): array
    {
        return [
            self::ROLE_PRIVATE,
            self::ROLE_SERGEANT,
            self::ROLE_OFFICER,
            self::ROLE_DEPUTY,
            self::ROLE_COMMANDER,
        ];
    }

    public function thunderRole(): ?string
    {
        if ($this->thunderRoleResolved) {
            return $this->resolvedThunderRole;
        }

        $this->thunderRoleResolved = true;
        $this->resolvedThunderRole = $this->resolveThunderRole();

        return $this->resolvedThunderRole;
    }

    protected function resolveThunderRole(): ?string
    {
        $token = $this->effectiveThunderToken();

        if ($token === null) {
            return null;
        }

        try {
            $self = app(ThunderApi::class)->getSelf($token);
        } catch (Throwable) {
            return null;
        }

        $role = $self['squadron']['user']['role']['name'] ?? null;

        return is_string($role) && $role !== '' ? $role : null;
    }

    protected function effectiveThunderToken(): ?string
    {
        if ($this->effectiveThunderTokenResolved) {
            return $this->resolvedEffectiveThunderToken;
        }

        $this->effectiveThunderTokenResolved = true;
        $this->resolvedEffectiveThunderToken = $this->resolveEffectiveThunderToken();

        return $this->resolvedEffectiveThunderToken;
    }

    protected function resolveEffectiveThunderToken(): ?string
    {
        $server = app(ServerThunderApi::class);

        if ($server->isConfigured()) {
            try {
                return $server->token();
            } catch (Throwable) {
                // Fall back to the user's own token below.
            }
        }

        $token = ThunderApiToken::query()->find(auth()->id());

        if (! $token instanceof ThunderApiToken || $token->isExpired()) {
            return null;
        }

        $refreshAfterHours = max(1, (int) config('peck.thunderapi_refresh.refresh_after_hours'));

        if (! $token->isRefreshDue($refreshAfterHours)) {
            return $token->token;
        }

        try {
            $expires = app(ThunderApi::class)->refreshToken($token->token);
        } catch (ThunderApiException) {
            return $token->token;
        }

        if ($expires === null) {
            $token->forceFill(['expires_at' => now()->subSecond()->timestamp])->save();

            return null;
        }

        $token->forceFill([
            'expires_at' => $expires,
            'refreshed_at' => now(),
        ])->save();

        return $token->token;
    }

    public function isAdminRole(?string $role): bool
    {
        return in_array($role, self::ADMIN_ROLES, true);
    }

    public function canEdit(): bool
    {
        return $this->isAdminRole($this->thunderRole());
    }

    public function canChangeRoles(): bool
    {
        return in_array($this->thunderRole(), [self::ROLE_DEPUTY, self::ROLE_COMMANDER], true);
    }

    protected function ensureCanEdit(): bool
    {
        if (! $this->canEdit()) {
            abort(403);
        }

        return $this->ensureVerifiedForEdit();
    }

    protected function ensureVerifiedForEdit(): bool
    {
        $user = auth()->user();

        if ($user instanceof User && ! $user->hasVerifiedEmail()) {
            $this->showVerificationRequiredModal = true;

            return false;
        }

        return true;
    }

    public function dismissVerificationRequiredModal(): void
    {
        $this->showVerificationRequiredModal = false;
    }

    public function dismissThunderApiError(): void
    {
        $this->thunderApiError = false;
        $this->thunderApiErrorDismissed = true;
    }

    protected function syncThunderApiErrorState(bool $unreachable): void
    {
        if (! $unreachable) {
            $this->thunderApiErrorDismissed = false;
        }

        $this->thunderApiError = $unreachable && ! $this->thunderApiErrorDismissed;
    }

    public function openMemberModal(int $gaijinId): void
    {
        $peckUser = PeckUser::query()->findOrFail($gaijinId);

        $this->selectedMemberGaijinId = $peckUser->gaijin_id;
        $this->memberTab = 'member';
        $this->memberEditMode = false;
        $this->ownerSearch = '';
        $this->showAddContextForm = false;
        $this->contextForm = $this->blankContextForm();
        $this->showContextEntryModal = false;
        $this->selectedContextEntryId = null;
        $this->contextEntryEditMode = false;
        $this->contextEntryComment = '';
        $this->showKickConfirmModal = false;
        $this->kickReason = '';
        $this->manageActionError = '';
        $this->manageRole = null;
        $this->memberForm = $this->memberFormFromUser($peckUser);
        $this->showMemberModal = true;
        $this->resetValidation();
    }

    public function closeMemberModal(): void
    {
        $this->showMemberModal = false;
        $this->selectedMemberGaijinId = null;
        $this->memberTab = 'member';
        $this->memberEditMode = false;
        $this->ownerSearch = '';
        $this->showAddContextForm = false;
        $this->contextForm = $this->blankContextForm();
        $this->showContextEntryModal = false;
        $this->selectedContextEntryId = null;
        $this->contextEntryEditMode = false;
        $this->contextEntryComment = '';
        $this->showKickConfirmModal = false;
        $this->kickReason = '';
        $this->manageActionError = '';
        $this->manageRole = null;
        $this->resetValidation();
    }

    public function selectMemberTab(string $tab): void
    {
        if (! in_array($tab, ['member', 'context', 'manage'], true)) {
            return;
        }

        if ($tab === 'manage' && ! $this->canEdit()) {
            abort(403);
        }

        $this->memberTab = $tab;
    }

    public function enterMemberEditMode(): void
    {
        if (! $this->ensureCanEdit()) {
            return;
        }

        $peckUser = $this->selectedMember();

        if ($peckUser === null) {
            return;
        }

        $this->memberForm = $this->memberFormFromUser($peckUser);
        $this->ownerSearch = '';
        $this->memberEditMode = true;
        $this->resetValidation();
    }

    public function cancelMemberEdit(): void
    {
        if (! $this->ensureCanEdit()) {
            return;
        }

        $peckUser = $this->selectedMember();

        if ($peckUser !== null) {
            $this->memberForm = $this->memberFormFromUser($peckUser);
        }

        $this->ownerSearch = '';
        $this->memberEditMode = false;
        $this->resetValidation();
    }

    public function saveMember(): void
    {
        if (! $this->ensureCanEdit()) {
            return;
        }

        $peckUser = $this->selectedMember();

        if ($peckUser === null) {
            $this->addError('selectedMemberGaijinId', __('The selected member no longer exists.'));
            $this->closeMemberModal();

            return;
        }

        $validated = $this->validate($this->memberRules());
        $form = $validated['memberForm'];

        $ownerGaijinId = $this->nullableInteger($form['owner']);

        if ($ownerGaijinId !== null && $ownerGaijinId === $peckUser->gaijin_id) {
            $this->addError('memberForm.owner', __('A member cannot own themselves.'));

            return;
        }

        DB::transaction(function () use ($peckUser, $form, $ownerGaijinId): void {
            $peckUser->fill([
                'discord_id' => $this->nullableInteger($form['discord_id']),
                'tz' => $this->nullableInteger($form['tz']),
                'sqb_part' => $form['sqb_part'],
            ]);
            $peckUser->save();

            $this->syncMemberOwner($peckUser->gaijin_id, $ownerGaijinId);
        });

        $this->memberEditMode = false;
        $this->dispatch('peck-member-saved');
        $this->memberForm = $this->memberFormFromUser($peckUser->fresh());
    }

    protected function syncMemberOwner(int $gaijinId, ?int $ownerId): void
    {
        if ($ownerId === null) {
            PeckAlt::query()->where('alt_id', $gaijinId)->delete();

            return;
        }

        if ($ownerId === $gaijinId) {
            return;
        }

        PeckAlt::query()->where('owner_id', $gaijinId)->delete();

        PeckAlt::query()->updateOrCreate(
            ['alt_id' => $gaijinId],
            ['owner_id' => $ownerId],
        );
    }

    /**
     * @return array{gaijin_id:?string,discord_id:?string,tz:?string,sqb_part:bool,owner:?string}
     */
    protected function memberFormFromUser(PeckUser $peckUser): array
    {
        return [
            'gaijin_id' => $this->nullableString($peckUser->gaijin_id),
            'discord_id' => $this->nullableString($peckUser->discord_id),
            'tz' => $this->nullableString($peckUser->tz),
            'sqb_part' => (bool) $peckUser->sqb_part,
            'owner' => $this->nullableString($this->memberOwnerOf($peckUser->gaijin_id)),
        ];
    }

    protected function memberOwnerOf(int $gaijinId): ?int
    {
        $ownerId = PeckAlt::query()->where('alt_id', $gaijinId)->value('owner_id');

        return is_numeric($ownerId) ? (int) $ownerId : null;
    }

    protected function selectedMember(): ?PeckUser
    {
        if ($this->selectedMemberGaijinId === null) {
            return null;
        }

        return PeckUser::query()->find($this->selectedMemberGaijinId);
    }

    public function openAddContextForm(): void
    {
        if (! $this->ensureCanEdit()) {
            return;
        }

        if ($this->selectedMemberGaijinId === null) {
            $this->addError('selectedMemberGaijinId', __('Select a member before adding context.'));

            return;
        }

        $this->contextForm = $this->blankContextForm();
        $this->showAddContextForm = true;
        $this->resetValidation();
    }

    public function closeAddContextForm(): void
    {
        if (! $this->ensureCanEdit()) {
            return;
        }

        $this->showAddContextForm = false;
        $this->contextForm = $this->blankContextForm();
        $this->resetValidation();
    }

    public function addContext(): void
    {
        if (! $this->ensureCanEdit()) {
            return;
        }

        if ($this->selectedMemberGaijinId === null) {
            $this->addError('selectedMemberGaijinId', __('Select a member before adding context.'));

            return;
        }

        $validated = $this->validate($this->contextRules());
        $contextForm = $validated['contextForm'];

        if ($contextForm['type'] === PeckUserContext::TYPE_RECURRING_ABSENCE) {
            $hasWeekdays = $contextForm['weekdays'] !== [];
            $hasMonthDay = filled($contextForm['monthDay']);

            if (! $hasWeekdays && ! $hasMonthDay) {
                $this->addError('contextForm.weekdays', __('A recurring absence requires weekdays or a month day.'));

                return;
            }
        }

        DB::transaction(function () use ($contextForm): void {
            foreach ($this->contextPayloads($contextForm) as $payload) {
                PeckUserContext::query()->create([
                    'user_id' => $this->selectedMemberGaijinId,
                    'context_id' => PeckUserContext::lowestAvailableContextId((int) $this->selectedMemberGaijinId),
                    ...$payload,
                ]);
            }
        });

        $this->dispatch('peck-context-added');
        $this->showAddContextForm = false;
        $this->contextForm = $this->blankContextForm();
        $this->resetValidation();
    }

    public function removeContext(int $contextId): void
    {
        if (! $this->ensureCanEdit()) {
            return;
        }

        if ($this->selectedMemberGaijinId === null) {
            return;
        }

        PeckUserContext::query()
            ->where('user_id', $this->selectedMemberGaijinId)
            ->where('context_id', $contextId)
            ->delete();
    }

    public function openContextEntryModal(int $contextId): void
    {
        $entry = $this->contextEntry($contextId);

        if ($entry === null || $entry->type !== PeckUserContext::TYPE_MISC) {
            return;
        }

        $this->selectedContextEntryId = $entry->context_id;
        $this->contextEntryComment = (string) $entry->comment;
        $this->contextEntryEditMode = false;
        $this->showContextEntryModal = true;
    }

    public function closeContextEntryModal(): void
    {
        $this->showContextEntryModal = false;
        $this->selectedContextEntryId = null;
        $this->contextEntryEditMode = false;
        $this->contextEntryComment = '';
    }

    public function enterContextEntryEditMode(): void
    {
        if (! $this->ensureCanEdit()) {
            return;
        }

        $entry = $this->selectedContextEntry();

        if ($entry !== null) {
            $this->contextEntryComment = (string) $entry->comment;
        }

        $this->contextEntryEditMode = true;
    }

    public function cancelContextEntryEdit(): void
    {
        if (! $this->ensureCanEdit()) {
            return;
        }

        $entry = $this->selectedContextEntry();

        if ($entry !== null) {
            $this->contextEntryComment = (string) $entry->comment;
        }

        $this->contextEntryEditMode = false;
    }

    public function saveContextEntry(): void
    {
        if (! $this->ensureCanEdit()) {
            return;
        }

        $entry = $this->selectedContextEntry();

        if ($entry === null) {
            $this->closeContextEntryModal();

            return;
        }

        $validated = $this->validate([
            'contextEntryComment' => ['required', 'string', 'max:1000'],
        ]);

        $entry->comment = $validated['contextEntryComment'];
        $entry->save();

        $this->dispatch('peck-context-updated');
        $this->contextEntryEditMode = false;
        $this->contextEntryComment = (string) $entry->comment;
    }

    protected function contextEntry(int $contextId): ?PeckUserContext
    {
        if ($this->selectedMemberGaijinId === null) {
            return null;
        }

        return PeckUserContext::query()
            ->where('user_id', $this->selectedMemberGaijinId)
            ->where('context_id', $contextId)
            ->first();
    }

    protected function selectedContextEntry(): ?PeckUserContext
    {
        if ($this->selectedMemberGaijinId === null || $this->selectedContextEntryId === null) {
            return null;
        }

        return PeckUserContext::query()
            ->where('user_id', $this->selectedMemberGaijinId)
            ->where('context_id', $this->selectedContextEntryId)
            ->first();
    }

    public function contextEntryTypeLabel(PeckUserContext $context): string
    {
        return match ($context->type) {
            PeckUserContext::TYPE_MISC => 'misc',
            PeckUserContext::TYPE_ONCE_ABSENCE, PeckUserContext::TYPE_RECURRING_ABSENCE => 'absence',
            default => $context->type,
        };
    }

    public function contextEntrySummary(PeckUserContext $context): string
    {
        if ($context->type === PeckUserContext::TYPE_MISC) {
            return Str::limit((string) $context->comment, 25);
        }

        if ($context->type === PeckUserContext::TYPE_ONCE_ABSENCE) {
            return ($context->from_date?->format('Y.m.d') ?? '—').' - '.($context->to_date?->format('Y.m.d') ?? '—');
        }

        if (is_array($context->weekdays)) {
            $weekdayNames = collect($context->weekdays)
                ->map(fn (mixed $weekday): string => $this->weekdayName((int) $weekday))
                ->implode(', ');

            return __('every :weekdays', ['weekdays' => $weekdayNames]);
        }

        return __('every month on the :day', [
            'day' => $this->ordinal((int) $context->month_day),
        ]);
    }

    public function openKickConfirmModal(): void
    {
        if (! $this->ensureCanEdit()) {
            return;
        }

        if ($this->selectedMemberGaijinId === null) {
            return;
        }

        $this->kickReason = '';
        $this->manageActionError = '';
        $this->showKickConfirmModal = true;
    }

    public function cancelKick(): void
    {
        $this->showKickConfirmModal = false;
        $this->kickReason = '';
    }

    public function kickMember(): void
    {
        if (! $this->ensureCanEdit()) {
            return;
        }

        $gaijinId = $this->selectedMemberGaijinId;

        if ($gaijinId === null) {
            return;
        }

        $token = $this->effectiveThunderToken();

        if ($token === null) {
            $this->manageActionError = __('Your ThunderAPI token is no longer valid. Please reconnect your account.');

            return;
        }

        $this->manageActionError = '';

        try {
            app(ThunderApi::class)->kickMember($token, (string) $gaijinId, $this->kickReason);
        } catch (ThunderApiUnauthorizedException $exception) {
            $this->manageActionError = $exception->getMessage();

            return;
        } catch (ThunderApiException $exception) {
            $this->manageActionError = $exception->getMessage();

            return;
        } catch (Throwable $throwable) {
            report($throwable);

            $this->manageActionError = __('ThunderAPI could not be reached.');

            return;
        }

        $this->showKickConfirmModal = false;
        $this->kickReason = '';
        $this->manageActionError = '';
        $this->dispatch('peck-member-kicked');
    }

    public function changeMemberRole(): void
    {
        if (! $this->ensureCanEdit()) {
            return;
        }

        if (! $this->canChangeRoles()) {
            abort(403);
        }

        $gaijinId = $this->selectedMemberGaijinId;
        $role = $this->manageRole;

        if ($gaijinId === null || ! in_array($role, $this->assignableRoles(), true)) {
            return;
        }

        $token = $this->effectiveThunderToken();

        if ($token === null) {
            $this->manageActionError = __('Your ThunderAPI token is no longer valid. Please reconnect your account.');

            return;
        }

        $this->manageActionError = '';

        try {
            app(ThunderApi::class)->changeMemberRole($token, (string) $gaijinId, $role);
        } catch (ThunderApiUnauthorizedException $exception) {
            $this->manageActionError = $exception->getMessage();

            return;
        } catch (ThunderApiException $exception) {
            $this->manageActionError = $exception->getMessage();

            return;
        } catch (Throwable $throwable) {
            report($throwable);

            $this->manageActionError = __('ThunderAPI could not be reached.');

            return;
        }

        $this->manageRole = null;
        $this->manageActionError = '';
        $this->dispatch('peck-member-role-changed');
    }

    public function squadronIdConfigured(): bool
    {
        return filled((string) config('peck.squadron_id'));
    }

    public function thunderLoggedIn(): bool
    {
        $token = ThunderApiToken::query()->find(auth()->id());

        return $token instanceof ThunderApiToken && ! $token->isExpired();
    }

    /**
     * Returns the reason the Squadron pages are blocked, or null when accessible.
     */
    public function squadronBlockReason(): ?string
    {
        if (! $this->isSquadronSection()) {
            return null;
        }

        if (! $this->squadronIdConfigured()) {
            return 'squadron_id';
        }

        if (! $this->thunderLoggedIn()) {
            return $this->thunderPromptDismissed ? null : 'thunder';
        }

        if ($this->section === 'squadron_management' && ! $this->squadronManagementAuthorized()) {
            return 'clearance';
        }

        return null;
    }

    public function dismissThunderPrompt(): void
    {
        $this->thunderPromptDismissed = true;
    }

    public function squadronManagementAuthorized(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->canManageSquadron();
    }

    public function loadMoreSquadronLogs(): void
    {
        $this->loadSquadronLogs($this->squadronLogsLastLog);
    }

    protected function resolveSquadronToken(): ?ThunderApiToken
    {
        $token = ThunderApiToken::query()->find(auth()->id());

        if (! $token instanceof ThunderApiToken || $token->isExpired()) {
            return null;
        }

        $refreshAfterHours = max(1, (int) config('peck.thunderapi_refresh.refresh_after_hours'));

        if (! $token->isRefreshDue($refreshAfterHours)) {
            return $token;
        }

        try {
            $expires = app(ThunderApi::class)->refreshToken($token->token);
        } catch (ThunderApiException $exception) {
            return $token;
        }

        if ($expires === null) {
            $token->forceFill(['expires_at' => now()->subSecond()->timestamp])->save();

            return null;
        }

        $token->forceFill([
            'expires_at' => $expires,
            'refreshed_at' => now(),
        ])->save();

        return $token;
    }

    public function loadSquadronLogs(?string $fromEntry = null): void
    {
        if ($this->squadronBlockReason() !== null || $this->thunderPromptDismissed) {
            return;
        }

        $clanId = (string) config('peck.squadron_id');

        $token = $this->resolveSquadronToken();

        if ($token === null || $clanId === '') {
            return;
        }

        $this->squadronLogsLoading = true;
        $this->squadronLogsFailed = false;
        $this->squadronLogsErrorMessage = '';

        try {
            $result = app(ThunderApi::class)->getClanLogs($token->token, $clanId, $fromEntry);
        } catch (ThunderApiUnauthorizedException $exception) {
            $token->forceFill(['expires_at' => now()->subSecond()->timestamp])->save();

            $this->squadronLogsLoading = false;
            $this->squadronLogsFailed = true;
            $this->squadronLogsErrorMessage = $exception->getMessage();

            return;
        } catch (ThunderApiException $exception) {
            $this->squadronLogsLoading = false;
            $this->squadronLogsFailed = true;
            $this->squadronLogsErrorMessage = $exception->getMessage();

            return;
        } catch (Throwable $throwable) {
            report($throwable);

            $this->squadronLogsLoading = false;
            $this->squadronLogsFailed = true;
            $this->squadronLogsErrorMessage = __('ThunderAPI could not be reached.');

            return;
        }

        $entries = array_map(fn (array $entry): array => $this->normalizeSquadronLog($entry), $result['logs']);

        $this->squadronLogs = array_merge($this->squadronLogs, $entries);
        $this->squadronLogsLastLog = $result['lastLog'] !== '' ? $result['lastLog'] : null;
        $this->squadronLogsHasMore = count($result['logs']) > 0;
        $this->squadronLogsLoading = false;
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array{action_label:string,actor:?string,datetime:?string,details:list<array{label:?string,value:string,glyphs:bool}>}
     */
    protected function normalizeSquadronLog(array $entry): array
    {
        $action = $entry['action'] ?? null;
        $actionValue = is_array($action) && is_numeric($action['value'] ?? null) ? (int) $action['value'] : null;

        $admin = $entry['admin'] ?? null;
        $adminNickname = is_array($admin) ? (string) ($admin['nickname'] ?? '') : '';
        $adminId = is_array($admin) ? ($admin['_id'] ?? null) : null;

        $affected = $entry['affected'] ?? null;
        $affectedNickname = is_array($affected) ? (string) ($affected['nickname'] ?? '') : '';
        $affectedId = is_array($affected) ? ($affected['_id'] ?? null) : null;

        $roleChange = $entry['roleChange'] ?? null;
        $roleOld = is_array($roleChange) ? ($roleChange['old'] ?? null) : null;
        $roleNew = is_array($roleChange) ? ($roleChange['new'] ?? null) : null;

        $comment = $entry['comment'] ?? null;
        $info = $entry['info'] ?? null;

        $timestamp = $entry['timestamp'] ?? null;

        $actor = $adminNickname !== ''
            ? $this->formatSquadronLogUser($adminNickname, $adminId)
            : null;

        $affectedUser = $affectedNickname !== ''
            ? $this->formatSquadronLogUser($affectedNickname, $affectedId)
            : __('Unknown user');

        $isKick = $adminNickname !== ''
            && $affectedNickname !== ''
            && ! ($adminId !== null && $affectedId !== null && (string) $adminId === (string) $affectedId);

        $details = [];

        if ($roleOld !== null || $roleNew !== null) {
            $details[] = [
                'label' => null,
                'value' => __('Role changed from :old to :new', [
                    'old' => $roleOld ?? '—',
                    'new' => $roleNew ?? '—',
                ]),
                'glyphs' => false,
            ];
        }

        if (is_string($comment) && $comment !== '') {
            $details[] = ['label' => null, 'value' => $comment, 'glyphs' => true];
        }

        if ($actionValue === 4) {
            foreach (['tag', 'desc', 'region', 'status'] as $infoKey) {
                $infoValue = $entry[$infoKey] ?? null;

                if (is_string($infoValue) && $infoValue !== '') {
                    $details[] = [
                        'label' => ucfirst($infoKey),
                        'value' => $infoValue,
                        'glyphs' => in_array($infoKey, ['tag', 'desc'], true),
                    ];
                }
            }
        }

        if ($actionValue === 5 && is_array($info)) {
            $infoParts = [];

            foreach (['name', 'tag', 'slogan'] as $infoKey) {
                $infoValue = $info[$infoKey] ?? null;

                if (is_string($infoValue) && $infoValue !== '') {
                    $infoParts[] = $infoValue;
                }
            }

            if ($infoParts !== []) {
                $details[] = ['label' => null, 'value' => implode(' — ', $infoParts), 'glyphs' => true];
            }
        }

        return [
            'action_label' => $this->squadronLogActionLabel($actionValue, $affectedUser, $isKick),
            'actor' => $actor,
            'datetime' => is_numeric($timestamp) ? Carbon::createFromTimestamp((int) $timestamp)->format('Y-m-d H:i') : null,
            'details' => $details,
        ];
    }

    public function squadronLogActionLabel(?int $value, string $user, bool $isKick = false): string
    {
        return match ($value) {
            0 => $isKick ? __(':user was kicked', ['user' => $user]) : __(':user left', ['user' => $user]),
            1 => __(':user\'s application was accepted', ['user' => $user]),
            2 => __(':user\'s role was changed', ['user' => $user]),
            3 => __(':user\'s application was rejected', ['user' => $user]),
            4 => __('Squadron information changed'),
            5 => __('Squadron created'),
            default => __('Unknown action'),
        };
    }

    protected function formatSquadronLogUser(string $nickname, mixed $gaijinId): string
    {
        return is_numeric($gaijinId)
            ? $nickname.' (#'.$gaijinId.')'
            : $nickname;
    }

    public function loadSquadronApplicants(): void
    {
        if ($this->squadronBlockReason() !== null || $this->thunderPromptDismissed) {
            return;
        }

        $clanId = (string) config('peck.squadron_id');

        $token = $this->resolveSquadronToken();

        if ($token === null || $clanId === '') {
            return;
        }

        $this->squadronApplicantsLoading = true;
        $this->squadronApplicantsFailed = false;
        $this->squadronApplicantsErrorMessage = '';

        try {
            $applicants = app(ThunderApi::class)->getClanApplicants($token->token, $clanId);
        } catch (ThunderApiUnauthorizedException $exception) {
            $token->forceFill(['expires_at' => now()->subSecond()->timestamp])->save();

            $this->squadronApplicantsLoading = false;
            $this->squadronApplicantsFailed = true;
            $this->squadronApplicantsErrorMessage = $exception->getMessage();

            return;
        } catch (ThunderApiException $exception) {
            $this->squadronApplicantsLoading = false;
            $this->squadronApplicantsFailed = true;
            $this->squadronApplicantsErrorMessage = $exception->getMessage();

            return;
        } catch (Throwable $throwable) {
            report($throwable);

            $this->squadronApplicantsLoading = false;
            $this->squadronApplicantsFailed = true;
            $this->squadronApplicantsErrorMessage = __('ThunderAPI could not be reached.');

            return;
        }

        $this->squadronApplicants = array_map(fn (array $applicant): array => $this->normalizeSquadronApplicant($applicant), $applicants);
        $this->squadronApplicantsLoading = false;
    }

    /**
     * @param  array<string, mixed>  $applicant
     * @return array{uid:string,nickname:string,timestamp:?string,country:?string,timezone:?string,comment:string}
     */
    protected function normalizeSquadronApplicant(array $applicant): array
    {
        $uid = $applicant['uid'] ?? null;
        $geodata = $applicant['geodata'] ?? null;
        $country = is_array($geodata) ? ($geodata['country'] ?? null) : null;
        $timezone = is_array($geodata) ? ($geodata['timezone'] ?? null) : null;
        $timestamp = $applicant['timestamp'] ?? null;

        return [
            'uid' => is_numeric($uid) ? (string) $uid : '',
            'nickname' => (string) ($applicant['nickname'] ?? ''),
            'timestamp' => is_numeric($timestamp) ? Carbon::createFromTimestamp((int) $timestamp)->format('Y-m-d H:i') : null,
            'country' => is_string($country) && $country !== '' ? $country : null,
            'timezone' => is_int($timezone) ? $this->formatSquadronTimezone($timezone) : null,
            'comment' => (string) ($applicant['comment'] ?? ''),
        ];
    }

    protected function formatSquadronTimezone(int $timezone): string
    {
        return 'UTC'.($timezone >= 0 ? '+' : '').$timezone;
    }

    /**
     * @return array{uid:string,nickname:string,timestamp:?string,country:?string,timezone:?string,comment:string}|null
     */
    public function selectedApplicant(): ?array
    {
        if ($this->selectedApplicantUid === null) {
            return null;
        }

        foreach ($this->squadronApplicants as $applicant) {
            if ($applicant['uid'] === $this->selectedApplicantUid) {
                return $applicant;
            }
        }

        return null;
    }

    public function openApplicantModal(string $uid): void
    {
        $this->selectedApplicantUid = $uid;
        $this->applicantActionError = '';
        $this->showApplicantModal = true;
    }

    public function closeApplicantModal(): void
    {
        $this->showApplicantModal = false;
        $this->selectedApplicantUid = null;
        $this->showRejectApplicantModal = false;
        $this->rejectApplicantReason = '';
        $this->applicantActionError = '';
    }

    public function openRejectApplicantModal(): void
    {
        if (! $this->ensureVerifiedForEdit()) {
            return;
        }

        $this->rejectApplicantReason = '';
        $this->applicantActionError = '';
        $this->showRejectApplicantModal = true;
    }

    public function cancelRejectApplicant(): void
    {
        $this->showRejectApplicantModal = false;
        $this->rejectApplicantReason = '';
        $this->applicantActionError = '';
    }

    public function acceptApplicant(): void
    {
        $this->performApplicantAction('accept');
    }

    public function confirmRejectApplicant(): void
    {
        $this->performApplicantAction('reject', $this->rejectApplicantReason);
    }

    public function performApplicantAction(string $action, string $message = ''): void
    {
        if (! $this->ensureVerifiedForEdit()) {
            return;
        }

        $uid = $this->selectedApplicantUid;

        if ($uid === null || ! in_array($action, ['accept', 'reject'], true)) {
            return;
        }

        $token = $this->resolveSquadronToken();

        if ($token === null) {
            $this->applicantActionError = __('Your ThunderAPI token is no longer valid. Please reconnect your account.');

            return;
        }

        $this->applicantActionError = '';

        try {
            if ($action === 'accept') {
                app(ThunderApi::class)->acceptApplicant($token->token, $uid);
            } else {
                app(ThunderApi::class)->rejectApplicant($token->token, $uid, $message);
            }
        } catch (ThunderApiUnauthorizedException $exception) {
            $token->forceFill(['expires_at' => now()->subSecond()->timestamp])->save();

            $this->applicantActionError = $exception->getMessage();

            return;
        } catch (ThunderApiException $exception) {
            $this->applicantActionError = $exception->getMessage();

            return;
        } catch (Throwable $throwable) {
            report($throwable);

            $this->applicantActionError = __('ThunderAPI could not be reached.');

            return;
        }

        $this->showRejectApplicantModal = false;
        $this->rejectApplicantReason = '';
        $this->selectedApplicantUid = null;
        $this->showApplicantModal = false;
        $this->loadSquadronApplicants();
    }

    /**
     * @return array{type:string,from:?string,to:?string,weekdays:list<int>,monthDay:?string,comment:string}
     */
    protected function blankContextForm(): array
    {
        return [
            'type' => PeckUserContext::TYPE_MISC,
            'from' => null,
            'to' => null,
            'weekdays' => [],
            'monthDay' => null,
            'comment' => '',
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function contextRules(): array
    {
        return [
            'contextForm.type' => [
                'required',
                'string',
                Rule::in($this->contextTypes()),
            ],
            'contextForm.from' => [
                'required_if:contextForm.type,'.PeckUserContext::TYPE_ONCE_ABSENCE,
                'nullable',
                'date_format:Y-m-d',
            ],
            'contextForm.to' => [
                'required_if:contextForm.type,'.PeckUserContext::TYPE_ONCE_ABSENCE,
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:contextForm.from',
            ],
            'contextForm.weekdays' => [
                'array',
            ],
            'contextForm.weekdays.*' => [
                'integer',
                'between:0,6',
                'distinct',
            ],
            'contextForm.monthDay' => [
                'nullable',
                'integer',
                'between:1,31',
            ],
            'contextForm.comment' => [
                'required_if:contextForm.type,'.PeckUserContext::TYPE_MISC,
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * @param  array{type:string,from:?string,to:?string,weekdays:list<int>,monthDay:?string,comment:string}  $contextForm
     * @return list<array<string, mixed>>
     */
    protected function contextPayloads(array $contextForm): array
    {
        if ($contextForm['type'] === PeckUserContext::TYPE_ONCE_ABSENCE) {
            return [[
                'type' => PeckUserContext::TYPE_ONCE_ABSENCE,
                'from_date' => $contextForm['from'],
                'to_date' => $contextForm['to'],
                'weekdays' => null,
                'month_day' => null,
                'comment' => null,
            ]];
        }

        if ($contextForm['type'] === PeckUserContext::TYPE_MISC) {
            return [[
                'type' => PeckUserContext::TYPE_MISC,
                'from_date' => null,
                'to_date' => null,
                'weekdays' => null,
                'month_day' => null,
                'comment' => $contextForm['comment'],
            ]];
        }

        $payloads = [];

        if ($contextForm['weekdays'] !== []) {
            $payloads[] = [
                'type' => PeckUserContext::TYPE_RECURRING_ABSENCE,
                'from_date' => null,
                'to_date' => null,
                'weekdays' => collect($contextForm['weekdays'])
                    ->map(fn (mixed $weekday): int => (int) $weekday)
                    ->unique()
                    ->sort()
                    ->values()
                    ->all(),
                'month_day' => null,
                'comment' => null,
            ];
        }

        if (filled($contextForm['monthDay'])) {
            $payloads[] = [
                'type' => PeckUserContext::TYPE_RECURRING_ABSENCE,
                'from_date' => null,
                'to_date' => null,
                'weekdays' => null,
                'month_day' => (int) $contextForm['monthDay'],
                'comment' => null,
            ];
        }

        return $payloads;
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function memberRules(): array
    {
        return [
            'memberForm.gaijin_id' => [
                'required',
                'integer',
            ],
            'memberForm.discord_id' => [
                'nullable',
                'integer',
            ],
            'memberForm.tz' => [
                'nullable',
                'integer',
                'between:-11,12',
            ],
            'memberForm.sqb_part' => [
                'boolean',
            ],
            'memberForm.owner' => [
                'nullable',
                'integer',
                Rule::exists('peck_users', 'gaijin_id'),
            ],
        ];
    }

    public function render(): View
    {
        $sortBy = $this->isSortableColumn($this->sortBy) ? $this->sortBy : 'gaijin_id';
        $sortDirection = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        $searchTerm = trim($this->search);
        $unreachable = false;
        $allMemberUsernames = null;

        if ($this->isMembersSection() && $searchTerm !== '' && ! ctype_digit($searchTerm)) {
            $allMemberUsernames = $this->resolveAllMemberUsernames($unreachable);
        }

        $usernameMatches = $this->matchingUsernameIds($allMemberUsernames, $searchTerm);

        $shownUsers = null;

        if ($this->isMembersSection()) {
            $membersQuery = PeckUser::query()
                ->when($searchTerm !== '', function (Builder $query) use ($searchTerm, $usernameMatches): void {
                    $like = '%'.$searchTerm.'%';

                    $query->where(function (Builder $innerQuery) use ($like, $usernameMatches): void {
                        $innerQuery
                            ->where('gaijin_id', 'like', $like)
                            ->orWhere('discord_id', 'like', $like);

                        if ($usernameMatches !== []) {
                            $innerQuery->orWhereIn('gaijin_id', $usernameMatches);
                        }
                    });
                });

            if (in_array($sortBy, ['username', 'status'], true)) {
                $shownUsers = $this->paginateMembersByResolvedColumn($membersQuery, $sortBy, $sortDirection, $unreachable);
            } else {
                $shownUsers = $membersQuery
                    ->orderBy($sortBy, $sortDirection)
                    ->orderBy('gaijin_id')
                    ->paginate(15);
            }
        }

        $selectedMember = null;
        $selectedMemberContexts = collect();
        $selectedContextEntry = null;
        $memberOwnerGaijinId = null;

        if ($this->selectedMemberGaijinId !== null) {
            $selectedMember = PeckUser::query()->find($this->selectedMemberGaijinId);

            if ($selectedMember !== null) {
                $memberOwnerGaijinId = $this->memberOwnerOf($selectedMember->gaijin_id);

                $selectedMemberContexts = PeckUserContext::query()
                    ->where('user_id', $selectedMember->gaijin_id)
                    ->orderBy('context_id')
                    ->get();

                if ($this->selectedContextEntryId !== null) {
                    $selectedContextEntry = $selectedMemberContexts->firstWhere('context_id', $this->selectedContextEntryId);
                }
            }
        }

        $usernameGaijinIds = [];

        foreach ($shownUsers ?? [] as $peckUser) {
            $usernameGaijinIds[] = (int) $peckUser->gaijin_id;
        }

        if ($this->selectedMemberGaijinId !== null) {
            $usernameGaijinIds[] = $this->selectedMemberGaijinId;
        }

        if ($memberOwnerGaijinId !== null) {
            $usernameGaijinIds[] = $memberOwnerGaijinId;
        }

        if ($this->showMemberModal && $this->memberEditMode) {
            foreach (PeckUser::query()->get(['gaijin_id']) as $peckUser) {
                $usernameGaijinIds[] = (int) $peckUser->gaijin_id;
            }
        }

        if ($allMemberUsernames !== null) {
            $usernames = $allMemberUsernames;
        } else {
            $usernames = $usernameGaijinIds === []
                ? []
                : app(ResolveUsernames::class)->resolve($usernameGaijinIds, $this->effectiveThunderToken(), $unreachable);
        }

        $memberStatuses = $this->resolveMemberStatuses($shownUsers, $selectedMember, $unreachable);

        $this->syncThunderApiErrorState($unreachable);

        $ownerOptions = collect();

        if ($this->showMemberModal && $this->memberEditMode && $this->selectedMemberGaijinId !== null) {
            $trimmedOwnerSearch = mb_strtolower(trim($this->ownerSearch));

            $ownerOptions = PeckUser::query()
                ->where('gaijin_id', '!=', $this->selectedMemberGaijinId)
                ->orderBy('gaijin_id')
                ->get(['gaijin_id'])
                ->filter(function (PeckUser $peckUser) use ($trimmedOwnerSearch, $usernames): bool {
                    if ($trimmedOwnerSearch === '') {
                        return true;
                    }

                    if (str_contains((string) $peckUser->gaijin_id, $trimmedOwnerSearch)) {
                        return true;
                    }

                    $nickname = $usernames[$peckUser->gaijin_id] ?? null;

                    return is_string($nickname) && str_contains(mb_strtolower($nickname), $trimmedOwnerSearch);
                })
                ->map(fn (PeckUser $peckUser): array => [
                    'gaijin_id' => $peckUser->gaijin_id,
                    'username' => $usernames[$peckUser->gaijin_id] ?? null,
                ])
                ->values();
        }

        return view('livewire.peck-users-dashboard', [
            'shownUsers' => $shownUsers,
            'selectedMember' => $selectedMember,
            'selectedMemberContexts' => $selectedMemberContexts,
            'selectedContextEntry' => $selectedContextEntry,
            'memberOwnerGaijinId' => $memberOwnerGaijinId,
            'memberUsername' => $usernames[$this->selectedMemberGaijinId] ?? null,
            'memberStatuses' => $memberStatuses,
            'ownerOptions' => $ownerOptions,
            'contextTypes' => $this->contextTypes(),
            'assignableRoles' => $this->assignableRoles(),
            'usernames' => $usernames,
        ]);
    }

    /**
     * Paginate the members list by a value (username or status) that is only
     * known after each member has been resolved through ThunderAPI.
     *
     * @return LengthAwarePaginator<PeckUser>
     */
    protected function paginateMembersByResolvedColumn(Builder $query, string $column, string $direction, bool &$unreachable): LengthAwarePaginator
    {
        $members = $query->get();

        $values = $column === 'status'
            ? $this->resolveStatusesFor($members, $unreachable)
            : $this->resolveUsernamesFor($members, $unreachable);

        $sorted = $members->sort(function (PeckUser $left, PeckUser $right) use ($values, $direction): int {
            $leftValue = mb_strtolower((string) ($values[$left->gaijin_id] ?? ''));
            $rightValue = mb_strtolower((string) ($values[$right->gaijin_id] ?? ''));

            $comparison = $leftValue <=> $rightValue;

            if ($comparison === 0) {
                return $left->gaijin_id <=> $right->gaijin_id;
            }

            return $direction === 'desc' ? -$comparison : $comparison;
        })->values();

        $page = max(1, (int) $this->getPage());
        $perPage = 15;

        return new LengthAwarePaginator(
            $sorted->forPage($page, $perPage)->values(),
            $sorted->count(),
            $perPage,
            $page,
        );
    }

    /**
     * @param  iterable<int, PeckUser>  $members
     * @return list<int>
     */
    protected function memberGaijinIds(iterable $members): array
    {
        $gaijinIds = [];

        foreach ($members as $peckUser) {
            $gaijinIds[] = (int) $peckUser->gaijin_id;
        }

        return array_values(array_unique($gaijinIds));
    }

    /**
     * @param  iterable<int, PeckUser>  $members
     * @return array<int, string>
     */
    protected function resolveUsernamesFor(iterable $members, bool &$unreachable): array
    {
        $gaijinIds = $this->memberGaijinIds($members);

        if ($gaijinIds === []) {
            return [];
        }

        $usernamesUnreachable = false;
        $usernames = app(ResolveUsernames::class)->resolve($gaijinIds, $this->effectiveThunderToken(), $usernamesUnreachable);
        $unreachable = $unreachable || $usernamesUnreachable;

        return $usernames;
    }

    /**
     * @param  iterable<int, PeckUser>  $members
     * @return array<int, ?string>
     */
    protected function resolveStatusesFor(iterable $members, bool &$unreachable): array
    {
        $gaijinIds = $this->memberGaijinIds($members);

        if ($gaijinIds === []) {
            return [];
        }

        $rosterUnreachable = false;
        $resolver = app(ResolveSquadronRoster::class);
        $roster = $resolver->resolve($this->effectiveThunderToken(), null, $rosterUnreachable);
        $unreachable = $unreachable || $rosterUnreachable;

        $statuses = [];

        foreach ($gaijinIds as $gaijinId) {
            $statuses[$gaijinId] = $resolver->statusFor($gaijinId, $roster);
        }

        return $statuses;
    }

    /**
     * @param  LengthAwarePaginator<PeckUser>|null  $shownUsers
     * @return array<int, ?string>
     */
    protected function resolveMemberStatuses(mixed $shownUsers, ?PeckUser $selectedMember, bool &$unreachable): array
    {
        if (! $this->isMembersSection()) {
            return [];
        }

        $members = [];

        foreach ($shownUsers ?? [] as $peckUser) {
            $members[] = $peckUser;
        }

        if ($selectedMember !== null) {
            $members[] = $selectedMember;
        }

        return $this->resolveStatusesFor($members, $unreachable);
    }

    protected function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return (string) $value;
    }

    protected function nullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    protected function weekdayName(int $weekday): string
    {
        return [
            0 => 'Mon',
            1 => 'Tue',
            2 => 'Wed',
            3 => 'Thu',
            4 => 'Fri',
            5 => 'Sat',
            6 => 'Sun',
        ][$weekday] ?? (string) $weekday;
    }

    protected function ordinal(int $number): string
    {
        if (in_array($number % 100, [11, 12, 13], true)) {
            return $number.'th';
        }

        return $number.match ($number % 10) {
            1 => 'st',
            2 => 'nd',
            3 => 'rd',
            default => 'th',
        };
    }
}
